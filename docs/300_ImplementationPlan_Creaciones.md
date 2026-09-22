# Solicitudes de creación: plan de implementación

> Requisitos: [100_Requirements_Creaciones.md](100_Requirements_Creaciones.md)
>
> Diseño: [200_DesignPlan_Creaciones.md](200_DesignPlan_Creaciones.md)

## 1. Objetivo de este documento

Este plan convierte el diseño aprobado en tareas de código ordenadas y
verificables. Define archivos, funciones, consultas, contrato HTTP y
pruebas, pero no implementa todavía nada.

La implementación se considera terminada cuando:

- `/op/staff/solicitudes_creacion.php` lista las peticiones pendientes de
  los 5 perfiles (no solo Técnicas), agrupadas en los 3 grupos ya
  conocidos (sin respuesta / usuario respondió / a la espera del usuario);
- cada petición listada muestra también los moderadores que ya postearon
  en el tema, no solo el usuario que lo abrió;
- Completada y Cancelada mueven el tema al subforo correcto según su
  perfil de origen, resuelto siempre en servidor, y están disponibles en
  los tres grupos sin depender del autor del último post;
- la sección de estadísticas muestra completadas/canceladas por perfil,
  dentro de la misma página;
- el contador del header (`#peticiones_staff`) suma los 5 perfiles, vía un
  plugin nuevo — sin una sola línea tocada en `global.php`;
- no existe ninguna consulta N+1 por tarjeta listada;
- todas las mutaciones validan permisos, `post_key`, y que el `tid`
  pertenece a uno de los 5 FIDs de perfil antes de mover nada.
- cada resolución nueva registra moderador, acción y perfil para alimentar
  el resumen desplegable de actividad de moderación.

## 2. Archivos

### 2.1 Crear

| Archivo | Responsabilidad |
|---|---|
| `inc/plugins/op_solicitudes_creacion.php` | Ciclo de vida del plugin, hook de header |
| `inc/plugins/op_solicitudes_creacion/functions.php` | Mapa de perfiles, queries compartidas, resolución de FID destino |
| `op/staff/solicitudes_creacion.php` | Controlador de página: listado, filtro, acciones POST, estadísticas |
| `templates/One_Piece_Gaiden_Templates/staff_solicitudes_creacion.html` | Plantilla de la página |

### 2.2 Modificar

| Archivo | Cambio |
|---|---|
| `templates/One_Piece_Gaiden_Templates/header.html` | Reemplazar manualmente la línea de `Creaciones` por `Solicitudes: {$op_solicitudes_creacion_pendientes}`, enlazando a `solicitudes_creacion.php` |

### 2.3 No se toca

- `global.php` — decisión ya tomada, no se agrega ni se borra nada ahí.
  `$g_total_creaciones_sin_moderar` / `$g_total_creaciones_pendientes`
  quedan calculados pero sin ningún consumidor una vez migrado el header;
  limpiarlos es una tarea de código muerto aparte, no parte de esta
  entrega.
- `op/staff/tecnicas_creacion.php` — queda intacto como está. No se
  redirige ni se borra en esta entrega; es candidato a deprecar en un
  commit de limpieza posterior, una vez que `solicitudes_creacion.php`
  esté validado en producción.
- El plugin crea `op_solicitudes_creacion_resoluciones`; no requiere una
  migración SQL manual.

## 3. Convenciones de implementación

- Prefijo PHP: `op_solicitudes_creacion_`.
- Identidad de staff: `is_mod($uid) || is_staff($uid) || is_user($uid)`,
  chequeado primero en toda ruta de entrada (página y hook).
- IDs (`tid`): enteros positivos antes de entrar en SQL.
- Texto de salida: `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- El motor (`functions.php`) devuelve arrays de datos, no concatena HTML.
- El controlador (`solicitudes_creacion.php`) contiene los helpers de
  presentación (tarjetas), siguiendo el mismo patrón que ya usa
  `tecnicas_creacion.php` con `tc_item()`.
- El FID destino de una mutación (Completada/Cancelada) nunca sale de
  input del cliente — siempre se resuelve en servidor contra el mapa de
  perfiles, a partir del `fid` actual del tema.

Constante prevista (única fuente de verdad de perfiles/FIDs — ver §5):

```php
const OP_SOLICITUDES_CREACION_CATEGORIA_FID = 8;

