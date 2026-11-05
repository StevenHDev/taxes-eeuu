<?php

namespace App\Services\Expediente;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Lectura\EstadoExcepcion;
use App\Models\CatalogoFiscal\CatalogoRegla;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Entrevista\ChecklistDocumentos;
use Illuminate\Support\Collection;

/**
 * Estado derivado de un expediente y qué le falta (plan de F5, "Estados del
 * expediente y transiciones"). Solo lee. Es el único lugar donde se decide
 * qué es "listo para preparar", y donde se evalúa R-02: no crea filas en la
 * cola de excepciones, se mira sobre el checklist.
 */
class DiagnosticoExpediente
{
    public const R02 = 'R-02';

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly Consentimientos $consentimientos,
        private readonly ChecklistDocumentos $checklist,
    ) {}

    public function para(User $cliente, int $taxYear): Diagnostico
    {
        $consentimiento = $this->consentimientos->firmado($cliente, $taxYear);
        $entrevista = Entrevista::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first();

        if ($entrevista === null) {
            return new Diagnostico(
                estado: EstadoExpediente::Invitado,
                consentimiento: $consentimiento,
                entrevistaConfirmada: false,
                r02Activa: false,
                r02Cumple: false,
                faltan: [],
                noAplica: [],
                excepcionesQueFrenan: 0,
                informativas: 0,
                consultas: 0,
                itemsCliente: 0,
                itemsPreparador: 0,
                frases: $consentimiento ? [__('Falta iniciar la entrevista')] : [__('Falta firmar el consentimiento')],
            );
        }

        $confirmada = $entrevista->estado === EstadoEntrevista::Confirmada;
        $items = $entrevista->documentos()->vigentes()->with('persona')->orderBy('id')->get();
        $excepciones = Excepcion::query()->where('entrevista_id', $entrevista->id)->abiertas()->get(['id', 'estado', 'severidad']);

        $enEstados = fn (EstadoDocumento ...$estados) => $items->filter(fn (EntrevistaDocumento $i) => in_array($i->estado, $estados, true));

        $consultasItems = $enEstados(EstadoDocumento::ConObservacion, EstadoDocumento::NoAplicaRechazado)->count();
        $consultasExcepciones = $excepciones->where('estado', EstadoExcepcion::Consultada)->count();
        $recoleccion = $enEstados(EstadoDocumento::Pendiente, EstadoDocumento::NoSePudoLeer)->count();
        $porValidar = $enEstados(EstadoDocumento::Subido, EstadoDocumento::Leido)->count();

        $frenan = $excepciones->filter(fn (Excepcion $e) => in_array($e->severidad, [SeveridadRegla::Bloqueante, SeveridadRegla::Revision], true));
        $bloqueantes = $frenan->where('severidad', SeveridadRegla::Bloqueante)->count();
        $deRevision = $frenan->count() - $bloqueantes;
        $informativas = $excepciones->where('severidad', SeveridadRegla::Informativa)->count();

        $r02Activa = CatalogoRegla::query()->delAnio($taxYear)->activos()
            ->where('codigo', self::R02)->where('implementada', true)->exists();
        $r02Cumple = ! $r02Activa || $this->checklist->completo($entrevista);

        $estado = match (true) {
            ! $consentimiento => EstadoExpediente::Invitado,
            ! $confirmada => EstadoExpediente::EnEntrevista,
            $consultasItems + $consultasExcepciones > 0 => EstadoExpediente::EnConsulta,
            $recoleccion > 0 => EstadoExpediente::EnRecoleccion,
            $porValidar > 0, $frenan->isNotEmpty(), ! $r02Cumple => EstadoExpediente::PorValidar,
            default => EstadoExpediente::ListoParaPreparar,
        };

        $faltan = $items->reject(fn (EntrevistaDocumento $i) => $i->estado->cerrado())
            ->map(fn (EntrevistaDocumento $i) => [
                'id' => $i->id,
                'codigo' => $i->documento_codigo,
                'nombre' => $this->nombre($i, $taxYear),
                'numero' => $this->numero($i, $items),
                'persona_orden' => $i->persona?->orden,
                'estado' => $i->estado->value,
            ])->values()->all();

        $noAplica = $items->where('estado', EstadoDocumento::NoAplica)
            ->map(fn (EntrevistaDocumento $i) => [
                'id' => $i->id,
                'nombre' => $this->nombre($i, $taxYear),
                'motivo' => $i->motivo_no_aplica,
            ])->values()->all();

        $frases = [];

        if (! $confirmada) {
            $frases[] = __('Falta confirmar la entrevista');
        }

        if ($consultasItems + $consultasExcepciones > 0) {
            $total = $consultasItems + $consultasExcepciones;
            $frases[] = $total === 1 ? __('1 consulta sin responder') : __(':count consultas sin responder', ['count' => $total]);
        }

        if ($recoleccion > 0) {
            $frases[] = $recoleccion === 1 ? __('1 documento pendiente del cliente') : __(':count documentos pendientes del cliente', ['count' => $recoleccion]);
        }

        if ($porValidar > 0) {
            $frases[] = $porValidar === 1 ? __('1 documento sin validar') : __(':count documentos sin validar', ['count' => $porValidar]);
        }

        if ($bloqueantes > 0) {
            $frases[] = $bloqueantes === 1 ? __('1 excepción bloqueante abierta') : __(':count excepciones bloqueantes abiertas', ['count' => $bloqueantes]);
        }

        if ($deRevision > 0) {
            $frases[] = $deRevision === 1 ? __('1 excepción por revisar abierta') : __(':count excepciones por revisar abiertas', ['count' => $deRevision]);
        }

        return new Diagnostico(
            estado: $estado,
            consentimiento: $consentimiento,
            entrevistaConfirmada: $confirmada,
            r02Activa: $r02Activa,
            r02Cumple: $r02Cumple,
            faltan: $faltan,
            noAplica: $noAplica,
            excepcionesQueFrenan: $frenan->count(),
            informativas: $informativas,
            consultas: $consultasItems + $consultasExcepciones,
            itemsCliente: $recoleccion + $consultasItems,
            itemsPreparador: $porValidar,
            frases: $estado === EstadoExpediente::ListoParaPreparar ? [] : array_values(array_unique($frases)),
        );
    }

    private function nombre(EntrevistaDocumento $item, int $taxYear): string
    {
        if ($item->esOtro()) {
            return (string) $item->descripcion;
        }

        $documento = $this->catalogo->documentos($taxYear)->firstWhere('codigo', $item->documento_codigo);

        if (app()->getLocale() === 'en' && ! empty($documento['nombre_sencillo_en'])) {
            return $documento['nombre_sencillo_en'];
        }

        return $documento['nombre_sencillo_es'] ?? $item->documento_codigo;
    }

    /**
     * El número solo se muestra si hay más de uno del mismo documento y persona.
     *
     * @param  Collection<int, EntrevistaDocumento>  $items
     */
    private function numero(EntrevistaDocumento $item, Collection $items): ?int
    {
        $hermanos = $items->where('documento_codigo', $item->documento_codigo)->where('persona_id', $item->persona_id)->count();

        return $hermanos > 1 ? $item->numero : null;
    }
}
