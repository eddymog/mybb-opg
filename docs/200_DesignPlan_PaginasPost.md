# Rediseño visual de las páginas de post: plan de diseño

> Depende de: [100_Requirements_PaginasPost.md](100_Requirements_PaginasPost.md)
>
> **Estado: por implementar.** Construido sobre el trabajo ya hecho del
> editor SCEditor (los tres `*_SCEditor.md`) — ese trabajo no se revierte
> salvo el punto explícito de la Tarea 4 (botones de BBCode en la toolbar),
> ver §5.

## 1. Objetivo

Pasar `newreply.html`, `newthread.html` y `editpost.html` del marco
genérico de MyBB al marco de tres fondos + componentes de `style.md`
(`.barra-op`, `.opg-chip`, `.btn-op`, `.af-field`), con ancho propio más
amplio que el estándar de 1030px, mobile-friendly, y sacar los botones de
BBCode (`spoiler`/`dado`/`tecnica`) de la toolbar nativa de SCEditor a una
fila de `.opg-chip` fuera del editor.

## 2. Archivos

| Archivo | Qué cambia |
|---|---|
| `templates/One_Piece_Gaiden_Templates/newreply.html` | Marco, encabezados, widget de contador, fila de acciones, botones BBCode externos |
| `templates/One_Piece_Gaiden_Templates/newthread.html` | Ídem + prefijo/fecha a `.af-field` |
| `templates/One_Piece_Gaiden_Templates/editpost.html` | Ídem (sin recrear lo que ya tiene distinto — el CSS de `.post.classic` queda igual, confirmado que no choca, 100_Requirements §4) |
| `templates/One_Piece_Gaiden_Templates/codebuttons.html` | Revertir Tarea 4 del 300 anterior: sacar `dado,tecnica` (y `spoiler`) del string `toolbar` |
| `jscripts/post_editor.js` | Se le agrega el manejo de los tres botones externos nuevos |
| `jscripts/newpost-frame.css` (nuevo) | Ancho propio del marco de tres fondos para estas tres páginas |

`jscripts/sceditor-theme.css` y `jscripts/bbcodes_sceditor.js` (el fix de
`dado`) **no se tocan** — siguen siendo válidos tal cual quedaron. Los
comandos `dado`/`tecnica` en `bbcodes_sceditor.js` tampoco se borran: solo
dejan de estar en el `toolbar`. El `_dropDown()` de cada uno sigue siendo
la referencia de qué campos pedir (§5).

## 3. Marco de tres fondos con ancho propio

```html
{$header}
<div class="indice newpost-pagina">
<div class="mainBackground">
<div class="secondBackground">
<div class="thirdBackground">
  ...contenido actual de la tabla, reemplazado por los componentes de abajo...
</div>
</div>
</div>
</div>
{$footer}
```

```css
.newpost-pagina .thirdBackground { width: 100%; max-width: 1300px; }
@media (max-width: 700px) {
  .newpost-pagina .secondBackground, .newpost-pagina .thirdBackground { padding: 10px; }
}
```

1300px es un primer valor razonable (más espacio que el estándar de
1030px, sin ocupar toda la pantalla en monitores grandes) — ajustable al
ver cómo se siente con contenido real, no es un número con significado
especial.

## 4. Componentes

### 4.1 Encabezado

```html
<div class="barra-op bbox">
  <span class="barra-texto-op">Enviar nueva respuesta</span>
</div>
<div class="barra-espacio-op bbox">
  Respuesta al tema: <strong>{$subject}</strong>
</div>
```

Mismo patrón para "Responder al tema" (`newthread.html`) y "Editar post"
(`editpost.html`), con el texto correspondiente.

### 4.2 Widget de contador

```html
<div class="opg-chip" id="pe-contador" style="pointer-events:none;">
  Mínimo: 300 palabras — <span id="palabras">0</span>
</div>
```

El texto de los tres mínimos por tipo de post
("Post: 300 | Autonarradas: 800 | Frutas: Normativa") se queda, pero deja
de ir en un `<div>` con grises hardcodeados — pasa a texto simple arriba
del chip, o a un `title=""` con el detalle si se prefiere más compacto.
`post_editor.js` no cambia su lógica (100_Requirements §4.3) — sigue
siendo `#palabras.pe-cumple`/`.pe-falta`, ya coloreado.

### 4.3 Fila de acciones

```html
<div class="newpost-acciones">
  <button type="submit" class="btn-op btn-op--primario" name="submit">Enviar respuesta</button>
  <button type="submit" class="btn-op btn-op--sm" name="previewpost">Vista previa</button>
  {$savedraftbutton}
  <button type="button" class="btn-op btn-op--sm" id="recuperar-post">Recuperar post</button>
</div>
```

