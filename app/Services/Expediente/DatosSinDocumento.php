<?php

namespace App\Services\Expediente;

use App\Enums\AccionAuditoria;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Models\BitacoraActividad;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteDatoSinDocumento;
use App\Models\Expediente\ExpedienteDatoSinDocumentoCambio;
use App\Models\User;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Entrevista\TransicionNoPermitida;
use App\Services\Lectura\DatosDocumento;
use App\Services\Lectura\Reglas\MotorReglas;
use App\Services\Lectura\ResultadoSumas;
use App\Services\Lectura\SumasRegistrosPorRenglones;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * El respaldo manual sin documento (F6 6.5, último recurso): el único camino
 * de escritura de `expediente_datos_sin_documento`.
 *
 * - Solo se anota una clave del catálogo del año que no tiene documento
 *   leído; si el documento llega después, `vigentes()` marca el manual como
 *   reemplazado (no lo borra) y deja de devolverlo.
 * - Cada escritura deja su fila en el historial, corre el motor de reglas y,
 *   como F5, recalcula el expediente (`EstadosExpediente`) y devuelve a
 *   revisión un expediente ya preparado.
 * - El valor y el motivo nunca van a la bitácora, a los eventos ni a las
 *   excepciones: solo la clave y los ids.
 */
class DatosSinDocumento
{
    public function __construct(
        private readonly EstadosExpediente $estados,
        private readonly DatosDocumento $datos,
        private readonly CatalogoFiscal $catalogo,
    ) {}

    /**
     * "$1,234.5" → "1234.50". Null si no es un monto en dólares (sin signo,
     * hasta dos decimales): un monto anotado de memoria nunca es negativo.
     */
    public static function monto(?string $valor): ?string
    {
        $limpio = str_replace(['$', ',', ' '], '', trim((string) $valor));

        if (preg_match('/^\d{1,12}(\.\d{1,2})?$/', $limpio) !== 1) {
            return null;
        }

        $centavos = Dinero::deDolares($limpio)->centavos();

        return sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
    }

    /**
     * La clave existe en el catálogo del año: una casilla de monto (no
     * sensible) de un documento, o una línea de dinero del Schedule C de los
     * registros del negocio (`registros-ingresos-gastos:<línea>`).
     */
    public function claveValida(int $taxYear, string $clave): bool
    {
        [$codigo, $casilla] = array_pad(explode(':', $clave, 2), 2, '');

        if ($codigo === '' || $casilla === '' || $codigo === EntrevistaDocumento::OTRO) {
            return false;
        }

        $documento = $this->catalogo->documentos($taxYear)->firstWhere('codigo', $codigo);

        if ($documento === null) {
            return false;
        }

        if ($codigo === SumasRegistrosPorRenglones::DOCUMENTO) {
            return in_array($casilla, ResultadoSumas::LINEAS, true);
        }

        return collect($documento['casillas'] ?? [])->contains(
            fn (array $c) => $c['casilla'] === $casilla && $c['tipo_dato'] === 'monto' && ! $c['sensible'],
        );
    }

