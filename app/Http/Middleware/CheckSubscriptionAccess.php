<?php

namespace App\Http\Middleware;

use App\Models\Setting;
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

        $freeQuotaLimit = Setting::get('free_questions_limit', config('dmv.free_questions_limit', 10));

        if ($user->free_questions_used < $freeQuotaLimit) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Free question limit reached. Please upgrade to premium.',
        ], 403);
    }
}
