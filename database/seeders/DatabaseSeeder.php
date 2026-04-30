<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            EstadoSeeder::class,
        ]);

        User::updateOrCreate(
            ['email' => 'supervisor@demo.com'],
            ['name' => 'Supervisor Demo', 'role' => 'supervisor', 'password' => Hash::make('password')]
        );

        User::updateOrCreate(
            ['email' => 'comercial1@demo.com'],
            ['name' => 'Comercial Uno', 'role' => 'comercial', 'password' => Hash::make('password')]
        );

        User::updateOrCreate(
            ['email' => 'comercial2@demo.com'],
            ['name' => 'Comercial Dos', 'role' => 'comercial', 'password' => Hash::make('password')]
        );
    }
}
