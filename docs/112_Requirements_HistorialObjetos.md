# Requisitos: Historial público de objetos

> Modelo: [110_Requirements_HistorialTecnicas.md](110_Requirements_HistorialTecnicas.md) y [212_DesignPlan_HistorialTecnicas.md](212_DesignPlan_HistorialTecnicas.md).
> Documentos hermanos: [112_Reglas_HistorialObjetos.md](112_Reglas_HistorialObjetos.md) (Game Designer, "112-GD"), [214_UI_HistorialObjetos.md](214_UI_HistorialObjetos.md) (UI Designer, "214-UI"), [214_DesignPlan_HistorialObjetos.md](214_DesignPlan_HistorialObjetos.md) (Developer, "214-DP") y [314_ImplementationPlan_HistorialObjetos.md](314_ImplementationPlan_HistorialObjetos.md) (plan e issues).
> Estado: borrador del PM, **revisión 2** (corregida con lo que descubrieron los otros roles; ver la sección 18, "Registro de cambios de este documento"). Las decisiones del usuario siguen pendientes y no se dan por resueltas: donde un requisito depende de una, se indica con "(depende de D-n)" o "(depende de U-n)". La tabla única de decisiones y contradicciones está en el documento 314, sección 6.
> Convención de marcas: **(corregido, RC-n)** indica un cambio que no altera el sentido del requisito (cifras, rutas, precisiones). **(CAMBIA EL SENTIDO, RC-n)** indica que el requisito dice algo distinto de la revisión 1 y necesita el visto bueno del usuario al aprobar. Los requisitos nuevos empiezan en el 64 para no renumerar los existentes.

## 1. Propósito

`/op/historial_objetos.php` informa públicamente de las altas, modificaciones,
renombres y retiradas del catálogo de objetos. La página debe permitir que un
usuario responda con rapidez qué objeto cambió, qué cambió exactamente, cuándo
y si el ajuste afecta a un objeto que tiene su personaje.

La diferencia principal con el historial de técnicas es el punto de partida:
hoy los cambios de objetos casi no dejan rastro útil. `objetos_modificar.php`
solo anota nombre, categoría y subcategoría del estado final, sin el valor
anterior de ningún campo, y `op/barco_unico.php` no deja ningún rastro
(corregido, RC-3). Por eso esta funcionalidad tiene dos partes de igual peso:

1. **Empezar a registrar** cada cambio del catálogo con el antes y el después
   de cada campo, en un formato estructurado.
2. **Mostrar** ese registro (y lo poco que hay del pasado) en una página pública.

Principio de planificación (añadido, RC-21): **se registra todo y se decide
después qué se muestra.** El registro guarda también los cambios de copias,
de objetos invisibles y de campos reservados; las reglas de visibilidad se
aplican al leer. Así las decisiones de visibilidad no retrasan el inicio del
registro.

### Datos de partida (medidos en producción)

(corregido, RC-1: cifras sustituidas por las medidas por el Game Designer, 112-GD sección 0)

| Dato | Valor |
|---|---|
| Filas en `mybb_op_objetos` | 16.240 |
| Objetos de catálogo (`custom='0'`) | **1.204** (la revisión 1 decía 1.304) |
| De ellos, visibles en el catálogo (`invisible=0`) | **543** |
| De ellos, invisibles (`invisible=1`) | **661 (55 %)** |
| De ellos, `exclusivo=1` | 792 |
| Desglose por categoría | Pendiente de volver a medir. El de la revisión 1 (Armas 309, Materiales 263, Utensilios 215, Consumibles 211, Especiales 183, Estructuras 120, Transportes 3) suma 1.304 y no es fiable |
| Copias personalizadas de jugadores (`custom='1'`, ids `BASE-uid-n` o `BASE-n` en barcos) | **15.036** (14.852 invisibles; la revisión 1 decía 14.936). Son el 93 % de la tabla |
| Objetos retirados hoy (id `BORR...`, categoría `borrada`) | 0 |
| Registros de objetos en `mybb_op_audit_consola_mod` (desde 2024-02-17) | 430 |
| De ellos, altas de `objetos_crear.php` (`Nuevo objeto creado de objeto ID X ...`) | 133, con valores iniciales de varios campos |
| Altas de `objetos_modificar.php` (`Objeto X (creado por Y): ...`) | 49, solo nombre, categoría y subcategoría |
| Modificaciones de `objetos_modificar.php` (`Objeto X (modificado por Y): ...`) | 248, sin antes ni después; algunas sobre copias de jugadores |
| Movimientos `[Objetos][Ficha]` en `mybb_op_audit_general` | 1.132 (no son cambios del objeto) |
| Motor de `mybb_op_objetos` (volcado) | MyISAM: no hay transacción posible entre el objeto y su registro (214-DP H-1). Pendiente de confirmar en producción |

## 2. Qué entra en el historial y qué no

Este es el punto que más condiciona el resto. Las opciones están en la
sección 16 y en la tabla consolidada del documento 314; aquí se fija la
recomendación como base de trabajo.

| Tipo de registro | Origen | ¿Se registra? | ¿Se muestra al público? (recomendación) |
|---|---|---|---|
| Alta, modificación, renombre o retirada de un objeto del catálogo hecha por el staff | `objetos_modificar.php`, `barco_unico.php` (corregido, RC-3), `objetos_crear.php` (según D-13) | Sí | Sí, según las reglas de visibilidad |
| Cambios del staff sobre una copia personalizada de un jugador (por ejemplo `ROPA004-261-4`) | `objetos_modificar.php` | Sí | No; sí para su dueño (depende de U-4) y para el staff (depende de D-8) |
| Copias creadas por jugadores al craftear, bautizar barcos o personalizar | `crafteo.php`, `barco_bautizar.php`, `inventario.php`, `personaje.php` | No (depende de D-5; el Game Designer recomienda A) | No |
| Objetos de catálogo marcados `invisible=1` | Cualquier ruta del staff | Sí | Depende de U-1 (que sustituye a la parte de invisibles de D-3). Recomendación: solo los que estén en circulación (CAMBIA EL SENTIDO, RC-6) |
| Objetos de catálogo `exclusivo=1` (no se venden en la tienda) | Cualquier ruta del staff | Sí | Sí: `exclusivo` no oculta nada por sí solo |
| Otorgar o retirar objetos de una ficha | `objetos_ficha.php` (`mybb_op_audit_general`) | No | No: es movimiento de inventario |
| Cambios masivos de id (tabla `mybb_op_cambioid`) | Herramienta antigua | No en la primera fase | No |

**Qué significa `invisible` (corregido, RC-5).** En el código, `invisible=1`
solo significa "no aparece en el listado del catálogo". **No significa "aún no
lanzado"**: la tienda, el crafteo, el mercado negro, el BBCode `[objeto=ID]`,
el inventario y los intercambios ignoran la marca, así que un objeto invisible
puede estar a la venta, fabricarse o salir en el mercado negro (112-GD 2.1).
Con 661 objetos (el 55 % del catálogo), la marca mezcla objetos únicos de
personaje, recompensas de evento, materiales internos y objetos que solo se
fabrican.

