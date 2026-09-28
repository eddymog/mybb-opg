# Cronología de personajes: plan de diseño

> Depende de: [100_Requirements_Cronologia.md](100_Requirements_Cronologia.md)

## 1. Objetivo

Rehacer desde cero la página `/op/cronologia.php` (código legado, sin
enlaces desde otras páginas) para mostrar, sobre un
calendario de estaciones, los temas de rol en los que ha participado un
personaje según la fecha in-game del tema (`year`, `estacion`, `day` de
`mybb_threads`).

La primera entrega incluye:

- vista de estación (rejilla fija de 5 × 18);
- vista de lista de la estación (títulos completos);
- vista de año (4 estaciones con marcas de actividad);
- overlay centrado con los temas del día al pasar el ratón, fijable con clic;
- filtros por tipo de tema y por estado (abierto/cerrado);
- marcador de días con más de un tema;
- acceso restringido a usuarios con sesión y ficha;
- buscador de personajes en la cabecera (sección 7.5);
- rol del usuario en cada tema (personaje o narrador), pestaña de edición,
  filtro por rol y aviso de narrador visible entre cronologías (sección 6.5
  y 7.3.3);
- enlace desde `op/personaje.php`: el ícono que antes abría el modal de
  texto libre "cronología" (`mybb_op_fichas.cronologia`, un workaround previo
  para pegar la URL de una cronología externa) ahora enlaza directamente a
  `/op/cronologia.php?uid={$ficha['fid']}` (sección 2).

No incluye: lista de temas sin fecha, eventos de isla, comparación entre
personajes, caché, ni sustituir la convención de texto `[narrador]` de
`op/staff/recompensasAventuras.php`.

## 2. Estado actual relevante

- `op/cronologia.php` (162 líneas) dibuja un calendario vacío de 7 columnas con
  navegación por estación y año. Calcula día de la semana desde una época
  (Primavera 725). Su plantilla es `op_cronologia`
  (`templates/One_Piece_Gaiden_Templates/op_cronologia.html`).
- La página tiene activos `error_reporting(E_ALL)` y `display_errors` con el
  comentario "DEPURACIÓN TEMPORAL". Se retiran en esta entrega.
- No existe otro código que llame a `cronologia.php` ni hay enlaces a ella
  desde el header.
- `mybb_op_fichas.cronologia` era un texto libre del jugador (URL de una
  cronología externa, ej. un Google Doc), sin relación con esta página. El
  ícono de `op/personaje.php` que abría su modal (`openCronologiaModal()`,
  `templates/One_Piece_Gaiden_Templates/op_personaje.html`) ahora enlaza a
  `/op/cronologia.php?uid={$ficha['fid']}` en su lugar (decisión tomada,
  ver 1). El campo `cronologia` y el modal (`#cronologiaModal`,
  `jscripts/ficha_script2.js` / `jscripts/ficha/modales.js`) quedan sin usar
  pero no se borran: no es parte de esta entrega.
- Una ficha existe si hay una fila en `mybb_op_fichas` con `fid` = UID. Esta
  página no distingue el estado de aprobación (`aprobada_por`) y tampoco
  depende de `does_ficha_exist()`: consulta la tabla directamente.
- El foro FID 10 es solo el padre de toda la zona de rol y no contiene temas
  propios, por lo que `parentlist LIKE '10,%'` cubre todos los temas de rol.
- Las plantillas de `templates/One_Piece_Gaiden_Templates/` se sincronizan
  manualmente con la base de datos; hasta entonces la página seguirá usando la
  plantilla anterior.

## 3. Decisiones de arquitectura

### 3.1 Componentes

| Archivo | Cambio |
|---|---|
| `op/cronologia.php` | Reescritura: acceso, consulta, normalización, construcción de HTML y JSON, rol del tema (6.5, 7.3.3) |
| `templates/One_Piece_Gaiden_Templates/op_cronologia.html` | Nuevo CSS de rejilla 5 × 18, estructura de la página y estilos de la pestaña "Marcar roles" |
| Base de datos | Tabla nueva `mybb_op_thread_roles` (uid, tid, rol), creada de forma perezosa desde `cronologia.php` (ver 3.4) |
| Sin plugin | No se necesitan hooks: solo lectura, salvo el guardado de roles (POST propio de la página) |

### 3.2 Fuente de verdad

- Participación: `mybb_posts` (`uid`, `visible = 1`).
- Fecha del tema: `mybb_threads.year/estacion/day`.
- Zona de rol: `mybb_forums.parentlist LIKE '10,%'`.
- Nombre del personaje: `mybb_op_fichas.nombre`.
- Rol del usuario en un tema: `mybb_op_thread_roles` (ver 3.4). Ausencia de
  fila = rol `personaje`.

No se copia ni se cachea nada; un cambio de fecha desde `editar_tema.php` se
refleja en la siguiente carga.

### 3.3 Una consulta principal

