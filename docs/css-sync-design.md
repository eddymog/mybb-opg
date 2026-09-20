# Sync de hojas de estilo del tema: diseño

> Nace de una discusión sobre `templates/op_global.css` en [style.md](style.md) §7: se notó que el archivo trackeado en git tenía contenido distinto al de `cache/themes/theme3/op_global.css` local. Esa comparación local no prueba nada sobre lo que está en producción — `cache/` es salida generada (ver `docs/CLAUDE.md`, "no es fuente editable") y esta copia de trabajo no se mantiene sincronizada con el servidor en vivo. Confirmado aparte: `global.css` y `op_global.css` **estaban sincronizados con la base de datos real** en el momento de esta discusión. El problema de fondo sigue siendo real igual: a diferencia de los templates `.html` (que ya tienen `export_templates.php`/`import_templates.php`/`watch_templates.sh`), las hojas de estilo del tema no tenían ningún mecanismo de sync — el único camino era copiar y pegar a mano en el editor de Admin CP, y nada impedía que la próxima edición se desincronizara de la misma forma. Este diseño es prevención, no una reparación de algo roto en ese momento.

## Objetivo

Que editar los `.css` de `One_Piece_Gaiden_Templates/` en git tenga el mismo flujo que ya existe para sus `.html`: un import que empuja el archivo a la base de datos y regenera el CSS que realmente sirve el foro, y un export para el sentido inverso (por ejemplo, si algo se edita por error desde Admin CP y hay que traer ese cambio de vuelta a git). Tres formas de disparar ese import/export, según el acceso disponible:

- **Con CLI:** `export_stylesheets.php` / `import_stylesheets.php`, este último enganchado a `watch_templates.sh`. Requiere SSH, que no está disponible en este hosting — ver "Estado" más abajo.
- **Sin CLI, manual (solo FTP + Admin CP):** `inc/plugins/opg_stylesheet_export.php` (export) siguen existiendo así. El equivalente de import, `inc/plugins/opg_stylesheet_import.php`, existió como plugin de un solo uso (Desactivar/Activar para repetir) y **se retiró** una vez confirmado que el camino automático (siguiente punto) lo cubre sin necesitar ese paso manual.
- **Sin CLI, automático:** `inc/plugins/opg_stylesheet_sync.php`, un plugin real (engancha `global_start`) que revisa `stylesheets/*.css` cada ~10 segundos de tráfico al foro y sincroniza solo lo que cambió, sin activar nada a mano. Mismo patrón que `inc/plugins/template_sync.php` ya usa para los `.html` — ver la sección dedicada más abajo. Es el camino en uso hoy.

## Dónde viven los archivos

`templates/One_Piece_Gaiden_Templates/` es la carpeta que `import_templates.php` ya sincroniza contra un templateset — y, dato del usuario, `One_Piece_Gaiden_Templates` es también el nombre que corresponde al tema `tid=3` (`cache/themes/theme3/`). Un mismo directorio cumple las dos funciones (templateset de HTML + tema de CSS) porque en este proyecto van de la mano; no son la misma tabla de MyBB por dentro (`mybb_templatesets` vs. `mybb_themes`/`mybb_themestylesheets`), pero sí la misma carpeta en git.

Los `.css` del tema viven en una subcarpeta propia, para no mezclarse con los `.html`:

```
templates/One_Piece_Gaiden_Templates/
├── *.html                  ← ya sincronizado por import_templates.php (sin cambios)
└── stylesheets/
    ├── global.css
    ├── op_global.css
    └── ... (16 más — ver "Estado" abajo)
```

`import_templates.php` no se ve afectado: solo hace `glob($dir . '/*.html')` dentro de cada carpeta de templateset, así que ignora `stylesheets/` sin necesidad de ningún cambio ahí (confirmado leyendo el archivo).

## Estado (actualizado tras la primera corrida real)

`opg_stylesheet_export.php` (ver "Sin CLI" abajo) se activó una vez en producción. Confirmó `tid=3` con datos reales y trajo **18 hojas de estilo**, no solo las dos con las que arrancó este diseño:

`global.css`, `op_global.css`, `ficha.css`, `css3.css`, `mapaMundo.css`, `temas.css`, `contar_temas.css`, `copyphpcode.css`, `fonts.css`, `modcp.css`, `op_modal.css`, `ring.css`, `showthread.css`, `spoiler.css`, `star_ratings.css`, `thread_status.css`, `tooltip.css`, `usercp.css`.

