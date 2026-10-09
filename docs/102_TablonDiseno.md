# Rediseño visual del sistema de aventuras

## Narradores & Narrados compacto (aprobado, 2026-10-08)

Estado: **implementado y verificado localmente; pendiente de despliegue**.

### Jerarquía

- Si no hay invitaciones ni candidaturas relevantes, `Propuestas para narrar` no
  existe en el DOM. La pestaña no muestra una insignia vacía.
- La insignia superior representa acciones personales pendientes, no el inventario
  de aventuras sin narrador. Los conteos de oferta y demanda viven en sus secciones.
- El orden visual es: propuestas personales cuando existan, oferta propia,
  narradores disponibles y aventuras sin narrador.

### Oferta y narradores

- La oferta propia es una franja horizontal compacta. Identidad, vigencia, Discord
  y mensaje ocupan una línea flexible; Renovar, Editar y Retirar se agrupan a la
  derecha como acciones secundarias con icono, etiqueta accesible y tooltip.
- Las ofertas ajenas usan `grid-template-columns: repeat(auto-fit,
  minmax(260px, 1fr))`, limitado visualmente a tres columnas. Las tarjetas mantienen
  altura contenida y el mensaje se trunca a dos líneas.
- Discord se muestra como texto seleccionable, sin botón de copia.

### Aventuras sin narrador

- Cada aventura es una fila de ancho completo, no una tarjeta de media columna.
  El contenido usa una zona flexible de identidad y metadatos y una zona estable
  para estado y acción.
- Sin tema, la identidad es `Solicitud #ID`. Los metadatos existentes forman la
  primera línea; creador y participantes, la segunda; el comentario aparece una
  sola vez, se limita inicialmente a dos líneas y permite alternar entre `Ver
  más...` y `Ver menos` cuando es largo.
- `Me interesa narrar` se alinea a la derecha en escritorio y al final en móvil.
  Conserva el color de acción principal con tipografía y altura de botón normal.
- Los datos ausentes no se señalan ni impiden postularse como narrador.

### Ritmo y responsive

- Los títulos de Narradores disponibles y Aventuras sin narrador comparten patrón:
  título a la izquierda, contador informativo a la derecha y regla inferior.
- La distancia entre secciones es de 20 a 28 px. No se reserva altura para estados
  vacíos ni para columnas sin contenido.
- A menos de 700 px, franja, cuadrícula y filas pasan a una columna; los textos se
  envuelven y las acciones ocupan el ancho disponible sin solaparse.
- Esta revisión no incorpora filtros. Se evaluarán únicamente cuando el volumen
  haga difícil recorrer el listado.

## Crónicas autonarradas (implementado, 2026-10-08)

Estado: **implementado; pendiente de despliegue**.

### Solicitar aventura

- El primer control del formulario será un selector claro entre `Aventura` y
  `Autonarrada`. La segunda opción solo se renderiza para nivel 1 a 15.
- Al elegir Autonarrada, la sección Personas se omite por completo: el servidor
  fija al solicitante como jugador, único participante y su propio narrador.
- Datos de la aventura conserva isla y día, pero no tier ni inframundo.
- Descripción conserva el comentario público y elimina la propuesta para
  narradores.
- Un aviso compacto junto al tipo resume `1 post`, `mínimo 800 palabras` y
  `1 por semana`. No se crea una tarjeta explicativa grande.
- Si el nivel o el cupo impiden solicitarla, no se renderizan el selector de tipo
  ni menciones a la autonarrada; el formulario comienza directamente como aventura
  convencional.

### Mis aventuras

- Las autonarradas tienen un bloque propio y compacto, separado de `Activas de mi
  personaje` y `Aventuras que narro`.
- Cada fila muestra ID, nombre del tema o `Sin tema vinculado`, semana reservada,
  estado y, cuando corresponda, palabras validadas.
- El bloque no suma a los contadores ni cupos de aventuras convencionales.
- Las acciones contextuales son `Editar`, `Vincular tema`, `Ver tema` y, después
  de publicar, `Entregar autonarrada`; nunca aparecen `Buscar narrador`,
  `Postularme` o la entrega convencional.

