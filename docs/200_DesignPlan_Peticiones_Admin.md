# Plan de diseño: actividad del equipo en Peticiones administrativas

## 1. Objetivo

Mejorar `/op/staff/peticiones_admin.php` para que, además de gestionar
peticiones, permita conocer de forma clara:

- cuántas peticiones siguen pendientes en cada categoría;
- cuántas se han resuelto históricamente;
- qué clase de peticiones ha resuelto cada integrante del equipo;
- cómo consultar listas grandes con una vista más compacta;
- una presentación visual coherente con la página de Solicitudes de creación.

La interfaz será informativa, no competitiva. Se usarán expresiones como
**Actividad del equipo**, **Resoluciones** y **Distribución por categoría**.
No se mostrarán puestos, podios, medallas ni el texto "Top moderadores".

## 2. Alcance

Los cambios se limitarán a:

- `op/staff/peticiones_admin.php`;
- `templates/One_Piece_Gaiden_Templates/staff_peticiones_admin.html`.

No se creará un plugin ni una tabla adicional. La página ya dispone de los
datos necesarios y su plantilla ya existe en el repositorio.

Quedan fuera de este trabajo:

- peticiones de programación, administradas en `peticiones_bugs.php`;
- resets de build, que conservan su acceso y contador independientes;
- cambios en el contador global del header;
- cambios en el flujo actual para asignar, anotar o resolver peticiones.

## 3. Fuentes de datos

### 3.1 Peticiones administrativas

La tabla `mybb_op_peticiones` ya contiene:

- `categoria`: tipo de petición;
- `resuelto`: pendiente o resuelta;
- `mod_uid`: UID de quien la resolvió;
- `mod_nombre`: nombre guardado al resolverla;
- `atendidoPor`: persona a la que está asignada mientras sigue abierta.

Al pulsar **Resolver**, la página escribe el UID y nombre del usuario que
ejecuta la acción. Esto permite atribuir resoluciones históricas sin inferir
la autoría a partir de las notas o de la asignación actual.

Las categorías incluidas serán:

1. Ajustes de Ficha y Recursos (`ficha`).
2. Petición de Narración (`tema`).
3. Moderación de Combate (`combate`).
4. Técnicas, Akumas y Estilos (`tecnica`).
5. Otras Moderaciones (`otros`).

### 3.2 Solicitudes de tripulación

La tabla `mybb_op_tripulaciones_solicitudes` ya contiene:

- `estado = 0`: pendiente;
- `estado = 1`: aprobada;
- `estado = 2`: rechazada;
- `resuelto_por`: UID de quien aprobó o rechazó la solicitud.

**Tripulaciones** se incorporará como una categoría adicional tanto en la
distribución general como en la actividad del equipo. Para obtener el nombre
actual del moderador se hará un `LEFT JOIN` con `mybb_users`.

## 4. Resumen general

Debajo del título se mostrarán dos indicadores:

- **Pendientes**: suma de las cinco categorías administrativas más las
  solicitudes de tripulación con `estado = 0`.
- **Resueltas**: peticiones con `resuelto = 1` más tripulaciones aprobadas o
  rechazadas.

Los resets de build no se mezclarán en estas cantidades porque tienen su
propio sistema y su propio acceso en la página.

## 5. Distribución por categoría

Al final de la página, después del listado y su paginación, aparecerá una
tabla global con las columnas:

| Categoría | Pendientes | Resueltas | Total |
|---|---:|---:|---:|
| Ajustes de Ficha y Recursos | — | — | — |
| Petición de Narración | — | — | — |
| Moderación de Combate | — | — | — |
| Técnicas, Akumas y Estilos | — | — | — |
| Otras Moderaciones | — | — | — |
| Tripulaciones | — | — | — |
| **Total** | — | — | — |

Esta tabla representa el estado global y no cambia al aplicar los filtros de
la lista de resueltas. Así evita que una selección parcial parezca el total
real del sistema.

