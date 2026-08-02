<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserAnswer;
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

        return $this->successResponse($questions, 'Questions retrieved successfully', 200, [
            'quota' => $this->freeQuotaSummary($user),
        ]);
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

            $view = $user->questionViews()->where('question_id', $question->id)->first();

            if (! $view) {
                return $this->freeQuotaExceededResponse($user);
            }

            // First attempt at this question (right or wrong) is what
            // "uses up" a slot in the free set — re-answering it again
            // afterwards doesn't consume anything further.
            if (! $view->answered_at) {
                $view->update(['answered_at' => now()]);
            }
        }

        $isCorrect = $request->selected_answer === $question->correct_answer;

        $this->recordPracticeAnswer($user, $question, $request->selected_answer, $isCorrect);

        $subscription = $hasActiveSubscription
            ? [
                'has_active_subscription' => true,
                'free_questions_used' => null,
                'free_questions_limit' => null,
                'remaining_free_questions' => null,
                'upgrade_required' => false,
            ]
            : array_merge(
                ['has_active_subscription' => false],
                $quota = $this->freeQuotaSummary($user),
                ['upgrade_required' => $quota['remaining_free_questions'] === 0],
            );

        return $this->successResponse([
            'question_id' => $question->id,
            'selected_answer' => $request->selected_answer,
            'is_correct' => $isCorrect,
            'correct_answer' => $question->correct_answer,
            'explanation_ar' => $question->explanation_ar,
            'subscription' => $subscription,
        ], 'Answer checked successfully');
    }

    /**
     * Records an answer checked outside of a formal simulation exam (free
     * practice, whether the user is subscribed or on the free quota) so it
     * still counts toward the user's overall progress stats — per the SRS
     * data model, Exam_Attempt.exam_id is nullable specifically "for
     * free-practice attempts". Re-checking the same question again is a
     * no-op (firstOrCreate), matching the free-quota "first attempt only"
     * semantics used elsewhere.
     */
    private function recordPracticeAnswer(User $user, Question $question, string $selectedAnswer, bool $isCorrect): void
    {
        $attempt = ExamAttempt::firstOrCreate(
            ['user_id' => $user->id, 'exam_id' => null, 'completion_status' => 'in_progress'],
            // state_id is a required column; the question's own state is always
            // present, whereas the user's selected_state_id might not be.
            ['state_id' => $user->selected_state_id ?: $question->state_id, 'start_time' => now(), 'total_questions' => 0]
        );

        UserAnswer::firstOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $question->id],
            ['selected_answer' => $selectedAnswer, 'is_correct' => $isCorrect, 'answered_at' => now()]
        );
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
    }

    private function isInFreeQuestionSet(User $user, int $questionId): bool
    {
        return $user->questionViews()->where('question_id', $questionId)->exists();
    }

    /**
     * "Used" means answered (right or wrong), not merely assigned — the
     * fixed set of 10 is granted upfront, but progress/remaining only moves
     * as the student actually attempts questions from it.
     */
    private function freeQuotaSummary(User $user): array
    {
        $limit = Setting::get('free_questions_limit', config('dmv.free_questions_limit', 10));
        $used = $user->questionViews()->whereNotNull('answered_at')->count();

        $user->update(['free_questions_used' => $used]);

        return [
            'free_questions_used' => $used,
            'free_questions_limit' => $limit,
            'remaining_free_questions' => max(0, $limit - $used),
        ];
    }

    private function freeQuotaExceededResponse(User $user)
    {
        $quota = $this->freeQuotaSummary($user);

        return response()->json([
            'success' => false,
            'message' => 'Free question quota exceeded. Please upgrade your subscription.',
            'data' => [
                'free_questions_used' => $quota['free_questions_used'],
                'free_questions_limit' => $quota['free_questions_limit'],
                'upgrade_required' => true,
            ],
        ], 403);
    }
}
