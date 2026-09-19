# Sync de hojas de estilo del tema: diseño

> Nace de una discusión sobre `templates/op_global.css` en [style.md](style.md) §7: se notó que el archivo trackeado en git tenía contenido distinto al de `cache/themes/theme3/op_global.css` local. Esa comparación local no prueba nada sobre lo que está en producción — `cache/` es salida generada (ver `docs/CLAUDE.md`, "no es fuente editable") y esta copia de trabajo no se mantiene sincronizada con el servidor en vivo. Confirmado aparte: `global.css` y `op_global.css` **sí están sincronizados con la base de datos real** ahora mismo. El problema de fondo sigue siendo real igual: a diferencia de los templates `.html` (que ya tienen `export_templates.php`/`import_templates.php`/`watch_templates.sh`), las hojas de estilo del tema no tienen ningún mecanismo de sync — el único camino hoy es copiar y pegar a mano en el editor de Admin CP, y nada impide que la próxima edición se desincronice de la misma forma. Este diseño es prevención, no una reparación de algo roto ahora mismo.

## Objetivo

Que editar los `.css` de `One_Piece_Gaiden_Templates/` en git tenga el mismo flujo que ya existe para sus `.html`: un script de import que empuja el archivo a la base de datos y regenera el CSS que realmente sirve el foro, disparado automáticamente por `watch_templates.sh`. Y un script de export para el sentido inverso, para cuando alguien edite algo por error desde Admin CP y haga falta traer ese cambio de vuelta a git.

## Dónde viven los archivos

`templates/One_Piece_Gaiden_Templates/` es la carpeta que `import_templates.php` ya sincroniza contra un templateset — y, dato del usuario, `One_Piece_Gaiden_Templates` es también el nombre que corresponde al tema `tid=3` (`cache/themes/theme3/`). Un mismo directorio cumple las dos funciones (templateset de HTML + tema de CSS) porque en este proyecto van de la mano; no son la misma tabla de MyBB por dentro (`mybb_templatesets` vs. `mybb_themes`/`mybb_themestylesheets`), pero sí la misma carpeta en git.

Los `.css` del tema pasan a vivir en una subcarpeta propia, para no mezclarse con los `.html`:

```
templates/One_Piece_Gaiden_Templates/
├── *.html                  ← ya sincronizado por import_templates.php (sin cambios)
└── stylesheets/
    ├── global.css          ← movido desde templates/global.css
    └── op_global.css       ← movido desde templates/op_global.css
```

`import_templates.php` no se ve afectado: solo hace `glob($dir . '/*.html')` dentro de cada carpeta de templateset, así que ignora `stylesheets/` sin necesidad de ningún cambio ahí (confirmado leyendo el archivo).

## Por qué las hojas de estilo no son solo "otro `.html` más"

`import_templates.php` hace `UPDATE`/`INSERT` directo contra `mybb_templates` con una conexión mysqli mínima, sin bootstrapear MyBB — porque a un template no le hace falta nada más: MyBB lee la fila de la tabla en cada request.

Una hoja de estilo del tema **no se sirve desde la base de datos en cada visita**. `{$stylesheets}` en `headerinclude.html` termina generando `<link>` a un archivo plano en `cache/themes/theme{tid}/{nombre}.css` (+ `{nombre}.min.css`), y ese archivo plano **solo se regenera cuando alguien lo guarda desde Admin CP** (o cuando algo llama a la función que hace ese trabajo). Escribir directo la fila de `mybb_themestylesheets` sin tocar esos dos archivos no arregla nada — el drift se movería de "editor de Admin CP" a "script de sync", no desaparecería.

**La solución no es reimplementar esa regeneración a mano.** MyBB ya tiene el código que hace esto — lo usa el propio Admin CP — en `admin/inc/functions_themes.php`:

