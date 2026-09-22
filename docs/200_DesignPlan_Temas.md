# Bitacora de rol: plan de diseno

> Depende de: [100_Requirements_Temas.md](100_Requirements_Temas.md)

## 1. Objetivo

Implementar un plugin de MyBB que siga, por personaje, los temas de la zona de
rol y calcule rondas completas. El sistema debe indicar si el personaje debe
responder, si espera a otros participantes o si el tema ya esta cerrado.

La primera entrega incluye:

- alta automatica al crear un tema o publicar una respuesta;
- alta manual mediante TID;
- calculo de rondas por participantes esperados;
- overrides `Me toca responder` y `No me toca responder`;
- gestion de participantes por tema;
- pagina privada `/op/bitacora.php`;
- resumen compacto en el header, como ultima fase.

El tracker usa el personaje activo de MyBB. Account Switcher ya cambia
`$mybb->user`; por tanto, la identidad del tracker sera siempre
`(int)$mybb->user['uid']`, sin sumar ni recorrer cuentas vinculadas.

## 2. Estado actual relevante

- La zona de rol se reconoce mediante `forums.parentlist LIKE '10,%'`.
- `mybb_posts.uid` coincide con `mybb_op_fichas.fid` para los personajes.
- `mybb_posts` ya dispone de indices por `(tid, uid)`, `uid`, `visible` y
  `(tid, dateline)`.
- `mybb_threads` expone `fid`, `subject`, `lastpost`, `lastposteruid`, `closed`
  y `visible`.
- `mybb_op_thread_personaje` es un snapshot de estadisticas para otros
  sistemas. No representa seguimiento ni rondas y no se reutilizara.
- El repositorio dispone de Alpine.js 3.17.3 y HTMX 2.0.10 en
  `/jscripts/vendor/`.
- Las plantillas del tema OPG se sincronizan desde
  `templates/One_Piece_Gaiden_Templates/` y las hojas de estilo desde su
  subdirectorio `stylesheets/`.

## 3. Decisiones de arquitectura

### 3.1 Componentes

Se crearan estos componentes:

| Componente | Responsabilidad |
|---|---|
| `inc/plugins/op_bitacora.php` | Registro del plugin, instalacion, hooks y variable del header |
| `inc/plugins/op_bitacora/functions.php` | Acceso a datos, validaciones y calculo de estados |
| `op/bitacora.php` | Pagina, acciones POST y fragmentos HTMX |
| `op/temas.php` | Redireccion de compatibilidad hacia la nueva URL |
| Tres plantillas `op_bitacora*` | Pagina completa, fragmento HTMX y header |
| `op_bitacora.css` | Presentacion OPG responsive |

La logica de negocio debe permanecer en `functions.php`. La pagina y los hooks
solo validan la entrada y llaman al servicio. `op/bitacora.php` construye el HTML
repetido de tarjetas, participantes y resultados del typeahead, siguiendo el
patron existente en `op/aventuras_personaje.php`.

Solo se persisten tres plantillas MyBB:

- `op_bitacora`: documento completo;
- `op_bitacora_contenido`: region reemplazable mediante HTMX;
- `op_bitacora_header`: barra compacta global.

### 3.2 Fuente de verdad

MyBB conserva la fuente de verdad del contenido:

- `posts` determina quien publico durante una ronda;
- `threads.closed` determina si un tema esta cerrado;
- `threads.fid` y `forums.parentlist` determinan si sigue en la zona de rol;
- los permisos de MyBB determinan si el personaje puede verlo.

Las tablas nuevas solo almacenan la decision personal de seguir un tema, el
punto de inicio de la ronda, los overrides y los participantes esperados. No se
copiaran titulos, nombres, fechas ni estados de MyBB.

### 3.3 Sin copia historica completa de rondas

La primera version solo necesita conocer la ronda actual. Se guarda el PID que
la inicia y se consulta si cada participante esperado tiene al menos un post
visible posterior. No se crea una fila por ronda ni una copia de cada post.

Esto reduce escrituras, evita desincronizaciones y permite que eliminar o
desmoderar un post corrija el estado en la siguiente lectura.

