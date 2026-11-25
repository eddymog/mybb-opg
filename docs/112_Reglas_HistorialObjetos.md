# Reglas de juego: Historial público de objetos

> Rol: Game Designer. Revisa y completa [112_Requirements_HistorialObjetos.md](112_Requirements_HistorialObjetos.md).
> Estado: propuesta. Lo marcado como **provisional** depende de los datos de calibración de la sección 10. Las decisiones de la sección 9 son del usuario.
> Este documento resuelve D-5, D-6, D-7, D-9 y los grupos de campos (requisito 23), y revisa D-2 y D-3 desde la vista de juego.

## 0. Datos de partida corregidos

Medidos en producción (sustituyen a las cifras de la sección 1 del documento 112):

| Dato | Valor |
|---|---|
| Filas en `mybb_op_objetos` | 16.240 |
| Catálogo real (`custom='0'`) | **1.204** (el documento 112 decía 1.304) |
| De ellos, visibles en el catálogo (`invisible=0`) | **543** |
| De ellos, invisibles (`invisible=1`) | **661 (55 %)** |
| De ellos, `exclusivo=1` | 792 |
| Copias de jugadores (`custom='1'`) | **15.036** (14.852 invisibles; el documento 112 decía 14.936) |
| Registros antiguos de cambios | 430 (solo nombre, categoría y subcategoría, sin antes ni después) |

La marca fiable de copia personalizada es `custom='1'`. El requisito 1 del documento 112 debe esperar 1.204 objetos de catálogo, no 1.304.

## 1. Alcance y principios

El historial responde a una pregunta de jugador: **"¿cambió algo que me importa en un objeto que puedo conseguir o que ya tengo?"**. No es un registro de auditoría (para eso está el log de consola del staff) ni una forma de descubrir lo que el juego aún no ha enseñado.

Principios, por orden de prioridad cuando chocan:

1. **No adelantar lo que el juego guarda en secreto.** El historial nunca es el primer sitio donde un jugador se entera de un objeto aún no publicado, del catálogo del mercado negro, de una receta exclusiva de otro personaje o de un sistema que todavía no existe (fusión y engastes).
2. **Decir la verdad sobre lo que hace el código.** Las etiquetas siguen el comportamiento real, no la ayuda del formulario del staff (que hoy está invertida en dos campos, ver sección 3.3). Si un cambio no afecta a las copias ya fabricadas, el historial lo dice.
3. **Transparencia con quien tiene el objeto.** Si un jugador tiene un objeto en su inventario, tiene derecho a saber que cambió, aunque el objeto no esté en el catálogo público.
4. **Útil, no ruidoso.** Solo entra lo que cambia la experiencia de juego o la ficha visible del objeto. Las copias de jugadores (el 93 % de la tabla) no entran en la vista pública.
5. **Ninguna pista indirecta.** Si todos los campos cambiados de un registro son ocultos para quien mira, la entrada no existe para él. Nunca se muestra "N campos ocultos", ni recuentos que incluyan entradas ocultas.

## 2. Qué entra y qué no

### 2.1 Qué significa `invisible` (deducido del código)

Uso real de la columna en el código:

| Dónde | Qué hace con `invisible` |
|---|---|
| `op/objetos.php` (catálogo público) | Lo **oculta en el navegador** con JavaScript. El servidor envía igualmente la fila completa de los 1.204 objetos de catálogo, incluidos los invisibles, a cualquier visitante (ver hallazgos, sección 11). |
| `op/objetos_unicos.php` | Página solo para staff, en el menú como **"Objetos Únicos"**, que lista exactamente los `invisible=1`. |
| Ayuda del formulario del staff | "Invisible = lo oculta de los listados públicos de objetos sin dejar de funcionar para quien ya lo tiene." |
| PunkRecords (asistente) | Excluye los invisibles y los exclusivos de sus respuestas. |
| `op/tienda.php` | **No mira `invisible`.** Vende todo objeto con `custom='0'`, `exclusivo='0'` y `tier <= 3`, sea o no invisible. |
| `op/crafteo.php` | **No mira `invisible`.** Un invisible con oficio y nivel se puede fabricar. |
| `op/mercado_negro.php` | **No mira `invisible`.** Entra en la rotación todo objeto con `negro=1` y tier 1 a 5. |
| BBCode `[objeto=ID]`, inventario, ficha, intercambios | No miran `invisible`. |

Conclusión: técnicamente `invisible` solo significa **"no aparece en el listado del catálogo"**. No significa "aún no lanzado": un invisible puede estar a la venta en la tienda, fabricarse o salir en el mercado negro. Por el nombre de la página de staff, la intención es "objeto único o especial", pero con 661 objetos (el 55 % del catálogo) es casi seguro que la marca mezcla varios casos: objetos únicos de personaje, recompensas de evento, materiales internos y objetos que solo se consiguen fabricando. **El código no permite saber en qué proporción.** Por eso la regla de visibilidad es una decisión del usuario (U-1, sección 9), con datos de calibración (sección 10).

Mi recomendación (U-1 opción B) no depende de adivinar la intención: usa la **disponibilidad real** del objeto, que sí se puede calcular.

