<?php

namespace Tests\Feature\Expediente\Concerns;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\FieldState;
use App\Enums\Lectura\EstadoExcepcion;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenDato;
use App\Enums\UserRole;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoDato;
use App\Models\Lectura\DocumentoLectura;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use Illuminate\Support\Str;

/**
 * Ayuda para las pruebas del expediente (F5). Quien la usa siembra el catálogo
 * (`CatalogoFiscalSeeder`) antes de armar un cliente.
 */
trait ArmaExpediente
{
    /**
     * Un cliente con su entrevista, sus respuestas, el consentimiento firmado
     * y la entrevista confirmada.
     *
     * @param  array<string, mixed>  $respuestas  código de pregunta → valor
     * @return array{0: User, 1: Entrevista, 2: User} [cliente, entrevista, preparador]
     */
    protected function clienteConExpediente(array $respuestas = ['D1' => 'si'], bool $consentimiento = true, bool $confirmada = true, ?User $preparador = null): array
    {
        $preparador ??= User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $anio = (int) config('tax.current_tax_year');

        if ($consentimiento) {
            app(ConsentimientosCargador::class)->cargar();
            app(Consentimientos::class)->firmar($cliente, $anio, 1, 'Cliente de Prueba', null, null);
        }

        $guardador = app(RespuestaGuardador::class);
        $entrevista = $guardador->entrevistaDe($cliente, CanalRespuesta::Portal);

        foreach ($respuestas as $codigo => $valor) {
            $guardador->guardar($entrevista, new RespuestaData($codigo, $valor), CanalRespuesta::Portal, $cliente);
        }

        if ($confirmada) {
            $entrevista->update(['estado' => EstadoEntrevista::Confirmada, 'confirmada_en' => now()]);
        }

        return [$cliente, $entrevista->fresh(), $preparador];
    }

    /**
     * El ítem del checklist (lo crea si falta) en el estado pedido. No sube archivos.
     */
    protected function item(Entrevista $e, string $codigo, EstadoDocumento $estado, ?int $personaId = null, int $numero = 1): EntrevistaDocumento
    {
        $item = $e->documentos()->where('documento_codigo', $codigo)->where('persona_id', $personaId)->where('numero', $numero)->first();

        if ($item === null) {
            return $e->documentos()->create(['documento_codigo' => $codigo, 'persona_id' => $personaId, 'numero' => $numero, 'estado' => $estado]);
        }

        $item->update(['estado' => $estado, 'retirado_en' => null]);

        return $item;
    }

    /**
     * Un archivo subido al ítem, sin pasar por el almacén ni cambiar su estado.
     */
    protected function archivo(EntrevistaDocumento $item): Documento
    {
        $e = $item->entrevista;

        return Documento::query()->create([
            'user_id' => $e->user_id,
            'tax_year' => $e->tax_year,
            'documento_codigo' => $item->documento_codigo,
            'entrevista_id' => $e->id,
            'persona_id' => $item->persona_id,
            'entrevista_documento_id' => $item->id,
            'file_path' => 'documentos/'.Str::uuid().'.pdf',
            'file_original_name' => 'documento.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => 10,
            'formato' => 'pdf',
            'hash_contenido' => hash('sha256', (string) Str::uuid()),
            'cifrado' => true,
            'estado_validacion' => FieldState::Recibido,
        ]);
    }

    /**
     * Un dato leído del ítem: crea el archivo, la lectura `leida` y el dato.
     */
    protected function datoLeido(EntrevistaDocumento $item, string $casilla, ?string $valor, float $confianza = 0.99, MotivoConfianza $motivo = MotivoConfianza::EnTextoDelPdf): DocumentoDato
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

        return $lectura->datos()->create([
            'entrevista_documento_id' => $item->id,
            'casilla' => $casilla,
            'valor' => $valor,
            'valor_leido' => $valor,
            'confianza' => $confianza,
            'motivo_confianza' => $motivo,
            'legibilidad' => Legibilidad::Claro,
            'pagina' => 1,
            'recuadro' => ['x' => 0.1, 'y' => 0.2, 'ancho' => 0.3, 'alto' => 0.05],
            'evidencia' => $valor === null ? null : "Casilla {$casilla} {$valor}",
            'origen' => OrigenDato::Lector,
        ]);
    }

    protected function excepcion(Entrevista $e, string $regla, SeveridadRegla $sev, ?EntrevistaDocumento $item = null, EstadoExcepcion $estado = EstadoExcepcion::Abierta): Excepcion
    {
        return Excepcion::query()->create([
            'entrevista_id' => $e->id,
            'entrevista_documento_id' => $item?->id,
            'regla_codigo' => $regla,
            'severidad' => $sev,
            'clave' => $regla.'|'.($item?->id ?? 0).'|'.uniqid(),
            'detalle' => [],
            'estado' => $estado,
        ]);
    }
}
