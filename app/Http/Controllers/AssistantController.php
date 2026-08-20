<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssistantController extends Controller
{
    /** قائمة المساعدين + صلاحياتهم. */
    public function index()
    {
        $assistants = User::where('role', 'assistant')
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'stages', 'allowed_teachers', 'scope', 'is_active']);

        return response()->json($assistants);
    }

    /** إنشاء مساعد جديد بصلاحيات مرنة. */
    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $assistant = User::create([
            'name'             => $data['name'],
            'phone'            => $data['phone'],
            'password'         => $data['password'],
            'role'             => 'assistant',
            'stages'           => $data['stages'] ?? [],
            'allowed_teachers' => $data['allowed_teachers'] ?? [],
            'scope'            => $data['scope'],
            'is_active'        => true,
        ]);

        return response()->json([
            'message'   => 'تم إنشاء حساب المساعد.',
            'assistant' => $this->present($assistant),
        ], 201);
    }

    /** تعديل بيانات وصلاحيات مساعد. */
    public function update(Request $request, User $assistant)
    {
        abort_if($assistant->role !== 'assistant', 404);

        $data = $request->validate([
            'name'               => ['sometimes', 'string', 'max:255'],
            'phone'              => ['sometimes', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($assistant->id)],
            'stages'             => ['sometimes', 'array'],
            'stages.*'           => ['string', Rule::in(User::STAGES)],
            'allowed_teachers'   => ['sometimes', 'array'],
            'allowed_teachers.*' => ['integer', 'exists:users,id'],
            'scope'              => ['sometimes', Rule::in(User::SCOPES)],
            'is_active'          => ['sometimes', 'boolean'],
        ]);

        $assistant->fill($data)->save();

        return response()->json([
            'message'   => 'تم تحديث بيانات المساعد.',
            'assistant' => $this->present($assistant),
        ]);
    }

    public function resetPassword(Request $request, User $assistant)
    {
        abort_if($assistant->role !== 'assistant', 404);

        $data = $request->validate(['password' => ['required', 'string', 'min:6']]);
        $assistant->password = $data['password'];
        $assistant->save();
        $assistant->tokens()->delete();

        return response()->json(['message' => 'تمت إعادة تعيين كلمة المرور.']);
    }

    public function toggleActive(User $assistant)
    {
        abort_if($assistant->role !== 'assistant', 404);

        $assistant->is_active = ! $assistant->is_active;
        $assistant->save();
        if (! $assistant->is_active) {
            $assistant->tokens()->delete();
        }

        return response()->json([
            'message'   => $assistant->is_active ? 'تم تفعيل الحساب.' : 'تم إيقاف الحساب.',
            'is_active' => $assistant->is_active,
        ]);
    }

    public function destroy(User $assistant)
    {
        abort_if($assistant->role !== 'assistant', 404);
        $assistant->delete();

        return response()->json(['message' => 'تم حذف المساعد.']);
    }

    /* ---------------------------------------------------------------- */

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'phone'              => ['required', 'string', 'max:30', 'unique:users,phone'],
            'password'           => ['required', 'string', 'min:6'],
            'stages'             => ['sometimes', 'array'],
            'stages.*'           => ['string', Rule::in(User::STAGES)],
            'allowed_teachers'   => ['sometimes', 'array'],
            'allowed_teachers.*' => ['integer', 'exists:users,id'],
            'scope'              => ['required', Rule::in(User::SCOPES)],
        ]);
    }

    private function present(User $u): array
    {
        return $u->only(['id', 'name', 'phone', 'stages', 'allowed_teachers', 'scope', 'is_active']);
    }
}
