# Mejoras al editor de posts (SCEditor): plan de implementación

> Requisitos: [100_Requirements_SCEditor.md](100_Requirements_SCEditor.md)
>
> Diseño: [200_DesignPlan_SCEditor.md](200_DesignPlan_SCEditor.md)

## 1. Objetivo de este documento

Convierte el diseño en tareas de código ordenadas y verificables. Ningún
`.php` aparece en ninguna tarea — confirmado en el diseño, se reconfirma aquí
en cada tarea.

La implementación se considera terminada cuando:

- `newreply.html`, `newthread.html` y `editpost.html` cargan un solo archivo
  JS compartido (`jscripts/post_editor.js`) para el contador de palabras y
  la recuperación de borrador, en vez de tener cada una su propia copia;
- el contador tiene feedback visual (color) contra un único umbral fijo, sin
  distinguir por prefijo del hilo (100_Requirements §2);
- `editpost.html` tiene el botón "Recuperar post" que hoy le falta (100_Requirements §3);
- el editor usa `MyBBEditor.getBody()` y el evento `valuechanged` en vez de
  adivinar la posición del iframe de SCEditor o comparar `before`/`after` a
  mano en cada `keyup`;
- `[dado]` y `[tecnica=TID]` aparecen como botones reales en la toolbar —
  `tecnica` activado tal cual estaba, `dado` con su bug de sintaxis
  corregido (insertaba `[dado=XdY]`, un BBCode que `BBCustom_dado.php` nunca
  reconoció) y con límites 1-20/1-100000 aplicados;
- el editor tiene theming propio del foro (`jscripts/sceditor-theme.css`)
  sobre `.sceditor-container`, su toolbar y sus dropdowns;
- `newreply.php`, `newthread.php` y `editpost.php` no aparecen en el diff.

## 2. Punto de partida

Tres plantillas con JS casi idéntico copiado a mano
(`newreply.html:60-89`, `newthread.html:197-228`, `editpost.html:83-95` —
esta última sin el bloque de recuperación de borrador), más dos comandos de
SCEditor ya escritos pero nunca conectados a la toolbar
(`jscripts/bbcodes_sceditor.js:609` y `:826`). No hay página de
configuración ni base de datos de por medio — todo el trabajo es JS/CSS
estático más cuatro plantillas editadas.

## 3. Archivos

### 3.1 Crear

| Archivo | Contenido |
|---|---|
| `jscripts/post_editor.js` | Archivo compartido: contador de palabras + recuperación de borrador, sobre `MyBBEditor.getBody()`/`valuechanged` |
| `jscripts/sceditor-theme.css` | Theming de `.sceditor-container`, toolbar y dropdowns con los tokens de `opg-tokens.css` |

### 3.2 Modificar

| Archivo | Cambio |
|---|---|
| `jscripts/bbcodes_sceditor.js` | Corregir el insert de `dado` (línea 826) y agregarle `min`/`max` a sus dos `<input>` |
| `templates/One_Piece_Gaiden_Templates/codebuttons.html` | Agregar `dado,tecnica` al string de `toolbar` |
| `templates/One_Piece_Gaiden_Templates/newreply.html` | `<link>` nuevo en el head, `<script>` nuevo después de `{$codebuttons}`, quitar el `<script>` inline viejo |
| `templates/One_Piece_Gaiden_Templates/newthread.html` | Ídem |
| `templates/One_Piece_Gaiden_Templates/editpost.html` | Ídem, más agregar el botón `id="recuperar-post"` que hoy no tiene |

### 3.3 No se toca

- `newreply.php`, `newthread.php`, `editpost.php` — ningún `.php` (100_Requirements §2).
- `jscripts/belico/panel.js`, `jscripts/belico/calc.js` — la Calculadora
  Bélica, aparte del editor (100_Requirements §3). El desfase de versión
  (`?v=24` vs `?v=20`) encontrado entre `newreply.html` y `newthread.html`
  **no se corrige aquí** — no es parte de este alcance, solo se anotó al
  encontrarlo.
- Los seis comandos huérfanos de `bbcodes_sceditor.js` (`haki`, `personaje`,
  `arma`, `vida`, `energia`, `belico`) — fuera de alcance, ver
  200_DesignPlan §8.
- `jquery.sceditor.min.js`, `jquery.sceditor.bbcode.min.js` — el vendor de
  SCEditor en sí, no se parchea.

### 3.4 Sincronización

