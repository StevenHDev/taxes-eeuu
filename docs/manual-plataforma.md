# Manual de la plataforma

## Qué es esta plataforma

Es el sistema donde el despacho organiza y controla todo el proceso de preparación de declaraciones de impuestos de sus clientes: qué información falta, qué ya se recibió, qué documentos se cargaron, y qué resultados fiscales arrojan esos datos (estado civil fiscal, ingreso ajustado y créditos).

La idea central es simple: cada cliente tiene una ficha en la plataforma que se va completando campo por campo — algunos datos los carga un asistente conversacional que habla con el cliente, y otros los revisa o corrige el equipo del despacho. La plataforma siempre sabe, en todo momento, qué porcentaje de la información de cada cliente ya está lista y qué falta.

Todo se organiza además **por año fiscal**: un mismo cliente puede tener su declaración de 2025 completa y ya estar empezando a cargar datos de 2026, sin que se mezclen ni se pisen entre sí.

---

## Quién usa la plataforma

La plataforma reconoce tres tipos de personas:

- **Administrador** — Es el rol de mayor control. Ve y gestiona a todos los clientes del despacho, sin importar quién los atiende. Además de lo que puede hacer un preparador, el administrador es quien da de alta a los preparadores, quien asigna qué clientes atiende cada uno, y quien puede eliminar clientes o cuentas por completo.

- **Preparador** — Es el contador o especialista que efectivamente trabaja la declaración. Solo ve y gestiona a los clientes que tiene asignados (no a los de sus compañeros), y es quien revisa la información recolectada, corrige datos, carga documentos y ejecuta los cálculos fiscales.

- **Cliente** — Es el contribuyente cuya declaración se está preparando. **El cliente no entra a este panel de trabajo** — no tiene ni necesita una pantalla como la que usan los preparadores. Su manera de interactuar con el despacho es a través de un asistente conversacional (un chat inteligente) que le va preguntando sus datos de forma natural, uno a la vez, y le permite enviar sus documentos (W-2, 1099, comprobantes, etc.). Todo lo que el cliente responde o envía en esa conversación aparece automáticamente, en tiempo real, en su ficha dentro de la plataforma — el preparador nunca tiene que transcribir nada a mano.

---

## El panel principal (Dashboard)

Es la pantalla de inicio para preparadores y administradores, y da una foto general de cómo viene el trabajo del despacho:

- **Clientes en gestión**: cuántos clientes hay en total, y cuántos están sin iniciar, en progreso o completos.
- **Actividad**: un gráfico de cuántos datos se fueron recibiendo día a día en la última semana, para ver de un vistazo si el ritmo de recolección es activo o se frenó.
- **Porcentaje de campos recibidos**: qué proporción de toda la información que se necesita (de todos los clientes y formularios) ya llegó.
- **Porcentaje de formularios completos**: de todos los formularios que ya se empezaron, cuántos llegaron al 100%.
- **Pendientes de revisar**: formularios que ya están completos pero todavía nadie del despacho los marcó como revisados — es la bandeja de "hay que darle una mirada humana a esto".
- **Casos de alto riesgo**: cuántos clientes tienen hoy un nivel de riesgo alto (ver más abajo qué significa esto).
- **Actividad reciente**: un detalle campo por campo de lo último que se cargó, de qué cliente, y si lo cargó el asistente conversacional, un preparador o un administrador.
- **Distribución por formulario**: cuántos clientes tienen cada tipo de formulario (1040, Schedule C, etc.), para ver qué tan variada es la cartera del despacho.
- **Últimos clientes**: acceso rápido a las fichas más recientes.

---

## El módulo de Clientes

### El listado de clientes

Es la tabla principal desde donde se accede a cada cliente. Permite:

- Buscar por nombre, email o teléfono.
- Filtrar por **estado** (sin iniciar / en progreso / completo), por **nivel de riesgo** (bajo / medio / alto) y por **tipo de formulario**.
- Ver de un vistazo, para cada cliente, su estado general, su nivel de riesgo y qué formularios le corresponden.
- Dar de alta un cliente nuevo (nombre, email y teléfono).

