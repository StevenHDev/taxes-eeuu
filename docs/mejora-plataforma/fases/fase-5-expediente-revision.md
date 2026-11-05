# Fase 5 — Expediente, revisión del preparador y hoja para TaxWise: plan técnico

5 nov. 2026 · **Decisiones respondidas; falta la aprobación del plan en conjunto** · Documento madre: [secciones 2, 5.3, 6.2 (R-02), 7 y 9.5–9.7](../documento-madre.md), pendientes P6, P7, L13 y L14 de la [sección 10](../documento-madre.md#10-decisiones-pendientes) · Subtareas para Sonnet 5.5: [`fase-5-subtareas.md`](fase-5-subtareas.md)

> **Objetivo:** que el preparador **revise y cargue en TaxWise sin abrir cada documento** (9.5). La plataforma junta en un **expediente por cliente y año** lo que respondió, lo que subió y lo que se leyó (con su confianza y su origen), le pone un **estado con fecha**, le dice al preparador qué le toca, le da un **checklist de revisión** (7.2) con el **resumen de debida diligencia del 8867** (7.1), y le entrega una **hoja ordenada como el 1040** con cada valor, su origen y su estado (7.3).
>
> **Resultado que se busca (9.5):** un preparador revisa y carga en TaxWise sin abrir cada documento.
>
> **Objetivo del producto (Steven):** entender al cliente, entrevista corta, no pasarle al cliente el trabajo del preparador, extraer de los documentos, 1040 correcto. En esta fase: el preparador deja de digitar a ciegas (la hoja dice de dónde sale cada número y qué tan seguro es) y deja de perseguir expedientes uno por uno (estados, cola y avisos).
>
> **Buenas prácticas:** skills `laravel-best-practices`, `react-best-practices`, `vercel-react-best-practices` y `responsive-design`; cada entrega cita la regla que aplica. Reglas de interfaz de Steven (guía de desarrollo 4.2): skeletons, responsive a 375 px, jerarquía, `components/ui`, accesibilidad, estados vacío/error/éxito, estados de interacción.
>
> **Modelo por tarea (Steven, 3 nov.):** el diseño, las decisiones fiscales y de §7216 están tomadas en este plan (Opus 5.5). **Todo lo programa Sonnet 5.5**, en subtareas autocontenidas y en paralelo por olas ([`fase-5-subtareas.md`](fase-5-subtareas.md)). La revisión visual en el navegador la hace el agente principal al cerrar cada ola de pantallas.
>
> **Base:** `main` al 4 nov. (`a4c1e4d`): F0 menos F0.4, F1, F2, F3 (menos 3.6) y F4 en producción.

## Qué hay hoy (verificado en el código)

- **No hay estado del caso.** Hay tres estados sueltos que sirven de insumo:
  - `entrevistas.estado` (`en_curso`, `confirmada`, `reabierta`) y `confirmada_en` (F2.7);
  - `entrevista_documentos.estado` (8 estados de F3.2/F4) con `revisado_por` y `revisado_en`; `EstadoDocumento::cerrado()` (validado o "no aplica") y `grupo()` (`faltan`, `por_revisar`, `validados`, `no_aplica`);
  - `excepciones.estado` (`abierta`, `consultada`, `corregida`, `aceptada`) con severidad del catálogo (F4.5).
- **R-02 ya tiene su base:** `ChecklistDocumentos::completo()` (todos los ítems vigentes validados o en "no aplica") y `resumenPorCliente()` (conteos por grupo en una consulta). En el catálogo, R-02 está **sin** `implementada`; `MotorReglas` no la corre (su lista `REGLAS` no la tiene).
- **"No aplica":** cuenta como cerrado apenas el cliente lo marca (decisión 4 de F3); el preparador lo puede rechazar (`no_aplica_rechazado`). No existe un "aceptar" explícito.
- **Ficha del cliente** (`pages/clientes/show.tsx`, 1.336 líneas, `ClienteController@show`):
  - encabezado con selector de año, **"Exportar ZIP"**, conversación de WhatsApp y acciones de administrador;
  - la entrevista diferida (`VistaEntrevistaPreparador::para`, componente `EntrevistaCliente` de 1.182 líneas) con respuestas, checklist de documentos, revisión de cada documento y "N excepciones abiertas";
  - **nivel de riesgo** (`NivelRiesgoControl`, `RiesgoCasoService`, `nivel_riesgo_manual`), **determinación fiscal** (calculadoras viejas sobre `campos_cliente`), **trazas de derivación** y las **secciones por forma** del formulario anterior, en solo lectura salvo "marcar revisado" (decisión 15 de F2).
- **Lista de clientes** (`pages/clientes/index.tsx`, TanStack en el navegador): estado general por formas (viejo), nivel de riesgo, resumen de documentos (F3.4), formas y WhatsApp.
- **Panel** (`DashboardController` + `DashboardSummaryService`): todo sobre `formas_cliente` y `campos_cliente` (viejo). "Pendientes de revisar" casi siempre vacío.
- **Visor (F4.6):** no recibe un dato inicial por URL. "Revelar" deja en la bitácora la acción `Actualizado` con la etiqueta `revelado` (pendiente de F4).
- **Cola de excepciones (F4.7):** `ColaExcepciones::lista()` sin filtro por año; `conteo()` por cliente y año.
- **Catálogo:** `formularios.php` ya tiene las líneas del 1040 y sus schedules con su origen (`casilla`, `linea`, `respuesta`, `calculo`; varias fuentes con `;`). Es el mapa que usa la hoja. No hay Form 8867 ni fechas de vencimiento.
- **Cliente:** "Mi información" se retiró en F2.11; el inicio del portal muestra el avance de la entrevista. El agente (`HerramientasEntrevista::consultar`) no sabe el estado del caso.
- **Lo que no se puede borrar:** la API externa (E3, decisión 14 de F2) usa `ClienteExportService` y `FormaCliente::marcarRevisado`.
- **Infraestructura:** sin librería de PDF ni de Excel; sin tabla `notifications` (el correo sí funciona: `BienvenidaClientePortal`); sin componente de pestañas, pero el catálogo ya tiene el patrón de **pestañas como enlaces** con layout anidado (`layouts/catalogo-fiscal/layout.tsx`, registrado en `app.tsx`). Un solo `es.json`/`en.json`.

## Alcance

| Entra en F5 | Sale de |
| --- | --- |
| Expediente por cliente y año con **estados y fecha de cada cambio** (sección 2) | 2, 7.6 |
| **R-02** como compuerta del expediente | 6.2, decisión del 2 nov. |
| Recalcular el estado solo, y **reabrir la revisión** si algo cambia | 6.4.3, 7.2 |
| **Ficha en pestañas**: Resumen, Entrevista, Documentos, Excepciones, Revisión, Hoja para TaxWise, Debida diligencia, Historial | 9.7 |
| **Revisión del preparador**: estado civil para el IRS y clasificación de cada persona con motivo, checklist 7.2, aprobar, estados después de preparar (7.4) | 7.2, 7.4 |
| **Resumen de debida diligencia (8867)** armado desde las respuestas y los documentos | 7.1 |
| **Hoja para TaxWise** en pantalla, imprimible (PDF desde el navegador) y CSV que abre Excel | 7.3 |
| **Cola de trabajo**: lista de clientes por estado del expediente, tipo de ingreso, excepciones y canal; panel por estados, colas, avisos y tiempos | 7.5, 7.6, 9.7 |
| **Avisos al preparador** en el panel y por correo | 7.5 |
| "¿Cómo va lo mío?": el agente y el portal muestran el estado en lenguaje del cliente | 9.5, 9.6 |
| Retirar de la ficha el **nivel de riesgo**, el **ZIP** y **"marcar revisado"** por forma | 9.3.2 |
| Pendientes de F4 que F5 usa: acción **"Revelado"** en la bitácora y abrir el visor en un dato | F4, pendientes menores |

## Fuera de esta fase

- **Cálculos y borrador del 1040** (F6): las líneas con origen `calculo` o `linea` no se calculan aquí; la hoja dice "lo calcula TaxWise". El panel de determinación fiscal viejo queda tal cual en Historial, en solo lectura, hasta F6. R-10, R-11 bloqueante y R-12 son de F6.
- **R-13**, comparación con el año anterior automática, transcripciones (F7). El punto 7 del checklist es manual.
- **Firma del 8879 en el portal** (L14, P7), **rol asistente** (P7), **segundo revisor** (L13): sin respuesta, no se asumen.
- **Importación directa a TaxWise** (T-f, bloqueada por el proveedor).
- **Consultas al cliente que no son de un documento.** Hoy la consulta sale de un documento (F4.7); una duda general se resuelve tomando el control de WhatsApp o corrigiendo la respuesta como preparador.
- **Resumen final para el cliente con créditos y montos** (7.4): necesita los cálculos de F6. En F5 el cliente ve el estado, nunca montos.
- **Resumen del expediente escrito por IA**: ver la decisión 1.
- Borrar la API externa (E3) o las tablas viejas (`formas_cliente`, `campos_cliente`, `nivel_riesgo_manual`): se conservan (L12).

## Resumen de entregas

| # | Entrega | Sale de | Tamaño | Riesgo | Subtareas |
| --- | --- | --- | --- | --- | --- |
| 5.0 | Pendientes de F4: acción "Revelado"/"Exportado" en la bitácora y visor abierto en un dato | F4 | Pequeño | Bajo | 5.0a |
| 5.1 | Expediente: datos, estados con fecha, R-02, recálculo y reapertura; catálogo (8867, vencimientos, R-02) | 2, 6.2, 7.6 | Grande | **Alto**: es la base de todo | 5.1a, 5.1b, 5.1c |
| 5.2 | Ficha en pestañas (Resumen, Entrevista, Documentos, Excepciones, Historial) | 9.7 | Grande | Medio: reemplaza la pantalla más usada | 5.2a, 5.2b, 5.2c, 5.2d |
| 5.3 | Revisión del preparador: decisiones, checklist, aprobar y estados después de preparar | 7.2, 7.4 | Grande | Medio: lógica fiscal y de proceso | 5.3a, 5.3b |
| 5.4 | Debida diligencia (8867) | 7.1 | Mediano | Medio: registro que pide el IRS | 5.1c, 5.4a, 5.4b |
| 5.5 | Hoja para TaxWise | 7.3 | Grande | **Alto**: datos sensibles y exactitud | 5.5a, 5.5b |
| 5.6 | Cola de trabajo, panel, avisos y retiros (riesgo, ZIP, marcar revisado) | 7.5, 7.6, 9.7 | Grande | Medio | 5.6a, 5.6b |
| 5.7 | "¿Cómo va lo mío?" en el portal y el agente | 9.5, 9.6 | Pequeño | Bajo | 5.7a |

**Olas de trabajo** (detalle en las subtareas): ola 1 = 5.0a, 5.1a, 5.1c, 5.2a · ola 2 = 5.1b · ola 3 = 5.2b, 5.3a, 5.4a, 5.5a, 5.6a, 5.7a · ola 4 = 5.2c, 5.2d, 5.3b, 5.4b, 5.5b, 5.6b · ola 5 = cierre (agente principal).

---

## Modelo de datos

**Migración** `database/migrations/2026_11_05_100000_crea_expedientes.php` (reglas `eloquent-casts`, `eloquent-relationships`; con `down()`):

```php
Schema::create('expedientes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('tax_year');
    $table->foreignId('entrevista_id')->nullable()->constrained('entrevistas')->nullOnDelete();
    $table->string('estado', 30)->index();
    $table->timestamp('estado_desde');
    // Decisión del preparador (7.2, punto 2): el estado civil para el IRS.
    $table->string('estado_civil_irs', 10)->nullable();
    $table->text('estado_civil_motivo')->nullable();
    $table->foreignId('estado_civil_por')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('estado_civil_en')->nullable();
    // Créditos que piden la debida diligencia del 8867 (7.1): lista de códigos.
    $table->json('creditos_8867')->nullable();
    $table->foreignId('creditos_8867_por')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('creditos_8867_en')->nullable();
    $table->date('presentado_en')->nullable();
    $table->timestamp('cambios_tras_presentar_en')->nullable();
    // Tipos de ingreso y situación (para filtrar la lista); nunca montos ni datos sensibles.
    $table->json('perfil')->nullable();
    $table->date('vence_en')->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'tax_year']);
});

Schema::create('expediente_eventos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
    $table->string('tipo', 30)->index();
    $table->string('estado_anterior', 30)->nullable();
    $table->string('estado_nuevo', 30)->nullable();
    $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
    $table->text('motivo')->nullable();
    $table->json('datos')->nullable();
    $table->timestamp('created_at')->useCurrent();

    $table->index(['expediente_id', 'created_at']);
});

Schema::create('expediente_personas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
    $table->foreignId('persona_id')->constrained('entrevista_personas')->cascadeOnDelete();
    $table->string('clasificacion', 30);
    $table->text('motivo');
    $table->foreignId('decidido_por')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('decidido_en');
    $table->timestamps();

    $table->unique(['expediente_id', 'persona_id']);
});

Schema::create('expediente_revision_puntos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
    $table->string('punto', 30);
    $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('confirmado_en')->nullable();
    $table->text('nota')->nullable();
    $table->timestamps();

    $table->unique(['expediente_id', 'punto']);
});
```

**Por qué así:**
- **Una tabla aparte y no columnas en `entrevistas`:** el expediente existe aunque el cliente no haya empezado (estado `invitado`) y junta cosas que no son de la entrevista (decisiones, revisión, presentación).
- **`expediente_eventos` solo se inserta** (sin `updated_at`): es la bitácora del expediente, la fuente de las fechas de cada estado (7.6), de los avisos y de la línea de tiempo. Las decisiones y los puntos también dejan su evento, así queda rastro aunque se reabra la revisión.
- **`perfil`** guarda códigos (`asalariado`, `independiente`…), no montos: la lista de clientes filtra sin correr el motor por cada cliente.
- **Los motivos** son texto del preparador, como `excepciones.nota` y `entrevista_documentos.observacion` (mismo criterio: sin cifrar, nunca en la bitácora general, que guarda solo nombres de atributos).
- Nada se borra a mano; los registros se van con el usuario cuando vence su plazo (L12), como el resto.

**Enums** (`app/Enums/Expediente/`): `EstadoExpediente`, `EstadoCivilIrs` (`single`, `mfj`, `mfs`, `hoh`, `qss`: las siglas que usa TaxWise), `ClasificacionPersona` (`hijo_calificado`, `pariente_calificado`, `no_dependiente`), `PuntoRevision`, `Credito8867` (`eic`, `ctc`, `aotc`, `hoh`), `TipoEventoExpediente` (`estado`, `reabierto`, `decision`, `punto`, `cambio_tras_presentar`). El enum viejo `FilingStatus` no se toca (es de las calculadoras viejas y no tiene MFS; F6 decide).

**Modelos** (`app/Models/Expediente/`): `Expediente`, `ExpedienteEvento`, `ExpedientePersona`, `ExpedienteRevisionPunto`. `Entrevista::expediente()` (`HasOne`). La bitácora general (`AuditoriaObserver`) observa `Expediente`, `ExpedientePersona` y `ExpedienteRevisionPunto`; `ExpedienteEvento` no (ya es un registro).

## Estados del expediente y transiciones

Sale de la sección 2 y de 7.4 del documento madre, con dos ajustes que se explican abajo.

| Estado | Quién lo pone | Qué significa | Turno |
| --- | --- | --- | --- |
| `invitado` | Plataforma | No empezó la entrevista o no firmó el consentimiento §7216 del año | Cliente |
| `en_entrevista` | Plataforma | Entrevista `en_curso` o `reabierta` (no confirmada en el portal) | Cliente |
| `en_consulta` | Plataforma | Hay una consulta sin responder: un documento "con observación", un "no aplica" rechazado o una excepción `consultada` | Cliente |
| `en_recoleccion` | Plataforma | Faltan documentos del cliente (`pendiente` o `no_se_pudo_leer`) | Cliente |
| `por_validar` | Plataforma | Hay documentos subidos o leídos sin validar, o excepciones abiertas que frenan (bloqueante o revisión) | Preparador |
| `listo_para_preparar` | Plataforma | Pasa todo: consentimiento, entrevista confirmada, **R-02** y sin excepciones que frenan | Preparador |
| `en_revision` | Preparador | Empezó la revisión (7.2) y la carga en TaxWise | Preparador |
| `preparado_en_taxwise` | Preparador | Aprobó la revisión y terminó de cargar | Preparador |
| `esperando_firma` | Preparador | Se le mandó el 8879 al cliente (fuera de la plataforma) | Cliente |
| `presentado` | Preparador | Se envió al IRS (fecha obligatoria) | IRS |
| `aceptado` | Preparador | El IRS la aceptó | — |
| `rechazado` | Preparador | El IRS la rechazó (motivo obligatorio) | Preparador |

**Ajustes frente al documento madre:**
1. **"Con excepciones" pasa a llamarse "Por validar"** y cubre también los documentos subidos que el preparador no ha validado: los dos son trabajo del preparador y la sección 2 no tenía dónde poner un documento leído sin excepciones.
2. **El orden de "En consulta" y "En recolección"**: si el cliente tiene a la vez documentos pendientes y una consulta, manda la consulta (es lo más específico que tiene que hacer).

**Estado derivado** (`DiagnosticoExpediente`, en este orden, el primero que se cumple):
1. `invitado`: no hay entrevista del año o `Consentimientos::firmado()` es falso.
2. `en_entrevista`: `entrevistas.estado` ≠ `confirmada`.
3. `en_consulta`: algún ítem vigente `con_observacion` o `no_aplica_rechazado`, o alguna excepción `consultada`.
4. `en_recoleccion`: algún ítem vigente `pendiente` o `no_se_pudo_leer`.
5. `por_validar`: algún ítem vigente `subido` o `leido`, o alguna excepción `abierta` con severidad `bloqueante` o `revision`.
6. `listo_para_preparar`: si no, y además R-02 se cumple (abajo).

**Transiciones manuales** (solo el preparador del cliente o un administrador, `ClientePolicy::update`):

| Acción | Desde | Hacia | Requisito |
| --- | --- | --- | --- |
| `empezar` | `listo_para_preparar` | `en_revision` | El diagnóstico sigue en listo |
| `preparado` ("Aprobar y marcar preparado en TaxWise") | `en_revision` | `preparado_en_taxwise` | Todos los puntos requeridos del checklist cumplidos |
| `a_firma` | `preparado_en_taxwise` | `esperando_firma` | — |
| `presentado` | `preparado_en_taxwise`, `esperando_firma` | `presentado` | Fecha (no futura, del año siguiente al fiscal o después) |
| `aceptado` | `presentado` | `aceptado` | — |
| `rechazado` | `presentado` | `rechazado` | Motivo (código y texto del rechazo del IRS) |
| `volver_a_revision` | `rechazado` | `en_revision` | — |

**Recalcular** (`EstadosExpediente::recalcular`): corre solo después de cada cambio que lo afecta (abajo). Con el estado actual:
- **derivado** (los 6 primeros): pasa al estado del diagnóstico.
- **`en_revision`, `preparado_en_taxwise` o `esperando_firma`** ("la revisión se reabre sola", 7.2 y 6.4.3): si el diagnóstico ya no está en listo, vuelve al estado derivado, **borra las confirmaciones de los puntos manuales** y deja un evento `reabierto` con el motivo en palabras ("Se abrió la regla R-04 en el W-2 #1", "Un documento volvió a revisión"). Si sigue listo, no cambia.
- **`presentado`, `aceptado` o `rechazado`**: nunca cambia solo (es un hecho). Si el diagnóstico deja de estar listo, marca `cambios_tras_presentar_en` y deja un evento `cambio_tras_presentar` una vez; la ficha lo muestra como alerta (posible enmienda, fuera de F5).
- Cada cambio de estado deja un evento `estado` con el anterior, el nuevo, el actor (nulo = plataforma) y dispara el evento de Laravel `ExpedienteCambioDeEstado` (lo escuchan los avisos).

**Cuándo se recalcula** (regla `eloquent-model-events`, `arch-event-driven`): un observer (`ExpedienteObserver`) sobre `EntrevistaDocumento` (guardado), `Excepcion` (guardada), `Entrevista` (guardada) y `Consentimiento` (creado) pide el recálculo **después del commit** (`DB::afterCommit`) y sin repetir el mismo cliente y año dentro de la misma petición. `MotorReglas::correr()` lo pide al final de forma explícita, porque cierra excepciones con un `update` masivo que no dispara eventos. No hay ciclos: el recálculo escribe solo en `expedientes` y `expediente_eventos`, que nadie observa para recalcular.

**Clientes existentes:** comando `expedientes:recalcular {--anio=} {--solo-prueba} {--dry-run}` que crea o actualiza el expediente de cada entrevista. Se corre en producción una vez, con autorización.

## Reglas de esta fase

- **R-02** (bloqueante): *todo documento activado está validado o en "no aplica"*. Se evalúa **en el expediente**, no en la cola: con `ChecklistDocumentos::completo()`. **No crea filas en `excepciones`**: una excepción por documento pendiente duplicaría el checklist en la cola y la llenaría de trabajo que es del cliente. Se muestra en el Resumen y en la Revisión con la lista de lo que falta y su enlace.
  - Corre si en el catálogo del año está **activa e `implementada`** (la regla sigue siendo dato; si un administrador la apaga, el expediente puede quedar listo sin todos los documentos cerrados).
  - **"No aplica"** cuenta como cerrado apenas el cliente lo marca (decisión 4 de F3); ver la decisión 6.
  - Catálogo: `implementada => true` y la descripción dice dónde se ve ("Se revisa en el expediente, no en la cola de excepciones").
- **Excepciones que frenan "Listo para preparar":** abiertas o consultadas con severidad `bloqueante` o `revision`. Las `informativa` se muestran y no frenan (6.2).
- **Vencimiento del cliente** (6.3 ordena la cola por él): parámetros nuevos del año `vencimientos.presentacion` (2025: 2026-04-15) y `vencimientos.extension` (2025: 2026-10-15; IRC §6072(a) y Form 4868). `vence_en` = extensión si G2 = "sí", si no la fecha de presentación. 2026: 2027-04-15 y 2027-10-15 en `2026/ajustes.php`.
- **Nada nuevo en las pantallas:** la regla de qué es "listo" vive solo en `DiagnosticoExpediente`; la del vencimiento, en los parámetros; la de qué preguntas respaldan cada crédito del 8867, en el catálogo (Form 8867, abajo).

---

## 5.0 Pendientes de F4 que F5 usa

**Hoy:** el visor registra "Revelar" como `Actualizado` y no abre un dato por URL.

**Enfoque:**
- `AccionAuditoria::Revelado` (`revelado`, "Dato revelado") y `AccionAuditoria::Exportado` (`exportado`, "Exportado"). `VisorDocumentoController@reveal` usa `Revelado` (sin `campos_afectados`). La bitácora ya muestra `label()`.
- `VisorDocumentoController@show` acepta `?dato={id}`: si el dato está entre los vigentes del ítem, manda la prop `dato_inicial` (id); si no, null. `pages/clientes/visor.tsx` arranca con ese dato seleccionado (estado inicial perezoso: `useState(() => datoInicial)`, regla `rerender-lazy-state-init`) y en la hoja donde está.

**Pruebas:** revelar deja `revelado`; `?dato=` de otro ítem da `dato_inicial` nulo; la bitácora no guarda el valor.

## 5.1 Expediente: datos, estados, R-02 y recálculo

**Hoy:** ver "Qué hay hoy".

**Enfoque backend** (reglas `arch-service-classes`, `arch-value-objects`, `eloquent-model-events`, `arch-event-driven`):
- Migración, enums y modelos de arriba.
- `App\Services\Expediente\DiagnosticoExpediente::para(User $cliente, int $taxYear): Diagnostico` (DTO `readonly`): estado derivado, si cumple R-02 y qué falta, cuántos ítems le tocan al cliente y al preparador, excepciones que frenan, consultas, y `motivos()` en palabras. Solo lee.
- `App\Services\Expediente\EstadosExpediente`: `recalcular(User, int): Expediente`, `pedirRecalculo(int $clienteId, int $taxYear): void` (después del commit, sin repetir; por cliente y año para que también sirva al firmar el consentimiento, que no tiene entrevista) y `registrarCambio(...)` (escribe el estado y el evento; lo usan el recálculo y la revisión manual).
- `perfil` y `vence_en` se calculan en `recalcular` desde `MotorEntrevista::situacion()` y los parámetros.
- `App\Services\Expediente\TextoRespuesta::de(array $pregunta, mixed $valor, bool $ingles): ?string`: una respuesta en palabras para el preparador ("Sí", "Usted y cónyuge", el texto de la opción). La usan la revisión, la debida diligencia y la hoja.
- `App\Services\Expediente\FichaCliente::encabezado(User $cliente, int $taxYear, User $actor): array`: lo que comparte el encabezado de todas las pestañas (cliente, año, estado y su fecha, turno, vencimiento, conteos, pestañas disponibles). Solo lee: si no hay expediente todavía, usa el diagnóstico.
- Comando `expedientes:recalcular`.
- Los archivos de rutas de F5 (`routes/expediente-*.php`) se crean vacíos y se registran en `routes/web.php`, para que cada subtarea llene el suyo sin pisar a otra.

**Catálogo (5.1c):** R-02 implementada, los dos parámetros de vencimiento y el Form 8867 (ver 5.4). Hay que recargar el catálogo en producción (con autorización).

**Pruebas:** un caso por estado derivado; R-02 con un ítem pendiente, uno leído, uno validado y uno en "no aplica"; un "no aplica" rechazado lleva a `en_consulta`; informativas no frenan; reabrir desde `en_revision` y desde `preparado_en_taxwise` borra las confirmaciones y deja el evento con motivo; un cambio en `presentado` no cambia el estado y marca la alerta una sola vez; el observer recalcula al validar un documento y al aceptar una excepción; el motor recalcula al cerrar una excepción; correr dos veces no duplica eventos; `perfil` y `vence_en` (con y sin G2); el comando con `--dry-run` no escribe y no muestra datos del cliente.

## 5.2 Ficha en pestañas

**Hoy:** una sola página de 1.336 líneas.

**Enfoque:**
- **Pestañas como enlaces** (mismo patrón que el catálogo fiscal): cada pestaña es su propia ruta y su propia página Inertia, con un **layout persistente** `layouts/ficha/layout.tsx` (registrado en `app.tsx` para las páginas `ficha/*`) que pinta el encabezado y la navegación sin volver a montarse al cambiar de pestaña. Se pueden abrir en otra pestaña del navegador y volver atrás; cada una carga solo lo suyo (regla `async-defer-await` y `server-dedup-props`: cada página pide solo sus props).
- **Las pestañas que se ven salen del servidor** (`ficha.pestanas`, con su URL y solo si su ruta existe, `Route::has`). Así cada entrega se puede desplegar sola: si la hoja todavía no está, su pestaña no aparece.
- Rutas y páginas:

| Pestaña | Ruta (nombre) | Página | Contenido |
| --- | --- | --- | --- |
| Resumen | `GET clientes/{cliente}` (`clientes.show`, se conserva) | `ficha/resumen` | Estado, qué falta para "Listo" (R-02, excepciones, consultas), acción principal según el estado, línea de tiempo, avisos (años sin declarar) |
| Entrevista | `GET clientes/{cliente}/entrevista` (`clientes.ficha.entrevista`) | `ficha/entrevista` | Respuestas por pantalla, corregir, revelar, historial (lo de F2.9) |
| Documentos | `GET clientes/{cliente}/documentos` (`clientes.ficha.documentos`) | `ficha/documentos` | Checklist, revisar documentos, "Ver datos" (lo de F3.4 y F4.6) |
| Excepciones | `GET clientes/{cliente}/excepciones` (`clientes.ficha.excepciones`) | `ficha/excepciones` | Las abiertas del cliente y del año con sus tres salidas |
| Revisión | `GET clientes/{cliente}/revision` | `ficha/revision` | 5.3 |
| Hoja para TaxWise | `GET clientes/{cliente}/hoja-taxwise` | `ficha/hoja-taxwise` | 5.5 |
| Debida diligencia | `GET clientes/{cliente}/debida-diligencia` | `ficha/debida-diligencia` | 5.4 |
| Historial | `GET clientes/{cliente}/historial` (`clientes.ficha.historial`) | `ficha/historial` | Eventos del expediente y, si existen, los datos del formulario anterior (solo lectura), la determinación fiscal vieja y las trazas de derivación |

- El **año** viaja en `?tax_year=` en todas las pestañas; el selector del encabezado navega a la misma pestaña con el otro año.
- La **conversación de WhatsApp** sigue como botón del encabezado (no es pestaña): el chat del portal ya está en Mensajes (F2.9).
- `EntrevistaCliente` se parte: las respuestas quedan en `components/cliente/entrevista-cliente.tsx` y el checklist pasa a `components/cliente/documentos-cliente.tsx` (sin cambiar su lógica).
- Lo viejo de `clientes/show.tsx` (secciones por forma, revelar, historial de campos, determinación y trazas) se mueve a `components/ficha-anterior/` y se muestra en Historial solo si el cliente tiene datos del formulario anterior. Se quitan "marcar revisado" y el nivel de riesgo (5.6).

**Interfaz (escritorio; en el celular todo en una columna):**

```
┌ Clientes › María Pérez ────────────────────────────────────────────────┐
│ María Pérez  [Prueba]                         Año [2025 ▾]  [WhatsApp] │
│ maria@… · +1 305…                                                      │
│ ● Por validar · desde el 3 nov. · Le toca: preparador · Vence 15 abr.  │
│ 3 excepciones (1 bloqueante) · 2 documentos por validar                │
├────────────────────────────────────────────────────────────────────────┤
│ Resumen | Entrevista | Documentos | Excepciones | Revisión | Hoja… | … │  ← en el celular: un Select "Sección"
└────────────────────────────────────────────────────────────────────────┘
 Resumen
 ┌ Qué falta para "Listo para preparar" ──────────────────────────────┐
 │ ✓ Consentimiento firmado (v1, 30 oct.)                              │
 │ ✓ Entrevista confirmada (1 nov.)                                    │
 │ ✗ R-02: 2 documentos sin validar → W-2 #2 (Leído), 1095-A (Subido)  │
 │ ✗ 1 excepción bloqueante, 2 de revisión  [Ver excepciones]          │
 │                                     [Ir a documentos]  ← acción principal
 └─────────────────────────────────────────────────────────────────────┘
 Línea de tiempo: 30 oct. Invitado → En entrevista · 1 nov. En recolección · 3 nov. Por validar …
```

- La acción principal del Resumen depende del estado: `por_validar` → "Ir a documentos"; `listo_para_preparar` → "Empezar revisión" (lleva a Revisión); `en_revision` → "Abrir la hoja para TaxWise"; estados del cliente → ninguna (texto "Le toca al cliente: …").
- El estado se ve con **texto e ícono**, nunca solo color (accesibilidad).

**Pruebas:** cada pestaña responde 200 al preparador asignado y al administrador, 403 a otro preparador y al cliente; `ficha.pestanas` trae solo rutas que existen; Historial trae los datos viejos solo si existen y sin valores sensibles en claro; Excepciones filtra por año.

## 5.3 Revisión del preparador

**Hoy:** "marcar revisado" por forma, que no se reabre (que-tiene-gts-hoy 5.4.3).

**Enfoque:** `App\Services\Expediente\RevisionExpediente` (reglas `arch-service-classes`, `controller-form-requests`, `validation-conditional-rules`) y `RevisionExpedienteController`:
- **Decisiones del preparador** (6.4: la IA no decide posiciones fiscales; la plataforma muestra lo que respondió el cliente al lado):
  - **Estado civil para el IRS** (`EstadoCivilIrs`) con motivo obligatorio; al lado, las respuestas B1, B2, B3, B5 y B6 en palabras.
  - **Clasificación de cada persona del bloque C** (`hijo_calificado`, `pariente_calificado`, `no_dependiente`) con motivo; al lado, C3, C4, C5, C6, C7, C8, C11, C12, C13 y C14 (C1, C2 y C9 son sensibles: "ver en Entrevista").
  - Cambiar una decisión en `preparado_en_taxwise` o `esperando_firma` devuelve el expediente a `en_revision` (evento `reabierto`: hay que corregir TaxWise). Después de `presentado` las decisiones quedan cerradas.
- **Checklist 7.2**, con puntos automáticos (los calcula la plataforma) y manuales (los confirma el preparador, con nota opcional):

| Punto | Tipo | Cumplido cuando | Requerido |
| --- | --- | --- | --- |
| 1. Identidad verificada con la identificación y las tarjetas | Manual | El preparador lo confirma | Siempre |
| 2. Estado civil para el IRS decidido con motivo | Automático | `estado_civil_irs` decidido | Siempre |
| 3. Cada persona clasificada con motivo | Automático | Todas las personas vigentes clasificadas | Si hay personas |
| 4. Cada documento validado o en "no aplica" (R-02) | Automático | R-02 se cumple | Siempre |
| 5. Sin excepciones abiertas que frenan | Automático | Ninguna bloqueante o de revisión abierta | Siempre |
| 6. Resumen de debida diligencia revisado | Manual | El preparador lo confirma | Si hay créditos del 8867 marcados |
| 7. Comparación con el año anterior | Manual | El preparador lo confirma | Si G5 = "sí" |

- Los puntos manuales solo se confirman en `en_revision`. "Aprobar" = la acción `preparado`, habilitada cuando todos los requeridos están cumplidos; si no, el botón deshabilitado dice al lado cuántos faltan.
- **Corregir** sigue en su lugar (visor y Entrevista). **Devolver** al cliente es la consulta de un documento (F4.7) o la observación (F3.4): al hacerlo, el expediente pasa solo a `en_consulta` y la revisión se reabre.
- Cada decisión, punto y transición deja un evento con actor y fecha.

**Interfaz:**

```
 Revisión                                   Estado: En revisión (desde 4 nov., Ana)
 ┌ 1. Estado civil para el IRS ─────────────────────────────────────────┐
 │ El cliente respondió: B1 Soltera · B5 Sí pagó más de la mitad del hogar │
 │ Decisión: [Cabeza de familia (HOH) ▾]  Motivo: [___________] [Guardar]  │
 │ Decidido por Ana el 4 nov.                                              │
 └─────────────────────────────────────────────────────────────────────────┘
 ┌ 2. Personas (bloque C) ──────────────────────────────────────────────┐
 │ Persona 1 — Hijo(a) · vivió 12 meses · estudiante: No …                │
 │ Clasificación: (•) Hijo calificado ( ) Pariente calificado ( ) No dep. │
 │ Motivo: [___________]                                       [Guardar]  │
 └─────────────────────────────────────────────────────────────────────────┘
 ┌ 3. Checklist de revisión ────────────────────────────────────────────┐
 │ [✓] Identidad verificada…           Confirmado por Ana, 4 nov.        │
 │  ✓  Estado civil decidido            (lo verifica la plataforma)      │
 │  ✗  R-02: falta validar el 1095-A    [Ir a documentos]                │
 │ [ ] Debida diligencia revisada       [Abrir resumen 8867]             │
 └─────────────────────────────────────────────────────────────────────────┘
          [Aprobar y marcar preparado en TaxWise]  "Faltan 2 puntos"
 Después: [Enviado a firmar (8879)] [Presentado…] [Aceptado] [Rechazado…]
```

**Pruebas:** cada transición permitida y una prohibida por estado (`TransicionNoPermitida` → 422 con mensaje); `preparado` sin puntos requeridos falla; `presentado` sin fecha o con fecha futura falla; `rechazado` sin motivo falla; confirmar un punto fuera de `en_revision` falla; cambiar una decisión en `preparado_en_taxwise` vuelve a `en_revision`; después de `presentado` las decisiones fallan; una persona de otro cliente da 404; otro preparador 403; cada acción deja su evento.

## 5.4 Debida diligencia (Form 8867)

**Hoy:** las respuestas se guardan con canal, fecha, actor e historial (F2), los documentos con quién y cuándo se validaron (F3.4). Falta juntarlo por crédito.

**Enfoque:**
- **El catálogo dice qué respalda cada crédito** (regla de oro: nada de reglas en las pantallas). Formulario nuevo `f8867` en `formularios.php` (`ficha => 'completa'`, sin activaciones: aplica según los créditos que marque el preparador), con una línea por parte y origen `respuesta`:

| Línea | Descripción | Preguntas (origen) |
| --- | --- | --- |
| `parte-i` | Requisitos de debida diligencia (entrevista y conocimiento) | `A1;A3;A5;A6;B1;C0` |
| `parte-ii` | Crédito por ingreso del trabajo (EIC) | `A3;A5;A6;B1;B3;C0;C2;C3;C4;C5;C6;C7;C8;C9;C11;D1;D9;D10;D15;D16` |
| `parte-iii` | Crédito por hijos, ACTC y otros dependientes | `C0;C2;C3;C4;C5;C6;C9;C11;C11.1;C12;C13;C14` |
| `parte-iv` | Crédito de oportunidad americana (AOTC) | `F1;F1.1` |
| `parte-v` | Cabeza de familia | `B1;B3;B5;C0;C3;C4` |

  La subtarea verifica los nombres de las partes contra el Form 8867 y sus instrucciones (rev. nov. 2025) y lo anota en "Cómo quedó".
- **Documentos en que se basó cada parte:** los que activan esas preguntas en el catálogo (`CatalogoFiscal::activadosPor`) y están en el checklist, con quién los validó y cuándo (sale de los datos, no se escribe a mano).
- **Créditos que se reclaman** (`creditos_8867`): los marca el preparador. La plataforma los **sugiere** (y dice por qué): `hoh` si el estado civil decidido es HOH; `ctc` si hay una persona clasificada como dependiente; `aotc` si F1 = "sí"; `eic` si hubo ingreso del trabajo (D1, D15 o D16) y A3 = SSN válido. El preparador confirma; queda el evento.
- **Resumen** (`DebidaDiligencia::vista`), **determinista**: por parte, cada pregunta con la respuesta en palabras (lo sensible no sale: "ver en Entrevista"), **cómo** (canal), **cuándo** (fecha y hora), **de quién** (cliente o el preparador que la corrigió) y cuántos cambios tuvo; y los documentos. Arriba: inicio de la entrevista, canal, confirmación y consentimiento firmado. Se puede imprimir (mismo mecanismo que la hoja).
- Es el registro que pide el IRS (7.1: "cómo, cuándo y de quién"); por eso no lo escribe una IA (decisión 1).

**Interfaz:**

```
 Debida diligencia (Form 8867)                               [Imprimir]
 Créditos que se reclaman (sugeridos por la plataforma, los confirmas tú):
 [✓] Crédito por hijos — sugerido: Persona 1 clasificada como hijo calificado
 [✓] Cabeza de familia — sugerido: estado civil decidido HOH
 [ ] EIC — sugerido: tuvo salario (D1) y SSN válido (A3)      [Guardar créditos]
 Entrevista: iniciada el 30 oct. por WhatsApp · confirmada el 1 nov. en el portal · consentimiento v1
 ┌ Parte III — Crédito por hijos ────────────────────────────────────────┐
 │ C3 Parentesco (Persona 1): Hijo(a) · WhatsApp · 30 oct. 10:12 · cliente │
 │ C4 Meses que vivió contigo: 12 · Portal · 1 nov. · corregida por Ana (1) │
 │ C9 Número de identificación: (sensible, ver en Entrevista)              │
 │ Documentos: Tarjeta del Seguro Social (Persona 1) · validada por Ana, 3 nov. │
 └──────────────────────────────────────────────────────────────────────────┘
```

**Pruebas:** con el catálogo real, las partes y sus preguntas salen del `f8867`; un crédito no marcado no muestra su parte (salvo la I); las sugerencias según los datos; ningún valor sensible en las props; cada pregunta trae canal, fecha y actor; guardar créditos deja el evento; `CatalogoRealTest` cuenta el formulario nuevo y `verificar` pasa.

## 5.5 Hoja para TaxWise

**Hoy:** no existe; el preparador digita en TaxWise abriendo cada documento.

**Decisiones de diseño:**
- **Qué es:** la lista de lo que el preparador **digita** en TaxWise, en el orden de carga (7.3: encabezado y estado civil → dependientes → W-2 → 1099 → Schedule C → créditos → pagos → banco), y al final los **totales de control** por línea del 1040 y de sus schedules. TaxWise se carga **por documento** (una pantalla por W-2, por 1099), así que la hoja muestra cada documento con sus casillas y **a qué línea va** cada casilla; y los totales dejan comparar con lo que calcula TaxWise.
- **De dónde sale cada valor** (una sola fuente, el catálogo del año):

| Origen | Cómo se arma la fila | Ejemplo |
| --- | --- | --- |
| Casilla leída (`documento_datos`, los vigentes de `DatosDocumento::vigentes`) | Una fila por casilla con valor o por casilla clave sin valor; el destino sale de las líneas del catálogo que apuntan a esa casilla (`w2:1` → "Form 1040 · línea 1a") | W-2 #1 (Empresa X), casilla 1 → 1040 · 1a |
| Respuesta de la entrevista | Una fila por pregunta visible, con `TextoRespuesta` | A1.2 Ocupación → encabezado del 1040 |
| Decisión del preparador | Estado civil para el IRS y clasificación de cada persona | HOH, con motivo |
| Documento que no se lee (recibos, registros, datos bancarios, "otro documento") | Una fila "digitar desde el documento" con sus archivos | Registros de ingresos y gastos → Schedule C, líneas 8–27 |
| Líneas `calculo` o `linea` | No son filas: "lo calcula TaxWise" (F6 hará el borrador) | 1040 · 11a |

- **Secciones** (constante única `HojaTaxwise::SECCIONES`, para ajustarla cuando GTS muestre las pantallas de TaxWise, P6): `encabezado` (A1, A1.1, A1.2, A2, SSN de la tarjeta del contribuyente, A3, A12, estado civil decidido y B1, cónyuge B4–B4.3, A5, A9, A14, A15) · `dependientes` (por persona: C1, C2, C3, C4, SSN de su tarjeta, clasificación) · `salarios` (grupo `trabajo`) · `negocio` (grupo `negocio`: 1099-NEC, 1099-K, 1099-MISC, K-1, registros) · `otros_ingresos` (grupos `jubilacion`, `inversiones`, `otros_ingresos`) · `deducciones` (grupo `gastos`) · `situaciones` (grupo `situaciones`, incluido el 1095-A → 8962) · `pagos` (G1, G2 y el grupo `pagos` menos `datos-bancarios`) · `banco` (G3, G4, `datos-bancarios`) · `totales`.
- **Estado de cada fila** (texto e ícono, nunca solo color), en este orden de prioridad:

| Estado | Cuándo | Texto (servidor, `__()`) |
| --- | --- | --- |
| `con_excepcion` | El dato tiene una excepción abierta | "Regla R-04 abierta (bloqueante)" |
| `pendiente` | Falta el documento, falta leerlo, la casilla clave no tiene valor, o la pregunta visible no tiene respuesta, o falta la decisión | "Falta: el cliente no lo ha subido" / "Sin valor en el documento" / "Falta decidirlo en Revisión" |
| `baja_confianza` | Confianza < umbral (`lectura.umbral_confianza`) y no lo corrigió nadie | "Leído con dudas (0,60)" **+ la explicación del motivo y el enlace a la tabla** |
| `corregido` | `origen = preparador` | "Corregido por Ana el 3 nov." |
| `validado` | El documento está validado | "Validado por Ana el 3 nov." |
| `sin_validar` | El documento está leído y sin validar | "Leído, sin validar" |
| `respondido` | Respuesta de la entrevista | "Respondido por WhatsApp el 30 oct." |
| `decidido` | Decisión del preparador | "Decidido por Ana el 4 nov." |
| `manual` | Documento que no se lee | "Digitar desde el documento" |

  **La confianza se explica:** toda fila con confianza muestra el número, el **motivo en palabras** (`MotivoConfianza::titulo()` y `explicacion()`) y un botón "¿Cómo se calcula?" que abre `ConfianzaPanel` (la tabla de `CalculadoraConfianza::tabla()` y el umbral vigente, que el administrador cambia en Parámetros).
- **Origen y enlace:** "W-2 #1 (Empresa X), casilla 1" enlaza al visor **en ese dato** (`?dato=`, 5.0); una respuesta enlaza a la pestaña Entrevista; una decisión, a Revisión. El nombre del emisor (casilla `c` del W-2, `pagador-nombre` de los 1099) sale solo si la casilla no es sensible en el catálogo.
- **Totales de control:** por cada línea del catálogo con origen `casilla` y tipo `monto`, de un formulario activo del cliente (o el 1040), la suma de esas casillas en todos sus documentos, con cuántos documentos suma y si está **completa** (todos validados o corregidos y ninguno pendiente). Es una suma, no un cálculo fiscal ("1040 · 1a = suma de la casilla 1 de 2 W-2").
- **Datos sensibles** (casillas o preguntas `sensible`): nunca en claro en las props. Llevan `mascara` (los últimos 4 para SSN, EIN o TIN; "Oculto" para lo demás) y el botón "Revelar", que usa **los endpoints que ya existen** (visor: `clientes.documentos.visor.revelar`; entrevista: `clientes.entrevista.respuestas.revelar`): piden la contraseña reciente y dejan registro. Ver la decisión 3.
- **Formatos de salida:**
  - **Pantalla** (la principal), con filtro "Solo lo pendiente".
  - **Imprimir o guardar como PDF** con el diálogo del navegador (`window.print()` y estilos `print:`): sin librería nueva, sin un PDF con datos del cliente guardado en el servidor, y es la misma vista que se revisó. Lleva arriba "Hoja de trabajo para TaxWise — no se presenta al IRS", el cliente, el año y la fecha. Lo sensible sale como esté en pantalla (enmascarado salvo lo que se reveló con contraseña). El PDF oficial del 1040 diligenciado es de F6.
  - **CSV** (abre en Excel; UTF-8 con BOM, separado por comas): mismas filas y columnas (sección, formulario, línea, descripción, valor, origen, estado), **siempre enmascarado**, generado al vuelo (`streamDownload`, sin archivo temporal), `Cache-Control: private, no-store`, y deja en la bitácora `Exportado` con quién y cuándo (nunca el contenido). Ver la decisión 2.
- **Rendimiento:** la hoja se pide diferida (`Inertia::defer`, skeleton por sección); una consulta por ítem para los datos vigentes (pocos ítems por cliente); los textos se arman en el servidor (una sola fuente para la pantalla y el CSV, guía 4.3).

**Interfaz:**

```
 Hoja para TaxWise · 2025 · generada el 4 nov. 10:30
 18 filas · 3 pendientes · 1 con dudas · 1 con excepción
 [Solo lo pendiente ☐]                       [Descargar CSV] [Imprimir o guardar PDF]
 ── Encabezado y estado civil ─────────────────────────────────────────
 Form 1040 · Encabezado  Nombre del contribuyente  María Pérez   Entrevista A1   ✓ Respondido en el portal el 1 nov.
 Form 1040 · Encabezado  SSN del contribuyente     ***-**-1234 [Revelar]  Tarjeta SS, casilla ssn  ✓ Validado por Ana, 3 nov.
 Form 1040 · Estado civil Estado civil para el IRS Cabeza de familia (HOH)  Revisión  ✓ Decidido por Ana, 4 nov.
 ── W-2 ───────────────────────────────────────────────────────────────
 W-2 #1 (Empresa X)
   → 1040 · 1a  Casilla 1 Salarios      45,120.00  casilla 1 ↗  ✓ Validado por Ana, 3 nov.
   → 1040 · 25a Casilla 2 Impuesto ret.  4,810.00  casilla 2 ↗  ⚠ Leído con dudas (0,60) — "La IA no está segura…" [¿Cómo se calcula?]
 ── Totales de control ────────────────────────────────────────────────
 1040 · 1a  Salarios  45,120.00  (suma de 1 W-2, completa)
```

En el celular cada fila es una tarjeta (destino, descripción, valor, origen, estado), sin scroll horizontal.

**Pruebas:**
- María (W-2 y tarjeta leídos, HOH decidido): secciones en orden, destinos `1040 · 1a` y `25a`, total de 1a.
- Prioridad de estados (excepción > pendiente > baja confianza > corregido > validado > sin validar), con el umbral leído del parámetro (cambiarlo cambia el estado).
- Ningún valor sensible en claro en las props ni en el CSV (SSN como `***-**-1234`); el CSV deja `Exportado` en la bitácora sin valores; trae BOM y los encabezados.
- Un documento que no se lee sale como `manual`; uno pendiente como `pendiente`; las líneas `calculo` no salen como filas.
- 2026 usa las casillas de 2026 (`1099-nec:1a`).
- Permisos: otro preparador 403; el cliente 403.

## 5.6 Cola de trabajo, panel, avisos y retiros

**Hoy:** lista y panel sobre formas viejas, riesgo y campos.

**Enfoque:**
- **Lista de clientes** (`ClienteController@index`, sigue filtrando en el navegador): por cliente, el estado del expediente (o `invitado` si no hay), su fecha, el turno, el vencimiento, el perfil, el canal de inicio y las excepciones abiertas (total y bloqueantes, en una consulta agrupada). Columnas nuevas: Estado del expediente, Le toca, Vence, Tipo de ingreso, Excepciones, Documentos (ya existe), WhatsApp. Filtros: estado, le toca, tipo de ingreso, con excepciones, canal. El filtro de estado se puede abrir desde el panel (`?estado=`). Orden por defecto: le toca al preparador primero, después vencimiento más cercano. Se quitan las columnas de riesgo, formas y estado general viejo.
- **Panel** (`DashboardController`, servicio nuevo `PanelExpedientes` que reemplaza a `DashboardSummaryService`):
  - expedientes **por estado** (tarjetas que abren la lista filtrada);
  - **colas**: listos para preparar, por validar, en consulta, excepciones abiertas (y bloqueantes, enlace a la cola), vencen en 30 días;
  - **avisos** de los últimos 14 días, sacados de los eventos de estado: "terminó la entrevista" (`en_entrevista` → otro), "subió documentos" (`en_recoleccion` → `por_validar`), "respondió la consulta" (`en_consulta` → otro), "listo para preparar";
  - **tiempos** (7.6) de los expedientes del año: días desde el inicio de la entrevista hasta "Listo", horas de "En revisión" a "Preparado", consultas y correcciones por expediente;
  - se conservan "últimos clientes".
- **Correo al preparador** (decisión 4): listener `AvisarPreparadorDeExpediente` del evento `ExpedienteCambioDeEstado`, con la notificación en cola `AvisoExpedientePreparador` (solo nombre del cliente, estado y enlace; nada de la declaración) cuando el expediente llega a `listo_para_preparar` o el cliente responde una consulta (`en_consulta` → otro). Va al preparador asignado o, si no tiene, a los administradores. Bandera `tax.avisos_correo_preparador` (prendida por defecto) para apagarlo sin deploy.
- **Retiros** (9.3.2): rutas web `clientes.export`, `clientes.marcar-revisado`, `clientes.nivel-riesgo.*`; métodos de `ClienteController`; `RiesgoCasoService` y su prueba; `DashboardSummaryService`. **Se conservan** `ClienteExportService` y `FormaCliente::marcarRevisado` (los usa la API externa, decisión 14 de F2) y las tablas y modelos viejos (L12).
- `ClienteController@show` pasa a ser la pestaña Resumen (`ficha/resumen`): `ficha` + `resumen` (diagnóstico, eventos, avisos de la entrevista).

**Interfaz del panel:**

```
 Panel · 2025
 [Por validar 4] [Listos 2] [En revisión 1] [En consulta 3] [En recolección 6] [En entrevista 9] …
 ┌ Te toca ────────────────────┐ ┌ Avisos (14 días) ─────────────────────────┐
 │ Listos para preparar   2  → │ │ ● Juan R. respondió la consulta · hoy      │
 │ Por validar            4  → │ │ ● María P. está lista para preparar · ayer │
 │ Excepciones 7 (2 bloq.) → │ │ ● Ana G. terminó la entrevista · 2 nov.    │
 │ Vencen en 30 días      1  → │ └────────────────────────────────────────────┘
 └─────────────────────────────┘ ┌ Tiempos ── días a "Listo" 6,2 · horas de preparación 1,4 · consultas 0,8 ┐
```

**Pruebas:** el preparador ve solo sus clientes en lista y panel; un cliente sin entrevista sale `invitado`; los conteos por estado; los avisos salen de los eventos y respetan los 14 días; los tiempos con eventos sembrados; el correo sale una vez al llegar a listo (y no con la bandera apagada, ni al volver a recalcular sin cambio); las rutas retiradas dan 404; la API externa sigue exportando y marcando revisado.

## 5.7 "¿Cómo va lo mío?"

**Enfoque:**
- `EstadoExpediente::textoCliente()` (con `__()`), sin montos ni jerga:

| Estado | Para el cliente |
| --- | --- |
| `invitado` | Falta firmar la autorización para empezar. |
| `en_entrevista` | Estamos en la entrevista: faltan algunas respuestas. |
| `en_recoleccion` | Estamos esperando tus documentos. |
| `en_consulta` | Tenemos una pregunta sobre uno de tus documentos. |
| `por_validar` | Estamos revisando tus documentos. |
| `listo_para_preparar`, `en_revision` | Tu preparador está revisando tu declaración. |
| `preparado_en_taxwise`, `esperando_firma` | Tu declaración está lista; te contactaremos para firmarla. |
| `presentado` | Presentamos tu declaración al IRS el :fecha. |
| `aceptado` | El IRS aceptó tu declaración. |
| `rechazado` | El IRS nos devolvió tu declaración; tu preparador te contactará. |

- **Portal:** tarjeta "Tu declaración" arriba del inicio de la entrevista con ese texto y la fecha.
- **Agente:** `consultar_entrevista` trae `expediente: {estado, para_el_cliente, desde}`; una línea en `prompt_actuales/fases/seguimiento.md` y `portal_dudas.md`: si el cliente pregunta cómo va, contestar con `para_el_cliente`, sin inventar fechas ni montos. Después del deploy hay que armar el borrador de prompts **con `--fases=seguimiento,portal_dudas`** y Steven lo publica (con autorización).

**Pruebas:** el texto por estado en español e inglés; `consultar_entrevista` lo trae y no trae montos; el portal lo muestra; otro cliente no ve el de nadie.

---

## Permisos y bitácora

| Acción | Cliente | Preparador | Administrador | Registro |
| --- | --- | --- | --- | --- |
| Ver cualquier pestaña de la ficha | ✗ (403) | Solo sus clientes | Todos | — |
| Decisiones, puntos y transiciones | ✗ | Solo sus clientes | Todos | `expediente_eventos` (actor, fecha, motivo) + `bitacora_actividad` (nombres de atributo) |
| Revelar un dato en la hoja | ✗ | Con contraseña reciente | Con contraseña reciente | `Revelado` (visor) o `entrevista_respuesta_reveals` (respuesta) |
| Descargar el CSV | ✗ | Sí | Sí | `Exportado` en la bitácora |
| Ver el estado en lenguaje del cliente | Solo el suyo | — | — | — |

- Todo con `ClientePolicy` (`view` para leer, `update` para escribir). Un ítem, persona o expediente de otro cliente da **404** (como el visor).
- Ningún evento, motivo de reapertura, aviso, correo o fila del CSV lleva un SSN, una cuenta o un monto.

## Idiomas

- **Textos del servidor** (`__()`; su inglés, para no pisarse entre subtareas, en `lang/expediente/<area>/en.json`, que se registran con `addJsonPath` y funcionan igual que `lang/en.json`): etiquetas y textos de los enums, estados y orígenes de la hoja, motivos de reapertura, avisos, correo, mensajes de error de transiciones. Sin `trans_choice()` (guía 4.3).
- **Textos de la interfaz:** para que las subtareas en paralelo no se pisen, F5 no edita `es.json`/`en.json`. Cada área tiene su par de archivos con un espacio de nombres propio en `resources/js/i18n/locales/expediente/` (`ficha`, `revision`, `hoja`, `debidaDiligencia`, `panel`, `miDeclaracion`), que `i18n/index.ts` mezcla con los de siempre. Las claves viejas que queden sin uso se borran al cerrar la fase.
- Las preguntas y documentos del catálogo ya tienen `_es` y `_en`. El Form 8867 nuevo lleva su descripción en español (el catálogo de formularios no tiene `_en`; igual que el resto de líneas).

## Reglas de las skills que se aplican

| Skill | Regla | Dónde |
| --- | --- | --- |
| laravel-best-practices | `arch-service-classes` | `DiagnosticoExpediente`, `EstadosExpediente`, `RevisionExpediente`, `DebidaDiligencia`, `HojaTaxwise`, `PanelExpedientes` |
| | `arch-value-objects` / `arch-dto-pattern` | `Diagnostico` (DTO `readonly`) |
| | `arch-event-driven`, `eloquent-model-events` | Observer de recálculo; `ExpedienteCambioDeEstado` → aviso |
| | `arch-queue-routing` | Correo en cola (`ShouldQueue`) |
| | `controller-form-requests`, `validation-conditional-rules` | `TransicionExpedienteRequest` (`required_if` de fecha y motivo), decisiones y puntos |
| | `controller-dependency-injection` | Servicios inyectados en los métodos de los controladores |
| | `eloquent-casts`, `eloquent-relationships`, `eloquent-eager-loading` | Modelos nuevos; conteos agrupados en la lista y el panel |
| | `sec-mass-assignment` | `#[Fillable]` explícito en los modelos nuevos |
| react-best-practices | Estado derivado en el render; sin efectos para lo que se calcula; `key` para resetear formularios | Revisión (formularios por persona con `key`), filtro "solo lo pendiente" |
| | Composición sobre props booleanas | `EntrevistaCliente` y `DocumentosCliente` separados en vez de un `modo` |
| vercel-react-best-practices | `async-defer-await` / `server-dedup-props` | Una página por pestaña, props diferidas solo donde pesan |
| | `rerender-no-inline-components`, `rerender-lazy-state-init` | Componentes de fila fuera del padre; dato inicial del visor |
| | `js-index-maps`, `js-set-map-lookups` | Agrupar filas por sección y totales con `Map` |
| | `bundle-barrel-imports` | Importar cada componente de `components/ui` por su archivo |
| responsive-design | Mobile-first, touch targets de 44 px, sin overflow horizontal | Pestañas como `Select` en el celular; filas de la hoja como tarjetas; tablas de la lista como en F3/F4 |

## Riesgos

| Riesgo | Mitigación |
| --- | --- |
| El recálculo no corre en algún camino (estado viejo) | Observer en los cuatro modelos + llamada explícita en `MotorReglas`; comando `expedientes:recalcular` para reparar; prueba por cada camino (validar, observar, no aplica, subir, aceptar excepción, confirmar entrevista, firmar consentimiento) |
| Recalcular demasiado (lento) | Uno por entrevista y por commit; el diagnóstico usa conteos, no carga datos leídos |
| Un ciclo de eventos | El recálculo escribe solo `expedientes` y `expediente_eventos`, que no están observados para recalcular |
| La reapertura borra trabajo del preparador | Solo borra la confirmación de los puntos manuales; el evento `reabierto` guarda el motivo y los eventos anteriores siguen; las decisiones no se tocan |
| Datos sensibles en la hoja, el CSV o el correo | Props y CSV siempre enmascarados; revelar con contraseña y registro; pruebas que buscan un SSN y un monto en las props, el CSV, los eventos y el correo |
| Un número mal digitado en TaxWise por una lectura dudosa | Cada fila dice su estado y su confianza con el motivo; filtro "solo lo pendiente"; totales de control que se comparan con TaxWise |
| SQLite en pruebas y PostgreSQL en producción | Nada de `lockForUpdate()` con agregados; `orderByRaw` con `case` (ya usado en la cola); el agente principal revisa en el navegador contra PostgreSQL local |
| Subtareas en paralelo que se pisan | Archivos repartidos (rutas, i18n y tipos por área); Wayfinder regenera igual para todos (determinista); `pint` solo sobre los archivos propios; el cierre corre todo junto |
| `types:check` falla por un archivo de otra subtarea de la misma ola | Cada subtarea revisa que **sus** archivos no tengan errores; el cierre exige el chequeo completo limpio |
| Correo que molesta | Solo dos situaciones, una vez por cambio de estado, con bandera para apagarlo |
| El orden de la hoja no es el de TaxWise (P6) | Una sola constante `SECCIONES`; se ajusta cuando GTS muestre las pantallas |
| Reemplazar la ficha rompe pruebas viejas | Las pruebas de la ficha vieja se mueven a Historial (datos del formulario anterior) y a Resumen, en la misma ola |

## Plan de pruebas

- **Pest**, en `tests/Feature/Expediente/` (nuevas) y las que se ajustan (`ClientePanelTest`, `DashboardTest`, `Lectura/VisorTest`, `Entrevista/AgenteEntrevistaTest`, `Portal/EntrevistaTest`, `CatalogoFiscal/CatalogoRealTest`); `RiesgoCasoTest` se borra con el servicio.
- Por subtarea: solo sus archivos de prueba (`PAO_DISABLE=1 php artisan test --compact <archivos>`); **la suite completa solo al cerrar la fase**: `PAO_DISABLE=1 php artisan test --parallel`, más `vendor/bin/pint --test` y `npm run types:check` limpios, `npm run build`.
- **Caso de punta a punta** (`tests/Feature/Expediente/MariaExpedienteTest.php`, en el cierre): María responde, firma, sube W-2, tarjetas y 1095-A, se leen (OpenAI simulado), el preparador valida, el expediente pasa por cada estado hasta `listo_para_preparar`; decide HOH y clasifica al hijo; confirma los puntos; la hoja trae 1a, 25a y el 8962; la debida diligencia trae CTC y HOH; aprueba, presenta y acepta. Un W-2 corregido después de `preparado_en_taxwise` reabre la revisión.
- **En el navegador** (agente principal, al cerrar las olas 4 y 5): cada pestaña en escritorio y a 375 px, en español e inglés, con estados vacío, error, éxito y procesando; imprimir la hoja (vista previa del navegador); descargar el CSV y abrirlo en Excel/Numbers; revelar con contraseña.

## Orden de entregas y commits sugeridos

Se programa por olas (las subtareas), pero se **commitea por entrega**, en este orden. Cada grupo se diseñó para compilar y desplegarse solo: las pestañas que no existen no aparecen (salen del servidor con `Route::has`), cada área tiene sus rutas, sus textos y sus tipos.

1. **5.0** — `feat(bitacora): record reveals and exports with their own action` · `feat(visor): open the viewer on a given data point`
2. **5.1** — `feat(expediente): add the case file with dated states and R-02` · `feat(catalogo): add Form 8867, filing deadlines and implement R-02` · `docs: record phase 5.1 outcome`
   - Después del deploy (con autorización): `catalogo-fiscal:cargar 2025` y `2026`, `catalogo-fiscal:verificar`, `expedientes:recalcular --dry-run` y luego sin `--dry-run`.
3. **5.2 + 5.6** (van juntas: la Resumen nueva, la lista y el panel reemplazan las vistas viejas y retiran riesgo, ZIP y "marcar revisado") — `feat(ficha): split the client file into tabs` · `feat(panel): show cases by state with queues, notices and times` · `feat(clientes): filter the client list by case state` · `refactor(clientes): retire risk level, ZIP export and per-form review` · `docs: record phase 5.2 and 5.6 outcome`
4. **5.3** — `feat(revision): add the preparer review with decisions and checklist` · `docs: …`
5. **5.4** — `feat(debida-diligencia): add the Form 8867 due diligence summary` · `docs: …`
6. **5.5** — `feat(hoja): add the TaxWise worksheet with print and CSV` · `docs: …`
7. **5.7** — `feat(portal): tell the client how their return is going` · `docs(agente): answer how the return is going` · `docs: …`
   - Después del deploy (con autorización): `agente:borrador-prompts --fases=seguimiento,portal_dudas`; Steven publica.

Siempre preguntándole a Steven antes de cada commit; se hace con `/commit` (push a `main` = deploy).

## Migración de datos y despliegue

- Migración nueva sin datos que mover; `expedientes:recalcular` crea los expedientes de las entrevistas existentes (en producción todos son de prueba, 30 oct.).
- Recargar el catálogo de 2025 y 2026 (R-02, vencimientos, Form 8867) y verificar.
- El correo usa la cola y el `MAIL_*` que ya existe; la bandera `AVISOS_CORREO_PREPARADOR` se puede dejar sin definir (prendida).
- Verificación por SSH, solo lectura: migración corrida, rutas `clientes.ficha.*`, `clientes.revision.*`, `clientes.hoja-taxwise.*`, `clientes.debida-diligencia.*`, sin errores nuevos en el log.

## Listo cuando

- Cada cliente tiene su expediente con estado y fecha de cada cambio, que cambia solo y se reabre cuando algo cambia después de revisado.
- R-02 frena "Listo para preparar" y dice qué falta.
- El preparador decide estado civil y dependientes con motivo, completa el checklist 7.2, aprueba y registra preparado, firma, presentado y acuse.
- La hoja muestra cada valor con su destino, su origen (con enlace al dato) y su estado, con la confianza explicada; se imprime y se descarga en CSV sin datos sensibles en claro.
- El resumen 8867 junta preguntas, respuestas, canal, fecha y quién, y los documentos de cada crédito marcado.
- La lista y el panel muestran expedientes por estado, colas, avisos y tiempos; el preparador recibe el correo.
- El cliente y el agente saben "cómo va lo mío" sin ver montos.
- Ya no están el nivel de riesgo, el ZIP ni "marcar revisado" en la web; la API externa sigue igual.

## Ajustes por lo que se hizo el 4 y 5 de noviembre

El plan se escribió el 4 nov. Después se hicieron cambios en producción que F5 usa; estos son los ajustes que las subtareas deben tomar en cuenta (ya están en las subtareas indicadas):

| Cambio ya hecho | Qué cambia en F5 | Subtareas |
|---|---|---|
| **`ExplicadorExcepcion`** (frase de por qué saltó cada regla, `caso` y `que_hacer`) en la cola, el visor y el diálogo de aceptar | La pestaña Excepciones de la ficha, el punto de revisión de excepciones y el estado `con_excepcion` de la hoja usan este servicio; no se escriben textos de reglas en otro lado. | 5.2d, 5.3a, 5.5a |
| **El visor ya lista las excepciones de todo el documento** (R-06 año o tipo y R-07 repetido salen en un recuadro arriba) | 5.0a queda solo con "Revelado" en la bitácora y `?dato=`. | 5.0a |
| **Tarjeta del Seguro Social del cónyuge** como documento aparte (`tarjeta-ssn-conyuge`, uno por declaración) y B4 y B4.1 solo por el portal | La hoja toma el SSN del cónyuge de esa tarjeta (o de B4.3) y los datos del cónyuge (B4, B4.1, B4.3) se tratan como sensibles (enmascarados en el CSV, revelables con contraseña). El punto 1 del checklist ("identidad verificada con la identificación y las tarjetas") incluye la tarjeta del cónyuge. | 5.3a, 5.5a |
| **Personas exentas**: quien pagó más de la mitad de su propia manutención (C12 = sí), ganó USD 5.200 o más (C13 = no) o no depende del cliente (C14 = no) no recibe más preguntas ni documentos | En la clasificación, esa persona llega **sugerida como `no_dependiente`** con el motivo "Sus respuestas la dejan fuera como dependiente (C12, C13, C14)"; el preparador confirma. No tiene C1, C2, C5–C11 ni tarjeta, así que se muestra por su parentesco ("Persona 1 · padre") y la sección `dependientes` de la hoja la omite. La sugerencia de crédito por dependientes ignora a las exentas. | 5.3a, 5.3b, 5.4a, 5.5a |
| **R-06 y R-01** tratan la tarjeta del cónyuge como el mismo tipo que la de cada persona | Nada que hacer; los puntos de verificación automática no deben pedir una tarjeta de más. | 5.1b |
| **La lectura funciona** (esquema corregido, worker como `www-data`) y el visor carga la imagen | Los datos leídos existen de verdad: las pruebas de punta a punta pueden usar los documentos de prueba de Javier (`~/Downloads/documentos-prueba-javier/`) además de los simulados. | Cierre |
| Condición nueva `desde` en el catálogo (número mayor o igual) | Ninguna; hay que conocerla si el catálogo de F5 agrega condiciones (5.1c). | 5.1c |

## Decisiones para Steven

**Respondidas el 5 nov. 2026: Steven aceptó la recomendación en las seis.** (1) Resumen determinista, sin IA. (2) CSV, no .xlsx. (3) CSV siempre enmascarado; la impresión sale como la pantalla. (4) Correo solo en "Listo para preparar" y cuando el cliente responde una consulta, con bandera. (5) Los estados después de preparar entran en F5. (6) "No aplica" se mantiene como en F3. Falta su aprobación del plan en conjunto antes de programar.

1. **Resumen del expediente y del 8867 escrito por IA (9.5 lo menciona).** Recomiendo **no hacerlo en F5**: el resumen 8867 es el registro que pide el IRS ("cómo, cuándo y de quién"), y armado desde los datos es exacto y defendible; uno escrito por IA puede inventar y además manda más datos del cliente a OpenAI (L2). El "resumen del expediente" ya lo cubren el Resumen y la hoja. Se puede sumar en F7 como ayuda, siempre al lado del registro determinista.
2. **"Excel" = CSV o .xlsx.** Recomiendo **CSV** (abre en Excel, sin librería nueva, generado al vuelo sin archivo en el servidor). Un .xlsx con formato necesitaría agregar `openspout/openspout`; se puede hacer después si GTS lo pide.
3. **Datos sensibles al imprimir y en el CSV.** Recomiendo: **CSV siempre enmascarado** (últimos 4 del SSN o EIN), y **la impresión sale como esté la pantalla**: lo que el preparador reveló con contraseña (queda registrado) sale completo; lo demás, enmascarado. Así un archivo descargado nunca lleva SSN completos. La alternativa es una "hoja completa" que pida contraseña y lo traiga todo revelado (más cómoda para digitar, más riesgosa si el archivo se pierde).
4. **Avisos por correo.** Recomiendo correo solo cuando el expediente queda **"Listo para preparar"** y cuando el cliente **responde una consulta**; lo demás (terminó la entrevista, subió documentos) solo en el panel. Con la bandera para apagarlo. ¿Te sirve así o lo quieres solo en el panel?
5. **Estados después de preparar (7.4).** Recomiendo incluir en F5 "Esperando firma (8879)", "Presentado" (con fecha), "Aceptado" y "Rechazado" (con motivo) como marcas manuales del preparador: son cuatro botones, dan la medición completa (7.6) y no dependen de L14 (la firma sigue en TaxWise). Si prefieres, F5 llega hasta "Preparado en TaxWise" y lo demás pasa a F7.
6. **"No aplica" y R-02.** El documento madre dice "no aplica con motivo **aceptado**", pero en F3 decidiste que cuenta apenas el cliente lo marca y el preparador lo puede rechazar. Recomiendo **mantener lo de F3** (no sumar un clic por documento) y que el punto 4 del checklist liste los "no aplica" con su motivo para que el preparador los mire antes de aprobar. La alternativa es exigir que el preparador acepte cada uno (R-02 no se cumple hasta entonces).