Una sola consulta por carga trae todos los temas participados por el
personaje, agrupados por tema. La selección por año, estación, filtros y
"último tema" se hace en PHP sobre ese resultado. Esto evita una consulta por
vista y permite calcular el "último tema" y las marcas de la vista de año con
los mismos datos.

Se acepta traer todos los temas del personaje (cientos como máximo) a cambio
de simplicidad. Si resultara lento con datos reales, el diseño lo revisará
(sección 12).

### 3.4 Tabla `mybb_op_thread_roles`

Tabla nueva y **global** (no exclusiva de la cronología, sin prefijo `cron_`
en su nombre, para que otras herramientas la reutilicen, p. ej. recompensas):

```sql
CREATE TABLE IF NOT EXISTS `mybb_op_thread_roles` (
  `uid` INT UNSIGNED NOT NULL,
  `tid` INT UNSIGNED NOT NULL,
  `rol` ENUM('personaje','narrador') NOT NULL DEFAULT 'personaje',
  `dateline` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`uid`, `tid`),
  KEY `idx_tid_rol` (`tid`, `rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
```

- Se crea de forma perezosa (`CREATE TABLE IF NOT EXISTS`, con un flag
  estático para no repetir la sentencia en cada request), igual que hacen
  otras páginas de `/op/` (ej. `op/barco_cofre.php`). No requiere plugin ni
  migración manual, pero la misma sentencia se deja también en
  `docs/cronologia_thread_roles_migration.sql` por si se prefiere crearla a
  mano (mismo patrón que `docs/bitacora_migration.sql`).
- **Almacenamiento disperso:** solo se escribe una fila cuando alguien guarda
  un cambio desde la pestaña "Marcar roles" (ver 7.3.3); no se inserta nada
  al postear en un tema. Sin fila, el rol efectivo es `personaje`
  (`COALESCE` en las consultas).
- **Varios narradores por tema:** al ser la clave `(uid, tid)`, distintos
  usuarios pueden tener `rol = 'narrador'` en el mismo `tid` sin conflicto
  (co-narración, o narrador que cambia a mitad de tema).
- `idx_tid_rol` sirve para la consulta de "quién narra este tema" (7.3.3 y
  6.5), que filtra por `tid IN (...) AND rol = 'narrador'`.

## 4. Acceso

Orden de comprobaciones:

1. Sin sesión (`$mybb->user['uid'] == 0`): `error_no_permission()`.
2. El usuario conectado debe tener ficha (una fila en `mybb_op_fichas` con
   `fid` = su UID). Si no, `error_no_permission()`.
3. `uid` objetivo = `?uid=` (entero) o, si falta o es 0, el del usuario
   conectado.
4. El objetivo debe tener ficha; si no, `error()` con mensaje
   genérico ("Personaje no encontrado").
5. Fichas de la facción `Staff` solo las ve el staff, igual que en
   `op/personaje.php` (`$ficha_staff`), para no revelar a través de la
   cronología lo que la ficha oculta.

Los usuarios `is_staff` sin ficha propia quedan fuera, según el requisito
5.1. Si el staff necesitara acceso sin ficha, se añade una excepción explícita
en este punto.

### 4.1 Acceso a "Marcar roles" y al guardado

- Ver la pestaña `vista=roles`: mismo acceso que el resto de la página
  (sesión + ficha propia); no hay restricción adicional para *ver* el
  formulario de un personaje ajeno (igual que hoy se puede ver su
  cronología completa).
- Guardar cambios (`action=guardar_roles`, ver 6.5.2): si el `uid` del
  formulario no es el del usuario conectado, se exige `is_staff($viewer_uid)`;
  si no, `error_no_permission()`. Esto se comprueba en servidor, no en el
  cliente.

## 5. Parámetros de URL

| Parámetro | Valores | Por defecto |
|---|---|---|
| `uid` | entero > 0 | usuario conectado |
| `y` | entero 700 a 9999 | año del último tema (o 725) |
| `t` | `primavera`, `verano`, `otono`, `invierno` | estación del último tema (o `primavera`) |
| `vista` | `estacion`, `lista`, `anio`, `roles` | `estacion` |
| `tipo` | id de prefijo (3, 1, 6, 9 o el de Diario) o 0 | 0 (todos) |
| `rol` | `personaje`, `narrador` (filtro; no aplica en `vista=roles`) | (todos) |
| `cerrados` | `0` para ocultar temas cerrados | mostrar todos |
| `alcance` | `estacion`, `global` (solo con `vista=lista`) | `estacion` |
| `orden` | `desc` (recientes primero), `asc` (solo en modo global) | `desc` |
| `pagina` | entero ≥ 1 (modo global o `vista=roles`) | 1 |
| `action` | `buscar_personajes` (con `q`) o `guardar_roles` (POST, ver 6.5.2) | página normal |

Valores fuera de rango se corrigen al valor por defecto o al límite más
cercano; no se muestran errores. Todos los parámetros se reflejan en los
enlaces de navegación.

## 6. Consulta y normalización

### 6.1 Consulta

```sql
SELECT t.tid, t.subject, t.year, t.estacion, t.day, t.prefix,
       t.closed, t.dateline, t.fid, f.parentlist, f.parent_isla, COUNT(*) AS posts