Las cuatro plantillas (`codebuttons.html` + las tres de post) se sincronizan
a mano con la base (`import_templates.php`), como el resto del proyecto. Los
archivos `jscripts/*.js`/`*.css` son estáticos — se ven apenas se suben al
servidor, sin paso de sincronización, pero sí pueden quedar cacheados por el
navegador si no se sube el `?ver=N` al cambiarlos otra vez después de este
trabajo.

## 4. Convenciones

- **Nada de `.php`.** Si en algún punto de la implementación parece que hace
  falta tocar un `.php`, es señal de que algo se escapó del alcance — parar
  y revisar contra 100_Requirements §2 antes de seguir.
- **La regla de "sin `$` sueltos" es solo para plantillas `.html` de MyBB**
  (`$templates->get()` + `eval()`), no aplica a `jscripts/post_editor.js` ni
  a `jscripts/sceditor-theme.css` — son archivos estáticos servidos tal
  cual, nunca pasan por el intérprete de templates. `$` de jQuery se usa
  libremente ahí.
- Los `<link>`/`<script>` nuevos llevan `?ver=1` (mismo patrón de
  cache-busting que ya usan `jscripts/select2/select2.css?ver=1807` o
  `jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1832`) — subir el
  número en cualquier cambio futuro a estos dos archivos.
- Español neutro en cualquier texto nuevo de cara al usuario (tooltips de
  los botones de toolbar, mensajes de alerta).
- `getBody()`/`valuechanged`/`insert()`/`createDropDown()`/`closeDropDown()`/
  `inSourceMode()` son los únicos puntos de contacto con la API de SCEditor
  que usa este trabajo — todos confirmados como métodos públicos reales
  leyendo `jquery.sceditor.min.js` (100_Requirements §3), no inventados.

## 5. Tareas

### Tarea 1: `jscripts/post_editor.js`

**Archivo:** `jscripts/post_editor.js` (nuevo)

```js
(function () {
  'use strict';

  var PostEditor = {
    init: function (editor) {
      this._editor = editor;
      this._initContador();
      this._initBorrador();
    },

    _initContador: function () {
      var span = document.getElementById('palabras');
      if (!span) { return; }
      var self = this;
      var UMBRAL = 300; // único umbral fijo, sin prefijo (100_Requirements §2)
      var timer = null;

      function recalcular() {
        var texto = self._editor.getBody().innerText || '';
        var palabras = texto.split(/\s+/).filter(function (w) { return w !== ''; }).length;
        span.textContent = palabras;
        span.classList.toggle('pe-cumple', palabras >= UMBRAL);
        span.classList.toggle('pe-falta', palabras < UMBRAL);
      }

      self._editor.bind('valuechanged', function () {
        clearTimeout(timer);
        timer = setTimeout(recalcular, 180); // debounce
      });
      recalcular();
    },

    _initBorrador: function () {
      var boton = document.getElementById('recuperar-post');
      if (!boton) { return; }
      var self = this;

      self._editor.bind('valuechanged', function () {
        var body = self._editor.getBody();
        var texto = body.innerText || '';
        var palabras = texto.split(/\s+/).filter(function (w) { return w !== ''; }).length;
        if (palabras >= 10) {
          try { localStorage.setItem('user-post', body.innerHTML); } catch (e) { /* storage bloqueado, no romper el editor */ }
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
  };

  window.PostEditor = PostEditor;

  $(function () {
    if (!window.MyBBEditor) { return; } // página sin editor activo
    PostEditor.init(MyBBEditor);
  });
})();
```

**Verificación:** `node --check jscripts/post_editor.js` (o cargarlo en el
navegador y confirmar que no tira error de sintaxis en consola) antes de
subirlo.

### Tarea 2: `jscripts/sceditor-theme.css`

**Archivo:** `jscripts/sceditor-theme.css` (nuevo)

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
  transform: scale(1.1);
}
.sceditor-button:focus-visible {
  outline: none;
  box-shadow: 0 0 0 2px var(--opg-foco);
}
.sceditor-dropdown {
  border: 2px solid var(--opg-tinta);
  border-radius: var(--opg-radio-sm);
  background: var(--opg-crema-clara);
}