const OP_SOLICITUDES_CREACION_PERFILES = [
    369 => ['nombre' => 'Técnicas',          'completadas' => 447, 'canceladas' => 446],
    373 => ['nombre' => 'Akuma no Mi',        'completadas' => 448, 'canceladas' => 449],
    370 => ['nombre' => 'Estilos y Pasivas',  'completadas' => 450, 'canceladas' => 451],
    371 => ['nombre' => 'Objetos y Crafteos', 'completadas' => 452, 'canceladas' => 453],
    372 => ['nombre' => 'Mascotas y NPC',     'completadas' => 454, 'canceladas' => 455],
];
```

## 4. Tarea 1: esqueleto y ciclo de vida del plugin

**Archivo:** `inc/plugins/op_solicitudes_creacion.php`

### 4.1 Registro

```php
if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

require_once MYBB_ROOT . 'inc/plugins/op_solicitudes_creacion/functions.php';

$plugins->add_hook('global_intermediate', 'op_solicitudes_creacion_hook_header');
```

Funciones de ciclo de vida:

- `op_solicitudes_creacion_info()`;
- `op_solicitudes_creacion_is_installed()`;
- `op_solicitudes_creacion_install()`;
- `op_solicitudes_creacion_uninstall()`;
- `op_solicitudes_creacion_activate()`;
- `op_solicitudes_creacion_deactivate()`.

`install()` y `activate()` crean la tabla de auditoría si no existe y
registran la plantilla. `uninstall()` elimina ambos recursos propios;
`deactivate()` conserva el historial.

### 4.2 Instalación de la plantilla

`install()` y `activate()` leen
`templates/One_Piece_Gaiden_Templates/staff_solicitudes_creacion.html` y
registran o actualizan `staff_solicitudes_creacion` en el set maestro y el
set OPG, siguiendo el cargador de plantillas de `op_bitacora`.

`activate()`, `deactivate()` y `uninstall()` no modifican `header.html`.
La referencia a las variables del contador se mantiene manualmente en el
archivo versionado y en la plantilla sincronizada del tema.

### 4.3 Verificación

```bash
php -l inc/plugins/op_solicitudes_creacion.php
php -l inc/plugins/op_solicitudes_creacion/functions.php
```

En MyBB:

1. instalar y activar;
2. confirmar que `staff_solicitudes_creacion` fue instalada;
3. desactivar y reactivar sin modificar `header.html`;
4. confirmar que `tecnicas_creacion.php` sigue funcionando sin cambios
   (no se tocó).

## 5. Tarea 2: motor compartido (`functions.php`)

**Archivo:** `inc/plugins/op_solicitudes_creacion/functions.php`

### 5.1 Mapa de perfiles y helpers de resolución

```php
op_solicitudes_creacion_fids_perfiles(): array               // array_keys(OP_SOLICITUDES_CREACION_PERFILES)
op_solicitudes_creacion_perfil_de_fid(int $fid): ?array       // OP_SOLICITUDES_CREACION_PERFILES[$fid] ?? null
op_solicitudes_creacion_fid_destino(int $fidOrigen, string $accion): ?int // 'completar'|'cancelar' -> fid o null si $fidOrigen no es de perfil
```

`op_solicitudes_creacion_fid_destino()` es el único punto donde se decide
a qué FID se mueve un tema — todo el resto del código (hook, página,
acciones) pasa por acá. Esto evita que la lógica de "qué FID le
corresponde a este perfil" quede duplicada entre la página y el motor.

### 5.2 Conteo agregado (para el header)

```php
op_solicitudes_creacion_contar_pendientes(): array // ['sin_responder' => int, 'reabiertas' => int]
```

Query única con `SUM(CASE ...)` (ver diseño §6.1):

```sql
SELECT
    SUM(CASE WHEN replies = 0 THEN 1 ELSE 0 END) AS sin_responder,
    SUM(CASE WHEN replies > 0 THEN 1 ELSE 0 END) AS reabiertas
