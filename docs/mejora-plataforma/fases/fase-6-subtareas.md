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
| 1 | 6.0 | Catálogo: destinos corregidos, líneas con fuente y validación | — | ✅ |
| 1 | 6.1a | Parámetros 2025 completos y tablas como datos | — | ✅ |
| 1 | 6.2 | Oráculo del IRS: tablas extraídas del PDF y formato de casos | — | ✅ |
| 1 | 6.4a | Núcleo del motor (`Dinero`, `Linea`, `Traza`, `Declaracion`, DTO de entradas, interfaz) | — | ✅ |
| 1 | 6.3a | Casillas de los documentos de montos (campos fijos) y formatos `xlsx`/`csv` | — | ✅ |
| 2 | 6.3b | Hojas `xlsx`/`csv` sin IA, modelo de renglones, `SumasRegistros` y R-14 básica | ola 1 | ✅ |
| 2 | 6.4c | Calculadoras `Schedule1` e `Ingresos1040` | ola 1 | ✅ |
| 2 | 6.4e | Calculadora `ImpuestoYSchedule2` (Tax Table, hoja de cálculo del impuesto, Schedule 2, líneas 21 a 24) | ola 1 | ✅ |
| 2 | 6.4g | Calculadora `Eitc` | ola 1 | ✅ |
| 2 | 6.1b | Parámetros de 2026 | ola 1 | ✅ |
| 2 | 6.9a | Primer lote de casos del IRS, escrito sin ver el código | ola 1 | ✅ |
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

## Cómo quedó la ola 1 (9 nov. 2026, commits `f07b3ff` a `49e6ab2`)

- **6.0:** migración con fuente y validación por línea; verificador nuevo (toda línea de cálculo cita su fuente y página); botón "Marcar validada" por línea (solo se habilita con un caso que la cubra, `CoberturaCasos`); catálogo 2025 con 235 líneas verificadas contra los PDF de 2025. Schedule C líneas 8 a 27b con origen `calculo` (suma de renglones, no casilla). Form 8995 nuevo y activado junto al Schedule SE. Revisado en el navegador: se corrigieron "Página p. 1" y el panel que no se actualizaba al validar.
- **6.1a:** 88 parámetros 2025 con fuente y página (`parametros-metadatos.php`, migración espejo `2026_11_09_160000`); `TablaFiscal` y `ParametrosFiscales::tabla()`. Las tasas del EIC salen del estatuto (26 U.S.C. §32(b)(1)), no de un PDF del año.
- **6.2:** 20 PDF de 2025 fijados con hash en `tests/Fixtures/irs/2025/pdf/`; tablas CSV (Tax Table 2.062 filas, EIC 1.374, 2441, 8962); 7 casos con cita; comando `calculo:cobertura`. Las 3 pruebas de tablas completas siguen saltadas hasta que existan las calculadoras.
- **6.4a:** núcleo en `App\Services\Calculo\Nucleo` (`Dinero`, `Linea`, `Traza`, `Declaracion`, `Calculadora`, `EntradasDeclaracion`, `PersonaEntrada`, `DatoEntrada`, `RespuestaEntrada`, `RolPersona`). Redondeo opcional a dólares: 50 centavos o más sube (instrucciones del 1040, p. 23).
- **6.3a:** casillas de los 6 documentos de montos; `registros-ingresos-gastos` acepta `xlsx` y `csv`; VIN sensible (confirmado por Steven). `lectura = renglones` quedó para 6.3b (necesita migración).
- **Suite completa:** 1.097 pruebas verdes, 7 saltadas. PDF de referencia también en `~/Downloads/pdf-irs-2025-fase6`.
- **Carpeta de trabajo:** los PDF ya están en `tests/Fixtures/irs/2025/pdf/`; no hace falta bajarlos. Para texto, `pdftotext -layout` a una carpeta de `mktemp -d` (autorizado por Steven; se borra al terminar).

## Qué hace el agente principal al cerrar la ola 1

1. Revisar cada entrega contra el plan; **verificar al azar** tres cifras de parámetros y cinco filas de cada tabla contra el PDF del IRS.
2. `php artisan wayfinder:generate --with-form`, `vendor/bin/pint --test`, `npm run types:check`, `npx eslint` sobre lo tocado, y `PAO_DISABLE=1 php artisan test` solo de los archivos nuevos y los del catálogo y la lectura (la suite completa va al cierre de la fase).
3. Probar la pantalla Formularios del catálogo en el navegador (375 px y escritorio, es y en, botón de validar con y sin caso).
4. Escribir el "Cómo quedó" en el plan y marcar ✅ aquí; actualizar `tareas-desarrollo.md` y Wiplitask (empresa **Cupperlab**); preguntar a Steven antes de cada commit y usar `/commit`.
5. Detallar la **ola 2** en este archivo con los nombres reales de lo que dejó la ola 1.

---

# Ola 2

**Qué trae:** la lectura de hojas sin IA y el contrato `SumasRegistros` (6.3b), tres calculadoras que no dependen de la lectura de montos (6.4c, 6.4e, 6.4g), los parámetros de 2026 (6.1b) y el primer lote de casos del IRS escrito sin ver el código (6.9a). Corren las seis **en paralelo**.

## Contratos compartidos de la ola 2