### Consola

- Solicitudes usa un chip `Autonarrada` y omite datos irrelevantes como tier,
  narrador externo y número de participantes.
- Entregas conserva la pestaña existente. Un filtro de tipo permite `Todas`,
  `Aventuras` y `Autonarradas` sin añadir otra pestaña principal.
- La tarjeta autonarrada presenta una franja de comprobaciones: nivel, autor,
  un post, 800 palabras, semana y pago previo. Verde indica lista; rojo identifica
  el bloqueo concreto.
- El autor elige la orientación positiva o negativa en la entrega reducida del
  tablón. La consola muestra esa selección como dato de solo lectura.
- `Entregar recompensa y finalizar` es la única acción principal. Confirmación,
  procesamiento, éxito, fallo recuperable y ya pagada tienen estados visuales
  distintos y accesibles.

### Responsive y accesibilidad

- A 375 px, identidad, validaciones y recompensa pasan a una columna sin tabla
  horizontal.
- Tipo, estado y bloqueos siempre llevan texto; el color y los iconos son apoyo.
- El cambio de tipo conserva foco, etiquetas y mensajes de error próximos al
  control que los produce.

## Modo vista de otro personaje (2026-10-08)

- La cabecera incorpora, a la derecha, un botón morado `Ver otro personaje` con
  el patrón visual de Bitácora. El buscador por nombre o FID se despliega desde
  ese botón y no ocupa espacio dentro de ninguna pestaña.
- Novedades pertenece únicamente a Mis aventuras; el resto de pestañas no deja
  un panel vacío ni conserva el historial visible.
- Elegir un resultado abre `?modo_vista=FID` como una pantalla aislada: cabecera
  con la identidad, buscador para cambiar de personaje, botón de regreso y
  resumen de activas como jugador y como narrador.
- En esta pantalla no se renderizan las cuatro pestañas, Novedades, estadísticas
  privadas, filtros, tarjetas editables ni acciones del usuario conectado.
- Los títulos vinculados abren el tema en una pestaña nueva. Sin un tema visible
  se muestran como texto plano.

Estado del Tablón: **implementado y verificado, pendiente de despliegue**.
Estado de la Consola: **implementado y verificado, pendiente de despliegue**
(2026-10-07). Tablón y Consola se validaron a 375, 700, 1030 y 1440 px sin
desbordamiento horizontal.

## Evolución UX aprobada (2026-10-07)

Esta fase sucede después de los ocho puntos originales de este documento. Su
objetivo es convertir el tablón de un registro legible en una herramienta
orientada a tareas, sin perder la estética de `style.md`.

### Arquitectura de Mis aventuras

- Debajo de estadísticas aparece un control segmentado principal:
  `Activas`, `Historial` y `Todas`. Activas es la selección inicial.
- El resumen de cupos conserva `Activas de mi personaje` y `Aventuras que narro`,
  pero usa filas mínimas: título, fecha y estado. No incluye descripción ni
  duplica acciones.
- En móvil el resumen puede plegarse con un botón que conserva `aria-expanded`.
- Los filtros de estado solo muestran opciones pertinentes a la vista elegida.
- Cambiar vista, estado o papel actualiza URL, estadísticas y lista como una sola
  operación HTMX.

### Estadísticas informativas

- Las celdas son indicadores estáticos y no botones de filtrado.
- No tienen `hover`, `aria-pressed` ni estado seleccionado; los filtros explícitos
  de Vista, Estado y Papel son el único mecanismo para alterar el listado.
- Los contadores no cambian de ancho al pasar de una a varias cifras.
- En Otras aventuras se conservan solo total y estados; no vuelven los papeles.

### Terminología y siguiente paso

- Estados visibles: En revisión, Aprobada, Denegada, Finalizada y Buscando
  narrador.
- La tarjeta puede mostrar una línea `Siguiente paso` cuando existe una acción
  clara: aceptar propuesta, vincular tema, entregar, corregir entrega o esperar
  revisión.
