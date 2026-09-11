<?php

namespace App\Support;

use App\Enums\FieldDataType;
use App\Enums\FieldKind;
use App\Enums\FieldMode;
use App\Enums\TaxForm;
use App\Models\CampoCatalogo;
use Illuminate\Http\UploadedFile;

/**
 * Validación semántica de un evento de recolección contra el catálogo
 * maestro (TaxFieldCatalog) — extraída de EventoRequest para compartirla
 * entre el camino HTTP (EventoRequest::withValidator(), agente externo vía
 * n8n hoy) y ToolExecutor (agente de WhatsApp, sin request HTTP). Ver
 * decisión de arquitectura en docs/implementar_agente_n8n.md.
 *
 * Solo valida lo que depende del catálogo DINÁMICO (existe el campo, calza
 * tipo_campo/tipo_dato, es coherente acumular/subcampo) — la validación
 * ESTRUCTURAL (requerido/enum/tipos de PHP) sigue siendo responsabilidad de
 * quien arma `$datos` antes de llamar acá (las reglas de EventoRequest para
 * el camino HTTP, el schema de function-calling para ToolExecutor).
 */
class EventoValidator
{
    /**
     * @param  array<string, mixed>  $datos  mismo shape que EventoRequest::validated()
     * @param  ?UploadedFile  $file  presente solo cuando modo="archivo"; valida su extensión
     *                               contra `formatos_aceptados` del catálogo
     * @return array<string, array<int, string>> errores por campo (clave con el mismo shape que un
     *                                           MessageBag de Laravel, ej. "revelados.0.campo"); vacío si es válido
     */
    public function validar(array $datos, ?UploadedFile $file = null): array
    {
        $errores = [];

        $taxYear = (int) ($datos['tax_year'] ?? 0);
        $forma = (string) ($datos['forma'] ?? '');

        // Si la forma en sí no es una de las válidas, no tiene sentido cruzarla
        // contra el catálogo — la regla estructural (Rule::in) ya la rechaza.
        if (! in_array($forma, CampoCatalogo::pseudoFormas(), true) && ! TaxForm::tryFrom($forma)) {
            return $errores;
        }

        $field = TaxFieldCatalog::find($taxYear, $forma, (string) ($datos['campo'] ?? ''));

        if (! $field) {
            $errores['campo'][] = 'El campo indicado no existe en el catálogo para esa forma.';

            return $errores;
        }

        $modo = FieldMode::tryFrom((string) ($datos['modo'] ?? ''));

        $this->validarCoincidenciaCatalogo(
            errores: $errores,
            prefijo: '',
            field: $field,
            tipoCampoInput: (string) ($datos['tipo_campo'] ?? ''),
            tipoDatoInput: $datos['tipo_dato'] ?? null,
            // Solo aplica en modo="texto" — un modo="archivo"/"no_aplica" no
            // manda tipo_dato, o manda uno que no describe el contenido real.
            verificarTipoDato: $modo === FieldMode::Texto,
            acumular: filter_var($datos['acumular'] ?? false, FILTER_VALIDATE_BOOLEAN),
            subcampo: $datos['subcampo'] ?? null,
        );

        if ($field['tipo'] === FieldKind::Documento && ! in_array($modo, [FieldMode::Archivo, FieldMode::NoAplica], true)) {
            $errores['modo'][] = 'Este campo solo admite modo "archivo" (o "no_aplica" si es opcional).';
        }

        if ($field['tipo'] === FieldKind::Dato && ! in_array($modo, [FieldMode::Texto, FieldMode::NoAplica], true)) {
            $errores['modo'][] = 'Este campo solo admite modo "texto" (o "no_aplica" si es opcional).';
        }

        // "no_aplica" es una respuesta del cliente ("no lo tengo"/"no aplica"),
        // no la ausencia de un valor obligatorio — solo tiene sentido en un
        // campo que de verdad puede faltar sin bloquear la forma.
        if ($modo === FieldMode::NoAplica && $field['obligatorio']) {
            $errores['modo'][] = 'Este campo es obligatorio y no se puede marcar como "no_aplica".';
        }

        if ($modo === FieldMode::Archivo) {
            if ($file === null) {
                // Sin esto, un modo="archivo" sin archivo real pasaba la
                // validación sin error y tronaba después con un TypeError
                // dentro de EventoRecoleccionService::procesarArchivo() (que
                // exige un UploadedFile no nulo) — acá sí es una situación
                // conversacional recuperable, no un fallo del turno entero
                // (ver ToolExecutor::guardarCampoCliente).
                $errores['file'][] = 'Falta el archivo.';
            } else {
                $extension = strtolower($file->getClientOriginalExtension());
                $formatos = $field['formatos_aceptados'] ?? [];

                if ($formatos && ! in_array($extension, $formatos, true)) {
                    $errores['file'][] = 'Formato de archivo no aceptado para este campo. Formatos válidos: '.implode(', ', $formatos);
                }
            }
        }

        $this->validarRevelados($errores, $taxYear, (array) ($datos['revelados'] ?? []));

        return $errores;
    }