```css
.newpost-acciones { display: flex; flex-wrap: wrap; gap: var(--opg-espacio-3); align-items: center; }
```

`{$savedraftbutton}` ya trae su propio `<input>`, se envuelve pero no se
reescribe su markup. `flex-wrap` resuelve mobile sin un breakpoint aparte.

### 4.4 `newthread.html`: prefijo y fecha

```html
<div class="newpost-fila af-row">
  <div class="af-field">{$prefixselect}</div>
  <div class="af-field"><input type="text" name="subject" ... /></div>
</div>

<if $is_mundo_rol then>
<div class="newpost-fila af-row">
  <div class="af-field"><label>Estación</label><select name="estacion_input" ...>...</select></div>
  <div class="af-field"><label>Día (1-90)</label><input type="number" name="day_input" min="1" max="90" ... /></div>
  <div class="af-field"><label>Año</label><input type="number" name="year_input" min="700" max="725" ... /></div>
</div>
</if>
```

```css
.af-row { display: flex; flex-wrap: wrap; gap: var(--opg-espacio-3); }
```

Mismos `name`/`required`/`min`/`max` que ya tiene el HTML actual — cambia
el markup envolvente, no la validación ni los valores.

## 5. Botones de BBCode fuera del editor

### 5.1 Markup

```html
<div class="newpost-bbcodes">
  <button type="button" class="opg-chip" data-bbcode="spoiler">Spoiler</button>
  <button type="button" class="opg-chip" data-bbcode="dado">Dado</button>
  <button type="button" class="opg-chip" data-bbcode="tecnica">Técnica</button>
</div>
```

Va entre el editor y la fila de acciones, en las tres plantillas.

### 5.2 JS: modal propio con componentes OPG, insertar con `MyBBEditor.insert()`

**Decidido: modal propio, no `prompt()` nativo** — visualmente consistente
con el resto del sitio, reusando `.af-field`/`.btn-op`. Un solo modal
genérico en el HTML (las tres plantillas), cuyo contenido cambia según qué
chip se apretó — mismo patrón de "un formulario, campos condicionales
según tipo" que ya usa `staff_cofres_gestion_editor.html`.

```html
<div class="opg-modal-overlay" id="pe-bbcode-overlay" hidden>
  <div class="opg-modal" id="pe-bbcode-modal">
    <button type="button" class="opg-modal-cerrar" id="pe-bbcode-cerrar" aria-label="Cerrar">&times;</button>
    <h3 id="pe-bbcode-titulo"></h3>

    <div class="af-field pe-campo-dado">
      <label for="pe-dado-cantidad">¿Cuántos dados? (1-20)</label>
      <input type="number" id="pe-dado-cantidad" min="1" max="20" value="1">
    </div>
    <div class="af-field pe-campo-dado">
      <label for="pe-dado-caras">¿Cuántas caras? (1-100000)</label>
      <input type="number" id="pe-dado-caras" min="1" max="100000" value="6">
    </div>

    <div class="af-field pe-campo-tecnica">
      <label for="pe-tecnica-tid">ID de la técnica</label>
      <input type="text" id="pe-tecnica-tid">
    </div>

    <div class="af-field pe-campo-spoiler">
      <label for="pe-spoiler-titulo">Título del spoiler (opcional)</label>
      <input type="text" id="pe-spoiler-titulo">
    </div>

    <button type="button" class="btn-op btn-op--primario" id="pe-bbcode-insertar">Insertar</button>
  </div>
</div>
```

Los tres grupos `.pe-campo-*` se muestran/ocultan según el tipo (mismo
patrón `display:none` + `.pe-activo` que ya usa `staff_cofres_gestion_editor.html`
para sus campos condicionales por tipo de recompensa), en vez de levantar
y destruir el modal cada vez.

