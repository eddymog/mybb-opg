# Requisito: sistema de "Creaciones" (peticiones de Técnicas, Akuma no Mi, Estilos, Objetos, Mascotas/NPC)

## Objetivo

El foro tiene 5 perfiles de creaciones (subforos donde los usuarios piden que
staff les cree/apruebe algo):

1. Técnicas
2. Akuma no Mi
3. Estilos y Pasivas
4. Objetos y Crafteos
5. Mascotas y NPC

Cada uno de estos subforos tiene, a su vez, dos subforos hijos: uno de
"Completadas" y otro de "Canceladas", donde se archivan las peticiones ya
resueltas.

Se necesita un sistema centralizado para que el staff sepa, de un vistazo,
cuántas peticiones de creación hay pendientes de atender (en cualquiera de
los 5 perfiles), y para poder mover una petición específica a Completada o
Cancelada sin tener que mover el tema a mano.

## FIDs de referencia (confirmados por el dueño del proyecto)

| Perfil               | FID del subforo | FID Completadas | FID Canceladas |
|-----------------------|:---------------:|:----------------:|:----------------:|
| Técnicas              | 369              | 447              | 446              |
| Akuma no Mi            | 373              | 448              | 449              |
| Estilos y Pasivas      | 370              | 450              | 451              |
| Objetos y Crafteos     | 371              | 452              | 453              |
| Mascotas y NPC         | 372              | 454              | 455              |

**FID 8 = el forum padre (categoría) que contiene a los 5 subforos de
arriba.** Confirmado. Esto importa para las queries: filtrar solo por
`f.fid = 8` (como hace hoy el código existente) no alcanza, porque los
temas de petición viven en los subforos hijos (369-373), no directamente
en la categoría contenedora. Hay que filtrar por los 5 FIDs de perfil
(`t.fid IN (369,370,371,372,373)`) o por `f.parentlist LIKE '%,8,%'` —
cualquiera de las dos funciona, y la segunda tiene la ventaja de no
requerir tocar código si algún día se agrega un 6to perfil bajo la misma
categoría.

## Estado actual del código (importante: esto ya existe, parcialmente)

Antes de diseñar esto desde cero: **ya hay una versión de este sistema
funcionando**, pero cubre un solo perfil, no los 5.

- **[`op/staff/tecnicas_creacion.php`](/Users/eddymogollon/Documents/Code/mybb-opg/op/staff/tecnicas_creacion.php)**
  — página de staff que lista peticiones pendientes en 3 grupos: "Sin
  respuesta de moderación" (nadie contestó todavía), "El usuario respondió,
  a la espera de moderación" (mod ya contestó, el usuario volvió a postear
  — exactamente el segundo caso que describís en el pedido original), y "A
  la espera del usuario" (mod ya contestó, el usuario no volvió — con un
  botón "Abandonar" que mueve el tema a `fid=85` y lo cierra).
- **[`global.php`](/Users/eddymogollon/Documents/Code/mybb-opg/global.php) (línea ~857)**
  — calcula dos contadores (`$g_total_creaciones_sin_moderar` y
  `$g_total_creaciones_pendientes`) dentro del mismo bloque `if
  (is_staff($g_uid) || ...)` donde ya se calculan `$g_peticiones`,
  `$g_fichas_en_cola`, etc.
- **[`templates/.../header.html`](/Users/eddymogollon/Documents/Code/mybb-opg/templates/One_Piece_Gaiden_Templates/header.html) (línea 996, dentro de `#peticiones_staff`)**
  — ya muestra `Creaciones: {$g_total_creaciones_sin_moderar} /
  {$g_total_creaciones_pendientes}` como link a `tecnicas_creacion.php`,
  junto a Fichas/Peticiones/Bugs/Avisos. Es decir: el div `#peticiones_staff`
  que pedís ya existe y ya tiene una entrada de Creaciones.