Esto no cambia nada del diseño — el import/export ya recorren la carpeta entera con `glob()`, nunca hubo una lista fija de nombres — pero sí confirma que el alcance real de "las hojas de estilo de este tema" es bastante más grande de lo que parecía al principio. Antes de tocar cualquiera de las 16 hojas nuevas con el import, vale la pena mirar una vez de qué se trata cada una (algunas, como `showthread.css` o `usercp.css`, probablemente sean CSS específico de esas páginas del foro, no del sistema `/op/`).

## Por qué las hojas de estilo no son solo "otro `.html` más"

`import_templates.php` hace `UPDATE`/`INSERT` directo contra `mybb_templates` con una conexión mysqli mínima, sin bootstrapear MyBB — porque a un template no le hace falta nada más: MyBB lee la fila de la tabla en cada request.

Una hoja de estilo del tema **no se sirve desde la base de datos en cada visita**. `{$stylesheets}` en `headerinclude.html` termina generando `<link>` a un archivo plano en `cache/themes/theme{tid}/{nombre}.css` (+ `{nombre}.min.css`), y ese archivo plano **solo se regenera cuando alguien lo guarda desde Admin CP** (o cuando algo llama a la función que hace ese trabajo). Escribir directo la fila de `mybb_themestylesheets` sin tocar esos dos archivos no arregla nada — el drift se movería de "editor de Admin CP" a "script/plugin de sync", no desaparecería.

**La solución no es reimplementar esa regeneración a mano.** MyBB ya tiene el código que hace esto — lo usa el propio Admin CP — en `admin/inc/functions_themes.php`:

| Función | Qué hace |
|---|---|
| `cache_stylesheet($tid, $nombre, $contenido)` | Escribe `cache/themes/theme{tid}/{nombre}.css` y, llamando a `minify_stylesheet()`, también `{nombre}.min.css`. También resuelve `{$theme[...]}` (`parse_theme_variables()`) y reescribe `url(...)` relativos a la ruta real de `cache/themes/theme{tid}/` (`fix_css_urls_callback`) — confirmado necesario: `global.css` tiene 6 `url(...)` relativos (`url(images/flatty/header-logo.png)`, etc.), así que reimplementar esto a mano es exactamente el tipo de bug silencioso (imagen de fondo rota en todo el foro) que se quiere evitar. |
| `minify_stylesheet($css)` | La minificación real de Admin CP (saca comentarios, espacios, `;}` sobrante, `#rrggbb`→`#rgb`). |
| `update_theme_stylesheet_list($tid)` | Refresca el manifiesto de hojas de estilo del tema (`mybb_themes.stylesheets`) que usa `{$stylesheets}` para saber qué imprimir. Es lo mismo que corre después de guardar en Admin CP. |

Ninguna de las tres (ni sus dependencias directas, `make_child_theme_list`/`make_parent_theme_list`/`parse_theme_variables`) referencia `$lang` ni nada específico del panel de administración — corren bien con el bootstrap normal de MyBB (`$db`, `$mybb`, `$cache`), sea vía `global.php` (script CLI) o vía el propio Admin CP (plugin activado desde ahí).

**Consecuencia de diseño:** tanto el script de import como el plugin de import, a diferencia de `import_templates.php`, bootstrapean MyBB completo (o corren dentro del Admin CP, que ya lo tiene bootstrapeado) y llaman a las funciones reales de `admin/inc/functions_themes.php`, en vez de una conexión mysqli mínima. Es más pesado que el de templates, pero es lo que hace falta para no reinventar minificación ni reescritura de URLs.

## Alcance

- Todo `templates/One_Piece_Gaiden_Templates/stylesheets/*.css` — hoy 18 archivos (ver "Estado"). El import/export recorren la carpeta entera con `glob()`, así que un archivo nuevo que se agregue ahí entra solo, **siempre que ya exista como fila en `mybb_themestylesheets`** (ver siguiente sección).
- Como toda la carpeta corresponde a un único tema, no hace falta un mapeo archivo→tema: un solo valor, `tid=3`, alcanza para todos.

## Tema (`tid`)

```php
const STYLESHEETS_TID = 3; // templates/One_Piece_Gaiden_Templates/ == tid 3
const STYLESHEETS_DIR  = __DIR__ . '/templates/One_Piece_Gaiden_Templates/stylesheets';
```

