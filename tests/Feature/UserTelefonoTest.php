<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User::phone se normaliza al mismo formato que Twilio/Meta usan para
 * WhatsappMensaje.telefono (+dígitos, sin separadores) — ver el mutador en
 * el modelo y ClienteController::conversacionWhatsapp, que compara ambos
 * por igualdad exacta de string.
 */
class UserTelefonoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_telefono_ya_normalizado_no_cambia(): void
    {
        $usuario = User::factory()->create(['phone' => '+15551234567']);

        $this->assertSame('+15551234567', $usuario->fresh()->phone);
    }

    public function test_agrega_el_signo_mas_si_falta(): void
    {
        $usuario = User::factory()->create(['phone' => '15551234567']);

        $this->assertSame('+15551234567', $usuario->fresh()->phone);
    }

    public function test_quita_guiones_parentesis_y_espacios(): void
    {
        $usuario = User::factory()->create(['phone' => '(555) 123-4567']);

        $this->assertSame('+5551234567', $usuario->fresh()->phone);
    }

    public function test_quita_espacios_de_un_telefono_ya_con_signo_mas(): void
    {
        $usuario = User::factory()->create(['phone' => '+1 555 123 4567']);

        $this->assertSame('+15551234567', $usuario->fresh()->phone);
    }

    public function test_un_telefono_vacio_queda_null(): void
    {
        $usuario = User::factory()->create(['phone' => '']);

        $this->assertNull($usuario->fresh()->phone);
    }

    public function test_null_se_mantiene_null(): void
    {
        $usuario = User::factory()->create(['phone' => null]);

        $this->assertNull($usuario->fresh()->phone);
    }
}
