# Rediseño visual de las páginas de post: plan de implementación

> Requisitos: [100_Requirements_PaginasPost.md](100_Requirements_PaginasPost.md)
>
> Diseño: [200_DesignPlan_PaginasPost.md](200_DesignPlan_PaginasPost.md)

## 1. Objetivo de este documento

Convierte el diseño en tareas ordenadas. Como este trabajo reescribe buena
parte del `<body>` de tres plantillas que ya fueron tocadas una vez en el
trabajo anterior del editor, **cada tarea empieza releyendo el archivo
real antes de tocarlo** — no asumir que el contenido sigue siendo el que
se citó en los documentos anteriores (ya pasó una vez en este proyecto que
un rango de líneas documentado quedó desactualizado, ver
`300_ImplementationPlan_SCEditor.md` §Tarea 6/7).

La implementación se considera terminada cuando:

- `newreply.html`, `newthread.html`, `editpost.html` usan el marco de tres
  fondos (`.mainBackground`/`.secondBackground`/`.thirdBackground`) con
  ancho propio, no la tabla `tborder` de MyBB;
- los encabezados usan `.barra-op`/`.barra-espacio-op`, el widget de
  mínimo de palabras usa componentes OPG (no grises hardcodeados), y la
  fila de acciones usa la jerarquía `.btn-op` (una primaria, el resto
  chips/`--sm`);
- `newthread.html` tiene el bloque de prefijo/fecha en `.af-field` dentro
  de filas flex, no en la tabla cruda actual;
- `[spoiler]`/`[dado]`/`[tecnica=TID]` se insertan desde un modal propio
  con componentes OPG, fuera de `.sceditor-container`, y los tres salen
  del string `toolbar` de `codebuttons.html`;
- las tres páginas son usables en ≤700px;
- `{$header}` (navegación global), el botón/panel BÉLICO, el editor
  SCEditor en sí (`.sceditor-container`, `jscripts/post_editor.js` en su
  lógica de contador/borrador) y cualquier `.php` quedan sin tocar.

## 2. Punto de partida

Las tres plantillas ya pasaron por el trabajo del editor (link a
`sceditor-theme.css`, script a `post_editor.js`, sin el `<script>` viejo de
contador/borrador). Este trabajo las toca de nuevo, más a fondo: cambia el
marco exterior completo y revierte un punto puntual de ese trabajo anterior
(los tres BBCode salen de la toolbar nativa).

## 3. Archivos

### 3.1 Crear

| Archivo | Contenido |
|---|---|
| `jscripts/newpost-frame.css` | Ancho del marco (§3 del 200), `.opg-modal`/`.opg-modal-overlay`, `.newpost-acciones`, `.af-row`, estilos del widget de contador nuevo |

### 3.2 Modificar

| Archivo | Cambio |
|---|---|
| `templates/One_Piece_Gaiden_Templates/newreply.html` | Marco de tres fondos, encabezado, widget de contador, fila de acciones, modal BBCode, `<link>` nuevo |
| `templates/One_Piece_Gaiden_Templates/newthread.html` | Ídem + prefijo/fecha a `.af-field` |
| `templates/One_Piece_Gaiden_Templates/editpost.html` | Ídem (sin tocar el `<style>` de `.post.classic`) |
| `templates/One_Piece_Gaiden_Templates/codebuttons.html` | Revertir: sacar `spoiler,dado,tecnica` del `toolbar` |
| `jscripts/post_editor.js` | Agregar `_initBBCodesExternos()`/`_mostrarCamposBBCode()`/`_insertarBBCode()` (200 §5.2) |

### 3.3 No se toca

- `jscripts/sceditor-theme.css`, `jscripts/bbcodes_sceditor.js` (los
  comandos `dado`/`tecnica` se quedan definidos, solo dejan de estar en el
  `toolbar` — no se borran).
- `jscripts/belico/panel.js`, `jscripts/belico/calc.js` — BÉLICO, fuera de
  alcance.
- `templates/One_Piece_Gaiden_Templates/header.html` — navegación global.
- `.post.classic` y el resto del `<style>` inline de `editpost.html`.
- `_initContador()`/`_initBorrador()` de `post_editor.js` — su lógica no
  cambia, solo se agrega código nuevo al lado.
- Cualquier `.php`.

### 3.4 Sincronización

Las cuatro plantillas se sincronizan con `import_templates.php`, igual que
el trabajo anterior. `jscripts/newpost-frame.css` es estático, se ve apenas
se sube.

