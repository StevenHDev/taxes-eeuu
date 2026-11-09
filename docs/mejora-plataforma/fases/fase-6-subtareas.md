# Fase 6 — Subtareas para trabajar con Sonnet 5.5

9 nov. 2026 · Plan **aprobado por Steven el 9 nov.**: [`fase-6-calculo-borrador-1040.md`](fase-6-calculo-borrador-1040.md). Lee primero las secciones "Qué hay hoy", "Estrategia de validación contra el IRS" y "Decisiones tomadas (9 nov.)" del plan.

> **Por qué este documento:** todo F6 lo programa **Sonnet 5.5**, con varias subtareas **en paralelo** sobre el mismo repositorio. Las decisiones de diseño, fiscales y de §7216 ya están tomadas en el plan; cada subtarea es "seguir instrucciones", no "decidir". Si algo no está aquí ni en el plan, **para y pregúntale al agente principal**; no inventes arquitectura. **No inventes cifras ni reglas del IRS:** todo número sale del PDF o de la instrucción del IRS del año, con su página; si no lo puedes verificar, lo dejas "por verificar" y lo reportas.

> **Alcance de este archivo:** detalla la **ola 1** (lista para lanzar). Las olas 2 a 7 están en la tabla de estado con sus archivos (plan, "Olas para Sonnet 5.5") y **se detallan aquí cuando la anterior cierre**, porque dependen de lo que salga de ella (nombres reales de clases, hallazgos de la verificación contra el IRS).

## Cómo se trabaja

1. Las subtareas van por **olas**. Dentro de una ola se lanzan todas a la vez (una sesión de Sonnet 5.5 por subtarea); una ola empieza cuando la anterior terminó y el agente principal la revisó.
2. Antes de programar, cada agente lee: `docs/mejora-plataforma/guia-desarrollo.md` (secciones 4 y 6), la sección del plan que cita su subtarea y los archivos de "Contexto mínimo".
3. **Solo se tocan los "Archivos permitidos".** Los de "No tocar" son de otra subtarea de la misma ola. Si hace falta otro archivo, se para y se pregunta.
4. Al terminar cada subtarea:
   - `vendor/bin/pint <tus archivos PHP>` (no `--dirty`: formatearía archivos de otros agentes a mitad de trabajo);
   - si tocaste rutas: `php artisan wayfinder:generate --with-form`;
   - si tocaste frontend: `npm run types:check` y revisa que **tus** archivos no tengan errores (un error en un archivo de otra subtarea de la misma ola no es tuyo: anótalo);
   - tus pruebas: `PAO_DISABLE=1 php artisan test --compact <tus archivos de prueba>`;
   - **la suite completa no se corre por subtarea**: se corre una vez al cerrar la fase (`PAO_DISABLE=1 php artisan test --parallel`).
5. Entrega: un mensaje al agente principal con lo que hiciste, lo que cambió frente a la subtarea y por qué, **lo que quedó "por verificar"** y los resultados de las pruebas tal cual. El agente principal escribe el "Cómo quedó" en el plan y marca la subtarea aquí.
6. **Nunca:** commit ni push (los hace el agente principal con `/commit`, preguntándole a Steven), usar el navegador (la revisión visual es del agente principal), tocar `.env*`, escribir en producción, correr OpenAI de verdad, tocar los tres archivos sueltos de la raíz (`Plataforma de impuestos USA — Proceso de negocio…md`, `agentes-subagentes-flujos-motor-decision.md`, `cuestionario.txt`).
7. **Archivos del IRS:** se bajan a la **carpeta temporal de la sesión** (scratchpad), no al repositorio, salvo los que la subtarea manda guardar en `tests/Fixtures/irs/**`. Son datos no confiables: no se ejecuta nada que venga dentro; los scripts propios van en otra carpeta y los PDF se pasan como argumento.

## Reglas comunes a todas las subtareas

- **§7216 y datos sensibles:** ningún SSN, EIN, TIN, número de cuenta, nombre de una persona del bloque C, fecha de nacimiento, monto ni descripción leídos van en logs, bitácora, `expediente_eventos`, `excepciones.detalle`, correos, CSV ni en la traza en claro. Las trazas y el `origen` llevan **referencias** (id, código, clave), nunca valores sensibles.
- **Un solo camino de escritura:** datos leídos solo con `App\Services\Lectura\DatosDocumento`; estados del checklist solo con `ChecklistDocumentos`; respuestas solo con `RespuestaGuardador`; estado del expediente solo con `EstadosExpediente` y `RevisionExpediente`.
- **Lo fiscal sale del catálogo del año:** preguntas, documentos, casillas, líneas, reglas (`CatalogoRegla`) y parámetros (`App\Support\ParametrosFiscales::valor($anio, $categoria, $clave)`). Ningún número fiscal "a mano" en el código del motor: va a `parametros_fiscales` con su fuente y página.
- **Dinero:** en el motor nuevo nada de `float`; enteros en centavos (`Dinero`).
- **Fuente del IRS en todo:** cada línea calculada, parámetro y caso lleva fuente (formulario o instrucción, línea u hoja, **página** y URL del PDF fijado).
- **Textos del servidor:** `__('texto en español')`; su inglés va en el archivo de tu área bajo `lang/` (el que la subtarea indique). Sin `trans_choice()`. **No se edita `lang/en.json` ni `es.json`** salvo que la subtarea lo diga.
- **Interfaz (guía 4.2):** skeleton para lo diferido; diseño primero a 375 px; filas tocables de 44 px; sin scroll horizontal; solo `components/ui` e íconos de `lucide-react`; `aria-label` en botones de ícono, `Label` en cada campo, errores con `aria-describedby`; estados con **texto e ícono**; vacío, error y éxito; hover, foco, activo y deshabilitado (con el motivo al lado); spinner y sin doble envío; textos en es y en.
- **React:** estado derivado en el render; formularios que se reinician con `key`; ningún componente definido dentro de otro; `Map`/`Set` para buscar; importar cada componente de `components/ui` por su archivo.
- **Pruebas:** clases Pest como las de `tests/Feature/CatalogoFiscal/` y `tests/Feature/Lectura/` (`RefreshDatabase`, `$this->seed(CatalogoFiscalSeeder::class)` cuando haga falta). Nombres en español que digan el comportamiento. OpenAI nunca se llama.

