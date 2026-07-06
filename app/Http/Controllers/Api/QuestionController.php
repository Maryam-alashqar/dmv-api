<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class QuestionController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $request->validate([
            'state_id' => 'required|exists:states,id',
            'category_id' => 'nullable|exists:categories,id',
            'category_type' => 'nullable|in:general,signs',
        ]);

        $questions = Question::with(['state:id,name_en,name_ar', 'category:id,name_en,name_ar,category_type'])
            ->where('state_id', $request->state_id)
            ->where('is_active', true)
            ->where('import_status', 'active')
            ->when($request->category_id, fn ($query) => $query->where('category_id', $request->category_id))
            ->when($request->category_type, function ($query) use ($request) {
                $query->whereHas('category', fn ($q) => $q->where('category_type', $request->category_type));
            })
            ->paginate(10);

        return $this->successResponse($questions, 'Questions retrieved successfully');
    }

    public function show(Request $request, $id)
    {
        $question = Question::with(['state:id,name_en,name_ar', 'category:id,name_en,name_ar,category_type'])
            ->where('is_active', true)
            ->where('import_status', 'active')
            ->findOrFail($id);

        $user = $request->user();

        $hasActiveSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('expiry_date', '>=', now())
            ->exists();

        if (! $hasActiveSubscription) {
            $alreadyViewed = $user->questionViews()
                ->where('question_id', $question->id)
                ->exists();

            if (! $alreadyViewed && $user->free_questions_used < 10) {
                $user->questionViews()->create([
                    'question_id' => $question->id,
                    'viewed_at' => now(),
                ]);

                $user->increment('free_questions_used');
            }
        }

        return $this->successResponse($question, 'Question retrieved successfully');
    }

    public function checkAnswer(Request $request, $id)
    {
        $request->validate([
            'selected_answer' => 'required|in:a,b,c,d',
        ]);

        $question = Question::where('is_active', true)
            ->where('import_status', 'active')
            ->findOrFail($id);

        $isCorrect = $request->selected_answer === $question->correct_answer;

        return $this->successResponse([
            'question_id' => $question->id,
            'selected_answer' => $request->selected_answer,
            'is_correct' => $isCorrect,
            'correct_answer' => $question->correct_answer,
            'explanation_ar' => $question->explanation_ar,
        ], 'Answer checked successfully');
    }
}
