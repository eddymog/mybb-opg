# Solicitudes de creación: plan de diseño

> Depende de: [100_Requirements_Creaciones.md](100_Requirements_Creaciones.md)

## 1. Objetivo

Generalizar el sistema de moderación de peticiones de creación (hoy limitado
a un solo perfil, Técnicas, vía `op/staff/tecnicas_creacion.php`) para que
cubra los 5 perfiles del foro, con:

- una página única `/op/staff/solicitudes_creacion.php` que lista las
  peticiones pendientes de los 5 perfiles, con filtro por perfil;
- dos acciones nuevas por petición — **Completada** y **Cancelada** — que
  archivan el tema en el subforo correspondiente a su propio perfil;
- un contador global (plugin, sin tocar `global.php`) visible en
  `#peticiones_staff` del header, sumando los 5 perfiles;
- una sección de estadísticas de completadas/canceladas dentro de la misma
  página, desglosada por perfil;
- en cada petición listada, además de quién la abrió, quién ya la respondió
  (los moderadores que postearon en el tema).

El estado de una petición lo representa el forum donde vive el tema. La única
tabla propia, `op_solicitudes_creacion_resoluciones`, es un registro de
auditoría para atribuir cada resolución al moderador que pulsó la acción.

## 2. Estado actual relevante

- **`op/staff/tecnicas_creacion.php`** ya resuelve la lógica de 3 grupos
  (sin respuesta / usuario respondió / a la espera del usuario) y la acción
  "Abandonar", pero solo para `f.fid = 8` — la categoría padre, no los
  subforos donde viven los temas. Con la estructura actual (8 como padre de
  369-373), ese filtro no matchea nada: hay que asumir que estos 3 grupos
  están rotos/en cero en producción desde que se separaron los 5 perfiles.
- **`global.php`** (~línea 842-908) calcula
  `$g_total_creaciones_sin_moderar` / `$g_total_creaciones_pendientes` con
  el mismo `f.fid = 8` roto, dentro del bloque `is_staff()` que también
  arma `$g_peticiones`, `$g_fichas_en_cola`, etc. **No se va a tocar** —
  decisión ya tomada de no seguir agregando lógica de features a
  `global.php`; estos dos contadores quedan como código muerto hasta que
  alguien los limpie en un commit aparte.
- **`templates/.../header.html`** (línea 996, dentro de `<if $g_is_staff
  then>`) ya muestra `Creaciones: {$g_total_creaciones_sin_moderar} /
  {$g_total_creaciones_pendientes}` enlazando a `tecnicas_creacion.php`.
  Esta entrada se reemplaza manualmente por las variables nuevas del plugin.
- **`inc/plugins/op_bitacora.php`** (+ su `functions.php`) es el
  precedente exacto a seguir para "variable global calculada por request,
  sin tocar `global.php`": hookea `global_intermediate`, expone
  `$op_temas_header`, y su `activate()` inserta el placeholder en la fila
  `header` de `mybb_templates` automáticamente
  (`op_temas_insertar_placeholder_header()`). Para Solicitudes solo se
  reutilizan el hook y el instalador de plantillas; no se copia la mutación
  automática del header.
- FIDs confirmados por el dueño del proyecto: los 5 perfiles (369, 370,
  371, 372, 373), sus 10 subforos de Completadas/Canceladas (446-455), y
  el FID 8 como forum padre/categoría que los contiene a los 5.
- Nombre elegido para esta feature: **`solicitudes_creacion`** — no
  "creaciones" (ambiguo con crear ficha/objeto) ni "tracker" (ya lo usa
  `op_bitacora` para otra cosa).

## 3. Decisiones de arquitectura

### 3.1 Componentes

| Componente | Responsabilidad |
|---|---|
| `inc/plugins/op_solicitudes_creacion.php` | Registro del plugin, instalación, hook de header |
| `inc/plugins/op_solicitudes_creacion/functions.php` | Mapa de perfiles/FIDs, queries compartidas, cálculo de contadores |
| `op/staff/solicitudes_creacion.php` | Página de staff: listado, filtros, acciones Completada/Cancelada, estadísticas |
| `templates/.../staff_solicitudes_creacion.html` | Plantilla de la página, instalada y actualizada por el plugin |
| `templates/.../header.html` | Consume manualmente las variables del contador y enlaza a la página |
| `mybb_op_solicitudes_creacion_resoluciones` | Auditoría de TID, perfil, acción, moderador y fecha para el resumen de actividad |

