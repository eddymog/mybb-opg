# Requisitos: Mejoras al editor de posts (SCEditor)

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

## 2. Decisiones ya tomadas

- **Marco de página: sin cambios.** `newreply.php`/`newthread.php`/
  `editpost.php` siguen con el `<table class="tborder">` estándar de MyBB
  (no pasan al marco de tres fondos `.mainBackground/.secondBackground/
  .thirdBackground` de las páginas `/op/`) — son páginas del foro, no del
  juego. El alcance visual de este documento es **solo el editor**
  (`.sceditor-container` y su toolbar), no el resto de la página.
- **Nada de `.php` para estos features — solo `.html`/`.css`/`.js`.** Si en
  algún momento hiciera falta lógica de servidor nueva, va en un plugin
  (`inc/plugins/`), nunca editando `newreply.php`/`newthread.php`/
  `editpost.php` directamente. Verificado que todo el alcance de abajo se
  puede hacer sin PHP nuevo (detalle en §4.2 y §4.4).
- **No se toca el ACP** (`/admin/`, el panel de administración de MyBB) —
  todo el cambio visual vive en CSS propio dentro del `<style>` de cada
  template, sin tocar la config del tema (`editortheme` en `mybb_themes`).
  Ya es el enfoque natural (§3 confirma que hoy no hay ningún override ahí).
- **Unificar el JS del contador + borrador (§4.5) va primero**, antes que
  cualquier otra mejora. Es un cambio delicado porque toca las tres páginas
  donde se escribe/edita contenido real de usuarios en producción — ver §5,
  "Cómo encararlo con cuidado".
- **Dos BBCodes como piloto de botones de toolbar (§4.4): `[dado]` y
  `[tecnica=TID]`.** No se escriben desde cero — **ya existen** como
  comandos en `jscripts/bbcodes_sceditor.js`, solo sin wirear al `toolbar`.
  `[tecnica=TID]` se usa tal cual está (ya inserta el BBCode correcto);
  `[dado]` necesita corregirse primero (inserta hoy `[dado=XdY]`, un bug —
  `BBCustom_dado.php` solo reconoce `[XdY]`, ver §3) y agregarle los
  límites 1-20 dados / 1-100000 caras que ya valida
  `BBCustom_dado.php` del lado del servidor. `[spoiler]` queda fuera por
  estar ya resuelto (ver §3). Los otros seis comandos sin usar del mismo
  archivo (`haki`, `personaje`, `arma`, `vida`, `energia`, `belico`) quedan
  **fuera de alcance**, no se tocan (ver §3).
- **El contador de palabras (§4.2) no distingue por prefijo del hilo.**
  Se descartó la detección de prefijo (Común/Autonarrada/Frutas/etc.) por
  la ambigüedad real de qué mínimo corresponde a cada prefijo — el feedback
  visual usa un solo umbral fijo para todos los casos.
- **Sin prioridad de mobile** para esta página en este documento — a
  diferencia del resto de herramientas del proyecto (probadas en ≤700px),
  no se pide por ahora.

## 3. Estado actual (verificado en el código)

- **Toolbar:** configuración estándar de MyBB, sin ningún botón propio del
  foro agregado (`templates/One_Piece_Gaiden_Templates/codebuttons.html:44`):
  `{$basic1}{$align}{$font}{$size}{$color}{$removeformat}{$basic2}image,{$email}{$link},spoiler|video{$emoticon}|{$list}quote|maximize,source`.
- **Skin visual:** el CSS del editor (`jscripts/sceditor/themes/{$theme['editortheme']}`)
  es uno de los skins que trae SCEditor de fábrica (`default.css`,
  `modern.css`, etc., en `jscripts/sceditor/themes/`), configurado desde el
  ACP (`admin/modules/style/themes.php`). **No hay ningún CSS propio del
  foro apuntado a `.sceditor-container`** — ni en `templates/`, ni en
  `cache/themes/` (los dos revisados con grep, cero resultados).
- **`[spoiler]` ya tiene botón de toolbar funcionando de punta a punta** —
  `jscripts/bbcodes_sceditor.js:871` registra un comando SCEditor completo
  (dropdown con título + contenido, `txtExec` que inserta
  `[spoiler][/spoiler]`), `codebuttons.html:44` tiene `spoiler` en el string
  de la toolbar, y `BBCustom_spoiler.php` es el plugin que lo instaló: al
  activarse, inyecta el botón en el template `codebuttons` vía
  `find_replace_templatesets()` y carga `jscripts/spoiler.js`.