FROM {prefix}threads
WHERE fid IN (<fids de perfil>)
  AND closed = 0
  AND uid = lastposteruid
  AND visible = 1
```

No se porta la excepción heredada `tid != 97`: cualquier tema visible que
esté en uno de los cinco FIDs de perfil debe seguir las mismas reglas.

### 5.3 Listado de pendientes (para la página)

```php
op_solicitudes_creacion_listar_pendientes(?int $filtroFid = null): array
```

Devuelve las 3 listas (`sin_respuesta`, `reabiertas`, `esperando_usuario`),
cada tema con: `tid`, `subject`, `fid` (perfil), `uid` (autor), `username`
(autor), `lastpost`. Reutiliza las 3 queries de `tecnicas_creacion.php`
(`print_tecnicas_sin_contestar()`, `print_tecnicas_a_moderar()`,
`print_tecnicas_no_moderar()`), cambiando `f.fid = 8` por `t.fid IN
(<fids de perfil>)` (más `AND t.fid = <filtroFid>` si hay filtro por
perfil) — y agregando `f.fid`/nombre de perfil al `SELECT` para poder
mostrarlo en cada tarjeta.

### 5.4 Moderadores por tema (requisito #7)

```php
op_solicitudes_creacion_cargar_posteadores(array $tids): array // ['tid' => [['uid'=>, 'username'=>], ...]]
```

Un solo query para todos los `$tids` de la página actual (ver diseño
§6.2):

```sql
SELECT DISTINCT p.tid, p.uid, p.username
FROM {prefix}posts AS p
WHERE p.tid IN (<tids>)
  AND p.uid != 0
  AND p.visible = 1
```

El llamador descarta, por tema, el `uid` que coincide con el autor del
thread — esto se hace en PHP al armar el array de salida, no en SQL, para
no repetir el `uid` del autor por parámetro en la query.

### 5.5 Estadísticas por perfil

```php
op_solicitudes_creacion_estadisticas(): array // [$fidPerfil => ['completadas' => int, 'canceladas' => int]]
```

Un query (ver diseño §6.3):

```sql
SELECT fid, COUNT(*) AS total
FROM {prefix}threads
WHERE fid IN (<10 fids de completadas+canceladas>)
GROUP BY fid
```

y en PHP se reparte contra `OP_SOLICITUDES_CREACION_PERFILES` cruzando
cada `completadas`/`canceladas` con el resultado (0 si no aparece en el
`GROUP BY`).

La página también llama a
`op_solicitudes_creacion_distribucion_pendientes()`, que agrupa por FID los
temas abiertos y visibles donde `uid = lastposteruid`, separando mediante
sumas condicionales los que tienen `replies = 0` y `replies > 0`. Devuelve
siempre las cinco claves del mapa, con ceros cuando un perfil no tiene
moderaciones pendientes, y no se limita por el filtro visual de tarjetas.

### 5.6 Mutación: mover una petición

```php
op_solicitudes_creacion_resolver(int $tid, string $accion, int $moderadorUid): array // ['ok'=>bool, 'code'=>string]
```

`$accion` es `'completar'` o `'cancelar'`. Secuencia:

1. cargar `threads.fid` actual del `tid`;
2. exigir que el `fid` actual esté
   en `OP_SOLICITUDES_CREACION_PERFILES` (si no, `code = 'perfil_invalido'`
   y no se mueve nada — ver validación §10 del diseño);
3. resolver el FID destino con `op_solicitudes_creacion_fid_destino()`;
4. `UPDATE threads SET fid = <destino>, closed = 1 WHERE tid = <tid> AND
   fid IN (<fids de perfil>)` — el `AND fid IN (...)` en el propio
   `UPDATE` es la protección real contra mover un tema ajeno, no solo la
   validación previa en PHP (defensa en profundidad, ante una carrera
   entre el paso 1 y el `UPDATE`);
5. registrar o actualizar la auditoría del TID con perfil original, acción,
   UID del moderador y fecha;
6. devolver `ok=true` solo si `affected_rows > 0`.

## 6. Tarea 3: hook de header

**Archivo:** `inc/plugins/op_solicitudes_creacion.php`

```php
function op_solicitudes_creacion_hook_header()
{
    global $mybb, $op_solicitudes_creacion_pendientes;
    $op_solicitudes_creacion_pendientes = 0;

    $uid = (int)($mybb->user['uid'] ?? 0);
    if ($uid <= 0 || !(is_staff($uid) || is_mod($uid) || is_user($uid))) {
        return;
    }

    $conteo = op_solicitudes_creacion_contar_pendientes();
    $op_solicitudes_creacion_pendientes = (int)$conteo['sin_responder']
        + (int)$conteo['reabiertas'];
}
```

Salida temprana para invitados y usuarios sin rol de staff, igual que ya
hace el bloque `is_staff()` de `global.php` hoy para el resto de
contadores de `#peticiones_staff`.

