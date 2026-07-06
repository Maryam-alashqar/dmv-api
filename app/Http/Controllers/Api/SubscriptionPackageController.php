<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPackage;

class SubscriptionPackageController extends Controller
{
    public function index()
    {
        $packages = SubscriptionPackage::where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $packages,
        ]);
    }
}
