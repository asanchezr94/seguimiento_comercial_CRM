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
        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->string('cedula', 30)->nullable()->after('nombre')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->dropColumn('cedula');
        });
    }
};
