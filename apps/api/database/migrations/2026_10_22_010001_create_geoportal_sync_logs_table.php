<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('geoportal_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('triggered_by', 50)->default('scheduler')
                ->comment('scheduler, manual_admin, cli');
            $table->string('status', 30)->default('running')
                ->comment('running, success, failed');
            $table->integer('records_found')->default(0);
            $table->integer('records_inserted')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->text('error_message')->nullable();
            $table->float('duration_seconds')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geoportal_sync_logs');
    }
};
