<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('titulo')->nullable();
            $table->string('cliente_nombre');
            $table->string('telefono', 50)->nullable();
            $table->string('direccion')->nullable();
            $table->text('detalle_inicial')->nullable();
            $table->dateTime('programada_at');
            $table->string('estado', 30)->default('programada');
            $table->text('resultado')->nullable();
            $table->dateTime('registrada_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas');
    }
};
