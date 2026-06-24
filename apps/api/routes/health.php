<?php

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/api/health', static fn (): JsonResponse => ApiResponse::success([
    'status' => 'up',
]))->name('api.health');