### Verificación

1. como usuario normal: el header no muestra la línea de Solicitudes (la
   plantilla ya la envuelve en `<if $g_is_staff then>`, no hace falta
   lógica extra acá);
2. como staff: el número coincide con la suma manual de ambos estados sobre
   los 5 FIDs;
3. crear una petición de prueba en cada uno de los 5 perfiles y confirmar
   que el contador sube en los 5 casos (no solo en Técnicas, que era el
   bug original).

## 7. Tarea 4: página `/op/staff/solicitudes_creacion.php`

### 7.1 Bootstrap

```php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'solicitudes_creacion.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";
require_once MYBB_ROOT . 'inc/plugins/op_solicitudes_creacion/functions.php';

global $templates, $mybb, $db;
$uid = (int) $mybb->user['uid'];

if (!is_mod($uid) && !is_staff($uid) && !is_user($uid)) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}
```

Gate de permisos primero, mismo patrón que `tecnicas_creacion.php`.

### 7.2 Acciones POST

```php
$accion = $mybb->get_input('accion', MyBB::INPUT_STRING);
$tid_input = (int) $mybb->get_input('tid', MyBB::INPUT_INT);

if (in_array($accion, ['completar', 'cancelar'], true) && $tid_input > 0) {
    verify_post_check($mybb->get_input('my_post_key'));
    $resultado = op_solicitudes_creacion_resolver($tid_input, $accion, $uid);
    header('Location: /op/staff/solicitudes_creacion.php' . ($resultado['ok'] ? '' : '?error=' . rawurlencode($resultado['code'])));
    exit;
}
```

Post/Redirect/Get, igual que el resto de `/op/staff/`. Si `$resultado['ok']`
es `false`, no hace falta un sistema de mensajes nuevo — un query string
`?error=` simple alcanza para esta pantalla, siguiendo el nivel de
sofisticación del resto de estas páginas.

### 7.3 GET: filtro y listado

```php
$filtro_fid = (int) $mybb->get_input('perfil', MyBB::INPUT_INT);
$filtro_fid = array_key_exists($filtro_fid, OP_SOLICITUDES_CREACION_PERFILES) ? $filtro_fid : null;

$pendientes = op_solicitudes_creacion_listar_pendientes($filtro_fid);
$todos_los_tids = array_column(
    array_merge($pendientes['sin_respuesta'], $pendientes['reabiertas'], $pendientes['esperando_usuario']),
    'tid'
);
$posteadores = op_solicitudes_creacion_cargar_posteadores($todos_los_tids);
$distribucion_pendientes = op_solicitudes_creacion_distribucion_pendientes();
$estadisticas = op_solicitudes_creacion_estadisticas();
$post_key = generate_post_check();
```

Validar `$filtro_fid` contra el mapa (no aceptar cualquier entero como
filtro) evita queries con un FID arbitrario que no es de perfil.

### 7.4 Render de tarjetas

Extender `tc_item()` (renombrado `sc_item()` en el nuevo archivo, para no
importar la función vieja de `tecnicas_creacion.php`) para que reciba
también la lista de posteadores del tema y el nombre del perfil, y
agregue:

- el nombre del perfil (badge o texto);
- la lista de moderadores que ya postearon (nombre + link a
  `/op/personaje.php?uid=`), o "Nadie respondió todavía" si está vacía;
