<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('layers')) {
            Schema::create('layers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('protocol', 255);
                $table->string('url', 500);
                $table->string('layer_name', 255)->nullable()->unique();
                $table->boolean('is_active')->default(true);
                $table->boolean('default_visible')->default(false);
                $table->double('opacity')->default(1.0);
                $table->integer('order')->default(0);
                $table->string('attribution', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('source_type', 50)->default('internal');
                $table->boolean('is_synced')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('layers');
    }
};
