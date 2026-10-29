<?php

namespace Tests;

use Database\Seeders\CatalogoCamposSeeder;
use Database\Seeders\ParametrosFiscalesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // El catálogo vive ahora en BD (ver TaxFieldCatalog); RefreshDatabase envuelve
        // cada test en una transacción propia, así que hay que re-sembrarlo por test.
        // La caché en memoria ('array' driver) sí persiste entre tests dentro del mismo
        // proceso, así que también hay que vaciarla para no arrastrar datos de otro test.
        Cache::flush();

        // La mayoría de las pruebas usan personal creado por la factory, sin
        // 2FA ni passkey. El MFA obligatorio (F0.1) se prueba aparte, en
        // EnsureStaffHasMfaTest, que vuelve a activarlo.
        config(['security.mfa_roles' => []]);

        if (Schema::hasTable('catalogo_campos')) {
            $this->seed(CatalogoCamposSeeder::class);
        }

        if (Schema::hasTable('parametros_fiscales')) {
            $this->seed(ParametrosFiscalesSeeder::class);
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
