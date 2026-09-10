Hola, te comparto este documento porque quiero que hagamos una revisión más profunda de taxes-eeuu desde el punto de vista de Global Tax Services.

La plataforma ya tiene una parte importante desarrollada, pero como desarrollador yo conozco principalmente cómo está construido el sistema y las decisiones que hemos ido tomando durante el desarrollo. Hay decisiones del negocio, procesos, reglas y situaciones que solamente ustedes, por la experiencia trabajando con los clientes y preparando las declaraciones, conocen realmente.

Por eso, el objetivo de este documento no es preguntarte cosas técnicas ni pedirte que diseñes el software.

Lo que quiero obtener de ti es una descripción clara de:

* Cómo trabaja actualmente Global Tax Services.

* Qué problemas quieres solucionar con la plataforma.

* Cómo debería funcionar idealmente el proceso.

* Qué información necesitan de los clientes.

* Cómo determinan qué información y documentos necesita cada cliente.

* Qué situaciones especiales o excepciones existen.

* Qué decisiones deben tomar los preparadores.

* Qué cosas deberían automatizarse y cuáles deberían seguir requiriendo revisión humana.

* Qué esperas que haga la plataforma actualmente y en el futuro.

* Qué consideras prioritario para el negocio.

También incluí algunas preguntas sobre cosas que ya existen actualmente en la plataforma, como el catálogo de información y documentos. La idea es que puedas decirme si lo que construimos realmente representa la forma en que Global Tax Services trabaja, o si debemos modificarlo.

No es necesario que utilices términos técnicos ni que pienses en cómo programar cada cosa. Respóndeme desde tu conocimiento del negocio, incluso si alguna respuesta es simplemente "no lo hemos definido todavía".

Después de recibir tus respuestas, voy a utilizar la información para:

**1\.** Comparar lo que Global Tax Services necesita con lo que actualmente hace la plataforma.

**2\.** Detectar cosas que están bien implementadas y debemos conservar.

**3\.** Detectar cosas que están incompletas o deben cambiar.

**4\.** Identificar reglas, procesos o casos especiales que todavía no conocemos.

**5\.** Separar lo que es una decisión del negocio de lo que es una decisión técnica.

**6\.** Definir con mayor precisión qué debería hacer el producto.

**7\.** Priorizar qué debemos desarrollar, modificar o investigar antes de continuar.

En otras palabras, quiero asegurarme de que sigamos desarrollando la plataforma correcta para Global Tax Services y no simplemente sigamos agregando funcionalidades al sistema que ya tenemos.

**No es necesario responder todo de una sola vez. Si alguna pregunta necesita conversación o explicación adicional, la podemos revisar juntos.**

**Cuestionario de Descubrimiento**

Global Tax Services — Plataforma taxes-eeuu

Este documento busca recopilar la información del negocio necesaria para asegurar que taxes-eeuu represente correctamente la forma en que Global Tax Services trabaja actualmente y la forma en que desea trabajar en el futuro.

No es necesario responder preguntas técnicas. Las preguntas están enfocadas en el funcionamiento del despacho, sus procesos, sus clientes y las decisiones que debe tomar el equipo.

Cuando una pregunta no aplica, puede indicarse simplemente **"No aplica".**

*Si algo no está definido todavía, indíquelo como tal. Es preferible responder "No lo hemos definido" que asumir una respuesta.*

# **1\. Visión del producto**

**1.1 ¿Qué problema principal quieres solucionar con esta plataforma? (Descríbelo desde el punto de vista de Global Tax Services, no desde la tecnología.)**

Simplificar procesos manuales a procesos automatizados para mayor control y efectividad en tiempo, así como, tener procesos de seguimiento a clientes.  Se busca mayor efectividad para con la misma infraestructura, tener mayor volumen de clientes

**1.2 ¿Qué tareas del proceso actual consumen más tiempo al equipo? Por ejemplo:**

* Recolección de información. ***Importante porque con estos datos se hace la declaración de impuestos***

* Solicitud de documentos. ***No se tienen un mecanismo efectivo para solicitar los documentos necesarios.***

* Revisión de documentos. ***Se realiza manualmente con el cliente en frente***

* Ingreso de información. ***Con los documentos en mano, se ingresan al software de Taxes***

* Corrección de información. ***Manual***

* Cálculo. ***Lo realiza el sistema que ya está preestablecido***

* Comunicación con clientes. ***Llaman a solicitar cita***

* Seguimiento de casos. ***NULO, No hay tiempo para eso***

* Preparación para TaxWise. ***Ingreso manual de clientes nuevos y los antiguos con el SSN los trae por estar en base de datos***

* Otra.

*Indica cuáles son las más importantes y por qué.*

* Recolección de información

* Ingreso de información

