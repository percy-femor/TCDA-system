<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasClientUuid
{
    protected static function bootHasClientUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->client_uuid)) {
                $model->client_uuid = (string) Str::uuid();
            }
        });
    }
}
