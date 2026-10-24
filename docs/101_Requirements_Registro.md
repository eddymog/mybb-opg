# Requisitos: Registro de aventuras (Tablón de aventuras)

## Revisión compacta de Narradores & Narrados (2026-10-08)

Estado: **implementada y verificada localmente; pendiente de despliegue**. Estas reglas sustituyen cualquier decisión
anterior sobre el contador de la pestaña o la composición visual de este panel.
No cambian permisos, estados ni el flujo de candidaturas.

1. `Propuestas para narrar` solo se renderiza cuando el usuario tiene al menos una
   invitación o candidatura relevante. No se muestra un bloque ni un estado vacío.
2. La insignia de `Narradores & Narrados` cuenta exclusivamente propuestas
   personales pendientes que requieren atención. Si el total es cero, se oculta.
   Los totales de narradores y aventuras sin narrador permanecen en sus cabeceras.
3. La oferta propia se presenta como una franja compacta de gestión con estado,
   vigencia, Discord, mensaje y acciones Renovar, Editar y Retirar. No compite
   visualmente con las ofertas de otros narradores.
4. Las ofertas ajenas se distribuyen en una cuadrícula de dos o tres columnas
   según el ancho disponible. Cada elemento conserva nombre, disponibilidad,
   aventuras en curso/finalizadas, mensaje, fecha y Discord. Discord ofrece una
   texto seleccionable, sin una acción adicional para copiarlo.
5. `Aventuras sin narrador` usa filas horizontales de ancho completo en escritorio
   y una columna en móvil. La fila prioriza solicitud, estado, tier, isla, día,
   creador, participantes, extracto y la acción `Me interesa narrar`.
6. Mientras no exista tema, el título es `Solicitud #ID`; no se presenta `Sin tema
   vinculado` como si fuera una anomalía.
7. Los datos incompletos no se muestran en este mercado y no bloquean una
   candidatura. El staff puede completar o corregir esos datos posteriormente.
8. `Me interesa narrar` conserva prioridad, pero usa el tamaño normal de acción
   operativa y no tipografía de titular.
9. Los contadores `N disponibles` y `N esperando narrador` comparten posición,
   tamaño y tratamiento visual junto al título de cada sección; son informativos.
10. Las secciones usan entre 20 y 28 px de separación. Los estados vacíos no crean
    huecos verticales. No se añaden filtros en esta fase.

## Crónicas autonarradas (2026-10-08)

Estado: **implementado y verificado; pendiente de despliegue**. Este apartado prevalece sobre cualquier
regla anterior que identifique una autonarrada por tier o por la coincidencia
accidental entre participante y narrador.

### Naturaleza y elegibilidad

1. Una crónica autonarrada es un tipo explícito de solicitud distinto de una
   aventura convencional.
2. Solo está disponible para una ficha aprobada cuyo nivel actual esté entre 1
   y 15, ambos incluidos.
3. El solicitante conserva el papel de jugador y es automáticamente el único
   participante y su propio narrador. No se pueden añadir participantes ni
   elegir otro narrador.
4. No usa tier, dificultad, inframundo, propuesta para narradores, ofertas de
   narración ni NPC oficiales. Las autonarradas nunca permiten NPC oficiales.
5. No consume los límites de aventuras solitarias, grupales o totales. Sus
   límites son exclusivamente los semanales definidos en este apartado.

### Solicitud y cupo semanal

6. En Solicitar aventura, las fichas elegibles pueden escoger entre una aventura
   convencional y una crónica autonarrada. El servidor vuelve a validar el tipo
   aunque el cliente oculte controles.
7. El formulario autonarrado mantiene isla, día y comentario público. Oculta
   participantes adicionales, narrador, tier, inframundo y propuesta para
   narradores.
8. El formulario informa, sin convertirlo en texto promocional, de las dos reglas
   operativas: un único post de al menos 800 palabras y un cupo por semana.
9. La semana se calcula en `Europe/Madrid` y comienza cada lunes a las 06:00,
   respetando automáticamente el horario de verano español.
10. `semana_cupo` se fija al enviar la solicitud. Una aprobación, publicación,
    revisión o entrega posterior no mueve la autonarrada a otra semana.
11. Cada ficha puede crear como máximo una solicitud autonarrada por
    `semana_cupo`.
12. Además, una ficha no puede crear otra autonarrada mientras conserve una
    anterior aprobada o pendiente que todavía no tenga tema publicado. Al
    publicarla se libera este bloqueo, aunque la nueva solicitud pertenezca a
    otra semana.
13. Los retrasos del staff no eliminan ni trasladan la recompensa reservada para
    la semana de solicitud.

### Aprobación, publicación y vínculo

14. Una autonarrada nueva entra En revisión y debe ser aprobada por el staff. No
    pasa por Buscando narrador.
15. El staff puede aprobarla o denegarla desde Solicitudes en
    `consola_aventuras.php`, con el mismo historial y motivo obligatorio de las
    demás solicitudes.
16. Una vez aprobada, aparece en el selector de aventuras al crear un tema solo
    para su propietario y únicamente mientras su nivel actual sea 15 o menor.
17. Alcanzar nivel 16 antes de publicar invalida la publicación como autonarrada:
    desaparece del selector y cualquier intento directo de vincularla se rechaza
    en el servidor. Una solicitud ya publicada conserva su vínculo aunque el
    personaje suba después.
18. El tema vinculado debe pertenecer al usuario, contener exactamente un post
    visible y ese post debe ser suyo.
19. El contenido debe alcanzar al menos 800 palabras después de retirar BBCode,
    HTML y espacios de formato. El conteo Unicode del servidor es el autoritativo.
20. Una solicitud solo puede tener un tema vinculado y un tema solo puede
    pertenecer a una autonarrada. La vinculación puede corregirse antes del pago
    si el tema anterior no ha sido recompensado.

### Entrega y recompensa

21. Las autonarradas usan una entrega reducida propia. Después de vincular y
    publicar el tema, el jugador debe abrir su solicitud, pulsar `Entregar
    autonarrada` e indicar si la reputación es positiva o negativa.
22. Todas entregan exactamente 30 XP, 2 Nikas, 5 puntos de reputación y 300.000
    Berries. No existen tiers de autonarrada.
23. No se aplican bonos de Contrabandista, Arqueólogo, oficio, inframundo,
    cantidad de jugadores, narrador ni ningún otro multiplicador.
24. El jugador elige al entregar si los 5 puntos corresponden a reputación
    positiva o negativa; la cantidad nunca cambia. Staff ve esa elección como
    dato de solo lectura y no puede sustituirla al pagar.
25. Solo después de esa entrega las autonarradas aparecen en la pestaña Entregas
    de `consola_aventuras.php`, identificadas como Autonarrada. Vincular el tema
    por sí solo no las envía a revisión. No se crea una pestaña principal adicional.
26. La tarjeta de entrega muestra personaje, nivel al solicitar, nivel al
    publicar, semana reservada, tema, autor, posts, palabras, validaciones y la
    recompensa fija. No muestra tier, porcentajes, destinatarios ni ajustes.
27. Antes de pagar, el servidor repite todas las validaciones de propiedad,
    publicación, nivel, posts, palabras, semana y pago previo.
28. `Entregar recompensa y finalizar` paga una sola vez, registra moderador y
    fecha, finaliza la solicitud, conserva el tema vinculado y escribe tanto el
    historial de la solicitud como el log general de entregas.
29. Dos clics o dos moderadores concurrentes no pueden duplicar ninguna moneda.
    Una interrupción deja un estado recuperable que permite completar únicamente
    los componentes todavía no aplicados.
30. Tras el pago, el tema se cierra automáticamente.

### Compatibilidad y presentación

31. `op/staff/entregar_recompensas.php` deja de aceptar pagos y redirige a
    `consola_aventuras.php?tab=entregas&tipo=autonarrada`, conservando marcadores
    antiguos sin mantener dos superficies de pago.
32. Mis aventuras separa visualmente las autonarradas de las aventuras activas
    que consumen cupo. Una autonarrada no aparece como aventura narrada sin límite.
33. Otras aventuras, el modo vista y el historial del personaje muestran el tipo
    Autonarrada de forma explícita.
34. Los registros antiguos detectados mediante la heurística de único jugador
    igual al narrador se revisan antes de migrarlos. Después de la migración, todas
    las consultas usan el tipo persistido y eliminan esa heurística.

## Revisión de experiencia del tablón (2026-10-07)

Esta revisión prevalece sobre los apartados anteriores cuando exista conflicto.
No cambia los límites, permisos de staff, recompensas ni estados persistidos de
las solicitudes; cambia cómo se organizan, se encuentran y se asigna un narrador.

### Activas e historial

- **Mis aventuras** se abre en `vista=activas`. Incluye estados 0 (en revisión),
  1 (aprobada/en curso) y 4 (buscando narrador).
- `vista=historial` incluye estados 2 (denegada) y 3 (finalizada). Existe una
  opción `vista=todas` para consultas excepcionales, pero no es la predeterminada.
- El resumen `Activas de mi personaje / Aventuras que narro` se mantiene como
  bloque compacto. Cada fila enlaza directamente al tema en una pestaña nueva
  cuando existe un vínculo visible y accesible; sin tema se muestra como texto
  plano. No vuelve a desplegar la descripción ni las acciones de la tarjeta.
- La cabecera del Tablón permite buscar otra ficha aprobada por nombre o FID
  mediante un botón desplegable inspirado en Bitácora. Su `modo_vista` es una
  consulta aislada y de solo lectura del mismo resumen: oculta
  las pestañas, Novedades y controles propios, y ofrece una acción para volver.
- Novedades solo se muestra en la pestaña Mis aventuras y se retira al cambiar a
  cualquier otra pestaña, también durante navegación HTMX.
- La lista principal respeta la vista elegida. En móvil no se cargan primero
  veinte aventuras históricas cuando el usuario solo necesita ver las actuales.
- **Otras aventuras** conserva todos sus estados visibles, pero ofrece los
  accesos `Actuales` (0 y 1) e `Historial` (2 y 3), con `Actuales` como inicio.

### Estadísticas informativas

- Cada contador de estado y, en Mis aventuras, cada contador de papel es un
  indicador estático. No aplica filtros, no tiene estado seleccionado y no usa
  `hover` ni `aria-pressed`.
- Cada vista solo presenta estados pertinentes: Activas muestra Total, En
  revisión, Aprobadas y Buscando narrador; Historial muestra Total, Denegadas y
  Finalizadas; Todas muestra el conjunto completo.
- Los conteos respetan la vista, búsqueda y filtros explícitos activos. Los
  controles de Vista, Estado y Papel son el único mecanismo que modifica el
  listado; cuando exista algún filtro se muestra `Limpiar filtros` junto a ellos.
- El bloque de papeles se rotula `Tus papeles` y advierte de forma accesible que
  una misma aventura puede contar en más de un papel.

### Etiquetas comprensibles

Los códigos de base de datos no cambian. Las etiquetas para jugadores son:

| Estado | Etiqueta principal | Explicación breve |
|---|---|---|
| 0 | En revisión | El staff todavía debe revisarla. |
| 1 | Aprobada | Puede jugarse, vincularse y entregarse. (Vincular también se permite en los estados 0 y 4.) |
| 2 | Denegada | Incluye el motivo del staff. |
| 3 | Finalizada | La aventura y sus recompensas se cerraron. |
| 4 | Buscando narrador | Acepta propuestas de narrador. |
| 5 | Pendiente de borrado | Solo visible en consola. |

Toda etiqueta concuerda con `aventura` o `solicitud`; no se muestran `Creado`,
`Aprobado` ni `Pendiente narrador` en la interfaz de jugadores.

### Estado navegable y persistente

- Pestaña, vista, estado, papel, búsqueda, página y densidad se representan en
  la URL: `tab`, `vista`, `estado`, `rol`, `q`, `pagina` y `densidad`.
- Cambiar un filtro reinicia `pagina=1` y usa historial del navegador. Recargar,
  compartir el enlace o usar Atrás/Adelante reproduce la misma vista.
- La densidad elegida se conserva en la URL. En móvil la presentación siempre es
  compacta, aunque el enlace indique expandida, sin cambiar el valor guardado.
- Tras filtrar o paginar, el foco se mueve al resumen de resultados. Al paginar
  también se lleva el listado al comienzo sin mover al usuario a la cabecera global.

### Límites antes del envío

- El indicador de cupo responde al papel seleccionado. Como jugador muestra el
  cupo del personaje y puede bloquear el envío; como narrador muestra `Narrar no
  consume el límite de tu personaje` y no bloquea por el cupo propio.
- Cada participante seleccionado muestra `S/T`, donde `S` son sus solitarias
  activas de un máximo de 3 y `T` sus activas totales de un máximo de 4.
- Al añadir o quitar participantes, cambiar de papel o cambiar entre aventura
  solitaria y grupal, el servidor recalcula la elegibilidad del conjunto.
- Si cualquier participante excedería un límite, se identifica a la persona y
  el motivo junto al selector, y `Enviar solicitud` queda deshabilitado.
- La comprobación transaccional al enviar y al aprobar se conserva: la
  comprobación anticipada mejora la experiencia, pero no sustituye la protección
  contra carreras.

### Asignación bilateral de narrador

- Una persona ajena no se convierte en narrador únicamente al marcar una
  casilla. Toda asignación entre dos usuarios requiere una propuesta pendiente y
  la aceptación de la contraparte.
