<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = [
        'school_class_id',
        'name',
        'position',
    ];

    protected $casts = ['position' => 'integer'];

    /** المادة الجديدة تُلحق في نهاية الترتيب داخل صفّها. */
    protected static function booted(): void
    {
        static::creating(function (Subject $subject) {
            if (empty($subject->position)) {
                $max = self::where('school_class_id', $subject->school_class_id)->max('position');
                $subject->position = (int) $max + 1;
            }
        });
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** مذكرة واحدة لكل مادة (اختيارية). */
    public function document()
    {
        return $this->hasOne(Document::class);
    }
}
