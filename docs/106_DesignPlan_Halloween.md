# Halloween 2026: plan de diseño

> Depende de:
> [106_Requirements_Halloween_2026.md](106_Requirements_Halloween_2026.md)
>
> **Estado: por implementar.** Este documento describe la arquitectura y el
> orden de construcción; no implica que las tablas o archivos existan aún.

## 1. Objetivo

Construir `/op/halloween2026.php` como página del evento **La Cosecha de
Calabazas Malditas**, con obtención automática, saldo e historial propios,
estadísticas, tienda y archivo histórico.

El sistema tendrá una economía aislada. Ningún dato de Calabazas se guardará
en `mybb_op_fichas`: todas las ganancias, gastos, regalos, reversiones y
compras vivirán en tablas InnoDB específicas `mybb_op_halloween2026_*`.

La integración con el sistema independiente de logros queda fuera del alcance
de esta entrega.

## 2. Decisiones técnicas

- Integración mediante un plugin MyBB; no se editarán `newthread.php`,
  `newreply.php` ni `inc/datahandlers/post.php`.
- Un servicio central será la única autoridad para conceder o revertir
  Calabazas.
- El libro de movimientos será inmutable; las correcciones serán nuevos
  movimientos enlazados.
- El saldo materializado permitirá lecturas rápidas, pero siempre podrá
  reconstruirse desde el libro.
- Todas las tablas nuevas usarán InnoDB para que saldo, movimiento y compra
  participen en la misma transacción.
- Las operaciones automáticas serán idempotentes mediante índices únicos.
- La actividad se evaluará con la fecha original del post en
  `Europe/Madrid`, no con la hora actual del servidor.
- Estadísticas y rankings se calcularán desde las tablas del evento, nunca
  recorriendo todos los posts al abrir la página.
- No se construirá un catálogo genérico de eventos ni se añadirá `event_id` a
  todas las tablas. Un futuro evento de Navidad tendrá sus propias tablas.
- El código común se extraerá únicamente cuando exista un segundo evento y se
  hayan identificado comportamientos realmente compartidos.

## 3. Archivos

| Archivo | Responsabilidad | Estado |
|---|---|---|
| `op/halloween2026.php` | Controlador restringido, vistas y acciones de compra | Nuevo |
| `op/staff/halloween2026.php` | Movimientos manuales, regalos, catálogo y consultas | Nuevo |
| `inc/plugins/opg_halloween2026.php` | Registro de hooks y adaptadores de MyBB | Nuevo |
| `inc/plugins/lib/halloween2026.php` | Dominio, calendario, movimientos, saldos y reconciliación | Nuevo |
| `inc/plugins/lib/halloween2026_store.php` | Catálogo, límites, compra y entrega | Nuevo |
| `inc/plugins/lib/halloween2026_stats.php` | Resúmenes, rankings y caché | Nuevo |
| `templates/One_Piece_Gaiden_Templates/op_halloween2026.html` | Estructura principal | Nuevo |
| `templates/One_Piece_Gaiden_Templates/op_halloween2026_evento.html` | Resumen y reglas | Nuevo |
| `templates/One_Piece_Gaiden_Templates/op_halloween2026_estadisticas.html` | Métricas y rankings | Nuevo |
| `templates/One_Piece_Gaiden_Templates/op_halloween2026_tienda.html` | Catálogo y carrito | Nuevo |
| `templates/One_Piece_Gaiden_Templates/op_halloween2026_actividad.html` | Historial personal | Nuevo |
| `templates/One_Piece_Gaiden_Templates/staff_halloween2026.html` | Consola de staff | Nuevo |
| `jscripts/halloween2026.js` | Tabs, carrito y confirmación | Nuevo |
| `css/halloween2026.css` | Estilos responsive del evento | Nuevo |
| `docs/halloween2026_migration.sql` | Tablas, índices y evento inicial | Nuevo |

La ubicación final del CSS seguirá el mecanismo de estilos que esté usando el
tema al implementar; no se insertará un bloque grande de CSS inline en la
plantilla.

## 4. Activos

Las referencias entregadas se descargarán antes de publicar:

| Destino local propuesto | Origen |
|---|---|
| Banner de tienda remoto | `https://i.imgur.com/kdGOe7n.jpeg` |
| Banner de estadisticas remoto | `https://i.imgur.com/tYndMUv.jpeg` |
| Decoracion de tienda remota | `https://i.imgur.com/G5okouN.png` |
| Portada del evento | `/images/op/halloween2026/portada-evento.jpg` |

