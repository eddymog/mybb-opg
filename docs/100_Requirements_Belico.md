# Requisitos: Página Bélica (`belico.php`)

## 1. Propósito

El foro tiene muchos cálculos de combate (daño de golpe básico y técnicas,
penalizaciones por múltiples armas, movimiento, salto, caídas, umbrales de
dolor, etc.) que hoy el usuario tiene que hacer a mano leyendo la
[Guía Bélica](../op/punkrecords/guias/b01_guia_belica.md) (extraída del hilo
`showthread.php?tid=3731`).

Ya existe una "Calculadora Bélica" parcial, pero vive como un panel flotante
dentro de `newreply.php`/`newthread.php` (botón "BÉLICO", cargado por
`jscripts/belico/panel.js` + `jscripts/belico/calc.js`). Este documento define
una **página propia**, `belico.php`, como complemento para escribir posts
bélicos: un lugar dedicado donde hacer estos cálculos con las estadísticas
reales del personaje.

## 2. Decisiones ya tomadas

- **Ubicación:** `op/belico.php`, junto al endpoint que ya existe
  (`op/belico_data.php`) y al resto de herramientas del jugador
  (`op/calculadora.php`, `op/coliseo_ficha.php`).
- **Todo el código vive en `belico.php`.** HTML, CSS y JS dentro del mismo
  archivo — no se reparte en una plantilla aparte más archivos en
  `jscripts/`, como hace el panel actual. Implicación: la página no pasa por
  `$templates->get()` ni necesita `import_templates.php` para verse en
  producción (a diferencia de casi todas las páginas de `/op/`). Ver §5.
- **La página carga sus propios datos.** `belico.php` consulta la base en el
  mismo request y entrega los datos ya listos al JS de la página, en vez de
  depender de `belico_data.php` por XHR. `belico_data.php` queda sin tocar,
  sirviendo al panel viejo mientras exista.
- **Parámetros `?tid=` y `?fid=`, los dos opcionales:**
  - `fid` = de quién es la ficha (`fid` = `uid`, una ficha por cuenta). Por
    defecto, la del usuario logueado.
  - `tid` = de qué hilo. Con un `tid`, se usan las estadísticas de esa ficha
    **tal como quedaron fijadas en ese hilo**; sin `tid` (o si esa ficha no
    tiene snapshot en ese hilo), las estadísticas actuales.
  - **Se puede ver la ficha de otro participante** (`fid` distinto al propio)
    — por ejemplo para calcular contra el rival. Es alcance nuevo: hoy no
    existe (§3.1). Las fichas ya son públicas en el foro, así que no expone
    nada nuevo (§5).
- **El panel flotante "BÉLICO"** de `newreply`/`newthread` se reemplazará
  eventualmente por un enlace que abra `belico.php?tid=X&fid=X`. No forma
  parte de esta primera versión.
- **Cálculos: todos los que se puedan calcular** — lo que ya hace `calc.js`
  (§3.2), todas las reglas con fórmula de la Guía (§3.3) y la tabla de
  reacciones (§3.4).
- **Regeneración de energía `NIV` y de haki `NIV/2`:** confirmadas como
  correctas, aunque no aparezcan en la Guía Bélica.
- **Inventario y técnicas: siempre los actuales**, aun cuando las
  estadísticas vengan del snapshot de un hilo.
- **Referencias de partida:** `jscripts/belico/calc.js` y `panel.js` (lógica
  y cálculos ya escritos) y la Guía Bélica (reglas canónicas).

## 3. Estado actual (verificado en el código)

### 3.1 El comportamiento de `?tid=` ya existe

`op/belico_data.php:17-41` ya resuelve exactamente lo que se pide:

```php
$tid = isset($mybb->input['tid']) ? intval($mybb->input['tid']) : 0;
if ($tid > 0) {
    // snapshot del personaje en ese hilo
    SELECT ... FROM mybb_op_thread_personaje WHERE tid='$tid' AND uid='$uid' ORDER BY pid ASC LIMIT 1
}
if (!$ficha) {
    // ficha actual
    SELECT ... FROM mybb_op_fichas WHERE fid='$uid'
}
if (!$ficha) { echo json_encode(['error' => 'no_ficha']); }
```