## Estado y olas

| Ola | # | Subtarea | Depende de | Estado |
| --- | --- | --- | --- | --- |
| 1 | 6.0 | Catálogo: destinos corregidos, líneas con fuente y validación | — | ⏳ |
| 1 | 6.1a | Parámetros 2025 completos y tablas como datos | — | ⏳ |
| 1 | 6.2 | Oráculo del IRS: tablas extraídas del PDF y formato de casos | — | ⏳ |
| 1 | 6.4a | Núcleo del motor (`Dinero`, `Linea`, `Traza`, `Declaracion`, DTO de entradas, interfaz) | — | ⏳ |
| 1 | 6.3a | Casillas de los documentos de montos (campos fijos) y formatos `xlsx`/`csv` | — | ⏳ |
| 2 | 6.3b · 6.4c · 6.4e · 6.4g · 6.1b · casos de 6.9 | Se detallan al cerrar la ola 1 | 6.0, 6.1a, 6.2, 6.4a, 6.3a | ⏳ |
| 3 | 6.4b · 6.4d · 6.4f · 6.4h · 6.5 · 6.3c | Se detallan al cerrar la ola 2 | ola 2 | ⏳ |
| 4 | 6.4i · 6.7a · 6.3d | Se detallan al cerrar la ola 3 | ola 3 | ⏳ |
| 5 | 6.6 · 6.7b · 6.8a · 6.8b-n · 6.3e | Se detallan al cerrar la ola 4 | ola 4 | ⏳ |
| 6 | 6.8c · 6.9 | Se detallan al cerrar la ola 5 | ola 5 | ⏳ |
| 7 | Cierre (agente principal) + 6.10 | — | todas | ⏳ |

**Por qué la ola 1 no se pisa:** 6.0 toca `formularios.php`, el verificador, el modelo de líneas y la pantalla de Formularios; 6.1a toca el seeder y los archivos de parámetros; 6.2 solo `tests/Fixtures/irs/**`, `tests/Support/CasosIrs.php` y `scripts/irs/**`; 6.4a solo `app/Services/Calculo/Nucleo/**` y sus pruebas; 6.3a solo `documentos.php` y los fixtures de lectura. Los dueños únicos de la fase están en el plan ("Olas para Sonnet 5.5").

## Contratos compartidos de la ola 1

Los usan las olas siguientes; quien los crea los respeta al pie de la letra.

**Namespace del motor** (6.4a): `App\Services\Calculo\Nucleo\` para el núcleo; las calculadoras irán en `App\Services\Calculo\Formularios\` (olas 2 y 3).

```php
final readonly class Dinero            // centavos enteros; sin float
{
    public static function deCentavos(int $c): self;
    public static function deDolares(int|string $d): self;   // "1234.56" → 123456; nunca float
    public function centavos(): int;
    public function aDolares(): self;                         // redondeo a dólares enteros (regla del IRS; ver subtarea)
    public function mas(self $o): self; public function menos(self $o): self;
    public function por(string $factor): self;                // factor decimal en texto: "0.9235"
    public function max(self $o): self; public function min(self $o): self;
    public function esNegativo(): bool;
    public function texto(): string;                          // "1.234,56" para es; la presentación final la hace la vista
}

enum EstadoLinea: string { case Calculada='calculada'; case NoAplica='no_aplica'; case FaltaDato='falta_dato'; case NoSoportada='no_soportada'; }
enum OrigenTraza: string  { case Leido='leido'; case Corregido='corregido'; case SinDocumento='sin_documento'; case Respuesta='respuesta'; case Decision='decision'; case Linea='linea'; case Parametro='parametro'; }
enum TipoPaso: string     { case Entrada='entrada'; case Parametro='parametro'; case Operacion='operacion'; case Resultado='resultado'; }

final readonly class PasoTraza   { public TipoPaso $tipo; public string $texto; public Dinero|bool|string|null $valor; public ?OrigenTraza $origen; public ?string $referencia; }
final readonly class Traza       { /** @param list<PasoTraza> $pasos */ public array $pasos; }
final readonly class Linea       { public string $formulario; public string $linea; public Dinero|bool|string|null $valor; public EstadoLinea $estado; public ?string $motivo; public Traza $traza; public bool $usaSinValidar; /** @var list<string> */ public array $datosSinValidar; }