**1.3 ¿Qué errores ocurren actualmente con mayor frecuencia? Describe qué errores ocurren, porque ocurren y qué consecuencias tienen.**

*La recepción de documentos por ser muy manual puede ocasionar problemas, un cliente entrega algunos documentos y luego en la cita dice que entregó algo que no se tiene… es la palabra de él contra la de nosotros.*

**1.4 Si la plataforma funcionará exactamente como esperas, ¿qué cambiaría en el trabajo diario de Global Tax Services?**

*Tener más tiempo para incorporar nuevos clientes. Busco efectividad del sistema y me ahorre tiempo de clientes pequeños sin necesidad de estar haciendo todo* 

# **2\. Proceso actual de una declaración**

**2.1 Describe paso a paso qué ocurre desde que llega un cliente nuevo hasta que su declaración está lista para ser presentada. (Puedes describirlo de manera sencilla: Cliente nuevo → ... → ... → declaración lista.)**

Cliente nuevo \- Solicitud de cita por teléfono \- entrega documentos \- ingreso de datos personales de las personas que están en la declaración \-  explicación de los valores generados en pagos y créditos al cliente \- declaracion lista para imprimir y entrega de folder

**2.2 ¿Quién participa en cada etapa? Por ejemplo:**

* Cliente.

* Agente de WhatsApp.

* Preparador.

* Administrador.

* Otra persona.

En casi todos los casos es Cliente & Preparador, algunos casos específicos “Otra Persona” recibe documentación por que el cliente no ha solicitado cita 

**2.3 ¿En qué momentos debe intervenir una persona del despacho?**

Siempre\! 

**2.4 ¿Qué partes del proceso consideras que deberían ser completamente automáticas?**

Lo ideal es que en clientes sencillos se realice TODO el proceso automático

**2.5 ¿Qué partes nunca deberían ejecutarse automáticamente y deben ser revisadas por una persona?**

Si se llega al punto de automatizar todo el proceso se necesita una auditoría final por parte de una persona en todos los casos

# **3\. ¿Cuándo está completo un caso?**

**3.1 ¿Cuándo consideras que una declaración está realmente lista para ser revisada por el preparador?**

Cuando están ingresados todos los datos y el software da el valor final de pago o de devolución

**3.2 ¿Qué condiciones deben cumplirse para considerar que un caso está "completo"? Por ejemplo:**

* Toda la información obligatoria está registrada.

* Todos los documentos necesarios fueron recibidos.

* Los documentos fueron revisados.

* El cálculo fue realizado.

* El preparador aprobó el resultado.

* Otra condición.

Todos los puntos dan una declaración COMPLETA

**3.3 ¿Puede un caso estar completo aunque falte algún documento o información? Si la respuesta es sí, explica en qué situaciones.**

Se puede terminar y enviar pero si faltó algún documento no entregado por el cliente se debe hacer una enmienda anexando ese documento en la misma declaración pero incluyendo la forma de enmienda (1040X)

# **4\. Información que necesita Global Tax Services**

**4.1 ¿Qué información necesita el despacho de un cliente para poder preparar correctamente su declaración? (Puedes hacer una lista general.)**

Nombres completos de cada persona que aparece en la declaración, fechas de nacimientos, número de seguro social, fecha de nacimiento, estado civil, parentesco. Todos los tipos de Ingresos generados (w2, 1099-nec, extractos bancarios) 

**4.2 ¿Qué información es obligatoria para prácticamente todos los clientes?**

Toda la descrita anteriormente

**4.3 ¿Qué información solamente aplica a determinados clientes?**

Muchos casos, si es retirado, la forma de retiro, si es dueño de casa la forma 1098, si estudia la forma 1098-T, si invierte en bolsa o crypto los extractos de la cuenta

**4.4 ¿Qué información debe proporcionar directamente el cliente?**

Nosotros solo recibimos la documentación, no generamos ningún documento. Solo entregamos impresa la declaración terminada

**4.5 ¿Qué información puede obtenerse de documentos?**

Allí se ve reflejada todo la información necesaria

**4.6 ¿Qué información debe ser revisada o confirmada por un preparador?**

Se debe revisar bien los números que aparecen porque ahí se refleja el ingreso total del año 

# **5\. Catálogo actual de información y documentos**

*Importante: actualmente la plataforma tiene un catálogo configurable que determina qué información y documentos deben solicitarse a un cliente dependiendo de su situación y del tipo de declaración. El catálogo está organizado por año fiscal y formulario, y contempla tanto información transversal del cliente como información relacionada con formularios específicos.*

**Campos transversales principales:**

* SSN.

* Estado civil.

* Cónyuge.

* Dependientes.

**Elementos/documentos principales:**

* W-2.

* 1099-NEC.

