# Requisitos: Cronología de Personajes

## 1. Propósito

Crear una interfaz de calendario que muestre la cronología de un personaje: en
qué temas de rol ha participado y en qué fecha del mundo (año, estación y día)
ocurrieron.

La herramienta debe permitir responder preguntas como:

- ¿Qué ha hecho mi personaje en la Primavera del año 726?
- ¿Qué temas coinciden en el tiempo (y por tanto no pueden solaparse)?
- ¿Cuánto tiempo "in-game" ha transcurrido entre dos aventuras?
- ¿Con qué otros personajes coincidió en un tema?

Este documento define requisitos funcionales. La arquitectura, las consultas,
las plantillas y el diseño detallado se definirán después en
`200_DesignPlan_Cronologia.md`.

## 2. Contexto existente

### 2.1 Fecha de un tema

La fecha in-game vive en tres columnas custom de `mybb_threads`:

| Columna | Tipo actual | Contenido |
|---|---|---|
| `year` | `varchar(255)`, default `'0'` | Año (>= 700) |
| `estacion` | `varchar(255)`, default `'0'` | `Primavera`, `Verano`, `Otoño` o `Invierno` |
| `day` | `varchar(255)`, default `'0'` | Día de la estación, 1 a 90 |

Se escriben desde `newthread.php` (campos `year_input`, `estacion_input`,
`day_input`) y se corrigen desde `op/staff/editar_tema.php`. `postbit_ficha.php`
ya las muestra junto al título del tema.

Al ser `varchar` sin validación en `newthread.php`, la tabla puede contener
valores vacíos, `'0'`, texto libre o fuera de rango. Ver sección 8.

### 2.2 Calendario ya implementado

`op/cronologia.php` (+ plantilla `op_cronologia`) es código legado: dibuja un
calendario vacío de una estación, con semanas de 7 días calculadas desde una
época (Primavera, año 725, día 1), sin ningún dato. No lo enlaza ninguna otra
página.

Esta funcionalidad **rehace esa página desde cero** en la misma ruta
(`/op/cronologia.php`). Del calendario legado solo se conserva el modelo de
tiempo: 4 estaciones de 90 días y años de 360 días. Se abandonan la época y
los días de la semana (ver 5.2 y 5.4).

### 2.3 Participación

Un personaje "participa" en un tema si ha publicado en él. Ya existen fuentes
relacionadas, con distinto propósito:

- `mybb_posts.uid` / `tid`: fuente de verdad de quién ha publicado.
- `mybb_op_temas_seguidos` / `mybb_op_temas_participantes`: seguimiento de
  turnos (Bitácora). Es por personaje y puede quitarse, por lo que **no** sirve
  como historial.
- `mybb_op_thread_personaje`: snapshot de estadísticas por tema (combates).
  Solo existe en temas que lo usan.

## 3. Alcance

La página debe permitir:

- elegir un personaje y ver su cronología;
- ver los temas del personaje ubicados en el calendario según su fecha;
- navegar por estación y año, y saltar a una fecha concreta;
- ver una vista de año completo (4 estaciones) para localizar actividad;
- ver una vista de lista de la estación, con todos los temas y sus títulos
  completos;
- abrir un tema desde el calendario;

Fuera de alcance de esta versión:

- editar la fecha de un tema desde el calendario (sigue en `editar_tema.php`);
- crear temas o eventos desde el calendario;
- cronología de facciones, islas o tripulaciones (`mybb_op_isla_eventos` ya
  cubre eventos de isla y no se mezcla aquí; tampoco se superpone como capa
  en esta versión);
- comparar la cronología de dos personajes;
- notificaciones.

## 4. Definiciones

### 4.1 Tema de rol

Igual que en `100_Requirements_Temas.md`: el tema pertenece a un foro con

```sql
f.parentlist LIKE '10,%'
```

Los temas de otros foros no aparecen en la cronología.

### 4.2 Tema participado

Un tema es participado por un personaje cuando existe al menos un post
**visible** (`visible = 1`) de su UID en ese tema. Se excluyen borradores
(`visible = -2`) y posts en papelera o pendientes de moderación.

Regla adicional: los temas eliminados o en papelera (`threads.visible <> 1`)
no se muestran.

### 4.3 Tema fechado

Un tema es fechado cuando `year`, `estacion` y `day` son válidos según la
sección 8. Solo los temas fechados aparecen en la cronología. Los temas sin
fecha válida se **omiten** sin mostrarse en ninguna lista: corresponden a
temas anteriores a que existieran las fechas, y todo tema actual de la zona
de rol está fechado.

