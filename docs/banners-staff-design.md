# Herramienta de staff para los banners: diseño

> Depende de: [banner-rotativo-design.md](banner-rotativo-design.md) (plugin `op_banner_rotativo`)

## Objetivo

Que el staff pueda **añadir, desactivar, eliminar y restaurar** los banners de la rotación del header desde el foro, sin tener que subir archivos por FTP. También podrá **forzar** qué banner se ve en ese momento.

## Dónde vive

- Página nueva: `op/staff/banners.php`.
- `op/staff/consola_mod.php` no cambia: solo carga la plantilla `staff_consola_mod`, que es una lista de botones. Se añade el botón **"Banners del header"** en la categoría *Utilidades* de `templates/One_Piece_Gaiden_Templates/staff_consola_mod.html`, al lado de "Hosting de imágenes" y "Modificar Sabías Qué".
- La página es PHP con el HTML en el mismo archivo y sin plantilla en la base de datos, así que se instala solo con subir el archivo. Se pinta **dentro del layout del foro**: usa `$headerinclude`, `$header` y `$footer` de `global.php` y termina con `output_page()`.

## Modelo de datos: la carpeta es la fuente de verdad

No hay tabla nueva. El estado de cada banner es **la carpeta en la que está su archivo**:

| Estado | Carpeta | ¿Rota en el header? |
|---|---|---|
| Activo | `images/op/banners/` | Sí |
| Inactivo | `images/op/banners/_inactivos/` | No |
| Papelera | `images/op/banners/_papelera/` | No |

El plugin solo escanea la raíz (`scandir` sin recursión, filtrado por la regex), así que mover un archivo a una subcarpeta lo saca de la rotación sin tocar el plugin. El número del banner (`Banner<N>`) es su identificador en todas las carpetas.

**¿Por qué no una tabla `mybb_op_banners`?** Para añadir, modificar y eliminar no hace falta: el plugin ya lee la carpeta, y una tabla obligaría a mantener sincronizados la base de datos y los archivos. Si en el futuro se quieren banners programados (por ejemplo, Navidad del 20 al 31 de diciembre) o con más peso que otros, ese sería el momento de crear la tabla.

## Acciones

| Acción | Desde | Efecto |
|---|---|---|
| **Subir** | — | Se guarda como `Banner{N}` en la raíz. N = el número más alto en *todas* las carpetas + 1, para que restaurar un banner nunca choque con uno nuevo. |
| **Desactivar** | activo | Pasa a `_inactivos/`. |
| **Activar / Restaurar** | inactivo o papelera | Vuelve a la raíz. |
| **Eliminar** | activo o inactivo | Pasa a `_papelera/`. **No se borra nada del disco.** |
| **Fijar** (panel de arriba) | activo | Guarda el nombre del archivo en la entrada `op_banner_rotativo_fijo` del datacache. El plugin muestra siempre ese banner hasta que se pulse *Quitar*. |
| **Quitar** fijado | — | Borra `op_banner_rotativo_fijo`: vuelve la rotación. |

**No se vacía la caché de la rotación tras cada cambio.** El plugin ya se comporta bien:
- Un banner **nuevo o reactivado** entra en la lista la próxima vez que caduque la caché (como mucho 5 minutos). Si se quiere ver ya, se fija.
- Si se **desactiva o elimina el banner que se está mostrando**, el `file_exists()` del plugin lo detecta en la siguiente petición y elige otro. Nunca se ve una imagen rota.
- No hay acción de *Reemplazar*: para cambiar la imagen de un banner, se sube uno nuevo y se elimina el viejo.

**Banners protegidos:** `header.html` usa Banner59 (el respaldo del `<else>`) y Banner60 (el banner fijo de ciertos UIDs) con la ruta fija. Si se sacaran de la raíz, esas imágenes aparecerían rotas. Por eso la herramienta no permite *Desactivar* ni *Eliminar* los números de `$banners_protegidos = array(59, 60)`: oculta esos botones y rechaza el POST si llega igualmente. Sí se pueden fijar.

**Fuera de alcance:** vaciar la papelera. Para borrar definitivamente, se hace por FTP. Así se evita tener un `unlink()` desde la web.

## Validación de imágenes

1. La subida llega sin errores (`UPLOAD_ERR_OK`) y pesa como mucho **5 MB**.
2. `getimagesize()` confirma que es **JPEG, PNG o WEBP**. No se mira la extensión, que se puede falsificar.
3. **Proporción 1100:620** con un 2 % de tolerancia. Todos los banners actuales miden 1100×620. Una proporción distinta deformaría el header, así que se rechaza con un mensaje que indica el tamaño recibido.
4. **Siempre se recodifica con GD**: `imagecreatefromstring()`, luego `imagecopyresampled()` a 1100×620 y por último `imagejpeg()` con calidad 88. Así:
   - todos los banners quedan como `.jpg` del mismo tamaño, que es lo que espera la regex del plugin;
   - los que suban imágenes de 2200×1240 no inflan la página;
   - se elimina cualquier cosa escondida en el archivo que no sea la imagen, como EXIF o contenido incrustado.
