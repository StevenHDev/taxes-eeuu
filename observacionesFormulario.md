# Observaciones del formulario dinámico del portal

Registro de trazabilidad: cada vez que revisamos el formulario (`resources/js/pages/portal/formulario.tsx` y todo lo que toca — catálogo, validación, cálculo de determinación fiscal) anotamos acá el problema encontrado, la causa raíz y la corrección propuesta, antes de tocar código. Cuando se aplique un fix, se marca el estado y se referencia el commit.

Estados: `pendiente de decisión` · `aprobado, falta aplicar` · `aplicado`

---

## 1. "Información de tus dependientes" (obligatorio) no permite decir "no tengo dependientes"

**Estado:** pendiente de decisión

**Campo:** `info_dependientes` (transversal, `array_object`, `obligatorio: true`)

**Síntoma:** el cliente no tiene forma de indicar que no tiene dependientes. El campo es obligatorio, así que el botón "No aplica" no se muestra (solo aparece cuando `!fila.obligatorio`). Si el cliente intenta guardar sin agregar ninguna fila (array vacío `[]`), el backend rechaza el envío con `422 The contenido field must be present` — un array vacío no sobrevive la serialización `forceFormData` (multipart), así que ni siquiera queda el camino silencioso de "guardar lista vacía".

**Confirmado en navegador:** clic en "Guardar" sin agregar filas → error visible "The contenido field must be present."

**Causa raíz:**
- `database/seeders/CatalogoCamposSeeder.php:88-92` — `info_dependientes` no pasa `obligatorio: false`, así que usa el default `true` del helper `campo()` (línea 484).
- Ya existe precedente exacto para este mismo bug: `w2` y `form_1099_nec` (líneas 93-98) se cambiaron a `obligatorio: false` con este comentario textual: *"antes, al ser obligatorios, un cliente sin ninguno de los dos quedaba trabado para siempre (EventoValidator rechaza modo="no_aplica" en un campo obligatorio)."*

**Propuesta:** cambiar `info_dependientes` a `obligatorio: false` en el seeder — mismo patrón ya aplicado a w2/1099-NEC. Esto habilita el botón "No aplica", dando un estado explícito ("confirmado: no aplica") en vez de dejar la ambigüedad de un array vacío que se vería igual a un envío incompleto.

**Alternativa descartada:** aceptar un array vacío como válido (`Recibido` con `[]`) — quedaría indistinguible de un bug/envío accidental, sin ninguna confirmación explícita del cliente.

---

## 2. Guardado parcial de "Estado civil" puede producir una determinación fiscal incorrecta sin ninguna advertencia

**Estado:** pendiente de decisión

**Campo:** `estado_civil` (transversal, `object`, 8 subcampos: `casado_al_31_dic`, `convivio_conyuge_ultimos_6_meses`, `costeo_mas_mitad_hogar`, `existe_persona_calificable`, `conyuge_fallecio_en_anio`, `anio_fallecimiento_conyuge`, `se_caso_en_anio`, `se_divorcio_o_separo_en_anio`)

**Síntoma:** la UI muestra los 8 subcampos como inputs sueltos bajo un solo botón "Guardar" (no hay guardado por subcampo). Un cliente puede escribir "no" solo en "Casado al 31 de diciembre" y guardar, dejando los otros 7 campos completamente ausentes del objeto.