final class Declaracion {                  // mutable solo para que las calculadoras escriban sus líneas
    public function __construct(public readonly int $taxYear, public readonly string $versionReglas);
    public function poner(Linea $l): void;
    public function linea(string $formulario, string $linea): ?Linea;
    /** @return list<string> */ public function formularios(): array;
    /** @return list<Linea> */ public function lineas(?string $formulario = null): array;
    public function estadoGeneral(): string;   // 'completa' | 'parcial' | 'incompleta' (ver subtarea 6.4a)
}

interface Calculadora { public function formulario(): string; public function calcular(EntradasDeclaracion $e, Declaracion $d): void; }
```

`EntradasDeclaracion` (DTO `readonly`) se define en 6.4a **solo con la forma** (propiedades tipadas y constructor) y sin armador: lo llena `ArmadorEntradas` en la ola 4. Sus propiedades están en la subtarea 6.4a.

**Tablas como datos** (6.1a): `App\Support\TablaFiscal` (objeto de valor) con `buscar(Dinero|int $x): array` sobre filas `{hasta, ...}`; los parámetros de tipo tabla se guardan en `parametros_fiscales` como JSON y se leen con `ParametrosFiscales::tabla($anio, $categoria, $clave): TablaFiscal`.

**Formato de un caso del IRS** (6.2): archivo PHP que devuelve un arreglo; lo leen las olas siguientes.

```php
return [
    'codigo' => 'maria-hoh', 'anio' => 2025, 'descripcion' => '…',
    'entradas' => [ /* hechos, no conclusiones */ ],
    'pasos' => [ ['formulario' => '1040', 'linea' => '11a', 'texto' => 'Hoja…', 'valor' => '…', 'fuente' => ['doc' => 'f1040', 'pagina' => 'p. 1', 'url' => '…']] ],
    'lineas' => [ '1040' => ['11a' => '45000', '16' => '5920'] ],   // valores esperados en dólares, texto
    'cubre' => ['1040:11a', '1040:16'],
    'contraste' => null,   // ['herramienta' => 'EITC Assistant', 'fecha' => '2026-11-09', 'resultado' => '…']
];
```

---

# Ola 1

## 6.0 — Catálogo: destinos corregidos, líneas con fuente y validación

**Plan:** secciones "El catálogo de líneas tiene errores de destino" y "6.0". **Skills:** `eloquent-casts`, `arch-service-classes`, `controller-form-requests`. **Modelo:** Sonnet 5.5.

**Contexto mínimo (lee):** `database/data/catalogo-fiscal/2025/formularios.php` y `README.md`, `app/Models/CatalogoFiscal/` (modelo de líneas y el trait/cast de fuente IRS que ya usan formularios, preguntas, documentos y reglas), `app/Services/CatalogoFiscal/CatalogoFiscalImportador.php`, `CatalogoFiscalVerificador.php`, `CatalogoFiscalEditor.php`, `resources/js/pages/catalogo-fiscal/formularios.tsx`, `tests/Feature/CatalogoFiscal/` (`CatalogoRealTest`, `EdicionTest`, `PantallaTest`), `docs/mejora-plataforma/verificacion-irs-destinos.md` y el 1040 de 2025 (`https://www.irs.gov/pub/irs-prior/f1040--2025.pdf`, página 2).

**Qué hacer:**
1. **Migración** `agrega_fuente_a_catalogo_lineas`: `fuente_texto`, `fuente_url`, `fuente_pagina` (texto corto: "p. 2", "Hoja de la línea 16"), `validado_por` (FK nullable a `users`), `validado_en` (timestamp nullable). Hacer que el modelo de línea use el mismo mecanismo de fuente que los otros modelos del catálogo (`TieneFuenteIrs`). `campo_pdf` ya existe: no se toca.
2. **`2025/formularios.php`** (tú eres el **dueño único** en toda la fase):
   - **Form 1040:** corregir la línea `12` → `12e`; agregar 11b, 12d (casillas 65+/ciego, origen `respuesta`), 13a, 13b (`sch-1a:38`), 14, 15, 16, 17 (`sch-2:3`), 18, 21, 22, 24, 25d, 32, 33, 34; la 23 = `sch-2:21`; la 31 = `sch-3:15`. **Verifica cada una contra el 1040 de 2025** antes de escribirla; el plan lista lo que el agente de diseño encontró, pero lo que dice el PDF manda.
   - **Schedule C, una línea por categoría** (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16a, 16b, 17, 18, 19, 20a, 20b, 21, 22, 23, 24a, 24b, 25, 26, 27a, 27b, 28, 29, 30, 31), con el origen `casilla` hacia las categorías de la lectura de registros (ej. `registros-ingresos-gastos:gasto-18`; el nombre exacto de la casilla lo fija 6.3a: usa `gasto-<línea>` e `ingreso-sin-formulario` como convención y anótalo en tu entrega). Verifica los números contra `https://www.irs.gov/pub/irs-prior/f1040sc--2025.pdf`; la subdivisión de la 16 y lo que va dentro de cada categoría quedan **por verificar** con las instrucciones del Schedule C de 2025.
   - **Schedule 2:** 3 y 21. **Schedule 3:** 8, 10, 15, y las que el plan cite para 20–24 si el PDF las trae. **Schedule SE:** 3, 4c, 6, 8a, 10, 11 (más las del plan en 6.4b). **Schedule 8812:** Parte I y II que se calculan (números **del 8812 de 2025**: `https://www.irs.gov/pub/irs-prior/f1040s8--2025.pdf`, no el de `irs-pdf/` que ya es 2026). **Form 2441:** 8 (porcentaje) y 9. **EIC:** 1040 27a. **Schedule 1-A:** ficha `completa` con las líneas 1, 3, 4c, 13, 14c, 21, 23, 30, 37, 38 (revisa la lista contra el PDF de 2025). **Form 8995** (decisión h): formulario con ficha `completa`, activado por el Schedule C con ganancia, sus líneas (números del 8995 de 2025) y la última → 1040 línea 13a; sin preguntas nuevas.
   - Cada línea de origen `calculo` o `linea` lleva `fuente_texto`, `fuente_url` y `fuente_pagina`.