Fuentes visuales consultadas:

- portada: miniatura del video oficial enlazado en el evento;
- arte de Halloween: galeria promocional de One Piece Thousand Storm en 4Gamer;
- historias: fotograma de la llegada a Thriller Bark asociado al episodio 337;
- monstruos: fotograma de Oars durante el arco de Thriller Bark.

El banner de tienda se utilizará inicialmente en su estado actual.

Cada imagen tendrá dimensiones declaradas, `alt` y un comportamiento
responsive. La decoración sustituirá a Nami únicamente en esta tienda; no se
modificará `op_tienda_kuros.html`.

## 5. Esquema de datos

### 5.1 `mybb_op_halloween2026_config`

Una única fila configura este evento; no es un catálogo genérico:

```sql
CREATE TABLE mybb_op_halloween2026_config (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    timezone VARCHAR(64) NOT NULL,
    earn_start_utc DATETIME NOT NULL,
    earn_end_utc DATETIME NOT NULL,
    claims_end_utc DATETIME NULL,
    store_start_utc DATETIME NULL,
    store_end_utc DATETIME NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    config_json JSON NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

La aplicación solo acepta `id = 1`. La migración carga:

- zona: `Europe/Madrid`;
- obtención local: `[2026-10-26 06:00, 2026-11-10 06:00)`;
- cierre de reclamaciones: 16 de noviembre de 2026 a las 23:59 de Madrid;
- tienda: permanecerá cerrada durante la comprobación inicial y se abrirá
  manualmente después de validar una compra completa; su cierre será el 16 de
  noviembre de 2026 a las 23:59, hora de Madrid.

Las fechas se convierten a UTC al guardar. La zona se conserva para mostrar y
calcular los días del evento.

### 5.2 `mybb_op_halloween2026_saldos`

```sql
CREATE TABLE mybb_op_halloween2026_saldos (
    uid INT UNSIGNED NOT NULL,
    ganado_total INT NOT NULL DEFAULT 0,
    gastado_total INT NOT NULL DEFAULT 0,
    saldo INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (uid),
    KEY idx_halloween_earned (ganado_total, uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Semántica:

- premio: aumenta `ganado_total` y `saldo`;
- reversión de premio: reduce `ganado_total` y `saldo`;
- compra: aumenta `gastado_total` y reduce `saldo`;
- reembolso: reduce `gastado_total` y aumenta `saldo`.

Una reversión puede dejar saldo negativo si el usuario ya gastó las Calabazas:
la deuda no se oculta ni se limita artificialmente a cero, y se bloquean nuevas
compras hasta recuperar saldo.

### 5.3 `mybb_op_halloween2026_movimientos`

```sql
CREATE TABLE mybb_op_halloween2026_movimientos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uid INT UNSIGNED NOT NULL,
    amount INT NOT NULL,
    bucket VARCHAR(16) NOT NULL,
    source_type VARCHAR(32) NOT NULL,
    source_id VARCHAR(128) NOT NULL,
    source_pid INT UNSIGNED NULL,
    source_tid INT UNSIGNED NULL,
    idempotency_key VARCHAR(191) NOT NULL,
    reverses_movement_id BIGINT UNSIGNED NULL,
    actor_uid INT UNSIGNED NULL,
    note VARCHAR(255) NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_halloween_idempotency (idempotency_key),
    KEY idx_halloween_user_date (uid, created_at),
    KEY idx_halloween_source (source_type, source_id),
    KEY idx_halloween_pid (source_pid),
    KEY idx_halloween_tid (source_tid),
    KEY idx_reversal (reverses_movement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`bucket` distingue ganancias de gastos para mantener correctamente ambos
totales. `source_pid` y `source_tid` permiten revertir contenido incluso
después de que MyBB lo haya borrado físicamente.

### 5.4 `mybb_op_halloween2026_bonos_diarios`

Guarda quién posee el bono de cada día:

```sql
CREATE TABLE mybb_op_halloween2026_bonos_diarios (
    uid INT UNSIGNED NOT NULL,
    event_day DATE NOT NULL,
    pid INT UNSIGNED NOT NULL,
    movement_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (uid, event_day),
    UNIQUE KEY uq_halloween_daily_pid (pid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

La clave primaria resuelve carreras entre dos publicaciones simultáneas. La
reserva se inserta inicialmente con `movement_id = NULL`, se crea el
movimiento y después se enlaza, todo dentro de la misma transacción. Si el
post propietario deja de ser válido, se revierte su movimiento, se libera la
fila y se busca el primer post válido restante de ese día.

### 5.5 Catálogo y compras

`mybb_op_halloween2026_tienda_items`:

- `id`, nombre, descripción, imagen y categoría;
- coste, tipo y `reward_json`;
- stock global y límite por usuario opcionales;
- fechas propias opcionales, orden y estado.

`mybb_op_halloween2026_compras`:

- cabecera con UID, total, estado y clave idempotente;
- fechas y mensaje de error interno opcional.

`mybb_op_halloween2026_compra_lineas`:

- compra, artículo, cantidad, precio unitario;
- copia de nombre y `reward_json` entregado.

Los tres objetos serán tablas InnoDB. La migración incluirá índices por
usuario, artículo y fecha. El catálogo inicial, sin stock global ni límite por
personaje, será:

| Artículo | `reward_json` | Coste |
|---|---|---:|
| Cofre Calabaza | `{"objects":[{"id":"CFRHLW","quantity":1}]}` | 7 |
| Nika | `{"nikas":1}` | 5 |
| Puntos de oficio | `{"craft_points":100}` | 3 |
| Berries | `{"berries":50000}` | 1 |

### 5.6 Aislamiento frente a eventos futuros

No existirá `mybb_op_eventos`, ni estas tablas tendrán `event_id`. Navidad u
otro evento futuro recibirá su propio namespace y su propia migración, por
ejemplo `mybb_op_navidad2027_*`.

No se creará ahora una librería económica genérica. Cuando exista un segundo
evento se comparará con Halloween y se extraerán únicamente los helpers que
demuestren compartir reglas y garantías, sin mezclar sus datos históricos.

## 6. Configuración central

`halloween2026.php` expondrá una configuración cargada una vez por petición:

```php
array(
    'idempotency_namespace' => 'halloween2026',
    'role_root_fid' => 10,
    'common_prefix_id' => 1,
    'adventure_prefix_id' => 3,
    'spooky_prefix_id' => 15,
    'main_thread_url' => 'https://onepiecegaiden.com/showthread.php?tid=9471',
    'stories_thread_url' => 'https://onepiecegaiden.com/showthread.php?tid=9535',
    'monsters_thread_url' => 'https://onepiecegaiden.com/showthread.php?tid=9536',
);
```

Los valores verificados en `mybb_threadprefixes` son `Común = 1`,
`Aventura = 3` y `Spooky = 15`.

La pertenencia a la zona de rol se comprobará con el foro cacheado por MyBB:
el foro es elegible cuando su FID es 10 o su `parentlist` contiene 10 como ID
completo. No se usará una coincidencia textual ambigua como `%10%`.

## 7. Plugin y hooks

### 7.1 Inserción

Hooks confirmados en esta instalación:

```php
$plugins->add_hook('datahandler_post_insert_post_end',
    'opg_halloween_post_insert');
$plugins->add_hook('datahandler_post_insert_thread_end',
    'opg_halloween_post_insert');
```

El adaptador obtiene `pid`, `tid`, `uid` y `visible` del `PostDataHandler`, y
llama a:

```php
opg_halloween_reconcile_post($pid, 'insert');
```

El mismo callback sirve para respuestas y temas. El servicio decide si el PID
es `mybb_threads.firstpost`; no confía en cuál de los dos hooks lo llamó.

### 7.2 Moderación

Hooks confirmados:

- `class_moderation_approve_posts`;
- `class_moderation_unapprove_posts`;
- `class_moderation_soft_delete_posts`;
- `class_moderation_restore_posts`;
- `class_moderation_delete_post_start` y `class_moderation_delete_post`;
- `class_moderation_approve_threads`;
- `class_moderation_unapprove_threads`;
- `class_moderation_soft_delete_threads`;
- `class_moderation_restore_threads`;
- `class_moderation_delete_thread_start` y `class_moderation_delete_thread`;
- hooks de movimiento de temas.

Los hooks posteriores reciben PIDs o TIDs que ya reflejan el nuevo estado y
llaman a reconciliación. En borrado definitivo, el hook `*_start` conserva en
memoria los IDs necesarios; el hook posterior revierte usando las referencias
guardadas en movimientos.

Un hook de tema llama a `opg_halloween_reconcile_thread($tid, $reason)`, que
reconcilia recompensa del tema, posts premiados y bono diario. Así se cubre el
caso en que MyBB cambia la visibilidad de un tema entero sin ejecutar un hook
individual para cada respuesta.

### 7.3 Movimiento de temas

- Si el destino queda fuera de la zona de rol, se revierten los movimientos
  de post, tema y bonos diarios asociados.
- Si el destino entra en la zona de rol, no se conceden premios retroactivos.
- El movimiento almacena una marca de exclusión para que una reconciliación
  posterior no interprete el nuevo foro como elegibilidad original.

## 8. Procesamiento de una publicación

### 8.1 Lectura única

El servicio hará una consulta equivalente a:

```sql
SELECT
    p.pid, p.tid, p.uid, p.fid, p.dateline, p.visible,
    t.firstpost, t.prefix, t.visible AS thread_visible,
    f.parentlist
FROM mybb_posts p
JOIN mybb_threads t ON t.tid = p.tid
JOIN mybb_forums f ON f.fid = p.fid
WHERE p.pid = ?
LIMIT 1;
```

No se consulta `mybb_op_fichas` para guardar moneda. Solo se comprueba la
existencia de ficha mediante el helper actual o una consulta de existencia.

### 8.2 Validaciones

El post es elegible si:

- `uid > 0` y existe una ficha activa y aprobada;
- post y tema tienen `visible = 1`;
- su `dateline` cae dentro del intervalo de obtención;
- el FID original pertenece a la zona de rol;
- no existe una marca que indique que entró posteriormente por movimiento.

La moderación posterior al evento usa el `dateline` original. Un post creado
dentro del periodo y aprobado más tarde sigue siendo válido.

### 8.3 Cálculo

```php
$awards = array();

if ($eligiblePost) {
    $awards[] = array('post', 1, "halloween2026:post:$pid");
}

if ($eligiblePost && $pid === $firstPost) {
    if ($prefixId === $spookyPrefixId) {
        $awards[] = array('thread', 3, "halloween2026:thread:$tid");
    } elseif ($prefixId === $commonPrefixId ||
              $prefixId === $adventurePrefixId) {
        $awards[] = array('thread', 2, "halloween2026:thread:$tid");
    }
}
```

Después se intenta reservar el bono diario.

### 8.4 Día del evento

```php
$date = new DateTimeImmutable('@' . $postDateline);
$date = $date->setTimezone(new DateTimeZone('Europe/Madrid'));
$eventDay = $date->modify('-6 hours')->format('Y-m-d');
```

Esto respeta automáticamente horario de verano/invierno y define el corte
exacto a las 06:00.

### 8.5 Ejemplos

| Caso | Resultado |
|---|---:|
| Respuesta elegible | 1 |
| Primera respuesta elegible del día | 2 |
| Nuevo tema Común/Aventura | 3 |
| Nuevo tema Común/Aventura y primer post diario | 4 |
| Nuevo tema `Spooky` | 4 |
| Nuevo tema `Spooky` y primer post diario | 5 |

## 9. Escritura de movimientos

### 9.1 Transacción de concesión

El helper `opg_halloween_apply_movement()`:

1. inicia transacción;
2. crea la fila de saldo en cero con `INSERT IGNORE` si no existe;
3. bloquea el saldo con `SELECT ... FOR UPDATE`;
4. inserta el movimiento validado con su clave única;
5. si la inserción fue nueva, actualiza los tres acumulados;
6. confirma;
7. si la clave ya existía, no modifica el saldo.

No se usará `INSERT IGNORE` con datos sin validar. Su único propósito será
resolver de forma segura claves únicas controladas.

### 9.2 Lote por publicación

Los movimientos de post, tema y diario se procesan dentro de una misma
transacción y con el saldo bloqueado una sola vez. Solo se suma el importe de
las filas realmente nuevas.

Si una inserción falla por una causa distinta a una clave ya existente, se
revierte el lote completo.

### 9.3 Reversiones

Una reversión crea otro movimiento:

- importe opuesto al original;
- `reverses_movement_id` apuntando al premio;
- clave `reversal:{movement_id}:{reason}`;
- misma referencia PID/TID.

Antes de revertir se comprueba que ese movimiento no tenga ya una reversión
activa. Restaurar crea una concesión nueva identificada por PID y una versión
incremental de reconciliación, por ejemplo `restore:{movement_id}:1`; nunca
edita ni reutiliza la clave del movimiento histórico.

## 10. Bono diario

### 10.1 Reserva

Dentro de la transacción del post:

1. calcular `event_day`;
2. insertar `(uid, event_day, pid)` en
   `mybb_op_halloween2026_bonos_diarios`;
3. si la inserción gana la clave única, crear el movimiento `+1`;
4. guardar el ID del movimiento en la reserva;
5. si la fila ya existe, no conceder nada.

### 10.2 Reasignación

Cuando el PID propietario deja de ser válido:

1. revertir su movimiento diario;
2. eliminar la reserva bajo bloqueo;
3. buscar el post visible elegible más antiguo de ese UID y día;
4. reservarlo y concederle el bono;
5. si no existe otro, dejar el día sin bono.

Si el post original se restaura después, no desplaza al nuevo propietario.

## 11. Página del evento

### 11.1 Routing

La vista se seleccionará mediante `?vista=`:

- `evento` por defecto;
- `estadisticas`;
- `tienda`;
- `actividad`.

El servidor renderiza únicamente la vista solicitada. Cambiar de tab mantiene
la posición superior de la página y no usa anclas que desplacen al usuario.

### 11.2 Acceso

- Todas las vistas de `/op/halloween2026.php` requieren sesión y una ficha
  activa y aprobada, incluidas Evento y Estadísticas.
- Invitados y personajes sin ficha, con ficha inactiva o no aprobada no
  pueden acceder a la sección ni consultar historial.
- Los registros internos existentes se conservan exclusivamente para
  auditoría y consistencia económica.

### 11.3 Estados

El controlador obtiene el estado desde fechas y configuración:

- próximo;
- obtención activa;
- reclamaciones;
- tienda abierta;
- archivado.

El cliente no decide si una compra o premio está abierto. Todos los endpoints
lo validan de nuevo en servidor.

## 12. Estadísticas

### 12.1 Consultas

Resumen desde movimientos y compras:

- ganado neto;
- gastado neto;
- saldo en circulación;
- participantes;
- posts, temas, `Spooky` y bonos diarios;
- compras.

Ranking principal desde `mybb_op_halloween2026_saldos`, ordenado por
`ganado_total DESC`. El total incluye ganancias automáticas, presets, regalos
y premios manuales. Gastar no modifica la posición; una reversión o corrección
de una ganancia sí modifica el total neto.

### 12.2 Empates

Para participantes empatados se consulta la fecha en la que cada uno alcanzó
su total actual. Gana quien lo alcanzó antes; UID resuelve el último empate.

### 12.3 Caché

- Resumen y top 10: caché MyBB durante 120 segundos.
- Invalidación después de movimientos manuales grandes o cambios de catálogo.
- Historiales y ranking completo: consulta paginada, sin cargar todo en
  memoria.

## 13. Tienda

### 13.1 Catálogo

El servidor entrega únicamente artículos activos para el evento y vista
actual. `reward_json` acepta tipos controlados:

```json
{
  "objects": [{"id": "CFR001", "quantity": 1}],
  "nikas": 0,
  "berries": 0,
  "experience": 0,
  "craft_points": 0
}
```

No se ejecutan nombres de funciones ni código almacenado en JSON.

### 13.2 Confirmación

El navegador envía IDs y cantidades, más token CSRF e idempotencia. El
servidor vuelve a cargar:

- precios;
- disponibilidad temporal;
- stock;
- límite individual;
- saldo.

Nunca acepta el total calculado por JavaScript.

### 13.3 Transacción y motores de almacenamiento

Saldo, catálogo, compras y movimientos nuevos serán InnoDB y compartirán una
transacción. Antes de implementar la entrega se auditará el motor de cada
tabla receptora: una transacción MySQL no puede garantizar atomicidad sobre
una tabla MyISAM.

Para recompensas cuyo destino sea InnoDB:

1. abrir transacción;
2. bloquear saldo e ítems con stock;
3. validar carrito y límites;
4. crear compra y líneas con snapshot;
5. entregar recompensas mediante helpers que propaguen errores;
6. crear movimiento de gasto;
7. actualizar stock y estado de compra;
8. confirmar.

Para cualquier recompensa cuyo destino siga siendo MyISAM, antes de habilitar
ese tipo se elegirá y documentará una de estas soluciones:

- migrar la tabla receptora a InnoDB tras probar compatibilidad;
- excluir temporalmente ese tipo de recompensa del catálogo;
- usar una cola de entrega persistente e idempotente, con reintentos y
  compensación automática si no puede completarse.

No se afirmará que existe una compra atómica cruzando motores si la base de
datos no puede ofrecerla. La tienda no entrará en producción con el flujo
heredado de Kuros, que puede entregar parcialmente y cobrar después.

## 14. Consola de staff

`op/staff/halloween2026.php` requerirá `is_staff` y tendrá vistas separadas:

- **Personajes:** saldo, historial y verificación;
- **Movimientos:** concesión, reversión y notas;
- **Regalos de Kuro:** reparto identificado e idempotente;
- **Presets:** premios de participación predefinidos, una sola vez por
  personaje para historias, monstruos, aventura `Spooky`, avatar del foro y
  avatar de Discord;
- **Tienda:** catálogo, stock, límites y fechas;
- **Compras:** búsqueda y recibos;
- **Resultados:** datos históricos y concursos;
- **Exportar:** CSV.

No existirá un `UPDATE saldo = X` desde formulario. Corregir crea un
movimiento y exige motivo.

Cada preset tendrá una clave única por tipo y UID. La consola mostrará si ya
fue concedido y el servicio rechazará cualquier repetición aunque se reenvíe
la petición. No exigirá una referencia o evidencia adjunta: se confía en la
verificación previa del moderador. No se ofrecerá un preset para **Llamada del
Grand Line**.

## 15. Integración con logros

No se implementará ninguna integración, cola ni concesión pendiente para
`/op/logros.php` en esta entrega.

## 16. Seguridad

- `verify_post_check()` en compras y acciones de staff.
- Permisos de staff comprobados dentro de cada acción.
- IDs convertidos a enteros y textos escapados mediante `$db`.
- Cantidades positivas y límites máximos validados en servidor.
- Transacciones, bloqueos de fila e índices únicos contra concurrencia.
- Ningún dato de Calabazas escrito en `mybb_op_fichas`.
- Ningún precio o recompensa aceptado desde el navegador.
- Errores registrados sin exponer SQL.
- Auditoría con actor, objetivo, motivo y referencia.

## 17. Rendimiento

Publicar un post elegible requerirá:

- una consulta conjunta de post/tema/foro;
- una comprobación ligera de ficha;
- una transacción sobre las tablas indexadas del evento;
- como máximo tres movimientos nuevos.

No se calcula el ranking ni se recorren publicaciones durante el hook. Las
peticiones fuera del periodo finalizan tras cargar la configuración del
evento y comprobar la fecha, reduciendo el coste después del 9 de noviembre.

## 18. Pruebas

### 18.1 Dominio puro

- intervalo del evento y cambio horario;
- jerarquía de foros;
- matriz de prefijos;
- cálculo de día a las 05:59 y 06:00;
- construcción de claves;
- movimientos y reversiones.

### 18.2 Base de datos

- dos hooks iguales no duplican;
- dos posts simultáneos solo conceden un bono diario;
- lote de tres movimientos actualiza un saldo una vez;
- fallo intermedio revierte todo;
- compra concurrente no supera saldo, stock ni límite;
- saldo se reconstruye exactamente desde movimientos.

### 18.3 Moderación

- aprobación posterior;
- desaprobación y restauración;
- borrado suave y definitivo;
- eliminación de tema con varias respuestas;
- movimiento fuera y dentro de la zona de rol;
- reasignación del bono diario.

### 18.4 Página

- cada tab carga solo sus consultas;
- no hay salto al cambiar de tab;
- estados próximo, activo, reclamación, tienda y archivo;
- escritorio y móvil;
- carrito, recibo, errores y estados vacíos.

## 19. Orden de implementación

1. Escribir y revisar la migración InnoDB con la configuración confirmada.
3. Implementar calendario, claves y servicio de movimientos con pruebas.
4. Implementar hooks de inserción y matriz de recompensas.
5. Implementar hooks de moderación y reconciliación.
6. Implementar saldo, historial y estadísticas.
7. Implementar catálogo y compra transaccional.
8. Construir consola de staff.
9. Construir página y plantillas responsive.
10. Descargar, corregir y optimizar activos.
11. Ejecutar pruebas concurrentes, revisión visual y prueba completa en una
    copia de la base de datos.

## 20. Decisiones pendientes

- Verificar durante la implementación qué helper entrega los puntos de oficio
  y si su almacenamiento permite incluirlo en la misma transacción.
- Auditar los helpers de entrega del cofre `CFRHLW`, Nikas y Berries antes de
  habilitar compras reales.
