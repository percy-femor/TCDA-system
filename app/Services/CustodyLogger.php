<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\CustodyEvent;
use App\Models\User;

class CustodyLogger
{
    public function record(Batch $batch, string $eventType, ?User $actor = null, array $payload = []): CustodyEvent
    {
        return $batch->custodyEvents()->create([
            'event_type' => $eventType,
            'payload' => $payload,
            'actor_user_id' => $actor?->id,
            'happened_at' => now(),
        ]);
    }
}
