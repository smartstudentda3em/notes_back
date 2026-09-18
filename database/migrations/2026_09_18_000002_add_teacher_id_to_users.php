<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط "المشاهد المقيّد" بمدرّس واحد فقط: كل محتواه محصور في هذا المدرّس،
 * ثم يُقيَّد أكثر عبر مصفوفة المواد (viewer_permissions).
 * NULL = غير مربوط بمدرّس (لا يرى شيئاً). عند حذف المدرّس → NULL (لا يرى شيئاً).
 * لا يُستعمل هذا العمود للأدوار الأخرى.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->after('scope')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_id');
        });
    }
};
