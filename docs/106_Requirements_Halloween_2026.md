# Requisitos: Página y tienda de Halloween 2026

## 1. Propósito

Este documento define los requisitos funcionales y técnicos de la página
`/op/halloween2026.php`, que será el centro digital de **La Cosecha de
Calabazas Malditas**. La página reunirá:

- la información principal del evento;
- el saldo y el historial personal de Calabazas Malditas;
- las estadísticas y clasificaciones del evento;
- la Tienda de Calabazas Malditas.

La página debe seguir siendo consultable una vez terminado el evento. Sus
resultados y ganadores formarán parte del historial del foro y no se
eliminarán al cerrar la tienda.

La arquitectura detallada y el plan de implementación se documentan en
`106_DesignPlan_Halloween.md`.

## 2. Decisiones ya tomadas

### 2.1 Identidad y fechas

- Nombre del evento: **La Cosecha de Calabazas Malditas**.
- Moneda: **Calabazas Malditas**. En espacios pequeños puede mostrarse como
  **Calabazas**, siempre acompañada de su icono.
- Periodo de obtención: desde el **26 de octubre de 2026 a las 06:00** hasta
  el **10 de noviembre de 2026 a las 06:00**, hora de Madrid. Esto incluye la
  actividad del 9 de noviembre hasta el reinicio diario siguiente.
- El día del evento se reinicia a las **06:00, hora peninsular española**.
- La zona horaria técnica será `Europe/Madrid`; no se debe utilizar una
  diferencia UTC fija, porque debe respetarse el horario oficial de España.
- Para que el 9 de noviembre sea un día completo del evento, el intervalo
  técnico recomendado es desde el 26 de octubre de 2026 a las 06:00 hasta el
  10 de noviembre de 2026 a las 05:59:59, hora de Madrid.
- La tienda permanecerá cerrada durante la comprobación inicial, se abrirá
  manualmente después de validar una compra completa y cerrará el
  **16 de noviembre de 2026 a las 23:59**, hora de Madrid.
- Las fechas se guardarán en una configuración central y no se repetirán
  directamente en varias páginas o archivos.

### 2.2 Activos visuales

| Uso | Referencia entregada |
|---|---|
| Banner de la tienda | `https://i.imgur.com/kdGOe7n.jpeg` |
| Banner de estadísticas | `https://i.imgur.com/tYndMUv.jpeg` |
| Decoración de la tienda | `https://i.imgur.com/G5okouN.png` |

- La decoración sustituirá, solo en la tienda de Halloween, a
  `/images/op/uploads/NamiUwUNikas_One_Piece_Gaiden_Foro_Rol.png`.
- Los archivos deberán descargarse y servirse desde una ruta local, por
  ejemplo `/images/op/uploads/halloween2026/`. Imgur será la fuente del
  material, pero no una dependencia en producción.
- El banner de tienda entregado se utilizará en su estado actual durante esta
  primera versión.
- Las imágenes deben tener `alt`, dimensiones reservadas y versiones
  adaptables para que no produzcan saltos de contenido al cargar.

### 2.3 Tienda de referencia

La tienda tomará como referencia funcional a `/op/tienda_kuros.php`:

- catálogo por categorías;
- selección de artículos y cantidades;
- carrito;
- total de la compra;
- saldo actual y saldo restante;
- entrega de objetos y recompensas especiales.

No se copiará literalmente su implementación. La tienda de Kuros actual
carece de una transacción única para compra, entrega y cobro, confía en parte
del carrito enviado por el navegador y calcula algunos límites leyendo texto
de auditoría. La tienda de Halloween debe resolver esos problemas desde su
primera versión.

### 2.4 Persistencia de Calabazas

- Cualquier dato relacionado con Calabazas Malditas se guardará exclusivamente
  en tablas específicas `mybb_op_halloween2026_*`: saldo, ganancias, gastos, regalos, ajustes,
  reversiones, compras y referencias de origen.
