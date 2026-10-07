# Mejoras al editor de posts (SCEditor): plan de diseño

> Depende de: [100_Requirements_SCEditor.md](100_Requirements_SCEditor.md)
>
> **Estado: por implementar.** Ninguno de los archivos nuevos descritos aquí
> existe todavía. `newreply.php`, `newthread.php` y `editpost.php` no se
> tocan en ningún punto de este plan (100_Requirements §2).

## 1. Objetivo

Reemplazar el JS copiado y pegado tres veces (contador de palabras,
recuperación de borrador) por un solo archivo compartido que usa la API
pública de SCEditor en vez de adivinar su DOM interno, agregar feedback
visual al contador, theming propio del foro al editor, y dos botones de
toolbar nuevos (`[dado]`, `[tecnica=TID]`) — todo en `.html`/`.css`/`.js`,
sin tocar ningún `.php`.

## 2. Archivos

| Archivo | Qué hace | Nuevo/existente |
|---|---|---|
| `jscripts/post_editor.js` | Archivo compartido: contador de palabras con feedback visual, recuperación de borrador, wiring a `valuechanged`/`getBody()` | Nuevo |
| `jscripts/sceditor-theme.css` | Theming del editor (`.sceditor-container`, toolbar, dropdowns) con los tokens de `opg-tokens.css` | Nuevo |
| `jscripts/bbcodes_sceditor.js` | Se le agregan dos comandos nuevos (`dado`, `tecnica`), mismo patrón que el de `spoiler` ya existente | Editado |
| `templates/One_Piece_Gaiden_Templates/codebuttons.html` | Se agregan `dado,tecnica` al string de `toolbar` | Editado |
| `templates/One_Piece_Gaiden_Templates/newreply.html` | Se quita el `<script>` inline viejo (contador + borrador), se agregan los `<link>`/`<script>` nuevos | Editado |
| `templates/One_Piece_Gaiden_Templates/newthread.html` | Ídem | Editado |
| `templates/One_Piece_Gaiden_Templates/editpost.html` | Ídem, más el contador (hoy no tiene recuperación de borrador — queda agregada, ver §6) | Editado |

Ningún archivo `.php` aparece en esta tabla — es la verificación concreta de
la decisión de 100_Requirements §2.

## 3. `jscripts/post_editor.js` (archivo compartido)

Reemplaza los tres bloques `<script>` inline de contador + borrador. Se
carga igual en las tres plantillas, **inmediatamente después de
`{$codebuttons}`** (que es donde se define `MyBBEditor`), no en el `<head>`:

```html
{$codebuttons}
<script src="{$mybb->settings['bburl']}/jscripts/post_editor.js?ver=1"></script>
```

### 3.1 Esperar a que el editor exista

`{$codebuttons}` registra su propio `$(function() { $("#{$bind}").sceditor(opt_editor); MyBBEditor = ...; })` dentro de un `$(document).ready`. jQuery corre los callbacks de `ready` **en el orden en que se registraron**, que sigue el orden en que el navegador parsea los `<script>` del documento — no alcanza con envolver la inicialización de `post_editor.js` en su propio `$(function(){...})` si el `<script src="post_editor.js">` está en el `<head>`: ahí se parsea *antes* que el `<script>` inline de `{$codebuttons}` (que vive más abajo, dentro del `<body>`), así que su callback de `ready` se registraría primero y correría primero — antes de que `MyBBEditor` exista. **La posición del `<script>` en el HTML importa, no solo el `$(function(){...})` que lo envuelve** — por eso va pegado después de `{$codebuttons}` (§3, arriba), nunca en el `<head>`. Con esa posición correcta, sí alcanza con:

```js
(function () {
  'use strict';
  $(function () {
    if (!window.MyBBEditor) { return; } // no hay editor en esta página, no hacer nada
    PostEditor.init(MyBBEditor);
  });

  var PostEditor = {
    init: function (editor) {
      this._editor = editor;
      this._initContador();
      this._initBorrador();
    }
    // ...
  };
  window.PostEditor = PostEditor;
})();
```

### 3.2 Contador de palabras (100_Requirements §4.2)