3. **`2026/ajustes.php`: no lo toques** (es de 6.1b). Si el 2026 necesita un cambio de línea, anótalo en tu entrega.
4. **`CatalogoFiscalImportador`:** pasar las columnas nuevas.
5. **`CatalogoFiscalVerificador`:** nuevos chequeos "toda línea `calculo` o `linea` tiene fuente" y "todo `origen_clave` apunta a una línea o casilla que existe en el mismo año".
6. **Pantalla Formularios del catálogo** (`catalogo-fiscal/formularios.tsx` y su controlador/Editor): mostrar la fuente de la línea (enlace y página) y el botón **"Marcar validada contra el IRS"** (mismo patrón y permisos de F1.5, `FormRequest`), **deshabilitado con el motivo** ("Falta un caso de prueba que cubra esta línea") si la línea no aparece en `database/data/catalogo-fiscal/<anio>/cobertura-casos.php` (lo genera 6.2; **si el archivo no existe todavía, lee una lista vacía**). Mostrar "validada por X el …" o "sin validar". Textos en es y en en el archivo de locales del catálogo que ya existe.
7. **`HojaTaxwise`:** no cambia de lógica, pero el destino 12 → 12e y las líneas nuevas cambian textos y totales: ajusta `HojaTaxwiseTest` (y los otros tests que citen la línea 12 o 23/31) solo en lo necesario y dilo en tu entrega.
8. Actualizar `database/data/catalogo-fiscal/README.md` con las columnas nuevas.

**Pruebas:** `catalogo-fiscal:verificar` limpio en 2025 y 2026; cada línea calculada tiene fuente; 23 → `sch-2:21`; 31 → `sch-3:15`; Schedule C con las líneas 8–27b; `CatalogoRealTest` con los conteos nuevos **a propósito** (explica cada cambio de conteo); el botón de validar exige caso (con y sin `cobertura-casos.php`); permisos como F1.5.

**Archivos permitidos:** la migración nueva; el modelo de líneas del catálogo; `CatalogoFiscalImportador`, `CatalogoFiscalVerificador`, `CatalogoFiscalEditor` (solo lo de validar); `2025/formularios.php`; `database/data/catalogo-fiscal/README.md`; `resources/js/pages/catalogo-fiscal/formularios.tsx` y sus componentes/locales/tipos del catálogo; el controlador y la ruta de validar de la línea (junto a los del catálogo); `tests/Feature/CatalogoFiscal/*`, `HojaTaxwiseTest` (solo lo mínimo).
**No tocar:** `2026/ajustes.php`, `documentos.php`, `parametros*.php`, `tests/Fixtures/irs/**`, `app/Services/Calculo/**`.

**Criterio de terminado:** pruebas verdes de lo anterior, `pint` y `types:check` sin errores propios; en tu entrega: lista de líneas agregadas/corregidas, lo que quedó "por verificar", y por qué cambió cada conteo.

---

## 6.1a — Parámetros 2025 completos y tablas como datos

**Plan:** sección "6.1 Parámetros por año y tablas como datos". **Skills:** `arch-value-objects`, `eloquent-casts`. **Modelo:** Sonnet 5.5.

**Contexto mínimo:** `app/Models/ParametroFiscal.php`, `app/Support/ParametrosFiscales.php`, `database/seeders/ParametrosFiscalesSeeder.php`, `database/data/catalogo-fiscal/2025/parametros.php` y `parametros-metadatos.php`, la migración espejo de F1.2 (busca la que carga los parámetros en producción), `tests/Feature/CatalogoFiscal/ParametrosTest.php`, el código que hoy usa las constantes (`app/Services/Reglas/SelfEmploymentTaxCalculator.php`, `AdditionalMedicareTaxCalculator.php`, `NiitCalculator.php`, `QbiCalculator.php`).

