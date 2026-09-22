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

            $fs = max(20, (int) round($w / 30));

            $draw = new \ImagickDraw();
            $draw->setFont($font);                 // مسار صريح → لا fontconfig
            $draw->setFontSize($fs);
            $draw->setFillColor(new \ImagickPixel('rgba(120,126,136,0.14)')); // رمادي فاتح شفّاف
            $draw->setStrokeColor(new \ImagickPixel('transparent'));

            // قياس أبعاد النص لضبط التباعد
            $m = $page->queryFontMetrics($draw, $name);
            $tw = max(1, (int) round($m['textWidth']));
            $th = max(1, (int) round($m['textHeight']));

            // توزيع متفرّق (قليل الكثافة): مسافات واسعة + إزاحة صفوف للتبعثر
            $stepX = max($tw + 170, (int) round($w * 0.55));
            $stepY = max($th + 190, (int) round($h * 0.30));

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