### La ficha de cada cliente

Al entrar a un cliente en particular, se ve toda su información organizada por año fiscal (con un selector arriba para cambiar de año) y dividida en las siguientes partes:

**Datos del cliente e identificación.** La información propia de la persona que no depende de ningún formulario en particular — por ejemplo su número de seguro social, su estado civil, la información de su cónyuge o sus dependientes. Estos datos se cargan una sola vez y se comparten para todos los formularios que le correspondan ese año.

**Nivel de riesgo del caso.** Cada cliente tiene una etiqueta de riesgo — bajo, medio o alto — que ayuda a priorizar qué casos necesitan más atención. Por defecto la plataforma lo calcula sola con reglas simples (por ejemplo, sube a "alto" si hay campos o documentos inválidos, y a "medio" si el caso es complejo o le faltan formularios por completar), pero cualquier preparador o administrador puede fijarlo manualmente si su criterio profesional dice otra cosa. Cuando alguien lo fija a mano, queda claro que es un criterio humano y no el automático, y se puede quitar ese ajuste en cualquier momento para volver a la sugerencia del sistema.

**Cálculo fiscal automático.** Esta es una de las piezas más importantes de la plataforma: a partir de los datos que ya se cargaron, el sistema puede calcular automáticamente:

- El **estado civil fiscal** del cliente (soltero, casado declarando en conjunto, cabeza de familia, o cónyuge sobreviviente calificado) — se determina solo, a partir de hechos concretos (si estuvo casado, si convivió con su cónyuge, si sostiene el hogar, etc.), nunca preguntándoselo directamente al cliente como si fuera una opción a elegir.
- El **ingreso bruto ajustado (AGI)** — la suma de todos los ingresos declarados menos los ajustes que correspondan.
- Los **créditos fiscales** a los que califica: Crédito Tributario por Hijos, Crédito por Otros Dependientes y Crédito por Cuidado de Dependientes, cada uno con su monto y con el detalle de por qué se llegó a ese número (incluyendo cualquier reducción por nivel de ingreso).
- Qué **dependientes califican** para cada crédito, listados uno por uno.

Este cálculo no ocurre solo: el preparador presiona un botón de "Calcular" (o "Recalcular", si ya se había hecho antes) cuando considera que ya hay suficiente información cargada. Si todavía falta algún dato clave, la plataforma lo avisa en vez de inventar un resultado.

**Los formularios y sus campos.** Debajo aparece una sección por cada tipo de formulario que le corresponde al cliente (por ejemplo, Form 1040 para su declaración individual, o Schedule C si tiene un negocio propio). Dentro de cada sección se ve, campo por campo:

- El nombre del dato o documento que se pide.
- Su valor actual (o el documento cargado, con un visor para verlo sin descargarlo).
- Su estado: **pendiente** (todavía no llegó), **recibido** (ya está cargado y es válido) o **inválido** (llegó algo, pero no pasa las validaciones mínimas — por ejemplo un archivo dañado).
- Un mini-indicador visual que muestra de un vistazo cuánto de ese formulario ya está completo.

Desde ahí mismo el preparador puede corregir un dato a mano, cargar o reemplazar un archivo, agregar un campo que todavía no se haya pedido, o eliminar un dato cargado por error — y una vez que un formulario está listo, marcarlo como "revisado" para dejar constancia de que un humano del despacho lo validó (no solo que el asistente lo recibió).

**Documentos y detección de duplicados.** Cuando se sube un archivo, la plataforma revisa automáticamente si ese mismo documento ya se había subido antes — ya sea para el mismo cliente en otro campo, o incluso para otro cliente distinto. Esto ayuda a detectar, por ejemplo, que alguien subió el mismo W-2 dos veces sin darse cuenta. Por privacidad, un preparador solo ve el nombre del otro cliente involucrado si ese cliente también le pertenece a él; si es de otro preparador, solo se le avisa que existe una coincidencia, sin revelar de quién se trata.

