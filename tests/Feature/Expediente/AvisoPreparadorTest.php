<?php

namespace Tests\Feature\Expediente;

use App\Enums\Expediente\EstadoExpediente;
use App\Enums\UserRole;
use App\Events\ExpedienteCambioDeEstado;
use App\Models\Expediente\Expediente;
use App\Models\User;
use App\Notifications\AvisoExpedientePreparador;
use App\Services\Expediente\EstadosExpediente;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

class AvisoPreparadorTest extends TestCase
{
    use ArmaExpediente;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
        Notification::fake();
    }

    private function cambiar(Expediente $expediente, EstadoExpediente $nuevo): void
    {
        app(EstadosExpediente::class)->registrarCambio($expediente, $nuevo, null);
    }

    private function expediente(User $cliente, EstadoExpediente $estado): Expediente
    {
        return Expediente::query()->where('user_id', $cliente->id)->firstOrFail()->forceFill(['estado' => $estado]);
    }

    public function test_al_quedar_listo_para_preparar_el_preparador_recibe_un_correo(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();
        $expediente = $this->expediente($cliente, EstadoExpediente::PorValidar);

        $this->cambiar($expediente, EstadoExpediente::ListoParaPreparar);

        Notification::assertSentToTimes($preparador, AvisoExpedientePreparador::class, 1);
        Notification::assertSentTo($preparador, AvisoExpedientePreparador::class, function (AvisoExpedientePreparador $aviso) use ($cliente, $preparador) {
            $correo = $aviso->toMail($preparador);

            return ! $aviso->respondioConsulta
                && str_contains($correo->subject, $cliente->name)
                && $aviso->url === route('clientes.show', ['cliente' => $cliente->id, 'tax_year' => (int) config('tax.current_tax_year')]);
        });
    }

    public function test_sin_preparador_el_aviso_va_a_los_administradores(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $otroAdmin = User::factory()->create(['role' => UserRole::Administrator]);
        [$cliente] = $this->clienteConExpediente();
        $cliente->update(['preparer_id' => null]);

        $this->cambiar($this->expediente($cliente, EstadoExpediente::PorValidar), EstadoExpediente::ListoParaPreparar);

        Notification::assertSentTo($admin, AvisoExpedientePreparador::class);
        Notification::assertSentTo($otroAdmin, AvisoExpedientePreparador::class);
    }

    public function test_cuando_el_cliente_responde_una_consulta_el_preparador_recibe_el_correo_de_consulta(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();

        $this->cambiar($this->expediente($cliente, EstadoExpediente::EnConsulta), EstadoExpediente::PorValidar);

        Notification::assertSentTo($preparador, AvisoExpedientePreparador::class, function (AvisoExpedientePreparador $aviso) use ($preparador) {
            App::setLocale('es');

            return $aviso->respondioConsulta
                && str_contains($aviso->toMail($preparador)->subject, 'respondió una consulta');
        });
    }

    public function test_otros_cambios_de_estado_no_mandan_correo(): void
    {
        [$cliente] = $this->clienteConExpediente();

        $this->cambiar($this->expediente($cliente, EstadoExpediente::EnRecoleccion), EstadoExpediente::PorValidar);
        $this->cambiar($this->expediente($cliente, EstadoExpediente::EnConsulta), EstadoExpediente::EnEntrevista);
        $this->cambiar($this->expediente($cliente, EstadoExpediente::ListoParaPreparar), EstadoExpediente::EnRevision);

        Notification::assertNothingSent();
    }

    public function test_con_la_bandera_apagada_no_se_manda_nada(): void
    {
        config(['tax.avisos_correo_preparador' => false]);
        [$cliente] = $this->clienteConExpediente();

        $this->cambiar($this->expediente($cliente, EstadoExpediente::PorValidar), EstadoExpediente::ListoParaPreparar);

        Notification::assertNothingSent();
    }

    public function test_recalcular_sin_cambio_de_estado_no_manda_nada(): void
    {
        [$cliente] = $this->clienteConExpediente();
        $anio = (int) config('tax.current_tax_year');

        app(EstadosExpediente::class)->recalcular($cliente, $anio);
        Notification::assertNothingSent();

        app(EstadosExpediente::class)->recalcular($cliente, $anio);
        Notification::assertNothingSent();
    }

    public function test_el_listener_se_registra_solo_una_vez_para_el_evento(): void
    {
        $this->assertCount(1, collect(app('events')->getListeners(ExpedienteCambioDeEstado::class)));
    }

    public function test_el_correo_no_trae_montos_ni_ssn(): void
    {
        [$cliente, , $preparador] = $this->clienteConExpediente();
        $cliente->update(['name' => 'María Pérez']);
        $aviso = new AvisoExpedientePreparador('María Pérez', 'Listo para preparar', false, route('clientes.show', $cliente));

        $texto = implode(' ', array_merge(
            [$aviso->toMail($preparador)->subject],
            $aviso->toMail($preparador)->introLines,
            [$aviso->toMail($preparador)->actionText, $aviso->toMail($preparador)->actionUrl],
        ));

        $this->assertDoesNotMatchRegularExpression('/\d{3}-?\d{2}-?\d{4}|\$\s?\d/', $texto);
        $this->assertStringContainsString('María Pérez', $texto);
    }
}
