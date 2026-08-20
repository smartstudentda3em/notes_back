<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'subject_id',
        'user_id',
        'title',
        'file_path',
        'original_name',
        'size',
    ];

    // إخفاء المسار الحقيقي للملف عن أي استجابة JSON (منع تسريب المسار)
    protected $hidden = [
        'file_path',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
