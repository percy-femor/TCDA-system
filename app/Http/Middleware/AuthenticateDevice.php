<?php

namespace App\Http\Middleware;

use App\Models\DeviceToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        if (! $plain) {
            return response()->json(['message' => 'Device token missing.'], 401);
        }

        $record = DeviceToken::query()
            ->with('user.organization')
            ->where('token_hash', DeviceToken::hashToken($plain))
            ->first();

        if (! $record?->user) {
            return response()->json(['message' => 'Device token is not valid. Sign in again when you have signal.'], 401);
        }

        $record->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(fn () => $record->user);
        $request->attributes->set('deviceToken', $record);

        return $next($request);
    }
}
