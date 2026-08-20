<?php

namespace App\Http\Controllers;

use App\Models\PrintLog;
use Illuminate\Http\Request;

class PrintLogController extends Controller
{
    /** سجلّ الطباعة + عدّادات، مع فلترة (تاريخ / مدرس / مادة). */
    public function index(Request $request)
    {
        $q = PrintLog::query()->orderByDesc('printed_at');

        if ($request->filled('from'))       $q->whereDate('printed_at', '>=', $request->query('from'));
        if ($request->filled('to'))         $q->whereDate('printed_at', '<=', $request->query('to'));
        if ($request->filled('teacher_id')) $q->where('teacher_id', $request->query('teacher_id'));
        if ($request->filled('subject'))    $q->where('subject_name', 'like', '%' . $request->query('subject') . '%');

        // ملخّص النتائج المفلترة
        $summary = [
            'operations' => (clone $q)->count(),
            'copies'     => (int) (clone $q)->sum('copies'),
        ];

        $logs = $q->limit(500)->get([
            'id', 'actor_name', 'actor_role', 'teacher_name',
            'memo_title', 'subject_name', 'class_name', 'stage', 'copies', 'printed_at',
        ]);

        return response()->json([
            'grand_total_copies'     => (int) PrintLog::sum('copies'),
            'grand_total_operations' => PrintLog::count(),
            'summary'                => $summary,
            'logs'                   => $logs,
        ]);
    }
}
