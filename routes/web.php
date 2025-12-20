<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\OAuthController;
use Illuminate\Support\Facades\Auth;

// Home page (login page)
Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/dashboard');
    }
    return view('welcome');
});

// Google OAuth routes
Route::get('/auth/google', [OAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [OAuthController::class, 'handleGoogleCallback']);

// Hubspot OAuth routes (for later)
Route::get('/auth/hubspot', [OAuthController::class, 'redirectToHubspot'])->name('auth.hubspot');
Route::get('/auth/hubspot/callback', [OAuthController::class, 'handleHubspotCallback']);

// Logout
Route::post('/logout', [OAuthController::class, 'logout'])->name('logout');

// Protected routes (require authentication)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});