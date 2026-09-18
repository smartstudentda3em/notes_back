<?php

namespace App\Support;

/**
 * حرق علامة مائية قطرية شبه شفّافة داخل صورة صفحة PNG (الهاتف + IP + الوقت).
 * نصّ لاتيني/أرقام فقط ليُرسَم بشكل صحيح دون الحاجة لتشكيل عربي.
 * يفضّل Imagick؛ وإلا يسقط إلى GD؛ وإلا يعيد الصورة كما هي (فشل العلامة لا يمنع العرض).
 */
class Watermark
{
    /** يعيد بايتات PNG للصفحة بعد ختمها بالعلامة المائية. */
    public static function stamp(string $pngPath, string $text): string
    {
        if (extension_loaded('imagick')) {
            try {
                return self::withImagick($pngPath, $text);
            } catch (\Throwable $e) {
                // نكمل للبديل
            }
        }

        if (extension_loaded('gd')) {
            try {
                return self::withGd($pngPath, $text);
            } catch (\Throwable $e) {
                // نكمل
            }
        }

        return (string) file_get_contents($pngPath);
    }

    private static function fontFile(): ?string
    {
        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ] as $f) {
            if (is_file($f)) {
                return $f;
            }
        }
        return null;
    }

    private static function withImagick(string $pngPath, string $text): string
    {
        $im = new \Imagick();
        $im->readImage($pngPath);
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();

        $draw = new \ImagickDraw();
        $font = self::fontFile();
        if ($font) {
            $draw->setFont($font);
        }
        $fs = max(16, (int) round($w / 42));
        $draw->setFontSize($fs);
        $draw->setFillColor(new \ImagickPixel('rgba(0,0,0,0.12)'));
        $draw->setGravity(\Imagick::GRAVITY_NORTHWEST);

        $stepY = (int) round($fs * 9);
        $stepX = (int) round($fs * 24);
        for ($y = -$stepY; $y < $h + $stepY; $y += $stepY) {
            for ($x = -$stepX; $x < $w + $stepX; $x += $stepX) {
                $im->annotateImage($draw, $x, $y, -30, $text);
            }
        }

        $im->setImageFormat('png');
        $blob = $im->getImageBlob();
        $im->clear();
        $im->destroy();

        return $blob;
    }

    private static function withGd(string $pngPath, string $text): string
    {
        $src = imagecreatefrompng($pngPath);
        $w = imagesx($src);
        $h = imagesy($src);
        imagealphablending($src, true);

        $font = self::fontFile();
        $color = imagecolorallocatealpha($src, 0, 0, 0, 108); // شبه شفّاف

        if ($font) {
            $fs = max(12, (int) round($w / 60));
            $stepY = (int) round($fs * 10);
            $stepX = (int) round($fs * 26);
            for ($y = 0; $y < $h + $stepY; $y += $stepY) {
                for ($x = -$stepX; $x < $w + $stepX; $x += $stepX) {
                    imagettftext($src, $fs, 30, $x, $y, $color, $font, $text);
                }
            }
        } else {
            // بلا خط TTF: خط GD النقطي المدمج (لاتيني فقط)
            $stepY = 120;
            $stepX = 420;
            for ($y = 0; $y < $h; $y += $stepY) {
                for ($x = 0; $x < $w; $x += $stepX) {
                    imagestring($src, 5, $x, $y, $text, $color);
                }
            }
        }

        ob_start();
        imagepng($src);
        $blob = (string) ob_get_clean();
        imagedestroy($src);

        return $blob;
    }
}
