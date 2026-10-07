# Página Bélica (`op/belico.php`): plan de diseño

> Depende de: [100_Requirements_Belico.md](100_Requirements_Belico.md)
>
> **Estado: por implementar.** El único archivo nuevo es `op/belico.php`. No
> se toca ningún otro archivo del sitio.

## 1. Objetivo

Una página propia, en un solo archivo, que carga la ficha de un personaje
(propia o ajena, actual o fijada en un hilo) y hace todos los cálculos
bélicos que tienen fórmula en la Guía Bélica, más los que ya hace
`jscripts/belico/calc.js`, con un resumen listo para copiar al post.

**Los cálculos se hacen en PHP y la página usa htmx** para pedirlos y
mostrar el resultado sin recargar. El JS propio queda reducido a copiar al
portapapeles.

## 2. Archivos

| Archivo | Qué hace | Nuevo/existente |
|---|---|---|
| `op/belico.php` | Todo: carga de datos, cálculos, página completa y fragmentos htmx | Nuevo |
| `jscripts/vendor/htmx-2.0.10.min.js` | htmx, ya vendorizado y usado por 7 plantillas del sitio (ej. `staff_cofres_gestion_editor.html`, `op_punkrecords.html`) | Existente, solo se carga |

No se tocan `op/belico_data.php`, `jscripts/belico/panel.js`,
`jscripts/belico/calc.js` ni las plantillas `newreply`/`newthread`
(100_Requirements §5).

## 3. Estructura del archivo

`op/belico.php`, en este orden:

1. **Bootstrap** — mismo patrón que el resto de `/op/`:
   ```php
   define("IN_MYBB", 1);
   define('THIS_SCRIPT', 'belico.php');
   require_once "./../global.php";
   require_once "./functions/op_functions.php";
   ```
2. **Reglas** — el array `$BELICO_REGLAS` con todos los números de la Guía
   (§6).
3. **Funciones de datos** (`belico_cargar_*`, §4) y **de cálculo**
   (`belico_calc_*`, §5) — PHP puro, sin HTML.
4. **Funciones de render** (`belico_render_*`) — devuelven el HTML de cada
   bloque o resultado, escapado.
5. **Parámetros y carga** — `tid`, `fid`, modificadores, y la ficha.
6. **Ruteo**: si la petición viene de htmx, se responde solo el fragmento
   pedido y `exit`; si no, se arma la página completa.

### 3.1 Página completa vs fragmento

Mismo criterio que `op/staff/cofres_gestion.php:186`:

```php
function belico_es_htmx() {
    return isset($_SERVER['HTTP_HX_REQUEST']);
}
```

- **Sin htmx** (primera carga, o `?tid=&fid=` desde un enlace): página
  completa con `$headerinclude`/`$header`/`$footer` y `output_page()`. Cada
  bloque ya sale calculado con los valores por defecto, así que la página
  sirve aunque htmx no cargue.
- **Con htmx**: se lee `accion` (`ataque`, `reaccion`, `danio`, `caida`,
  `asfixia`, `lanzamiento`, `agarre`, `iniciativa`, `estado`, `registrar`),
  se calcula y se devuelve solo el HTML del resultado. Una `accion`
  desconocida devuelve un aviso, no un error de PHP.

### 3.2 Armado del HTML sin plantilla

No se usa `$templates->get()` ni `eval()`. Las partes fijas (CSS y la poca
estructura que no cambia) van en *nowdoc* (`<<<'CSS'`, `<<<'HTML'`), que
PHP no interpreta — mismo recurso que `op/cronologia.php:923,1000`. Todo lo
dinámico lo arman las funciones `belico_render_*` con
`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`; nunca se interpola un valor
dentro de un nowdoc.

```php
$page = '<html><head><title>Calculadora Bélica</title>' . $headerinclude
      . '<style>' . $css . '</style>'
      . '<script src="' . $mybb->settings['bburl'] . '/jscripts/vendor/htmx-2.0.10.min.js"></script>'
      . '</head><body>' . $header
      . belico_render_pagina($datos)
      . $footer
      . '<script>' . $js_copiar . '</script></body></html>';
output_page($page);
```

