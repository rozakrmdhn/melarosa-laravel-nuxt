<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('backup_type', 20)->unique();
            $table->boolean('is_active')->default(true);
            $table->string('frequency', 20)->default('daily');
            $table->string('cron_expression', 100)->nullable();
            $table->string('run_time', 5)->default('01:00');
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->unsignedSmallInteger('retention_days')->default(14);
            $table->boolean('encryption_enabled')->default(true);
            $table->boolean('replicate_to_remote')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};

