<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مصفوفة صلاحيات "المشاهد المقيّد": كل صف = مادة محددة (subject_id) مسموح له برؤيتها.
 * كل مادة تابعة لصف يخص المدرّس المربوط به المشاهد (users.teacher_id)، فالمعرّف يحدّد
 * بدقة (الصف/الرتبة + اسم المادة + المذكرة) بلا مطابقة نصية.
 * اصطلاح حاسم: لا صفوف = لا يرى شيئاً (عكس منطق المساعد null=الكل).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viewer_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viewer_permissions');
    }
};
