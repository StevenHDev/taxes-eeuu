<?php

namespace App\Enums\Expediente;

use Carbon\CarbonInterface;

/**
 * Estado de un expediente (cliente + año). Los seis primeros los deriva la
 * plataforma; los demás los pone el preparador.
 */
enum EstadoExpediente: string
{
    case Invitado = 'invitado';
    case EnEntrevista = 'en_entrevista';
    case EnConsulta = 'en_consulta';
    case EnRecoleccion = 'en_recoleccion';
    case PorValidar = 'por_validar';
    case ListoParaPreparar = 'listo_para_preparar';
    case EnRevision = 'en_revision';
    case PreparadoEnTaxwise = 'preparado_en_taxwise';
    case EsperandoFirma = 'esperando_firma';
    case Presentado = 'presentado';
    case Aceptado = 'aceptado';
    case Rechazado = 'rechazado';

    public function label(): string
    {
        return match ($this) {
            self::Invitado => __('Invitado'),
            self::EnEntrevista => __('En entrevista'),
            self::EnConsulta => __('En consulta'),
            self::EnRecoleccion => __('En recolección'),
            self::PorValidar => __('Por validar'),
            self::ListoParaPreparar => __('Listo para preparar'),
            self::EnRevision => __('En revisión'),
            self::PreparadoEnTaxwise => __('Preparado en TaxWise'),
            self::EsperandoFirma => __('Esperando firma'),
            self::Presentado => __('Presentado'),
            self::Aceptado => __('Aceptado'),
            self::Rechazado => __('Rechazado'),
        };
    }

    /**
     * Lo que ve el cliente: sin montos ni jerga.
     */
    public function textoCliente(?CarbonInterface $presentadoEn = null): string
    {
        return match ($this) {
            self::Invitado => __('Falta firmar la autorización para empezar.'),
            self::EnEntrevista => __('Estamos en la entrevista: faltan algunas respuestas.'),
            self::EnRecoleccion => __('Estamos esperando tus documentos.'),
            self::EnConsulta => __('Tenemos una pregunta sobre uno de tus documentos.'),
            self::PorValidar => __('Estamos revisando tus documentos.'),
            self::ListoParaPreparar, self::EnRevision => __('Tu preparador está revisando tu declaración.'),
            self::PreparadoEnTaxwise, self::EsperandoFirma => __('Tu declaración está lista; te contactaremos para firmarla.'),
            self::Presentado => $presentadoEn !== null
                ? __('Presentamos tu declaración al IRS el :fecha.', ['fecha' => $presentadoEn->isoFormat('LL')])
                : __('Presentamos tu declaración al IRS.'),
            self::Aceptado => __('El IRS aceptó tu declaración.'),
            self::Rechazado => __('El IRS nos devolvió tu declaración; tu preparador te contactará.'),
        };
    }

    /** Los seis estados que calcula la plataforma. */
    public function derivado(): bool
    {
        return in_array($this, [
            self::Invitado, self::EnEntrevista, self::EnConsulta,
            self::EnRecoleccion, self::PorValidar, self::ListoParaPreparar,
        ], true);
    }

    /** La revisión se reabre sola si el diagnóstico deja de estar listo. */
    public function reabrible(): bool
    {
        return in_array($this, [self::EnRevision, self::PreparadoEnTaxwise, self::EsperandoFirma], true);
    }

    public function trasPresentar(): bool
    {
        return in_array($this, [self::Presentado, self::Aceptado, self::Rechazado], true);
    }

    /** De quién es el turno: cliente, preparador, irs o nadie. */
    public function turno(): string
    {
        return match ($this) {
            self::Invitado, self::EnEntrevista, self::EnConsulta, self::EnRecoleccion, self::EsperandoFirma => 'cliente',
            self::PorValidar, self::ListoParaPreparar, self::EnRevision, self::PreparadoEnTaxwise, self::Rechazado => 'preparador',
            self::Presentado => 'irs',
            self::Aceptado => 'nadie',
        };
    }

    /** Posición en el flujo, de 1 a 12. */
    public function orden(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }
}