La lógica compartida (mapa de perfiles, queries de conteo, resolución de
FID destino para Completada/Cancelada) vive en `functions.php` para que la
use tanto el plugin (contador del header) como la página de staff —igual
patrón que `op_bitacora.php` / `op_bitacora/functions.php`.

### 3.2 Fuente de verdad

MyBB conserva la fuente de verdad completa:

- `mybb_threads.fid` determina en qué perfil vive la petición, y si está
  pendiente, completada o cancelada (según en cuál de los 3 FIDs del
  perfil esté);
- `mybb_threads.uid` (autor del tema) es el usuario que pidió la creación;
- `mybb_threads.uid == mybb_threads.lastposteruid` determina si le toca
  responder a staff (última palabra la tuvo el usuario) o no;
- `mybb_posts` determina quién ya posteó en el tema (para listar los
  moderadores que respondieron);
- `mybb_op_solicitudes_creacion_resoluciones` determina quién ejecutó cada
  resolución desde que se habilitó la auditoría.

No se persiste ningún estado nuevo: mover el tema de forum ES la acción de
marcarlo Completada o Cancelada.

### 3.3 Mapa de perfiles (configuración fija, no tabla)

Vive como constante en `functions.php`, análoga a los catálogos fijos que
ya usan otras pantallas de staff (ej. `$ESTILO_OPCIONES` en
`ficha_atributos2.php`):

```php
const OP_SOLICITUDES_CREACION_CATEGORIA_FID = 8; // forum padre de los 5

const OP_SOLICITUDES_CREACION_PERFILES = [
    369 => ['nombre' => 'Técnicas',          'completadas' => 447, 'canceladas' => 446],
    373 => ['nombre' => 'Akuma no Mi',        'completadas' => 448, 'canceladas' => 449],
    370 => ['nombre' => 'Estilos y Pasivas',  'completadas' => 450, 'canceladas' => 451],
    371 => ['nombre' => 'Objetos y Crafteos', 'completadas' => 452, 'canceladas' => 453],
    372 => ['nombre' => 'Mascotas y NPC',     'completadas' => 454, 'canceladas' => 455],
];
```

Todas las queries de "pendientes" filtran por
`t.fid IN (array_keys(OP_SOLICITUDES_CREACION_PERFILES))`. Las de
"estadísticas" recorren el array y hacen un `COUNT(*)` por cada FID de
`completadas`/`canceladas`. Si el día de mañana se agrega un 6to perfil,
el cambio es una línea en este array — nada de SQL nuevo.

## 4. Lógica de estados

Cada petición (thread) cae en exactamente uno de estos grupos, igual que
ya distingue `tecnicas_creacion.php` — solo que ahora recorriendo los 5
FIDs en vez de uno:

1. **Sin respuesta de moderación** — `t.fid IN (perfiles)`, `t.uid =
   t.lastposteruid`, `t.replies = 0`. Nadie de staff contestó nunca.
2. **El usuario respondió, a la espera de moderación** — mismo filtro,
   pero `t.replies > 0`. Staff ya había contestado y el usuario volvió a
   postear; hay que revisar esa respuesta de nuevo.
3. **A la espera del usuario** — `t.fid IN (perfiles)`, `t.uid !=
   t.lastposteruid`. Staff ya contestó y espera al usuario; no toca moderar.

Los grupos 1 y 2 son "pendiente" en el sentido del requisito original; el
grupo 3 no cuenta para el contador global.

### 4.1 Completada / Cancelada

Disponibles sobre cualquier petición de los tres grupos. El autor del último
post no condiciona la posibilidad de completar o cancelar; incluso una
petición a la espera del usuario puede resolverse directamente. Acción:

```text
UPDATE mybb_threads
SET fid = <FID completadas o canceladas del perfil de origen>, closed = 1
WHERE tid = <tid> AND fid IN (369,370,371,372,373)
```