**Dónde está lo que ya existe (ola 1):**
- Núcleo del motor: `App\Services\Calculo\Nucleo\*` (`Dinero`, `Linea`, `Traza`, `PasoTraza`, `Declaracion`, `Calculadora`, `EntradasDeclaracion`, `PersonaEntrada`, `DatoEntrada`, `RespuestaEntrada`, `RolPersona`, `EstadoLinea`, `OrigenTraza`, `TipoPaso`). Léelos antes de escribir una calculadora.
- Parámetros: `App\Support\ParametrosFiscales::valorRequerido($anio, $categoria, $clave)` y `::tabla(...)` (devuelve `TablaFiscal`). Las claves están en `database/data/catalogo-fiscal/2025/parametros-metadatos.php` (formato `categoria.clave`). Los porcentajes nuevos son **texto** (`"0.124"`): pásalos tal cual a `Dinero::por()`. Los montos son dólares enteros: conviértelos con `Dinero::deDolares()`.
- PDF de 2025 fijados con hash en `tests/Fixtures/irs/2025/pdf/` y tablas CSV en `tests/Fixtures/irs/2025/`. **No bajes los PDF otra vez**; saca el texto con `pdftotext -layout` a una carpeta de `mktemp -d` y bórrala al terminar.

**Convenciones de toda calculadora** (`App\Services\Calculo\Formularios\<Nombre>`):
1. Implementa `Calculadora`: `formulario(): string` devuelve el **código de catálogo** (`'1040'`, `'sch-1'`, `'sch-2'`, `'sch-c'`, `'sch-se'`, `'sch-eic'`…); si la calculadora escribe líneas de varios formularios, devuelve el principal y escribe cada línea con su propio código de formulario en `Linea`.
2. **Una calculadora escribe solo sus líneas** (las de su subtarea) y lee las de otros formularios con `Declaracion::linea($formulario, $linea)`. Si una línea que necesita **no está o es `falta_dato`**, escribe `Linea::faltaDato(...)` con el motivo en palabras ("Falta la línea 31 del Schedule C"); **nunca supone 0**.
3. Todo número fiscal sale de `parametros_fiscales` con `ParametrosFiscales`; **ninguna constante fiscal en el código**. Si el parámetro está pendiente (2026), captura la `RuntimeException` y escribe `falta_dato` "No se puede calcular: falta el parámetro X de <año> (Catálogo → Parámetros)".
4. Cada línea lleva su **traza** (`Traza`): entradas con su `origen` y `referencia` (id o clave, **nunca un valor sensible**), parámetros con su clave, la operación y el resultado. Si una entrada viene de un dato sin validar o con excepción (`DatoEntrada::usaSinValidar()`), la línea se escribe con `usaSinValidar` y la lista `datosSinValidar` (referencias).
5. Lo que el piloto no calcula **no se inventa**: marca `Linea::noSoportada(...)` diciendo qué documento lo causa y deja la declaración "parcial". Documentos fuera del piloto, para detectar: `1099-int`, `1099-div`, `1099-r`, `ssa-1099`, `1099-b`, `1099-da`, `1099-g`, `k-1`, `1098-e` (si existe en el catálogo del año); basta con que haya un `datos` cuyo código empiece por ese documento.
6. **Estado civil:** solo `EntradasDeclaracion::$estadoCivil` (`EstadoCivilIrs`). Si es `null`, todas las líneas dependientes de él salen `falta_dato` "Falta decidir el estado civil en Revisión". **MFS tiene sus propios tramos y deducción** (parámetros `tax_brackets.mfs`, `deduccion_estandar.monto_mfs`); los créditos con reglas especiales para MFS que no verifiques en las instrucciones salen `falta_dato` con "Con MFS, este crédito lo revisa el preparador".
7. **Pruebas sin el orquestador:** cada calculadora se prueba sola construyendo `EntradasDeclaracion` y una `Declaracion` a mano y poniendo en ella, con `Declaracion::poner(Linea::calculada(...))`, las líneas de otros formularios que necesita. Valores esperados **sacados de las instrucciones y las tablas del IRS** (no del código que escribes), cada uno con un comentario de fuente (formulario, línea, página). Prueba de arquitectura: ya existe una que prohíbe `float` y `round(` en `app/Services/Calculo`; cúmplela.

**Cómo llegan los datos a una calculadora** (lo llena `ArmadorEntradas` en la ola 4; hoy lo armas tú a mano en las pruebas):
- `EntradasDeclaracion::$datos`: clave `"{documento}:{casilla}"` (por ejemplo `w2:1`, `1099-misc:3`, `recibos-utiles-educador:total`). El valor es el **total ya sumado** de todos los documentos de ese tipo de la declaración (`Dinero`), con `origen`, `referencia` y las banderas `validado` y `conExcepcion`.
- `$sumasRegistros`: `array<string, Dinero>` con clave = línea del Schedule C (`'1-sin-formulario'`, `'2'`, `'8'` … `'27b'`, `'millas'`, `'pies-casa'`, `'pies-oficina'`).
- `$personas`: contribuyente, cónyuge y dependientes con `ClasificacionPersona`, `edadAlFinDeAnio`, `ssnValido`, `noValidoParaTrabajar`.
- `$respuestas`: por código de pregunta (`'E8'`, `'D15'`…).

**Archivos compartidos de la ola 2 y quién manda** (para no pisarse; si dos subtareas tocan el mismo archivo, edita con reemplazos exactos de tu zona y **vuelve a leer el archivo justo antes de editar**):

| Archivo | Dueño |
| --- | --- |
| `app/Services/CatalogoFiscal/CatalogoFiscalImportador.php` | 6.3b (clave `lectura` de documentos); 6.1b solo `aplicarAjustesDeParametros()` |
| `app/Services/Lectura/Reglas/MotorReglas.php`, `database/data/catalogo-fiscal/2025/reglas.php`, `ExplicadorExcepcion` | 6.3b |
| `app/Services/Calculo/Nucleo/PersonaEntrada.php` | 6.4g (solo agregar propiedades opcionales **al final**, con valor por defecto) |
| `tests/Feature/Calculo/TablasIrsTest.php`, `CasosIrsTest.php` | **nadie** en esta ola (las pruebas de tablas completas van en archivos propios; el agente principal quita los `skip` al cierre) |
| `composer.json`, `composer.lock` | 6.3b (`openspout/openspout`) |

