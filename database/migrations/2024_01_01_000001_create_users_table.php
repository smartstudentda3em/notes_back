<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();          // تسجيل الدخول برقم التليفون
            $table->string('password');
            $table->enum('role', ['admin_press', 'assistant', 'teacher'])->default('teacher');
            $table->json('stages')->nullable();            // للمدرس: مراحله | للمساعد: المراحل المسموح له بها
            $table->json('allowed_teachers')->nullable();  // للمساعد: قائمة معرّفات المدرسين المسموح بهم (فارغ = الكل)
            $table->string('scope')->nullable();           // للمساعد: 'print' (طباعة فقط) | 'manage' (إضافة/تعديل + طباعة)
            $table->boolean('is_active')->default(true);   // لإيقاف/تفعيل الحساب
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