### 2.2 Objeto "en circulación" (regla recomendada para U-1)

Un objeto del catálogo está **en circulación** si un jugador cualquiera puede conseguirlo por una vía normal del juego, según el estado de la fila:

| Vía | Condición en la fila | Pública |
|---|---|---|
| Tienda | `exclusivo=0` y `tier <= 3` | Sí |
| Fabricación abierta | `oficio` no vacío y `crafteo_usuarios` vacío | Sí |
| Mercado negro | `negro=1` y tier entre 1 y 5 | **No**: solo cuenta para quien tiene acceso al mercado negro (ver U-2) |

Un objeto es **público en el historial** si `custom=0`, no es una creación única de personaje (id que empieza por número, sección 2.3) y además:

- es visible en el catálogo (`invisible=0`), **o**
- está en circulación por la tienda o por fabricación abierta.

Para quien tiene acceso al mercado negro, cuenta también la vía del mercado negro.

Con esta regla, un material invisible que cualquier Herrero puede fabricar sí tiene historial público (los Herreros ya lo ven en su pantalla de crafteo), y una recompensa de evento invisible, exclusiva y no fabricable no lo tiene.

### 2.3 Tabla de reglas por tipo de objeto

| Tipo | Cómo se reconoce | Vista pública | Dueño (con sesión) | Staff |
|---|---|---|---|---|
| Catálogo visible | `custom=0`, `invisible=0`, id no empieza por número | **Sí**, con las reglas de campos de la sección 3 | Sí, con "Lo tienes" | Sí |
| Catálogo invisible en circulación | `custom=0`, `invisible=1`, y tienda o fabricación abierta (2.2) | **Sí** (U-1 B) | Sí | Sí |
| Catálogo invisible solo de mercado negro | `custom=0`, `invisible=1`, solo vía mercado negro | No; sí para quien tiene acceso al mercado negro (U-2 A) | Sí | Sí |
| Catálogo invisible fuera de circulación (único, evento, interno) | `custom=0`, `invisible=1`, ninguna vía | **No** | **Sí**, con la etiqueta "Lo tienes · no aparece en el catálogo" | Sí, "Solo staff" |
| Exclusivo (`exclusivo=1`) | Cualquier objeto con `exclusivo=1` | Sigue la fila que le toque por `invisible` y vías. `exclusivo` solo significa "no se vende en la tienda" | Igual | Sí |
| Creación única de personaje | `custom=0` e id que empieza por número (por ejemplo `352MI001`; el número es el UID del dueño) | Solo si `invisible=0`. Si es invisible, no (el id delata al dueño) | Sí | Sí |
| Barco único del staff (`op/barco_unico.php`) | `custom=0`, categoría Transportes | Como cualquier objeto de catálogo | Sí | Sí |
| Copia personalizada | `custom=1` (ids `BASE-uid-n`, o `BASE-n` en barcos) | **No** (D-2 A) | Según U-4 | Sí, "Solo staff" |
| Objeto retirado | id `BORR...`, categoría `borrada` | Si era público antes de retirarse: entrada "Retirado" | Igual | Sí |
| Objeto de evento | No hay columna que lo marque | Se trata por las reglas anteriores (en la práctica, invisible fuera de circulación) | Sí | Sí |

Notas:

- **D-3, parte de `exclusivo`:** de acuerdo con el PM, `exclusivo=1` no oculta nada. Hay 792 exclusivos y muchos son visibles en el catálogo.
- **Prefijos `WAZ` y `MT`:** `listado_objetos.php` los excluye como "no originales", pero en los datos son subcategorías de armas (Wazamono, Especial MT). No hay base en el código para tratarlos distinto: siguen la regla general.

### 2.4 Transiciones de visibilidad

La visibilidad se evalúa con la **instantánea de disponibilidad** guardada en cada registro (antes y después del cambio; ver la petición al PM en la sección 11), no solo con el estado actual.

| Antes del cambio | Después del cambio | Qué ve el público |
|---|---|---|
| Público | Público | Entrada normal con `Antes / Ahora` |
| No público | Público | Entrada **"Nuevo en el catálogo"** con los valores actuales, sin `Antes / Ahora`. Los cambios anteriores, hechos mientras no era público, no se revelan nunca |
| Público | No público | Entrada **"Deja de estar en el catálogo"**, sin valores del estado nuevo |
| No público | No público | Nada (solo staff y dueño) |

Una entrada que fue pública sigue siéndolo aunque el objeto se oculte después: es historia ya contada y retirarla no protege nada.

### 2.5 Qué nunca entra

- Movimientos de inventario (`objetos_ficha.php`, tienda, ventas, intercambios, mercado negro): D-4 A.
- **Cambios hechos por jugadores (D-5): opción A, no entran.** Motivo de juego: craftear, personalizar (apodo e imagen, que viven en `mybb_op_inventario`, no en el objeto) y bautizar barcos **crean copias nuevas**; no modifican el objeto del catálogo. La copia es una foto del objeto base en el momento de crearla. Registrar esas copias en el historial sería un registro de inventario disfrazado y expondría lo que fabrica cada personaje.
- Cambios masivos de id (`mybb_op_cambioid`), en esta fase.

## 3. Grupos de campos