- Si un jugador crea una solicitud con `Tengo narrador`, la solicitud queda en
  estado 4 y se crea una **invitación** para esa ficha. Pasa a estado 0 y asigna
  el narrador solo cuando la persona invitada acepta.
- En Aventuras sin narrador, `Me interesa narrar` crea una **candidatura**. El
  creador la acepta o rechaza; aceptar asigna el narrador y pasa la solicitud a
  estado 0 para revisión.
- Quien crea como `Soy el narrador` se asigna a sí mismo sin propuesta, porque
  ambas partes son la misma persona.
- Una solicitud puede tener varias propuestas pendientes, pero solo una puede
  aceptarse. La aceptación bloquea la solicitud, asigna al narrador y cancela el
  resto atómicamente.
- Invitaciones y candidaturas pendientes vencen a los 7 días. Quien las inició
  puede retirarlas; la contraparte puede rechazarlas. Aceptar, rechazar, retirar,
  cancelar por otra aceptación y vencer quedan auditados.
- El creador ve las propuestas en Mis aventuras. El narrador ve una sección
  `Propuestas para narrar` dentro de Narradores & Narrados.
- Las solicitudes que ya tienen narrador al aplicar la migración lo conservan y
  no requieren una aceptación retroactiva.

### Tarjetas contextuales

- Si hay tema vinculado, todas las tarjetas muestran `#petición · nombre del
  tema`; si no, muestran `#petición · Sin tema vinculado`.
- Creador, narrador y participantes enlazan al perfil del foro. Cuando el usuario
  puede contactar a la otra parte, también se ofrece `Enviar mensaje privado`.
- `Idea para narradores` se muestra completa en estado 4 y en propuestas
  pendientes. En otros estados queda dentro de Detalles y no ocupa la lectura
  principal.
- `Sin narrador` solo aparece cuando es una condición actual y útil. No se muestra
  en finalizadas ni en el bloque que ya se titula Aventuras sin narrador.
- `Datos incompletos` enumera visualmente `Falta isla`, `Falta día` o `Falta tier`;
  no depende de un `title` que no existe en pantallas táctiles.
- Motivos de denegación o renuncia y estados de entrega siempre permanecen
  visibles cuando afectan al siguiente paso.

### Paginación completa

- Mis aventuras y Otras aventuras conservan 20 resultados por página. Ofertas
  de narrador conserva 20 y Aventuras sin narrador conserva 10 por carga.
- Los listados paginados muestran primera, anterior, páginas cercanas, siguiente
  y última. No renderizan cientos de números a la vez.
- El directorio de narradores debe exponer todas las páginas que ya calcula el
  dominio; ninguna oferta vigente puede quedar inaccesible después de la 20.
- `Ver más` de aventuras sin narrador conserva su comportamiento incremental y
  la URL indica hasta qué página se cargó para que Atrás/recarga sean coherentes.

### Notificaciones

- El sistema genera una notificación para los usuarios afectados cuando ocurre:
  invitación o candidatura nueva; aceptación, rechazo, retirada o vencimiento;
  aprobación o denegación; salida de narrador; entrega rechazada; y recompensas
  entregadas.
- Los destinatarios se deduplican por evento y UID. La persona que ejecuta la
  acción no recibe una copia redundante.
- La notificación incluye solicitud, acción, actor y enlace directo. Nunca
  incluye nota privada de staff ni información que el destinatario no pueda ver.
- La acción de negocio se confirma aunque el canal externo de notificación falle.
  El aviso queda pendiente para reintento y no se envía dos veces.
- El canal mínimo es un aviso dentro del tablón. Puede integrarse con MyAlerts o
  mensaje privado, pero la ausencia de MyAlerts no impide el funcionamiento.
- Las novedades se muestran como un panel persistente debajo de las pestañas,
  no dentro de un modal. Las pendientes quedan visibles y las leídas se
  conservan en un historial reciente desplegable.
- Una oferta de narrador avisa a su propietario cuando faltan 3 días para vencer.

### Historial para usuarios

- Creador, narrador y participantes pueden abrir `Historial` en su tarjeta.
- La línea temporal muestra fecha, actor y descripción segura de: creación,
  edición, propuestas de narrador, asignación o salida, aprobación o denegación,
  vinculación, entrega, rechazo de entrega y recompensas entregadas.
- No muestra notas de staff, payloads técnicos, importes internos no entregados,
  direcciones IP ni acciones de moderación ajenas al usuario.
- Las filas anteriores a la auditoría se presentan como `Sin historial anterior
  a la migración`; no se inventan eventos.

### Participantes como relación

- Se crea `op_aventuras_participantes` como fuente indexable de participantes,
  con una fila por participante. `jugadores_json` se conserva durante la
  transición como snapshot de compatibilidad.
- La relación guarda `id`, `peticion_id`, `uid` anulable, `nombre_snapshot`,
  `orden` y fecha de alta; tiene unicidad por `(peticion_id, orden)` y, para las
  fichas identificadas, por `(peticion_id, uid)`, además de índice
  `(uid, peticion_id)`.
- Una entrada antigua sin UID se conserva con `uid=NULL`: puede mostrarse, pero
  no concede permisos ni consume cupo hasta que staff resuelva su identidad.
- Listados, estadísticas, límites y permisos consultan la tabla relacional. Las
  escrituras actualizan relación y snapshot dentro de la misma transacción.
- La migración rellena la tabla desde JSON válido, conserva entradas sin UID,
  registra JSON o estructuras inválidas para revisión y compara conteos antes de
  convertirla en fuente de verdad.
- Mientras la calculadora antigua pueda escribir, sus rutas sincronizan la
  relación después de cada cambio de participantes; además se mantiene fallback
  y reparación por lotes. No se retira el fallback hasta desactivar la escritura
  legacy y verificar un último checksum.

## Revisión visual del tablón (2026-10-06)

La especificación detallada y el orden de implementación están en
[102_TablonDiseno.md](102_TablonDiseno.md). Esta revisión añade:

- Tarjetas compactas con comentario público recortado a tres líneas y expansión
  accesible; isla, fecha y tier forman una fila de metadatos. Los ausentes se
  agrupan como `Datos incompletos`.
- Una acción principal contextual por tarjeta y el resto en `Más acciones`, sin
  cambiar permisos, confirmaciones ni reglas de negocio.
- La cabecera del tablón conserva el banner principal y el bloque editorial del
  foro, sin relleno vertical específico que deje franjas vacías de fondo.
- Las ofertas de narrador admiten frecuencia, tiers preferidos y estilo. Son
  opcionales para mantener compatibles las ofertas existentes.
- El total sin narrador se integra junto al título, los estados usan colores
  semánticos con etiqueta textual y Mis/Otras permiten densidad Compacta o
  Expandida. Compacta presenta dos columnas en escritorio y descripciones de una
  línea; Expandida usa todo el ancho y muestra la descripción completa.

La ampliación de ofertas requiere ejecutar
`aventuras_tablon_diseno_migration.sql` antes de desplegar el PHP actualizado.

## Revisión de interfaz (2026-10-05)

Esta revisión sustituye las referencias posteriores a tres pestañas y al mercado
dentro de Otras aventuras:

- Cuatro pestañas, en orden: **Mis aventuras**, **Solicitar aventura**,
  **Narradores & Narrados** (`tab=narradores`), **Otras aventuras**.
- Narradores & Narrados reúne Narradores disponibles y Aventuras sin narrador.
  Otras aventuras contiene solo el listado ajeno de estados 0, 1, 2 y 3.
  El contador de pendientes pertenece a la nueva pestaña.
- Mis y Otras muestran, debajo de las pestañas y antes del encabezado del listado,
  Total, En revisión, Buscando narrador, Aprobadas, Denegadas y Finalizadas.
  Las cifras abarcan el conjunto completo que cumple los filtros, no solo la
  página. Solo Mis aventuras añade conteos separados como narrador y jugador, en
  un bloque visualmente separado; Otras aventuras dedica todo el ancho a estados.
  En móvil, los roles de Mis forman una franja independiente bajo los estados.
- Ambos listados tienen **20 aventuras por página**, conservan filtros y muestran
  total de resultados y página actual. Estadísticas y listado se actualizan juntos.
- Mis aventuras incluye búsqueda reactiva por número de solicitud (`#850`). Otras
  aventuras incluye búsqueda reactiva por número o por nombre de personaje; el
  nombre busca sus participaciones y sus aventuras narradas.
- Mis aventuras separa **Activas de mi personaje** (solitarias / 3, total / 4)
  de **Aventuras que narro** (conteo independiente, sin límite). Solo las primeras
  consumen cupo. Ambas muestran el listado exacto; las del personaje se muestran
  separado en solitarias y grupales, con título del tema vinculado, fecha de
  registro, fecha de rol cuando exista, estado y enlace a la solicitud. Sin tema,
  se muestra el ID y "Sin tema vinculado". Cuenta
  participación como jugador en estados 0, 1 y 4, excluye las narradas y es
  independiente de los filtros. Se mantiene el contador compacto en Solicitar.
  Cada aventura se presenta como una fila compacta; las narradas usan dos columnas
  en escritorio y una en móvil, sin repetir el contador bajo el encabezado.
- Al seleccionar narrador, desaparece su buscador. Quitar el narrador permite
  elegir otro; solo hay uno. Con al menos un participante, su buscador se abre
  mediante **Añadir participante** y se cierra tras cada selección. Sin
  participantes, se muestra directamente. Día tiene placeholder **1-90**.
- El narrador asignado y cualquiera de los participantes pueden vincular una
  aventura normal mientras la solicitud siga viva (en revisión, aprobada o buscando
  narrador): no hace falta que esté aprobada, basta con que exista y tenga número.
  Una solicitud denegada, finalizada o eliminada ya no admite vincular, y una
  autonarrada sigue pidiendo estar aprobada. El buscador de `newthread.php` ofrece
  esas aventuras a sus jugadores y narrador, indicando el estado si no están
  aprobadas y si ya tienen tema (en ese caso el tema nuevo sustituye al anterior:
  revincular). La entrega exige aprobación y puede hacerla el narrador o cualquiera
  de los jugadores de la aventura: quien envía marca una casilla obligatoria
  ("He confirmado con el resto del grupo...") y la entrega registra quién la envió
  (`enviado_por`), mientras que el narrador cobrado sigue siendo el de la aventura.
  Las autonarradas siguen siendo cosa de su narradora.
  Un tema vinculado a una solicitud denegada o eliminada queda libre para otra.
- La creación valida límites antes de insertar, para cada participante: no admite
  una cuarta solitaria ni una quinta activa total. Con tres solitarias admite una
  grupal si queda cupo total. Los envíos simultáneos no pueden consumir el mismo cupo.
- Comentario público obligatorio: una descripción breve de la aventura, hasta
  1000 caracteres. La idea opcional describe cómo quiere ser narrado el jugador,
  qué desea que pase y qué narración o narrador busca. Buscar isla va arriba y
  el chip de Isla debajo, alineando el buscador con los campos Día y Tier.
- El formulario se organiza en tres secciones sin tarjetas anidadas: Personas,
  Datos de la aventura y Descripción. La propuesta para posibles narradores va
  después del comentario público y solo aparece cuando corresponde.
- El rediseño sigue `style.md`: marco de tres fondos, tokens compartidos,
  `.barra-op`, Inter para cuerpo y controles, `moonGetHeavy` para títulos y cifras,
  bandas sin tarjetas anidadas, controles accesibles y adaptación a 375px.

Aceptación adicional: verificar cuatro pestañas y rutas directas; estadísticas
con filtros, sin resultados y con más de 20 registros; activas independientes
del filtro; selección, retirada y nueva selección de narrador/participantes;
vinculación por participante y rechazo de ajenos; ausencia de desbordes en móvil.

## 1. Propósito

La Calculadora de Tiers (`op/Calculadora_Tiers.php`) cumple dos funciones que
nacieron juntas: calcular la dificultad de un encuentro y servir de registro
oficial de solicitudes de aventura (crear, aprobar, asignar narrador, vincular
la aventura, finalizar). Con el tiempo el registro pasó a ser la parte
importante, pero sigue viviendo dentro de un único archivo PHP de 1273 líneas,
un template de 1149 líneas con CSS y estilos en línea, y tres archivos JS en
`jscripts/calculadoraDificultad/`.

Este documento define el rediseño de esa herramienta como el **Tablón de
aventuras**. La versión nueva se construye en una página separada,
`/op/tablon_de_aventuras.php`, mientras la calculadora actual sigue funcionando
sin cambios. Cuando la nueva esté validada, la antigua se retira o redirige.

La versión nueva **no calcula dificultad**: desaparecen los enemigos, el ratio
de poder y la etiqueta de dificultad. Se conserva el **tier de la aventura**, que
el usuario elige al crearla y que determina cuántos posts se esperan y cuánto se
paga al entregarla. Es un registro de quién juega, quién narra, dónde y cuándo
ocurre la aventura, y de cómo se cierra y se recompensa.

## 2. Decisiones tomadas

- La página nueva es `/op/tablon_de_aventuras.php`. El nombre usa
  `snake_case` en minúsculas, igual que `objetos_armas_todas.php`,
  `tecnicas_belicas_todas.php` o `ficha_crear.php`. El template MyBB se llama
  `op_tablon_de_aventuras`, el JS vive en `jscripts/tablon_aventuras/` y las
  acciones AJAX se atienden en el mismo archivo con `?action=`.
