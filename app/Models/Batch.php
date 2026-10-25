<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Batch extends Model
{
    use HasClientUuid;
    protected $fillable = [
        'public_id',
        'client_uuid',
        'chain',
        'status',
        'capture_channel',
        'aggregator_id',
        'mill_id',
        'exporter_id',
        'opened_by',
        'closed_at',
        'mill_weight_kg',
        'shipment_ref',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'mill_weight_kg' => 'float',
        ];
    }

    public function aggregator(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'aggregator_id');
    }

    public function mill(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'mill_id');
    }

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'exporter_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(BatchMember::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function custodyEvents(): HasMany
    {
        return $this->hasMany(CustodyEvent::class)->orderBy('happened_at');
    }

    public function compliance(): HasOne
    {
        return $this->hasOne(ComplianceAssessment::class);
    }

    public function farmCount(): int
    {
        return $this->members->pluck('farmer_id')->unique()->count();
    }

    public function totalVolumeKg(): float
    {
        return (float) $this->members->sum('volume_kg');
    }
}