Los nombres de grupo son los que se usan en el filtro "Grupo de campo" y como etiquetas de cada entrada.

### 3.1 Tabla de grupos

| Grupo (etiqueta) | Columnas | Nombre del campo para el jugador | Visibilidad |
|---|---|---|---|
| **Identidad** | `objeto_id` | ID | Pública |
| | `nombre` | Nombre | Pública |
| | `categoria`, `subcategoria` | Categoría, Subcategoría | Pública |
| | `tier` | Tier | Pública (D-6) |
| **Combate y uso** | `dano` | Daño | Pública |
| | `bloqueo` | Bloqueo | Pública |
| | `efecto` | Efecto | Pública |
| | `alcance` | Alcance | Pública |
| | `escalado` | Escalado | Pública |
| | `requisitos` | Requisitos de uso | Pública |
| **Precio y comercio** | `berries` | Valor en berries | Pública (D-6) |
| | `comerciable` | Se puede intercambiar | Pública, **con el valor invertido** (3.3) |
| **Fabricación** | `oficio` | Oficio | Pública |
| | `nivel` | Nivel de oficio | Pública |
| | `berriesCrafteo` | Coste de fabricación | Pública (D-6) |
| | `tiempo_creacion` | Tiempo de fabricación | Pública (en horas y minutos) |
| | `desbloquear` | Puntos para desbloquear la receta | Pública |
| | `crafteo_usuarios` | Quién puede fabricarlo | **Nunca los valores**; solo el texto de la sección 3.4 (D-9) |
| **Inventario** | `espacios` | Espacios | Pública |
| | `cantidadMaxima` | Cantidad máxima | Pública |
| **Presentación** | `descripcion` | Descripción | Pública |
| | `imagen`, `imagen_avatar` | Imagen, Imagen de avatar | Pública (se muestra "Imagen actualizada", sin pintar la imagen anterior) |
| | `imagen_id` | Color de rareza | Pública, como nombre de color (1 gris, 2 verde, 3 azul, 4 morado, 5 dorado, 6 o más arcoíris, otro naranja) |
| | `editable` | Personalizable | Pública ("Se puede personalizar: Sí/No") |
| **Disponibilidad** | `exclusivo` | Se vende en la tienda | Pública, como consecuencia (3.3 y 5.3) |
| | `invisible` | Aparece en el catálogo | Pública solo como transición (2.4); nunca como "Antes 1 / Ahora 0" |
| **Mercado negro** | `negro` | En el mercado negro | Solo con acceso al mercado negro, y staff (U-2) |
| | `negro_berries` | Precio en el mercado negro | Solo con acceso al mercado negro, y staff (U-2) |
| **Reservado** | `fusion_tipo` | Tipo de fusión | **Solo staff** |
| | `engastes` | Engastes | **Solo staff** |

Columnas que no se registran ni se muestran: `id` (interno; sirve para unir el historial tras un renombre, nunca se enseña), `custom` (decide si el objeto es copia, no es un dato de juego), `desbloqueado` y `fecha_creacion` (no se editan en el formulario).

### 3.2 Motivos de las decisiones de visibilidad

- **Combate, precio, fabricación, inventario y presentación son públicos** porque el catálogo, la tienda y la pantalla de crafteo ya los enseñan. Saber cuándo cambiaron no da ventaja nueva y es lo que más interesa (D-1 A).
- **Oficio y nivel son públicos** aunque revelan "quién puede fabricar" en general: es información de carrera (qué oficio conviene subir) y la pantalla de crafteo ya la enseña a quien tiene el oficio. Lo que no se publica es la lista nominal de `crafteo_usuarios`.
- **Mercado negro restringido:** el acceso está limitado en el código a personajes con rango en el inframundo o Contrabandista (Mercader nivel 2 con esa sub). Su catálogo y sus precios son parte del premio de ese acceso. Publicarlos en una página abierta incluso a visitantes regala ese premio.
- **Fusión y engastes solo para staff:** el código de fusión tiene la acción principal sin implementar (`fusion.php`, "TODO: Implementar lógica de fusión") y la ayuda del staff dice que no hay lógica de juego activa que use esos campos. Publicar sus cambios anuncia un sistema que no existe y crea expectativas. Cuando el sistema se lance, el grupo pasa a público (lo decide el usuario en ese momento; no hace falta decidirlo ahora).

### 3.3 Etiquetas que siguen al código, no a la ayuda del staff

| Campo | Lo que dice la ayuda del formulario | Lo que hace el código | Etiqueta del historial |
|---|---|---|---|
| `comerciable` | "En 1, el objeto se comercia por la vía normal" | `intercambio.php` y `barco_cofre.php` solo dejan comerciar con `comerciable=0`; `mercado_negro.php` rechaza el 1 con "Este objeto no es comerciable" | `0` = "Se puede intercambiar: Sí"; `1` = "Se puede intercambiar: No" |
| `exclusivo` | "Solo se consigue comprándolo, no se puede craftear" | `tienda.php` **solo vende** los `exclusivo=0`; `crafteo.php` no mira el campo | `0` = "Se vende en la tienda" (si además tier ≤ 3); `1` = "No se vende en la tienda" |

