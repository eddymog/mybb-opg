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
- Los secretos ocultos (sección 21) están pendientes de diseño técnico e
  implementación; la lista de ubicaciones es un estimado.

## 21. Secretos ocultos

**Estado: completo.** El sistema está terminado y están implementados los 30 secretos
(tabla 21.3). Para cambiar o añadir alguno, el procedimiento está en 21.5. La operación diaria (interruptor, pruebas, reseteo, alertas y despliegue) está
en `docs/106_Operacion_Halloween.md`.

### 21.1 Reglas

- Hay **30 secretos** repartidos por el foro.
- Cada secreto se descubre **haciendo clic** en una parte oculta de una página
  o sección del foro. No son iconos ni píxeles que buscar.
- Los secretos son **globales**: cualquier usuario puede descubrir cualquiera,
  aunque otra persona ya lo haya hecho. Cada usuario lo reclama una sola vez.
- Premios por secreto:
  - **Primera persona en descubrirlo:** 1 Cofre Calabaza (`CFRHLW`).
  - **Quienes lo descubren después:** 1 Calabaza Maldita.
- Esto sustituye el reparto anterior (15 secretos de 1 a 5 Calabazas y 5 de
  1 Papeleta).

### 21.2 Página del evento

Dos secciones nuevas:

1. **Mis secretos descubiertos:** los secretos que ha encontrado el usuario
   conectado y cuándo. Los que aún no ha encontrado se muestran como casillas
   bloqueadas, sin revelar dónde están.
2. **Quién descubrió cada secreto primero:** por cada secreto descubierto, el
   usuario que lo encontró primero y la fecha. Los que nadie ha encontrado
   aparecen sin descubrir.

### 21.3 Secretos implementados

Todos dan el mismo premio: la primera persona en descubrirlo recibe 1 Cofre Calabaza y
las siguientes, 1 Calabaza Maldita. Las claves son las del catálogo
(`opg_hlw_secret_catalog()` en `inc/plugins/lib/halloween2026_secretos.php`).

