<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'token_hash',
        'last_used_at',
        'last_pull_at',
        'last_push_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'last_pull_at' => 'datetime',
            'last_push_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * @return array{plain: string, token: self}
     */
    public static function issue(User $user, string $deviceId, ?string $deviceName = null): array
    {
        $plain = Str::random(64);
        $token = static::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ],
            [
                'device_name' => $deviceName,
                'token_hash' => static::hashToken($plain),
                'last_used_at' => now(),
            ]
        );

        return ['plain' => $plain, 'token' => $token];
    }
}
