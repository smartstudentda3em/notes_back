<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintLog extends Model
{
    protected $fillable = [
        'actor_id', 'actor_name', 'actor_role',
        'teacher_id', 'teacher_name',
        'document_id', 'memo_title', 'subject_name', 'class_name', 'stage',
        'copies', 'printed_at',
    ];

    protected $casts = [
        'copies'     => 'integer',
        'printed_at' => 'datetime',
    ];
}