### 4.4 Rango de un tema

Un tema ocurre en **una fecha** (un día). Confirmado: por ahora no existen
temas que abarquen varios días, por lo que no se necesita fecha final. Si más
adelante se requiere duración, se añadirá una columna de fecha final y este
requisito se revisará.

## 5. Requisitos funcionales

### 5.1 Selección de personaje

- Acceso restringido: solo pueden abrir la cronología los usuarios con sesión
  iniciada **y ficha propia** (fila en `mybb_op_fichas`). Los
  visitantes y los usuarios sin ficha no tienen acceso.
- Por defecto se muestra la cronología del personaje del usuario conectado.
- Un usuario con ficha puede consultar la de cualquier otro personaje
  mediante UID (`?uid=`), sin restricción por dueño.
- La página incluye un **buscador de personajes** dentro de la cabecera, por
  nombre, apodo o ID (mínimo 3 caracteres, o un número). Al elegir un
  resultado se abre la cronología de ese personaje con los filtros
  **reiniciados** (sin tipo, sin ocultar cerrados, vista de estación) y en la
  estación de su último tema.
- Si se está viendo la cronología de otro personaje, la cabecera ofrece un
  acceso "Ver mi cronología".
- El buscador aplica las mismas reglas que la página: solo personajes con
  ficha y las fichas de la facción `Staff` solo aparecen para el staff.
- Solo se aceptan UID de personajes con ficha, es decir, con una fila en
  `mybb_op_fichas` (`fid` = UID). No se distingue el estado de aprobación.
- Las fichas de la facción `Staff` solo las ve el staff, igual que en
  `op/personaje.php`; para el resto de usuarios, ese personaje se trata como
  inexistente.
- La cronología se calcula solo a partir de la ficha del personaje y su UID.
  No distingue publicaciones hechas con personaje secreto: cuentan igual.
- La ficha del personaje (`op/personaje.php`, plantilla `op_personaje`)
  incluye un enlace a su cronología. `op/ficha.php` es código legado y no se
  toca.
- El acceso a la cronología no anula la visibilidad de los temas (sección 6):
  cada usuario ve solo los temas que puede abrir.

### 5.2 Vista de estación (principal)

Rejilla fija de **5 columnas × 18 filas** (90 días). El día 1 siempre queda
arriba a la izquierda y no hay huecos. Reemplaza las semanas de 7 días del
calendario actual: desaparecen los encabezados de día de la semana y el
cálculo de época.

Cada día con temas debe mostrar:

- un indicador visual de actividad;
- el título del tema, que se parte en hasta 2 líneas en vez de recortarse en
  una, o un contador cuando haya más de los que caben (`+2 más`);
- un enlace directo al tema;
- cuando el filtro de tipo es "Todos", el **tipo de tema** como etiqueta de
  color antes del título, para identificarlo de un vistazo. Con un tipo
  concreto la etiqueta no se muestra, porque todos los temas son de ese tipo.

Cada tipo tiene un color propio (de la paleta de [style.md](style.md)),
consistente en la casilla, el overlay y la vista de lista.

Al pasar el ratón (o enfocar con teclado) una casilla con temas, aparece un
**overlay centrado en la pantalla** con la fecha y todos los temas del día
(título completo, tipo, estado, posts del personaje y otros participantes).
El overlay de hover es solo informativo. Al hacer clic (o Enter, o un toque en
pantalla táctil) el overlay queda **fijado**: se puede pulsar sus enlaces y se
cierra con un botón, con Escape o pulsando fuera. El día fijado queda
resaltado.

Cuando un día tiene varios temas, se ordenan por fecha real de creación del
tema (`dateline`), del más antiguo al más reciente.

Los días sin temas se muestran vacíos.

### 5.2.1 Vista de lista

Muestra todos los temas de la estación seleccionada, ordenados por día (y por
fecha de creación dentro del día), con título completo enlazado, día, tipo,
estado, posts del personaje y otros participantes (hasta 5 y `+N`). Respeta los
mismos filtros y la misma navegación por estación y año que la vista de
estación. Si la estación no tiene temas, muestra "Sin temas en esta
estación."

### 5.2.2 Modo lista global

