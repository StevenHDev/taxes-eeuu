<?php

namespace App\Services\Calculo\Formularios\Form8962;

/**
 * Qué tabla de la línea de pobreza usa la línea 4 del 8962 (i8962 2025, p. 8: "Check the box to indicate
 * your state of residence in 2025"): Alaska (Tabla 1-2), Hawái (Tabla 1-3) o los 48 estados contiguos y DC
 * (Tabla 1-1).
 *
 * La plataforma no pregunta el estado de residencia: solo guarda la dirección postal (pregunta A2, texto
 * libre). Se toma el estado si la dirección trae, una sola vez, la sigla o el nombre del estado seguido del
 * código postal ("Miami, FL 33101"). Si no se reconoce, o trae dos estados distintos, devuelve null y la
 * línea 4 sale `falta_dato`. Puerto Rico y los territorios no entran: no tienen tabla en el 8962.
 */
final class EstadoResidencia
{
    /** Los 50 estados y DC con su nombre (en inglés y, si cambia, en español) para reconocerlos en la dirección. */
    private const ESTADOS = [
        'AL' => ['ALABAMA'], 'AK' => ['ALASKA'], 'AZ' => ['ARIZONA'], 'AR' => ['ARKANSAS'],
        'CA' => ['CALIFORNIA'], 'CO' => ['COLORADO'], 'CT' => ['CONNECTICUT'], 'DE' => ['DELAWARE'],
        'DC' => ['DISTRICT OF COLUMBIA'], 'FL' => ['FLORIDA'], 'GA' => ['GEORGIA'], 'HI' => ['HAWAII', 'HAWAI', 'HAWÁI'],
        'ID' => ['IDAHO'], 'IL' => ['ILLINOIS'], 'IN' => ['INDIANA'], 'IA' => ['IOWA'], 'KS' => ['KANSAS'],
        'KY' => ['KENTUCKY'], 'LA' => ['LOUISIANA', 'LUISIANA'], 'ME' => ['MAINE'], 'MD' => ['MARYLAND'],
        'MA' => ['MASSACHUSETTS'], 'MI' => ['MICHIGAN'], 'MN' => ['MINNESOTA'], 'MS' => ['MISSISSIPPI', 'MISISIPI'],
        'MO' => ['MISSOURI', 'MISURI'], 'MT' => ['MONTANA'], 'NE' => ['NEBRASKA'], 'NV' => ['NEVADA'],
        'NH' => ['NEW HAMPSHIRE', 'NUEVO HAMPSHIRE'], 'NJ' => ['NEW JERSEY', 'NUEVA JERSEY'], 'NM' => ['NEW MEXICO', 'NUEVO MÉXICO', 'NUEVO MEXICO'],
        'NY' => ['NEW YORK', 'NUEVA YORK'], 'NC' => ['NORTH CAROLINA', 'CAROLINA DEL NORTE'], 'ND' => ['NORTH DAKOTA', 'DAKOTA DEL NORTE'],
        'OH' => ['OHIO'], 'OK' => ['OKLAHOMA'], 'OR' => ['OREGON'], 'PA' => ['PENNSYLVANIA', 'PENSILVANIA'],
        'RI' => ['RHODE ISLAND'], 'SC' => ['SOUTH CAROLINA', 'CAROLINA DEL SUR'], 'SD' => ['SOUTH DAKOTA', 'DAKOTA DEL SUR'],
        'TN' => ['TENNESSEE'], 'TX' => ['TEXAS'], 'UT' => ['UTAH'], 'VT' => ['VERMONT'], 'VA' => ['VIRGINIA'],
        'WA' => ['WASHINGTON'], 'WV' => ['WEST VIRGINIA', 'VIRGINIA OCCIDENTAL'], 'WI' => ['WISCONSIN'], 'WY' => ['WYOMING'],
    ];

    /** La tabla (`TablasPrima::TABLA_*`) que corresponde a la dirección, o null si no se reconoce un solo estado. */
    public static function tablaDesdeDireccion(?string $direccion): ?string
    {
        $estado = self::estadoDesdeDireccion($direccion);

        return match ($estado) {
            null => null,
            'AK' => TablasPrima::TABLA_ALASKA,
            'HI' => TablasPrima::TABLA_HAWAII,
            default => TablasPrima::TABLA_48_ESTADOS,
        };
    }

    /** La sigla del estado de la dirección, o null. */
    public static function estadoDesdeDireccion(?string $direccion): ?string
    {
        if ($direccion === null || trim($direccion) === '') {
            return null;
        }

        $texto = mb_strtoupper($direccion);
        $encontrados = [];

        if (preg_match_all('/\b([A-Z]{2})\.?[\s,]+\d{5}(?:-\d{4})?\b/u', $texto, $m) > 0) {
            foreach ($m[1] as $sigla) {
                if (isset(self::ESTADOS[$sigla])) {
                    $encontrados[$sigla] = true;
                }
            }
        }

        // Los nombres más largos primero y, al reconocerlos, se borran: "West Virginia 25301" no es también "Virginia".
        $nombres = [];

        foreach (self::ESTADOS as $sigla => $lista) {
            foreach ($lista as $nombre) {
                $nombres[$nombre] = $sigla;
            }
        }

        uksort($nombres, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($nombres as $nombre => $sigla) {
            $patron = '/\b'.preg_quote($nombre, '/').'[\s,]+\d{5}(?:-\d{4})?\b/u';

            if (preg_match($patron, $texto) === 1) {
                $encontrados[$sigla] = true;
                $texto = (string) preg_replace($patron, ' ', $texto);
            }
        }

        return count($encontrados) === 1 ? (string) array_key_first($encontrados) : null;
    }
}