El jugador nunca ve los valores 0 y 1, solo la frase.

### 3.4 Texto público de `crafteo_usuarios` (D-9 A, concretada)

| Antes | Después | Texto público | Texto para el personaje afectado (opcional, mejora) |
|---|---|---|---|
| vacía | con UID | "La receta pasa a ser exclusiva: solo pueden fabricarlo personajes autorizados." | "Ahora puedes fabricar este objeto." |
| con UID | vacía | "La receta se abre: puede fabricarlo cualquier personaje con el oficio y nivel indicados." | |
| con UID | con otros UID | Nada en la vista pública (solo staff). Revela una concesión a un jugador concreto y no sirve a nadie más | "Ahora puedes fabricar este objeto." o "Ya no puedes fabricar este objeto.", si su UID entró o salió |

Nunca se muestran UID, nombres de personaje ni el número de personajes autorizados (un "1 personaje" invita a buscar al dueño).

## 4. Texto de ejemplo por tipo de entrada

Tono: informativo, de tablón del foro, frases cortas, sin jerga técnica. Los números con punto de miles. Los valores de texto largo (efecto, descripción) se comparan en bloque.

**Nuevo objeto** (alta de un objeto ya público, o publicación de uno que no lo era):
> **Nuevo en el catálogo: Katana Shirasagi** (`KAT014`) · Armas / Armas de Filo · Tier 2
> Llega al catálogo con estos valores: Daño 14+[FUEx1] de [Daño cortante], Valor 6.000 B, 1 espacio. La fabrican Herreros de nivel 2.

**Actualizado** (título según el grupo de mayor peso cambiado, en este orden: Combate y uso, Precio y comercio, Fabricación, Disponibilidad, Inventario, Presentación):
> **Ajuste de combate: Katana Kiku** (`KAT001`) · Tier 2 · *Combate y uso · Precio y comercio*
> Daño: 12+[FUEx1] → 14+[FUEx1]
> Valor en berries: 5.000 B → 6.000 B (+20 %)

Otros títulos: "Cambio de precio: ...", "Cambio en la fabricación: ...", "Cambio de disponibilidad: ...", "Cambio de inventario: ...", "Retoque de presentación: ...".

**Renombrado:**
> **Renombrado: Katana Kiku** · `KAT001` → `KATF001`
> Mismo objeto con un ID nuevo. Tu inventario se actualizó solo; el historial anterior sigue unido a este objeto.

**Retirado:**
> **Retirado del catálogo: Katana Kiku** (`KAT001`)
> Este objeto ya no forma parte del catálogo.

**Deja de estar en el catálogo** (pasa de público a no público, 2.4):
> **Deja de estar en el catálogo: Bomba de Humo** (`BHU001`)
> Ya no se puede conseguir por las vías habituales. Si lo tienes, sigue funcionando.

**Registro antiguo** (sección 6):
> **Registro antiguo: Katana Kiku** (`KAT001`) · 14 mar 2024
> El objeto se actualizó, pero en esa fecha no se guardaba qué cambió. Estado tras el cambio: Armas / Armas de Filo.

**Marcas en la entrada:**
- "Lo tienes": el personaje tiene el objeto tal cual.
- "Tienes una copia": el personaje tiene una copia de este objeto (sección 5.4).
- "Lo tienes · no aparece en el catálogo": entrada oculta al público que ve por tenerlo.
- "Mercado negro": campos que solo ve quien tiene acceso.
- "Solo staff".

## 5. Reglas sobre precio, tier, mercado negro y copias

### 5.1 Precio (D-6, resuelta como B ampliada)

- **`berries`, `berriesCrafteo` y `tier` son públicos.** Ya los enseñan el catálogo, la tienda y el crafteo.
- `berries` se etiqueta "Valor en berries", no "precio de tienda": es el precio de la tienda solo si el objeto se vende allí, y además es la base de lo que paga la tienda cuando el jugador vende (`vender.php`). Bajo el valor se añade, una vez por página, la nota: "La tienda puede aplicar recargos por defectos del personaje."
- **Variación porcentual** en `berries`, `berriesCrafteo` y `negro_berries`: `(nuevo − anterior) / anterior × 100`, redondeado a entero, con signo (`+20 %`, `−15 %`). Si el valor anterior era 0, no se calcula el porcentaje y se escribe "antes sin valor".
- `negro_berries = 9.999.999` (valor por defecto de la tabla) se muestra como "Sin precio fijado".
- `tiempo_creacion` (segundos) se muestra en horas y minutos redondeados al minuto (10.000 s = "2 h 47 min").

### 5.2 Tier

El tier se muestra como cualquier campo (`Tier 3 → Tier 4`). Como el tier decide disponibilidad en tres sitios del código, la entrada añade las consecuencias de la sección 5.3.

### 5.3 Consecuencias de disponibilidad (líneas derivadas)

Se calculan comparando la instantánea antes y después. Se muestran bajo el título "Qué cambia para ti", sin valores 0 y 1:

| Condición calculada | Pasa de no a sí | Pasa de sí a no | Quién la ve |
|---|---|---|---|
| Se vende en la tienda: `custom=0`, `exclusivo=0`, `tier <= 3` | "Ahora se vende en la tienda." | "Deja de venderse en la tienda." | Público |
| Se puede intercambiar: `comerciable=0` y `tier <= 5` | "Ahora se puede intercambiar." | "Ya no se puede intercambiar." | Público |
| Fabricación abierta: `oficio` no vacío y `crafteo_usuarios` vacío | "Ahora puede fabricarlo cualquier personaje con el oficio indicado." | "Ya no se puede fabricar libremente." | Público |
| En el mercado negro: `negro=1`, tier 1 a 5 | "Entra en el mercado negro." | "Sale del mercado negro." | Solo con acceso (U-2) |

### 5.4 Copias ya fabricadas (D-7, resuelta como A con aviso)

Hecho del código: **un cambio en el objeto base no modifica las copias existentes.** `crafteo.php`, `inventario.php`, `personaje.php` y `barco_bautizar.php` copian daño, efecto, bloqueo, alcance, valor y el resto de campos en una fila nueva. La venta a la tienda de una copia usa el valor de la copia, no el de la base.

Regla:

1. En `Mis objetos`, una copia cuenta como su objeto base (opción A de D-7): quien tiene `KAT001-261-3` ve los cambios de `KAT001`, con la marca "Tienes una copia".
2. Esa entrada añade siempre el aviso: **"Tu copia `KAT001-261-3` conserva los valores con los que se fabricó. Este cambio no la modifica."** Si la copia se fabricó después del cambio (su `fecha_creacion` es posterior a la fecha del registro), el aviso es: "Tu copia ya se fabricó con estos valores." (**Provisional**: depende de que `fecha_creacion` sea fiable en las copias, ver sección 10.)
3. La base de una copia se obtiene del prefijo del id **siguiendo los renombres**: si `KAT001` se renombró a `KATF001`, la copia `KAT001-261-3` (que conserva el prefijo viejo) cuenta como copia de `KATF001`. Sin esto, una copia pierde su historial tras cualquier renombre de la base.
4. Si el usuario decide otra política para las copias (U-3), este aviso cambia en consecuencia.

### 5.5 Mercado negro

Ver U-2. Con la opción recomendada, los campos `negro` y `negro_berries` y la línea "Entra/Sale del mercado negro" aparecen solo a quien pasa el mismo control de acceso que `op/mercado_negro.php` (rango en el inframundo distinto de "Sin rango", o Contrabandista de nivel 1 o más) y al staff. Si un registro solo cambia esos campos, no existe para el público.

## 6. Tratamiento del pasado

Los 430 registros antiguos no tienen antes ni después y no se pueden reconstruir (de acuerdo con D-12 A).

| Formato antiguo | Cantidad | Cómo se muestra |
|---|---|---|
| `Nuevo objeto creado de objeto ID X ...` (`objetos_crear.php`) | 133 | "Registro antiguo · Alta" con los valores iniciales que conserva el log, sin `calidad` ni `unidades` |
| `Objeto X (creado por Y): ...` | 49 | "Registro antiguo · Alta" con nombre, categoría y subcategoría |
| `Objeto X (modificado por Y): ...` | 248 | "Registro antiguo · Actualización", "Estado tras el cambio" con nombre, categoría y subcategoría |

Reglas:

1. **Visibilidad por estado actual.** El log antiguo no guarda si el objeto era invisible ni si estaba en circulación, así que se aplica la regla de la sección 2 al estado actual del objeto. Es la opción segura: si hoy no es público, su pasado tampoco.
2. **Objeto que ya no existe con ese id** (renombrado sin rastro, o borrado): solo staff. No se puede saber si era público.
3. **Copias:** si el id es una copia (`custom=1` hoy, o patrón `BASE-uid-n` o `BASE-n` si la fila ya no existe), solo staff.
4. **Retiradas antiguas:** el log de retirada de `objetos_crear.php` dice "Remover técnica ID ...", el mismo texto que podría usar una técnica. No se interpreta como objeto en la vista pública (solo staff), porque mostrar por error una técnica como objeto retirado confunde más de lo que informa.
5. Nunca se muestra `razon` (en `objetos_modificar.php` es la palabra fija "Apertura").
6. La página incluye, una sola vez, el aviso: "Antes del [fecha de despliegue del registro nuevo] no se guardaba qué cambiaba en cada objeto. Esos registros aparecen como Registro antiguo."
7. Con el periodo inicial de 90 días, los registros antiguos no aparecen salvo con `periodo=todo`. Es correcto: son contexto, no novedad.

## 7. Ejemplos resueltos

Cuadro para el QA. "Público" es un visitante sin sesión; "MN" es un usuario con acceso al mercado negro.

**E-1. Katana visible: daño de 12 a 14 y valor de 5.000 a 6.000.** `KAT001`, `invisible=0`, `exclusivo=0`, tier 2.
- Público: entrada "Ajuste de combate: Katana Kiku", etiquetas *Combate y uso* y *Precio y comercio*. Filas: "Daño: 12 → 14" y "Valor en berries: 5.000 B → 6.000 B (+20 %)". Sin líneas de disponibilidad (el tier no cambia y sigue en la tienda).
- Usuario con 2 `KAT001`: además "Lo tienes".
- Usuario con la copia `KAT001-261-3` fabricada antes: "Tienes una copia" y "Tu copia `KAT001-261-3` conserva los valores con los que se fabricó. Este cambio no la modifica."