- **`[dado]` y `[tecnica=TID]` ya tienen comando SCEditor escrito, pero
  ninguno de los dos está wireado al `toolbar`.** Hallazgo corregido
  respecto a una versión anterior de este documento, que asumía que había
  que escribirlos desde cero:
  - `jscripts/bbcodes_sceditor.js:609` (`$.sceditor.command.set('tecnica', {...})`)
    ya tiene dropdown (campo de texto para el TID) y **ya inserta el BBCode
    correcto**: `[tecnica=' + mod + ']'`, coincide exacto con lo que espera
    `BBCustom_tecnica.php`. Solo falta agregar `tecnica` al string de
    `toolbar` en `codebuttons.html:44`.
  - `jscripts/bbcodes_sceditor.js:826` (`$.sceditor.command.set('dado', {...})`)
    tiene dropdown (cantidad de dados, caras) pero **inserta el BBCode
    equivocado**: `` `[dado=${mod1}d${mod2}]` `` (ej. `[dado=2d20]`).
    `BBCustom_dado.php` solo reconoce `[XdY]` (ej. `[2d20]`, regex
    `#\[(\d+d\d+)\]#si`) — este comando, si se activara tal cual está, nunca
    generaría un dado válido. Es un bug preexistente en el archivo, no una
    feature a medio hacer. Hay que **corregir la línea del insert**
    (`` `[${mod1}d${mod2}]` `` sin el prefijo `dado=`) y agregarle los
    límites 1-20/1-100000 (100_Requirements §4.4), además de agregar `dado`
    al `toolbar`.
- **Hay seis comandos más en el mismo archivo, sin usar y fuera de alcance
  de este documento:** `haki`, `personaje`, `arma`, `vida`, `energia`,
  `belico` (líneas 567-978 de `jscripts/bbcodes_sceditor.js`). Ninguno tiene
  un `BBCustom_*.php` detrás — los BBCodes que insertan (`[arma=ID]`,
  `[vida=...]`, etc.) se conectan en cambio con
  `inc/plugins/OLD_codigos_rol.php` (un plugin que, a pesar del prefijo
  "OLD_" en el nombre de archivo, podría seguir activo — el estado de
  activación de un plugin en MyBB no depende del nombre del archivo) y con
  `op/api_combate.php`, un sistema de combate aparte. No se toca nada de
  esto en este documento — es un hallazgo para anotar, no para resolver
  en este documento.
- **Página sí carga el lenguaje visual del foro:** las tres plantillas
  cargan `{$headerinclude}`, así que `opg-tokens.css`/`opg-components.css`
  (`docs/style.md` §7) **ya están disponibles** ahí — hoy nadie los usa
  para el editor, pero no hace falta inyectar nada nuevo para empezar.
