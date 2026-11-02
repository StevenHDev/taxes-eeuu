<?php

namespace Tests\Feature;

use App\Enums\FaseConversacion;
use App\Services\WhatsappAgent\EstadoConversacionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstadoConversacionResolverTest extends TestCase
{
    use RefreshDatabase;

    private EstadoConversacionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(EstadoConversacionResolver::class);
    }

    public function test_sin_cliente_la_fase_es_verificacion_de_cuenta(): void
    {
        $this->assertSame(FaseConversacion::VerificacionCuenta, $this->resolver->resolver(null));
    }
}
