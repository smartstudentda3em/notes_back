<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// الصفوف الدراسية التي ينشئها المدرس داخل كل مرحلة مخصّصة له
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stage');   // المرحلة (ضمن مراحل المدرس)
            $table->string('name');    // اسم الصف (مثال: الصف الأول)
            $table->unsignedInteger('position')->default(0); // للترتيب التسلسلي/اليدوي
            $table->timestamps();

            // لا يتكرر نفس اسم الصف داخل نفس المرحلة لنفس المدرس
            $table->unique(['user_id', 'stage', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
