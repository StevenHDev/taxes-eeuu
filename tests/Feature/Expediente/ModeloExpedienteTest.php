<?php

namespace Tests\Feature\Expediente;

use App\Enums\AccionAuditoria;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\PuntoRevision;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Enums\UserRole;
use App\Models\BitacoraActividad;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteEvento;
use App\Models\Expediente\ExpedientePersona;
use App\Models\Expediente\ExpedienteRevisionPunto;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F5.1a: tablas, enums y modelos del expediente.
 */
class ModeloExpedienteTest extends TestCase
{
    use RefreshDatabase;

    private function expediente(?User $cliente = null, int $anio = 2025, array $extra = []): Expediente
    {
        $cliente ??= User::factory()->create(['role' => UserRole::Client]);

        return Expediente::create($extra + [
            'user_id' => $cliente->id,
            'tax_year' => $anio,
            'estado' => EstadoExpediente::Invitado,
            'estado_desde' => now(),
        ]);
    }

    public function test_los_casts_devuelven_enums_y_fechas(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $expediente = $this->expediente(extra: [
            'estado' => 'por_validar',
            'estado_civil_irs' => 'mfj',
            'estado_civil_motivo' => 'Casados todo el año',
            'estado_civil_por' => $preparador->id,
            'estado_civil_en' => '2026-03-01 10:00:00',
            'creditos_8867' => ['eic', 'ctc'],
            'creditos_8867_en' => '2026-03-02 10:00:00',
            'presentado_en' => '2026-04-10',
            'cambios_tras_presentar_en' => '2026-04-11 09:00:00',
            'perfil' => ['asalariado', 'dependientes'],
            'vence_en' => '2026-04-15',
        ])->fresh();

        $this->assertSame(EstadoExpediente::PorValidar, $expediente->estado);
        $this->assertSame(EstadoCivilIrs::Mfj, $expediente->estado_civil_irs);
        $this->assertInstanceOf(CarbonInterface::class, $expediente->estado_desde);
        $this->assertInstanceOf(CarbonInterface::class, $expediente->estado_civil_en);
        $this->assertInstanceOf(CarbonInterface::class, $expediente->creditos_8867_en);
        $this->assertInstanceOf(CarbonInterface::class, $expediente->cambios_tras_presentar_en);
        $this->assertSame('2026-04-10', $expediente->presentado_en->toDateString());
        $this->assertSame('2026-04-15', $expediente->vence_en->toDateString());
        $this->assertSame(['eic', 'ctc'], $expediente->creditos_8867);
        $this->assertSame(['asalariado', 'dependientes'], $expediente->perfil);
        $this->assertTrue($expediente->decisorEstadoCivil->is($preparador));

        $evento = ExpedienteEvento::create([
            'expediente_id' => $expediente->id,
            'tipo' => 'estado',
            'estado_anterior' => 'invitado',
            'estado_nuevo' => 'en_entrevista',
            'datos' => ['causa' => 'prueba'],
        ])->fresh();
        $this->assertSame(TipoEventoExpediente::Estado, $evento->tipo);
        $this->assertSame(EstadoExpediente::Invitado, $evento->estado_anterior);
        $this->assertSame(EstadoExpediente::EnEntrevista, $evento->estado_nuevo);
        $this->assertSame(['causa' => 'prueba'], $evento->datos);
        $this->assertInstanceOf(CarbonInterface::class, $evento->created_at);

        $entrevista = Entrevista::create([
            'user_id' => $expediente->user_id, 'tax_year' => 2025,
            'estado' => EstadoEntrevista::EnCurso, 'canal_inicio' => CanalRespuesta::Portal,
            'iniciada_en' => now(),
        ]);
        $persona = EntrevistaPersona::create(['entrevista_id' => $entrevista->id, 'orden' => 1, 'etiqueta' => 'Persona 1']);
        $decision = ExpedientePersona::create([
            'expediente_id' => $expediente->id, 'persona_id' => $persona->id,
            'clasificacion' => 'hijo_calificado', 'motivo' => 'Vivió todo el año',
            'decidido_por' => $preparador->id, 'decidido_en' => now(),
        ])->fresh();
        $this->assertSame(ClasificacionPersona::HijoCalificado, $decision->clasificacion);
        $this->assertTrue($decision->clasificacion->esDependiente());
        $this->assertInstanceOf(CarbonInterface::class, $decision->decidido_en);
        $this->assertTrue($decision->decisor->is($preparador));

        // La persona archivada sigue saliendo (withTrashed).
        $persona->delete();
        $this->assertTrue($decision->fresh()->persona->is($persona));

        $punto = ExpedienteRevisionPunto::create([
            'expediente_id' => $expediente->id, 'punto' => 'identidad',
            'confirmado_por' => $preparador->id, 'confirmado_en' => now(),
        ])->fresh();
        $this->assertSame(PuntoRevision::Identidad, $punto->punto);
        $this->assertInstanceOf(CarbonInterface::class, $punto->confirmado_en);
        $this->assertTrue($punto->confirmador->is($preparador));

        $this->assertCount(1, $expediente->eventos);
        $this->assertCount(1, $expediente->personas);
        $this->assertCount(1, $expediente->puntos);
        $this->assertTrue($expediente->entrevista()->doesntExist());
    }

    public function test_un_cliente_tiene_un_expediente_por_anio(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        $this->expediente($cliente, 2025);
        $this->expediente($cliente, 2024);

        $this->assertCount(2, $cliente->expedientes);
        $this->assertSame(1, Expediente::query()->delAnio(2025)->count());

        $this->expectException(QueryException::class);
        $this->expediente($cliente, 2025);
    }