La consulta de `mybb_op_peticiones` agrupará por `categoria` usando sumas
condicionales. Tripulaciones se consultará una sola vez y se añadirá al
resultado en PHP.

## 6. Actividad del equipo

Un botón **Actividad del equipo** desplegará u ocultará una tabla dentro de
la misma página. El texto al cerrarla será **Ocultar actividad**, no
"Ocultar ranking".

La tabla aprovechará el espacio con solo dos columnas:

| Moderador | Resoluciones |
|---|---:|

Reglas:

- una fila por UID de moderador;
- el nombre enlaza a `/op/personaje.php?uid=<UID>`;
- `Resoluciones` es la suma de todas las peticiones administrativas y de
  tripulación resueltas por esa persona;
- las filas se ordenan por cantidad total descendente y, en empate, por
  nombre;
- no se muestra un número de posición ni se destaca un ganador;
- las peticiones resueltas sin `mod_uid` válido no se atribuyen a una persona;
- si existen resoluciones sin atribución, se muestra una nota con su cantidad
  para que el total histórico sea transparente;
- las aprobaciones y rechazos de tripulación cuentan dentro del total.

El panel estará cerrado inicialmente para no desplazar la gestión cotidiana.
Abrirlo no hará una petición adicional al servidor: el HTML se genera con la
página y JavaScript solo controla su visibilidad.

## 7. Modos de visualización

Junto a los controles superiores se añadirá un selector de dos estados:

- **Tarjetas**;
- **Compacta**.

Ambos modos reutilizarán exactamente el mismo HTML y conservarán todas las
acciones. La preferencia se guardará en `localStorage`.

La vista compacta:

- reduce espacios internos y márgenes;
- presenta contenido y gestión en columnas cuando haya espacio;
- reduce la altura inicial de las notas sin eliminar el campo;
- conserva categoría, personaje, resumen, descripción, URL, fecha,
  asignación, notas y acciones;
- vuelve a una columna en pantallas estrechas.

No se ocultará información importante únicamente para conseguir una lista
más baja.

## 8. Rediseño visual

La referencia directa será
`templates/One_Piece_Gaiden_Templates/staff_solicitudes_creacion.html`. No se
copiará únicamente su color: se reutilizará su jerarquía, ritmo y forma de
presentar controles, datos y estados.

### 8.1 Cabecera

La barra de título actual se sustituirá por una cabecera equivalente a
`.sc-cabecera`:

- fondo naranja con la textura OPG;
- borde negro de 2px, radio de 8px y sombra offset;
- título blanco con sombra de tinta;
- una descripción corta: "Gestión de ficha, narración, combate, técnicas y
  otras peticiones del foro";
- bloque de resumen integrado a la derecha con **Pendientes** y **Resueltas**.

En móvil, el título y el resumen se apilarán. Los dos contadores ocuparán el
ancho disponible a partes iguales.

### 8.2 Barra de herramientas

Los enlaces y controles dejarán de aparecer como chips aislados. Se reunirán
en una toolbar de fondo crema dorada, equivalente a `.sc-toolbar`, con:

- navegación a Resets de build;
- cambio entre pendientes y resueltas;
- botón **Actividad del equipo** con icono de gráfica y chevron;
- selector segmentado **Tarjetas / Compacta** con iconos;
- filtros de categoría y persona asignada cuando corresponda.

En escritorio, filtros y acciones se distribuirán en dos zonas. En móvil se
apilarán y los controles conservarán un área de pulsación cómoda.

### 8.3 Encabezado del listado

El listado tendrá un encabezado de sección como los tres estados de
Solicitudes:

- icono dentro de un bloque de color;
- título **Peticiones pendientes** o **Peticiones resueltas**;
- contador visible con la cantidad de resultados de la vista actual;
- frase breve que explique el estado o los filtros aplicados.

Esto reemplaza la entrada brusca desde filtros directamente hacia las
tarjetas y deja claro si el usuario está viendo la cola activa o el histórico.