- Es un **rediseño completo**: no se reutiliza HTML, CSS ni JS de la calculadora
  actual (`op_Calculadora_Tiers.html` ni `jscripts/calculadoraDificultad/`). Los
  templates y estilos se escriben de cero siguiendo [style.md](style.md).
- La interfaz usa **HTMX**: el servidor responde con fragmentos HTML y el
  navegador los inserta en la página. El JavaScript propio se limita a mejoras
  progresivas de interacción, como foco y confirmaciones; las reglas y cálculos
  permanecen en el servidor.
- La página de jugadores tiene **cuatro pestañas, en este orden: Mis aventuras,
  Solicitar aventura, Narradores & Narrados y Otras aventuras**. La pestaña inicial es Mis aventuras. No
  existe una pestaña "Registro": sus dos mitades son pestañas independientes. La
  pestaña Consola no existe en ella, para nadie. Toda la lógica de gestión pasa
  a `/op/staff/consola_aventuras.php`, enlazada desde la consola de mods
  (sección 7.4).
- El creador de una solicitud **puede modificarla mientras no esté aprobada**
  (sección 6.8). El staff no corrige participantes ni narrador: lo hace el
  creador.
- Hay dos tipos de narrador: el **narrador usuario**, cualquier usuario con ficha
  que narra una aventura, que no tiene acceso a la consola; y el **narrador del
  staff** (`is_narra`), que tiene acceso a la consola de aventuras como el resto
  del staff (sección 7.4).
- La aventura se **entrega desde el Tablón** (sección 6.9): el narrador rellena
  el formulario que antes se publicaba en el foro, y el staff paga las recompensas
  a los participantes y al narrador en una **página nueva y rediseñada,
  `/op/staff/aventuras_recompensas.php`**, que sigue la lógica de
  `recompensasAventuras.php` (la herramienta real de pago de aventuras). La
  entrega guarda quién era el narrador asignado al enviarla; no se exige ni se
  interpreta una etiqueta `[Narrador]` dentro de los posts.
- La pestaña Narradores & Narrados funciona como un **mercado de oferta y demanda de
  narración** (sección 6.7): destaca las aventuras sin narrador y lista a los
  usuarios que se ofrecen a narrar.
- Desaparece el cálculo de dificultad: no hay enemigos, ratio, etiqueta ni
  detalle por enemigo, ni en la interfaz, ni en el servidor, ni en los
  endpoints. **El tier se conserva**: se elige al crear la aventura (del 1 al 10)
  y es el que se usa por defecto al entregar las recompensas (sección 6.9).
- La solicitud la puede crear un jugador o un narrador (sección 6.2).
- La isla y la fecha son campos propios de la solicitud (sección 6.3), y el
  comentario público tiene un límite de 1000 caracteres.
- `Calculadora_Tiers.php` no se modifica durante el desarrollo, salvo el
  arreglo de seguridad de la sección 9, que no puede esperar.
- Los estados y sus códigos numéricos actuales (0 a 5) se mantienen para no
  romper datos existentes.
- La lógica compartida (límites de aventuras activas, catálogo de estados,
  transiciones, validación de fecha e isla) pasa a una librería reutilizable en
  `inc/plugins/lib/`, como ya se hizo con `belico_calculos.php`, para que el
  Registro y la consola no dupliquen reglas.

## 3. Alcance

La primera versión debe permitir:

- crear una solicitud de aventura con participantes, narrador, isla, día y
  comentario público;
- listar y filtrar las solicitudes con su estado;
- que un narrador se postule a una solicitud sin narrador;
- que cualquier usuario con ficha se ofrezca como narrador disponible, y que
  quienes buscan narrador vean quién está disponible (sección 6.7);
- que el narrador o cualquiera de los participantes vincule la URL del hilo de
  la aventura;
- que el creador pida el borrado de su solicitud;
- que el creador modifique su solicitud mientras no esté aprobada;
- aplicar y mostrar los límites de aventuras activas por jugador;
- que el narrador entregue la aventura terminada (tema, resumen y reputación) y
  que el staff pague las recompensas en la página nueva `aventuras_recompensas.php`,
  que sigue la lógica de `recompensasAventuras.php` (sección 6.9);
- que staff, mods y narradores del staff aprueben, denieguen, finalicen y
  comenten desde la consola de mods, no desde la página de jugadores;
- que la consola tenga una cola de revisión, el historial de cada solicitud y
  moderación de las ofertas de narrador.

Fuera de alcance: cambiar los montos de las recompensas, cualquier cálculo de
dificultad, crear hilos de foro
automáticamente, notificaciones por Discord, recompensas automáticas al
finalizar y migrar los datos a otra tabla.

## 4. Modelo de datos

Se reutilizan las tablas actuales. No se renombran columnas ni se borran datos.

| Tabla | Responsabilidad |
|---|---|
| `mybb_op_peticionAventuras` | Solicitud: participantes, narrador, isla, día, estado, comentarios y URL de la aventura. |
| `mybb_op_peticionAventuras_meta` | Nota de staff y comentario público con autor y fecha de la última edición. |
| `mybb_op_fichas` | Búsqueda de personajes por `nombre` y `apodo`. Columna legacy `aventurasActivas`. |
| `mybb_forums` | Origen de la lista de islas (sección 6.3). |
| `mybb_op_aventuras_entregas` | La entrega de una aventura terminada: tema, resumen, reputación y estado de la revisión (sección 6.9). Tabla nueva. |
| `mybb_op_aventuras_entregas_pagos` | Un registro por destinatario de las recompensas de una entrega, para pagar una sola vez a cada uno (sección 6.9). Tabla nueva. |
| `mybb_op_narradores_disponibles` | Usuarios que se ofrecen como narradores (sección 6.7). Tabla nueva. |

Campos que usa la versión nueva en `mybb_op_peticionAventuras`: `uid`
(creador), `narrador_uid`, `narrador_fid`, `narrador_nombre`, `jugadores_json`,
`estado`, `comentario_publico`, `comentario_staff`, `aventura_url`,
`inframundo`, `created_at`.

Campos que la versión nueva escribe además de los anteriores:
`tier_seleccionado` (el tier de la aventura, de 1 a 10).

Campos que la versión nueva deja de escribir: `nivel_promedio`, `dificultad_texto`, `dificultad_color`, `ratio_poder`,
`descripcion_tier`, `enemigos_json` y `detalles_json`. Las filas existentes
conservan sus valores. Como varias de esas columnas son `NOT NULL` sin valor
por defecto, la migración les asigna un valor por defecto (0 o cadena
vacía) o las permite nulas, para que el insert nuevo no tenga que llenarlas.

Cambios en la migración, todos aditivos:

- Columna `isla_fid` (`smallint unsigned`, nullable): `fid` del foro de la isla.
- Columna `dia` (`tinyint unsigned`, nullable): día de la estación, de 1 a 90.
- Columna `motivo_denegacion` (`varchar(500)`, nullable): motivo escrito al
  denegar.
- Columna `idea_narrador` (`varchar(500)`, nullable): nota con la que el creador
  "vende su idea" cuando la solicitud no tiene narrador (sección 6.2).
- Columna `motivo_renuncia` (`varchar(500)`, nullable): motivo escrito por el
  narrador que se salió de la aventura (sección 6.2).
- Columna `editado_at` (`int unsigned`, nullable): fecha de la última edición
  hecha por el creador (sección 6.8).
- Tabla `mybb_op_narradores_disponibles` (`uid` clave primaria, `discord`
  `varchar(64)`, `mensaje` `varchar(200)`, `created_at`, `updated_at`): una fila por usuario que se
  ofrece como narrador. Es una tabla nueva y no toca los datos existentes.
- Índices en `estado`, `narrador_uid`, `isla_fid` y `created_at`.
- Una tabla de auditoría `mybb_op_audit_aventuras` (`id`, `peticion_id`, `uid`,
  `accion`, `detalle_json`, `fecha`), porque hoy ningún cambio de estado deja
  rastro.
- Las dos estructuras que hoy se crean en runtime con `CREATE TABLE IF NOT
  EXISTS` y `ALTER TABLE ... ADD COLUMN` dentro de las peticiones (`_meta` e
  `inframundo`) pasan a un archivo de migración SQL en `docs/`, como el resto
  de migraciones del repo, y se eliminan del código de las peticiones.

`comentario_publico` ya es `text`, que admite 1000 caracteres: solo cambia la
validación.

Las solicitudes anteriores no tienen `isla_fid` ni `dia`: el Registro las
muestra sin esos datos y sin ningún dato de dificultad.

Dentro de `jugadores_json`, el campo `uid` guarda en realidad el `fid` de la
ficha (que es igual al `uid` del usuario). Se mantiene ese formato. La versión
nueva guarda solo `uid` y `nombre` por participante; el `nivel` ya no se usa.

### 4.1 Estados

| Código | Clave | Etiqueta | Significado |
|---|---|---|---|
| 0 | `creado` | Creado | Enviada, con narrador, pendiente de revisión. |
| 1 | `aprobado` | Aprobado | Aventura en curso. Cuenta para el límite de activas. |
| 2 | `denegado` | Denegado | Rechazada por staff, mod o narrador autorizado, con un motivo obligatorio. Estado final. |
| 3 | `finalizado` | Finalizado | Aventura terminada. Deja de contar como activa. |
| 4 | `pendiente-narrador` | Pendiente narrador | Creada sin narrador, a la espera de que alguien se postule. |
| 5 | `pendiente-borrado` | Pendiente de borrado | El creador pidió ocultarla; staff decide. |

Transiciones válidas:

- Crear con narrador: 0. Crear sin narrador: 4.
- 4 a 0 cuando un narrador se postula.
- 0 a 4 y 4 a 0 cuando el creador edita la solicitud y quita o pone narrador
  (sección 6.8).
- 0 o 1 a 4 cuando el narrador asignado deja de narrar (sección 6.2). La
  aventura vuelve a quedar sin narrador; si estaba aprobada, pierde la
  aprobación.
- 0 a 1 y 0 o 4 a 2 desde la consola de aventuras. Denegar exige escribir el motivo.
  Denegar un 4 permite a un moderador limpiar una solicitud que nadie narra.
- Un moderador o staff también puede denegar (estado 2) una aventura ya
  aprobada (estado 1), por ejemplo si el acuerdo entre narrador y jugadores no
  era real. Exige motivo igual que cualquier denegación.
- 2 es un estado final: una solicitud denegada **no se reabre**. Los usuarios
  leen el motivo en el Registro y envían una solicitud nueva.
- 1 a 3 cuando se entregan las recompensas de la aventura (sección 6.9), o al
  finalizar manualmente desde la consola una aventura sin entrega pendiente. Cualquier estado a 5 por el creador.
- 5 a borrado definitivo, solo staff.

Cualquier transición fuera de esta lista se rechaza en el servidor.

### 4.2 Compatibilidad con las solicitudes existentes

Todas las solicitudes hechas en la calculadora actual siguen siendo válidas en
el sistema nuevo y conservan su número. Las reglas son:

- **Mismo número, misma tabla.** El número de una solicitud es su `id` en
  `mybb_op_peticionAventuras`. La versión nueva usa esa tabla, no copia ni
  renumera filas, no reinicia el `AUTO_INCREMENT` y no borra nada. Una
  solicitud nueva continúa la secuencia. Los números que ya se citaron en
  hilos, mensajes o por el staff siguen apuntando a la misma solicitud.
- **Migración no destructiva.** La migración solo agrega columnas (`isla_fid`,
  `dia`, `motivo_denegacion`) con valor nulo, agrega índices y da valores por
  defecto a columnas existentes. No modifica ni elimina datos. Antes de
  aplicarla se hace una copia de la tabla y de `_meta`, y después se comprueba
  que el número de filas y el conjunto de `id` son idénticos.
- **Se conservan los datos antiguos.** Enemigos, tier, nivel promedio,
  dificultad, ratio y detalle siguen guardados en la fila, pero el sistema
  nuevo no los muestra ni los usa. No se borran, por si hace falta consultarlos.
- **Estados.** Los códigos 0 a 5 y su significado no cambian, así que cada
  solicitud conserva el estado que tenía.
- **Lectura sin isla ni día.** Una solicitud antigua se muestra en el Registro
  con "sin isla" y "sin día", sin ningún dato de dificultad. Tiene todos los
  demás datos: creador, narrador, participantes, comentario público, URL de la
  aventura e inframundo.
- **Comentario público.** Si `comentario_publico` está vacío, se usa el
  comentario de `mybb_op_peticionAventuras_meta`, igual que hace la calculadora
  actual. Los comentarios antiguos se muestran completos aunque excedan 1000
  caracteres: el servidor de la calculadora actual no validaba el límite en
  `save`, solo el formulario (500). El límite de 1000 aplica a lo que se escriba
  desde ahora.
- **Participantes.** El `jugadores_json` antiguo puede traer `nivel` y entradas
  sin `uid` (jugadores que no se resolvieron a una ficha). Se leen igual: se
  muestra el nombre y se ignora el nivel. Una entrada sin `uid` no cuenta para
  los límites de ningún jugador, porque no se puede asignar a una ficha.
- **Límites sobre solicitudes antiguas.** Los estados 0, 1 y 4 antiguos cuentan
  como activos para la regla de 3 solitarias y 4 en total. La regla nueva no se
  aplica con efecto retroactivo: si un jugador ya excede el límite por
  solicitudes aprobadas con la regla vieja, esas solicitudes no se tocan ni se
  deniegan, pero no se podrá aprobar una nueva hasta bajar del límite.