- El estado y el siguiente paso son información distinta; no se reemplazan.

### Formularios y cupos

- El indicador superior cambia con el selector de papel. Como narrador se reduce
  a un mensaje neutral sin cifras de bloqueo.
- Cada chip de participante puede incluir `2/3 solitarias · 3/4 total` o un aviso
  de cupo agotado.
- El pie del formulario contiene el aviso de elegibilidad inmediatamente antes
  del botón. Si el servidor indica exceso, el botón está deshabilitado.
- El error también se anuncia en `aria-live` y enlaza o enfoca el participante
  que causa el bloqueo.

### Propuestas de narrador

- `Me interesa narrar` no asigna de inmediato. Abre una ventana que resume la
  aventura y permite añadir un mensaje corto antes de crear la candidatura.
- Mis aventuras muestra en la tarjeta un bloque compacto `Propuestas de narrador`
  con aceptar, rechazar y acceso al perfil/MP.
- Narradores & Narrados añade `Propuestas para narrar` encima del directorio:
  invitaciones recibidas y candidaturas enviadas, con estado y vencimiento.
- Aceptar es la única acción primaria; rechazar o retirar son secundarias y piden
  confirmación. Las propuestas vencidas son históricas, no accionables.
- Una aceptación actualiza ambas vistas y el contador de aventuras sin narrador.

### Tarjetas y datos contextuales

- La cabecera reserva espacio estable para `#ID · nombre del tema` y estado.
- Los nombres de personas son enlaces reconocibles y no convierten toda la tarjeta
  en un enlace accidental.
- Idea para narradores, ausencia de narrador y datos incompletos se muestran solo
  en los contextos definidos por `101_Requirements_Registro.md`.
- `Datos incompletos` abre o muestra una lista textual; no usa información solo al
  hover.
- El historial se abre como panel o diálogo con línea temporal. En móvil ocupa el
  ancho disponible y devuelve el foco al control que lo abrió.

### Paginación y estado de URL

- Paginadores compactos: primera, anterior, hasta cinco páginas cercanas,
  siguiente y última.
- Botones ausentes se sustituyen por espacio estable cuando sea necesario para no
  desplazar el centro del paginador.
- Tras navegar se enfoca el resumen `N aventuras · Página X de Y` y se posiciona
  el comienzo del listado bajo las pestañas.
- Los controles se derivan de la URL. No existe un segundo estado oculto en CSS
  que contradiga `vista`, `estado`, `rol`, `q`, `pagina` o `densidad`.

### Notificaciones

- El tablón muestra, debajo de las pestañas, un panel persistente `Novedades`
  inspirado en Bitácora, con la cantidad no leída y sin abrir un modal.
- Las pendientes usan filas compactas con evento, solicitud, fecha y enlace. Las
  leídas permanecen en un desplegable `Historial reciente`; marcar como leída
  actualiza únicamente el panel y no elimina el historial de la aventura.
- La notificación de oferta próxima a vencer enlaza directamente a Renovar.
- El contador de la pestaña Narradores sigue reservado a aventuras sin narrador;
  no mezcla propuestas ni notificaciones en una cifra ambigua.

### Elementos que se reducen

- En móvil se oculta el selector de densidad y se usa Compacta.
- Historial no muestra Idea para narradores en primer nivel.
- El resumen de activas no repite comentarios, personas ni botones de la tarjeta.
- Las estadísticas de Otras permanecen como resumen informativo del conjunto
  filtrado y no añaden una segunda navegación implícita.

## Objetivo

Hacer que `op/tablon_de_aventuras.php` sea más rápido de leer y operar sin perder
la identidad visual de One Piece Gaiden. Los ocho puntos visuales originales no
alteran permisos ni reglas de negocio; la evolución UX 2026-10-07 sí incorpora
las reglas aprobadas de propuestas, cupos anticipados y notificaciones. Se
mantiene la interacción con HTML, CSS y HTMX siempre que sea suficiente.

