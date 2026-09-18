# Banner rotativo del header: plan de implementación

> Diseño: [banner-rotativo-design.md](banner-rotativo-design.md)

**Objetivo:** crear el plugin `inc/plugins/op_banner_rotativo.php`. El plugin expone `$op_banner_rotativo` con un banner `Banner<N>_One_Piece_Gaiden_Foro_Rol.jpg` que cambia cada 5 minutos y se guarda en el datacache de MyBB. Después, la rama `<else>` de `#logo` en `header.html` pasa a usar esa variable.

**Archivos:**

| Acción | Archivo |
|---|---|
| Crear | `inc/plugins/op_banner_rotativo.php` |
| Modificar | `templates/One_Piece_Gaiden_Templates/header.html` (bloque `#logo`, ~línea 943) |
| Opcional | `global.php:638`: borrar `$banner_number = rand(1, 56);` (código muerto) |

No hay migraciones SQL. La entrada del caché (`mybb_datacache.title = 'op_banner_rotativo'`) se crea sola la primera vez que se ejecuta.

---

## Tarea 1: crear el plugin

**Archivo:** `inc/plugins/op_banner_rotativo.php`

```php
<?php
/**
 * OPG - Banner rotativo
 *
 * Elige al azar un banner de images/op/banners/ (serie
 * Banner<N>_One_Piece_Gaiden_Foro_Rol.jpg) y lo mantiene durante
 * OP_BANNER_ROTATIVO_INTERVALO segundos, guardado en el datacache de MyBB.
 * header.html lo usa como banner por defecto de #logo vía
 * $op_banner_rotativo. Para añadir un banner basta con subirlo por FTP con
 * el nombre correcto: entra en la rotación en el siguiente ciclo.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

define('OP_BANNER_ROTATIVO_INTERVALO', 300);
define('OP_BANNER_ROTATIVO_DIR',       'images/op/banners/');
define('OP_BANNER_ROTATIVO_PATRON',    '/^Banner(\d+)_One_Piece_Gaiden_Foro_Rol\.jpg$/');
define('OP_BANNER_ROTATIVO_CACHE',     'op_banner_rotativo');

$plugins->add_hook('global_intermediate', 'op_banner_rotativo_run');

function op_banner_rotativo_info()
{
    return array(
        'name'          => 'OPG - Banner rotativo',
        'description'   => 'Rota el banner del header entre los Banner<N>_One_Piece_Gaiden_Foro_Rol.jpg cada 5 minutos (cacheado).',
        'website'       => '',
        'author'        => 'OPG',
        'authorsite'    => '',
        'version'       => '1.0',
        'compatibility' => '18*',
    );
}

function op_banner_rotativo_activate() {}

function op_banner_rotativo_deactivate()
{
    global $cache;
    $cache->delete(OP_BANNER_ROTATIVO_CACHE);
}

/**
 * Nombres de archivo de la serie Banner<N>_..., ordenados por número.
 */
function op_banner_rotativo_lista()
{
    $archivos = @scandir(MYBB_ROOT . OP_BANNER_ROTATIVO_DIR);
    if (!$archivos) {
        return array();
    }

    $banners = array();
    foreach ($archivos as $archivo) {
        if (preg_match(OP_BANNER_ROTATIVO_PATRON, $archivo, $m)) {
            $banners[(int)$m[1]] = $archivo;
        }
    }
    ksort($banners);

    return array_values($banners);
}

function op_banner_rotativo_run()
{
    global $cache, $op_banner_rotativo;

    $op_banner_rotativo = '';
    $data = $cache->read(OP_BANNER_ROTATIVO_CACHE);

    $vigente = is_array($data)
        && !empty($data['actual'])
        && TIME_NOW < (int)$data['expira']
        && file_exists(MYBB_ROOT . OP_BANNER_ROTATIVO_DIR . $data['actual']);

    if (!$vigente) {
        $banners = op_banner_rotativo_lista();
        if (empty($banners)) {
            return;
        }

        $anterior = is_array($data) ? $data['actual'] : '';
        $candidatos = count($banners) > 1 ? array_values(array_diff($banners, array($anterior))) : $banners;

        $data = array(
            'actual' => $candidatos[array_rand($candidatos)],
            'expira' => TIME_NOW + OP_BANNER_ROTATIVO_INTERVALO,
            'total'  => count($banners),
        );
        $cache->update(OP_BANNER_ROTATIVO_CACHE, $data);
    }

    $op_banner_rotativo = '/' . OP_BANNER_ROTATIVO_DIR . $data['actual'];
}
```

