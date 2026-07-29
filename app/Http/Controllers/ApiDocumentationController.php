<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ApiDocumentationController extends Controller
{
    /**
     * Show the rendered API documentation (docs/api.md), con una tabla de
     * contenidos lateral extraída de sus encabezados ## / ###.
     */
    public function index(): Response
    {
        $markdown = File::get(base_path('docs/api.md'));

        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return Inertia::render('api-docs', [
            // El H1 del markdown queda fuera: la página ya tiene su propio
            // título (Heading + i18n), y mostrar los dos sería redundante.
            'html' => $this->sinH1($this->conAnclas($html)),
            'toc' => $this->tablaDeContenidos($markdown),
        ]);
    }

    /**
     * Descarga el spec de OpenAPI (docs/openapi.yaml) — la contraparte
     * máquina-legible de este mismo documento, importable en Postman/Insomnia.
     */
    public function openapi(): HttpResponse
    {
        return response(File::get(base_path('docs/openapi.yaml')))
            ->header('Content-Type', 'application/yaml');
    }

    /**
     * Extrae los encabezados `##`/`###` del markdown crudo, en el mismo orden
     * en que aparecen. Los `###` quedan anidados bajo el `##` más cercano.
     *
     * @return array<int, array{title: string, slug: string, children: array<int, array{title: string, slug: string}>}>
     */
    private function tablaDeContenidos(string $markdown): array
    {
        $toc = [];
        $seccionActual = null;

        foreach (explode("\n", $markdown) as $linea) {
            if (! preg_match('/^(#{2,3})\s+(.+)$/', $linea, $m)) {
                continue;
            }

            $titulo = $this->textoPlano($m[2]);
            $entrada = ['title' => $titulo, 'slug' => $this->slug($titulo)];

            if (strlen($m[1]) === 2) {
                if ($seccionActual !== null) {
                    $toc[] = $seccionActual;
                }

                $seccionActual = ['title' => $entrada['title'], 'slug' => $entrada['slug'], 'children' => []];
            } elseif ($seccionActual !== null) {
                $seccionActual['children'][] = $entrada;
            }
        }

        if ($seccionActual !== null) {
            $toc[] = $seccionActual;
        }

        return $toc;
    }

    /**
     * Inserta `id="{slug}"` en cada `<h2>`/`<h3>` del HTML ya renderizado, con
     * el mismo algoritmo de slug que `tablaDeContenidos()` — así el nav lateral
     * y los enlaces internos que el propio markdown ya trae (`(ver [Emitir
     * eventos](#emitir-un-evento-post-apieventos))`) apuntan al mismo lugar.
     */
    private function conAnclas(string $html): string
    {
        return (string) preg_replace_callback(
            '/<h([23])>(.*?)<\/h\1>/s',
            fn (array $m) => "<h{$m[1]} id=\"{$this->slug($this->textoPlano($m[2]))}\">{$m[2]}</h{$m[1]}>",
            $html,
        );
    }

    /** Quita el primer `<h1>` (título general, redundante con el Heading de la página). */
    private function sinH1(string $html): string
    {
        return (string) preg_replace('/<h1>.*?<\/h1>/s', '', $html, limit: 1);
    }

    /** Quita marcado markdown/HTML liviano (backticks, negrita, tags) para basar el slug en texto puro. */
    private function textoPlano(string $texto): string
    {
        return trim(strip_tags(str_replace(['`', '**'], '', $texto)));
    }

    /** Mismo algoritmo de slug que usa GitHub para anclas de encabezado. */
    private function slug(string $texto): string
    {
        $texto = mb_strtolower($texto);
        $texto = (string) preg_replace('/[^\p{L}\p{N}\s-]/u', '', $texto);

        return (string) preg_replace('/\s+/u', '-', trim($texto));
    }
}
