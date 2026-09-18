<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تحويل عمود users.role من enum إلى string لإتاحة دور جديد 'restricted_viewer'
 * دون قيد CHECK يرفض القيمة (خاصةً على SQLite في الإنتاج).
 * مدعوم أصلاً في Laravel 11 عبر ->change() بلا حاجة لـ doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('teacher')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin_press', 'assistant', 'teacher'])->default('teacher')->change();
        });
    }
};