---

## 6.3b — Hojas `xlsx`/`csv` sin IA, modelo de renglones, `SumasRegistros` y R-14 básica

**Plan:** secciones "6.3b" y "Contrato `SumasRegistros`" de `fase-6-calculo-borrador-1040.md` y la decisión (l). **Skills:** `arch-service-classes`, `arch-dto-pattern`, `eloquent-casts`, `eloquent-relationships`, `eloquent-eager-loading`, `sec-mass-assignment`. **Modelo:** Sonnet 5.5.

**Contexto mínimo:** `app/Services/Lectura/` (`DatosDocumento`, `LectorDocumentos`, `CalculadoraConfianza`, `MotivoConfianza`, `EsquemaLectura`, `Reglas/MotorReglas`, `Reglas/R11HijoSinSsn` como ejemplo de regla), `app/Models/Lectura/*`, `database/migrations/2026_11_03_120000_crea_lectura_de_documentos.php`, `database/data/catalogo-fiscal/2025/documentos.php` (`registros-ingresos-gastos` con sus casillas de 6.3a), `public/plantillas/registros-negocio-{es,en}.xlsx`, `tests/Fixtures/lectura/montos/` (plantilla llena en `xlsx` y `hoja-propia.csv`), `docs/mejora-plataforma/guia-confianza-de-lectura.md`, el controlador y la petición que suben documentos al portal (`SubirDocumentoRequest`).

**Qué hacer** (todo según la sección "6.3b" del plan; aquí lo que no está claro allí):
1. **`lectura = renglones`:** migración que agrega a `catalogo_documentos` una columna `lectura` (`string`, nullable; valores `casillas` por defecto o `renglones`), el modelo `CatalogoDocumento` (`#[Fillable]`), la clave `lectura` en `CLAVES['documentos']` del importador y, en `documentos.php`, `'lectura' => 'renglones'` para `registros-ingresos-gastos` (es la única edición que te toca de `documentos.php`; **nada más de ese archivo**). Que el lector de F4 (`LectorDocumentos`/`LectorOpenAi`) **no mande a OpenAI** un documento con `lectura = renglones` mientras no exista el lector de renglones con IA (tramo 2): para esos, `LectorHojas` si el archivo es `xlsx`/`csv`; si es PDF o imagen, lectura manual (sin datos) y R-14 "este archivo se lee en una versión posterior: el preparador lo revisa". Así 6.3a no dispara lecturas de OpenAI de extractos antes de tiempo.
2. **Modelo de renglones:** migración(es) y modelos `DocumentoRenglon`, `DocumentoRenglonCambio` y columnas nuevas de `documento_lecturas` tal como las define el plan (descripción y monto **cifrados**; totales impresos cifrados). Relaciones y casts como el resto de `app/Models/Lectura`. Un solo camino de escritura: agrega a `DatosDocumento` los métodos para escribir y corregir renglones (con historial en `documento_renglon_cambios`); nada de `update()` directo desde fuera.
3. **`App\Services\Lectura\LectorHojas`** (sin OpenAI, lector `hoja`): plantilla de F3.7 (reconocida por su encabezado y etiquetas, en español e inglés, usando una tabla fija en el nuevo `database/data/catalogo-fiscal/2025/plantilla-registros.php` que mapea cada etiqueta de categoría a su línea del Schedule C) y hoja propia por encabezados (sinónimos en el mismo archivo). `xlsx` con **`openspout/openspout`** (`composer require`; deja la versión en tu entrega) y `csv` con `fgetcsv` (separador detectado; UTF-8 o Latin-1). Fórmulas: se toma el valor guardado; una celda con fórmula sin valor → R-14. Confianza 0,99 con el motivo nuevo **"Leído de la hoja de cálculo (sin IA)"** en `MotivoConfianza` (título y explicación en palabras, y en la tabla de la guía). Una categoría que no coincide queda `no_se` y suma a R-14.
4. **`SumasRegistros`:** interfaz `App\Services\Lectura\SumasRegistros` con `para(Expediente $expediente): ResultadoSumas`, e implementación `SumasRegistrosPorRenglones` (registrada en el contenedor). `ResultadoSumas` (DTO `readonly`): por cada clave de línea del Schedule C (`'1-sin-formulario'`, `'2'`, `'8'` … `'27b'`, `'millas'`, `'pies-casa'`, `'pies-oficina'`) un `SumaCategoria` con `total` (`Dinero`), `renglonIds` (lista de ids), `confianza`, `motivo`. En este tramo suma los renglones vigentes de hojas y plantillas de todos los archivos de `registros-ingresos-gastos` del cliente (excluye `transferencia`, `personal` y `no_se`); el cuadre y la conciliación son del tramo 2 y **no cambian el contrato**.
5. **R-14 (primera versión)** `R14RegistrosNegocio`: columnas o filas no reconocidas de una hoja, celdas con fórmula sin valor, renglones sin categoría y archivos PDF o imagen de registros (aún sin lector con IA). Una excepción por archivo o por categoría, **nunca por renglón**; el detalle lleva ids, nunca montos ni descripciones. Texto en `ExplicadorExcepcion`. Agrega **R-14 y R-15** a `2025/reglas.php` (R-14 `revision` e `implementada`; R-15 `informativa` **sin** `implementada`, la implementa 6.5) y registra solo R-14 en `MotorReglas`.
6. **Portal:** `registros-ingresos-gastos` acepta `xlsx` y `csv` (ya lo hace por el catálogo de 6.3a); el mensaje de formatos no aceptados debe leer los formatos del catálogo en vez de decir "PDF, JPG, PNG o HEIC" fijo, y el texto "Qué subir" del documento presenta la plantilla como **la vía más sencilla, sin presionar** ("Si llevas tus cuentas, la plantilla es lo más rápido; si no, sube tus extractos y recibos"), en `que_subir_es` y `que_subir_en` de `documentos.php` solo para este documento.

