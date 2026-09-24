<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\EventoRecoleccionData;
use App\Enums\FaseConversacion;
use App\Enums\FieldDataType;
use App\Enums\FieldKind;
use App\Enums\FieldMode;
use App\Enums\FieldState;
use App\Enums\TaxForm;
use App\Enums\TipoPromptActivoStep;
use App\Enums\UserRole;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Models\FormaCliente;
use App\Models\PortalMensaje;
use App\Models\PromptActivoStep;
use App\Models\User;
use App\Services\DocumentoExtraccion\DocumentoExtraccionService;
use App\Services\DocumentoExtraccion\RevelacionExtractorService;
use App\Services\EventoRecoleccionService;
use App\Services\WhatsappAgent\EstadoConversacionResolver;
use App\Support\EventoValidator;
use App\Support\TaxFieldCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Formulario dinámico del portal seguro (Fase 3): genera las preguntas desde
 * el mismo TaxFieldCatalog que ya usa el agente conversacional, y guarda cada
 * campo directamente vía EventoRecoleccionService::registrarDesdePortal() —
 * sin pasar por tool-calling, a diferencia de WhatsApp/PortalChatController.
 * El chat que aparece al lado (mismo endpoint de PortalChatController, con
 * canalPortal=true) queda en modo "solo dudas" mientras esta pantalla existe
 * — ver AgenteConversacionalService::responder().
 *
 * Cuando el campo que se guarda es un documento que "revela" otros (ver
 * TaxFieldCatalog::revelaPara()), el guardado SÍ pasa por IA
 * (RevelacionExtractorService + EventoRecoleccionService::procesar(), no
 * registrarDesdePortal()) para completar esos campos leyendo el texto
 * extraído — mismo mecanismo que ya usa WhatsApp, disparado desde el
 * formulario en vez de un turno de chat.
 */
