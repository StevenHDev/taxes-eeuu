<?php

namespace Database\Seeders;

use App\Enums\FieldDataType;
use App\Enums\FieldMode;
use App\Enums\NivelRiesgo;
use App\Enums\UserRole;
use App\Models\NivelRiesgoManual;
use App\Models\User;
use App\Services\DeterminacionFiscalService;
use App\Services\EventoRecoleccionService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Clientes de demostración con datos reales de punta a punta — pensado para
 * ver la plataforma con casos representativos (completo, incompleto, con
 * problemas) sin tener que cargarlos a mano desde el panel. Reutiliza
 * EventoRecoleccionService::corregirManualmente() (el mismo camino que usa un
 * preparador corrigiendo un dato desde el panel) en vez de insertar filas
 * crudas, para que la completitud de cada forma, el hash de los documentos y
 * el historial de cambios queden calculados exactamente como en producción.
 *
 * Solo se registra fuera de producción (ver DatabaseSeeder) — usa
 * UploadedFile::fake(), que no tiene sentido fuera de un entorno de desarrollo.
 */
class ClientesDemoSeeder extends Seeder
{
    private const TAX_YEAR = 2025;

    public function run(): void
    {
        $preparador = User::firstOrCreate(
            ['email' => 'preparador@example.com'],
            ['name' => 'Preparador de prueba', 'role' => UserRole::Preparer],
        );

        $eventos = new EventoRecoleccionService();
        $determinaciones = app(DeterminacionFiscalService::class);

        $this->clienteCompleto($eventos, $determinaciones, $preparador);
        $this->clienteIncompleto($eventos, $preparador);
        $this->clienteConProblemas($eventos, $preparador);
    }

    /**
     * "Ana Completo" — form_1040, casada, un dependiente, todo obligatorio
     * (y un opcional) cargado y válido. Sin campos/documentos inválidos, una
     * sola forma → el heurístico de riesgo la deja en "bajo" automáticamente.
     */
    private function clienteCompleto(EventoRecoleccionService $eventos, DeterminacionFiscalService $determinaciones, User $preparador): void
    {
        $cliente = User::factory()->create([
            'name' => 'Ana Completo', 'email' => 'ana.completo@example.com',
            'role' => UserRole::Client, 'preparer_id' => $preparador->id,
        ]);

        $this->dato($eventos, $cliente, $preparador, 'transversal', 'identificacion_ssn_itin', FieldDataType::String, '900-12-3456');
        $this->dato($eventos, $cliente, $preparador, 'transversal', 'info_conyuge', FieldDataType::Object, [
            'nombre_completo' => 'Marco Rivas', 'fecha_nacimiento' => '1988-04-12', 'ssn' => '900-23-4567',
        ]);
        $this->dato($eventos, $cliente, $preparador, 'transversal', 'info_dependientes', FieldDataType::ArrayObject, [[
            'nombre_completo' => 'Sofía Rivas', 'fecha_nacimiento' => '2016-09-02', 'ssn' => '900-34-5678',
            'relacion' => 'hija', 'meses_en_hogar' => 12, 'estudiante_tiempo_completo' => false,
            'discapacitado' => false, 'provee_mas_50_soporte_propio' => false, 'ingreso_bruto_anual' => 0,
            'custodia_compartida_sin_conflicto' => false,
        ]]);
        $this->dato($eventos, $cliente, $preparador, 'transversal', 'estado_civil', FieldDataType::Object, [
            'casado_al_31_dic' => true, 'convivio_conyuge_ultimos_6_meses' => true, 'costeo_mas_mitad_hogar' => true,
            'existe_persona_calificable' => true, 'conyuge_fallecio_en_anio' => false, 'anio_fallecimiento_conyuge' => null,
        ]);
        $this->documento($eventos, $cliente, $preparador, 'transversal', 'w2', 'w2-ana.pdf', 'w2-ana-contenido-unico');
        $this->documento($eventos, $cliente, $preparador, 'transversal', 'form_1099_nec', '1099-ana.pdf', '1099-ana-contenido-unico');

        $this->dato($eventos, $cliente, $preparador, 'form_1040', 'ingresos', FieldDataType::Object, [
            'salarios' => 82000, 'intereses_dividendos' => 400, 'ganancias_capital' => 0,
            'ingresos_jubilacion' => 0, 'otros_ingresos' => 0, 'ajustes_ingreso' => 0,
        ]);
        $this->dato($eventos, $cliente, $preparador, 'form_1040', 'deducciones', FieldDataType::Number, 0);
        $this->dato($eventos, $cliente, $preparador, 'form_1040', 'impuestos_retenidos', FieldDataType::Number, 9800);
        $this->dato($eventos, $cliente, $preparador, 'form_1040', 'info_bancaria', FieldDataType::Object, [
            'banco' => 'Chase', 'tipo_cuenta' => 'checking', 'numero_cuenta' => '000123456789', 'routing_number' => '021000021',
        ]);
        $this->dato($eventos, $cliente, $preparador, 'form_1040', 'gastos_cuidado_dependientes', FieldDataType::Object, [
            'proveedor_nombre' => 'Guardería Sol', 'proveedor_ssn_ein' => '12-3456789',
            'monto_anual' => 3200, 'dependiente_relacionado' => 'Sofía Rivas',
        ]);

        $determinaciones->calcularPara($cliente, self::TAX_YEAR);
    }

