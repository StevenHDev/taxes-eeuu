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
        $this->call(CatalogoCamposSeeder::class);
        $this->call(RelacionesDocumentoCampoSeeder::class);
        $this->call(ParametrosFiscalesSeeder::class);
        $this->call(AgentePromptsSeeder::class);

        // Usuario administrador. Se crea sin factory (no depende de faker, que
        // es require-dev y no existe en producción con --no-dev). Es idempotente
        // gracias a firstOrCreate. Define ADMIN_EMAIL / ADMIN_PASSWORD en el
        // entorno de producción; el password se hashea solo (cast 'hashed').
        User::firstOrCreate(
            ['email' => config('seeding.admin.email')],
            [
                'name' => config('seeding.admin.name'),
                'password' => config('seeding.admin.password'),
                'role' => UserRole::Administrator,
            ]
        );

        // Usuarios de prueba: solo fuera de producción, ya que la factory usa
        // fake() (faker) que no está disponible con --no-dev.
        if (! app()->isProduction()) {
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

            $this->call(ClientesDemoSeeder::class);
        }
    }
}