class PortalFormularioController extends Controller
{
    public function __construct(
        private readonly EstadoConversacionResolver $resolver,
        private readonly EventoValidator $eventoValidator,
        private readonly EventoRecoleccionService $eventos,
        private readonly DocumentoExtraccionService $extraccion,
        private readonly RevelacionExtractorService $revelacion,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $cliente = $this->clienteAutenticado($request);
        $fase = $this->resolver->resolver($cliente);

        // Sin ninguna forma declarada todavía no hay nada forma-específico que
        // mostrar (TaxFieldCatalog::pendientesPara() solo devolvería
        // transversales) — esa determinación sigue siendo conversacional, ver
        // decisión de arquitectura del portal seguro.
        if (in_array($fase, [FaseConversacion::VerificacionCuenta, FaseConversacion::DeterminacionFormas], true)) {
            return redirect()->route('portal.chat');
        }

        $taxYear = (int) FormaCliente::query()->where('user_id', $cliente->id)->max('tax_year');

        $formas = FormaCliente::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->pluck('forma')
            ->map(fn (string $f) => TaxForm::tryFrom($f))
            ->filter()
            ->values()
            ->all();

        $orden = $this->ordenPorCampo($taxYear, $formas);
        $grupos = $this->gruposPorCampo();

        $camposCliente = CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->with('documento')
            ->get()
            ->keyBy('campo');

        $ocultosPorDependencia = $this->ocultosPorDependencia($camposCliente);

        return Inertia::render('portal/formulario', [
            'taxYear' => $taxYear,
            'formas' => collect($formas)->map(fn (TaxForm $f) => [
                'forma' => $f->value,
                'label' => $f->label(),
            ])->all(),
            // 'orden': posición estable del campo en el catálogo — sin esto,
            // el frontend arma la lista "primero todos los pendientes, después
            // todos los respondidos" y un campo recién guardado salta al final
            // de todo en vez de quedarse en su lugar (ver portal/formulario.tsx).
            // 'grupo': mismo agrupamiento ya usado por el agente de WhatsApp
            // para no convertir esto en un interrogatorio (ver
            // PromptActivoStep tipo=grupo) — el formulario lo reusa para
            // colapsar juntos los campos opcionales de baja frecuencia.
            // Se excluyen los campos cuya dependencia todavía no se cumple
            // (ver ocultosPorDependencia()) — solo aplica a pendientes, nunca
            // a lo ya respondido, para no esconderle al cliente algo que ya
            // llenó si la respuesta de la que dependía cambió después.
            'pendientes' => collect(TaxFieldCatalog::pendientesPara($taxYear, $formas, $cliente->id))
                ->reject(fn (array $campo) => in_array($campo['campo'], $ocultosPorDependencia, true))
                ->map(fn (array $campo) => [
                    ...$campo,
                    'orden' => $orden["{$campo['forma']}|{$campo['campo']}"] ?? PHP_INT_MAX,
                    'grupo' => $grupos[$campo['campo']] ?? null,
                ])
                ->values()
                ->all(),
            // Enriquecido con la definición del catálogo (tipo_dato, subcampos,
            // formatos_aceptados) — CampoCliente no la guarda, y el formulario
            // necesita la misma información que ya tiene un pendiente para
            // poder renderizar el input de edición de un campo ya respondido.
            'respondidos' => $camposCliente->values()
                ->map(function (CampoCliente $c) use ($taxYear, $orden, $grupos) {
                    $definicion = TaxFieldCatalog::find($taxYear, $c->forma, $c->campo);

                    // Mismo shape que el tipo CampoCliente de resources/js/types/tax-event.ts
                    // (el que ya usa mi-informacion.tsx) — el formulario reusa ese tipo tal cual.
                    return [
                        'forma' => $c->forma,
                        'campo' => $c->campo,
                        'tipo_campo' => $c->tipo_campo->value,
                        'tipo_dato' => $definicion['tipo_dato']?->value,
                        'subcampos' => $definicion['subcampos'] ?? null,
                        'modo' => $c->modo->value,
                        'estado' => $c->estado->value,
                        'advertencia' => $c->advertencia,
                        'valor' => $c->valor,
                        'es_sensible' => $c->esSensible(),
                        'documento' => $c->documento ? [
                            'id' => $c->documento->id,
                            'file_original_name' => $c->documento->file_original_name,
                            'file_mime_type' => $c->documento->file_mime_type,
                            'formato' => $c->documento->formato,
                            'estado_validacion' => $c->documento->estado_validacion->value,
                            'download_url' => $c->documento->downloadUrl(),
                            'preview_url' => $c->documento->previewUrl(),
                        ] : null,
                        'formatos_aceptados' => $definicion['formatos_aceptados'] ?? null,
                        'obligatorio' => $definicion['obligatorio'] ?? false,
                        'updated_at' => $c->updated_at,
                        'orden' => $orden["{$c->forma}|{$c->campo}"] ?? PHP_INT_MAX,
                        'grupo' => $grupos[$c->campo] ?? null,
                    ];
                })
                ->all(),
            // El panel de dudas al lado reusa el mismo historial que
            // PortalChatController — ver AgenteConversacionalService::responder(),
            // $canalPortal: en esta pantalla siempre responde en modo pasivo.
            'mensajesChat' => PortalMensaje::query()
                ->where('cliente_id', $cliente->id)
                ->orderBy('id')
                ->get()
                ->map(fn (PortalMensaje $m) => [
                    'id' => $m->id,
                    'rol' => $m->rol->value,
                    'contenido' => $m->contenido,
                    'created_at' => $m->created_at,
                ])
                ->all(),
        ]);
    }

