# Halloween 2026: plan de implementación

> Requisitos:
> [106_Requirements_Halloween_2026.md](106_Requirements_Halloween_2026.md)
>
> Diseño:
> [106_DesignPlan_Halloween.md](106_DesignPlan_Halloween.md)
>
> **Estado:** implementación local completada el 24 de octubre de 2026.
> Pendientes de despliegue: activar el plugin, sincronizar sus recursos y
> ejecutar la prueba transaccional de compra antes de abrir la tienda.

## 1. Objetivo

Construir `/op/halloween2026.php` y su consola de staff con:

- obtención automática e idempotente de Calabazas;
- saldo e historial independientes de `mybb_op_fichas`;
- estadísticas y ranking;
- tienda transaccional;
- movimientos manuales auditados;
- archivo histórico una vez finalizado el evento.

La primera versión no incluye Logros ni Secretos. Ninguna tabla del evento
tendrá borrado físico desde la aplicación: las correcciones se representarán
con estados y contramovimientos.

## 2. Punto de partida verificado

### 2.1 Configuración del foro

| Concepto | Valor |
|---|---:|
| Zona de rol | `FID 10` y descendientes |
| Prefijo Común | `PID 1` |
| Prefijo Aventura | `PID 3` |
| Prefijo Spooky | `PID 15` |
| Acceso a consola | `is_staff($uid)` |
| MySQL | `5.7.44-48` |

La pertenencia a rol se comprobará separando `parentlist` por comas y buscando
el entero `10`, o aceptando directamente `fid = 10`. No se usará `LIKE '%10%'`.

### 2.2 Motores y atomicidad

Verificado en producción mediante consultas de solo lectura:

| Tabla | Motor | Uso en Halloween |
|---|---|---|
| `mybb_op_fichas` | InnoDB | Entregar Nikas, Berries y puntos de oficio |
| `mybb_op_inventario` | InnoDB | Entregar `CFRHLW` |
| `mybb_op_audit_general` | InnoDB | Auditoría de recompensas |
| `mybb_op_objetos` | MyISAM | Solo validar y mostrar el catálogo |
| `mybb_posts`, `mybb_threads`, `mybb_forums` | MyISAM | Solo leer actividad del foro |
| `mybb_users` | MyISAM | No participa en las compras iniciales |

La compra puede ser atómica porque todas las escrituras del catálogo inicial
se hacen en tablas InnoDB. `mybb_op_objetos` se consulta antes de entregar el
cofre, pero no se modifica.

`mybb_op_inventario` ya tiene una clave única `(objeto_id, uid)`, adecuada
para una entrega con `INSERT ... ON DUPLICATE KEY UPDATE`.

### 2.3 Objeto estacional verificado

El objeto `CFRHLW` fue creado en `mybb_op_objetos` el 24 de octubre de 2026
mediante `docs/halloween2026_object.sql`, con:

- identificador exacto `CFRHLW`;
- nombre **Cofre Calabaza**;
- imagen y descripción definitivas;
- imagen `https://i.imgur.com/87wW3Xt.jpeg`.

Su tabla de premios se configurará por separado. La tienda debe seguir
validando que el objeto exista antes de entregar una compra.

## 3. Archivos

### 3.1 Crear

