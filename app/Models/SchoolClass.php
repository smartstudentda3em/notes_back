<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $fillable = [
        'user_id',
        'stage',
        'name',
        'position',
    ];

    protected $casts = ['position' => 'integer'];

    /** ترتيب الصفوف تلقائياً حسب الترتيب الأبجدي/الرقمي للاسم عند الإنشاء. */
    protected static function booted(): void
    {
        static::creating(function (SchoolClass $class) {
            if (empty($class->position)) {
                $class->position = self::ordinalRank($class->name);
            }
        });
    }

    /** استخراج رتبة رقمية من اسم الصف (الأول=1، الثاني=2 ...) لضمان التسلسل. */
    public static function ordinalRank(string $name): int
    {
        $map = [
            'أول' => 1, 'الأول' => 1, 'الأولى' => 1, 'اول' => 1,
            'ثاني' => 2, 'الثاني' => 2, 'الثانية' => 2,
            'ثالث' => 3, 'الثالث' => 3, 'الثالثة' => 3,
            'رابع' => 4, 'الرابع' => 4,
            'خامس' => 5, 'الخامس' => 5,
            'سادس' => 6, 'السادس' => 6,
            'سابع' => 7, 'السابع' => 7,
            'ثامن' => 8, 'الثامن' => 8,
            'تاسع' => 9, 'التاسع' => 9,
            'عاشر' => 10, 'العاشر' => 10,
        ];

        foreach ($map as $word => $rank) {
            if (mb_strpos($name, $word) !== false) {
                return $rank;
            }
        }

        // أرقام لاتينية/عربية في الاسم
        if (preg_match('/(\d+)/u', self::toLatinDigits($name), $m)) {
            return (int) $m[1];
        }

        return 900; // بلا ترتيب معروف → يُلحق في النهاية
    }

    private static function toLatinDigits(string $s): string
    {
        return strtr($s, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class)->orderBy('position')->orderBy('id');
    }
}
