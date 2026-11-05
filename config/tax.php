<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Año fiscal actual del sistema
    |--------------------------------------------------------------------------
    |
    | Usado ÚNICAMENTE como valor por defecto de conveniencia en superficies de
    | UI internas (selector de año en el catálogo, alcance por defecto del
    | dashboard/panel de clientes). NUNCA debe usarse como fallback silencioso
    | en el camino de datos del agente externo (EventoRequest,
    | EventoRecoleccionService, TaxFieldCatalog) ni en la corrección manual de
    | un campo (CampoClienteUpdateRequest) — ahí `tax_year` es siempre un
    | valor explícito y requerido, sin default, para no asumir en nombre de
    | quien hace la petición qué año está reportando.
    |
    */
    'current_tax_year' => (int) env('CURRENT_TAX_YEAR', 2025),

    /*
    |--------------------------------------------------------------------------
    | Avisos por correo al preparador (F5.6)
    |--------------------------------------------------------------------------
    |
    | Un correo cuando un expediente queda "Listo para preparar" y cuando el
    | cliente responde una consulta. Apágalo sin deploy con
    | AVISOS_CORREO_PREPARADOR=false.
    |
    */
    'avisos_correo_preparador' => (bool) env('AVISOS_CORREO_PREPARADOR', true),

    /*
    |--------------------------------------------------------------------------
    | Entrevista sobre el catálogo fiscal (F2)
    |--------------------------------------------------------------------------
    |
    | `entrevista_whatsapp`: si el agente hace la entrevista por WhatsApp a
    | clientes reales. Apagado hasta L3 (decisión de Steven, 30 oct.); los
    | usuarios de prueba la tienen siempre.
    |
    */
    'entrevista_whatsapp' => (bool) env('ENTREVISTA_WHATSAPP', false),

    /*
    |--------------------------------------------------------------------------
    | Lectura automática de documentos (F4)
    |--------------------------------------------------------------------------
    |
    | `lectura_automatica`: si la plataforma lee con OpenAI los documentos que
    | suben los clientes (W-2, 1099…). Corre para todos los usuarios (decisión
    | de Steven, 3 nov.). Apagada, no se llama a OpenAI y el preparador escribe
    | los datos en el visor.
    |
    */
    'lectura_automatica' => (bool) env('LECTURA_AUTOMATICA', true),

    /*
    | `lectura_temporal`: carpeta donde se convierten las páginas de un archivo
    | antes de guardarlas cifradas. Vacía, se usa la temporal del sistema. Las
    | pruebas ponen una propia para no verse con las de otros procesos.
    */
    'lectura_temporal' => env('LECTURA_TEMPORAL'),

];