**E-2. Tier de 3 a 4 en un objeto no exclusivo.** `BOT010`, `exclusivo=0`, `comerciable=0`.
- Público: "Tier 3 → Tier 4" y "Qué cambia para ti: Deja de venderse en la tienda." (sigue intercambiable porque 4 ≤ 5).

**E-3. Solo entra en el mercado negro.** `negro` 0 → 1, `negro_berries` 9.999.999 → 250.000, tier 2.
- Público: no hay entrada.
- MN: "Cambio de disponibilidad", etiqueta *Mercado negro*: "En el mercado negro: No → Sí", "Precio en el mercado negro: Sin precio fijado → 250.000 B" y "Entra en el mercado negro."
- Staff: igual que MN, más el enlace al editor.

**E-4. Mismo guardado que E-3, pero también sube el daño.**
- Público: entrada "Ajuste de combate" solo con la fila de daño. No se indica que hubo más cambios.
- MN: la entrada completa.

**E-5. Receta que pasa a exclusiva.** `crafteo_usuarios` vacío → `352`.
- Público: etiqueta *Fabricación*, "La receta pasa a ser exclusiva: solo pueden fabricarlo personajes autorizados." y "Qué cambia para ti: Ya no se puede fabricar libremente."
- Usuario 352 (si se implementa la mejora): "Ahora puedes fabricar este objeto."
- Si después cambia de `352` a `352,1044`: el público no ve nada; el 1044 ve "Ahora puedes fabricar este objeto."

**E-6. Objeto invisible de evento, editado tres veces y publicado.** `EVT020`, `invisible=1`, `exclusivo=1`, sin oficio, `negro=0`. Tres guardados cambian su efecto; el cuarto pone `invisible=0`.
- Público: nada en los tres primeros. En el cuarto, "Nuevo en el catálogo: ..." con los valores actuales, sin `Antes / Ahora`.
- Usuario que ya lo tenía (recompensa del evento): ve los tres primeros con "Lo tienes · no aparece en el catálogo".
- Staff: los cuatro, los tres primeros con "Solo staff".

**E-7. Material invisible fabricable por cualquier Herrero (con U-1 B).** `MAT050`, `invisible=1`, `oficio=Herrero`, `crafteo_usuarios` vacío. Sube `tiempo_creacion` de 3.600 a 7.200.
- Público (U-1 B): "Cambio en la fabricación", "Tiempo de fabricación: 1 h → 2 h".
- Público (U-1 A): nada.

**E-8. Objeto invisible a la venta en la tienda (con U-1 B).** `invisible=1`, `exclusivo=0`, tier 1. Baja el valor de 800 a 600.
- Público (U-1 B): "Cambio de precio", "Valor en berries: 800 B → 600 B (−25 %)". Cualquier jugador ya lo ve en la tienda.

**E-9. Renombre.** `KAT001` → `KATF001`.
- Público: "Renombrado: Katana Kiku · `KAT001` → `KATF001`". Buscar `KAT001` o `KATF001` devuelve E-1 y esta entrada. La copia `KAT001-261-3` sigue contando como copia de `KATF001` en `Mis objetos`.

**E-10. Staff edita una copia de jugador.** `ROPA004-261-4`, baja el bloqueo.
- Público: nada.
- Dueño (UID 261): según U-4 (recomendado: "Cambio del staff en tu objeto" con `Antes / Ahora`).
- Staff: entrada con "Solo staff".

**E-11. Solo cambia `fusion_tipo` y `engastes`.**
- Público y MN: nada. Staff: entrada "Solo staff", etiqueta *Reservado*.

**E-12. `comerciable` de 0 a 1.**
- Público: etiqueta *Precio y comercio*, "Se puede intercambiar: Sí → No" y "Qué cambia para ti: Ya no se puede intercambiar."

**E-13. Creación única de personaje.** `352MI001`, `invisible=1`, `crafteo_usuarios=352`. Se le sube el daño.
- Público: nada (aunque fuera fabricable, el id delata al dueño y la receta es exclusiva).
- Usuario 352, que lo tiene: entrada con "Lo tienes · no aparece en el catálogo".

**E-14. Guardar el formulario sin cambios.** No genera registro (requisito 38).

**E-15. Registro antiguo de una copia.** `Objeto KAT001-261-4 (modificado por X): ...`. Solo staff.

## 8. Riesgos y abusos previstos