| Archivo | Responsabilidad |
|---|---|
| `docs/halloween2026_object.sql` | Alta idempotente del objeto `CFRHLW` |
| `docs/halloween2026_migration.sql` | Tablas, índices, configuración y catálogo inicial |
| `inc/plugins/opg_halloween2026.php` | Registro de hooks y ciclo del plugin |
| `inc/plugins/lib/halloween2026.php` | Calendario, elegibilidad, movimientos y reconciliación |
| `inc/plugins/lib/halloween2026_store.php` | Catálogo, compra y entrega transaccional |
| `inc/plugins/lib/halloween2026_stats.php` | Resumen, ranking, historial y caché |
| `op/halloween2026.php` | Controlador restringido y acciones del usuario |
| `op/staff/halloween2026.php` | Consola para `is_staff` |
| `templates/One_Piece_Gaiden_Templates/op_halloween2026.html` | Marco, cabecera y tabs |
| `templates/One_Piece_Gaiden_Templates/op_halloween2026_evento.html` | Reglas, fechas y enlaces |
| `templates/One_Piece_Gaiden_Templates/op_halloween2026_estadisticas.html` | Resumen y ranking |
| `templates/One_Piece_Gaiden_Templates/op_halloween2026_tienda.html` | Catálogo, carrito y recibo |
| `templates/One_Piece_Gaiden_Templates/op_halloween2026_actividad.html` | Saldo e historial personal |
| `templates/One_Piece_Gaiden_Templates/staff_halloween2026.html` | Consola de administración |
| `templates/One_Piece_Gaiden_Templates/stylesheets/op_halloween2026.css` | Estilos responsive |
| `jscripts/halloween2026.js` | Carrito, confirmación y mejoras progresivas |
| `tests/halloween2026_domain_test.php` | Pruebas puras de fechas, reglas y claves |

### 3.2 Activos locales

Descargar y servir desde `/images/op/uploads/halloween2026/`:

| Archivo | Origen |
|---|---|
| Banner de tienda remoto | `https://i.imgur.com/kdGOe7n.jpeg` |
| Banner de estadisticas remoto | `https://i.imgur.com/tYndMUv.jpeg` |
| Decoracion de tienda remota | `https://i.imgur.com/G5okouN.png` |

Se usará por ahora el banner de tienda sin alterar su texto. Cada imagen debe
optimizarse, tener dimensiones declaradas y conservar una copia de origen solo
si resulta necesaria para futuras ediciones.

### 3.3 No modificar

- `newthread.php`, `newreply.php` e `inc/datahandlers/post.php`;
- `/op/tienda_kuros.php` y su plantilla;
- `mybb_op_fichas` para almacenar Calabazas;
- `global.php` para calcular saldos, rankings o recompensas;
- `/op/logros.php` o cualquier tabla de Logros.

## 4. Esquema y migración

### Tarea 1: migración revisable

**Archivo:** `docs/halloween2026_migration.sql`

Crear con `ENGINE=InnoDB` y `utf8mb4`:

1. `mybb_op_halloween2026_config`;
2. `mybb_op_halloween2026_saldos`;
3. `mybb_op_halloween2026_movimientos`;
4. `mybb_op_halloween2026_publicaciones`;
5. `mybb_op_halloween2026_bonos_diarios`;
6. `mybb_op_halloween2026_tienda_items`;
7. `mybb_op_halloween2026_compras`;
8. `mybb_op_halloween2026_compra_lineas`.

`publicaciones` complementa el diseño inicial y debe guardar como mínimo:

- `pid` como clave primaria;
- `tid`, `uid`, `original_fid` y `original_dateline`;
- `original_role_eligible`;
- `is_first_post` y `original_prefix_id`;
- estado de visibilidad observado;
- fecha y motivo de la última reconciliación.

Esta tabla permite demostrar que un post fue creado fuera de rol y evitar que
un movimiento posterior hacia `FID 10` produzca una recompensa retroactiva.

### Tarea 2: restricciones e índices

La migración incluirá:

- `UNIQUE (idempotency_key)` en movimientos y compras;
- `UNIQUE (reverses_movement_id)` para impedir dos reversiones del mismo
  movimiento;
- `PRIMARY KEY (uid, event_day)` en bonos diarios;
- `UNIQUE (objeto_id, uid)` ya existente en inventario, validado como
  precondición;
- índices de movimientos por `(uid, created_at)`, `(source_type, source_id)`,
  `source_pid` y `source_tid`;
- índices de compras por usuario, fecha y estado;
- restricciones de aplicación para impedir cantidades cero en movimientos y
  cantidades menores de uno en líneas de compra.

MySQL 5.7 no aplica todos los `CHECK` como MySQL moderno. Las invariantes se
validarán también en PHP y mediante tipos e índices compatibles con 5.7.