**Objeto "en circulación" (añadido, RC-6).** Definición del Game Designer
(112-GD 2.2), base de la recomendación U-1 B: un objeto de catálogo está en
circulación pública si se vende en la tienda (`exclusivo=0` y `tier <= 3`) o
tiene fabricación abierta (`oficio` no vacío y `crafteo_usuarios` vacío). El
mercado negro (`negro=1`, tier 1 a 5) cuenta solo para quien tiene acceso a él
(depende de U-2). Valor provisional hasta la calibración 112-GD 10.1.

Requisitos:

1. Un objeto pertenece al catálogo si no es una copia personalizada. La regla
   para distinguirlo debe ser la misma que ya usa el catálogo público
   `op/objetos.php` (`custom='0'`), y el diseño debe confirmar con una consulta
   de solo lectura en producción que esa regla da los **1.204** objetos medidos
   (corregido, RC-1; la revisión 1 decía 1.304). Si no coincide, se combina con
   el patrón de id `BASE-uid-n`.
2. La vista pública no muestra cambios de copias personalizadas de jugadores
   (depende de D-2).
3. (CAMBIA EL SENTIDO, RC-6) La visibilidad pública de un registro nuevo se
   decide con la **instantánea** de disponibilidad guardada en el propio
   registro (antes y después del cambio, requisito 40), no con el estado
   actual del objeto. La regla que se aplica a la instantánea depende de U-1
   (recomendación B: público si es visible o está en circulación). El estado
   actual solo decide la visibilidad de los registros antiguos (requisito 49).
   *Revisión 1: "La vista pública no muestra cambios de objetos cuyo estado
   actual sea `invisible=1`, ni el de ningún registro cuyo payload indique que
   el objeto era invisible en el momento del cambio (depende de D-3)."*
4. (CAMBIA EL SENTIDO, RC-7) Cuando un objeto pasa de no público a público, el
   historial lo presenta como **"Nuevo en el catálogo"** con sus valores
   actuales, sin `Antes / Ahora` y sin revelar nunca los cambios hechos
   mientras no era público. Cuando pasa de público a no público, se presenta
   como **"Deja de estar en el catálogo"**, sin valores del estado nuevo. Una
   entrada que ya fue pública sigue siéndolo aunque el objeto se oculte
   después (112-GD 2.4). *Revisión 1: solo se pedía "objeto publicado" al pasar
   de invisible a visible.*
5. Los movimientos de inventario de `objetos_ficha.php` y de cualquier otra
   ruta de jugadores nunca aparecen en esta página.

## 3. Acceso

Decisión propuesta: **página pública, también para visitantes sin sesión**,
igual que el historial de técnicas.

Justificación (corregido, RC-8): el catálogo `op/objetos.php` muestra
públicamente precio, tier, daño, bloqueo, efecto, alcance, escalado y
requisitos de cada objeto visible, así que saber cuándo y cómo cambiaron esos
valores no añade información sensible y ayuda a que los jugadores confíen en
los ajustes de equilibrio. **Matiz descubierto por el Game Designer:** hoy el
catálogo envía al navegador la fila completa de todos los objetos de
catálogo, incluidos los invisibles y los campos restringidos, y lo oculta solo
con JavaScript; dos rutas más devuelven JSON público de todas las armas. Por
eso la página nueva no debe publicarse hasta corregir esa fuga (issue #34,
requisito 80). Sin esa corrección, ocultar algo en el historial no protege
nada.

6. La página es pública y se puede consultar, filtrar y paginar sin iniciar
   sesión.
7. (corregido, RC-9) El alcance `Mis objetos` aparece solo con sesión iniciada
   y se calcula con el inventario del personaje del UID de la sesión
   (`mybb_op_inventario`). Una copia personalizada del jugador cuenta como su
   objeto base (el prefijo antes de `-uid-n`, o de `-n` en barcos), **siguiendo
   la cadena de renombres**: si `KAT001` se renombró a `KATF001`, la copia
   `KAT001-261-3`, que conserva el prefijo viejo, cuenta como copia de
   `KATF001` (depende de D-7; el Game Designer recomienda A).
8. La página es de solo lectura y no acepta peticiones que modifiquen datos.
9. (corregido, RC-10) El staff (`is_mod` o `is_staff`) puede ver, además, los
   registros que la vista pública oculta, marcados con una etiqueta visible
   "Solo staff" y el motivo: objeto invisible, fuera de circulación, copia de
   jugador, solo mercado negro, campos reservados, registro antiguo sin objeto
   actual o retirada antigua sin confirmar (depende de D-8).

## 4. Vista principal

10. Existe una única vista cronológica, sin pestañas, con periodo inicial de
    90 días.
11. (corregido, RC-7) Cada entrada muestra fecha y hora, nombre, id del
    objeto, tipo de entrada (nuevo, actualizado, renombrado, retirado, nuevo en
    el catálogo, deja de estar en el catálogo, registro antiguo), categoría,
    subcategoría, tier y los campos afectados como etiquetas.
12. Los detalles de cada entrada empiezan contraídos.
13. Un registro estructurado muestra, por cada campo cambiado **y visible para
    quien mira**, el valor `Antes` y el valor `Ahora`. Un alta muestra los
    valores iniciales (corregido, RC-11).
14. Un renombre muestra el id anterior y el nuevo, y el historial del objeto
    sigue siendo uno solo: buscar por el id nuevo también encuentra los
    registros hechos con el id anterior, y al revés. La búsqueda por id
    anterior solo funciona con ids que aparecen en entradas visibles para
    quien busca (corregido, RC-11).
15. Un registro antiguo se identifica como "Registro antiguo" y no promete una
    comparación que no existe (ver sección 10).
16. Las entradas se agrupan visualmente por día (`Hoy`, `Ayer`, fecha).
17. Cada entrada tiene un ancla estable `#cambio-ID`.
18. (corregido, RC-12) Si el objeto sigue existiendo y es público para quien
    mira (o quien mira es staff o su dueño), la entrada ofrece la ficha del
    estado actual. El enlace `Ver en el catálogo` aparece solo si además el
    objeto es visible en el catálogo (`invisible=0`), no es de Transportes
    (requisito 78) y no está retirado.
19. (corregido, RC-13) Los objetos que tiene el usuario se distinguen con texto
    e icono, no solo con color. Marcas: "Lo tienes" (tiene el objeto tal cual),
    "Tienes una copia" (tiene una copia, depende de D-7), "Lo tienes · no
    aparece en el catálogo" (entrada no pública que ve por tenerlo, requisito
    69), "Cambio del staff en tu objeto" (depende de U-4) y "Mercado negro"
    (campos que solo ve quien tiene acceso, depende de U-2).
20. (corregido, RC-14) Los cambios de valor en berries (`berries`), coste de
    fabricación (`berriesCrafteo`) y tier se muestran como cualquier otro campo
    (D-6, resuelta por el Game Designer como B ampliada, pendiente de confirmar
    por el usuario). Los campos del mercado negro (`negro`, `negro_berries`)
    siguen el requisito 73 (depende de U-2).
