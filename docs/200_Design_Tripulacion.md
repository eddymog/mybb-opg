# Sistema de Tripulaciones: diseño

> Depende de: [100_Requirements_Tripulacion.md](100_Requirements_Tripulacion.md)

## Objetivo

Reemplazar el proceso manual actual (crear una tripulación era hacer un post en la zona de posts) por cuatro páginas nuevas dentro de `/op/`: un directorio público, un formulario de solicitud, una página de gestión interna por tripulación, y una herramienta en la consola de mods. Además, dos páginas existentes reciben un cambio chico para que la tripulación no quede aislada del resto del foro: `op/peticiones.php` (para que la solicitud pendiente aparezca donde ya se ven las demás peticiones del usuario) y `op/ficha.php` (para que la ficha del personaje muestre su tripulación).

## Estado actual

- No existe ninguna tabla `tripulacion` ni `banda` en `rovddqmy_op.sql` — se parte de cero para el modelo propio de tripulaciones.
- `mybb_op_fichas` ya tiene `fid` (= uid), `faccion` (varchar, coincide con las variables de color de `templates/op_global.css`: `--pirata-group-color`, `--marine-group-color`, `--cipher-pol-group-color`, `--cazarrecompensas-group-color`, `--revolucionario-group-color`, `--civil-group-color`, `--narradores-group-color`, `--npcs-group-color`) y **dos pares de columnas de reputación**: `reputacion`/`reputacion_positiva`/`reputacion_negativa` y `reputacion2`/`reputacion_positiva2`/`reputacion_negativa2`. La tripulación suma la columna `reputacion` (la principal/por defecto) — `reputacion2` no se usa en este sistema.
- `mybb_op_barcos` ya existe (`barco_id`, `nombre_barco`, `vitalidad`, `espacios`, `velocidad`, `tiempo_viaje`, `resistencia`, `espacios_mejora`, `ruputura`), pero **no tiene ninguna columna de propiedad** — nada dice hoy qué barco es de qué usuario o tripulación. Este diseño le añade una columna nueva en vez de crear una tabla aparte (ver Modelo de datos).
- `mybb_op_cofres` **no se usa para este sistema**: es el catálogo de tipos de cofre que usa el sistema de gacha (`/opg/`), no un almacén genérico de items. Para el almacenamiento de items de la tripulación se crea una tabla propia — y se le llama **baúl**, no "cofre", justamente para no pisar el término que ya significa otra cosa en el foro.
- `mybb_op_peticiones` **no se usa para este sistema** — la solicitud de creación de tripulación tiene su propia tabla (ver Modelo de datos), separada del sistema general de peticiones administrativas.
- `op/peticiones.php` es la página real de "mis peticiones": inserta en `mybb_op_peticiones` y lista, con `SELECT * FROM mybb_op_peticiones WHERE uid='$uid' AND resuelto=0`, las peticiones pendientes del usuario actual, con una categoría fija por tipo (`ficha`, `tema`, `combate`, `tecnica`, `programacion`, `reset`). No filtra por rol ni paginación — es una lista simple de texto armada a mano (`$peticiones_txt`). **Nota de seguridad ajena a este diseño:** este archivo usa `addslashes()` e interpolación directa en el `INSERT`, no `$db->escape_string()` — no se toca acá, pero no replicar ese patrón en el código nuevo.
- `op/staff/consola_mod.php:21` usa `if (!is_mod($uid) && !is_staff($uid))` como entrada a la consola, y cada herramienta interna puede exigir un nivel superior (`is_admin($uid)`) si hace falta — así se gateó `op/staff/banners.php` en [banners-staff-design.md](banners-staff-design.md). El botón de cada herramienta vive en `templates/One_Piece_Gaiden_Templates/staff_consola_mod.html`, agrupado por categoría.
- `op/functions/op_functions.php` expone las funciones que este diseño usa: `does_ficha_exist($uid)`, `is_staff($uid)`, `is_mod($uid)`, `is_narra($uid)`, `is_user($uid)`, `log_audit($uid, $username, $categoria, $log)`, `log_audit_currency(...)`, `log_security_event($tipo, $endpoint, $extra_info)`.
- `templates/op_global.css` ya tiene el sistema visual que usan otras páginas de `/op/`: marco anidado `.mainBackground` → `.secondBackground` → `.thirdBackground`, barras naranjas `.barra-op`/`.barra-op-abajo` con texto `.barra-texto-op`, cuerpos crema `.barra-espacio-op`, botones `.button-op-custom`/`.button-action-op-custom`, y tarjetas de item (`.item-outer`, `.item-nombre`, `.item-price`, `.item-image`) que ya resuelven visualmente "una cuadrícula de cosas con nombre, imagen y datos" — el mismo problema que el listado de miembros/barcos/baúl.