FROM mybb_posts p
JOIN mybb_threads t ON t.tid = p.tid
JOIN mybb_forums f ON f.fid = t.fid
WHERE p.uid = {$uid}
  AND p.visible = 1
  AND t.visible = 1
  AND f.parentlist LIKE '10,%'
  AND t.fid NOT IN ({$inaccesibles})   -- solo si hay foros inaccesibles
GROUP BY t.tid, t.subject, t.year, t.estacion, t.day, t.prefix,
         t.closed, t.dateline, t.fid, f.parentlist, f.parent_isla
```

- `{$uid}` se fuerza a entero.
- `{$inaccesibles}` viene de `get_unviewable_forums(true)`, que respeta
  permisos de foro y contraseñas del visitante. Se omite la cláusula si la
  cadena está vacía.
- No se filtra por año en SQL porque `year` es `varchar` con posibles
  espacios; la normalización se hace en PHP.

### 6.2 Normalización (PHP)

Por cada fila:

- `year`: se recorta; válido si es solo dígitos y ≥ 700.
- `estacion`: se recorta, minúsculas, `ñ` → `n`; válido si es `primavera`,
  `verano`, `otono` o `invierno`.
- `day`: se recorta; válido si es solo dígitos entre 1 y 90.

Una fila con cualquier campo inválido se descarta. No se registra ni se
lista.

### 6.3 Estructuras resultantes

Tras normalizar se construye:

- `$temas[]`: lista con `tid`, `titulo`, `year`, `estacion` (slug), `dia`,
  `prefijo`, `cerrado`, `posts`, `dateline`.
- Índice por `(year, estacion, dia)` para la vista de estación y por
  `(year, estacion)` con conteo por día para la vista de año.
- "Último tema": el de mayor `(year, índice de estación, día)`, con
  `dateline` como desempate.

Los filtros `tipo` y `cerrados` se aplican a `$temas` antes de construir los
índices, de modo que ambas vistas y el "último tema" los respetan.

### 6.3.1 Isla del tema

Una consulta adicional (la tercera, sin consultas por tema) resuelve la isla:

```sql
SELECT fid, name, isla_rol FROM mybb_forums WHERE fid IN ({$ids})
```

`{$ids}` son los enteros de los `parentlist` de los temas mostrados más sus
`parent_isla`. En PHP, por cada tema se recorre su `parentlist` de hoja a raíz
y se toma el primer foro con `isla_rol = 1`; si no hay, el `parent_isla`; si
tampoco, el nombre del foro del tema como lugar sin enlace (requisito 5.5.1).
El resultado es `{nombre, url}`, donde `url` es `/op/isla.php?isla_id=<fid>` si
es una isla y vacío si es el respaldo.

### 6.4 Otros participantes

Para los temas visibles en la vista actual (estación o año) se hace **una
segunda consulta**:

```sql
SELECT DISTINCT p.tid, p.uid, COALESCE(fi.nombre, p.username) AS nombre
FROM mybb_posts p
LEFT JOIN mybb_op_fichas fi ON fi.fid = p.uid
WHERE p.tid IN ({$tids}) AND p.uid <> {$uid} AND p.visible = 1
```

`{$tids}` son enteros ya validados. En PHP se limita a 5 nombres por tema y se
añade `+N` con el resto. Junto con la de 6.3.1, son las únicas consultas por carga.

### 6.5 Rol del tema (personaje / narrador)

#### 6.5.1 Lectura: rol propio y narradores del tema

Dos consultas más sobre `mybb_op_thread_roles`, ambas acotadas a los `tid` ya
cargados en la vista actual (mismo patrón que 6.3.1 y 6.4: nunca una consulta
por celda):

```sql
-- Rol del personaje mostrado en sus propios temas (para el filtro "Rol"
-- y para preseleccionar la pestaña "Marcar roles")
SELECT tid, rol FROM mybb_op_thread_roles WHERE uid = {$uid} AND tid IN ({$tids})