- No se creará una tabla genérica `mybb_op_eventos` ni se mezclarán monedas de
  celebraciones distintas mediante un `event_id`.
- No se añadirá ninguna columna de Calabazas a `mybb_op_fichas` ni se
  reutilizará una de sus monedas existentes.
- El sistema puede consultar `mybb_op_fichas` para validar que el personaje
  existe, está activo o puede recibir una recompensa, pero no puede modificar
  esa tabla para almacenar o contabilizar Calabazas.
- Las recompensas adquiridas en la tienda conservarán su funcionamiento
  normal: por ejemplo, una compra puede entregar Nikas, Berries o puntos de
  oficio mediante los helpers económicos correspondientes. Esa entrega no
  convierte a `mybb_op_fichas` en el almacén de la moneda del evento.

## 3. Alcance

### 3.1 Incluido

- Página del evento, restringida a personajes con ficha activa y aprobada,
  con sus estados antes, durante y después.
- Obtención automática de Calabazas Malditas por actividad válida.
- Saldo e historial individual.
- Estadísticas globales y clasificaciones.
- Tienda configurable desde base de datos.
- Compras atómicas, auditadas e idempotentes.
- Herramienta de staff para movimientos manuales y administración de tienda.
- Archivo histórico del evento.

### 3.2 Fuera de alcance de esta primera entrega

- Rediseñar `/op/tienda_kuros.php`.
- Cualquier integración con el sistema permanente de logros. Halloween 2026
  se implementará sin depender de `/op/logros.php`.
- Reemplazar el tema principal o los temas de concursos del foro.
- Crear el sistema de votación de los concursos.
- Programar secretos ocultos o sus recompensas.

## 4. Experiencia de la página

### 4.1 Navegación

`/op/halloween2026.php` tendrá cuatro vistas principales, conservando la vista
activa en la URL:

1. **Evento**: resumen, fechas, reglas de obtención y enlaces a los temas.
2. **Estadísticas**: cifras globales, clasificación y resultados históricos.
3. **Tienda**: catálogo, carrito, saldo y compras.
4. **Mi actividad**: desglose de Calabazas obtenidas, gastadas y ajustadas.

Cada vista se cargará de manera independiente. Entrar en la página no debe
descargar al mismo tiempo todo el catálogo, todas las estadísticas y todo el
historial del usuario.

Enlaces iniciales:

- tema principal: `https://onepiecegaiden.com/showthread.php?tid=9471`;
- concurso de historias: `https://onepiecegaiden.com/showthread.php?tid=9535`;
- concurso de monstruos: `https://onepiecegaiden.com/showthread.php?tid=9536`.

Toda `/op/halloween2026.php`, incluidas las vistas **Evento** y
**Estadísticas**, requiere una sesión válida y una ficha activa y aprobada.
Invitados y personajes sin ficha, con ficha inactiva o no aprobada no pueden
entrar en la sección ni consultar historial. Sus registros internos, si los
hubiera, se conservan exclusivamente para auditoría.

### 4.2 Cabecera y estado

La cabecera mostrará:

- nombre del evento;
- fecha o estado actual;
- saldo personal, cuando exista una sesión válida;
- acceso al tema principal;
- acceso a los concursos.

Estados previstos:

| Estado | Comportamiento |
|---|---|
| Próximamente | Muestra fecha de apertura; no concede moneda ni permite comprar |
| Obtención activa | Registra actividad, muestra estadísticas y permite comprar si la tienda está abierta |
| Reclamaciones | Ya no hay obtención automática; la tienda y los ajustes manuales pueden continuar |
| Tienda abierta | Se puede gastar el saldo, aunque haya terminado la obtención |
| Archivado | Solo lectura: estadísticas, resultados e historial |

Las fechas de obtención, reclamación y tienda son configuraciones diferentes.

### 4.3 Diseño visual

- Seguir `docs/style.md` y el lenguaje visual actual del foro.
- Usar el banner de estadísticas en esa vista y el banner de tienda en la
  vista correspondiente.
