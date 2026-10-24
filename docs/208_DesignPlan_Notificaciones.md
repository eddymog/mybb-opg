# Notificaciones: plan de diseño

> Depende de:
> [108_Requirements_Notificaciones.md](108_Requirements_Notificaciones.md)
>
> **Estado: por implementar.** Este documento describe la arquitectura y el
> orden de construcción; no implica que las tablas o archivos existan aún.

## 1. Objetivo

Reunir en un solo sistema las notificaciones del foro: una librería de envío
(`opg_notificar`), una página de lectura (`/op/notificaciones.php`) y un contador
de no leídas en el header que cuesta, como máximo, una consulta por clave
primaria.

Toma de la Bitácora el contador cacheado del header y la inyección mediante
hook de plugin. Toma del Tablón de aventuras la clave de evento única, el panel
con pendientes e historial y los envoltorios que ya usan los llamadores.

## 2. Decisiones técnicas

- **Plugin de MyBB** (`opg_notificaciones.php`) para el hook del header y la
  instalación de tablas; no se edita `global.php`, siguiendo el patrón de
  `op_bitacora.php`.
- **Contador materializado.** Una fila por usuario con las no leídas. Se
  actualiza en la misma operación que escribe o lee la notificación; el header
  solo hace un `SELECT` por clave primaria. No hay TTL de caché que provoque
  retrasos: el valor es siempre el real, salvo desfase, que se autorepara.
- **Todo en InnoDB**, para que la notificación y el contador puedan participar
  en la transacción de la acción que los origina.
- **Un solo punto de entrada** para crear avisos: `opg_notificar()`. Ningún
  módulo hace `INSERT` directo en la tabla.
- **Catálogo de tipos en PHP** (arreglo declarativo), no en base de datos. Añadir
  un tipo es añadir una entrada y no requiere migración.
- **Sin uniones al listar.** Los nombres necesarios para el texto se guardan en
  `datos_json` al crear el aviso.
- **El fallo de un aviso no rompe la acción.** `opg_notificar()` captura sus
  errores, los registra y devuelve `false`.
- **Sin dependencia de `global.php` para la lógica.** La librería se carga con
  `require_once` desde el plugin y desde las páginas que la necesiten.
- **La Bitácora conserva su estado de turno** (`debes_responder`, `esperando`)
  y su historial de actividad. Solo sus eventos pasan a notificaciones.

## 3. Archivos

| Archivo | Responsabilidad | Estado |
|---|---|---|
| `inc/plugins/opg_notificaciones.php` | Instalación de tablas, hook `global_intermediate` del header | Nuevo |
| `inc/plugins/lib/notificaciones.php` | Envío, agrupación, lectura, contador, limpieza | Nuevo |
| `inc/plugins/lib/notificaciones_catalogo.php` | Tipos, categorías, orígenes, textos y enlaces | Nuevo |
| `inc/plugins/lib/notificaciones_vistas.php` | HTML de la lista, filas y filtros | Nuevo |
| `op/notificaciones.php` | Controlador de la página y acciones HTMX | Nuevo |
| `inc/tasks/notificaciones.php` | Tarea diaria de retención y reconciliación | Nuevo |
| `templates/One_Piece_Gaiden_Templates/op_notificaciones.html` | Estructura de la página | Nuevo |
| `templates/One_Piece_Gaiden_Templates/op_notificaciones_header.html` | Campana del header | Nuevo |
| `templates/One_Piece_Gaiden_Templates/header.html` | Variable `{$op_notificaciones_header}` junto a `{$op_temas_header}` | Modificar |
| `docs/notificaciones_migration.sql` | Tablas, índices y tarea programada, idempotente | Nuevo |
| `op/staff/migrar_notificaciones.php` | Copia por lotes de las notificaciones del Tablón | Nuevo |
| `inc/plugins/lib/aventuras.php` | `opg_av_notificar*` pasan a envoltorios | Modificar |
| `inc/plugins/op_bitacora/functions.php` | Eventos de la Bitácora llaman a `opg_notificar` | Modificar |
| `tests/notificaciones/*.php` | Pruebas puras y con base de datos | Nuevo |

Los nombres de plantilla y la creación de tablas siguen las convenciones del
proyecto (`op_*`, `mybb_op_*`). Los `.html` de `templates/` son referencia hasta
importarlos a la base con `import_templates.php`.