```js
_initContador: function () {
  var span = document.getElementById('palabras');
  if (!span) { return; } // editpost.html lo tiene igual que newreply/newthread
  var self = this;
  var UMBRAL = 300; // único umbral fijo, sin distinguir por prefijo (100_Requirements §2)
  var timer = null;

  function recalcular() {
    var texto = self._editor.getBody().innerText || '';
    var palabras = texto.split(/\s+/).filter(function (w) { return w !== ''; }).length;
    span.textContent = palabras;
    span.classList.toggle('pe-cumple', palabras >= UMBRAL);
    span.classList.toggle('pe-falta', palabras < UMBRAL);
  }

  this._editor.bind('valuechanged', function () {
    clearTimeout(timer);
    timer = setTimeout(recalcular, 180); // debounce (100_Requirements §4.3.3)
  });
  recalcular(); // estado inicial (por si hay contenido precargado, ej. al editar)
}
```

`.pe-cumple`/`.pe-falta` son las dos clases que `sceditor-theme.css` colorea
(verde `--opg-verde-exito` / rojo `--opg-rojo-error`, mismos tokens que ya
usa el resto del foro para estados de éxito/error — `style.md` §2).

`getBody().innerText` reproduce el mismo conteo que el código legacy
(`contentDocument.body.innerText`), mismo comportamiento observable, solo
sin la ruta frágil para llegar ahí.

### 3.3 Recuperación de borrador (100_Requirements §3)

```js
_initBorrador: function () {
  var boton = document.getElementById('recuperar-post');
  if (!boton) { return; } // se agrega a editpost.html, ver §6 — así que esto deja de ser condicional ahí
  var self = this;

  this._editor.bind('valuechanged', function () {
    var body = self._editor.getBody();
    var texto = body.innerText || '';
    var palabras = texto.split(/\s+/).filter(function (w) { return w !== ''; }).length;
    if (palabras >= 10) {
      try { localStorage.setItem('user-post', body.innerHTML); } catch (e) { /* localStorage puede fallar (privado/bloqueado) — no romper el editor por esto */ }
    }
  });

  boton.addEventListener('click', function (e) {
    e.preventDefault();
    if (self._editor.inSourceMode && self._editor.inSourceMode()) {
      alert('Sólo puedes recuperar lo último escrito en el post si el botón de "Ver código de Fuente (<>)" no está apretado/activo.');
      return;
    }
    var guardado;
    try { guardado = localStorage.getItem('user-post'); } catch (e) { guardado = null; }
    if (guardado === null) { return; }
    var actual = self._editor.getBody().innerHTML;
    if (actual === guardado) {
      alert('El último texto guardado es idéntico al que está actual en la página. No hay nada que recuperar.');
    } else {
      self._editor.getBody().innerHTML = guardado;
    }
  });
}
```

`inSourceMode()` reemplaza el chequeo actual de
`$('.sceditor-button-source').hasClass('active')` — método público
equivalente, mismo propósito (no se puede recuperar HTML dentro del modo
"código fuente").

Los `try/catch` alrededor de `localStorage` son nuevos respecto al código
legacy (que no los tenía) — `localStorage` puede lanzar en navegación
privada o con cookies/storage bloqueado; sin esto, un error ahí rompería
todo el `valuechanged` handler (incluido el contador, que comparte el mismo
evento). Es la única adición de robustez que no estaba explícitamente
pedida en el 100_Requirements, pero se deriva directo de unificar el
archivo (si un handler de `valuechanged` tira una excepción, no debe tumbar
a los demás que escuchan el mismo evento).

## 4. `jscripts/sceditor-theme.css` (theming, 100_Requirements §4.1)

Un solo archivo estático (mismo patrón que `jscripts/select2/select2.css`,
ya referenciado así en varios templates del core), cargado igual en las
tres plantillas:

```html
<link rel="stylesheet" href="{$mybb->asset_url}/jscripts/sceditor-theme.css?ver=1" />
```

Apunta a las clases reales que SCEditor genera (confirmado leyendo
`jquery.sceditor.min.js`, 100_Requirements §3):