**El problema real:** las 3 queries de `tecnicas_creacion.php` y las 2 de
`global.php` filtran por `f.fid = 8` — la categoría padre, no los subforos
donde realmente viven los temas. Con el forum estructurado como está hoy
(8 como padre de 369-373), esa condición no matchea los temas de petición
reales, así que estos contadores probablemente están rotos/en cero desde
que se separaron los 5 perfiles. Hay que cambiar `f.fid = 8` por
`t.fid IN (369,370,371,372,373)` (o `f.parentlist LIKE '%,8,%'`) en las 5
queries existentes.

**Lo que definitivamente no existe todavía:** ninguna acción de
"Completada" / "Cancelada" por petición. Lo único que hay parecido es
"Abandonar" en `tecnicas_creacion.php`, que mueve el tema a un forum de
archivo genérico (`fid=85`) — no es lo mismo que archivarlo en el subforo
de Completadas o Canceladas de su propio perfil. Tampoco hay nada que
use los FIDs 446-455.

## Requisitos funcionales

1. **Contar peticiones pendientes en los 5 perfiles**, no solo en uno. Dos
   casos cuentan como "pendiente":
   - El usuario creó el tema y nadie de staff respondió todavía.
   - Staff ya respondió, pero el usuario volvió a postear después — hay
     que revisar esa respuesta también.
2. **Página `/op/staff/solicitudes_creacion.php`** que liste, agrupadas por
   perfil (o con filtro por perfil), las peticiones pendientes de los 5
   subforos — básicamente `tecnicas_creacion.php` generalizado a los 5 FIDs
   en vez de uno solo.
3. **Botones "Completada" / "Cancelada" por petición**, que muevan el tema
   al subforo hijo correspondiente de su propio perfil (ej: una petición de
   Técnicas completada va al FID 447, no a un forum de archivo genérico).
4. **Contador global visible en todo el sitio** (el que ya vive en
   `#peticiones_staff` del header) — debe sumar los 5 perfiles, no solo
   Técnicas.
5. **Estadísticas de completadas/canceladas dentro de
   `solicitudes_creacion.php`** (no en una página aparte), **divididas por
   perfil**: una sección/tabla con el total de completadas y canceladas de
   cada uno de los 5 (Técnicas, Akuma no Mi, Estilos y Pasivas, Objetos y
   Crafteos, Mascotas y NPC), no un total único combinado. Como se
   archivan en subforos reales, esto es un `COUNT(*)` por FID de
   Completadas/Canceladas de la tabla de arriba — no hace falta una tabla
   nueva para esto.
6. **Cada petición listada debe mostrar también quién ya posteó
   respondiéndola**, no solo quién la abrió. Por convención: el que abre
   el tema es el usuario que pide la creación; el/los que postean después
   son moderadores. `tc_item()` (la función que arma cada tarjeta en
   `tecnicas_creacion.php` hoy) solo muestra usuario (el que abrió el
   tema), título y fecha — hay que sumarle la lista de moderadores que ya
   participaron. Se resuelve con un `SELECT DISTINCT uid, username FROM
   mybb_posts WHERE tid=X AND uid != <uid del que abrió el tema>` por cada
   tema listado (o un solo query con `GROUP_CONCAT` para toda la lista de
   una).

## Cómo implementarlo (respuesta a "¿plugin o no?")

**Sí, plugin — y no tocar `global.php` para esto.** `global.php` ya tiene
el bloque `$g_peticiones` / `$g_fichas_en_cola` / `$g_total_creaciones_*`
(~línea 842), pero es código del core de bootstrap y no queremos seguir
agregando lógica de features ahí. Este mismo repo ya tiene el patrón
correcto armado como plugin real, para un caso casi idéntico —
**[`inc/plugins/op_bitacora.php`](/Users/eddymogollon/Documents/Code/mybb-opg/inc/plugins/op_bitacora.php)**
(+ `inc/plugins/op_bitacora/functions.php`):

