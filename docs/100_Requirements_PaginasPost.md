# Requisitos: Rediseño visual de las páginas de escribir/editar posts

> Construido sobre el trabajo ya hecho del editor SCEditor
> ([100](100_Requirements_SCEditor.md)/[200](200_DesignPlan_SCEditor.md)/[300](300_ImplementationPlan_SCEditor.md)_*_SCEditor.md)
> — ese trabajo theming solo `.sceditor-container`, con una decisión
> explícita de no tocar el marco de la página ("son páginas del foro, no
> del juego"). Este documento **revierte esa decisión puntual** y encara el
> resto de la página. El editor en sí (ya rediseñado) no se vuelve a tocar.

## 1. Propósito

`newreply.php`, `newthread.php` y `editpost.php` usan el marco genérico de
MyBB (`<table class="tborder">`, barras `thead`/`tcat` planas, botones
`.button` violeta, un widget de "mínimo de palabras" con grises
hardcodeados) — ninguno de los componentes de `docs/style.md` está
presente. Son las tres páginas donde cualquier usuario del foro (no solo
staff) escribe contenido, así que el salto visual contra el resto del sitio
(que sí sigue el sistema de diseño) es más notorio ahí que en ninguna otra
parte.

## 2. Decisiones ya tomadas

- **Alcance: las tres páginas** — `newreply.html`, `newthread.html`,
  `editpost.html`.
- **Marco de tres fondos, revirtiendo 100_Requirements_SCEditor §2.**
  Pasan de `<table class="tborder">` a
  `.mainBackground`/`.secondBackground`/`.thirdBackground`, igual que las
  páginas de `/op/`.
- **Ancho propio, más ancho que los 1030px estándar.** Un editor de texto
  necesita más espacio horizontal que una herramienta de staff típica —
  **precedente nuevo**: el marco de tres fondos no está atado a un único
  ancho fijo en todo el sitio, una página puede pedir el suyo si lo
  justifica (ver `style.md` §3, "ancho fijo de 1030px... cambialo en la
  propia página" — ya está permitido, esto es solo el primer caso real que
  lo usa de forma intencionalmente más ancha, no solo responsive).
- **Mobile sí es requisito** (≤700px, mismo criterio que el resto del
  sitio) — a diferencia del trabajo anterior del editor (herramienta
  interna), estas páginas las usa cualquier usuario del foro.
- **BÉLICO queda completamente afuera.** El botón y el panel
  (`jscripts/belico/panel.js`/`calc.js`) no se tocan ni se restylean.
- **`{$header}` (la barra de navegación naranja) no es parte de estas
  plantillas** — vive en el template global `header.html`, compartido por
  todo el sitio. Tocarlo está fuera de alcance de este documento.
- **Jerarquía de botones a criterio del trabajo de diseño** (el usuario lo
  delegó): una acción primaria (`.btn-op--primario`, enviar/publicar) y el
  resto como `.opg-chip`/`.btn-op--sm`, no los cuatro con el mismo peso
  visual que hoy.
- **Los botones de `[spoiler]`/`[dado]`/`[tecnica=TID]` se mueven afuera
  del editor.** Decisión nueva, **supersede la Tarea 4 de
  `300_ImplementationPlan_SCEditor.md`** (que los activó como íconos
  dentro de la toolbar nativa de SCEditor). En vez de competir por espacio
  en la fila apretada de íconos, pasan a ser una fila de `.opg-chip` más
  grande y clara fuera de `.sceditor-container`, que insertan el BBCode
  llamando `MyBBEditor.insert(...)` directamente — misma API pública, sin
  necesidad de vivir dentro del editor. El fix del bug de `[dado]`
  (insertaba `[dado=XdY]` en vez de `[XdY]`, ver 100_Requirements_SCEditor
  §3) **se mantiene**, es independiente de dónde viva el botón que lo
  dispara.
- **`[spoiler]` pierde la capacidad de envolver texto seleccionado.**
  Regresión aceptada explícitamente por el usuario: el comando original
  (en la toolbar nativa) envolvía el texto ya seleccionado en
  `[spoiler][/spoiler]` cuando había selección; el modal externo siempre
  inserta fresco, sin revisar selección previa.
- **`MyBBEditor.focus()` antes de cada `insert()` del modal externo.**
  Necesario porque el editor puede no tener una selección/posición de
  cursor guardada si el usuario nunca clickeó dentro de él antes de abrir
  el modal — sin esto, la inserción puede fallar o caer en un lugar
  inesperado (método público confirmado real en `jquery.sceditor.min.js`).

## 3. Qué no cambia

- La lógica PHP de las tres páginas — cero `.php` tocado, mismo principio
  que el trabajo anterior.
- El editor SCEditor en sí (`.sceditor-container`, su toolbar básica,
  `jscripts/post_editor.js`) — ya rediseñado, trabajo previo.
- El CSS inline específico de `editpost.html` (`.post.classic` y los
  overrides del postbit clásico) — se revisa que conviva con el marco
  nuevo, no se rediseña su lógica.
- El selector de prefijo/fecha de `newthread.html` en su funcionalidad —
  cambia de markup (tabla cruda → `.af-field` en fila flex), no de
  comportamiento.

## 4. Requisitos funcionales

### 4.1 Marco de página

Las tres plantillas pasan al marco de tres fondos, con un ancho propio
(`.newpost-frame` o similar, a definir el valor exacto en el diseño) en vez
del `max-width: 1030px` por defecto. En mobile, `width: 100%` como ya hacen
otras páginas que necesitan este requisito.

### 4.2 Encabezados

Las barras "Enviar nueva respuesta"/"Responder al tema: X"/"Editar post"
pasan de `thead`/`tcat` al patrón `.barra-op`/`.barra-espacio-op`
(`style.md` §3).

### 4.3 Widget de mínimo de palabras

Deja los grises hardcodeados (`#f0f0f0`, `#ccc`) por un componente con
`--opg-crema-clara`/`--opg-tinta` o `.opg-chip` — el contador (`#palabras`,
con el feedback de color ya implementado en el trabajo del editor) vive
ahí integrado, no aparte.

### 4.4 Fila de acciones

Jerarquía `.btn-op`: una acción primaria (enviar/publicar/actualizar según
la página), el resto (vista previa, guardar borrador, recuperar post,
selector de personaje) con menor peso visual.

### 4.5 Botones de BBCode fuera del editor

Fila de `.opg-chip` (o similar) fuera de `.sceditor-container`, uno por
`[spoiler]`/`[dado]`/`[tecnica=TID]`. Cada uno abre su propio pequeño
formulario/modal (mismos campos que ya existen en los comandos de
`bbcodes_sceditor.js` — cantidad/caras para dado, TID para técnica, título
para spoiler) e inserta con `MyBBEditor.insert(...)` al confirmar. Los tres
comandos salen del string `toolbar` de `codebuttons.html` (revierte la
Tarea 4 del 300 anterior).

### 4.6 `newthread.html`: prefijo y fecha

El bloque de prefijo + fecha (estación/día/año, condicional a
`$is_mundo_rol`) pasa a filas `.af-field` en flex-wrap (`style.md` §7),
reemplazando la tabla cruda actual. Mismo comportamiento (los `required`,
los rangos `min`/`max` de los inputs numéricos).

## 5. Requisitos no funcionales

- Mobile: ≤700px usable en las tres páginas (editor, botones de acción,
  campos de `newthread.html`).
- Diseño: `style.md` como referencia obligatoria — paleta real, sombra de
  cómic, sin tablas crudas.
- Nada de `.php` tocado, en ningún punto de este trabajo.

## 6. Preguntas abiertas (para el `200_DesignPlan`)

1. ~~Ancho exacto del marco nuevo.~~ **Resuelto en el 200**: `max-width: 1300px`
   como primer valor, ajustable al verlo con contenido real.
2. ~~Si el CSS de `.post.classic` choca con el marco nuevo.~~ **Resuelto**:
   apunta a `#posts_container`/`.post.classic` (la vista de posts previos),
   no a la tabla/marco del formulario — no debería chocar.
3. ~~Si el modal de los tres BBCode reusa el dropdown viejo o se rediseña.~~
   **Resuelto, confirmado por el usuario**: modal propio con componentes
   OPG (`.af-field`/`.btn-op`), no el HTML viejo de los dropdowns de
   SCEditor ni `prompt()` nativo — ver 200_DesignPlan §5.2.
