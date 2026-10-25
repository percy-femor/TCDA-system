<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'public_id',
        'name',
        'type',
        'chain',
        'region',
        'district',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function farmers(): HasMany
    {
        return $this->hasMany(Farmer::class, 'aggregator_id');
    }
}