**Pruebas** (`tests/Feature/Lectura/LectorHojasTest.php`, `RenglonesTest.php`, `SumasRegistrosTest.php`; OpenAI nunca se llama, `Http::assertNothingSent()` en las de hojas): plantilla de F3.7 en `xlsx` (la de `tests/Fixtures/lectura/montos/`) → cada categoría a su línea, confianza 0,99 con el motivo nuevo; `csv` con encabezados en inglés y una columna desconocida → renglones leídos y R-14 por la columna; categoría desconocida → `no_se` y R-14; fórmula sin valor → R-14; un PDF de registros no sale a OpenAI y abre R-14; suma por categoría con los ids de sus renglones; corregir un renglón deja historial y rehace la suma; ningún monto ni descripción en `excepciones.detalle`, logs ni bitácora; el importador carga `lectura = renglones`.

**Archivos permitidos:** las migraciones y modelos nuevos de renglones y de `lectura`; `app/Models/CatalogoFiscal/CatalogoDocumento.php` (solo `lectura`); `app/Services/CatalogoFiscal/CatalogoFiscalImportador.php` (solo `CLAVES['documentos']`); `app/Services/Lectura/*` (nuevos y los puntos exactos de `LectorDocumentos`, `DatosDocumento`, `MotivoConfianza`, `Reglas/MotorReglas`, `ExplicadorExcepcion`); `database/data/catalogo-fiscal/2025/plantilla-registros.php` y `reglas.php`; de `documentos.php` solo `lectura` y `que_subir_*` de `registros-ingresos-gastos`; `composer.json` y `composer.lock`; la petición de subida del portal para los formatos; `docs/mejora-plataforma/guia-confianza-de-lectura.md`; sus pruebas; fixtures en `tests/Fixtures/lectura/montos/`.
**No tocar:** `app/Services/Calculo/**`, `formularios.php`, `parametros*.php`, `2026/ajustes.php`, `tests/Fixtures/irs/**`.

**Criterio de terminado:** pruebas verdes, `pint` limpio, `catalogo-fiscal:verificar` limpio en 2025 (carga con `CatalogoFiscalSeeder` en las pruebas; no corras el cargador contra la base local); en tu entrega: versión de openspout, tabla de etiquetas de la plantilla → línea, cómo detectaste plantilla vs. hoja propia, y todo lo que dejaste "por verificar".

---

## 6.4c — Calculadoras `Schedule1` e `Ingresos1040`

**Plan:** tabla de 6.4b a 6.4h (fila 6.4c). **Skills:** `arch-service-classes`, `arch-value-objects`. **Modelo:** Sonnet 5.5.

**Contexto mínimo:** el núcleo (`Nucleo/*`) y sus pruebas, las convenciones de arriba, el catálogo de líneas de `sch-1` y `1040` en `2025/formularios.php` (con las fuentes y páginas que ya tienen), los PDF `f1040`, `f1040s1` y las instrucciones `i1040gi` (sección de las líneas 1a a 11 y "Educator Expenses", p. 93).

**Qué hacer** (en `App\Services\Calculo\Formularios\Schedule1` y `\Ingresos1040`):
- **`Ingresos1040`** escribe del Form 1040: `1a` (suma de `w2:1`), `1c` y `1e` **no las calcula** (1c es del Form 4137, ficha pendiente → `no_soportada` solo si hay dato de propinas no reportadas; `1e` la escribe la calculadora del 2441 en la ola 3: tú **no la tocas**), `1z` (suma de 1a a 1h: solo 1a y las que otra calculadora ya haya puesto; las demás cuentan 0 porque el piloto no las soporta), `8` (línea 10 del Schedule 1), `9` (ingresos totales: 1z, 2b, 3b, 4b, 5b, 6b, 7a y 8; si hay documentos fuera del piloto —lista de las convenciones— la línea sale `no_soportada` diciendo cuál), `10` (línea 26 del Schedule 1), `11a` (9 menos 10) y `11b` (copia de 11a). Cero o negativo se mantiene tal cual en 9 y 11a (el formulario no lo limita).
- **`Schedule1`** escribe `3` (línea 31 del Schedule C; si no está, `falta_dato`), `8z` (`1099-misc:3` más `1099-misc:8`), `9` (suma de 8a a 8z: solo 8z), `10` (1 a 7 y 9: solo 3, 8 y 9 del piloto; otras líneas → `no_soportada` si hay documentos fuera del piloto), `11` (gastos de educador: `recibos-utiles-educador:total` con el tope `schedule_1.tope_gastos_educador`; **primera versión: el tope se aplica una vez**; si el estado civil es MFJ y el total supera el tope, agrega a la traza "con declaración conjunta, si los dos cónyuges son educadores el tope es doble: lo revisa el preparador" y repórtalo como pendiente de decisión), `15` (línea 13 del Schedule SE; si no está, `falta_dato`), `26` (suma de 11 a 23 y 25: solo 11 y 15; si hay documentos fuera del piloto, `no_soportada`).
- Verifica cada número de línea y su descripción contra los PDF; si el catálogo de 6.0 tiene algo distinto, **repórtalo**, no lo cambies (el catálogo es de otra subtarea).

