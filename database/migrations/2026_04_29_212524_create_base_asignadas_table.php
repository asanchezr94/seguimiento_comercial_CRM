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
        Schema::create('base_asignadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asesor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('estado_id')->nullable()->constrained('estados')->nullOnDelete();
            $table->string('nombre');
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('empresa')->nullable();
            $table->string('origen')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('base_asignadas');
    }
};
