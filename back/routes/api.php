<?php

use App\Http\Controllers\Api\Account\DestroyAccountController;
use App\Http\Controllers\Api\Auth\FirebaseLoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

// Probed by the deploy after the containers come up.
Route::get('health', HealthController::class);

// The way in, and the only route outside the group below: it is what mints the token the rest
// of them require.
Route::post('auth/firebase', FirebaseLoginController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', MeController::class);
    Route::post('auth/logout', LogoutController::class);

    // Deletes the user and everything hanging off them. Required by Play's User Data policy
    // for any app with accounts, and irreversible — see the controller.
    Route::delete('account', DestroyAccountController::class);
});
