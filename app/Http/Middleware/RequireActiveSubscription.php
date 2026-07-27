<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-03: simulation exams require an active paid subscription — unlike
 * practice questions, there is no free-quota exception. This is deliberately
 * separate from CheckSubscriptionAccess, which allows the free quota through.
 */
class RequireActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $hasActiveSubscription = $request->user()->subscriptions()
            ->where('status', 'active')
            ->where('expiry_date', '>=', now())
            ->exists();

        if ($hasActiveSubscription) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'An active subscription is required to start a DMV simulation exam.',
            'data' => [
                'upgrade_required' => true,
            ],
        ], 403);
    }
}
