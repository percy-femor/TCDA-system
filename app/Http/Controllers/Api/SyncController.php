<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(private SyncService $sync) {}

    public function push(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'uuid'],
            'operations' => ['required', 'array', 'max:500'],
            'operations.*.op_id' => ['required', 'uuid'],
            'operations.*.type' => ['required', 'string'],
            'operations.*.client_uuid' => ['nullable', 'uuid'],
            'operations.*.happened_at' => ['nullable', 'date'],
            'operations.*.payload' => ['nullable', 'array'],
        ]);

        $device = $this->device($request, $data['device_id']);
        $result = $this->sync->push($request->user(), $device, $data['operations']);

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'accepted' => $result['accepted'],
            'rejected' => $result['rejected'],
        ]);
    }

    public function pull(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'uuid'],
            'since' => ['nullable', 'date'],
        ]);

        $device = $this->device($request, $data['device_id']);

        return response()->json($this->sync->pull(
            $request->user(),
            $device,
            $data['since'] ?? null,
        ));
    }

    private function device(Request $request, string $deviceId): DeviceToken
    {
        $device = $request->attributes->get('deviceToken');
        if (! $device instanceof DeviceToken || $device->device_id !== $deviceId) {
            abort(response()->json(['message' => 'device_id does not match this token.'], 403));
        }

        return $device;
    }
}
