# Documentación de op/crafteo.php

Documento de estudio. Describe qué hace [op/crafteo.php](../op/crafteo.php) (1322 líneas), cómo está organizado, qué fórmulas aplica y qué detalles conviene tener presentes. Los números de línea corresponden a la versión leída al escribir este documento.

Lo que NO se revisó: las plantillas (`op_crafteo`, `op_crafteo_en_curso`, `op_crafteo_completo`), el JS que llama a este archivo, y el contenido real de las tablas. Todo lo que dice este documento sale de leer el PHP.

---

## 1. Qué es este archivo

Es a la vez:

1. **Una página** (`/op/crafteo.php`): muestra el panel de crafteo del jugador (recetas disponibles, materiales, NPCs, crafteos en curso).
2. **Un endpoint AJAX**: casi todas las acciones llegan por `POST` con un campo `accion` y se contestan con JSON.

No hay clases ni archivos auxiliares propios. Todo vive en un solo script procedural, con funciones declaradas a mitad de camino (en PHP las funciones de nivel superior se pueden usar antes de su declaración, pero las que están dentro de un `if` solo existen cuando ese bloque se ejecuta, ver sección 8).

Es el sistema que produce los objetos de los oficios: el jugador paga Berries, espera un tiempo, reclama y recibe una **copia propia** del objeto.

---

## 2. Mapa del archivo

| Líneas | Bloque | Qué hace |
|---|---|---|
| 11-17 | Depuración | Activa `display_errors` y manda el log a `./php-error.log`. Marcado como temporal. |
| 19-23 | Bootstrap | Define `IN_MYBB`, `THIS_SCRIPT` (copiado de viajes.php), incluye `global.php`, `config.php` y `op_functions.php`. |
| 25-29 | Variables base | `$uid`, `$username`, `$ficha`. |
| 31-45 | Redirecciones de seguridad | Invitado (uid 0) y personaje muerto (`$g_ficha['muerto']`). |
| 47-62 | Modo vista | Permite ver la página de otro usuario. |
| 64-84 | Ficha y oficio | Carga la ficha, exige que esté aprobada y que tenga al menos un oficio. |
| 86-103 | Tabla de NPCs | `CREATE TABLE IF NOT EXISTS mybb_op_crafteo_npcs` en cada carga. |
| 105-125 | Virtudes y defectos | Detecta `D024`, `V035`, `V036`, `V028`. Bloquea a "Sin Oficio". |
| 127-245 | Inventario | Funciones `darObjeto()` y `quitarObjeto()`. |
| 247-261 | Entradas y datos de ficha | `$accion`, `$objetoIdPost`, Berries, Nikas, puntos, nivel, `$oficios`. |
| 264-389 | Helpers de descuentos | `getOficiosNiveles`, `getDescuento`, `getTiempoCreacion`, `getBerriesCosto`, virtudes, Recolector y Mayorista. |
| 391-454 | Salas de barco | `_getBarcoSalas`, `getBarcoSalaTimeMult`, `getBarcoSalaQuantityMult`. |
| 456-518 | Condiciones SQL de recetas | Construye los trozos de `WHERE` según oficios y especializaciones. |
| 520-538 | Acción `cancelar` | Cancela crafteos del jugador y devuelve Berries. |
| 540-560 | Acción `cancelarNpc` | Igual para un NPC. |
| 562-797 | Acción `craftear` | Inicia un crafteo (rama jugador y rama NPC). |
| 799-906 | Acción `reclamar` | Entrega los objetos terminados. |
| 908-925 | Acción `desbloquear` | Compra una receta con Puntos de Oficio. |
| 927-971 | Acción `desbloquearReceta` | Compra una receta gastando un objeto "Receta de Oficio". |
| 973-1049 | Acción `get_objects_for_actor` | Lista las recetas que puede craftear el jugador o un NPC. |
| 1051-1189 | Carga de datos para la página | Recetas, materiales, recetas desbloqueadas, NPCs, JSON para el front. |
| 1191-1201 | Estado de crafteos activos | Banderas para el front. |
| 1203-1321 | Render | Elige entre tres plantillas y cierra con `output_page()`. |

---

## 3. Flujo de una petición