## 4. Modelo de datos

```sql
CREATE TABLE IF NOT EXISTS `mybb_op_notificaciones` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid`          INT UNSIGNED NOT NULL,                 -- destinatario
  `tipo`         VARCHAR(48)  NOT NULL,                 -- clave del catálogo
  `categoria`    VARCHAR(24)  NOT NULL,                 -- copia del catálogo
  `origen`       VARCHAR(8)   NOT NULL,                 -- gestion | usuario | sistema
  `importancia`  VARCHAR(10)  NOT NULL DEFAULT 'normal', -- normal | importante (copia del catálogo)
  `agrupable`    TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- copia del catálogo
  `actor_uid`    INT UNSIGNED NOT NULL DEFAULT 0,
  `clave_evento` VARCHAR(128) NOT NULL,
  `url`          VARCHAR(255) NULL,
  `cantidad`     INT UNSIGNED NOT NULL DEFAULT 1,
  `datos_json`   TEXT NULL,
  `created_at`   INT UNSIGNED NOT NULL,
  `leida_en`     INT UNSIGNED NULL,
  `sent_at`      INT UNSIGNED NULL,                     -- reservado para envíos externos
  `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evento_destinatario` (`uid`, `clave_evento`),
  KEY `idx_uid_fecha` (`uid`, `created_at`, `id`),
  KEY `idx_uid_categoria` (`uid`, `categoria`, `created_at`, `id`),
  KEY `idx_uid_origen` (`uid`, `origen`, `created_at`, `id`),
  KEY `idx_uid_leida` (`uid`, `leida_en`, `created_at`, `id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mybb_op_notificaciones_contador` (
  `uid`         INT UNSIGNED NOT NULL,
  `sin_leer`    INT UNSIGNED NOT NULL DEFAULT 0,
  `actualizado` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Notas:

- `clave_evento` pasa a 128 caracteres (la de los requisitos era 96) para que
  quepa el sufijo `:leida:ID` de las agrupables.
- `created_at` de una agrupable se actualiza con cada evento nuevo; es la fecha
  de la última actividad y la que ordena la lista.
- `sent_at` y `attempts` no se usan en la primera fase. Se incluyen porque la
  tabla del Tablón ya los tiene y evitan una migración cuando se añadan envíos
  externos.
- El orden de la lista es por `created_at DESC, id DESC`. Así, una agrupable que
  recibe un evento nuevo sube al principio. Para ello se usan los índices
  `(uid, created_at, id)` y, con filtro, los equivalentes por categoría y origen.

## 5. Librería de envío

### 5.1 API pública

```php
opg_notificar($destinatarios, $tipo, $datos = array(), $opciones = array())
    // devuelve cuántas notificaciones nuevas creó (int) o false en error
opg_notif_contar($uid)                  // no leídas (con autoreparación)
opg_notif_listar($uid, $filtros, $cursor, $limite)  // cursor = (created_at, id)
opg_notif_marcar_vista($uid, $id)       // true si cambió de no leída a leída
opg_notif_marcar_todas($uid, $filtros)  // devuelve cuántas marcó
opg_notif_reconciliar($uid)             // recuenta y corrige el contador
```

Opciones de `opg_notificar`:

| Opción | Efecto |
|---|---|
| `actor` | `uid` de quien provoca el cambio (0 = sistema) |
| `clave` | Clave de evento. Obligatoria salvo en tipos agrupables, donde se genera a partir del tipo y de `agrupar_por` |
| `agrupar_por` | Valor que identifica el grupo (por ejemplo el `tid`) |
| `url` | Enlace interno. Si no es una ruta interna se descarta |
| `incluir_actor` | Si es `true`, el actor también recibe el aviso. Por defecto no |
| `silenciosa` | Reservada para acciones técnicas; no crea nada. Cada uso debe justificarse en el código |

### 5.2 Flujo de una notificación normal

1. Normalizar destinatarios: enteros mayores que cero, sin repetidos y sin el
   actor (salvo `incluir_actor`). Si no queda ninguno, devolver 0.
2. Resolver el tipo en el catálogo. Si no existe, registrar y devolver `false`.
3. Insertar con `INSERT IGNORE` (una sola sentencia con varias filas).
4. Para cada destinatario cuya fila se insertó de verdad, incrementar el
   contador. Como `INSERT IGNORE` no dice qué filas entraron, se hace así:
   - Si el lote es de un solo destinatario, `affected_rows` basta.
   - Si el lote es mayor, se vuelve a consultar por `clave_evento` y `created_at`
     el conjunto realmente nuevo (consulta por el índice único), y se incrementa
     solo esos.
5. Incremento atómico:

```sql
UPDATE mybb_op_notificaciones_contador
   SET sin_leer = sin_leer + 1, actualizado = :ahora
 WHERE uid = :uid;
```

Si el `UPDATE` no afecta ninguna fila (el contador no existe todavía), se crea
con un recuento, que ya incluye la notificación recién insertada:

```sql
INSERT INTO mybb_op_notificaciones_contador (uid, sin_leer, actualizado)
SELECT :uid, COUNT(*), :ahora FROM mybb_op_notificaciones
 WHERE uid = :uid AND leida_en IS NULL
ON DUPLICATE KEY UPDATE sin_leer = VALUES(sin_leer), actualizado = VALUES(actualizado);
```

### 5.3 Flujo de una notificación agrupable

Una sola sentencia por destinatario (o una multifila para un lote):

```sql
INSERT INTO mybb_op_notificaciones
  (uid, tipo, categoria, origen, agrupable, actor_uid, clave_evento, url,
   cantidad, datos_json, created_at)
VALUES (:uid, :tipo, :categoria, 'usuario', 1, :actor, 'bitacora:post:123', :url,
        1, :datos, :ahora)
ON DUPLICATE KEY UPDATE
  cantidad   = cantidad + 1,
  actor_uid  = VALUES(actor_uid),
  datos_json = VALUES(datos_json),
  created_at = VALUES(created_at);
```

La clave única `(uid, clave_evento)` hace que, mientras exista una fila no
leída con esa clave, el evento la actualice. El resultado de MySQL distingue los
casos: **1** fila afectada si se insertó, **2** si se actualizó. Solo en el
primero se incrementa el contador.

> Esto depende de que la conexión no use la bandera `CLIENT_FOUND_ROWS`. Se
> verifica con una prueba al instalar (ver apartado 13); si no se cumple, se usa
> la variante de `SELECT` + `INSERT`/`UPDATE` dentro de una transacción.

### 5.4 Marcar como vista

```sql
-- Una notificación
UPDATE mybb_op_notificaciones
   SET leida_en = :ahora,
       clave_evento = IF(agrupable = 1, CONCAT(clave_evento, ':leida:', id), clave_evento)
 WHERE id = :id AND uid = :uid AND leida_en IS NULL;
```

Si `affected_rows` es 1:

```sql
UPDATE mybb_op_notificaciones_contador
   SET sin_leer = GREATEST(sin_leer - 1, 0), actualizado = :ahora
 WHERE uid = :uid;
```

Si es 0 (ya estaba leída o no es suya), no se toca el contador. Eso hace que
pulsar **Visto** dos veces no descuente dos veces y que un usuario no pueda
marcar notificaciones ajenas.

**Marcar todas** usa el mismo `UPDATE` sin `id`, con el filtro de categoría u
origen si está activo, y resta el número de filas afectadas:

```sql
UPDATE mybb_op_notificaciones_contador
   SET sin_leer = GREATEST(sin_leer - :n, 0), actualizado = :ahora WHERE uid = :uid;
```

Ambas operaciones corren en una transacción corta para que fila y contador no
queden a medias.

### 5.5 Autoreparación

`opg_notif_contar($uid)`:

1. Lee `(sin_leer, actualizado)` por clave primaria.
2. Si la fila no existe, o `actualizado` tiene más de 24 horas, ejecuta
   `opg_notif_reconciliar($uid)`: `COUNT(*)` de las no leídas por el índice
   `(uid, leida_en, id)` y reescribe la fila.
3. Cachea el resultado en una variable estática de la petición.

La tarea diaria reconcilia además los contadores de usuarios con actividad
reciente, para que el desfase no dependa de que el usuario entre.

## 6. Catálogo

`notificaciones_catalogo.php` devuelve un arreglo por tipo:

```php
'entrega_rechazada' => array(
    'categoria' => 'aventuras',
    'origen'    => 'gestion',
    'icono'     => 'fa-rotate-left',
    'agrupable' => false,
    'texto'     => function ($d, $cantidad) { return 'Tu entrega de la solicitud #' . (int) $d['peticion_id'] . ' fue rechazada.'; },
    'url'       => function ($d) { return 'op/tablon_de_aventuras.php?id=' . (int) $d['peticion_id']; },
),
'bitacora_post' => array(
    'categoria' => 'temas',
    'origen'    => 'usuario',
    'icono'     => 'fa-comment',
    'agrupable' => true,
    'texto'     => function ($d, $cantidad) { /* "N publicaciones nuevas en Tema X" */ },
    'url'       => function ($d) { return 'showthread.php?tid=' . (int) $d['tid'] . '&action=newpost'; },
),
```

Reglas:

- El texto se construye siempre al mostrar, con los datos guardados. Lo que sale
  del catálogo se escapa antes de salir (`htmlspecialchars`), salvo el icono.
- Los `url` guardados en la fila prevalecen sobre la función del catálogo, para
  enlaces que no se pueden reconstruir.
- Un tipo desconocido se muestra con el texto "Actualización" y sin enlace.
- Las categorías son: `aventuras`, `ficha`, `recompensas`, `tecnicas`,
  `peticiones`, `temas`, `tripulacion`, `sistema`.

Tipos iniciales: los 13 del Tablón (`invitacion_nueva`, `candidatura_nueva`,
`propuesta_*`, `aventura_aprobada`, `aventura_denegada`, `narrador_salio`,
`entrega_rechazada`, `recompensas_entregadas`, `oferta_por_vencer`) y, de la
Bitácora, `bitacora_post`, `bitacora_ronda_iniciada`, `bitacora_narrador_asignado`,
`bitacora_narrador_cambiado` y `bitacora_narrador_retirado`. Los demás
orígenes del apartado 7 de los requisitos se añaden por fases (apartado 12).

## 7. Header

### 7.1 Inyección

`opg_notificaciones.php` registra:

```php
$plugins->add_hook('global_intermediate', 'opg_notificaciones_hook_header');
```

La función construye `$op_notificaciones_header` (una variable global de
plantilla) y sale sin consultar cuando `uid` es 0. La campana se coloca en
`header.html` junto a `{$op_temas_header}`, que es el bloque de la Bitácora, con
el criterio de convivir en ese espacio (decisión 4 de los requisitos). El diseño
visual concreto se resuelve al implementar, con prueba en móvil.

### 7.2 Costo por petición

- Invitados: 0 consultas.
- Usuarios: 1 consulta `SELECT sin_leer, actualizado ... WHERE uid = ?`.
- Resto de la petición: el valor queda en una variable estática; no se vuelve a
  consultar aunque lo pida otra parte de la página.
- Si falta la tabla (despliegue a medias), una comprobación estática con
  `table_exists` devuelve 0 y el header sale vacío.

### 7.3 Plantilla

`op_notificaciones_header.html` muestra un enlace a `/op/notificaciones.php`
con icono, una insignia con el número (`99+` desde 100, oculta en cero) y una
etiqueta accesible con el número exacto. No muestra textos de notificaciones.

## 8. Página `/op/notificaciones.php`

Sigue las convenciones HTMX de `tablon_de_aventuras.php` (acciones por
`?action=`, respuestas en fragmentos, `verify_post_check` en las que cambian
estado).

| Acción | Método | Efecto |
|---|---|---|
| (sin acción) | GET | Página completa: filtros, no leídas, historial |
| `listar` | GET | Fragmento de lista con filtros y cursor `antes` (fecha e id) |
| `mas` | GET | Siguiente página de 30 usando `antes=FECHA_ID` |
| `visto` | POST | Marca una notificación y devuelve la fila actualizada y el contador (`hx-swap-oob`) |
| `todas` | POST | Marca todas (o las del filtro) y devuelve la lista y el contador |

Detalles:

- **Consulta de la lista:** una sola, por `(uid, id)` o por los índices de
  filtro, con `LIMIT 31` para saber si hay más. Sin uniones.
- **Orden:** no leídas primero (por `created_at DESC`), después el historial.
  Se implementan como dos listas visibles (Pendientes e Historial reciente), igual
  que el panel Novedades del Tablón.
- **Cursor:** `antes` es el par `created_at` e `id` de la última fila mostrada del bloque (por ejemplo `1760000000_812`), porque el orden ya no es solo por `id`.
- **Filtros:** categoría y origen. Son parámetros GET; el contador del header no se
  recarga con el filtro.
- **Contador en la página:** tras `visto` o `todas`, el fragmento incluye el
  contador actualizado como `hx-swap-oob` sobre la campana.
- **Seguridad:** el `uid` sale siempre de la sesión. Solo se leen filas con ese
  `uid`. El modo espejo no tiene tratamiento especial: se comporta como el usuario.
- **Texto vacío:** "No tienes notificaciones".

## 9. Integración con los orígenes

### 9.1 Tablón de aventuras

- `opg_av_notificar($clave, $peticion_id, $uid, $actor, $tipo, $datos)` y
  `opg_av_notificar_partes(...)` mantienen su firma y llaman a `opg_notificar`.
  La `clave_evento` actual se conserva; `peticion_id` se guarda en `datos_json`.
- `opg_av_notificaciones_contar/listar/leer` leen de la tabla global filtrando
  por `categoria = 'aventuras'`.
- La acción `leer_notificacion` del Tablón llama a `opg_notif_marcar_vista`.
- Cuando alguien envía una entrega, `opg_ent_enviar` notifica a los demás
  participantes de la aventura. El staff no recibe notificación: sigue usando el
  contador de entregas pendientes del header.
- El panel Novedades de Mis aventuras se mantiene en la primera fase (decisión 3)
  y también actualiza el contador global.
- Migración: `op/staff/migrar_notificaciones.php` copia
  `mybb_op_aventuras_notificaciones` por lotes de 500 con `INSERT IGNORE`,
  mapeando `read_at` a `leida_en` y reconstruyendo `datos_json`. Es idempotente
  por la clave única; al terminar, reconcilia el contador de los usuarios
  afectados. La tabla antigua no se borra.

### 9.2 Bitácora

- Los eventos que la Bitácora registra hoy con `op_bitacora_registrar_evento()`
  (`post_publicado`, `ronda_narrada_iniciada`, `narrador_asignado`,
  `narrador_cambiado`, `narrador_retirado`) siguen escribiendo en
  `mybb_op_bitacora_eventos`, porque alimentan el Historial de actividad, que
  se conserva.
- Además llaman a `opg_notificar` con origen `usuario` para el destinatario que
  corresponda. El bucle de `op_bitacora_registrar_actividad_post` ya recorre los
  seguimientos de un tema; solo hay que seleccionar también `personaje_uid` y
  enviar el lote completo en una sola llamada.
- `post_publicado` usa el tipo agrupable `bitacora_post` con
  `agrupar_por = tid`, de modo que hay una notificación no leída por tema.
- `manual_turno` y `manual_al_dia` son acciones del propio usuario sobre su
  seguimiento; no generan notificación.
- El aviso de "novedad pendiente" y el cursor de lectura de la Bitácora
  (`op_bitacora_lecturas`) dejan de usarse para el header cuando la campana
  esté estable (fase 4). El estado de turno y los conteos propios no cambian.
- Para limitar el coste de un tema con muchos seguidores, el lote se divide en
  bloques de 200 destinatarios por sentencia.

### 9.3 Herramientas de gestión (futuro)

No forma parte de la primera entrega. Cuando se aborde:

Cada herramienta de la tabla del apartado 7 de los requisitos obtiene su tipo
de catálogo y una llamada a `opg_notificar` justo después de la escritura que
modifica algo del usuario, dentro de la misma transacción si la hay. Las
acciones masivas (`berries_masivo.php` y similares) envían una notificación
por destinatario con el resumen del cambio, no una por cada fila de la ficha.

## 10. Retención y mantenimiento

Tarea programada `notificaciones` (en `mybb_tasks`, diaria, de madrugada):

1. Elimina las notificaciones de más de 90 días, leídas o no, en lotes de 1000 con `LIMIT`.
2. Para cada usuario con más de 500 notificaciones, elimina las más antiguas,
   las leídas primero, hasta quedar en 500.
3. Reconcilia el contador de los usuarios afectados.
4. Reconcilia los contadores con `actualizado` de más de 24 horas y actividad
   reciente.

La limpieza nunca se ejecuta durante una petición del usuario.

## 11. Seguridad y fallos

- El `uid` de lectura y escritura sale de `$mybb->user['uid']`. Con cuenta
  alterna, es el personaje activo.
- Los enlaces guardados se validan al crear: solo rutas internas relativas o del
  mismo dominio; cualquier otra cosa se descarta.
- Todo texto de usuario guardado en `datos_json` se escapa al mostrar.
- Los datos guardados no incluyen información que el destinatario no pueda ver
  en el foro (por ejemplo, motivos internos del staff).
- `opg_notificar` envuelve su cuerpo en `try`/`catch`, registra el error con
  `error_log` y devuelve `false`; la acción original continúa.
- Si la acción original revierte su transacción, la notificación también, porque
  se inserta dentro de ella.

## 12. Orden de implementación

1. **Base.** SQL de tablas, plugin, librería de envío y lectura, catálogo con los
   tipos del Tablón. Pruebas puras y con base de datos.
2. **Header y página.** Campana en el header, `/op/notificaciones.php`, acciones
   HTMX y filtros. Comprobar el coste de una petición.
3. **Tablón.** Envoltorios de `opg_av_notificar*`, lectura desde la tabla global
   y migración de las notificaciones antiguas.
4. **Bitácora.** Eventos con agrupación y retirada del aviso de "novedad
   pendiente" del header de la Bitácora.
5. **Mantenimiento.** Tarea de retención y reconciliación.
6. **Herramientas de gestión (futuro, fuera de la primera entrega).** Conectar una
   a una las herramientas del apartado 7 de los requisitos (ficha, recompensas,
   técnicas y objetos, cofres, peticiones, tripulación, sistema). El sistema
   queda preparado: bastará añadir un tipo al catálogo y una llamada a
   `opg_notificar` en cada herramienta.

Cada fase se puede desplegar sola. Las fases 1 y 2 no cambian ningún
comportamiento existente.

## 13. Pruebas

Siguen el estilo de `tests/aventuras/` (scripts PHP con base de datos simulada
y, donde hace falta, con base real).

- **Puras:** normalización de destinatarios, filtro del actor, validación de
  enlaces, catálogo (tipo desconocido, texto escapado), clave de agrupables.
- **Con base de datos:**
  - Idempotencia: la misma `clave_evento` dos veces crea una fila y suma uno.
  - Agrupación: tres eventos seguidos crean una fila con `cantidad = 3` y
    suman uno al contador; tras **Visto**, el siguiente crea una nueva.
  - `affected_rows` de `ON DUPLICATE KEY UPDATE`: 1 al insertar, 2 al actualizar.
    Es la prueba de instalación que valida el apartado 5.3.
  - Marcar vista dos veces descuenta solo una; marcar notificación ajena no
    cambia nada.
  - **Marcar todas** respeta el filtro.
  - Desfase: alterar el contador a mano y comprobar que se repara.
  - Concurrencia: dos procesos agrupan y marcan a la vez sin dejar el contador
    negativo ni desfasado.
  - Tablas ausentes: el header devuelve vacío sin error.
  - Fallo de inserción: la acción original se completa.
- **Rendimiento:** con 10.000 usuarios y 500.000 filas, el header añade menos de
  2 ms y la página de notificaciones responde en menos de 100 ms (objetivos del
  apartado 6 de los requisitos).

## 14. Riesgos y decisiones por confirmar al implementar

- **`affected_rows`.** El apartado 5.3 depende de la semántica de MySQL; se
  valida con la prueba de instalación y existe la variante transaccional.
- **Seguidores numerosos.** Un tema con cientos de seguidores multiplica cada
  publicación; el lote por bloques de 200 limita la sentencia, pero conviene
  medir el coste real de un tema muy seguido antes de la fase 4.
- **Duplicidad temporal.** Durante las fases 3 y 4 el Tablón y la Bitácora
  conservan su panel o su contador anterior. Hasta retirarlos, el usuario puede
  ver el mismo aviso en dos sitios; se acepta como parte de la transición.
- **Coexistencia en el header.** La posición exacta de la campana junto al
  bloque de la Bitácora y su ancho en móvil se deciden con el diseño visual.
