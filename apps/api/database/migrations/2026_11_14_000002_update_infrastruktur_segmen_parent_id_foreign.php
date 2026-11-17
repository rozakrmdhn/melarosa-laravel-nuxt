<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE "infrastruktur_segmen" DROP CONSTRAINT IF EXISTS "infrastruktur_segmen_parent_id_foreign"');

            if (Schema::hasTable('jalan_porosdesa')) {
                $hasPkOrUnique = DB::selectOne("
                    SELECT 1
                    FROM pg_index i
                    JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                    WHERE i.indrelid = 'jalan_porosdesa'::regclass
                      AND (i.indisprimary OR i.indisunique)
                      AND i.indnatts = 1
                      AND a.attname = 'id'
                    LIMIT 1
                ");

                if (!$hasPkOrUnique) {
                    DB::statement('ALTER TABLE "jalan_porosdesa" ADD CONSTRAINT "jalan_porosdesa_id_unique" UNIQUE ("id")');
                }

                Schema::table('infrastruktur_segmen', function (Blueprint $table) {
                    $table->foreign('parent_id')
                        ->references('id')
                        ->on('jalan_porosdesa')
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE "infrastruktur_segmen" DROP CONSTRAINT IF EXISTS "infrastruktur_segmen_parent_id_foreign"');

            Schema::table('infrastruktur_segmen', function (Blueprint $table) {
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('infrastruktur_segmen')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }
    }
};
