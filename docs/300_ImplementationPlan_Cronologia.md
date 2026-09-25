# Cronología de personajes: plan de implementación

> Requisitos: [100_Requirements_Cronologia.md](100_Requirements_Cronologia.md)
>
> Diseño: [200_DesignPlan_Cronologia.md](200_DesignPlan_Cronologia.md)

## 1. Objetivo de este documento

Convierte el diseño en tareas de código ordenadas y verificables. Define
archivos, funciones, parámetros y pruebas.

La implementación se considera terminada cuando:

- `/op/cronologia.php` exige sesión y ficha, y muestra la cronología
  de un personaje (el propio por defecto, otro con `?uid=`);
- cada tema participado y con fecha válida aparece en su día, con la
  estación y el año correctos;
- existen la vista de estación (rejilla 5 × 18), la vista de lista, la vista de año, el overlay de
  día, los filtros y el botón "Último tema";
- los temas sin fecha válida, en foros fuera de la zona de rol o en foros
  inaccesibles para quien consulta no aparecen;
- no se modifica la base de datos, `global.php`, `op/personaje.php` ni
  `op/ficha.php`;
- la plantilla `op_cronologia` queda sincronizada con la base de datos.

## 2. Punto de partida

`op/cronologia.php` es código legado (calendario vacío de 7 columnas, sin
enlaces desde otras páginas y con `display_errors` activo), y su plantilla
`op_cronologia` referencia un CSS inexistente. Ambos se reemplazan por
completo; no se reutiliza su lógica salvo el modelo de tiempo (4 estaciones de
90 días).

Cada tarea de la sección 5 indica qué debe cumplir el código y cómo
verificarlo. Las tareas 6 (pruebas con la base real) y 7 (despliegue) son
manuales.

## 3. Archivos

### 3.1 Modificar

| Archivo | Cambio |
|---|---|
| `op/cronologia.php` | Reescritura completa |
| `templates/One_Piece_Gaiden_Templates/op_cronologia.html` | CSS de rejilla 5 × 18, overlay, vista de lista, vista de año; sin enlace a `cache/themes/cronologia.css` (no existe) |

### 3.2 No se toca

- `global.php`, `inc/` (núcleo de MyBB) y cualquier plugin;
- `op/ficha.php` (código legado) y `op/personaje.php` (el enlace desde la
  ficha se decide más adelante);
- `op/functions/op_functions.php`;
- `header.html`: no hay enlace en el menú por ahora;
- base de datos: sin tablas, columnas, índices ni triggers.

### 3.3 Sincronización

Las plantillas del repositorio se sincronizan a mano con la base de datos.
Sin sincronizar `op_cronologia`, la página seguirá usando la plantilla
anterior.

## 4. Convenciones

- Patrón de página de `/op/`: definir `IN_MYBB` y `THIS_SCRIPT`, incluir
  `../global.php` y `functions/op_functions.php`.
- Sin `display_errors` ni `error_reporting` dentro de la página.
- SQL con interpolación: solo enteros forzados con `(int)` o listas de
  enteros validadas con `preg_match`. Ninguna cadena del usuario entra en
  una consulta.
- Salida con `htmlspecialchars_uni` (o `htmlspecialchars` con `ENT_QUOTES`
  dentro de atributos).
- El JavaScript no va dentro de la plantilla, va como cadena PHP (nowdoc)
  inyectada con `{$cronologia_script}`. No usa `$` ni jQuery.
- Los datos hacia el cliente viajan como JSON dentro de un atributo
  `data-dias`, nunca interpolados en un `<script>`. El cliente crea los nodos
  con `textContent`.
- Español neutro en todos los textos de la interfaz.

## 5. Tareas

### Tarea 1: acceso y parámetros

**Archivo:** `op/cronologia.php`