El `WHERE fid IN (...)` se hace explícito en el propio `UPDATE`, para que no
se pueda mover un tema arbitrario
mandando un `tid` de cualquier otro forum del sitio (ver §10).

El FID destino se resuelve buscando `t.fid` original dentro de
`OP_SOLICITUDES_CREACION_PERFILES` y tomando su `completadas` o
`canceladas` — nunca un valor que venga del formulario/POST.

## 5. Hooks del plugin

### 5.1 Header

Hook: `global_intermediate` (mismo que `op_bitacora`), después de que
MyBB cargó usuario y permisos.

```php
function op_solicitudes_creacion_hook_header()
{
    global $mybb, $op_solicitudes_creacion_pendientes;
    $op_solicitudes_creacion_pendientes = 0;

    if (!is_staff($mybb->user['uid']) && !is_mod($mybb->user['uid']) && !is_user($mybb->user['uid'])) {
        return;
    }

    $conteo = op_solicitudes_creacion_contar_pendientes();
    $op_solicitudes_creacion_pendientes =
        (int)$conteo['sin_responder'] + (int)$conteo['reabiertas'];
}
```

Sale temprano para invitados y usuarios sin rol de staff — mismo criterio
de permisos que ya usa `is_staff() || is_mod() || is_user()` en
`global.php` para calcular `$g_peticiones` hoy.

### 5.2 Header — plantilla

`templates/.../header.html` reemplaza la línea 996 actual:

```text
<a href="/op/staff/tecnicas_creacion.php">Creaciones</a>: {$g_total_creaciones_sin_moderar} / {$g_total_creaciones_pendientes}
```

por:

```text
<a href="/op/staff/solicitudes_creacion.php">Solicitudes</a>: {$op_solicitudes_creacion_pendientes}
```

Esta línea se mantiene manualmente en `header.html`. El ciclo de vida del
plugin no inserta, reemplaza ni retira contenido del header.

## 6. Consultas y rendimiento

### 6.1 Conteo del header

Una sola consulta agregada con `SUM(CASE ...)` en vez de las dos queries
separadas que usa hoy `global.php`:

```sql
SELECT
    SUM(CASE WHEN replies = 0 THEN 1 ELSE 0 END) AS sin_responder,
    SUM(CASE WHEN replies > 0 THEN 1 ELSE 0 END) AS reabiertas
FROM mybb_threads
WHERE fid IN (369,370,371,372,373)
  AND closed = 0
  AND uid = lastposteruid
  AND visible = 1
```

### 6.2 Listado con posteadores (requisito #7)

Para no hacer una query por tarjeta, se resuelven los "moderadores que ya
participaron" de todos los temas listados en una página con un solo query
adicional:

```sql
SELECT DISTINCT p.tid, p.uid, p.username
FROM mybb_posts AS p
WHERE p.tid IN (<tids de la página actual>)
  AND p.uid != 0
  AND p.visible = 1
GROUP BY p.tid, p.uid
```

y en PHP se descarta, por tema, el `uid` que coincide con el autor del
thread (`t.uid`) — así queda solo la lista de quienes respondieron. Se
arma un mapa `tid => [usuarios]` antes de renderizar las tarjetas.

### 6.3 Estadísticas por perfil

Un `COUNT(*) ... GROUP BY fid` sobre los 10 FIDs de completadas/canceladas
en una sola query, en vez de 10 queries sueltas:

```sql
SELECT fid, COUNT(*) AS total
FROM mybb_threads
WHERE fid IN (446,447,448,449,450,451,452,453,454,455)
GROUP BY fid
```

El resultado se reparte en PHP contra `OP_SOLICITUDES_CREACION_PERFILES`
para armar la tabla por perfil (completadas y canceladas en columnas
separadas).

## 7. Página `/op/staff/solicitudes_creacion.php`

### 7.1 Acceso

Mismo gate que `tecnicas_creacion.php`: `is_mod($uid) || is_staff($uid) ||
is_user($uid)`, chequeado primero, antes de cualquier otra lógica.

### 7.2 Composición

1. Título + descripción breve.
2. Filtro por perfil (los 5, más "Todos" por defecto) — `<select>` que
   recarga por GET, mismo patrón simple que el resto de `/op/staff/`.