21. (corregido, RC-15) El campo `crafteo_usuarios` (lista de UID que pueden
    fabricar un objeto) nunca muestra sus valores, ni nombres, ni cuántos
    personajes hay en la lista. Textos públicos exactos (112-GD 3.4): de vacía
    a con UID, "La receta pasa a ser exclusiva: solo pueden fabricarlo
    personajes autorizados."; de con UID a vacía, "La receta se abre: puede
    fabricarlo cualquier personaje con el oficio y nivel indicados."; de una
    lista no vacía a otra, nada en la vista pública. El personaje que entra o
    sale de la lista ve "Ahora puedes fabricar este objeto." o "Ya no puedes
    fabricar este objeto." (depende de D-9).

## 5. Filtros principales

Siempre visibles:

| Parámetro | Valores | Predeterminado |
|---|---|---|
| `q` | Texto libre sobre nombre, id (actual o anterior) o detalle | Vacío |
| `periodo` | `7`, `30`, `90`, `todo` | `90` |
| `accion` | `todas`, `creado`, `modificado`, `renombrado`, `retirado` | `todas` |
| `orden` | `recientes`, `antiguos` | `recientes` |

22. Los cuatro filtros principales están siempre visibles y se combinan entre
    sí. La acción puede presentarse como fila de chips en lugar de un
    desplegable (decisión visual del UI Designer, 214-UI 2.3; corregido, RC-16).

## 6. Filtros avanzados

23. (CAMBIA EL SENTIDO, RC-17) Dentro de un bloque desplegable se ofrecen:
    - Categoría (Armas, Materiales, Utensilios, Consumibles, Especiales,
      Estructuras, Transportes).
    - Subcategoría.
    - Tier.
    - Grupo de campo modificado, con los **nueve grupos** del Game Designer
      (112-GD 3.1): Identidad, Combate y uso, Precio y comercio, Fabricación,
      Inventario, Presentación, Disponibilidad, Mercado negro (solo con acceso
      al mercado negro o staff) y Reservado (solo staff). *Revisión 1: siete
      grupos provisionales (clasificación, economía, combate, fabricación,
      inventario, presentación y visibilidad).*
    - Autor del cambio.
    - Formato: todos, comparación (estructurado) o registro antiguo.
    - Alcance: todos los objetos o mis objetos (solo con sesión).
24. Los valores de categoría, subcategoría y tier se validan contra los datos
    reales del catálogo. Un parámetro manipulado o desconocido vuelve a su
    valor neutro y nunca llega concatenado a una consulta.
25. El bloque de filtros avanzados aparece abierto si alguno de sus filtros
    está activo.

## 7. Estado de filtros en la URL

26. Todos los filtros viven en la URL, de modo que un enlace filtrado se puede
    compartir.
27. Los filtros activos aparecen como etiquetas que se pueden quitar una a una,
    y existe la acción `Limpiar filtros`.
28. Cambiar un filtro vuelve a la página 1; la paginación conserva todos los
    filtros.
29. El resumen indica cuántos resultados hay con los filtros y cuántos cambios
    reconocidos hay en el periodo, contados solo entre las entradas visibles
    para quien mira (corregido, RC-18).
30. Si no hay resultados se muestra un estado vacío con salida para limpiar
    filtros.

## 8. Relación con el catálogo de objetos

Hallazgo: **no existe hoy un buscador público de objetos**. `op/objetos.php`
pinta el catálogo entero con JavaScript (cada objeto en un `div` con su id),
sin buscador ni parámetro para abrir un objeto concreto. El único buscador por
id o nombre es el typeahead interno de `objetos_modificar.php`, solo para staff.

31. `Ver objeto actual` lleva a un lugar donde se ve el objeto vigente. La
    forma concreta se decide en D-10; la recomendación es mostrar una ficha
    compacta del estado actual dentro de la propia entrada del historial y,
    además, enlazar a `op/objetos.php#ID` con un pequeño ajuste para que la
    página se desplace al objeto una vez pintado.
32. `op/objetos.php` muestra un acceso visible al historial, con el mismo
    patrón de tarjeta que `op/tecnicas.php` usa para el historial de técnicas
    (posición: depende de V-5; recomendación, arriba).
33. Para el staff, cada entrada enlaza también a
    `op/staff/objetos_modificar.php?objeto_id=ID`.
34. Un objeto retirado (`BORR...`, categoría `borrada`) se muestra como
    "Retirado" y no ofrece `Ver objeto actual`.

## 9. Registro de cambios futuros (el cambio más importante)

Hoy no se guarda el antes ni el después. Sin esta parte, la página nueva solo
podría mostrar registros antiguos sin detalle. Es la primera pieza a construir
y debe estar en producción cuanto antes, porque cada día sin ella es historial
que se pierde.

35. (CAMBIA EL SENTIDO, RC-3: amplía las rutas) Toda alta, modificación,
    renombre y retirada del catálogo hecha desde las herramientas del staff se
    registra con un formato estructurado: id del objeto, id interno de la fila
    (`id`, que no cambia al renombrar), nombre, acción, origen, autor y la
    lista de campos con su valor anterior y nuevo. Las rutas son
    `op/staff/objetos_modificar.php`, **`op/barco_unico.php`** (crea y edita
    barcos de catálogo y hoy no deja ningún log) y `op/staff/objetos_crear.php`
    mientras exista (depende de D-13). También se registran las ediciones del
    staff sobre copias de jugadores (su visibilidad la deciden D-2 y U-4).
36. El registro se escribe en `mybb_op_audit_consola_mod`, igual que el de
    técnicas, con una marca estable al inicio del log (análoga a
    `[CAMBIO_TECNICA]`; el diseño propone `[CAMBIO_OBJETO] `) seguida de JSON.
    No requiere migración SQL. Si el diseño propone una tabla propia, debe
    justificarlo (ver D-11; el diseño adopta la opción A). La consulta pública
    recomienda una migración de índices idempotente y no bloqueante
    (`docs/214_historial_objetos_indices.sql`, corregido, RC-19).
37. (corregido, RC-4) El valor anterior se lee de la base de datos justo antes
    de escribir, no del formulario, y el valor nuevo **se relee de la base
    justo después de escribir**, porque MySQL puede convertir lo que manda el
    formulario (por ejemplo, una cadena vacía en una columna entera se guarda
    como 0).
38. (corregido, RC-4) Solo se registra una modificación cuando al menos un
    campo cambia. Guardar el formulario sin cambios no genera entrada. Un
    cambio que solo añade o quita espacios en los extremos, o que solo cambia
    `\r\n` por `\n`, no cuenta como cambio.
