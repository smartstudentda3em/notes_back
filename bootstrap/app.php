<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // تسجيل اسم مختصر للميدل وير الخاص بالأدوار: role:teacher / role:admin_press
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);

        // تطهير المدخلات (XSS) + ترويسات الأمان على كل الاستجابات
        $middleware->append(\App\Http\Middleware\SanitizeInput::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // نظام API فقط: لا يوجد صفحة login للتوجيه إليها — نمنع نداء route('login')
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // نظام API فقط: كل الأخطاء تُعاد كـ JSON دائماً (لا صفحات HTML ولا توجيه)
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // نظام API فقط: أي فشل مصادقة يعيد 401 JSON بدل التوجيه لصفحة login
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json(['message' => 'غير مصرّح. يرجى تسجيل الدخول.'], 401);
        });
    })->create();