3. Tabla "Moderaciones pendientes por perfil", siempre global y situada
   antes de los listados. Separa en columnas `sin respuesta` y `usuario
   respondió` para cada uno de los cinco perfiles; no incluye "A la espera
   del usuario".
4. Tres secciones (igual que hoy, pero ahora con el perfil de cada tema
   visible en la tarjeta):
   - Sin respuesta de moderación
   - El usuario respondió, a la espera de moderación
   - A la espera del usuario
5. Cada tarjeta de petición (extensión de `tc_item()`):
   - Perfil (nuevo — de qué categoría es esta petición)
   - Usuario (autor del tema)
   - **Moderadores que ya respondieron** (nuevo, requisito #7) — lista de
     usuarios distintos al autor que postearon en el tema
   - Título (link al tema)
   - Fecha del último post
   - Acciones: **Completada** y **Cancelada** en los tres grupos
6. Sección de estadísticas, al final de la página: una tabla con una fila
   por perfil y columnas Completadas / Canceladas (más un total).
7. Selector Tarjetas/Compacta junto al filtro. La preferencia se conserva en
   `localStorage`; ambos modos reutilizan el mismo HTML y las mismas acciones.
8. Botón Actividad del equipo que despliega una tabla con total, completadas,
   canceladas y desglose dinámico por perfil. Solo usa resoluciones registradas
   por este sistema; no atribuye archivos históricos al último posteador.

### 7.3 Confirmaciones

Completada/Cancelada llevan `onclick="return confirm(...)"`; son
irreversibles desde esta pantalla (mover el tema no tiene un botón de deshacer).

## 8. Contrato HTTP

No hay API/AJAX nueva — todo es la misma página con acciones GET (filtro)
y POST (mutaciones), como el resto de `/op/staff/`.

| Método | Acción | Resultado |
|---|---|---|
| `GET` | página, opcionalmente `?perfil=<fid>` | Listado filtrado + estadísticas |
| `POST` | `accion=completar&tid=X` | Mueve el tema al FID `completadas` de su perfil, cierra |
| `POST` | `accion=cancelar&tid=X` | Mueve el tema al FID `canceladas` de su perfil, cierra |

Cada `POST` exige `verify_post_check($mybb->get_input('my_post_key'))`,
`tid` casteado a entero, y confirma `t.fid IN
(array_keys(OP_SOLICITUDES_CREACION_PERFILES))` antes de mover nada (ver
§10).

## 9. Validaciones

1. Permisos de staff — primero, antes de cualquier query.
2. `my_post_key` en todo POST.
3. `tid` entero positivo.
4. El tema debe existir y su `fid` actual debe estar en
   `OP_SOLICITUDES_CREACION_PERFILES` (para completar/cancelar) — evita
   que alguien arme la URL a mano con el `tid` de un tema de cualquier
   otra parte del foro y lo mueva de lugar.
5. El FID destino sale siempre del mapa de perfiles según el `fid` actual
   del tema, nunca de un valor recibido por POST/GET.
6. Salida escapada con `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` para
   `subject`, `username` de autor y de cada moderador listado — mismo
   fix que ya se aplicó en `tecnicas_creacion.php`.

## 10. Instalación y ciclo de vida del plugin

`op_solicitudes_creacion_install()`:
- registra o actualiza la plantilla `staff_solicitudes_creacion` desde el
  archivo versionado del tema OPG;
- crea `op_solicitudes_creacion_resoluciones` si no existe.

`op_solicitudes_creacion_activate()`:
- asegura que la plantilla y la tabla de auditoría estén instaladas;
- no modifica `header.html`.

`op_solicitudes_creacion_deactivate()`:
- deshabilita el hook sin modificar `header.html` ni eliminar la plantilla.

`op_solicitudes_creacion_uninstall()`:
- retira la plantilla y la tabla de auditoría propias del plugin;
- no modifica el header.

## 11. Seguridad

- Gate de permisos primero, como en toda la carpeta `/op/staff/`.
- CSRF (`my_post_key`) en Completada/Cancelada.
- FID destino resuelto server-side desde el mapa fijo, nunca desde input
  del cliente.
- `tid` casteado a entero antes de cualquier interpolación SQL.
- Confirmar que el tema pertenece a uno de los 5 FIDs de perfil antes de
  moverlo — cierra la puerta a mover temas ajenos al sistema de
  solicitudes.
- Salida HTML escapada (título, usuario autor, usuarios moderadores).

## 12. Casos límite

- **Petición sin ningún post de staff todavía**: no tiene moderadores que
  listar — la sección "Moderadores que respondieron" queda vacía o no se
  muestra.
- **Varios moderadores postearon en la misma petición**: se listan todos,
  sin duplicados (`DISTINCT` por `tid, uid`).
- **Staff completa/cancela una petición del grupo 3** ("a la espera del
  usuario"): las acciones Completada/Cancelada quedan disponibles ahí
  también; no hace falta esperar a que el usuario responda.
- **Tema movido/cerrado por fuera de esta página** (ej. un mod lo mueve a
  mano desde el ACP): en la siguiente carga simplemente deja de aparecer
  en pendientes y empieza a contar en las estadísticas si cayó en un FID
  de completadas/canceladas — no hace falta sincronizar nada.
- **Exclusiones heredadas por TID**: no se conserva `tid != 97` ni otra
  excepción sin una regla funcional confirmada. Todo tema visible dentro
  de los cinco FIDs de perfil se procesa con las mismas reglas.
- **Temas archivados antes del registro de actividad**: cuentan en el histórico por perfil,
  pero no se atribuyen a ningún moderador porque no existe evidencia fiable
  de quién pulsó la resolución.

## 13. Estrategia de pruebas

- **Migración de conteo**: confirmar que el nuevo contador del header
  (`t.fid IN (369,...)`) devuelve resultados donde el viejo
  (`f.fid = 8`) devolvía cero.
- **3 grupos por perfil**: crear peticiones de prueba en cada uno de los 5
  FIDs y confirmar que aparecen en el grupo correcto.
- **Completada/Cancelada**: confirmar que el tema termina en el FID
  correcto según su perfil de origen (ej. una de Técnicas completada cae
  en 447, no en 452).
- **Intento de mover un tema ajeno**: armar un POST con `tid` de un tema
  fuera de los 5 FIDs y confirmar que la validación del §10 lo rechaza.
- **Moderadores listados**: petición con 0, 1 y varios posts de staff;
  confirmar que la lista no incluye al autor del tema ni duplicados.
- **Estadísticas por perfil**: confirmar que los totales de la tabla
  coinciden con un `COUNT(*)` manual por FID.
- **Permisos**: usuario sin rol de staff no accede a la página ni puede
  disparar las acciones POST directamente.

## 14. Fases de implementación

### Fase 1: Plugin y contador de header
- `inc/plugins/op_solicitudes_creacion.php` + `functions.php`.
- Mapa de perfiles, query agregada de conteo (§6.1).
- Hook `global_intermediate`; la línea que consume sus variables se agrega
  manualmente a `header.html`.

### Fase 2: Página funcional
- `/op/staff/solicitudes_creacion.php`: 3 grupos, filtro por perfil,
  listado generalizado a los 5 FIDs.
- Acciones Completada/Cancelada con las validaciones del §10.

### Fase 3: Moderadores por petición
- Query de posteadores (§6.2) y su render en cada tarjeta.

### Fase 4: Estadísticas
- Sección de completadas/canceladas por perfil (§6.3) al final de la
  página.

### Fase 5: Auditoría y actividad
- Tabla de resoluciones instalada por el plugin.
- Registro del moderador dentro de la acción POST.
- Panel Resoluciones por moderador con desglose por perfil.

## 15. Fuera de alcance

- Deshacer una petición ya marcada Completada/Cancelada (mover manual
  desde el ACP si hace falta corregir un error).
- Notificaciones (Discord, PM) cuando una petición cambia de estado.
- Editar el mapa de perfiles/FIDs desde el ACP — queda como constante en
  código; cambiarlo es un cambio de código, no de configuración.
- Un 6to perfil o reestructuración de la categoría padre (fid=8) — el
  diseño lo soporta agregando una línea al mapa, pero no es parte de esta
  entrega.
