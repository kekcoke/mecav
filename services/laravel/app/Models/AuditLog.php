<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'auditable_type', 'auditable_id', 'user_id', 'tenant_id',
        'event', 'ip_address', 'user_agent',
        'old_values', 'new_values', 'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convenience factory method.
     */
    public static function record(
        Model $auditable,
        int $userId,
        string $event,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
    ): self {
        return self::create([
            'auditable_type' => get_class($auditable),
            'auditable_id'   => $auditable->id,
            'user_id'        => $userId,
            'tenant_id'      => $auditable->tenant_id ?? null,
            'event'          => $event,
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'metadata'       => $metadata,
        ]);
    }
}