## Modelo de datos

Tablas nuevas `mybb_op_*` (sin FK reales, se resuelven por código, como el resto del proyecto), más un cambio a una tabla existente.

### `mybb_op_tripulaciones`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | int, PK, auto_increment | |
| `nombre` | varchar(80) | Único. |
| `faccion` | varchar(20) | Elegida por el líder al crear la tripulación, de la lista de facciones existentes en el foro. No se copia automáticamente de su ficha. |
| `logo` | varchar(255) | Ruta de imagen. Vacío hasta que se suba una. |
| `lider_fid` | int | El fundador original. La autoridad real la da el rango en `mybb_op_tripulaciones_miembros`, no esta columna — se guarda solo como dato histórico de quién la fundó. |
| `mensaje_publico` | varchar(255) | Texto libre tipo "banda cerrada", "buscamos miembros". |
| `buscando_miembros` | tinyint(1) | Flag estructurado, independiente del texto libre de arriba, para que el directorio lo pueda filtrar. |
| `estado` | tinyint | 0=Activa, 1=Inactiva, 2=Disuelta. Default 0. Solo lo cambia staff. |
| `ideales` | text | Opcional. |
| `jerarquia_texto` | text | Decorativo, ver sección 3. |
| `otros` | text | |
| `link_formacion` | varchar(255) | Tema donde se formó. |
| `berries`, `nika`, `kuro` | int, default 0 | Balance del baúl. Solo estos tres — `puntos_oficio`/`puntos_estadistica` de `mybb_op_fichas` son personales, no tiene sentido un pozo compartido de esos. |
| `created_at` | int | Unix timestamp, mismo patrón que `timestamp_end` en otras tablas. |

### `mybb_op_tripulaciones_miembros`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | int, PK, auto_increment | |
| `tripulacion_id` | int | |
| `fid` | int | Personaje (= uid). |
| `rango` | tinyint | 0=Miembro, 1=Vicecapitán, 2=Capitán. No es único por tripulación — puede haber más de una fila con rango 2 o 1 a la vez. |
| `rol_decorativo` | varchar(100) | Texto libre, sin efecto en permisos. |
| `fecha_ingreso` | int | |

### `mybb_op_tripulaciones_baul`

Almacenamiento de items de la tripulación. Tabla propia — **no** usa `mybb_op_cofres`, que es del sistema de gacha.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | int, PK, auto_increment | |
| `tripulacion_id` | int | |
| `objeto_id` | varchar(255) | |
| `nombre` | varchar(255) | |
| `tipo` | varchar(255) | |
| `peso` | int | |
| `cantidad` | int, default 1 | |

Visible solo para los miembros de la tripulación (ver Decisiones).

### Cambio a `mybb_op_barcos`

Se añade una columna `tripulacion_id` (int, NULL por defecto = sin dueño) a la tabla existente, en vez de crear una tabla de propiedad aparte. La lista de barcos de una tripulación es `SELECT * FROM mybb_op_barcos WHERE tripulacion_id = {id}`.

### `mybb_op_tripulaciones_solicitudes`

