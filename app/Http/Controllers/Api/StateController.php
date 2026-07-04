<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\State;
use App\Traits\ApiResponseTrait;

class StateController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        $states = State::where('is_active', true)
            ->select('id', 'name_en', 'name_ar', 'abbreviation', 'dmv_question_count', 'dmv_passing_score', 'icon_url')
            ->orderBy('name_en')
            ->get();

        return $this->successResponse($states, 'States retrieved successfully');
    }
}