```
Entra la petición
  -> global.php (sesión de MyBB)
  -> invitado? redirige.  muerto? redirige.
  -> modo_vista (staff mira a otro usuario)
  -> carga ficha de $uid. Sin aprobar? redirige. Sin oficios? redirige.
  -> crea tabla de crafteos NPC si no existe
  -> lee virtudes/defectos. "Sin Oficio"? redirige.
  -> define funciones (inventario, descuentos, salas)
  -> construye condiciones SQL de recetas
  -> if/if/if sobre $_POST['accion']  (cada acción es un bloque independiente)
        cancelar | cancelarNpc | craftear | reclamar | desbloquear
        | desbloquearReceta | get_objects_for_actor
  -> si ninguna acción terminó la petición con exit/return:
        consulta recetas, materiales, NPCs, recetas desbloqueadas
        decide plantilla (en curso / completo / normal)
        output_page()
```

Punto clave: **las acciones no son un `switch`**. Son `if` consecutivos. Las que terminan con `exit` o `return` cortan la petición (`craftear` con éxito, `reclamar`, `desbloquearReceta`, `get_objects_for_actor`). Las que no (`cancelar`, `cancelarNpc`, `desbloquear`, y `craftear` cuando no alcanzan los Berries) **siguen de largo y dibujan la página completa**.

---

## 4. Modo vista

Líneas 52-61.

```php
$modo_vista = ($modo_vista_input && ($g_is_staff) || $mybb->user['uid'] == $modo_vista_input);
```

Por precedencia de operadores equivale a `(input && staff) || (uid_propio == input)`. Es decir: el staff puede ver a cualquiera, y cualquier usuario puede "verse a sí mismo". Si `$modo_vista` es verdadero, `$uid` pasa a ser el del `modo_vista`, y desde ahí **todo el archivo opera sobre ese uid** (ficha, Berries, crafteos).

Consecuencias:
- Solo se lee `modo_vista` con `get_input` (sirve para GET y POST), así que las acciones POST también se ejecutarían sobre el usuario observado.
- En la parte de render, `$en_curso && !$modo_vista` evita mostrar la pantalla "en curso" al staff que observa.
- El comentario del código explica que debe ir antes de cargar la ficha, porque antes se resolvía tarde y nunca mostraba la ficha ajena.

---

## 5. Datos de la ficha que usa

Del registro de `mybb_op_fichas`:

| Campo | Uso |
|---|---|
| `aprobada_por` | Debe no ser `sin_aprobar` ni `pendiente_reset`. |
| `oficio1`, `oficio2` | Oficios principales. Al menos uno debe existir. |
| `oficios` (JSON) | Estructura de oficios, niveles y especializaciones. |
| `berries` | Moneda que se gasta al craftear. |
| `puntos_oficio` | Moneda que se gasta al desbloquear recetas. |
| `nika`, `nivel` | Se leen a variables pero este archivo no los usa después. |

Forma del JSON `oficios` (deducida del código):

```json
{
  "Herrero": {...},
  "Artesano": {
    "nivel": 2,
    "espe1": "Herrero",
    "espe2": "Modista",
    "sub": { "Herrero": 1, "Modista": 3 }
  },
  "Recolector": { "nivel": 1, "sub": {} }
}
```

- `nivel`: rango del oficio base (1 o 2).
- `espe1`, `espe2`: nombres de las especializaciones elegidas.
- `sub`: mapa especialización a rango (1 a 3).

`getOficiosNiveles()` (línea 271) aplana esto en un mapa plano `nombre => nivel`, donde conviven oficios base y especializaciones, por ejemplo `['Artesano'=>2, 'Herrero'=>1, 'Modista'=>3]`. Casi todos los descuentos se calculan a partir de este mapa.

---

## 6. Virtudes y defectos relevantes

Una sola consulta a `mybb_op_virtudes_usuarios` (línea 110).

| ID | Variable | Efecto en este archivo |
|---|---|---|
| `D024` | `$has_sin_oficio` | Bloquea toda la página con mensaje de redirección. |
| `V035` | `$has_estudioso` | Multiplica el tiempo de crafteo por 0.8 (sección 8.4). |
| `V036` | `$has_polivalente` | Se lee pero **no se usa** después. |
| `V028` | `$has_erudito` | Multiplica el tiempo de crafteo por 0.8 (sección 8.4). |