- **Denegadas antiguas.** Las solicitudes antiguas en estado 2 quedan como
  están, sin `motivo_denegacion` (se muestra "sin motivo registrado"). Pasan a
  ser estado final como el resto; ya no se pueden reabrir.
- **Completar datos faltantes.** En la consola de aventuras, el staff puede
  editar `isla_fid` y `dia` de una solicitud antigua o nueva. No es obligatorio
  hacerlo.
- **Enlaces antiguos.** `Calculadora_Tiers.php?peticion=N` redirige a la
  solicitud N en el sistema nuevo cuando la calculadora se retire.
- **Convivencia.** Mientras ambas páginas funcionen, las solicitudes que cree la
  calculadora actual (con enemigos, sin isla ni día) siguen apareciendo en el
  Registro nuevo y en la consola, con las mismas limitaciones de lectura.

## 5. Fecha de la aventura

La fecha se compone de tres partes y sigue el calendario de
[op/cronologia.php](../op/cronologia.php):

- **Año:** siempre 725, constante del servidor (`CRON_ANO_DEFECTO`).
- **Estación:** siempre Verano, constante del servidor.
- **Día:** número entero de 1 a 90, que el usuario elige en un input numérico
  (`CRON_DIAS` en cronologia.php).

El usuario solo elige el día. El servidor valida que sea entero entre 1 y 90 y
rechaza cualquier otro valor. Las constantes de año, estación y número de días
viven en la librería compartida y `cronologia.php` las reutiliza o las iguala,
de modo que la fecha de la solicitud sea compatible con `year`, `estacion` y
`day` de `mybb_threads` cuando el narrador cree el hilo de la aventura.

Cuando el calendario deje de ser "siempre verano de 725", el cambio es de
constantes y no de estructura.

## 6. Reglas de negocio

### Revisión de entrega y recompensas (2026-10-08)

- El narrador de una entrega es el usuario asignado a la solicitud en el momento
  de enviarla. Se conserva en la propia entrega y es la fuente de verdad para el
  rol y el cálculo, aunque la asignación de la solicitud cambie después.
- Los mensajes del tema no necesitan la etiqueta `[Narrador]`; el sistema no la
  busca ni bloquea el pago por su ausencia.
- La página de recompensas presenta primero el nombre del tema y deja los números
  de entrega y solicitud como metadatos secundarios.
- La revisión se organiza en cuatro secciones compactas: entrega, criterios,
  destinatarios y validación/acciones. Ajustar un criterio o destinatario actualiza
  la vista previa automáticamente y existe además una acción explícita para
  actualizarla.
- Advertencias y bloqueos se muestran una sola vez, distinguiendo qué impide pagar.
  Cuando el pago está deshabilitado se explica la causa junto al botón.
- Antes de pagar se abre una confirmación propia con el alcance de la acción. La
  barra de acciones permanece visible al recorrer listas largas.
- En móvil, cada destinatario se presenta como un bloque legible, sin depender de
  una tabla horizontal.

### 6.1 Límite de aventuras activas

> **Estado actual (octubre de 2026): límites desactivados.** La constante
> `OPG_AV_LIMITES_ACTIVOS` de `inc/plugins/lib/aventuras.php` vale `false`: no hay
> máximo de solitarias ni de aventuras activas, y se pueden enviar, editar y aprobar
> todas las solicitudes que se quieran. Las aventuras se siguen contando para mostrar
> `N solitarias` y `N en total`, sin tope. Las reglas de este apartado, y las cifras
> `S/3` y `T/4` que aparecen en el resto del documento, describen el comportamiento con
> `OPG_AV_LIMITES_ACTIVOS = true` (se conservan para poder reactivarlo). El sistema
> antiguo (`op/Calculadora_Tiers.php`) mantiene sus propios límites (5 y 1 grupal) y no
> se tocó.

- Una aventura está activa en los estados 0, 1 y 4.
- Una aventura es **solitaria** si tiene 1 participante y **grupal** si tiene 2
  o más. El narrador no cuenta como participante.
- Cada jugador puede tener como máximo **3 aventuras solitarias** activas.
- El máximo total es **4** aventuras activas, entre solitarias y grupales.
- Las grupales no tienen tope propio: puede tener 4 grupales y ninguna
  solitaria. Solo las solitarias están limitadas a 3.
- Combinaciones válidas: 3 solitarias + 1 grupal, 2 solitarias + 2 grupales,
  1 solitaria + 3 grupales, 4 grupales. Inválidas: 4 solitarias, y cualquier
  combinación con más de 4 en total.
- Quien actúa como narrador de una aventura no la cuenta como activa propia.
- La regla anterior en el código (5 activas más 1 con una grupal, máximo 6) no
  es la regla vigente y se reemplaza.
- El límite se valida en el servidor al crear y al aprobar. Al aprobar se excluye
  la solicitud actual del conteo. El indicador no sustituye la validación del envío.
- Hoy el conteo del indicador (`action=list`) no excluye al narrador y el de la
  aprobación sí. La versión nueva usa una sola función para ambos.
- La columna `aventurasActivas` de `mybb_op_fichas` es legacy (se incrementa con
  tope 4, que coincide con el total vigente pero no distingue solitarias de
  grupales). La versión nueva calcula el conteo a partir de las solicitudes y
  deja de escribir en esa columna. Debe confirmarse que ningún otro módulo la
  lee antes de retirarla.

### 6.2 Quién crea la solicitud, participantes y narrador

Hay dos formas de crear una solicitud. En ambas el servidor determina quién es
el creador (`uid`) por la sesión, nunca por un valor del cliente.

**A. La crea el narrador.**

- El narrador queda asignado a sí mismo: `narrador_uid` = `narrador_fid` = su
  propio `uid`.
- Debe elegir **al menos un participante** con un typeahead de fichas. No puede
  crearla sin participantes.
- No puede incluirse a sí mismo como participante de su propia aventura.
- Estado inicial: 0.

**B. La crea un jugador.**

- El creador se incluye a sí mismo como participante y puede añadir otros
  participantes con el mismo typeahead.
- Indica si **tiene narrador** o **no tiene narrador**.
  - Si tiene, lo nombra con un typeahead de fichas: es el narrador con el que ya
    se puso de acuerdo o al que desea invitar. No puede ser alguno de los
    participantes. La solicitud nace en estado 4 y la invitación debe aceptarse;
    solo entonces pasa al estado 0.
  - Si no tiene, aparece un campo para escribir una **nota "vendiendo su idea"**
    de hasta 500 caracteres (opcional, pero se recomienda), que verán los
    posibles narradores en el bloque de aventuras sin narrador. Estado inicial 4.
  - Si indica que tiene narrador, la nota no se guarda.

Reglas comunes:

- Los participantes se eligen siempre desde el typeahead de fichas existentes
  (por `nombre` o `apodo`); no se acepta texto libre sin ficha. Un participante
  no puede repetirse.
- La solicitud se rechaza si no cumple ninguna de las dos formas, es decir, si
  el creador no es ni narrador asignado ni participante.
- Nadie puede postularse a narrar una solicitud de la que es participante ni de
  la que es creador.
- Solo se puede postular a solicitudes en estado 4 sin narrador asignado.
- `Me interesa narrar` crea una candidatura pendiente y puede incluir un mensaje
  breve. No asigna al narrador directamente. La ventana muestra quién creó la
  solicitud, participantes, isla y día; el creador acepta o rechaza después.
