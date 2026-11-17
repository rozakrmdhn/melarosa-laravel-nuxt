<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_restore_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('backup_history_id')->nullable()->constrained('backup_histories')->nullOnDelete();
            $table->string('source_file_name');
            $table->string('restore_scope', 20)->default('database');
            $table->string('target_database')->nullable();
            $table->string('target_mode', 20)->default('isolated');
            $table->string('status', 20)->default('pending');
            $table->foreignUuid('pre_restore_backup_id')->nullable()->constrained('backup_histories')->nullOnDelete();
            $table->json('validation_report')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_restore_histories');
    }
};

