<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $hasActiveSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('expiry_date', '>=', now())
            ->exists();

        if ($hasActiveSubscription) {
            return $next($request);
        }

        if ($user->free_questions_used < 10) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Free question limit reached. Please upgrade to premium.',
        ], 403);
    }
}