- botones Completada/Cancelada en los tres grupos, sin importar quién hizo
  el último post. Cada acción incluye `my_post_key` y `confirm()`.

### 7.5 Sección de estadísticas

Tabla simple, una fila por perfil, columnas Completadas / Canceladas /
Total — iterando `OP_SOLICITUDES_CREACION_PERFILES` cruzado con
`$estadisticas`.

## 8. Tarea 5: plantilla `staff_solicitudes_creacion.html`

Sigue el mismo patrón visual que `staff_tecnicas_creacion.html` (barras
`.barra-op`/`.barra-espacio-op`, sin necesidad de HTMX/Alpine — esta
página es 100% server-rendered con Post/Redirect/Get, igual que el resto
de `/op/staff/`, a diferencia de `op_bitacora.html` que sí los usa).

Composición (ver diseño §7.2):

1. Título + descripción.
2. `<select>` de filtro por perfil (GET, recarga la página).
3. Tabla global de moderaciones pendientes por los cinco perfiles.
4. Tres secciones de tarjetas.
5. Tabla de estadísticas al final.
6. Selector Tarjetas/Compacta persistido en `localStorage`. El modo compacto
   reorganiza las tarjetas como filas mediante una clase CSS en el contenedor,
   sin duplicar peticiones ni formularios.
7. Botón Actividad del equipo que despliega el resumen registrado, con columnas
   Total, Completadas, Canceladas y una columna por perfil.

El plugin instala y actualiza esta plantilla desde el archivo versionado,
siguiendo el mecanismo de `op_bitacora`. No requiere creación manual desde
el Admin CP.

## 9. Tarea 6: `header.html`

Reemplazar manualmente la entrada actual de Creaciones por el enlace a
`/op/staff/solicitudes_creacion.php` y las variables
`{$op_solicitudes_creacion_pendientes}`. Ninguna función del ciclo de vida
del plugin debe editar esta plantilla.

## 10. Tarea 7: pruebas

### 10.1 Comprobaciones estáticas

```bash
php -l inc/plugins/op_solicitudes_creacion.php
php -l inc/plugins/op_solicitudes_creacion/functions.php
php -l op/staff/solicitudes_creacion.php
git diff --check
```

Buscar accidentalmente FIDs hardcodeados sueltos fuera del mapa central:

```bash
rg -n "fid.?=.?8\b|fid.?=.?369|fid.?=.?370|fid.?=.?371|fid.?=.?372|fid.?=.?373" \
  inc/plugins/op_solicitudes_creacion.php \
  inc/plugins/op_solicitudes_creacion/functions.php \
  op/staff/solicitudes_creacion.php
```

Cualquier resultado fuera de la definición de
`OP_SOLICITUDES_CREACION_PERFILES` es una señal de que un FID se coló
hardcodeado en vez de leerse del mapa.

### 10.2 Matriz funcional mínima

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | Crear tema de prueba en cada uno de los 5 FIDs de perfil | Los 5 aparecen en "Sin respuesta de moderación" |
| 2 | Staff responde a uno | Pasa a "A la espera del usuario" en ese perfil |
| 3 | El usuario vuelve a postear en ese tema | Pasa a "El usuario respondió, a la espera de moderación" |
| 4 | Completar la petición del paso 3 | Termina en el FID `completadas` de su perfil, cerrado |
| 5 | Cancelar otra distinta | Termina en el FID `canceladas` de su perfil, cerrado |
| 6 | Completar o cancelar una del grupo "a la espera del usuario" | Se archiva en el FID correspondiente de su perfil |
| 7 | Filtrar por un perfil específico | Solo aparecen temas de ese `fid` |
| 8 | Ver estadísticas | Los totales de completadas/canceladas coinciden con el paso 4 y 5 |
| 9 | Ver una petición sin respuesta de staff | Sección de moderadores vacía |
| 10 | Ver una con 2 moderadores distintos posteando | Ambos listados, sin duplicados, sin incluir al autor |
| 11 | Resolver solicitudes con dos cuentas de staff | La tabla atribuye cada acción a quien pulsó el botón y desglosa el perfil correcto |

