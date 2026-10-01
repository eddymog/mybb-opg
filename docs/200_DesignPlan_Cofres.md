# Gestión de Cofres: plan de diseño

> Depende de: [100_Requirements_Cofres.md](100_Requirements_Cofres.md)
>
> **Estado: por implementar.** Ninguno de los archivos descritos aquí existe
> todavía, salvo `opg/tirada_cofre.php`, que no se toca (ver 2).

## 1. Objetivo

Una página de staff (solo `is_admin()`) para gestionar la loot table de los
cofres (`mybb_op_cofres`) sin tocar código ni la base de datos a mano, más
una herramienta separada en la consola de staff para repartir un cofre a
todas las fichas. El mecanismo de tirada en sí (peso acumulativo vía SQL) no
cambia; lo que cambia es cómo se administran los datos y, en
`opg/tirada_cofre2.php`, cómo se valida qué cofres existen y cómo se
procesan las recompensas `Custom`.

## 2. Archivos

| Archivo | Qué hace | Nuevo/existente |
|---|---|---|
| `op/staff/cofres_gestion.php` | Listado de cofres + editor de filas de un cofre + alta de loot table nueva | Nuevo |
| `templates/One_Piece_Gaiden_Templates/staff_cofres_gestion_listado.html` | Plantilla del listado | Nuevo |
| `templates/One_Piece_Gaiden_Templates/staff_cofres_gestion_editor.html` | Plantilla del editor de un cofre | Nuevo |
| `op/staff/cofres_repartir.php` | "Repartir cofre a todos los usuarios", enlazado desde la consola | Nuevo |
| `templates/One_Piece_Gaiden_Templates/staff_cofres_repartir.html` | Plantilla de lo anterior | Nuevo |
| `op/staff/consola_mod.php` | Se le agrega un enlace a `cofres_repartir.php` | Editado (un enlace nuevo, nada más) |
| `opg/tirada_cofre2.php` | Copia de `opg/tirada_cofre.php` con la validación de `cofre_id` dinámica (4.1) y el rediseño de `Custom` (4.2) | Nuevo |
| `opg/tirada_cofre.php` | La tirada real de los jugadores, **sin tocar** hasta que `tirada_cofre2.php` esté probado y se decida el corte (100_Requirements §2) | Sin cambios por ahora |
| `op/legacy/migrar_cofres_custom.php` | Script de migración de una sola vez: traduce las filas `Custom` existentes al JSON nuevo | Nuevo, se corre una vez y se puede borrar después |

`cofres_gestion.php` sigue el mismo patrón de un archivo con dos vistas según
query string que ya usa el proyecto (ej. `tecnicas_modificar.php` con
`?tecnica_id=`): sin parámetro muestra el listado, con `?cofre_id=X` muestra
el editor de ese cofre.

## 3. Cambios de esquema

### 3.1 `mybb_op_cofres.custom_data` (columna nueva)

```sql
ALTER TABLE `mybb_op_cofres` ADD COLUMN `custom_data` JSON NULL;
```

Mismo patrón que las columnas JSON ya existentes en `mybb_op_fichas`
(`oficios`, `belicas`, `estilos`). Solo se usa cuando `tipo='Custom'`; para
`Objeto` y `Jackpot` queda `NULL`.

Forma (100_Requirements §4.2):

```json
{
  "nikas": 5,
  "berries": 200000,
  "experiencia": 20,
  "puntos_oficio": 100,
  "objeto_bonus": "LLST001",
  "objeto_bonus_cantidad": 1
}
```

Las columnas actuales de `mybb_op_cofres` (`objeto_id`, `nombre`, `tipo`,
`peso`, `cantidad`) no cambian. Para una fila `Custom`, `objeto_id` deja de
usarse como el código mágico de texto (ya no hace falta, el dato real vive en
`custom_data`) — se recomienda dejarlo como una copia legible del contenido
del JSON (ej. `'5N-200KB-20E-100PO'`), solo para que sea reconocible mirando
la tabla a mano; el código nunca lo vuelve a leer para decidir nada.

### 3.2 Migración de las filas `Custom` existentes

`op/legacy/migrar_cofres_custom.php` — mismo patrón que los demás scripts de
un solo uso de esa carpeta (`export_temp.php`, etc.): bootstrap de
`global.php`, gateado por `is_admin()`, pensado para abrirse una vez desde
el navegador logueado como admin, no para correr por CLI. Recorre
`SELECT * FROM mybb_op_cofres WHERE tipo='Custom'`, y por cada fila traduce
su `objeto_id` (el código viejo) al `custom_data` JSON equivalente, usando
la misma tabla de traducción que hoy vive en el switch de
`procesarCustomRecompensa()` (`opg/tirada_cofre.php:433-547`) — es
literalmente trasladar cada `case` a una entrada de un array de mapeo
`['5N' => ['nikas' => 5], '200KB' => ['berries' => 200000], ...]`, y para
los códigos compuestos (`'3N100KB50PO10E'`) separar sus partes.