-- Quién narra cada tema (para "Narra: ..."), sin importar de quién es
-- la cronología que se está viendo
SELECT r.tid, COALESCE(fi.nombre, r.uid) AS nombre
FROM mybb_op_thread_roles r
LEFT JOIN mybb_op_fichas fi ON fi.fid = r.uid
WHERE r.tid IN ({$tids}) AND r.rol = 'narrador'
ORDER BY r.tid, nombre
```

- Rol efectivo de cada tema del personaje mostrado: `personaje` si no
  aparece en la primera consulta.
- La segunda consulta puede devolver varias filas por `tid` (varios
  narradores); se agrupan igual que "otros participantes" (6.4), sin límite
  de 5 porque no se espera que haya muchos narradores por tema.
- El filtro `rol` (sección 5) se aplica sobre `$temas` junto con `tipo` y
  `cerrados`, antes de construir los índices por día (igual que 6.3).
- "Narra: ..." se añade al lado de "Con: ..." en el overlay (7.3) y en la
  vista de lista (7.3.1), para **cualquier** cronología donde aparezca ese
  tema, no solo la de quien narra. No se añade a la casilla del día, igual
  que "otros participantes".

#### 6.5.2 Guardado: pestaña "Marcar roles"

`action=guardar_roles`, POST, desde el formulario de 7.3.3:

1. `verify_post_check($mybb->get_input('post_code'), true)`; si falla, error
   estándar del proyecto.
2. Si `uid` del formulario ≠ `$viewer_uid`, exigir `is_staff($viewer_uid)`
   (ver 4.1).
3. Recalcular en servidor los `tid` válidos para ese `uid`: los mismos que
   arma la consulta principal (6.1), filtrados a prefijo Aventura (3) o
   Evento (6). **No se confía en la lista de `tid` que manda el formulario**:
   solo se procesan los que también salen de este recálculo.
4. El formulario manda el radio de **todas** las filas de la página, se
   hayan tocado o no (así funcionan los grupos de radio en HTML). Por eso,
   por cada `tid` válido presente en el POST con un valor `personaje` o
   `narrador` (cualquier otro valor se ignora), se compara contra el rol ya
   cargado en el recálculo del punto 3: si es igual, se descarta sin
   escribir; si cambió, `INSERT INTO mybb_op_thread_roles (uid, tid, rol,
   dateline) VALUES (...) ON DUPLICATE KEY UPDATE rol = VALUES(rol),
   dateline = VALUES(dateline)`. Evita escribir (y generar ruido en
   `dateline`) en las filas que nadie tocó.
5. Redirige (o, con htmx, refresca `#cron-raiz`) a `vista=roles` del mismo
   `uid`, con un aviso breve de guardado solo si al menos un `tid` cambió de
   rol.

## 7. Vistas

### 7.1 Vista de estación

Rejilla fija de 5 columnas × 18 filas con los 90 días, del 1 al 90 de
izquierda a derecha y de arriba abajo. Sin encabezados de día de la semana ni
huecos.

Cada celda muestra:

- número de día;
- hasta 2 temas, cada uno un enlace con, en línea y en este orden: la etiqueta
  de tipo (solo si el filtro de tipo es "Todos", color por tipo, ver 9.1), la
  etiqueta de rol (solo si el filtro de rol es "Todos" y el tema es Aventura
  o Evento, color por rol, ver 9.1), su `#tid` y el título en hasta 2 líneas
  (`line-clamp: 2`); el `title` lleva tipo, rol (si aplica), `#tid`, título
  completo e isla;
- `+N más` si hay más de 2;
- un marcador de coincidencia (esquina de la celda) si hay ≥ 2 temas.

Al pasar el ratón o enfocar una celda con temas se muestra el overlay
(sección 7.3).

En móvil (≤ 700 px) la celda solo muestra número de día y un punto de
actividad; los títulos van en el overlay al tocar la casilla.

### 7.2 Vista de año

Cuatro bloques (Primavera, Verano, Otoño, Invierno), cada uno una rejilla
5 × 18 en miniatura donde cada día con temas se marca con el color de la
estación. Sin títulos. Al pulsar un bloque o la cabecera de una estación se
abre la vista de estación de esa estación.

### 7.3 Overlay del día

Tarjeta centrada en el viewport (`position: fixed`), rellenada en el cliente
sin recargar la página. Muestra, por cada tema del día ordenado por `dateline`
ascendente:

- título (enlace a `get_thread_link($tid)`) y su ID (`#tid`) en monoespaciado;
- fecha in-game (año, estación, día);
- tipo (nombre del prefijo);
- rol, con color, si el filtro de rol es "Todos" y el tema es Aventura o
  Evento (9.1);
- isla (chip enlazado a `/op/isla.php?isla_id=` si es isla);
- estado (Abierto / Cerrado);
- número de posts del personaje;
- otros participantes (hasta 5 y `+N`);
- si hay uno o más narradores marcados (6.5.1), "Narra: <nombre(s)>", debajo
  de "Con: ...".

Estados:

- **Hover / foco:** aparece tras ~150 ms sobre una casilla con temas y
  desaparece al salir. Es informativo (`pointer-events: none`), para que no
  intercepte el ratón ni parpadee.
- **Fijado:** clic, Enter/Espacio o toque en una casilla fija el overlay. Ahí
  sí acepta ratón: los enlaces se pueden pulsar; se cierra con el botón "×",
  con Escape o pulsando fuera. La casilla fijada queda resaltada.
- En pantalla táctil no hay hover: el toque fija directamente.

Con JavaScript desactivado, cada celda enlaza al tema con los títulos visibles;
el overlay no aparece.

### 7.3.1 Vista de lista