    /**
     * "Carlos Progreso" — dos formas de negocio (schedule_c + schedule_e),
     * solo parcialmente cargadas, y a propósito sube el MISMO estado bancario
     * para ambas (mismo cliente, dos formas) — para ver el badge de posible
     * duplicado dentro del mismo cliente. Nunca se calculó la determinación
     * fiscal → el heurístico lo deja en "medio" automáticamente.
     */
    private function clienteIncompleto(EventoRecoleccionService $eventos, User $preparador): void
    {
        $cliente = User::factory()->create([
            'name' => 'Carlos Progreso', 'email' => 'carlos.progreso@example.com',
            'role' => UserRole::Client, 'preparer_id' => $preparador->id,
        ]);

        $this->dato($eventos, $cliente, $preparador, 'transversal', 'identificacion_ssn_itin', FieldDataType::String, '900-45-6789');
        $this->dato($eventos, $cliente, $preparador, 'transversal', 'estado_civil', FieldDataType::Object, [
            'casado_al_31_dic' => false, 'convivio_conyuge_ultimos_6_meses' => false, 'costeo_mas_mitad_hogar' => true,
            'existe_persona_calificable' => false, 'conyuge_fallecio_en_anio' => false, 'anio_fallecimiento_conyuge' => null,
        ]);
        // info_conyuge, info_dependientes, w2 y form_1099_nec quedan sin cargar
        // a propósito — es el caso "en progreso".

        $this->dato($eventos, $cliente, $preparador, 'schedule_c', 'ingresos_negocio', FieldDataType::Number, 61000);
        $this->documento($eventos, $cliente, $preparador, 'schedule_c', 'estados_bancarios', 'estado-cuenta.pdf', 'mismo-estado-de-cuenta');
        // gastos_deducibles_negocio, millaje, activos, costo_ventas: sin cargar.

        $this->dato($eventos, $cliente, $preparador, 'schedule_e', 'ingresos_renta', FieldDataType::Number, 18000);
        $this->documento($eventos, $cliente, $preparador, 'schedule_e', 'estados_bancarios', 'estado-cuenta.pdf', 'mismo-estado-de-cuenta');
        // gastos_propiedad, depreciacion, intereses_hipotecarios, impuestos_propiedad,
        // seguros_propiedad: sin cargar.
    }