### Tarea 3: datos iniciales

Sembrar una sola fila de configuración:

| Campo | Valor UTC |
|---|---|
| Inicio de obtención | `2026-10-26 05:00:00` |
| Fin exclusivo de obtención | `2026-11-10 05:00:00` |
| Inicio de tienda | Apertura manual después de la prueba inicial |
| Fin exclusivo de tienda | `2026-11-16 23:00:00` |
| Zona de presentación | `Europe/Madrid` |

La obtención se habilita exactamente el 26 de octubre a las 06:00 de Madrid.
La tienda permanece cerrada al abrir el evento y se habilita después de una
compra completa de prueba. Esta separación es una condición de despliegue, no
un sistema general de interruptores de emergencia.

Sembrar el catálogo:

| Slug | Nombre | Coste | Recompensa |
|---|---|---:|---|
| `cofre-calabaza` | Cofre Calabaza | 7 | `CFRHLW` x1 |
| `nika` | 1 Nika | 5 | 1 Nika |
| `oficio-100` | 100 puntos de oficio | 3 | 100 puntos |
| `berries-50000` | 50.000 Berries | 1 | 50.000 Berries |

Los cuatro se crean sin stock global y sin límite personal. La configuración
global permanece en estado `draft` hasta que la aplicación y las pruebas estén
listas.

### Tarea 4: despliegue no destructivo

La migración se ejecutará manualmente después de revisarla y respaldar la
base. No incluirá `DROP`, `TRUNCATE` ni borrado de filas. Repetirla debe fallar
de forma visible o verificar primero que las tablas ya existen; nunca debe
reinicializar un evento con datos.

**Verificación:** revisar `SHOW TABLE STATUS`, `SHOW INDEX`, la fila de
configuración y los cuatro artículos esperados antes de activar el plugin.

## 5. Dominio y calendario

### Tarea 5: API interna estable

**Archivo:** `inc/plugins/lib/halloween2026.php`

Exponer funciones con prefijo `opg_hlw_`, sin estado global mutable:

```php
opg_hlw_config();
opg_hlw_estado(DateTimeImmutable $now = null);
opg_hlw_event_day($dateline);
opg_hlw_ficha_elegible($uid, $forUpdate = false);
opg_hlw_forum_es_rol($fid, $parentlist);
opg_hlw_cargar_publicacion($pid);
opg_hlw_reconciliar_post($pid, $reason);
opg_hlw_reconciliar_thread($tid, $reason);
opg_hlw_aplicar_movimientos($uid, array $movements);
opg_hlw_revertir_movimiento($movementId, $reason, $actorUid = null);
```

La fecha se deriva siempre del `dateline` original. Para el día del evento:

1. convertir UNIX a `Europe/Madrid`;
2. restar seis horas;
3. usar `Y-m-d`.

Pruebas obligatorias: 05:59 y 06:00 del 26 de octubre, cambio de día normal,
fin del 9 de noviembre y conversión UTC.

### Tarea 6: elegibilidad de ficha

Una ficha es elegible solamente si:

```text
existe
AND muerto = 0
AND aprobada_por NOT IN ('sin_aprobar', 'pendiente_reset')
```

La misma función se usará en hooks, página y compra. No se reutilizará
`does_ficha_exist()` porque actualmente no comprueba `muerto` ni
`pendiente_reset`.

Las fichas no elegibles reciben `error_no_permission()` en las vistas
personales. Sus registros históricos internos no se muestran, pero se conservan
para auditoría.

### Tarea 7: libro y saldo

Toda escritura económica usará una transacción:

1. crear saldo cero con `INSERT IGNORE` si no existe;
2. bloquear la fila con `SELECT ... FOR UPDATE`;
3. insertar los movimientos con claves idempotentes;
4. actualizar `ganado_total`, `gastado_total` y `saldo` solo por filas nuevas;
5. confirmar o revertir el lote completo.

Tipos iniciales de origen:

```text
post, thread, daily, staff_gift, staff_adjustment,
reversal, purchase, refund, contest
```

Una reversión puede dejar saldo negativo. En ese estado se conserva el
historial y se bloquean compras, sin modificar movimientos anteriores.

## 6. Registro automático

### Tarea 8: plugin y hooks de inserción

**Archivo:** `inc/plugins/opg_halloween2026.php`

Registrar:

```php
$plugins->add_hook('datahandler_post_insert_post_end', 'opg_hlw_hook_post');
$plugins->add_hook('datahandler_post_insert_thread_end', 'opg_hlw_hook_post');
```

El adaptador extrae el PID confirmado y llama una sola API de reconciliación.
Ambos hooks pueden observar el primer post; la clave por PID/TID evita
duplicados.

Fuera del intervalo de obtención, el callback termina tras cargar la
configuración y comprobar la fecha. No calcula rankings ni carga el catálogo.

### Tarea 9: matriz de premios

Para una publicación elegible:

| Condición | Movimiento |
|---|---:|
| Todo post visible de rol | `+1 post` |
| Primer post con prefijo Común/Aventura | `+2 thread` |
| Primer post con prefijo Spooky | `+3 thread` |
| Primer post elegible del día | `+1 daily` |

Claves base:

```text
halloween2026:post:{pid}
halloween2026:thread:{tid}
halloween2026:daily:{uid}:{event_day}:{pid}
```

El registro en `publicaciones` se crea incluso si el post original no estaba
en rol, siempre que su fecha caiga dentro del evento. Esto conserva el origen
necesario para impedir premios al moverlo después.

### Tarea 10: bono diario sin borrado

Reservar `(uid, event_day)` dentro de la misma transacción del post. Si dos
publicaciones compiten, solo la que inserte primero la clave puede crear el
movimiento.

Si el post propietario deja de ser válido:

1. crear el contramovimiento;
2. localizar el siguiente post elegible del día;
3. actualizar la reserva con el nuevo PID y movimiento;
4. dejar la reserva en estado `vacant` si no existe sustituto.

No se elimina la reserva. Cada cambio queda explicado por los movimientos y
los metadatos de reconciliación.

### Tarea 11: moderación

Usar los mismos hooks ya comprobados en `op_bitacora.php` para:

- aprobar y desaprobar posts/temas;
- borrado suave y restauración;
- observar un borrado definitivo iniciado por MyBB;
- mover, fusionar o dividir temas cuando el hook entregue IDs suficientes.

Los callbacks no aplican cantidades directamente. Solo llaman a
`opg_hlw_reconciliar_post()` o `opg_hlw_reconciliar_thread()`.

El sistema de Halloween no ejecutará la eliminación de contenido. Si MyBB o un
moderador elimina contenido, Halloween conserva previamente PID, TID, UID y
movimientos para generar una reversión posterior.

### Tarea 12: reconciliación y backfill controlado

Crear en la consola una comprobación de rango para staff que:

- previsualice publicaciones del intervalo sin movimientos;
- muestre diferencias antes de aplicar;
- procese lotes pequeños con cursor por PID;
- use las mismas claves idempotentes de los hooks;
- nunca vuelva a premiar una publicación ya observada.

Esto cubre despliegues tardíos o una desactivación temporal del plugin sin
introducir una tarea pesada en cada carga de página.

## 7. Tienda

### Tarea 13: adaptadores de entrega

**Archivo:** `inc/plugins/lib/halloween2026_store.php`

No llamar directamente a `darObjeto()`, `darNikas()`, `darBerries()`,
`darOficios()` ni `log_audit_currency()`: esos helpers heredados vuelven a
leer saldos, escriben valores absolutos y no propagan todos los errores.

Crear adaptadores internos que se ejecuten dentro de la transacción:

- `opg_hlw_store_give_object($uid, 'CFRHLW', $quantity)`;
- `opg_hlw_store_add_ficha_currency($uid, 'nika', $amount)`;
- `opg_hlw_store_add_ficha_currency($uid, 'berries', $amount)`;
- `opg_hlw_store_add_ficha_currency($uid, 'puntos_oficio', $amount)`.

