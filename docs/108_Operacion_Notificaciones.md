# Notificaciones: guía de operación

Estado: **implementado** (issues #1 a #15 y #28 fusionadas en `main`; esta guía es la issue #16).

Documentos de la funcionalidad: requisitos en `108_Requirements_Notificaciones.md`, diseño en `208_DesignPlan_Notificaciones.md`, plan de implementación en `308_ImplementationPlan_Notificaciones.md` (este último es local, no se versiona). Esta guía describe lo que existe en el código, no lo planeado. Cuando algo difiera de un documento de diseño, manda el código y esta guía.

## 1. Qué es el sistema

Un único sistema de avisos para todo el foro. Cualquier módulo avisa a un usuario con una llamada a `opg_notificar()`. El usuario ve una campana en el header con el número de avisos sin leer y una página, `/op/notificaciones.php`, con la lista, los filtros y los botones **Visto** y **Marcar todas como vistas**.

Piezas:

| Pieza | Archivo | Qué hace |
|---|---|---|
| Tablas | `docs/notificaciones_migration.sql` | `mybb_op_notificaciones` (una fila por aviso) y `mybb_op_notificaciones_contador` (una fila por usuario con `sin_leer` e `importantes`). InnoDB, utf8mb4, `utf8mb4_unicode_ci`. |
| Plugin | `inc/plugins/opg_notificaciones.php` | Crea las tablas si faltan, instala la plantilla de la campana y el marcador en `header`, y registra los hooks `global_start` (precarga la plantilla) y `global_intermediate` (construye `$op_notificaciones_header`). |
| Librería de envío y lectura | `inc/plugins/lib/notificaciones.php` | `opg_notificar`, `opg_notif_contar`, `opg_notif_listar`, `opg_notif_marcar_vista`, `opg_notif_marcar_todas`, `opg_notif_reconciliar`. |
| Catálogo | `inc/plugins/lib/notificaciones_catalogo.php` | Tipos, textos, iconos, importancia y enlaces. Lógica pura, sin base de datos. |
| Vistas | `inc/plugins/lib/notificaciones_vistas.php` | HTML de la lista, filas y filtros. |
| Página | `op/notificaciones.php` | Lista y acciones HTMX (`listar`, `mas`, `visto`, `todas`). |
| Plantillas | `op_notificaciones.html`, `op_notificaciones_header.html`, `header.html` (en `templates/One_Piece_Gaiden_Templates/`) | Página, campana y marcador `{$op_notificaciones_header}` junto a `{$op_temas_header}`. |
| Estilos | `jscripts/notificaciones.css` | Diseño de la página (una columna en móvil, modo oscuro). La campana lleva su `<style>` dentro de su plantilla. |
| Tarea | `inc/tasks/notificaciones.php` y `docs/notificaciones_tarea_migration.sql` | Retención diaria y reconciliación de contadores. |
| Migración del Tablón | `op/staff/migrar_notificaciones.php` y `inc/plugins/lib/notificaciones_migracion.php` | Copia las novedades antiguas del Tablón a la tabla global. |
| Pruebas | `tests/notificaciones/` | 21 archivos entre pruebas y ayudantes (puras y con MySQL real). |

Reglas de diseño que conviene recordar:

- **Un solo punto de entrada.** Ningún módulo hace `INSERT` directo en `mybb_op_notificaciones`. La única excepción es la migración del Tablón, que debe conservar fecha y estado de lectura y no avisar a nadie.
- **El fallo de un aviso no rompe la acción.** `opg_notificar` captura todo, registra con `error_log` (prefijo `[notificaciones]`) y devuelve `false`. Nunca lanza excepciones ni escribe salida.
- **Contador materializado.** El header hace como máximo una consulta por clave primaria del contador, cacheada por petición. Los invitados no consultan nada.
- **El `uid` sale siempre de la sesión** (`$mybb->user['uid']`). Con modo espejo o cambio de personaje es la cuenta activa.
- **Textos sin HTML guardado.** En la base solo hay datos (`datos_json`); el texto se construye al mostrar y se escapa una vez.
- **Las notificaciones son para usuarios.** Los contadores de trabajo del staff (fichas en cola, peticiones, bugs, entregas) no generan notificaciones.

## 2. Tipos del catálogo

El catálogo tiene 19 tipos. Categoría, origen e importancia viven en el catálogo y se copian a la fila al crearla.

| Tipo | Categoría | Origen | Importancia | Agrupable | Quién lo envía |
|---|---|---|---|---|---|
| `invitacion_nueva` | aventuras | usuario | importante | no | Tablón (`lib/aventuras.php`) |
| `candidatura_nueva` | aventuras | usuario | importante | no | Tablón |
| `propuesta_aceptada` | aventuras | usuario | normal | no | Tablón |
| `propuesta_rechazada` | aventuras | usuario | normal | no | Tablón |
| `propuesta_retirada` | aventuras | usuario | normal | no | Tablón |
| `propuesta_cancelada` | aventuras | usuario | normal | no | Tablón |
| `propuesta_vencida` | aventuras | sistema | normal | no | Tablón |
| `aventura_aprobada` | aventuras | gestión | normal | no | Tablón (staff) |
| `aventura_denegada` | aventuras | gestión | importante | no | Tablón (staff) |
| `narrador_salio` | aventuras | usuario | importante | no | Tablón |
| `entrega_enviada` | aventuras | usuario | normal | no | Tablón (`lib/aventuras_entregas.php`, #11) |
| `entrega_rechazada` | aventuras | gestión | importante | no | Tablón (staff) |
| `recompensas_entregadas` | recompensas | gestión | normal | no | Tablón (staff) |
| `oferta_por_vencer` | aventuras | sistema | importante | no | Tarea `inc/tasks/aventurasux.php` |
| `bitacora_post` | temas | usuario | normal | **sí** | Bitácora |
| `bitacora_ronda_iniciada` | temas | usuario | normal | no | Bitácora |
| `bitacora_narrador_asignado` | temas | usuario | normal | no | Bitácora |
| `bitacora_narrador_cambiado` | temas | usuario | normal | no | Bitácora |
| `bitacora_narrador_retirado` | temas | usuario | normal | no | Bitácora |

Notas:

- Categorías válidas (lista fija contra la que se validan los filtros): `aventuras`, `ficha`, `recompensas`, `tecnicas`, `peticiones`, `temas`. Hoy solo se usan `aventuras`, `recompensas` y `temas`; las otras tres esperan a las herramientas de gestión.
- Orígenes válidos: `gestion`, `usuario`, `sistema`. El filtro de la página ofrece Todas, De gestión y De usuarios; los de origen `sistema` solo salen en Todas.
- `bitacora_post` es el único agrupable: varias publicaciones nuevas en un tema seguido se resumen en una fila con `cantidad`.
- Un tipo que ya no esté en el catálogo se muestra como "Actualización", con el icono `fa-bell` y sin enlace, y no rompe la página.
- Una notificación de tipo `importante` hace que la campana lo diga con texto accesible ("N sin leer, M importantes") y con una clase de color; el número no cambia.

## 3. Cómo añadir un tipo nuevo

No requiere SQL. Solo código y pruebas.

1. Añadir una entrada en `opg_notif_catalogo()` (`inc/plugins/lib/notificaciones_catalogo.php`):

   ```php
   'mi_tipo' => array(
       'categoria' => 'aventuras',      // una de opg_notif_categorias()
       'origen' => 'gestion',           // gestion | usuario | sistema
       'importancia' => 'normal',       // normal | importante
       'agrupable' => false,
       'icono' => 'fa-gift',            // clase de Font Awesome
       'texto' => function ($d, $n) { return opg_notif_actor($d) . ' hizo algo en ' . opg_notif_nombre_aventura($d) . '.'; },
       'url' => 'opg_notif_url_aventura',   // nombre de función; devuelve ruta interna o ''
   ),
   ```

2. El `texto` devuelve texto **plano** (sin HTML). Se escapa una sola vez en `opg_notif_texto()`. Usa las claves de datos que el catálogo ya lee: `peticion_id`, `tema`, `isla`, `tier`, `actor`, `narrador`, `motivo`, `dias`, `tid`, `nombre`. Si necesitas una clave nueva, léela con `opg_notif_dato()` y documéntala en el encabezado del catálogo.
3. Si la categoría o el origen son nuevos, añádelos a `opg_notif_categorias()` u `opg_notif_origenes()` en `lib/notificaciones.php` y a las etiquetas de los filtros en `lib/notificaciones_vistas.php`. Si no, el filtro no los conocerá.
4. Los enlaces son rutas internas relativas (`op/pagina.php?id=1`). Pasan por `opg_notif_url_interna()`; lo que no sea una ruta interna se descarta.
5. Si el tipo es agrupable, la clave se genera como `tipo:grupo` con la opción `agrupar_por`, y su largo máximo es 111 caracteres (se reservan 17 para el sufijo `:leida:ID`).
6. Añade el tipo a `tests/notificaciones/catalogo.php` y la prueba de envío del módulo que lo use.
7. Despliegue: solo subir `notificaciones_catalogo.php` (y la librería si tocaste categorías). Una fila con un tipo aún desconocido para el servidor sale como "Actualización", así que subir primero el catálogo y después el módulo que envía.

## 4. Cómo conectar otra herramienta

1. Declara el tipo en el catálogo (apartado 3).
2. En el módulo, carga la librería de forma defensiva (el sistema puede no estar instalado) y llama una sola vez por acción con todos los destinatarios. Ejemplos reales: `opg_av_notif_global()` en `lib/aventuras.php` y `op_bitacora_notif_disponible()` en `op_bitacora/functions.php`.

   ```php
   opg_notificar($uids, 'mi_tipo', array('peticion_id' => $id, 'actor' => $nombre_actor), array(
       'actor' => $uid_actor,                    // no se avisa al actor, salvo 'incluir_actor' => true (ver nota)
       'clave' => 'modulo:' . $id . ':evento',   // obligatoria en tipos no agrupables
   ));
   ```

3. Reglas obligatorias:
   - **Clave de evento estable.** Repetir la acción con la misma clave no duplica el aviso (es la clave única `(uid, clave_evento)`). Solo letras, números y `_ . : -`, hasta 128 caracteres. La comparación no distingue mayúsculas, y la clave no incluye el tipo: si la clave ya existe para ese destinatario con otro tipo, el aviso nuevo se ignora y se registra el conflicto. Empieza la clave por el módulo o el tipo y lleva el id del objeto.
   - **Una llamada por acción**, con todos los destinatarios (la librería los ordena y bloquea siempre en el mismo orden). No llames destinatario a destinatario dentro de una transacción: dos acciones cruzadas se interbloquearían.
   - **Avisos de origen usuario, fuera de la transacción del módulo**, después del `COMMIT` y con un tema (o grupo) por llamada. Así un fallo del aviso nunca revierte la acción. La Bitácora lo hace así (`op_bitacora_notificar`).
   - **Datos sin información privada.** Solo guarda lo que el destinatario pueda ver. Los títulos de temas del foro solo se guardan si pasas `'tema_publico' => true` y el tema está visible; si no, el texto dice "el tema #tid".
   - **Nunca falles por el aviso.** Ignora el `false`: el error ya quedó registrado.
   - `'silenciosa' => true` evita crear el aviso en acciones técnicas; cada uso debe justificarse donde se llama.
4. No hagas `INSERT` directo en las tablas ni toques `mybb_op_notificaciones_contador`.
5. No añadas lógica a `global.php` (regla del proyecto). Si necesitas un hook, usa un plugin.
6. Escribe pruebas en `tests/notificaciones/` y, si el módulo usa transacciones, una prueba con MySQL real (ver `tablon_db.php` y `bitacora_db.php`).

## 5. Si el contador se desfasa

Síntomas: la campana muestra un número distinto al de notificaciones sin leer de la página.

Causas posibles y límites conocidos:

- Un envío en autocommit (sin transacción de quien llama) confirma la fila y el contador en dos sentencias. Si la reconciliación cuenta justo entre las dos, el contador queda uno **por encima** (nunca por debajo) hasta la siguiente reconciliación.
- Un contador recién creado empieza en el valor de su primer aviso y no cuenta avisos sin leer que ya existieran sin contador (solo pasa con los copiados desde el Tablón; la migración reconcilia al terminar).
- Con `innodb_autoinc_lock_mode = 2`, en lotes parciales bajo concurrencia, la identificación de filas nuevas puede no ser exacta (se registra un aviso). MySQL 5.7 usa el modo 1 por defecto.
- Edición manual de tablas o restauraciones de copias de seguridad.

Autorreparación: `opg_notif_contar()` recuenta y reescribe el contador de un usuario si no existe su fila o si su columna `actualizado` (fecha de la última reconciliación) tiene más de 24 horas. La tarea diaria (apartado 6) reconcilia además a los usuarios con actividad reciente. Un desfase dura, como mucho, un día.

Para repararlo ya, sin código, desde phpMyAdmin (fuerza el recuento la próxima vez que el usuario cargue una página):

```

> **Nota (decision del usuario):** los avisos de acciones del staff sobre una ficha (ficha modificada, aprobada o rechazada, Akuma, tecnicas, virtudes y objetos, peticiones y bugs, recompensas y salarios, cofres y resets) se envian con `'incluir_actor' => true`. Si el staff cambia algo en su propia ficha, tambien recibe el aviso, para que quede en su historial. Los avisos de la Bitacora y del Tablon no lo hacen: quien publica o actua no se avisa a si mismo.sql
UPDATE mybb_op_notificaciones_contador SET actualizado = 0 WHERE uid = 123;
-- o, para todos:
UPDATE mybb_op_notificaciones_contador SET actualizado = 0;
```

Para comprobar un usuario:

```sql
SELECT COUNT(*) AS sin_leer, COALESCE(SUM(importancia = 'importante'), 0) AS importantes
  FROM mybb_op_notificaciones WHERE uid = 123 AND leida_en IS NULL;
SELECT sin_leer, importantes, FROM_UNIXTIME(actualizado) FROM mybb_op_notificaciones_contador WHERE uid = 123;
```

No hace falta borrar ni recrear la fila del contador.

## 6. La tarea de retención

`inc/tasks/notificaciones.php`, registrada como "OPG: Mantenimiento de notificaciones" por `docs/notificaciones_tarea_migration.sql`: todos los días a las 04:30. Nunca corre durante una petición de usuario (solo la carga el planificador de MyBB).

Pasos, en este orden:

1. Borra las notificaciones de más de 90 días, leídas o no, en lotes de 1.000 por clave primaria (tope de 500 lotes por ejecución).
2. Deja como máximo 500 filas por usuario: borra primero las leídas más antiguas y, si no basta, las no leídas más antiguas (tope de 2.000 usuarios por ejecución).
3. Reconcilia el contador de los usuarios afectados por los pasos 1 y 2.
4. Reconcilia los contadores con más de 24 horas (o sin fila) de los usuarios que recibieron un aviso en los últimos 7 días (tope de 5.000 por ejecución).

Los topes mantienen corta la ejecución; lo que quede se hace al día siguiente. Los borrados son autocommit: si la ejecución se corta, lo hecho queda hecho y no hay corrupción. Las constantes (`OPG_NOTIF_RETENCION_DIAS`, `OPG_NOTIF_MAX_POR_USUARIO` y los topes) están al inicio del archivo.

Operación:

- Ver el resultado: panel de administración, Herramientas > Tareas, registro de la tarea ("N borradas por antigüedad, N por exceso, N contadores reconciliados").
- Ejecutarla a mano: Herramientas > Tareas > Ejecutar ahora.
- Medido en Docker (#15): ejecución diaria normal 0,34 s; la primera con mucho atraso (50.000 filas antiguas, 9.714 usuarios) unos 36 s. Si la tarea estuvo desactivada semanas y hay decenas de miles de filas atrasadas, la primera ejecución puede pasar de 30 s; si el hosting la corta, sigue al día siguiente.
- Desactivarla: `UPDATE mybb_tasks SET enabled = 0 WHERE file = 'notificaciones';` (o desde el panel). Quitarla: `DELETE FROM mybb_tasks WHERE file = 'notificaciones';` (no borra notificaciones).

## 7. Despliegue

Estado de producción al cerrar esta guía: las dos tablas (`docs/notificaciones_migration.sql`) ya están ejecutadas. El resto se despliega por pasos, que el usuario decide y ordena (los pasos acoplados se retienen; ver `AGENTS.md`, "Deployment After Code Changes"). Todo lo que sigue es idempotente salvo donde se indica.

Orden recomendado:

| Paso | Qué | Detalle y acoplamientos |
|---|---|---|
| 1 | Tablas SQL | `docs/notificaciones_migration.sql`. **Ya ejecutada en producción.** Idempotente. Sin las tablas, todo el sistema queda inerte: el foro carga, la campana muestra cero y los módulos siguen con sus avisos antiguos. |
| 2 | Librerías | Subir `inc/plugins/lib/notificaciones.php`, `notificaciones_catalogo.php`, `notificaciones_vistas.php` y `notificaciones_migracion.php`. Sin efecto visible por sí solas. |
| 3 | Plugin y campana (acoplado) | Subir `inc/plugins/opg_notificaciones.php`. Subir las plantillas `op_notificaciones_header.html` y `header.html`: **subirlas las activa** (template_sync las importa a la base en unos 10 s). Después, en el panel de MyBB, **desactivar y volver a activar** el plugin "OPG - Notificaciones": instala la plantilla y el marcador `{$op_notificaciones_header}` en `header`, y es idempotente. Subir la plantilla sin el plugin deja la variable vacía; subir el plugin sin la plantilla no muestra la campana. Reversión: restaurar el `header.html` anterior (quitar el `div.op-barra-usuario` y dejar `{$op_temas_header}`) o desactivar el plugin (el marcador queda inerte). |
| 4 | Página (acoplado) | Subir `op/notificaciones.php`, `jscripts/notificaciones.css` y la plantilla `op_notificaciones.html` (hoja `?v=2`; subir la plantilla activa el cambio). Conviene que plantilla y hoja suban juntas: si la plantilla llega antes, los botones salen sin sus estilos propios. Mientras la plantilla no esté en la base, la página usa la copia del archivo. |
| 5 | Tablón y migración (acoplados, juntos) | Subir `inc/plugins/lib/aventuras.php` y `aventuras_entregas.php` (#9 y #11) y, **en la misma sesión**, subir `op/staff/migrar_notificaciones.php` y ejecutarla como administrador (`/op/staff/migrar_notificaciones.php`: la simulación al abrir, el botón "Ejecutar la migración" copia). Si la tabla global existe y aún no se migró, las novedades antiguas no se ven. Es idempotente (clave única) y no borra la tabla antigua. Mejor con poco movimiento, aunque es segura con el foro abierto. El panel "Novedades" de Mis aventuras se conserva, filtrado por la categoría Aventuras más el tipo `recompensas_entregadas`. |
| 6 | Bitácora | Subir `inc/plugins/op_bitacora/functions.php`. Incluye #12 (eventos como notificaciones) y #13 (retira el aviso de "novedad pendiente" del header de la Bitácora). **Desplegar solo cuando la campana esté confirmada en producción.** Sin las tablas, la Bitácora funciona como antes. Las tablas y la columna `ultima_novedad_en` de la Bitácora se conservan. |
| 7 | Tarea (acoplado) | Subir `inc/tasks/notificaciones.php` y **después** ejecutar `docs/notificaciones_tarea_migration.sql` (para que el planificador no apunte a un archivo ausente). La primera ejecución ocurre en la siguiente revisión del planificador (`nextrun = 0`); para verla al momento, ejecutarla desde Herramientas > Tareas. |

Otras notas de despliegue:

- Las plantillas `.html` de `templates/One_Piece_Gaiden_Templates/` son copias de referencia; MyBB renderiza desde la base. Informar siempre que una plantilla cambió.
- No hay SQL adicional: la tarea y las tablas son las únicas migraciones.
- Reversión general: revertir el commit del paso correspondiente; lo creado en la tabla global queda y es inofensivo. Para volver a avisos antiguos del Tablón, ver el encabezado de `op/staff/migrar_notificaciones.php` (consulta de reversión).
- **Desinstalar el plugin no borra tablas ni datos.** Por eso el panel de MyBB puede decir "desinstalado" y seguir listando el plugin como instalado (`_is_installed` mira las tablas, que siguen ahí). Es intencional: las notificaciones se conservan. Para quitar el sistema de verdad hay que borrar las tablas a mano (rollback comentado al final de `notificaciones_migration.sql`), solo si ningún módulo las usa.
- El plugin de la campana no pisa `template_sync`: el marcador y la plantilla se reescriben al activar, no en cada petición.

## 8. Operación diaria

- **Registro de errores.** Todo lo que falla se registra con `error_log` y el prefijo `[notificaciones]` (conflictos de clave, enlaces descartados, interbloqueos, filas afectadas inesperadas). La pantalla del usuario nunca muestra esos errores.
- **Tablas ausentes.** Cada función comprueba una vez por petición que existan las dos tablas. Sin ellas: el envío devuelve `false` sin registrar, el contador es cero y la lista está vacía.
- **Rendimiento medido (Docker, MySQL 5.7, 10.000 usuarios y 500.000 filas, issue #15).** Página (contar y listar): 0,75 ms de mediana. Marcar una: 1 ms. Marcar todas (unas 122): 14 ms. Envío a 1 destinatario: 4 ms; a 1.000 destinatarios: 1,2 s (1 a 2 ms por destinatario). Con el contador al día, el header hace una sola consulta por clave primaria. Docker en macOS es una referencia, no los tiempos del hosting. La prueba es manual: `php tests/notificaciones/rendimiento_db.php --medir`.
- **Pruebas.** `sh tests/run_all.sh` (puras) y `sh tests/run_all.sh --db --area notificaciones` (con MySQL 5.7 de pruebas, ver `tests/aventuras/bootstrap_db.php`). Las pruebas `tests/aventuras/` del Tablón estaban en rojo antes de este trabajo (issue #27); para notificaciones use `--db --area notificaciones`.

## 9. Límites conocidos

1. **Riesgo residual de interbloqueo al crear la fila agrupable.** Si dos transacciones abiertas crean a la vez la misma fila agrupable (misma clave y destinatario) y la que gana inserta después, en esa misma transacción, otra clave del mismo destinatario que queda justo antes en el índice único, InnoDB las interbloquea. Es propio de InnoDB (bloqueos de inserción en el índice único), no del contador, y solo ocurre al crear el grupo (medido: 1 de cada 150 acciones en la prueba de estrés). Por eso la Bitácora avisa fuera de sus transacciones y con un tema por llamada: en ese caso solo se pierde ese aviso, que queda registrado, y la acción del usuario ya está confirmada. Quien conecte otro módulo con tipos agrupables debe hacer lo mismo.
2. **Contador uno por encima** en envíos en autocommit, hasta la siguiente reconciliación (apartado 5).
3. **Marcar todas** marca todas las no leídas del filtro en el momento de pulsar (también las llegadas después de abrir la página, pero no las que lleguen durante la propia llamada). Las no leídas permanecen aunque se visite la página.
4. **Marcar vista y reconciliar abren su propia transacción corta**, así que no deben llamarse con una transacción abierta (confirmaría la de quien llama). Los reintentos ante 1213 y 1205 son 2.
5. **Una clave de evento ya usada por otro tipo** ignora el aviso nuevo y lo registra. La comparación de claves no distingue mayúsculas.
6. **Envíos muy grandes** (miles de destinatarios en una llamada) tardan segundos en la petición que los dispara. Hoy ningún origen avisa así.
7. **La tarea con mucho atraso** puede superar 30 s la primera vez (apartado 6).
8. **Duplicidad temporal.** Hasta que se retire el panel "Novedades" de Mis aventuras, el usuario puede ver un aviso del Tablón en dos sitios.
9. **Sin probar en navegador real ni en móvil** el diseño de la página (issue #7): la hoja tiene reglas para móvil, `overflow-wrap` y modo oscuro, pero la prueba visual queda para el usuario.
10. **Tipos de otras herramientas** (fichas, técnicas, objetos, cofres, peticiones) no están conectados. Ver "Cómo conectar otra herramienta". Las categorías `ficha`, `tecnicas` y `peticiones` ya existen en las listas, sin tipos.
11. **Sin canales externos** (correo, Discord, MP), preferencias para silenciar, avisos masivos, contador en vivo ni API REST. Las columnas `sent_at` y `attempts` están reservadas y sin uso.

## 10. Comprobación de los criterios de aceptación (apartado 11 de `108`)

Cada criterio se comprobó contra el código y las pruebas de `tests/notificaciones/`. "Pruebas" nombra el archivo que lo cubre; los `*_db.php` usan MySQL 5.7 real.

| # | Criterio | Cumple | Issues | Evidencia |
|---|---|---|---|---|
| 1 | Una acción de cada módulo conectado en la primera entrega (Tablón y Bitácora) genera su notificación. | Sí | #9, #11 (Tablón), #12 (Bitácora) | `tablon_db.php`, `entrega_db.php`, `bitacora_db.php` |
| 2 | Reintentar la misma acción no duplica la notificación. | Sí | #3 (clave única), #9, #12 | `envio_db.php` (idempotencia), `tablon_db.php`, `bitacora_db.php` |
| 3 | El header muestra el número correcto, lo oculta en cero y muestra "99+" desde 100. | Sí | #6 | `header.php` (0, 1, 99, 100, 250) |
| 4 | El header ejecuta una sola consulta por clave primaria y ninguna para invitados. | Sí | #6, #15 | `header.php` (invitado sin consultas ni carga de la librería), `rendimiento_db.php` (EXPLAIN `const` por PRIMARY) |
| 5 | Abrir `/op/notificaciones.php` no cambia el contador. | Sí | #7 | `pagina_db.php` (la página no escribe) |
| 6 | **Visto** baja el contador en uno, y volver a pulsarlo no lo baja de nuevo. | Sí | #4 (lógica), #8 (botón) | `lectura_db.php`, `acciones_db.php` |
| 7 | **Marcar todas como vistas** deja el contador en cero (o descuenta solo la categoría filtrada). | Sí | #4, #8 | `lectura_db.php`, `acciones_db.php` |
| 8 | Las llegadas mientras la página estaba abierta siguen contando al recargar, y las no leídas permanecen aunque se visite la página. | Sí, con el límite 3 del apartado 9 | #4, #7, #8 | `lectura_db.php` (un aviso que llega mientras se marca no se marca), `pagina_db.php` |
| 9 | La lista pagina por id y mantiene el filtro de categoría. | Sí | #4, #7 | `lectura_db.php` (cursor `(created_at, id)`, 75 filas con fechas repetidas), `pagina.php`, `pagina_db.php` |
| 10 | Un fallo al insertar una notificación no impide completar el pago, la aprobación ni el cambio de ficha que la originó. | Sí | #3, #9, #12 | `envio_db.php` (fallo de inserción, mysqli estricto, tablas ausentes), `tablon_db.php`, `bitacora_db.php` (fallo) |
| 11 | Con las tablas ausentes el foro carga con normalidad y la campana muestra cero. | Sí | #3, #4, #6 | `header.php` (una consulta, campana sin insignia, sin errores), `lectura_db.php`, `envio_db.php` |
| 12 | Las notificaciones del Tablón se ven en la página global con su fecha y su estado originales. | Sí | #9, #10 | `migracion_db.php` (conserva `created_at`, `leida_en`, clave, `sent_at`, `attempts`), `tablon_db.php` |
| 13 | El contador se autorepara si se altera manualmente. | Sí | #4, #14 | `lectura_db.php` (desfase, más de 24 h, fila ausente), `retencion_db.php` |
| 14 | La tarea de limpieza respeta 90 días y 500 filas por usuario. | Sí | #14 | `retencion_db.php`, `rendimiento_db.php` (volumen) |
| 15 | La página funciona en móvil, con modo oscuro y sin desbordamiento horizontal. | Parcial: por revisión de código | #7, #8 | `jscripts/notificaciones.css` (columna única bajo 700 px, `overflow-wrap`, `prefers-color-scheme: dark`). No probado en navegador real ni en móvil (apartado 9, punto 9). Pendiente de visto bueno visual del usuario. |

Resumen: 14 criterios cumplidos y comprobados con pruebas; el 15 (visual) cumplido por revisión de código y pendiente de comprobación en navegador.
