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
| `year` | `varchar(255)`, default `'0'` | Año (>= 725) |
| `estacion` | `varchar(255)`, default `'0'` | `Primavera`, `Verano`, `Otoño` o `Invierno` |
| `day` | `varchar(255)`, default `'0'` | Día de la estación, 1 a 90 |

Se escriben desde `newthread.php` (campos `year_input`, `estacion_input`,
`day_input`) y se corrigen desde `op/staff/editar_tema.php`. `postbit_ficha.php`
ya las muestra junto al título del tema.

Al ser `varchar` sin validación en `newthread.php`, la tabla puede contener
valores vacíos, `'0'`, texto libre o fuera de rango. Ver sección 8.

### 2.2 Calendario ya implementado

`op/cronologia.php` (+ plantilla `op_cronologia`) ya dibuja un calendario
vacío de una estación:

- época: Primavera, año 725, día 1;
- 4 estaciones de 90 días, año de 360 días;
- semanas de 7 días, con día de la semana calculado desde la época;
- navegación anterior/siguiente y selector año + estación;
- cada celda tiene un contenedor `.gc-events` vacío, previsto para eventos.

Esta funcionalidad **extiende esa página**, no crea una nueva. Debe conservar
el modelo de calendario existente y rellenar las celdas con los temas.

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
- abrir un tema desde el calendario;
- ver qué temas no tienen fecha asignada.

Fuera de alcance de esta versión:

- editar la fecha de un tema desde el calendario (sigue en `editar_tema.php`);
- crear temas o eventos desde el calendario;
- cronología de facciones, islas o tripulaciones (`mybb_op_isla_eventos` ya
  cubre eventos de isla y no se mezcla aquí);
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
sección 8. Solo los temas fechados se ubican en el calendario. El resto se
lista aparte como "sin fecha".

### 4.4 Rango de un tema

Un tema ocurre en **una fecha** (un día). Un tema no abarca varios días en el
modelo actual. Si más adelante se requiere duración, se añadirá una columna de
fecha final y este requisito se revisará.

## 5. Requisitos funcionales

### 5.1 Selección de personaje

- Por defecto se muestra la cronología del personaje del usuario conectado.
- Cualquier usuario con permiso para ver fichas puede consultar la cronología
  de otro personaje mediante UID (`?uid=`), del mismo modo que se consulta su
  ficha.
- Solo se aceptan UID de personajes con ficha aprobada
  (`does_ficha_exist($uid)`).
- Los invitados no acceden a la herramienta.

### 5.2 Vista de estación (principal)

Es la vista actual de `op/cronologia.php`: rejilla de 90 días en semanas de 7.

Cada día con temas debe mostrar:

- un indicador visual de actividad;
- el título del tema (truncado si es largo) o un contador cuando haya más de
  los que caben (`+2 más`);
- un enlace directo al tema.

Al pulsar un día con varios temas, debe poder verse la lista completa (panel,
popover o modal, a definir en el diseño).

Los días sin temas se muestran vacíos, como hoy.

### 5.3 Vista de año

Vista compacta de las 4 estaciones (360 días) con una marca por día que
tenga actividad. Sirve para ver de un vistazo en qué periodos el personaje
estuvo activo. Al pulsar una estación, se abre la vista de estación.

### 5.4 Navegación

- Estación anterior / siguiente, como ahora.
- Selector de año y estación, como ahora.
- Acceso rápido a la fecha del **último tema** del personaje.
- El año mínimo es 725; no se navega antes de la época.
- La URL debe reflejar personaje, año y estación (`?uid=&y=&t=`) para poder
  compartir el enlace.

### 5.5 Datos de cada tema

Cada tema mostrado debe indicar:

- título, con enlace al tema;
- fecha in-game (año, estación, día);
- tipo de tema, derivado de `mybb_threads.prefix`
  (3=Aventura, 10=MT, 1=Común, 6=Evento, 9=Autonarrada, 14=Requerimiento);
- estado del tema (abierto/cerrado), si el dato está disponible;
- número de posts del personaje en el tema;
- otros personajes participantes.

### 5.6 Filtros

- Por tipo de tema (prefijo). Por defecto, todos.
- Opción de incluir/excluir temas cerrados.

Los filtros se aplican a ambas vistas y se reflejan en la URL.

### 5.7 Temas sin fecha

Debajo del calendario (o en una pestaña) se lista los temas participados que
no tienen fecha válida, con enlace al tema.

Esta lista sirve para que el jugador o el staff detecte temas por corregir. El
staff puede llevar desde ahí a `op/staff/editar_tema.php?tid=`.

### 5.8 Coincidencias de fecha

Si el personaje tiene dos o más temas en el mismo día, el calendario debe
señalarlo visualmente (por ejemplo, un marcador de "coincidencia"). Es
informativo: no bloquea nada.

### 5.9 Cronología contra otro personaje (opcional)

