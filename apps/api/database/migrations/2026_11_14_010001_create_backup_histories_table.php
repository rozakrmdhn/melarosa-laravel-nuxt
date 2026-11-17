<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('backup_name');
            $table->string('backup_type', 20);
            $table->string('trigger_type', 20)->default('manual');
            $table->string('status', 20)->default('pending');
            $table->string('disk', 50)->default('backups');
            $table->string('remote_disk', 50)->nullable();
            $table->string('remote_path')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('checksum_sha256', 64)->nullable();
            $table->boolean('is_encrypted')->default(true);
            $table->string('encryption_algorithm', 32)->nullable();
            $table->string('database_name')->nullable();
            $table->string('database_version')->nullable();
            $table->string('postgis_version')->nullable();
            $table->boolean('includes_files')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedSmallInteger('retention_days')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['backup_type', 'status']);
            $table->index('trigger_type');
            $table->index('created_at');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_histories');
    }
};

