<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plot extends Model
{
    use HasClientUuid;
    protected $fillable = [
        'public_id',
        'farmer_id',
        'client_uuid',
        'latitude',
        'longitude',
        'geojson',
        'area_hectares',
        'land_status',
        'mapped_at',
        'mapped_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'area_hectares' => 'float',
            'mapped_at' => 'datetime',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function mappedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by');
    }

    public function hasLocation(): bool
    {
        return ($this->latitude && $this->longitude) || filled($this->geojson);
    }
}
