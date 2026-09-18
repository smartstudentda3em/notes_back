<?php

namespace App\Support;

use App\Models\Document;
use App\Models\User;

/**
 * منطق الصلاحيات المرن لمدير المطبعة ومساعديه.
 * - admin_press: صلاحية كاملة بلا قيود.
 * - assistant: مقيّد بـ (المراحل + مدرسين محددين + نطاق العمل).
 */
class Access
{
    /** هل المستخدم من طاقم المطبعة (مدير أو مساعد)؟ */
    public static function isStaff(User $u): bool
    {
        return in_array($u->role, ['admin_press', 'assistant'], true);
    }

    /** هل يملك صلاحية الإضافة/التعديل (وليس الطباعة فقط)؟ */
    public static function canManage(User $u): bool
    {
        return $u->role === 'admin_press'
            || ($u->role === 'assistant' && $u->scope === 'manage');
    }

    /** المراحل المسموح بها (null = كل المراحل). */
    public static function allowedStages(User $u): ?array
    {
        if ($u->role === 'admin_press') return null;
        $s = $u->stages ?? [];
        return count($s) ? array_values($s) : null;
    }

    public static function allowsStage(User $u, ?string $stage): bool
    {
        $allowed = self::allowedStages($u);
        if ($allowed === null || $stage === null) return true;
        return in_array($stage, $allowed, true);
    }

    /** معرّفات المدرسين المسموح بهم (null = كل المدرسين). */
    public static function allowedTeacherIds(User $u): ?array
    {
        if ($u->role === 'admin_press') return null;
        $t = $u->allowed_teachers ?? [];
        return count($t) ? array_map('intval', $t) : null;
    }

    /** هل يُسمح للمستخدم بالوصول لهذا المدرس؟ */
    public static function allowsTeacher(User $u, User $teacher): bool
    {
        if ($teacher->role !== 'teacher') return false;
        if ($u->role === 'admin_press') return true;

        $ids = self::allowedTeacherIds($u);
        if ($ids !== null && ! in_array($teacher->id, $ids, true)) return false;

        // يجب أن يشترك المدرس في مرحلة واحدة على الأقل من مراحل المساعد
        $stages = self::allowedStages($u);
        if ($stages !== null && ! count(array_intersect($teacher->stages ?? [], $stages))) return false;

        return true;
    }

    /* ===================================================================
     |  المشاهد المقيّد (Restricted Viewer)
     |  اصطلاح مختلف عمداً: المصفوفة الفارغة = لا يرى شيئاً (وليس الكل).
     =================================================================== */

    public static function isRestrictedViewer(User $u): bool
    {
        return $u->role === User::ROLE_RESTRICTED_VIEWER;
    }

    /** معرّف المدرّس الوحيد المربوط به المشاهد (null = غير مربوط = لا يرى شيئاً). */
    public static function viewerTeacherId(User $viewer): ?int
    {
        return $viewer->teacher_id ? (int) $viewer->teacher_id : null;
    }

    /**
     * معرّفات المواد المسموح للمشاهد برؤيتها. مصفوفة فارغة = لا يُسمح بشيء.
     */
    public static function viewerAllowedSubjectIds(User $viewer): array
    {
        return $viewer->viewerPermissions()
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * هل تقع هذه المذكرة ضمن صلاحية المشاهد؟ يُستدعى في كل طلب فتح/صفحة.
     * الشرطان معاً: المادة ضمن مصفوفته، والمذكرة تخص مدرّسه المربوط (دفاع مزدوج).
     */
    public static function viewerAllowsDocument(User $viewer, Document $doc): bool
    {
        if (! self::isRestrictedViewer($viewer)) return false;

        $teacherId = self::viewerTeacherId($viewer);
        if ($teacherId === null) return false;
        if ((int) $doc->user_id !== $teacherId) return false;

        return in_array((int) $doc->subject_id, self::viewerAllowedSubjectIds($viewer), true);
    }
}
