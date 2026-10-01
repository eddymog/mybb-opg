# Requisitos: Mejoras al editor de posts (SCEditor)

> **Estado de este documento:** a diferencia de los otros `100_Requirements_*`,
> todavía no hay decisiones tomadas con el usuario — es un relevamiento de
> oportunidades de mejora encontradas al revisar el editor real, para discutir
> y recortar antes de pasar a un `200_DesignPlan_SCEditor.md`.

## 1. Propósito

El editor de posts (SCEditor) que usan `newreply.php`, `newthread.php` y
`editpost.php` corre con la configuración de fábrica de MyBB 1.8, sin ningún
ajuste visual ni funcional propio del foro. Al mismo tiempo, alrededor del
editor hay JS custom (contador de palabras, recuperación de borrador) escrito
de forma frágil, que depende de la estructura interna de SCEditor en vez de
su API pública — y ese JS está **copiado y pegado en las tres plantillas**
(`newreply.html`, `newthread.html`, `editpost.html`), no compartido. Cualquier
corrección o mejora que no toque las tres a la vez deja a las otras
desactualizadas o inconsistentes.

Este documento junta lo que se encontró al revisar el estado real (no
supuesto) del editor, como base para decidir qué mejorar.

## 2. Estado actual (verificado en el código)

- **Toolbar:** configuración estándar de MyBB, sin ningún botón agregado
  (`templates/One_Piece_Gaiden_Templates/codebuttons.html:44`):
  `{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link},spoiler|video{$emoticon}|{$list}quote|maximize,source`.
- **Skin visual:** el CSS del editor (`jscripts/sceditor/themes/{$theme['editortheme']}`)
  es uno de los skins que trae SCEditor de fábrica (`default.css`,
  `modern.css`, etc. — están en `jscripts/sceditor/themes/`), configurado
  desde el ACP (`admin/modules/style/themes.php`). **No hay ningún CSS propio
  del foro apuntado a `.sceditor-container`** — ni en `templates/`, ni en
  `cache/themes/` (los dos se revisaron con grep, cero resultados).
- **BBCodes custom sin integración visual:** el foro tiene 15 procesadores
  `BBCustom_*.php` (`[ficha]`, `[dado]`, `[npc=ID]`, `[tecnica=TID]`,
  `[objeto=ID]`, etc.), pero ninguno agrega un botón a la toolbar de
  SCEditor — se escriben a mano o se insertan desde paneles aparte
  (ej. `jscripts/belico/panel.js`, cargado solo en `newreply.html`/
  `newthread.html`).
- **Página sí carga el lenguaje visual del foro:** `newreply.html` (y
  `newthread.html`, `editpost.html`) cargan `{$headerinclude}`, así que
  `opg-tokens.css`/`opg-components.css` (ver `docs/style.md` §7) **ya están
  disponibles** en esas páginas — hoy nadie los usa para el editor, pero no
  hace falta inyectar nada nuevo para empezar a hacerlo.
- **Marco de página:** el formulario vive en un `<table class="tborder">`
  genérico de MyBB (`thead`/`tcat`/`trow2`), no en el marco de tres fondos
  `.mainBackground/.secondBackground/.thirdBackground` que usan las páginas
  de `/op/` — es una página "del foro", no "del juego", visualmente.