#palabras.pe-cumple { color: var(--opg-verde-exito); font-weight: bold; }
#palabras.pe-falta   { color: var(--opg-rojo-error); }
```

**Verificación:** cargar `newreply.php` después de subir el archivo (§7) y
confirmar visualmente borde negro + fondo crema en el editor, hover con
"pop" en los botones de la toolbar.

### Tarea 3: corregir `dado` en `jscripts/bbcodes_sceditor.js`

**Archivo:** `jscripts/bbcodes_sceditor.js` (editado, línea 826 en adelante)

```diff
            $content.find('.button').on('click', function (e) {
                var mod1 = $content.find('#mod1').val();
                var mod2 = $content.find('#mod2').val();
                var attrs = "";

                if (mod1 !== undefined && mod1 !== "" && mod2 !== undefined && mod2 !== "") {
-                   editor.insert(`[dado=${mod1}d${mod2}]`);
+                   var cant = Math.min(20, Math.max(1, parseInt(mod1, 10) || 1));
+                   var caras = Math.min(100000, Math.max(1, parseInt(mod2, 10) || 1));
+                   editor.insert(`[${cant}d${caras}]`);
                }

                editor.closeDropDown(true);
                e.preventDefault();
            });
```

Y en el `$content` del mismo bloque (unas líneas arriba):

```diff
- '<input type="number" id="mod1" value="1" />' +
+ '<input type="number" id="mod1" value="1" min="1" max="20" />' +
  ...
- '<input type="number" id="mod2" value="6" />' +
+ '<input type="number" id="mod2" value="6" min="1" max="100000" />' +
```

`tecnica` (línea 609) **no se toca** — ya inserta `[tecnica=' + mod + ']'`
correctamente.

**Verificación:** `node --check jscripts/bbcodes_sceditor.js`. Manual: con
el comando ya en la toolbar (Tarea 4), probar `[dado]` con 2 y 20 en los
campos → debe insertar `[2d20]` en el editor, no `[dado=2d20]`; probar con
999 caras tipeado a mano → debe insertar `[2d100000]`, recortado.

### Tarea 4: activar `dado,tecnica` en la toolbar

**Archivo:** `templates/One_Piece_Gaiden_Templates/codebuttons.html` (línea 44)

```diff
- toolbar: "{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link},spoiler|video{$emoticon}|{$list}quote|maximize,source",
+ toolbar: "{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link},spoiler,dado,tecnica|video{$emoticon}|{$list}quote|maximize,source",
```

**Verificación:** `grep -no '\$[a-zA-Z_][a-zA-Z0-9_]*' templates/One_Piece_Gaiden_Templates/codebuttons.html`
no debe mostrar nada nuevo fuera de las interpolaciones `{$...}` que ya
existían (esta línea no agrega ningún `$` suelto, solo texto plano dentro
de las comillas). Visual: tras importar el template, aparecen dos íconos
nuevos en la toolbar de `newreply.php`/`newthread.php`/`editpost.php`, al
lado del de spoiler.

### Tarea 5: `newreply.html`

**Archivo:** `templates/One_Piece_Gaiden_Templates/newreply.html`

1. En el `<head>` (después de `{$headerinclude}`, línea 4):
   ```diff
     {$headerinclude}
   + <link rel="stylesheet" href="{$mybb->asset_url}/jscripts/sceditor-theme.css?ver=1" />
     {$post_javascript}
   ```
2. Después de `{$codebuttons}` (línea 35):
   ```diff
     {$codebuttons}
   + <script src="{$mybb->settings['bburl']}/jscripts/post_editor.js?ver=1"></script>
     {$multiquote_external}
   ```
3. Quitar el bloque de minimo-de-palabras-widget que hoy va junto al
   `<textarea>` **no** — ese `<div>` con el `id="palabras"` y el texto de
   mínimos **se queda igual**, solo cambia el JS que lo actualiza. Lo único
   que se borra es el `<script>` completo al final del `<body>` (líneas
   55-90: `sleep()`, el handler de `#recuperar-post`, `contarPalabras()`).

**Verificación:** `grep -no '\$[a-zA-Z_][a-zA-Z0-9_]*' templates/One_Piece_Gaiden_Templates/newreply.html`
solo interpolaciones intencionales. Visual: abrir `newreply.php` sobre un
hilo real, escribir, ver el contador cambiar de color al pasar 300
palabras, probar "Recuperar post" tras refrescar la página a medio escribir.

### Tarea 6: `newthread.html`

**Archivo:** `templates/One_Piece_Gaiden_Templates/newthread.html`