- La ilustración `G5okouN.png` acompañará el carrito en escritorio y pasará a
  un bloque secundario en móvil; nunca debe reducir el catálogo a una columna
  incómoda.
- El catálogo usará una cuadrícula responsive de artículos. Las tarjetas
  mostrarán imagen, nombre, coste, disponibilidad y límite, sin depender de
  tooltips para información esencial.
- El carrito será lateral en escritorio y un resumen fijo o desplegable en
  móvil.
- No se usarán secciones dentro de tarjetas decorativas anidadas.
- Todos los controles deben tener foco visible, etiquetas accesibles y
  estados de carga, vacío, error y agotado.

## 5. Obtención automática

### 5.1 Reglas

Durante el periodo activo se concederá:

| Actividad | Recompensa | Condiciones |
|---|---:|---|
| Publicar un post | 1 Calabaza | Solo en zonas de rol elegibles |
| Crear un tema | 2 Calabazas | Solo temas Comunes y Aventuras |
| Crear un tema con etiqueta `Spooky` | 3 Calabazas en total | Sustituye las 2 del tema normal; no se acumulan |
| Primer post diario | 1 Calabaza adicional | Una vez por día de evento; reinicio a las 06:00 de Madrid |
| Regalo impredecible de Kuro | Cantidad variable | Puede recibirse más de una vez; no requiere reclamación |

El primer mensaje de un tema puede conceder simultáneamente:

- 1 Calabaza por el post;
- 2 por el tema común/aventura, o 3 si lleva `Spooky`;
- 1 adicional si es el primer post diario del personaje.

La bonificación `Spooky` reemplaza únicamente la recompensa de creación del
tema, no la recompensa normal del post ni el primer post diario.

### 5.2 Actividad elegible

- Las zonas de rol se identificarán desde una configuración central de FID o
  por la jerarquía de foros de MyBB. No se repetirá una lista hardcodeada en
  varios archivos.
- Antes de implementar se confirmará el FID raíz de la zona de rol y los PID
  de los prefijos `Común`, `Aventura` y `Spooky`.
- La etiqueta `Spooky` se resolverá mediante la tabla de prefijos de MyBB o
  una configuración central; no se dependerá de un PID escrito directamente
  en el código de negocio.
- Solo cuentan posts y temas visibles y aprobados.
- Solo generan Calabazas las cuentas con una ficha activa y aprobada en el
  momento de la publicación o de su aprobación posterior.
- Editar un mensaje no genera una nueva recompensa.
- Mover un tema posteriormente a una zona elegible no concede recompensas de
  forma retroactiva.
- El borrado o desaprobación de contenido premiado genera un movimiento de
  reversión. El movimiento original no se elimina.
- Restaurar o aprobar posteriormente puede volver a conceder la recompensa,
  mediante una nueva transición idempotente enlazada al movimiento original.
- Un tema con `Spooky` nunca puede recibir a la vez los movimientos de 2 y 3
  Calabazas por su creación.

### 5.3 Integración con MyBB

La concesión debe ejecutarse después de que MyBB haya insertado y aprobado el
post o tema correctamente. Un plugin pequeño escuchará
`datahandler_post_insert_post_end` y
`datahandler_post_insert_thread_end`, hooks que ya existen en esta instalación
de MyBB, y llamará a un servicio de evento centralizado.

No se debe duplicar la lógica económica en `newthread.php`, `newreply.php` y
la página de Halloween. El servicio será la única autoridad para:

- comprobar fechas y foros;
- calcular el día de evento;
- construir la clave idempotente;
- registrar el movimiento;
- actualizar el saldo.

Para cada publicación, el servicio leerá conjuntamente post, tema y foro y
obtendrá: PID, TID, UID, fecha original, visibilidad, FID, jerarquía del foro,
prefijo y si el PID es el primer post del tema. La evaluación se hará en este
orden:

1. rechazar invitados, fichas inexistentes, inactivas o no aprobadas,
   contenido no visible y fechas fuera del periodo de obtención;