## Orden obligatorio de implementación

Los puntos se implementan y verifican en este orden. Cada punto puede apoyar al
siguiente, pero no debe introducir acciones que el usuario no tenga autorizadas.

### 1. Tarjetas compactas y comentarios expandibles

- Reducir padding y separación vertical de las tarjetas de solicitudes.
- El comentario público se muestra inicialmente con un máximo de tres líneas.
- Si existe comentario, un control accesible `Ver descripción` / `Ocultar descripción`
  permite expandirlo sin petición al servidor.
- Idea para narradores, renuncia, denegación y entrega mantienen su visibilidad
  completa porque contienen información excepcional o necesaria para actuar.
- La vista expandida del punto 8 elimina el recorte y oculta el control individual.

### 2. Metadatos compactos y datos incompletos

- La cabecera contiene solo `#ID` y el estado.
- Isla, fecha de rol y tier forman una única fila de metadatos con iconos y texto.
- Los valores ausentes no compiten con el título: se agrupan en un chip
  `Datos incompletos`, con `title` y texto accesible que enumera qué falta.
- Creador, narrador y participantes se conservan en un bloque compacto y legible.

### 3. Jerarquía de acciones

- Cada tarjeta elige una acción principal según contexto: postularse, entregar,
  corregir entrega, vincular o editar, en ese orden.
- La acción principal usa `btn-op btn-op--primario` e icono Font Awesome.
- Las acciones restantes quedan en un menú nativo `<details>` llamado `Más acciones`.
- Las acciones peligrosas conservan color rojo, confirmación y permisos actuales.
- Si solo existen acciones secundarias, la primera acción útil puede actuar como
  principal; nunca se cambia la autorización de una acción.

### 4. Cabecera coherente con el foro

- Añadir una clase específica al `<body>` del tablón para poder limitar cualquier
  ajuste futuro a esta página.
- Conservar el banner gráfico `#logo`, el bloque editorial `¿Sabías que…?`, el
  menú, los indicadores de staff, Bitácora y los avisos privados.
- No añadir relleno ni altura artificial entre la cabecera y el contenido.
- No modificar `header.html` ni el encabezado de otras páginas.
- Dentro del tablón, sustituir la barra naranja pesada por un encabezado compacto
  de herramienta con icono, título alineado a la izquierda y línea de acento.
- Las pestañas forman una barra segmentada continua con iconos y subrayado para la
  activa. Orden: Mis aventuras, Solicitar aventura, Narradores & Narrados y Otras
  aventuras.

### 5. Narradores comparables

- Extender `op_narradores_disponibles` con campos retrocompatibles:
  `frecuencia`, `tiers` y `estilo`.
- Frecuencia es una opción cerrada: diaria, varias por semana, semanal o flexible.
- Tiers es una selección textual validada de tiers 1 a 10; puede quedar vacía.
- Estilo es texto corto, pensado como etiqueta de género o enfoque narrativo.
- El formulario permite editar los tres campos y las tarjetas los muestran como
  chips junto con carga actual y aventuras finalizadas.
- Ofertas antiguas continúan válidas con campos vacíos.

### 6. Contador integrado de aventuras sin narrador

- Sustituir la cifra aislada por un chip junto al título:
  `1 esperando narrador` / `N esperando narrador`.
- Mantener `aria-label`, actualización HTMX y contador de la pestaña.

### 7. Color semántico consistente

- Verde: aprobado, vigente, disponible y entrega completada.
- Morado: pendiente de narrador y navegación secundaria.
- Naranja: creado, navegación activa y acción principal.
- Rojo: denegación, error y acciones destructivas.
- Gris: finalizado, vencido o información neutral.
- El estado no dependerá solo del color: siempre conserva texto y borde.

### 8. Densidad compacta y expandida

- Mis aventuras y Otras aventuras muestran un control segmentado con dos radios:
  `Compacta` y `Expandida`.
