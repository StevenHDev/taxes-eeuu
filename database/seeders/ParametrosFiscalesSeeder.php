<?php

namespace Database\Seeders;

use App\Models\ParametroFiscal;
use App\Support\ParametrosFiscales;
use Illuminate\Database\Seeder;

/**
 * Montos y umbrales del IRS para tax_year 2025 — confirmados por búsqueda web
 * (no inventados), ver docs/plan-desarrollo-fases.md sección 2 para las
 * fuentes. Un CPA debe validar estas cifras antes de usarlas para preparar
 * declaraciones reales; son las cifras públicas vigentes al momento de
 * construir esta fase, pero esta plataforma no reemplaza el criterio
 * profesional de un preparador.
 */
class ParametrosFiscalesSeeder extends Seeder
{
    private const TAX_YEAR = 2025;

    public function run(): void
    {
        $metadatos = require database_path('data/catalogo-fiscal/2025/parametros-metadatos.php');
        // Mismo orden que el archivo de metadatos (y que la migración 2026_10_29_150000).
        $posiciones = array_flip(array_keys($metadatos));

        foreach ($this->valores() as [$categoria, $clave, $valor]) {
            [$grupo, $tipo, $nombre, $formularioLinea, $condicion, $fuente, $fuenteUrl] = $metadatos["{$categoria}.{$clave}"] ?? [null, null, null, null, null, null, null];

            ParametroFiscal::query()->firstOrCreate(
                ['tax_year' => self::TAX_YEAR, 'categoria' => $categoria, 'clave' => $clave],
                [
                    'valor' => $valor,
                    'grupo' => $grupo,
                    'tipo' => $tipo,
                    'nombre_es' => $nombre,
                    'formulario_linea' => $formularioLinea,
                    'condicion_es' => $condicion,
                    'orden' => ($posiciones["{$categoria}.{$clave}"] ?? count($posiciones)) + 1,
                    'fuente_texto' => $fuente,
                    'fuente_url' => $fuenteUrl,
                ],
            );
        }

        ParametrosFiscales::invalidate();
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function valores(): array
    {
        return [
            // Child Tax Credit: $2,200/dependiente calificado, phase-out desde
            // $200k (soltero/HOH) o $400k (MFJ), $50 menos por cada $1,000 de
            // exceso (redondeado hacia arriba — ver CreditEligibilityCalculator).
            ['credito_ctc', 'monto_por_dependiente', 2200],
            ['credito_ctc', 'phase_out_umbral_soltero', 200000],
            ['credito_ctc', 'phase_out_umbral_mfj', 400000],
            ['credito_ctc', 'reduccion_por_1000_exceso', 50],

            // Credit for Other Dependents: $500/dependiente, mismo phase-out
            // que el CTC (se combinan en un solo cálculo, no por separado).
            ['credito_odc', 'monto_por_dependiente', 500],
            ['credito_odc', 'phase_out_umbral_soltero', 200000],
            ['credito_odc', 'phase_out_umbral_mfj', 400000],
            ['credito_odc', 'reduccion_por_1000_exceso', 50],

            // Prueba de ingreso bruto para "qualifying relative".
            ['dependiente_calificado', 'limite_ingreso_bruto_pariente_calificado', 5200],

            // Deducción estándar 2025 — corregida por la "One Big Beautiful
            // Bill Act" (OBBBA, firmada 4-jul-2025), que subió estos montos
            // POR ENCIMA del ajuste por inflación original de Rev. Proc.
            // 2024-40 ($15,000/$30,000/$22,500) — esa cifra quedó obsoleta a
            // mitad de año. Confirmado con las instrucciones oficiales del
            // Form 1040 (irs.gov/instructions/i1040gi) y múltiples fuentes
            // independientes (Tax Foundation, H&R Block, IRS Newsroom del
            // 2025 tax year 2026 release). Usada por StandardDeductionCalculator.
            ['deduccion_estandar', 'monto_soltero', 15750],
            ['deduccion_estandar', 'monto_mfj', 31500],
            ['deduccion_estandar', 'monto_hoh', 23625],

            // Child and Dependent Care Credit (Form 2441). El porcentaje real
            // del IRS es una tabla escalonada; acá se interpola linealmente
            // entre estos dos extremos como simplificación documentada — ver
            // CreditEligibilityCalculator.
            ['credito_cuidado_dependientes', 'tope_gastos_1_persona', 3000],
            ['credito_cuidado_dependientes', 'tope_gastos_2_mas_personas', 6000],
            ['credito_cuidado_dependientes', 'tope_beneficios_excluibles', 5000],
            ['credito_cuidado_dependientes', 'porcentaje_maximo', 0.35],
            ['credito_cuidado_dependientes', 'porcentaje_minimo', 0.20],
            ['credito_cuidado_dependientes', 'agi_umbral_porcentaje_maximo', 15000],
            ['credito_cuidado_dependientes', 'agi_umbral_porcentaje_minimo', 43000],
            ['credito_cuidado_dependientes', 'edad_limite_dependiente', 13],

            // Foreign Tax Credit — elección "de minimis" sin Form 1116
            // (IRC §904(j)): si el impuesto extranjero pagado no supera este
            // umbral, el crédito es el monto pagado tal cual, sin aplicar la
            // fórmula de límite por ingreso extranjero/ingreso total. Montos
            // fijos por estatuto, no ajustados por inflación — mismos
            // $300/$600 desde que existe la elección, no cambian por año
            // fiscal, pero se siembran acá igual por consistencia con el
            // resto de parámetros versionados. Por encima del umbral, Fase 4
            // del plan de cierre de brecha GTS deja el crédito en 0 con
            // motivo explícito — requiere Form 1116 completo (categoría de
            // ingreso, límite por país, carryover), no modelado todavía —
            // ver ForeignTaxCreditCalculator.
            ['credito_ftc', 'umbral_de_minimis_soltero', 300],
            ['credito_ftc', 'umbral_de_minimis_mfj', 600],

            // Tax Rate Tables 2025 (IRC §1(j), Rev. Proc. 2024-40 tablas 1-3 —
            // leídas directo del PDF oficial, no de un resumen). OBBBA no
            // modificó estos tramos ni sus montos (solo hizo permanentes las
            // tasas de la TCJA que iban a expirar) — a diferencia de la
            // deducción estándar y el rango de QBI, estos números NO
            // necesitaron corrección. QSS usa la misma tabla que MFJ (regla
            // del IRC, ver TaxableIncomeAndTaxCalculator), así que no tiene
            // clave propia. Cada tramo: {desde, tasa} ordenado ascendente.
            ['tax_brackets', 'single', [
                ['desde' => 0, 'tasa' => 0.10],
                ['desde' => 11925, 'tasa' => 0.12],
                ['desde' => 48475, 'tasa' => 0.22],
                ['desde' => 103350, 'tasa' => 0.24],
                ['desde' => 197300, 'tasa' => 0.32],
                ['desde' => 250525, 'tasa' => 0.35],
                ['desde' => 626350, 'tasa' => 0.37],
            ]],
            ['tax_brackets', 'mfj', [
                ['desde' => 0, 'tasa' => 0.10],
                ['desde' => 23850, 'tasa' => 0.12],
                ['desde' => 96950, 'tasa' => 0.22],
                ['desde' => 206700, 'tasa' => 0.24],
                ['desde' => 394600, 'tasa' => 0.32],
                ['desde' => 501050, 'tasa' => 0.35],
                ['desde' => 751600, 'tasa' => 0.37],
            ]],
            ['tax_brackets', 'hoh', [
                ['desde' => 0, 'tasa' => 0.10],
                ['desde' => 17000, 'tasa' => 0.12],
                ['desde' => 64850, 'tasa' => 0.22],
                ['desde' => 103350, 'tasa' => 0.24],
                ['desde' => 197300, 'tasa' => 0.32],
                ['desde' => 250500, 'tasa' => 0.35],
                ['desde' => 626350, 'tasa' => 0.37],
            ]],

            // Self-employment tax (Schedule SE) — tope de salario de Social
            // Security 2025 anunciado por la SSA en oct-2024 (no lo fija el
            // IRS ni Rev. Proc. 2024-40, es un anuncio separado). Usado por
            // SelfEmploymentTaxCalculator.
            ['self_employment_tax', 'tope_salario_social_security', 176100],

            // Additional Medicare Tax (Form 8959) — umbrales fijos por ley
            // (IRC §3101(b)(2)) desde 2013, NO ajustados por inflación ni
            // tocados por OBBBA. Usado por AdditionalMedicareTaxCalculator.
            ['additional_medicare_tax', 'umbral_soltero', 200000],
            ['additional_medicare_tax', 'umbral_mfj', 250000],

            // Net Investment Income Tax (Form 8960) — mismos umbrales fijos
            // desde 2013 (IRC §1411), sin ajuste por inflación ni cambio de
            // OBBBA. Usado por NiitCalculator.
            ['niit', 'umbral_soltero', 200000],
            ['niit', 'umbral_mfj', 250000],

            // Qualified Business Income (Form 8995, §199A) — umbral 2025
            // donde empieza cualquier limitación (Rev. Proc. 2024-40 .27).
            // OBBBA NO cambió este umbral — solo amplió el RANGO de phase-out
            // (que esta plataforma no modela, ver QbiCalculator, limitación
            // documentada). Usado por QbiCalculator.
            ['qbi', 'umbral_soltero', 197300],
            ['qbi', 'umbral_mfj', 394600],

            // ---- F6 · 6.1a: parámetros que usa el motor de cálculo. Cada uno lleva su
            // fuente y página en parametros-metadatos.php (los porcentajes nuevos van
            // como texto para no pasar por coma flotante; las tablas son filas
            // {hasta, ...} que se leen con ParametrosFiscales::tabla()).
            ['tax_brackets', 'mfs', [
                ['desde' => 0, 'tasa' => 0.1],
                ['desde' => 11925, 'tasa' => 0.12],
                ['desde' => 48475, 'tasa' => 0.22],
                ['desde' => 103350, 'tasa' => 0.24],
                ['desde' => 197300, 'tasa' => 0.32],
                ['desde' => 250525, 'tasa' => 0.35],
                ['desde' => 375800, 'tasa' => 0.37],
            ]],
            ['deduccion_estandar', 'monto_mfs', 15750],
            ['deduccion_estandar', 'adicional_65_ciego_casado', 1600],
            ['deduccion_estandar', 'adicional_65_ciego_soltero_hoh', 2000],
            ['deduccion_estandar', 'dependiente_minimo', 1350],
            ['deduccion_estandar', 'dependiente_adicional_ingreso_trabajo', 450],
            ['self_employment_tax', 'tasa_seguro_social', '0.124'],
            ['self_employment_tax', 'tasa_medicare', '0.029'],
            ['self_employment_tax', 'factor_ingreso_neto', '0.9235'],
            ['self_employment_tax', 'minimo_ingreso_neto', 400],
            ['self_employment_tax', 'factor_deduccion_mitad', '0.50'],
            ['additional_medicare_tax', 'tasa', '0.009'],
            ['additional_medicare_tax', 'umbral_mfs', 125000],
            ['additional_medicare_tax', 'tasa_medicare_regular', '0.0145'],
            ['niit', 'tasa', '0.038'],
            ['niit', 'umbral_mfs', 125000],
            ['qbi', 'tasa', '0.20'],
            ['credito_cuidado_dependientes', 'tabla', [
                ['hasta' => 15000, 'decimal' => '0.35'],
                ['hasta' => 17000, 'decimal' => '0.34'],
                ['hasta' => 19000, 'decimal' => '0.33'],
                ['hasta' => 21000, 'decimal' => '0.32'],
                ['hasta' => 23000, 'decimal' => '0.31'],
                ['hasta' => 25000, 'decimal' => '0.30'],
                ['hasta' => 27000, 'decimal' => '0.29'],
                ['hasta' => 29000, 'decimal' => '0.28'],
                ['hasta' => 31000, 'decimal' => '0.27'],
                ['hasta' => 33000, 'decimal' => '0.26'],
                ['hasta' => 35000, 'decimal' => '0.25'],
                ['hasta' => 37000, 'decimal' => '0.24'],
                ['hasta' => 39000, 'decimal' => '0.23'],
                ['hasta' => 41000, 'decimal' => '0.22'],
                ['hasta' => 43000, 'decimal' => '0.21'],
                ['hasta' => null, 'decimal' => '0.20'],
            ]],
            ['credito_eic', 'por_hijos', [['hasta' => 0, 'hijos' => '0', 'tasa_entrada' => '0.0765', 'tasa_reduccion' => '0.0765', 'ingreso_ganado' => 8490, 'maximo' => 649, 'inicio_reduccion_mfj' => 17730, 'fin_reduccion_mfj' => 26214, 'inicio_reduccion_otros' => 10620, 'fin_reduccion_otros' => 19104], ['hasta' => 1, 'hijos' => '1', 'tasa_entrada' => '0.34', 'tasa_reduccion' => '0.1598', 'ingreso_ganado' => 12730, 'maximo' => 4328, 'inicio_reduccion_mfj' => 30470, 'fin_reduccion_mfj' => 57554, 'inicio_reduccion_otros' => 23350, 'fin_reduccion_otros' => 50434], ['hasta' => 2, 'hijos' => '2', 'tasa_entrada' => '0.40', 'tasa_reduccion' => '0.2106', 'ingreso_ganado' => 17880, 'maximo' => 7152, 'inicio_reduccion_mfj' => 30470, 'fin_reduccion_mfj' => 64430, 'inicio_reduccion_otros' => 23350, 'fin_reduccion_otros' => 57310], ['hasta' => null, 'hijos' => '3+', 'tasa_entrada' => '0.45', 'tasa_reduccion' => '0.2106', 'ingreso_ganado' => 17880, 'maximo' => 8046, 'inicio_reduccion_mfj' => 30470, 'fin_reduccion_mfj' => 68675, 'inicio_reduccion_otros' => 23350, 'fin_reduccion_otros' => 61555]]],
            ['credito_eic', 'tope_ingreso_inversion', 11950],
            ['credito_actc', 'tope_reembolsable_por_hijo', 1700],
            ['credito_actc', 'umbral_ingreso_trabajo', 2500],
            ['credito_actc', 'tasa_ingreso_trabajo', '0.15'],
            ['schedule_1a', 'propinas_tope', 25000],
            ['schedule_1a', 'propinas_umbral_magi_soltero', 150000],
            ['schedule_1a', 'propinas_umbral_magi_mfj', 300000],
            ['schedule_1a', 'propinas_reduccion_por_1000', 100],
            ['schedule_1a', 'horas_extra_tope_soltero', 12500],
            ['schedule_1a', 'horas_extra_tope_mfj', 25000],
            ['schedule_1a', 'horas_extra_umbral_magi_soltero', 150000],
            ['schedule_1a', 'horas_extra_umbral_magi_mfj', 300000],
            ['schedule_1a', 'horas_extra_reduccion_por_1000', 100],
            ['schedule_1a', 'vehiculo_tope', 10000],
            ['schedule_1a', 'vehiculo_umbral_magi_soltero', 100000],
            ['schedule_1a', 'vehiculo_umbral_magi_mfj', 200000],
            ['schedule_1a', 'vehiculo_reduccion_por_1000', 200],
            ['schedule_1a', 'mayores_tope', 6000],
            ['schedule_1a', 'mayores_umbral_magi_soltero', 75000],
            ['schedule_1a', 'mayores_umbral_magi_mfj', 150000],
            ['schedule_1a', 'mayores_tasa_reduccion', '0.06'],
            ['credito_prima_salud', 'linea_pobreza_48_estados', [
                ['hasta' => 1, 'monto' => 15060],
                ['hasta' => 2, 'monto' => 20440],
                ['hasta' => 3, 'monto' => 25820],
                ['hasta' => 4, 'monto' => 31200],
                ['hasta' => 5, 'monto' => 36580],
                ['hasta' => 6, 'monto' => 41960],
                ['hasta' => 7, 'monto' => 47340],
                ['hasta' => 8, 'monto' => 52720],
            ]],
            ['credito_prima_salud', 'linea_pobreza_48_estados_adicional', 5380],
            ['credito_prima_salud', 'linea_pobreza_alaska', [
                ['hasta' => 1, 'monto' => 18810],
                ['hasta' => 2, 'monto' => 25540],
                ['hasta' => 3, 'monto' => 32270],
                ['hasta' => 4, 'monto' => 39000],
                ['hasta' => 5, 'monto' => 45730],
                ['hasta' => 6, 'monto' => 52460],
                ['hasta' => 7, 'monto' => 59190],
                ['hasta' => 8, 'monto' => 65920],
            ]],
            ['credito_prima_salud', 'linea_pobreza_alaska_adicional', 6730],
            ['credito_prima_salud', 'linea_pobreza_hawaii', [
                ['hasta' => 1, 'monto' => 17310],
                ['hasta' => 2, 'monto' => 23500],
                ['hasta' => 3, 'monto' => 29690],
                ['hasta' => 4, 'monto' => 35880],
                ['hasta' => 5, 'monto' => 42070],
                ['hasta' => 6, 'monto' => 48260],
                ['hasta' => 7, 'monto' => 54450],
                ['hasta' => 8, 'monto' => 60640],
            ]],
            ['credito_prima_salud', 'linea_pobreza_hawaii_adicional', 6190],
            ['credito_prima_salud', 'tabla_cifra_aplicable', [
                ['hasta' => 149, 'decimal' => '0.0000'],
                ['hasta' => 150, 'decimal' => '0.0000'],
                ['hasta' => 151, 'decimal' => '0.0004'],
                ['hasta' => 152, 'decimal' => '0.0008'],
                ['hasta' => 153, 'decimal' => '0.0012'],
                ['hasta' => 154, 'decimal' => '0.0016'],
                ['hasta' => 155, 'decimal' => '0.0020'],
                ['hasta' => 156, 'decimal' => '0.0024'],
                ['hasta' => 157, 'decimal' => '0.0028'],
                ['hasta' => 158, 'decimal' => '0.0032'],
                ['hasta' => 159, 'decimal' => '0.0036'],
                ['hasta' => 160, 'decimal' => '0.0040'],
                ['hasta' => 161, 'decimal' => '0.0044'],
                ['hasta' => 162, 'decimal' => '0.0048'],
                ['hasta' => 163, 'decimal' => '0.0052'],
                ['hasta' => 164, 'decimal' => '0.0056'],
                ['hasta' => 165, 'decimal' => '0.0060'],
                ['hasta' => 166, 'decimal' => '0.0064'],
                ['hasta' => 167, 'decimal' => '0.0068'],
                ['hasta' => 168, 'decimal' => '0.0072'],
                ['hasta' => 169, 'decimal' => '0.0076'],
                ['hasta' => 170, 'decimal' => '0.0080'],
                ['hasta' => 171, 'decimal' => '0.0084'],
                ['hasta' => 172, 'decimal' => '0.0088'],
                ['hasta' => 173, 'decimal' => '0.0092'],
                ['hasta' => 174, 'decimal' => '0.0096'],
                ['hasta' => 175, 'decimal' => '0.0100'],
                ['hasta' => 176, 'decimal' => '0.0104'],
                ['hasta' => 177, 'decimal' => '0.0108'],
                ['hasta' => 178, 'decimal' => '0.0112'],
                ['hasta' => 179, 'decimal' => '0.0116'],
                ['hasta' => 180, 'decimal' => '0.0120'],
                ['hasta' => 181, 'decimal' => '0.0124'],
                ['hasta' => 182, 'decimal' => '0.0128'],
                ['hasta' => 183, 'decimal' => '0.0132'],
                ['hasta' => 184, 'decimal' => '0.0136'],
                ['hasta' => 185, 'decimal' => '0.0140'],
                ['hasta' => 186, 'decimal' => '0.0144'],
                ['hasta' => 187, 'decimal' => '0.0148'],
                ['hasta' => 188, 'decimal' => '0.0152'],
                ['hasta' => 189, 'decimal' => '0.0156'],
                ['hasta' => 190, 'decimal' => '0.0160'],
                ['hasta' => 191, 'decimal' => '0.0164'],
                ['hasta' => 192, 'decimal' => '0.0168'],
                ['hasta' => 193, 'decimal' => '0.0172'],
                ['hasta' => 194, 'decimal' => '0.0176'],
                ['hasta' => 195, 'decimal' => '0.0180'],
                ['hasta' => 196, 'decimal' => '0.0184'],
                ['hasta' => 197, 'decimal' => '0.0188'],
                ['hasta' => 198, 'decimal' => '0.0192'],
                ['hasta' => 199, 'decimal' => '0.0196'],
                ['hasta' => 200, 'decimal' => '0.0200'],
                ['hasta' => 201, 'decimal' => '0.0204'],
                ['hasta' => 202, 'decimal' => '0.0208'],
                ['hasta' => 203, 'decimal' => '0.0212'],
                ['hasta' => 204, 'decimal' => '0.0216'],
                ['hasta' => 205, 'decimal' => '0.0220'],
                ['hasta' => 206, 'decimal' => '0.0224'],
                ['hasta' => 207, 'decimal' => '0.0228'],
                ['hasta' => 208, 'decimal' => '0.0232'],
                ['hasta' => 209, 'decimal' => '0.0236'],
                ['hasta' => 210, 'decimal' => '0.0240'],
                ['hasta' => 211, 'decimal' => '0.0244'],
                ['hasta' => 212, 'decimal' => '0.0248'],
                ['hasta' => 213, 'decimal' => '0.0252'],
                ['hasta' => 214, 'decimal' => '0.0256'],
                ['hasta' => 215, 'decimal' => '0.0260'],
                ['hasta' => 216, 'decimal' => '0.0264'],
                ['hasta' => 217, 'decimal' => '0.0268'],
                ['hasta' => 218, 'decimal' => '0.0272'],
                ['hasta' => 219, 'decimal' => '0.0276'],
                ['hasta' => 220, 'decimal' => '0.0280'],
                ['hasta' => 221, 'decimal' => '0.0284'],
                ['hasta' => 222, 'decimal' => '0.0288'],
                ['hasta' => 223, 'decimal' => '0.0292'],
                ['hasta' => 224, 'decimal' => '0.0296'],
                ['hasta' => 225, 'decimal' => '0.0300'],
                ['hasta' => 226, 'decimal' => '0.0304'],
                ['hasta' => 227, 'decimal' => '0.0308'],
                ['hasta' => 228, 'decimal' => '0.0312'],
                ['hasta' => 229, 'decimal' => '0.0316'],
                ['hasta' => 230, 'decimal' => '0.0320'],
                ['hasta' => 231, 'decimal' => '0.0324'],
                ['hasta' => 232, 'decimal' => '0.0328'],
                ['hasta' => 233, 'decimal' => '0.0332'],
                ['hasta' => 234, 'decimal' => '0.0336'],
                ['hasta' => 235, 'decimal' => '0.0340'],
                ['hasta' => 236, 'decimal' => '0.0344'],
                ['hasta' => 237, 'decimal' => '0.0348'],
                ['hasta' => 238, 'decimal' => '0.0352'],
                ['hasta' => 239, 'decimal' => '0.0356'],
                ['hasta' => 240, 'decimal' => '0.0360'],
                ['hasta' => 241, 'decimal' => '0.0364'],
                ['hasta' => 242, 'decimal' => '0.0368'],
                ['hasta' => 243, 'decimal' => '0.0372'],
                ['hasta' => 244, 'decimal' => '0.0376'],
                ['hasta' => 245, 'decimal' => '0.0380'],
                ['hasta' => 246, 'decimal' => '0.0384'],
                ['hasta' => 247, 'decimal' => '0.0388'],
                ['hasta' => 248, 'decimal' => '0.0392'],
                ['hasta' => 249, 'decimal' => '0.0396'],
                ['hasta' => 250, 'decimal' => '0.0400'],
                ['hasta' => 251, 'decimal' => '0.0404'],
                ['hasta' => 252, 'decimal' => '0.0408'],
                ['hasta' => 253, 'decimal' => '0.0412'],
                ['hasta' => 254, 'decimal' => '0.0416'],
                ['hasta' => 255, 'decimal' => '0.0420'],
                ['hasta' => 256, 'decimal' => '0.0424'],
                ['hasta' => 257, 'decimal' => '0.0428'],
                ['hasta' => 258, 'decimal' => '0.0432'],
                ['hasta' => 259, 'decimal' => '0.0436'],
                ['hasta' => 260, 'decimal' => '0.0440'],
                ['hasta' => 261, 'decimal' => '0.0444'],
                ['hasta' => 262, 'decimal' => '0.0448'],
                ['hasta' => 263, 'decimal' => '0.0452'],
                ['hasta' => 264, 'decimal' => '0.0456'],
                ['hasta' => 265, 'decimal' => '0.0460'],
                ['hasta' => 266, 'decimal' => '0.0464'],
                ['hasta' => 267, 'decimal' => '0.0468'],
                ['hasta' => 268, 'decimal' => '0.0472'],
                ['hasta' => 269, 'decimal' => '0.0476'],
                ['hasta' => 270, 'decimal' => '0.0480'],
                ['hasta' => 271, 'decimal' => '0.0484'],
                ['hasta' => 272, 'decimal' => '0.0488'],
                ['hasta' => 273, 'decimal' => '0.0492'],
                ['hasta' => 274, 'decimal' => '0.0496'],
                ['hasta' => 275, 'decimal' => '0.0500'],
                ['hasta' => 276, 'decimal' => '0.0504'],
                ['hasta' => 277, 'decimal' => '0.0508'],
                ['hasta' => 278, 'decimal' => '0.0512'],
                ['hasta' => 279, 'decimal' => '0.0516'],
                ['hasta' => 280, 'decimal' => '0.0520'],
                ['hasta' => 281, 'decimal' => '0.0524'],
                ['hasta' => 282, 'decimal' => '0.0528'],
                ['hasta' => 283, 'decimal' => '0.0532'],
                ['hasta' => 284, 'decimal' => '0.0536'],
                ['hasta' => 285, 'decimal' => '0.0540'],
                ['hasta' => 286, 'decimal' => '0.0544'],
                ['hasta' => 287, 'decimal' => '0.0548'],
                ['hasta' => 288, 'decimal' => '0.0552'],
                ['hasta' => 289, 'decimal' => '0.0556'],
                ['hasta' => 290, 'decimal' => '0.0560'],
                ['hasta' => 291, 'decimal' => '0.0564'],
                ['hasta' => 292, 'decimal' => '0.0568'],
                ['hasta' => 293, 'decimal' => '0.0572'],
                ['hasta' => 294, 'decimal' => '0.0576'],
                ['hasta' => 295, 'decimal' => '0.0580'],
                ['hasta' => 296, 'decimal' => '0.0584'],
                ['hasta' => 297, 'decimal' => '0.0588'],
                ['hasta' => 298, 'decimal' => '0.0592'],
                ['hasta' => 299, 'decimal' => '0.0596'],
                ['hasta' => 300, 'decimal' => '0.0600'],
                ['hasta' => 301, 'decimal' => '0.0603'],
                ['hasta' => 302, 'decimal' => '0.0605'],
                ['hasta' => 303, 'decimal' => '0.0608'],
                ['hasta' => 304, 'decimal' => '0.0610'],
                ['hasta' => 305, 'decimal' => '0.0613'],
                ['hasta' => 306, 'decimal' => '0.0615'],
                ['hasta' => 307, 'decimal' => '0.0618'],
                ['hasta' => 308, 'decimal' => '0.0620'],
                ['hasta' => 309, 'decimal' => '0.0623'],
                ['hasta' => 310, 'decimal' => '0.0625'],
                ['hasta' => 311, 'decimal' => '0.0628'],
                ['hasta' => 312, 'decimal' => '0.0630'],
                ['hasta' => 313, 'decimal' => '0.0633'],
                ['hasta' => 314, 'decimal' => '0.0635'],
                ['hasta' => 315, 'decimal' => '0.0638'],
                ['hasta' => 316, 'decimal' => '0.0640'],
                ['hasta' => 317, 'decimal' => '0.0643'],
                ['hasta' => 318, 'decimal' => '0.0645'],
                ['hasta' => 319, 'decimal' => '0.0648'],
                ['hasta' => 320, 'decimal' => '0.0650'],
                ['hasta' => 321, 'decimal' => '0.0653'],
                ['hasta' => 322, 'decimal' => '0.0655'],
                ['hasta' => 323, 'decimal' => '0.0658'],
                ['hasta' => 324, 'decimal' => '0.0660'],
                ['hasta' => 325, 'decimal' => '0.0663'],
                ['hasta' => 326, 'decimal' => '0.0665'],
                ['hasta' => 327, 'decimal' => '0.0668'],
                ['hasta' => 328, 'decimal' => '0.0670'],
                ['hasta' => 329, 'decimal' => '0.0673'],
                ['hasta' => 330, 'decimal' => '0.0675'],
                ['hasta' => 331, 'decimal' => '0.0678'],
                ['hasta' => 332, 'decimal' => '0.0680'],
                ['hasta' => 333, 'decimal' => '0.0683'],
                ['hasta' => 334, 'decimal' => '0.0685'],
                ['hasta' => 335, 'decimal' => '0.0688'],
                ['hasta' => 336, 'decimal' => '0.0690'],
                ['hasta' => 337, 'decimal' => '0.0693'],
                ['hasta' => 338, 'decimal' => '0.0695'],
                ['hasta' => 339, 'decimal' => '0.0698'],
                ['hasta' => 340, 'decimal' => '0.0700'],
                ['hasta' => 341, 'decimal' => '0.0703'],
                ['hasta' => 342, 'decimal' => '0.0705'],
                ['hasta' => 343, 'decimal' => '0.0708'],
                ['hasta' => 344, 'decimal' => '0.0710'],
                ['hasta' => 345, 'decimal' => '0.0713'],
                ['hasta' => 346, 'decimal' => '0.0715'],
                ['hasta' => 347, 'decimal' => '0.0718'],
                ['hasta' => 348, 'decimal' => '0.0720'],
                ['hasta' => 349, 'decimal' => '0.0723'],
                ['hasta' => 350, 'decimal' => '0.0725'],
                ['hasta' => 351, 'decimal' => '0.0728'],
                ['hasta' => 352, 'decimal' => '0.0730'],
                ['hasta' => 353, 'decimal' => '0.0733'],
                ['hasta' => 354, 'decimal' => '0.0735'],
                ['hasta' => 355, 'decimal' => '0.0738'],
                ['hasta' => 356, 'decimal' => '0.0740'],
                ['hasta' => 357, 'decimal' => '0.0743'],
                ['hasta' => 358, 'decimal' => '0.0745'],
                ['hasta' => 359, 'decimal' => '0.0748'],
                ['hasta' => 360, 'decimal' => '0.0750'],
                ['hasta' => 361, 'decimal' => '0.0753'],
                ['hasta' => 362, 'decimal' => '0.0755'],
                ['hasta' => 363, 'decimal' => '0.0758'],
                ['hasta' => 364, 'decimal' => '0.0760'],
                ['hasta' => 365, 'decimal' => '0.0763'],
                ['hasta' => 366, 'decimal' => '0.0765'],
                ['hasta' => 367, 'decimal' => '0.0768'],
                ['hasta' => 368, 'decimal' => '0.0770'],
                ['hasta' => 369, 'decimal' => '0.0773'],
                ['hasta' => 370, 'decimal' => '0.0775'],
                ['hasta' => 371, 'decimal' => '0.0778'],
                ['hasta' => 372, 'decimal' => '0.0780'],
                ['hasta' => 373, 'decimal' => '0.0783'],
                ['hasta' => 374, 'decimal' => '0.0785'],
                ['hasta' => 375, 'decimal' => '0.0788'],
                ['hasta' => 376, 'decimal' => '0.0790'],
                ['hasta' => 377, 'decimal' => '0.0793'],
                ['hasta' => 378, 'decimal' => '0.0795'],
                ['hasta' => 379, 'decimal' => '0.0798'],
                ['hasta' => 380, 'decimal' => '0.0800'],
                ['hasta' => 381, 'decimal' => '0.0803'],
                ['hasta' => 382, 'decimal' => '0.0805'],
                ['hasta' => 383, 'decimal' => '0.0808'],
                ['hasta' => 384, 'decimal' => '0.0810'],
                ['hasta' => 385, 'decimal' => '0.0813'],
                ['hasta' => 386, 'decimal' => '0.0815'],
                ['hasta' => 387, 'decimal' => '0.0818'],
                ['hasta' => 388, 'decimal' => '0.0820'],
                ['hasta' => 389, 'decimal' => '0.0823'],
                ['hasta' => 390, 'decimal' => '0.0825'],
                ['hasta' => 391, 'decimal' => '0.0828'],
                ['hasta' => 392, 'decimal' => '0.0830'],
                ['hasta' => 393, 'decimal' => '0.0833'],
                ['hasta' => 394, 'decimal' => '0.0835'],
                ['hasta' => 395, 'decimal' => '0.0838'],
                ['hasta' => 396, 'decimal' => '0.0840'],
                ['hasta' => 397, 'decimal' => '0.0843'],
                ['hasta' => 398, 'decimal' => '0.0845'],
                ['hasta' => 399, 'decimal' => '0.0848'],
                ['hasta' => null, 'decimal' => '0.0850'],
            ]],
            ['credito_prima_salud', 'tabla_tope_devolucion', [['hasta' => 199, 'soltero' => 375, 'otros' => 750], ['hasta' => 299, 'soltero' => 975, 'otros' => 1950], ['hasta' => 399, 'soltero' => 1625, 'otros' => 3250], ['hasta' => null, 'soltero' => null, 'otros' => null]]],
            ['schedule_c', 'millaje_estandar', '0.70'],
            ['schedule_c', 'comidas_porcentaje_deducible', '0.50'],
            ['schedule_c', 'oficina_casa_tarifa_pie_cuadrado', 5],
            ['schedule_c', 'oficina_casa_tope_pies_cuadrados', 300],
            ['schedule_1', 'tope_gastos_educador', 300],
        ];
    }
}