Las monedas se actualizarán de forma relativa (`campo = campo + cantidad`)
tras bloquear la ficha. Los puntos de oficio siempre se entregarán como
`puntos_oficio`; esta tienda no los convertirá en experiencia.

Cada entrega escribirá `mybb_op_audit_general` con saldo anterior, saldo
nuevo, compra y cantidad. Todos los errores deben lanzar una excepción para
permitir `ROLLBACK`.

### Tarea 14: compra transaccional

`opg_hlw_store_purchase()` recibirá solo IDs de artículo, cantidades, UID y
clave idempotente. El flujo será:

1. validar ficha, CSRF y ventana de tienda;
2. iniciar transacción;
3. bloquear saldo y ficha;
4. bloquear filas de catálogo InnoDB;
5. volver a calcular precios y total;
6. rechazar saldo negativo o insuficiente;
7. insertar compra pendiente y líneas con snapshot;
8. entregar todas las recompensas;
9. crear movimiento `purchase` y actualizar el saldo de Calabazas;
10. marcar compra completada;
11. confirmar.

Ante cualquier error se ejecuta `ROLLBACK`; no se cobra ni se conserva una
entrega parcial. Una recarga con la misma clave devuelve el recibo ya creado.

El carrito tendrá un máximo razonable por línea y por compra para impedir
enteros desbordados o peticiones enormes, aunque el producto no tenga límite
comercial.

### Tarea 15: interfaz de tienda

- Catálogo en cuadrícula y carrito lateral en escritorio.
- Resumen desplegable en móvil, sin tapar acciones ni contenido.
- Stepper numérico con botones de reducir/aumentar y entrada accesible.
- Total y saldo restante como vista previa; el servidor conserva autoridad.
- Confirmación clara antes del POST.
- Patrón POST/Redirect/GET hacia `?vista=tienda&compra={id}`.
- Recibo con saldo anterior, artículos, cantidades, coste total, recompensas
  entregadas y saldo final.

JavaScript mejora la experiencia, pero la compra debe funcionar mediante
formulario aunque JavaScript falle.

## 8. Página del evento

### Tarea 16: controlador por vista

**Archivo:** `op/halloween2026.php`

Aceptar solamente:

```text
?vista=evento
?vista=estadisticas
?vista=tienda
?vista=actividad
```

La pestaña seleccionada se renderiza en servidor y solo ejecuta sus consultas.
Los enlaces no usarán fragmentos `#...`, evitando saltos verticales.

Con JavaScript disponible, HTMX cargará solo el panel activo y actualizará
la URL mediante historial. Cada tab conservará un `href` real para funcionar
sin JavaScript, abrirse en otra pestaña y admitir enlaces directos.

Antes de renderizar la página completa o cualquier fragmento HTMX, las cuatro
vistas exigirán sesión y ficha activa y aprobada. Invitados y personajes sin
ficha elegible recibirán la respuesta de acceso denegado del proyecto; no se
renderizarán Evento, Estadísticas, Tienda ni Actividad.

### Tarea 17: contenido y estados

La cabecera muestra estado, fechas, saldo cuando proceda y enlaces a:

- evento: `showthread.php?tid=9471`;
- historias: `showthread.php?tid=9535`;
- monstruos: `showthread.php?tid=9536`.

Cada estado controla acciones en servidor:

- próximamente;
- obtención activa;
- reclamaciones/tienda abierta;
- archivado.

No confiar en fechas calculadas por el navegador.

### Tarea 18: estadísticas

**Archivo:** `inc/plugins/lib/halloween2026_stats.php`

Generar desde movimientos y compras:

- ganado neto, gastado y circulación;
- participantes;
- posts, temas, Spooky y bonos diarios;
- compras;
- top 10 y ranking paginado.

`ganado_total` incluirá ganancias automáticas, presets, regalos y premios
manuales positivos. Los gastos de tienda no lo reducen; las reversiones y
correcciones de ganancias sí ajustan su valor neto.

