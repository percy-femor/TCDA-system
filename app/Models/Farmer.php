<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farmer extends Model
{
    use HasClientUuid;
    protected $fillable = [
        'public_id',
        'name',
        'phone',
        'community',
        'district',
        'region',
        'chain',
        'paper_card_no',
        'consent_at',
        'enrolled_by',
        'aggregator_id',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
        ];
    }

    public function aggregator(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'aggregator_id');
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function plots(): HasMany
    {
        return $this->hasMany(Plot::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function hasGeolocation(): bool
    {
        return $this->plots->contains(function (Plot $plot) {
            return ($plot->latitude && $plot->longitude) || filled($plot->geojson);
        });
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canAccessAllRegions()) {
            return $query;
        }

        $region = $user->operatingRegion();
        if (! $region) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('region', $region);
    }
}