**Pruebas** (`tests/Unit/Calculo/Formularios/Schedule1Test.php` y `Ingresos1040Test.php`): casos con valores esperados de las instrucciones o armados a mano con cita: dos W-2, ganancia del Schedule C, educador con y sin tope, línea 9 con un `ssa-1099` → `no_soportada`, falta de la línea 31 del Schedule C → `falta_dato` y las líneas que dependen de ella también, traza con origen y sin valores sensibles, dato sin validar → `usaSinValidar`.

**Archivos permitidos:** `app/Services/Calculo/Formularios/Schedule1.php`, `Ingresos1040.php` y sus pruebas.
**No tocar:** el núcleo, el catálogo, otras calculadoras.

**Criterio de terminado:** pruebas verdes, `pint` limpio; entrega con las líneas que escribe cada clase, lo que quedó `no_soportada`, la decisión pendiente del tope de educador y las diferencias que encontraste con el catálogo.

---

## 6.4e — Calculadora `ImpuestoYSchedule2` (impuesto, Schedule 2 y líneas 21 a 24 del 1040)

**Plan:** tabla de 6.4b a 6.4h (fila 6.4e) y "Estrategia de validación" capa 1. **Skills:** `arch-service-classes`, `arch-value-objects`. **Modelo:** Sonnet 5.5.

**Contexto mínimo:** el núcleo, las convenciones, `tests/Fixtures/irs/2025/tax-table.csv` y `fuentes.json`, `tests/Feature/Calculo/TablasIrsTest.php` (el comentario de la clase esperada: `TablaImpuesto::paraFila(string $estadoCivil, Dinero $ingresoGravable): Dinero`), PDF `p1040` (Tax Table p. 2 a 13, Tax Computation Worksheet p. 14), `f1040`, `f1040s2`, el Form 8959 de 2025 (`https://www.irs.gov/pub/irs-prior/f8959--2025.pdf` a una carpeta de `mktemp -d`; es de 6.1a), los parámetros `tax_brackets.*`, `additional_medicare_tax.*`.

**Qué hacer** (en `App\Services\Calculo\Formularios\ImpuestoYSchedule2`, más `Impuesto\TablaImpuesto`):
1. **`TablaImpuesto::paraFila(string $estadoCivil, Dinero $ingresoGravable): Dinero`** (`$estadoCivil` es el valor del enum: `single`, `mfj`, `mfs`, `hoh`, `qss`; `qss` usa la columna de MFJ): para ingreso gravable **menor de USD 100.000**, el método del IRS: la tabla da el impuesto del **punto medio de cada franja** (de USD 5 en las primeras, de USD 25 y de USD 50 según el tramo; deduce las franjas del PDF de la Pub. 1040 y **verifícalas**) calculado con los tramos del año (`tax_brackets.*`) y redondeado a dólares enteros como dice la Tax Table. **Se prueba contra las 2.062 filas de `tax-table.csv`** (todas las columnas, MFS incluida): `tests/Feature/Calculo/TablaImpuestoCsvTest.php` (archivo propio; no toques `TablasIrsTest.php`).
2. **Línea 16 del 1040:** si el ingreso gravable (línea 15) es menor de USD 100.000, `TablaImpuesto`; si no, la **Tax Computation Worksheet** (Pub. 1040, p. 14: por estado civil, "multiplica por la tasa y resta la cifra"): impleméntala con las cifras del PDF **derivadas de los tramos del año** y pruébala con casos por tramo y en los bordes (USD 99.999 / 100.000 / 100.001 y los cortes de cada tramo). Si hay dividendos calificados o ganancias de capital (documentos fuera del piloto), la línea sale `no_soportada` (falta la hoja QDCG).
3. **Líneas del 1040:** `17` (línea 3 del Schedule 2), `18` (16 + 17), `21` (19 + 20, leídas de Declaracion), `22` (18 − 21, mínimo cero), `23` (línea 21 del Schedule 2) y `24` (22 + 23). Las que dependan de líneas de otras calculadoras (19 y 20 de 6.4f en la ola 3) salen `falta_dato` si no están.
4. **Schedule 2:** `3` (1a —línea 29 del 8962, leída de `f8962:29`; si no está, 0 sin aviso solo si el cliente no tiene 1095-A, si no `falta_dato`— más línea 2 AMT, que el piloto no soporta), `4` (línea 12 del Schedule SE; `falta_dato` si no está) y `11` (impuesto adicional de Medicare, **Form 8959**: parte I sobre `w2:5` con el umbral por estado civil `additional_medicare_tax.umbral_*`, parte II sobre el ingreso de autoempleo con el umbral reducido por los salarios, tasa `additional_medicare_tax.tasa`; la parte de retenciones de las líneas 19 a 24 **no**, es del 25c de 6.4h), `21` (4, 7 a 16, 18 y 19: solo 4 y 11 en el piloto).
5. **NIIT (8960) y AMT** no se soportan: `no_soportada` solo si hay datos que la dispararían (intereses, dividendos, alquileres).

**Pruebas:** la de las 2.062 filas; `TablaImpuestoTest` (puntos medios, bordes entre franjas, mínimo, MFS vs soltero, QSS = MFJ); hoja de cálculo por tramos con cifras del PDF; el ejemplo del IRS ya transcrito (`pub1040-tax-table-mfj-25300`: USD 25.300 MFJ → USD 2.562) **sin leer el archivo de caso**: cópialo a mano con su cita; Medicare adicional con salarios por encima y por debajo del umbral y para MFS; todas las líneas de arriba con `falta_dato` cuando falta una dependencia.

**Archivos permitidos:** `app/Services/Calculo/Formularios/ImpuestoYSchedule2.php`, `Formularios/Impuesto/*`, `tests/Unit/Calculo/Formularios/ImpuestoYSchedule2Test.php`, `TablaImpuestoTest.php`, `tests/Feature/Calculo/TablaImpuestoCsvTest.php`.
**No tocar:** el núcleo, `TablasIrsTest.php`, el catálogo, otras calculadoras.

