<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UtilisateurController;

// Public authentication routes
Route::post('/admin/login', [UtilisateurController::class, 'login']);

// Protected authentication routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin/logout', [UtilisateurController::class, 'logout']);
    Route::get('/admin/profile', [UtilisateurController::class, 'me']);
});