Tabla propia, independiente de `mybb_op_peticiones`. Sigue el patrón de `mybb_op_peticionAventuras` (campo `estado`, no un simple `resuelto` binario) en vez del de `mybb_op_peticiones`.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | int, PK, auto_increment | |
| `uid` | int | Quién envía y edita el formulario — el creador. |
| `nombre_propuesto` | varchar(80) | |
| `faccion` | varchar(20) | De la lista de facciones del foro. |
| `bandera` | varchar(255) | Opcional. |
| `link` | varchar(255) | |
| `usuarios_iniciales_json` | mediumtext | Lista de `fid`, mismo patrón que `jugadores_json` en `peticionAventuras`. |
| `lider_fid` | int | |
| `jerarquia_texto` | text | |
| `ideales` | text | |
| `otros` | text | |
| `estado` | tinyint | 0=pendiente, 1=aprobada, 2=rechazada. |
| `comentario_staff` | text | Motivo si se rechaza. |
| `created_at` | int | |
| `updated_at` | int | Se actualiza cada vez que el creador edita mientras sigue pendiente (ver Página 2). |
| `resuelto_at` | int | |
| `resuelto_por` | int | uid del staff que decidió. |

**Editable mientras `estado=0`:** el creador (`uid`) puede volver a la página 2 y modificar cualquier campo de su propia solicitud pendiente — por ejemplo si staff le pide un cambio, o cambia de opinión — sin crear una solicitud nueva. Deja de ser editable en cuanto `estado` pasa a 1 o 2.

Al aprobar, la consola de mods crea la fila en `mybb_op_tripulaciones` + las filas iniciales en `mybb_op_tripulaciones_miembros` (el líder entra con `rango=2`, el resto con `rango=0`) a partir de los datos ya estructurados de la solicitud — no hace falta decodificar JSON de un campo genérico, cada dato tiene su propia columna. La solicitud no se borra, queda como registro histórico con `estado=1`.

### `mybb_op_audit_tripulaciones`

El proyecto tiene dos patrones de auditoría ya en uso: uno genérico (`mybb_op_audit_general`, usado por `banners.php` con `categoria='banners'`) y uno específico por dominio (`audit_crafteo`, `audit_entrenamientos`, `audit_stats`, `audit_recompensas`, `audit_oficios`, etc.). El historial de tripulación necesita filtrarse por tripulación y ser público, así que sigue el patrón específico por dominio.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | int, PK, auto_increment | |
| `tripulacion_id` | int | |
| `uid` | int | Quién hizo el cambio. |
| `accion` | varchar(50) | `miembro_agregado`, `miembro_removido`, `logo_cambiado`, `estado_cambiado`, etc. |
| `detalle` | text | |
| `created_at` | int | |

## Decisiones

