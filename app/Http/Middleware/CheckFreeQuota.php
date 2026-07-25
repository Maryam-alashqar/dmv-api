<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

class CheckFreeQuota
{
    public function handle(Request $request, Closure $next)
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

        if ($user->free_questions_used >= $freeQuotaLimit) {
            return response()->json([
                'success' => false,
                'message' => 'Free question quota exceeded. Please upgrade your subscription.',
                'data' => [
                    'free_questions_used' => $user->free_questions_used,
                    'free_questions_limit' => $freeQuotaLimit,
                    'upgrade_required' => true,
                ],
            ], 403);
        }

        return $next($request);
    }
}
