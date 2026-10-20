<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bkkd_proposal_lampiran', function (Blueprint $table) {
            $table->string('tipe_sumber', 20)->default('file')->after('jenis_dokumen'); // 'file' or 'link'
            $table->text('url_link')->nullable()->after('tipe_sumber');
            $table->string('path')->nullable()->change();
            $table->string('mime_type')->nullable()->change();
            $table->bigInteger('ukuran_byte')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bkkd_proposal_lampiran', function (Blueprint $table) {
            $table->dropColumn(['tipe_sumber', 'url_link']);
            $table->string('path')->nullable(false)->change();
            $table->string('mime_type')->nullable(false)->change();
            $table->bigInteger('ukuran_byte')->nullable(false)->change();
        });
    }
};