- Compacta es la predeterminada: usa dos columnas desde 901 px, comentarios de
  una línea, tipografía secundaria menor y acciones más pequeñas. La expansión
  individual de un comentario sigue disponible.
- Expandida usa una sola columna, muestra completos los comentarios, oculta el
  control individual y aumenta el espaciado.
- El control no se envía como filtro ni cambia datos. Permanece al refrescar el
  listado por HTMX porque vive fuera de `#ta-lista-*`.
- Aventuras sin narrador usa la densidad compacta para facilitar comparación.

## Fase aprobada: rediseño de la Consola de aventuras

Estado: **implementado y verificado, pendiente de despliegue** (2026-10-07).

La Consola reutiliza el lenguaje visual del Tablón, pero se diseña como una cola
operativa de staff: prioriza comparar, resolver y continuar con la siguiente tarea.
No replica elementos decorativos ni controles de densidad que no aporten al flujo.

### 1. Responsive real

- El armazón completo usa ancho fluido, `min-width: 0` y dimensiones acotadas.
- Se elimina cualquier ancho heredado que produzca una página de 1100 px en móvil.
- Pestañas, filtros, filas, formularios e historial se verifican a 375, 700, 1030
  y 1440 px sin desplazamiento horizontal.

### 2. Encabezado compacto

- Icono de herramienta, `h1` "Consola de aventuras", texto breve y línea naranja.
- Se conserva toda la cabecera global del foro y no se añade espacio artificial.
- Desaparece la combinación de barra naranja pesada más caja descriptiva.

### 3. Resumen de trabajo

- Una banda compacta informa: por revisar, entregas pendientes, borrados pendientes
  y sin narrador.
- Es informativa; las pestañas y filtros siguen siendo los controles de navegación.
- Las colas con cero mantienen una cifra estable sin reclamar atención visual.

### 4. Integridad antes de aprobar

- `Datos incompletos` enumera isla, día, tier, comentario, narrador o participantes.
- Si falta algo, la acción principal es `Completar datos` y Aprobar está desactivado.
- La comprobación visual refleja la validación obligatoria del servidor.

### 5. Filas compactas y comparables

- Cabecera: `#ID · título`, antigüedad y estado.
- Metadatos: isla, día y tier; personas: creador, narrador y participantes.
- Comentario, idea, nota interna y motivos se pliegan en Detalles.
- La altura inicial no depende de la longitud del comentario público.

### 6. Pestañas segmentadas

- Tres columnas: Solicitudes, Entregas y Narradores disponibles.
- Cada una incluye icono Font Awesome grande, texto y subrayado naranja activo.
- El contador de Entregas está integrado y no cambia la alineación.

### 7. Jerarquía de acciones

- Solo una acción primaria por fila: Aprobar, Completar datos o Revisar y pagar.
- Denegar conserva semántica peligrosa.
- Editar, Historial y Finalizar viven en `Más acciones`; Eliminar sigue reservado
  a staff y exige confirmación.
- Toda acción HTMX se deshabilita mientras está en curso.

### 8. Filtros de cola

- Estado conserva sus conteos junto a la búsqueda por solicitud o persona.
- Orden inicial: más recientes primero; se puede invertir explícitamente.
- Estado, búsqueda y orden activos aparecen en la URL.

### 9. Búsqueda unificada

- Un typeahead acepta `#ID`, UID o nombre de personaje.
- Busca creador, narrador y participantes; cada sugerencia explica su coincidencia.
- La selección usa un valor estable, se puede quitar y se conserva al paginar.

### 10. Historial enfocado

- Línea temporal compacta, con fecha, actor, acción y cambios legibles.
- Se abre en panel lateral o diálogo en escritorio y a ancho completo en móvil.
- Solo uno puede estar abierto; gestiona foco de apertura y retorno.

### 11. Paginación de narradores

- Veinte ofertas por página, con el mismo paginador compacto del Tablón.
- Carga completa, navegación HTMX, URL y botón atrás representan la misma página.

### 12. Contexto completo de narradores

- Cada oferta incluye frecuencia, tiers, estilo, aventuras en curso y finalizadas,
  además de Discord, mensaje, vigencia y fechas.
