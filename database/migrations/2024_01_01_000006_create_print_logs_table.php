<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// سجلّ عمليات الطباعة (بيانات مُنسَّخة لتبقى حتى لو حُذف المدرس/المذكرة)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name');
            $table->string('actor_role')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->string('teacher_name')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('memo_title')->nullable();
            $table->string('subject_name')->nullable();
            $table->string('class_name')->nullable();
            $table->string('stage')->nullable();
            $table->unsignedInteger('copies')->default(1);
            $table->timestamp('printed_at');
            $table->timestamps();

            $table->index('printed_at');
            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_logs');
    }
};
