<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gestions', function (Blueprint $table) {
            if (! Schema::hasColumn('gestions', 'linea_ahorro')) {
                $table->string('linea_ahorro', 50)->nullable()->after('linea_credito_gestion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gestions', function (Blueprint $table) {
            if (Schema::hasColumn('gestions', 'linea_ahorro')) {
                $table->dropColumn('linea_ahorro');
            }
        });
    }
};
