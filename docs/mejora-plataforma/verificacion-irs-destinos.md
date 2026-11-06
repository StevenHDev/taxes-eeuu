# Verificación contra el IRS: a dónde va cada dato (5 nov. 2026)

Qué se verificó y con qué fuente, para que cada destino de la **Hoja para TaxWise** y cada formulario activado tenga respaldo y se pueda revalidar por año fiscal.

## Cómo se verificó
- Se bajaron los PDF oficiales de `irs.gov/pub` y se leyó su texto (no resúmenes).
- **Año 2025:** formularios finales (Form 1040, Schedules 1, 2, SE y E), instrucciones del Form 1040 (2025), instrucciones del W-2 para el empleado (2025), 1099-MISC y 1099-NEC (Rev. abril 2025, instrucciones para el receptor).
- **Año 2026:** borradores del IRS del Form 1040 y de los Schedules 1, 2, SE y E, y las revisiones de diciembre 2026 del 1099-MISC y del 1099-K.
- **Año 2027:** el IRS todavía no publica nada. No se busca hasta que salgan los borradores.
- **Regla para años nuevos:** al preparar un año, se vuelve a abrir cada fuente de esta tabla y se compara el número de línea. Si no cambió, no se toca el catálogo.

## Destinos verificados

| Dato | Destino según el IRS | Fuente | 2025 | 2026 |
|---|---|---|---|---|
| Retención federal de los Form 1099 (casilla 4 del NEC, K y MISC; igual para los demás 1099) | Form 1040, línea **25b** ("Form(s) 1099"). "Otros ingresos" incluidos | Instrucciones del Form 1040 (2025), "Line 25b"; f1040.pdf | ✔ | ✔ (borrador) |
| 1099-MISC casilla 1, alquileres | Schedule E línea 3. Pasa a Schedule C si hubo servicios importantes al inquilino, venta de inmuebles como negocio o alquiler de bienes personales como negocio | Instrucciones para el receptor del 1099-MISC; f1040se.pdf | ✔ | ✔ |
| 1099-MISC casilla 2, regalías | Schedule E línea 4 (un interés de explotación, según las instrucciones del Schedule E) | ídem | ✔ | ✔ |
| 1099-MISC casilla 3, otros ingresos | Schedule 1 línea **8z** "Other income", identificando el pago. Si es ingreso de negocio, Schedule C o F | 1099-MISC receptor; f1040s1.pdf | ✔ | ✔ |
| 1099-MISC casillas 5, 6 y 7 | Schedule C (individuos) | 1099-MISC receptor | ✔ | — |
| 1099-MISC casilla 8 | Schedule 1 "Other income" | 1099-MISC receptor | ✔ | — |
| 1099-MISC casilla 9 | Schedule F (no modelado todavía; pendiente F7) | 1099-MISC receptor | ✔ | — |
| 1099-MISC casilla 10, abogado | Sin línea fija: "solo la parte gravable como ingreso". Decide el preparador | 1099-MISC receptor | ✔ | — |
| 1099-NEC casilla 1 | Schedule C o F si es ingreso de trabajo por cuenta propia. Si es esporádico o hobby, Schedule 1 "Other income". Si el cliente cree que era empleado, línea de sueldos más Form 8919. **Decide el preparador** | 1099-NEC (Rev. abril 2025), instrucciones para el receptor | ✔ | — |
| 1099-K casilla 1a | Sin línea fija: "vea las instrucciones de su declaración". Ingreso de negocio, normalmente Schedule C. Decide el preparador | 1099-K (Rev. diciembre 2026), instrucciones del participante | ✔ (misma redacción) | ✔ |
| Ganancia del Schedule C | Schedule 1 línea 3 y Schedule SE línea 2 | f1040s1.pdf, f1040sse.pdf | ✔ | ✔ |
| Impuesto de autoempleo | Schedule SE línea 12 va a Schedule 2 línea **4**. La mitad deducible (Schedule SE línea 13) va a Schedule 1 línea **15** | f1040s2.pdf, f1040sse.pdf | ✔ | ✔ |
| Cuándo se presenta el Schedule SE | Cuando la línea 4c del propio Schedule SE es de USD **400 o más** (esa línea ya multiplica por 92,35 %, así que sobre la ganancia son unos USD 433) | Instrucciones del Schedule SE, "Who Must File" | ✔ | ✔ |
| W-2 casilla 1 y 2 | Línea de sueldos (1a) y retención de W-2 (25a) | Instrucciones del W-2 para el empleado | ✔ | — |
| W-2 casilla 10 | Form 2441 | ídem | ✔ | — |
| W-2 casilla 8 | Form 4137 | ídem | ✔ | — |
| W-2 casilla 5 | Form 8959 si aplica | ídem | ✔ | — |
| W-2 casillas 3, 4, 6 y 7 | No se entran en una línea del 1040 | ídem | ✔ | — |

## Diferencias entre años que ya se conocen
- **1099-K 2026:** trae las casillas **1c y 1d** (propinas en efectivo y códigos de ocupación) para la deducción por propinas calificadas del Schedule 1-A, Parte II. El catálogo hoy conoce 1a, 1b, 2, 3 y 4. Se agrega al preparar el 2026.
- **Líneas de formularios 2025 y borradores 2026:** idénticas para todo lo de esta tabla (25b; Schedule 1 líneas 3, 5, 8z y 15; Schedule 2 línea 4; Schedule SE líneas 2, 12 y 13; Schedule E líneas 3 y 4). Se revalidan cuando el IRS publique las versiones finales de 2026.

## Lo que no se puede verificar con el IRS
- **Pantallas de TaxWise.** Son del programa, no del IRS. Por eso la hoja usa un destino descriptivo ("Pantalla del Form W-2 en TaxWise · casilla 3") y no nombres de menú. Los nombres exactos los confirma GTS.
- **Decisiones de criterio** (si un alquiler es de negocio o de inversión, si un 1099-NEC es trabajo por cuenta propia, a qué línea va un 1099-K). La hoja las muestra como "normalmente..." y las decide el preparador.

## Pendiente
- Form 8995 (deducción por ingresos calificados del negocio): no está entre los 30 formularios del catálogo. Validar con GTS y con las instrucciones del IRS antes de agregarlo.
