<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::Administrator,
        ]);

        $preparador = User::factory()->create([
            'name' => 'Preparador de prueba',
            'email' => 'preparador@example.com',
            'role' => UserRole::Preparer,
        ]);

        User::factory()->create([
            'name' => 'Cliente de prueba',
            'email' => 'cliente@example.com',
            'role' => UserRole::Client,
            'preparer_id' => $preparador->id,
        ]);
    }
}
