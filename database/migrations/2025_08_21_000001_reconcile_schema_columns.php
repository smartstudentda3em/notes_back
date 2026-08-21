<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration توفيقي آمن (Idempotent) — يضمن وجود كل الأعمدة المطلوبة على أي بيئة
 * حتى لو رُحّلت قبل إضافتها. لا يمسح أي بيانات، ويتخطّى أي عمود موجود بالفعل.
 * (متوافق مع SQLite و MySQL.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'stages'))           $t->json('stages')->nullable();
            if (! Schema::hasColumn('users', 'allowed_teachers')) $t->json('allowed_teachers')->nullable();
            if (! Schema::hasColumn('users', 'scope'))            $t->string('scope')->nullable();
            if (! Schema::hasColumn('users', 'is_active'))        $t->boolean('is_active')->default(true);
        });

        if (Schema::hasTable('school_classes') && ! Schema::hasColumn('school_classes', 'position')) {
            Schema::table('school_classes', fn (Blueprint $t) => $t->unsignedInteger('position')->default(0));
        }

        if (Schema::hasTable('subjects') && ! Schema::hasColumn('subjects', 'position')) {
            Schema::table('subjects', fn (Blueprint $t) => $t->unsignedInteger('position')->default(0));
        }
    }

    public function down(): void
    {
        // migration توفيقي إضافي — لا تراجع (لا نحذف أعمدة تحمل بيانات)
    }
};
