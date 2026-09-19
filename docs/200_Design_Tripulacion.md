# Sistema de Tripulaciones: diseño

> Depende de: [100_Requirements_Tripulacion.md](100_Requirements_Tripulacion.md)

## Objetivo

Reemplazar el proceso manual actual (crear una tripulación era hacer un post en la zona de posts) por cuatro páginas nuevas dentro de `/op/`: un directorio público, un formulario de solicitud, una página de gestión interna por tripulación, y una herramienta en la consola de mods.

## Estado actual

- No existe ninguna tabla `tripulacion` ni `banda` en `rovddqmy_op.sql` — se parte de cero para el modelo propio de tripulaciones.
- `mybb_op_fichas` ya tiene `fid` (= uid), `faccion` (varchar, coincide con las variables de color de `templates/op_global.css`: `--pirata-group-color`, `--marine-group-color`, `--cipher-pol-group-color`, `--cazarrecompensas-group-color`, `--revolucionario-group-color`, `--civil-group-color`, `--narradores-group-color`, `--npcs-group-color`) y **dos pares de columnas de reputación**: `reputacion`/`reputacion_positiva`/`reputacion_negativa` y `reputacion2`/`reputacion_positiva2`/`reputacion_negativa2`. La tripulación suma la columna `reputacion` (la principal/por defecto) — `reputacion2` no se usa en este sistema.
- `mybb_op_barcos` ya existe (`barco_id`, `nombre_barco`, `vitalidad`, `espacios`, `velocidad`, `tiempo_viaje`, `resistencia`, `espacios_mejora`, `ruputura`), pero **no tiene ninguna columna de propiedad** — nada dice hoy qué barco es de qué usuario o tripulación. Este diseño le añade una columna nueva en vez de crear una tabla aparte (ver Modelo de datos).
- `mybb_op_cofres` **no se usa para este sistema**: es el catálogo de tipos de cofre que usa el sistema de gacha (`/opg/`), no un almacén genérico de items. Para el almacenamiento de items de la tripulación se crea una tabla propia — y se le llama **baúl**, no "cofre", justamente para no pisar el término que ya significa otra cosa en el foro.
- `mybb_op_peticiones` ya existe como tabla genérica de solicitudes: `id`, `uid`, `nombre` (varchar(20)), `categoria`, `resumen` (varchar(255)), `descripcion` (text), `url`, `resuelto` (tinyint binario), `enviado`, `mod_uid`, `mod_nombre`, `atendidoPor`, `notasMod`. Se reutiliza para la solicitud de creación de tripulación en vez de crear una tabla nueva — ver el mapeo de campos más abajo.
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

### Solicitud de creación: reuso de `mybb_op_peticiones`

No se crea tabla nueva. Mapeo de los campos del formulario (sección 2 del requirements doc) a las columnas existentes:

| Campo del formulario | Columna de `mybb_op_peticiones` |
|---|---|
| — | `categoria = 'tripulacion_creacion'` (constante para filtrar en la consola) |
| Nombre de la tripulación propuesto | `resumen` (255 caracteres — **no** `nombre`, que es varchar(20) y se queda corto para un nombre de tripulación) |
| Link (tema de formación) | `url` |
| Bandera, facción, usuarios iniciales, líder, jerarquía, ideales, otros | Empaquetados como JSON en `descripcion` |
| — | `uid` = quién envía el formulario |

**Limitación aceptada al reusar esta tabla:** `resuelto` es binario, no distingue aprobada de rechazada. Convención: al resolver, `notasMod` se guarda con el prefijo `APROBADA:` o `RECHAZADA:` seguido del motivo, para que la consola pueda distinguir el resultado sin una columna de estado propia.

Al aprobar, la consola de mods lee y decodifica el JSON de `descripcion`, crea la fila en `mybb_op_tripulaciones` + las filas iniciales en `mybb_op_tripulaciones_miembros` (el líder entra con `rango=2`, el resto con `rango=0`), y marca la petición como `resuelto=1` con `notasMod='APROBADA'`. La fila de `mybb_op_peticiones` no se borra, queda como registro histórico.

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
| Solicitud de creación: ¿tabla nueva o reusar `mybb_op_peticiones`? | Reusar `mybb_op_peticiones`, con `categoria='tripulacion_creacion'` y los campos extra empaquetados como JSON en `descripcion`. | Evita una tabla nueva solo para esto. El costo es que `resuelto` no distingue aprobado/rechazado — se resuelve con una convención de texto en `notasMod`. |
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
- **Al enviar (POST, con `verify_post_check()`):** inserta en `mybb_op_peticiones` con `categoria='tripulacion_creacion'`, `resumen` = nombre propuesto, `url` = link, y el resto empaquetado como JSON en `descripcion` (ver Modelo de datos). No crea todavía la tripulación ni las filas de miembros — eso pasa recién cuando staff aprueba desde la consola.
- **Selección de usuarios iniciales:** por nombre de personaje con autocompletado contra `mybb_op_fichas`, guardando los `fid` resultantes en el JSON.

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