1. Constantes: año mínimo 700, máximo 9999, año por defecto 725, 90 días, tope
   de 5 participantes; mapas de estaciones (slug → etiqueta) y de tipos de
   tema (prefijo → nombre). Los filtros son Aventura (3), Común (1), Evento (6),
   Autonarrada (9) y Diario; el ID de Diario se busca por nombre en
   `mybb_threadprefixes` (no hay ID fijo en el repositorio). MT (10) y
   Requerimiento (14) no son filtro, pero conservan su etiqueta.
2. Sin sesión (`uid` 0): `error_no_permission()`.
3. `cron_ficha($uid)`: lee `fid, nombre, faccion` de `mybb_op_fichas`;
   devuelve `null` si no hay fila para ese `fid`.
4. El usuario conectado debe tener ficha; si no, `error_no_permission()`.
5. `uid` objetivo: `?uid=` o el conectado. Si no tiene ficha, o es de la
   facción `Staff` y quien consulta no es `is_staff`: `error('Personaje no
   encontrado.')`.
6. Parámetros: `vista` (`estacion` por defecto o `anio`), `tipo` (solo
   prefijos conocidos), `cerrados` (`0` oculta cerrados), `y`, `t`.

**Verificación:** sin sesión y con usuario sin ficha no hay acceso; `uid`
inexistente muestra el error; parámetros inválidos se ignoran sin errores.

### Tarea 2: consulta y normalización

**Archivo:** `op/cronologia.php`

1. `get_unviewable_forums(true)`: si devuelve una lista de enteros, agregar
   `AND t.fid NOT IN (...)`; si está vacía, omitir.
2. Consulta principal (diseño 6.1): temas donde el personaje tiene posts con
   `visible = 1`, tema `visible = 1`, foro con `parentlist LIKE '10,%'`,
   agrupados por tema con `COUNT(*)` de posts. Incluye `t.fid`,
   `f.parentlist` y `f.parent_isla` (también en `GROUP BY`).
3. `cron_normalizar_fecha($year, $estacion, $day)`: recorta; año entero
   700–9999, día entero 1–90; estación en minúsculas sin tildes (`Otoño` →
   `otono`) y dentro de las 4 válidas. Devuelve `null` si algo falla.
4. Recorrer resultados: descartar fecha inválida; aplicar `cerrados` y
   `tipo`; construir la lista `$temas`.
5. Calcular el "último tema" por `(año, estación, día, dateline)`.
6. Determinar `y` y `t` mostrados: parámetros, si no el último tema, si no
   Primavera 725.

**Verificación:** un tema con `year='0'`, día 91 o año 699 no aparece; `Otoño`,
`otono` y espacios sobrantes se aceptan; un tema con 4 posts del personaje
cuenta una vez con `posts = 4`.

### Tarea 2.1: isla del tema

**Archivo:** `op/cronologia.php`

1. Reunir los FID de los `parentlist` y `parent_isla` de los temas que
   quedan tras los filtros y consultar `fid, name, isla_rol` de esos foros.
2. Función que, por tema, recorre el `parentlist` de hoja a raíz y elige el
   primer foro con `isla_rol = 1`; si no hay, `parent_isla`; si tampoco, el
   foro del tema como lugar sin enlace.
3. Guardar `isla` (nombre) e `isla_url` en cada tema.

**Verificación:** un tema en un subforo de isla muestra el nombre de la isla;
uno publicado directamente en el foro de la isla también; uno en un foro sin
isla muestra el nombre del foro sin enlace.

### Tarea 3: vista de estación y overlay de día

**Archivo:** `op/cronologia.php`

1. Agrupar por día los temas de la estación y año mostrados; ordenar por
   `dateline` y `tid` ascendentes.
2. Segunda consulta de otros participantes solo si hay temas en la estación:
   `DISTINCT` por tema, con `COALESCE(fichas.nombre, posts.username)`, excluye
   al propio personaje, `visible = 1`. Tope de 5 nombres por tema y `+N`.
3. Rejilla de 90 celdas (5 columnas × 18 filas): número de día, hasta 2
   títulos como enlaces, `+N más`, contador si hay ≥ 2 temas.
