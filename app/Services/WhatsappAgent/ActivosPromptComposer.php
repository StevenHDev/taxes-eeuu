<?php

namespace App\Services\WhatsappAgent;

use App\Enums\TipoPromptActivoStep;
use App\Models\PromptActivoStep;
use Illuminate\Support\Collection;

/**
 * Compila la lista ACTIVOS (orden + condiciones, ver PromptActivoStep) y su
 * SALVAGUARDA a texto, para prompt_actuales/fases/recoleccion.md — leído por
 * AgentePromptsSeeder al publicar. Sacar esto de texto fijo a config permite
 * reordenar/agregar/quitar un ACTIVO sin editar el prompt a mano, y
 * garantiza que TODO campo de la lista tenga la misma salvaguarda
 * "no repreguntes si el historial ya lo respondió" — antes solo existía a
 * mano para el caso Empleo (bug real: estado_civil no la tenía, y el agente
 * repetía la pregunta indefinidamente cuando pendientes no reflejaba a
 * tiempo un guardado ya hecho).
 */
class ActivosPromptComposer
{
    /**
     * @return Collection<int, PromptActivoStep>
     */
    private function pasos(): Collection
    {
        return PromptActivoStep::query()->orderBy('orden')->get();
    }

    public function compilarLista(): string
    {
        return $this->pasos()
            ->map(fn (PromptActivoStep $p) => $this->lineaPara($p))
            ->implode("\n");
    }

    private function lineaPara(PromptActivoStep $p): string
    {
        return match ($p->tipo) {
            TipoPromptActivoStep::Simple => $p->nota !== null
                ? "{$p->orden}. {$p->campo} — {$p->nota}"
                : "{$p->orden}. {$p->campo}",
            TipoPromptActivoStep::Condicional => "{$p->orden}. {$p->campo} — solo si {$p->condicion}. {$p->nota_si_no_aplica}",
            TipoPromptActivoStep::DocumentoConNota => "{$p->orden}. {$p->campo} — {$p->nota}",
            TipoPromptActivoStep::Bifurcacion => implode("\n", [
                "{$p->orden}. {$p->etiqueta} — pregunta simple: \"{$p->pregunta}\" (esta pregunta no tiene un campo propio en `pendientes`; es una bifurcación conversacional entre {$p->campo_si} y {$p->campo_no}):",
                "    - {$p->campo_si} y {$p->campo_no} suelen traer obligatorio:false en `pendientes` — eso significa que un cliente sin ESTE tipo de ingreso no necesita ninguno de los dos, nunca que esta bifurcación en sí sea opcional. La respuesta del cliente a esta pregunta determina siempre cuál de los dos pedir; nunca marques los dos como no_aplica sin haber pedido primero el que corresponde según la respuesta.",
                "    - Si responde que sí: pide {$p->campo_si}. Al guardarlo, invoca también guardar_campo_cliente con modo=\"no_aplica\" para {$p->campo_no} en el mismo turno (el cliente ya confirmó que es empleado, lo cual responde implícitamente por el 1099-NEC — esto sí cuenta como información entregada explícitamente, ver GROUNDING ESTRICTO).",
                "    - Si responde que no: pide {$p->campo_no}. Al guardarlo (o si el cliente no tiene ninguno), guarda modo=\"no_aplica\" para {$p->campo_si} en el mismo turno, por la misma razón.",
            ]),
            TipoPromptActivoStep::Grupo => implode("\n", [
                "{$p->orden}. {$p->etiqueta} — pregunta compuesta: \"{$p->pregunta}\" (esta pregunta no tiene un campo propio en `pendientes`; agrupa varios campos distintos en un solo turno: ".implode(', ', $p->miembros ?? []).'):',
                '    - Todos los miembros de este grupo traen obligatorio:false en `pendientes` — el cliente puede confirmar ninguno, algunos o todos.',
                '    - Pregunta la lista completa una sola vez, en un solo mensaje de WhatsApp. Por cada miembro que el cliente confirme que tiene, sigue las reglas normales de guardado de ese campo puntual (si es documento, pide el archivo; si es dato, pide el valor) — puede requerir más de un turno si el cliente confirma varios a la vez pero solo entrega uno por mensaje.',
                '    - Por cada miembro que el cliente NO confirme (dice que no tiene ninguno de esos, o los que no menciona al responder), invoca guardar_campo_cliente con modo="no_aplica" para ese campo, en la misma tanda de turnos que resuelve el grupo — nunca vuelvas a preguntar por un miembro individualmente después de esta pregunta compuesta.',
            ]),
        };
    }

    public function compilarSalvaguardas(): string
    {
        $bloques = $this->pasos()
            ->map(fn (PromptActivoStep $p) => $this->salvaguardaPara($p));

        $intro = 'Principio general: si en algún momento consultar_pendientes_cliente devuelve como pendiente '
            .'un campo (o el complementario de una bifurcación) que, según el historial de ESTA misma '
            .'conversación, el cliente YA respondió explícitamente, NUNCA vuelvas a hacer esa pregunta ni a '
            .'pedir ese documento — el guardado correspondiente puede haber fallado en el turno original sin '
            .'que se note de inmediato, y el historial de la conversación es la fuente de verdad de que la '
            .'pregunta ya fue respondida, incluso si `pendientes` todavía no lo refleja. En su lugar, reintenta '
            .'silenciosamente el guardado correspondiente (con el mismo contenido que el cliente ya dio, o '
            .'modo="no_aplica" para el complementario de una bifurcación) sin mencionárselo al cliente, y '
            .'continúa directamente con el siguiente campo pendiente real.';

        return $intro."\n\n".$bloques->implode("\n\n");
    }

    private function salvaguardaPara(PromptActivoStep $p): string
    {
        return match ($p->tipo) {
            TipoPromptActivoStep::Bifurcacion => "- {$p->etiqueta}: si `pendientes` devuelve {$p->campo_si} o {$p->campo_no} pero el historial ya muestra que el cliente respondió \"{$p->pregunta}\" (sí o no), no vuelvas a preguntarlo ni a pedir el documento complementario ya resuelto por esa respuesta.",
            TipoPromptActivoStep::Grupo => "- {$p->etiqueta}: si `pendientes` devuelve cualquiera de ".implode(', ', $p->miembros ?? []).' pero el historial ya muestra que el cliente respondió "'.$p->pregunta.'", no vuelvas a hacer esa pregunta compuesta — retoma solo los miembros que quedaron sin resolver de esa respuesta (guardando el dato/documento, o modo="no_aplica" si el cliente no lo tiene).',
            TipoPromptActivoStep::Simple, TipoPromptActivoStep::Condicional, TipoPromptActivoStep::DocumentoConNota => "- {$p->campo}: si `pendientes` lo devuelve pero el historial ya muestra la respuesta del cliente (o el documento ya subido), no lo vuelvas a preguntar ni a pedir.",
        };
    }
}
