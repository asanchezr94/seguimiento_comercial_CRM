<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['base_asignadas', 'cliente_potencials'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'desembolso_estado')) {
                    $table->string('desembolso_estado', 50)->nullable()->after('efectivo');
                }
                if (! Schema::hasColumn($tableName, 'desembolso_estado_pendiente')) {
                    $table->string('desembolso_estado_pendiente', 50)->nullable()->after('desembolso_estado');
                }
                if (! Schema::hasColumn($tableName, 'desembolso_solicitado_at')) {
                    $table->timestamp('desembolso_solicitado_at')->nullable()->after('desembolso_estado_pendiente');
                }
                if (! Schema::hasColumn($tableName, 'desembolso_solicitado_por')) {
                    $table->foreignId('desembolso_solicitado_por')->nullable()->after('desembolso_solicitado_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'desembolso_aprobado_at')) {
                    $table->timestamp('desembolso_aprobado_at')->nullable()->after('desembolso_solicitado_por');
                }
                if (! Schema::hasColumn($tableName, 'desembolso_motivo_devolucion')) {
                    $table->text('desembolso_motivo_devolucion')->nullable()->after('desembolso_aprobado_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['base_asignadas', 'cliente_potencials'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'desembolso_solicitado_por')) {
                    $table->dropConstrainedForeignId('desembolso_solicitado_por');
                }

                $columns = array_filter([
                    Schema::hasColumn($tableName, 'desembolso_estado') ? 'desembolso_estado' : null,
                    Schema::hasColumn($tableName, 'desembolso_estado_pendiente') ? 'desembolso_estado_pendiente' : null,
                    Schema::hasColumn($tableName, 'desembolso_solicitado_at') ? 'desembolso_solicitado_at' : null,
                    Schema::hasColumn($tableName, 'desembolso_aprobado_at') ? 'desembolso_aprobado_at' : null,
                    Schema::hasColumn($tableName, 'desembolso_motivo_devolucion') ? 'desembolso_motivo_devolucion' : null,
                ]);

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
