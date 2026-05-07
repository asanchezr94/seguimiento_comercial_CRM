<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->string('lote_uid', 120)->nullable()->after('supervisor_id');
            $table->index('lote_uid');
        });

        $lotes = DB::table('base_asignadas')
            ->select('lote_nombre')
            ->whereNotNull('lote_nombre')
            ->where('lote_nombre', '!=', '')
            ->distinct()
            ->pluck('lote_nombre');

        foreach ($lotes as $loteNombre) {
            $uid = Str::slug((string) $loteNombre) . '-legacy';
            DB::table('base_asignadas')
                ->where('lote_nombre', $loteNombre)
                ->update(['lote_uid' => $uid]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->dropIndex(['lote_uid']);
            $table->dropColumn('lote_uid');
        });
    }
};
