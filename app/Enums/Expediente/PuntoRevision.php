<?php

namespace App\Enums\Expediente;

/**
 * Los puntos del checklist de revisión del preparador (7.2 del documento madre).
 */
enum PuntoRevision: string
{
    case Identidad = 'identidad';
    case EstadoCivil = 'estado_civil';
    case Dependientes = 'dependientes';
    case Documentos = 'documentos';
    case Excepciones = 'excepciones';
    case DebidaDiligencia = 'debida_diligencia';
    case AnioAnterior = 'anio_anterior';

    public function titulo(): string
    {
        return match ($this) {
            self::Identidad => __('Identidad verificada con la identificación y las tarjetas'),
            self::EstadoCivil => __('Estado civil para el IRS decidido con motivo'),
            self::Dependientes => __('Cada persona clasificada con motivo'),
            self::Documentos => __('Cada documento validado o en "no aplica"'),
            self::Excepciones => __('Sin excepciones abiertas que frenan'),
            self::DebidaDiligencia => __('Resumen de debida diligencia revisado'),
            self::AnioAnterior => __('Comparación con el año anterior'),
        };
    }

    public function ayuda(): string
    {
        return match ($this) {
            self::Identidad => __('Confírmalo tú: compara la identificación y las tarjetas del Seguro Social, incluida la del cónyuge, con lo que respondió el cliente.'),
            self::EstadoCivil => __('Se cumple cuando decides el estado civil para el IRS y dejas el motivo.'),
            self::Dependientes => __('Se cumple cuando clasificas a cada persona del cliente y dejas el motivo. Si no hay personas, no se pide.'),
            self::Documentos => __('Se cumple cuando cada documento requerido está validado o marcado como "no aplica" (regla R-02).'),
            self::Excepciones => __('Se cumple cuando no queda ninguna excepción bloqueante o de revisión abierta.'),
            self::DebidaDiligencia => __('Confírmalo tú cuando hayas revisado el resumen del Form 8867. Solo se pide si marcaste créditos.'),
            self::AnioAnterior => __('Confírmalo tú cuando hayas comparado con la declaración del año anterior. Solo se pide si el cliente la presentó.'),
        };
    }

    /** Los que confirma el preparador (los demás los calcula la plataforma). */
    public function manual(): bool
    {
        return in_array($this, [self::Identidad, self::DebidaDiligencia, self::AnioAnterior], true);
    }

    public function orden(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }
}
