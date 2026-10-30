<?php

/*
 * Versiones de los textos de consentimiento (F2.4). El texto de cada una
 * vive al lado, en `{codigo}-v{version}.es.md` (y `.en.md` si existe), y se
 * carga con `php artisan consentimientos:cargar`.
 *
 * Una versión que ya tiene firmas no se cambia: un texto nuevo es una
 * versión nueva. Mientras no llegue el texto definitivo del abogado (L1),
 * la vigente es provisional y solo la pueden firmar usuarios de prueba.
 */

return [
    [
        'codigo' => 'uso_7216',
        'version' => 1,
        'titulo_es' => 'Consentimiento para usar tu información de impuestos',
        'titulo_en' => 'Consent to use your tax return information',
        'provisional' => true,
        'vigente_desde' => '2026-10-30',
    ],
];
