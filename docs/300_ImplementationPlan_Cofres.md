# Gestión de Cofres: plan de implementación

> Requisitos: [100_Requirements_Cofres.md](100_Requirements_Cofres.md)
>
> Diseño: [200_DesignPlan_Cofres.md](200_DesignPlan_Cofres.md)

## 1. Objetivo de este documento

Convierte el diseño en tareas de código ordenadas y verificables. Define
archivos, funciones, consultas y pruebas.

La implementación se considera terminada cuando:

- un administrador (`is_admin()`) puede crear la loot table de un cofre
  nuevo a partir de un ítem ya existente y editar/agregar/borrar sus filas
  de recompensa (sin opción de borrar el `cofre_id` completo — decisión
  explícita, ver 100_Requirements_Cofres.md §2);
- una fila `Custom` se guarda como JSON en `custom_data`, sin pasar por
  ningún switch de PHP;
- `opg/tirada_cofre2.php` valida qué `cofre_id` existen consultando
  `mybb_op_cofres` en vez de listas fijas, y procesa `Custom` leyendo
  `custom_data`;
- `opg/tirada_cofre.php` sigue funcionando exactamente igual que hoy, sin
  ningún cambio, mientras `tirada_cofre2.php` se prueba;
- "repartir cofre a usuarios activos" vive en
  `op/staff/cofres_repartir.php`, enlazado desde la consola de staff, con
  permiso `$uid === 10` (decisión explícita, no `is_admin()`) en vez de la
  whitelist de dos UID, y reparte solo a fichas con al menos un post en la
  zona de rol (fid=10 y subforos) dentro de los últimos 2 meses;
- el cofre bromista `CFF010` no fue tocado en ningún archivo;
- las filas `Custom` que ya existían en `mybb_op_cofres` quedan migradas a
  `custom_data` por el script de la Tarea 2, sin ninguna quedar sin mapear
  en silencio.

## 2. Punto de partida

`opg/tirada_cofre.php` es el único archivo que hoy implementa la tirada de
cofres; no tiene página de gestión ni validación dinámica. Todo lo descrito
aquí es código nuevo — no se reescribe nada existente salvo el enlace nuevo en
`consola_mod.php`.

Cada tarea de la sección 5 indica qué debe cumplir el código y cómo
verificarlo. Las tareas de pruebas y despliegue son manuales.

## 3. Archivos

### 3.1 Crear

| Archivo | Contenido |
|---|---|
| `docs/cofres_custom_data_migration.sql` | `ALTER TABLE mybb_op_cofres ADD COLUMN custom_data JSON NULL;` |
| `op/legacy/migrar_cofres_custom.php` | Script de una sola vez, mismo patrón que `op/legacy/export_temp.php` (bootstrap de `global.php`, gateado, pensado para abrirse una vez desde el navegador) |
| `op/staff/cofres_gestion.php` | Listado + editor + alta (sin borrado, ver 100_Requirements §2) |
| `templates/One_Piece_Gaiden_Templates/staff_cofres_gestion_listado.html` | Plantilla del listado |
| `templates/One_Piece_Gaiden_Templates/staff_cofres_gestion_editor.html` | Plantilla del editor de un cofre |
| `op/staff/cofres_repartir.php` | "Repartir cofre a todos los usuarios" |
| `templates/One_Piece_Gaiden_Templates/staff_cofres_repartir.html` | Plantilla de lo anterior |
| `opg/tirada_cofre2.php` | Copia de `tirada_cofre.php` con 4.1/4.2 del diseño |

### 3.2 Modificar

| Archivo | Cambio |
|---|---|
| `op/staff/consola_mod.php` | Un enlace nuevo a `cofres_repartir.php`, mismo patrón que los enlaces ya existentes |

### 3.3 No se toca

- `opg/tirada_cofre.php` — la tirada real de los jugadores, sin cambios
  hasta que se decida el corte (fuera de este documento, ver
  100_Requirements §2 y 200_DesignPlan §9).
- `$cff010_chain` y la lógica del cofre bromista, en ningún archivo.
- `global.php`, `inc/` (núcleo de MyBB).
- `darNikas()`, `darBerries()`, `darExp()`, `darOficio()`, `darObjeto()`,
  `resolverObjetoId()`, `procesarJackpotCofre()` (su lógica no cambia, solo
  cómo le llega el dato Custom — ver Tarea 9).