    /**
     * "Beatriz Riesgo" — un SSN mal formado (campo inválido) y un 1099 con
     * formato no aceptado (documento inválido) ya dejarían el heurístico en
     * "alto" automáticamente; acá además se agrega un override MANUAL a
     * "medio" (el preparador revisó el caso y lo bajó de prioridad), para ver
     * el badge mostrando "fijado manualmente" en vez de la sugerencia
     * automática. El w2 se sube con el MISMO contenido que el de Ana
     * Completo — para ver el badge de posible duplicado cruzado entre
     * clientes distintos.
     */
    private function clienteConProblemas(EventoRecoleccionService $eventos, User $preparador): void
    {
        $cliente = User::factory()->create([
            'name' => 'Beatriz Riesgo', 'email' => 'beatriz.riesgo@example.com',
            'role' => UserRole::Client, 'preparer_id' => $preparador->id,
        ]);

        // SSN mal formado (regla real de validación: 9 dígitos) → estado inválido.
        $this->dato($eventos, $cliente, $preparador, 'transversal', 'identificacion_ssn_itin', FieldDataType::String, 'no-es-un-ssn');
        $this->dato($eventos, $cliente, $preparador, 'transversal', 'estado_civil', FieldDataType::Object, [
            'casado_al_31_dic' => false, 'convivio_conyuge_ultimos_6_meses' => false, 'costeo_mas_mitad_hogar' => true,
            'existe_persona_calificable' => false, 'conyuge_fallecio_en_anio' => false, 'anio_fallecimiento_conyuge' => null,
        ]);
        // Mismo contenido que el w2 de Ana Completo — duplicado cruzado entre clientes.
        $this->documento($eventos, $cliente, $preparador, 'transversal', 'w2', 'w2-beatriz.pdf', 'w2-ana-contenido-unico');
        // Formato no permitido para form_1099_nec (solo pdf/jpg/png/heic) → documento inválido.
        $this->documento($eventos, $cliente, $preparador, 'transversal', 'form_1099_nec', '1099-beatriz.exe', 'contenido-irrelevante', 'application/x-msdownload');

        $this->dato($eventos, $cliente, $preparador, 'form_1040', 'ingresos', FieldDataType::Object, [
            'salarios' => 45000, 'intereses_dividendos' => 0, 'ganancias_capital' => 0,
            'ingresos_jubilacion' => 0, 'otros_ingresos' => 0, 'ajustes_ingreso' => 0,
        ]);
        $this->dato($eventos, $cliente, $preparador, 'form_1040', 'impuestos_retenidos', FieldDataType::Number, 3100);

        NivelRiesgoManual::query()->updateOrCreate(
            ['user_id' => $cliente->id, 'tax_year' => self::TAX_YEAR],
            ['nivel' => NivelRiesgo::Medio, 'establecido_por' => $preparador->id, 'establecido_en' => now()],
        );
    }

    private function dato(EventoRecoleccionService $eventos, User $cliente, User $actor, string $forma, string $campo, FieldDataType $tipoDato, mixed $contenido): void
    {
        $eventos->corregirManualmente(
            cliente: $cliente,
            taxYear: self::TAX_YEAR,
            forma: $forma,
            campo: $campo,
            tipoCampo: 'dato',
            modo: FieldMode::Texto,
            tipoDato: $tipoDato,
            contenido: $contenido,
            file: null,
            nombreOriginal: null,
            actor: $actor,
        );
    }

    private function documento(EventoRecoleccionService $eventos, User $cliente, User $actor, string $forma, string $campo, string $nombre, string $contenido, string $mime = 'application/pdf'): void
    {
        $eventos->corregirManualmente(
            cliente: $cliente,
            taxYear: self::TAX_YEAR,
            forma: $forma,
            campo: $campo,
            tipoCampo: 'documento',
            modo: FieldMode::Archivo,
            tipoDato: null,
            contenido: null,
            file: UploadedFile::fake()->createWithContent($nombre, $contenido)->mimeType($mime),
            nombreOriginal: $nombre,
            actor: $actor,
        );
    }
}