| # | Clave | Nombre | Dónde y cómo se descubre | Tipo |
|---|---|---|---|---|
| 1 | `hs-k7q2m9` | El Luffy gris | Botón gris de Luffy en el índice, junto al de Discord | clic |
| 2 | `hs-p4x8d1` | El título escondido | Imagen del título de la quinta categoría del índice | clic |
| 3 | `hs-m2c6w5` | La palabra mágica | Escribir «calabaza» en el chatbox del índice | chat |
| 4 | `hs-r9t3b6` | El primer puesto | Primer puesto del podio de «Más experiencia», en Estadísticas | enlace |
| 5 | `hs-d3k7a9` | La calabaza podrida | Objeto Calabaza Podrida, en la página de Objetos | clic delegado |
| 6 | `hs-h6j2q4` | La tirada de prueba | Botón «Tirada de prueba» de la página del Haki del Rey | clic delegado |
| 7 | `hs-g7w4e8` | El baúl animado | GIF de cofres de la página de tiradas de cofres | clic delegado |
| 8 | `hs-q4x7n2` | La granada de humo | Objeto Granada de Humo, en la página de Objetos | clic delegado |
| 9 | `hs-w9m3k6` | El dial abrasador | Objeto Dial Abrasador, en la página de Objetos | clic delegado |
| 10 | `hs-t2u5l9` | La contraseña del evento | Escribir «halloween2026» en el chatbox del índice | chat |
| 11 | `hs-b1y6s4` | El banner de los NPCs | Banner de la cabecera de la página de NPCs (`compas.php`) | clic |
| 12 | `hs-c8z2f7` | El adorno del carrito | Imagen decorativa del carrito de la Tienda del evento | clic delegado |
| 13 | `hs-s9p3v1` | El primer cofre de la tienda | Comprar un Cofre Calabaza en la Tienda del evento | compra |
| 14 | `hs-f3d8m1` | El nombre del encabezado | Tu nombre en el encabezado (`#ficha`), en cualquier página | clic delegado, global |
| 15 | `hs-n5k9r3` | El fantasma de la cabecera | Icono del fantasma de la cabecera de la página del evento | clic |
| 16 | `hs-j4h8t5` | El mar profundo | Imagen de Deep Ocean en el índice | enlace |
| 17 | `hs-a2e6c9` | La fruta murciélago | Batto Batto no Mi, en la página de Akumas | clic delegado |
| 18 | `hs-u6r1q8` | La fruta araña | Kumo Kumo no Mi, en la página de Akumas | clic delegado |
| 19 | `hs-y3l8e5` | El defecto pesimista | Defecto Pesimista (D046), en el registro de virtudes (`registro_virtudes.php`) | clic delegado |
| 20 | `hs-v7n2z4` | El mar del norte | Botón «North Blue» del mapa del mundo (`mapa_mundo.php`) | clic delegado |
| 21 | `hs-z5q9d2` | El segundo rostro de Barbanieves | Segundo avatar de la ficha de Barbanieves, en la página de NPCs (`compas.php`) | clic delegado |
| 22 | `hs-b4t6x9` | La apariencia de Lisstez | Apartado «Apariencia» de la ficha de Lisstez M. Shingehardz, en la página de NPCs (`compas.php`) | clic delegado con condición |
| 23 | `hs-e8m5j3` | El capitán de la portada | Imagen de Lance Turner, en la página de la Prensa Rosa (`prensarosa.php`) | clic delegado |
| 24 | `hs-k2d7p6` | La recompensa de los treinta días | Recompensa de 30 días, en la página de recompensas (`/op/recompensas.php`) | clic delegado |
| 25 | `hs-m9c4h2` | El icono del oficio | Icono de Puntos de Oficio de la portada de una ficha, en `personaje.php` | clic delegado |
| 26 | `hs-r6u3b8` | El saldo de los Kuros | Icono de Kuros del «Saldo actual», en la tienda de Kuros (`/op/tienda_kuros.php`) | clic delegado con selector hermano |
| 27 | `hs-l5g7y1` | El enlace escondido | Enlace puesto en un mensaje del foro (`/op/descubrir.php?c=ficha-belica-asfixia`) | enlace |
| 28 | `hs-v4d9s2` | La puerta de la beta | Enlace puesto en un mensaje del foro (`/op/descubrir.php?c=beta-opg`) | enlace |
| 29 | `hs-x2j6c4` | El secreto de las razas | Enlace puesto en la enciclopedia de razas (`/op/descubrir.php?c=enciclopedia-razas`) | enlace |
| 30 | `hs-m8q2t7` | La muerte más épica | Enlace puesto en el tema de El Despertar de Norfeo (`/op/descubrir.php?c=la-muerte-mas-epica`) | enlace |

Tipos: **clic** (elemento que existe al cargar la página), **clic delegado** (elemento creado
con JavaScript después de cargar, el clic se escucha en todo el documento y no se bloquea),
**enlace** (se reclama y después se sigue al enlace), **chat** y **compra** (se detectan en
el servidor, sin clic) y **global** (vale en todas las páginas).

Secretos de **enlace** (del 27 al 30): sin página ni selector. Se descubren siguiendo un enlace normal
puesto en un mensaje (`/op/descubrir.php?c=CODIGO`). El código lo eliges tú: minúsculas,
números y guiones (de 6 a 40 caracteres), p. ej. `ficha-belica-asfixia`, y distinto de la clave interna. Abrir el enlace no reclama nada: la página
enseña un botón y solo reclama al pulsarlo (con protección CSRF), así que una vista previa
del enlace o una imagen que apunte a él no dan premios. El enlace puede repartirse: cada
usuario lo descubre una sola vez. Valen las reglas de siempre (interruptor, cuentas de
prueba, bloqueos). Para crear otro, una entrada con `tipo => 'link'`, `codigo` e `imagen`.

Condición sobre otro elemento (el 22: el apartado «Apariencia» es el mismo para todos los NPCs y
solo vale cuando el nombre mostrado en la ficha es el de Lisstez): la entrada lleva
`condicion => array(selector, texto)`. Se comprueba al hacer clic, no al cargar la página.