### 8.4 Tarjetas

Cada petición pasará a una tarjeta visualmente emparentada con `.sc-card`:

- borde negro, radio máximo de 8px, textura crema y sombra offset;
- cinta superior con categoría e identificador de petición;
- el resumen de la petición será el título principal;
- los metadatos usarán una lista semántica de etiqueta/valor para personaje,
  fecha y enlace relacionado;
- nombres y enlaces tendrán estilo OPG propio, sin heredar el azul por defecto
  del foro;
- descripción separada visualmente para poder leerla sin mezclarla con los
  controles.

La gestión de staff tendrá una zona propia dentro de la misma tarjeta:

- persona asignada;
- selector para cambiar la asignación;
- notas de moderación;
- botones para guardar y resolver.

Esta zona puede usar fondo crema dorada y borde superior en la vista de
tarjetas. No será una tarjeta anidada: seguirá siendo una región de la tarjeta
principal. En pantallas anchas puede ocupar una columna lateral; en móvil se
coloca debajo del contenido.

Las solicitudes de tripulación utilizarán la misma carcasa visual, aunque no
tengan los controles de asignación y notas de `mybb_op_peticiones`.

### 8.5 Rejilla y densidad

La vista **Tarjetas** usará dos columnas cuando el ancho y el contenido lo
permitan, siguiendo `.sc-grid`. Como las peticiones con notas pueden ser más
densas, las tarjetas mantendrán altura independiente y no se forzará que dos
tarjetas tengan la misma altura.

La vista **Compacta** cambiará a una sola columna y reorganizará cada tarjeta
como una fila: cinta, información principal, gestión y acciones. Reutiliza el
mismo patrón de `.sc-page--compacta`, adaptado al formulario de notas.

### 8.6 Tablas y paneles de datos

**Distribución por categoría** y **Actividad del equipo** seguirán el mismo
tratamiento que `.sc-distribucion`, `.sc-actividad` y `.sc-tabla`:

- separación superior negra de 4px;
- título titular y texto auxiliar corto;
- tabla dentro de un contenedor con borde y desplazamiento horizontal;
- encabezado morado;
- filas alternas crema;
- total general en una franja naranja.

El panel de actividad continuará colapsado por defecto. Su botón cambiará de
estado visual y rotará el chevron al abrirlo, igual que en Solicitudes.

### 8.7 Coherencia sin duplicación

El resultado debe sentirse parte de la misma familia, pero no se copiarán las
clases `.sc-*` directamente. Peticiones conservará el prefijo `.pa-*` para
evitar acoplar dos plantillas independientes y permitir que cada flujo
evolucione sin efectos laterales.

Se usarán los tokens de `docs/style.md`, Font Awesome ya disponible y los
componentes globales `.btn-op`, `.af-field`, `.opg-volver` y `.opg-vacio`.
No se introducirán librerías nuevas.

## 9. Composición de la página

El orden propuesto es:

1. Volver a Consola.
2. Título **Peticiones administrativas**.
3. Indicadores globales de pendientes y resueltas.
4. Barra de herramientas:
   - Resets de build;
   - Ver pendientes / Ver resueltas;
   - Actividad del equipo;
   - Tarjetas / Compacta.
5. Panel colapsable de Actividad del equipo.
6. Filtros de categoría y persona asignada dentro de la toolbar, solo al ver
   resueltas.
7. Encabezado del listado con estado y cantidad visible.
8. Paginación, rejilla/lista de peticiones y paginación final.
9. Tabla global de Distribución por categoría al final de la página.

El ancho útil de la página crecerá de `800px` a aproximadamente `1100px` para
que las tablas y las tarjetas en dos columnas respiren sin salirse del fondo.
En móvil, las tablas tendrán desplazamiento horizontal dentro de su propio
contenedor.

## 10. Integración con el listado actual

No se cambiará la semántica de los filtros existentes:

