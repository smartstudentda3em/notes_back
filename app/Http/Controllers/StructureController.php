<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Support\TreeBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StructureController extends Controller
{
    private const DISK = 'private';

    /** الشجرة التعليمية الكاملة للمدرس الحالي. */
    public function tree(Request $request)
    {
        return response()->json(TreeBuilder::for($request->user()));
    }

    /* ---------------- الصفوف الدراسية ---------------- */

    public function storeClass(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'stage' => ['required', 'string', Rule::in($user->stages ?? [])],
            'name'  => ['required', 'string', 'max:255'],
        ], [
            'stage.in' => 'المرحلة غير مخصّصة لك. تواصل مع المطبعة.',
        ]);

        // منع التكرار داخل نفس المرحلة
        $exists = $user->classes()
            ->where('stage', $data['stage'])
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'هذا الصف موجود بالفعل في هذه المرحلة.'], 422);
        }

        $class = $user->classes()->create($data);

        return response()->json(['message' => 'تم إنشاء الصف.', 'class' => $class], 201);
    }

    public function destroyClass(Request $request, SchoolClass $class)
    {
        abort_if($class->user_id !== $request->user()->id, 403, 'ليس صفّك.');

        // حذف ملفات كل المذكرات تحت هذا الصف قبل حذفه
        $this->deleteFilesUnderClass($class);

        $class->delete(); // cascade على المواد والمذكرات

        return response()->json(['message' => 'تم حذف الصف وكل ما بداخله.']);
    }

    /** إعادة ترتيب الصفوف يدوياً (ترتيب المعرّفات = الترتيب الجديد). */
    public function reorderClasses(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $user = $request->user();
        foreach ($data['ids'] as $index => $id) {
            SchoolClass::where('id', $id)->where('user_id', $user->id)->update(['position' => $index]);
        }

        return response()->json(['message' => 'تم إعادة الترتيب.']);
    }

    /* ---------------- المواد الدراسية ---------------- */

    public function storeSubject(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer'],
            'name'     => ['required', 'string', 'max:255'],
        ]);

        $class = SchoolClass::findOrFail($data['class_id']);
        abort_if($class->user_id !== $request->user()->id, 403, 'ليس صفّك.');

        if ($class->subjects()->where('name', $data['name'])->exists()) {
            return response()->json(['message' => 'هذه المادة موجودة بالفعل في هذا الصف.'], 422);
        }

        $subject = $class->subjects()->create(['name' => $data['name']]);

        return response()->json(['message' => 'تمت إضافة المادة.', 'subject' => $subject], 201);
    }

    public function destroySubject(Request $request, Subject $subject)
    {
        abort_if($subject->schoolClass->user_id !== $request->user()->id, 403, 'ليست مادتك.');

        // حذف ملف المذكرة المرتبطة (إن وُجدت) قبل حذف المادة
        if ($subject->document) {
            Storage::disk(self::DISK)->delete($subject->document->file_path);
        }

        $subject->delete(); // cascade على المذكرة

        return response()->json(['message' => 'تم حذف المادة.']);
    }

    /** إعادة ترتيب المواد داخل صف يدوياً. */
    public function reorderSubjects(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer'],
            'ids'      => ['required', 'array'],
            'ids.*'    => ['integer'],
        ]);

        $class = SchoolClass::findOrFail($data['class_id']);
        abort_if($class->user_id !== $request->user()->id, 403, 'ليس صفّك.');

        foreach ($data['ids'] as $index => $id) {
            Subject::where('id', $id)->where('school_class_id', $class->id)->update(['position' => $index]);
        }

        return response()->json(['message' => 'تم إعادة الترتيب.']);
    }

    /* ---------------- مساعد ---------------- */

    private function deleteFilesUnderClass(SchoolClass $class): void
    {
        $class->load('subjects.document');
        foreach ($class->subjects as $subject) {
            if ($subject->document) {
                Storage::disk(self::DISK)->delete($subject->document->file_path);
            }
        }
    }
}
