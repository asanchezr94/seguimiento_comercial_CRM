<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gestions', function (Blueprint $table) {
            if (! Schema::hasColumn('gestions', 'es_vinculacion')) {
                $table->boolean('es_vinculacion')->default(false)->after('minutos_invertidos');
            }
            if (! Schema::hasColumn('gestions', 'es_ahorro')) {
                $table->boolean('es_ahorro')->default(false)->after('es_vinculacion');
            }
            if (! Schema::hasColumn('gestions', 'linea_credito_gestion')) {
                $table->string('linea_credito_gestion', 50)->nullable()->after('es_ahorro');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gestions', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('gestions', 'linea_credito_gestion') ? 'linea_credito_gestion' : null,
                Schema::hasColumn('gestions', 'es_ahorro') ? 'es_ahorro' : null,
                Schema::hasColumn('gestions', 'es_vinculacion') ? 'es_vinculacion' : null,
            ]);

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
