<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DeviceAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'uuid'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Those credentials were not found.'], 422);
        }

        if (! $user->canUseFieldApp()) {
            return response()->json([
                'message' => 'This phone app is for field officers. Sign in with your officer email.',
            ], 403);
        }

        $issued = DeviceToken::issue($user, $data['device_id'], $data['device_name'] ?? 'Field phone');

        return response()->json([
            'token' => $issued['plain'],
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'region' => $user->operatingRegion(),
                'sees_all_regions' => $user->canAccessAllRegions(),
            ],
            'regions' => $user->canAccessAllRegions()
                ? \App\Support\GhanaRegions::all()
                : array_values(array_filter([$user->operatingRegion()])),
            'aggregators' => Organization::query()
                ->where('type', 'aggregator')
                ->when(
                    ! $user->canAccessAllRegions() && $user->operatingRegion(),
                    fn ($q) => $q->where('region', $user->operatingRegion())
                )
                ->orderBy('name')
                ->get(['public_id', 'name', 'chain', 'district', 'region']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $device = $request->attributes->get('deviceToken');
        if ($device instanceof DeviceToken) {
            $device->delete();
        }

        return response()->json(['ok' => true]);
    }
}
