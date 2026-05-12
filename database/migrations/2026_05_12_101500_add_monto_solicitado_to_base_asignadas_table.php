<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->decimal('monto_solicitado', 15, 2)->nullable()->after('linea_credito');
        });
    }

    public function down(): void
    {
        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->dropColumn('monto_solicitado');
        });
    }
};