**No se crea una fila nueva en `mybb_themestylesheets` automáticamente.** Si el import encuentra un `.css` en `stylesheets/` que no tiene fila con ese `name` + `tid=3` en la tabla, lo reporta y lo salta — mismo comportamiento que ya tiene `import_templates.php` con un directorio de templates sin `templateset` conocido ("Warning: no DB set found... skipping"). Crear y *attachear* una hoja de estilo nueva a páginas específicas es una decisión de Admin CP (el campo `attachedto`), no algo para automatizar a ciegas desde un script/plugin de sync.

## Flujo (camino con CLI)

### Import (`stylesheets/*.css` → DB + `cache/themes/`) — automático vía `watch_templates.sh`

1. Bootstrap: `require_once "./global.php"` + `require_once "./admin/inc/functions_themes.php"`.
2. Para cada archivo `.css` en `STYLESHEETS_DIR`:
   a. `$name = basename($archivo)`.
   b. Buscar la fila existente: `simple_select('themestylesheets', '*', "tid='" . STYLESHEETS_TID . "' AND name='...'")`.
      - No existe → avisar y saltar (ver arriba).
   c. **Salvaguarda de contenido** (ver más abajo) sobre el archivo nuevo contra el contenido actual de la fila. Si no pasa, avisar y saltar — no se toca ni la fila ni el cache.
   d. Si el contenido no cambió → no hacer nada (evita reescribir el cache en cada guardado de un `.html` cualquiera).
   e. Si cambió: **backup automático** del contenido *actual* de la fila (ver más abajo), después `update_query('themestylesheets', ['stylesheet' => ...], "sid='{$row['sid']}'")`.
   f. `cache_stylesheet(STYLESHEETS_TID, $name, $contenido)` — regenera `.css` y `.min.css` reales, con `url()` y `{$theme[...]}` resueltos como lo haría Admin CP.
   g. `update_theme_stylesheet_list(STYLESHEETS_TID)`.
   h. **Log** de la actualización real (ver más abajo).
3. Imprime cuántas hojas se actualizaron y cuáles se saltearon.

Se llama a `cache_stylesheet()` **directamente**, no a `resync_stylesheet()` — esa función de MyBB solo regenera el cache si el archivo *no existe todavía* (es un "reparar cache faltante", pensada para cuando se restaura un theme). Acá el archivo casi siempre va a existir y justamente hay que pisarlo con el contenido nuevo.

### Salvaguardas (por qué hacen falta, no solo qué hacen)

`watch_templates.sh` corre contra la base de **producción**, directo, sin ambiente de staging en el medio — confirmado por el usuario. Eso sube el costo de un archivo mal guardado de "hay que corregirlo" a "ya se sirvió a los visitantes", así que el import no puede asumir que el contenido que lee de `stylesheets/*.css` en un momento dado es siempre válido. Durante la propia revisión de este diseño se observó un caso real de por qué: un archivo de este mismo repositorio (este mismo documento, de hecho) volvió a un contenido viejo en disco después de haber sido corregido, aparentemente por una pestaña de editor con el buffer desactualizado guardando encima — el mismo mecanismo, aplicado a un `.css` en vez de a un `.md`, sería exactamente el escenario que estas tres salvaguardas cubren:

- **Chequeo de contenido antes de escribir:** si el archivo nuevo está vacío, o mide menos de la mitad que el contenido actual de la fila, se rechaza el import con un error explícito en vez de pisar la hoja de estilo con algo probablemente truncado o a medio guardar. No es una validación de sintaxis CSS (eso queda fuera de alcance, ver abajo) — es una detección barata del caso "esto no se parece en nada a una edición normal".
- **Backup automático:** antes de cada `update_query` real, el contenido *anterior* de la fila se guarda en `templates/backups/{name}.pre_import_<timestamp>.css` — mismo directorio que ya se usa para backups manuales en este repo. Da un punto de vuelta atrás inmediato sin depender de acordarse de copiar algo a mano antes de guardar.
- **Log de cada cambio real:** cada vez que el import sí actualiza una hoja (no en los "sin cambios" ni en los saltos), se agrega una línea a `logs/css_sync.log` (ya gitignorado por el patrón `*.log` existente) con fecha, nombre y bytes antes/después. Responde "¿cuándo cambió esto de verdad por última vez" sin necesitar una tabla de auditoría de MyBB atada a un uid de staff.

