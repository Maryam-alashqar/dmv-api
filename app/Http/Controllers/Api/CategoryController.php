<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponseTrait;
class CategoryController extends Controller
{
    use ApiResponseTrait;

    public function index($stateId)
    {
        $categories = Category::where('state_id', $stateId)
            ->orderBy('sequence_order')
            ->get();

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }
}
