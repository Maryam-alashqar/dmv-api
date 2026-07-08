<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
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

    public function history(Request $request)
{
    $subscriptions = $request->user()
        ->subscriptions()
        ->with(['package', 'payment'])
        ->latest()
        ->paginate(10);

    return response()->json([
        'success' => true,
        'message' => 'Subscription history retrieved successfully',
        'data' => $subscriptions,
    ]);
}

public function paymentHistory(Request $request)
{
    $payments = Payment::with('package')
        ->where('user_id', $request->user()->id)
        ->latest()
        ->paginate(10);

    return response()->json([
        'success' => true,
        'message' => 'Payment history retrieved successfully',
        'data' => $payments,
    ]);
}
}
