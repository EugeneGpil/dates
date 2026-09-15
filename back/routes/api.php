<?php

use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

// Probed by the deploy after the containers come up.
Route::get('health', HealthController::class);