Una ampliacion posterior puede guardar un historial ligero de acciones de la
Bitacora. Ese registro solo conserva eventos relevantes para explicar cambios
(inicio de ronda, ajuste manual y narrador), no una fila por cada post ni una
copia del contenido de MyBB.

## 4. Modelo de datos

Todas las tablas nuevas usaran el prefijo configurado por MyBB. Los nombres
mostrados omiten `mybb_` cuando se pasan a la API de `$db`.

### 4.1 `mybb_op_temas_seguidos`

Una fila representa que un personaje sigue un tema.

```sql
CREATE TABLE mybb_op_temas_seguidos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    personaje_uid INT UNSIGNED NOT NULL,
    tid INT UNSIGNED NOT NULL,
    ronda_inicio_pid INT UNSIGNED NOT NULL DEFAULT 0,
    override_estado VARCHAR(12) NOT NULL DEFAULT 'auto',
    narrador_uid INT UNSIGNED NOT NULL DEFAULT 0,
    creado_en INT UNSIGNED NOT NULL,
    actualizado_en INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY personaje_tema (personaje_uid, tid),
    KEY tema (tid),
    KEY personaje_actualizado (personaje_uid, actualizado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`override_estado` admite exclusivamente:

- `auto`: manda el calculo de la ronda;
- `me_toca`: fuerza `Debes responder` hasta que el personaje publique;
- `no_me_toca`: fuerza la espera hasta que se complete la ronda corregida o
  el personaje cambie el override.

`narrador_uid = 0` mantiene la ronda normal. Un UID positivo activa la ronda
dirigida para ese seguimiento; solo un post visible de ese narrador posterior
al inicio de ronda completa la condicion automatica.

No se crean claves foraneas contra tablas core porque en esta instalacion son
MyISAM. La integridad se valida en servidor.

### 4.2 `mybb_op_temas_participantes`

Contiene las personas que el personaje espera en ese tema.

```sql
CREATE TABLE mybb_op_temas_participantes (
    seguimiento_id INT UNSIGNED NOT NULL,
    participante_uid INT UNSIGNED NOT NULL,
    origen VARCHAR(10) NOT NULL DEFAULT 'auto',
    creado_en INT UNSIGNED NOT NULL,
    PRIMARY KEY (seguimiento_id, participante_uid),
    KEY participante (participante_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`origen` puede ser `auto` o `manual` y sirve para explicar la configuracion;
no cambia el calculo. Al dejar de esperar a alguien se elimina su fila. Si esa
persona vuelve a publicar, el hook la insertara otra vez con origen `auto`.

Al retirar un seguimiento se eliminan primero sus participantes y despues la
fila principal, dentro de una transaccion de las tablas InnoDB nuevas.

## 5. Maquina de estados

### 5.1 Precedencia

El estado visible se resuelve en este orden:

1. tema inexistente, no visible, sin permiso o fuera de la zona de rol:
   `oculto`;
2. `threads.closed` activo: `cerrado`;
3. override `me_toca`: `debes_responder_manual`;
4. override `no_me_toca` y ronda incompleta: `esperando_manual`;
5. ronda completa: `debes_responder`;
6. cualquier otro caso: `esperando`.

Los temas ocultos conservan internamente el seguimiento. Si un tema vuelve a
la zona de rol o recupera visibilidad, reaparece sin reconstruir su
configuracion. Los temas cerrados se muestran hasta que el personaje pulse
`Dejar de seguir`.

### 5.2 Ronda automatica

Una publicacion visible del personaje seguido abre una ronda:

```text
ronda_inicio_pid = pid_del_personaje
override_estado = auto
```

Para cada participante esperado, se comprueba si existe:

```sql
posts.tid = seguimiento.tid
AND posts.uid = participante_uid
AND posts.visible = 1
AND posts.pid > ronda_inicio_pid
```

La ronda esta completa cuando existe al menos un participante esperado y todos
tienen un post que cumple esas condiciones. Una lista vacia permanece en
`Esperando respuesta`; la interfaz indicara que todavia no hay participantes y
permitira agregarlos. Esto evita considerar completa una ronda vacia.

El PID, no la fecha, delimita la ronda. Es monotono, evita empates de timestamp
y aprovecha la clave primaria de `posts`.

### 5.3 Alta de un tema ya empezado

Al agregar manualmente un tema:

1. se incorporan como participantes esperados todos los autores con ficha que
   tengan posts visibles, excepto el personaje actual;
2. si el personaje ya publico, su ultimo PID visible pasa a ser
   `ronda_inicio_pid` y se calcula la ronda desde ahi;
3. si nunca publico, `ronda_inicio_pid` queda en `0` y el formulario exige un
   estado inicial;
4. para el caso habitual de un tema creado para el personaje, se guarda
   `override_estado = me_toca`.

### 5.4 Override `Me toca responder`

La accion cambia `override_estado` a `me_toca`. El estado permanece hasta que:

- el personaje publica, lo que abre una ronda nueva y devuelve el override a
  `auto`; o
- el personaje selecciona explicitamente `No me toca responder`.

### 5.5 Override `No me toca responder`

Para que el override no expire inmediatamente cuando la ronda anterior ya
parecia completa, la accion crea un punto de espera corregido:

```text
ronda_inicio_pid = ultimo_pid_visible_actual
override_estado = no_me_toca
```

Desde ese punto, los participantes esperados deben publicar de nuevo. Cuando
todos lo hagan, el estado efectivo pasa a `Debes responder` aunque el valor
guardado siga siendo `no_me_toca`; en la siguiente escritura puede
normalizarse a `auto`.

El personaje tambien puede terminar la espera en cualquier momento usando
`Me toca responder`.

### 5.6 Cambios en participantes

- Agregar un participante recalcula inmediatamente la ronda.
- Retirar un participante puede completar la ronda si era el unico pendiente.
- Un autor nuevo con ficha se agrega automaticamente a todos los seguimientos
  activos de ese TID, excepto al seguimiento del propio autor.
- El post que presenta al participante nuevo cuenta como su respuesta en la
  ronda actual.
- Si un participante retirado vuelve a publicar, se incorpora otra vez.

### 5.7 Ronda dirigida por narrador

El personaje selecciona un `narrador_uid` mediante el mismo typeahead de
fichas. El narrador se incorpora a participantes si todavia no estaba. Mientras
este configurado, `op_temas_resolver_estado()` sustituye la condicion de
"todos respondieron" por `narrador_respondio`.

Para presentar el progreso se obtiene el mayor PID visible del narrador
posterior a `ronda_inicio_pid`. Ese PID pasa a ser la frontera de los demas
participantes: solo se consideran respondidos cuando su mayor PID es posterior
al PID del narrador. El narrador aparece respondido por haber iniciado la
ronda.

Retirar al participante que actua como narrador tambien limpia
`narrador_uid`. Quitar el modo narrado no elimina al participante: simplemente
restaura el calculo normal de todos los esperados.

## 6. Hooks del plugin

### 6.1 Publicacion de respuesta

Hook: `datahandler_post_insert_post_end`.

Se procesa solo cuando el post y el tema son visibles, el UID es positivo, el
autor tiene fila en `op_fichas` y el foro cumple `parentlist LIKE '10,%'`.

Flujo:

1. insertar o reactivar el seguimiento `(autor_uid, tid)`;
2. fijar `ronda_inicio_pid` al PID nuevo y `override_estado = auto`;
3. si era un alta, poblar participantes con los autores historicos visibles;
4. agregar al autor como participante esperado de los seguimientos de los
   demas personajes en ese TID;
5. actualizar `actualizado_en`.

La clave unica `(personaje_uid, tid)` hace la operacion idempotente y evita
duplicados ante reintentos.

### 6.2 Creacion de tema

Hook: `datahandler_post_insert_thread_end`.

Usa el mismo servicio que una respuesta. El nuevo seguimiento empezara sin
participantes y permanecera en espera hasta que otro personaje con ficha
publique o el autor configure uno manualmente.

### 6.3 Moderacion, cierre y movimiento

No hace falta persistir cierres, movimientos, eliminaciones ni
desmoderaciones en las tablas del tracker:

- cierre y reapertura se leen desde `threads.closed`;
- movimiento se lee desde `threads.fid` y `forums.parentlist`;
- eliminacion o moderacion de posts se refleja al volver a calcular los posts
  visibles posteriores a `ronda_inicio_pid`;
- eliminacion u ocultacion de temas los excluye mediante el join con
  `threads.visible = 1` y los permisos actuales.

Esto evita depender de todos los caminos de moderacion de MyBB. Los hooks
`class_moderation_approve_posts` y `class_moderation_approve_threads` son la
excepcion: al aprobar contenido que nacio invisible deben procesarse sus posts
para crear el seguimiento automatico que no pudo crearse durante la insercion.
Los demas hooks `class_moderation_*` solo serian necesarios si posteriormente
se introduce un cache persistente de estados.

### 6.4 Header

Hook recomendado: `global_intermediate`, despues de que MyBB haya cargado al
usuario y los permisos.

El hook define `$op_temas_header` para la plantilla `header`. Debe salir pronto
si el usuario es invitado, no tiene ficha o las tablas no existen.

La plantilla fuente `templates/One_Piece_Gaiden_Templates/header.html`
incluira `{$op_temas_header}` cerca de `#peticiones_staff`. La activacion del
plugin aplicara la misma insercion mediante `find_replace_templatesets`; la
desactivacion la retirara sin borrar datos.

## 7. Consultas y rendimiento

### 7.1 Lectura en bloque

La pagina y el header reutilizaran una consulta agregada para el personaje
activo:

- seguimiento + thread + forum;
- participantes esperados;
- autores con un post visible posterior al inicio de ronda;
- ficha y usuario del ultimo autor para presentacion.

Se usaran `COUNT(DISTINCT participante_uid)` para esperados y respondidos. No
se ejecutara una consulta por tarjeta ni por participante.

Despues de la consulta, los FID se agrupan y se validan con
`forum_permissions($fid)`. Un tema sin `canview` o `canviewthreads` se descarta
antes de construir HTML o conteos.

### 7.2 Conteos del header

El header pide el mismo resumen, pero no carga las tarjetas. Devuelve solo:

- total `Debes responder`;
- total `Esperando respuesta`.

Los cerrados cuentan dentro de `Esperando respuesta`; los ocultos no cuentan.
La primera version no necesita cache: cada request realiza una consulta
acotada para un unico personaje. Se medira con datos reales antes de introducir
invalidacion o tareas programadas.

### 7.3 Indices

Los indices de las tablas nuevas cubren identidad, listado por personaje y
actualizacion por TID. El indice existente `posts(tid, uid)` reduce cada
comprobacion a los posts de un participante en un tema.

No se modifica inicialmente la tabla core `posts`. Si `EXPLAIN` muestra un
coste relevante con datos de produccion, la optimizacion candidata es un
indice compuesto `(tid, uid, visible, pid)`.

## 8. Pagina `/op/bitacora.php`

### 8.1 Acceso

- El modo propio requiere sesion iniciada y una ficha con
  `op_fichas.fid = $mybb->user['uid']`.
- `modo_vista=<FID>` permite a cualquier visitante abrir el tracker de otra
  ficha en modo de solo lectura.
- Usa el header, footer, navegacion y permisos normales de MyBB.
- El FID recibido solo decide el personaje observado. Nunca autoriza acciones
  ni sustituye al UID de sesion como propietario de una mutacion.
- Los temas se filtran con los permisos de foro del visitante actual.

### 8.2 Composicion

La pantalla se organiza en este orden:

1. encabezado compacto `Bitácora de rol`;
2. selector colapsable para buscar otro personaje por nombre o FID;
3. guia colapsable y autosuficiente sobre alta automatica, estados, rondas,
   participantes, narradores, correcciones y casos especiales;
4. aviso de Modo vista, cuando corresponda;
5. resumen con los dos conteos;
6. formulario `Agregar tema` por TID;
7. pestanas `Tu turno` y `Al dia`;
8. lista de tarjetas compactas del estado seleccionado;
9. panel de configuracion de ronda del tema elegido.

Cada tarjeta incluye:

- titulo enlazado al primer post no leido o ultimo post;
- foro/localizacion y TID;
- ultimo autor y fecha;
- progreso de ronda, por ejemplo `2 de 3 respondieron`;
- participantes pendientes mediante nombre y avatar pequeno;
- origen del estado: automatico o manual;
- acciones con iconos y tooltip: configurar, cambiar turno y dejar de seguir.

Los cerrados usan una banda de estado inequivoca y conservan la accion
`Dejar de seguir`. No presentan controles de turno.

### 8.3 Configuracion de ronda

El panel muestra dos grupos:

- `Ya respondieron`;
- `Pendientes`.

El selector de narrador permanece oculto dentro de un control colapsable
`Modo de ronda`. Al abrirlo recibe foco y muestra sus coincidencias en un
dropdown flotante, sin aumentar la altura del panel de configuracion.
`Agregar participante` sigue el mismo patron: permanece cerrado inicialmente,
recibe foco al abrirse y comparte el motor cancelable de busqueda y el dropdown
absoluto del selector de narrador.

Cada participante tiene enlace a `/op/personaje.php?uid={uid}` y una accion de
retirada. Un typeahead permite agregar personajes con ficha por nombre o FID;
solo consulta al servidor cuando el termino tiene al menos tres caracteres.
Cada instancia usa un destino de resultados unico, envia exclusivamente el
termino de busqueda y sustituye las consultas anteriores para impedir cruces o
respuestas obsoletas.

Las acciones principales son:

- `Me toca responder`;
- `No me toca responder`;
- `Agregar participante`;
- `Retirar participante`;
- `Dejar de seguir` con confirmacion.

### 8.4 HTMX y Alpine.js

La pagina carga las copias locales:

```text
/jscripts/vendor/htmx-2.0.10.min.js
/jscripts/vendor/alpinejs-3.17.3.min.js
```

HTMX actualiza la lista, los conteos y el panel despues de cada accion. Alpine
controla pestanas, dialogos y estados de interfaz que no necesitan servidor.

Todas las acciones funcionan tambien como formularios POST tradicionales con
patron Post/Redirect/Get. HTMX es una mejora progresiva, no una dependencia de
seguridad ni de integridad.

## 9. Contrato HTTP

La misma pagina expone acciones pequenas; no se crea una API publica.

| Metodo | Accion | Resultado |
|---|---|---|
| `GET` | pagina | Vista completa del personaje activo |
| `GET` | `modo_vista=<FID>` | Vista publica y de solo lectura de otra ficha |
| `GET` | `buscar_modo_vista&q=` | Resultados publicos del selector de personaje |
| `GET` | `buscar_personajes&q=` | Fragmento HTML del typeahead |
| `GET` | `fragmento=tracker` | Conteos y listas para HTMX |
| `POST` | `agregar_tema` | Valida TID, estado inicial y crea seguimiento |
| `POST` | `dejar_seguir` | Elimina seguimiento y participantes |
| `POST` | `me_toca` | Aplica override manual |
| `POST` | `no_me_toca` | Abre punto de espera corregido |
| `POST` | `agregar_participante` | Agrega una ficha al seguimiento propio |
| `POST` | `retirar_participante` | Retira una ficha del seguimiento propio |

Cada POST exige `verify_post_check($mybb->get_input('my_post_key'))`. Los IDs se
convierten a enteros y cada mutacion vuelve a cargar el seguimiento con
`personaje_uid = $mybb->user['uid']`.

Las respuestas HTMX devuelven HTML renderizado por plantillas. No se duplica
una segunda capa JSON para el mismo caso de uso.

## 10. Validaciones

### 10.1 Agregar tema

El servidor valida, en este orden:

1. sesion y ficha activa;
2. post key;
3. TID entero positivo;
4. thread existente y `visible = 1`;
5. foro existente y `parentlist LIKE '10,%'`;
6. permisos `canview` y `canviewthreads`;
7. ausencia de seguimiento duplicado;
8. estado inicial permitido.

Un error de existencia, visibilidad o permisos usa el mismo mensaje generico:
`No se pudo agregar ese tema`. Asi no se revela un tema privado.

### 10.2 Participantes

- El participante debe tener ficha.
- No puede ser el propio personaje.
- No puede duplicarse dentro del seguimiento.
- Agregarlo no le concede acceso al tema ni modifica su tracker.
- Retirarlo solo afecta las condiciones del personaje activo.

## 11. Instalacion y ciclo de vida

`op_bitacora_install()`:

- crea las dos tablas con `$db->table_prefix` y el collation disponible;
- registra las plantillas maestras `op_bitacora*`;
- registra `op_bitacora.css` para el tema OPG;
- no importa posts historicos.

`op_bitacora_activate()`:

- inserta `{$op_temas_header}` una sola vez en la plantilla del header;
- habilita los hooks sin alterar datos existentes.

`op_bitacora_deactivate()`:

- retira la variable del header;
- conserva tablas y seguimientos.

`op_bitacora_uninstall()`:

- elimina tablas, plantillas y stylesheet mediante el flujo de desinstalacion
  confirmado del Admin CP.

Los archivos fuente de las plantillas y CSS se versionan tambien en las rutas
sincronizadas del tema para evitar que un ciclo archivo-DB revierta cambios.

## 12. Seguridad

- Identidad obtenida exclusivamente de `$mybb->user['uid']`.
- CSRF protegido mediante `post_key` en todas las mutaciones.
- Comprobacion de propiedad en cada seguimiento.
- Permisos MyBB comprobados antes de mostrar o agregar temas.
- Salida escapada con `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Consultas construidas con enteros casteados y `$db->escape_string()` para
  texto de busqueda.
- Typeahead limitado, sin busqueda antes de tres caracteres y con maximo de
  resultados.
- Sin informacion de temas ocultos en mensajes, conteos o fragmentos HTMX.

## 13. Casos limite

- **Tema sin otros participantes:** queda esperando y muestra configuracion
  vacia; no se completa por vacio.
- **Primer turno sin post propio:** requiere estado inicial manual.
- **Dos posts seguidos del personaje:** el segundo PID reinicia la ronda.
- **Participante publica varias veces:** cuenta una sola vez.
- **Participante nuevo:** se agrega y su primer post cuenta en esa ronda.
- **Participante retirado que vuelve:** se agrega automaticamente otra vez.
- **Post relevante desmoderado o eliminado:** deja de contar en la siguiente
  lectura.
- **Tema cerrado:** aparece en `Esperando` con una etiqueta propia hasta su
  retirada manual.
- **Tema reabierto:** vuelve al estado calculado de su ronda conservada.
- **Tema movido fuera de rol:** queda oculto y sin contar; reaparece si vuelve.
- **Tema eliminado:** la fila puede quedar huerfana e invisible; una limpieza
  administrativa futura puede purgarla, pero no es necesaria para el MVP.
- **Cambio de personaje:** la siguiente request usa otro UID y otros conteos.
- **Publicaciones concurrentes:** las claves unicas y operaciones idempotentes
  impiden duplicar seguimientos o participantes.

## 14. Estrategia de pruebas

### 14.1 Instalacion

- instalar, activar, desactivar, reactivar y desinstalar;
- verificar tablas, indices, templates y stylesheet;
- comprobar que activar dos veces no duplica el bloque del header.

### 14.2 Seguimiento

- crear tema en rol y fuera de rol;
- responder por primera vez, dejar de seguir y volver a responder;
- agregar TID con y sin post previo;
- rechazar TID privado, inexistente, cerrado fuera de permisos o fuera de rol;
- cambiar de personaje con Account Switcher.

### 14.3 Rondas

- rondas de dos, tres y mas participantes;
- participante que publica cero, una o varias veces;
- participante nuevo y participante retirado que regresa;
- agregar y retirar participante en mitad de ronda;
- overrides en ronda incompleta y completa;
- dos publicaciones consecutivas del personaje;
- eliminar, aprobar, desaprobar y restaurar posts relevantes.

### 14.4 Estados del tema

- cerrar, reabrir, mover fuera y devolver a la zona de rol;
- ocultar, moderar y eliminar un tema;
- confirmar que los cerrados entran en `Esperando` y conservan su etiqueta;
- confirmar que lo inaccesible no filtra titulo ni existencia.

### 14.5 Interfaz

- escritorio y movil;
- teclado, foco y dialogos;
- HTMX habilitado y JavaScript deshabilitado;
- estados vacios y nombres largos;
- coincidencia exacta entre conteos de pagina y header.

### 14.6 Rendimiento

- ejecutar `EXPLAIN` sobre listado y resumen con volumen representativo;
- confirmar una cantidad acotada de consultas por pagina;
- comprobar que no existe una consulta por tarjeta o participante;
- medir el coste del header antes de considerar cache o un indice core nuevo.

## 15. Fases de implementacion

### Fase 1: Persistencia y motor

- plugin instalable;
- tablas y servicio de estados;
- hooks de publicacion;
- pruebas de rondas mediante fixtures controlados.

### Fase 2: Pagina funcional

- `/op/bitacora.php` renderizada en servidor;
- alta y retirada;
- overrides;
- gestion de participantes;
- tres listas y permisos.

### Fase 3: Experiencia interactiva

- fragmentos HTMX;
- typeahead;
- Alpine para pestanas y confirmaciones;
- CSS OPG responsive conforme a `style.md`.

### Fase 4: Header

- conteos globales del personaje activo;
- estado positivo cuando ambos son cero;
- verificacion de coste en paginas normales del foro.

## 16. Fuera de alcance

- importar automaticamente temas anteriores al lanzamiento;
- sumar trackers de personajes vinculados;
- sustituir suscripciones o alertas nativas de MyBB;
- imponer un orden entre participantes dentro de una ronda;
- estadisticas completas de rondas terminadas o copia historica de cada post;
- notificaciones push, correo o Discord;
- administracion de trackers ajenos por Staff;
- limpieza automatica de seguimientos huerfanos en la primera version.

## 17. Ampliacion: tiempo de estado y orden

`op_temas_seguidos` incorpora:

```sql
estado_grupo VARCHAR(12) NOT NULL DEFAULT '',
estado_desde INT UNSIGNED NOT NULL DEFAULT 0
```

`estado_grupo` conserva unicamente el grupo visible (`turno` o `al_dia`), no
el detalle automatico/manual. `estado_desde` se modifica cuando cambia ese
grupo. Los seguimientos anteriores se inicializan usando la fecha reconstruida
de la ronda y, cuando no existe, `actualizado_en`.

Las tarjetas muestran `threads.replies + 1`, la fecha absoluta y relativa del
ultimo post y la antiguedad del grupo actual. Incluyen `data-lastpost` para que
el control Mas recientes/Mas antiguos reordene las dos listas en el navegador,
sin consultas ni recargas. La eleccion se conserva en `localStorage` y se
reaplica despues de reemplazos HTMX.

## 18. Diseno de mejoras pendientes

### 18.1 Checklist y orden recomendado

- [x] Prioridad visual basada en la antiguedad del estado.
- [x] Accion principal `Responder` o `Ver ultimo post`.
- [x] Modo compacto persistente.
- [x] Estados vacios especificos por pestana.
- [x] Ultima actividad en el header.
- [x] Historial corto de eventos por tema.

Las cinco primeras mejoras pueden construirse sobre el modelo actual. El
historial debe implementarse al final porque introduce persistencia, hooks y
una migracion nueva.

### 18.2 Antiguedad y prioridad visual

La clasificacion usa la diferencia entre `TIME_NOW` y `estado_desde`. Los
umbrales iniciales recomendados son constantes configurables:

```php
define('OP_BITACORA_ATENCION_DIAS', 3);
define('OP_BITACORA_ANTIGUO_DIAS', 7);
```

- menos de 3 dias: presentacion normal;
- entre 3 y 6 dias: etiqueta textual `Pendiente desde hace X` y acento suave;
- 7 dias o mas: etiqueta `Pendiente antiguo` con mayor contraste.

La prioridad fuerte solo se aplica a `Tu turno`. En `Al dia` se conserva la
fecha, pero no se responsabiliza visualmente al propietario por la demora de
otra persona. Color, texto e icono deben comunicar juntos el nivel.

### 18.3 Accion principal y modo compacto

La tarjeta incorpora un enlace propio, sin heredar el componente global
`.btn-op`. `Responder` abre `/newreply.php?tid={tid}`; `Ver ultimo post` abre
`/showthread.php?tid={tid}&action=lastpost`. Ambos usan `target="_blank"` y
`rel="noopener"`, colores de enlace explicitos y dejan que MyBB valide permisos
y cierre en el destino.

El selector de densidad usa Alpine y `localStorage` con la clave
`opBitacoraVista`. La opcion `compacta` reduce espacios y oculta progreso,
participantes, historial y metadatos secundarios. Conserva titulo, antiguedad,
estado, cantidad de posts, actividad relativa, accion principal y acceso a la
configuracion. HTMX debe restaurar la clase de densidad despues de cada
intercambio.

La Bitacora muestra `op_fichas.nombre` sin concatenar `apodo`; `username` solo
se utiliza como fallback cuando falta el nombre.

### 18.4 Estados vacios

Los vacios se renderizan en servidor para funcionar sin JavaScript:

- `Tu turno` vacio: mensaje positivo solicitado y acceso a `Al dia`;
- `Al dia` vacio con pendientes: mensaje que indica que toda la actividad esta
  en `Tu turno`;
- Bitacora completamente vacia: invitacion a agregar un TID y explicacion breve
  de la incorporacion automatica al publicar.

No se usa el mensaje de felicitacion cuando no existe ningun seguimiento, ya
que podria interpretarse como un calculo realizado sobre actividad inexistente.

### 18.5 Ultima actividad del header

`op_bitacora_resumen()` debe devolver, ademas de los conteos, el maximo de:

```text
threads.lastpost
op_temas_seguidos.actualizado_en
op_temas_seguidos.estado_desde
```

solo para seguimientos visibles del personaje activo. El header lo presenta
como `Actualizado hace X`. No se usa `TIME_NOW` como fecha de actividad ni se
realiza una consulta por tema. El texto se recalcula al renderizar una pagina;
no necesita un temporizador en vivo.

### 18.6 Historial corto

Se anade una tabla de eventos ligeros:

```sql
CREATE TABLE mybb_op_bitacora_eventos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  seguimiento_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  actor_uid INT UNSIGNED NOT NULL DEFAULT 0,
  relacionado_uid INT UNSIGNED NOT NULL DEFAULT 0,
  pid INT UNSIGNED NULL,
  datos TEXT NULL,
  creado_en INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY evento_post (seguimiento_id, tipo, pid),
  KEY seguimiento_fecha (seguimiento_id, creado_en)
);
```

`pid` es `NULL` para acciones manuales, permitiendo multiples eventos del mismo
tipo. Para eventos originados por un post, la clave unica evita duplicados si
un hook se procesa mas de una vez. `datos` guarda JSON pequeno solo cuando sea
necesario conservar valores anterior y nuevo.

Tipos iniciales: `narrador_asignado`, `narrador_cambiado`,
`narrador_retirado`, `manual_turno`, `manual_al_dia`,
`ronda_normal_iniciada` y `ronda_narrada_iniciada`.

La consulta de listado obtiene como maximo los cinco eventos mas recientes por
seguimiento en una sola consulta para todos los IDs visibles. Para mantener
compatibilidad con MySQL sin funciones de ventana, puede usar un self-join que
cuente los eventos posteriores y conserve los que tengan menos de cinco, con
`(seguimiento_id, creado_en)` como indice de apoyo. Nunca debe ejecutarse una
consulta por tarjeta ni cargar un historial sin limite para recortarlo en PHP.
Los eventos empiezan a registrarse desde el despliegue; no hay backfill
especulativo. En Modo vista son de solo lectura y siguen los permisos del tema
asociado. Al dejar de seguir un tema, sus eventos se eliminan explicitamente.

### 18.7 Pruebas de diseno

- [ ] Los nombres largos no rompen el titulo ni la accion principal.
- [ ] Los umbrales cambian exactamente al comenzar los dias 3 y 7.
- [ ] Vista detallada y compacta funcionan a 390, 1024 y 1440 px.
- [ ] Los tres vacios posibles muestran mensajes diferentes.
- [ ] La ultima actividad coincide entre pagina y header.
- [ ] Reprocesar un PID no duplica el evento automatico.
- [ ] Cargar 20 temas no genera consultas N+1 para el historial.