## 4. Convenciones

- Misma regla que el trabajo anterior: **la prohibición de `$` sueltos es
  solo para `.html` de MyBB**, no para `jscripts/*.js`/`*.css`.
- **Antes de editar cada plantilla, releerla completa** — no confiar en
  fragmentos citados en 100/200, que son del diseño, no un dump exacto del
  archivo en el momento de implementar.
- Las interpolaciones `{$variable}` existentes (`$tid`, `$message`,
  `$codebuttons`, `$prefixselect`, `$subject`, `$is_mundo_rol`, etc.) se
  reubican dentro del nuevo markup, no se inventan de nuevo ni se cambian
  de nombre.
- `grep -no '\$[a-zA-Z_][a-zA-Z0-9_]*' <archivo>` después de cada
  plantilla editada, comparando contra lo que había antes del cambio (no
  solo "que no haya nada raro" — confirmar que sigue estando todo lo que
  tenía).
- Español neutro en los textos nuevos (encabezados, labels del modal).

## 5. Tareas

### Tarea 1: `jscripts/newpost-frame.css`

**Archivo:** nuevo

Contenido: ancho del marco (`.newpost-pagina .thirdBackground`, 200 §3),
`.newpost-acciones`/`.af-row` (200 §4.3/4.4), `.opg-modal-overlay`/
`.opg-modal` (200 §5.2), estilos del widget de contador si no alcanza con
`.opg-chip` tal cual.

**Verificación:** `node --check` no aplica a CSS; revisar visualmente tras
subirlo junto con la Tarea 6 (ninguna plantilla lo referencia todavía antes
de esa tarea, así que no rompe nada por sí solo).

### Tarea 2: agregar el modal + lógica BBCode a `post_editor.js`

**Archivo:** `jscripts/post_editor.js`

1. Agregar `_initBBCodesExternos()`, `_mostrarCamposBBCode()`,
   `_insertarBBCode()` (código completo en 200_DesignPlan §5.2).
2. Llamar `this._initBBCodesExternos();` desde `init()`, junto a
   `_initContador()`/`_initBorrador()`.
3. Los tres métodos deben tolerar que el modal no exista en el DOM
   (`document.getElementById('pe-bbcode-overlay')` puede ser `null` si
   alguna plantilla no llegó a tener el markup todavía) — mismo patrón
   defensivo que ya usan `_initContador()`/`_initBorrador()`.
4. **No omitir el `this._editor.focus();` al principio de
   `_insertarBBCode()`** (200 §5.2) — sin eso, insertar desde el botón
   externo puede fallar si el usuario nunca clickeó dentro del editor
   antes de abrir el modal.
5. `[spoiler]` ya no envuelve texto seleccionado (regresión aceptada por
   el usuario, ver 200 §5.2) — no intentar recuperar ese comportamiento.

**Verificación:** `node --check jscripts/post_editor.js`.

### Tarea 3: revertir `dado,tecnica` (y `spoiler`) en `codebuttons.html`

**Archivo:** `templates/One_Piece_Gaiden_Templates/codebuttons.html`

```diff
- toolbar: "{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link},spoiler,dado,tecnica|video{$emoticon}|{$list}quote|maximize,source",
+ toolbar: "{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link}|video{$emoticon}|{$list}quote|maximize,source",
```

**Verificación:** tras sincronizar, la toolbar del editor ya no muestra
(ni vacíos) los tres botones — confirmar visualmente contando íconos como
se hizo al activar la Tarea 4 del trabajo anterior.

### Tarea 4: `newreply.html`

**Archivo:** `templates/One_Piece_Gaiden_Templates/newreply.html`

1. Releer el archivo completo primero.
2. `<head>`: agregar `<link rel="stylesheet" href="{$mybb->asset_url}/jscripts/newpost-frame.css?ver=1" />` junto al de `sceditor-theme.css`.
3. Reemplazar el `<table class="tborder">` completo por el marco de tres
   fondos (200 §3), con los componentes de 200 §4.1-4.3 adentro — cada
   `{$variable}` del `<table>` viejo se reubica en el lugar equivalente del
   nuevo markup, ninguna se pierde: `{$header}`, `{$preview}`,
   `{$maximageserror}`, `{$attacherror}`, `{$reply_errors}`,
   `{$moderation_notice}`, el `<form>` con sus atributos y `$tid`,
   `{$mybb->post_code}`, `{$theme[...]}` (ya no hace falta si se saca la
   tabla, pero revisar si se usa en otro lado), `{$message}`,
   `{$codebuttons}`, el `<script>` de `post_editor.js` (ya agregado en el
   trabajo anterior, se mantiene en el mismo lugar relativo — después de
   `{$codebuttons}`), `{$multiquote_external}`, `{$captcha}`, `{$as_post}`,
   `{$savedraftbutton}`, el botón `#recuperar-post` (ahora dentro de
   `.newpost-acciones`, 200 §4.3), `{$replyto}`, `{$posthash}`,
   `{$quoted_ids}`, `{$tid}`, `{$editdraftpid}`, `{$forumrules}`,
   `{$threadreview}`, `{$footer}`.