4. Celdas con temas: `data-dia`, `tabindex="0"`, `role="button"` y
   `aria-label`.
5. JSON de `data-dias`: `{ temas: {dia: [...]}, fechas: {dia: texto} }`.
6. Enlaces a temas con `get_thread_link()` precedido de `bburl`, para que
   funcionen desde `/op/`.
7. Etiqueta de tipo: con `tipo` = 0, anteponer al título de cada `cron-evento`
   un `<span class="cron-tipo cron-tipo--<clase>">`; el tipo con color también
   en el overlay (`tipoClase` en el JSON) y en la vista de lista. La clase sale
   de `cron_tipo_clase()` (nombre en minúsculas y sin tildes).
8. Overlay y JSON: incluir `isla` e `isla_url`; el overlay muestra la isla
   como chip (enlace si hay URL). El `title` de la casilla incluye la isla.
9. Títulos en las celdas: hasta 2 líneas (`line-clamp: 2`), con el título
   completo en `title`.
10. Overlay (diseño 7.3): tarjeta centrada `position: fixed`, rellenada con
   `textContent`. Hover o foco (retraso ~150 ms) la muestra sin interacción
   (`pointer-events: none`); clic, Enter/Espacio o toque la fija con enlaces
   pulsables, botón "×", cierre con Escape o clic fuera. Los clics sobre los
   enlaces de la celda navegan sin abrir el overlay.

**Verificación:** un día con 3 temas muestra el contador, dos títulos y
"+1 más"; el overlay lista los 3 en orden; los títulos con HTML o comillas se
ven como texto; el overlay de hover no parpadea al mover el ratón por la
rejilla; fijado, sus enlaces funcionan y Escape lo cierra.

### Tarea 3.1: vista de lista

**Archivos:** `op/cronologia.php`, `op_cronologia.html`

1. Aceptar `vista=lista` y conservarlo en `cron_url()`.
2. Cargar los otros participantes en las vistas de estación y de lista.
3. Construir una fila por tema de la estación (orden: día, `dateline`, `tid`)
   con chip de día, título completo enlazado, isla, tipo, estado, posts y otros
   participantes (hasta 5 y `+N`); filas alternas dentro de barra y cuerpo.
4. Sin temas: `.opg-vacio` con "Sin temas en esta estación."
5. Botón "Lista" entre "Estación" y "Año", con el estado activo.

**Verificación:** todos los temas de la estación aparecen con título completo y
en orden; los filtros y la navegación se conservan; sin temas se muestra el
mensaje.

### Tarea 3.2: modo lista global

**Archivos:** `op/cronologia.php`, `op_cronologia.html`

1. Parámetros `alcance` (`estacion` o `global`), `orden` (`desc` por defecto o
   `asc`) y `pagina`; conservarlos en `cron_url()` (la página solo se pasa
   explícitamente).
2. Extraer la consulta de otros participantes y la construcción de una fila de
   lista a funciones comunes usadas por la lista de estación y la global.
3. Ordenar los temas (diseño 7.3.2), paginar en tramos de 100, cargar
   participantes solo del tramo y construir las barras de año y estación con su
   total.
4. Controles: sub-chips "Esta estación" / "Todo el historial" en la vista de
   lista, chip de orden, paginación arriba y abajo. En modo global ocultar la
   navegación por estación, el selector de año y estación y "Último tema".
5. Plantilla: estilos de la paginación (`.opg-chip` con estado activo) y de la
   barra de agrupación entre filas.

**Verificación:** con más de 100 temas hay varias páginas; el orden por defecto
es de lo más reciente a lo más antiguo y el chip lo invierte; las barras
agrupan por año y estación y se repiten al cortar una página; una página
inexistente se corrige; los filtros y el orden se conservan al paginar.

### Tarea 4: vista de año

**Archivo:** `op/cronologia.php`

