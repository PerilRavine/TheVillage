<?php

use Illuminate\Support\Facades\Route;

// Serve the frontend SPA
Route::get('/', function () {
    return view('welcome');
});

Route::get('/village', function () {
    return view('village');
});

Route::get('/admin', function () {
    return view('admin');
})->middleware('auth');

// API routes are in api.php
Route::prefix('api')->middleware('api')->group(function () {
    require __DIR__.'/api.php';
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
    ]);
});
