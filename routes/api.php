<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StateController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\SimulationExamController;
use App\Http\Controllers\Api\SubscriptionPackageController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Authentication
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Public APIs
Route::get('/states', [StateController::class, 'index']);
Route::get('/states/{stateId}/categories', [CategoryController::class, 'index']);
Route::get('/subscription-packages', [SubscriptionPackageController::class, 'index']);
Route::get('/subscriptions/status', [SubscriptionController::class, 'status']);

// Protected APIs
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/users/profile', [AuthController::class, 'profile']);
    Route::put('/users/selected-state', [AuthController::class, 'updateSelectedState']);
    Route::put('/users/profile', [AuthController::class, 'updateProfile']);
    Route::put('/users/change-password', [AuthController::class, 'changePassword']);
    //
    Route::post('/exam-attempts/{attemptId}/answers', [SimulationExamController::class, 'saveAnswer']);
    Route::post('/exam-attempts/{attemptId}/submit', [SimulationExamController::class, 'submit']);
    Route::get('/exam-attempts/{attemptId}/results', [SimulationExamController::class, 'results']);

});
Route::middleware(['auth:sanctum', 'subscription'])->group(function () {
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questions/{id}', [QuestionController::class, 'show']);
    Route::post('/questions/{id}/check-answer', [QuestionController::class, 'checkAnswer']);

    Route::get('/simulation-exams', [SimulationExamController::class, 'index']);
    Route::post('/simulation-exams/{examId}/start', [SimulationExamController::class, 'start']);
});
