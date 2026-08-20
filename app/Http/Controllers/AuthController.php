<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * تسجيل الدخول برقم التليفون + كلمة المرور.
     * يعيد توكن Sanctum يُستخدم في كل الطلبات اللاحقة (Bearer).
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'phone'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['رقم التليفون أو كلمة المرور غير صحيحة.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'phone' => ['هذا الحساب موقوف. تواصل مع المطبعة.'],
            ]);
        }

        // توكن واحد لكل جلسة دخول
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    /** تمثيل موحّد لبيانات المستخدم (يشمل صلاحيات المساعد). */
    private function userPayload(\App\Models\User $u): array
    {
        return [
            'id'               => $u->id,
            'name'             => $u->name,
            'phone'            => $u->phone,
            'role'             => $u->role,
            'stages'           => $u->stages ?? [],
            'allowed_teachers' => $u->allowed_teachers ?? [],
            'scope'            => $u->scope,
        ];
    }

    /** المستخدم الحالي (لإعادة بناء الجلسة في الواجهة). */
    public function me(Request $request)
    {
        return response()->json($this->userPayload($request->user()));
    }

    /**
     * تجديد رمز الجلسة تلقائياً — يُصدر رمزاً جديداً ويُبطل القديم،
     * لإبقاء المستخدم مسجّلاً أثناء العمل وتقليل عمر الرمز الواحد.
     */
    public function refresh(Request $request)
    {
        $user  = $request->user();
        $token = $user->createToken('auth-token')->plainTextToken;
        $user->currentAccessToken()?->delete();

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    /** تسجيل الخروج (حذف التوكن الحالي فقط). */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }
}