**Notas:**
- `$data['actual']` solo llega al HTML si coincidió con la regex al elegirse, así que no se puede inyectar nada a través del nombre del archivo.
- `$cache->read()` no hace ninguna consulta, porque con `cache_store = 'db'` el datacache entero ya está en memoria. `$cache->update()` hace una escritura cada 5 minutos.
- `array_diff` evita repetir el banner anterior. Si solo hay uno, se usa ese mismo.

**Verificación:**
1. `php -l inc/plugins/op_banner_rotativo.php` → `No syntax errors detected`.

## Tarea 2: activar el plugin

1. ACP → Configuración → Plugins → **OPG - Banner rotativo** → *Activar*.
2. Comprobar en la base de datos:
   ```sql
   SELECT title, cache FROM mybb_datacache WHERE title = 'op_banner_rotativo';
   ```
   La fila aparece después de la primera carga de cualquier página del foro, con `actual`, `expira` y `total = 61`.

## Tarea 3: usar la variable en `header.html`

**Archivo:** `templates/One_Piece_Gaiden_Templates/header.html`, rama `<else>` del bloque `#logo`.

Antes:
```html
				<else>
					<img src="/images/op/banners/Banner59_One_Piece_Gaiden_Foro_Rol.jpg" alt="One Piece Gaiden">
				</if>
```

Después:
```html
				<elseif $op_banner_rotativo then>
					<img src="{$op_banner_rotativo}" alt="One Piece Gaiden">
				<else>
					<img src="/images/op/banners/Banner59_One_Piece_Gaiden_Foro_Rol.jpg" alt="One Piece Gaiden">
				</if>
```

Se añade un `<elseif>` y se deja `Banner59` como `<else>`, para que el header siga funcionando si el plugin está desactivado o no encuentra banners. Las ramas por UID no se tocan.

**Despliegue de la plantilla:** el plugin `template_sync` importa a la base de datos los cambios subidos por FTP a `templates/One_Piece_Gaiden_Templates/` en unos 10 segundos. La otra alternativa es editar la plantilla `header` desde el ACP. `templates/mybb_templates/header.html` es otra copia y no hace falta modificarla.

**Verificación:**
1. Abrir el índice como invitado (sesión cerrada) → el `<img>` de `#logo` apunta a `/images/op/banners/Banner<N>_...jpg` y la imagen carga.
2. Recargar varias veces dentro de la misma ventana → siempre sale el **mismo** banner.
3. Forzar la expiración sin esperar 5 minutos:
   ```sql
   DELETE FROM mybb_datacache WHERE title = 'op_banner_rotativo';
   ```
   Tras recargar → sale otro banner, distinto del anterior.
4. Entrar con uno de los UIDs con banner personalizado (por ejemplo 7) → sigue viendo `Banner60`.
5. Desactivar el plugin → el header vuelve a mostrar `Banner59`. Reactivarlo.
6. Probar en una página de `/op/` (por ejemplo `op/ficha.php`) → el banner rota igual, porque esas páginas también cargan `global.php`.

## Tarea 4 (opcional): limpieza

- Borrar `$banner_number = rand(1, 56);` en `global.php:638` después de confirmar con `grep -rn banner_number` que ninguna plantilla ni script lo usa. A día de hoy solo aparece en esa línea.

## Tarea 5: commit

```bash
git add inc/plugins/op_banner_rotativo.php templates/One_Piece_Gaiden_Templates/header.html
git commit -m "Banner rotativo cada 5 minutos en el header"
```

(Añadir `global.php` si se hizo la tarea 4.)

---

## Rollback

1. Desactivar el plugin en el ACP. El header vuelve a `Banner59` gracias al fallback, sin tener que tocar la plantilla.
2. Si se quiere quitar del todo, revertir el cambio en `header.html` y borrar `inc/plugins/op_banner_rotativo.php`.
