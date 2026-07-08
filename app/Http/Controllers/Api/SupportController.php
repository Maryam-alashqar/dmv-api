<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class SupportController extends Controller
{
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