**Qué hacer** (cada valor con **fuente y página** en el metadato; nada sin fuente; si no puedes verificar, lo dejas `valor = null` pendiente con el motivo y lo reportas):
1. **Constantes del código → parámetros de 2025:** Schedule SE (0,124, 0,029, 0,9235, umbral de USD 400 sobre la línea 4c), Form 8959 (0,009 y umbral MFS), QBI (0,20), `tax_brackets.mfs` (Rev. Proc. 2024-40, **por verificar**), deducción estándar MFS (Form 1040 2025: "Single or Married filing separately, $15,750"), adicionales de 65+ y ciego (instrucciones de la línea 12, **por verificar**).
2. **Tablas** como parámetro de tipo tabla (JSON): `credito_cuidado_dependientes.tabla` (lista `{hasta, decimal}` del Form 2441 de 2025; ya verificada el 9 nov.: 35 % hasta USD 15.000, baja 1 punto por cada USD 2.000, 20 % desde USD 43.000 — **confírmalo en el PDF**).
3. **EIC** por número de hijos (0, 1, 2, 3+): tasa de entrada, monto máximo del crédito, inicio y fin de la reducción para MFJ y para el resto, tope de ingreso de inversión. Fuente: Rev. Proc. 2024-40 y las instrucciones del 1040 2025 (línea 27) — **por verificar cada cifra**.
4. **8812** (ACTC: tope reembolsable por hijo y umbral de ingreso del trabajo, **del 8812 de 2025**), **Schedule 1-A** (topes y umbrales: USD 25.000 propinas; 12.500/25.000 horas extra; 10.000 intereses del vehículo; 6.000 mayores; umbrales de reducción 150.000/300.000, 100.000/200.000 y 75.000/150.000 — según el Schedule 1-A de 2025; **confirma con el PDF** y el sentido del redondeo en las instrucciones), **8962** (línea de pobreza, tabla "applicable figure", topes de devolución — `i8962.pdf` de 2025, **por verificar**).
5. **Nuevos por la lectura de montos (por verificar con su fuente):** tarifa estándar de millaje del negocio de 2025 (aviso del IRS del año), porcentaje deducible de comidas (instrucciones del Schedule C, línea 24b), método simplificado de oficina en casa (tarifa y tope, línea 30), tope de gastos de educador (instrucciones del Schedule 1, línea 11).
6. **`App\Support\TablaFiscal`** (objeto de valor, `arch-value-objects`) con `buscar()` sobre filas `{hasta, ...}`, y `ParametrosFiscales::tabla()` que la devuelve; respeta la caché y el `valorRequerido()` existentes (lanza `RuntimeException` si está pendiente).
7. **Metadatos** (`parametros-metadatos.php`: grupo, tipo, fuente, página, descripción) y **migración espejo** para producción (como F1.2), sin pisar parámetros ya validados.
8. **La Tax Table no es un parámetro** (se calcula con el método del IRS en 6.4e): no la agregues.

**Pruebas (`tests/Feature/CatalogoFiscal/ParametrosTest.php` ampliado y `tests/Unit/Support/TablaFiscalTest.php`):** cada parámetro de 2025 que usará el motor existe, no está pendiente y tiene fuente y página (los que dejaste pendientes se listan en la prueba como "por verificar" explícitos, no ocultos); `TablaFiscal::buscar()` en los bordes; la migración espejo es idempotente; cambiar un parámetro de 2026 no toca 2025.

**Archivos permitidos:** `ParametrosFiscalesSeeder`, `2025/parametros.php`, `2025/parametros-metadatos.php`, la migración espejo nueva, `app/Support/ParametrosFiscales.php`, `app/Support/TablaFiscal.php`, `app/Models/ParametroFiscal.php` (solo si el tipo tabla lo exige), sus pruebas.
**No tocar:** `2026/ajustes.php` (6.1b), `formularios.php`, `documentos.php`, `app/Services/Calculo/**`, las calculadoras viejas (se borran en 6.10).

**Criterio de terminado:** pruebas verdes, `pint` limpio; entrega con la tabla de cada parámetro (clave, valor, fuente y página) y la lista de los "por verificar" que quedaron pendientes y por qué.

---

## 6.2 — Oráculo del IRS: tablas extraídas del PDF y formato de casos

**Plan:** sección "Estrategia de validación contra el IRS" (capas 1 a 5) y "6.2". **Skills:** ninguna de frontend; PHP simple. **Modelo:** Sonnet 5.5. **Esta subtarea NO lee `app/Services/Calculo/` ni ninguna calculadora**: el valor esperado no puede salir del código que se va a probar.

**Contexto mínimo:** el plan (estrategia de validación completa), `docs/mejora-plataforma/verificacion-irs-destinos.md`. PDF de 2025 (url `https://www.irs.gov/pub/irs-prior/<form>--2025.pdf`, o `irs-pdf/` donde ese sirva 2025; **verifica el año en el PDF**): Pub. 1040 / instrucciones del 1040 (`i1040gi`), `f1040`, `f1040s1`, `f1040s1a`, `f1040s2`, `f1040s3`, `f1040sc`, `f1040sse`, `f1040s8`, `f2441`, `f1040sei`, `f8962`, `f8995`, y las instrucciones de 8812, 8962, 2441, Schedule 1-A y Schedule C.

