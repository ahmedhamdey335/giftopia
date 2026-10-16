<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OptionalSanctumAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            Auth::forgetGuards();
            $user = Auth::guard('sanctum')->user();
            if (! $user) {
                return ApiResponse::error('Unauthenticated.', status: 401);
            }
            $request->setUserResolver(static fn () => $user);
        }

        return $next($request);
    }
}
