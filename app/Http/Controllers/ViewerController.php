<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\User;
use App\Models\ViewerPermission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * إدارة حسابات "المشاهد المقيّد" ومصفوفة صلاحياته (لمدير المطبعة فقط).
 * كل مشاهد مربوط بمدرّس واحد، ويُمنح مواد محددة (subject_id) من مواد ذلك المدرّس.
 */
class ViewerController extends Controller
{
    private const ROLE = User::ROLE_RESTRICTED_VIEWER;

    public function index()
    {
        $viewers = User::where('role', self::ROLE)
            ->with(['viewerTeacher:id,name', 'viewerPermissions:id,user_id,subject_id'])
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'teacher_id', 'is_active']);

        return response()->json($viewers->map(fn ($v) => $this->present($v)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'phone'        => ['required', 'string', 'max:30', 'unique:users,phone'],
            'password'     => ['required', 'string', 'min:6'],
            'teacher_id'   => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')],
            'subject_ids'  => ['sometimes', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
        ]);

        $viewer = User::create([
            'name'       => $data['name'],
            'phone'      => $data['phone'],
            'password'   => $data['password'],
            'role'       => self::ROLE,
            'teacher_id' => $data['teacher_id'],
            'is_active'  => true,
        ]);

        if (! empty($data['subject_ids'])) {
            $this->syncPermissions($viewer, $data['subject_ids']);
        }

        return response()->json([
            'message' => 'تم إنشاء حساب المشاهد.',
            'viewer'  => $this->present($viewer->fresh(['viewerTeacher', 'viewerPermissions'])),
        ], 201);
    }

    public function update(Request $request, User $viewer)
    {
        abort_if($viewer->role !== self::ROLE, 404);

        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:255'],
            'phone'      => ['sometimes', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($viewer->id)],
            'teacher_id' => ['sometimes', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        // تغيير المدرّس يُبطل المصفوفة السابقة (المواد تخص مدرّساً آخر)
        if (array_key_exists('teacher_id', $data) && (int) $data['teacher_id'] !== (int) $viewer->teacher_id) {
            $viewer->viewerPermissions()->delete();
        }

        $viewer->fill($data)->save();

        return response()->json([
            'message' => 'تم تحديث بيانات المشاهد.',
            'viewer'  => $this->present($viewer->fresh(['viewerTeacher', 'viewerPermissions'])),
        ]);
    }

    /** ضبط مصفوفة المواد المسموح بها (استبدال كامل). */
    public function setPermissions(Request $request, User $viewer)
    {
        abort_if($viewer->role !== self::ROLE, 404);

        $data = $request->validate([
            'subject_ids'   => ['present', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
        ]);

        $this->syncPermissions($viewer, $data['subject_ids']);

        return response()->json([
            'message' => 'تم تحديث صلاحيات العرض.',
            'viewer'  => $this->present($viewer->fresh(['viewerTeacher', 'viewerPermissions'])),
        ]);
    }

    public function resetPassword(Request $request, User $viewer)
    {
        abort_if($viewer->role !== self::ROLE, 404);

        $data = $request->validate(['password' => ['required', 'string', 'min:6']]);
        $viewer->password = $data['password'];
        $viewer->save();
        $viewer->tokens()->delete();

        return response()->json(['message' => 'تمت إعادة تعيين كلمة المرور.']);
    }

    public function toggleActive(User $viewer)
    {
        abort_if($viewer->role !== self::ROLE, 404);

        $viewer->is_active = ! $viewer->is_active;
        $viewer->save();
        if (! $viewer->is_active) {
            $viewer->tokens()->delete();
        }

        return response()->json([
            'message'   => $viewer->is_active ? 'تم تفعيل الحساب.' : 'تم إيقاف الحساب.',
            'is_active' => $viewer->is_active,
        ]);
    }

    public function destroy(User $viewer)
    {
        abort_if($viewer->role !== self::ROLE, 404);
        $viewer->delete();

        return response()->json(['message' => 'تم حذف المشاهد.']);
    }

    /* ---------------------------------------------------------------- */

    /**
     * مزامنة المواد المسموح بها، مع التحقق أن كل مادة تخص مدرّس المشاهد المربوط.
     */
    private function syncPermissions(User $viewer, array $subjectIds): void
    {
        $subjectIds = array_values(array_unique(array_map('intval', $subjectIds)));

        if ($subjectIds) {
            // إبقاء المواد التي تخص مدرّس المشاهد فقط (دفاع ضد تمرير مواد مدرّس آخر)
            $owned = Subject::whereIn('subjects.id', $subjectIds)
                ->whereHas('schoolClass', fn ($q) => $q->where('user_id', $viewer->teacher_id))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            abort_if(count($owned) !== count($subjectIds), 422, 'بعض المواد المختارة لا تخص المدرّس المربوط.');
        }

        $viewer->viewerPermissions()->delete();
        foreach ($subjectIds as $sid) {
            ViewerPermission::create(['user_id' => $viewer->id, 'subject_id' => $sid]);
        }
    }

    private function present(User $v): array
    {
        return [
            'id'           => $v->id,
            'name'         => $v->name,
            'phone'        => $v->phone,
            'is_active'    => (bool) $v->is_active,
            'teacher_id'   => $v->teacher_id ? (int) $v->teacher_id : null,
            'teacher_name' => $v->viewerTeacher?->name,
            'subject_ids'  => $v->viewerPermissions->pluck('subject_id')->map(fn ($id) => (int) $id)->values(),
        ];
    }
}