Dentro de la vista de lista, una opción "Todo el historial" (frente a "Esta
estación") muestra en una sola lista **todos los temas fechados del personaje
de todos los años y estaciones**.

- **Agrupación:** una barra por cada año y estación ("Verano 725 · 12 temas",
  con el total de temas de esa estación). Si una página corta una estación por
  la mitad, la barra se repite al inicio de la página siguiente.
- **Orden:** por defecto, del tiempo más reciente al más antiguo. Un botón
  alterna al orden inverso (del más antiguo al más reciente). Dentro de un día,
  el orden es por fecha de creación en el mismo sentido.
- **Paginación:** páginas de **100 temas**, con controles anterior/siguiente,
  números de página y un texto "Temas 101–200 de 537", arriba y abajo. Una
  página fuera de rango se corrige al límite.
- **Filas:** las mismas de la lista de estación (día, título completo, isla,
  tipo, estado, posts y otros participantes).
- **Filtros:** respeta tipo y "Ocultar cerrados". Los temas sin fecha válida
  siguen omitidos.
- En este modo no se muestran la navegación anterior/siguiente por estación ni
  el selector de año y estación, que no aplican.

### 5.3 Vista de año

Vista compacta de las 4 estaciones (360 días) con una marca por día que
tenga actividad. Sirve para ver de un vistazo en qué periodos el personaje
estuvo activo. Al pulsar una estación, se abre la vista de estación.

### 5.4 Navegación

- Estación anterior / siguiente.
- Selector de año y estación.
- Sin `y` ni `t` en la URL, la vista abre en la estación del **último tema**
  fechado del personaje (o en Primavera 725 si no tiene ninguno).
- Acceso rápido a esa misma fecha desde cualquier otra estación.
- El año mínimo es 700; no se navega antes.
- La URL debe reflejar personaje, año y estación (`?uid=&y=&t=`) para poder
  compartir el enlace.

### 5.5 Datos de cada tema

Cada tema mostrado debe indicar:

- título, con enlace al tema;
- fecha in-game (año, estación, día);
- **isla** donde ocurre (nombre, enlazado a `/op/isla.php?isla_id=`), ver 5.5.1;
- tipo de tema, derivado de `mybb_threads.prefix`
  (3=Aventura, 1=Común, 6=Evento, 9=Autonarrada y Diario; los temas con
  prefijo MT (10) o Requerimiento (14) siguen mostrando su nombre);
- estado del tema (abierto o cerrado, según `threads.closed`);
- número de posts del personaje en el tema;
- otros personajes participantes, hasta un máximo de 5 (nombre), seguidos
  de "+N" si hay más.

### 5.5.1 Isla del tema

Todo tema debe indicar en qué isla ocurre. La isla no está en el tema, sino
en la estructura de foros: un foro es una isla cuando `mybb_forums.isla_rol`
es 1, y sus subforos (zonas) llevan `subisla = 1` y `parent_isla` con el FID
de la isla. La isla de un tema es la del foro donde está publicado:

1. el foro del tema o el ancestro más cercano (según `parentlist`) que sea
   isla (`isla_rol = 1`);
2. si no hay ninguno, la isla indicada por `parent_isla`;
3. si tampoco, se muestra el nombre del propio foro del tema como lugar, sin
   enlace, para que ningún tema quede sin ubicación.

La isla se ve en el overlay del día, en la vista de lista y en el tooltip de
la casilla. No se añade un filtro por isla en esta versión.

### 5.6 Filtros

- Por tipo de tema (prefijo): Aventura, Común, Evento, Autonarrada y Diario.
  MT y Requerimiento no se ofrecen como filtro. Por defecto, todos.
- Opción de incluir/excluir temas cerrados.

Los filtros se aplican a ambas vistas y se reflejan en la URL.

### 5.7 Coincidencias de fecha

Si el personaje tiene dos o más temas en el mismo día, el calendario debe
señalarlo visualmente (por ejemplo, un marcador de "coincidencia"). Es
informativo: no bloquea nada.

## 6. Requisitos de permisos y privacidad

- La cronología solo muestra temas que el usuario que consulta puede ver.
  Debe respetarse la visibilidad del foro (`forumpermissions`) y no revelar
  títulos de temas de foros restringidos.
- Los temas de foros ocultos o de acceso restringido se omiten sin dejar
  huella (no cuentan en contadores ni en el resumen de año).
- Nadie debe poder ver, por medio de la cronología de un personaje, temas que
  su cuenta no podría abrir directamente.
- Los parámetros de URL se validan y se escapan; las consultas deben usar
  enteros forzados o `escape_string` (el proyecto interpola SQL).

## 7. Requisitos no funcionales

- **Rendimiento:** una consulta principal por carga de página, agrupada por
  tema. No debe hacerse una consulta por celda. La vista por defecto es la
  última estación con temas; las demás se calculan solo cuando el usuario las
  pide. Se asume que un personaje no supera unos 100 temas por estación, por
  lo que no se exige optimización adicional.
- **Caché:** no se usa caché. Un cambio de fecha hecho por el staff (por
  ejemplo desde `editar_tema.php`) debe verse de inmediato en la cronología.
- **Compatibilidad:** PHP y MySQL del proyecto; sin dependencias nuevas de
  JS/CSS más allá de jQuery.
- **Responsive:** el calendario debe ser usable en móvil (5 columnas, con solo
  el número de día y un indicador de actividad; los temas del día en el
  overlay al tocar la casilla).
- **Idioma:** interfaz en español.
- **Estilo visual:** la página sigue [style.md](style.md): marco de tres
  fondos, borde negro, tipografía `moonGetHeavy` solo en títulos y variables
  de `opg-tokens.css`, sin colores ni botones inventados.
- **Plantilla:** la maquetación se mantiene en `op_cronologia.html`, siguiendo
  el patrón de eval de plantillas del proyecto.

## 8. Calidad de datos

Como `year`, `estacion` y `day` son `varchar`, deben normalizarse al leer:

| Campo | Válido si | Tratamiento si no |
|---|---|---|
| `year` | entero >= 700 | El tema se omite |
| `estacion` | coincide (sin distinguir mayúsculas ni acentos) con una de las 4 estaciones | Se omite |
| `day` | entero de 1 a 90 | Se omite |

Casos que deben tratarse:

- valor por defecto `'0'` en cualquier campo → se omite;
- `Otoño` escrito como `Otono` u `otoño` → se acepta y se normaliza;
- espacios sobrantes → se recortan;
- año anterior a 700 → se omite.

No se hace limpieza de datos históricos: los temas sin fecha válida
simplemente no se muestran.

Además, `newthread.php` no valida estos campos en servidor. Endurecer esa
validación queda como mejora recomendada, pero no es parte de esta entrega.

## 9. Criterios de aceptación

1. Un personaje con temas fechados ve cada tema en el día correcto de la
   estación y año correspondientes.
2. Un tema fechado con el personaje sin posts visibles no aparece.
3. Un tema fuera de la zona de rol no aparece.
4. Un tema con fecha inválida o sin fecha no aparece en ninguna vista.
5. Un día con 3 temas muestra el contador y permite ver los 3.
6. La navegación entre estaciones cruza correctamente el año
   (Invierno 725 → Primavera 726) y no baja de Primavera 700.
7. Un usuario sin permiso sobre un foro no ve en la cronología los temas de
   ese foro.
8. La vista de año marca los mismos días que la suma de las 4 vistas de
   estación.
9. Un visitante sin sesión o un usuario sin ficha no puede abrir la
   cronología.
10. La URL con `uid`, `y`, `t` y filtros reproduce exactamente la misma
    vista.
11. Tras cambiar la fecha de un tema desde `op/staff/editar_tema.php`, la
    cronología muestra el tema en la nueva fecha en la carga siguiente, sin
    esperar ni vaciar ninguna caché.
12. Con varios temas el mismo día, aparecen ordenados por fecha de creación.

## 10. Decisiones tomadas

1. La cronología requiere sesión y ficha propia; con eso se puede consultar
   la de cualquier personaje (ver 5.1).
2. No existen temas de varios días; cada tema tiene una sola fecha.
3. Los eventos de `mybb_op_isla_eventos` no se incluyen por ahora.
4. La comparación entre dos personajes queda fuera de la primera versión.
5. Un tema cuenta solo si el personaje publicó en él (no por ser mencionado ni
   por tener `[ficha]` sin publicar).
6. No se limpian los datos históricos. Los temas sin fecha válida se omiten
   y no hay lista de "sin fecha" (ver 4.3 y sección 8).
7. La estación se dibuja en rejilla fija de 5 × 18, sin días de la semana.
8. El año mínimo es 700 (valor del formulario de crear tema). En la práctica
   casi nadie tiene temas antes del 724, por lo que la vista inicial no debe
   abrirse en el año mínimo sino en el del último tema del personaje.
9. Los temas de un día se muestran en un overlay centrado al pasar el ratón
   (fijable con clic), no en un panel bajo el calendario.
10. La vista de año entra en la primera versión.
11. Se añade una vista de lista de la estación, con títulos completos.
12. Modo lista global: todo el historial, recientes primero por defecto con
    botón para invertir, páginas de 100 temas.

## 11. Preguntas abiertas

Ninguna por ahora.
