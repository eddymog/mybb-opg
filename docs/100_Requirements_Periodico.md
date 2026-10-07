# Requisitos: Periódico

## 1. Propósito

La página pública actual `op/periodico.php` solo renderiza el template
`op_periodico`: las páginas y noticias están escritas directamente en HTML y
los carteles wanted viven en `jscripts/periodico.js`. Publicar una noticia
exige editar código o templates, no hay borradores ni historial editorial y
el contenido está mezclado con la presentación.

Este documento define un sistema editorial para que el staff pueda crear,
guardar, previsualizar, publicar y archivar ediciones de *El Gaviotazo* desde
una herramienta propia. El público consulta la última edición publicada y el
archivo histórico.

## 2. Decisiones tomadas

- La gestión vive en `/op/staff/periodico.php`.
- El periódico público sigue en `/op/periodico.php`; sin parámetro muestra la
  última edición publicada.
- El número de edición se escribe manualmente en el editor. Debe ser único y
  validarse antes de guardar o publicar.
- El contenido editorial se guarda en la base de datos. No se guardan páginas
  completas como HTML libre.
- Una edición se compone de páginas y bloques ordenables. La configuración de
  cada bloque se guarda como JSON validado por el servidor.
- Los cuerpos de texto usan BBCode de MyBB y se procesan con su parser. No se
  permite HTML arbitrario desde el editor.
- Los borradores nunca son públicos. Solo el estado `publicado` puede aparecer
  en la ruta pública o en el archivo.
- Una edición publicada puede editarse directamente. Cada guardado y cada
  publicación quedan en la auditoría para preservar su trazabilidad.
- Los wanted publicados son una instantánea: un cambio posterior de ficha, NPC
  o recompensa no modifica una edición histórica.
- Cada edición tiene exactamente un bloque `portada`, situado como primer bloque
  de la primera página.
- Las imágenes pueden proceder de `/images/op/uploads/` o de una URL externa
  HTTPS validada.
- El panel staff reutiliza `.opg-card`, `.btn-op`, `.opg-chip` y `.af-field`.

## 3. Alcance

La primera versión debe permitir:

- listar ediciones con estado, número, fecha, autor y acciones;
- crear, guardar, duplicar, publicar y archivar ediciones;
- editar páginas y bloques de contenido ordenados;
- previsualizar el mismo renderer que verá el público;
- mostrar la última edición y navegar el archivo histórico;
- conservar auditoría editorial básica.

Fuera de alcance: edición colaborativa en tiempo real, comentarios de
lectores, publicación programada, importación de fuentes externas, un editor
de CSS y bloques arbitrarios de HTML o JavaScript.

## 4. Modelo de datos

| Tabla | Responsabilidad | Campos mínimos |
|---|---|---|
| `mybb_op_periodicos` | Metadatos y ciclo de vida de una edición. | `id`, `slug`, `numero`, `titulo`, `subtitulo`, `portada`, `tema`, `estado`, `fecha_publicacion`, `autor_uid`, `firma_visible`, timestamps. |
| `mybb_op_periodico_paginas` | Páginas de una edición y su orden. | `id`, `periodico_id`, `orden`, `titulo`, `tema`. |
| `mybb_op_periodico_bloques` | Bloques ordenados dentro de una página. | `id`, `pagina_id`, `orden`, `tipo`, `contenido_json`. |
| `mybb_op_periodico_auditoria` | Rastro de cambios relevantes. | `id`, `periodico_id`, `uid`, `accion`, `detalle_json`, `fecha`. |

La migración debe indexar `estado`/`fecha_publicacion`, `periodico_id` y
`pagina_id`. El `slug` es único y estable: una edición archivada mantiene su
URL.

### 4.1 Estados

- `borrador`: editable por staff o narrador autorizado y no público.
- `publicado`: visible públicamente y en el archivo.
- `archivado`: visible en el archivo, pero no es la edición actual.

Solo una edición es la portada pública actual. Al publicar una nueva, la
anterior pasa a `archivado`, sin borrar sus páginas ni bloques.

## 5. Bloques de la primera versión

| Tipo | Uso | Datos JSON requeridos |
|---|---|---|
| `portada` | Titular principal. | Titular, bajada, cuerpo BBCode, imagen, pie, autor y etiqueta opcional. |
| `noticia` | Artículo estándar. | Titular, entradilla opcional, cuerpo BBCode, imagen opcional, pie y enlace relacionado opcional. |
| `dos_columnas` | Dos notas relacionadas. | Objetos `izquierda` y `derecha` con título, cuerpo e imagen opcional. |
| `recuadro` | Contexto, aviso, cita o dato destacado. | Título opcional, cuerpo BBCode y variante aprobada. |
| `wanted` | Cartel histórico de jugador o NPC. | Nombre, recompensa, imagen, enlace opcional, tipo y estado. Son valores congelados. |
| `galeria` | Conjunto breve de imágenes. | Lista ordenada de imágenes, texto alternativo y pie opcional. |
| `separador` | Ruptura editorial. | Variante aprobada, sin texto libre. |