```css
.sceditor-container {
  border: 2px solid var(--opg-tinta);
  border-radius: var(--opg-radio-sm);
  background: var(--opg-crema-clara);
}
.sceditor-toolbar {
  background: var(--opg-crema-clara);
  border-bottom: 2px solid var(--opg-tinta);
}
.sceditor-button {
  border-radius: var(--opg-radio-sm);
  transition: transform var(--opg-rapido);
}
.sceditor-button:hover {
  transform: scale(1.1); /* mismo "pop" al hover que el resto del foro, style.md §4 */
}
.sceditor-button:focus-visible {
  outline: none;
  box-shadow: 0 0 0 2px var(--opg-foco); /* halo dorado de foco por teclado, style.md §7 */
}
/* Los dropdowns (spoiler, fuente, color, tamaño) ya viven dentro de
   .sceditor-container (100_Requirements §3) — este selector alcanza para
   los tres, no hace falta apuntar a .sceditor-dropdown aparte. */
.sceditor-dropdown {
  border: 2px solid var(--opg-tinta);
  border-radius: var(--opg-radio-sm);
  background: var(--opg-crema-clara);
}

/* Feedback visual del contador (100_Requirements §4.2) */
#palabras.pe-cumple { color: var(--opg-verde-exito); font-weight: bold; }
#palabras.pe-falta   { color: var(--opg-rojo-error); }
```

No toca `cache/themes/` ni el `editortheme` del ACP (100_Requirements §2) —
es un archivo estático nuevo, aparte del skin de fábrica que SCEditor sigue
cargando primero; este CSS solo sobrescribe encima.

## 5. `jscripts/bbcodes_sceditor.js`: activar `[dado]` y `[tecnica=TID]` (100_Requirements §4.4)

**No se escriben comandos nuevos.** `dado` (línea 826) y `tecnica` (línea
609) ya existen en este archivo, con dropdown y todo — solo nunca se
activaron en la toolbar, y `dado` tiene un bug de sintaxis que hay que
corregir primero (100_Requirements §3).

### 5.1 `tecnica`: sin cambios en el archivo

Ya inserta `[tecnica=' + mod + ']'` — exactamente lo que espera
`BBCustom_tecnica.php`. No se toca nada de este comando, solo se activa en
el `toolbar` (§5.3).

### 5.2 `dado`: corregir el insert y agregar límites

```diff
  $content.find('.button').on('click', function (e) {
      var mod1 = $content.find('#mod1').val();
      var mod2 = $content.find('#mod2').val();
      var attrs = "";

      if (mod1 !== undefined && mod1 !== "" && mod2 !== undefined && mod2 !== "") {
-         editor.insert(`[dado=${mod1}d${mod2}]`);
+         var cant = Math.min(20, Math.max(1, parseInt(mod1, 10) || 1));
+         var caras = Math.min(100000, Math.max(1, parseInt(mod2, 10) || 1));
+         editor.insert(`[${cant}d${caras}]`);
      }

      editor.closeDropDown(true);
      e.preventDefault();
  });
```

Y en los `<input>` del dropdown (mismo bloque, arriba del `.on('click')`),
agregar los atributos `min`/`max` que hoy no tienen:

```diff
- '<input type="number" id="mod1" value="1" />' +
+ '<input type="number" id="mod1" value="1" min="1" max="20" />' +
  ...
- '<input type="number" id="mod2" value="6" />' +
+ '<input type="number" id="mod2" value="6" min="1" max="100000" />' +
```

El `min`/`max` del `<input>` es el límite "blando" del navegador (se puede
evadir escribiendo a mano o pegando); el `Math.min`/`Math.max` dentro del
handler del click es el límite real que importa — evita que llegue al
servidor un `[25d500000]` aunque alguien haya tocado el campo fuera de los
límites visuales. Mismos rangos que valida `BBCustom_dado.php:142`
(1-20 dados, 1-100000 caras).

### 5.3 `codebuttons.html`: activar los dos en la toolbar

```diff
- toolbar: "{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link},spoiler|video{$emoticon}|{$list}quote|maximize,source",
+ toolbar: "{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link},spoiler,dado,tecnica|video{$emoticon}|{$list}quote|maximize,source",
```

