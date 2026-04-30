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
            $table->text('motivo_devolucion')->nullable()->after('cierre_solicitado_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->dropColumn('motivo_devolucion');
        });
    }
};
