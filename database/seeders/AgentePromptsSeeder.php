<?php

namespace Database\Seeders;

use App\Enums\FaseConversacion;
use App\Models\AgentePrompt;
use App\Models\PromptActivoStep;
use App\Services\WhatsappAgent\ActivosPromptComposer;
use App\Support\AgentePromptVigente;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Siembra la versión 1 (ya publicada) del prompt del agente de WhatsApp,
 * leyendo el contenido adaptado por fase desde `prompt_actuales/fases/*.md`
 * — ver docs/implementar_agente_n8n.md. Idempotente: puede re-ejecutarse
 * para refrescar el contenido de la version=1 si esos archivos cambian,
 * antes de publicar una version=2 real vía el flujo normal de versionado.
 *
 * La fase `recoleccion` trae dos marcadores (`<!-- ACTIVOS_LISTA -->`,
 * `<!-- ACTIVOS_SALVAGUARDAS -->`) que se sustituyen acá por el texto
 * compilado desde `prompt_activo_steps` (ver ActivosPromptComposer) — el
 * .md sigue siendo la fuente versionada de todo lo demás, solo esas dos
 * secciones vienen de config editable en vez de texto fijo. Si esa tabla
 * está vacía (primera vez, o un entorno que nunca la sembró), este seeder
 * la puebla con PromptActivoStepsSeeder — pero solo entonces, para nunca
 * pisar en silencio una personalización ya guardada por un admin.
 */
class AgentePromptsSeeder extends Seeder
{
    private const VERSION = 1;

    public function run(): void
    {
        if (PromptActivoStep::query()->doesntExist()) {
            $this->call(PromptActivoStepsSeeder::class);
        }

        $activos = app(ActivosPromptComposer::class);

        foreach (FaseConversacion::cases() as $fase) {
            $ruta = base_path("prompt_actuales/fases/{$fase->value}.md");

            throw_if(
                ! is_file($ruta),
                new RuntimeException("Falta el archivo de prompt para la fase [{$fase->value}]: {$ruta}"),
            );

            $contenido = file_get_contents($ruta);

            throw_if($contenido === false, new RuntimeException("No se pudo leer el archivo de prompt: {$ruta}"));

            if ($fase === FaseConversacion::Recoleccion) {
                $lista = $activos->compilarLista();
                $salvaguardas = $activos->compilarSalvaguardas();

                // Guardarraíl: publicar recoleccion con estas secciones
                // vacías dejaría al agente sin saber qué preguntar en toda
                // la fase de recolección — un silencio que solo se notaría
                // en producción con clientes reales. PromptActivoStepsSeeder
                // debe correr ANTES que este seeder.
                throw_if($lista === '', new RuntimeException(
                    'prompt_activo_steps está vacía — corre PromptActivoStepsSeeder antes de publicar el prompt (la fase recoleccion depende de esa config para su lista ACTIVOS).',
                ));

                $contenido = strtr($contenido, [
                    '<!-- ACTIVOS_LISTA -->' => $lista,
                    '<!-- ACTIVOS_SALVAGUARDAS -->' => $salvaguardas,
                ]);
            }

            AgentePrompt::query()->updateOrCreate(
                ['version' => self::VERSION, 'fase' => $fase->value],
                ['contenido' => $contenido, 'publicada_en' => now()],
            );
        }

        AgentePromptVigente::invalidate();
    }
}