- Hay filtros de vigencia y búsqueda por UID o nombre.

### 13. Conteos reutilizados

- Una respuesta calcula una sola vez conteos de entregas y estados.
- Resumen, pestañas y filtros consumen el mismo resultado para no discrepar.

### 14. CSS compartido

- Tarjetas, estados, chips, pestañas, modales, foco, vacíos y paginador se extraen
  a una hoja común del sistema de aventuras.
- Los templates de Tablón, Consola y Recompensas conservan únicamente composición
  y excepciones propias.

### 15. Semántica y accesibilidad

- Un `h1`; secciones con `h2`; cada solicitud, entrega u oferta es un `article`.
- Resultados anuncian total y página; cambios HTMX enfocan el bloque pertinente.
- Todos los controles tienen nombre, foco visible, teclado y área táctil adecuada.
- Estado y acciones siempre tienen texto; iconos y colores son complementarios.

## Responsive y accesibilidad

- La estadística usa una banda compacta: seis estados a la izquierda y dos roles
  visualmente separados a la derecha en Mis aventuras; Otras aventuras muestra
  únicamente los seis estados a todo el ancho. En móvil se distribuye en filas de
  dos columnas. Sus etiquetas no se dividen en varias líneas.
- El resumen de activas mantiene solitarias y grupales en columnas separadas y
  presenta cada aventura como una fila compacta. Las aventuras narradas eliminan
  el subtítulo redundante y forman una cuadrícula de dos columnas en escritorio.
- Solicitar aventura agrupa el formulario en Personas, Datos de la aventura y
  Descripción. La propuesta condicional para narradores aparece después del
  comentario público; inframundo queda con los datos y el envío cierra el flujo.
- Narradores & Narrados presenta la oferta propia como franja compacta de gestión,
  excluye esa misma oferta del directorio que ve su autor y mantiene una cuadrícula
  uniforme para los demás narradores. Un único resultado conserva un ancho máximo
  y no se estira por toda la página; fecha y Discord comparten el pie de la tarjeta.
  Las aventuras pendientes usan tarjetas compactas, omiten el dato redundante
  `Narrador: Sin narrador` y mantienen la acción de postularse visible.
- Ningún control puede provocar desbordamiento horizontal a 375, 700, 1030 o
  1440 px.
- Acciones y metadatos envuelven líneas; los botones mantienen un área cómoda.
- Controles de expansión, densidad y menús funcionan con teclado y foco visible.
- Iconos son complementarios; todo comando mantiene texto o `aria-label`.
- Se evita anidar tarjetas y se mantienen radios, chips y botones del sistema visual.

## Verificación

1. Sintaxis PHP de dominio, vistas y página.
2. Pruebas de dominio para migración, validación y persistencia de ofertas.
3. Pruebas de página para estructura compacta, jerarquía de acciones, densidad,
   contador integrado y campos nuevos.
4. Capturas Playwright en 1440, 1030, 700 y 375 px, sin desbordamiento.
5. Interacciones: expandir comentario, abrir acciones, cambiar densidad y editar
   una oferta de narrador.
6. Consola: `scrollWidth <= clientWidth` en sus tres pestañas y estados
   expandibles a 375, 700, 1030 y 1440 px.
7. Consola: solicitud incompleta no aprobable, búsqueda por los tres tipos de
   persona, filtros/orden en URL, 21 ofertas paginadas y conteos sin duplicación.
8. Consola: recorrido completo con teclado por pestañas, filtros, acción primaria,
   menú secundario, historial y retorno de foco.

## Despliegue

- Ejecutar la migración de columnas antes de desplegar el PHP que las consulta.
- Importar de nuevo `op_tablon_de_aventuras` después de actualizar el template.
- Al implementar la fase de Consola, importar también `staff_consola_aventuras`
  y `staff_aventuras_recompensas` después de desplegar la hoja compartida.
- El tablón conserva la cabecera compartida y no exige importar `header.html`.
