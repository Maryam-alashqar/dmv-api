<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StateController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\SimulationExamController;
use App\Http\Controllers\Api\SubscriptionPackageController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SupportController;
use Illuminate\Support\Facades\Route;

// Authentication
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/social', [AuthController::class, 'social']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/auth/verify', [AuthController::class, 'verify']);
Route::post('/auth/resend-verification-code', [AuthController::class, 'resendVerificationCode']);

Route::middleware(['auth:sanctum', 'ability:refresh'])->group(function () {
    Route::get('/auth/refresh-token', [AuthController::class, 'refreshToken']);
});

// Public APIs
Route::get('/states', [StateController::class, 'index']);
Route::get('/states/{stateId}/categories', [CategoryController::class, 'index']);
Route::get('/subscription-packages', [SubscriptionPackageController::class, 'index']);
Route::post('/subscriptions/stripe-webhook', [SubscriptionController::class, 'stripeWebhook']);

Route::get('/about', [SupportController::class, 'about']);
Route::get('/privacy-policy', [SupportController::class, 'privacyPolicy']);
Route::get('/terms', [SupportController::class, 'terms']);

// Protected APIs
Route::middleware(['auth:sanctum', 'ability:access'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/users/profile', [AuthController::class, 'profile']);
    Route::put('/users/selected-state', [AuthController::class, 'updateSelectedState']);
    Route::put('/users/profile', [AuthController::class, 'updateProfile']);
    Route::put('/users/change-password', [AuthController::class, 'changePassword']);
    Route::patch('/users/profile-photo', [AuthController::class, 'updateProfilePhoto']);
    Route::delete('/users/account', [AuthController::class, 'deleteAccount']);
    //
    Route::post('/exam-attempts/{attemptId}/answers', [SimulationExamController::class, 'saveAnswer']);
    Route::post('/exam-attempts/{attemptId}/submit', [SimulationExamController::class, 'submit']);
    Route::get('/exam-attempts/{attemptId}/results', [SimulationExamController::class, 'results']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::post('/subscriptions/initiate', [SubscriptionController::class, 'initiate']);
    Route::post('/subscriptions/apple-iap', [SubscriptionController::class, 'appleIap']);
    Route::get('/subscriptions/status', [SubscriptionController::class, 'status']);
    Route::get('/subscriptions/history', [SubscriptionController::class, 'history']);
    Route::get('/payments/history', [SubscriptionController::class, 'paymentHistory']);

    Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);
    Route::get('/analytics/progress', [AnalyticsController::class, 'progress']);
    Route::get('/analytics/by-category', [AnalyticsController::class, 'byCategory']);

    Route::get('/exam-attempts/history', [SimulationExamController::class, 'history']);



});
Route::middleware(['auth:sanctum', 'ability:access', 'subscription'])->group(function () {
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questions/{id}', [QuestionController::class, 'show']);

    Route::get('/simulation-exams', [SimulationExamController::class, 'index']);
    Route::post('/simulation-exams/{examId}/start', [SimulationExamController::class, 'start']);

});

Route::middleware(['auth:sanctum', 'ability:access', 'free.quota'])->group(function () {
    Route::post('/questions/{id}/check-answer', [QuestionController::class, 'checkAnswer']);

    });
