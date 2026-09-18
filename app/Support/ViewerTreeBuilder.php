<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * يبني قائمة المحتوى المفلترة للمشاهد المقيّد: شجرة مدرّسه الوحيد (مرحلة > صف > مادة > مذكرة)،
 * مبقياً فقط المواد المسموح بها في مصفوفته، ومحذوفاً منها تماماً أي مادة/صف/مرحلة خارجها
 * (لا يتسرّب أي اسم محجوب). نفس شكل شجرة المدرّس لتوحيد الواجهة.
 */
class ViewerTreeBuilder
{
    public static function for(User $viewer): array
    {
        $teacher = $viewer->viewerTeacher; // المدرّس المربوط
        if (! $teacher) {
            return [];
        }

        $allowed = array_flip(Access::viewerAllowedSubjectIds($viewer)); // subject_id => idx
        if (! count($allowed)) {
            return [];
        }

        $classes = $teacher->classes()
            ->with('subjects.document')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('stage');

        $stages = $teacher->stages ?? [];
        $order  = array_flip(User::STAGES);
        usort($stages, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        $tree = [];

        foreach ($stages as $stage) {
            $stageClasses = ($classes[$stage] ?? new Collection())
                ->map(function ($class) use ($allowed) {
                    // إبقاء المواد المسموح بها فقط
                    $subjects = $class->subjects
                        ->filter(fn ($subject) => isset($allowed[$subject->id]))
                        ->map(fn ($subject) => [
                            'id'       => $subject->id,
                            'name'     => $subject->name,
                            'document' => $subject->document ? [
                                'id'            => $subject->document->id,
                                'title'         => $subject->document->title,
                                'original_name' => $subject->document->original_name,
                                'size'          => $subject->document->size,
                                'created_at'    => $subject->document->created_at,
                            ] : null,
                        ])->values();

                    return [
                        'id'       => $class->id,
                        'name'     => $class->name,
                        'subjects' => $subjects,
                    ];
                })
                // حذف الصفوف التي لا تحوي أي مادة مسموحة
                ->filter(fn ($class) => count($class['subjects']) > 0)
                ->values();

            // حذف المراحل الفارغة تماماً
            if ($stageClasses->count() > 0) {
                $tree[] = [
                    'stage'   => $stage,
                    'classes' => $stageClasses,
                ];
            }
        }

        return $tree;
    }
}