| Tema | Decisión | Motivo |
|---|---|---|
| Solicitud de creación: ¿tabla nueva o reusar `mybb_op_peticiones`? | Tabla nueva `mybb_op_tripulaciones_solicitudes`, con `estado` de 3 valores. | Decisión explícita — mantiene el sistema de tripulaciones autocontenido en sus propias tablas, con columnas propias en vez de un campo JSON genérico. |
| ¿La solicitud pendiente aparece en algún lado fuera de la página 2? | Sí: `op/peticiones.php` (la página general de "mis peticiones") suma una segunda consulta contra `mybb_op_tripulaciones_solicitudes WHERE uid=$uid AND estado=0` y la muestra en la misma lista, con su propia etiqueta. | Decisión explícita — el usuario espera ver el estado de su solicitud donde ya mira sus otras peticiones, aunque los datos vivan en una tabla separada. |
| ¿Se puede editar una solicitud ya enviada? | Sí, mientras `estado=0`. El creador vuelve a la página 2 y reenvía el formulario, que actúa como `UPDATE` en vez de `INSERT` sobre su propia solicitud pendiente. | Pedido explícito — cubre el caso de que staff pida un cambio antes de aprobar, o el creador cambie de opinión. |
| ¿La tripulación se refleja en la ficha del personaje? | Sí — `op/ficha.php` suma una sección chica que busca al personaje en `mybb_op_tripulaciones_miembros` y, si pertenece a una, muestra nombre/logo con link a la página 3. No se agrega una columna de tripulación en `mybb_op_fichas`. | Pedido explícito ("que se cree la tripulación... en la ficha"). Se resuelve por consulta en vez de duplicar el dato, para no tener dos fuentes de verdad de la misma membresía. |
| Baúl: ¿reusar `mybb_op_cofres`? | No — `mybb_op_cofres` es el catálogo de tipos de cofre del sistema de gacha, no almacenamiento genérico. Tabla nueva `mybb_op_tripulaciones_baul`, y el término se llama **baúl**, no cofre. | Evita confundir dos conceptos distintos que comparten la palabra "cofre" en español. |
| Barcos: ¿columna en `mybb_op_barcos` o tabla de propiedad nueva? | Columna `tripulacion_id` directo en `mybb_op_barcos`. | Decisión explícita — se usa la columna existente en vez de una tabla de join nueva. |
| Historial: ¿`audit_general` o tabla propia? | Tabla propia `mybb_op_audit_tripulaciones`. | Necesita filtrar por tripulación y mostrarse en una página pública — `audit_general` no tiene columna de entidad. |
| Rango de Capitán/Vicecapitán: ¿único por tripulación? | No, puede haber varios con rango 2 o 1 a la vez. | Ya definido en el requirements doc: "en teoría podrían existir bandas donde todos son Capitanes". |
| Rol decorativo: ¿estructurado (lista fija) o texto libre? | Texto libre. | Ya definido en el requirements doc — es puramente narrativo. |
| Facción de la tripulación: ¿propia, heredada o elegida? | El líder la elige al crear la tripulación, de entre las facciones que existen en el foro. No se copia automáticamente de su ficha. Editable después solo por Staff (página 4), nunca por Capitán/Vicecapitán. | Decisión explícita — permite que la facción de la tripulación sea independiente de la facción personal del líder. |
| Duplicados de nombre | Se bloquean. `mybb_op_tripulaciones.nombre` es único, y la página 2 valida contra tripulaciones existentes + solicitudes pendientes antes de dejar enviar el formulario. | Decisión explícita, aunque en la práctica sea poco probable que choque. |
| Baúl/barcos de una tripulación Disuelta | Quedan congelados, visibles pero no editables. Qué hacer con ellos después lo decide Staff caso por caso, sin automatismo. | Decisión explícita — no se libera ni se reparte solo. |
| ¿Quién ve la página de la tripulación? | Cualquier usuario logueado puede entrar y ver nombre, logo, facción, estado, miembros, barcos, reputación total e historial. El baúl solo lo ven los miembros. Las acciones de modificar (logo, nombre, miembros) solo las ven Capitán/Vicecapitán. | Consistente con las decisiones ya tomadas: "historial es público" y "baúl visible para miembros, privado para el resto". |

## Página 1: Directorio de Tripulaciones

- **Archivo:** `op/tripulaciones.php`, página pública (cualquier usuario logueado; no hace falta `does_ficha_exist`, es solo lectura).
- **Consulta:** todas las filas de `mybb_op_tripulaciones` sin filtrar por `estado` por defecto, con paginación (a confirmar contra el patrón de paginación que ya use otro listado de `/op/` al implementar).
- **Filtros:** facción (dropdown con las mismas facciones de `mybb_op_fichas.faccion`/`op_global.css`), `buscando_miembros` (checkbox), rango de cantidad de miembros.
- **Cada tarjeta muestra:** logo (o placeholder si está vacío), nombre, badge de facción (con el color de la variable CSS correspondiente, ej. `--pirata-group-color`), cantidad de miembros, `mensaje_publico`, y el estado si no es Activa.
- **Interfaz:** cuadrícula de tarjetas reutilizando `.item-outer`/`.item-image`/`.item-nombre` de `op_global.css` como base, dentro del marco `.mainBackground`/`.secondBackground`/`.thirdBackground` que ya usan las demás páginas de `/op/`.

## Página 2: Solicitud de Creación

- **Archivo:** `op/tripulacion_solicitar.php`.
- **Acceso:** requiere `does_ficha_exist($uid)` — sin personaje no se puede fundar una tripulación.
- **Formulario, 9 campos:** nombre propuesto, facción (dropdown, de la lista de facciones del foro), bandera (opcional), link, usuarios iniciales, líder, jerarquía, ideales, otros.
- **Al enviar (POST, con `verify_post_check()`):**
  - Si el usuario **no tiene** una solicitud propia con `estado=0`: `INSERT` en `mybb_op_tripulaciones_solicitudes`.
  - Si **ya tiene** una pendiente: la página la carga precargada en el mismo formulario en vez de mostrarlo vacío, y el envío hace `UPDATE` sobre esa fila (refresca `updated_at`) en vez de crear una segunda. No se puede tener más de una solicitud pendiente a la vez.
  - Si la última solicitud del usuario está `estado=1` o `estado=2` (ya resuelta), el formulario se muestra vacío y un envío nuevo crea otra fila — no hay límite a solicitudes históricas, solo a pendientes simultáneas.