* 1095-A.

El sistema también tiene un conjunto de "documentos extra": actualmente 18 documentos opcionales, separados del conjunto principal para evitar que el agente de WhatsApp los solicite automáticamente a todos los clientes. Se consultan cuando la situación o la conversación del cliente indica que podrían ser necesarios, para que la recolección sea más reactiva a la situación real del cliente.

**5.1 Teniendo en cuenta lo anterior, ¿el catálogo actual representa correctamente la forma en que Global Tax Services decide qué información y documentos pedir a cada cliente? (Sí / No / Parcialmente)**

Parcialmente. En la última prueba si pero preguntando varias veces lo mismo

**5.2 Si respondiste "No" o "Parcialmente", ¿qué información o documentos hacen falta?**

En la prueba solo se realizó con un empleado que tiene dos formas, la forma W2 y la forma del seguro de salud 1095-A, por tanto no tengo toda la información completa para contestar

**5.3 ¿Existe actualmente información o documentos en el catálogo que Global Tax Services realmente no necesita solicitar?**

No

**5.4 ¿Hay información o documentos que deberían solicitarse solamente cuando se presenta una determinada situación del cliente? Describe las situaciones.**

Si, por ejemplo, a un cliente se le debe preguntar si tuvo seguro de salud (Marketplace) seguro subsidiado por el gobierno, si la respuesta es SÍ, Inmediatamente debe tener la forma 1095-A para ingresar esos datos en la declaración

**5.5 ¿Existen documentos que deberían solicitarse siempre, independientemente de la situación del cliente?**

Si, en las preguntas iniciales están (Datos personales y tipo de ingresos)

**5.6 ¿Existen documentos que solamente deben solicitarse después de que el cliente responda determinada pregunta? Por ejemplo: "Si el cliente indica que tiene un negocio → solicitar determinada información/documentación". Describe los casos que conozcas.**

Ejemplo: Seguro de salud descrito anteriormente / Ejemplo: Trabajador por cuenta propia \- Forma 1099-nec / Ejemplo: Crypto \- Extractos de la cuenta / Dueño de casa \- Forma 1098

**5.7 ¿Cómo decide actualmente un preparador qué información o documentos necesita un cliente? Describe el razonamiento que utiliza un preparador experimentado.**

Con las preguntas exactas al cliente: Que tipo de ingreso tiene? ¿Es empleado o independiente? Inversiones? Seguro de salud? Tiene negocio? El negocio esta con una corporación? 

**5.8 ¿Hay casos en los que dos clientes con situaciones aparentemente similares necesitan información o documentos diferentes? Sí sí, explica ejemplos.**

Normalmente no\! Todo depende de sus ingresos deben tener las mismas formas. Pueden haber diferentes situaciones que llevan que haya diferencias entre dos clientes pero allí ya no son similares

**5.9 ¿Hay información que actualmente un preparador conoce que debe solicitar, pero que no está contemplada en el catálogo?**

No

**5.10 ¿Hay información que el catálogo debería poder solicitar en el futuro y que actualmente no contempla?**

# **6\. Documentos**

**6.1 ¿Qué tipos de documentos puede recibir normalmente el despacho de un cliente?**

**6.2 ¿Cómo determina el preparador si un documento recibido es válido?**

**6.3 ¿Qué ocurre cuando un cliente entrega un documento incorrecto?**

**6.4 ¿Qué ocurre cuando entrega un documento duplicado?**

**6.5 ¿Qué ocurre cuando un documento está incompleto o es ilegible?**

**6.6 ¿Qué documentos requieren obligatoriamente revisión humana?**

**6.7 ¿Puede un cliente continuar con su declaración aunque falte algún documento? Si sí, ¿en qué situaciones?**

# **7\. Recolección mediante WhatsApp e IA**

*Actualmente el cliente interactúa con un agente conversacional mediante WhatsApp. El agente solicita información y documentos y utiliza la API de la plataforma para registrar la información.*

**7.1 ¿Qué quieres que el cliente pueda hacer completamente mediante WhatsApp?**

**7.2 ¿Qué cosas NO quieres que el agente de IA haga?**

**7.3 ¿En qué situaciones debe intervenir un empleado del despacho?**

**7.4 ¿Qué debería ocurrir si el cliente proporciona información contradictoria?**

**7.5 ¿Qué debería ocurrir si el cliente cambia una respuesta que había proporcionado anteriormente?**

**7.6 ¿Qué debería ocurrir si el agente no puede determinar qué información necesita el cliente?**

# **8\. Preparadores y revisión humana**

**8.1 ¿Qué información debe poder modificar un preparador?**

**8.2 ¿Qué información nunca debería poder modificarse sin dejar un registro?**

**8.3 ¿Qué decisiones deben ser tomadas exclusivamente por un preparador?**

