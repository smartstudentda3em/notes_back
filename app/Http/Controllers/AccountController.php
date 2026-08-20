<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    /**
     * تعديل بيانات الحساب الشخصي (الاسم / رقم التليفون).
     * متاح لأي مستخدم مسجّل دخوله على نفسه.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        // ملاحظة: المراحل يحددها مدير المطبعة فقط، لا يعدّلها المدرس على نفسه.
        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->id)],
        ]);

        $user->fill($data)->save();

        return response()->json([
            'message' => 'تم تحديث البيانات.',
            'user'    => [
                'id'     => $user->id,
                'name'   => $user->name,
                'phone'  => $user->phone,
                'role'   => $user->role,
                'stages' => $user->stages ?? [],
            ],
        ]);
    }

    /**
     * تغيير كلمة المرور الشخصية (مع التحقق من كلمة المرور الحالية).
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        $user->password = $data['new_password']; // يُشفّر تلقائياً عبر cast: hashed
        $user->save();

        // إبطال بقية التوكنات مع الإبقاء على التوكن الحالي
        $currentId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentId)->delete();

        return response()->json(['message' => 'تم تغيير كلمة المرور.']);
    }
}
