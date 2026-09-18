# Banner rotativo del header: diseño

## Objetivo

Que el banner de `#logo` en `header.html` cambie solo cada 5 minutos, usando las imágenes de la serie `images/op/banners/Banner<N>_One_Piece_Gaiden_Foro_Rol.jpg`. Así nadie tendrá que editar la plantilla a mano cada vez que se quiera cambiar el banner por defecto (ahora mismo está fijo en `Banner59`).

## Estado actual

- `templates/One_Piece_Gaiden_Templates/header.html:933` contiene el bloque `#logo`, que es una cadena de `<if>/<elseif>` por UID:
  - Los UIDs 7, 25, 279, 23, 9, 11, 799, 2 y 347 ven `Banner60`.
  - Los UIDs 304, 310 y 92 ven `Besties.png`.
  - Los UIDs 348 y 850 ven `BannerDearWendy.webp`.
  - Los UIDs 10 y 944 ven una imagen de WhatsApp.
  - El resto (`<else>`) ve **`Banner59_One_Piece_Gaiden_Foro_Rol.jpg` fijo**.
- En `images/op/banners/` hay 61 banners de la serie (`Banner1` … `Banner61`). También hay otras imágenes (`BannerKuro*`, `BannerNavidad`, `Besties.png`, `BannerDearWendy.webp`) que **no** deben entrar en la rotación.
- `global.php:638` ya define `$banner_number = rand(1, 56);`, pero ninguna plantilla lo usa. Sobra y además daría un banner distinto en cada petición, no cada 5 minutos.
- La plantilla `header` se evalúa en `global.php:1370`, después del hook `global_intermediate` (`global.php:498`).
- `inc/config.php` usa `cache_store = 'db'`. Con ese backend MyBB carga toda la tabla `mybb_datacache` en memoria al arrancar, así que leer una entrada propia con `$cache->read()` **no cuesta ninguna consulta extra**.

## Decisiones

| Tema | Decisión | Motivo |
|---|---|---|
| ¿A quién afecta? | Solo a la rama `<else>` (el banner por defecto). Los banners personalizados por UID se quedan igual. | Esos banners son regalos o personalizaciones deliberadas. |
| ¿Global o por usuario? | **Global**: todos los visitantes ven el mismo banner durante la misma ventana de 5 minutos. | Es lo que implica "cada 5 minutos y cacheado", y evita el parpadeo de un banner distinto en cada página. |
| Intervalo | 300 segundos, en una constante. | Lo que se pidió. Se puede cambiar sin tocar la lógica. |
| Qué archivos entran | Los de `images/op/banners/` que cumplan exactamente `^Banner(\d+)_One_Piece_Gaiden_Foro_Rol\.jpg$`. | Deja fuera `BannerKuro*`, `BannerNavidad`, los `.png` y los `.webp`. |
| Descubrimiento de archivos | Automático con `scandir()`, **solo al expirar el caché** (una vez cada 5 minutos, no en cada petición). | Subir `Banner62_...jpg` por FTP basta: entra en la rotación en el siguiente ciclo sin tocar código. |
| Selección | Aleatoria, sin repetir el banner que acaba de salir. | Una secuencia 1, 2, 3… resulta predecible. El azar sin repetición da variedad. |
| Almacén del caché | `$cache->update('op_banner_rotativo', …)`, que es el datacache de MyBB. | No hace falta crear tablas ni archivos nuevos, sale gratis con `cache_store = db` y es el mecanismo estándar de MyBB. |
| Punto de enganche | Hook `global_intermediate`. | Corre antes de evaluar `header` y en todas las páginas que cargan `global.php`, incluidas las de `/op/`. |
| Salida hacia la plantilla | Variable global `$op_banner_rotativo`, que es la URL relativa de la imagen. | Mismo patrón que el resto de variables `$g_*` que usa `header.html`. |
| Si el plugin falla o está desactivado | La plantilla vuelve a `Banner59` si la variable está vacía. | El header nunca debe quedarse con una imagen rota. |

## Arquitectura

