<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente_potencials', function (Blueprint $table) {
            if (! Schema::hasColumn('cliente_potencials', 'monto_solicitado')) {
                $table->decimal('monto_solicitado', 15, 2)->nullable()->after('linea_credito');
            }

            if (! Schema::hasColumn('cliente_potencials', 'efectivo')) {
                $table->boolean('efectivo')->nullable()->after('monto_solicitado');
            }

            if (! Schema::hasColumn('cliente_potencials', 'monto_linea_credito')) {
                $table->decimal('monto_linea_credito', 15, 2)->nullable()->after('efectivo');
            }

            if (! Schema::hasColumn('cliente_potencials', 'cierre_solicitado_at')) {
                $table->timestamp('cierre_solicitado_at')->nullable()->after('monto_linea_credito');
            }

            if (! Schema::hasColumn('cliente_potencials', 'cierre_solicitado_por')) {
                $table->foreignId('cierre_solicitado_por')->nullable()->after('cierre_solicitado_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('cliente_potencials', 'cierre_aprobado_at')) {
                $table->timestamp('cierre_aprobado_at')->nullable()->after('cierre_solicitado_por');
            }

            if (! Schema::hasColumn('cliente_potencials', 'motivo_devolucion')) {
                $table->text('motivo_devolucion')->nullable()->after('cierre_aprobado_at');
            }

            if (! Schema::hasColumn('cliente_potencials', 'ultima_gestion_at')) {
                $table->timestamp('ultima_gestion_at')->nullable()->after('motivo_devolucion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cliente_potencials', function (Blueprint $table) {
            if (Schema::hasColumn('cliente_potencials', 'cierre_solicitado_por')) {
                $table->dropConstrainedForeignId('cierre_solicitado_por');
            }

            $columns = array_filter([
                Schema::hasColumn('cliente_potencials', 'monto_solicitado') ? 'monto_solicitado' : null,
                Schema::hasColumn('cliente_potencials', 'efectivo') ? 'efectivo' : null,
                Schema::hasColumn('cliente_potencials', 'monto_linea_credito') ? 'monto_linea_credito' : null,
                Schema::hasColumn('cliente_potencials', 'cierre_solicitado_at') ? 'cierre_solicitado_at' : null,
                Schema::hasColumn('cliente_potencials', 'cierre_aprobado_at') ? 'cierre_aprobado_at' : null,
                Schema::hasColumn('cliente_potencials', 'motivo_devolucion') ? 'motivo_devolucion' : null,
                Schema::hasColumn('cliente_potencials', 'ultima_gestion_at') ? 'ultima_gestion_at' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