Estudioso y Erudito dan el mismo -20% y no se acumulan.

---

## 7. Inventario: darObjeto y quitarObjeto

### `darObjeto($objeto_id, $uid, $username, $oficios_json, $crear_copia, $efecto_mult)` (línea 140)

Dos modos según `$crear_copia`.

**Modo copia (`true`), usado al reclamar un crafteo.**
1. Lee el objeto base de `mybb_op_objetos` y cuenta cuántas copias tiene ya ese usuario con `objeto_id LIKE 'BASE-UID%'`.
2. Genera el nuevo id: `{base}-{uid}-{contador}` (por ejemplo `Sword-123-1`).
3. Si `$efecto_mult > 1` y la subcategoría es `plato`, multiplica **todos los números del campo `efecto`** con una expresión regular (enteros se redondean a entero, decimales a 2 decimales). Es el bonus de la sala Cocina.
4. Inserta una fila en `mybb_op_inventario` con `autor`, `autor_uid`, `oficios` (el JSON de oficios de quien lo creó) y `especial=1`.
5. Inserta una fila nueva en `mybb_op_objetos` con la copia, marcada `custom=1`, `exclusivo=1`, `invisible=1`, `editable=1`, `negro=1`.
6. Si `bloqueo` o `alcance` quedan vacíos, anota un aviso en `mybb_op_audit_crafteo`.

**Modo estándar (`false`), usado para devolver materiales.**
1. Si el usuario ya tiene el objeto, suma 1 a `cantidad`. Si no, inserta una fila.
2. Calcula el JSON de oficios solo para la familia del objeto (con `oficioFamilia()`, definida en `op_functions.php`), con su nivel y sus subs.
3. Solo escribe autor/oficios/especial si estaban vacíos.

### `quitarObjeto($objeto_id)` (línea 229)

Resta 1 a `cantidad` si hay 2 o más. Si hay 1, borra la fila. Siempre opera sobre el `$uid` global.

---

## 8. Descuentos y multiplicadores

### 8.1 `getDescuento($nivel)` (línea 294)

Tabla base compartida por especializaciones:

| Rango | Multiplicador | Equivale a |
|---|---|---|
| 1 | 0.95 | -5% |
| 2 | 0.90 | -10% |
| 3 | 0.80 | -20% |
| otro | 1.0 | sin efecto |

### 8.2 Tiempo: `getTiempoCreacion($oficio)` (línea 306)

Recibe el oficio **de la receta** y busca la especialización que da el descuento de tiempo para esa familia:

| Receta de | Especialización que da tiempo |
|---|---|
| Cocinero, Chef, Aprovisionador | Chef |
| Artesano, Herrero, Modista | Herrero |
| Médico, Doctor, Farmacólogo | Doctor |
| Carpintero, Astillero, Constructor | Constructor |
| Inventor, Ingeniero, Biólogo | Ingeniero |

Otros oficios (Navegante, Investigador, Mercader, Aventurero, Recolector y sus ramas) no están en el mapa: devuelven 1.0.

### 8.3 Coste: `getBerriesCosto($oficio)` (línea 319)

| Receta de | Especialización que da coste |
|---|---|
| Cocinero, Chef, Aprovisionador | Aprovisionador |
| Artesano, Herrero, Modista | Modista |
| Médico, Doctor, Farmacólogo | Farmacólogo |
| Carpintero, Astillero, Constructor | Astillero |
| Inventor, Ingeniero, Biólogo | Biólogo |

Importante: el descuento depende de la **familia de la receta**, no de la rama. Un Herrero R3 que craftee una receta de Cocinero (si pudiera) no recibe nada, y un Herrero con Modista R3 abarata también las recetas de armas (Herrero).

### 8.4 Virtudes: `getTiempoCreacionVirtud()` (línea 332)

Estudioso o Erudito: 0.8 (-20%, no se acumulan). Ninguna: 1.0.

### 8.5 Recolector y Mayorista

