# Requisitos: Gestión de Cofres (gacha)

## 1. Propósito

Hoy, la tabla de recompensas de cada cofre (`mybb_op_cofres`) solo se puede
editar escribiendo filas en la base de datos a mano, y una parte de las
recompensas ("Custom": nikas, berries, experiencia, puntos de oficio) depende
de un switch de más de 60 casos hardcodeado en PHP
(`opg/tirada_cofre.php::procesarCustomRecompensa()`). Si el texto de una fila
no coincide exacto con un `case` de ese switch, el cofre no da nada, sin
ningún aviso.

Este documento define los requisitos de una página nueva, **solo para
administradores**, que permita gestionar cofres (crear, editar su loot table,
y dar de alta cofres nuevos) sin tocar código ni la base de datos a mano.

La arquitectura y el modelo de datos detallado se definen en un
`200_DesignPlan_Cofres.md` aparte (no escrito todavía).

## 2. Decisiones ya tomadas

- **Quién lo usa:** solo administradores del foro — `is_admin($uid)` en
  `op/functions/op_functions.php` (grupo 4 de MyBB, como grupo principal o
  adicional), no `is_user()` ni `is_staff()`.
- **Rediseño de "Custom":** se reemplaza el switch de PHP por una columna JSON
  nativa de MySQL en `mybb_op_cofres` (mismo patrón que `oficios`, `belicas`,
  `estilos` en `mybb_op_fichas`). Ver 4.2.
- **Cofres existentes:** la página permite editar el contenido (filas de
  recompensa) de cofres que ya existen.
- **Cofres nuevos:** la página permite dar de alta un `cofre_id` completamente
  nuevo, de punta a punta — incluye arreglar la validación hardcodeada de
  `opg/tirada_cofre.php` para que un cofre nuevo sea abrible en el juego sin
  tocar código (ver 4.3).
- **Borrado de cofres:** fuera de alcance. No existe forma de eliminar un
  `cofre_id` desde esta página; se considera riesgoso dejar un ítem sin loot
  table. Si un cofre deja de usarse, simplemente no se reparte más.
- **Cofre bromista (`CFF010` y sus variantes):** queda completamente fuera del
  alcance de esta página. Es una secuencia fija en código
  (`$cff010_chain` en `opg/tirada_cofre.php`), no una loot table real.
- **Si hace falta tocar `opg/tirada_cofre.php`:** no se edita el archivo en
  vivo — se crea `opg/tirada_cofre2.php` aparte. Eventualmente
  `tirada_cofre2.php` reemplaza al archivo viejo, pero recién **al final**,
  después de probarlo a fondo — no hay convivencia indefinida de los dos.

## 3. Alcance

La página debe permitir que un administrador:

- vea la lista de todos los `cofre_id` existentes, con su nombre (de
  `mybb_op_objetos`), cantidad de filas de recompensa y suma total de pesos;
- cree la loot table de un `cofre_id` nuevo, a partir de un ítem ya existente
  en `mybb_op_objetos` (ver 5.4);
- agregue, edite o elimine filas de recompensa dentro de un cofre;
- para cada fila, elija el tipo (`Objeto`, `Custom` o `Jackpot`) y cargue los
  datos según el tipo (ver 4.1 y 4.2);
- vea, antes de guardar, la probabilidad resultante de cada fila (peso propio
  dividido por la suma de pesos del cofre);
- elimine un `cofre_id` completo, bloqueado si algún jugador todavía lo tiene
  en inventario (ver 2).

La herramienta de "repartir cofre a todos los usuarios" (hoy
`action=dar_cofre_masivo` dentro de `opg/tirada_cofre.php`, restringida a dos
UID hardcodeados) **se muda a la consola de staff**
(`op/staff/consola_mod.php`), como una entrada nueva separada — no se queda
en `opg/tirada_cofre2.php` ni en la página de gestión de cofres en sí. Ver
5.7.

Fuera de alcance: el cofre bromista `CFF010` (ver 2); cualquier cambio a
cómo se calcula la tirada en sí (el mecanismo de peso acumulativo vía SQL no
cambia, solo cómo se administran los datos).

## 4. Modelo de datos y reglas

### 4.1 Tipos de recompensa

| Tipo | Qué hace hoy | Qué necesita la página |
|---|---|---|
| `Objeto` | Da un ítem real de inventario (`darObjeto()`). La cantidad puede venir de la columna `cantidad` o de un sufijo `BASEIDXn` en `objeto_id` (ver `resolverObjetoId()`) | Selector de ítem (buscando en `mybb_op_objetos`, mismo patrón que otras herramientas de staff) + cantidad numérica |
| `Custom` | Switch hardcodeado en PHP, por código de texto mágico | **Rediseñado** — ver 4.2 |
| `Jackpot` | Da automáticamente todas las demás filas no-jackpot del mismo `cofre_id` | Sin datos propios más allá de nombre y peso — la página solo deja crear la fila |

