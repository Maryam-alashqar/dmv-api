<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function contact(Request $request)
    {
        $request->validate([
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        $user = $request->user();

        SupportMessage::create([
            'user_id' => $user->id,
            'name' => $user->full_name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'subject' => $request->subject,
            'message' => $request->message,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your message has been sent. Our support team will get back to you soon.',
        ]);
    }

    public function about()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'app_name' => 'DMV Arabic Exam Simulator',
                'version' => '1.0.0',
                'description' => 'Arabic DMV exam preparation platform for Arabic-speaking communities in the United States.',
            ],
        ]);
    }

    public function privacyPolicy()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'title' => 'Privacy Policy',
                'content' => 'Privacy Policy content will be added here.',
            ],
        ]);
    }

    public function terms()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'title' => 'Terms of Use',
                'content' => 'Terms of Use content will be added here.',
            ],
        ]);
    }
}