Puntos importantes:

- **Siempre es la ficha del usuario logueado (`$uid`).** Con un `tid`, busca
  el snapshot de *ese mismo usuario* en ese hilo. No existe ninguna tabla que
  asocie un hilo a un personaje independiente del usuario — nunca muestra la
  ficha de otro participante del hilo ni de un NPC.
- `mybb_op_thread_personaje` es el snapshot que se guarda la primera vez que
  el usuario usa el código `[ficha]` en un hilo (`inc/plugins/BBCustom_ficha.php:223-251`).
  Coincide con la regla de la Guía Bélica: *"usemos el [Código] de
  'Personaje' en ese mismo post para dejar fijadas las estadísticas del
  personaje y su inventario equipado en ese momento"* (línea 56).
- Toma el **primer** post del usuario en ese hilo (`ORDER BY pid ASC`), no el
  último.
- Además devuelve: vida/energía/haki máximos con bonos de virtudes
  (`belico_data.php:52-80`), técnicas aprendidas (`:83-92`) e inventario
  (`:96-108`). **El inventario y las técnicas son siempre los actuales**, no
  un snapshot del hilo.
- **El parámetro nunca se usa hoy**: `calc.js:257-258` lo envía si existe
  `window.BC_TID`, pero ninguna plantilla ni archivo define `BC_TID`. Es
  infraestructura que quedó sin conectar.

### 3.2 Qué calcula hoy `calc.js`

- **Atributos:** Base (de la ficha) + Modificador (editable a mano) = Final,
  para NIV/FUE/AGI/PUN/DES/RES/REF/VOL.
- **Golpe/Bloqueo básico:** según armas elegidas (hasta N espacios), con modo
  Ataque/Defensa. Sin arma: Fuerza (ataque) o Resistencia (defensa).
- **Múltiples armas:** 1 arma 100%, 2 armas 75% cada una, 3+ armas 60% cada
  una (coincide con la Guía, "Armas", líneas 264-266).
- **Daño de técnica:** parsea patrones como `[NxNivel]` del texto de
  `efectos` de la técnica.
- **Daño total:** golpe + técnica, con reglas para técnicas defensivas.
- **Estadísticas bélicas** (todas coinciden con la Guía): Movimiento
  `AGI/2 + RES/4`, Salto `1 + (FUE+AGI)/5`, Trepar `1 + (AGI+DES)/10`, Nadar
  `Movimiento/2`, Distancia de lanzamiento `FUE/2`, Límite de caída
  `2 + (FUE+RES)/4`, Defensa Pasiva `RES`, Fortaleza Espiritual `VOL`,
  Concentración `VOL+RES`, Umbral de Dolor `5×(VOL+RES)`.
- **Regeneración de energía `NIV` y de haki `NIV/2`:** están en `calc.js`
  pero no en la Guía Bélica — confirmadas como correctas igual (§2).
- **Registro de acciones:** lista de acciones calculadas durante la sesión,
  para armar el post.

### 3.3 Qué dice la Guía y `calc.js` todavía no calcula

Reglas con fórmula concreta en la Guía, sin implementar hoy:

| Regla | Fórmula (Guía Bélica) | Línea |
|---|---|---|
| Tasa de Acierto con múltiples armas | 2 armas: −10 TA; más de 2: −5 TA por arma; con armas distintas se toma la TA más baja | 265-269 |
| Arma de una mano con las dos | +50% al daño y bloqueo base (no amuletos, arrojadizas ni armas de fuego) | 254 |
| Distancia | −5 TA cada 20 metros de separación | 309 |
| Ataque sorpresa detectado | −20 Reflejos | 310 |
| Bloques de acción sin cortar | +5 Reflejos acumulativos por bloque | 96 |
| Mitigación pasiva | Defensa Pasiva resta `RES` de todo daño salvo Espiritual; Fortaleza Espiritual resta `VOL` solo del Espiritual; Daño Verdadero ignora ambas | 219-220, 236 |
| Daño perforante | Ignora 25 de Defensa Pasiva por cada 100 de daño | 228 |
| Daño con dos tipos | El daño se reparte mitad y mitad entre los tipos | 238-240 |
| Efectos por tipo de daño | Hemorragia cada 300 cortante; Entumecimiento cada 200 contundente; Quemadura/Daño Interno/Fatiga/Congelación/Electroshock cada 250 elemental; Desorientación cada 200 sónico; Amputación ≥50% vida máx (cortante); Fractura/Vértigo ≥40% vida máx | 226-234 |
| Regla de los Efectos | El objetivo debe recibir al menos un tercio del daño total, redondeado hacia arriba, tras mitigaciones (la Guía dice "33%", pero su ejemplo — 200 de daño → 67 — es un tercio exacto, no 0,33) | 317-319 |
| Daño por caída | 15 de Daño Contundente por cada metro por encima del límite de caída | 374 |
| Asfixia | 1 turno por cada 20 de Resistencia | 396 |
| Lanzamiento de objeto | Daño = Fuerza (+ daño base si es un arma no arrojadiza); TA = Puntería | 378-382 |
| Agarre | Comparativa de Fuerza; +10 Fuerza acumulativo por turno de forcejeo | 388 |
| Estado por recursos | Vida ≤0 derrotado; negativo > VOL+RES inconsciente; negativo > (RES+VOL)×3 muerte. Mismo esquema para Energía y Haki | 412-432 |
| Iniciativa | Empieza quien tenga el atributo más bajo más alto; desempate con el segundo más bajo; luego 1d100 | 52 |

### 3.4 Tabla de reacciones

La Guía la menciona (líneas 92, 301, 311, 454) pero no aparece en el texto
extraído — era una imagen en el hilo original. El usuario la pasó aparte;
esta es la transcripción. La diferencia es **Reflejos del defensor − Tasa de
Acierto del atacante**:

| Reflejos − Tasa de Acierto | Tipo de defensa | Efecto |
|---|---|---|
| Más de 30 | Esquiva Absoluta | Esquiva el 100% del daño |
| 21 a 30 (ver nota 1) | Esquiva Efectiva | Esquiva el 75% del daño |
| 11 a 20 | Esquiva Torpe | Esquiva el 50% del daño |
| 0 a 10 | Choque Absoluto | El choque aplica el 100% del daño |
| −1 a −15 | Choque Efectivo | El choque aplica el 75% del daño |
| −16 a −30 | Choque Torpe | El choque aplica el 50% del daño |
| −31 a −45 | Bloqueo Absoluto | La defensa aplica el 100% de mitigación |
| −46 a −60 | Bloqueo Efectivo | La defensa aplica el 75% de mitigación |
| −61 a −75 | Bloqueo Torpe | La defensa aplica el 50% de mitigación |
| Menos de −75 | Sin Reacción | No hay opción a ninguna defensa o reacción |

Antes de buscar en la tabla se aplican los modificadores de §3.3: −5 TA cada
20 metros, −10/−5 TA por múltiples armas, −20 Reflejos por ataque sorpresa
detectado (sin detectar: sin reacción), +5 Reflejos por bloque de acción sin
cortar. Además, la esquiva requiere poder desplazarse más que el área del
ataque (Guía, línea 301).

**Notas sobre la tabla:**

1. **Errata en la imagen original:** dice "Entre 21 y 31", que se pisa con
   "Más de 30". **Confirmado por el usuario: el rango correcto es 21 a 30.**
2. **Mantenidas activadas en el mismo turno (Guía, línea 311).** Un aumento
   de Reflejos por una mantenida activada en el mismo turno de la defensa
   (ej. Kenbunshoku) solo cuenta si, **antes** de activarla, la diferencia ya
   alcanzaba para un Bloqueo Absoluto. El texto y el ejemplo de la Guía no
   coinciden (el ejemplo dice −30). **Decidido por el usuario: se sigue el
   texto, no el ejemplo** — el mínimo es **−45**, el límite inferior del
   Bloqueo Absoluto en la tabla. El valor vive en un solo lugar del código
   para poder cambiarlo si la Guía se corrige.