### 4.2 Rediseño de "Custom"

Nueva columna `custom_data` (JSON) en `mybb_op_cofres`, usada solo cuando
`tipo='Custom'`. Todas las claves son opcionales; la ausencia de una clave
equivale a 0 o ninguno:

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

- `objeto_bonus`/`objeto_bonus_cantidad` cubre el caso que hoy existe (el
  `JACKPOT` de texto del cofre `KTC001`, que da moneda **y además** un ítem
  real) — sin relación con el tipo `Jackpot` de la tabla (ver advertencia más
  abajo).
- El switch de PHP (`procesarCustomRecompensa()`) se reemplaza por completo:
  ya no se mantienen los dos sistemas en paralelo.
- **Advertencia para la interfaz:** no llamar a esto "Jackpot" en ningún lado
  de la página de gestión — ya existe el tipo de fila `Jackpot` (da todo el
  resto del cofre) y es un mecanismo distinto. Usar un nombre que no choque,
  por ejemplo "ítem bonus" para `objeto_bonus`.

### 4.3 Validación de `cofre_id` dinámica

Hoy, `opg/tirada_cofre.php` valida contra listas de `cofre_id` escritas a mano
en al menos tres lugares del archivo (la condición de `tirada_cofre=='true'`,
la lista de `dar_cofre_masivo`, la consulta de inventario de la página). Un
`cofre_id` nuevo creado desde la página de gestión no sería abrible hasta que
alguien edite esas listas a mano.

Para que "dar de alta un cofre nuevo de punta a punta" sea cierto, la
validación tiene que pasar a comprobar contra lo que existe en
`mybb_op_cofres` (`SELECT DISTINCT cofre_id ...` o equivalente), no contra una
lista fija. Este cambio va en `opg/tirada_cofre2.php` (ver 2).

### 4.4 Validaciones de la página

- `peso`: entero positivo (mínimo 1). No se permite guardar una fila con peso
  0 o negativo.
- Un `cofre_id` debe tener al menos una fila de recompensa antes de
  considerarse "activo" — si queda en 0 filas, la tirada fallaría (división
  por cero en la suma de pesos). La página debe avisar o impedir dejar un
  cofre sin filas.
- Para `tipo='Objeto'`, el `objeto_id` elegido debe existir en
  `mybb_op_objetos` — cualquier ítem del catálogo es válido como recompensa
  de cofre, incluidos los personalizados (`custom='1'`) o invisibles
  (`invisible='1'`), sin excluir ninguno por esas columnas.
- Para `tipo='Custom'`, al menos una clave del JSON debe tener un valor mayor
  a 0 (una fila Custom vacía no hace nada, igual que hoy).

## 5. Requisitos funcionales

### 5.1 Acceso

- Solo administradores (`is_admin($uid)`, ver 2).
- Cualquier otro usuario que intente acceder ve el mismo
  `error_no_permission()` que el resto de herramientas restringidas.

### 5.2 Listado de cofres

- Tabla con todos los `cofre_id`, nombre, número de filas de recompensa y
  suma de pesos.
- Acceso a crear un cofre nuevo desde esta misma vista.

### 5.3 Edición de un cofre

- Lista de filas de recompensa del cofre, con su tipo, nombre, peso y
  probabilidad calculada (peso / suma total).
- Agregar fila nueva, editar una existente, eliminar una fila.
- El formulario cambia según el tipo elegido (selector de ítem para
  `Objeto`, campos numéricos para `Custom`, sin campos extra para
  `Jackpot`).

### 5.4 Alta de cofre nuevo

- `mybb_op_cofres` no tiene columna de "nombre del cofre" — el cofre **es**
  un ítem más de `mybb_op_objetos` (el mismo `objeto_id`/`cofre_id` que el
  jugador tiene en inventario), y su nombre visible ya sale de ahí. "Dar de
  alta un cofre" en esta página significa crear su **loot table**, no crear
  el ítem — el ítem (`mybb_op_objetos`) se crea antes, con la herramienta de
  staff que ya existe para eso.
- Formulario: elegir un `objeto_id` ya existente en `mybb_op_objetos` (mismo
  selector que para las filas de tipo `Objeto`), que pasa a ser el
  `cofre_id` de la loot table nueva. Si ese `objeto_id` ya tiene filas en
  `mybb_op_cofres`, no se puede "crear" de nuevo — se edita el existente.
- Tras elegir el ítem, pasa directo a la vista de edición (5.3) para cargar
  sus filas de recompensa.
- El `cofre_id` nuevo debe quedar abrible en el juego sin ningún cambio de
  código adicional (ver 4.3).

