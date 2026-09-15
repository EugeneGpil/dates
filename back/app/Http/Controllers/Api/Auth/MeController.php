<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who the bearer of this token is. The client holds the same fields from the sign-in exchange,
 * so this exists for the launch that starts with a stored token and no user object — and as
 * the cheapest possible "is this token still good".
 */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success($request->user());
    }
}
