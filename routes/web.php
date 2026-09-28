<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/', fn () => view('welcome'));

Route::fallback(function () {
    abort_if(request()->is('api/*', 'sanctum/*'), 404);

    return view('welcome');
});