### 5.5 Auditoría

- Cada alta o edición queda registrada (mismo patrón que
  `log_audit()`/`log_audit_currency()` ya usado en el resto del sistema de
  cofres), con quién lo hizo y qué cambió.

### 5.6 Repartir cofre a todos los usuarios (consola de staff)

- Nueva entrada en `op/staff/consola_mod.php`, en un archivo propio (no dentro
  de la página de gestión de cofres ni de `opg/tirada_cofre2.php`).
- **Permiso más restrictivo que el resto de la herramienta**: solo
  `$uid === 10` (decisión explícita — repartir a todos los jugadores de una
  vez es una acción de mayor riesgo que editar una loot table, así que no
  comparte el permiso `is_admin()` del resto de Gestión de Cofres). Reemplaza
  la whitelist de dos UID hardcodeados que tenía hoy
  (`in_array(intval($uid), [850, 10])`), pero no la amplía a todo el grupo
  admin — la reduce a uno solo.
- Mismo comportamiento funcional que existe hoy: elige un `cofre_id` válido
  (de la lista real en `mybb_op_cofres`, ya no de la lista fija del código) y
  le da 1 unidad a cada ficha, con un cambio nuevo respecto a hoy:
  **solo a fichas activas en la zona de rol** — al menos un post en un hilo
  de un foro dentro de `fid=10` (zona de rol) o cualquiera de sus subforos,
  en los últimos 2 meses. No alcanza con postear en cualquier parte del
  foro (`mybb_users.lastpost` global no sirve para esto) ni con solo entrar
  (`lastvisit`). Un usuario sin posts de rol recientes no recibe el cofre,
  aunque tenga ficha.
- Queda registrado en auditoría igual que el resto (ver 5.5).

## 6. Requisitos no funcionales

- **Seguridad:** toda entrada validada y escapada; verificación CSRF en cada
  guardado; la página de gestión de cofres en sí no toca
  `mybb_op_inventario` de otros jugadores — esa capacidad vive solo en la
  herramienta separada de la consola (5.7).
- **Consistencia con el resto del proyecto:** mismo patrón de acceso que
  otras herramientas de staff (`op/staff/*.php`).
- **Diseño visual:** la interfaz sigue [style.md](style.md) como referencia
  de diseño (paleta, componentes, sombras de cómic, etc. — mismo sistema que
  ya usan páginas como el asistente RAG o las fichas rediseñadas). El diseño
  debe ser moderno e intuitivo: nada de tablas crudas de solo lectura o
  formularios sueltos — pensar la edición de filas de recompensa, el cálculo
  de probabilidades y el alta/edición de cofres como una herramienta real,
  no un panel de administración genérico.
- **No modificar `opg/tirada_cofre.php` en vivo:** cualquier cambio que
  necesite ese archivo (la validación dinámica de 4.3) se hace en
  `opg/tirada_cofre2.php`.
- **Sin downtime del sistema de cofres:** mientras se construye esto, la
  tirada real (`opg/tirada_cofre.php`) sigue funcionando con el switch viejo
  hasta que el rediseño de 4.2 esté probado.

## 7. Criterios de aceptación

1. Un administrador puede crear un `cofre_id` nuevo, cargarle 3 filas de
   recompensa (una de cada tipo) y un jugador con ese ítem en inventario
   puede abrirlo y recibir una recompensa válida, sin que nadie haya tocado
   código.
2. Editar el peso de una fila cambia de forma visible la probabilidad
   calculada en la misma pantalla.
3. Una fila `Custom` con `{"nikas": 5, "berries": 200000}` le da exactamente
   eso al jugador al abrir el cofre, sin pasar por ningún switch de PHP.
4. No existe ninguna forma, desde esta página, de eliminar un `cofre_id`
   completo.
5. Un usuario sin permiso de administrador no puede acceder a la página.
6. El cofre bromista `CFF010` sigue funcionando exactamente igual que antes,
   sin que esta página lo toque ni lo liste.
7. Un administrador puede repartir un cofre a todas las fichas desde la
   consola de staff, y un usuario con `is_admin($uid)` en falso no puede
   hacerlo aunque conozca la URL directa.

## 8. Migración de las filas "Custom" existentes

Las filas que ya existen en `mybb_op_cofres` con el código de texto viejo
(`'5N200KB100PO20E'`, etc.) se convierten al JSON nuevo (4.2) con un **script
de migración de una sola vez**, no a mano desde la página nueva. El script
recorre todas las filas `tipo='Custom'`, traduce cada código conocido del
switch (`procesarCustomRecompensa()`) a su `custom_data` JSON equivalente, y
se corre antes de poner en producción el rediseño de 4.2 — no conviven el
switch viejo y el JSON nuevo para las mismas filas.

Sin preguntas abiertas pendientes.
