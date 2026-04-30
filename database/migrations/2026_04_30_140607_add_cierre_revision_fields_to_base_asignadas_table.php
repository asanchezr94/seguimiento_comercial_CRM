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
            $table->timestamp('cierre_solicitado_at')->nullable()->after('monto_linea_credito');
            $table->foreignId('cierre_solicitado_por')->nullable()->after('cierre_solicitado_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cierre_solicitado_por');
            $table->dropColumn('cierre_solicitado_at');
        });
    }
};
