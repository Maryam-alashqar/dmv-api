<?php

use App\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/privacy-policy', [LegalController::class, 'privacyPolicy'])->name('legal.privacy-policy');
Route::get('/support', [LegalController::class, 'support'])->name('legal.support');
