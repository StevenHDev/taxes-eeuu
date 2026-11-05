<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Models\BitacoraActividad;
use App\Models\User;
use App\Services\Expediente\FichaCliente;
use App\Services\Expediente\HojaTaxwise;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pestaña "Hoja para TaxWise" de la ficha (F5.5): lo que el preparador digita
 * en TaxWise y su descarga en CSV. El CSV sale siempre enmascarado y deja la
 * acción "Exportado" en la bitácora, sin contenido.
 */
class HojaTaxwiseController extends Controller
{
    public function __construct(
        private readonly HojaTaxwise $hoja,
        private readonly FichaCliente $ficha,
    ) {}

    public function show(Request $request, User $cliente): Response
    {
        $this->authorize('view', $cliente);

        $taxYear = $this->anio($request);

        return Inertia::render('ficha/hoja-taxwise', [
            'ficha' => $this->ficha->encabezado($cliente, $taxYear, $request->user()),
            'hoja' => Inertia::defer(fn () => $this->hoja->para($cliente, $taxYear), rescue: true),
            'csv_url' => route('clientes.hoja-taxwise.csv', ['cliente' => $cliente->id, 'tax_year' => $taxYear]),
        ]);
    }

    public function csv(Request $request, User $cliente): StreamedResponse
    {
        $this->authorize('view', $cliente);

        $taxYear = $this->anio($request);
        $actor = $request->user();

        // La bitácora guarda quién descargó y cuándo, nunca el contenido.
        BitacoraActividad::query()->create([
            'actor_id' => $actor->id,
            'actor_nombre' => $actor->name,
            'actor_email' => $actor->email,
            'accion' => AccionAuditoria::Exportado,
            'auditable_type' => $cliente::class,
            'auditable_id' => $cliente->id,
            'etiqueta' => "Descargó la hoja para TaxWise {$taxYear} (cliente #{$cliente->id})",
            'campos_afectados' => null,
            'ip_address' => $request->ip(),
        ]);

        return response()->streamDownload(function () use ($cliente, $taxYear) {
            $salida = fopen('php://output', 'w');
            // El BOM hace que Excel abra el archivo como UTF-8.
            fwrite($salida, "\xEF\xBB\xBF");

            foreach ($this->hoja->filasCsv($cliente, $taxYear) as $fila) {
                fputcsv($salida, array_map($this->celda(...), $fila), ',', '"', '');
            }

            fclose($salida);
        }, "hoja-taxwise-{$cliente->id}-{$taxYear}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function anio(Request $request): int
    {
        return (int) $request->query('tax_year', config('tax.current_tax_year'));
    }

    /**
     * Evita que Excel ejecute como fórmula un texto que empieza con =, + o @
     * (o con - y una letra); los montos negativos ("-100.00") no se tocan.
     */
    private function celda(string $texto): string
    {
        return preg_match('/^(?:[=+@\t\r]|-(?!\d))/', $texto) === 1 ? "'".$texto : $texto;
    }
}
