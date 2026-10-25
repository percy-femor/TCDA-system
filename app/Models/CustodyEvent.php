<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustodyEvent extends Model
{
    protected $fillable = [
        'batch_id',
        'event_type',
        'payload',
        'actor_user_id',
        'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'happened_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