3. **La página muestra solo la reacción que da la tabla.** No ofrece elegir
   una reacción peor que la máxima (ej. chocar teniendo +35), porque ni la
   tabla ni la Guía dicen si está permitido. Decidido por el usuario; si se
   confirma que sí se puede, se agrega después.

## 4. Requisitos funcionales

### 4.1 Acceso

- Solo usuarios logueados (igual que `belico_data.php`, que devuelve
  `not_logged_in` a invitados). Un invitado ve un aviso, no un error de
  JavaScript.
- Si el usuario no tiene ficha (`no_ficha`), la página lo muestra con un
  mensaje claro — hoy `calc.js` solo deja los valores en `-` y escribe un
  warning en la consola (`calc.js:264-265`).

### 4.2 Origen de las estadísticas

- `fid` = de quién es la ficha; por defecto, la del usuario logueado. Un
  `fid` que no existe muestra un aviso claro.
- Con `?tid=N`: snapshot de **esa ficha** en ese hilo
  (`mybb_op_thread_personaje WHERE tid=N AND uid=fid`, el primer post, igual
  que `belico_data.php` hoy), si existe.
- Sin `tid`, o si no hay snapshot: ficha actual (`mybb_op_fichas`).
- Inventario y técnicas: siempre los actuales de esa ficha (§2).
- **La página debe indicar visiblemente qué está mostrando**: de quién es la
  ficha, y si son las estadísticas fijadas en el hilo (ej. "Estadísticas de
  *Personaje* en *Nombre del hilo*") o las actuales. Hoy el panel no lo dice,
  y es importante para el rol: la Guía exige usar las estadísticas fijadas
  al inicio del combate.
- Si se pasa un `tid` pero esa ficha no tiene snapshot en ese hilo, avisar
  que se están usando las actuales (no caer en silencio).

### 4.3 Cálculos

Todo lo que ya calcula `calc.js` (§3.2), sin regresiones, más todas las
reglas con fórmula de la Guía (§3.3) y la reacción según la tabla (§3.4):
dados los Reflejos del defensor y la Tasa de Acierto del atacante (con sus
modificadores), qué reacción da la tabla y con qué porcentaje, incluida la
regla de mantenidas en el mismo turno (§3.4, nota 2).

### 4.4 Resultado copiable

Los resultados deben poder llevarse al post con facilidad (texto o BBCode
listo para copiar), ya que el propósito de la página es complementar la
escritura del post bélico. El registro de acciones de `calc.js` es el punto
de partida.

## 5. Requisitos no funcionales

- **Un solo archivo.** HTML, CSS y JS dentro de `op/belico.php`. Consecuencias
  a tener en cuenta en el diseño:
  - Puede imprimir HTML directo (o con heredoc) en vez de
    `$templates->get()`, así que la regla de "nada de `$` sueltos en
    plantillas MyBB" no aplica — pero si se usa `eval()` de plantilla en algún
    punto, sí.
  - Sigue necesitando `{$header}`/`{$footer}`/`{$headerinclude}` del foro para
    verse integrada (el armado se define en el diseño).
- **Diseño:** `docs/style.md` — marco de tres fondos, `.barra-op`, `.btn-op`,
  `.opg-chip`, `.af-field`, paleta real. El panel actual (`calc.js`) usa
  estilos inline con colores sueltos (`#0055bb`, `#853dee`, `#a3180b`) que
  no siguen el sistema de diseño.
- **Mobile:** usable en ≤700px.
- **Seguridad:** `tid` y `fid` siempre con `intval()`; toda salida escapada.
  Ver fichas ajenas no expone nada nuevo: las fichas ya son públicas en el
  foro (`op/personaje.php`). Las identidades secretas
  (`[fichasecreta]`/`[personajesecreto]`) no se tienen en cuenta — decisión
  explícita del usuario.
- **Sin tocar el resto del sitio.** `belico_data.php`, `panel.js`, `calc.js`
  y las plantillas `newreply`/`newthread` quedan igual en esta versión.

## 6. Preguntas abiertas

Ninguna. Todas las preguntas quedaron resueltas: las decisiones están en §2
y las de la tabla de reacciones en las notas de §3.4.
