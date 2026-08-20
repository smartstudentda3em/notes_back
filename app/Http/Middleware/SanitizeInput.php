<?php

namespace App\Http\Middleware;

use App\Support\Phone;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /** حقول لا تُطهَّر (قد تحتوي رموزاً خاصة مقصودة). */
    protected array $skip = ['password', 'current_password', 'new_password', 'new_password_confirmation'];

    /**
     * تطهير كل المدخلات النصية (إزالة وسوم HTML/سكربتات) لمنع هجمات XSS المخزّنة،
     * وتوحيد صيغة رقم الهاتف (أرقام عربية/إنجليزية) قبل المطابقة والتخزين.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        array_walk_recursive($input, function (&$value, $key) {
            if (is_string($value) && ! in_array($key, $this->skip, true)) {
                $value = trim(strip_tags($value));
            }
        });

        $request->merge($input);

        // توحيد رقم الهاتف (يعالج فشل الدخول بأرقام عربية أو بها مسافات)
        if ($request->has('phone')) {
            $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        }

        return $next($request);
    }
}
