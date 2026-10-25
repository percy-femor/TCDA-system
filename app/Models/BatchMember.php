<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchMember extends Model
{
    use HasClientUuid;
    protected $fillable = [
        'batch_id',
        'farmer_id',
        'plot_id',
        'client_uuid',
        'volume_kg',
        'paper_stub_no',
        'capture_channel',
        'delivered_at',
        'captured_by',
    ];

    protected function casts(): array
    {
        return [
            'volume_kg' => 'float',
            'delivered_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
