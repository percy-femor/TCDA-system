<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceAssessment extends Model
{
    protected $fillable = [
        'batch_id',
        'status',
        'gaps',
        'assessed_at',
    ];

    protected function casts(): array
    {
        return [
            'gaps' => 'array',
            'assessed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
}