    /**
     * El documento de la clave tiene un archivo leído (la última lectura
     * terminada de un archivo vigente). Un documento único por entrevista no
     * mira la persona; los demás, los de esa persona (null = el titular).
     */
    public function tieneDocumentoLeido(Expediente $expediente, string $clave, ?int $personaId): bool
    {
        $codigo = explode(':', $clave, 2)[0];
        $documento = $this->catalogo->documentos($expediente->tax_year)->firstWhere('codigo', $codigo);

        $items = EntrevistaDocumento::query()
            ->where('entrevista_id', $expediente->entrevista_id ?? 0)
            ->where('documento_codigo', $codigo)
            ->vigentes()
            ->when(($documento['uno_por'] ?? null) !== 'unico', fn ($q) => $personaId === null ? $q->whereNull('persona_id') : $q->where('persona_id', $personaId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $items !== [] && $this->datos->lecturasVigentes($items)->isNotEmpty();
    }

    /** La persona es del bloque C de la entrevista del expediente. */
    public function personaDelExpediente(Expediente $expediente, ?int $personaId): bool
    {
        return $personaId === null || ($expediente->entrevista_id !== null
            && EntrevistaPersona::query()->whereKey($personaId)->where('entrevista_id', $expediente->entrevista_id)->exists());
    }

    public function hayVigente(Expediente $expediente, string $clave, ?int $personaId): bool
    {
        return ExpedienteDatoSinDocumento::query()
            ->where('expediente_id', $expediente->id)
            ->where('clave', $clave)
            ->where(fn ($q) => $personaId === null ? $q->whereNull('persona_id') : $q->where('persona_id', $personaId))
            ->vigentes()
            ->exists();
    }

    /**
     * @param  string  $fuente  `cliente_dijo` u `otro`
     *
     * @throws TransicionNoPermitida
     */
    public function anotar(
        Expediente $expediente,
        string $clave,
        ?int $personaId,
        string $valor,
        string $motivo,
        string $fuente,
        ?string $fuenteFecha,
        ?string $fuenteCanal,
        User $actor,
    ): ExpedienteDatoSinDocumento {
        $monto = $this->exigirMonto($valor);
        $motivo = $this->exigirMotivo($motivo, __('Escribe por qué no hay documento.'));

        if (! in_array($fuente, ExpedienteDatoSinDocumento::FUENTES, true)) {
            throw new InvalidArgumentException("Fuente desconocida: {$fuente}.");
        }

        $dato = null;

        $this->escribir($expediente, $actor, function (Expediente $e) use ($clave, $personaId, $monto, $motivo, $fuente, $fuenteFecha, $fuenteCanal, $actor, &$dato) {
            if (! $this->claveValida($e->tax_year, $clave)) {
                throw new TransicionNoPermitida(__('Esa casilla no existe en el catálogo del año o no es un monto.'));
            }

            if (! $this->personaDelExpediente($e, $personaId)) {
                throw new TransicionNoPermitida(__('Esa persona no es de este cliente.'));
            }

            $this->exigirSinDocumento($e, $clave, $personaId);

            if ($this->hayVigente($e, $clave, $personaId)) {
                throw new TransicionNoPermitida(__('Ya hay un dato anotado sin documento para esa casilla: cámbialo en vez de anotar otro.'));
            }

            $dato = ExpedienteDatoSinDocumento::query()->create([
                'expediente_id' => $e->id,
                'clave' => $clave,
                'persona_id' => $personaId,
                'valor' => $monto,
                'motivo' => $motivo,
                'fuente' => $fuente,
                'fuente_fecha' => $fuente === 'cliente_dijo' ? $fuenteFecha : null,
                'fuente_canal' => $fuente === 'cliente_dijo' ? $fuenteCanal : null,
                'anotado_por' => $actor->id,
            ]);

            $this->historial($dato, ExpedienteDatoSinDocumentoCambio::ANOTADO, null, $monto, $motivo, $actor);
        });

        /** @var ExpedienteDatoSinDocumento $dato */
        $this->bitacora($dato, $expediente, $actor, "Anotó un dato sin documento: {$dato->clave} (cliente #{$expediente->user_id})");

        return $dato;
    }

    /**
     * Cambia el monto con el motivo del cambio; el anterior queda en el historial.
     *
     * @throws TransicionNoPermitida
     */
    public function cambiar(ExpedienteDatoSinDocumento $dato, string $valor, string $motivo, User $actor): void
    {
        $monto = $this->exigirMonto($valor);
        $motivo = $this->exigirMotivo($motivo, __('Escribe por qué cambias el dato.'));
        $expediente = $dato->expediente;

        // El mismo monto no es un cambio: ni historial ni reapertura.
        if ($dato->vigente() && $dato->valor === $monto) {
            return;
        }

        $this->escribir($expediente, $actor, function (Expediente $e) use ($dato, $monto, $motivo, $actor) {
            $actual = $this->vigenteBloqueado($dato);
            $this->exigirSinDocumento($e, $actual->clave, $actual->persona_id);

            if ($actual->valor === $monto) {
                return;
            }

            $this->historial($actual, ExpedienteDatoSinDocumentoCambio::CAMBIADO, $actual->valor, $monto, $motivo, $actor);
            $actual->update(['valor' => $monto]);
        });

        $dato->refresh();
        $this->bitacora($dato, $expediente, $actor, "Cambió un dato sin documento: {$dato->clave} (cliente #{$expediente->user_id})");
    }

    /**
     * Lo deja de contar sin borrarlo (el IRS pide conservar el registro).
     *
     * @throws TransicionNoPermitida
     */
    public function retirar(ExpedienteDatoSinDocumento $dato, string $motivo, User $actor): void
    {
        $motivo = $this->exigirMotivo($motivo, __('Escribe por qué retiras el dato.'));
        $expediente = $dato->expediente;

        $this->escribir($expediente, $actor, function () use ($dato, $motivo, $actor) {
            $actual = $this->vigenteBloqueado($dato);

            $this->historial($actual, ExpedienteDatoSinDocumentoCambio::RETIRADO, $actual->valor, null, $motivo, $actor);
            $actual->update(['retirado_en' => now(), 'retirado_por' => $actor->id]);
        });

        $dato->refresh();
        $this->bitacora($dato, $expediente, $actor, "Retiró un dato sin documento: {$dato->clave} (cliente #{$expediente->user_id})");
    }

    /**
     * Lo anotado que cuenta para el cálculo (lo leerá ArmadorEntradas): solo
     * las claves que siguen sin documento leído. Las que ya lo tienen se
     * marcan como reemplazadas por el documento, con su fila en el historial.
     *
     * @return Collection<int, ExpedienteDatoSinDocumento>
     */
    public function vigentes(Expediente $expediente): Collection
    {
        $datos = ExpedienteDatoSinDocumento::query()
            ->where('expediente_id', $expediente->id)
            ->vigentes()
            ->orderBy('id')
            ->get();

        $conDocumento = [];

        $reemplazados = $datos->filter(function (ExpedienteDatoSinDocumento $d) use ($expediente, &$conDocumento) {
            $grupo = $d->documentoCodigo().'#'.($d->persona_id ?? 0);

            return $conDocumento[$grupo] ??= $this->tieneDocumentoLeido($expediente, $d->clave, $d->persona_id);
        });

        if ($reemplazados->isNotEmpty()) {
            DB::transaction(function () use ($reemplazados) {
                foreach ($reemplazados as $dato) {
                    $this->historial($dato, ExpedienteDatoSinDocumentoCambio::REEMPLAZADO, $dato->valor, null, null, null);
                    $dato->update(['reemplazado_en' => now()]);
                }
            });
        }

        return $datos->diffKeys($reemplazados)->values();
    }

    /**
     * Como RevisionExpediente: recalcula el expediente (F5), bloquea la fila
     * y escribe en una transacción. Después de presentar ya no se anota nada;
     * si estaba preparado o esperando firma, vuelve a revisión (hay que
     * corregir TaxWise). Al final corre el motor de reglas.
     *
     * @param  callable(Expediente): void  $accion
     *
     * @throws TransicionNoPermitida
     */
    private function escribir(Expediente $expediente, User $actor, callable $accion): void
    {
        DB::transaction(function () use ($expediente, $actor, $accion) {
            $cliente = User::withTrashed()->findOrFail($expediente->user_id);
            $this->estados->recalcular($cliente, $expediente->tax_year);

            $actual = Expediente::query()->whereKey($expediente->id)->lockForUpdate()->firstOrFail();

            if ($actual->estado->trasPresentar()) {
                throw new TransicionNoPermitida(__('Después de presentar la declaración ya no se pueden anotar ni cambiar datos sin documento.'));
            }

            $accion($actual);

            if (in_array($actual->estado, [EstadoExpediente::PreparadoEnTaxwise, EstadoExpediente::EsperandoFirma], true)) {
                $this->estados->registrarCambio($actual, EstadoExpediente::EnRevision, $actor, TipoEventoExpediente::Reabierto, __('Cambió un dato anotado sin documento después de preparar'));
            }

            $expediente->refresh();
        });

        $entrevista = $expediente->entrevista_id === null ? null : Entrevista::query()->find($expediente->entrevista_id);

        if ($entrevista !== null) {
            app(MotorReglas::class)->correr($entrevista);
        }
    }

    /**
     * @throws TransicionNoPermitida
     */
    private function vigenteBloqueado(ExpedienteDatoSinDocumento $dato): ExpedienteDatoSinDocumento
    {
        $actual = ExpedienteDatoSinDocumento::query()->whereKey($dato->id)->lockForUpdate()->firstOrFail();

        if (! $actual->vigente()) {
            throw new TransicionNoPermitida(__('Ese dato ya no cuenta: lo reemplazó el documento o se retiró.'));
        }

        return $actual;
    }

    /**
     * @throws TransicionNoPermitida
     */
    private function exigirSinDocumento(Expediente $expediente, string $clave, ?int $personaId): void
    {
        if ($this->tieneDocumentoLeido($expediente, $clave, $personaId)) {
            throw new TransicionNoPermitida(__('Este dato ya sale del documento; corrígelo en el visor.'));
        }
    }

    /**
     * @throws TransicionNoPermitida
     */
    private function exigirMonto(string $valor): string
    {
        return self::monto($valor) ?? throw new TransicionNoPermitida(__('Escribe un monto en dólares, sin signo y con hasta dos decimales.'));
    }

    /**
     * @throws TransicionNoPermitida
     */
    private function exigirMotivo(string $motivo, string $mensaje): string
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new TransicionNoPermitida($mensaje);
        }

        return $motivo;
    }

    private function historial(ExpedienteDatoSinDocumento $dato, string $accion, ?string $anterior, ?string $nuevo, ?string $motivo, ?User $actor): void
    {
        $dato->cambios()->create([
            'accion' => $accion,
            'valor_anterior' => $anterior,
            'valor_nuevo' => $nuevo,
            'motivo' => $motivo,
            'actor_id' => $actor?->id,
        ]);
    }

    /**
     * Solo la clave y el cliente: nunca el valor ni el motivo (§7216).
     */
    private function bitacora(ExpedienteDatoSinDocumento $dato, Expediente $expediente, User $actor, string $etiqueta): void
    {
        BitacoraActividad::query()->create([
            'actor_id' => $actor->id,
            'actor_nombre' => $actor->name,
            'actor_email' => $actor->email,
            'accion' => AccionAuditoria::Actualizado,
            'auditable_type' => $dato::class,
            'auditable_id' => $dato->id,
            'etiqueta' => $etiqueta,
            'campos_afectados' => null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