    public function guardarCampo(Request $request): RedirectResponse
    {
        $cliente = $this->clienteAutenticado($request);

        $modo = (string) $request->input('modo');
        $tipoDato = FieldDataType::tryFrom((string) $request->input('tipo_dato'));
        $esArray = in_array($tipoDato, [FieldDataType::ArrayString, FieldDataType::ArrayObject], true);

        $datos = $request->validate([
            'forma' => ['required', Rule::in([...array_map(fn (TaxForm $f) => $f->value, TaxForm::cases()), ...CampoCatalogo::pseudoFormas()])],
            'tax_year' => ['required', 'integer', 'digits:4'],
            'campo' => ['required', 'string'],
            'tipo_campo' => ['required', Rule::enum(FieldKind::class)],
            'modo' => ['required', Rule::enum(FieldMode::class)],
            'tipo_dato' => [
                Rule::requiredIf($modo === FieldMode::Texto->value),
                'nullable',
                Rule::enum(FieldDataType::class),
            ],
            'contenido' => $modo === FieldMode::Texto->value
                ? ($esArray ? ['present'] : ['required'])
                : ['nullable'],
            'archivo' => [
                Rule::requiredIf($modo === FieldMode::Archivo->value),
                'nullable',
                'file',
                'max:20480',
            ],
        ]);

        $errores = $this->eventoValidator->validar($datos, $request->file('archivo'));

        if ($errores !== []) {
            return back()->withErrors($errores);
        }

        // La regla de validación 'archivo' => ['file', ...] de arriba nunca
        // deja pasar un arreglo — se acota el tipo explícito porque
        // Request::file() lo admite en general (input tipo 'archivo[]').
        $archivo = $request->file('archivo');
        $archivo = $archivo instanceof UploadedFile ? $archivo : null;
        $revelados = $archivo !== null
            ? $this->reveladosDelDocumento($archivo, (int) $datos['tax_year'], (string) $datos['campo'])
            : [];

        if ($revelados !== []) {
            // Con algo que revelar, se guarda vía procesar() (no
            // registrarDesdePortal()) para que el mismo campo principal y
            // los revelados queden en la MISMA transacción — igual que hace
            // ToolExecutor::guardarCampoCliente() por WhatsApp. Por eso el
            // origen de este evento queda como AgenteIa (fue la IA quien leyó
            // el documento y decidió los valores), no Cliente.
            $this->eventos->procesar(new EventoRecoleccionData(
                // 'cliente_id' es obligatorio: sin él, resolverCliente() no
                // tiene ninguna pista (no hay 'phone' ni 'external_ref' en un
                // evento del portal) y crea un cliente nuevo en vez de usar
                // al que ya autenticamos arriba.
                datos: [...$datos, 'cliente_id' => $cliente->id, 'revelados' => $revelados],
                file: $archivo,
                actor: $cliente,
            ));
        } else {
            $this->eventos->registrarDesdePortal(
                cliente: $cliente,
                taxYear: (int) $datos['tax_year'],
                forma: (string) $datos['forma'],
                campo: (string) $datos['campo'],
                tipoCampo: (string) $datos['tipo_campo'],
                modo: FieldMode::from((string) $datos['modo']),
                tipoDato: $tipoDato,
                contenido: $datos['contenido'] ?? null,
                file: $archivo,
                nombreOriginal: null,
            );
        }

        return redirect()->route('portal.formulario');
    }

    /**
     * Si este documento revela otros campos (ver TaxFieldCatalog::revelaPara()),
     * extrae su texto y le pide a la IA los valores que realmente aparecen ahí
     * — nunca lanza, un array vacío significa "nada que revelar" (o que la
     * extracción falló), en cuyo caso el documento se guarda igual, solo, sin
     * revelados.
     *
     * @return array<int, array<string, mixed>>
     */
    private function reveladosDelDocumento(UploadedFile $archivo, int $taxYear, string $campo): array
    {
        $revela = TaxFieldCatalog::revelaPara($taxYear, $campo);

        if ($revela === []) {
            return [];
        }

        $rutaLocal = (string) $archivo->getRealPath();
        $mimeType = (string) ($archivo->getMimeType() ?? 'application/octet-stream');
        $extraido = $this->extraccion->extraer($rutaLocal, $mimeType);

        return $this->revelacion->extraer($extraido['texto'], $revela);
    }

    /**
     * Posición estable (0, 1, 2...) de cada campo del catálogo, para que el
     * frontend ordene "pendientes" y "respondidos" en la MISMA secuencia sin
     * importar de cuál de los dos arrays vino cada uno — mismo recorrido que
     * TaxFieldCatalog::pendientesPara() (transversales, luego cada forma sin
     * repetir los únicos por cliente), pero sin filtrar lo ya resuelto.
     *
     * @param  array<int, TaxForm>  $formas
     * @return array<string, int> clave "forma|campo" => posición
     */
    private function ordenPorCampo(int $taxYear, array $formas): array
    {
        $orden = [];
        $i = 0;

        foreach (TaxFieldCatalog::transversales($taxYear) as $field) {
            $orden[CampoCatalogo::TRANSVERSAL."|{$field['campo']}"] = $i++;
        }

        foreach ($formas as $forma) {
            foreach (TaxFieldCatalog::fieldsFor($taxYear, $forma) as $field) {
                if ($field['unico_por_cliente'] ?? false) {
                    continue;
                }

                $clave = "{$forma->value}|{$field['campo']}";

                if (! isset($orden[$clave])) {
                    $orden[$clave] = $i++;
                }
            }
        }

        return $orden;
    }