4. Agregar el markup del modal BBCode (200 §5.2) y la fila de chips
   `.newpost-bbcodes` (200 §5.1), entre el editor y `.newpost-acciones`.
5. El widget de mínimo de palabras (`#palabras`) pasa al componente nuevo
   (200 §4.2), sin perder el `id="palabras"` (lo usa `post_editor.js`).

**Verificación:** `grep -no '\$[a-zA-Z_][a-zA-Z0-9_]*'` antes/después,
mismo conjunto de variables. Visual: responder a un hilo real, confirmar
marco de tres fondos, botones con jerarquía, modal de dado/técnica/spoiler
funcional, contador con feedback de color.

### Tarea 5: `newthread.html`

**Archivo:** `templates/One_Piece_Gaiden_Templates/newthread.html`

Mismo patrón que la Tarea 4, más:

1. El bloque de prefijo (`{$prefixselect}`) + asunto (`subject`) pasa a una
   fila `.af-row` con dos `.af-field` (200 §4.4).
2. El bloque condicional `<if $is_mundo_rol then>` (estación/día/año) pasa
   a otra `.af-row`, manteniendo el `<if>` de MyBB alrededor — no se
   cambia la condición, solo el markup interno.
3. **Recordar lo ya aprendido de esta plantilla**: el `<script>` que
   contiene las funciones de clima/viento/encuentro de Aventura
   (`getRandomInt`, `tirarDireccionViento`, `tirarClima`, `tirarEncuentro`)
   no se toca — esta tarea es sobre el `<body>`/tabla visible, no sobre ese
   script, pero conviene releerlo para confirmar que nada de lo que se
   mueve dependía de un `id` que esas funciones buscaran en el DOM (no se
   encontró ninguna referencia cruzada al investigar el trabajo anterior,
   pero vale la pena confirmarlo de nuevo antes de dar por cerrada esta
   tarea, no asumir que sigue siendo cierto).

**Verificación:** igual que Tarea 4, más: crear un hilo nuevo con prefijo
"Mundo Rol" real, confirmar que los campos de fecha siguen validando
(`required`, `min`/`max`) y se ven bien en la fila flex.

### Tarea 6: `editpost.html`

**Archivo:** `templates/One_Piece_Gaiden_Templates/editpost.html`

Mismo patrón que la Tarea 4, con cuidado extra:

1. El `<style>` de `.post.classic` (líneas iniciales del `<head>`) **no se
   toca**.
2. El `<script>` compartido con "Avatar tab switching" y el modal de Akuma
   (confirmado en el trabajo anterior que vive en el mismo `<script>` que
   tenía el contador viejo) tampoco se toca — esta tarea es sobre el
   `<body>` visible del formulario, no sobre ese `<script>`.
3. El bloque condicional `<if $prefixselect then>` (el prefijo solo se
   muestra si se está editando el primer post del hilo) se mantiene igual,
   solo cambia el contenedor visual alrededor.
4. Agregar el modal BBCode y la fila de chips igual que en las Tareas 4/5
   — esta página no tenía recuperación de borrador antes del trabajo
   anterior, pero el modal de BBCode es nuevo para las tres por igual, no
   depende de eso.

**Verificación:** igual que Tarea 4, sobre `editpost.php` editando un post
real (tanto el primer post de un hilo como una respuesta, para cubrir los
dos casos de `$prefixselect`).

## 6. Pruebas

### 6.1 Comprobaciones estáticas

- `node --check` sobre `post_editor.js`.
- `grep -no '\$[a-zA-Z_][a-zA-Z0-9_]*'` sobre las cuatro plantillas,
  comparado contra el estado previo a este trabajo (no debe faltar ninguna
  interpolación que existiera antes).
- Ningún `.php` en el diff.

### 6.2 Con el navegador (manual)