```
Petición
  └─ global.php
       ├─ init.php → $cache->cache()  (carga mybb_datacache, incluido 'op_banner_rotativo')
       ├─ run_hooks('global_intermediate')
       │     └─ op_banner_rotativo_run()
       │          ├─ $data = $cache->read('op_banner_rotativo')
       │          ├─ ¿TIME_NOW < $data['expira']? → usar $data['actual']      (caso normal, 0 queries)
       │          └─ si expiró o no existe:
       │                ├─ scandir(images/op/banners) + regex → lista de banners
       │                ├─ elegir uno al azar ≠ $data['actual']
       │                └─ $cache->update('op_banner_rotativo', {actual, expira, total})   (1 write)
       │          └─ global $op_banner_rotativo = '/images/op/banners/' . actual
       └─ eval header  →  <img src="{$op_banner_rotativo}">
```

### Estructura de la entrada en el caché

```php
array(
    'actual' => 'Banner42_One_Piece_Gaiden_Foro_Rol.jpg', // nombre del archivo, no la ruta
    'expira' => 1789000000,                               // TIME_NOW + 300
    'total'  => 61,                                       // cuántos banners había en la rotación (para diagnóstico)
)
```

Se guarda el nombre del archivo y no el número, para que la plantilla no tenga que reconstruir la ruta y para no depender de que la numeración sea continua.

## Banner fijo (añadido después)

Desde `op/staff/banners.php` se puede **fijar** un banner. Se guarda su nombre de archivo en una entrada propia del datacache, `op_banner_rotativo_fijo`, separada de `op_banner_rotativo` para que la rotación nunca la pise al reescribir la suya. Al empezar, `op_banner_rotativo_run()` mira esa entrada:

- Si hay un banner fijo, cumple la regex y existe en la raíz de `images/op/banners/`, se muestra siempre y no se toca la rotación.
- Si no hay ninguno, o el archivo ya no está (se desactivó o se borró por FTP), sigue la rotación normal. Nunca se ve una imagen rota.

Al desactivar el plugin se borran las dos entradas del caché.

## Casos límite

- **Carrera al expirar.** Dos peticiones simultáneas pueden ver el caché expirado a la vez y escribir cada una su propio banner. La última escritura gana, y el peor caso es que el banner cambie dos veces en el mismo segundo. Es inofensivo y no merece la pena ponerle un lock.
- **Directorio vacío o ilegible.** Si no hay banners válidos, no se escribe en el caché, `$op_banner_rotativo` se queda vacío y la plantilla usa el fallback.
- **Solo hay un banner.** La regla de "sin repetir" no se puede cumplir, así que se usa ese mismo.
- **Se borra el banner actual por FTP.** Durante como mucho 5 minutos se mostraría una imagen rota. Para evitarlo, se comprueba con `file_exists()` que el archivo actual sigue existiendo, lo que supone una llamada a `stat` por petición. Es barato y el sistema de archivos lo cachea.
- **Caché del navegador.** Cada banner tiene su propia URL, así que el navegador cachea cada imagen por separado y el cambio se nota en cuanto se genera el HTML nuevo. Las páginas del foro no se cachean en HTML, así que no hay nada más que invalidar.
- **Usuarios invitados (uid 0).** Pasan por la rama `<else>` y ven la rotación igual que los demás.

## Fuera de alcance

- Un panel de administración para activar o desactivar banners concretos o cambiar el intervalo. Si hace falta más adelante, se puede añadir como settings del plugin.
- Cambiar los banners por UID.
- Rotación por JavaScript en el cliente (cambiar el banner sin recargar la página).

## Preguntas abiertas

1. ¿Deben entrar **todos** los banners del 1 al 61, o hay algunos antiguos o de temporada que conviene excluir? El diseño permite una lista de exclusión en una constante (`OP_BANNER_ROTATIVO_EXCLUIR`) si hiciera falta.
2. ¿Se borra el `$banner_number = rand(1, 56);` que no se usa en `global.php:638`? No afecta al plugin, pero es código muerto que puede confundir.