Se corre **una sola vez**, antes de que `opg/tirada_cofre2.php` entre en
producción, y después de correrlo se verifica que ninguna fila `Custom`
quedó con `custom_data` nulo (si quedó alguna, significa que había un código
sin mapear — hay que resolverlo a mano antes de seguir, no dejarlo pasar en
silencio).

## 4. `opg/tirada_cofre2.php`: diferencias contra el original

### 4.1 Validación de `cofre_id` dinámica

Reemplaza las listas fijas de `opg/tirada_cofre.php` (la condición larga de
`$cofre_id == 'CFR001' || ...` en la tirada, y la lista de `dar_cofre_masivo`
— esta última ya no aplica porque esa herramienta se muda a
`cofres_repartir.php`, ver 6) por una consulta real:

```php
function cofreIdValido($db, $cofre_id) {
    $safe = $db->escape_string($cofre_id);
    $q = $db->query("SELECT 1 FROM mybb_op_cofres WHERE cofre_id='$safe' LIMIT 1");
    return (bool) $db->fetch_array($q);
}
```

El cofre bromista `CFF010` (y su cadena `$cff010_chain`) sigue siendo un caso
especial aparte, fuera de esta consulta — se revisa primero (como ya hace
hoy `isset($cff010_chain[$cofre_id])`), y si no matchea ahí, se revisa contra
`mybb_op_cofres` en vez de la lista fija.

### 4.2 Procesado de `Custom` sin switch

`procesarCustomRecompensa()` (433-547 líneas de switch) se reemplaza por una
función que lee `custom_data`:

```php
function procesarCustomRecompensa($custom_data_json) {
    $datos = json_decode($custom_data_json, true);
    if (!is_array($datos)) { return; }

    if (!empty($datos['nikas']))         { darNikas((float) $datos['nikas']); }
    if (!empty($datos['berries']))       { darBerries((int) $datos['berries']); }
    if (!empty($datos['experiencia']))   { darExp((float) $datos['experiencia']); }
    if (!empty($datos['puntos_oficio'])) { darOficio((int) $datos['puntos_oficio']); }
    if (!empty($datos['objeto_bonus'])) {
        darObjeto($datos['objeto_bonus'], intval($datos['objeto_bonus_cantidad'] ?? 1));
    }
}
```

La llamada pasa a ser `procesarCustomRecompensa($cofre['custom_data'])` en
vez de `procesarCustomRecompensa($obj_id)` — ya no se lee `objeto_id` para
decidir qué dar en una recompensa Custom.

`procesarJackpotCofre()` (da todas las filas no-jackpot del mismo
`cofre_id`) no cambia su lógica, solo la llamada interna a
`procesarCustomRecompensa()` para las filas `Custom` que contenga.

### 4.3 Lo que NO cambia

- El cálculo de peso acumulativo vía SQL (`opg/tirada_cofre.php:647-677`).
- El descuento del ítem-cofre del inventario antes de tirar.
- El registro en `mybb_op_tirada_cofre`.
- La cadena de estados `CFF010` completa.
- `darNikas()`, `darBerries()`, `darExp()`, `darOficio()`, `darObjeto()`,
  `resolverObjetoId()`.

## 5. `op/staff/cofres_gestion.php`

### 5.1 Listado (sin `?cofre_id`)

Consulta:

```sql
SELECT o.objeto_id, obj.nombre, COUNT(*) AS num_filas, SUM(o.peso) AS peso_total
FROM mybb_op_cofres o
JOIN mybb_op_objetos obj ON obj.objeto_id = o.cofre_id
GROUP BY o.objeto_id
ORDER BY obj.nombre
```

Tarjeta o fila por cofre: nombre (de `mybb_op_objetos`), número de filas,
peso total, y enlace a editar. No hay botón
de borrado (fuera de alcance, ver §2 de 100_Requirements_Cofres.md). Botón
"Cofre nuevo" que lleva al selector de ítem (5.3).

### 5.2 Editor (`?cofre_id=X`)

- Cabecera con el nombre e imagen del ítem (de `mybb_op_objetos`, mismo
  patrón visual que otras herramientas de staff que ya muestran objetos).
- Lista de filas de recompensa, cada una con: tipo, nombre/resumen,
  peso, y **probabilidad calculada en vivo** (`peso / suma total`, recalculada
  en el cliente con JS cada vez que se edita un peso, sin esperar a guardar).
- Botón "Agregar recompensa" abre un formulario cuyo contenido cambia según
  el tipo elegido (selector de tipo primero, campos después):
  - `Objeto`: buscador de ítem (reusa el patrón de typeahead que ya existe
    en otras herramientas, ej. `tecnicas_modificar.php`) + cantidad.
  - `Custom`: cuatro campos numéricos (nikas, berries, experiencia, puntos de
    oficio) + un campo opcional de ítem bonus + cantidad.
  - `Jackpot`: sin campos extra, solo peso.