| Riesgo | Cómo se aprovecharía | Qué lo impide |
|---|---|---|
| Descubrir objetos sin publicar | Mirar el historial para ver qué prepara el staff | Regla 2.4: lo no público no deja rastro, y la publicación no revela cambios previos. **Pero** hoy el catálogo envía todos los invisibles al navegador (sección 11): sin corregirlo, la regla del historial protege poco |
| Destapar el mercado negro | Leer `negro` y `negro_berries` sin tener acceso | Grupo *Mercado negro* restringido (U-2); entradas que solo cambian esos campos no existen para el público |
| Deducir campos ocultos por huecos | "Esta entrada tiene 2 campos visibles y 3 ocultos" | Nunca se cuentan ni se anuncian campos ocultos. Los recuentos del resumen y los resultados de búsqueda se calculan después del filtro de visibilidad |
| Buscar al dueño de una receta exclusiva | Ver qué UID o cuántos personajes se añadieron | Sin valores ni recuentos (3.4); los cambios entre listas no vacías no se publican |
| Identificar dueños por el id | El id `352MI001` lleva el UID 352 | Las creaciones únicas invisibles no son públicas (E-13) |
| Anclajes `#cambio-ID` consecutivos | Ver huecos en la numeración y suponer cambios ocultos | Riesgo bajo: el log de consola mezcla objetos, técnicas y otras herramientas, así que los huecos son normales. Aceptado |
| Publicación accidental | El staff pone `invisible=0` para probar y vuelve a 1 | Genera "Nuevo en el catálogo" y luego "Deja de estar en el catálogo", y no se pueden borrar. Recomendación al staff: preparar los objetos con `invisible=1` y publicar una sola vez. Se menciona en la guía del staff |
| Acaparar antes de un ajuste | Comprar o fabricar mucho de un objeto antes de que se nerfee, porque las copias conservan sus valores | El historial es posterior al cambio: no adelanta nada. El riesgo existe hoy por el diseño de las copias (U-3), no por el historial |
| Especular con el valor de venta | Tras ver una subida de `berries`, vender existencias del objeto base a la tienda | La subida ya es pública en el catálogo; el historial solo la fecha. Aceptado |
| Ruido de guardados en ráfaga | El staff guarda el mismo objeto cinco veces en diez minutos | Se muestran todas (cada una es un cambio real). Mejora opcional para el diseño: agrupar visualmente guardados del mismo autor y objeto en menos de 30 minutos |
| HTML en `efecto` o `descripcion` | Texto con etiquetas que se pinta como HTML | Todo se escapa (requisito 60) |

## 9. Decisiones que siguen siendo del usuario

**U-1. Qué hacer con los objetos `invisible=1` del catálogo (661, el 55 %).** Revisa D-3.
- **A.** Ocultar todos los cambios de objetos invisibles (propuesta original del PM). Simple y máximo misterio, pero oculta más de la mitad del catálogo, incluidos objetos que se venden en la tienda o que cualquier Herrero fabrica, y deja sin historial a quien los usa a diario.
- **B.** Ocultar solo los invisibles **fuera de circulación**: público si es visible o si se consigue en la tienda o por fabricación abierta (sección 2.2); el mercado negro cuenta solo para quien tiene acceso. Los dueños ven los cambios de lo que tienen.
- **C.** Mostrar todos. Coherente con que hoy el catálogo ya los envía al navegador, pero renuncia al misterio de objetos únicos y de evento.
- **Recomendación: B.** No depende de adivinar para qué marcó el staff cada objeto como invisible: usa lo que el juego ya deja conseguir. Antes de cerrar, conviene ver los datos de calibración 10.1 y 10.2: si casi todos los invisibles están fuera de circulación, A y B dan casi lo mismo y A es más simple.

**U-2. Campos del mercado negro (`negro`, `negro_berries`).** Revisa D-6.
- **A.** Ocultos al público; visibles para quien tiene acceso al mercado negro y para el staff.
- **B.** Solo staff.
- **C.** Públicos como cualquier campo.
- **Recomendación: A.** Respeta el acceso que el juego ya exige y sigue siendo útil para quien comercia allí. Coste: el historial reutiliza la comprobación de acceso de `mercado_negro.php`.

**U-3. Política de juego para las copias ya fabricadas cuando cambia el objeto base.** Revisa D-7. Hoy el código no las toca nunca.
- **A.** Las copias conservan sus valores (como hoy). El historial lo dice en cada entrada (5.4).
- **B.** En los ajustes de equilibrio, el staff ajusta a mano las copias afectadas. El historial lo registraría como cambio de copia (solo staff y dueño, U-4).
- **C.** Los ajustes de combate de la base se propagan solos a las copias. Es una funcionalidad nueva, fuera del alcance de 112.
- **Recomendación: A** para este historial, porque describe la realidad. Aviso de equilibrio: con A, fabricar antes de un nerf deja copias más fuertes para siempre. Si eso preocupa, es tema para otra funcionalidad, no para esta.

**U-4. Cambios del staff en la copia de un jugador.** Revisa D-2.
- **A.** Solo staff.
- **C.** Visibles para el dueño de la copia (en todas las vistas, con la marca "Cambio del staff en tu objeto") y para el staff. Nunca para el público.
- **Recomendación: C.** Un ajuste del staff en el arma de un personaje cambia su juego, y su dueño debería poder verlo sin preguntar. El coste es bajo: el inventario del usuario ya se consulta para "Lo tienes". Si el desarrollo lo complica, A en la primera fase y C después.

**U-5. La fuga de datos del catálogo.** Hallazgo de la sección 11, no del historial.
- **A.** Corregirla dentro de esta funcionalidad, como primera issue.
- **B.** Issue aparte, antes de publicar el historial.
- **C.** Aceptarla y elegir C en U-1.
- **Recomendación: B.** Sin corregirla, ocultar invisibles, recetas exclusivas y mercado negro en el historial es solo apariencia: cualquiera los lee en el código fuente de `op/objetos.php`.