    /**
     * @param  array<string, array<int, string>>  $errores
     * @param  array<int, array<string, mixed>>  $revelados
     */
    private function validarRevelados(array &$errores, int $taxYear, array $revelados): void
    {
        foreach ($revelados as $i => $item) {
            $prefijo = "revelados.{$i}.";
            $forma = (string) ($item['forma'] ?? '');
            $campo = (string) ($item['campo'] ?? '');

            if (! in_array($forma, CampoCatalogo::pseudoFormas(), true) && ! TaxForm::tryFrom($forma)) {
                continue;
            }

            $field = TaxFieldCatalog::find($taxYear, $forma, $campo);

            if (! $field) {
                $errores["{$prefijo}campo"][] = 'El campo indicado no existe en el catálogo para esa forma.';

                continue;
            }

            if ($field['tipo'] === FieldKind::Documento) {
                $errores["{$prefijo}campo"][] = 'Un campo revelado no puede ser de tipo documento.';

                continue;
            }

            $this->validarCoincidenciaCatalogo(
                errores: $errores,
                prefijo: $prefijo,
                field: $field,
                tipoCampoInput: (string) ($item['tipo_campo'] ?? ''),
                tipoDatoInput: $item['tipo_dato'] ?? null,
                verificarTipoDato: true,
                acumular: filter_var($item['acumular'] ?? false, FILTER_VALIDATE_BOOLEAN),
                subcampo: $item['subcampo'] ?? null,
            );
        }
    }

    /**
     * Coincidencia tipo_campo/tipo_dato contra el catálogo maestro, y
     * consistencia acumular/subcampo — compartido entre el campo raíz y cada
     * item de `revelados`.
     *
     * @param  array<string, array<int, string>>  $errores
     * @param  array<string, mixed>  $field
     */
    private function validarCoincidenciaCatalogo(
        array &$errores,
        string $prefijo,
        array $field,
        string $tipoCampoInput,
        mixed $tipoDatoInput,
        bool $verificarTipoDato,
        bool $acumular,
        mixed $subcampo,
    ): void {
        $tipoCampo = FieldKind::tryFrom($tipoCampoInput);

        if ($tipoCampo !== $field['tipo']) {
            $errores["{$prefijo}tipo_campo"][] = 'El tipo_campo no coincide con el catálogo maestro para este campo.';
        }

        $tipoDatoEnviado = FieldDataType::tryFrom((string) $tipoDatoInput);

        if ($verificarTipoDato && $field['tipo_dato'] !== null && $tipoDatoEnviado !== $field['tipo_dato']) {
            $errores["{$prefijo}tipo_dato"][] = 'El tipo_dato no coincide con el catálogo maestro para este campo.';
        }

        if (! $acumular) {
            return;
        }

        if ($tipoDatoEnviado === FieldDataType::Number) {
            if ($subcampo !== null) {
                $errores["{$prefijo}subcampo"][] = 'No se especifica subcampo cuando el campo acumulable es numérico simple.';
            }
        } elseif ($tipoDatoEnviado === FieldDataType::Object) {
            $subcampos = $field['subcampos'] ?? [];

            if (! is_string($subcampo) || $subcampo === '') {
                $errores["{$prefijo}subcampo"][] = 'Se requiere indicar el subcampo a acumular para un campo tipo objeto.';
            } elseif (! in_array($subcampo, $subcampos, true)) {
                $errores["{$prefijo}subcampo"][] = 'El subcampo indicado no existe en el catálogo para este campo.';
            }
        } else {
            $errores["{$prefijo}acumular"][] = 'acumular solo aplica a campos numéricos o a un subcampo de un campo tipo objeto.';
        }
    }
}