| # | Caso | Esperado |
|---|---|---|
| 1 | Abrir `newreply.php` sobre un hilo real | Marco de tres fondos, ancho más amplio que 1030px, encabezados con `.barra-op` |
| 2 | Escribir hasta pasar 300 palabras | Contador cambia de color (sin cambios respecto al trabajo anterior) |
| 3 | Apretar el chip "Dado" | Se abre el modal, pide cantidad/caras, inserta `[XdY]` válido al confirmar |
| 4 | Apretar el chip "Técnica" | Modal pide TID, inserta `[tecnica=TID]` |
| 5 | Apretar el chip "Spoiler" | Modal pide título opcional, inserta `[spoiler]...[/spoiler]` |
| 6 | Cerrar el modal con Escape, con click afuera, y con el botón `×` | Las tres formas cierran sin insertar nada |
| 7 | Toolbar del editor (íconos de SCEditor) | Ya no muestra `dado`/`tecnica`/`spoiler` |
| 8 | `newthread.php` con prefijo "Mundo Rol" | Campos de fecha en fila, validan igual que antes |
| 9 | `editpost.php` sobre el primer post de un hilo | Selector de prefijo visible, resto del marco igual que `newreply.php` |
| 10 | `editpost.php` sobre una respuesta (no el primer post) | Sin selector de prefijo (como siempre), resto del marco igual |
| 11 | Las tres páginas en ≤700px | Marco al 100%, botones/campos apilados, modal usable |
| 12 | Avatar tab switching y modal de Akuma en `editpost.php` | Siguen funcionando exactamente igual (no tocados) |
| 13 | Tiradas de clima/viento/encuentro en `newthread.php`, si tienen un flujo real que las dispare | Sin cambios |
| 14 | Botón/panel BÉLICO | Sin cambios |

## 7. Despliegue

### 7.1 Antes de subir

- Revisar el diff completo: un archivo CSS nuevo, cinco plantillas/JS
  editados.
- Confirmar que `sceditor-theme.css`/`bbcodes_sceditor.js` del trabajo
  anterior no aparecen en el diff salvo donde se espera (ninguno debería
  cambiar en este trabajo, excepto que `bbcodes_sceditor.js` **no** se
  toca en absoluto).
- Confirmar que no aparece ningún `.php`.

### 7.2 Orden

1. Subir `jscripts/newpost-frame.css` (no rompe nada sin referencia).
2. Subir el `post_editor.js` actualizado (Tarea 2) — agrega funciones
   nuevas, no rompe el contador/borrador existentes mientras el modal no
   exista en el DOM todavía.
3. Sincronizar `codebuttons.html` (Tarea 3) — a partir de aquí los tres
   botones desaparecen de la toolbar nativa, antes de que existan los
   chips externos. Ventana corta sin ninguna de las dos formas disponible;
   aceptable si se hace en el mismo lote que el paso 4.
4. Sincronizar las tres plantillas (Tareas 4-6) — recién aquí aparecen el
   marco nuevo y los chips externos.

### 7.3 Rollback

- Sin base de datos de por medio. Revertir las plantillas y
  `jscripts/post_editor.js`/`newpost-frame.css` desde git.
- Si se revierte solo `codebuttons.html` sin revertir las plantillas, los
  tres BBCode quedan sin forma de insertarse desde la UI (ni toolbar ni
  chips) — revertir junto, no por separado.

## 8. Orden recomendado de commits

1. `docs: requisitos, diseño e implementación del rediseño de páginas de post`.
2. `feat: jscripts/newpost-frame.css y modal de BBCode en post_editor.js`.
3. `refactor: sacar spoiler/dado/tecnica de la toolbar nativa del editor`.
4. `feat: marco de tres fondos y componentes OPG en newreply/newthread/editpost`.

No commit sin que el usuario lo pida.

## 9. Fuera de alcance

- `{$header}` (navegación global del sitio).
- El botón/panel BÉLICO.
- El editor SCEditor en sí y su lógica de contador/recuperación de
  borrador (`_initContador()`/`_initBorrador()`) — ya rediseñados, trabajo
  previo, sin cambios aquí salvo agregar código nuevo al lado.
- Typeahead de búsqueda para el campo de TID del modal de técnica — el
  modal pide el TID en texto plano, mismo alcance acotado que ya se había
  decidido para el botón de toolbar que reemplaza (100_Requirements_SCEditor §4.4).
- Cualquier cambio a `.php`.
