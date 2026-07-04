<?php

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'excursion-api']));

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => UserResource::make($request->user()));
});
