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
            $table->boolean('efectivo')->nullable()->after('linea_credito');
            $table->decimal('monto_linea_credito', 15, 2)->nullable()->after('efectivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->dropColumn(['efectivo', 'monto_linea_credito']);
        });
    }
};
