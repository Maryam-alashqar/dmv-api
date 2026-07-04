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

    $questions = Question::where('state_id', $exam->state_id)
        ->where('is_active', true)
        ->where('import_status', 'active')
        ->inRandomOrder()
        ->limit($exam->total_questions)
        ->get();

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

    $attempt->update([
        'end_time' => now(),
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

}
