<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visitas')) {
            return;
        }

        DB::statement('ALTER TABLE visitas MODIFY programada_at DATETIME NOT NULL');
        DB::statement('ALTER TABLE visitas MODIFY finaliza_at DATETIME NULL');
        DB::statement('ALTER TABLE visitas MODIFY registrada_at DATETIME NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('visitas')) {
            return;
        }

        DB::statement('ALTER TABLE visitas MODIFY programada_at TIMESTAMP NOT NULL');
        DB::statement('ALTER TABLE visitas MODIFY finaliza_at TIMESTAMP NULL');
        DB::statement('ALTER TABLE visitas MODIFY registrada_at TIMESTAMP NULL');
    }
};