**8.4 ¿Qué resultados calculados deben ser revisados antes de considerarse definitivos?**

**8.5 ¿Hay situaciones que automáticamente deberían requerir una revisión especial? Describe cuáles.**

# **9\. Cálculo y determinación fiscal**

*La plataforma actualmente cuenta con un motor de reglas tributarias que calcula diferentes componentes de la declaración y finalmente puede producir el impuesto total. Entre las reglas implementadas existen cálculos relacionados con filing status, AGI, créditos, deducción estándar, QBI, impuesto de autoempleo, NIIT, Additional Medicare Tax y taxable income/impuesto total.*

**9.1 ¿El resultado que actualmente produce el sistema coincide con lo que espera Global Tax Services?**

**9.2 ¿Qué resultados del cálculo deben ser revisados obligatoriamente por un preparador?**

**9.3 ¿Existen situaciones en las que el cálculo automático no debería considerarse definitivo?**

**9.4 ¿Qué situaciones tributarias especiales deberían generar una alerta o revisión adicional?**

**9.5 ¿Hay cálculos o reglas importantes que actualmente no estén contemplados?**

# **10\. Riesgo y casos especiales**

*Actualmente el sistema calcula un nivel de riesgo para los casos y permite que el preparador pueda modificar esa clasificación.*

**10.1 ¿Qué hace que un cliente sea considerado un caso de alto riesgo o complejo para Global Tax Services?**

**10.2 ¿Qué situaciones deberían generar automáticamente una alerta?**

**10.3 ¿Qué debería hacer el despacho cuando un caso es considerado de alto riesgo?**

**10.4 ¿Quién debería poder cambiar manualmente el nivel de riesgo?**

# **11\. TaxWise**

*La integración con TaxWise está actualmente pendiente de definir con el proveedor.*

**11.1 ¿Qué esperas que ocurra entre taxes-eeuu y TaxWise?**

**11.2 ¿Qué información debería pasar automáticamente a TaxWise?**

**11.3 ¿En qué momento debería realizarse ese envío?**

**11.4 ¿Debe existir una aprobación del preparador antes de enviar información?**

**11.5 ¿Qué debería ocurrir si TaxWise rechaza información?**

**11.6 ¿Qué debería ocurrir si después de enviar información a TaxWise el preparador modifica los datos?**

# **12\. Administración del despacho**

**12.1 ¿Qué funciones debería poder realizar un administrador?**

**12.2 ¿Qué funciones debería poder realizar un preparador?**

**12.3 ¿Qué información debería poder consultar cada uno?**

**12.4 ¿Un cliente puede ser atendido por más de un preparador?**

**12.5 ¿Cómo se asignan actualmente los clientes a los preparadores?**

**12.6 ¿Qué ocurre cuando un cliente cambia de preparador?**

# **13\. Futuro del producto**

**13.1 ¿Qué te gustaría que Global Tax Services pudiera hacer con esta plataforma dentro de 1 año?**

**13.2 ¿Y dentro de 3 años?**

**13.3 ¿La plataforma está pensada exclusivamente para Global Tax Services o podría utilizarse posteriormente para otros despachos?**

**13.4 ¿Te interesa que el sistema pueda manejar diferentes tipos de declaraciones además de las que actualmente soporta?**

**13.5 ¿Qué parte del proceso te gustaría automatizar en el futuro?**

**13.6 ¿Hay alguna funcionalidad que consideres importante aunque actualmente no esté contemplada?**

# **14\. Prioridades**

**Si tuvieras que elegir solamente cinco mejoras para realizar a continuación, ¿cuáles serían?**

**1\.**  

**2\.**  

**3\.**  

**4\.**  

**5\.**  

**14.1 ¿Cuál de esas cinco consideras la más importante?**

**14.2 ¿Por qué?**

# **15\. Preguntas abiertas**

**15.1 ¿Hay algo importante sobre la forma en que Global Tax Services prepara declaraciones que no hayamos preguntado?**

**15.2 ¿Hay alguna decisión que actualmente toma un preparador manualmente y que consideras importante que el sistema pueda comprender en el futuro?**

**15.3 ¿Hay alguna parte del sistema actual que no funciona como esperabas?**

**15.4 ¿Hay alguna parte del sistema actual que consideras especialmente útil y que no debería modificarse?**

**15.5 Si pudieras cambiar una sola cosa del sistema actual, ¿qué cambiarías?**

**Gracias por completar este cuestionario.**

Las respuestas serán utilizadas para comparar cómo funciona actualmente el sistema frente a cómo necesita funcionar Global Tax Services. De esta comparación se determinarán las decisiones de producto, las funcionalidades pendientes, los cambios necesarios y las prioridades de desarrollo.