Páginas que comparten el mismo `THIS_SCRIPT` se distinguen con `archivo`
(`tirada_rey.php` y `tirada_cofre.php`, `compas.php` y `companeros.php`,
`/op/halloween2026.php` y `/op/staff/halloween2026.php`).

### 21.4 Estimación inicial de ubicaciones (referencia)

Esta lista fue la primera idea de 20 ubicaciones. **No es la lista vigente** (esa es la
tabla 21.3): se conserva solo como referencia de las ideas iniciales: los 30 secretos ya están implementados.

| # | Ubicación |
|---|---|
| 1 | Página del NPC Jonethanthony |
| 2 | Clic en la Akuma de sangre, en la página de Akumas |
| 3 | Imagen de la temporada en el índice |
| 4 | Banner del evento |
| 5 | Botón de Estadísticas de Pruebas Personales |
| 6 | Cofre Calabaza en la página de Objetos |
| 7 | Akuma Horo Horo |
| 8 | Akuma de sombra |
| 9 | Escribir "halloween" en el chatbox (hecho como «halloween2026», secreto 10) |
| 10 | Clic en Barbanieves |
| 11 | Akuma de huesos |
| 12 | Clic en el NPC Samira |
| 13 | "¿Sabías que?" de Robin |
| 14 | Enlace en el evento de Halloween anterior |
| 15 | Acto Maléfico, en Virtudes |
| 16 | Botón Índice, junto al de Discord (hecho, secreto 1) |
| 17 | Clic en el solecito del header |
| 18 | Guía de Muerte y Resurrección |
| 19 | Subforo de la Grand Line |
| 20 | Banner de Halloween |

### 21.5 Implementación

| Pieza | Archivo |
|---|---|
| Tabla de descubrimientos | `docs/halloween2026_secretos_migration.sql` |
| Tabla de alertas (señuelos y sospechosos) | `docs/halloween2026_secretos_alertas_migration.sql` |
| Catálogo, reclamo, códigos de visita, señuelos y alertas | `inc/plugins/lib/halloween2026_secretos.php` |
| Respuesta JSON (nombre neutro a propósito) | `op/ajuste_indice.php` |
| Script del navegador (se escribe dentro de la pagina) | `inc/plugins/lib/halloween2026_cliente.php` |
| Carga del script solo donde hace falta | hook `pre_output_page` en `inc/plugins/opg_halloween2026.php` |
| Pestaña Secretos de la página del evento | `opg_hlw_render_secrets()` en `op/halloween2026.php` |
| Interruptor y lista de alertas | `op/staff/halloween2026.php` |
| Pruebas | `tests/halloween2026/secretos.php` |

Quién puede descubrir:

- Con el interruptor de la consola de staff apagado (valor por defecto), solo las
  cuentas de prueba 315 y 172 (`OPG_HLW_SECRET_TESTERS`), en cualquier momento.
- Encendido, cualquier usuario con personaje elegible mientras el evento esté
  activo y dentro de la ventana de obtención.
- Ver la pestaña Secretos no depende del interruptor.

Premios: el primero recibe 1 `CFRHLW`; los demás, 1 Calabaza por el libro de
movimientos (`source_type = 'secret'`, clave de idempotencia por secreto y
usuario). Todo en una transacción.

Para añadir un secreto de clic: una entrada en `opg_hlw_secret_catalog()` con
clave, número, nombre, pista, `pagina` (el `THIS_SCRIPT` donde vive) y
`selector` (un selector CSS que encuentre el elemento en la plantilla). La
plantilla no se toca: no lleva ningún id ni atributo.

Secretos sobre elementos que se crean con JavaScript después de cargar la página
(el 5, el objeto Calabaza Podrida en `objetos.php`, `#CALPOD`): la entrada lleva
`delegar => true`. El script escucha el clic en todo el documento y comprueba
si cae dentro del selector, sin bloquear el clic del elemento.

Páginas que comparten `THIS_SCRIPT` (`opg/tirada_cofre.php` declara por error
`tirada_rey.php`, igual que la del Haki del Rey): la entrada del catálogo lleva
`archivo => 'tirada_rey.php'` y solo vale si se está ejecutando ese archivo
(`basename($_SERVER['SCRIPT_NAME'])`).

