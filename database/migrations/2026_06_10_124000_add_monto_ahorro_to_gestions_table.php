<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gestions', function (Blueprint $table) {
            if (! Schema::hasColumn('gestions', 'monto_ahorro')) {
                $table->decimal('monto_ahorro', 15, 2)->nullable()->after('linea_ahorro');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gestions', function (Blueprint $table) {
            if (Schema::hasColumn('gestions', 'monto_ahorro')) {
                $table->dropColumn('monto_ahorro');
            }
        });
    }
};
