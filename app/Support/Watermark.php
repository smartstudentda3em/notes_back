<?php

namespace App\Support;

/**
 * حرق علامة مائية خفيفة ومتفرّقة داخل صورة الصفحة: اسم المشاهد فقط (بلا هاتف/IP).
 *
 * يُرسَم النص العربي متشكّلاً بشكل صحيح عبر annotateImage مع تحديد ملف خط Amiri
 * *بمساره المطلق من المستودع* (ImageMagick 7 مبني بـ raqm فيشكّل العربي)، ما يتجنّب
 * كلياً اعتماد Pango/fontconfig الذي يختلف بين CLI وسياق الويب. رمادي فاتح وشفافية
 * منخفضة وتوزيع متباعد ليكون هادئاً وغير مزعج. أي فشل لا يمنع العرض.
 */
class Watermark
{
    public static function stamp(string $pngPath, string $name): string
    {
        $name = trim($name);
        if (! extension_loaded('imagick') || $name === '') {
            return (string) file_get_contents($pngPath);
        }

        $font = resource_path('fonts/Amiri-Regular.ttf');
        if (! is_file($font)) {
            return (string) file_get_contents($pngPath);
        }

        try {
            $page = new \Imagick();
            $page->readImage($pngPath);
            $w = $page->getImageWidth();
            $h = $page->getImageHeight();

            $fs = max(22, (int) round($w / 28));

            $draw = new \ImagickDraw();
            $draw->setFont($font);                 // مسار صريح → لا fontconfig
            $draw->setFontSize($fs);
            // رمادي واضح لكن هادئ (~24%): باين وقابل للتتبّع دون إزعاج القراءة
            $draw->setFillColor(new \ImagickPixel('rgba(88,96,110,0.24)'));
            $draw->setStrokeColor(new \ImagickPixel('transparent'));

            // قياس أبعاد النص لضبط التباعد
            $m = $page->queryFontMetrics($draw, $name);
            $tw = max(1, (int) round($m['textWidth']));
            $th = max(1, (int) round($m['textHeight']));

            // توزيع متوازن: متفرّق ومريح، لكن يغطي الصفحة فلا يبدو غائباً
            $stepX = max($tw + 110, (int) round($w * 0.44));
            $stepY = max($th + 130, (int) round($h * 0.23));

            $row = 0;
            for ($y = $th; $y < $h + $stepY; $y += $stepY) {
                $offset = ($row % 2) ? (int) round($stepX / 2) : 0;
                for ($x = -$stepX + $offset; $x < $w + $stepX; $x += $stepX) {
                    $page->annotateImage($draw, $x, $y, -30, $name);
                }
                $row++;
            }

            $page->setImageFormat('png');
            $blob = $page->getImageBlob();
            $page->clear();
            $page->destroy();

            return $blob;
        } catch (\Throwable $e) {
            return (string) file_get_contents($pngPath);
        }
    }
}