| Función | Qué hace |
|---|---|
| `cache_stylesheet($tid, $nombre, $contenido)` | Escribe `cache/themes/theme{tid}/{nombre}.css` y, llamando a `minify_stylesheet()`, también `{nombre}.min.css`. También resuelve `{$theme[...]}` (`parse_theme_variables()`) y reescribe `url(...)` relativos a la ruta real de `cache/themes/theme{tid}/` (`fix_css_urls_callback`) — confirmado necesario: `global.css` tiene 6 `url(...)` relativos (`url(images/flatty/header-logo.png)`, etc.), así que reimplementar esto a mano es exactamente el tipo de bug silencioso (imagen de fondo rota en todo el foro) que se quiere evitar. |
| `minify_stylesheet($css)` | La minificación real de Admin CP (saca comentarios, espacios, `;}` sobrante, `#rrggbb`→`#rgb`). |
| `update_theme_stylesheet_list($tid)` | Refresca el manifiesto de hojas de estilo del tema (`mybb_themes.stylesheets`) que usa `{$stylesheets}` para saber qué imprimir. Es lo mismo que corre después de guardar en Admin CP. |

Ninguna de las tres (ni sus dependencias directas, `make_child_theme_list`/`make_parent_theme_list`/`parse_theme_variables`) referencia `$lang` ni nada específico del panel de administración — corren bien con el bootstrap normal de `global.php` (`$db`, `$mybb`, `$cache`, `$plugins`, `TIME_NOW`), no hace falta cargar el ACP.

**Consecuencia de diseño:** el script de import, a diferencia de `import_templates.php`, sí bootstrapea MyBB completo (`require_once "./global.php"`) y llama a las funciones reales de `admin/inc/functions_themes.php`, en vez de una conexión mysqli mínima. Es más pesado que el de templates, pero es lo que hace falta para no reinventar minificación ni reescritura de URLs.

## Alcance

- Todo `templates/One_Piece_Gaiden_Templates/stylesheets/*.css`. Hoy son `global.css` (CSS core de MyBB) y `op_global.css` (marco visual de `/op/`), pero el script recorre la carpeta entera con `glob()` — un tercer archivo que se agregue ahí en el futuro entra solo, **siempre que ya exista como fila en `mybb_themestylesheets`** (ver siguiente sección).
- Como toda la carpeta corresponde a un único tema, no hace falta un mapeo archivo→tema: un solo valor, `tid=3`, alcanza para todos.

## Tema (`tid`)

```php
const STYLESHEETS_TID = 3; // templates/One_Piece_Gaiden_Templates/ == tid 3
const STYLESHEETS_DIR  = __DIR__ . '/templates/One_Piece_Gaiden_Templates/stylesheets';
```

**No se crea una fila nueva en `mybb_themestylesheets` automáticamente.** Si el script encuentra un `.css` en `stylesheets/` que no tiene fila con ese `name` + `tid=3` en la tabla, lo reporta y lo salta — mismo comportamiento que ya tiene `import_templates.php` con un directorio de templates sin `templateset` conocido ("Warning: no DB set found... skipping"). Crear y *attachear* una hoja de estilo nueva a páginas específicas es una decisión de Admin CP (el campo `attachedto`), no algo para automatizar a ciegas desde un script de sync.

## Flujo

### Import (`stylesheets/*.css` → DB + `cache/themes/`) — automático

1. Bootstrap: `require_once "./global.php"` + `require_once "./admin/inc/functions_themes.php"`.
2. Para cada archivo `.css` en `STYLESHEETS_DIR`:
   a. `$name = basename($archivo)`.
   b. Buscar la fila existente: `simple_select('themestylesheets', '*', "tid='" . STYLESHEETS_TID . "' AND name='...'")`.
      - No existe → avisar y saltar (ver arriba).
      - Existe y el contenido no cambió → no hacer nada (evita reescribir el cache en cada guardado de un `.html` cualquiera).
      - Existe y cambió → `update_query('themestylesheets', ['stylesheet' => ...], "sid='{$row['sid']}'")`.
   c. `cache_stylesheet(STYLESHEETS_TID, $name, $contenido)` — regenera `.css` y `.min.css` reales, con `url()` y `{$theme[...]}` resueltos como lo haría Admin CP.
   d. `update_theme_stylesheet_list(STYLESHEETS_TID)`.