```js
_initBBCodesExternos: function () {
  var self = this;
  var overlay = document.getElementById('pe-bbcode-overlay');
  var tipoActual = null;

  document.querySelectorAll('.newpost-bbcodes [data-bbcode]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      tipoActual = btn.dataset.bbcode;
      self._mostrarCamposBBCode(tipoActual);
      overlay.hidden = false;
    });
  });

  document.getElementById('pe-bbcode-cerrar').addEventListener('click', function () {
    overlay.hidden = true;
  });
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) { overlay.hidden = true; } // click afuera del modal
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !overlay.hidden) { overlay.hidden = true; }
  });

  document.getElementById('pe-bbcode-insertar').addEventListener('click', function () {
    self._insertarBBCode(tipoActual);
    overlay.hidden = true;
  });
},

_mostrarCamposBBCode: function (tipo) {
  var titulos = { dado: 'Lanzar dados', tecnica: 'Insertar técnica', spoiler: 'Insertar spoiler' };
  document.getElementById('pe-bbcode-titulo').textContent = titulos[tipo] || '';
  document.querySelectorAll('.pe-campo-dado, .pe-campo-tecnica, .pe-campo-spoiler').forEach(function (el) {
    el.style.display = 'none';
  });
  document.querySelectorAll('.pe-campo-' + tipo).forEach(function (el) {
    el.style.display = '';
  });
},

_insertarBBCode: function (tipo) {
  // El editor puede no tener foco/selección guardada si el usuario nunca
  // clickeó adentro antes de usar el chip externo — focus() arma una
  // posición válida antes de insert() (confirmado método público real en
  // jquery.sceditor.min.js: se.focus=function(...)).
  this._editor.focus();
  if (tipo === 'dado') {
    var c = Math.min(20, Math.max(1, parseInt(document.getElementById('pe-dado-cantidad').value, 10) || 1));
    var k = Math.min(100000, Math.max(1, parseInt(document.getElementById('pe-dado-caras').value, 10) || 1));
    this._editor.insert('[' + c + 'd' + k + ']');
  } else if (tipo === 'tecnica') {
    var tid = document.getElementById('pe-tecnica-tid').value.trim();
    if (!tid) { return; }
    this._editor.insert('[tecnica=' + tid + ']');
  } else if (tipo === 'spoiler') {
    var titulo = document.getElementById('pe-spoiler-titulo').value.trim();
    this._editor.insert(titulo ? '[spoiler]' + titulo + '[/spoiler]' : '[spoiler]', titulo ? null : '[/spoiler]');
  }
}
```

**Regresión aceptada, decisión explícita del usuario:** el comando
`[spoiler]` original (dentro de la toolbar nativa) envolvía el texto que
ya estuviera **seleccionado** en `[spoiler][/spoiler]` si había selección
al apretar el botón, y solo abría el formulario de título si no había
nada seleccionado. El modal externo **siempre** abre el formulario e
inserta fresco — no revisa si hay texto seleccionado, no lo envuelve.
Confirmado con el usuario que esta pérdida de funcionalidad es aceptable,
a cambio de la consistencia visual de mover los tres BBCode afuera del
editor.

CSS del modal (`.opg-modal-overlay`/`.opg-modal`, clases nuevas, no
existen hoy) — overlay + caja centrada con borde negro/sombra de cómic de
`style.md`, va en `jscripts/newpost-frame.css` junto al resto del CSS de
este trabajo. El patrón de **JS** (cerrar con click afuera comparando
`e.target === overlay`) sí tiene precedente real en el mismo
`editpost.html` (`#akumaModal`, `openAkumaModalPostbit()`/
`closeAkumaModalPostbit()`, línea 234) — mismo enfoque, clases CSS propias
nuevas.

### 5.3 `codebuttons.html`: revertir la Tarea 4 anterior

```diff
- toolbar: "{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link},spoiler,dado,tecnica|video{$emoticon}|{$list}quote|maximize,source",
+ toolbar: "{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link}|video{$emoticon}|{$list}quote|maximize,source",
```

`jscripts/bbcodes_sceditor.js` no se edita — los comandos siguen definidos
(sirven de referencia/no estorban estando sin usar, mismo estado que los
seis comandos huérfanos ya documentados en 100_Requirements_SCEditor §3),
simplemente ya no están en el `toolbar`.

## 6. Mobile

- `.thirdBackground` con `max-width` en vez de ancho fijo (§3).
- `.newpost-acciones`/`.af-row`/`.newpost-bbcodes` en `flex-wrap` — se
  apilan solos en ≤700px sin breakpoint propio para cada uno.
- Probar el editor (`.sceditor-container`, ya responsive por defecto de
  SCEditor) dentro del marco más angosto.

## 7. Casos límite y fallos

| Caso | Comportamiento |
|---|---|
| Click en "Dado"/"Técnica"/"Spoiler" sin JS de `post_editor.js` cargado (`MyBBEditor` no existe) | Los botones existen en el HTML pero no hacen nada — mismo patrón de `_initContador()`/`_initBorrador()`, que ya chequean `if (!window.MyBBEditor) return` |
| `prompt()` cancelado (botón Cancelar del navegador) | No inserta nada, no deja el editor en un estado raro |
| `editpost.html` con el CSS de `.post.classic` activo | No debería verse afectado — ese CSS apunta a `#posts_container`/`.post.classic`, la vista de posts previos, no a la tabla/marco del formulario de edición (confirmado, 100_Requirements §4) |
| Pantalla ≤700px | Marco al 100% de ancho, botones apilados, editor usable |

## 8. Pendientes

- Ajustar el valor de `max-width: 1300px` (§3) una vez que se vea con
  contenido real — no es un número final.
