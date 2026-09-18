<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * ترسيم صفحات ملف PDF إلى صور PNG عبر Ghostscript، مع كاش على القرص الخاص.
 * لا يُعطى المتصفح ملف الـ PDF إطلاقاً — صور صفحات فقط.
 */
class PdfPageRenderer
{
    private const DISK = 'private';
    private const DPI  = 150;                 // دقة كافية للقراءة/العرض
    private const CACHE_PREFIX = 'rendered';  // storage/app/private/rendered/{id}/p{n}.png

    /** المسار المطلق لملف المذكرة الأصلي. */
    public static function pdfPath(string $filePath): string
    {
        return Storage::disk(self::DISK)->path($filePath);
    }

    /** مسار الـ Ghostscript القابل للتنفيذ (gs على لينكس، gswin على ويندوز). */
    private static function gsBinary(): string
    {
        $finder = new ExecutableFinder();
        $bin = $finder->find('gs') ?: $finder->find('gswin64c') ?: $finder->find('gswin32c');
        if (! $bin) {
            throw new RuntimeException('Ghostscript غير متوفّر على الخادم.');
        }
        return $bin;
    }

    /** عدد صفحات ملف الـ PDF عبر Ghostscript. */
    public static function pageCount(string $absPdf): int
    {
        if (! is_file($absPdf)) {
            throw new RuntimeException('الملف غير موجود.');
        }

        $script = '(' . str_replace(['\\', '('], ['/', '\\('], $absPdf) . ') (r) file runpdfbegin pdfpagecount = quit';

        $process = new Process([
            self::gsBinary(), '-q', '-dNODISPLAY', '-dNOSAFER', '-c', $script,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return max((int) trim($process->getOutput()), 0);
    }

    /**
     * يضمن وجود صورة الصفحة في الكاش (يُصيّرها مرة واحدة)، ويعيد مسارها المطلق.
     * الصورة الخام بلا علامة مائية — العلامة تُضاف per-request فوقها.
     */
    public static function ensurePagePng(int $documentId, string $absPdf, int $page): string
    {
        $dir = Storage::disk(self::DISK)->path(self::CACHE_PREFIX . '/' . $documentId);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $png = $dir . DIRECTORY_SEPARATOR . 'p' . $page . '.png';
        if (is_file($png) && filesize($png) > 0) {
            return $png; // موجود في الكاش
        }

        $process = new Process([
            self::gsBinary(),
            '-dSAFER', '-dBATCH', '-dNOPAUSE',
            '-sDEVICE=png16m',
            '-r' . self::DPI,
            '-dFirstPage=' . $page,
            '-dLastPage=' . $page,
            '-dUseCropBox',
            '-sOutputFile=' . $png,
            $absPdf,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($png)) {
            throw new ProcessFailedException($process);
        }

        return $png;
    }

    /** حذف كاش صور مذكرة (يُستدعى عند حذف/استبدال الملف). */
    public static function clearCache(int $documentId): void
    {
        $dir = self::CACHE_PREFIX . '/' . $documentId;
        Storage::disk(self::DISK)->deleteDirectory($dir);
    }
}
