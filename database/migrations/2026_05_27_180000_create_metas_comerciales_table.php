<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('metas_comerciales')) {
            return;
        }

        Schema::create('metas_comerciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->decimal('monto_meta', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'mes', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metas_comerciales');
    }
};
