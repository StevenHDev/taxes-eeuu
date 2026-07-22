<?php

namespace Tests;

use Database\Seeders\CatalogoCamposSeeder;
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

        if (Schema::hasTable('catalogo_campos')) {
            $this->seed(CatalogoCamposSeeder::class);
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
