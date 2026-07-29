<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Setting;
use App\Models\User;
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

        $user = $request->user();

        $query = Question::with(['state:id,name_en,name_ar', 'category:id,name_en,name_ar,category_type'])
            ->where('state_id', $request->state_id)
            ->where('is_active', true)
            ->where('import_status', 'active')
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->category_type, function ($q) use ($request) {
                $q->whereHas('category', fn ($cat) => $cat->where('category_type', $request->category_type));
            });

        if ($user->hasActiveSubscription()) {
            return $this->successResponse($query->paginate(10), 'Questions retrieved successfully');
        }

        // BR-01: free-tier users get a fixed set of 10 randomly-chosen
        // questions, assigned once and reused every time — not an
        // ever-growing count of whatever they happen to browse.
        $this->ensureFreeQuestionSetAssigned($user);

        $questionIds = $user->questionViews()->pluck('question_id');

        $questions = $query->whereIn('id', $questionIds)->get();

        return $this->successResponse($questions, 'Questions retrieved successfully');
    }

    public function show(Request $request, $id)
    {
        $question = Question::with(['state:id,name_en,name_ar', 'category:id,name_en,name_ar,category_type'])
            ->where('is_active', true)
            ->where('import_status', 'active')
            ->findOrFail($id);

        $user = $request->user();

        if (! $user->hasActiveSubscription()) {
            $this->ensureFreeQuestionSetAssigned($user);

            if (! $this->isInFreeQuestionSet($user, $question->id)) {
                return $this->freeQuotaExceededResponse($user);
            }
        }

        return $this->successResponse($question, 'Question retrieved successfully');
    }

    public function checkAnswer(Request $request, $id)
    {
        $request->validate([
            'selected_answer' => 'required|in:a,b,c,d',
        ]);

        $user = $request->user();

        $question = Question::where('is_active', true)
            ->where('import_status', 'active')
            ->findOrFail($id);

        $hasActiveSubscription = $user->hasActiveSubscription();

        if (! $hasActiveSubscription) {
            $this->ensureFreeQuestionSetAssigned($user);

            if (! $this->isInFreeQuestionSet($user, $question->id)) {
                return $this->freeQuotaExceededResponse($user);
            }
        }

        $isCorrect = $request->selected_answer === $question->correct_answer;

        $freeQuotaLimit = Setting::get('free_questions_limit', config('dmv.free_questions_limit', 10));
        $remainingFreeQuestions = $hasActiveSubscription
            ? null
            : max(0, $freeQuotaLimit - $user->questionViews()->count());

        return $this->successResponse([
            'question_id' => $question->id,
            'selected_answer' => $request->selected_answer,
            'is_correct' => $isCorrect,
            'correct_answer' => $question->correct_answer,
            'explanation_ar' => $question->explanation_ar,

            'subscription' => [
                'has_active_subscription' => $hasActiveSubscription,
                'free_questions_used' => $hasActiveSubscription ? null : $user->questionViews()->count(),
                'free_questions_limit' => $hasActiveSubscription ? null : $freeQuotaLimit,
                'remaining_free_questions' => $remainingFreeQuestions,
                'upgrade_required' => ! $hasActiveSubscription && $remainingFreeQuestions === 0,
            ],
        ], 'Answer checked successfully');
    }

    /**
     * BR-01/FR-16: assign a free user's permanent set of (up to)
     * free_questions_limit randomly-selected, non-repeating questions from
     * their selected state — once, the first time they touch the question
     * bank. Re-viewing or re-answering any of these never consumes another
     * slot; questions outside this set are simply not accessible for free.
     */
    private function ensureFreeQuestionSetAssigned(User $user): void
    {
        if ($user->questionViews()->exists() || ! $user->selected_state_id) {
            return;
        }

        $limit = Setting::get('free_questions_limit', config('dmv.free_questions_limit', 10));

        $questionIds = Question::where('state_id', $user->selected_state_id)
            ->where('is_active', true)
            ->where('import_status', 'active')
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('id');

        foreach ($questionIds as $questionId) {
            $user->questionViews()->create([
                'question_id' => $questionId,
                'viewed_at' => now(),
            ]);
        }

        $user->update(['free_questions_used' => $questionIds->count()]);
    }

    private function isInFreeQuestionSet(User $user, int $questionId): bool
    {
        return $user->questionViews()->where('question_id', $questionId)->exists();
    }

    private function freeQuotaExceededResponse(User $user)
    {
        $freeQuotaLimit = Setting::get('free_questions_limit', config('dmv.free_questions_limit', 10));

        return response()->json([
            'success' => false,
            'message' => 'Free question quota exceeded. Please upgrade your subscription.',
            'data' => [
                'free_questions_used' => $user->questionViews()->count(),
                'free_questions_limit' => $freeQuotaLimit,
                'upgrade_required' => true,
            ],
        ], 403);
    }
}
