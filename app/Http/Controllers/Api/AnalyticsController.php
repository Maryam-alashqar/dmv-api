<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\UserAnswer;
use App\Models\Category;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();

        $completedAttempts = ExamAttempt::where('user_id', $user->id)
            ->where('completion_status', 'completed');

        $totalSimulations = (clone $completedAttempts)->count();

        $averageScore = (clone $completedAttempts)->avg('score') ?? 0;

        $highestScore = (clone $completedAttempts)->max('score') ?? 0;

        $totalAnswers = UserAnswer::whereHas('attempt', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->count();

        $correctAnswers = UserAnswer::whereHas('attempt', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->where('is_correct', true)->count();

        $correctRatio = $totalAnswers > 0
            ? round(($correctAnswers / $totalAnswers) * 100, 2)
            : 0;

        return response()->json([
            'success' => true,
            'message' => 'Analytics summary retrieved successfully',
            'data' => [
                'total_simulations' => $totalSimulations,
                'average_score' => round($averageScore, 2),
                'highest_score' => $highestScore,
                'total_answers' => $totalAnswers,
                'correct_answers' => $correctAnswers,
                'correct_ratio' => $correctRatio,
            ],
        ]);
    }

    public function progress(Request $request)
{
    $progress = ExamAttempt::where('user_id', $request->user()->id)
        ->where('completion_status', 'completed')
        ->orderBy('created_at')
        ->get([
            'id',
            'score',
            'created_at',
        ]);

    return response()->json([
        'success' => true,
        'message' => 'Progress retrieved successfully',
        'data' => $progress,
    ]);
}

public function byCategory(Request $request)
{
    $user = $request->user();

    $categories = Category::withCount([
        'questions as total_answers' => function ($query) use ($user) {
            $query->whereHas('userAnswers.attempt', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        },
        'questions as correct_answers' => function ($query) use ($user) {
            $query->whereHas('userAnswers', function ($q) use ($user) {
                $q->where('is_correct', true)
                    ->whereHas('attempt', function ($attemptQuery) use ($user) {
                        $attemptQuery->where('user_id', $user->id);
                    });
            });
        },
    ])->get();

    $data = $categories->map(function ($category) {
        $ratio = $category->total_answers > 0
            ? round(($category->correct_answers / $category->total_answers) * 100, 2)
            : 0;

        return [
            'category_id' => $category->id,
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
            'total_answers' => $category->total_answers,
            'correct_answers' => $category->correct_answers,
            'correct_ratio' => $ratio,
        ];
    });

    return response()->json([
        'success' => true,
        'message' => 'Category analytics retrieved successfully',
        'data' => $data,
    ]);
}
}