**Criterio de terminado:** pruebas verdes (las 2.062 filas incluidas), `pint` limpio; entrega con las franjas que dedujiste, las cifras de la hoja de cálculo con su página, y lo que dejaste `no_soportada`.

---

## 6.4g — Calculadora `Eitc`

**Plan:** fila 6.4g y los límites de la capa 4 (EITC). **Skills:** `arch-service-classes`, `arch-value-objects`. **Modelo:** Sonnet 5.5.

**Contexto mínimo:** el núcleo (en especial `PersonaEntrada`), las convenciones, `tests/Fixtures/irs/2025/eic-table.csv`, `eic-notas.csv` y `fuentes.json`, el comentario de `TablasIrsTest.php` (`CreditoEic::paraFila(string $columna, Dinero $montoDeLaHoja): Dinero`), PDF `p1040` (EIC Table desde la p. 16), `i1040gi` (instrucciones de la línea 27a, **Hoja de trabajo A y B**, requisitos y reglas de hijo calificado), `f1040sei`, el parámetro `credito_eic.por_hijos` y `credito_eic.tope_ingreso_inversion`.

**Qué hacer** (en `App\Services\Calculo\Formularios\Eitc`, más `Eitc\CreditoEic`):
1. **`CreditoEic::paraFila(string $columna, Dinero $montoDeLaHoja): Dinero`:** el crédito según la EIC Table (columnas por número de hijos y por estado civil conjunto o no) calculado **con la fórmula del IRS sobre el punto medio de cada franja de USD 50** con los parámetros `credito_eic.por_hijos` (tasa de entrada, máximo, inicio y fin de la reducción), redondeado como la tabla; incluye las **8 celdas con asterisco** (notas en `eic-notas.csv`). **Se prueba contra las 1.374 filas** de `eic-table.csv`: `tests/Feature/Calculo/CreditoEicCsvTest.php` (archivo propio).
2. **Hoja de trabajo:** ingreso del trabajo (sueldos `w2:1` más la ganancia neta del Schedule C menos la mitad del impuesto de autoempleo, según la hoja A o B de las instrucciones; **verifica la regla exacta en el PDF**) e ingreso ajustado (línea 11b del 1040); el crédito es el que corresponde al **mayor** de los dos, por las reglas de la hoja; tope de ingreso de inversión (`credito_eic.tope_ingreso_inversion`; si hay intereses o dividendos —documentos fuera del piloto—, `no_soportada`).
3. **Requisitos** (líneas del 1040 27a y del Schedule EIC): hijo calificado (edad y estudiante/incapacitado, tiempo de convivencia, parentesco), SSN válido del contribuyente, del cónyuge y de cada hijo, y MFS. **`PersonaEntrada` no trae estudiante, incapacitado, meses de convivencia ni parentesco:** agrega **al final**, con valor por defecto, las propiedades opcionales que necesites (`?bool $esEstudiante = null`, `?bool $incapacitado = null`, `?int $mesesConviviendo = null`, `?string $parentesco = null`, y las que verifiques que hacen falta) y documéntalas en tu entrega: las llena `ArmadorEntradas` en la ola 4 desde las preguntas C3, C4, C7 y C8. Cuando el dato opcional es `null` y es necesario, la línea sale `falta_dato` diciendo cuál.
4. **MFS:** el crédito de MFS que vive separado tiene una excepción; **verifícala en las instrucciones de 2025**; si no puedes, `falta_dato` con "Con MFS, este crédito lo revisa el preparador".
5. **Salidas:** línea `27a` del 1040 y el `credito` del Schedule EIC (con su traza) y las líneas `1` a `6` del Schedule EIC solo en el sentido de "qué hijos entran" (referencias, sin nombres ni SSN). Un cliente sin hijos: la hoja sin hijos (con los requisitos de edad del contribuyente, **verifícalos**).

**Pruebas:** las 1.374 filas; casos por número de hijos (0, 1, 2, 3 y 4), en los bordes (fin de la entrada, inicio y fin de la reducción, MFJ vs. otros) con cifras de la EIC Table; ingreso de inversión; hijo sin SSN válido; hijo de 19 años estudiante; MFS; datos opcionales `null`. Ejemplo ya transcrito del IRS (soltero, un hijo, hoja USD 2.455 → USD 842) copiado a mano con su cita (**sin leer el archivo de caso**).

**Archivos permitidos:** `app/Services/Calculo/Formularios/Eitc.php`, `Formularios/Eitc/*`, `app/Services/Calculo/Nucleo/PersonaEntrada.php` (solo agregar al final), `tests/Unit/Calculo/Formularios/EitcTest.php`, `CreditoEicTest.php`, `tests/Feature/Calculo/CreditoEicCsvTest.php`, y la prueba de `PersonaEntrada` que ya exista (solo ampliar).
**No tocar:** `TablasIrsTest.php`, el catálogo, otras calculadoras.

**Criterio de terminado:** pruebas verdes (las 1.374 filas incluidas), `pint` limpio; entrega con las propiedades nuevas de `PersonaEntrada`, las reglas de la hoja con su página, la excepción de MFS (verificada o pendiente) y lo que dejaste `falta_dato`.

---

## 6.1b — Parámetros de 2026

**Plan:** sección "6.1 Parámetros por año", viñeta "2026". **Skills:** `laravel-best-practices` (solo si tocas el importador). **Modelo:** Sonnet 5.5.

**Contexto mínimo:** `database/data/catalogo-fiscal/2026/ajustes.php`, `app/Services/CatalogoFiscal/CatalogoFiscalImportador.php` (`aplicarAjustesDeParametros`), `CatalogoFiscalCopiador`, `2025/parametros-metadatos.php` (la lista de parámetros y su forma), `tests/Feature/CatalogoFiscal/CargaTest.php` (prueba de copia a 2026), `ParametrosTest.php`.