Secretos que son un enlace (por ejemplo el 4, el primer puesto del podio de
«Más experiencia»): la entrada lleva `navegar => true`. El script reclama el
secreto y, al cerrar la felicitación, sigue al enlace; un clic con Ctrl, Mayús o
Cmd conserva el comportamiento normal. Si el secreto solo vale en una vista de
la página se añade `cuando => array(parametro => valor)`, que se compara con los
parámetros de la petición.

Secreto de tipo `visita` (disponible, ahora sin ninguno activo): la entrada lleva
`tipo => 'visita'`, `pagina` e `imagen`. El hook `pre_output_page` lo reclama en
el servidor al cargar la página (solo con una petición GET de una página HTML
completa) y pasa el resultado al script, que solo muestra la felicitación.
No lleva código de visita ni señuelos: la visita es el propio secreto.

Secreto de tipo `chat` (el 3, escribir «calabaza» en el chat del índice): la
entrada lleva `tipo => 'chat'`, `vigilar` (zona del chat) e `imagen`. Lo detecta
el servidor con el hook `rt_chat_commit_message_insert` de RT Chat, cuando el
mensaje público ya está guardado, y deja el resultado en una cookie de 90 s
que el script del índice recoge para mostrar la felicitación.

### 21.6 Medidas contra trampas

El objetivo es subir el esfuerzo y dejar rastro, no hacerlo imposible: nada de
lo que se ejecuta en el navegador es seguro.

0. **Disimulo del script y de la configuración.** El script no es un archivo aparte:
   se escribe dentro de la página, ya compacto, sin comentarios y sin palabras del
   evento (los textos del modal los pone el servidor). La página solo lleva un
   billete opaco (cifrado con libsodium, con el usuario, la página y los parámetros
   de la visita). La configuración (códigos, selectores y textos de apoyo) no va en
   el HTML: el script la pide al servidor (`op/ajuste_indice.php`, petición `c`) cuando
   alguien interactúa con la página, o a los 4 s, y llega cifrada con un XOR derivado
   del billete, sin estructura de JSON ni claves con nombre. Los resultados de un
   reclamo viajan como listas sin nombres de campo. El código de visita conserva la
   hora de carga de la página, por eso el tiempo mínimo se cuenta desde ahí.
1. **Sin pistas en el HTML.** Ningún atributo, clase ni id habla de secretos
   ni marca los elementos: el script los encuentra con selectores CSS que van
   dentro de la configuración de cada visita. El script y la dirección del
   servidor tienen nombres neutros y el hook no deja mensajes. El motivo de un
   rechazo solo se devuelve a las cuentas de prueba.
2. **Prueba de visita.** Al cargar la página, el servidor genera un código
   cifrado y autenticado (libsodium `secretbox`) por cada secreto real y por
   cada señuelo. Contiene usuario, secreto, hora y si es señuelo; el
   navegador solo ve texto opaco. El reclamo se acepta solo si el código se
   abre, es del usuario, no ha caducado (12 h) y pasó al menos 1,5 s desde la
   carga. La clave de cifrado sale de la configuración de la base de datos.
3. **Señuelos con alarma.** Se emiten 3 señuelos por página. El script crea
   para ellos un elemento oculto fuera de pantalla, con un id aleatorio
   distinto en cada visita (`#v` + 6 hex), que un usuario no puede pulsar. Reclamar uno
   registra una alerta, bloquea al usuario para todos los secretos y
   responde igual que "cerrado". Las cuentas de prueba nunca se bloquean.
4. **Auditoría.** `mybb_op_halloween2026_secretos_alertas` guarda usuario,
   tipo (`senuelo`, `rapido`, `codigo`), IP y navegador. La consola de staff
   lista las últimas 50 y permite perdonar a un usuario, lo que le quita el
   bloqueo.

Pendiente, no hecho: rastro del ratón, límite de peticiones por usuario y
comprobación de varias cuentas con la misma IP.
