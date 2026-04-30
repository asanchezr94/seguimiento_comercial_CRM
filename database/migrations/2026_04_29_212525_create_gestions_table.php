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
        Schema::create('gestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asesor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('estado_id')->nullable()->constrained('estados')->nullOnDelete();
            $table->foreignId('base_asignada_id')->nullable()->constrained('base_asignadas')->cascadeOnDelete();
            $table->foreignId('cliente_potencial_id')->nullable()->constrained('cliente_potencials')->cascadeOnDelete();
            $table->string('tipo')->default('llamada');
            $table->text('detalle');
            $table->timestamp('proxima_gestion_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gestions');
    }
};