**Qué hacer:**
1. Con **fuentes oficiales** (irs.gov; usa la búsqueda y descarga a una carpeta de `mktemp -d`): la Rev. Proc. de ajustes por inflación de 2026 (**número por verificar**: busca "Revenue Procedure 2025" "tax year 2026" inflation adjustments), los formularios y borradores de 2026 en `irs.gov/pub/irs-dft/` o `irs-pdf/` (el Schedule C, SE y 8812 de 2026 ya se sirven en `irs-pdf/`), y el aviso de la tarifa de millaje de 2026. Para **cada parámetro de la lista de 2025** que el IRS ya haya publicado para 2026, pon su valor en `2026/ajustes.php`, sección `parametros`, **con fuente** (formulario o Rev. Proc., sección y página). Lo que **no** encuentres publicado queda pendiente (no lo pongas) y se lista en tu entrega con el motivo. Nada de cifras de memoria: si no lo verificaste en un documento del IRS, no entra.
2. Formato de un parámetro con fuente: `'categoria.clave' => ['valor' => X, 'fuente_texto' => '… p. N', 'fuente_url' => '…']` (el escalar simple sigue valiendo). Ajusta **solo** `aplicarAjustesDeParametros()` del importador para aceptar las dos formas y guardar `fuente_texto` y `fuente_url` del parámetro; sin validar.
3. **`lineas`** de `ajustes.php`: solo las diferencias de número de línea que muestren los formularios o borradores de 2026 frente a los de 2025 (por ejemplo, si el 1040 o el Schedule 1-A cambian de numeración); cada una con su fuente en `fuente_texto`, `fuente_url` y `fuente_pagina`. **Si ninguna cambia, déjalo vacío y dilo.**
4. No toques nada de 2025.

**Pruebas:** `CargaTest` (la copia a 2026 deja los parámetros publicados con su valor y fuente, y el resto pendiente) y `ParametrosTest` (ningún parámetro de 2026 con valor sin fuente; `verificar` lista los pendientes de 2026 por nombre; cambiar 2026 no toca 2025).

**Archivos permitidos:** `database/data/catalogo-fiscal/2026/ajustes.php`; `app/Services/CatalogoFiscal/CatalogoFiscalImportador.php` (solo `aplicarAjustesDeParametros`); `tests/Feature/CatalogoFiscal/CargaTest.php` y `ParametrosTest.php` (solo lo necesario); un fixture de ajustes de prueba si lo necesitas.
**No tocar:** `2025/**`, `formularios.php`, `documentos.php`, `reglas.php`.

**Criterio de terminado:** pruebas verdes, `pint` limpio; entrega con la tabla de cada parámetro de 2026 puesto (clave, valor, documento y página), la lista de los que siguen pendientes, el número de la Rev. Proc. y las líneas que cambian de número (si las hay).

---

## 6.9a — Primer lote de casos del IRS (escrito sin ver el código)

**Plan:** "Estrategia de validación" capas 3 y 4, y "6.9 Casos de punta a punta". **Skills:** ninguna. **Modelo:** Sonnet 5.5. **Regla de oro:** **no abras `app/Services/Calculo/` ni ninguna calculadora (vieja o nueva) ni sus pruebas**; el valor esperado sale del PDF y de las instrucciones del IRS, calculado **a mano**, paso por paso, como lo haría un preparador en papel.

**Contexto mínimo:** `tests/Fixtures/irs/casos/README.md` (formato y regla de doble lectura), los 7 casos ya escritos como modelo, `tests/Support/CasosIrs.php`, los PDF y tablas de `tests/Fixtures/irs/2025/` (`tax-table.csv`, `eic-table.csv`, `f2441-tabla.csv`, `f8962-*.csv`, PDF fijados), y las instrucciones del 1040 de 2025 (`i1040gi`).

**Qué hacer:** escribe en `tests/Fixtures/irs/casos/2025/` estos casos, cada uno con `entradas` (hechos, no conclusiones), `pasos` (la hoja del IRS transcrita línea por línea con su fuente y **página**), `lineas` esperadas y `cubre` con **solo las líneas de las que de verdad das el valor**:
1. `tax-soltero-ingreso-gravable-100000-borde`: ingreso gravable USD 99.999, 100.000 y 100.001, soltero (Tax Table → hoja de cálculo); valores con el CSV y la hoja de la p. 14 de la Pub. 1040.
2. `tax-mfj-franja-borde-5` y `tax-mfs-franja`: bordes de las franjas de USD 5, 25 y 50 de la Tax Table, MFJ y MFS.
3. `tax-hoh-tramo-24`: cambio de tramo con HOH.
4. `eitc-sin-hijos`, `eitc-un-hijo-borde-reduccion`, `eitc-dos-hijos-mfj`, `eitc-tres-hijos-limite`: bordes de la EIC Table (fin de la entrada, inicio y fin de la reducción; MFJ vs. otros).
5. `schedule1-educador-tope`: gastos de educador por encima del tope (verifica el tope y la regla de MFJ en las instrucciones del 1040 de 2025, p. 93).
6. `medicare-adicional-soltero` y `medicare-adicional-mfs`: Form 8959 Parte I con salarios por encima del umbral (cifras del PDF del 8959; descárgalo de `https://www.irs.gov/pub/irs-prior/f8959--2025.pdf` a una carpeta de `mktemp -d`).
Cada valor esperado cita formulario, línea, **página** y URL del PDF fijado (agrega a `fuentes.json` solo si fijas un PDF nuevo; si necesitas el 8959, pregunta antes de copiarlo al repositorio). Donde dos lecturas posibles de la instrucción den cifras distintas, **escribe las dos como `pendiente de revisión`** en el caso y repórtalo.