`vista=lista`: barra de acento con el título de la estación y, debajo, una fila
por tema (orden: día, `dateline`, `tid`) con chip de día, título completo
enlazado, isla, tipo, rol (con color, si aplica, ver 9.1), estado, posts del
personaje, otros participantes (hasta 5 y `+N`) y, si aplica, "Narra:
<nombre(s)>" (6.5.1). Filas alternas como el overlay. Sin temas: `.opg-vacio`
con "Sin temas en esta estación." Usa los mismos datos que la vista de
estación (`$por_dia`, la segunda consulta de participantes y la de
narradores), así que no añade consultas nuevas.

### 7.3.2 Modo lista global

`vista=lista&alcance=global`. Usa los temas ya cargados por la consulta
principal (todos los años), así que no añade consultas de datos.

1. Se ordenan por `(año, índice de estación, día, dateline, tid)`; con
   `orden=desc` (por defecto) se invierte el orden completo.
2. Se calcula `total`, `paginas = ceil(total / 100)` y `pagina` acotada a
   `1..paginas`; se toma el tramo de 100 temas.
3. La consulta de otros participantes (6.4) se hace solo para los `tid` del
   tramo (máximo 100).
4. Se recorre el tramo insertando una barra de acento cada vez que cambia el
   par (año, estación), con el total de temas de esa estación (del conjunto
   completo, no del tramo); la barra se repite al inicio de cada página.
5. Cada fila es la de la vista de lista (función común).
6. Controles de página arriba y abajo: `.opg-chip` anterior/siguiente y
   números (con "…" para rangos largos), más "Temas a–b de total". Los enlaces
   conservan `tipo`, `cerrados` y `orden` (`cron_url()`).
7. Controles del modo: chips "Esta estación" / "Todo el historial" dentro de la
   vista de lista y un chip para invertir el orden. Se ocultan la navegación por
   estación, el selector de año y estación y "Último tema".

### 7.3.3 Vista "Marcar roles"

`vista=roles`. Cuarta pestaña, junto a Estación / Lista / Año (7.4). Solo
accesible si `uid === viewer_uid` o `is_staff(viewer_uid)` (4.1); si no,
mismo tratamiento que un acceso indebido a `uid` (`error_no_permission()`).

- **Contenido:** de `$temas_todos` (sin filtrar por `tipo`/`cerrados`/`rol`
  de la sesión actual) se toman solo los de prefijo Aventura (3) o Evento
  (6), sin agrupar por día ni estación, ordenados por fecha in-game
  descendente (más reciente primero).
- **Paginación:** de 100 en 100, igual que el modo lista global (7.3.2):
  parámetro `pagina` (reutilizado, ver sección 5), controles arriba y abajo
  con `cron_paginacion_html()` y texto "Temas a–b de total". Cada página es
  un lote independiente: el botón "Guardar cambios" de una página solo
  envía los `tid` que están en esa página.
- **Fila:** `#tid`, título (enlace normal `hx-boost="false"`, no se edita
  desde aquí), fecha in-game, y un grupo de dos botones de radio
  Personaje/Narrador (`name="roles[<tid>]"`), marcado según el rol efectivo
  (6.5.1).
- **Formulario:** un único `<form method="post">` por página, que envuelve
  las filas de esa página, con `post_code` (CSRF), `uid` y `pagina` ocultos,
  y un botón "Guardar cambios" arriba y abajo. Al enviar, POST a
  `cronologia.php?action=guardar_roles` (6.5.2); la respuesta vuelve a la
  misma página de `vista=roles` con un aviso de guardado.
- Sin temas de Aventura/Evento: `.opg-vacio` con "Este personaje no tiene
  temas de Aventura o Evento para marcar."
- Esta vista no participa de la navegación por estación/año ni de los
  filtros de tipo/cerrados/rol (7.4): son conceptos de las otras tres
  vistas.

### 7.4 Navegación y filtros