`[tecnica=TID]` pide el TID en texto plano, sin buscador — decisión
explícita (100_Requirements §4.4), un typeahead real queda fuera de este
piloto.

## 6. Las tres plantillas: qué cambia

Mismo patrón en `newreply.html`, `newthread.html` y `editpost.html`:

1. En el `<head>`, agregar **solo** el `<link>` de `sceditor-theme.css`
   (§4), después de `{$headerinclude}` — es CSS, no depende de ningún
   orden de ejecución.
2. **El `<script src="post_editor.js">` va pegado inmediatamente después
   de `{$codebuttons}`, nunca en el `<head>`** (§3.1 explica por qué: el
   orden de los `<script>` en el HTML determina el orden real de los
   callbacks de `ready`, y `{$codebuttons}` es quien define `MyBBEditor`).
3. Quitar el `<script>` inline viejo (contador + borrador) — todo el bloque
   que hoy manipula `.sceditor-container` a mano.
4. **`editpost.html` no tiene hoy el botón "Recuperar post" ni su `id="recuperar-post"`** (100_Requirements §3, asimetría encontrada). Se agrega aquí, mismo markup que ya usan `newreply.html`/`newthread.html`:
   ```html
   <button id="recuperar-post" class="button">Recuperar post</button>
   ```
   `_initBorrador()` en `post_editor.js` ya maneja el caso de que el botón
   no exista (`if (!boton) { return; }`) — agregar el botón aquí es lo único
   que falta para que el archivo compartido active la misma feature en las
   tres páginas.
5. El `id="palabras"` (span del contador) y el texto de mínimos se quedan
   igual en las tres — no cambia el HTML de esa parte, solo el JS que lo
   actualiza.

## 7. Casos límite y fallos

| Caso | Comportamiento |
|---|---|
| Página sin editor activo (ej. `MyBBEditor` nunca se definió) | `PostEditor.init()` nunca corre (`if (!window.MyBBEditor) { return; }`) — no tira error |
| `editpost.html` editando una respuesta (no el primer post) | Igual que hoy: sin `$prefixselect`, pero ya no importa — el contador no usa prefijo (100_Requirements §2) |
| `localStorage` bloqueado o lleno | `try/catch` en lectura y escritura (§3.3) — el contador y el resto de `valuechanged` siguen funcionando aunque el borrador falle |
| Usuario en modo "código fuente" (`source`) y aprieta "Recuperar post" | Mismo aviso de hoy, vía `inSourceMode()` en vez de chequear la clase CSS del botón a mano |
| `[dado]` con cantidad/caras fuera de rango tipeado a mano | Se recorta a 1-20 / 1-100000 antes de insertar, nunca llega al servidor un valor inválido |
| `[tecnica=TID]` con un TID que no existe | Mismo comportamiento de hoy del lado del servidor (`BBCustom_tecnica.php` ya muestra "Técnica no encontrada: X") — el botón activado no valida el TID, solo inserta el BBCode |

## 8. Pendientes

- Typeahead real para `[tecnica=TID]` (buscar por nombre en vez de tipear
  el TID a mano) — necesita un endpoint nuevo, que por la regla de
  100_Requirements §2 debe ir en un plugin, no en un `.php` de página. Fuera
  de este piloto.
- Enforzar el mínimo de palabras del lado del servidor, autosave real al
  borrador de la base de datos, o cualquier otra mejora que necesite PHP —
  explícitamente descartadas (ver conversación de 100_Requirements), no
  forman parte de ningún documento de este alcance.
- **Los seis comandos huérfanos de `bbcodes_sceditor.js`** (`haki`,
  `personaje`, `arma`, `vida`, `energia`, `belico` — 100_Requirements §3):
  no tienen `BBCustom_*.php` detrás, se conectan en cambio con
  `inc/plugins/OLD_codigos_rol.php` (posiblemente todavía activo pese al
  nombre) y `op/api_combate.php`. No se tocan en este documento — es un
  sistema aparte, más grande, que merece su propio `100_Requirements` si
  alguna vez se decide revisarlo.