- Editar una fila existente reusa el mismo formulario, precargado.
- Eliminar una fila: confirmación simple, sin restricciones.

### 5.3 Alta de cofre nuevo

Selector de ítem (buscador sobre `mybb_op_objetos`, excluyendo los
`objeto_id` que ya tengan filas en `mybb_op_cofres`) → al elegir uno, crea
la primera fila vacía y redirige a `?cofre_id=<elegido>` (5.2).

### 5.4 Permisos y CSRF

`is_admin($mybb->user['uid'])` al principio del archivo, igual que el resto
de herramientas de staff restringidas (`error_no_permission()` si no
cumple). CSRF con `verify_post_check()` en cada acción que escribe.

### 5.5 Auditoría

Cada alta de fila, edición y borrado de fila llama a `log_audit()` (acción +
quién) y, cuando corresponde, `log_audit_currency()` si el cambio afecta
algo que ese helper registra.

## 6. `op/staff/cofres_repartir.php`

Traslado de la lógica que hoy vive en `action=dar_cofre_masivo` dentro de
`opg/tirada_cofre.php:288-326`, con tres cambios:

- Permiso: `$uid === 10` (decisión explícita, más restrictivo que `is_admin()`
  — ver 100_Requirements §5.6) en vez de `in_array(intval($uid), [850, 10])`.
- Lista de cofres válidos: `SELECT DISTINCT cofre_id FROM mybb_op_cofres`
  (más `CFF010` a mano, igual que hoy) en vez de la lista fija
  `$cofres_validos` escrita en el código.
- **Filtro de actividad (nuevo, no existía antes):** en vez de recorrer
  `SELECT fid FROM mybb_op_fichas` entero, el `SELECT` agrega un `WHERE
  EXISTS (...)` que busca, para cada ficha, al menos un post
  (`mybb_posts` → `mybb_threads` → `mybb_forums`) con `dateline >= $umbral`
  (`strtotime('-2 months')`, calculado en PHP al momento del reparto, no un
  valor guardado) **dentro de la zona de rol**: el foro del post tiene
  `fid=10` o `FIND_IN_SET(10, parentlist)` es verdadero (subforo de 10, a
  cualquier nivel de anidado — `parentlist` de un subforo incluye el fid de
  todos sus ancestros, confirmado en
  `admin/inc/functions.php::make_parent_list()`). A propósito **no** se usa
  `mybb_users.lastpost` (cuenta posts en cualquier foro del sitio, no solo
  de rol) ni `lastvisit` (eso cuenta solo entrar al foro, no participar).

El resto de la lógica (dar 1 unidad a cada `fid` que pasa el filtro,
`log_audit`) no cambia de forma. Se agrega un enlace a este archivo en
`op/staff/consola_mod.php`, siguiendo el mismo patrón que los enlaces ya
existentes ahí (ej. `ficha_atributos2.php`), con el permiso `'uid10'` nuevo
en `consola_puede()` en vez de `'admin'`.

## 7. Diseño visual

Sigue [style.md](style.md) como referencia: paleta real de colores (nunca
inventados), sombra de cómic (`--opg-sombra-offset`), `.opg-vacio` para el
listado sin cofres (caso borde improbable, pero consistente con el resto del
sistema), tarjetas (`.opg-card`) para cada cofre en el listado. Diseño
moderno e intuitivo, no una tabla cruda:

- El listado de cofres como tarjetas con imagen del ítem, no una tabla de
  texto.
- El editor de filas con la probabilidad recalculada en vivo (barra o
  porcentaje junto a cada peso), para que armar un cofre balanceado no
  requiera sacar cuentas a mano.
- El formulario de recompensa cambia de forma según el tipo elegido (no
  todos los campos visibles todos el tiempo, confunde más que ayuda).

## 8. Casos límite y fallos

| Caso | Comportamiento |
|---|---|
| Cofre sin ninguna fila | El listado lo marca visualmente ("sin recompensas configuradas"); la tirada real fallaría si se intentara abrir así, así que se avisa antes de eso, no después |
| Fila `Custom` con todas las claves del JSON en 0/vacías | No se deja guardar (100_Requirements §4.4) |
| Migración deja una fila `Custom` sin mapear | El script la reporta explícitamente al terminar, no la deja en silencio con `custom_data` nulo |
| Crear un cofre sobre un `objeto_id` que ya tiene loot table | No se ofrece como opción en el selector de alta (5.3) |
| `opg/tirada_cofre2.php` recibe un `cofre_id` que no existe en `mybb_op_cofres` ni es `CFF010` | Mismo mensaje de error que hoy ("No tienes ese cofre o no es válido") |

## 9. Pendientes

- El plan de corte definitivo de `tirada_cofre2.php` sobre el archivo viejo
  (100_Requirements §2) no es parte de este documento — se decide aparte,
  después de probar `tirada_cofre2.php` a fondo.