- `getRecolectorDescuento()` (línea 343): `min(0.9 si Recolector >= 1 o 1.0, getMayoristaDescuentoBerries())`. Se toma el mejor descuento, no se acumulan.
- `getMayoristaDescuentoBerries()` (línea 360): rango 1 = 1.0 (sin descuento propio, el -10% ya lo da Recolector R1), rango 2 = 0.85, rango 3 = 0.80.
- `getMayoristaReduccionTiempo()` (línea 369): rango 1 = 0.90, rango 2 = 0.85, rango 3 = 0.80.
- `getReduccionTiempoTotal()` (línea 383): hoy solo devuelve la reducción de Mayorista. El comentario habla de "sustituir otros beneficios", pero no hay otro beneficio.
- Estos descuentos **se aplican a todas las recetas del jugador**, no solo a las del oficio Recolector/Mayorista.

### 8.6 Salas de barco

`_getBarcoSalas($uid)` (línea 393) devuelve un mapa `tipo_de_sala => true` con las salas a las que el usuario tiene acceso, por una de dos vías:
- Es **dueño** de un barco en su inventario (`objeto_id LIKE '%BARC%-%'`) con esa sala, y el dueño no está marcado `owner_ausente`.
- Es **tripulante** no ausente del barco de otro.

Usa `static $cache`, así que solo consulta una vez por petición. Ojo: la caché no depende del uid, solo funciona bien porque se llama siempre con el mismo uid.

`getBarcoSalaTimeMult($uid, $oficioObjeto)` (línea 420): si hay sala, **tiempo x0.75** para estas recetas:

| Receta de | Sala |
|---|---|
| Herrero | forja |
| Modista | taller |
| Astillero, Constructor, Ingeniero, Biólogo | taller |
| Cartógrafo | mapa |
| Arqueólogo, Periodista, Contrabandista | archivos |

`getBarcoSalaQuantityMult($uid, $oficioObjeto)` (línea 444): devuelve **2** (duplica las unidades entregadas) en estos casos, y 1 en el resto:

| Sala | Recetas de |
|---|---|
| enfermería | Médico, Farmacólogo |
| quirófano | Médico, Doctor |
| invernadero | Recolector, Agreste, Mayorista |

La sala **cocina** no cambia tiempo ni cantidad: aumenta un 25% los números del efecto de los platos al reclamar (sección 10.4).

---

## 9. Fórmulas finales

Para un crafteo del jugador (líneas 588 y 620-625):

```
costo_unidad = berriesCrafteo
             * getBerriesCosto(oficio_receta)
             * material_pct
             * getRecolectorDescuento()

costo_total  = costo_unidad * cantidad

duracion_seg = tiempo_creacion(horas) * 3600
             * getTiempoCreacion(oficio_receta)
             * getTiempoCreacionVirtud()
             * material_pct
             * getReduccionTiempoTotal()        (solo Mayorista)
             * getBarcoSalaTimeMult()

unidades_entregadas = cantidad * getBarcoSalaQuantityMult()
```

Donde `material_pct = 1 - (tier_material / 10)` si se usa un material, y 1 si no. Es decir, **un material de tier 3 baja coste y tiempo un 30%**. El material se consume (`quitarObjeto`).

Los multiplicadores de distintas fuentes se **multiplican entre sí** (especialización, material, Recolector/Mayorista). Pero Recolector y Mayorista no se acumulan entre ellos: se toma el mejor. Recolector R1 solo: 0.9. Mayorista R1: 0.9 (por Recolector). Mayorista R2: 0.85. Mayorista R3: 0.80.

`berriesCrafteo` y `tiempo_creacion` salen de `mybb_op_objetos`. `tiempo_creacion` se interpreta como **horas**.

Para NPC (líneas 759 y 771-773) es parecido pero más simple:

```
costo   = berriesCrafteo * getBerriesCostoNPC() * material_pct
duracion = tiempo_creacion * 3600 * getTiempoCreacionNPC() * material_pct
cantidad = 1
```

Sin virtudes, sin Recolector, sin Mayorista, sin salas. Los mapas de `getBerriesCostoNPC` y `getTiempoCreacionNPC` son los mismos que los del jugador, con los mismos nombres de especialización, pero leyendo el JSON de oficios del NPC.

---

## 10. Acciones, una por una

Todas leen `$_POST['accion']`.

