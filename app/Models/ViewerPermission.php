<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * صف واحد في مصفوفة صلاحيات المشاهد المقيّد:
 * "يُسمح لهذا المستخدم برؤية هذه المادة المحددة (subject_id)" —
 * والمادة تابعة للمدرّس المربوط به المشاهد.
 */
class ViewerPermission extends Model
{
    protected $fillable = [
        'user_id',
        'subject_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