5. Se escribe primero en `Banner{N}_...jpg.tmp` y después se hace `rename()`. El rename es atómico, así que el plugin nunca ve un JPG a medio escribir. El `.tmp` no cumple la regex y por eso no entra en la rotación.

## Seguridad

- **Acceso:** `is_admin($uid)` (grupo 4, como grupo principal o en `additionalgroups`) antes de cualquier lógica. Si no es admin, se muestra la plantilla `op_redireccion`, como en la consola. El resto del staff y los moderadores ven el botón en la consola, pero la herramienta les denegará el acceso.
- **CSRF:** todas las acciones son POST y pasan `verify_post_check()`. No hay ninguna acción por GET.
- **Sin rutas controladas por el usuario:** el formulario solo envía `accion` y `n` (convertido con `MyBB::INPUT_INT`). El servidor construye el nombre del archivo (`banners_nombre($n)`) y la carpeta sale de una lista fija de tres estados. No hay forma de salirse de la carpeta con `../`.
- **Escapado de salida:** todo lo que se imprime pasa por `htmlspecialchars`, incluidos los mensajes `?ok=` y `?err=` de la redirección.
- **Auditoría:** cada acción que sale bien se registra en `mybb_op_audit_general` mediante `log_audit()`, con `categoria = 'banners'`. `log_audit()` interpola sin escapar, así que el usuario y el texto se escapan antes con `$db->escape_string()`.
- **Post/Redirect/Get:** después de cada POST se redirige siempre a `banners.php`, sin query string. El aviso ("Banner N fijado", un error…) viaja en una cookie de un solo uso, `banners_msg` (HttpOnly, 15s de vida como red de seguridad), que la propia página borra en cuanto la lee con `my_unsetcookie()`. Así, pulsar F5 no repite la acción ni deja el mensaje visible en la URL.

## Cambio en el plugin `op_banner_rotativo`

Añadir `?v=<filemtime>` a la URL del banner:

```php
$op_banner_rotativo = '/' . OP_BANNER_ROTATIVO_DIR . $data['actual']
    . '?v=' . @filemtime(MYBB_ROOT . OP_BANNER_ROTATIVO_DIR . $data['actual']);
```

Sin esto, si alguien sobrescribe por FTP un banner con el mismo nombre, los navegadores seguirían mostrando la imagen vieja que tienen en caché. El coste es una llamada a `stat()` que PHP ya tiene en caché gracias al `file_exists()` anterior.

La página de staff carga el plugin con `require_once` para reutilizar sus constantes (`OP_BANNER_ROTATIVO_DIR`, `_PATRON`, `_CACHE`, `_INTERVALO`). Si el plugin está activo, MyBB ya lo ha cargado con la misma ruta y `require_once` no lo vuelve a incluir.

## Interfaz

- Un formulario "Nuevo banner" arriba.
- Arriba, el panel **Banner fijo**: muestra cuál está fijado (con *Quitar*) y un campo "Banner nº" con sugerencias de los activos para *Fijar* o *Cambiar*. Debajo, **Nuevo banner**: el botón *Subir* solo aparece cuando se ha elegido un archivo (CSS `:has(input:valid)`, sin JavaScript).
- Después, una **cuadrícula de dos columnas** (una en móvil) con los activos. Cada tarjeta muestra su número, la miniatura, la etiqueta "En el header" o "Fijado" si es el banner actual y "Protegido" si es el 59 o el 60, y los botones *Desactivar* y *Eliminar* (este último pide confirmación). El banner fijado no tiene esos botones.
- Secciones plegables (`<details>`) para *Inactivos* y *Papelera*. La de inactivos aparece abierta si tiene algún banner.
- Estilo según [style.md](style.md), tomando como referencia `op_intercambio` (Intercambios de usuarios). Reutiliza sus clases de `op_global.css`: el marco `.mainBackground`, `.secondBackground` y `.thirdBackground`, las barras naranjas `.barra-op` con `.barra-texto-op`, y los cuerpos crema `.barra-espacio-op`. Los botones de acción usan el morado de *ENTREGAR*, y las secciones usan la barra naranja de *Historial de Intercambios*. Los botones pequeños van en morado (*Fijar*, *Subir*), naranja `#d26500` (*Desactivar*), verde (*Activar*) y rojo (*Eliminar*). El banner actual se marca en morado `#8f59f7`. En móvil, la cuadrícula pasa a una columna y el ancho fijo de 1030 px de `.thirdBackground` se sustituye por `max-width`.

## Riesgos

- **Sincronización por FTP:** `images/op/` está en `.gitignore`, así que los banners solo existen en el servidor. Si alguien sube por FTP una copia local antigua de `images/op/banners/`, reaparecerán banners eliminados o se perderán los subidos desde la herramienta.
- **Permisos:** el usuario de PHP necesita permiso de escritura en `images/op/banners/` para crear `_inactivos/` y `_papelera/` y guardar los archivos. `imagenes_islas.php` ya escribe en `images/op/uploads/`, así que probablemente ya lo tenga, pero hay que comprobarlo.
- **GD con soporte WEBP:** el servidor necesita GD con WEBP para aceptar ese formato. Si no lo tiene, `imagecreatefromstring` falla y la herramienta muestra "No se pudo leer la imagen". JPG y PNG funcionan con cualquier GD.
