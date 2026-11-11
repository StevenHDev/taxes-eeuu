<?php

use App\Support\ParametrosFiscales;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F6, cierre de la ola 3 (11 nov. 2026): dos parámetros de 2025 que las calculadoras nuevas necesitan.
 * Copia fija para producción de lo que siembran `ParametrosFiscalesSeeder` y `parametros-metadatos.php`
 * (mismo patrón que 2026_11_09_160000). Solo inserta si no existen; 2026 los recibe pendientes al
 * correr `catalogo-fiscal:cargar 2026` (completa lo que el origen sembró después).
 */
return new class extends Migration
{
    private const TAX_YEAR = 2025;

    /** categoria.clave => [valor, grupo, tipo, nombre, formulario y línea, condición, fuente, URL]. */
    private const NUEVOS = [
        'credito_cuidado_dependientes.tope_beneficios_excluibles' => [5000, 'creditos', 'monto', 'Crédito por cuidado: máximo de beneficios de cuidado que se excluyen', 'Form 2441, línea 21', 'Con MFS y el ingreso del cónyuge en la línea 19, la mitad (USD 2.500).', 'Form 2441 de 2025, p. 2, línea 21 ("Enter $5,000 ($2,500 if married filing separately and you were required to enter your spouse\'s earned income on line 19)")', 'https://www.irs.gov/pub/irs-prior/f2441--2025.pdf'],
        'additional_medicare_tax.tasa_medicare_regular' => ['0.0145', 'topes_umbrales', 'porcentaje', 'Medicare: tasa normal retenida al empleado', 'Form 8959, línea 21 (1040, línea 25c)', null, 'Form 8959 de 2025, p. 1, línea 21 ("Multiply line 20 by 1.45% (0.0145)")', 'https://www.irs.gov/pub/irs-prior/f8959--2025.pdf'],
    ];

    public function up(): void
    {
        if (! DB::table('parametros_fiscales')->where('tax_year', self::TAX_YEAR)->exists()) {
            return; // base sin el año sembrado: lo siembra el seeder completo
        }

        $ahora = now();
        $orden = (int) DB::table('parametros_fiscales')->where('tax_year', self::TAX_YEAR)->max('orden');

        foreach (self::NUEVOS as $codigo => [$valor, $grupo, $tipo, $nombre, $formularioLinea, $condicion, $fuente, $url]) {
            [$categoria, $clave] = explode('.', $codigo, 2);
            $llave = ['tax_year' => self::TAX_YEAR, 'categoria' => $categoria, 'clave' => $clave];

            if (DB::table('parametros_fiscales')->where($llave)->exists()) {
                continue;
            }

            DB::table('parametros_fiscales')->insert([
                ...$llave,
                'valor' => json_encode($valor, JSON_UNESCAPED_UNICODE),
                'grupo' => $grupo,
                'tipo' => $tipo,
                'nombre_es' => $nombre,
                'formulario_linea' => $formularioLinea,
                'condicion_es' => $condicion,
                'orden' => ++$orden,
                'fuente_texto' => $fuente,
                'fuente_url' => $url,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        ParametrosFiscales::invalidate();
    }

    /** No se borra nada: un parámetro con cifra del IRS no se quita al revertir. */
    public function down(): void {}
};
