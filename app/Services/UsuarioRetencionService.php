<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use Illuminate\Support\Facades\DB;

/**
 * Archivar, restaurar y eliminar definitivamente usuarios (F0.3 del
 * documento madre). Un cliente real solo se archiva: se conservan sus datos,
 * documentos y conversación, porque el IRS exige guardarlos 3 años. Solo un
 * usuario marcado como prueba (`es_prueba`) se elimina definitivamente.
 */
class UsuarioRetencionService
{
    public function __construct(private readonly DocumentoAlmacen $almacen) {}

    public function archivar(User $usuario): void
    {
        $usuario->delete();
    }

    public function restaurar(User $usuario): void
    {
        $usuario->restore();
    }

    /**
     * Borra el usuario con todo: filas (por cascade de la base), archivos de
     * sus documentos (también los archivados) y, si se pide, su
     * conversación de WhatsApp.
     */
    public function eliminarDefinitivamente(User $usuario, bool $conConversacion): void
    {
        DB::transaction(function () use ($usuario, $conConversacion) {
            if ($conConversacion && $usuario->phone) {
                WhatsappMensaje::query()->where('telefono', $usuario->phone)->delete();
                WhatsappControl::query()->where('telefono', $usuario->phone)->delete();
            }

            $this->borrarArchivosDe($usuario);
            $usuario->forceDelete();
        });
    }

    /**
     * El cascade de la base borra las filas de `documentos`, pero no toca
     * el storage.
     */
    public function borrarArchivosDe(User $usuario): void
    {
        Documento::withTrashed()
            ->where('user_id', $usuario->id)
            ->pluck('file_path')
            ->each(fn (string $ruta) => $this->almacen->borrar($ruta));
    }
}
