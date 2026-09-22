<?php

namespace App\Support;

/**
 * حرق علامة مائية خفيفة ومتفرّقة داخل صورة الصفحة: اسم المشاهد فقط (بلا هاتف/IP).
 * يُرسَم النص العربي بشكل صحيح عبر Pango + خط Amiri (تشكيل وربط حروف سليم)،
 * برمادي فاتح وشفافية منخفضة وتوزيع متباعد ليكون هادئاً وغير مزعج للقراءة.
 * أي فشل (لا Imagick/Pango/خط) لا يمنع العرض — تُعاد الصورة كما هي.
 */
class Watermark
{
    private const FONT = 'Amiri';   // مثبّت في ~/.fonts على الخادم (خط عربي مفتوح)

    public static function stamp(string $pngPath, string $name): string
    {
        if (! extension_loaded('imagick') || trim($name) === '') {
            return (string) file_get_contents($pngPath);
        }

        self::ensureFont();

        try {
            $page = new \Imagick();
            $page->readImage($pngPath);
            $w = $page->getImageWidth();
            $h = $page->getImageHeight();

            // بلاطة نص الاسم (Amiri) رمادي فاتح على خلفية شفافة
            $fs = max(20, (int) round($w / 32));
            $esc = htmlspecialchars($name, ENT_QUOTES);

            $tile = new \Imagick();
            $tile->setBackgroundColor(new \ImagickPixel('transparent'));
            $tile->readImage('pango:<span font="' . self::FONT . ' ' . $fs . '" foreground="#8b9098">' . $esc . '</span>');
            $tile->setImageFormat('png');
            $tile->rotateImage(new \ImagickPixel('transparent'), -30);
            // خفّة: تقليل الشفافية إلى ~13%
            $tile->evaluateImage(\Imagick::EVALUATE_MULTIPLY, 0.13, \Imagick::CHANNEL_ALPHA);

            $tw = $tile->getImageWidth();
            $th = $tile->getImageHeight();

            // توزيع متفرّق (قليل الكثافة): مسافات واسعة + إزاحة صفوف للتبعثر
            $stepX = max($tw + 140, (int) round($w * 0.52));
            $stepY = max($th + 140, (int) round($h * 0.30));
            $row = 0;
            for ($y = -$th; $y < $h + $th; $y += $stepY) {
                $offset = ($row % 2) ? (int) round($stepX / 2) : 0;
                for ($x = -$tw + $offset; $x < $w + $tw; $x += $stepX) {
                    $page->compositeImage($tile, \Imagick::COMPOSITE_OVER, (int) $x, (int) $y);
                }
                $row++;
            }

            $page->setImageFormat('png');
            $blob = $page->getImageBlob();

            $tile->clear(); $tile->destroy();
            $page->clear(); $page->destroy();

            return $blob;
        } catch (\Throwable $e) {
            // فشل الختم (خط مفقود مثلاً) → لا نكسر العرض
            return (string) file_get_contents($pngPath);
        }
    }

    /**
     * self-heal: يضمن وجود خط Amiri العربي في ~/.fonts (ينسخه من المستودع ويحدّث
     * كاش الخطوط إن غاب) حتى لا تنكسر العلامة المائية صامتةً بعد إعادة بناء الخادم.
     * يعمل مرّة واحدة فقط (يتخطّى فوراً إن كان الخط موجوداً).
     */
    private static function ensureFont(): void
    {
        try {
            $home = getenv('HOME') ?: null;
            if (! $home) {
                return;
            }
            $dest = $home . '/.fonts/Amiri-Regular.ttf';
            if (is_file($dest)) {
                return; // موجود — لا عمل
            }
            $bundled = resource_path('fonts/Amiri-Regular.ttf');
            if (! is_file($bundled)) {
                return;
            }
            @mkdir(dirname($dest), 0755, true);
            @copy($bundled, $dest);

            $fc = (new \Symfony\Component\Process\ExecutableFinder())->find('fc-cache');
            if ($fc) {
                $p = new \Symfony\Component\Process\Process([$fc, '-f', dirname($dest)]);
                $p->setTimeout(30);
                $p->run();
            }
        } catch (\Throwable $e) {
            // تجاهل — الختم سيسقط بأمان إن ظل الخط غائباً
        }
    }
}