## Página 4: Consola de Mods

- **Archivo:** `op/staff/tripulaciones.php`.
- **Acceso:** `if (!is_mod($uid) && !is_staff($uid))` — mismo gate exacto que `op/staff/consola_mod.php:21`. Botón nuevo en `templates/One_Piece_Gaiden_Templates/staff_consola_mod.html`.
- **Acciones:**
  - Ver cola de `mybb_op_peticiones` con `categoria='tripulacion_creacion'` y `resuelto=0`, y **aprobar** (decodifica el JSON, crea la tripulación + miembros iniciales) o **rechazar** (pide motivo, se guarda en `notasMod` con el prefijo `RECHAZADA:`).
  - Cambiar `estado` de una tripulación existente (Activa/Inactiva/Disuelta) — decisión ya tomada de que este cambio es manual y solo de staff.
  - Override completo: editar nombre, logo, facción, miembros y rangos de cualquier tripulación, para los casos de disputa que el requirements doc resume como "manipular todo lo relacionado a las bandas".
- Cada acción exitosa se registra en `mybb_op_audit_tripulaciones`, igual que las acciones que hacen capitán/vicecapitán desde la página 3 — no hay una tabla de auditoría separada para las acciones de staff.

## Seguridad

- **CSRF:** todas las acciones de escritura (páginas 2, 3 y 4) son POST con `verify_post_check()`, sin excepciones por GET — mismo patrón que `banners.php`.
- **Permisos server-side, no solo ocultar botones:** cada acción de escritura vuelve a comprobar el rango en `mybb_op_tripulaciones_miembros` (página 3) o `is_mod`/`is_staff` (página 4) en el servidor, no solo esconde el botón en el HTML.
- **Escapado de salida:** `htmlspecialchars` en todo lo que venga de `nombre`, `mensaje_publico`, `ideales`, `jerarquia_texto`, `otros` — son campos de texto libre escritos por usuarios.
- **Filtrado por `categoria`:** como `mybb_op_peticiones` es compartida con otras features, toda consulta de la consola de tripulaciones debe incluir `categoria='tripulacion_creacion'` explícitamente — nunca listar la tabla sin ese filtro.
- **Auditoría:** toda acción de escritura relevante (crear, aprobar/rechazar solicitud, cambiar estado, añadir/quitar miembro, cambiar logo) se registra en `mybb_op_audit_tripulaciones`.
- **Post/Redirect/Get:** después de cada POST, redirigir sin query string, igual que `banners.php`, para que F5 no repita la acción.
- **Subida de logo:** validar tipo real de imagen con `getimagesize()` (no la extensión), límite de tamaño, y recodificar con GD antes de guardar — mismo tratamiento que se le da a los banners en `banners-staff-design.md`.

## Casos límite

- **Tripulación sin Capitán.** Si se quita al único Capitán/Vicecapitán (por ejemplo, el jugador se va del foro), nadie puede gestionar la tripulación desde la página 3. La consola de mods necesita poder asignar rango sin depender de que exista uno previo — ya cubierto por el "override completo" de la página 4.
- **Solicitud con un `fid` de usuarios iniciales que ya no existe** (personaje borrado entre que se envía la solicitud y se aprueba). Al aprobar, filtrar la lista de `fid` del JSON contra `mybb_op_fichas` vigente antes de crear las filas de miembros.
- **Doble solicitud del mismo nombre.** La página 2 valida el nombre propuesto contra `mybb_op_tripulaciones.nombre` (único) y contra otras peticiones pendientes (`categoria='tripulacion_creacion'`, `resuelto=0`) antes de aceptar el envío. Si dos solicitudes pasan la validación casi al mismo tiempo (carrera), la consola rechaza automáticamente la segunda que se intente aprobar si el nombre ya quedó tomado por la primera.
- **Tripulación Disuelta con baúl no vacío.** El diseño no borra `mybb_op_tripulaciones_baul` ni el balance al disolver — queda congelado, visible en la página (que sigue siendo accesible aunque el estado no sea Activa). Qué hacer con esos recursos después lo decide Staff caso por caso, sin automatismo en el sistema.

## Fuera de alcance

- Editor visual de banderas (mencionado como idea en la conversación previa, descartado).
- Alianzas/rivalidades entre tripulaciones, notificaciones a Discord, sistema de invitaciones/solicitud de ingreso, progresión/niveles de tripulación — todas ideas propuestas y descartadas para esta primera versión.
- Vaciar o transferir el baúl entre tripulaciones.
- Límite máximo o mínimo de miembros por tripulación.