2. comprobar que el foro es `fid=10` o descendiente de la zona de rol;
3. conceder 1 Calabaza por el post;
4. si es el primer post, conceder 3 por `Spooky` o 2 por `Común`/`Aventura`;
5. intentar conceder el bono del primer post del día.

La creación de un tema se procesa una sola vez desde el primer post: no habrá
una segunda lógica separada que pueda duplicar su recompensa.

### 5.4 Prefijos y recompensa del tema

MyBB permite un único prefijo por tema. Para este evento, `Spooky` sustituye
al prefijo ordinario a efectos del cálculo:

```php
if ($prefixId === $spookyPrefixId) {
    $threadReward = 3;
} elseif (in_array($prefixId, [$commonPrefixId, $adventurePrefixId], true)) {
    $threadReward = 2;
} else {
    $threadReward = 0;
}
```

Por tanto, un tema `Spooky` recibe 3 y nunca 2 + 3. Los IDs de los prefijos se
resolverán desde una configuración central. Los valores comprobados en
`mybb_threadprefixes` son `Común = 1`, `Aventura = 3` y `Spooky = 15`; la
lógica no repetirá estos números en distintos archivos.

Cambiar el prefijo después de crear el tema no genera una recompensa nueva ni
recalcula automáticamente la ya concedida.

### 5.5 Claves idempotentes

Cada concesión tendrá una identidad estable:

| Concepto | Ejemplo de clave |
|---|---|
| Post | `halloween2026:post:12345` |
| Tema | `halloween2026:thread:987` |
| Primer post diario | `halloween2026:daily:1017:2026-11-05:12345` |

La base de datos impondrá una restricción única para que un doble clic, una
recarga, un reintento del hook o dos peticiones concurrentes no dupliquen una
recompensa. El bono diario tendrá además un registro único por personaje y
día de Halloween, asociado al PID que lo obtuvo.

La clave mostrada para post o tema identifica su primera concesión. Si el
contenido atraviesa ciclos posteriores de reversión y restauración, cada
transición tendrá su propia clave idempotente y quedará enlazada al movimiento
original; la historia nunca se sobrescribe.

### 5.6 Primer post diario

- El día se calcula en `Europe/Madrid` y comienza a las 06:00.
- Para calcularlo se convierte el `dateline` del post a `Europe/Madrid`, se
  restan seis horas y se obtiene la fecha resultante.
- Se almacena un registro único por UID y fecha de Halloween.
- Dos posts enviados casi simultáneamente solo pueden producir una
  bonificación diaria.
- Debe contarse el primer post elegible, no el primer post del usuario en
  cualquier sección del foro.
- Si se elimina o desaprueba el post que poseía el bono, se revierte el
  movimiento y el siguiente post válido de ese día puede ocuparlo. Restaurar
  el post original no duplica ni desplaza un bono que ya tenga otro PID.

### 5.7 Ejemplos de cálculo

| Acción | Movimientos | Total |
|---|---|---:|
| Respuesta de rol que es el primer post diario | `+1 post`, `+1 diario` | 2 |
| Tema Común cuando ya publicó ese día | `+1 post`, `+2 tema` | 3 |
| Tema `Spooky` como primera publicación diaria | `+1 post`, `+3 tema`, `+1 diario` | 5 |

### 5.8 Moderación y reconciliación

El plugin también escuchará los hooks existentes de aprobación,
desaprobación, borrado suave, restauración y borrado definitivo de posts y
temas. Estos hooks no aplicarán cantidades a mano: solicitarán al mismo
servicio que reconcilie el contenido con su estado actual.

- Aprobar o restaurar contenido publicado durante el evento puede conceder lo
  que le corresponda, aunque la moderación ocurra después del cierre.
- Desaprobar o borrar crea movimientos de reversión; nunca elimina el
  movimiento original.
- Eliminar un tema revierte tanto su recompensa de creación como las
  recompensas de sus posts.
