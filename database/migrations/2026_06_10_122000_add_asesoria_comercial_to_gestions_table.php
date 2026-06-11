<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gestions') || Schema::hasColumn('gestions', 'es_asesoria_comercial')) {
            return;
        }

        Schema::table('gestions', function (Blueprint $table) {
            $table->boolean('es_asesoria_comercial')->default(false)->after('es_ahorro');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gestions') || ! Schema::hasColumn('gestions', 'es_asesoria_comercial')) {
            return;
        }

        Schema::table('gestions', function (Blueprint $table) {
            $table->dropColumn('es_asesoria_comercial');
        });
    }
};