- Una solicitud en estado 4 no se puede aprobar hasta que tenga narrador.
- **El narrador puede salirse.** El narrador asignado a una solicitud en estado 0
  o 1 puede dejar de narrarla con la acción "Dejar de narrar", que pide una
  confirmación en una ventana ("¿Quieres dejar de narrar esta aventura? Volverá
  a la lista de aventuras sin narrador"). El servidor exige esa confirmación.
  - La ventana pide un **motivo obligatorio** de hasta 500 caracteres. El
    servidor rechaza una salida sin motivo o con motivo vacío.
  - El motivo se guarda en `motivo_renuncia` y es visible en la tarjeta de la
    solicitud mientras esté sin narrador, tanto para los participantes como para
    los posibles narradores del bloque de aventuras sin narrador ("El narrador
    anterior se salió: ..."). Cuando otro narrador se postula, el campo se vacía
    (el motivo sigue en la auditoría).
  - La solicitud pasa a estado 4: se borra el narrador asignado y vuelve al
    bloque de aventuras sin narrador de Narradores & Narrados, donde otro narrador
    puede postularse (con su confirmación).
  - Si estaba aprobada (estado 1), pierde la aprobación: el nuevo narrador, al
    postularse, la deja en estado 0 y necesita una nueva aprobación desde la
    consola. Los participantes la siguen contando como activa (estado 4).
  - Se conserva la URL de la aventura si ya estaba vinculada (el hilo ya existe);
    el nuevo narrador puede reemplazarla al vincular.
  - Solo puede salirse el narrador asignado. No se puede dejar de narrar una
    aventura finalizada, denegada ni pendiente de borrado.
  - Se avisa automáticamente al creador y a los participantes mediante la
    bandeja interna. Discord puede seguir usándose como canal complementario.
  - Si el narrador es además el creador (forma A), tras salirse la solicitud
    queda en estado 4 y sigue siendo suya en Mis aventuras, pero no puede
    postularse de nuevo a ella como narrador (regla de creador). Puede pedir su
    borrado.
  - La acción queda en la auditoría con el narrador que se salió y el motivo.
- Cualquier usuario con ficha puede ser narrador; no se exige `is_narra`.
- Nombrar a un narrador ajeno al crear o editar requiere su aceptación en el
  sistema mediante una invitación. La única asignación inmediata es la de quien
  crea la solicitud con `Soy el narrador`.
- No hay límite de participantes por solicitud.

### 6.3 Isla, día y comentario público

- **Isla (obligatoria).** Un desplegable con typeahead que lista los foros de
  `mybb_forums` cuyo padre directo (`pid`) es 15, 16, 17 o 18. Los subforos de
  esos foros no cuentan. El servidor valida que el `fid` enviado cumpla esa
  condición y que el foro esté activo (`active = 1`), y lo guarda en `isla_fid`.
  El nombre se obtiene en cada lectura desde `mybb_forums.name`, no se copia.
- **Día (obligatorio).** Entero de 1 a 90 (sección 5).
- **Tier (obligatorio).** Entero de 1 a 10, que se elige al crear la solicitud y
  se guarda en `tier_seleccionado`. Fija cuántos posts válidos se esperan de cada
  participante (de 4 en el tier 1 a 12 en el tier 10, sección 6.9) y las
  recompensas por defecto. El formulario muestra esa cifra junto al selector.
  Se puede cambiar editando la solicitud mientras no esté aprobada (sección 6.8)
  y, opcionalmente, el staff puede cambiarlo al entregar la aventura.
- **Comentario público.** Descripción breve obligatoria de hasta 1000 caracteres. Se valida el
  límite en el servidor y se escapa al renderizar. Reemplaza al comentario de
  500 caracteres (al crear) y 150 (al editar) del sistema actual.
- **Nota de staff.** Solo se muestra y se edita en la consola de aventuras; el
  Registro de jugadores no la devuelve. El límite es de **200 caracteres**
  (hoy son 20, demasiado corto para una nota).
- Todo texto libre se escapa al renderizar. No se acepta HTML.

### 6.4 Vinculación de la aventura

- El narrador asignado o cualquiera de los participantes de la solicitud puede
  vincular el tema de la aventura, y solo con la solicitud aprobada (estado 1).
  Ser creador por sí solo no concede este permiso. Es la acción "Vincular aventura" de la tarjeta, y
  se puede hacer en cuanto el tema está creado, **antes de entregar**.
- Se vincula de tres formas equivalentes: eligiendo el tema en un typeahead,
  pegando el enlace del tema o escribiendo su `tid`.
  El selector contiene un solo tema: con un chip seleccionado o precargado no
  se muestra "Busca el tema". Quitar el chip restaura el buscador para cambiarlo.
  Se aplica también al formulario de entrega; quitar el chip no guarda cambios.
  - El typeahead busca a partir de **3 caracteres** y solo ofrece temas del tipo
    (prefijo) **Aventura**.
  - Un enlace o `tid` se valida igual que una elección del typeahead: el tema debe
    existir, ser visible para el usuario, tener el prefijo Aventura, estar en el
    foro de la isla de la solicitud (o de sus subforos; en solicitudes antiguas sin
    isla, en un foro de rol), estar abierto y no pertenecer a otra aventura.
- El enlace se guarda normalizado como `showthread.php?tid=N`, el formato que
  reconocen las herramientas actuales (la calculadora y `recompensasAventuras.php`
  buscan `tid=N` en la URL). Máximo 255 caracteres. Se acepta solo un tema del
  propio foro, no URL externas.
- Al entregar la aventura (sección 6.9), el campo del tema aparece **ya relleno**
  con el tema vinculado, que el narrador puede cambiar.

### 6.5 Denegación

- Al pasar una solicitud a estado 2, quien deniega debe escribir un motivo de
  hasta 500 caracteres. El servidor rechaza una denegación sin motivo o con
  motivo vacío.
- El motivo se guarda en una columna propia, `motivo_denegacion`, visible para
  los usuarios en el Registro. No se mezcla con la nota de staff, que es
  interna.
- El motivo queda en la auditoría junto con el usuario que denegó.
- La solicitud denegada no se reabre ni se edita. El usuario crea una nueva
  teniendo en cuenta el motivo. Una solicitud denegada deja de contar para los
  límites de aventuras activas.

### 6.6 Inframundo

El indicador `inframundo` marca un requerimiento de inframundo. Se puede fijar
al crear y desde la consola de aventuras.

### 6.7 Mercado de narradores

La pestaña Narradores & Narrados conecta a quien busca narrador con quien quiere
narrar. Discord continúa visible como vía de conversación, pero la aceptación y
la asignación se registran dentro de la herramienta.

**Demanda: aventuras sin narrador.** Son las solicitudes en estado 4 (pendiente
de narrador) que no son del usuario. Se muestran en un bloque destacado al
en Narradores & Narrados, para que quien esté dispuesto a narrar vea las
ofertas: los narradores pueden aceptar las aventuras de otros usuarios (o de
grupos de usuarios) que esperan narrador. Cada tarjeta muestra los datos de la
solicitud y la nota con la que el creador vende su idea. La acción `Me interesa
narrar` crea la candidatura descrita en la sección 6.2. La pestaña muestra además
un contador con cuántas hay.

**Oferta: narradores disponibles.** Cualquier usuario con ficha puede
**ofrecerse como narrador** y **retirar** su oferta cuando quiera:

- Al ofrecerse debe indicar su usuario de **Discord** (obligatorio, hasta 64
  caracteres), porque ahí lo contactan, y puede dejar un mensaje opcional de
  hasta 200 caracteres (por ejemplo, qué tipo de aventura prefiere o su
  disponibilidad).
- Hay una sola oferta por usuario. Ofrecerse de nuevo actualiza el Discord y el
  mensaje.
- La lista de narradores disponibles la ve cualquier usuario con sesión. Cada
  narrador muestra su nombre de ficha, su mensaje, desde cuándo está disponible,
  cuántas aventuras narra en curso (estados 0 y 1) y cuántas ha finalizado
  (estado 3), y su usuario de Discord como texto, para que quien busca narrador
  lo contacte allí.
- Para el autor, su oferta aparece una sola vez como franja compacta con acciones
  de renovar, editar y retirar; no se repite dentro del directorio. Los demás
  usuarios continúan viéndola como una tarjeta de narrador disponible.
- Ofrecerse no asigna nada y no obliga a nada: es una publicación de
  disponibilidad. Desde una oferta se puede iniciar una invitación, y desde una
  aventura sin narrador se puede iniciar una candidatura; ambas requieren la
  aceptación de la contraparte según la sección 6.2.
- Estar en la lista no limita ni cambia las reglas de las aventuras que ya narra.
- Una oferta **vence a los 30 días** sin renovarse (valor por defecto, ajustable
  en una constante). Mientras no venza, su autor ve cuántos días le quedan y un
  botón para renovarla. Una oferta vencida deja de mostrarse, pero no se borra.

**Moderación.** Un mod, narrador del staff o staff puede retirar una oferta desde
la consola (sección 7.4), con un motivo que queda en la auditoría, por ejemplo
si el mensaje es inapropiado o el Discord es falso. Retirarla no impide que el
usuario vuelva a ofrecerse.

**Orden.** Las aventuras sin narrador se muestran de la más antigua a la más
reciente, para que quien lleva más tiempo esperando no quede al final. Los
narradores disponibles se muestran de menos a más aventuras en curso y, a igual
número, de la oferta más antigua a la más reciente, para repartir la carga.

### 6.8 Edición de la solicitud por su creador

- Solo el **creador** (el `uid` de la solicitud) puede editarla, y solo mientras
  esté en estado **0 (creada) o 4 (pendiente de narrador)**, es decir, mientras
  no esté aprobada. En los estados 1, 2, 3 y 5 la edición se rechaza, y también
  si quien lo intenta no es el creador.
- Puede cambiar los participantes, el narrador (o pasar a no tener), la nota de
  idea, la isla, el día, el tier, el comentario público y el inframundo. No puede cambiar
  su rol: si creó la solicitud como narrador sigue siendo el narrador, y si la
  creó como jugador sigue siendo participante.
- Se aplican las mismas validaciones que al crearla (secciones 6.2 y 6.3). Los
  límites de aventuras activas se siguen comprobando al aprobar, no al editar.
- Si se conserva el narrador ya asignado, el estado no cambia. Si se elimina,
  pasa a 4. Elegir un narrador ajeno crea una invitación y mantiene el estado 4
  hasta su aceptación; al crearla se vacía `motivo_renuncia`.
- Cada edición guarda `editado_at` y queda en la auditoría con los campos que
  cambiaron. La consola marca las solicitudes editadas ("Editada el ...") para
  que quien revisa sepa que cambiaron.
- Si un revisor aprueba la solicitud mientras el creador la edita, gana lo que
  llegue primero: la edición se rechaza con un aviso si la solicitud ya no está
  en 0 o 4.

### 6.9 Entrega de la aventura y recompensas

**Cómo se hace hoy.** Para cerrar una aventura, el usuario escribe en un tema del
foro un formulario (nombre de la aventura, enlace al tema, número de petición,
participantes, narrador y su nivel, resumen y reputación). El staff lo lee y, en
la calculadora actual, pulsa **"Vincular y Pagar"** (o **"Pagar"** si la petición
ya tiene el tema vinculado), que lleva a `op/staff/recompensasAventuras.php?tid=N`.
Esa página es la herramienta real de pago de aventuras:

- lee todos los posts del tema;
- usa como narrador al usuario indicado en la entrega;
- cuenta, por usuario, los **posts válidos** (los de 300 caracteres o más, sin
  etiquetas); los demás se descartan;
- compara con los **posts esperados** del tier (4, 5, 6, 7, 8, 9, 10, 10, 11 y 12
  para los tiers 1 a 10) y calcula un **porcentaje de participación**;
- toma el tier y el indicador de inframundo de la petición, y deja al staff
  ajustarlos y corregir los posts de cada usuario;
- paga experiencia, nikas, berries, reputación y cofres, aplica un **tope mensual
  de experiencia al narrador**, publica un mensaje de "RECOMPENSAS PROCESADAS" en
  el tema y lo cierra.

Las páginas `entregar_recompensas_aventuras.php` y `entregar_recompensas_narradores.php`
entregan montos fijos por tramo a un solo personaje y a mano; no calculan quién
narró ni cuántos posts hizo cada quien. Pueden seguir existiendo para entregas
manuales, pero **las aventuras se entregan con la herramienta nueva**, que integra
la lógica de `recompensasAventuras.php`.

**Quién envía y cuándo.** El **narrador asignado** de una solicitud en estado 1
(aprobada) ve en su tarjeta de Mis aventuras el botón **Entregar aventura**. Solo
el narrador envía la entrega y es el responsable de escribir el resumen. Hay una
sola entrega por solicitud.

**Formulario de entrega.** Lo que el sistema ya conoce se rellena solo y no se
edita:

| Campo del foro | En el Tablón |
|---|---|
| Nombre de la aventura | El **nombre del tema**. Se elige con el typeahead (desde 3 caracteres, solo temas del tipo Aventura), o se pega el enlace o el `tid`. Si la aventura ya estaba vinculada (sección 6.4), viene ya rellena. |
| Link al tema | El enlace del tema elegido (se guarda su `tid`). |
| Número de petición | El número de la solicitud, ya ligado. |
| Usuarios participantes | Los participantes de la solicitud, por nombre de personaje. |
| Usuario narrador y nivel | El narrador asignado y su nivel, leído de `mybb_op_fichas` (`nivelnarrador`). |
| Tier | El de la solicitud, solo informativo para el narrador. |
| Resumen | Texto que escribe el narrador con el resultado final (obligatorio, hasta 2000 caracteres). |
| Reputación | Una sola opción para la aventura: **Positiva** o **Negativa** (obligatoria). |

**Reglas del tema.** Las de la sección 6.4: existe, es visible para el usuario,
tiene el prefijo Aventura, está en el foro de la isla o sus subforos (en solicitudes
antiguas sin isla, en un foro de rol), está **abierto** y no tiene ya el mensaje de
"RECOMPENSAS PROCESADAS", y no está en otra entrega pendiente o entregada. El
servidor vuelve a validar el tema al enviar.

**Estados de la entrega.** La entrega tiene su propio estado, distinto del de la
solicitud (que sigue en 1 mientras tanto):

- **Pendiente de revisión**: enviada, esperando al staff. No se puede editar.
- **Rechazada**: el staff la devolvió con un motivo (hasta 500 caracteres). El
  narrador ve el motivo, corrige el formulario y la reenvía.
- **Entregada**: el staff pagó las recompensas y la solicitud pasa a estado 3
  (finalizada). No se edita más.

**Revisión y pago (página `aventuras_recompensas.php`).** En la sección
**Entregas** de la consola (sección 7.4) el staff ve la lista de entregas y abre
una; la página nueva `/op/staff/aventuras_recompensas.php` (sección 7.5) muestra el
**análisis del tema**, el mismo que hoy hace `recompensasAventuras.php`, sin elegir
un tramo a mano:

- Las **reglas de cálculo** son las de esa página, sin cambios:
  - **Narrador:** el usuario asignado cuando se envió la entrega, aunque no haya
    escrito ningún post. En ese caso se muestra con cero posts y una advertencia.
  - **Posts válidos** de cada usuario: los de 300 caracteres o más, sin
    etiquetas. Los demás no cuentan.
  - **Porcentaje base** = posts válidos / posts esperados del tier x 100, si no
    supera lo esperado. Si lo supera, 100 más la mitad del valor de un post
    normal por cada post extra. Límites: de 0 a 150.
  - **Narradores:** porcentaje base más un **extra por jugadores** (0, 10, 20, 30,
    40 o 50, a elección del staff, 10 por jugador extra), con tope de 200.
  - **Jugadores:** porcentaje base, con tope de 200.
  - **Variación de posts** de cada usuario, de -100 a 100, que el staff suma o
    resta a sus posts válidos antes del cálculo.
  - **Posts mínimos:** esperados del tier x número de participantes. El análisis
    marca si se cumplen. Si no, el staff debe confirmarlo expresamente para pagar.
- **Tier:** por defecto, el de la solicitud (`tier_seleccionado`). El staff **puede
  cambiarlo**, de forma opcional.
- **Inframundo:** el de la solicitud, para las berries de los jugadores.
- **Cada destinatario** (narradores y jugadores detectados en el tema) aparece con
  su porcentaje, y el staff puede **incluirlo o excluirlo** del pago. Se marca a
  quien escribió en el tema sin estar en la solicitud, y a quien está en la
  solicitud sin haber escrito.
- **Reputación de cada jugador:** por defecto la que indicó el narrador (positiva o
  negativa); el staff puede cambiarla por jugador, incluida "ninguna".
- **Nivel del narrador:** el de su ficha (`nivelnarrador`), no uno elegido a mano.
- **Recompensas** (tablas por tier y por nivel de narrador, de jugador, bonos de
  oficio Contrabandista y Arqueólogo, variante de inframundo, cofres, kuros por
  exceso): las mismas de `recompensasAventuras.php`.
- **Tope mensual de experiencia del narrador: 675**, el mismo que usa hoy
  `recompensasAventuras.php` (no cambia). La experiencia que excede el tope del mes
  no se entrega; por cada 2 puntos de exceso se entrega 1 kuro. El contador
  (`expNarradorMensualActual`) se reinicia el primer pago de cada mes.
- Una **previsualización** muestra lo que recibirá cada destinatario antes de pagar.

Entonces el staff puede **Pagar** o **Rechazar** la entrega con un motivo
obligatorio. Al pagar, se publica el mensaje de "RECOMPENSAS PROCESADAS" en el tema
(con los mismos datos que publica hoy), se cierra el tema, la entrega queda
entregada y la solicitud pasa a estado 3.

**Una sola vez.** Cada destinatario recibe su recompensa una única vez por
entrega, aunque el staff pulse dos veces o la operación se interrumpa a medias. Si
se interrumpe, la entrega marca quién ya cobró y quién falta, y el staff puede
completarla sin repetir pagos. Un mismo tema no puede entregarse dos veces. Como
la herramienta nueva publica el mismo mensaje y cierra el tema, y la antigua
rechaza un tema cerrado o con ese mensaje, **ambas se protegen entre sí** mientras
convivan.

**Convivencia.** `recompensasAventuras.php` y el botón "Vincular y Pagar" de la
calculadora siguen funcionando hasta que esta se retire, sin cambios y con el
tope de 675. `aventuras_recompensas.php` es la página nueva que las reemplaza para
las aventuras entregadas desde el Tablón. Las páginas `entregar_recompensas_*` no
cambian.

**Moderación propia.** Mods, staff y narradores con permiso de gestión pueden
pagar o rechazar una entrega aunque sean creadores, narradores o participantes.

**Visible para los usuarios.** La tarjeta en Mis aventuras muestra "Entrega
pendiente de revisión", "Entrega rechazada: {motivo}" con el botón para corregirla
y reenviarla, o "Recompensas entregadas el ...". Finalizar manualmente desde la
consola solo se permite si no hay una entrega pendiente.

## 7. Interfaz

La calculadora actual tiene tres pestañas: Solicitud, Registro y Consola. En
`/op/tablon_de_aventuras.php` hay **cuatro**: Mis aventuras, Solicitar aventura,
Narradores & Narrados y Otras aventuras. La gestión sale de esta página.

| Pestaña | Visible para | Contenido |
|---|---|---|
| Mis aventuras | Todos los usuarios con sesión | Las solicitudes en las que el usuario es creador, narrador o participante. |
| Otras aventuras | Todos los usuarios con sesión | Las demás solicitudes y sus estadísticas filtradas. |
| Solicitar aventura | Usuarios con ficha | Formulario de solicitud. |
| Narradores & Narrados | Todos los usuarios con sesión | Ofertas de narrador y aventuras sin narrador. |

Al abrir la página se muestra **Mis aventuras**. En este documento, "el
Registro" designa conjuntamente las pestañas Mis aventuras y Otras aventuras.

La pestaña Consola **no existe** en esta página: no hay botón, panel, JS ni
endpoints de gestión, ni siquiera para staff. Hoy la pestaña se oculta con
`<if $g_is_staff or $g_is_narra then>`, pero sus acciones viven en el mismo
endpoint que las de los jugadores. Toda esa lógica pasa a la consola de
aventuras (sección 7.4).

### 7.1 Solicitar aventura

- Un selector de rol: "Soy el narrador" o "Soy jugador". Cambia qué campos se
  muestran según la sección 6.2.
- Typeahead de fichas para elegir participantes, con lista de elegidos que se
  puede quitar. El jugador que crea ya aparece como participante en la forma B.
  Con al menos un participante, se abre mediante "Añadir participante" y se
  oculta después de elegir. Sin participantes, aparece directamente.
- En la forma B, un selector "Tengo narrador" o "No tengo narrador":
  - con narrador: typeahead de fichas para nombrarlo; al seleccionarlo se oculta
    el buscador. Quitar el chip permite elegir otro; solo puede haber uno;
  - sin narrador: un campo de texto (500 caracteres) para escribir la nota con
    la que "vende su idea" a los posibles narradores.
  En la forma A el narrador es el propio usuario y no se puede cambiar.
- Desplegable de isla con typeahead (sección 6.3).
- Input numérico de día, de 1 a 90, placeholder "1-90" y etiqueta fija "Verano 725".
- Selector de tier del 1 al 10, que muestra cuántos posts se esperan por
  participante en el tier elegido.
- Comentario público de hasta 1000 caracteres.
- Casilla de inframundo.
- Indicador compacto de aventuras activas. El servidor rechaza el envío si
  cualquier participante excedería los límites; también se comprueban al aprobar.
- No hay formulario de enemigos, panel de nivel promedio ni etiqueta de
  dificultad. La lista de narradores disponibles no aparece aquí.

### 7.2 Mis aventuras

- Banda de estadísticas antes del encabezado: total, estados y conteos por rol
  sobre el conjunto filtrado completo. Mis activas enumera solitarias y grupales
  como jugador y muestra los límites 3/4, independientemente de esos filtros.
- Muestra las solicitudes en las que el usuario es creador, narrador o
  participante.
- Filtros por estado: Creadas, Pendiente (de narrador), Aprobadas, Denegadas y
  Finalizadas. Filtros por rol: **Soy narrador** y **Soy jugador**.
- Los filtros de estado y de rol se combinan entre sí (estado Y rol). Dentro de
  cada grupo solo se elige uno a la vez, y cada grupo tiene una opción "Todas"
  para volver a ver todo.
- Las solicitudes en estado Pendiente de borrado no aparecen en ninguna pestaña
  de los jugadores: el creador las pidió ocultar y solo las ve la consola.
- Cada tarjeta muestra id, creador, narrador, participantes, isla, día, tier
  (Verano 725, día N), inframundo, comentario público, la nota de idea si la
  hay y el enlace a la aventura si existe. No muestra la nota de staff. Las solicitudes denegadas
  muestran el motivo.
- Acciones por tarjeta según rol: vincular aventura (narrador asignado o
  participante, solo en estado Aprobada), **entregar aventura**
  (narrador asignado, estado Aprobada, o corregir y reenviar si fue rechazada) y
  dejar de narrar (narrador asignado, esta última solo en estados Creada y
  Aprobada), y editar
  (creador, solo en estados Creada y Pendiente de narrador) y pedir borrado
  (creador).
- La lista se pagina de **20 en 20** en el servidor, conserva filtros y muestra total.
- Si el usuario **no tiene ninguna aventura** (ninguna solicitud en la que sea
  creador, narrador o participante, sin contar las pendientes de borrado), en
  lugar de una lista vacía se le recomienda pedir una, con un botón que lleva a la
  pestaña Solicitar aventura. Si tiene aventuras pero los filtros no devuelven
  ninguna, solo se muestra un aviso de lista vacía.

### 7.3 Otras aventuras

Muestra todo lo que no es del usuario: **ninguna solicitud en la que sea
creador, narrador o participante**, en estados 0, 1, 2 y 3. Estadísticas antes
del encabezado sobre todo el conjunto filtrado, solo por total y estado, sin
contadores "Como narrador" ni "Como jugador", y paginación de 20 en 20.
Filtros por estado (Creadas, Aprobadas, Denegadas y Finalizadas) y UID de narrador
o participante. Las solicitudes pendientes se consultan en la cuarta pestaña.

### 7.3.1 Narradores & Narrados

Reúne estos dos bloques, mostrando primero las ofertas de narrador:

1. **Aventuras sin narrador (bloque destacado).** Las solicitudes en estado 4 que
   no son del usuario, con la nota "vendiendo su idea" de cada creador y el botón
   "Postularme como narrador" en cada una (que abre la ventana de confirmación). Es el
   bloque que más llama la atención de la página: título propio, color distintivo
   y el número de aventuras esperando. La pestaña lleva un contador con ese
   mismo número. Si no hay ninguna, el bloque lo dice ("Ahora mismo todas las
   aventuras tienen narrador") en vez de desaparecer.
2. **Narradores disponibles.** Los usuarios que se ofrecieron a narrar (sección
   6.7), con el botón **Ofrecerme como narrador** si el usuario aún no está en la
   lista. Si ya está, ve su propia oferta con "Renovar" y "Retirar oferta".

Las tarjetas tienen el mismo contenido que en Mis aventuras. La unión de Mis
aventuras, Otras aventuras y el bloque de sin narrador es todo lo visible en las pestañas de
jugadores, sin repetidos ni faltantes.

### 7.4 Consola de aventuras (consola de mods)

La gestión vive en una página propia, `/op/staff/consola_aventuras.php`, que se
agrega como tarjeta "Aventuras" en `$consola_grupos` de
[op/staff/consola_mod.php](../op/staff/consola_mod.php), con el permiso `narra`.
Sigue el patrón de `consola_mod.php` y `consola_narras.php`: comprueba el rol al
principio, redirige con `op_redireccion` si no hay acceso y usa un template
`staff_consola_aventuras`.

**Quién accede.** Mods, staff y narradores del staff (`is_narra`), el mismo
criterio que ya usa esa consola. Los narradores del staff tienen acceso a toda la
consola de aventuras. Los **narradores usuarios** (usuarios con ficha que narran
una aventura, sin `is_narra`) no entran. La única acción reservada es eliminar
definitivamente, que exige `is_staff`.

**Nadie resuelve su propia aventura.** Aprobar, denegar y finalizar se rechazan
si quien las intenta es creador, narrador o participante de esa solicitud,
aunque tenga permisos de consola. Otra persona con acceso debe resolverla. En la
fila, esas acciones aparecen deshabilitadas con la explicación "No puedes
resolver una aventura en la que participas".

La consola tiene tres secciones: Solicitudes, **Entregas** y Narradores
disponibles. La pestaña Entregas lleva un contador con las entregas pendientes.

**Solicitudes**

- **Cola de revisión.** Se abre filtrada por **En revisión** (estado 0), las que
  esperan revisión. Cada filtro de estado muestra cuántas solicitudes hay en él
  ("En revisión (3)"), incluido "Pendientes de borrado". La búsqueda acepta `#ID`,
  UID o nombre de creador, narrador o participante. No hay filtros por isla ni
  por día.
- Cada fila muestra id, isla, día, narrador, participantes, estado, nota de
  staff, la nota de idea del creador, el motivo de renuncia si existe y la marca
  "Editada el ..." si el creador la modificó.
- Acciones: aprobar, denegar y finalizar (con las restricciones anteriores),
  editar nota de staff, comentario público, inframundo, isla y día (el staff no
  corrige participantes ni narrador, lo hace el creador), e historial.
- **Historial.** Cada fila abre el historial de esa solicitud: quién hizo qué y
  cuándo, a partir de la auditoría (creación, ediciones, postulaciones, salidas
  de narrador con su motivo, cambios de estado con su motivo, vinculación,
  borrado). Las solicitudes anteriores a la migración se muestran con "Sin
  historial anterior a la migración".
- Al denegar, el motivo es obligatorio (sección 6.5). Sin motivo, la acción se
  rechaza.
- Eliminar definitivamente solicitudes en estado 5. Esta acción exige
  `is_staff`; el resto no la ve.
- Mensaje de error explícito cuando una aprobación se bloquea por límite,
  indicando qué jugadores lo exceden y con cuántas aventuras solitarias y
  totales.

**Entregas**

- Lista las entregas por estado (Pendientes, Rechazadas, Entregadas) con su
  conteo; se abre en **Pendientes**.
- Cada fila muestra el número de petición, el tema (enlace), el narrador, los
  participantes, el tier, la marca de inframundo y, si la reenviaron, el número de
  intentos. Un botón **Revisar y pagar** abre `aventuras_recompensas.php` con esa
  entrega. Las entregas entregadas muestran la fecha y un enlace a su detalle.
- La consola solo lista y lleva a la página; el análisis, los ajustes y el pago
  están en `aventuras_recompensas.php`.

**Narradores disponibles**

- Lista todas las ofertas, incluidas las vencidas (marcadas como tales), con
  nombre de ficha, usuario de Discord, mensaje, frecuencia, tiers preferidos,
  estilo, carga actual, aventuras finalizadas y fechas de oferta y de última
  renovación.
- La lista está paginada de 20 en 20 y permite filtrar por vigencia y buscar por
  UID o nombre. No puede quedar limitada silenciosamente a la primera página.
- Acción **Retirar oferta**, con confirmación y un motivo obligatorio de hasta
  200 caracteres. Borra la oferta y el motivo queda en la auditoría. El usuario
  puede volver a ofrecerse.

**Rediseño operativo implementado y verificado (2026-10-07)**

1. La consola es responsive y no tiene desbordamiento horizontal a 375, 700,
   1030 ni 1440 px. El contenedor, la cabecera compartida, las pestañas, filtros,
   tarjetas, formularios e historial se adaptan al ancho disponible.
2. La barra naranja de título se sustituye por el encabezado compacto de
   herramienta del Tablón: icono, un único `h1`, texto de apoyo breve y línea de
   acento. No se ocultan el banner, menú, avisos de staff ni Bitácora.
3. La consola no repite una banda general de trabajo pendiente: Solicitudes muestra
   sus estadísticas por estado y Entregas conserva su contador en la pestaña.
4. Una solicitud no puede aprobarse si le falta isla, día, tier, comentario
   público, narrador o participantes válidos. La tarjeta enumera lo que falta,
   deshabilita `Aprobar` y ofrece `Completar datos`; el servidor repite la
   validación dentro de la transición transaccional.
5. Las filas de solicitudes son compactas y comparables. En primer nivel muestran
   ID y título del tema, antigüedad, estado, isla/día/tier, creador, narrador,
   participantes y acciones. Comentario, idea, nota interna, renuncia y denegación
   se consultan en un detalle expandible sin perder su contenido.
6. Las tres pestañas forman una barra segmentada de columnas estables, con iconos
   Font Awesome, texto, estado activo por subrayado y contador integrado en
   Entregas. En móvil pueden repartirse en filas sin recortar texto.
7. Cada fila tiene una sola acción principal. `Aprobar`, `Revisar y pagar` o
   `Completar datos` ocupan ese lugar según contexto; `Denegar` conserva tratamiento
   peligroso y Editar, Historial y Finalizar se agrupan en `Más acciones`.
8. Solicitudes conserva los filtros por estado, persona y orden. El orden
   predeterminado es por las más recientes; el staff puede elegir las más antiguas.
9. `Buscar por UID` se sustituye por un typeahead único que acepta `#ID`, UID o
   nombre y busca entre creador, narrador y participantes. La selección se
   representa en la URL.
10. Historial se presenta como línea temporal en un panel lateral, acordeón o
    diálogo compacto. Solo hay uno abierto; al abrir recibe foco y al cerrar lo
    devuelve al botón. En móvil ocupa el ancho disponible y no desplaza de forma
    confusa toda la cola.
11. Narradores disponibles respeta `pagina` tanto en carga completa como HTMX y
    muestra paginación cuando supera 20 ofertas.
12. La consola presenta las preferencias estructuradas y la carga que ya muestra
    el directorio público, para que el staff modere con el mismo contexto.
13. Los conteos de estados y entregas se calculan una vez por petición y se
    reutilizan entre resumen, pestañas y paneles; no se repite una consulta
    idéntica para construir el contador.
14. Los estilos comunes de Tablón, Consola y Recompensas viven en una hoja común;
    cada template conserva solo reglas propias. No se duplican bloques completos
    `.ta-*` y `.ca-*` susceptibles de divergir.
15. La página usa `h1`, `article`, encabezados y nombres accesibles adecuados. Los
    cambios HTMX anuncian total/página, enfocan el contenido nuevo cuando procede,
    mantienen foco visible y funcionan con teclado. Iconos y colores nunca son la
    única forma de comunicar estado o acción.

La consola y las pestañas de jugadores comparten las reglas de transición y de límites
mediante la librería de `inc/plugins/lib/` (sección 2); la consola no
reimplementa nada.

### 7.5 Página de recompensas (`aventuras_recompensas.php`)

Página nueva y rediseñada, en `/op/staff/aventuras_recompensas.php?id=<entrega>`
(también acepta `?tid=<tema>` y busca su entrega vigente). Sigue la lógica de
`recompensasAventuras.php`, pero no reutiliza su código ni su template, y exige una
entrega enviada desde el Tablón (no paga un tema suelto). Tiene el mismo permiso que
la consola (mod, narrador del staff o staff), redirige con `op_redireccion` si no
hay acceso y usa el mismo patrón de herramienta de `/op/staff/`.

Contenido:

- **Datos de la entrega**: número de petición, tema (enlace), reputación indicada
  por el narrador, el resumen escrito por el narrador, el narrador con su nivel, los
  participantes, el tier de la solicitud, la marca de inframundo y, si es un
  reenvío, el número de intentos y el motivo del rechazo anterior.
- **Análisis del tema** (sección 6.9): posts totales y válidos por usuario, quién
  narró, posts esperados del tier y si se cumplen los mínimos. Avisa de quien
  escribió sin estar en la solicitud, de quien está en la solicitud sin haber
  escrito, incluido el narrador conservado en la entrega.
- **Ajustes del staff**: tier (opcional, por defecto el de la solicitud), extra por
  jugadores para los narradores, variación de posts de cada usuario, reputación de
  cada jugador, incluir o excluir a cada destinatario y número de participantes
  para los mínimos.
- **Previsualización** de lo que recibirá cada destinatario, que se recalcula al
  cambiar un ajuste.
- **Pagar** y **Rechazar** (motivo obligatorio). Para pagar sin cumplir los posts
  mínimos hace falta una casilla de confirmación. Quien participa en la aventura ve
  ambas acciones deshabilitadas con su explicación.
- **Entrega interrumpida**: quién ya cobró y quién falta, y el botón **Completar
  entrega**, con las opciones para un pago de experiencia dudoso.
- **Entrega ya pagada**: lo que recibió cada destinatario, el tier y la fecha, sin
  acciones.

## 8. Endpoints

Las respuestas son fragmentos HTML para HTMX (no JSON), con el código HTTP que
corresponda (200, 403, 404, 409 o 422). Las acciones de jugadores se atienden en
`tablon_de_aventuras.php?action=...`; las de gestión, en
`op/staff/consola_aventuras.php?action=...`. Una acción de gestión enviada a
`tablon_de_aventuras.php` responde 404, sin importar el rol de quien llama.

| Acción | Método | Quién puede | Notas |
|---|---|---|---|
| `crear` | POST | Usuario con ficha | Valida la forma A o B, participantes, isla, día y comentario. |
| `listar` | GET | Usuario con sesión | De Mis aventuras o de Otras aventuras; paginado y filtrado. Sin nota de staff. |
| `sin_narrador` | GET | Usuario con sesión | Bloque de aventuras sin narrador (excluye las mías). |
| `contar_sin_narrador` | GET | Usuario con sesión | Contador de aventuras sin narrador para la pestaña. |
| `narradores_disponibles` | GET | Usuario con sesión | Lista de narradores con oferta vigente. |
| `ofrecerme_narrador` | POST | Usuario con ficha | Crea o actualiza la oferta del usuario: Discord obligatorio y mensaje opcional. |
| `renovar_oferta` | POST | Usuario con ficha | Renueva la oferta vigente del propio usuario. |
| `retirar_oferta` | POST | Usuario con ficha | Elimina la oferta del propio usuario. |
| `ver` | GET | Usuario con sesión | Detalle de una solicitud, sin la nota de staff. |
| `form_postular` | GET | Usuario con ficha | Ventana de confirmación de la postulación. |
| `form_editar` | GET | Creador | Formulario de edición de la propia solicitud, con sus datos. Solo estados 0 y 4. |
| `form_participante` | GET | Usuario con sesión | Abre el buscador mediante Añadir participante. |
| `quitar_narrador` | GET | Usuario con sesión | Restaura el buscador en el slot de narrador. |
| `editar` | POST | Creador | Valida como `crear`. Solo estados 0 y 4. Puede cambiar el estado entre 0 y 4. |
| `buscar_temas` | GET | Narrador asignado o participante para vincular; narrador asignado para entregar | Typeahead de temas elegibles (máximo 20), desde 3 caracteres y solo del tipo Aventura. Lo usan "Vincular aventura" y "Entregar aventura". |
| `elegir_tema` | GET | Narrador asignado o participante para vincular; narrador asignado para entregar | Valida un tema, un enlace o un `tid` pegado y devuelve el chip con su `tid`. |
| `form_vincular` | GET | Narrador asignado o participante | Formulario de "Vincular aventura" con el typeahead de tema, enlace o `tid`. Solo estado 1. |
| `form_entrega` | GET | Narrador asignado | Formulario de entrega (o de corrección si fue rechazada). Solo estado 1. |
| `enviar_entrega` | POST | Narrador asignado | Crea o reenvía la entrega. Solo estado 1 y entrega inexistente o rechazada. |
| `form_renunciar` | GET | Narrador asignado | Ventana de confirmación de "Dejar de narrar". |
| `renunciar_narrador` | POST | Narrador asignado | Solo estados 0 y 1. Exige confirmación y `motivo`. La solicitud pasa a estado 4. |
| `postular_narrador` | POST | Usuario con ficha | Solo estado 4. Exige el campo de confirmación marcado. |
| `vincular_aventura` | POST | Narrador asignado o participante | Solo aprobada. Acepta tema elegido, enlace o `tid`; guarda `showthread.php?tid=N`. |
| `pedir_borrado` | POST | Creador | Pasa a estado 5. |
| `buscar_fichas` | GET | Usuario con ficha | Máximo 20 resultados. Para participantes y narrador. No sugiere ni marca narradores disponibles. |
| `buscar_islas` | GET | Usuario con ficha | Foros con `pid` 15, 16, 17 o 18. |

Acciones de `aventuras_recompensas.php` (mismo permiso que la consola; las que cambian
datos exigen `post_code`):

| Acción | Método | Quién puede | Notas |
|---|---|---|---|
| (sin acción) | GET | Mod, narrador del staff o staff | Página completa con la entrega indicada por `id` o `tid`. |
| `analizar` | GET | Mod, narrador del staff o staff | Análisis del tema y previsualización de recompensas por destinatario, con los ajustes enviados. No escribe nada. |
| `pagar` | POST | Mod, narrador del staff o staff | Paga a cada destinatario una sola vez, publica el mensaje en el tema y lo cierra. No puede ser parte de la solicitud. |
| `form_rechazar` | GET | Mod, narrador del staff o staff | Formulario del motivo. |
| `rechazar` | POST | Mod, narrador del staff o staff | Devuelve la entrega con un motivo (500). No puede ser parte de la solicitud. |
| `completar` | POST | Mod, narrador del staff o staff | Termina una entrega interrumpida sin repetir pagos. |
| `xp_dudosa` | POST | Mod, narrador del staff o staff | Resuelve un pago con experiencia dudosa. Auditado. |

Acciones de `consola_aventuras.php`:

| Acción | Método | Quién puede | Notas |
|---|---|---|---|
| `listar` | GET | Mod, narrador del staff o staff | Incluye la nota de staff, la idea y el motivo de renuncia, y los conteos por estado. Se abre en Creadas. Paginado y filtrado. |
| `cambiar_estado` | POST | Mod, narrador del staff o staff | Valida transición, límites y que quien actúa no sea parte de la solicitud. Exige `motivo` al denegar. |
| `actualizar_datos` | POST | Mod, narrador del staff o staff | Nota de staff, comentario, inframundo, isla y día. |
| `historial` | GET | Mod, narrador del staff o staff | Historial de una solicitud a partir de la auditoría. |
| `narradores` | GET | Mod, narrador del staff o staff | Todas las ofertas de narrador, incluidas las vencidas. |
| `retirar_oferta` | POST | Mod, narrador del staff o staff | Borra una oferta. Exige `motivo` (200). Auditado. |
| `entregas` | GET | Mod, narrador del staff o staff | Entregas por estado, con conteos. Se abre en Pendientes. Cada fila enlaza a `aventuras_recompensas.php`. |
| `eliminar` | POST | Staff | Solo estado 5. Transaccional y auditado. |

## 9. Problemas del sistema actual que la versión nueva debe resolver

Observados en `op/Calculadora_Tiers.php`:

1. **Permisos de servidor comentados.** En `save`, `get`, `update_meta`,
   `claim_narrador`, `update_enemigos`, `delete` y `search_fichas`, la
   comprobación `is_mod/is_staff/is_narra` está comentada. Solo se exige
   petición AJAX y mismo origen, que un cliente puede falsificar. Como
   consecuencia, `update_meta` (cambiar estado) y `delete` no verifican quién
   los llama, y `save` e `update_meta` ni siquiera exigen sesión. Es el punto
   de mayor prioridad y debe corregirse en la página vieja de inmediato.
2. **Sin `post_code`.** Las escrituras no verifican el token CSRF de MyBB
   (`verify_post_check`); la comprobación de origen por `stripos` sobre el
   `Host` es débil.
3. **`resolve_fid_by_name` usa `$db` sin `global $db`**, por lo que falla al
   resolver jugadores sin `uid` enviado por el cliente.
4. **Depuración activa en producción:** `display_errors` en 1 y `error_log` de
   depuración con valores de `inframundo`.
5. **DDL en runtime:** `CREATE TABLE` y `ALTER TABLE` dentro de peticiones
   normales (sección 4).
6. **Transiciones de estado sin validar:** `update_meta` acepta cualquier
   estado hacia cualquier estado.
7. **`list` sin paginar**, con una consulta que une usuarios y meta para todas
   las filas, y `JSON_CONTAINS` sobre `mediumtext` para el conteo.
8. **Inconsistencias:** límite de comentario 500 vs 150; el servidor y
   `solicitud.js` aplican 5 activas más 1 grupal (máximo 6) cuando la regla
   vigente es 3 solitarias y 4 en total; `search_fichas` con la tabla
   `mybb_op_fichas` escrita a mano en lugar del prefijo.
9. **Eliminación sin auditoría** y sin comprobar el estado, de modo que se puede
   borrar una aventura aprobada o finalizada.
10. **Gestión mezclada con la página de jugadores:** la pestaña Consola se
    oculta con un `<if>` del template, pero sus acciones están en el mismo
    endpoint que las de los jugadores.
11. **Tamaño y mantenimiento:** estilos en línea masivos en el template y
    `onclick` en línea; JS en tres archivos con dependencias de orden.

## 10. Requisitos no funcionales

- Cada acción de escritura verifica sesión, rol y propiedad en el servidor, usa
  `verify_post_check` y escapa los valores con `$db->escape_string` o casts
  enteros.
- Los permisos usan `is_staff`, `is_narra` e `is_mod` de
  `op/functions/op_functions.php`, y `does_ficha_exist` para jugadores.
- Las consultas de listado usan índices y `LIMIT`; ninguna consulta recorre la
  tabla completa para filtrar por jugador.
- Cambios de estado, vinculación y borrado quedan en la auditoría con usuario y
  fecha, mediante `log_audit`.
- La página es usable en pantallas de hasta 700px: tarjetas apiladas y controles
  con texto legible.
- Estética OPG: borde negro de 2-3px, crema, naranja y morado, sombras duras y
  sin sombras difusas ni degradados entre matices. Reutiliza `.opg-card`,
  `.btn-op`, `.opg-chip` y `.af-field`.
- Sin `onclick` en línea ni estilos en línea masivos. La interacción se declara
  con atributos HTMX (`hx-*`); el único JS es la librería HTMX, fijada a una
  versión y servida desde el propio sitio.
- Las plantillas MyBB no pueden contener identificadores JavaScript que
  empiecen con `$`, salvo interpolaciones intencionales como `{$variable}`.
- La página no define `display_errors`; usa la configuración del servidor.

## 11. Estrategia de migración

1. Corregir los permisos de la página vieja (problema 1 de la sección 9).
2. Respaldar `mybb_op_peticionAventuras` y `_meta`, escribir y aplicar la
   migración SQL de la sección 4, y verificar que el número de filas y los `id`
   no cambiaron (sección 4.2).
3. Extraer la lógica compartida a `inc/plugins/lib/` (límites, estados,
   transiciones, fecha e isla).
4. Crear `tablon_de_aventuras.php`, el template `op_tablon_de_aventuras` y los
   JS nuevos, apuntando a las mismas tablas.
5. Crear `op/staff/consola_aventuras.php`, su template y su tarjeta en la
   consola de mods.
6. Probar en paralelo con staff. Mientras convivan ambas páginas, las dos
   aplican las mismas reglas de límites porque comparten la librería.
7. Cuando la nueva esté validada, enlazarla desde el menú y convertir
   `Calculadora_Tiers.php` en una redirección.

## 12. Criterios de aceptación

1. Un jugador con ficha crea una solicitud como participante, con otro
   participante, un narrador, una isla, un día y un comentario; el registro
   guarda esos datos y el creador figura como participante.
2. Un narrador crea una solicitud con un participante y queda asignado a sí
   mismo; si no elige ningún participante, o se elige a sí mismo como
   participante, el servidor la rechaza.
3. Un usuario sin sesión o sin ficha no puede crear, postularse ni vincular
   mediante peticiones directas.
4. Un jugador no puede crear una solicitud en la que no es participante ni
   narrador, ni repetir participantes, ni usar como narrador a un participante.
5. Un narrador no puede postularse a su propia solicitud, a la de la que es
   participante, ni a una que ya tiene narrador.
6. La isla solo acepta foros con padre directo 15, 16, 17 o 18 y rechaza un
   subforo de ellos, un foro inactivo o un `fid` inexistente.
7. El día solo acepta enteros de 1 a 90; el año (725) y la estación (Verano) no
   los puede cambiar el cliente.
8. El comentario público acepta hasta 1000 caracteres y rechaza más, y se
   muestra escapado.
9. Aprobar una solicitud se bloquea si un jugador pasaría de 3 solitarias o de 4
   aventuras en total, y el mensaje nombra a los jugadores afectados. Un jugador
   con 4 grupales activas puede seguir sin problema mientras no sume una quinta,
   y uno con 3 solitarias puede sumar una grupal pero no una cuarta solitaria.
10. Una transición de estado fuera de la tabla de la sección 4.1 se rechaza.
    Denegar sin motivo se rechaza, y una solicitud denegada no puede volver a
    otro estado.
11. Un moderador o staff puede denegar una aventura aprobada con motivo, y el
    motivo se ve en el Registro y queda en la auditoría.
12. El narrador asignado o cualquiera de los participantes puede vincular la
    URL, solo con la solicitud aprobada y solo con una URL válida. Un usuario
    ajeno a esos roles no puede vincularla, aunque sea el creador.
13. La página se abre en la pestaña Mis aventuras. Mis aventuras solo contiene
    solicitudes donde el usuario es creador, narrador o participante. Otras
    aventuras no contiene ninguna de ellas. La unión de Mis aventuras, Otras
    aventuras y las solicitudes sin narrador de Narradores & Narrados es todo lo visible, sin
    repetidos ni faltantes.
14. Los filtros de estado, "Soy narrador" y "Soy jugador" de Mis aventuras, y
    los de estado y UID de Otras aventuras, devuelven resultados paginados y
    correctos. Una solicitud en estado Pendiente de borrado no aparece en
    ninguna pestaña de jugadores.
15. Cada cambio de estado, vinculación y borrado aparece en la auditoría con el
    usuario responsable.
16. Después de la migración, la tabla tiene el mismo número de filas y el mismo
    conjunto de `id` que antes, y cada solicitud conserva su número, su estado,
    sus participantes, su narrador, su comentario y su URL.
17. Una solicitud antigua se ve en el Registro nuevo como "sin isla" y "sin
    día", sin dificultad, y es gestionable desde la consola; el staff puede
    completarle isla y día.
18. Una solicitud creada nueva recibe un `id` posterior al mayor existente.
19. Las solicitudes antiguas en estados 0, 1 y 4 cuentan para los límites de
    3 solitarias y 4 en total, y un jugador que ya excede el límite no pierde
    ninguna solicitud pero no puede aprobar otra.
20. Una solicitud antigua denegada se muestra como "sin motivo registrado" y
    no se puede reabrir.
21. Las escrituras sin `post_code` válido se rechazan.
22. Un jugador sin rol de mod, narrador o staff ve solo las pestañas Solicitud, Mis
    aventuras y Otras aventuras, y abrir `consola_aventuras.php` o llamar a sus acciones lo
    redirige o devuelve acceso denegado.
23. Ni siquiera un usuario staff ve una pestaña Consola en
    `tablon_de_aventuras.php`, y enviarle una acción de gestión responde 404.
24. La tarjeta "Aventuras" aparece en la consola de mods solo para quien tiene
    el permiso `narra`, y la acción de eliminar solo para `is_staff`.
25. Ninguna respuesta, formulario ni columna escrita por la versión nueva
    contiene enemigos, ratio ni etiqueta de dificultad. El tier sí: la solicitud
    exige un tier de 1 a 10 al crearla (0 y 11 se rechazan), se muestra en las
    tarjetas y se puede cambiar editando la solicitud antes de su aprobación.
26. Narradores & Narrados muestra el bloque "Aventuras sin narrador" con las
    solicitudes en estado 4 que no son del usuario, de la más antigua a la más
    reciente, cada una con la nota de idea de su creador, y la pestaña muestra un
    contador con ese número. Con cero solicitudes, el bloque lo indica en vez de
    desaparecer.
27. Un usuario con ficha puede ofrecerse como narrador con su Discord (obligatorio,
    hasta 64 caracteres) y un mensaje de hasta 200, actualizar su oferta,
    renovarla y retirarla. Sin Discord, con 65 caracteres de Discord, con 201 de
    mensaje o sin ficha, se rechaza.
28. La lista de narradores disponibles muestra solo ofertas vigentes (menos de 30
    días desde su última renovación), con nombre, mensaje, Discord, aventuras en
    curso y finalizadas, ordenada de menos a más aventuras en curso. No tiene
    botón de elegir ni enlace de mensaje privado.
29. Una oferta vencida no se muestra, pero su autor puede volver a ofrecerse.
    Retirar la oferta de otro usuario por petición directa se rechaza.
30. En Solicitar aventura, un jugador que indica que no tiene narrador ve el
    campo de idea (hasta 500 caracteres); con 501 se rechaza. Si indica que
    tiene narrador, la nota no se guarda. El narrador se nombra con el typeahead
    de fichas, sin sugerencias de la lista de narradores disponibles.
31. Postularse como narrador abre una ventana que pide confirmar el acuerdo con
    el usuario. Enviar la postulación sin la confirmación marcada se rechaza, y
    cancelar la ventana no cambia nada.
32. Las pestañas aparecen en el orden Mis aventuras, Solicitar aventura,
    Narradores & Narrados y Otras aventuras, y la página se abre en Mis aventuras. Un usuario sin ninguna
    aventura ve en Mis aventuras la recomendación de pedir una, con un botón
    hacia Solicitar aventura; un usuario con aventuras cuyos filtros no devuelven
    nada ve solo el aviso de lista vacía.
33. El narrador asignado a una solicitud en estado 0 o 1 puede dejar de narrarla
    tras confirmar en la ventana y escribir un motivo. La solicitud pasa a estado
    4, sin narrador, y aparece en el bloque de aventuras sin narrador de Otras
    aventuras con el motivo visible. Enviar la acción sin confirmación, sin
    motivo, o con un motivo de 501 caracteres, se rechaza. Cancelar no cambia
    nada. Cuando otro narrador se postula, el motivo deja de mostrarse y queda
    solo en la auditoría.
34. Dejar de narrar una aventura aprobada la deja en estado 4 y, cuando otro
    narrador se postula, en estado 0, de modo que necesita una nueva aprobación.
    La URL ya vinculada se conserva.
35. Quien no es el narrador asignado, o la solicitud está en estado 2, 3, 4 o 5,
    no puede dejar de narrar: la petición se rechaza. La salida queda en la
    auditoría con el usuario que se salió y el motivo.
36. El creador puede editar su solicitud en estado 0 o 4 (participantes, narrador,
    idea, isla, día, comentario, inframundo) y no en 1, 2, 3 ni 5. Quien no es el
    creador, o un narrador asignado que no es el creador, no puede editarla. No
    puede cambiar su rol.
37. Al editar, quitar el narrador de una solicitud en estado 0 la pasa a 4, y
    nombrar uno en una solicitud en estado 4 la pasa a 0 y vacía el motivo de
    renuncia. La edición guarda `editado_at`, queda en la auditoría con los campos
    que cambiaron y la consola muestra "Editada el ...". Editar una solicitud que
    acaba de ser aprobada se rechaza.
38. Quien tiene acceso a la consola (mod, narrador del staff, staff) no puede
    aprobar, denegar ni finalizar una solicitud en la que es creador, narrador o
    participante, y esas acciones aparecen deshabilitadas con su explicación.
    Otro usuario con acceso sí puede.
39. Un narrador usuario sin `is_narra`, mod ni staff no entra a la consola de
    aventuras. Un narrador del staff tiene acceso a todas sus secciones.
    Eliminar sigue exigiendo `is_staff`.
40. La consola se abre filtrada por En revisión, y cada filtro de estado muestra
    cuántas solicitudes hay. La nota de idea y el motivo de renuncia se muestran
    en la fila.
41. El historial de una solicitud muestra, con usuario y fecha, la creación, las
    ediciones, las postulaciones, las salidas con su motivo, los cambios de estado
    con su motivo, la vinculación y el borrado. Una solicitud anterior a la
    migración muestra "Sin historial anterior a la migración".
42. La sección Narradores disponibles de la consola lista todas las ofertas,
    marca las vencidas y permite retirar una con confirmación y un motivo
    obligatorio de hasta 200 caracteres. Sin motivo, o con 201, se rechaza. La
    retirada queda en la auditoría y el usuario puede volver a ofrecerse. Un
    usuario sin permisos de consola no puede retirar ofertas por petición
    directa.
43. El narrador asignado de una solicitud aprobada ve "Entregar aventura" y
    "Vincular aventura". Nadie más, y solo en estado 1. El typeahead de temas
    empieza a buscar con 3 caracteres, con 2 no devuelve nada, y solo ofrece temas
    del tipo Aventura. Pegar un enlace o un `tid` se valida igual: un tema de otro
    tipo, de otra isla, cerrado, inexistente, inaccesible o ya usado se rechaza.
44. Vincular guarda `showthread.php?tid=N` en la solicitud, acepte el narrador tema
    elegido, enlace o `tid`, y al abrir "Entregar aventura" el tema viene relleno
    con el vinculado. Si se cambia en la entrega, se usa el nuevo.
45. El formulario de entrega muestra petición, participantes, narrador con su
    `nivelnarrador` y tier, no editables. Sin tema, sin resumen, sin reputación o con
    un resumen de 2001 caracteres se rechaza. Al enviar, la entrega queda pendiente,
    la solicitud sigue en estado 1, no se puede crear otra entrega para la misma
    solicitud ni usar el mismo tema en otra.
46. El análisis conserva los cálculos de `recompensasAventuras.php` para el mismo
    tema, tier y ajustes, pero toma el narrador de la entrega: mismos
    posts válidos (300 caracteres o más), mismos posts esperados por tier, mismos
    porcentajes (base de 0 a 150, final de 0 a 200, extra por jugadores de 0 a 50) y
    mismas recompensas, incluidos oficios, inframundo, cofres y kuros por exceso.
47. El tier de la entrega es por defecto el de la solicitud y el staff puede
    cambiarlo. El nivel del narrador sale de su ficha. La reputación de cada jugador
    es por defecto la elegida por el narrador y el staff puede cambiarla por jugador.
    Se puede excluir a un destinatario. El narrador sin posts aparece con cero y
    una advertencia, sin depender del contenido de los mensajes.
    Con posts mínimos sin cumplir hace falta confirmar.
48. El tope mensual de experiencia del narrador es 675, igual que en
    `recompensasAventuras.php`, que no cambia: con 625 acumulados y 100 de
    experiencia por pagar se entregan 50 y 25 kuros; con el contador en 675 no se
    entrega experiencia y todo el exceso pasa a kuros. El contador se reinicia el
    primer pago de cada mes.
49. Pagar paga una sola vez a cada destinatario aunque se pulse dos veces o dos
    miembros del staff lo hagan a la vez, publica el mensaje de "RECOMPENSAS
    PROCESADAS" en el tema, lo cierra, deja la entrega en entregada y pasa la
    solicitud a estado 3. Una interrupción a mitad deja registro de quién cobró y
    "Completar entrega" paga solo a quien falta. Un tema que la herramienta antigua
    ya pagó (cerrado o con el mensaje) no se puede pagar de nuevo desde la nueva, y
    al revés.
50. Rechazar sin motivo falla. Una entrega rechazada libera el tema y el narrador la
    corrige y reenvía. Un gestor puede moderar entregas propias; un usuario sin
    permisos de consola no puede abrir `aventuras_recompensas.php` ni llamar a sus
    acciones.
51. Mis aventuras muestra "Entrega pendiente de revisión", "Entrega rechazada:
    {motivo}" con botón de corregir y reenviar, o "Recompensas entregadas el ...".
    "Finalizar" manual se rechaza si hay una entrega pendiente.
52. La consola no desborda horizontalmente a 375, 700, 1030 ni 1440 px, conserva
    la cabecera global y presenta un único `h1` y pestañas segmentadas con iconos.
53. Una solicitud incompleta enumera sus campos ausentes y no se aprueba ni desde
    la interfaz ni mediante una petición directa. Tras completar los datos, puede
    aprobarse si también supera permisos, transición y límites.
54. El listado se abre con las creadas más recientes y permite invertir el orden.
    Busca por `#ID`, UID o nombre entre creador, narrador y participantes,
    conservando estado, búsqueda y orden en la URL.
55. Las tarjetas compactas tienen una sola acción principal y un menú secundario;
    el detalle y el historial son accesibles con teclado, gestionan el foco y no
    ocultan comentario, idea, nota privada ni motivos cuando se solicitan.
56. Narradores disponibles pagina de 20 en 20, permite alcanzar todas las páginas
    y muestra vigencia, preferencias, carga y finalizadas. La misma consulta de
    conteos no se ejecuta dos veces al construir una respuesta.
57. Tablón, Consola y Recompensas cargan estilos compartidos sin duplicar sus
    componentes base; las pruebas de página comprueban semántica, foco, URL,
    paginación, acciones, estados vacíos y ausencia de desbordamiento.