39. (corregido, RC-20) Los campos registrados son todos los que edita
    `objetos_modificar.php`: `objeto_id`, nombre, descripción, categoría,
    subcategoría, tier, `imagen_id`, `imagen_avatar`, imagen, berries,
    `berriesCrafteo`, `cantidadMaxima`, daño, bloqueo, efecto, alcance,
    escalado, requisitos, exclusivo, invisible, espacios, `crafteo_usuarios`,
    desbloquear, oficio, nivel, `tiempo_creacion`, editable, comerciable,
    negro, `negro_berries`, `fusion_tipo` y engastes (32 campos). No se
    registran como cambio: `id` (va como dato propio del registro), `custom`
    (va en la instantánea), `desbloqueado` y `fecha_creacion` (no se editan en
    el formulario). En `barco_unico.php` la comparación es sobre la fila
    entera releída, así que aparecen solo los campos que cambiaron. Las
    estadísticas de `mybb_op_barcos` (vitalidad, velocidad...) no son columnas
    del objeto y quedan fuera.
40. (CAMBIA EL SENTIDO, RC-6: amplía lo que se guarda) Cada registro guarda,
    **antes y después del cambio y aunque no hayan cambiado**, una instantánea
    de disponibilidad: `custom`, `invisible`, `exclusivo`, `tier`,
    `comerciable`, `negro`, si `oficio` está vacío, si `crafteo_usuarios` está
    vacío y la categoría. Nunca guarda los UID de `crafteo_usuarios` en la
    instantánea (solo en la lista de cambios si cambiaron). *Revisión 1: solo
    se pedía guardar si el objeto era copia y si era invisible.*
41. Un renombre registra el id anterior y el nuevo como cambio del campo
    `objeto_id` y conserva el id interno de la fila, para unir el historial
    del objeto antes y después.
42. (CAMBIA EL SENTIDO, RC-22, depende de D-13) La retirada de un objeto
    (renombre a `BORR...` y categoría `borrada`) se registra como acción
    `retirado` con el mismo formato. Con D-13 A, la retirada pasa a
    `objetos_modificar.php` y `objetos_crear.php` se sustituye por una
    redirección, **no se borra**, porque el despliegue nunca borra archivos en
    el servidor y un archivo borrado en local seguiría vivo en remoto.
    *Revisión 1: la retirada se registraba en `objetos_crear.php`.*
43. Los textos muy largos (descripción, efecto) pueden abreviarse en la
    auditoría, indicando de forma explícita que se abreviaron. Nunca se guarda
    un JSON truncado (corregido, RC-4).
44. (corregido, RC-23) El log interno del staff (`op/staff/log_consola_mod.php`)
    convierte el formato estructurado de objetos a texto legible, como ya hace
    con el de técnicas, y muestra los registros no confirmados (requisito 67)
    como "Cambio sin confirmar".
45. La lógica de registro, interpretación y detección de campos vive en una
    librería pura en `inc/plugins/lib/` con pruebas en `tests/objetos/`
    (análoga a `tecnicas_cambios.php` y `tests/tecnicas/cambios.php`).

Requisitos nuevos de esta sección (añadidos, RC-24):

64. (NUEVO) `objetos_modificar.php` rechaza el renombre hacia un id que ya
    existe, con el mensaje "Ya existe otro objeto con el ID X.", sin escribir
    nada. Hoy crea un duplicado que rompe el historial (`objeto_id` no es
    único en la tabla).
65. (NUEVO) Si hay varias filas con el mismo `objeto_id` (duplicados
    heredados), cada fila afectada genera su propio registro.
66. (NUEVO) Guardar en `objetos_modificar.php` sin cambios no escribe ni el
    objeto ni el registro, y muestra "No había cambios que guardar." en lugar
    de "Objeto guardado.".
67. (NUEVO) Un corte entre la escritura del objeto y la de su registro nunca
    deja un cambio del catálogo sin rastro, y la página pública nunca muestra
    un cambio que no llegó a escribirse. (El diseño lo resuelve con un
    registro en dos fases, pendiente y confirmado, porque la tabla de objetos
    no admite transacciones.)
68. (NUEVO) La razón que se guarda con cada registro nuevo es el texto fijo
    "Cambio de objeto" (hoy `objetos_modificar.php` guarda "Apertura"). La
    razón nunca se muestra en la página pública.

## 10. Cómo tratar el pasado

Los 430 registros existentes no tienen diferencias. No se pueden reconstruir.

46. (corregido, RC-25) Los registros antiguos se reconocen con sus tres
    formatos conocidos (`Nuevo objeto creado de objeto ID ...`, `Objeto X
    (creado por Y): ...` y `Objeto X (modificado por Y): ...`) y se interpretan
    de forma tolerante: un log que no encaja se ignora, no rompe la página. La
    retirada antigua de `objetos_crear.php` (`Remover técnica ID X (...). Nuevo
    ID de la técnica: BORRX. Tipo: borrada.`) también se reconoce, pero solo la
    ve el staff, porque su texto se confunde con los de técnicas.
47. Un registro antiguo de modificación se muestra como "Registro antiguo:
    el objeto se actualizó, pero en esa fecha no se guardaba qué cambió". Se
    muestran nombre, categoría y subcategoría resultantes como "Estado tras el
    cambio", nunca como "Antes / Ahora" (depende de D-12).
48. Un alta antigua de `objetos_crear.php` muestra los valores iniciales que
    conserva el log (nombre, categoría, daño, berries, cantidad máxima,
    imagen, descripción), descartando los campos que el log trae vacíos
    porque nunca existieron en el formulario (`calidad`, `unidades`).
49. (corregido, RC-25) Los registros antiguos sobre copias personalizadas o
    sobre objetos que hoy no son públicos siguen los requisitos 2 y 3 según el
    estado actual del objeto, ya que el log antiguo no guarda ese dato. Si el
    objeto ya no existe con ese id, el registro solo lo ve el staff. Los
    registros antiguos no tienen excepción de dueño (requisito 69).
50. Los registros antiguos no muestran el campo `razon` ni texto de
    moderación interna.

## 11. Accesibilidad y responsive

51. Todos los campos de filtro tienen `label` y nombre accesible.
52. Los filtros avanzados y los detalles usan elementos nativos desplegables
    que funcionan con teclado y sin JavaScript.
53. (CAMBIA EL SENTIDO, RC-26: endurece el valor) Los botones, enlaces de
    acción y campos de filtro tienen una altura mínima de **44 px** y foco
    visible con los tokens del foro. *Revisión 1: 40 px. El UI Designer fija
    44 px, que cumple también el valor anterior.*
54. Acción, pertenencia ("Lo tienes" y las demás marcas del requisito 19),
    tipo de registro y "Solo staff" se expresan con texto además de color.
55. En móvil los controles se apilan y la comparación `Antes / Ahora` pasa de
    dos columnas a una.
56. `Expandir todos` y `Contraer todos` funcionan como mejora progresiva.

La forma visual concreta (reutilizar el estilo del historial de técnicas,
iconos por tipo, ficha compacta del objeto, marcas) la define el UI Designer
en 214-UI.

## 12. Rendimiento y seguridad

57. (corregido, RC-19) La consulta de auditoría solo selecciona registros cuyo
    inicio coincide con los formatos de objetos conocidos (marca nueva y
    formatos antiguos), y aplica el periodo en SQL antes de interpretar.
    Además hay una consulta sin periodo, solo de renombres y retiradas, para
    unir el historial de un objeto renombrado, y un tope de 5.000 candidatos
    por visita con aviso si se alcanza.
