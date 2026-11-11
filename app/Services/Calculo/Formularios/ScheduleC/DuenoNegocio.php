<?php

namespace App\Services\Calculo\Formularios\ScheduleC;

use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\RolPersona;

/**
 * De quién es el negocio del Schedule C y del Schedule SE (F6 6.4b, decisión B de la ola 3).
 *
 * El Schedule C y el SE son por persona: con declaración conjunta, si los dos cónyuges tienen
 * trabajo independiente son dos Schedule C y dos SE (instrucciones del Schedule SE de 2025, p. 2,
 * "Joint Returns": "If both spouses have self-employment income, each must file a separate
 * Schedule SE"). El piloto calcula UN negocio: el de la persona dueña de los 1099-NEC, los
 * 1099-K y los registros del negocio. Si esos documentos son de dos personas, los dos
 * formularios salen no soportados (lo calcula el preparador; llega en F7).
 *
 * Sin `personaId` en los documentos y sin cónyuge en la declaración, el dueño es el
 * contribuyente; con cónyuge, el dueño queda sin saber (`personaId = null`) y lo que dependa de
 * él (la línea 8a del SE) sale falta_dato.
 */
final readonly class DuenoNegocio
{
    /** Documentos de trabajo independiente (decisión A) y registros del negocio. */
    public const DOCUMENTOS = ['1099-nec', '1099-k', 'registros-ingresos-gastos'];

    /** Casillas del 1099-MISC que indican un negocio (barcos de pesca y pagos médicos). */
    public const CASILLAS_MISC_NEGOCIO = ['1099-misc:5', '1099-misc:6'];

    private function __construct(
        public bool $hayNegocio,
        public bool $dosDuenos,
        public ?int $personaId,
    ) {}

    public static function de(EntradasDeclaracion $e): self
    {
        $hayNegocio = $e->sumasRegistros !== []
            || Apoyo::documentosPresentes($e, self::DOCUMENTOS) !== []
            || array_intersect(self::CASILLAS_MISC_NEGOCIO, array_keys($e->datos)) !== [];

        if (! $hayNegocio) {
            return new self(false, false, null);
        }

        $duenos = [];
        $sinDueno = false;

        foreach (self::DOCUMENTOS as $codigo) {
            foreach ($e->documentos($codigo) as $documento) {
                if ($documento->personaId === null) {
                    $sinDueno = true;
                } else {
                    $duenos[$documento->personaId] = true;
                }
            }
        }

        if (count($duenos) > 1) {
            return new self(true, true, null);
        }

        $conyuge = Apoyo::persona($e, RolPersona::Conyuge);

        if (count($duenos) === 1 && (! $sinDueno || $conyuge === null)) {
            return new self(true, false, (int) array_key_first($duenos));
        }

        // Sin cónyuge, el único que puede tener el negocio es el contribuyente.
        if ($conyuge === null) {
            return new self(true, false, Apoyo::persona($e, RolPersona::Contribuyente)?->id);
        }

        return new self(true, false, null);
    }

    public static function motivoDosDuenos(): string
    {
        return __('Hay 1099 de trabajo independiente o registros del negocio de dos personas: son dos negocios (un Schedule C y un Schedule SE por persona). Lo calcula el preparador; llega en F7.');
    }

    public static function motivoSinNegocio(): string
    {
        return __('No hay 1099-NEC, 1099-K ni registros del negocio.');
    }
}
