<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Notification::where(function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->orWhereNull('user_id');
            })
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully',
            'data' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where(function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->orWhereNull('user_id');
            })
            ->findOrFail($id);

        $notification->update([
            'read_status' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        Notification::where(function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->orWhereNull('user_id');
            })
            ->update([
                'read_status' => true,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }
}
