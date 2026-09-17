<?php

namespace Tests\Unit\Support;

use App\Support\TelefonoWhatsapp;
use PHPUnit\Framework\TestCase;

class TelefonoWhatsappTest extends TestCase
{
    public function test_agrega_el_signo_mas_si_falta(): void
    {
        $this->assertSame('+573213445027', TelefonoWhatsapp::normalizar('573213445027'));
    }

    public function test_conserva_el_signo_mas_si_ya_viene(): void
    {
        $this->assertSame('+573213445027', TelefonoWhatsapp::normalizar('+573213445027'));
    }

    public function test_quita_el_prefijo_whatsapp(): void
    {
        $this->assertSame('+573213445027', TelefonoWhatsapp::normalizar('whatsapp:+573213445027'));
        $this->assertSame('+573213445027', TelefonoWhatsapp::normalizar('whatsapp:573213445027'));
    }

    public function test_quita_espacios_guiones_y_parentesis(): void
    {
        $this->assertSame('+15551234567', TelefonoWhatsapp::normalizar('+1 (555) 123-4567'));
    }

    public function test_null_y_vacio_devuelven_null(): void
    {
        $this->assertNull(TelefonoWhatsapp::normalizar(null));
        $this->assertNull(TelefonoWhatsapp::normalizar(''));
        $this->assertNull(TelefonoWhatsapp::normalizar('   '));
    }
}