### 10.1 `craftear`

Parámetros: `objetoId`, `actor` (`player` o `npc`), `npcId`, `materialTier`, `materialNombre`, `materialId`, `cantidadCraftear`.

**Rama jugador (`actor = player`, líneas 567-648):**
1. Carga el objeto base (`custom='0'`).
2. Calcula `material_pct`, coste, y la cantidad.
3. **Cantidad:** solo se permite elegir más de 1 si el jugador tiene Recolector **y** la receta es de Recolector, Agreste o Mayorista.
   - Recolector R2 o más: máximo 2.
   - Agreste R1: 3. Agreste R2: 5. Agreste R3: 10 (sustituye al valor anterior).
   - La cantidad pedida se recorta al rango válido.
4. Si `berries - costo_total >= 0`:
   - `START TRANSACTION`.
   - Inserta **una fila por unidad entregada** en `mybb_op_crafteo_usuarios`, todas con el mismo `timestamp_end`, la misma `duracion` y `costo = costo_unidad`.
   - Resta los Berries de la ficha (`UPDATE ... SET berries = nuevo`).
   - Registra el movimiento con `log_audit_currency`.
   - Si había material, lo consume.
   - Escribe una línea en `mybb_op_audit_crafteo`.
   - `COMMIT`.
   - Responde JSON `{success: true, actor: 'player'}` y termina.
5. Si no alcanzan los Berries, **no responde nada especial**: sigue de largo y se dibuja la página.

**Rama NPC (`actor = npc`, líneas 650-796):**
1. Comprueba que el NPC es del usuario: o su `npc_id` empieza por `{uid}-`, o hay una fila en `mybb_op_npcs_usuarios` con su usuario/uid.
2. Carga el objeto. Si no existe, responde error JSON.
3. Verifica que el NPC puede craftearlo:
   - Oficio principal del NPC con `nivel >= nivel de la receta`, **o**
   - alguna especialización cuyo nombre coincida con el oficio de la receta y cuyo rango **+1** sea `>= nivel de la receta`. (Las especializaciones cuentan un nivel más.)
4. Mismo flujo de cobro y de inserción, pero en `mybb_op_crafteo_npcs`, una sola unidad.
5. Responde JSON `{success: true, actor: 'npc', npcId}`.

Notas:
- Un jugador puede tener **un crafteo propio y varios de NPC a la vez**. Cada NPC tiene su propia cola.
- Dentro de `craftear` se **declaran funciones dentro de un `if`** (`getBerriesCostoNPC`, `getTiempoCreacionNPC`). En PHP eso las crea solo cuando se ejecuta esa rama, y daría error "Cannot redeclare" si el bloque se ejecutara dos veces en la misma petición (no ocurre aquí).

### 10.2 `cancelar` y `cancelarNpc`

- Suman el campo `costo` de **todas** las filas del jugador (o del NPC concreto) en la tabla de crafteos.
- Devuelven esa suma a los Berries de la ficha y la registran con `log_audit_currency`.
- Si la fila tenía `material_id`, devuelven el material con `darObjeto` (modo estándar).
- Borran las filas.

### 10.3 `desbloquear`

Compra una receta con **Puntos de Oficio**. Lee `desbloquear` (el coste en puntos) del objeto. Si `puntos_oficio - coste >= 0`, descuenta los puntos e inserta una fila en `mybb_op_inventario_crafteo` con `desbloqueado = 1`. No devuelve JSON: tras ejecutarse, el script dibuja la página.

### 10.4 `reclamar`

Con `npcId` vacío: reclama los crafteos del jugador. Con `npcId`: los de ese NPC.

**Jugador (líneas 863-898):**
1. Lee todas las filas de `mybb_op_crafteo_usuarios` del uid.
2. Toma el `objeto_id` de la **primera fila** como el objeto a entregar.
3. Cantidad = número de filas.
4. Borra todas las filas.
5. Si el objeto es un plato y el usuario tiene la sala `cocina`, `efecto_mult = 1.25`.
6. Llama a `darObjeto(..., crear_copia=true, ...)` una vez por unidad.
7. Responde JSON `[ {log: "..."} ]`.