58. (corregido, RC-19) El catálogo no se carga entero en cada petición: se
    consultan solo los objetos referidos por los registros de la página (por
    id interno en los registros nuevos y por `objeto_id` solo en los
    antiguos). Estrategia elegida en 214-DP 6.1.
59. El inventario del usuario se consulta una vez por petición y solo si el
    alcance o la marca "Lo tienes" lo necesitan.
60. Todo texto procedente de auditoría o del catálogo se escapa antes de
    mostrarse. No se renderiza HTML guardado en la base de datos, aunque
    campos como `efecto` o `descripcion` lo contengan.
61. Ningún valor de la URL llega sin validar o escapar a una consulta.
62. No se muestra el campo `razon` del log de consola ni el UID del staff.
63. El volumen esperado (unos 430 registros antiguos más los nuevos) permite
    filtrar en PHP como en técnicas; si supera decenas de miles, el siguiente
    paso es una tabla indexada (fuera de esta fase).

## 12b. Reglas de visibilidad añadidas por el Game Designer (añadido, RC-27)

69. (NUEVO) **Excepción de dueño.** Quien tiene un objeto en su inventario (tal
    cual, o una copia si D-7 es A) ve las entradas de registros nuevos de ese
    objeto aunque no sean públicas, con la marca "Lo tienes · no aparece en el
    catálogo" y los mismos filtros de campos que el público.
70. (NUEVO) **Ninguna pista indirecta.** Si tras filtrar no queda ningún campo
    ni línea visible para quien mira, la entrada no existe para él. Nunca se
    muestra "N campos ocultos". Los recuentos del resumen y de los chips, las
    opciones de los filtros (autores, subcategorías, tiers) y la búsqueda se
    calculan después de aplicar la visibilidad.
71. (NUEVO) **Creación única de personaje.** Un objeto de catálogo cuyo id
    empieza por un número (el UID de su dueño, por ejemplo `352MI001`) y que
    es invisible no es público, aunque esté en circulación.
72. (NUEVO) **Qué cambia para ti.** Cuando un cambio altera la disponibilidad,
    la entrada añade líneas derivadas sin valores 0 y 1: "Ahora se vende en la
    tienda." / "Deja de venderse en la tienda.", "Ahora se puede
    intercambiar." / "Ya no se puede intercambiar.", "Ahora puede fabricarlo
    cualquier personaje con el oficio indicado." / "Ya no se puede fabricar
    libremente." (públicas) y "Entra en el mercado negro." / "Sale del mercado
    negro." (solo con acceso, depende de U-2).
73. (NUEVO, depende de U-2) Los campos `negro` y `negro_berries` y sus líneas
    derivadas solo los ven quien pasa el mismo control de acceso que
    `op/mercado_negro.php` y el staff. Un registro que solo cambia esos campos
    no existe para el público.
74. (NUEVO) Los campos `fusion_tipo` y `engastes` solo los ve el staff, porque
    la fusión no tiene mecánica activa y publicarlos anunciaría un sistema que
    no existe.
75. (NUEVO) **Las etiquetas siguen al código, no a la ayuda del formulario del
    staff:** `comerciable` se muestra como "Se puede intercambiar" con el
    valor invertido (0 = Sí); `exclusivo` como "Se vende en la tienda" (0 = sí,
    si además tier ≤ 3); `invisible` nunca como fila, solo como transición
    (requisito 4); las imágenes como "Imagen actualizada", sin pintar la
    anterior; `imagen_id` como nombre de color de rareza; `tiempo_creacion` en
    horas y minutos; `negro_berries = 9.999.999` como "Sin precio fijado";
    `berries` con la etiqueta "Valor en berries". El jugador nunca ve 0 y 1.
76. (NUEVO) La página muestra una vez el aviso "Antes del [fecha] no se
    guardaba qué cambiaba en cada objeto. Esos registros aparecen como
    Registro antiguo.", con la fecha real en que el registro nuevo empezó a
    funcionar en producción.
77. (NUEVO) Las variaciones de `berries`, `berriesCrafteo` y `negro_berries`
    muestran el porcentaje con signo (`+20 %`, `−15 %`), redondeado a entero;
    si el valor anterior era 0, "antes sin valor". Las demás cifras muestran la
    diferencia absoluta. Ninguna diferencia lleva color de "bueno" o "malo".
78. (NUEVO) Mientras `op_objetos.html` no pinte la categoría Transportes, las
    entradas de transportes no ofrecen `Ver en el catálogo`.
79. (NUEVO, depende de U-3) Cuando el visor tiene una copia del objeto
    cambiado, la entrada avisa: "Tu copia X conserva los valores con los que
    se fabricó. Este cambio no la modifica." (o "Tu copia ya se fabricó con
    estos valores." si se fabricó después del cambio; provisional hasta
    confirmar que `fecha_creacion` es fiable en las copias).
80. (NUEVO, dependencia externa) La página pública no se publica en producción
    hasta que esté cerrada la issue #34 (filtrar en el servidor los datos de
    objetos que se envían al navegador).

## 13. Fuera de alcance

- Restaurar versiones anteriores de un objeto desde el historial.
- Editar objetos desde la página pública.
- Reconstruir el antes y el después de los 430 registros antiguos.
- Historial de inventario de un personaje (qué objetos ganó o perdió).
- Notificaciones o suscripciones por objeto.
- Un buscador público completo del catálogo de objetos (si se quiere, es otra
  funcionalidad; ver D-10).
- Corregir la seguridad de `objetos_crear.php` más allá de lo necesario para
  registrar sus cambios: va en la issue #33 (ver D-13).
- Corregir la fuga de datos del catálogo y de los JSON públicos de armas: va
  en la issue #34 (U-5), que es dependencia de publicación (requisito 80).
- Diferencias palabra por palabra en textos largos: se comparan bloques
  completos.
- (añadido, RC-28) Historial de las estadísticas de barcos de `mybb_op_barcos`.
- (añadido, RC-28) Propagar los cambios de un objeto base a las copias ya
  fabricadas (U-3 C).
- (añadido, RC-28) Los hallazgos laterales de la sección 17 que no afectan al
  historial (ayuda invertida del formulario, `suriprueba.php`, Transportes en
  el catálogo, inventario tras una retirada, Akumas en intercambios): van al
  backlog (documento 314, sección 8).

## 14. Criterios de aceptación

(corregido, RC-29: se ajustan 6, 7 y 9 y se añaden 16 a 21)

1. Un visitante sin sesión puede consultar, buscar, filtrar y paginar el
   historial.
2. Al guardar un objeto en `objetos_modificar.php` cambiando su precio, el
   historial muestra una entrada con `Valor en berries`, el valor anterior, el
   nuevo y el porcentaje.
3. Guardar un objeto sin cambiar nada no crea entrada ni escribe el objeto.
4. Renombrar un objeto crea una entrada de renombre, y buscar por el id
   anterior o por el nuevo devuelve todo el historial del objeto.
