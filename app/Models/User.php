<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'organization_id',
        'region',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isTcda(): bool
    {
        return $this->role === UserRole::Tcda;
    }

    public function operatingRegion(): ?string
    {
        if ($this->isTcda()) {
            return null;
        }

        return $this->region ?: $this->organization?->region;
    }

    public function canAccessAllRegions(): bool
    {
        return $this->isTcda();
    }

    public function canAccessRegion(?string $region): bool
    {
        if ($this->canAccessAllRegions()) {
            return true;
        }

        return $this->operatingRegion() !== null && $this->operatingRegion() === $region;
    }

    public function canHandleFarmer(Farmer $farmer): bool
    {
        return $this->canEnrolFarmers() && $this->canAccessRegion($farmer->region);
    }

    public function canEnrolFarmers(): bool
    {
        return in_array($this->role, [UserRole::Tcda, UserRole::FieldOfficer, UserRole::Aggregator], true);
    }

    public function canManageBatches(): bool
    {
        return in_array($this->role, [UserRole::Tcda, UserRole::Aggregator], true);
    }

    public function canMillIntake(): bool
    {
        return in_array($this->role, [UserRole::Tcda, UserRole::Mill], true);
    }

    public function canExport(): bool
    {
        return in_array($this->role, [UserRole::Tcda, UserRole::Exporter], true);
    }

    public function canUseFieldApp(): bool
    {
        return $this->role === UserRole::FieldOfficer;
    }

    public function homeRoute(): string
    {
        return match ($this->role) {
            UserRole::Tcda => 'dashboard',
            UserRole::FieldOfficer => 'officer.home',
            UserRole::Aggregator => 'buying.home',
            UserRole::Mill => 'mill.index',
            UserRole::Exporter => 'dds.index',
        };
    }
}
