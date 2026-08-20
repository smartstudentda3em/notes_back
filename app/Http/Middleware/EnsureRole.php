<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * التحقق من الدور + أن الحساب نشط.
     * الاستخدام في الراوت: ->middleware('role:admin_press')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'الحساب موقوف أو غير مصرح.'], 403);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'ليس لديك صلاحية للوصول.'], 403);
        }

        return $next($request);
    }
}