- Toolbar: anterior, título ("Verano 725"), siguiente, selector año +
  estación, botones "Estación" / "Lista" / "Año" / "Marcar roles", botón "Ir
  al último tema". El botón "Marcar roles" solo aparece si `uid ===
  viewer_uid` o `is_staff(viewer_uid)` (4.1).
- Formulario de filtros: selector de tipo, selector de rol (Todos /
  Personaje / Narrador) y casilla "Ocultar cerrados". El filtro de rol no se
  muestra en `vista=roles`.
- Anterior/siguiente cruzan el año (Invierno 725 → Primavera 726). "Anterior"
  no baja de Primavera 700.
- Se reutiliza la función de navegación actual, ajustada al mínimo 700.

### 7.5 Buscador de personajes

Reutiliza el patrón de `op/aventuras_personaje.php`: htmx contra un endpoint
propio de la página, `.opg-buscar-caja` y `.opg-resultados`.

- **Ubicación:** dentro de la cabecera naranja, junto al título.
- **Endpoint:** `cronologia.php?action=buscar_personajes&q=...`, después del
  control de acceso (sesión y ficha) y antes de la consulta principal. Exige
  3 caracteres o un número. Busca en `mybb_op_fichas` por `nombre` y `apodo`
  (`LIKE` con `%` y `_` escapados) y por `fid` si el texto es numérico; máximo
  12 resultados, exactos primero. Excluye la facción `Staff` salvo para el
  staff. Devuelve HTML con botones `.opg-resultado` (`data-fid`), todo
  escapado. Sin coincidencias no devuelve nada.
- **Cliente:** el campo dispara la búsqueda con `keyup changed delay:250ms`.
  Un script propio (sin `$`) redirige a `cronologia.php?uid=<fid>` al pulsar
  un resultado o Enter (toma el primero); Escape o un clic fuera cierra la
  lista. No conserva ningún filtro ni la vista.
- **Acceso a la propia cronología:** si `uid` no es el del usuario conectado,
  la cabecera muestra un `.opg-chip` "Ver mi cronología" a `cronologia.php`.
- htmx se carga desde `jscripts/vendor/htmx-2.0.10.min.js`, ya usado por otras
  páginas. No se usa Alpine.js: el resto de la página es JavaScript sin
  dependencias.

## 8. Datos hacia el cliente

El panel necesita los datos de los temas de la estación mostrada. PHP genera
un objeto JSON `{ "<dia>": [ {tema}, ... ] }` con los campos de 7.3 ya
escapados (incluidos `otros`, `narradores` si aplica (6.5.1), y `rolMostrar`/
`rol`/`rolTexto` para la etiqueta de color del rol, ver 9.1) y lo coloca
en un atributo `data-dias` del contenedor del calendario. El JS lo lee con
`JSON.parse`. No se usa `eval` ni se interpola JSON en un script inline.

## 9. Plantilla y JavaScript

- La plantilla `op_cronologia` se reescribe siguiendo [style.md](style.md); el
  diseño visual está en 9.1.
- Los datos del calendario y el HTML de las vistas se construyen en PHP y se
  inyectan como `{$calendar_html}`.
- El JavaScript del panel va como cadena PHP (nowdoc) inyectada en la
  plantilla mediante una variable, no escrito dentro de la plantilla. Motivo:
  las plantillas de MyBB pasan por `eval` y un `$` o comillas en el JS las
  rompen o interpolan. El JS es vanilla, sin jQuery.
- Se retira el enlace a `cache/themes/cronologia.css`: el archivo no existe.
  Los estilos van dentro de la plantilla.

### 9.1 Diseño visual

Basado en [style.md](style.md), sin colores ni botones propios: solo
variables de `opg-tokens.css` y componentes de `opg-components.css`, que ya
carga `{$headerinclude}`.

- **Marco:** `indice > mainBackground > secondBackground > thirdBackground`,
  con `thirdBackground` fluido (`width: 100%; max-width: 1030px`) y
  `text-align: left`.
- **Cabecera (con buscador, 7.5):** franja naranja con trama (`--opg-textura-trama`), borde negro,
  sombra desplazada; título en `moonGetHeavy` blanco con contorno negro y
  enlace "← Ver ficha" como `.opg-volver`.
- **Controles:** flechas anterior/siguiente como `.btn-op--sm`; título de
  período en `moonGetHeavy` oscuro sin contorno; vistas (Estación, Año, Ir al
  último tema) como `.opg-chip`, la activa en naranja; filtros con `.af-field`
  y "Aplicar" como `.btn-op--primario`.
- **Un color de acento por estación:** Primavera verde éxito, Verano naranja,
  Otoño rojo facción, Invierno azul, aplicado a la barra del calendario, al
  día seleccionado y a las marcas de la vista de año.
- **Estación:** barra de acento y cuerpo crema con borde negro; cada día es
  una viñeta con borde de 2px. Días con temas en crema dorada, sombra
  desplazada y `scale(1.05)` al pasar el ratón; temas como píldoras moradas
  claras (contraste sobre crema); insignia ciruela cuando hay varios temas.
- **Overlay del día y vista de lista:** misma barra y cuerpo, sombra desplazada, filas alternas
  `--opg-crema-dorada` / `--opg-crema`; título de tema en `moonGetHeavy`;
  etiquetas de tipo, estado (verde abierto, gris cerrado) y posts.
- **Año:** cuatro tarjetas viñeta con franja superior de acento, cada una con
  una mini rejilla de 5 columnas; al pasar el ratón se levantan y giran.
- **Color por tipo** (etiqueta en casilla, overlay y lista; borde negro y
  sombra de texto para el contraste): Aventura naranja, Común gris bloqueado,
  Evento dorado con texto ciruela, Autonarrada morado, Diario azul; MT,
  Requerimiento y sin tipo, ciruela. La clase se deriva del nombre del tipo
  (`cron-tipo--aventura`, `--comun`...), sin depender del ID del prefijo.
- **Color por rol** (mismo patrón que el de tipo: solo con el filtro de rol
  en "Todos", en la casilla, el overlay y la lista, solo en temas de
  Aventura o Evento): Personaje azul (`--opg-azul`), Narrador ciruela
  (`--opg-ciruela`, texto blanco). Se reutilizan tokens ya usados en la
  página en vez de sumar colores nuevos: azul es el mismo de "Diario" y
  ciruela es el mismo de la etiqueta "Narra: ...", lo que refuerza la
  asociación en vez de generar un color más que aprender. Clases
  `cron-rol--personaje` / `cron-rol--narrador`.
- **"Marcar roles":** filas como las de la vista de lista, sin barra de
  acento por estación; el selector Personaje/Narrador como un `.opg-chip`
  de dos estados (segmented control), botón "Guardar cambios" como
  `.btn-op--primario`. La etiqueta "Narra: ..." usa el mismo estilo que "Con:
  ...", en un tono distinguible (ciruela, como el resto de etiquetas sin tipo
  propio) para no confundirse con "Con:".
- **Vacío:** `.opg-vacio`.
- **Tipografía:** `moonGetHeavy` solo en títulos; el resto en Inter.
- **Móvil (≤ 700 px):** días de 40 px con número y punto; los títulos pasan al
  panel; la vista de año a una columna.
- **Sin iconos:** no se usa Font Awesome, así que no hay riesgo con los
  shims v4 del header.

### 9.1.1 Guía "Cómo funciona la cronología"

`<details class="cron-guia" id="cron-guia" hx-preserve="true">` entre la
cabecera y los controles, con el patrón de `op_bitacora_contenido.html`
(`op-temas-guia`): barra morada (`--opg-morado-cta`) con icono de ayuda y
flecha, introducción con borde naranja, cinco secciones numeradas en dos
columnas (una en móvil) y nota final con bombilla. Sus estilos son propios
(`cron-guia*`) porque `op_bitacora.css` no se garantiza en esta página. El HTML
es estático, generado en PHP (nowdoc), y `hx-preserve` mantiene su estado al
cambiar de vista. Los iconos son Font Awesome 6 (`fa-solid`), que ya carga
`{$headerinclude}`.

### 9.2 Navegación sin recarga (htmx, `hx-boost`)

El contenedor `#cron-raiz` (la raíz de la cronología, incluida su clase de
estación) lleva `hx-boost="true"`, `hx-target="#cron-raiz"`,
`hx-select="#cron-raiz"`, `hx-swap="outerHTML show:top"` y
`hx-indicator="#cron-raiz"`. Los enlaces y el formulario de filtros pasan a
cargarse por AJAX; el servidor responde con la página completa (sin cambios
de PHP) y htmx toma solo `#cron-raiz`. La URL se actualiza (`hx-push-url`) y
Atrás funciona; sin JavaScript se comporta como enlaces normales.