- Hookea `global_intermediate` (`op_temas_hook_header()`) para calcular un
  dato por request y dejarlo en una variable global (`$op_temas_header`).
- El `header.html` la consume como `{$op_temas_header}` — sin que
  `global.php` sepa nada de esto.
- Su `activate()` (`op_temas_insertar_placeholder_header()`) inserta ese
  placeholder en la fila `header` de `mybb_templates` automáticamente al
  activar el plugin — no hace falta editar el template a mano.
- Trae funciones `_info()` / `_install()` / `_is_installed()` /
  `_activate()` / `_uninstall()` estándar de MyBB, instala su propio
  template y su propio stylesheet.

Nombre elegido para esta feature: **`solicitudes_creacion`** (no
"creaciones" a secas, que es ambiguo con crear ficha/objeto/etc.; y no
"tracker", que ya lo usa `op_bitacora` para otra cosa — turnos de
rol). Se apoya en el vocabulario que ya usa el sitio ("solicitudes de
tripulación" en `peticiones_admin.php`).

Para esto sería el mismo esqueleto que `op_bitacora`:

1. **`inc/plugins/op_solicitudes_creacion.php`** (+ un
   `op_solicitudes_creacion/functions.php` si se pone denso) con
   `$plugins->add_hook('global_intermediate',
   'op_solicitudes_creacion_hook_header')`, que calcula los dos contadores
   (mismas dos queries que hoy tiene `global.php`, pero sumando los 5 FIDs
   de perfiles en vez de `f.fid = 8`) y los deja en variables globales
   nuevas (ej. `$op_solicitudes_creacion_pendientes` /
   `$op_solicitudes_creacion_sin_responder`).
2. En `activate()`, insertar el placeholder correspondiente en el
   `header` (mismo mecanismo que `op_temas_insertar_placeholder_header()`)
   reemplazando el uso actual de `{$g_total_creaciones_sin_moderar}` /
   `{$g_total_creaciones_pendientes}` en `header.html`.
3. Dejar los dos contadores viejos en `global.php` tal cual (no vale la
   pena tocarlos si van a quedar sin uso una vez migrado el header) o, si
   se prefiere, borrarlos en un commit aparte — pero como tarea de
   limpieza, no como parte de construir la feature nueva.
4. Generalizar `tecnicas_creacion.php` → `/op/staff/solicitudes_creacion.php`:
   mismas 3 consultas (sin contestar / usuario respondió / a la espera del
   usuario), pero recorriendo los 5 FIDs y mostrando de qué perfil es cada
   petición. Esta parte sí es una página de `/op/staff/`, no un plugin —
   sigue el patrón normal de esa carpeta.
5. Agregar las dos acciones nuevas (Completada/Cancelada) siguiendo el
   mismo patrón que ya usa "Abandonar" en `tecnicas_creacion.php` (POST
   con `my_post_key`, `UPDATE mybb_threads SET fid=... WHERE tid=...`),
   pero calculando el FID de destino según el perfil de origen del tema en
   vez de un valor fijo.

No se necesita una tabla nueva (`mybb_op_creaciones` ni similar): el estado
de una petición ya lo representa en qué forum vive el tema (pendiente en su
subforo, o archivado en Completadas/Canceladas) — es el mismo enfoque que
ya usa "Abandonar", solo que apuntando al subforo correcto en vez de uno
genérico.

## Pendiente antes de implementar

- [x] FIDs de perfil y de Completadas/Canceladas (tabla de arriba) —
      confirmados.
- [x] Rol del FID 8 — confirmado, es el forum padre de los 5 perfiles.
- [x] Ubicación de las estadísticas de completadas/canceladas —
      confirmado, dentro de `solicitudes_creacion.php`, una sección por
      perfil.
- [ ] Nada más pendiente de definición — listo para pasar a
      implementación cuando se decida arrancar.
