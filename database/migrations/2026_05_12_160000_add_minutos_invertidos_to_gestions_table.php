<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gestions', function (Blueprint $table) {
            $table->unsignedInteger('minutos_invertidos')->nullable()->after('proxima_gestion_at');
        });
    }

    public function down(): void
    {
        Schema::table('gestions', function (Blueprint $table) {
            $table->dropColumn('minutos_invertidos');
        });
    }
};