    /**
     * Campos pendientes que todavía no deben mostrarse porque el campo del
     * que dependen no está en el estado que los hace relevantes (ej. no
     * mostrar los datos del cónyuge a alguien que no está casado). Nunca
     * oculta un campo ya respondido — solo evita preguntar algo antes de
     * tiempo, igual que ya hace el agente de WhatsApp por su cuenta al leer
     * el contexto de la conversación; el formulario no tiene ese contexto,
     * así que hay que dárselo explícito.
     *
     * A propósito una lista fija en código, no un dato del catálogo: del
     * mapa completo de dependencias del cuestionario real de GTS, estas son
     * las únicas donde tanto el campo dependiente como el campo del que
     * depende existen hoy en el catálogo — el resto (negocio, bienes raíces)
     * ya se resuelve solo porque esos campos solo aparecen bajo una forma
     * que el cliente debió declarar primero (Schedule C/E).
     *
     * @param  Collection<string, CampoCliente>  $camposCliente  keyBy('campo')
     * @return array<int, string>
     */
    private function ocultosPorDependencia(Collection $camposCliente): array
    {
        $recibido = fn (string $campo): bool => $camposCliente->get($campo)?->estado === FieldState::Recibido;

        $ocultos = [];

        // Cónyuge: solo si está casado al 31 de diciembre de ese año.
        $estadoCivil = $camposCliente->get('estado_civil')?->valor;
        $casado = is_array($estadoCivil) && ($estadoCivil['casado_al_31_dic'] ?? false) === true;

        if (! $casado) {
            $ocultos[] = 'info_conyuge';
        }

        // "¿Tienes otro W-2?" solo tiene sentido después del primero.
        if (! $recibido('w2')) {
            $ocultos[] = 'mas_w2';
        }

        // Rollover/distribución anticipada son sobre UNA distribución de
        // retiro/Social Security ya recibida — nada que corregir si no hubo.
        if (! $recibido('form_1099_r') && ! $recibido('ssa_1099')) {
            $ocultos[] = 'retiro_rollover_o_conversion_roth';
            $ocultos[] = 'retiro_distribucion_anticipada';
        }

        // Distribuciones/pérdidas pasivas de un K-1 solo si ya hay un K-1.
        if (! $recibido('k1_recibido')) {
            $ocultos[] = 'k1_distribuciones_recibidas';
            $ocultos[] = 'k1_perdidas_pasivas_o_basis_pendiente';
        }

        // La alocación del Marketplace solo aplica si entregó el 1095-A.
        if (! $recibido('form_1095_a')) {
            $ocultos[] = 'marketplace_seguro';
        }

        return $ocultos;
    }

    /**
     * "campo" => etiqueta del grupo al que pertenece, según los pasos tipo
     * `grupo` ya definidos para el agente de WhatsApp (ver
     * App\Services\WhatsappAgent\ActivosPromptComposer) — el formulario reusa
     * el mismo agrupamiento para colapsar juntos los campos opcionales de
     * baja frecuencia, en vez de listarlos todos sueltos.
     *
     * @return array<string, string>
     */
    private function gruposPorCampo(): array
    {
        $mapa = [];

        foreach (PromptActivoStep::query()->where('tipo', TipoPromptActivoStep::Grupo)->get(['etiqueta', 'miembros']) as $grupo) {
            foreach ($grupo->miembros ?? [] as $campo) {
                $mapa[$campo] = (string) $grupo->etiqueta;
            }
        }

        return $mapa;
    }

    /**
     * Mismo criterio que PortalChatController — sin middleware/policy
     * dedicados, ver DashboardController::miInformacion().
     */
    private function clienteAutenticado(Request $request): User
    {
        $user = $request->user();

        abort_unless($user->role === UserRole::Client, 403);

        return $user;
    }
}