- **Enlaces que salen de la página** (temas, islas, "Ver ficha"): llevan
  `hx-boost="false"` para navegar de forma normal.
- **Buscador de personajes:** su campo redefine los atributos heredados
  (`hx-select="unset"`, `hx-swap="innerHTML"`, `hx-push-url="false"`,
  `hx-indicator="closest .cron-buscar"`). Elegir un resultado sigue siendo una
  navegación completa.
- **JavaScript:** el overlay y el buscador usan delegación de eventos sobre
  `document` (el script está fuera de `#cron-raiz`, siempre presente), porque
  sus elementos se reemplazan en cada cambio. Al terminar un cambio se cierra
  el overlay, se anula su estado y se enfoca el título del período.
- **Errores:** si la respuesta no contiene `#cron-raiz` (sesión caducada, error
  de MyBB), se cancela el cambio y se navega de forma normal a esa URL, para
  no dejar la página en blanco.
- **Indicador de carga:** la clase `htmx-request` de `#cron-raiz` atenúa el
  contenido mientras carga.

## 10. Seguridad

- Todos los parámetros se validan con lista blanca o `(int)`. No hay
  interpolación de cadenas del usuario en SQL.
- Salida escapada con `htmlspecialchars_uni`, incluyendo títulos, nombres y
  los valores del JSON.
- Permisos de foro respetados mediante `get_unviewable_forums(true)`.
- Se retira `display_errors`/`error_reporting` de la página.
- **Guardado de roles (6.5.2):** única acción de escritura de la página, por
  eso requiere token CSRF (`post_code`, `verify_post_check`). El servidor
  recalcula los `tid` válidos para el `uid` del formulario (Aventura/Evento
  donde participó) en vez de confiar en la lista enviada, y exige
  `is_staff($viewer_uid)` cuando el `uid` del formulario no es el del
  usuario conectado.

## 11. Casos límite

