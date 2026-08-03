<?php

namespace App\Services;

use App\Models\Documento;
use Illuminate\Support\Collection;

/**
 * Detección de duplicados por contenido (sección 5 del roadmap, "pendientes
 * menores") — complementa, no reemplaza, `unico_por_cliente`: ese flag evita
 * que el mismo CAMPO se recolecte dos veces para el mismo cliente; esto
 * detecta que el mismo ARCHIVO (mismos bytes) se usó para cualquier cliente,
 * incluyendo distinto — la señal de mayor valor (ej. un mismo documento de
 * identidad reutilizado para dos personas).
 *
 * A propósito NO bloquea nada en la subida (ver EventoRecoleccionService::
 * procesarArchivo) — la búsqueda de coincidencias se hace al mostrar el
 * documento, nunca al recibirlo.
 */
class DocumentoDuplicadoService
{
    /**
     * @return Collection<int, Documento>
     */
    public function buscarCoincidencias(Documento $documento): Collection
    {
        if ($documento->hash_contenido === null) {
            return collect();
        }

        return Documento::query()
            ->where('hash_contenido', $documento->hash_contenido)
            ->where('id', '!=', $documento->id)
            ->with('user')
            ->get();
    }
}