**Qué pasa con ese guardado parcial:**
1. `EventoRecoleccionService::objetoTieneSubcampos()` (`app/Services/EventoRecoleccionService.php:805-828`) tiene una regla especial para `estado_civil`: si `casado_al_31_dic` no es afirmativo, automáticamente considera opcionales a `convivio_conyuge_ultimos_6_meses`, `costeo_mas_mitad_hogar` y `existe_persona_calificable` (sumados a los 4 que ya eran opcionales siempre). Con eso, el único campo requerido está presente → se guarda como **`Recibido`**, no `Invalido`. No queda ninguna señal de "incompleto".
2. `FilingStatusCalculator` (`app/Services/Reglas/FilingStatusCalculator.php:22-48`) lee cada subcampo ausente vía `?? false` / `?? null`, y `RespuestaSiNo::esAfirmativo()` trata `false` igual que un "no" explícito — no hay forma de distinguir "nunca se preguntó" de "contestó que no". Consecuencia concreta: `costeo_mas_mitad_hogar` ausente = `false` → nunca puede calificar para "Head of Household" aunque el cliente sí pague más de la mitad del hogar y tenga un dependiente calificado. Siempre cae en **"Single"** por descarte.
3. El administrador **no ve ninguna advertencia**: `detectarValorDuplicado()` (que setea `advertencia` en un `CampoCliente`) solo aplica a campos numéricos — para `estado_civil` (`object`) siempre es `null`. En `clientes/show.tsx`, `necesitaAtencion()` solo marca algo como "necesita atención" si está `pendiente`, `invalido`, o tiene `advertencia` — este campo, al ser `recibido` sin advertencia, se ve idéntico a si el cliente hubiera contestado las 8 preguntas completas.

**Impacto:** un preparador puede hacer clic en "Calcular" en el panel de determinación fiscal, ver "Single" con total normalidad, y aprobarlo — cuando el sistema nunca evaluó si el cliente calificaba para Head of Household, porque el formulario nunca lo obligó a completar la info necesaria. Bug silencioso de cálculo, no un bloqueo como el caso 1.

**Propuesta (a decidir):**
- Opción A: exigir el objeto completo de `estado_civil` cuando `casado_al_31_dic = "no"` en vez de volver opcionales los subcampos relacionados con Head of Household/Qualifying Surviving Spouse — forzar al cliente a contestar todo antes de que cuente como `Recibido`.
- Opción B: que `FilingStatusCalculator`/`DeterminacionFiscalService` detecten subcampos realmente ausentes (no solo `false`) y devuelvan "no disponible" en vez de asumir "no" — igual que ya hace `DeterminacionFiscalService::leerValor()` cuando el campo entero no está `Recibido`.
- Ambas no son excluyentes; falta decidir cuál(es) aplicar.

---

## 3. Dos subcampos de "Estado civil" no son en realidad sobre estado civil — y uno de ellos no se usa para nada

**Estado:** pendiente de decisión

**Relacionado con:** observación #2 (mismo campo `estado_civil`)

**Síntoma:** dentro del bloque "Estado civil" el formulario pregunta "Costeó más de la mitad del hogar" y "Existe una persona calificada" — ninguna de las dos es realmente una pregunta de estado civil.

**Qué es cada una en realidad:**

- **`costeo_mas_mitad_hogar`** — sí se usa, pero no para determinar estado civil: es un insumo de `FilingStatusCalculator.php:28,39,44` para decidir Head of Household / Qualifying Surviving Spouse. El campo `estado_civil` se diseñó a propósito como "hechos crudos para que el motor de reglas calcule el filing status" (comentario en `CatalogoCamposSeeder.php:100-101`), no como "estado civil" en sentido literal — de ahí el desajuste entre el nombre/etiqueta del campo y lo que realmente contiene.
- **`existe_persona_calificable`** — **no se usa en ningún cálculo.** `DependentQualificationCalculator.php:14` dice explícitamente que el cálculo real de si existe un dependiente calificado se hace de forma independiente a partir de `info_dependientes`, *"en vez de confiar en el flag auto-reportado `estado_civil.existe_persona_calificable` — evita que el agente conversacional y el cálculo real queden inconsistentes"*. Se le pregunta al cliente, se guarda, se muestra en `clientes/show.tsx:500` — pero ningún cálculo lo lee. Es fricción para el cliente sin ningún efecto downstream. Grep completo confirmado: cero usos fuera de la definición del catálogo, la lista de opcionales, el seeder de demo, y el display de solo lectura del admin.

**Propuesta (a decidir junto con el resto):**
- `existe_persona_calificable`: candidato a **eliminar** del formulario (dejar de preguntarlo) — no aporta nada al cálculo real y ya genera la ambigüedad de la observación #2 sin ningún beneficio a cambio. Si se elimina, hay que decidir qué hacer con los datos ya guardados en producción (dejarlos huérfanos vs. migración de limpieza).
- `costeo_mas_mitad_hogar`: sigue siendo necesario, pero valorar si debe seguir viviendo dentro de "estado_civil" o separarse en un grupo propio ("Hogar"/"Head of Household") con su propia etiqueta — para que el cliente entienda por qué se le pregunta esto en medio de "¿estás casado?".

