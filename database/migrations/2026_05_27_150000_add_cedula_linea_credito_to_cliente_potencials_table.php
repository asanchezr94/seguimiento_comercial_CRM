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
        Schema::table('cliente_potencials', function (Blueprint $table) {
            if (! Schema::hasColumn('cliente_potencials', 'cedula')) {
                $table->string('cedula', 30)->nullable()->after('nombre');
            }

            if (! Schema::hasColumn('cliente_potencials', 'linea_credito')) {
                $table->string('linea_credito', 50)->nullable()->after('cedula');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cliente_potencials', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('cliente_potencials', 'cedula') ? 'cedula' : null,
                Schema::hasColumn('cliente_potencials', 'linea_credito') ? 'linea_credito' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