- **Contador de palabras, triplicado:** presente en `newreply.html:73-89`,
  `newthread.html:210-228` y `editpost.html:83-95` — el mismo bloque de JS
  copiado y pegado en las tres plantillas, cuenta palabras del post en vivo
  contra mínimos mostrados como texto plano ("Post: 300 | Autonarradas: 800
  | Frutas: Normativa"), sin indicación visual de si se cumple o no.
- **Recuperación de borrador, duplicada (no triplicada):** presente en
  `newreply.html:60-71` y `newthread.html:197-207` (guarda el HTML del post
  en `localStorage` en cada keystroke relevante una vez que supera 10
  palabras; un botón "Recuperar post" lo restaura). **`editpost.html` no
  tiene este botón ni su handler** — tiene el contador pero no la
  recuperación, una asimetría que probablemente no fue intencional.
  **Confirmado como complementario, no redundante**, al mecanismo nativo de
  MyBB (`{$savedraftbutton}`/`post_savedraftbutton`, guarda en la base de
  datos): el de `localStorage` protege contra cerrar la pestaña o que el
  navegador se cuelgue sin haber tocado ningún botón, el nativo es un
  borrador real del lado del servidor que cruza dispositivos — cada uno
  cubre un caso que el otro no.
- **Versión de `calc.js` desincronizada:** `newreply.html` carga
  `jscripts/belico/calc.js?v=24` y `newthread.html` la misma ruta con
  `?v=20` — el query-string es cache-busting, pero el desfase sugiere que
  una plantilla se actualizó y la otra no (no es parte de este documento,
  `calc.js` es la Calculadora Bélica, una herramienta aparte del editor;
  se anota solo porque se encontró al revisar el head de las plantillas).
- **Contador y recuperación manipulan el DOM interno de SCEditor
  directamente**, no su API pública, y lo repiten en cada plantilla:
  ```js
  $($('.sceditor-container')[0].children[1].contentDocument.body)
  ```
  Esto asume la estructura interna exacta del iframe de SCEditor (que el
  segundo hijo del contenedor es el iframe). Funciona hoy, pero es frágil
  ante cualquier cambio de versión de SCEditor o de su DOM interno.
- **El contador reinventa a mano un evento que SCEditor ya emite.** El
  bloque actual guarda el HTML en `focus`, lo compara contra el actual en
  cada `keyup`, y dispara `'change'` manualmente si cambió — un hack para
  detectar "el contenido cambió". SCEditor ya emite un evento nativo
  `valuechanged` sobre el propio contenedor (confirmado leyendo
  `jscripts/sceditor/jquery.sceditor.min.js`:
  `Fe(x,"selectionchanged valuechanged nodechanged pasteraw paste",q)`,
  donde `x` es el mismo nodo creado como `.sceditor-container` unas líneas
  antes — `We(x=De("div",{className:"sceditor-container"}),r)`). Escuchar
  `MyBBEditor.bind('valuechanged', fn)` directo reemplaza todo el
  `before`/`after` sin falta.
- **Los dropdowns de SCEditor (el de `[spoiler]`, fuentes, color, etc.) ya
  se agregan dentro de `.sceditor-container`, no afuera.** Verificado en el
  mismo archivo: `createDropDown` hace `appendChild` del dropdown sobre esa
  misma variable `x` (el contenedor). Cualquier theming que apunte a
  `.sceditor-container` (§4.1) ya cubre los dropdowns sin selectores
  adicionales — no hace falta ningún cambio de arquitectura para esto.

## 4. Alcance del piloto

### 4.1 Theming visual del editor
Darle a `.sceditor-container` (toolbar y área de escritura) el lenguaje
visual de `docs/style.md`: borde negro grueso, radio de esquina del sistema
(`--opg-radio-sm/md`), fondo crema (`--opg-crema-clara`) en vez del blanco
plano de fábrica, iconos de toolbar con el mismo "pop" al hover que el
resto del foro. Solo CSS nuevo apuntando a clases que SCEditor ya genera
(`.sceditor-container`, `.sceditor-toolbar`, `.sceditor-button`), sin tocar
su JS.

### 4.2 Contador de palabras con feedback visual
Reemplazar el texto plano ("Palabras: 0") por un indicador que cambie de
color/estado según el conteo — **sin distinguir por prefijo del hilo**
(decisión explícita: se ignora la relación entre prefijos y mínimos de
palabras, por la ambigüedad real encontrada en qué prefijo corresponde a
cada mínimo mostrado hoy). Un solo umbral fijo para el feedback visual
(a definir cuál — candidato obvio: el "300" ya mostrado como mínimo
genérico de "Post" en el texto plano actual), sin ninguna detección de
prefijo ni lectura de `<select name="threadprefix">` ni de
`$thread['prefix']`. El texto de mínimos por tipo de post
("Post: 300 | Autonarradas: 800 | Frutas: Normativa") se queda como está,
solo informativo — el indicador de color no intenta saber cuál de esos
tres aplica.

Al no necesitar el prefijo, esta mejora no toca ninguna variable nueva del
lado del servidor ni interpola nada en ningún `.html` — es JS puro sobre el
conteo que ya existe hoy.

### 4.3 Robustecer el acceso al contenido del editor
Reemplazar la manipulación directa del DOM interno de SCEditor por su API
pública — tres cambios concretos, los tres parte de la misma limpieza:

1. **Leer el body del editor con `MyBBEditor.getBody()`** en vez de
   `$('.sceditor-container')[0].children[1].contentDocument.body`. Es un
   método público real de SCEditor (confirmado en
   `jquery.sceditor.min.js`: `se.getBody=function(){return f}`) que
   devuelve exactamente el mismo nodo al que hoy se llega adivinando la
   posición del iframe — mismo resultado, sin la fragilidad. `MyBBEditor`
   ya existe como variable global, asignada en `codebuttons.html:50`.
2. **Detectar cambios con el evento nativo `valuechanged`** en vez del hack
   actual de comparar `before`/`after` en cada `keyup` (ver §3) —
   `MyBBEditor.bind('valuechanged', fn)` reemplaza ese bloque entero.
3. **Debounce liviano en el recálculo del contador** (ej. 150-200ms) sobre
   el handler de `valuechanged`, ya que ese evento puede dispararse seguido
   en pegados grandes o ediciones rápidas — evita recorrer todo el texto
   del post en cada disparo si el post ya es largo.

Los tres sin tocar PHP, y los tres se resuelven en el mismo archivo
compartido de §4.5 (una sola referencia a `MyBBEditor` guardada una vez,
reusada por el contador, la recuperación de borrador y los botones nuevos
de 4.4).

### 4.4 Activar y corregir `[dado]` y `[tecnica=TID]` en la toolbar
No hace falta escribir comandos nuevos (corregido respecto a una versión
anterior de este documento) — los dos ya existen en
`jscripts/bbcodes_sceditor.js`, nunca se activaron:

- **`[tecnica=TID]`** (línea 609): ya inserta el BBCode correcto. Solo
  agregar `tecnica` al string de `toolbar` en `codebuttons.html:44`. **Sin
  typeahead de búsqueda por nombre en este piloto** — se investigó
  `op/tecnicas_buscar.php` como posible fuente de datos para un buscador,
  pero es una página HTML completa, no un endpoint JSON liviano; armar uno
  nuevo violaría la regla de §2 ("nada de `.php` nuevo sin pasar por un
  plugin") si se hace apurado. Queda anotado como mejora futura.
- **`[dado]`** (línea 826): corregir el insert —
  `` `[dado=${mod1}d${mod2}]` `` (bug: no coincide con lo que
  `BBCustom_dado.php` reconoce) pasa a `` `[${mod1}d${mod2}]` ``. **Los
  campos deben limitarse** a los mismos rangos que valida
  `BBCustom_dado.php` del lado del servidor (1-20 dados, 1-100000 caras) —
  confirmado en `BBCustom_dado.php:142`, donde hoy un valor fuera de rango
  se rechaza en silencio y se reemplaza por `[dadoinvalido=...]` sin aviso
  claro al usuario; el dropdown ya tiene los campos, solo le faltan
  `min`/`max` y el recorte al insertar. Después, agregar `dado` al
  `toolbar`.

### 4.5 Unificar el contador + borrador entre las tres plantillas
El JS del contador de palabras (triplicado) y el de recuperación de
borrador (duplicado) hoy vive copiado y pegado dentro de cada `.html`, con
el selector largo de `.sceditor-container` repitiéndose varias veces en
cada copia. Mover esta lógica a un solo archivo `.js` compartido, cargado
desde las tres plantillas, resuelve de paso la asimetría de `editpost.html`
(sin botón "Recuperar post"). Es el primer paso decidido (§2) — ver §5 para
cómo encararlo.

## 5. Cómo encararlo con cuidado (§4.5)

Unificar este JS toca el flujo de escritura real de cualquier usuario del
foro en tres páginas a la vez — un bug en este archivo no rompe una
herramienta de staff aislada, rompe la posibilidad de postear o editar
posts del sitio entero. Antes de dar esto por terminado:

1. **Comparar las tres copias línea por línea primero**, no asumir que son
   idénticas solo porque se ven parecidas — ya se encontró una asimetría
   real (`editpost.html` sin "Recuperar post") y un desfase de versión en
   un script relacionado (`calc.js`). Documentar cualquier otra diferencia
   antes de unificar, para decidir a propósito si se conserva o se
   descarta, no perderla sin querer.
2. **El archivo compartido nuevo no cambia comportamiento observable**,
   salvo las asimetrías que se decida corregir a propósito (ej. agregar
   "Recuperar post" a `editpost.html`) — y esas se listan explícitamente
   antes de tocar código, no se descubren en el camino.
3. **Probar las tres páginas por separado después del cambio**, no solo una
   y asumir que las otras funcionan igual: crear un post nuevo
   (`newthread.php`), responder (`newreply.php`) y editar un post existente
   (`editpost.php`) — contador y recuperación de borrador en las tres.
4. **Nada de `.php` en ningún paso de esto** (ver §2) — el archivo
   compartido es `.js` puro, cargado con un `<script src="...">` nuevo en
   cada plantilla `.html`.