Mismo patrón que la Tarea 5 (`<link>` después de `{$headerinclude}`,
`<script src="post_editor.js">` después de `{$codebuttons}`), con un
cuidado extra: **el `<script>` de este archivo (líneas 83-229) no es solo
el contador/borrador.** Contiene mezcladas funciones de tiradas de
Aventura — `getRandomInt`, `tirarDireccionViento`, `tirarClima`,
`tirarEncuentro` (líneas 84-191) — que **no se tocan**.

Borrar **solo** las líneas 193-228 de este bloque (confirmado con
`grep`/lectura directa, no por los rangos que había anotado antes en
100_Requirements §3, que estaban incompletos):

- `sleep()` (193-195) — verificado que solo la usa `contarPalabras()`
  (`await sleep(100)`, línea 211); ninguna función de clima/viento la
  llama, se puede borrar junto con el resto sin romper nada.
- El handler de `#recuperar-post` (197-207).
- `contarPalabras()` y su llamada (210-228).

El `<script>` **se queda abierto** con las cuatro funciones de Aventura
adentro (83-192) — no se cierra ni se borra la etiqueta en sí, solo el
contenido de 193 en adelante hasta `contarPalabras();` inclusive.

Hay un segundo `<script type="text/javascript">` en la línea 230
(pestañas de avatar, cierra en 434) — completamente aparte, no se toca.

El `id="palabras"` y el botón `id="recuperar-post"` (en el HTML, no el
`<script>`) ya existen aquí, se quedan igual.

**Verificación:** además de lo de la Tarea 5, confirmar que las tiradas de
clima/viento/encuentro de Aventura (si esta página las expone en algún
flujo real) siguen funcionando después del cambio — no deberían verse
afectadas en absoluto, pero vale la pena un chequeo visual rápido ya que
quedan en el mismo `<script>` que se edita.

### Tarea 7: `editpost.html`

**Archivo:** `templates/One_Piece_Gaiden_Templates/editpost.html`

Mismo patrón que la Tarea 5, con una diferencia: **agregar el botón
"Recuperar post"**, que hoy no existe aquí (100_Requirements §3, asimetría
encontrada):

```html
<button id="recuperar-post" class="button">Recuperar post</button>
```

Mismo lugar donde `newreply.html`/`newthread.html` lo tienen (junto a los
botones de enviar/preview).

**Cuidado igual que en la Tarea 6**: el `<script>` de este archivo (líneas
78-355) tampoco es solo el contador — tiene mezclado "Avatar tab switching"
(97-124) y un modal de Akuma (126 en adelante). Borrar **solo 79-95**
(`sleep()`, `contarPalabras()` y su llamada — rango corregido respecto al
`editpost.html:83-95` que tenía anotado 100_Requirements §3, que no incluía
`sleep()`), dejando el `<script>` abierto con todo lo demás intacto desde
la línea 97. `post_editor.js` ya maneja que el botón exista o no
(`_initBorrador()` hace `if (!boton) { return; }`) — agregar el botón es lo
único que falta para que esta página gane la misma feature que las otras
dos.

**Verificación:** igual que Tarea 5, sobre `editpost.php`, probando además
que "Recuperar post" (que antes no existía aquí) funciona editando un post
real.

## 6. Pruebas

### 6.1 Comprobaciones estáticas

- `node --check` sobre `post_editor.js` y `bbcodes_sceditor.js`.
- `grep -no '\$[a-zA-Z_][a-zA-Z0-9_]*'` sobre las cuatro plantillas
  tocadas — solo interpolaciones `{$variable}` intencionales, nada nuevo
  fuera de lo que ya existía.
- `grep -rn "children\[1\].contentDocument" templates/One_Piece_Gaiden_Templates/newreply.html templates/One_Piece_Gaiden_Templates/newthread.html templates/One_Piece_Gaiden_Templates/editpost.html`
  no debe devolver nada — confirma que la manipulación frágil del DOM se
  borró de las tres, no que se duplicó por error.

### 6.2 Con el navegador (manual)