**Historial de cambios.** Cada campo tiene su propio historial: quién lo cargó o modificó (el asistente conversacional, un preparador o un administrador), cuándo, y cuál era el valor anterior. Esto da trazabilidad completa de cómo llegó cada dato a su valor final.

**Datos sensibles protegidos.** Información como el número de seguro social se guarda cifrada y se muestra siempre enmascarada (por ejemplo, terminando solo en los últimos dígitos). Para verla completa, hay que confirmar la contraseña de la propia cuenta — así queda registrado quién decidió revelar un dato sensible y cuándo.

**Exportar cliente.** En cualquier momento se puede descargar toda la información y todos los documentos de un cliente (para ese año fiscal) en un solo archivo comprimido, útil por ejemplo para pasarle el caso a otro sistema o guardarlo como respaldo.

---

## Cómo llega la información: el asistente conversacional

El despacho no depende de que el cliente entienda formularios técnicos ni de que un preparador tenga que hacerle un cuestionario manual. En su lugar, un asistente conversacional (un chat con inteligencia artificial) le va haciendo preguntas al cliente en lenguaje simple y cotidiano — por ejemplo, "¿estuviste casado al 31 de diciembre?" en vez de preguntar directamente "¿cuál es tu estado civil fiscal?" — y le pide que suba sus documentos según se los va necesitando.

Cada respuesta o archivo que el cliente entrega en esa conversación se guarda automáticamente en su ficha dentro de la plataforma, con el estado correspondiente. El asistente siempre sabe qué le falta preguntar porque consulta en vivo, contra la plataforma, la lista de lo que ya se recibió y lo que sigue pendiente — así nunca pide dos veces lo mismo ni se olvida de algo que ya se agregó a la lista de requisitos.

Esto significa que, para el despacho, gran parte de la carga de datos "se hace sola": el trabajo del equipo pasa a ser sobre todo de revisión, corrección de casos puntuales, y el cálculo final — no de tipeo manual repetitivo.

---

## Qué se le pide a cada cliente, según su situación (catálogo por año)

No todos los clientes necesitan lo mismo: alguien con un solo empleo no necesita los mismos datos que alguien con un negocio propio o una propiedad en alquiler. La plataforma mantiene, para cada año fiscal, una lista configurable de qué información y qué documentos corresponden a cada tipo de situación (declaración individual, negocio propio, alquiler, sociedad, etc.).

Esta lista puede ajustarse año a año — porque los montos de créditos y los límites del IRS cambian cada año — sin afectar la información ya cargada de años anteriores. Es el administrador quien mantiene esta configuración al día, típicamente al comenzar cada temporada fiscal.

---

## Gestión de usuarios del despacho

El administrador es quien da de alta a los preparadores y a los clientes como cuentas dentro del sistema, define su rol, y — en el caso de los clientes — a qué preparador queda asignado cada uno. También es el único que puede editar el perfil de otra persona o eliminar una cuenta por completo. Un preparador, en cambio, puede corregir la información de sus clientes asignados, pero no puede cambiarles el rol ni reasignarlos a otro preparador — ese control queda reservado al administrador.

---

## Seguridad y privacidad

- La información sensible de cada cliente (como su número de seguro social) se guarda cifrada y nunca se muestra en texto plano sin una confirmación explícita de contraseña.
- Cada preparador solo puede ver y trabajar con los clientes que tiene asignados; solo el administrador ve la cartera completa del despacho.
- Todo cambio a un dato queda registrado con quién lo hizo y cuándo, así como quién reveló un dato sensible en cada ocasión.
- Los documentos se comparten mediante enlaces temporales, no quedan expuestos de forma permanente.
- Las cuentas de acceso pueden protegerse además con verificación en dos pasos, para una capa extra de seguridad al iniciar sesión.