**NPC (líneas 806-860):**
1. Lee las filas del NPC y filtra las ya terminadas (`timestamp_end <= time()`).
2. Convierte el JSON de oficios del NPC al mismo formato que el de un jugador (para guardar el autor con esos oficios).
3. Calcula el multiplicador de Cocina por cada objeto.
4. Entrega cada objeto terminado con `darObjeto` en modo copia.
5. Borra solo las filas terminadas.

### 10.5 `desbloquearReceta`

Alternativa a pagar puntos: gasta un objeto "Receta de Oficio" del inventario.
- El id debe empezar por `MJR00{tier}`, donde `tier` es el tier de la receta (`MJR001` a `MJR005`).
- El servidor elige **cualquier** receta que coincida y la consume con `quitarObjeto`.
- Rechaza si ya estaba desbloqueada o si el tier no está entre 1 y 5.
- Responde JSON `{ok, mensaje}`.

### 10.6 `get_objects_for_actor`

Devuelve JSON con las recetas disponibles para el jugador o un NPC (el front lo usa al cambiar de actor).
- Carga todos los objetos `custom=0` y filtra en PHP.
- Entra una receta si el uid está en su lista `crafteo_usuarios` (acceso individual), **o** si algún oficio/especialización del actor coincide con el oficio de la receta y su nivel es `>= nivel de la receta`.
- Para NPCs, descarta objetos cuyo id empieza por un dígito (por ejemplo `352MI001`, que son objetos personales).

---

## 11. Qué recetas ve el jugador en la página

Líneas 456-518 y 1051-1094. Se construye una consulta con `OR` de estas ramas, siempre sobre objetos `custom=0`:

| Rama | Condición |
|---|---|
| Oficio 1 | `oficio = oficio1` y `nivel <= nivel del oficio 1` |
| Oficio 2 | Igual, si existe |
| Especializaciones | Una rama por `espe1`/`espe2` de cada oficio, con el rango de esa especialización |
| Recolector | Se agrega **siempre que el jugador tenga Recolector**, sea oficio 1 o 2 o no, junto con sus especializaciones |
| Acceso individual | `FIND_IN_SET(uid, crafteo_usuarios)` |

Todas las ramas de oficio exigen `crafteo_usuarios` vacío o NULL (`$cond_libre`). Es decir, **una receta con lista de usuarios es exclusiva de esos usuarios**: nadie más la ve aunque cumpla el oficio.

Hay un pequeño bloque de normalización (líneas 1060-1076) que reemplaza `||` por `OR` en los trozos de SQL, porque se armaron con `||` primero.

La consulta (línea 1079) solo trae recetas. Si una receta está **desbloqueada o no** se resuelve aparte, en `$inventario` (tabla `mybb_op_inventario_crafteo`) y el front decide qué mostrar.

---

## 12. Datos que prepara para el front (líneas 1101-1201)

Todo se pasa a la plantilla como JSON:

| Variable | Contenido |
|---|---|
| `$objetos_json`, `$objetos_array_json` | Recetas visibles |
| `$materiales_json`, `..._array_json` | Todos los objetos del inventario con categoría `Materiales` (de cualquier oficio) |
| `$inventario_json`, `..._array_json` | Recetas ya desbloqueadas (`mybb_op_inventario_crafteo`) |
| `$objetos_recetas_json`, `..._array_json` | Objetos del inventario con `MJR00` en el id (recetas de oficio gastables) |
| `$npcs_json`, `$npcs_array_json` | NPCs del usuario |
| `$player_crafteo_json` | Booleano: hay un crafteo propio aún en marcha |
| `$npcs_crafteando_json` | Lista de `npc_id` con crafteo en marcha |

Los NPCs se obtienen de dos formas que luego se mezclan:
1. Por la tabla de relación `mybb_op_npcs_usuarios` (por username o por uid).
2. Por prefijo del id (`npc_id LIKE '{uid}-%'`), para NPCs que solo existen en `mybb_op_npcs`.

---

## 13. Qué plantilla se muestra (líneas 1203-1321)

Solo si hay ficha, está aprobada y no tiene "Sin Oficio".

1. Lee crafteos del jugador y de NPCs.
2. Marca `$en_curso` si alguno tiene `timestamp_end` en el futuro, `$completo` si alguno ya venció.
3. Entre los que están en curso elige **el que termina antes** como el que se muestra.