Orden principal:

```text
ganado_total DESC, fecha_ultimo_total ASC, uid ASC
```

La fila de saldo guardará `fecha_ultimo_total` para mantener un desempate
estable compatible con MySQL 5.7. Se actualiza cuando cambia el ganado neto.

Resumen y top 10 usarán caché MyBB durante 120 segundos. Los movimientos que
cambien estadísticas invalidarán la clave; nunca se contarán posts completos
al abrir la página.

### Tarea 19: actividad personal

Mostrar saldo, ganado, gastado, desglose por fuente e historial paginado.
Cada fila usa signo, texto e icono además de color. Los enlaces a PID/TID solo
se muestran si siguen siendo consultables.

HTMX se usará también para paginación de actividad y ranking. Cada respuesta
parcial tendrá una ruta completa equivalente y no dependerá de anclas.

## 9. Consola de staff

### Tarea 20: acceso y estructura

**Archivo:** `op/staff/halloween2026.php`

Requerir sesión e `is_staff($uid)` antes de procesar cualquier vista o acción.
Todas las acciones POST verifican `my_post_key`.

Tabs:

- Resumen;
- Personajes;
- Movimientos;
- Regalos;
- Tienda;
- Compras;
- Concursos;
- Reconciliación;
- Exportar.

Tabs, filtros, búsquedas y paginación de la consola usarán fragmentos HTMX,
siguiendo el patrón de `consola_aventuras.php`. Las acciones económicas no
confiarán en HTMX: seguirán siendo POST protegidos, idempotentes y con una
respuesta navegable sin JavaScript.

### Tarea 21: movimientos manuales

Permitir premio, ajuste, reversión y reembolso con:

- personaje validado;
- cantidad positiva en el formulario y sentido elegido por acción;
- motivo obligatorio;
- referencia opcional;
- actor UID y fecha;
- clave idempotente del formulario.

No ofrecer edición directa de saldo ni eliminación de movimientos. Una
reversión crea un movimiento opuesto enlazado al original.

### Tarea 22: regalos y concursos

Los repartos de Kuro y premios de concursos usarán un identificador estable,
por ejemplo `kuro-2026-10-31-a` o `historias-2026-participacion`. La consola
mostrará una previsualización de receptores e importe total antes de confirmar.

Reintentar el mismo reparto no duplica Calabazas ni cofres. La entrega de
cofres de concursos usará el mismo adaptador transaccional de inventario.

Añadir a la consola estos presets, todos limitados a una concesión por UID:

| Clave de preset | Concepto | Cantidad |
|---|---|---:|
| `historias` | Participar en el Concurso de Historias de Miedo | 10 |
| `monstruos` | Participar en el Concurso de Monstruos Salvajes | 10 |
| `aventura-spooky` | Completar una Aventura `Spooky` | 5 |
| `avatar-foro` | Usar avatar temático en el foro | 5 |
| `avatar-discord` | Usar avatar temático en Discord | 5 |

La idempotencia usará `halloween2026:preset:{clave}:{uid}` y un índice único.
La interfaz consultará el estado para señalar los presets ya entregados, pero
el servicio y la base de datos seguirán siendo la autoridad. No se añadirá
ningún preset para **Llamada del Grand Line**. No se pedirá enlace ni evidencia
en estos presets: el moderador confirma la comprobación al concederlos.

### Tarea 23: catálogo y compras

Staff puede activar/desactivar artículos, editar textos, coste, orden, fechas,
stock y límites. Cambiar un artículo no altera las líneas históricas de una
compra.

El catálogo inicial no tendrá stock ni límite por personaje. Las compras son
ilimitadas mientras haya saldo y la tienda esté abierta; los límites técnicos
por petición solo protegen al servidor y no constituyen un cupo acumulado.

Las compras se consultan por número, personaje, estado o artículo. Un fallo
interno muestra un mensaje genérico y conserva el detalle solo en logs.

### Tarea 24: exportación

