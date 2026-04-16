<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagramSnapshot extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'diagram_id', 'user_id', 'version', 'label',
        'mermaid_code', 'metadata', 'storage_bytes',
        'is_exported', 'export_path', 'expires_at',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'is_exported' => 'boolean',
        'expires_at'  => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark as exported and record its path (S3 key or local path).
     */
    public function markExported(string $path): self
    {
        $this->update(['is_exported' => true, 'export_path' => $path, 'expires_at' => null]);
        return $this;
    }
}