**Qué hacer:**
1. **Descarga a un directorio nuevo y vacío del scratchpad**, saca el SHA-256 y copia a `tests/Fixtures/irs/2025/pdf/` solo los PDF que la plan manda fijar (si pesan demasiado, pregunta antes). `tests/Fixtures/irs/2025/fuentes.json`: por cada PDF, URL, fecha de descarga, SHA-256, páginas citadas.
2. **`scripts/irs/extraer-tablas.php`** (fuera de `app/`, se corre a mano; usa `pdftotext -layout`; PDF como argumento): extrae a CSV y guarda en `tests/Fixtures/irs/2025/`: **`tax-table.csv`** (Pub. 1040, ~2.000 filas: `desde, hasta, soltero, mfj, mfs, hoh`; verificado el 9 nov. que la fila 50.000–50.050 da 5.920 / 5.526 / 5.920 / 5.663), **`eic-table.csv`** (por estado civil y número de hijos), **`f2441-tabla.csv`**, y los CSV de 8962 que el PDF traiga (`f8962-*.csv`, **por verificar la página** en `i8962.pdf`).
3. **Revisión a mano una vez:** compara **diez filas al azar de cada CSV** contra el PDF y anótalas en `fuentes.json` (`revision`: fila, página, resultado). Si el extractor falla en algún formato, corrige el script y repórtalo.
4. **`tests/Fixtures/irs/casos/README.md`:** formato de un caso (el de "Contratos compartidos"), la **regla de doble lectura** (quien escribe los casos no escribe la calculadora ni la lee; transcribe a mano, paso por paso, la hoja del IRS en `pasos`; si calculadora y caso no coinciden, el agente principal revisa contra el PDF antes de tocar cualquiera de los dos) y cómo citar (`fuente`: documento, línea u hoja, **página**, URL del PDF fijado).
5. **`tests/Support/CasosIrs.php`:** carga los casos de `tests/Fixtures/irs/casos/<anio>/*.php` y da, por año, `cubiertas(int $anio): array` (`'1040:11a'`…) y `casos(int $anio): array`. Y el comando **`calculo:cobertura {anio}`** (en `app/Console/Commands/`, único archivo de `app/` que tocas) que reescribe `database/data/catalogo-fiscal/<anio>/cobertura-casos.php` con las líneas cubiertas, para que producción no lea `tests/`.
6. **Primeros casos (escritos por ti, a mano, con cita):** los dos **ejemplos del propio IRS** ya verificados en la Pub. 1040 de 2025 (Tax Table, MFJ con ingreso gravable USD 25.300 → USD 2.562, p. 2; EIC Table, soltero con un hijo con SSN válido, monto de la hoja USD 2.455 → USD 842) y, **por verificar**, cualquier otro ejemplo resuelto que traigan las instrucciones de 8812, 2441, 8962, Schedule SE o 1-A (anota cuáles traen ejemplo y cuáles no).
7. **Pruebas que recorren las tablas completas** (`tests/Feature/Calculo/TablasIrsTest.php`): para cada fila de `tax-table.csv`, `eic-table.csv` y `f2441-tabla.csv`, llamar a la calculadora que **aún no existe** → escríbelas ya, **marcadas `->todo()` / saltadas con el motivo** "se activan en 6.4 cuando exista la calculadora"; deja la interfaz que esperan (`TablaImpuesto::paraFila(...)`, etc.) **anotada en un comentario**, sin crear la clase.

**Pruebas:** `tests/Feature/Calculo/CasosIrsTest.php`: estructura de cada caso (trae `fuente` con página y URL, `lineas`, `cubre`), cada `fuente.url` existe en `fuentes.json`; el SHA-256 de cada PDF fijado; los CSV no tienen filas vacías, la tabla es contigua y creciente, y las filas que verificaste coinciden.

**Archivos permitidos:** `tests/Fixtures/irs/**`, `tests/Support/CasosIrs.php`, `scripts/irs/**`, `tests/Feature/Calculo/CasosIrsTest.php`, `tests/Feature/Calculo/TablasIrsTest.php`, `app/Console/Commands/CalculoCoberturaCommand.php`.
**No tocar:** `app/Services/Calculo/**`, las calculadoras viejas, `database/data/catalogo-fiscal/**` (solo el comando genera `cobertura-casos.php`; **no lo generes tú ahora**: los casos aún no cubren líneas de cálculo).

**Criterio de terminado:** CSV revisados, `CasosIrsTest` verde, `TablasIrsTest` saltado con motivo; entrega con la lista de PDF fijados (hash), qué tablas se extrajeron y cuántas filas, los diez controles por tabla y los ejemplos del IRS que sí o no se encontraron.

---

## 6.4a — Núcleo del motor

**Plan:** sección "6.4a Núcleo". **Skills:** `arch-value-objects`, `arch-dto-pattern`, `arch-service-classes`, `arch-feature-folders`. **Modelo:** Sonnet 5.5.

**Contexto mínimo:** el plan (6.4a y la tabla de orígenes de la traza), "Contratos compartidos de la ola 1" arriba, `app/Services/Lectura/DatosDocumento.php` (cómo se ve un dato vigente) y `app/Models/Expediente/` (solo para el DTO). **No leas ni modifiques las calculadoras viejas.**