Ninguna de las tres reemplaza revisar el diff antes de guardar — son una red debajo, no un sustituto de mirar qué se está por sincronizar. El camino "sin CLI" (siguiente sección) implementa las mismas tres salvaguardas.

### Nota sobre Cloudflare

`opg-tokens.css`/`opg-components.css` necesitan `?ver=N` manual porque Cloudflare cachea agresivamente esas rutas bajo `/jscripts/`. **Eso no aplica acá:** Cloudflare no cachea las plantillas que salen de la base de datos ni las rutas de `cache/themes/`, así que un import exitoso se refleja sin necesidad de purgar ni versionar nada — confirmado por el usuario. No hace falta ningún paso extra de cache-busting para este mecanismo.

### Export (DB → `stylesheets/*.css`) — manual

Mismo espíritu que `export_templates.php`: se corre a mano cuando hace falta (por ejemplo, si alguien edita una hoja desde Admin CP y hay que traer ese cambio de vuelta a git), no automáticamente.

1. Bootstrap igual que el import.
2. `SELECT name, stylesheet FROM mybb_themestylesheets WHERE tid = 3` — escribe cada fila a `stylesheets/{name}`.

## Flujo (camino sin CLI, manual — histórico)

Sin SSH ni phpMyAdmin, no hay forma de ejecutar un script PHP desde una terminal ni de correr una consulta SQL directa. `inc/plugins/opg_stylesheet_export.php` / `inc/plugins/opg_stylesheet_import.php` resolvieron esto reutilizando algo que ya existía en el flujo de trabajo del proyecto: subir un archivo por FTP y usar el botón **Activar** de Admin CP > Plugins.

- **Export** (`opg_stylesheet_export.php`, sigue existiendo): al activarse, corre el mismo `SELECT ... WHERE tid=3` de arriba y escribe cada hoja a `MYBB_ROOT . 'templates/One_Piece_Gaiden_Templates/stylesheets/'` — una ruta real en el servidor. El resumen (qué se exportó, cuántos bytes) se muestra con `flash_message()` en la página de Plugins, porque `_activate()` no tiene una terminal escuchando su salida.
- **Import** (`opg_stylesheet_import.php`, **retirado** — ver "Flujo (camino automático)" abajo): al activarse, recorría esa misma carpeta en el servidor (con los `.css` ya subidos por FTP) y aplicaba exactamente el mismo flujo de la sección anterior — salvaguardas, backup, `cache_stylesheet()`, `update_theme_stylesheet_list()`, log — mostrando el resultado también con `flash_message()`. Requería Desactivar y volver a Activar cada vez que se subía un `.css` nuevo por FTP; se retiró una vez que `opg_stylesheet_sync.php` demostró cubrir el mismo caso sin ese paso manual.
- Ninguno de los dos enganchaba ningún hook de MyBB: eran plugins solo en el sentido de "un archivo que Admin CP sabe activar/desactivar", no plugins que hicieran algo mientras estaban activos.

## Flujo (camino automático — `opg_stylesheet_sync.php`)

Mismo problema que resuelve `inc/plugins/template_sync.php` para los `.html` — subir un archivo por FTP no es suficiente por sí solo, alguien tiene que disparar la sincronización — pero para `.html` ese plugin ya lo hace solo, enganchado a `global_start` (corre en cada visita al foro), con un archivo de cache (`cache/template_sync_last.php`) que guarda el timestamp del último ciclo para no releer el directorio entero en cada request. `opg_stylesheet_sync.php` replica exactamente ese patrón para `stylesheets/*.css`, con su propio archivo de cache (`cache/opg_css_sync_last.php`, independiente del de templates) y las mismas tres salvaguardas del camino manual (rechazo de contenido vacío/corto, backup automático, log en `logs/css_sync.log`).

Diferencia real con `template_sync.php`: una hoja de estilo no es un `UPDATE` directo — necesita pasar por `cache_stylesheet()`/`update_theme_stylesheet_list()` de `admin/inc/functions_themes.php` para que `cache/themes/theme3/*.css` se regenere de verdad (ver "Por qué las hojas de estilo no son solo 'otro .html' más", arriba). Por eso el `require_once` de ese archivo se hace recién dentro del bloque que sí encontró cambios, no en cada request — la mayoría de los requests solo leen un timestamp y salen.

