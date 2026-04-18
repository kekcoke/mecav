<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DiagramSnapshot extends Model
{

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

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($snapshot) {
            if (empty($snapshot->ulid)) {
                $snapshot->ulid = (string) Str::ulid();
            }
            if (empty($snapshot->created_at)) {
                $snapshot->created_at = now();
            }
        });
    }

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
