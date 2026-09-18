<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Support\Access;
use App\Support\PdfPageRenderer;
use App\Support\ViewerTreeBuilder;
use App\Support\Watermark;
use Illuminate\Http\Request;

/**
 * واجهة "المشاهد المقيّد": قائمة مفلترة + عرض صفحات كصور مختومة بعلامة مائية.
 * لا يُعطى المتصفح رابطاً مباشراً للـ PDF إطلاقاً، والتحقق من الصلاحية يتم في كل طلب.
 */
class RestrictedViewerController extends Controller
{
    /** شجرة المحتوى المسموح بها (مدرّس المشاهد + مواد مصفوفته فقط). */
    public function tree(Request $request)
    {
        return response()->json(ViewerTreeBuilder::for($request->user()));
    }

    /** بيانات المذكرة للعرض: عدد الصفحات فقط (بعد التحقق من الصلاحية). */
    public function meta(Request $request, Document $document)
    {
        $this->authorize($request, $document);

        $pages = PdfPageRenderer::pageCount(PdfPageRenderer::pdfPath($document->file_path));

        return response()->json([
            'id'    => $document->id,
            'title' => $document->title,
            'pages' => $pages,
        ]);
    }

    /** صورة صفحة واحدة (PNG) مختومة بعلامة مائية (الهاتف + IP + الوقت). */
    public function page(Request $request, Document $document, int $page)
    {
        $this->authorize($request, $document); // تحقّق في كل طلب صفحة

        $abs = PdfPageRenderer::pdfPath($document->file_path);
        abort_unless(is_file($abs), 404, 'الملف غير موجود.');

        $total = PdfPageRenderer::pageCount($abs);
        abort_if($total < 1, 404, 'تعذّر قراءة المذكرة.');
        $page = max(1, min($page, $total));

        $pngPath = PdfPageRenderer::ensurePagePng($document->id, $abs, $page);

        $viewer = $request->user();
        $mark = $viewer->phone . '  •  ' . $request->ip() . '  •  ' . now()->format('Y-m-d H:i');
        $blob = Watermark::stamp($pngPath, $mark);

        return response($blob, 200, [
            'Content-Type'           => 'image/png',
            'Content-Disposition'    => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'no-store, no-cache, must-revalidate, private',
            'Pragma'                 => 'no-cache',
        ]);
    }

    /** يمنع الوصول ما لم تكن المذكرة ضمن مصفوفة المشاهد ولمدرّسه المربوط. */
    private function authorize(Request $request, Document $document): void
    {
        abort_unless(
            Access::viewerAllowsDocument($request->user(), $document),
            403,
            'هذه المذكرة خارج نطاق صلاحيتك.'
        );
    }
}
