<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real reportado en producción: antes de App\Support\TelefonoWhatsapp,
 * la misma conversación podía quedar guardada bajo dos formatos de teléfono
 * ("573213445027" y "+573213445027") — dos filas de whatsapp_control para
 * la misma persona (una con cliente_id, otra sin), y whatsapp_mensajes
 * fragmentado entre ambas. Ver App\Console\Commands\NormalizarTelefonosWhatsapp.
 */
class NormalizarTelefonosWhatsappTest extends TestCase
{
    use RefreshDatabase;

    public function test_normaliza_whatsapp_mensajes_sin_signo_mas(): void
    {
        $mensaje = WhatsappMensaje::query()->create([
            'telefono' => '573213445027',
            'rol' => 'cliente',
            'contenido' => 'hola',
        ]);

        $this->artisan('whatsapp:normalizar-telefonos')->assertSuccessful();

        $this->assertSame('+573213445027', $mensaje->fresh()->telefono);
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $mensaje = WhatsappMensaje::query()->create([
            'telefono' => '573213445027',
            'rol' => 'cliente',
            'contenido' => 'hola',
        ]);

        $this->artisan('whatsapp:normalizar-telefonos --dry-run')->assertSuccessful();

        $this->assertSame('573213445027', $mensaje->fresh()->telefono);
    }

    public function test_fusiona_dos_filas_de_control_para_el_mismo_telefono_conservando_el_cliente(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $sinCliente = WhatsappControl::query()->create([
            'telefono' => '573213445027',
            'estado' => 'agente',
        ]);

        $conCliente = WhatsappControl::query()->create([
            'telefono' => '+573213445027',
            'cliente_id' => $cliente->id,
            'estado' => 'agente',
        ]);

        $this->artisan('whatsapp:normalizar-telefonos')->assertSuccessful();

        $this->assertSame(1, WhatsappControl::query()->where('telefono', '+573213445027')->count());
        $this->assertModelMissing($sinCliente);

        $restante = WhatsappControl::query()->where('telefono', '+573213445027')->first();
        $this->assertSame($cliente->id, $restante->cliente_id);
        $this->assertSame($conCliente->id, $restante->id);
    }

    public function test_al_fusionar_hereda_control_humano_de_la_fila_descartada_si_la_conservada_no_tiene(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        $preparador = User::factory()->create();

        WhatsappControl::query()->create([
            'telefono' => '573213445027',
            'estado' => 'humano',
            'tomado_por_user_id' => $preparador->id,
            'tomado_en' => now(),
        ]);

        WhatsappControl::query()->create([
            'telefono' => '+573213445027',
            'cliente_id' => $cliente->id,
            'estado' => 'agente',
        ]);

        $this->artisan('whatsapp:normalizar-telefonos')->assertSuccessful();

        $restante = WhatsappControl::query()->where('telefono', '+573213445027')->first();
        $this->assertSame('humano', $restante->estado->value);
        $this->assertSame($preparador->id, $restante->tomado_por_user_id);
    }

    public function test_un_conflicto_real_con_dos_clientes_distintos_no_se_toca(): void
    {
        $clienteA = User::factory()->create(['role' => UserRole::Client]);
        $clienteB = User::factory()->create(['role' => UserRole::Client]);

        WhatsappControl::query()->create(['telefono' => '573213445027', 'cliente_id' => $clienteA->id, 'estado' => 'agente']);
        WhatsappControl::query()->create(['telefono' => '+573213445027', 'cliente_id' => $clienteB->id, 'estado' => 'agente']);

        $this->artisan('whatsapp:normalizar-telefonos')->assertSuccessful();

        $this->assertSame(2, WhatsappControl::query()->whereIn('telefono', ['573213445027', '+573213445027'])->count());
    }

    public function test_una_sola_fila_ya_normalizada_no_se_toca(): void
    {
        $control = WhatsappControl::query()->create(['telefono' => '+573213445027', 'estado' => 'agente']);

        $this->artisan('whatsapp:normalizar-telefonos')->assertSuccessful();

        $this->assertSame('+573213445027', $control->fresh()->telefono);
        $this->assertSame(1, WhatsappControl::query()->count());
    }
}