5. Retirar un objeto crea una entrada `Retirado` sin enlace a objeto actual.
6. Un cambio sobre una copia personalizada (`BASE-uid-n`) no aparece en la
   vista pública (según D-2); su dueño lo ve según U-4.
7. Un cambio sobre un objeto no público (según U-1) no aparece en la vista
   pública, y su publicación posterior aparece como "Nuevo en el catálogo" sin
   revelar los cambios previos.
8. Los 430 registros antiguos se muestran como "Registro antiguo" sin
   comparación `Antes / Ahora`; las altas de `objetos_crear.php` muestran sus
   valores iniciales.
9. Un usuario conectado puede limitar la vista a sus objetos, incluidos los
   objetos base de sus copias personalizadas aunque la base se haya renombrado
   (según D-7).
10. Los filtros de periodo, acción, categoría, subcategoría, tier, grupo de
    campo, autor y formato se combinan, se reflejan en la URL y sobreviven a
    la paginación.
11. La página no muestra la razón interna, el UID del staff, los valores de
    `crafteo_usuarios`, los campos reservados, los del mercado negro sin
    acceso ni movimientos de inventario de fichas, ni siquiera en el código
    fuente del HTML.
12. El log interno del staff muestra los registros estructurados de objetos
    como texto legible, y los no confirmados como "Cambio sin confirmar".
13. Las pruebas de `tests/objetos/` cubren: registro de alta, modificación con
    y sin cambios, renombre, retirada, interpretación de los formatos
    antiguos, detección de campos, exclusión de copias e invisibles y los
    ejemplos resueltos E-1 a E-15 de 112-GD.
14. Todos los filtros y detalles se usan con teclado y funcionan en móvil.
15. `sh tests/run_all.sh` termina en VERDE.
16. Editar un barco único en `barco_unico.php` crea una entrada estructurada.
17. Renombrar un objeto hacia un id que ya existe se rechaza sin escribir nada.
18. Un registro que solo cambia campos ocultos para quien mira no aparece ni
    cuenta en el resumen ni en los chips.
19. Un usuario que tiene un objeto no público ve sus cambios con la marca "Lo
    tienes · no aparece en el catálogo".
20. Un fallo simulado entre la escritura del objeto y la de su registro deja
    un registro "pendiente" que la página pública no muestra.
21. La página no se publica antes de cerrar la issue #34.

## 15. Quién decide qué

(corregido, RC-30: la tabla única de decisiones, con la propuesta del PM y lo
que bloquea cada issue, está en el documento 314, sección 6)

| Tema | Responsable | Por qué |
|---|---|---|
| Qué se muestra públicamente (D-1, D-2, U-1 con D-3, D-8, D-9, U-2, U-4) | Usuario, con propuesta del PM y del Game Designer | Es una decisión de producto, de privacidad y de reglas |
| Precio y tier (D-6), copias ya fabricadas (D-7, U-3), cambios de jugadores (D-5) | **Game Designer** propone (ya lo hizo en 112-GD), decide el usuario | Economía y equilibrio |
| Grupos de campos (requisito 23) | Game Designer (resuelto: nueve grupos) | Deben coincidir con cómo los jugadores piensan los objetos |
| Aspecto de la página, ficha compacta, iconos, marcas, tarjeta de acceso (D-10, V-1 a V-7) | **UI Designer** propone, el usuario revisa con capturas | Decisiones visuales |
| Formato de almacenamiento (D-11), índices, orden de escrituras | Developer con el PM | Técnico |
| Contradicciones entre documentos (C-1 a C-15) | PM las resuelve con la regla: reglas de juego, Game Designer; aspecto, UI Designer; técnica, Developer | Ver 314, sección 6 |

## 16. Preguntas abiertas y decisiones del usuario

Las recomendaciones de la revisión 1 se conservan; donde otro rol las revisó,
se indica (corregido, RC-31).

**D-1. Qué se muestra públicamente.**
- A. Todo el historial del catálogo visible, con todos los campos (salvo
  `crafteo_usuarios`).
- B. Solo nombre, categoría, tier, precio y estadísticas de combate; ocultar
  campos de fabricación y de mercado negro.
- C. Página solo para staff.
- **Recomendación: A**, con las restricciones de campo del Game Designer
  (mercado negro según U-2, reservados solo staff, `crafteo_usuarios` sin
  valores).

**D-2. Copias personalizadas de jugadores (15.036 objetos, corregido).**
- A. Fuera de la vista pública; visibles solo para el staff.
- B. Visibles para todos.
- C. Visibles solo para su dueño (por ejemplo, en `Mis objetos`) y para el staff.
- **Recomendación: A** para el público. Lo que ve el dueño lo decide U-4 (el
  Game Designer recomienda que lo vea).

**D-3. Objetos invisibles y objetos exclusivos de tienda (CONSOLIDADA con
U-1, RC-6).**
- Parte de `exclusivo`: resuelta. `exclusivo=1` solo significa "no se vende en
  la tienda" y no oculta nada (PM y Game Designer de acuerdo).
- Parte de `invisible`: **se decide en U-1** (112-GD 9), que sustituye a esta
  parte de D-3. Opciones: A, ocultar todos los invisibles (recomendación de la
  revisión 1); B, ocultar solo los que están fuera de circulación
  (recomendación del Game Designer y del PM en la revisión 2); C, mostrar
  todos.
- **Recomendación: U-1 B**, antes de cerrarla conviene ver la calibración
  112-GD 10.1 y 10.2: si casi todos los invisibles están fuera de circulación,
  A y B dan casi lo mismo y A es más simple. El cambio de recomendación se
  debe a que `invisible` no significa "aún no lanzado" (sección 2).

**D-4. Movimientos de inventario (`objetos_ficha.php`, 1.132 registros).**
- A. Fuera de esta página.
- B. Incluirlos en un filtro aparte.
- **Recomendación: A.**

**D-5. Cambios hechos por jugadores (bautizar un barco, personalizar o editar
sus copias, craftear).**
- A. No se registran en este historial.
- B. Se registran solo para el staff.
- C. Se registran y se muestran al dueño.
- **Recomendación: A**, con visto bueno del Game Designer (112-GD 2.5): esas
  acciones crean copias nuevas, no modifican el objeto del catálogo.

**D-6. Cambios de precio (`berries`, `berriesCrafteo`, `negro_berries`) y de tier.**
- A. Se muestran como cualquier otro campo.
- B. Se muestran precio de tienda y tier, pero no el precio del mercado negro.
- C. No se muestran.
- **Recomendación: B ampliada** (Game Designer, 112-GD 5.1): `berries`,
  `berriesCrafteo` y `tier` públicos; `negro` y `negro_berries` según U-2.

**D-7. `Mis objetos` y copias personalizadas.**
- A. Una copia cuenta como su objeto base (quien tiene `KAT001-1044-3` ve los
  cambios de `KAT001`).
- B. Solo cuentan los objetos de catálogo que el personaje tiene tal cual.
- **Recomendación: A con aviso** (Game Designer, 112-GD 5.4): el código
  confirma que un cambio en la base no modifica las copias, así que la entrada
  lo dice (requisito 79), y la base se calcula siguiendo los renombres.

