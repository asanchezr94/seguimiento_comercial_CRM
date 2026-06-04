<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cliente_potencials', 'lote_nombre')) {
            return;
        }

        Schema::table('cliente_potencials', function (Blueprint $table) {
            $table->string('lote_nombre')->nullable()->after('asesor_id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cliente_potencials', 'lote_nombre')) {
            return;
        }

        Schema::table('cliente_potencials', function (Blueprint $table) {
            $table->dropColumn('lote_nombre');
        });
    }
};
