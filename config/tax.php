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

];