Decisión:

| Situación | Plantilla |
|---|---|
| Hay crafteo en curso y no es modo vista | `op_crafteo_en_curso` |
| No en curso, pero hay uno completo | `op_crafteo_completo` |
| Ninguno | `op_crafteo` (panel normal) |

Para plantillas `en_curso` y `completo` el script imprime `$reclamar_npc_id_js` (un literal JS hecho con `json_encode`) para que el botón de reclamar sepa si debe mandar un `npcId`.

Truco de render (líneas 1274-1276 y análogas): antes de `eval`, cambia `${` por `___DOLLAR_CURLY___` y luego lo revierte. Es porque las plantillas de MyBB se evalúan con `eval("...")` y el JS usa plantillas literales con `${...}`, que PHP intentaría interpretar.

Si no hay ficha o no tiene oficio, el `else` final (línea 1317) redirige con "debes tener un oficio".

---

## 14. Tablas que toca

| Tabla | Operaciones |
|---|---|
| `mybb_op_fichas` | Lee la ficha. Actualiza `berries` y `puntos_oficio`. Dispara el trigger de auditoría de la ficha. |
| `mybb_op_objetos` | Lee recetas y objetos base. Inserta copias personalizadas (`custom=1`). |
| `mybb_op_inventario` | Inserta, actualiza y borra objetos del jugador. |
| `mybb_op_inventario_crafteo` | Recetas desbloqueadas (inserta, lee). |
| `mybb_op_crafteo_usuarios` | Cola de crafteos del jugador (una fila por unidad). |
| `mybb_op_crafteo_npcs` | Cola de crafteos de NPC. Se crea desde este archivo si no existe. |
| `mybb_op_npcs`, `mybb_op_npcs_usuarios` | Lee NPCs y su propiedad. |
| `mybb_op_virtudes_usuarios` | Lee virtudes y defectos. |
| `mybb_op_audit_crafteo` | Inserta log de cada crafteo. |
| `mybb_op_barco_salas`, `mybb_op_barco_estado`, `mybb_op_barco_tripulacion` | Lee las salas disponibles. |
| `mybb_users` | Lee el username en modo vista. |

Funciones externas: `log_audit_currency()` y `oficioFamilia()` (de `op_functions.php`). Variables que no se definen aquí: `$g_ficha`, `$g_is_staff` (vienen de otro lado, probablemente un plugin).

Columnas de `mybb_op_objetos` que importan: `berriesCrafteo`, `tiempo_creacion` (horas), `oficio`, `nivel`, `tier`, `custom`, `desbloquear` (puntos), `crafteo_usuarios` (lista CSV de uids), `categoria`, `subcategoria`, `efecto`, `bloqueo`, `alcance`.

---

## 15. Observaciones para estudiar (posibles problemas)

Son cosas que se desprenden de leer el código. Ninguna fue probada en ejecución, así que conviene confirmar cada una antes de tocar nada. Están ordenadas por importancia.

### Posibles fallos de lógica o de seguridad

1. **`reclamar` del jugador no comprueba que el crafteo haya terminado** (línea 866). Toma todas las filas sin filtrar por `timestamp_end`. La versión de NPC sí lo hace. Posiblemente el front solo muestre el botón cuando está completo, pero el servidor lo acepta en cualquier momento.

2. **`reclamar` entrega el objeto de la primera fila para todas las unidades** (líneas 873-891). Si el servidor permite tener varios crafteos distintos a la vez, al reclamar se entregarían N copias del primero. Los crafteos simultáneos del jugador sí están permitidos por comentario en el código ("Permitir múltiples crafteos simultáneos"); no se verifica en el servidor que no haya uno en marcha.

3. **El reembolso de `cancelar` puede ser mayor que lo cobrado cuando una sala duplica las unidades.** Se cobra `costo_unidad * cantidad`, pero se insertan `cantidad * 2` filas, cada una con `costo = costo_unidad`. Al cancelar se suma el costo de todas las filas.

4. **`cancelar` no distingue crafteos terminados de los que no.** Borra y devuelve el dinero de todos, incluso los ya completados y sin reclamar.