**Pruebas:** `CasosIrsTest` ya revisa la estructura (cita con página y URL). Agrega **cero** pruebas que dependan del motor.

**Archivos permitidos:** `tests/Fixtures/irs/casos/2025/**` (solo archivos nuevos) y, si fijas un PDF, `tests/Fixtures/irs/2025/pdf/*` y `fuentes.json`.
**No tocar:** `app/**`, `tests/Unit/**`, `tests/Feature/**` salvo para correr `CasosIrsTest`.

**Criterio de terminado:** `CasosIrsTest` verde; entrega con la lista de casos y las líneas que cada uno cubre, las lecturas ambiguas que encontraste y cualquier cifra que **no** pudiste confirmar en el PDF.

---

## Qué hace el agente principal al cerrar la ola 2

1. Revisar cada entrega contra el plan; **verificar al azar** cinco cifras de casos y de parámetros de 2026 contra el PDF.
2. Quitar los `skip` de `TablasIrsTest.php` (Tax Table, EIC y 2441 salen de las pruebas propias de 6.4e y 6.4g; la del 2441 sigue para 6.4f).
3. `wayfinder:generate`, `pint --test`, `types:check`, `eslint` sobre lo tocado, suite completa con `PAO_DISABLE=1 php artisan test --parallel` (una sola vez).
4. Contrastar los casos de 6.9a con las calculadoras de 6.4e y 6.4g: **si no coinciden, el agente principal revisa contra el PDF antes de tocar cualquiera de los dos**.
5. Revisar en el navegador lo que toque (portal: formatos y "Qué subir"; visor de lectura de la hoja).
6. "Cómo quedó" de la ola, `tareas-desarrollo.md`, Wiplitask (empresa **Cupperlab**); preguntar a Steven antes de cada commit (`/commit`).
7. Detallar la **ola 3** con los nombres reales.

## Cómo quedó la ola 2 (9 y 10 nov. 2026)

- **6.3b:** `lectura = renglones` (migración `2026_11_10_100000`), tablas de renglones y de cambios, columnas nuevas de `documento_lecturas`, `LectorHojas` (`xlsx` con openspout v5.13.0 y `csv`, sin IA, confianza 0,99 "Leído de la hoja de cálculo"), `SumasRegistros` (interfaz + `SumasRegistrosPorRenglones`, 29 claves), regla R-14 y R-15 en el catálogo. Los PDF y fotos de registros ya no van a OpenAI: R-14 los marca para el preparador. Etiquetas de la plantilla → línea del Schedule C en `2025/plantilla-registros.php`.
- **6.4c:** `Ingresos1040` y `Schedule1`. **6.4e:** `ImpuestoYSchedule2` con `TablaImpuesto` (Tax Table, 2.062 filas × 4 columnas, todas iguales) y `HojaImpuesto` (Tax Computation Worksheet, 20 filas de la p. 14). **6.4g:** `Eitc` y `CreditoEic` (1.374 filas de la EIC Table, iguales); cinco propiedades opcionales nuevas en `PersonaEntrada` (C3, C4, C7, C8, C11).
- **6.1b:** 82 parámetros de 2026 con fuente (Rev. Proc. 2025-32 y borradores de 2026), 5 pendientes con motivo. Las líneas que cambian de número en 2026 quedaron **sin cargar**: el mecanismo de ajustes no puede crear ni renumerar líneas.
- **6.9a:** 20 casos nuevos del IRS escritos sin ver el código; los dos de Medicare adicional activos con el Form 8959 fijado. `ContrasteCasosImpuestoTest` corre los casos de la línea 16 por `ImpuestoYSchedule2`: todos coinciden.
- **Cierre:** códigos de formulario de los casos unificados con el catálogo (`f2441`, `f8962`, `sch-1a`); líneas 12, 14 y 16 del 2441 agregadas al catálogo; pruebas de tablas completas de `TablasIrsTest` reemplazadas por las de 6.4e y 6.4g (queda saltada la del 2441). Suite completa: 1.290 verdes y 7 saltadas; los 2 fallos del cierre (conteo de reglas y parámetros de 2026 en `PantallaTest`) se corrigieron y se volvieron a correr.

### Decisiones y pendientes que salen de la ola 2

1. **Redondeo a dólares enteros:** las calculadoras dejan centavos; el IRS permite redondear (p. 23 del 1040). Falta decidir cómo se muestra y se llena en el borrador.
2. **Tope de gastos de educador (USD 300 por persona):** la calculadora lo aplica una vez; con dos cónyuges educadores el IRS permite 600. Hace falta una pregunta y repartir los recibos por persona.
3. **Millaje de 2026 con dos tarifas** (hasta el 30 de junio y desde el 1 de julio) y **tabla del 2441 de 2026 con dos columnas:** forma de guardarlos.
4. **Renumeración de líneas de 2026** (1040, Schedule 1-A y Schedule C): cómo cargarla.
5. **`LeerDocumentoJob`:** con la lectura automática apagada no lee las hojas `xlsx`/`csv`, que no necesitan IA; y un PDF de registros queda "leído" sin datos.
6. **Inglés de los textos del servidor** de las calculadoras y de R-14: sin archivo de idioma asignado.
7. **Cobertura de documentos fuera del piloto:** alquileres y regalías (1099-MISC casillas 1 y 2) y el 1099-SA no marcan la declaración como parcial.
8. **Fechas ambiguas** en hojas propias (03/10/2025): se leen como mes/día/año.
9. **Texto fijo del portal** "PDF, JPG, PNG o HEIC" en `es.json` sigue sin leer los formatos del catálogo.
