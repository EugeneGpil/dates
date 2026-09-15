<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sign out of this device, and only this device: the token that made the request is deleted
 * and the account's other tokens are left alone. A phone signing out must not stop the tablet
 * receiving reminders.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(message: __('messages.auth.logged_out'));
    }
}