**Dirección DB → archivo:** igual que `template_sync_write_file()`, un guardado desde Admin CP escribe el contenido nuevo de vuelta a `stylesheets/{name}.css` para que no se desincronice de git. Como hay dos editores de stylesheet en Admin CP (simple y avanzado), cada uno con su propio hook de `_commit`, la función se engancha a los dos y relee la fila por `sid` en vez de depender del nombre de la variable local de cada rama — más robusto ante un futuro cambio en `admin/modules/style/themes.php`.

**Con este plugin activo, `opg_stylesheet_import.php` (el manual) dejó de ser necesario para el flujo normal y se retiró del repo** — un `.css` subido por FTP se sincroniza solo dentro de los próximos ~10 segundos de tráfico al foro, sin ningún paso manual en Admin CP.

## Seguridad

- El camino con CLI usa el mismo gate que `import_templates.php`/`export_templates.php`: si se corre por HTTP en vez de CLI, exige `?password=` contra una constante `IMPORT_PASSWORD` hardcodeada. **Nota, no bloqueante para este diseño:** ese password (`'changeme'` en el archivo actual) es débil y queda commiteado en git — ya es así para los scripts de templates, este solo replica el patrón existente en vez de corregirlo.
- El camino sin CLI no necesita ese gate — activar un plugin ya requiere sesión de Admin CP, que es un control de acceso más fuerte que un password hardcodeado en el propio repo.
- `export_stylesheets.php` (y el plugin equivalente) exponen credenciales de `inc/config.php` por cómo se conectan a la base — moverlo o borrarlo después de usarlo si se corre fuera de un entorno de confianza. El plugin de export ya se usó una vez y se borró después, siguiendo esta misma regla.
- No hay superficie nueva de inyección: `STYLESHEETS_TID`/`STYLESHEETS_DIR` son constantes del script (o valores fijos en el plugin), no vienen de input externo, y el nombre/contenido de cada archivo va escapado con `$db->escape_string()` igual que el resto del proyecto.

## Riesgos

- **`global.css` es el CSS core de MyBB, no de `/op/`.** Un error ahí es visible en *todo* el foro (no solo en las páginas del sistema de juego), a diferencia de `opg-tokens.css`/`opg-components.css`, que son aditivos y no reemplazan nada. Mitigado por las salvaguardas de arriba (backup automático + chequeo de contenido antes de escribir), no eliminado — siguen sin reemplazar mirar el diff antes de guardar.
- **`watch_templates.sh` corre contra producción, sin staging en el medio** (confirmado por el usuario) — es la razón concreta detrás de las tres salvaguardas, no una advertencia genérica. El camino sin CLI corre contra la misma producción, así que aplica igual.
- **Un editor con un archivo de `stylesheets/` abierto en una pestaña puede pisar una edición más nueva con su buffer viejo al guardar** (visto en vivo, dos veces, durante la propia elaboración de este diseño — sobre archivos `.md` del repo, no todavía sobre un `.css`, pero el mecanismo es el mismo). El chequeo de contenido mínimo solo atrapa el caso "el archivo quedó muy corto" — si el buffer viejo tiene un tamaño parecido al nuevo, el import lo sincroniza sin quejarse. Repasar el diff en git antes de un guardado importante sigue siendo la única defensa real contra esto.
- **`update_theme_stylesheet_list()` toca el tema entero (`mybb_themes.stylesheets`)**, no solo la hoja que cambió — es el comportamiento real de MyBB al guardar cualquier stylesheet, no algo que este diseño agregue, pero vale tenerlo presente si algún día se sincronizan muchas hojas a la vez.
- **16 de las 18 hojas de `tid=3` nunca se revisaron todavía** (ver "Estado") — antes de sincronizar cualquiera de ellas con el import, vale la pena confirmar qué página(s) usa cada una, para no asumir que todas son tan seguras de tocar como `op_global.css`.

## Fuera de alcance

- Crear o *attachear* una hoja de estilo nueva a un tema por primera vez — se sigue haciendo una vez desde Admin CP, como hoy.
- Endurecer el password hardcodeado de los scripts de sync (templates y stylesheets por igual).
- Sincronizar plantillas de tema (`mybb_themes.properties`) u otras tablas del sistema de temas — solo `mybb_themestylesheets`.
- Validar sintaxis CSS de verdad (balanceo de llaves, propiedades válidas) — las salvaguardas de tamaño mínimo detectan truncamientos groseros, no errores de sintaxis dentro de un archivo de tamaño normal.