- **Pendientes** muestra la cola activa completa.
- **Resueltas** mantiene filtro por categoría, filtro por `atendidoPor` y
  paginación.
- El filtro especial **Tripulaciones** continúa usando su tabla separada.

Se añadirán clases CSS descriptivas a las líneas de cada tarjeta para que el
modo compacto pueda ajustarlas sin duplicar el marcado.

Las funciones AJAX actuales para **Guardar asignación** y **Guardar notas**
seguirán funcionando igual. Los nuevos controles de interfaz se incorporarán
al mismo script vanilla JavaScript; no se requiere Alpine.js ni HTMX para
alternar clases y paneles locales.

## 11. Consultas previstas

Se añadirán tres cargas agregadas, cada una ejecutada una sola vez por página:

1. Conteos administrativos agrupados por categoría y estado.
2. Conteos de tripulación agrupados por estado.
3. Resoluciones agrupadas por moderador, más las resoluciones de tripulación
   agrupadas por `resuelto_por`.

No habrá consultas por tarjeta ni por moderador. La complejidad depende del
número de categorías y miembros del equipo, no del número de peticiones
renderizadas.

## 12. Seguridad y consistencia

- Los nuevos bloques serán de solo lectura.
- Los UID se convertirán a entero antes de usarlos en enlaces o claves.
- Los nombres y etiquetas se escaparán con
  `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Las categorías válidas saldrán del mapa definido en PHP, no de valores GET.
- No se modificarán las comprobaciones de permisos ni los tokens CSRF de las
  acciones existentes.
- Las cifras generales y de actividad se calcularán sin depender de los
  filtros recibidos por URL.

## 13. Verificación

Después de implementar se comprobará:

1. Sintaxis con `php -l op/staff/peticiones_admin.php`.
2. Página de pendientes con y sin datos.
3. Página de resueltas con cada filtro y varias páginas.
4. Categoría especial Tripulaciones en pendientes y resueltas.
5. Coincidencia entre totales de la tabla y las consultas de origen.
6. Total de una cuenta que haya resuelto peticiones administrativas y de
   tripulación.
7. Tratamiento visible de resoluciones antiguas sin moderador atribuido.
8. Apertura y cierre del panel de actividad.
9. Persistencia de Tarjetas/Compacta al recargar.
10. Asignación, guardado de notas y resolución después de añadir la nueva UI.
11. Distribución correcta en escritorio y móvil, sin salirse del fondo.
12. Coherencia visual con Solicitudes en cabecera, toolbar, tarjetas, tablas y
    estados interactivos.

## 14. Resultado esperado

La página seguirá siendo la herramienta operativa de peticiones, pero también
ofrecerá una lectura rápida de la carga pendiente y una memoria objetiva del
trabajo resuelto por el equipo. La información estará desglosada y será útil
para repartir trabajo o detectar acumulaciones, sin convertirla en una tabla
competitiva.

## 15. Implementación realizada

Implementado el 22 de septiembre de 2026 en:

- `op/staff/peticiones_admin.php`;
- `templates/One_Piece_Gaiden_Templates/staff_peticiones_admin.html`.

La implementación incluye:

- conteos globales de pendientes y resueltas;
- distribución por las cinco categorías administrativas y Tripulaciones;
- actividad por moderador, mostrando únicamente nombre y total de
  resoluciones;
- aviso para resoluciones históricas sin moderador atribuido;
- cabecera, toolbar, tablas y tarjetas rediseñadas con el patrón de
  Solicitudes de creación;
- selector Tarjetas/Compacta persistido en `localStorage`;
- panel de Actividad del equipo colapsado inicialmente;
- tarjetas específicas de Tripulaciones dentro de la misma rejilla;
- enlaces de personajes y referencias con estilo propio y apertura segura en
  una pestaña nueva;
- conservación del guardado AJAX de asignación y notas.

No se añadieron tablas, migraciones, plugins ni dependencias de JavaScript.
