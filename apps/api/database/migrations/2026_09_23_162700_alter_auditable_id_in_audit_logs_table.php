<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alter column to varchar(64) using raw SQL for reliable PostgreSQL support
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN auditable_id TYPE VARCHAR(64) USING auditable_id::text');
        } else {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->string('auditable_id', 64)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN auditable_id TYPE BIGINT USING auditable_id::bigint');
        } else {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('auditable_id')->nullable()->change();
            });
        }
    }
};
