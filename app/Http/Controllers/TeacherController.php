<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    /** قائمة المدرسين المسموح بهم للمستخدم الحالي (مدير أو مساعد). */
    public function index(Request $request)
    {
        $actor = $request->user();

        $query = User::where('role', 'teacher')->withCount('documents')->orderBy('name');

        // المساعد: قصر على المدرسين المخصّصين له
        $ids = Access::allowedTeacherIds($actor);
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        $teachers = $query->get(['id', 'name', 'phone', 'stages', 'is_active']);

        // المساعد: قصر على المدرسين الذين يشاركونه مرحلة واحدة على الأقل
        $stages = Access::allowedStages($actor);
        if ($stages !== null) {
            $teachers = $teachers
                ->filter(fn ($t) => count(array_intersect($t->stages ?? [], $stages)))
                ->values();
        }

        return response()->json($teachers);
    }

    /** إنشاء حساب مدرس جديد. */
    public function store(Request $request)
    {
        $actor = $request->user();
        abort_unless(Access::canManage($actor), 403, 'ليس لديك صلاحية الإضافة.');

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'phone'    => ['required', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6'],
            'stages'   => ['sometimes', 'array'],
            'stages.*' => ['string', Rule::in(User::STAGES)],
        ]);

        $teacher = User::create([
            'name'      => $data['name'],
            'phone'     => $data['phone'],
            'password'  => $data['password'],   // hashed cast
            'role'      => 'teacher',
            'stages'    => $data['stages'] ?? [],
            'is_active' => true,
        ]);

        // مساعد مقيّد بمدرسين محددين: أضف المدرس الجديد لقائمته
        if ($actor->role === 'assistant' && Access::allowedTeacherIds($actor) !== null) {
            $actor->allowed_teachers = array_values(array_unique([...($actor->allowed_teachers ?? []), $teacher->id]));
            $actor->save();
        }

        return response()->json([
            'message' => 'تم إنشاء حساب المدرس.',
            'teacher' => $teacher->only(['id', 'name', 'phone', 'stages', 'is_active']),
        ], 201);
    }

    /** تعديل بيانات مدرس (الاسم / رقم التليفون / التفعيل / المراحل). */
    public function update(Request $request, User $teacher)
    {
        $this->guardManage($request, $teacher);

        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:255'],
            'phone'     => ['sometimes', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($teacher->id)],
            'is_active' => ['sometimes', 'boolean'],
            'stages'    => ['sometimes', 'array'],
            'stages.*'  => ['string', Rule::in(User::STAGES)],
        ]);

        $teacher->fill($data)->save();

        return response()->json([
            'message' => 'تم تحديث بيانات المدرس.',
            'teacher' => $teacher->only(['id', 'name', 'phone', 'stages', 'is_active']),
        ]);
    }

    /** إعادة تعيين كلمة مرور مدرس. */
    public function resetPassword(Request $request, User $teacher)
    {
        $this->guardManage($request, $teacher);

        $data = $request->validate(['password' => ['required', 'string', 'min:6']]);

        $teacher->password = $data['password']; // hashed cast
        $teacher->save();
        $teacher->tokens()->delete();

        return response()->json(['message' => 'تمت إعادة تعيين كلمة المرور.']);
    }

    /** إيقاف/تفعيل حساب مدرس. */
    public function toggleActive(Request $request, User $teacher)
    {
        $this->guardManage($request, $teacher);

        $teacher->is_active = ! $teacher->is_active;
        $teacher->save();

        if (! $teacher->is_active) {
            $teacher->tokens()->delete();
        }

        return response()->json([
            'message'   => $teacher->is_active ? 'تم تفعيل الحساب.' : 'تم إيقاف الحساب.',
            'is_active' => $teacher->is_active,
        ]);
    }

    /** حذف حساب مدرس نهائياً (مع هيكله وملفاته عبر cascade). */
    public function destroy(Request $request, User $teacher)
    {
        $this->guardManage($request, $teacher);

        foreach ($teacher->documents as $doc) {
            Storage::disk('private')->delete($doc->file_path);
        }
        $teacher->delete();

        return response()->json(['message' => 'تم حذف حساب المدرس.']);
    }

    /* ---------------------------------------------------------------- */

    /** يتحقق أن المستخدم يملك صلاحية الإدارة على هذا المدرس. */
    private function guardManage(Request $request, User $teacher): void
    {
        $actor = $request->user();
        abort_if($teacher->role !== 'teacher', 404);
        abort_unless(Access::canManage($actor), 403, 'ليس لديك صلاحية التعديل.');
        abort_unless(Access::allowsTeacher($actor, $teacher), 403, 'هذا المدرس خارج نطاق صلاحيتك.');
    }
}
