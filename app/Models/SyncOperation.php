<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncOperation extends Model
{
    protected $fillable = [
        'op_id',
        'user_id',
        'device_id',
        'type',
        'client_uuid',
        'payload',
        'status',
        'reject_reason',
        'entity_type',
        'entity_id',
        'public_id',
        'happened_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'happened_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