### 10.3 Casos de seguridad

- POST sin `my_post_key`;
- `tid` de un tema fuera de los 5 FIDs de perfil (ej. un tema cualquiera
  del foro) en `accion=completar` — debe rechazarse, no moverlo;
- `tid` inexistente;
- usuario sin rol de staff intentando acceder a la página o disparar el
  POST directamente;
- filtro `perfil` con un FID que no está en el mapa (ej. `perfil=8` o
  cualquier otro forum del sitio) — debe ignorarse, no filtrar por un FID
  arbitrario.

### 10.4 Migración del contador roto

- confirmar en un ambiente de prueba que, antes del cambio, el contador
  viejo (`f.fid = 8`) efectivamente da 0 con peticiones reales existentes
  en 369-373;
- confirmar que, después del cambio, el nuevo contador las cuenta.

## 11. Tarea 8: despliegue

### 11.1 Antes de subir

1. revisar diff completo;
2. ejecutar `php -l` sobre los 3 archivos nuevos y `git diff --check`;
3. sincronizar manualmente la línea de Solicitudes de `header.html` y
   respaldar la plantilla de producción antes del cambio;
4. confirmar los 15 FIDs contra la base de producción una vez más
   inmediatamente antes de desplegar (ya fueron confirmados por el dueño
   del proyecto, pero son la pieza más frágil de todo el plan si cambian
   entretanto).

### 11.2 Orden de despliegue

1. subir plugin y `functions.php`;
2. subir página y plantilla;
3. sincronizar manualmente `header.html` con la entrada de Solicitudes;
4. reactivar el plugin desde Admin CP; instala o actualiza la plantilla y
   crea la tabla de auditoría, pero no modifica el header;
5. probar la página con una cuenta de staff de prueba, sin depender
   todavía del contador del header;
6. verificar que el contador del header aparece y coincide con la página;
7. dejar `tecnicas_creacion.php` accesible en paralelo un tiempo, por si
   hace falta comparar comportamiento — no se borra en este despliegue.

### 11.3 Rollback

1. desactivar el plugin; `header.html` permanece intacto y su entrada debe
   revertirse manualmente si también se desea retirar el enlace;
2. si se alcanzó a mover algún tema por error, corregir el `fid` a mano
   desde el ACP — no hay operación de deshacer automática (ver "Fuera de
   alcance" del diseño);
3. `tecnicas_creacion.php` sigue disponible como estaba, sin verse
   afectado por el rollback.

## 12. Orden recomendado de commits

1. `Add solicitudes_creacion profile map and shared query engine`
2. `Add global_intermediate hook and header counter for solicitudes_creacion`
3. `Add /op/staff/solicitudes_creacion.php with pending list and poster names`
4. `Add Completada/Cancelada actions with server-side destination resolution`
5. `Add per-profile completed/cancelled statistics section`
6. `Add moderator resolution audit and activity summary`

Cada commit debe pasar `php -l` y dejar la página en un estado coherente.

## 13. Definición de terminado por fase

### Fase 1: plugin y contador de header

- plugin instalable, activable, desactivable;
- plantilla `staff_solicitudes_creacion` instalada por el plugin;
- línea del header mantenida manualmente, sin mutaciones del plugin;
- contador correcto sobre los 5 FIDs, verificado contra el bug del
  contador viejo (§10.4).

### Fase 2: página funcional

- 3 grupos generalizados a los 5 perfiles;
- filtro por perfil;
- Completada/Cancelada con validación de pertenencia al mapa de
  perfiles;
- acciones principales utilizables sin JavaScript mediante Post/Redirect/Get;
  los selectores visuales y el panel de actividad usan JavaScript nativo.

### Fase 3: moderadores por petición

- lista de posteadores por tema sin consultas N+1;
- excluye al autor, sin duplicados.

### Fase 4: estadísticas

- tabla de completadas/canceladas por perfil, coincidiendo con
  `COUNT(*)` manual.

### Fase 5: actividad de moderadores

- tabla de auditoría creada por el plugin;
- cada resolución guarda el UID del moderador;
- tabla desplegable con acciones y perfiles desglosados.