## 4. Carga de datos

Todo filtrado por `$fid` (la ficha que se mira), no por el usuario logueado.

### 4.1 Acceso

- Invitado (`uid` 0): página con el aviso "Debes iniciar sesión para usar
  la calculadora." y sin datos. Una petición htmx de un invitado devuelve el
  mismo aviso.
- `fid` sin ficha en `mybb_op_fichas`: aviso "Ese personaje no existe" (o
  "Todavía no tienes ficha", si `fid` es el propio).

### 4.2 Estadísticas: snapshot o actuales

`belico_cargar_ficha($db, $fid, $tid)` devuelve
`['stats' => [...], 'origen' => 'hilo'|'actual', 'pid' => int|null, 'nombre' => string]`.

- Con `$tid > 0`: `SELECT ... FROM mybb_op_thread_personaje WHERE tid=$tid
  AND uid=$fid ORDER BY pid ASC LIMIT 1` — mismas columnas que
  `belico_data.php:19-23`, más `pid` y `nombre`.
- Si no hay fila: `mybb_op_fichas WHERE fid=$fid`.
- El snapshot no guarda fecha, pero sí el `pid` del post donde se fijó: se
  usa para enlazarlo (`showthread.php?pid=X#pidX`).
- Atributos finales y recursos máximos: **mismas fórmulas que
  `belico_data.php:43-80`**, copiadas, cambiando `$uid` por `$fid` (incluido
  el bono de virtudes V037-V041, V058-V059).

### 4.3 Título del hilo

`get_thread($tid)` de MyBB. El título solo se muestra si el usuario puede
ver ese foro (`forum_permissions($thread['fid'])['canview']`); si no,
"hilo #N".

### 4.4 Técnicas e inventario

Siempre los actuales de `$fid` (100_Requirements §2). Mismas consultas que
`belico_data.php:82-108`, con `$fid`. Del inventario solo se usan las armas
(`categoria = 'armas'`, sin `LLST001`, igual que `calc.js:474-476`).

### 4.5 Sin estado en el servidor

Cada petición htmx vuelve a cargar la ficha con `tid`/`fid` y recibe los
modificadores de atributos en la misma petición (§5.1). No hay sesión ni
nada guardado: la página es solo de lectura y cálculo. Por eso todas las
peticiones son **GET** (`hx-get`) — no cambian nada, así que no necesitan
`my_post_key`.

## 5. Página y cálculos

Marco de tres fondos (`.indice .belico-pagina` > `.mainBackground` >
`.secondBackground` > `.thirdBackground`), `max-width: 1100px`. Cada bloque
es un `.barra-op` + `.barra-espacio-op` (`style.md` §3), con un `<form>` de
entradas en `.af-field` y un `<div>` de resultado.

### 5.1 Cómo se conecta cada bloque con htmx

```html
<form id="belico-atributos">
  <input type="hidden" name="tid" value="...">
  <input type="hidden" name="fid" value="...">
  <div class="af-field"><label>Modif. FUE</label><input type="number" name="mod_fue" value="0"></div>
  ...
</form>

<form hx-get="belico.php"
      hx-trigger="input changed delay:300ms, change"
      hx-target="#res-ataque"
      hx-include="#belico-atributos">
  <input type="hidden" name="accion" value="ataque">
  ...entradas propias del bloque...
</form>
<div id="res-ataque">...resultado calculado en la primera carga...</div>
```

- `hx-include="#belico-atributos"` hace que **todos** los bloques reciban
  `tid`, `fid` y los modificadores actuales, sin estado compartido en JS.
- `delay:300ms` evita una petición por cada tecla.
- Cuando cambia un modificador de atributo, se recalculan todos los
  resultados: el formulario de atributos dispara un evento
  (`hx-trigger` con `from:#belico-atributos`) que escuchan los demás bloques.

### 5.2 Cabecera

- Nombre del personaje y enlace a su ficha.
- Origen, siempre visible: "Estadísticas fijadas en *Título del hilo*" +
  enlace al post, o "Estadísticas actuales" — con el aviso si se pidió un
  `tid` sin snapshot (100_Requirements §4.2).