    public function test_la_entrevista_alcanza_su_expediente(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        $entrevista = Entrevista::create([
            'user_id' => $cliente->id, 'tax_year' => 2025,
            'estado' => EstadoEntrevista::EnCurso, 'canal_inicio' => CanalRespuesta::Portal,
            'iniciada_en' => now(),
        ]);
        $expediente = $this->expediente($cliente, 2025, ['entrevista_id' => $entrevista->id]);

        $this->assertTrue($entrevista->expediente->is($expediente));
        $this->assertTrue($expediente->entrevista->is($entrevista));
    }

    public function test_un_evento_no_tiene_updated_at(): void
    {
        $expediente = $this->expediente();

        $evento = ExpedienteEvento::create([
            'expediente_id' => $expediente->id,
            'tipo' => TipoEventoExpediente::Estado,
            'estado_nuevo' => EstadoExpediente::Invitado,
        ]);

        $this->assertFalse(Schema::hasColumn('expediente_eventos', 'updated_at'));
        $this->assertArrayNotHasKey('updated_at', $evento->getAttributes());
        $this->assertNotNull($evento->fresh()->created_at);
    }

    public function test_la_bitacora_registra_el_expediente_sin_valores(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        $this->actingAs($admin);

        $expediente = $this->expediente($cliente, 2025);
        $expediente->update([
            'estado_civil_irs' => EstadoCivilIrs::Single,
            'estado_civil_motivo' => 'Motivo secreto del preparador',
        ]);

        $alta = BitacoraActividad::query()->where('auditable_type', Expediente::class)
            ->where('accion', AccionAuditoria::Creado)->firstOrFail();
        $this->assertSame("expediente 2025 (cliente #{$cliente->id})", $alta->etiqueta);

        $cambio = BitacoraActividad::query()->where('auditable_type', Expediente::class)
            ->where('accion', AccionAuditoria::Actualizado)->firstOrFail();
        $this->assertEqualsCanonicalizing(['estado_civil_irs', 'estado_civil_motivo'], $cambio->campos_afectados);
        $this->assertStringNotContainsString('Motivo secreto', json_encode($cambio->toArray()));

        ExpedienteRevisionPunto::create(['expediente_id' => $expediente->id, 'punto' => PuntoRevision::Identidad]);
        $this->assertDatabaseHas('bitacora_actividad', [
            'auditable_type' => ExpedienteRevisionPunto::class,
            'etiqueta' => "identidad (expediente #{$expediente->id})",
        ]);

        // El evento ya es un registro: no se duplica en la bitácora general.
        ExpedienteEvento::create(['expediente_id' => $expediente->id, 'tipo' => TipoEventoExpediente::Estado]);
        $this->assertDatabaseMissing('bitacora_actividad', ['auditable_type' => ExpedienteEvento::class]);
    }

    public function test_las_etiquetas_salen_en_ingles(): void
    {
        App::setLocale('es');
        $this->assertSame('Por validar', EstadoExpediente::PorValidar->label());

        App::setLocale('en');

        $this->assertSame('To validate', EstadoExpediente::PorValidar->label());
        $this->assertSame('Single', EstadoCivilIrs::Single->label());
        $this->assertSame('Qualifying child', ClasificacionPersona::HijoCalificado->label());
        $this->assertSame('Comparison with the prior year', PuntoRevision::AnioAnterior->titulo());
        $this->assertSame('The IRS accepted your return.', EstadoExpediente::Aceptado->textoCliente());
    }

    public function test_cada_estado_tiene_turno_texto_para_el_cliente_y_orden(): void
    {
        $turnos = [
            'invitado' => 'cliente', 'en_entrevista' => 'cliente', 'en_consulta' => 'cliente',
            'en_recoleccion' => 'cliente', 'esperando_firma' => 'cliente',
            'por_validar' => 'preparador', 'listo_para_preparar' => 'preparador', 'en_revision' => 'preparador',
            'preparado_en_taxwise' => 'preparador', 'rechazado' => 'preparador',
            'presentado' => 'irs', 'aceptado' => 'nadie',
        ];

        App::setLocale('es');
        $this->assertCount(12, EstadoExpediente::cases());
        foreach (EstadoExpediente::cases() as $i => $estado) {
            $this->assertSame($turnos[$estado->value], $estado->turno(), $estado->value);
            $this->assertNotSame('', $estado->textoCliente(), $estado->value);
            $this->assertNotSame('', $estado->label(), $estado->value);
            $this->assertSame($i + 1, $estado->orden());
        }

        $this->assertCount(6, array_filter(EstadoExpediente::cases(), fn ($e) => $e->derivado()));
        $this->assertTrue(EstadoExpediente::EnRevision->reabrible());
        $this->assertFalse(EstadoExpediente::Presentado->reabrible());
        $this->assertTrue(EstadoExpediente::Rechazado->trasPresentar());

        $this->assertSame(
            'Presentamos tu declaración al IRS.',
            EstadoExpediente::Presentado->textoCliente(),
        );
        $fecha = now()->setDate(2026, 4, 10);
        $this->assertStringContainsString(
            $fecha->isoFormat('LL'),
            EstadoExpediente::Presentado->textoCliente($fecha),
        );
        $this->assertSame(
            EstadoExpediente::ListoParaPreparar->textoCliente(),
            EstadoExpediente::EnRevision->textoCliente(),
        );
    }
}
