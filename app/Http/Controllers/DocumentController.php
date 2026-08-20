<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\PrintLog;
use App\Models\Subject;
use App\Models\User;
use App\Support\Access;
use App\Support\TreeBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const DISK = 'private'; // قرص أمني → storage/app/private

    /* =====================================================================
     |  جهة المدرس (Teacher)
     ===================================================================== */

    /**
     * رفع / استبدال مذكرة مادة (PDF فقط).
     * لا يمسّ المادة أو الصف — فقط ملف المذكرة يُنشأ أو يُستبدل.
     */
    public function upload(Request $request, Subject $subject)
    {
        abort_if($subject->schoolClass->user_id !== $request->user()->id, 403, 'ليست مادتك.');

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'file'  => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:51200'],
        ]);

        $file = $request->file('file');

        if (! $file->isValid()) {
            return response()->json([
                'message' => 'فشل رفع الملف. تأكد أن الحجم ضمن الحد المسموح وأن الصيغة PDF.',
            ], 422);
        }

        $user = $request->user();

        // استبدال: احذف الملف القديم إن وُجد (المادة تبقى)
        if ($subject->document) {
            Storage::disk(self::DISK)->delete($subject->document->file_path);
            $subject->document->delete();
        }

        $storedName = Str::uuid()->toString() . '.pdf';
        $path = $file->storeAs("documents/{$user->id}", $storedName, self::DISK);

        $document = $subject->document()->create([
            'user_id'       => $user->id,
            'title'         => $data['title'] ?? $subject->name,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'size'          => $file->getSize(),
        ]);

        return response()->json([
            'message'  => 'تم حفظ المذكرة.',
            'document' => $document->only(['id', 'title', 'original_name', 'size', 'created_at']),
        ], 201);
    }

    /** إعادة تسمية مذكرة (عنوان الملف فقط). */
    public function rename(Request $request, Document $document)
    {
        $this->authorizeOwner($request, $document);

        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $document->update(['title' => $data['title']]);

        return response()->json(['message' => 'تم تعديل الاسم.', 'title' => $document->title]);
    }

    /** حذف ملف المذكرة فقط — المادة والصف يبقيان. */
    public function destroy(Request $request, Document $document)
    {
        $this->authorizeOwner($request, $document);

        Storage::disk(self::DISK)->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'تم حذف الملف. المادة والصف باقيان.']);
    }

    /** طباعة المدرس لمذكرته هو (بثّ آمن inline). */
    public function streamOwn(Request $request, Document $document): StreamedResponse
    {
        $this->authorizeOwner($request, $document);

        return $this->fileResponse($document);
    }

    /* =====================================================================
     |  جهة المطبعة (Admin)
     ===================================================================== */

    /** الشجرة التعليمية لمدرس محدّد (مفلترة حسب صلاحية المستخدم). */
    public function teacherTree(Request $request, User $teacher)
    {
        $actor = $request->user();
        abort_if($teacher->role !== 'teacher', 404);
        abort_unless(Access::allowsTeacher($actor, $teacher), 403, 'هذا المدرس خارج نطاق صلاحيتك.');

        return response()->json(TreeBuilder::for($teacher, Access::allowedStages($actor)));
    }

    /**
     * البثّ الآمن للملف للطباعة — inline فقط، لا تحميل.
     * محمي بتوكن Sanctum + دور admin_press (يُطبَّق في الراوت).
     */
    public function stream(Request $request, Document $document): StreamedResponse
    {
        $actor = $request->user();
        $document->loadMissing('user', 'subject.schoolClass');

        // المساعد: يجب أن يكون المدرس ضمن نطاقه، والمرحلة ضمن مراحله المسموحة
        abort_unless(Access::allowsTeacher($actor, $document->user), 403, 'خارج نطاق صلاحيتك.');
        abort_unless(Access::allowsStage($actor, $document->subject?->schoolClass?->stage), 403, 'مرحلة غير مسموح بها.');

        // تسجيل عملية الطباعة (عدد النسخ من الطلب)
        $copies = max(1, min((int) $request->query('copies', 1), 10000));
        PrintLog::create([
            'actor_id'     => $actor->id,
            'actor_name'   => $actor->name,
            'actor_role'   => $actor->role,
            'teacher_id'   => $document->user_id,
            'teacher_name' => $document->user?->name,
            'document_id'  => $document->id,
            'memo_title'   => $document->title,
            'subject_name' => $document->subject?->name,
            'class_name'   => $document->subject?->schoolClass?->name,
            'stage'        => $document->subject?->schoolClass?->stage,
            'copies'       => $copies,
            'printed_at'   => now(),
        ]);

        return $this->fileResponse($document);
    }

    /* ===================================================================== */

    /** استجابة الملف inline المشتركة (للمطبعة وللمدرس صاحب الملف). */
    private function fileResponse(Document $document): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);

        abort_unless($disk->exists($document->file_path), 404, 'الملف غير موجود.');

        return $disk->response(
            $document->file_path,
            'memo.pdf',
            [
                'Content-Type'           => 'application/pdf',
                'Content-Disposition'    => 'inline; filename="memo.pdf"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control'          => 'no-store, no-cache, must-revalidate, private',
                'Pragma'                 => 'no-cache',
            ]
        );
    }

    private function authorizeOwner(Request $request, Document $document): void
    {
        abort_if($document->user_id !== $request->user()->id, 403, 'ليست مذكرتك.');
    }
}