---

## 4. Campos opcionales `dato`/`mixto` se sienten obligatorios: no hay pregunta de sí/no, solo un input para llenar (afecta 37 campos)

**Estado:** pendiente de decisión

**Síntoma original (caso semilla):** `ip_pin` — "IRS Identity Protection PIN" se ve como una etiqueta con un input de texto vacío y botones "Save"/"Not applicable", sin ninguna pregunta ni contexto. Un cliente que no sabe qué es un IP PIN (la mayoría) no tiene forma de entender qué se le está pidiendo ni por qué.

**Confirmado como patrón sistémico, no un caso aislado — más ejemplos encontrados:**
- `salarios_empleado_domestico` (`Dato`, número) → "Household employee wages", input numérico vacío. No dice qué es un "empleado doméstico" ni si aplica.
- `venta_a_plazos` (`Dato`, texto) → "Installment sale", input de texto vacío.
- `venta_residencia_principal` (`Mixto`, objeto con 3 subcampos) → se muestra directo como 3 inputs para llenar (fecha de venta, precio de venta, costo base) + selector de archivo, en vez de preguntar primero "¿Vendiste tu residencia principal?".

**Alcance real medido en el catálogo:** hay **37 campos opcionales `dato`/`mixto` sin ninguna pregunta de sí/no** — se saltan directo a "llena este input". En cambio, los **23 campos opcionales `documento` sí tienen** el gate "¿Tienes este documento? Sí/No" antes de mostrar el selector de archivo.

**Causa raíz:** el `cuestionario.txt` enmarca *cada uno* de sus ~100 ítems como `☐ Sí ☐ No ☐ N/A [pregunta]?` — un patrón uniforme de pregunta-primero. En el código, ese mismo patrón existe (`preguntaConfirmacionDocumento()` + diccionario `portalFormulario.preguntaDocumento.<campo>` en `formulario.tsx`) pero solo se activa cuando `tipo_campo === 'documento'` (`necesitaConfirmacion = esDocumento && !fila.obligatorio`) — nunca para `mixto` ni `dato`.

**Caso adicional específico de `ip_pin`:** además de la falta de pregunta, tampoco permite subir el documento correspondiente. El cuestionario ofrece explícitamente `Documento: CP01A / IP PIN` (línea 23) — el IRS manda cada año una carta (aviso CP01A) con el código; el cliente debería poder subir esa carta en vez de transcribir a mano un código de 6 dígitos que probablemente no tiene memorizado. Pero `ip_pin` está definido como `FieldKind::Dato` (`CatalogoCamposSeeder.php:149`), sin documento asociado. Precedente casi idéntico ya resuelto bien en el propio catálogo: `cartas_irs` (línea 210) es `FieldKind::Mixto`, acepta pdf/jpg/png y también texto, para "¿Recibió cartas o avisos del IRS?" — el CP01A es literalmente una carta del IRS, así que `ip_pin` debería seguir ese mismo patrón (`Mixto` en vez de `Dato`).

**Propuesta (a decidir junto con el resto):**
- Extender el gate de pregunta (`necesitaConfirmacion` + `preguntaDocumento`) para que aplique a **todo campo opcional**, no solo `tipo_campo === 'documento'` — esto es lo que realmente resuelve los 37 casos, no solo `ip_pin`. Implica ampliar el diccionario `preguntaDocumento` (hoy tiene ~22 entradas, todas para campos documento) con una pregunta por cada uno de los 37 campos `dato`/`mixto` opcionales.
- Además, específico de `ip_pin`: cambiar de `FieldKind::Dato` a `FieldKind::Mixto` (mismo patrón que `cartas_irs`) para permitir subir la carta CP01A en vez de solo texto.

---

## Pendiente de revisar

_(el usuario sigue revisando el formulario observación por observación; se agregan acá a medida que aparecen)_
