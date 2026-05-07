<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->timestamp('asignado_at')->nullable()->after('asesor_id');
            $table->timestamp('ultima_gestion_at')->nullable()->after('cierre_solicitado_por');
        });

        DB::table('base_asignadas')
            ->whereNotNull('asesor_id')
            ->whereNull('asignado_at')
            ->update(['asignado_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->dropColumn(['asignado_at', 'ultima_gestion_at']);
        });
    }
};

