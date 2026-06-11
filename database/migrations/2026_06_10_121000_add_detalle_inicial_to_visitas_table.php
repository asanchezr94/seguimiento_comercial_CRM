<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visitas') || Schema::hasColumn('visitas', 'detalle_inicial')) {
            return;
        }

        Schema::table('visitas', function (Blueprint $table) {
            $table->text('detalle_inicial')->nullable()->after('direccion');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visitas') || ! Schema::hasColumn('visitas', 'detalle_inicial')) {
            return;
        }

        Schema::table('visitas', function (Blueprint $table) {
            $table->dropColumn('detalle_inicial');
        });
    }
};
