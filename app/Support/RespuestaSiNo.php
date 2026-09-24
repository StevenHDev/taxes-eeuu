<?php

namespace App\Support;

/**
 * Normaliza una respuesta de sí/no que puede llegar en cualquiera de las
 * formas que el sistema ya produce, sin uniformidad entre ellas: un boolean
 * real (si el agente de WhatsApp lo escribe así en el JSON) o el texto
 * "si"/"no" (si el agente lo escribe como texto, o si viene del input de
 * texto plano del formulario del portal — CampoValorInput en formulario.tsx
 * nunca produce un boolean real para ningún subcampo, todo es string).
 *
 * Encontrado en producción (2026-09-24, cuenta de prueba de Steven Herrera):
 * FilingStatusCalculator, DependentQualificationCalculator y
 * PortalFormularioController::ocultosPorDependencia() leían estos subcampos
 * con `(bool) $valor` o `$valor ?? false` directo — `(bool) "no"` en PHP da
 * TRUE (cualquier string no vacío es truthy), así que un cliente que
 * contestó "no" por el formulario quedaba tratado como si hubiera
 * contestado "sí" en el cálculo de filing status/dependientes, o nunca
 * lograba ocultar preguntas que dependían de esa respuesta.
 */
class RespuestaSiNo
{
    public static function esAfirmativo(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if (is_string($valor)) {
            return in_array(mb_strtolower(trim($valor)), ['si', 'sí', 'yes', 'true', '1'], true);
        }

        return (bool) $valor;
    }
}
