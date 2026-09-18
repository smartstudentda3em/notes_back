<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PrintLogController;
use App\Http\Controllers\RestrictedViewerController;
use App\Http\Controllers\StructureController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\ViewerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — نظام مذكرات المطبعة
|--------------------------------------------------------------------------
| المصادقة: Laravel Sanctum (Bearer Token)
| الأدوار : admin_press (المطبعة) | teacher (المدرس)
*/

// عام — تسجيل الدخول (محمي بـ Rate Limiting ضد القوة الغاشمة)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// يتطلب توكن صالح + حدّ أقصى سخيّ للطلبات لكل مستخدم (منع DDoS دون كسر الاستخدام الطبيعي)
Route::middleware(['auth:sanctum', 'throttle:240,1'])->group(function () {

    // ----- عام لأي مستخدم مسجّل -----
    Route::get('/me',            [AuthController::class, 'me']);
    Route::post('/refresh-token', [AuthController::class, 'refresh']); // تجديد الرمز تلقائياً
    Route::post('/logout',       [AuthController::class, 'logout']);
    Route::put('/account/profile',  [AccountController::class, 'updateProfile']);
    Route::put('/account/password', [AccountController::class, 'updatePassword']);

    // ----- لوحة المدرس (teacher) -----
    Route::middleware('role:teacher')->group(function () {
        // الشجرة التعليمية (المرحلة > الصف > المادة > المذكرة)
        Route::get('/tree',                          [StructureController::class, 'tree']);

        // إدارة الهيكل الثابت: الصفوف والمواد
        Route::post('/classes',                      [StructureController::class, 'storeClass']);
        Route::post('/classes/reorder',              [StructureController::class, 'reorderClasses']);
        Route::delete('/classes/{class}',            [StructureController::class, 'destroyClass']);
        Route::post('/subjects',                     [StructureController::class, 'storeSubject']);
        Route::post('/subjects/reorder',             [StructureController::class, 'reorderSubjects']);
        Route::delete('/subjects/{subject}',         [StructureController::class, 'destroySubject']);

        // المذكرات (ملف PDF لكل مادة)
        Route::post('/subjects/{subject}/document',   [DocumentController::class, 'upload']);
        Route::put('/documents/{document}',           [DocumentController::class, 'rename']);
        Route::delete('/documents/{document}',        [DocumentController::class, 'destroy']);
        // طباعة المدرس لمذكرته (بثّ آمن)
        Route::get('/documents/{document}/stream',    [DocumentController::class, 'streamOwn']);
    });

    // ----- لوحة المطبعة (المدير + المساعدون) -----
    // الوصول للمدرسين والطباعة؛ والصلاحيات الدقيقة تُفرض داخل الـ Controllers.
    Route::middleware('role:admin_press,assistant')->prefix('admin')->group(function () {
        // إدارة المدرسين (التعديل مقيّد بنطاق المساعد داخل الـ Controller)
        Route::get('/teachers',                          [TeacherController::class, 'index']);
        Route::post('/teachers',                         [TeacherController::class, 'store']);
        Route::put('/teachers/{teacher}',                [TeacherController::class, 'update']);
        Route::put('/teachers/{teacher}/password',       [TeacherController::class, 'resetPassword']);
        Route::patch('/teachers/{teacher}/toggle',       [TeacherController::class, 'toggleActive']);
        Route::delete('/teachers/{teacher}',             [TeacherController::class, 'destroy']);

        // استعراض الشجرة التعليمية للمدرس + البثّ الآمن للطباعة (بدون تحميل)
        Route::get('/teachers/{teacher}/tree',           [DocumentController::class, 'teacherTree']);
        Route::get('/documents/{document}/stream',       [DocumentController::class, 'stream']);
    });

    // ----- إدارة المساعدين والمشاهدين (مدير المطبعة فقط) -----
    Route::middleware('role:admin_press')->prefix('admin')->group(function () {
        Route::get('/assistants',                        [AssistantController::class, 'index']);
        Route::post('/assistants',                       [AssistantController::class, 'store']);
        Route::put('/assistants/{assistant}',            [AssistantController::class, 'update']);
        Route::put('/assistants/{assistant}/password',   [AssistantController::class, 'resetPassword']);
        Route::patch('/assistants/{assistant}/toggle',   [AssistantController::class, 'toggleActive']);
        Route::delete('/assistants/{assistant}',         [AssistantController::class, 'destroy']);

        // المشاهدون المقيّدون + مصفوفة صلاحياتهم
        Route::get('/viewers',                           [ViewerController::class, 'index']);
        Route::post('/viewers',                          [ViewerController::class, 'store']);
        Route::put('/viewers/{viewer}',                  [ViewerController::class, 'update']);
        Route::put('/viewers/{viewer}/permissions',      [ViewerController::class, 'setPermissions']);
        Route::put('/viewers/{viewer}/password',         [ViewerController::class, 'resetPassword']);
        Route::patch('/viewers/{viewer}/toggle',         [ViewerController::class, 'toggleActive']);
        Route::delete('/viewers/{viewer}',               [ViewerController::class, 'destroy']);

        // سجلّ الطباعة وعدّاداته
        Route::get('/print-logs',                        [PrintLogController::class, 'index']);
    });

    // ----- واجهة المشاهد المقيّد (عرض صور مفلتر، بلا تحميل) -----
    Route::middleware('role:restricted_viewer')->prefix('viewer')->group(function () {
        Route::get('/tree',                               [RestrictedViewerController::class, 'tree']);
        Route::get('/documents/{document}/meta',          [RestrictedViewerController::class, 'meta']);
        Route::get('/documents/{document}/page/{page}',   [RestrictedViewerController::class, 'page'])
            ->whereNumber('page');
    });
});