- Mover un tema fuera de la zona elegible revierte sus recompensas.
- Mover un tema hacia una zona elegible no concede recompensas retroactivas.
- Editar un mensaje o cambiar su prefijo no genera Calabazas nuevas.

### 5.9 Regalo de Kuro

El regalo se concederá desde una acción de staff o un proceso explícito del
evento. Debe aceptar:

- personaje receptor;
- cantidad positiva;
- identificador único del regalo o reparto;
- nota opcional;
- UID de quien lo ejecutó.

El mismo personaje puede recibir regalos diferentes varias veces, pero el
mismo reparto no puede aplicarse dos veces por un doble clic o reintento.

### 5.10 Presets de participación

La consola de staff ofrecerá estos premios predefinidos:

| Preset | Recompensa | Límite |
|---|---:|---|
| Participar en el Concurso de Historias de Miedo | 10 Calabazas | Una vez por personaje |
| Participar en el Concurso de Monstruos Salvajes | 10 Calabazas | Una vez por personaje |
| Completar una Aventura `Spooky` | 5 Calabazas | Una vez por personaje |
| Usar avatar temático en el foro | 5 Calabazas | Una vez por personaje |
| Usar avatar temático en Discord | 5 Calabazas | Una vez por personaje |

Cada concesión exigirá seleccionar el personaje y quedará registrada con el
UID del staff, fecha, preset y una clave idempotente única. La restricción se
aplicará en base de datos y no solo desactivando el botón en la interfaz.
No será obligatorio adjuntar una referencia: se asume que el moderador realizó
la verificación antes de concederlo.

No se incluirá ningún preset ni recompensa por participar o narrar
**Llamada del Grand Line**.

## 6. Saldo e historial

### 6.1 Principios

- Las Calabazas no se guardarán en `mybb_op_fichas` bajo ninguna forma, ni
  siquiera como columna temporal, texto serializado o reutilización de otra
  moneda.
- Todo dato persistente de Calabazas se escribirá en la tabla
  `mybb_op_halloween2026_*` que le corresponda.
- Todo cambio se representará mediante un movimiento inmutable.
- El saldo será la suma materializada de ganancias, gastos y reversiones para
  que consultar la página sea rápido.
- Un ajuste no borra otro movimiento: crea una corrección vinculada al
  original.

### 6.2 Mi actividad

El usuario verá:

- saldo disponible;
- total obtenido;
- total gastado;
- desglose por fuente;
- historial paginado con fecha, concepto e importe;
- enlace al post, tema, compra o reclamación cuando exista.

Las cantidades positivas y negativas deben distinguirse también mediante
texto y signo, no solo por color.

## 7. Estadísticas

### 7.1 Resumen global

La vista mostrará como mínimo:

- Calabazas concedidas;
- Calabazas gastadas;
- Calabazas actualmente en circulación;
- participantes distintos;
- posts válidos;
- temas válidos;
- temas `Spooky`;
- bonificaciones de primer post diario;
- compras realizadas.

### 7.2 Clasificación principal

La clasificación “Grandes recolectores” se ordenará por **total neto
obtenido**, no por saldo actual. Incluye premios automáticos, concursos,
aventuras `Spooky`, avatares, regalos de Kuro y otras concesiones manuales
positivas. Gastar Calabazas en la tienda no debe bajar a un usuario en el
ranking; una corrección o reversión sí ajusta el total cuando invalida una
ganancia anterior.

Cada fila mostrará:

- posición;
- personaje y enlace a su ficha;
- total obtenido;
- saldo actual;
- desglose resumido por actividad.

En caso de empate, quedará primero quien alcanzó antes ese total; si todavía
hay empate, se usará el UID para obtener un orden estable.

### 7.3 Otras estadísticas

Podrán mostrarse como bloques secundarios:

- mayor número de posts válidos;
- mayor número de temas `Spooky`;
- artículos más comprados;
- resultados de concursos cargados por staff.

Estas estadísticas no deben competir visualmente con la clasificación
principal solicitada: quién ha ganado más Calabazas.

### 7.4 Rendimiento