- Formulario GET normal (sin htmx) para cambiar `tid`/`fid`: recarga la
  página entera, porque cambia la ficha base.

### 5.3 Atributos

NIV/FUE/AGI/PUN/DES/RES/REF/VOL con Base + Modificador = Final, igual que
`calc.js`. Todos los cálculos usan el Final.

### 5.4 Recursos y estados (`accion=estado`)

Vida/Energía/Haki máximos y un campo "actual" para cada uno → estado según
la Guía (líneas 412-432): consciente, derrotado pero consciente,
inconsciente, muerte.

### 5.5 Estadísticas bélicas

Movimiento, salto, trepar, nadar, distancia de lanzamiento, límite de caída,
defensa pasiva, fortaleza espiritual, regeneración de energía y haki,
concentración, umbral de dolor. **Mismas fórmulas y mismo redondeo que
`calc.js`** (`floor` en movimiento, `round` en salto/trepar/nadar/caída,
`ceil` en regen de haki), portados a PHP. Salen en la carga inicial y se
recalculan con los atributos.

### 5.6 Ataque (`accion=ataque`)

- Modo Ataque/Defensa, armas por espacio, técnica aprendida (filtro
  Todas/Activas/Mantenidas): todo como `calc.js`.
- Agregar o quitar un espacio de arma es otra petición htmx
  (`accion=ataque&espacios=N`) que devuelve el bloque entero con un selector
  más o menos — sin JS propio.
- Nuevo, por arma: **"A dos manos"** (casilla, +50% al daño y bloqueo base,
  Guía línea 254) y **atributo de Tasa de Acierto** (selector, ver §8).
- Resultado: golpe básico, daño de técnica, daño total (fórmulas de
  `calc.js`, incluido el parseo de `[FUEx1,5]` en el daño del arma y
  `[NxNivel]` en los efectos de la técnica, portados a `preg_match_all`) y
  **Tasa de Acierto final** (−10 con dos armas, −5 por arma con más, la más
  baja si difieren; sin arma, Agilidad — Guía líneas 265-269, 283).

### 5.7 Reacción (`accion=reaccion`)

Entradas: Reflejos del defensor (por defecto, los propios), TA del atacante,
distancia, ataque sorpresa (no / detectado / sin detectar), bloques sin
cortar, Reflejos de una mantenida activada este turno.

1. TA ajustada = TA − 5 × `floor(metros / 20)`.
2. Reflejos ajustados = Reflejos + 5 × bloques sin cortar − 20 si sorpresa
   detectada. Sin detectar: "Sin reacción".
3. Mantenida del turno: solo se suma si la diferencia sin ella ya es ≥ −45.
4. Diferencia → fila de la tabla (100_Requirements §3.4) → solo esa reacción
   y su porcentaje.
5. Si es esquiva: recordatorio de que hay que desplazarse más que el área.

### 5.8 Daño recibido (`accion=danio`)

Entradas: daño entrante, tipo(s), parte de daño verdadero, bloqueo o choque
aplicado, vida máxima (por defecto, la propia).

- Defensa Pasiva (`RES`) para todo menos Espiritual; Fortaleza Espiritual
  (`VOL`) solo Espiritual; Verdadero sin mitigar; Perforante ignora 25 de
  Defensa Pasiva cada 100 de daño; dos tipos: mitad y mitad.
- Salidas: daño final, Regla de los Efectos (recibir al menos
  `ceil(daño total / 3)` — un tercio, no 0,33: con 200 son 67),
  efectos por tipo de daño, y si se supera Concentración (`VOL+RES`) o
  Umbral de Dolor (`5 × (VOL+RES)`).

### 5.9 Utilidades

Una mini calculadora por cada una, cada una con su `accion`:
**caída** (metros sobre el límite × 15 contundente), **asfixia**
(`floor(RES / 20)` turnos), **lanzamiento** (daño = Fuerza, + daño base si
es arma no arrojadiza; alcance `FUE / 2`; TA = Puntería), **agarre**
(Fuerza agresor vs Fuerza defensor + 10 por turno de forcejeo),
**iniciativa** (gana quien tenga el atributo más bajo más alto, desempate
con el segundo más bajo, luego "tirar 1d100"; el que gana empieza o decide
quién empieza — Guía líneas 52 y 130).