| Caso | Comportamiento |
|---|---|
| Personaje sin temas fechados | Calendario vacío en Primavera 725 con mensaje "Sin temas registrados para este personaje." |
| Temas existentes que los filtros ocultan todos | Mensaje "Ningún tema coincide con los filtros." |
| `year`, `estacion` o `day` inválido | El tema se descarta |
| Tema con varias respuestas del personaje | Una sola entrada, con conteo de posts |
| Tema movido a un foro fuera de la zona de rol | Deja de aparecer |
| Tema en foro no accesible | No aparece ni cuenta en marcadores |
| Personaje inexistente o sin ficha | Error "Personaje no encontrado" |
| Fecha de tema editada por el staff | Se refleja en la siguiente carga |
| Título con HTML o comillas | Escapado en HTML y en JSON |
| Más de 2 temas en un día | `+N más` en la celda y lista completa en el overlay |
| Año > 9999 o < 700 | Se ajusta al límite |
| Tema sin fila en `mybb_op_thread_roles` | Rol efectivo `personaje` |
| Tema con varios narradores | Todos aparecen en "Narra: ..." |
| Usuario no staff intenta guardar el rol de otro `uid` (formulario manipulado) | `error_no_permission()`, nada se guarda |
| `tid` ajeno a Aventura/Evento o que el `uid` no participó, enviado en el POST | Se ignora (recálculo server-side, 6.5.2) |
| Personaje sin temas de Aventura ni Evento, en `vista=roles` | Mensaje de vacío (7.3.3) |

## 12. Rendimiento

- Hasta cuatro consultas por carga (6.1, 6.3.1, 6.4 y, si el tema lo
  requiere, 6.5.1). No hay consultas por celda; las de 6.5.1 se saltan si la
  vista no tiene temas de Aventura/Evento.
- No se añaden índices. La consulta filtra por `posts.uid`; se confirma con
  `EXPLAIN` sobre datos reales antes de considerarla lista. Si resultara
  lenta, se propone un índice y se pide aprobación, tal como indica el
  requisito.
- La vista por defecto es la última estación con temas y las demás se
  calculan solo cuando el usuario las pide. Se asume un máximo de unos 100
  temas por personaje y estación, por lo que no se prevé optimización
  adicional.
- Los requisitos no fijan un tiempo objetivo; las pruebas de rendimiento son
  manuales.

## 13. Estrategia de pruebas

Pruebas manuales, según acordado:

1. Personaje con temas en varias estaciones y años: cada tema en el día
   correcto.
2. Día con 3 temas: contador, marcador y overlay con los 3 en orden.
3. Navegación Invierno 725 → Primavera 726, y tope en Primavera 700.
4. Vista de año: mismos días marcados que las cuatro vistas de estación.
5. Filtros por tipo y ocultar cerrados, y que se conserven al navegar.
6. Visitante sin sesión y usuario sin ficha: sin acceso.
7. Tema en foro restringido: no aparece con un usuario sin permiso.
8. Tema con fecha inválida o en foro fuera de `10,%`: no aparece.
9. Cambio de fecha desde `editar_tema.php`: se refleja al recargar.
10. Móvil (≤ 700 px): rejilla usable y overlay al tocar.
11. URL con todos los parámetros reproduce la misma vista.
12. Marcar un tema propio como narrador desde "Marcar roles" y guardar: pasa
    a aparecer en el filtro "Narrador" y como "Narra: ..." en la cronología
    de otro personaje que también está en ese tema.
13. Como staff, corregir el rol de otro `uid`: se guarda correctamente.
14. Como usuario normal, enviar por POST un `uid` ajeno: `error_no_permission()`.
15. Enviar un `tid` fuera de Aventura/Evento o ajeno al `uid`: se ignora sin
    error visible.

## 14. Fases de implementación

1. **Acceso y datos:** reescribir `op/cronologia.php` con acceso, consulta,
   normalización y filtros; volcar el resultado en texto para verificar.
2. **Vista de estación:** rejilla 5 × 18 con temas y navegación.
3. **Overlay de día y JSON.**
4. **Vista de año y vista de lista.**
5. **Plantilla y CSS finales**, retirada del bloque de depuración y
   sincronización de la plantilla con la base de datos.
6. **Rol del tema:** tabla `mybb_op_thread_roles`, lectura (6.5.1), pestaña
   "Marcar roles" (7.3.3), guardado (6.5.2) y filtro por rol.

## 15. Riesgos y pendientes

- La plantilla `op_cronologia` debe sincronizarse a mano con la base de
  datos; sin eso la página no muestra los cambios.
- Las publicaciones con personaje secreto cuentan como cualquier otra: la
  cronología se basa solo en el UID y la ficha del personaje.
- Sin enlace desde el header ni desde la ficha: la página solo es accesible
  por URL hasta que se decida dónde enlazarla.
- `mybb_op_thread_roles` es una tabla nueva y global: si en el futuro otra
  herramienta (por ejemplo, recompensas) también escribe en ella, conviene
  extraer su acceso a una función compartida en
  `op/functions/op_functions.php` en vez de duplicar el `CREATE TABLE IF NOT
  EXISTS` en cada página consumidora. No se hace en esta entrega porque
  `cronologia.php` es su único consumidor (YAGNI).