Exportar CSV en UTF-8 para movimientos, saldos, compras y ranking. Las
exportaciones son consultas de solo lectura y aplican filtros/paginación para
no cargar todo el evento en memoria.

## 10. Plantillas, estilos y recursos

### Tarea 25: instalación de recursos

Seguir el patrón de `op_bitacora.php`:

- definición central de plantillas;
- importación al set global y a `One_Piece_Gaiden_Templates`;
- stylesheet registrado en el tema 3 y cacheado;
- activación idempotente que actualiza recursos sin duplicarlos.

Desactivar el plugin no elimina tablas, movimientos, plantillas ni estilos.
No habrá una rutina de desinstalación destructiva.

### Tarea 26: diseño responsive

Aplicar `docs/style.md` y los tokens existentes:

- marco de página del foro con ancho fluido;
- titulares `moonGetHeavy` con contorno legible;
- naranja para acciones y morado como acento secundario;
- bordes negros consistentes, radio máximo habitual de 8px;
- pestañas con todas sus divisiones verticales;
- tablas convertidas en filas escaneables en móvil;
- focos visibles, `aria-live` en resultados y estados vacíos claros;
- dimensiones estables para banners, productos, iconos y carrito.

No colocar tarjetas dentro de tarjetas ni texto explicando la interfaz. La
vista inicial será el evento real, no una landing promocional.

## 11. Seguridad y rendimiento

### Tarea 27: seguridad

