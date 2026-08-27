<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
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
                'title_ar' => Setting::get('about_title_ar', 'من نحن'),
                'title_en' => Setting::get('about_title_en', 'About Us'),
                'content_ar' => Setting::get('about_content_ar', ''),
                'content_en' => Setting::get('about_content_en', ''),
            ],
        ]);
    }

    public function privacyPolicy()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'title_ar' => Setting::get('privacy_title_ar', config('legal.privacy_title_ar')),
                'title_en' => Setting::get('privacy_title_en', config('legal.privacy_title_en')),
                'content_ar' => Setting::get('privacy_content_ar', config('legal.privacy_content_ar')),
                'content_en' => Setting::get('privacy_content_en', config('legal.privacy_content_en')),
                'deletion_title_ar' => Setting::get('deletion_title_ar', config('legal.deletion_title_ar')),
                'deletion_title_en' => Setting::get('deletion_title_en', config('legal.deletion_title_en')),
                'deletion_content_ar' => Setting::get('deletion_content_ar', config('legal.deletion_content_ar')),
                'deletion_content_en' => Setting::get('deletion_content_en', config('legal.deletion_content_en')),
            ],
        ]);
    }

    public function terms()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'title_ar' => Setting::get('terms_title_ar', 'الشروط والأحكام'),
                'title_en' => Setting::get('terms_title_en', 'Terms of Use'),
                'content_ar' => Setting::get('terms_content_ar', ''),
                'content_en' => Setting::get('terms_content_en', ''),
            ],
        ]);
    }
}