- **Contador de palabras, triplicado:** presente en `newreply.html:73-89`,
  `newthread.html:210-228` y `editpost.html:83-95` — el mismo bloque de JS
  copiado y pegado en las tres plantillas, cuenta palabras del post en vivo
  contra mínimos mostrados como texto plano ("Post: 300 | Autonarradas: 800
  | Frutas: Normativa"), sin indicación visual de si se cumple o no el
  mínimo.
- **Recuperación de borrador, duplicada (no triplicada):** presente en
  `newreply.html:60-71` y `newthread.html:197-207` (guarda el HTML del post
  en `localStorage` cuando supera 10 palabras; un botón "Recuperar post" lo
  restaura). **`editpost.html` no tiene este botón ni su handler** — tiene el
  contador pero no la recuperación, una asimetría de features entre las tres
  páginas que probablemente no fue intencional (¿para qué guardar un borrador
  de una edición si no se puede recuperar ahí?).
- **Versión de `calc.js` desincronizada:** `newreply.html` carga
  `jscripts/belico/calc.js?v=24` y `newthread.html` carga la misma ruta con
  `?v=20` — incluso si el archivo físico es el mismo (el query-string es solo
  cache-busting), la diferencia de número sugiere que una plantilla se
  actualizó y la otra no quedó sincronizada al mismo paso.
- **Ambas features (contador y recuperación) manipulan el DOM interno de
  SCEditor directamente**, no su API pública, y lo repiten en cada plantilla:
  ```js
  $($('.sceditor-container')[0].children[1].contentDocument.body)
  ```
  Esto asume la estructura interna exacta del iframe de SCEditor (que el
  segundo hijo del contenedor es el iframe del editor). Funciona hoy, pero
  es frágil ante cualquier cambio de versión de SCEditor o de su DOM interno
  — y hay dos copias de esta misma lógica (contador + recuperación), cada
  una repitiendo el mismo selector largo.
- **Mismo patrón en `newthread.php`/`editpost.php`:** no se revisó en detalle
  si tienen su propia copia de este JS o si difiere — pendiente si se decide
  tocar el contador/borrador (ver §4).

## 3. Oportunidades de mejora encontradas

No son decisiones, son opciones para discutir. Cada una es independiente de
las demás — se pueden tomar todas, ninguna, o una combinación.

### 3.1 Theming visual del editor
Darle a `.sceditor-container` (toolbar y área de escritura) el lenguaje
visual de `docs/style.md`: borde negro grueso, radio de esquina del sistema
(`--opg-radio-sm/md`), fondo crema (`--opg-crema-clara`) en vez del blanco
plano de fábrica, y los iconos de la toolbar con el mismo tratamiento de
"pop" al hover que el resto del foro. Técnicamente es solo CSS nuevo
apuntando a las clases que SCEditor ya genera (`.sceditor-container`,
`.sceditor-toolbar`, `.sceditor-button`), sin tocar su JS — bajo riesgo.

### 3.2 Contador de palabras con feedback visual
Reemplazar el texto plano ("Palabras: 0") por un indicador que cambie de
color/estado según si se cumple el mínimo correspondiente (post normal,
autonarrada, etc.) — hoy el usuario tiene que comparar el número a mano
contra el texto de mínimos. Requiere saber, en JS, qué tipo de post es
(¿hay una forma de detectarlo del lado del cliente, o habría que pasarlo
desde PHP como variable del template?) — pendiente de definir en §4.

### 3.3 Robustecer el acceso al contenido del editor
Reemplazar `$('.sceditor-container')[0].children[1].contentDocument.body`
por la API pública de SCEditor (`MyBBEditor.val()` para leer,
`MyBBEditor.val(html)` para escribir — `MyBBEditor` ya existe como variable
global, asignada en `codebuttons.html:50`). Mismo resultado funcional, sin
depender de la posición exacta del iframe en el DOM. Candidato a hacerse
igual se cambie o no el diseño visual, porque es una corrección de robustez,
no de estética.

### 3.4 Integrar BBCodes custom como botones de la toolbar
Agregar botones reales a la toolbar de SCEditor para los BBCodes más usados
del foro (`[dado]`, `[npc=ID]`, etc. — a definir cuáles), en vez de que el
usuario los escriba a mano o dependa de paneles externos aparte del editor.
Es el cambio de mayor alcance de esta lista: requiere extender `opt_editor`
en `codebuttons.html` con un plugin/extensión de SCEditor, no solo CSS.

### 3.5 Unificar el contador + borrador entre las tres plantillas
El JS del contador de palabras (triplicado) y el de recuperación de
borrador (duplicado) hoy vive copiado y pegado dentro de cada `.html`, con
el selector largo de `.sceditor-container` repitiéndose varias veces en
cada copia. Mover esta lógica a un solo archivo JS compartido
(`jscripts/...`) cargado desde las tres plantillas — en vez de tocar tres
copias cada vez que se corrige algo — resolvería de paso la asimetría de
`editpost.html` (sin botón "Recuperar post") y el desfase de versión de
`calc.js` detectado en §2. Es un cambio de bajo riesgo funcional (mismo
comportamiento, un solo lugar donde vive) y probablemente debería ir
**antes** que 3.2/3.3/3.4, para no triplicar también el trabajo de
cualquiera de esas otras mejoras.

## 4. Preguntas abiertas (para decidir antes de un 200_DesignPlan)

1. ¿Cuáles de las oportunidades de §3 se quieren encarar, y en qué orden?
2. Para 3.2 (contador con feedback visual): ¿cómo se determina desde el
   cliente qué mínimo de palabras aplica a este post en particular (post
   común/autonarrada/otro)? ¿Ya existe esa información en una variable del
   template, o hay que agregarla?
3. Para 3.4 (botones custom): ¿qué BBCodes concretos ameritan un botón en la
   toolbar? ¿Todos los de `BBCustom_*.php`, o solo los de uso más frecuente
   al escribir un post de rol?
4. Dado que el JS ya está copiado en las tres plantillas (no es "replicar
   después", ya existe en las tres hoy): ¿conviene unificarlo en un archivo
   compartido (3.5) antes de tocar cualquier otra mejora, para no repetir el
   trabajo tres veces? ¿O se prefiere arrancar por una sola página para
   validar el enfoque visual/funcional antes de tocar las otras dos?
5. ¿Alguna restricción de no tocar el ACP (`editortheme` del tema activo) —
   es decir, todo el cambio visual debe vivir en CSS propio del template, sin
   tocar la configuración del tema en `mybb_themes`?
