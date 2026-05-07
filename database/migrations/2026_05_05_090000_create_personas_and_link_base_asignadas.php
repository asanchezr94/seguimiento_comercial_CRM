<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('cedula', 30)->unique();
            $table->string('nombre')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->foreignId('persona_id')->nullable()->after('estado_id')->constrained('personas')->nullOnDelete();
            $table->index('persona_id');
        });

        $rows = DB::table('base_asignadas')
            ->select('id', 'cedula', 'nombre', 'telefono', 'email')
            ->whereNotNull('cedula')
            ->where('cedula', '!=', '')
            ->orderBy('id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $cedula = trim((string) $row->cedula);
            if ($cedula === '') {
                continue;
            }
            if (!isset($map[$cedula])) {
                $existing = DB::table('personas')->where('cedula', $cedula)->first();
                if ($existing) {
                    $map[$cedula] = (int) $existing->id;
                } else {
                    $id = DB::table('personas')->insertGetId([
                        'cedula' => $cedula,
                        'nombre' => $row->nombre ?: null,
                        'telefono' => $row->telefono ?: null,
                        'email' => $row->email ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $map[$cedula] = (int) $id;
                }
            }
            DB::table('base_asignadas')->where('id', $row->id)->update(['persona_id' => $map[$cedula]]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('base_asignadas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('persona_id');
        });

        Schema::dropIfExists('personas');
    }
};