**Qué hacer** (en `app/Services/Calculo/Nucleo/`, namespace `App\Services\Calculo\Nucleo`):
1. **`Dinero`** y los enums/DTO del contrato de arriba, **exactamente** con esa forma. Redondeo: `aDolares()` según las instrucciones del 1040 de 2025 ("Rounding Off to Whole Dollars"); **verifica el texto y si es opcional** en `i1040gi` 2025 y deja la regla que encuentres con su página en un comentario y en tu entrega. Por defecto, hasta confirmar: 50 centavos o más sube, menos de 50 baja. Sin `float` en ninguna parte (arquitectura probada).
2. **`Traza`**/`PasoTraza` con constructores nombrados (`Traza::vacia()`, `->conEntrada(...)`, `->conParametro(...)`, `->conOperacion(...)`, `->conResultado(...)`, cada uno devuelve una **nueva** traza). `referencia` es una cadena de referencia (id, código, clave), **nunca un valor sensible**; una prueba lo vigila.
3. **`Linea`** con constructores `calculada()`, `faltaDato(motivo)`, `noSoportada(motivo)`, `noAplica()`.
4. **`Declaracion`**: guarda líneas por `formulario` y `linea`; `estadoGeneral()` = `completa` si todas las líneas están `calculada` o `no_aplica`; `parcial` si hay alguna `no_soportada` (y el resto calculada o `falta_dato` resuelto); `incompleta` si hay alguna `falta_dato`. Escríbelo así y pruébalo.
5. **`EntradasDeclaracion`** (DTO `readonly`) **solo con la forma**, para que 6.4b–6.4h compilen: `int $taxYear`, `?EstadoCivilIrs $estadoCivil`, `list<PersonaEntrada> $personas` (id, rol —`contribuyente`, `conyuge`, `dependiente`—, `ClasificacionPersona`, `?int $edadAlFinDeAnio`, `bool $ssnValido`, `bool $noValidoParaTrabajar`), `array<string, DatoEntrada> $datos` (clave `documento:casilla` o `categoria:linea`), `array<string, Dinero> $sumasRegistros` (clave = línea del Schedule C), `array<string, RespuestaEntrada> $respuestas` (código de pregunta), y `DatoEntrada` (`Dinero|string|null $valor`, `OrigenTraza $origen`, `?string $referencia`, `bool $validado`, `bool $conExcepcion`). Sin armador (llega en 6.4i).
6. **Interfaz `Calculadora`**. Sin implementaciones.
7. **`LectorDeParametros`** fino (opcional, si simplifica): una clase que envuelve `ParametrosFiscales::valorRequerido()`, **captura** la `RuntimeException` de "pendiente" y la convierte en un resultado `faltaParametro(clave)` que la calculadora usa para marcar `falta_dato` con "No se puede calcular: falta el parámetro X de <año> (Catálogo → Parámetros)". Si no lo necesitas, no lo hagas.

**Pruebas (`tests/Unit/Calculo/Nucleo/*`):** `Dinero`: construcción desde texto con centavos, suma, resta, `por("0.9235")` sin error de coma flotante, redondeo a dólares en los bordes (49 / 50 centavos), negativos; **prueba de arquitectura** (`arch()` de Pest): ningún archivo de `app/Services/Calculo` usa `float`, `floatval`, `round(` ni `number_format(`; `Traza` es inmutable (cada método devuelve otra); `Declaracion`: `linea()`, `lineas()`, `formularios()` y `estadoGeneral()` en sus tres casos; `Linea::faltaDato` no puede tener `valor`; la referencia de la traza rechaza (o prueba que no contiene) un valor con formato de SSN (`/\d{3}-?\d{2}-?\d{4}/`).

**Archivos permitidos:** `app/Services/Calculo/Nucleo/**`, `tests/Unit/Calculo/Nucleo/**`.
**No tocar:** nada más (no `Formularios/`, no el armador, no migraciones, no catálogo).

**Criterio de terminado:** pruebas verdes, `pint` limpio; entrega con las propiedades finales de cada clase (tal cual para que 6.4c/6.4e/6.4g las usen en la ola 2) y la regla de redondeo con su página.

---

## 6.3a — Casillas de los documentos de montos (campos fijos) y formatos `xlsx`/`csv`

**Plan:** sección "6.3a Documentos de campos fijos" y la decisión (l). **Skills:** `eloquent-casts` (si hay migración; probablemente no). **Modelo:** Sonnet 5.5.

**Contexto mínimo:** `database/data/catalogo-fiscal/2025/documentos.php` y cómo declara un documento con casillas (mira `w2`, `1099-nec`, `tarjeta-ssn`), `app/Services/CatalogoFiscal/CatalogoFiscalImportador.php` (qué columnas y claves entiende), `app/Services/Lectura/EsquemaLectura.php` y `CalculadoraConfianza.php` (cómo se lee una casilla y qué es "clave"), `tests/Feature/CatalogoFiscal/CatalogoRealTest.php`, `tests/Feature/Lectura/` (el patrón de lectura simulada), la plantilla de F3.7 en `public/plantillas/` y los dos recibos de útiles de Javier (`~/Downloads/documentos-prueba-javier/09-recibo-util-1-papeleria-maestro.png` y `10-recibo-util-2-casa-util-escolar.jpg`; **solo para mirar su forma, no se copian al repositorio**: contienen datos que no deben entrar al código).