Permitir seleccionar un segundo personaje y resaltar solo los temas que
comparten. Se marca como **opcional**; se decide en el diseño si entra en la
primera versión.

## 6. Requisitos de permisos y privacidad

- La cronología solo muestra temas que el usuario que consulta puede ver.
  Debe respetarse la visibilidad del foro (`forumpermissions`) y no revelar
  títulos de temas de foros restringidos.
- Los temas de foros ocultos o de acceso restringido se omiten sin dejar
  huella (no cuentan en contadores ni en el resumen de año).
- Un personaje no debe poder ver, por medio de la cronología de otro, temas
  que su cuenta no podría abrir directamente.
- El staff (`is_staff`) ve los temas sin fecha con acceso a edición.
- Los parámetros de URL se validan y se escapan; las consultas deben usar
  enteros forzados o `escape_string` (el proyecto interpola SQL).

## 7. Requisitos no funcionales

- **Rendimiento:** una consulta por carga de página, agrupada por tema, con
  los temas del personaje para la ventana pedida (estación o año). No debe
  hacerse una consulta por celda.
- **Índices:** se debe revisar el plan de ejecución de la consulta base
  (`posts.uid`, `posts.tid`, `threads.year/estacion/day`). Si es necesario, se
  propone un índice en el diseño. Crear índices requiere aprobación previa.
- **Caché:** no obligatoria en la primera versión. Se evalúa si la consulta
  de vista de año resulta pesada para personajes con muchos posts.
- **Compatibilidad:** PHP y MySQL del proyecto; sin dependencias nuevas de
  JS/CSS más allá de jQuery y las clases `gc-*` existentes.
- **Responsive:** el calendario debe ser usable en móvil (7 columnas
  compactas; la lista de temas del día como panel).
- **Idioma:** interfaz en español.
- **Plantilla:** la maquetación se mantiene en `op_cronologia.html`, siguiendo
  el patrón de eval de plantillas del proyecto.

## 8. Calidad de datos

Como `year`, `estacion` y `day` son `varchar`, deben normalizarse al leer:

| Campo | Válido si | Tratamiento si no |
|---|---|---|
| `year` | entero >= 725 | El tema pasa a "sin fecha" |
| `estacion` | coincide (sin distinguir mayúsculas ni acentos) con una de las 4 estaciones | Sin fecha |
| `day` | entero de 1 a 90 | Sin fecha |

Casos que deben tratarse:

- valor por defecto `'0'` en cualquier campo → sin fecha;
- `Otoño` escrito como `Otono` u `otoño` → se acepta y se normaliza;
- espacios sobrantes → se recortan;
- año anterior a 725 → sin fecha.

Si la mayoría de las fechas existentes incumple estas reglas, el diseño debe
proponer una limpieza previa (script SQL de revisión). Antes de definir
cualquier normalización definitiva, se debe medir la calidad real de los datos
con una consulta de diagnóstico (temas de rol totales, fechados, sin fecha y
con valores inválidos).

Además, `newthread.php` no valida estos campos en servidor. Endurecer esa
validación queda como mejora recomendada, pero no es parte de esta entrega.

## 9. Criterios de aceptación

1. Un personaje con temas fechados ve cada tema en el día correcto de la
   estación y año correspondientes.
2. Un tema fechado con el personaje sin posts visibles no aparece.
3. Un tema fuera de la zona de rol no aparece.
4. Un tema con fecha inválida aparece en "sin fecha" y no en el calendario.
5. Un día con 3 temas muestra el contador y permite ver los 3.
6. La navegación entre estaciones cruza correctamente el año
   (Invierno 725 → Primavera 726) y no baja de Primavera 725.
7. Un usuario sin permiso sobre un foro no ve en la cronología los temas de
   ese foro.
8. La vista de año marca los mismos días que la suma de las 4 vistas de
   estación.
9. Un invitado no puede abrir la página.
10. La URL con `uid`, `y`, `t` y filtros reproduce exactamente la misma
    vista.

## 10. Preguntas abiertas

1. ¿La cronología de un personaje es pública para todos los usuarios con
   sesión, o solo para su dueño y el staff?
2. ¿Un tema cuenta si el personaje solo fue mencionado o tiene `[ficha]` sin
   haber publicado? Este documento propone que **no**, solo cuenta publicar.
3. ¿Hay temas que abarquen varios días (aventuras largas)? Si es así, se
   necesita una fecha final y un cambio en `newthread.php` y
   `editar_tema.php`.
4. ¿Deben aparecer los eventos de `mybb_op_isla_eventos` en la misma línea
   de tiempo, como una capa opcional?
5. ¿La cronología cruzada entre dos personajes (5.9) entra en la primera
   versión?
6. ¿Se muestra el día de la semana en el calendario como hoy, o se puede
   retirar? (Se conserva por defecto.)
7. ¿Se hace limpieza de datos históricos antes del lanzamiento o se muestran
   como "sin fecha" hasta que el staff los corrija?
