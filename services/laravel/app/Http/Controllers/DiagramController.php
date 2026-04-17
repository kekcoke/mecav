<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiagramController extends Controller
{
    /**
     * Diagram list — server-rendered blade view.
     */
    public function index(Request $request)
    {
        $diagrams = Diagram::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->with('latestSnapshot')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('diagrams.index', compact('diagrams'));
    }

    public function create()
    {
        return view('diagrams.create');
    }

    public function show(Diagram $diagram)
    {
        return view('diagrams.editor', compact('diagram'));
    }
}
