<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Grpc\MultimodalClient;
use App\Models\AuditLog;
use App\Models\Diagram;
use App\Models\DiagramSnapshot;
use App\Services\DiagramExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DiagramController extends Controller
{
    public function __construct(
        private readonly MultimodalClient     $grpc,
        private readonly DiagramExportService $exporter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $diagrams = Diagram::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->with('latestSnapshot')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json($diagrams);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'mermaid_code' => 'required|string',
            'diagram_type' => 'in:flowchart,sequence,class,erd,gantt,mindmap',
            'ai_enabled'   => 'boolean',
            'tags'         => 'nullable|array',
        ]);

        $diagram = Diagram::create([
            ...$validated,
            'user_id'   => Auth::id(),
            'tenant_id' => Auth::user()->tenant_id,
        ]);

        AuditLog::record($diagram, Auth::id(), 'created', newValues: $validated);

        return response()->json($diagram, 201);
    }

    public function show(Diagram $diagram): JsonResponse
    {
        $this->authorize('view', $diagram);
        return response()->json($diagram->load('snapshots', 'user'));
    }

    public function update(Request $request, Diagram $diagram): JsonResponse
    {
        $this->authorize('update', $diagram);

        $validated = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'mermaid_code' => 'sometimes|string',
            'status'       => 'in:draft,published,archived',
            'ai_enabled'   => 'boolean',
            'tags'         => 'nullable|array',
        ]);

        $old = $diagram->only(array_keys($validated));

        if (isset($validated['mermaid_code']) && $validated['mermaid_code'] !== $diagram->mermaid_code) {
            $diagram->saveSnapshot(Auth::id(), $validated['mermaid_code']);
        } else {
            $diagram->update($validated);
        }

        AuditLog::record($diagram, Auth::id(), 'updated', oldValues: $old, newValues: $validated);

        return response()->json($diagram->fresh());
    }

    public function destroy(Diagram $diagram): JsonResponse
    {
        $this->authorize('delete', $diagram);
        AuditLog::record($diagram, Auth::id(), 'deleted');
        $diagram->delete();

        return response()->json(null, 204);
    }

    public function snapshots(Diagram $diagram): JsonResponse
    {
        $this->authorize('view', $diagram);
        return response()->json($diagram->snapshots);
    }

    public function revert(Diagram $diagram, DiagramSnapshot $snapshot): JsonResponse
    {
        $this->authorize('update', $diagram);

        abort_if($snapshot->diagram_id !== $diagram->id, 403, 'Snapshot does not belong to this diagram.');

        $updated = $diagram->revertToSnapshot($snapshot, Auth::id());

        return response()->json($updated->fresh('snapshots'));
    }

    public function export(Request $request, Diagram $diagram): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('view', $diagram);

        $format   = $request->validate(['format' => 'required|in:svg,png,pdf,json'])['format'];
        $snapshot = $request->snapshot_id
            ? $diagram->snapshots()->where('ulid', $request->snapshot_id)->firstOrFail()
            : null;

        return $this->exporter->export($diagram, $format, $snapshot);
    }

    public function aiSuggest(Request $request, Diagram $diagram): JsonResponse
    {
        $this->authorize('update', $diagram);
        abort_unless($diagram->ai_enabled, 403, 'AI is not enabled for this diagram.');

        $validated = $request->validate(['prompt' => 'required|string|max:4000']);

        $sessionId   = Str::uuid()->toString();
        $suggestion  = $this->grpc->analyzeText($diagram->ulid, $sessionId, $validated['prompt']);

        AuditLog::record($diagram, Auth::id(), 'ai_suggest', metadata: ['session_id' => $sessionId]);

        return response()->json([
            'suggestion_id' => $suggestion->getSuggestionId(),
            'mermaid_code'  => $suggestion->getMermaidCode(),
            'explanation'   => $suggestion->getExplanation(),
            'confidence'    => $suggestion->getConfidence(),
            'sources'       => iterator_to_array($suggestion->getSources()),
        ]);
    }
}
