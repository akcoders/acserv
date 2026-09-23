<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:http,https', 'max:4000'],
            'keys.p256dh' => ['required', 'string', 'max:1000'],
            'keys.auth' => ['required', 'string', 'max:1000'],
        ]);
        $endpointHash = hash('sha256', $data['endpoint']);
        $subscription = PushSubscription::query()->withTrashed()->firstOrNew([
            'endpoint_hash' => $endpointHash,
        ]);

        $subscription->fill([
            'user_id' => $request->user()->getKey(),
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'last_used_at' => now(),
            'revoked_at' => null,
        ]);
        if ($subscription->trashed()) {
            $subscription->restore();
        } else {
            $subscription->save();
        }

        return response()->json([
            'message' => 'Push notifications enabled.',
            'subscription_id' => $subscription->getKey(),
        ]);
    }

    public function destroy(Request $request, PushSubscription $pushSubscription): JsonResponse
    {
        abort_unless($pushSubscription->user_id === $request->user()->getKey(), 404);
        $pushSubscription->update(['revoked_at' => now()]);

        return response()->json(['message' => 'Push notifications disabled.']);
    }
}