**D-8. Vista ampliada para el staff.**
- A. El staff ve también invisibles y copias, marcados "Solo staff".
- B. El staff ve lo mismo que el público y usa el log de consola para el resto.
- **Recomendación: A.**

**D-9. Quién puede fabricar un objeto (`crafteo_usuarios`).**
- A. No mostrar valores; solo un texto sobre el cambio.
- B. Mostrar los nombres de personaje.
- C. No mencionarlo.
- **Recomendación: A**, con los textos exactos del requisito 21 (Game
  Designer). Detalle técnico propuesto por el Developer: el staff sí ve los
  UID, que ya ve en el editor.

**D-10. Dónde se ve el objeto actual.**
- A. Ficha compacta del estado actual dentro de la entrada, más enlace a
  `op/objetos.php#ID` con desplazamiento al objeto.
- B. Crear un buscador público de objetos (análogo a `tecnicas_buscar.php`).
- C. Solo el enlace al catálogo, sin ficha.
- **Recomendación: A.**

**D-11. Dónde se guardan los registros nuevos.**
- A. En `mybb_op_audit_consola_mod` con marca y JSON, como técnicas.
- B. Tabla nueva `mybb_op_objetos_cambios` con columnas indexadas.
- **Recomendación: A** (el diseño la adopta). **Bloquea el inicio de la primera
  issue** (documento 314): con B, la primera issue cambia y necesita una
  migración antes de registrar.

**D-12. Registros antiguos de modificación (248).**
- A. Mostrarlos como "Registro antiguo" con el estado resultante (nombre,
  categoría, subcategoría).
- B. Mostrarlos solo con el filtro "registro antiguo" activado.
- C. No mostrarlos.
- **Recomendación: A.**