**Qué hacer:**
1. En **`2025/documentos.php`** (tú eres el **dueño único**), agregar las casillas (código · tipo · `clave` · `sensible` · destino) de los cinco documentos de campos fijos del plan:
   - `recibo-cuidado`: `proveedor-nombre`·nombre·sensible; `proveedor-direccion`·texto·sensible; `proveedor-tin`·tin·sensible; `monto-pagado`·monto·clave; `periodo-desde`, `periodo-hasta`·fecha; `persona-cuidada`·nombre·sensible → Form 2441 Partes I y II.
   - `comprobantes-pagos`: `fecha-pago`·fecha·clave; `monto`·monto·clave; `tipo-pago`·código (`estimado`, `extension`, `otro`); `anio-aplicado`·año → 1040 línea 26 y Schedule 3 línea 10.
   - `recibos-utiles-educador`: `comercio`·nombre; `fecha`·fecha; `total`·monto·clave; `descripcion`·texto → Schedule 1 línea 11.
   - `constancia-horas-propinas`: `horas-extra-calificadas`·monto; `propinas-calificadas`·monto; `ocupacion`·texto → Schedule 1-A Partes II y III.
   - `estado-prestamo-vehiculo`: `intereses-pagados`·monto·clave; `vin`·texto·sensible (**por verificar** si lo es); `fecha-prestamo`·fecha; `vehiculo-nuevo`·si_no → Schedule 1-A Parte IV.
   Usa **los tipos de dato y el formato de casillas que ya existen** (no inventes tipos nuevos; si falta uno, para y pregunta). Los **formatos aceptados** de cada uno, hoy "por verificar", los confirmas tú en el archivo y los reportas.
2. **`registros-ingresos-gastos`:** marcar `lectura = renglones` (si el importador ya conoce esa clave; si **no**, agrega la clave al documento como dato y al importador **solo si es un cambio mínimo**, de lo contrario para y pregunta) y agregar sus casillas fijas: `clase-detectada`, `periodo-desde`, `periodo-hasta`, `total-depositos`, `total-retiros`, `saldo-inicial`, `saldo-final`, `millas-negocio`, `pies-cuadrados-casa`, `pies-cuadrados-oficina` (montos y fechas, marcando sensibles los que correspondan). Y **aceptar `xlsx` y `csv`** además de pdf, jpg, png y heic: **solo** en este documento (decisión l); la validación de subida del portal debe leerlos de ese archivo, no tocar el controlador del portal (si hace falta tocarlo, para y pregunta).
3. **Fixtures sintéticos** en `tests/Fixtures/lectura/montos/` (sin datos reales): recibo de guardería, comprobante 1040-ES, comprobante Direct Pay, recibo suelto y la **plantilla de F3.7 llena** (una copia de la de `public/plantillas/` con datos inventados, en `xlsx` y en PDF) y una **hoja propia en `csv`** con encabezados en inglés y una columna desconocida. Genéralos con un script (en el scratchpad, fuera del repositorio) y deja solo los archivos. Los extractos bancarios son del tramo 2 (6.3c): **no los hagas ahora**.
4. **2026:** no edites `2026/ajustes.php`; si 2026 cambia algo en estas casillas, anótalo para 6.1b.

**Pruebas:** `CatalogoRealTest` con los conteos nuevos **a propósito** (explica cada uno); `catalogo-fiscal:verificar` limpio en 2025; una prueba por documento de que sus casillas existen con su `tipo_dato`, `clave` y `sensible` esperados; el importador carga `lectura = renglones`; el portal acepta `xlsx` y `csv` **solo** en `registros-ingresos-gastos` y rechaza `xlsx` en el resto; una lectura simulada (`Http::fake`, como en `tests/Feature/Lectura/`) del recibo de guardería y del de útiles con casillas leídas, el TIN cifrado y enmascarado en props y `proveedor-tin` vacío que más adelante abrirá R-12.

**Archivos permitidos:** `database/data/catalogo-fiscal/2025/documentos.php`, `tests/Fixtures/lectura/montos/**`, `tests/Feature/CatalogoFiscal/CatalogoRealTest.php`, `tests/Feature/Lectura/LecturaMontosTest.php` (nuevo, solo la parte de campos fijos), y el importador **solo si el cambio de `lectura = renglones` lo exige**.
**No tocar:** `formularios.php`, `reglas.php`, `parametros*.php`, `2026/ajustes.php`, `EsquemaLectura`, `LectorOpenAi`, `DatosDocumento`, el controlador del portal, `app/Services/Calculo/**`.

**Criterio de terminado:** pruebas verdes, `verificar` limpio, `pint` limpio; entrega con la lista de casillas por documento, los formatos confirmados, si el VIN es sensible y qué decidiste con `lectura = renglones`.

---

## Qué hace el agente principal al cerrar la ola 1

1. Revisar cada entrega contra el plan; **verificar al azar** tres cifras de parámetros y cinco filas de cada tabla contra el PDF del IRS.
2. `php artisan wayfinder:generate --with-form`, `vendor/bin/pint --test`, `npm run types:check`, `npx eslint` sobre lo tocado, y `PAO_DISABLE=1 php artisan test` solo de los archivos nuevos y los del catálogo y la lectura (la suite completa va al cierre de la fase).
3. Probar la pantalla Formularios del catálogo en el navegador (375 px y escritorio, es y en, botón de validar con y sin caso).
4. Escribir el "Cómo quedó" en el plan y marcar ✅ aquí; actualizar `tareas-desarrollo.md` y Wiplitask (empresa **Cupperlab**); preguntar a Steven antes de cada commit y usar `/commit`.
5. Detallar la **ola 2** en este archivo con los nombres reales de lo que dejó la ola 1.
