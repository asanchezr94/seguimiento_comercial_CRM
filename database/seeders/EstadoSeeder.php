<?php

namespace Database\Seeders;

use App\Models\Estado;
use Illuminate\Database\Seeder;

class EstadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $estados = [
            ['nombre' => 'Nuevo', 'slug' => 'nuevo'],
            ['nombre' => 'Contactado', 'slug' => 'contactado'],
            ['nombre' => 'Interesado', 'slug' => 'interesado'],
            ['nombre' => 'No interesado', 'slug' => 'no-interesado'],
            ['nombre' => 'Cerrado', 'slug' => 'cerrado'],
            ['nombre' => 'Pendiente de aprobacion (supervisor)', 'slug' => 'pendiente-aprobacion-supervisor'],
            ['nombre' => 'Devuelta', 'slug' => 'devuelta'],
            ['nombre' => 'Efectiva', 'slug' => 'efectiva'],
        ];

        foreach ($estados as $estado) {
            Estado::updateOrCreate(['slug' => $estado['slug']], $estado + ['activo' => true]);
        }
    }
}