### 5.10 Registro y copiar al post

- Botón "Registrar acción" en el bloque de ataque:
  `hx-get="belico.php?accion=registrar"`, `hx-include` del bloque de ataque y
  de atributos, `hx-target="#registro"`, `hx-swap="beforeend"`. El servidor
  devuelve una fila ya renderizada (texto de la acción + su línea en BBCode
  en un `data-bbcode`).
- Quitar una fila: `hx-on:click="this.closest('.belico-fila').remove()"` —
  sin petición.
- "Copiar al post": el único JS propio de la página (unas 10 líneas en
  nowdoc) junta los `data-bbcode` de las filas en un bloque
  `[spoiler=Cálculos bélicos]...[/spoiler]` y lo copia con
  `navigator.clipboard.writeText`; si no se puede, lo muestra en un
  `<textarea>` para copiarlo a mano.
- El registro vive solo en la página: al recargar, se pierde (no hay estado
  en el servidor, §4.5).

## 6. Reglas en un solo lugar

Al principio del archivo, en PHP:

```php
$BELICO_REGLAS = [
    'multi_arma'       => ['dos' => 0.75, 'mas' => 0.60, 'ta_dos' => -10, 'ta_por_arma' => -5], // Guía 264-269
    'dos_manos'        => 1.5,                                                                 // 254
    'distancia_ta'     => ['cada_metros' => 20, 'penalizacion' => -5],                         // 309
    'sorpresa_ref'     => -20,                                                                 // 310
    'bloque_sin_cortar'=> 5,                                                                   // 96
    'mantenida_minima' => -45, // texto de la línea 311, no el ejemplo (100_Requirements §3.4)
    'regla_efectos'    => 3,    // divisor: ceil(daño / 3). No 0.33: con 200 la Guía pide 67, 0.33 daría 66 // 317
    'caida_por_metro'  => 15,                                                                  // 374
    'asfixia_cada'     => 20,                                                                  // 396
    'reacciones'       => [ /* tabla de 100_Requirements §3.4, de mayor a menor */ ],
];
```

Si la Guía cambia, se cambia en un solo lugar.

## 7. Casos límite

| Caso | Comportamiento |
|---|---|
| Invitado | Aviso "Debes iniciar sesión", sin datos ni consultas (también en peticiones htmx) |
| `fid` inexistente | Aviso, sin calculadora |
| `tid` sin snapshot para ese `fid` | Estadísticas actuales + aviso visible |
| `tid` de un foro que el usuario no puede ver | Se usan los datos; el título se muestra como "hilo #N" |
| Personaje sin armas ni técnicas | Golpe básico con Fuerza/Resistencia; selectores con "Sin armas"/"Sin técnicas" |
| Arma con `dano`/`bloqueo` sin fórmula reconocible | Se muestra el texto tal cual, sin número (igual que `calc.js`) |
| `accion` desconocida en una petición htmx | Fragmento con aviso, no un error de PHP |
| Entradas numéricas vacías o no numéricas | `(int)`/`(float)` con valor por defecto; nunca un warning |
| htmx no carga | La página completa ya trae los resultados por defecto; solo no se recalculan |
| Navegador sin `navigator.clipboard` | Texto en un `<textarea>` para copiar a mano |
| Pantalla ≤ 700px | Bloques apilados en una columna |

## 8. Pendientes

- **Atributo de Tasa de Acierto de cada arma.** La Guía de Objetos
  (`b06_objetos.md:122`) dice que cada arma tiene uno, pero
  `mybb_op_objetos` no tiene una columna para eso, y sin datos reales no se
  pudo confirmar si está escrito en algún texto (`requisitos`, `escalado`,
  `efecto`). Hasta confirmarlo, se elige a mano (§5.6).
- **Si un arma es de una o dos manos** tampoco tiene columna; casilla
  manual.
