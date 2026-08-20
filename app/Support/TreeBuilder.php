<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class TreeBuilder
{
    /**
     * يبني الشجرة التعليمية للمدرس: المرحلة > الصف > المادة > (مذكرة أو null).
     * تُعرض كل المراحل المخصّصة للمدرس حتى لو كانت فارغة.
     */
    public static function for(User $teacher, ?array $onlyStages = null): array
    {
        $classes = $teacher->classes()
            ->with('subjects.document')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('stage');

        $stages = $teacher->stages ?? [];

        // قصر المراحل المعروضة على ما يُسمح به (للمساعد)
        if ($onlyStages !== null) {
            $stages = array_values(array_intersect($stages, $onlyStages));
        }

        // ترتيب المراحل حسب التسلسل الرسمي (ابتدائي ← متوسط ← ثانوي ← جامعة)
        $order = array_flip(User::STAGES);
        usort($stages, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        $tree = [];

        foreach ($stages as $stage) {
            $stageClasses = ($classes[$stage] ?? new Collection())->map(fn ($class) => [
                'id'       => $class->id,
                'name'     => $class->name,
                'subjects' => $class->subjects->map(fn ($subject) => [
                    'id'       => $subject->id,
                    'name'     => $subject->name,
                    'document' => $subject->document ? [
                        'id'            => $subject->document->id,
                        'title'         => $subject->document->title,
                        'original_name' => $subject->document->original_name,
                        'size'          => $subject->document->size,
                        'created_at'    => $subject->document->created_at,
                    ] : null,
                ])->values(),
            ])->values();

            $tree[] = [
                'stage'   => $stage,
                'classes' => $stageClasses,
            ];
        }

        return $tree;
    }
}