- No crea todavía la tripulación ni las filas de miembros — eso pasa recién cuando staff aprueba desde la consola.
- **Selección de usuarios iniciales:** por nombre de personaje con autocompletado contra `mybb_op_fichas`, guardando los `fid` resultantes en `usuarios_iniciales_json`.

### Cambio en `op/peticiones.php`

Se agrega una segunda consulta, `SELECT * FROM mybb_op_tripulaciones_solicitudes WHERE uid='$uid' AND estado=0`, y sus resultados se suman a `$peticiones_txt` con la etiqueta `"Solicitud de Tripulación"` (mismo formato que las demás filas: resumen = nombre propuesto, con link a `op/tripulacion_solicitar.php` para editarla). No se toca la tabla `mybb_op_peticiones` ni sus categorías existentes — es una consulta adicional, no una migración de datos.

## Página 3: Página de la Tripulación

- **Archivo:** `op/tripulacion.php?id={id}`.
- **Acceso:** lectura para cualquier usuario logueado. Las acciones de escritura (añadir/quitar miembro, cambiar logo, cambiar `mensaje_publico`/`buscando_miembros`) requieren que el `$mybb->user['uid']` tenga una fila en `mybb_op_tripulaciones_miembros` con `rango` 1 o 2 para ese `tripulacion_id`.
- **Secciones:**
  - **Identidad:** logo, nombre, facción, estado. Editable (logo, nombre) solo por Capitán/Vicecapitán — la facción no es editable desde acá, solo desde la consola de mods.
  - **Miembros:** listado completo (fid, nombre de personaje, rango, rol decorativo). Capitán/Vicecapitán ven botones de añadir/quitar; el líder puede editar el `rol_decorativo` de cualquiera, incluido el propio.
  - **Recursos:** baúl (berries/nika/kuro + items de `mybb_op_tripulaciones_baul`) — **solo visible si el usuario actual está en la lista de miembros**; lista de barcos (`mybb_op_barcos WHERE tripulacion_id = {id}`) — pública, igual que el resto de la página.
  - **Estadísticas:** suma de `mybb_op_fichas.reputacion` de todos los miembros.
  - **Historial:** últimas entradas de `mybb_op_audit_tripulaciones` para este `tripulacion_id`, público.
- **Interfaz:** mismo marco `.mainBackground`/`.secondBackground`/`.thirdBackground` + barras `.barra-op` por sección, listado de miembros con tarjetas tipo `.item-outer`.

### Cambio en `op/ficha.php`

Sección chica nueva: busca `fid` del personaje en `mybb_op_tripulaciones_miembros`, y si tiene fila, muestra el logo y nombre de su tripulación (`mybb_op_tripulaciones`) con link a `op/tripulacion.php?id={id}`. Si no pertenece a ninguna, no muestra nada — no hace falta un estado "sin tripulación" explícito.

## Página 4: Consola de Mods

- **Archivo:** `op/staff/tripulaciones.php`.
- **Acceso:** `if (!is_mod($uid) && !is_staff($uid))` — mismo gate exacto que `op/staff/consola_mod.php:21`. Botón nuevo en `templates/One_Piece_Gaiden_Templates/staff_consola_mod.html`.
- **Acciones:**
  - Ver cola de `mybb_op_tripulaciones_solicitudes` con `estado=0` y **aprobar** (crea la tripulación + miembros iniciales a partir de las columnas de la solicitud) o **rechazar** (pide `comentario_staff`).
  - Cambiar `estado` de una tripulación existente (Activa/Inactiva/Disuelta) — decisión ya tomada de que este cambio es manual y solo de staff.
  - Override completo: editar nombre, logo, facción, miembros y rangos de cualquier tripulación, para los casos de disputa que el requirements doc resume como "manipular todo lo relacionado a las bandas".