**D-13. `objetos_crear.php` (issue #33).**
- A. Retirar la página (sustituirla por una redirección, no borrarla) y mover
  la retirada de objetos a `objetos_modificar.php`.
- B. Mantenerla y corregirla (escape de valores, CSRF, registro estructurado).
- C. Mantenerla tal cual y registrar solo lo que hace.
- **Recomendación: A.** Ya no está enlazada desde la consola del staff y tiene
  fallos de seguridad. Hoy hay 0 objetos retirados, así que perder la
  retirada unos días mientras se mueve no afecta a nadie.

**Decisiones de los otros roles** (resumen; el texto completo está en 112-GD 9
y 214-UI 11, y la consolidación en 314, sección 6):
- U-1 (invisibles, sustituye a parte de D-3), U-2 (mercado negro), U-3
  (copias ya fabricadas), U-4 (cambios del staff en copias), U-5 (fuga del
  catálogo; en la práctica ya se eligió la opción B al crear la issue #34).
- V-1 (cómo reutilizar las clases `ct-*`), V-2 (color de rareza), V-3 (chips
  en móvil), V-4 (diferencia numérica), V-5 (posición de la tarjeta), V-6
  (chips como enlaces), V-7 (miniatura en cada entrada).

## 17. Hallazgos en el código existente que afectan al alcance

- **No hay buscador público de objetos.** `op/objetos.php` pinta todo el
  catálogo con JavaScript y oculta `invisible=1`; no admite ir a un objeto
  concreto. Afecta a la sección 8 y a D-10.
- **La marca fiable de copia es `custom=1`.** `crafteo.php`, `inventario.php`,
  `personaje.php` y `barco_bautizar.php` crean las copias con `custom=1`, y el
  catálogo público y la tienda filtran por `custom='0'`. Las copias de barco no
  se marcan `invisible=1`, así que "invisible" no sirve para distinguirlas.
  `listado_objetos.php` usa otra regla (patrón de id). El Game Designer midió
  1.204 objetos de catálogo con `custom='0'` (requisito 1).
- **`objetos_crear.php` sigue accesible y tiene fallos graves** (issue #33):
  interpola valores del formulario sin escapar, no verifica la clave CSRF, su
  log incluye variables que no existen (`calidad`, `unidades`) y el log de
  retirada dice "Remover técnica ID ..." aunque retira un objeto. D-13.
- **El renombre en `objetos_modificar.php` actualiza `mybb_op_inventario`**
  pero el log solo guarda el id nuevo. Sin el id interno de la fila en el
  registro (requisito 41) se pierde la continuidad del historial.
- **`objetos_modificar.php` guarda como razón la palabra fija "Apertura"**
  (requisito 68).
- **El log de consola del staff ya interpreta el JSON de técnicas**
  (`log_consola_mod.php`): el de objetos debe seguir el mismo camino (requisito 44).
- **`mybb_op_cambioid`** (cambios masivos de id) es otra vía que cambia ids de
  objetos sin registro; queda fuera de esta fase.

Hallazgos añadidos en la revisión 2 (RC-32):

- **Fuga del catálogo (Game Designer):** `op/objetos.php` envía al navegador
  la fila completa de los 1.204 objetos de catálogo, incluidos invisibles y
  columnas como `crafteo_usuarios`, `negro`, `negro_berries`, `fusion_tipo` y
  `engastes`; `op/armas_todas.php` y `op/objetos_armas_todas.php` devuelven
  JSON público de armas sin sesión. Issue #34, dependencia de publicación.
- **`invisible` no significa "no lanzado"** (sección 2).
- **Otra ruta del staff sin registro:** `op/barco_unico.php` (requisito 35).
- **Las copias conservan el prefijo viejo tras un renombre** (requisito 7).
- **La ayuda del formulario del staff está invertida** en `comerciable` y
  `exclusivo`: el staff puede estar marcando objetos con la semántica
  equivocada. El historial etiqueta según el código (requisito 75); corregir
  la ayuda va al backlog.
- **`requisitos` son requisitos de uso, no de fabricación**; `docs/agents/DATABASE.md`
  los describe mal.
- **Fusión y engastes no tienen mecánica activa** (requisito 74).
- **`mybb_op_objetos` es MyISAM y `objeto_id` no es único ni está indexado**
  (Developer): sin transacciones (requisito 67) y con posibles duplicados
  (requisitos 64 y 65).
- **`op/suriprueba.php` está activo** y crea copias interpolando valores sin
  escapar (backlog, seguridad).
- **`op_objetos.html` no pinta Transportes** (requisito 78; backlog).
- **La retirada no actualiza `mybb_op_inventario`**: los jugadores conservan
  filas con el id viejo (backlog).
- Fuera de alcance: en `intercambio.php` la excepción de Akuma no Mi compara
  mayúsculas con minúsculas y nunca se cumple (backlog).

## 18. Registro de cambios de este documento

Revisión 2, 24/11/2026, PM. Correcciones con lo que descubrieron el Game
Designer (112-GD), el UI Designer (214-UI) y el Developer (214-DP, sección 16 y
contradicciones C-1 a C-15 de la sección 18).

| # | Dónde | Cambio | ¿Cambia el sentido? | Origen |
|---|---|---|---|---|
| RC-1 | Sección 1 (datos), req. 1, D-2 | Cifras corregidas: 1.204 objetos de catálogo (no 1.304), 543 visibles, 661 invisibles, 792 exclusivos, 15.036 copias (no 14.936, el 93 %). El desglose por categoría queda pendiente de volver a medir | No | 112-GD 0, C-13 |
| RC-2 | Sección 1 (datos) | Añadido el motor MyISAM de `mybb_op_objetos` | No | 214-DP H-1 |
| RC-3 | Sección 1, sección 2, req. 35 | Añadida `op/barco_unico.php` como ruta del staff que se registra; se aclara que también se registran las ediciones de copias | Sí (amplía el alcance del registro) | 112-GD 11.4, 214-DP H-6 |
| RC-4 | Req. 37, 38, 43 | El después se relee de la base; la comparación ignora espacios de los extremos y `\r\n`; nunca se guarda un JSON truncado | No (precisa cómo se cumple) | 214-DP 3.5, 3.7, H-5 |
| RC-5 | Sección 2 | Nuevo párrafo: `invisible` no significa "aún no lanzado" | No (hecho del código) | 112-GD 2.1, 11.2 |
| RC-6 | Sección 2, req. 3, req. 40, D-3 | D-3 (parte de invisibles) consolidada con U-1; definición de "en circulación"; visibilidad de los registros nuevos por instantánea y no por estado actual; instantánea ampliada a nueve datos antes y después | **Sí** (req. 3 y 40; recomendación de D-3 pasa de A a U-1 B) | 112-GD 2.2, 2.4, 9 (U-1), 11.5; C-1, C-15 |
| RC-7 | Req. 4, req. 11 | "Objeto publicado" pasa a "Nuevo en el catálogo"; se añade "Deja de estar en el catálogo"; lo que fue público sigue siéndolo | **Sí** (req. 4) | 112-GD 2.4, C-4 |
| RC-8 | Sección 3 (justificación) | Se corrige "no expone nada nuevo": hoy el catálogo ya filtra todo en el navegador; la página depende de la issue #34 | No (la decisión de página pública se mantiene) | 112-GD 11.1, U-5 |
| RC-9 | Req. 7 | La base de una copia se calcula siguiendo la cadena de renombres; se añade el patrón `BASE-n` de barcos | No (precisa) | 112-GD 5.4.3, 11.6 |
| RC-10 | Req. 9 | Motivos de "Solo staff" ampliados | No | 214-DP 7.7, C-10 |
| RC-11 | Req. 13, 14 | Solo se comparan campos visibles para quien mira; la búsqueda por id anterior solo usa ids de entradas visibles | No (precisa) | 214-DP 7.4, 7.6 |
| RC-12 | Req. 18 | Ficha si el objeto es público para quien mira; enlace al catálogo solo si `invisible=0`, no Transportes y no retirado | No (precisa con U-1 B) | C-7, 214-DP 11.1 |
| RC-13 | Req. 19 | Marcas nuevas: "Tienes una copia", "Lo tienes · no aparece en el catálogo", "Cambio del staff en tu objeto", "Mercado negro" | No (amplía la lista) | 112-GD 4, C-9 |
| RC-14 | Req. 20 | D-6 resuelta por el Game Designer como B ampliada (pendiente del usuario) | No | 112-GD 5.1 |
| RC-15 | Req. 21 | Textos exactos de `crafteo_usuarios`; nada en público si cambia de una lista no vacía a otra; nunca recuentos | No (concreta D-9 A) | 112-GD 3.4 |
| RC-16 | Req. 22 | La acción puede ser una fila de chips | No | 214-UI 2.3 |
| RC-17 | Req. 23 | Nueve grupos de campos del Game Designer en lugar de los siete provisionales | **Sí** (cambia la lista) | 112-GD 3.1, C-2 |
| RC-18 | Req. 29 | Recuentos solo sobre entradas visibles | No | 112-GD principio 5 |
| RC-19 | Req. 36, 57, 58 | Migración de índices opcional; consulta de renombres sin periodo; tope de 5.000; estrategia de consulta del catálogo elegida | No | 214-DP 6.1, 6.2 |
| RC-20 | Req. 39 | Lista de campos que no se registran como cambio; barcos sobre fila entera; estadísticas de barcos fuera | No (precisa) | 112-GD 3.1, 214-DP 3.3 |
| RC-21 | Sección 1 | Principio "se registra todo y se decide después qué se muestra" | No | PM, para no bloquear el registro |
| RC-22 | Req. 42 | Con D-13 A, la retirada pasa a `objetos_modificar.php` y `objetos_crear.php` se sustituye por una redirección, no se borra | **Sí** (según D-13) | 214-DP 4.3, H-8 |
| RC-23 | Req. 44 | El log del staff muestra los no confirmados como "Cambio sin confirmar" | No | 214-DP 3.9 |
| RC-24 | Sección 9 | Requisitos nuevos 64 a 68: rechazo de renombre a id existente, un registro por fila duplicada, guardado sin cambios no escribe, registro sin huecos ante cortes, razón "Cambio de objeto" | **Sí** (nuevos; 64 y 66 cambian el comportamiento visible del editor del staff) | 214-DP 16 (puntos 15, 16), H-4, 3.9 |
| RC-25 | Req. 46, 49 | Retirada antigua reconocida solo para el staff; antiguos de objetos inexistentes solo staff; sin excepción de dueño para antiguos | No (precisa) | 112-GD 6, 214-DP 5 |
| RC-26 | Req. 53 | Altura mínima de 44 px en lugar de 40 px | **Sí** (endurece el valor) | 214-UI 5, C-14 |
| RC-27 | Sección 12b | Requisitos nuevos 69 a 80: excepción de dueño, sin pistas indirectas, creación única, "Qué cambia para ti", mercado negro, reservados, etiquetas según el código, aviso de fecha, porcentajes, Transportes, aviso de copia y dependencia de la issue #34 | **Sí** (nuevos) | 112-GD 2.3, 3, 4, 5; 214-DP 16; C-3, C-5, C-6, C-11 |
| RC-28 | Sección 13 | Fuera de alcance ampliado (estadísticas de barcos, propagación a copias, hallazgos laterales al backlog); #33 y #34 como issues aparte | No | 214-DP 16, 112-GD 9 |
| RC-29 | Sección 14 | Criterios 2, 3, 5, 6, 7, 9, 11, 12 y 13 ajustados; criterios nuevos 16 a 21 | Sí en 7 (ligado a U-1) | Todos |
| RC-30 | Sección 15 | Tabla de responsables actualizada; la consolidación de decisiones vive en 314 | No | PM |
| RC-31 | Sección 16 | Recomendaciones revisadas por otros roles anotadas; D-3 consolidada con U-1; D-11 marcada como bloqueante de la primera issue; D-13 enlazada a #33; resumen de U-1 a U-5 y V-1 a V-7 | No, salvo D-3 (ver RC-6) | 112-GD 9, 214-UI 11 |
| RC-32 | Sección 17 | Hallazgos añadidos de la revisión 2 | No | 112-GD 11, 214-DP 0 y 16, 214-UI 10 |