## 10. Calibración (lo provisional y el dato que lo confirma)

Consultas de solo lectura en producción. Las ejecuta el usuario o una persona con acceso; el Game Designer no.

1. **Invisibles por vía de obtención.** De los 661 `custom=0, invisible=1`: cuántos están a la venta (`exclusivo=0` y tier ≤ 3), cuántos con fabricación abierta (`oficio` no vacío y `crafteo_usuarios` vacío), cuántos con receta exclusiva (`crafteo_usuarios` no vacío), cuántos en el mercado negro (`negro=1`, tier 1 a 5) y cuántos sin ninguna vía. Decide U-1.
2. **Invisibles por categoría y subcategoría**, y cuántos tienen id que empieza por número (creaciones únicas). Confirma qué significa "invisible" en la práctica.
3. **Invisibles en inventarios:** cuántos de los 661 tiene al menos un personaje, y cuántos personajes distintos. Mide cuántas entradas verán los dueños con la excepción "Lo tienes".
4. **`fecha_creacion` de las copias:** cuántas copias `custom=1` tienen una fecha anterior a 2024 o igual para muchas filas (señal de migración). Confirma la regla 5.4.2.
5. **Copias de barcos:** cuántas copias `custom=1` tienen id `BASE-n` (sin UID). Confirma el patrón de la sección 6.3.
6. **Registros antiguos sobre copias:** de los 248 "modificado por", cuántos son de copias. Mide cuántos quedan para el público.
7. **Retiradas antiguas:** cuántos registros "Remover técnica ID ..." corresponden a ids que existen como `BORR...` en `mybb_op_objetos`. Decide si la regla 6.4 puede relajarse.

Valores provisionales: la condición "en circulación" (2.2), el aviso de fecha de las copias (5.4.2) y la ventana de agrupación de 30 minutos (sección 8, opcional).

## 11. Hallazgos que cambian el alcance de los requisitos 112

1. **El catálogo público ya filtra todo.** `op/objetos.php` hace `SELECT *` de los 1.204 objetos de catálogo y envía la fila completa al navegador, incluidos los 661 invisibles y las columnas `crafteo_usuarios`, `negro`, `negro_berries`, `fusion_tipo` y `engastes`. El filtro de invisibles es solo JavaScript. `op/armas_todas.php` y `op/objetos_armas_todas.php` devuelven JSON público de todas las armas (con invisibles, y la segunda también con todas las copias), sin sesión. Afecta a D-1, D-3, D-9 y a la justificación de la sección 3 del documento 112 ("no expone nada nuevo"): hoy no hay nada oculto de verdad. Ver U-5.
2. **`invisible` no significa "no lanzado".** La tienda, el crafteo y el mercado negro ignoran la marca (sección 2.1). La recomendación D-3 A del PM ocultaría objetos que los jugadores compran y fabrican.
3. **La ayuda del formulario del staff está invertida en dos campos** (`comerciable` y `exclusivo`, sección 3.3). El historial debe etiquetar según el código. Conviene corregir esa ayuda en otra issue, porque el staff puede estar marcando objetos con la semántica equivocada.
4. **Otra ruta del staff escribe en el catálogo sin registro:** `op/barco_unico.php` crea y edita barcos de catálogo (`custom=0`: nombre, imagen, descripción, oficio, nivel, coste de fabricación y `crafteo_usuarios`) y no deja ningún log. El requisito 35 debe incluirla, o el historial de barcos tendrá huecos.
5. **El payload necesita una instantánea de disponibilidad.** Para aplicar las secciones 2.4 y 5.3, cada registro nuevo debe guardar, antes y después del cambio y aunque no hayan cambiado: `invisible`, `exclusivo`, `tier`, `comerciable`, `negro`, `custom`, si `oficio` está vacío y si `crafteo_usuarios` está vacío. Amplía el requisito 40, que solo pedía `custom` e `invisible`.
6. **Las copias conservan el prefijo viejo tras un renombre.** `objetos_modificar.php` actualiza el inventario del id exacto, pero las copias `KAT001-uid-n` siguen con `KAT001`. El cálculo de la base de una copia en `Mis objetos` debe seguir la cadena de renombres (5.4.3). Afecta al requisito 7.
7. **`requisitos` son requisitos de uso, no de fabricación.** En los datos reales es "Esta arma tiene como requisitos tener 75 de [Fuerza]...". `docs/agents/DATABASE.md` lo describe mal ("Craft material requirements"). El grupo correcto es *Combate y uso*.
8. **Fusión y engastes no tienen mecánica activa** (`fusion.php` con la acción principal por hacer). Por eso quedan como grupo *Reservado*, solo staff.
9. Fuera de alcance, para el PM: en `intercambio.php` la excepción de Akuma no Mi compara la subcategoría en minúsculas con `'AKUMA NO MI'` en mayúsculas y nunca se cumple. Las Akumas de tier mayor que 5 no se pueden intercambiar aunque el código lo intenta permitir.