3. Imprime cuántas hojas se actualizaron y cuáles se saltearon.

Se llama a `cache_stylesheet()` **directamente**, no a `resync_stylesheet()` — esa función de MyBB solo regenera el cache si el archivo *no existe todavía* (es un "reparar cache faltante", pensada para cuando se restaura un theme). Acá el archivo casi siempre va a existir y justamente hay que pisarlo con el contenido nuevo.

### Export (DB → `stylesheets/*.css`) — manual

Mismo espíritu que `export_templates.php`: se corre a mano cuando hace falta (por ejemplo, si alguien edita una hoja desde Admin CP y hay que traer ese cambio de vuelta a git), no automáticamente.

1. Bootstrap igual que el import.
2. `SELECT name, stylesheet FROM mybb_themestylesheets WHERE tid = 3` — escribe cada fila a `stylesheets/{name}`.

### `watch_templates.sh`

```bash
fswatch -o "$SCRIPT_DIR/templates/" | while read -r event; do
    echo "[$(date '+%H:%M:%S')] Change detected, syncing..."
    php "$SCRIPT_DIR/import_templates.php"
    php "$SCRIPT_DIR/import_stylesheets.php"
done
```

Se corren las dos siempre, aunque el cambio haya sido en un solo `.html` o un solo `.css` — mismo criterio que ya usa `import_templates.php` (reprocesa *todos* los templates en cada disparo, no solo el que cambió). El chequeo de "sin cambios" del import de arriba hace que esto sea barato incluso cuando lo que cambió fue un `.html` y ningún `.css`.

## Seguridad

- Mismo gate que `import_templates.php`/`export_templates.php`: si se corre por HTTP en vez de CLI, exige `?password=` contra una constante `IMPORT_PASSWORD` hardcodeada. **Nota, no bloqueante para este diseño:** ese password (`'changeme'` en el archivo actual) es débil y queda commiteado en git — ya es así para los scripts de templates, este solo replica el patrón existente en vez de corregirlo. Si se quiere endurecer, es un cambio aparte que afecta a los cuatro scripts por igual.
- `export_stylesheets.php` lleva la misma advertencia que ya tiene `export_templates.php` en su docblock: expone credenciales de `inc/config.php` por cómo se conecta a la base — moverlo o borrarlo después de usarlo si se corre fuera de un entorno de confianza.
- No hay superficie nueva de inyección: `STYLESHEETS_TID`/`STYLESHEETS_DIR` son constantes del script, no vienen de input externo, y el nombre/contenido de cada archivo va escapado con `$db->escape_string()` igual que el resto del proyecto.

## Riesgos

- **`global.css` es el CSS core de MyBB, no de `/op/`.** Un error ahí es visible en *todo* el foro (no solo en las páginas del sistema de juego), a diferencia de `opg-tokens.css`/`opg-components.css`, que son aditivos y no reemplazan nada.
- **Confirmar `tid=3` contra la base real antes del primer import.** Es el dato que dio el usuario (`One_Piece_Gaiden_Templates` == tid 3), pero como el dump no trae filas de `mybb_themestylesheets`/`mybb_themes`, vale la pena un `SELECT` de confirmación antes de que el script escriba nada, simplemente porque el costo de revisar es bajo y el de equivocarse (escribirle a otro tema) no.
- **`update_theme_stylesheet_list()` toca el tema entero (`mybb_themes.stylesheets`)**, no solo la hoja que cambió — es el comportamiento real de MyBB al guardar cualquier stylesheet, no algo que este diseño agregue, pero vale tenerlo presente si algún día se sincronizan muchas hojas a la vez.

## Fuera de alcance

- Crear o *attachear* una hoja de estilo nueva a un tema por primera vez — se sigue haciendo una vez desde Admin CP, como hoy.
- Endurecer el password hardcodeado de los scripts de sync (templates y stylesheets por igual).
- Sincronizar plantillas de tema (`mybb_themes.properties`) u otras tablas del sistema de temas — solo `mybb_themestylesheets`.