- La página no contará posts recorriendo `mybb_posts` en cada visita.
- Las estadísticas saldrán del libro de movimientos y de contadores
  materializados.
- El resumen y el ranking podrán almacenarse en caché entre 1 y 5 minutos.
- El historial y las clasificaciones completas usarán paginación.
- Deben existir índices por usuario, fuente y fecha.

## 8. Tienda de Calabazas Malditas

### 8.1 Catálogo

Cada artículo será configurable con:

- nombre y descripción;
- imagen;
- categoría;
- coste en Calabazas;
- tipo de recompensa;
- recompensa y cantidad;
- orden de aparición;
- fecha de apertura y cierre opcionales;
- stock global opcional;
- límite por personaje opcional;
- estado activo/inactivo.

Tipos de recompensa iniciales:

- objeto de inventario;
- Nikas;
- Berries;
- experiencia;
- puntos de oficio;
- paquete compuesto por varias recompensas.

Los artículos, costes, existencias y límites se cargarán como datos y no se
programarán mediante un `switch`. El catálogo inicial será:

| Artículo | Recompensa | Coste |
|---|---:|---:|
| Cofre Calabaza | 1 objeto `CFRHLW` | 7 Calabazas |
| Nika | 1 Nika | 5 Calabazas |
| Puntos de oficio | 100 puntos | 3 Calabazas |
| Berries | 50.000 Berries | 1 Calabaza |

Estos artículos no tendrán stock global ni límite por personaje: las compras
serán ilimitadas mientras la tienda esté abierta y el personaje tenga saldo.
Podrán repetirse en una misma compra o en compras posteriores.

La obtención automática se habilitará el 26 de octubre a las 06:00 de Madrid.
La tienda se mantendrá cerrada durante la comprobación inicial y se abrirá
manualmente después de validar una compra completa. El Cofre Calabaza puede
estar visible y activo aunque su tabla interna de premios se configure más
adelante, ya que comprarlo solo entrega el objeto `CFRHLW` al inventario.

### 8.2 Carrito

- Añadir o quitar artículos y cambiar cantidades.
- Mostrar subtotal por línea, total y saldo restante estimado.
- Respetar stock y límite personal antes de añadir y al confirmar.
- Desactivar la compra cuando el carrito esté vacío o no haya saldo.
- Mostrar un resumen final antes de confirmar.
- Tras comprar, mostrar recibo con número de operación, saldo anterior,
  artículos, coste total, recompensas entregadas y saldo final.

El navegador puede calcular una vista previa, pero el servidor vuelve a leer
precios, límites, stock y saldo. Nunca se cobra según el total enviado por
JavaScript.

### 8.3 Compra atómica

La confirmación debe ejecutarse en una transacción de base de datos:

1. validar sesión, ficha, CSRF y estado de la tienda;
2. bloquear el saldo del personaje;
3. volver a cargar artículos, precios, límites y stock;
4. validar que el saldo alcanza;
5. crear la compra y sus líneas;
6. entregar todas las recompensas;
7. descontar las Calabazas mediante un movimiento;
8. confirmar la transacción.

Si cualquier paso falla, no se entrega ni se cobra nada. Una clave de
idempotencia impedirá que recargar la página o pulsar dos veces duplique la
compra.

### 8.4 Historial de compras

El usuario podrá consultar sus compras con:

- número de compra;
- fecha;
- artículos y cantidades;
- total pagado;
- estado.

Staff podrá buscar por usuario, compra o artículo.

## 9. Sistema de logros

La integración con `/op/logros.php` queda aplazada y fuera del alcance de esta
entrega. Halloween 2026 no creará colas, concesiones pendientes ni dependencias
con el sistema de logros.

## 10. Herramienta de staff

Se añadirá `/op/staff/halloween2026.php`, restringida mediante los helpers de
permisos existentes del proyecto. El acceso requerirá `is_staff`.

Debe permitir:

- buscar un personaje y consultar saldo e historial;
- conceder recompensas manuales con motivo y referencia;
- registrar regalos de Kuro;
- conceder los presets de participación definidos, respetando una sola
  concesión de cada preset por personaje;
- revertir un movimiento mediante un contramovimiento;
- administrar artículos, precios, límites, stock y fechas de tienda;
- consultar compras y recibos;
- registrar resultados de concursos;
- exportar movimientos, compras y estadísticas a CSV;
- recalcular y verificar un saldo sin modificar silenciosamente el historial.

No existirá un campo para sobrescribir directamente el saldo. Toda corrección
debe dejar rastro.

## 11. Modelo de datos propuesto

Los nombres definitivos se validarán en el plan técnico. Como mínimo se
necesitan estas entidades InnoDB:

### 11.1 Configuración y economía

`mybb_op_halloween2026_config`

- fila única de configuración de Halloween 2026;
- fechas de obtención, reclamación y tienda;
- zona horaria;
- estado y configuración JSON limitada.

`mybb_op_halloween2026_saldos`

- `uid` como clave única;
- `ganado_total`, `gastado_total`, `saldo`;
- fechas de creación y actualización.

`mybb_op_halloween2026_movimientos`

- `id`, `uid`, `cantidad`;
- `tipo` y `fuente`;
- referencia a post, tema, compra, reclamación o reparto;
- clave de idempotencia única;
- UID de staff cuando corresponda;
- nota y metadatos controlados;
- fecha de creación.

`mybb_op_halloween2026_bonos_diarios`

- una fila única por UID y fecha de Halloween;
- PID que obtuvo el bono y movimiento económico asociado;
- evita duplicados concurrentes y permite reasignar el bono al moderar.

### 11.2 Tienda

`mybb_op_halloween2026_tienda_items`

- catálogo, coste, recompensa estructurada, stock, límites, fechas y estado.

`mybb_op_halloween2026_compras`

- cabecera de compra, usuario, total, estado y clave de idempotencia.

`mybb_op_halloween2026_compra_lineas`

- artículo, cantidad, precio unitario y copia de la recompensa entregada.

La copia histórica evita que editar un artículo cambie recibos anteriores.

### 11.3 Índices mínimos

- movimientos: `(uid, created_at)`, `(fuente, source_id)` y clave de
  idempotencia única;
- saldos: `(ganado_total, uid)` para ranking;
- compras: `(uid, created_at)`;
- líneas: `(purchase_id)` y `(item_id)`.

Estas tablas pertenecen exclusivamente a Halloween 2026. Un futuro evento de
Navidad tendrá su propio prefijo y migración. Solo se extraerán helpers
compartidos cuando exista un segundo evento real y se conozca qué reglas son
verdaderamente comunes.

## 12. Seguridad y consistencia

- Todas las acciones POST requieren token CSRF de MyBB.
- IDs, cantidades y fechas se validan en servidor.
- Consultas con valores escapados y tipos numéricos forzados.
- No se aceptan precios, saldos ni recompensas calculados por el cliente.
- Compras y concesiones sensibles usan transacciones y bloqueo de filas.
- Las claves de idempotencia tienen restricciones únicas en base de datos.
- Los permisos de staff se validan en cada endpoint, no solo ocultando
  botones.
- La entrega de recompensas reutilizará helpers auditados del proyecto; no se
  crearán actualizaciones directas distintas para cada pantalla.
- Los movimientos económicos y las compras nunca se borran físicamente.
- Ningún flujo de concesión, compra, regalo, ajuste o reversión escribe datos
  de Calabazas en `mybb_op_fichas`.
- Errores internos se registran sin mostrar SQL ni datos sensibles al usuario.

## 13. Rendimiento

- La carga inicial consulta únicamente la vista seleccionada.
- El saldo se lee de una fila materializada, no sumando el historial completo.
- Rankings y contadores globales usan caché breve e invalidación al registrar
  movimientos importantes.
