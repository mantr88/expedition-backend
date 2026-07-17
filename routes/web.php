<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\SetPasswordController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [SessionController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::post('/set-password', [SetPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('password.set');