1. Conteo de temas por estación y día para el año mostrado.
2. Cuatro bloques con una mini rejilla de 90 celdas: vacío, con tema, con
   varios temas (color distinto). Título con el total de temas de la
   estación; cada bloque enlaza a la vista de estación.
3. En esta vista, anterior/siguiente mueven de año en año.

**Verificación:** los días marcados coinciden con los de las cuatro vistas de
estación.

### Tarea 5: navegación, filtros y plantilla

**Archivos:** `op/cronologia.php`, `op_cronologia.html`

1. `cron_url()`: construye enlaces conservando `uid` (si no es el propio),
   `vista`, `tipo` y `cerrados`, con parámetros que se sustituyen o se
   eliminan.
2. Botones anterior/siguiente: cruzan de año (Invierno 725 → Primavera 726);
   desactivados en Primavera 700 y en el año máximo.
3. Botones "Estación", "Lista", "Año" y "Último tema" (oculto si no hay temas).
4. Formulario GET con año, estación, tipo y "Ocultar cerrados".
5. Mensajes distintos: "Sin temas registrados para este personaje." si no
   tiene ningún tema fechado, y "Ningún tema coincide con los filtros." si
   los tiene pero los filtros activos los ocultan todos.
6. Enlace del título al perfil del personaje (`personaje.php?uid=`).
7. Plantilla y CSS según el diseño visual del plan 200 (9.1) y `style.md`:
   marco de tres fondos, `.btn-op`, `.opg-chip`, `.af-field`, `.opg-vacio`,
   `.opg-volver` y variables `--opg-*`. Orden del `<style>` como indica
   `style.md` §7: marco, componentes compartidos, específico, `@media`. En
   pantallas ≤ 700 px cada día muestra solo el número y un punto.
8. Plantilla incluye `{$calendar_html}` y `{$cronologia_script}`; se elimina
   la referencia a `cache/themes/cronologia.css`.

**Verificación:** los filtros se conservan al navegar entre estaciones, años y
vistas; la URL reproduce la misma vista.

### Tarea 5.1: buscador de personajes

**Archivos:** `op/cronologia.php`, `op_cronologia.html`

1. En `cronologia.php`, tras el control de acceso y antes de resolver el
   personaje: si `action` es `buscar_personajes`, responder con las opciones
   (diseño 7.5) y terminar.
2. Cabecera: campo `type="search"` dentro de `.opg-buscar-caja` con atributos
   htmx (`hx-get`, `hx-trigger="keyup changed delay:250ms"`, `hx-target`,
   `hx-vals` con la acción) y contenedor `.opg-resultados`.
3. `.opg-chip` "Ver mi cronología" cuando el personaje mostrado no es el del
   usuario.
4. Plantilla: script de htmx; estilos del buscador dentro de la cabecera
   (contenedor oculto si está vacío, campo con borde negro y radio pequeño).
5. Script: pulsar un resultado o Enter redirige a `cronologia.php?uid=` sin
   filtros; Escape y clic fuera cierran la lista.

**Verificación:** 2 letras no devuelven nada; 3 letras o un ID listan hasta
12 fichas; un personaje de facción `Staff` no aparece para un usuario normal;
un nombre con `<` o `%` se escapa; al elegir un resultado se abre su cronología
con los filtros reiniciados.

### Tarea 6: pruebas

Ver sección 6.

### Tarea 7: despliegue

Ver sección 7.

## 6. Pruebas

### 6.1 Comprobaciones estáticas

- `php -l op/cronologia.php` sin errores.
- El JavaScript generado pasa `node --check`.
- Buscar que no queden `display_errors` ni `error_reporting` en el archivo.
- Buscar que no se interpole ninguna variable del usuario sin validar en SQL.

### 6.2 Con datos simulados (sin base de datos)

Arnés local con MyBB y base de datos simulados (fuera del repositorio) que
verifique: descarte de fechas inválidas, normalización de estación, orden por
día, filtros, marcador de varios temas, otros participantes, navegación en
Primavera 700 y ambas vistas.

