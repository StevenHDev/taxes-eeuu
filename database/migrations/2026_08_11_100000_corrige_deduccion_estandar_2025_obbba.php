<?php

use App\Support\ParametrosFiscales;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La deducción estándar 2025 sembrada en la Fase 2 ($15,000/$30,000/$22,500)
 * refleja solo el ajuste por inflación original (Rev. Proc. 2024-40, oct-2024)
 * — la "One Big Beautiful Bill Act" (OBBBA, firmada 4-jul-2025) la subió por
 * encima de eso: $15,750/$31,500/$22,625 → $23,625 para HOH. Como
 * ParametrosFiscalesSeeder usa firstOrCreate, cambiar el seeder no corrige
 * las filas que ya existen en una base ya provisionada — de ahí esta
 * migración de datos (mismo estilo que las migraciones "fase*" del catálogo).
 *
 * Nada consumía este parámetro todavía (StandardDeductionCalculator es
 * nuevo), así que esta corrección nunca produjo un cálculo real incorrecto —
 * se corrige antes de que algo lo use.
 */
return new class extends Migration
{
    private const TAX_YEAR = 2025;

    /** @var array<string, int> */
    private const MONTOS_CORRECTOS = [
        'monto_soltero' => 15750,
        'monto_mfj' => 31500,
        'monto_hoh' => 23625,
    ];

    public function up(): void
    {
        $ahora = now();

        foreach (self::MONTOS_CORRECTOS as $clave => $valor) {
            DB::table('parametros_fiscales')->updateOrInsert(
                ['tax_year' => self::TAX_YEAR, 'categoria' => 'deduccion_estandar', 'clave' => $clave],
                ['valor' => json_encode($valor), 'updated_at' => $ahora, 'created_at' => $ahora],
            );
        }

        ParametrosFiscales::invalidate();
    }

    public function down(): void
    {
        $ahora = now();
        $montosViejos = ['monto_soltero' => 15000, 'monto_mfj' => 30000, 'monto_hoh' => 22500];

        foreach ($montosViejos as $clave => $valor) {
            DB::table('parametros_fiscales')
                ->where('tax_year', self::TAX_YEAR)->where('categoria', 'deduccion_estandar')->where('clave', $clave)
                ->update(['valor' => json_encode($valor), 'updated_at' => $ahora]);
        }

        ParametrosFiscales::invalidate();
    }
};
