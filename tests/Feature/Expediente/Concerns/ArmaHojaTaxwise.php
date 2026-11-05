<?php

namespace Tests\Feature\Expediente\Concerns;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Enums\Lectura\EstadoExcepcion;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenDato;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoDato;
use App\Models\Lectura\DocumentoLectura;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;

/**
 * Los casos de las pruebas de la hoja para TaxWise (F5.5). Va junto con
 * ArmaExpediente.
 */
trait ArmaHojaTaxwise
{
    /** El SSN de prueba: nunca debe salir en claro. */
    protected string $ssn = '123-45-6789';

    protected string $ssnConyuge = '987-65-4321';

    protected string $ein = '12-3456789';

    /**
     * Una lectura terminada con varios datos a la vez (solo cuenta la última lectura de cada archivo).
     *
     * @param  array<string, array{0: string|null, 1?: float, 2?: MotivoConfianza}>  $casillas  casilla → [valor, confianza, motivo]
     * @return array<string, DocumentoDato>
     */
    protected function leer(EntrevistaDocumento $item, array $casillas): array
    {
        $archivo = $item->archivos()->first() ?? $this->archivo($item);

        $lectura = DocumentoLectura::query()->create([
            'documento_id' => $archivo->id,
            'entrevista_documento_id' => $item->id,
            'lector' => 'openai',
            'modelo' => 'modelo-de-prueba',
            'estado' => EstadoLectura::Leida,
            'paginas' => 1,
            'terminada_en' => now(),
        ]);

        $datos = [];

        foreach ($casillas as $casilla => $c) {
            $datos[$casilla] = $lectura->datos()->create([
                'entrevista_documento_id' => $item->id,
                'casilla' => (string) $casilla,
                'valor' => $c[0],
                'valor_leido' => $c[0],
                'confianza' => $c[1] ?? 0.99,
                'motivo_confianza' => $c[2] ?? MotivoConfianza::EnTextoDelPdf,
                'legibilidad' => Legibilidad::Claro,
                'pagina' => 1,
                'recuadro' => ['x' => 0.1, 'y' => 0.2, 'ancho' => 0.3, 'alto' => 0.05],
                'evidencia' => $c[0],
                'origen' => OrigenDato::Lector,
            ]);
        }

        return $datos;
    }

    protected function excepcionDeDato(Entrevista $e, DocumentoDato $dato, string $regla = 'R-04', SeveridadRegla $sev = SeveridadRegla::Bloqueante): Excepcion
    {
        return Excepcion::query()->create([
            'entrevista_id' => $e->id,
            'entrevista_documento_id' => $dato->entrevista_documento_id,
            'documento_dato_id' => $dato->id,
            'regla_codigo' => $regla,
            'severidad' => $sev,
            'clave' => $regla.'|'.$dato->id,
            'detalle' => ['casilla' => $dato->casilla],
            'estado' => EstadoExcepcion::Abierta,
        ]);
    }

    /**
     * El caso María: soltera con un hijo, W-2 y tarjeta leídos y validados,
     * estado civil HOH decidido y el hijo clasificado.
     *
     * @return array{0: User, 1: Entrevista, 2: User, 3: EntrevistaDocumento, 4: EntrevistaDocumento}
     */
    protected function maria(): array
    {
        [$cliente, $e, $preparador] = $this->clienteConExpediente([
            'A1' => 'María Pérez', 'A3' => 'ssn_valido', 'B1' => 'nunca_casado', 'D1' => 'si', 'C0' => 'si',
        ]);
        $guardador = app(RespuestaGuardador::class);
        $guardador->guardar($e, new RespuestaData('A1.2', 'Cajera'), CanalRespuesta::Portal, $cliente);

        $persona = $guardador->agregarPersona($e, 'Persona 1');
        foreach (['C3' => 'hijo', 'C4' => 12, 'C1' => 'Ana Hija', 'C2' => '2015-03-04'] as $codigo => $valor) {
            $guardador->guardar($e, new RespuestaData($codigo, $valor, $persona->id), CanalRespuesta::Portal, $cliente);
        }

        $w2 = $this->item($e, 'w2', EstadoDocumento::Validado);
        $w2->update(['revisado_por' => $preparador->id, 'revisado_en' => now()]);
        $this->leer($w2, ['c' => ['Empresa X'], 'b' => [$this->ein], '1' => ['45120.00'], '2' => ['4810.00']]);

        $tarjeta = $this->item($e, 'tarjeta-ssn', EstadoDocumento::Validado);
        $tarjeta->update(['revisado_por' => $preparador->id, 'revisado_en' => now()]);
        $this->leer($tarjeta, ['nombre' => ['María Pérez'], 'ssn' => [$this->ssn]]);

        $expediente = $e->fresh()->expediente;
        $expediente->update(['estado_civil_irs' => EstadoCivilIrs::Hoh, 'estado_civil_motivo' => 'Vive con su hijo.', 'estado_civil_por' => $preparador->id, 'estado_civil_en' => now()]);
        $expediente->personas()->create(['persona_id' => $persona->id, 'clasificacion' => ClasificacionPersona::HijoCalificado, 'motivo' => 'Vivió 12 meses con ella.', 'decidido_por' => $preparador->id, 'decidido_en' => now()]);

        return [$cliente, $e->fresh(), $preparador, $w2, $tarjeta];
    }

    /**
     * Todas las filas de la hoja, en una lista plana.
     *
     * @param  array<string, mixed>  $hoja
     * @return list<array<string, mixed>>
     */
    protected function filas(array $hoja): array
    {
        return collect($hoja['secciones'])->flatMap(fn ($s) => $s['grupos'])->flatMap(fn ($g) => $g['filas'])->values()->all();
    }
}
