<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Diagram extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id', 'tenant_id', 'title', 'description',
        'mermaid_code', 'diagram_type', 'status',
        'ai_enabled', 'tags', 'export_formats', 'storage_bytes',
    ];

    protected $casts = [
        'ai_enabled'     => 'boolean',
        'tags'           => 'array',
        'export_formats' => 'array',
        'storage_bytes'  => 'integer',
    ];

    // ── Relations ────────────────────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(DiagramSnapshot::class)->orderByDesc('version');
    }

    public function latestSnapshot(): HasMany
    {
        return $this->snapshots()->limit(1);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'auditable_id')
                    ->where('auditable_type', self::class);
    }

    // ── Business logic ───────────────────────────────────────
    /**
     * Persist current state as a versioned snapshot and update HEAD.
     */
    public function saveSnapshot(int $userId, string $newCode, ?string $label = null): DiagramSnapshot
    {
        $nextVersion = ($this->snapshots()->max('version') ?? 0) + 1;

        $snapshot = DiagramSnapshot::create([
            'diagram_id'    => $this->id,
            'user_id'       => $userId,
            'version'       => $nextVersion,
            'label'         => $label,
            'mermaid_code'  => $newCode,
            'storage_bytes' => strlen($newCode),
            'metadata'      => ['source' => 'manual'],
        ]);

        $this->update([
            'mermaid_code'  => $newCode,
            'storage_bytes' => $this->storage_bytes + strlen($newCode),
        ]);

        // Prune snapshots if storage threshold exceeded
        $this->pruneSnapshotsIfNeeded();

        return $snapshot;
    }

    /**
     * Revert diagram HEAD to a specific snapshot version.
     */
    public function revertToSnapshot(DiagramSnapshot $snapshot, int $userId): self
    {
        $this->saveSnapshot($userId, $snapshot->mermaid_code, "Reverted to v{$snapshot->version}");

        AuditLog::record(
            auditable: $this,
            userId: $userId,
            event: 'reverted',
            metadata: ['to_snapshot_id' => $snapshot->id, 'to_version' => $snapshot->version],
        );

        return $this->fresh();
    }

    /**
     * Purge snapshots exceeding storage/time thresholds, preserving exports.
     * Thresholds are configurable via config/diagrams.php.
     */
    protected function pruneSnapshotsIfNeeded(): void
    {
        $maxBytes = config('diagrams.snapshot_storage_bytes_per_diagram', 10 * 1024 * 1024); // 10 MB
        $maxAge   = config('diagrams.snapshot_max_age_days', 90);

        // Mark old snapshots as expiring (do not hard-delete to allow export first)
        $this->snapshots()
             ->where('is_exported', false)
             ->where('created_at', '<', now()->subDays($maxAge))
             ->update(['expires_at' => now()->addDays(7)]);

        // Hard-prune already-expired ones beyond storage budget
        $totalBytes = $this->snapshots()->sum('storage_bytes');
        if ($totalBytes > $maxBytes) {
            $this->snapshots()
                 ->where('is_exported', false)
                 ->whereNotNull('expires_at')
                 ->where('expires_at', '<=', now())
                 ->oldest('created_at')
                 ->limit(20)
                 ->delete();
        }
    }
}
