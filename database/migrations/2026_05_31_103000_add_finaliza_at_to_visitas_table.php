<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('visitas', 'finaliza_at')) {
            return;
        }

        Schema::table('visitas', function (Blueprint $table) {
            $table->timestamp('finaliza_at')->nullable()->after('programada_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('visitas', 'finaliza_at')) {
            return;
        }

        Schema::table('visitas', function (Blueprint $table) {
            $table->dropColumn('finaliza_at');
        });
    }
};
