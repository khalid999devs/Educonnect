<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Support\ApiResponse;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;

final class AdminLogoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        $guard = Auth::guard('admin');

        if (! $guard instanceof SessionGuard) {
            throw new LogicException('The admin authentication guard must use sessions.');
        }

        $guard->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();
        Auth::shouldUse('web');

        return ApiResponse::success(null);
    }
}