- CSRF en compra y toda acción de staff.
- Escape de SQL con enteros forzados y `$db->escape_string()`.
- Escape HTML con `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Validación en servidor de fechas, precios, cantidades, recompensas y ficha.
- Sin nombres de funciones ni SQL ejecutable dentro de `reward_json`.
- Mensajes al usuario sin SQL, rutas internas ni credenciales.
- Auditoría de actor, objetivo, motivo, compra y valores anterior/nuevo.
- Límites de paginación y tamaño de carrito.

### Tarea 28: presupuesto de consultas

Publicar un post durante el evento debe requerir:

- una lectura conjunta de post/tema/foro/ficha;
- una lectura o inserción de `publicaciones`;
- una transacción breve sobre saldo, movimientos y posible bono diario.

Fuera del evento no se escriben publicaciones ni movimientos. La página
consulta solo la vista activa. Ranking e historial siempre están indexados y
paginados.

## 12. Pruebas

### Tarea 29: dominio

- límites exactos de obtención y tienda;
- horario de Madrid y corte de las 06:00;
- FID 10, descendiente real y foro no elegible;
- prefijos 1, 3, 15 y sin prefijo;
- ficha aprobada, sin aprobar, pendiente de reset y muerta;
- claves idempotentes y cantidades de cada combinación.

### Tarea 30: integración de publicaciones

- respuesta normal y primer post diario;
- tema Común, Aventura y Spooky;
- doble ejecución de hooks;
- dos posts simultáneos del mismo personaje;
- aprobación posterior al cierre con `dateline` válido;
- desaprobación, restauración y borrado iniciado por MyBB;
- movimiento fuera de rol y regreso;
- movimiento desde fuera hacia rol sin premio retroactivo;
- reasignación de bono diario.

### Tarea 31: tienda

- compra de cada uno de los cuatro productos;
- varias unidades del mismo producto;
- saldo exacto, insuficiente y negativo;
- doble clic y reenvío de idempotencia;
- alteración de precio mientras el carrito está abierto;
- ficha que deja de ser elegible antes de confirmar;
- fallo simulado tras cada paso: siempre revierte compra, entrega y cobro;
- compra concurrente con el mismo saldo;
- cada preset se concede una sola vez por personaje, incluso ante doble envío;
- la consola no ofrece un preset de Llamada del Grand Line;
- `CFRHLW` ausente mantiene el producto deshabilitado. Si el objeto existe,
  puede venderse aunque su tabla de apertura se configure posteriormente: la
  compra de Halloween solo añade el cofre cerrado al inventario.

### Tarea 31.1: prueba real previa

Mientras el evento permanezca en `draft`, la consola puede habilitar durante
una hora una prueba exclusiva para el UID `315`. Solo cuentan publicaciones
nuevas realizadas después de iniciar esa ejecución y pasan por el mismo hook
y la misma reconciliación que producción.

Un reply válido puede producir dos movimientos separados: `+1` por el post y
`+1` por ser el primero del día. Un tema añade además `+2`, o `+3` si usa el
prefijo Spooky. Al finalizar se deshabilita primero la excepción y se crean
contramovimientos para todas sus recompensas. No se elimina ninguna fila y no
se puede activar el evento mientras la prueba siga abierta.

### Tarea 32: interfaz

Verificar con capturas y navegación real:

- escritorio ancho, 1366 px, tableta y móvil de 375 px;
- tabs sin salto de scroll;
- banners sin desplazamiento de contenido;
- carrito largo, historial vacío y ranking paginado;
- textos largos sin desbordar;
- teclado, foco y lector de pantalla básico;
- errores, carga, tienda cerrada y evento archivado.

## 13. Orden de entrega

1. Crear y validar `CFRHLW` fuera de esta migración. **Completado.**
2. Escribir, revisar y ejecutar la migración no destructiva. **Completado.**
3. Descargar y optimizar activos.
4. Implementar dominio, calendario y pruebas puras.
5. Implementar libro, saldos y publicaciones observadas.
6. Implementar hooks de inserción.
7. Implementar moderación, reconciliación y backfill.
8. Implementar estadísticas e historial.
9. Implementar adaptadores y compra transaccional.
10. Implementar consola de staff.
11. Implementar página, plantillas, CSS y JavaScript.
12. Probar concurrencia y fallos simulados con el evento en `draft`.
13. Sincronizar plantillas y stylesheet.
14. Ejecutar reconciliación de previsualización y revisar resultados.
15. Habilitar la obtención el 26 de octubre a las 06:00 de Madrid.
16. Mantener la tienda cerrada, probar una compra completa y abrirla después
    de confirmar la entrega y el recibo.

## 14. Despliegue y observación

### Antes de abrir

- respaldo de base confirmado;
- tablas e índices verificados;
- `CFRHLW` existente y abrible;
- plantillas y stylesheet sincronizados;
- fechas mostradas correctamente en Madrid;
- usuario normal y `is_staff` probados;
- una compra real de prueba revertida mediante contramovimiento, sin alterar
  ni borrar el historial.

### Durante el evento

Revisar diariamente:

- errores del plugin;
- movimientos duplicados, que deben permanecer en cero;
- diferencias entre suma del libro y saldos materializados;
- compras pendientes o fallidas;
- latencia del hook y de la página;
- posts dentro del intervalo sin fila en `publicaciones`.

### Cierre

Al finalizar obtención, el calendario detiene premios sin desactivar el plugin.
Al cerrar la tienda, se bloquean nuevos POST de compra. La página pasa a modo
archivo y conserva ranking, actividad y recibos según los permisos definidos.

## 15. Criterio de finalización

La implementación está lista cuando:

- todas las pruebas de dominio e integración pasan;
- un post, tema y bono diario producen exactamente los movimientos esperados;
- no hay premios retroactivos por mover temas hacia rol;
- ninguna repetición duplica saldo o compra;
- las cuatro recompensas se entregan y auditan de forma atómica;
- las fichas no elegibles no generan actividad ni acceden a ninguna vista de
  `/op/halloween2026.php`;
- el ranking no cambia al gastar y usa consultas indexadas;
- las ganancias automáticas, presets y regalos aparecen en el ranking, y las
  correcciones ajustan su total neto;
- cada preset solo puede concederse una vez por personaje;
- staff puede corregir mediante contramovimientos sin editar saldos;
- no se almacena ninguna Calabaza en `mybb_op_fichas`;
- no existe ninguna operación destructiva sobre el historial del evento;
- la interfaz queda verificada en escritorio y móvil.