5. **`craftear` (jugador) no valida el acceso a la receta.** No comprueba que esté desbloqueada en `mybb_op_inventario_crafteo`, ni que el oficio y nivel del jugador la permitan. La rama NPC sí valida oficio y nivel, pero tampoco el desbloqueo. Si el objeto no existe, `$objeto` queda en `null`: el coste sería 0 y se insertaría igual una fila.

6. **`desbloquear` no evita desbloquear dos veces** la misma receta (cobra y duplica la fila) ni comprueba el oficio. `desbloquearReceta` sí comprueba que no esté desbloqueada, pero no el oficio.

7. **Posible doble gasto por concurrencia.** Los Berries se leen al inicio de la petición y se escribe un valor absoluto (`SET berries = $berriesNuevo`), no una resta (`berries = berries - X`). Dos peticiones simultáneas parten del mismo saldo y las dos se aprueban. Además `START TRANSACTION` no protege si la tabla es MyISAM (el proyecto mezcla motores) y no hay `SELECT ... FOR UPDATE`.

8. **Si no alcanzan los Berries, `craftear` no devuelve error.** Cae al render de página completa en una petición AJAX, y el front recibe HTML en lugar de JSON.

### Detalles menores

9. **`$nombre` no está definida** en la línea 639 (log de auditoría del jugador). El campo `nombre` queda vacío.

10. **El contador de copias de `darObjeto`** usa `LIKE 'BASE-UID%'`. Con un uid como 5 coincide también con `BASE-50-...` y `BASE-512-...`, así que el contador podría saltar o repetirse.

11. **Corregido:** el mapa del Médico (Doctor da tiempo, Farmacólogo da coste) y el -20% de Estudioso y Erudito ya coinciden con la guía, tanto para jugador como para NPC.

12. **Corregido:** Mayorista R1 ya no da descuento de coste propio, y Recolector y Mayorista ya no se acumulan (se toma el mejor), como dice la guía.

13. **Terrateniente** (grado de Recolector: -25% coste y tiempo, segunda cola de crafteo) no aparece en este archivo.

14. **La tabla `mybb_op_crafteo_npcs` se crea con `CREATE TABLE IF NOT EXISTS` en cada visita.** Es inofensivo pero innecesario.

15. **Depuración activada** (`display_errors = 1`) en producción: puede mostrar rutas y errores a los usuarios.

16. **Si el jugador tiene `oficio1` vacío pero `oficio2` con valor**, la línea 458 (`$oficios->{$oficio1}->{'nivel'}`) falla. El chequeo de la línea 78 sí permite ese caso.

17. **`THIS_SCRIPT = 'viajes.php'`**: ya documentado en CLAUDE.md como error sin efecto funcional.

18. **Las consultas usan interpolación de strings.** Varias entradas pasan por `escape_string`, pero `$_POST['objetoId']`, `materialId`, `materialTier` y `materialNombre` se interpolan sin escapar en consultas (por ejemplo líneas 575, 628, 668, 909, 939). Es el patrón general del proyecto, pero aquí afecta directamente a una acción que mueve dinero.

---

## 16. Guía rápida para modificar el archivo

| Quiero cambiar... | Dónde mirar |
|---|---|
| Qué especialización da descuento de tiempo o coste | `getTiempoCreacion` (línea 306) y `getBerriesCosto` (línea 319), más las dos funciones de NPC (líneas 712 y 735) |
| Porcentajes por rango | `getDescuento` (línea 294) |
| Bonus de Mayorista o Recolector | Líneas 344-389 |
| Bonus de virtudes | `getTiempoCreacionVirtud` (línea 332) |
| Cuántas unidades se pueden craftear a la vez | Líneas 590-612 |
| Bonus de salas de barco | Líneas 391-454 |
| Quién puede ver una receta | Líneas 456-518 y 1051-1094; también `get_objects_for_actor` (línea 983) |
| Qué se entrega al reclamar | `darObjeto` (línea 140) y `reclamar` (línea 799) |
| Reglas del NPC | Líneas 650-796 |

Regla práctica: **cualquier cambio de descuento hay que hacerlo en dos sitios** (jugador y NPC), porque el código está duplicado.
