<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function status(Request $request)
    {
        $user = $request->user();

        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('expiry_date', '>=', now())
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'has_active_subscription' => (bool) $subscription,
                'subscription' => $subscription,
                'free_questions_used' => $user->free_questions_used,
                'free_questions_limit' => 10,
                'free_questions_remaining' => max(0, 10 - $user->free_questions_used),
            ],
        ]);
    }
}
