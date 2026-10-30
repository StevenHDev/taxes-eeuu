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
    | Entrevista sobre el catálogo fiscal (F2)
    |--------------------------------------------------------------------------
    |
    | `entrevista_catalogo_por_defecto`: con qué valor de la bandera
    | `users.entrevista_catalogo` nacen los clientes nuevos. Falso hasta el
    | cambio de flujo (2.10); mientras tanto solo la usan los usuarios de
    | prueba que el administrador marque.
    |
    | `entrevista_whatsapp`: si el agente hace la entrevista por WhatsApp a
    | clientes reales. Apagado hasta L3 (decisión de Steven, 30 oct.); los
    | usuarios de prueba la tienen siempre.
    |
    */
    'entrevista_catalogo_por_defecto' => (bool) env('ENTREVISTA_CATALOGO_POR_DEFECTO', false),

    'entrevista_whatsapp' => (bool) env('ENTREVISTA_WHATSAPP', false),

];