| # | Caso | Esperado |
|---|---|---|
| 1 | Escribir un post en `newreply.php` hasta pasar 300 palabras | El número en `#palabras` cambia a verde al cruzar el umbral |
| 2 | Escribir menos de 300 y refrescar la página sin guardar | "Recuperar post" trae de vuelta lo escrito |
| 3 | Mismo caso en `editpost.php` | Funciona igual — antes esta página no tenía el botón |
| 4 | Activar modo "código fuente" (`<>`) y apretar "Recuperar post" | Aviso de que no se puede recuperar en ese modo, mismo texto de hoy |
| 5 | `localStorage` bloqueado (ej. navegación privada estricta, o deshabilitado a mano en devtools) | El contador sigue funcionando; "Recuperar post" no rompe la página |
| 6 | Click en el botón de `[dado]` de la toolbar, cargar 2/20, insertar | Aparece `[2d20]` en el editor, no `[dado=2d20]` |
| 7 | Postear con un `[XdY]` insertado así | `BBCustom_dado.php` lo procesa igual que si se hubiera tipeado a mano — tira los dados, muestra el resultado |
| 8 | Click en `[tecnica=TID]`, tipear un TID real, insertar y postear | Se muestra la técnica (mismo comportamiento de hoy, ya funcionaba al tipear el BBCode a mano) |
| 9 | `[tecnica=TID]` con un TID inventado | "Técnica no encontrada: X" — comportamiento del servidor, sin cambios |
| 10 | Visual del editor en las tres páginas | Borde negro, fondo crema, hover "pop" en los botones — consistente con el resto del foro |
| 11 | `opg/tirada_cofre2.php`, `op/staff/cofres_*.php`, cualquier otra herramienta ya existente | Sin cambios de comportamiento — este trabajo no las toca |

## 7. Despliegue

### 7.1 Antes de subir

- Revisar el diff completo: dos archivos nuevos (`post_editor.js`,
  `sceditor-theme.css`), cuatro plantillas editadas, un archivo JS vendor
  (`bbcodes_sceditor.js`) con un fix puntual.
- Confirmar que no aparece ningún `.php` en el diff.

### 7.2 Orden

1. Subir `jscripts/post_editor.js` y `jscripts/sceditor-theme.css` (no
   rompen nada mientras ninguna plantilla los referencie todavía).
2. Subir el fix de `jscripts/bbcodes_sceditor.js` (Tarea 3) — tampoco rompe
   nada mientras `dado` no esté en la toolbar.
3. Sincronizar `codebuttons.html` (Tarea 4) vía `import_templates.php` —
   recién aquí aparecen los botones de `dado`/`tecnica` en producción.
4. Sincronizar `newreply.html`, `newthread.html`, `editpost.html` (Tareas
   5-7) — recién aquí se activa el contador/borrador unificado y deja de
   usarse el `<script>` inline viejo.

### 7.3 Rollback

- Sin base de datos de por medio — revertir es restaurar las plantillas y
  los dos archivos `jscripts/*` desde git, o simplemente dejar de
  sincronizar las plantillas nuevas (las páginas vuelven al `<script>`
  inline viejo, que nunca se borró del historial de git).
- El fix de `bbcodes_sceditor.js` es seguro de revertir en cualquier
  momento — `dado` simplemente vuelve a estar roto (como ya lo estaba antes
  de este trabajo), no se pierde nada que estuviera funcionando.

## 8. Orden recomendado de commits

1. `docs: requisitos, diseño e implementación de mejoras al editor de posts` (los tres documentos).
2. `feat: jscripts/post_editor.js y sceditor-theme.css`.
3. `fix: BBCode de [dado] insertaba sintaxis que BBCustom_dado.php no reconocía`.
4. `feat: activar [dado] y [tecnica=TID] en la toolbar del editor`.
5. `refactor: unificar contador de palabras y recuperación de borrador en newreply/newthread/editpost`.

No commit sin que el usuario lo pida.

## 9. Fuera de alcance

- Typeahead de búsqueda para `[tecnica=TID]` — necesita un endpoint nuevo,
  queda para un plugin aparte (100_Requirements §4.4, 200_DesignPlan §8).
- Enforzar el mínimo de palabras del lado del servidor, autosave real al
  borrador de la base de datos — descartado explícitamente, no necesita
  PHP para este alcance.
- Los seis comandos huérfanos de `bbcodes_sceditor.js` (`haki`,
  `personaje`, `arma`, `vida`, `energia`, `belico`) y el sistema aparte al
  que se conectan (`OLD_codigos_rol.php`, `op/api_combate.php`).
- El desfase de versión entre `calc.js?v=24` y `calc.js?v=20` — no es
  parte del editor, solo se anotó al encontrarlo.
- Corregir el mínimo de palabras real de "Frutas"/por prefijo — se decidió
  ignorar esa relación (100_Requirements §2).