- No se analiza texto de logs para calcular límites o compras.
- No se consulta la tabla completa de posts para pintar estadísticas.
- Catálogo y banners pueden cachearse durante todo el evento.
- Historiales, compras y rankings extensos usan paginación.
- El plugin de posts realiza pocas consultas indexadas y no recalcula el
  ranking durante la publicación.

## 14. Archivos previstos

- `op/halloween2026.php`: controlador de la página restringida del evento.
- `op/staff/halloween2026.php`: administración y auditoría.
- `inc/plugins/opg_halloween2026.php`: integración con posts y temas.
- `inc/plugins/lib/halloween2026.php`: reglas y servicio económico.
- plantillas MyBB para evento, estadísticas, tienda e historial.
- CSS y JavaScript propios, versionados y sin grandes bloques inline.
- migración SQL reversible para tablas, índices y datos iniciales.

Los nombres pueden ajustarse al patrón definitivo del repositorio durante el
plan técnico.

## 15. Pruebas mínimas

### 15.1 Obtención

- Post válido dentro y fuera de fechas.
- Post válido dentro y fuera de una zona de rol.
- Tema común, aventura y tema `Spooky`.
- Primer post antes y después de las 06:00 de Madrid.
- Dos publicaciones simultáneas del mismo usuario.
- Edición, desaprobación, borrado y restauración.
- Reintento del mismo hook sin duplicar moneda.

### 15.2 Tienda

- Compra válida de cada tipo de recompensa.
- Saldo insuficiente.
- Varias unidades y compras repetidas del mismo artículo sin cupo acumulado.
- Precio modificado mientras el carrito está abierto.
- Doble clic y reenvío del formulario.
- Fallo durante la entrega: ni cobro ni entrega parcial.
- Compra exactamente en la apertura y cierre de tienda.

### 15.3 Estadísticas e integración

- El ranking usa total obtenido aunque el usuario haya gastado todo.
- Empates con orden estable.
- Movimientos revertidos reflejados correctamente.
- Evento archivado conserva ranking y recibos.
- Premios automáticos, presets y regalos suman al ranking; las compras no lo
  reducen y las reversiones sí corrigen el total neto.
- Cada preset solo puede concederse una vez al mismo personaje.

### 15.4 Interfaz

- Escritorio y móvil sin solapamientos ni desbordes.
- Navegación por teclado.
- Acceso denegado sin ficha activa y aprobada, además de estados sin saldo,
  sin artículos, tienda cerrada y error.
- Banners y decoración con espacio reservado y texto alternativo.

## 16. Criterios de aceptación

1. Un post válido concede exactamente 1 Calabaza y el primer post del día
   concede exactamente 1 adicional.
2. Un tema `Spooky` concede 3 Calabazas por tema, nunca 2 + 3, además de las
   recompensas de post que correspondan.
3. Ninguna acción repetida, doble clic o petición concurrente duplica una
   recompensa o una compra.
4. El usuario puede comprobar de dónde salió cada Calabaza de su saldo.
5. La clasificación principal ordena por total ganado y no cambia al comprar.
6. Cada preset de staff se puede conceder una sola vez por personaje.
7. La tienda permite compras repetidas sin stock global ni límite personal.
8. Una compra entrega todas sus recompensas y descuenta su coste, o no hace
   ninguna de las dos cosas.
9. Staff puede corregir un error sin borrar el movimiento original ni editar
   directamente el saldo.
10. Al finalizar el evento, la página continúa disponible como archivo
   histórico sin incorporar una colección de logros propia.
11. La página no depende de Imgur para mostrar sus imágenes en producción.
12. Todas las vistas rechazan a quien no tenga sesión y ficha activa y
    aprobada.
13. Todos los datos de Calabazas se conservan en las tablas del evento y una
    auditoría de consultas confirma que ningún flujo modifica
    `mybb_op_fichas` para almacenarlos.

## 17. Decisiones pendientes antes de implementar

Las decisiones funcionales necesarias para la primera versión están
confirmadas. Los secretos y la integración con Logros quedan expresamente fuera
del alcance actual.
