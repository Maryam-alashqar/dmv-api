<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimulationExam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\UserAnswer;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class SimulationExamController extends Controller
{

    use ApiResponseTrait;
    public function index(Request $request)
    {
        $request->validate([
            'state_id' => 'required|exists:states,id',
        ]);

        $exams = SimulationExam::where('state_id', $request->state_id)
            ->where('is_published', true)
            ->get();

        return $this->successResponse($exams, 'Exams retrieved successfully');
    }

    public function start(Request $request, $examId)
{
    $exam = SimulationExam::where('is_published', true)
        ->findOrFail($examId);

    // BR-05: simulation exams are scoped exclusively to the user's selected state.
    if ((int) $exam->state_id !== (int) $request->user()->selected_state_id) {
        return $this->errorResponse('This simulation exam is not available for your selected state.', 403);
    }

    $questions = $this->selectRandomizedQuestions($exam->state_id, $exam->total_questions);

    if ($questions->count() < $exam->total_questions) {
        return response()->json([
            'success' => false,
            'message' => 'Not enough questions available for this exam.',
        ], 422);
    }

    $attempt = ExamAttempt::create([
        'user_id' => $request->user()->id,
        'exam_id' => $exam->id,
        'state_id' => $exam->state_id,
        'start_time' => now(),
        'total_questions' => $exam->total_questions,
        'completion_status' => 'in_progress',
    ]);

    return response()->json([
        'success' => true,
        'data' => [
            'attempt_id' => $attempt->id,
            'exam' => $exam,
            'questions' => $questions,
        ],
    ]);
}

public function saveAnswer(Request $request, $attemptId)
{
    $request->validate([
        'question_id' => 'required|exists:questions,id',
        'selected_answer' => 'required|in:a,b,c,d',
    ]);

    $attempt = ExamAttempt::where('user_id', $request->user()->id)
        ->where('completion_status', 'in_progress')
        ->findOrFail($attemptId);

    $question = Question::findOrFail($request->question_id);

    $isCorrect = $request->selected_answer === $question->correct_answer;

    $answer = UserAnswer::updateOrCreate(
        [
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
        ],
        [
            'selected_answer' => $request->selected_answer,
            'is_correct' => $isCorrect,
            'answered_at' => now(),
        ]
    );

    return $this->successResponse($answer, 'Answer saved successfully');

}

public function submit(Request $request, $attemptId)
{
    $attempt = ExamAttempt::where('user_id', $request->user()->id)
        ->where('completion_status', 'in_progress')
        ->findOrFail($attemptId);

    $correctAnswers = UserAnswer::where('attempt_id', $attempt->id)
        ->where('is_correct', true)
        ->count();

    $answeredCount = UserAnswer::where('attempt_id', $attempt->id)->count();

    $incorrectAnswers = $attempt->total_questions - $correctAnswers;

    $exam = $attempt->exam;

    $passed = $correctAnswers >= $exam->passing_score;
    $endTime = now();
    $timeTakenSeconds = (int) round($attempt->start_time->diffInSeconds($endTime));

    $attempt->update([
        'end_time' => $endTime,
        'score' => $correctAnswers,
        'correct_answers' => $correctAnswers,
        'incorrect_answers' => $incorrectAnswers,
        'passed' => $passed,
        'completion_status' => 'completed',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Exam submitted successfully',
        'data' => [
            'attempt' => $attempt->fresh(),
            'time_taken_seconds' => $timeTakenSeconds,
            'time_taken' => $this->formatDuration($timeTakenSeconds),
            'answered_questions' => $answeredCount,
            'unanswered_questions' => $attempt->total_questions - $answeredCount,
            'passed' => $passed,
            'passing_score' => $exam->passing_score,
        ],
    ]);
}

public function results(Request $request, $attemptId)
{
    $attempt = ExamAttempt::with([
        'exam',
        'answers.question',
    ])
    ->where('user_id', $request->user()->id)
    ->findOrFail($attemptId);

    $timeTakenSeconds = $attempt->end_time
        ? (int) round($attempt->start_time->diffInSeconds($attempt->end_time))
        : null;

    return response()->json([
        'success' => true,
        'data' => [
            'attempt' => [
                'id' => $attempt->id,
                'score' => $attempt->score,
                'correct_answers' => $attempt->correct_answers,
                'incorrect_answers' => $attempt->incorrect_answers,
                'passed' => $attempt->passed,
                'start_time' => $attempt->start_time,
                'end_time' => $attempt->end_time,
                'time_taken_seconds' => $timeTakenSeconds,
                'time_taken' => $timeTakenSeconds !== null ? $this->formatDuration($timeTakenSeconds) : null,
            ],

            'exam' => $attempt->exam,

            'answers' => $attempt->answers->map(function ($answer) {
                return [
                    'question_id' => $answer->question->id,

                    'question_text_ar' => $answer->question->question_text_ar,
                    'image_url' => $answer->question->image_url,

                    'selected_answer' => $answer->selected_answer,
                    'correct_answer' => $answer->question->correct_answer,

                    'is_correct' => $answer->is_correct,

                    'explanation_ar' => $answer->question->explanation_ar,
                ];
            }),
        ],
    ]);
}

public function history(Request $request)
{
    $attempts = ExamAttempt::with(['exam', 'state'])
        ->where('user_id', $request->user()->id)
        ->where('completion_status', 'completed')
        ->latest()
        ->paginate(10);

    return response()->json([
        'success' => true,
        'message' => 'Exam attempts history retrieved successfully',
        'data' => $attempts,
    ]);
}

/**
 * FR-32: randomize the question set so it also reflects the state's real
 * category mix (content distribution), instead of pulling flat-random from
 * the whole state pool where one category could dominate by chance.
 * Allocates $totalNeeded proportionally across categories based on each
 * category's share of the active question bank (largest-remainder
 * rounding), then redistributes any shortfall from under-stocked
 * categories to others with spare questions before sampling and shuffling.
 */
private function selectRandomizedQuestions(int $stateId, int $totalNeeded)
{
    $categoryCounts = Question::where('state_id', $stateId)
        ->where('is_active', true)
        ->where('import_status', 'active')
        ->selectRaw('category_id, COUNT(*) as total')
        ->groupBy('category_id')
        ->pluck('total', 'category_id');

    $totalAvailable = $categoryCounts->sum();

    if ($totalAvailable < $totalNeeded) {
        return collect();
    }

    $allocations = [];
    $remainders = [];
    $allocatedSum = 0;

    foreach ($categoryCounts as $categoryId => $available) {
        $exact = ($available / $totalAvailable) * $totalNeeded;
        $allocations[$categoryId] = (int) floor($exact);
        $remainders[$categoryId] = $exact - $allocations[$categoryId];
        $allocatedSum += $allocations[$categoryId];
    }

    $leftover = $totalNeeded - $allocatedSum;
    arsort($remainders);

    foreach (array_keys($remainders) as $categoryId) {
        if ($leftover <= 0) {
            break;
        }

        $allocations[$categoryId]++;
        $leftover--;
    }

    // A category can be allocated more than it actually has left over from
    // rounding; cap it and push the shortfall onto categories with spare capacity.
    $shortfall = 0;

    foreach ($allocations as $categoryId => $count) {
        $available = $categoryCounts[$categoryId];

        if ($count > $available) {
            $shortfall += $count - $available;
            $allocations[$categoryId] = $available;
        }
    }

    if ($shortfall > 0) {
        foreach ($allocations as $categoryId => $count) {
            if ($shortfall <= 0) {
                break;
            }

            $spare = $categoryCounts[$categoryId] - $count;

            if ($spare > 0) {
                $add = min($spare, $shortfall);
                $allocations[$categoryId] += $add;
                $shortfall -= $add;
            }
        }
    }

    $questions = collect();

    foreach ($allocations as $categoryId => $count) {
        if ($count <= 0) {
            continue;
        }

        $questions = $questions->merge(
            Question::where('state_id', $stateId)
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->where('import_status', 'active')
                ->inRandomOrder()
                ->limit($count)
                ->get()
        );
    }

    return $questions->shuffle()->values();
}

private function formatDuration(int $seconds): string
{
    return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
}
}