- Cada acción exitosa se registra en `mybb_op_audit_tripulaciones`, igual que las acciones que hacen capitán/vicecapitán desde la página 3 — no hay una tabla de auditoría separada para las acciones de staff.

## Seguridad

- **CSRF:** todas las acciones de escritura (páginas 2, 3 y 4) son POST con `verify_post_check()`, sin excepciones por GET — mismo patrón que `banners.php`.
- **Permisos server-side, no solo ocultar botones:** cada acción de escritura vuelve a comprobar el rango en `mybb_op_tripulaciones_miembros` (página 3) o `is_mod`/`is_staff` (página 4) en el servidor, no solo esconde el botón en el HTML.
- **Escapado de salida:** `htmlspecialchars` en todo lo que venga de `nombre`, `mensaje_publico`, `ideales`, `jerarquia_texto`, `otros` — son campos de texto libre escritos por usuarios.
- **Editar una solicitud ajena:** la página 2 comprueba que la solicitud que se está editando pertenece al `uid` actual antes de aceptar el `UPDATE` — no alcanza con que el formulario mande el `id` correcto.
- **Auditoría:** toda acción de escritura relevante (crear, aprobar/rechazar solicitud, cambiar estado, añadir/quitar miembro, cambiar logo) se registra en `mybb_op_audit_tripulaciones`.
- **Post/Redirect/Get:** después de cada POST, redirigir sin query string, igual que `banners.php`, para que F5 no repita la acción.
- **Subida de logo:** validar tipo real de imagen con `getimagesize()` (no la extensión), límite de tamaño, y recodificar con GD antes de guardar — mismo tratamiento que se le da a los banners en `banners-staff-design.md`.

## Casos límite

- **Tripulación sin Capitán.** Si se quita al único Capitán/Vicecapitán (por ejemplo, el jugador se va del foro), nadie puede gestionar la tripulación desde la página 3. La consola de mods necesita poder asignar rango sin depender de que exista uno previo — ya cubierto por el "override completo" de la página 4.
- **Solicitud con un `fid` de usuarios iniciales que ya no existe** (personaje borrado entre que se envía la solicitud y se aprueba). Al aprobar, filtrar la lista de `fid` de `usuarios_iniciales_json` contra `mybb_op_fichas` vigente antes de crear las filas de miembros.
- **Doble solicitud del mismo nombre.** La página 2 valida el nombre propuesto contra `mybb_op_tripulaciones.nombre` (único) y contra otras solicitudes pendientes (`estado=0`) antes de aceptar el envío. Si dos solicitudes pasan la validación casi al mismo tiempo (carrera), la consola rechaza automáticamente la segunda que se intente aprobar si el nombre ya quedó tomado por la primera.
- **Editar una solicitud que staff está por resolver.** Si el creador edita justo cuando un mod tiene la página de aprobación abierta, el mod podría estar viendo datos viejos. No hay lock — la consola de mods relee la fila al momento de aprobar/rechazar, así que usa siempre el contenido más reciente, pero el mod podría no notar que cambió algo desde que abrió la pantalla. Mitigado por mostrar `updated_at` en la cola de la consola.
- **Tripulación Disuelta con baúl no vacío.** El diseño no borra `mybb_op_tripulaciones_baul` ni el balance al disolver — queda congelado, visible en la página (que sigue siendo accesible aunque el estado no sea Activa). Qué hacer con esos recursos después lo decide Staff caso por caso, sin automatismo en el sistema.

## Fuera de alcance

- Editor visual de banderas (mencionado como idea en la conversación previa, descartado).
- Alianzas/rivalidades entre tripulaciones, notificaciones a Discord, sistema de invitaciones/solicitud de ingreso, progresión/niveles de tripulación — todas ideas propuestas y descartadas para esta primera versión.
- Vaciar o transferir el baúl entre tripulaciones.
- Límite máximo o mínimo de miembros por tripulación.
- Editar una solicitud ya resuelta (aprobada o rechazada) — para eso existe el override completo de la página 4 una vez que la tripulación ya existe.