### 6.3 Con la base real (manual)

| # | Caso | Esperado |
|---|---|---|
| 1 | Personaje con temas en varias estaciones y años | Cada tema en su día correcto |
| 2 | Día con 3 temas | Contador, "+1 más" y overlay con los 3 en orden |
| 3 | Invierno 725 → siguiente | Primavera 726 |
| 4 | Primavera 700 → anterior | Desactivado |
| 5 | Vista de año | Mismos días que las 4 estaciones |
| 6 | Filtro por tipo y "Ocultar cerrados" | Se aplican y se conservan |
| 7 | Visitante sin sesión | Sin acceso |
| 8 | Usuario sin ficha | Sin acceso |
| 9 | Tema en foro restringido | No aparece para quien no tiene permiso |
| 10 | Tema con fecha inválida o fuera de `10,%` | No aparece |
| 11 | Cambiar fecha en `editar_tema.php` y recargar | Se refleja de inmediato |
| 12 | Móvil (≤ 700 px) | Rejilla usable y overlay al tocar |
| 13 | Título de tema con `<`, `"` o `&` | Se muestra como texto |
| 14 | Ficha de facción Staff con usuario normal | "Personaje no encontrado" |
| 15 | Sin parámetros | Abre en la estación del último tema |
| 16 | Buscar por nombre, apodo e ID en la cabecera | Resultados en vivo; elegir uno abre su cronología sin filtros |
| 17 | Buscador con usuario sin ficha o sin sesión | Sin acceso (mismo control que la página) |
| 18 | "Ver mi cronología" | Solo aparece viendo a otro personaje |
| 19 | Overlay: hover, clic para fijar, Escape, clic fuera | Ver diseño 7.3 |
| 20 | Vista de lista | Todos los temas de la estación con título completo |
| 23 | Modo global con más de 100 temas | Páginas de 100, recientes primero, botón para invertir, barras por año y estación |
| 24 | Página inexistente (`pagina=999`) | Se corrige a la última |
| 22 | Filtro "Todos" vs un tipo concreto | Etiqueta de tipo con color en la casilla solo con "Todos"; siempre en overlay y lista |
| 21 | Tema en subforo de isla, en el foro de la isla y en un foro sin isla | Isla correcta con enlace; el último, nombre del foro sin enlace |

### 6.4 Rendimiento (manual)

Se asume un máximo de unos 100 temas por personaje y estación, así que no se
espera problema. Aun así, abrir la cronología del personaje con más posts del
foro y anotar el tiempo de carga. Si es lenta, ejecutar `EXPLAIN` de la
consulta principal y proponer un índice, pidiendo aprobación antes de
crearlo.

## 7. Despliegue

### 7.1 Antes de subir

- Revisar el diff: solo `op/cronologia.php` y `op_cronologia.html` (más docs).
- Confirmar que no se incluye ningún archivo con secretos.

### 7.2 Orden

1. Subir `op/cronologia.php`.
2. Sincronizar la plantilla `op_cronologia` con la base de datos.
3. Abrir `/op/cronologia.php` con un personaje real y recorrer la matriz 6.3.

Hacer los pasos 1 y 2 seguidos: entre ambos la página mezcla el PHP nuevo con
la plantilla vieja y se verá rota (el HTML nuevo no tiene CSS).

### 7.3 Rollback

- Restaurar `op/cronologia.php` y la plantilla desde git.
- Sincronizar de nuevo la plantilla anterior.
- No hay datos ni esquema que revertir.

## 8. Orden recomendado de commits

1. `docs: requisitos, diseño y plan de cronología` (los tres documentos).
2. `feat: cronología de personajes (op/cronologia.php y plantilla)`.

No commit sin que el usuario lo pida.

## 9. Fuera de alcance

- Enlace desde `op/personaje.php` o desde el header.
- Lista de temas sin fecha, limpieza de datos históricos y validación de
  fechas en `newthread.php`.
- Eventos de isla y comparación entre personajes.