Cada tipo tiene un esquema JSON propio. Un bloque que no pasa validación no se
guarda ni se renderiza. Un tipo de bloque desconocido se muestra como error de
staff, nunca como HTML sin filtrar.

## 6. Herramienta de staff

### 6.1 Acceso y listado

- El acceso usa `is_staff($uid)` o `is_narra($uid)` mientras no exista un
  permiso editorial más específico.
- El listado muestra número, título, estado, fecha de publicación, autor y
  última modificación.
- Acciones: crear, editar, duplicar, previsualizar, publicar, archivar y abrir
  la versión pública.
- Duplicar copia páginas y bloques, pero crea un nuevo borrador, slug y número.

### 6.2 Editor

- La cabecera permite editar título, subtítulo, número, fecha, portada y tema
  visual aprobado.
- La firma visible es editable y puede usar un nombre editorial, mientras
  `autor_uid` conserva al responsable real para auditoría.
- Las páginas se gestionan en pestañas: agregar, renombrar, reordenar y borrar
  páginas vacías.
- Cada página contiene un constructor vertical de bloques: editar, subir,
  bajar, duplicar y eliminar.
- El editor garantiza una única portada como primer bloque de la primera página;
  no permite moverla, duplicarla ni eliminarla sin reemplazarla.
- La ordenación debe funcionar con botones explícitos. Drag and drop puede ser
  un complemento, nunca la única vía.
- La vista previa usa el mismo renderer que la página pública y no muestra
  controles administrativos, no persiste cambios, no es indexable y exige
  permiso staff más un `post_code` válido.
- `Guardar borrador` persiste sin cambiar visibilidad. `Publicar edición`
  requiere confirmación y registra auditoría.

## 7. Render público

- `/op/periodico.php` muestra la última edición publicada.
- `/op/periodico.php?edicion=N`, o un slug equivalente, muestra una edición
  publicada o archivada del archivo.
- Una edición inexistente, borrador o no autorizada responde como no
  encontrada, sin filtrar contenido.
- La navegación entre páginas se genera desde `mybb_op_periodico_paginas`, no
  desde elementos hardcodeados en JavaScript.
- El archivo es una lista compacta de ediciones con número, fecha, portada y
  titular; permite filtrar por año sin cargar el contenido completo de todas
  las ediciones.
- El renderer escapa todo texto que no sea BBCode, valida URLs de imágenes y
  procesa BBCode mediante MyBB.
- Un enlace relacionado de una noticia puede ser un `tid` o una URL interna;
  no se permiten enlaces no validados en ese campo estructurado.

## 8. Migración del periódico actual

La primera edición en la base debe recrear el contenido existente de
`op_periodico`. Se importa como una edición histórica publicada, con páginas,
textos, imágenes y wanted congelados. Antes de retirar las noticias del
template se conserva una copia y se valida el render migrado: mismo número y
orden de páginas, titulares, cuerpos, imágenes, enlaces y carteles wanted.

## 9. Requisitos no funcionales

- El panel staff sigue la estética OPG: borde negro de 2-3px, crema, naranja y
  morado, sombras duras y sin degradados entre matices ni sombras difusas.
- El editor y el render público son usables en pantallas de hasta 700px; las
  columnas se apilan y los controles conservan texto legible.
- Las escrituras usan `post_code` de MyBB, permisos de servidor, consultas
  escapadas y validación por tipo de bloque.
- Las plantillas MyBB no pueden contener identificadores JavaScript que
  empiecen con `$`, salvo interpolaciones intencionales como `{$variable}`.
- CSS y JS nuevos usan versionado de query string al cambiar.

## 10. Criterios de aceptación

1. Un usuario para el que `is_staff($uid)` o `is_narra($uid)` devuelve verdadero crea una edición
   con dos páginas y distintos bloques, guarda un borrador, cierra sesión y al
   volver lo encuentra intacto.
2. La vista previa y el render público muestran el mismo orden, texto, BBCode e
   imágenes de una edición publicada.
3. Un jugador no puede abrir un borrador por ID, slug, URL de preview o
   petición directa.
4. Al publicar una edición nueva, aparece en `/op/periodico.php` y la anterior
   queda accesible en el archivo.
5. Los wanted de una edición no cambian al editar la ficha o recompensa actual
   del personaje.
6. El sistema rechaza JSON de bloque inválido, URLs de imagen no permitidas y
   HTML arbitrario.
7. La auditoría registra creación, guardado, publicación, archivado y el usuario
   responsable.
8. La edición migrada desde el template actual conserva el mismo número y orden
   de páginas, titulares, cuerpos, imágenes, enlaces y wanted antes de retirar
   las noticias hardcodeadas.