### 3.4 Sincronización

Las plantillas se sincronizan a mano con la base de datos
(`import_templates.php`). Sin sincronizar `staff_cofres_gestion` y
`staff_cofres_repartir`, las páginas nuevas no tienen estilo.

## 4. Convenciones

- Patrón de archivo de `/op/staff/`: `IN_MYBB`, `THIS_SCRIPT`, incluir
  `../../global.php` y `../functions/op_functions.php`; `is_admin($mybb->user['uid'])`
  al principio, `error_no_permission()` si falla.
- CSRF: `generate_post_check()`/`verify_post_check()` en cada acción que
  escribe, mismo patrón que `op/staff/tecnicas_modificar.php`.
- SQL con interpolación: todo entero forzado con `(int)`; toda cadena
  (incluido cualquier valor que vaya dentro de `LIKE`) pasada por
  `$db->escape_string()`. Ningún valor de usuario entra crudo en una
  consulta (recordando el bug real encontrado y corregido en
  `tecnicas_modificar.php` antes de este trabajo).
- Salida con `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **Nunca un `$identificador` suelto en el JS de las plantillas nuevas** —
  `$templates->get()` aplica `addslashes()` y el template se evalúa como
  cadena PHP (`eval("\$page = \"...\";")`); cualquier `$palabra` que no sea
  la interpolación intencional `{$variable}` se interpreta como variable PHP.
  Verificar con `grep -no '\$[a-zA-Z_][a-zA-Z0-9_]*'` sobre cada plantilla
  nueva antes de darla por terminada (regla de `style.md` §7, ya aplicada
  varias veces en este proyecto).
- Auditoría: `log_audit()` en cada alta/edición (incluido el borrado de una
  fila individual), con quién y qué cambió; `log_audit_currency()` donde
  corresponda (reutilizar el patrón que ya usa `opg/tirada_cofre.php` en
  sus funciones `dar*()`).
- Español neutro en todos los textos de la interfaz.
- Diseño visual: `style.md` — paleta real (`--opg-*`), sombra de cómic,
  `.opg-card`, `.opg-vacio`, `.opg-chip`, `.btn-op`. Sin tablas crudas de
  solo lectura (100_Requirements §6).

## 5. Tareas

### Tarea 1: columna `custom_data`

**Archivo:** `docs/cofres_custom_data_migration.sql`

1. `ALTER TABLE mybb_op_cofres ADD COLUMN custom_data JSON NULL;` — mismo
   patrón que otras migraciones manuales del proyecto
   (`docs/cronologia_thread_roles_migration.sql`,
   `docs/bitacora_migration.sql`).
2. Ejecutar a mano contra la base real (fuera del flujo normal de la app,
   igual que las otras migraciones `.sql` del repo).

**Verificación:** `DESCRIBE mybb_op_cofres` muestra la columna nueva, tipo
`json`, nula.

### Tarea 2: script de migración de filas `Custom`

**Archivo:** `op/legacy/migrar_cofres_custom.php`

1. Mismo patrón que `op/legacy/export_temp.php`: `IN_MYBB`, `THIS_SCRIPT`,
   bootstrap de `global.php`, gateado con `is_admin((int) $mybb->user['uid'])`
   (`die('No autorizado.')` si falla), salida `Content-Type: text/plain`.
2. Array de mapeo `codigo => array`, trasladando cada `case` de
   `procesarCustomRecompensa()` (`opg/tirada_cofre.php:433-547`) a su
   `custom_data` equivalente — incluye los códigos compuestos
   (`'3N100KB50PO10E'` → `['nikas'=>3,'berries'=>100000,'puntos_oficio'=>50,'experiencia'=>10]`)
   y el caso especial `'JACKPOT'` de `KTC001` (→
   `['nikas'=>10,'berries'=>1000000,'experiencia'=>10,'puntos_oficio'=>100,'objeto_bonus'=>'LLST001','objeto_bonus_cantidad'=>1]`).
3. `SELECT id, objeto_id FROM mybb_op_cofres WHERE tipo='Custom' AND custom_data IS NULL`.
4. Por cada fila: buscar `objeto_id` en el mapeo; si existe,
   `UPDATE ... SET custom_data = '<json>' WHERE id = <id>`; si no existe,
   acumularla en una lista de "sin mapear".
5. Al terminar, imprimir cuántas filas se migraron y listar explícitamente
   las que quedaron sin mapear (si las hay) — nunca terminar en silencio
   con filas `Custom` sin `custom_data`.
6. Subir el archivo, abrirlo una vez logueado como admin, copiar/revisar el
   reporte, y luego **borrarlo del servidor y no subirlo a git** (mismo
   aviso que ya lleva `export_temp.php`) — una sola vez, antes de poner
   `opg/tirada_cofre2.php` en producción.

**Verificación:** `SELECT COUNT(*) FROM mybb_op_cofres WHERE tipo='Custom' AND custom_data IS NULL`
devuelve 0 tras correrlo (o la lista de sin mapear coincide con códigos que
había que revisar a mano).

### Tarea 3: `cofres_gestion.php` — acceso y listado

**Archivo:** `op/staff/cofres_gestion.php`

1. `IN_MYBB`, `THIS_SCRIPT`, incluir `global.php` y `op_functions.php`.
2. `is_admin($mybb->user['uid'])`, si no `error_no_permission()`.
3. Sin `?cofre_id`: consulta del listado (200_DesignPlan §5.1) — por cada
   `cofre_id` distinto en `mybb_op_cofres`, su nombre real desde
   `mybb_op_objetos` (`JOIN` por `objeto_id`), número de filas y suma de
   pesos.
4. Si un `cofre_id` de `mybb_op_cofres` no tiene fila correspondiente en
   `mybb_op_objetos` (el ítem se borró del catálogo en algún momento): se
   muestra igual, con un aviso visual ("ítem no encontrado en el catálogo"),
   no se oculta ni rompe la página.

**Verificación:** el listado muestra todos los `cofre_id` existentes con
datos correctos; un usuario sin `is_admin()` ve `error_no_permission()`.

### Tarea 4: `cofres_gestion.php` — editor de un cofre

**Archivo:** `op/staff/cofres_gestion.php`

1. Con `?cofre_id=X`: cabecera con nombre/imagen del ítem
   (`mybb_op_objetos`), lista de filas de `mybb_op_cofres` para ese
   `cofre_id` con tipo, nombre/resumen, peso y probabilidad (`peso / suma
   total` calculada en PHP para el render inicial).
2. Acción POST `agregar_fila`: valida `tipo` (`Objeto`/`Custom`/`Jackpot`),
   `peso` (entero ≥ 1); según tipo, valida `objeto_id` contra
   `mybb_op_objetos` (Objeto) o construye `custom_data` desde los campos
   numéricos del formulario (Custom, al menos una clave > 0) o no requiere
   más datos (Jackpot). `INSERT INTO mybb_op_cofres`.
3. Acción POST `editar_fila`: mismo set de validaciones, `UPDATE` por `id`.
4. Acción POST `eliminar_fila`: `DELETE FROM mybb_op_cofres WHERE id=...
   AND cofre_id=...` (el `cofre_id` en el `WHERE` evita borrar una fila de
   otro cofre por error de parámetro).
5. CSRF (`verify_post_check()`) en las tres acciones POST.
6. `log_audit()` en cada una.

**Verificación:** agregar una fila Objeto/Custom/Jackpot funciona y aparece
en el listado de filas; una fila Custom con todos los campos en 0 no se
guarda (mensaje de error); editar un peso cambia la probabilidad mostrada;
eliminar una fila la saca de la lista; un CSRF vencido no aplica el cambio.

### Tarea 5: `cofres_gestion.php` — alta de cofre nuevo

**Archivo:** `op/staff/cofres_gestion.php`

1. Vista/acción de alta: buscador sobre `mybb_op_objetos` (reusar el patrón
   de typeahead de `op/staff/tecnicas_modificar.php`), excluyendo
   `objeto_id` que ya tengan filas en `mybb_op_cofres`.
2. Al elegir un ítem: crear la primera fila (formulario mínimo, cualquier
   tipo) y redirigir a `?cofre_id=<elegido>` (Tarea 4).
3. Si el `objeto_id` elegido ya tiene filas (carrera entre dos admins
   abriendo el buscador a la vez), redirigir igual al editor existente en
   vez de duplicar.

**Verificación:** crear un cofre nuevo sobre un ítem sin loot table
funciona y lleva al editor; el buscador no ofrece ítems que ya son cofres.

### Tarea 6: (anulada — sin borrado de cofre)

Se decidió no permitir eliminar un `cofre_id` desde esta herramienta (ver
100_Requirements_Cofres.md §2). No hay acción `eliminar_cofre`; la única
eliminación posible es de una fila individual (Tarea 4).

### Tarea 7: plantillas `staff_cofres_gestion_listado.html` / `_editor.html`

**Archivos:**
`templates/One_Piece_Gaiden_Templates/staff_cofres_gestion_listado.html`,
`templates/One_Piece_Gaiden_Templates/staff_cofres_gestion_editor.html`

Dos plantillas, no una sola — el listado (tarjetas) y el editor (formulario
+ filas) son estructuralmente distintos; `cofres_gestion.php` ya llama a
`$templates->get('staff_cofres_gestion_listado')` o
`$templates->get('staff_cofres_gestion_editor')` según haya o no
`?cofre_id` (Tareas 3-5).

1. Listado como tarjetas (`.opg-card`), no tabla de texto — imagen del
   ítem, nombre, número de filas, peso total, enlace a editar.
2. Editor: lista de filas con peso editable y probabilidad que se
   **recalcula en vivo en el cliente** al cambiar cualquier peso (JS plano,
   sin `$` sueltos — ver convenciones), antes de guardar.
3. Formulario de fila que cambia de campos según el tipo elegido (selector
   de tipo primero).
4. `.opg-vacio` para un cofre sin ninguna fila, y para el listado si no hay
   ningún cofre todavía.
5. Verificar con `grep -no '\$[a-zA-Z_][a-zA-Z0-9_]*'` que no quede ningún
   identificador JS suelto fuera de `{$variable}` intencional.

**Verificación:** cambiar un peso en el editor actualiza los porcentajes de
todas las filas de ese cofre sin recargar la página; se ve correctamente en
móvil (≤ 700px, mismo criterio que el resto del sitio).

### Tarea 8: `cofres_repartir.php` + enlace en la consola

**Archivos:** `op/staff/cofres_repartir.php`,
`templates/One_Piece_Gaiden_Templates/staff_cofres_repartir.html`,
`op/staff/consola_mod.php`

1. Traslado de la lógica de `action=dar_cofre_masivo`
   (`opg/tirada_cofre.php:288-326`): `$uid === 10` en vez de
   `in_array(intval($uid), [850, 10])` (decisión explícita, más estricto que
   el `is_admin()` del resto de Gestión de Cofres — ver 100_Requirements
   §5.6); lista de cofres válidos desde
   `SELECT DISTINCT cofre_id FROM mybb_op_cofres` más `CFF010` agregado a
   mano (sigue siendo un caso especial fuera de la tabla).
2. Filtro de actividad (nuevo): `SELECT f.fid FROM mybb_op_fichas f WHERE
   EXISTS (SELECT 1 FROM mybb_posts p INNER JOIN mybb_threads t ON
   t.tid=p.tid INNER JOIN mybb_forums fo ON fo.fid=t.fid WHERE p.uid=f.fid
   AND p.dateline >= $umbral AND (fo.fid=10 OR FIND_IN_SET(10,
   fo.parentlist)))`, con `$umbral = strtotime('-2 months')` calculado en
   PHP al momento del reparto (no guardado) y `10` el fid de la zona de rol.
   No usa `mybb_users.lastpost` (cuenta posts en cualquier foro del sitio,
   no solo de rol) ni `lastvisit` (entrar al foro sin participar no cuenta
   como activo). Da 1 unidad del cofre elegido a cada `fid` que pasa el
   filtro, `log_audit()` con el total repartido.
3. Enlace nuevo en `op/staff/consola_mod.php`, con un permiso propio
   (`'uid10'` en `consola_puede()`) en vez de `'admin'`, para que el enlace
   solo aparezca en la consola de quien realmente puede usarlo.
4. Plantilla simple: selector de cofre (con nombre real del catálogo),
   confirmación antes de repartir, aviso de cuántas fichas lo recibieron.

**Verificación:** el UID 10 reparte un cofre y solo las fichas con un post
en un foro de la zona de rol (fid=10 o subforo) en los últimos 2 meses lo
reciben (una ficha de prueba que solo posteó fuera de la zona de rol, o con
su último post de rol vencido, queda afuera, a propósito); cualquier otro
usuario (incluidos otros administradores) no puede acceder
aunque conozca la URL, y no ve el enlace en la consola de staff.

### Tarea 9: `opg/tirada_cofre2.php`

**Archivo:** `opg/tirada_cofre2.php`

1. Copiar `opg/tirada_cofre.php` completo como punto de partida.
2. Reemplazar la condición larga de `cofre_id` válidos (hoy
   `$cofre_id == 'CFR001' || ...`) por `cofreIdValido($db, $cofre_id)`
   (200_DesignPlan §4.1): revisa primero `$cff010_chain`, si no matchea,
   consulta `mybb_op_cofres`.
3. Reemplazar `procesarCustomRecompensa($obj_id)` por la versión que
   recibe `$cofre['custom_data']` y lee sus claves con `json_decode`
   (200_DesignPlan §4.2) — ya no hay switch de casos.
4. Eliminar el bloque `action=dar_cofre_masivo` de este archivo (se mudó a
   `cofres_repartir.php`, Tarea 8) — `tirada_cofre2.php` solo tiene la
   tirada en sí.
5. No tocar: la cadena `$cff010_chain`, el cálculo de peso acumulativo, el
   descuento del ítem-cofre del inventario, el registro en
   `mybb_op_tirada_cofre`, `procesarJackpotCofre()` (salvo la llamada
   interna a `procesarCustomRecompensa()`, que ahora recibe `custom_data`
   en vez de `objeto_id`).

**Verificación:** abrir un cofre existente (migrado) da exactamente la
misma recompensa en distribución que antes (ver Tarea 10, prueba de
paridad); abrir un cofre creado desde `cofres_gestion.php` funciona sin
tocar código; el cofre bromista sigue intacto.

## 6. Pruebas

### 6.1 Comprobaciones estáticas

- `php -l` sobre los cinco archivos PHP nuevos, sin errores.
- `grep -no '\$[a-zA-Z_][a-zA-Z0-9_]*'` sobre las dos plantillas nuevas: solo
  interpolaciones `{$variable}` intencionales.
- Ningún valor de `$_POST`/`$_GET` entra a una consulta SQL sin pasar por
  `(int)` o `$db->escape_string()`.

### 6.2 Paridad entre `tirada_cofre.php` y `tirada_cofre2.php`

Antes de considerar el rediseño terminado: para cada `cofre_id` existente,
comparar que la distribución de probabilidades (peso por fila / suma total)
sea idéntica en los dos archivos, y que una recompensa `Custom` migrada dé
exactamente lo mismo (mismas cantidades de nikas/berries/experiencia/puntos
de oficio) que el código viejo del switch. Se puede automatizar con un
script que llame a `procesarCustomRecompensa()` de los dos lados con los
mismos datos migrados y compare el resultado, o verificarlo a mano fila por
fila si son pocas.

### 6.3 Con la base real (manual)

| # | Caso | Esperado |
|---|---|---|
| 1 | Crear un cofre nuevo sobre un ítem del catálogo, cargarle 3 filas (Objeto, Custom, Jackpot) | Funciona de punta a punta sin tocar código |
| 2 | Abrir ese cofre nuevo desde `tirada_cofre2.php` con un ítem de prueba en inventario | Da una recompensa válida de alguna de las 3 filas |
| 3 | Editar el peso de una fila | La probabilidad mostrada cambia en vivo, sin recargar |
| 4 | Fila Custom con todos los campos en 0 | No se deja guardar |
| 5 | Eliminar una fila individual de un cofre | Se quita de la lista, la probabilidad del resto se recalcula |
| 6 | Usuario sin `is_admin()` accede a `cofres_gestion.php` | Pantalla de sin permisos |
| 6b | Administrador con `is_admin()` pero `uid != 10` accede a `cofres_repartir.php` | Pantalla de sin permisos (el permiso de esta herramienta es más estricto que el resto) |
| 7 | UID 10 reparte un cofre desde la consola | Solo las fichas con un post en la zona de rol (fid=10 o subforo) dentro de los últimos 2 meses lo reciben |
| 8 | Correr el script de migración sobre una copia de prueba con filas Custom reales | Todas quedan con `custom_data`, ninguna sin mapear (o se reportan las que sí) |
| 9 | Abrir un cofre migrado desde `tirada_cofre2.php` varias veces | Las recompensas Custom dan las mismas cantidades que daba el switch viejo |
| 10 | Abrir el cofre bromista `CFF010` desde `tirada_cofre.php` (no el 2) | Sigue funcionando exactamente igual que antes de este trabajo |
| 11 | Móvil (≤ 700px) en `cofres_gestion.php` | Editor y listado usables |

## 7. Despliegue

### 7.1 Antes de subir

- Revisar el diff completo: los 8 archivos nuevos, el enlace agregado en
  `consola_mod.php`, y los documentos.
- Confirmar que `opg/tirada_cofre.php` no aparece en el diff (no se tocó).
- Confirmar que ningún archivo nuevo expone secretos.

### 7.2 Orden

1. Ejecutar `docs/cofres_custom_data_migration.sql` contra la base real
   (Tarea 1).
2. Subir `op/staff/cofres_gestion.php`, `op/staff/cofres_repartir.php`,
   `opg/tirada_cofre2.php`.
3. Sincronizar las plantillas nuevas (`import_templates.php`).
4. Subir `op/legacy/migrar_cofres_custom.php`, abrirlo una vez logueado como
   admin (Tarea 2), revisar su reporte de filas sin mapear, y borrarlo del
   servidor.
5. Subir el enlace nuevo en `consola_mod.php`.
6. Probar `tirada_cofre2.php` a fondo (6.2 y 6.3) antes de considerar
   cualquier corte sobre `tirada_cofre.php` — ese corte queda fuera de este
   documento.

### 7.3 Rollback

- Restaurar los archivos nuevos y el enlace de `consola_mod.php` desde git
  (o simplemente no enlazarlos — no afectan nada mientras no se usen).
- `opg/tirada_cofre.php` nunca se tocó, así que la tirada real de los
  jugadores no se ve afectada por ningún rollback de este trabajo.
- La columna `custom_data` puede dejarse aunque se revierta el resto: sin
  código que la lea o escriba, queda inerte. Si se quiere limpiar del todo,
  `ALTER TABLE mybb_op_cofres DROP COLUMN custom_data;` (se pierde lo
  migrado).

## 8. Orden recomendado de commits

1. `docs: requisitos, diseño e implementación de gestión de cofres` (los
   tres documentos).
2. `feat: columna custom_data y script de migración de recompensas Custom`.
3. `feat: página de gestión de cofres (op/staff/cofres_gestion.php)`.
4. `feat: repartir cofre desde la consola de staff (cofres_repartir.php)`.
5. `feat: opg/tirada_cofre2.php — validación dinámica y Custom sin switch`.

No commit sin que el usuario lo pida.

## 9. Fuera de alcance

- Borrado de un `cofre_id` completo (de `mybb_op_cofres`) desde esta
  herramienta — decisión explícita, ver 100_Requirements_Cofres.md §2.
- El corte definitivo de `tirada_cofre2.php` sobre `tirada_cofre.php`
  (reemplazo del archivo activo, actualización de la plantilla
  `op_tirada_cofre` y de cualquier enlace que apunte al nombre viejo) — se
  decide aparte, después de probar a fondo (100_Requirements §2,
  200_DesignPlan §9).
- Cualquier cambio al cofre bromista `CFF010`.
- Cualquier cambio al mecanismo de peso acumulativo vía SQL en sí.
- Excluir ítems personalizados o invisibles como recompensa (100_Requirements
  §4.4: cualquier ítem del catálogo es válido).
- Edición masiva de varias filas a la vez, importación/exportación de loot
  tables, o cualquier herramienta de balance automático (sugerencias de
  peso, simulación de distribución esperada) — nada de esto se pidió.
