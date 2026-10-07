# Periódico: plan de diseño

> Depende de: [100_Requirements_Periodico.md](100_Requirements_Periodico.md)
>
> **Estado: por implementar.** El periódico actual sigue siendo estático en
> `templates/One_Piece_Gaiden_Templates/op_periodico.html` hasta completar la
> migración descrita en §9.

## 1. Objetivo

Reemplazar el periódico hardcodeado por un CMS editorial pequeño y específico
para OPG. El staff administra ediciones desde `/op/staff/`; el público solo
lee ediciones publicadas o archivadas. El contenido se representa con bloques
JSON de esquema fijo, no con HTML libre.

La edición de una publicación existente queda permitida por requisito. La
auditoría guarda una instantánea anterior y posterior de los metadatos o el
bloque que cambió; no se crea una entidad de revisiones completa en la primera
versión.

## 2. Archivos

| Archivo | Responsabilidad | Estado |
|---|---|---|
| `inc/plugins/lib/periodico.php` | Acceso, validación, CRUD de dominio, auditoría y renderer compartido de bloques. | Nuevo |
| `op/staff/periodico.php` | Listado de ediciones y acciones de crear, duplicar, publicar y archivar. | Nuevo |
| `op/staff/periodico_editar.php` | Editor de una edición, páginas y bloques. | Nuevo |
| `op/periodico.php` | Obtiene una edición pública o archivada y delega el render al helper. | Editado |
| `templates/One_Piece_Gaiden_Templates/staff_periodico_listado.html` | Pantalla de listado staff. | Nuevo |
| `templates/One_Piece_Gaiden_Templates/staff_periodico_editor.html` | Editor staff y preview. | Nuevo |
| `templates/One_Piece_Gaiden_Templates/op_periodico.html` | Shell público con el HTML renderizado por el helper. Reemplaza las noticias estáticas. | Editado |
| `jscripts/periodico_editor.js` | Acciones de interfaz: orden visual, confirmaciones y preview local. No persiste datos por sí solo. | Nuevo |
| `jscripts/periodico.css` | Renderer público editorial. | Nuevo |
| `jscripts/periodico_editor.css` | Panel staff OPG. | Nuevo |
| `docs/periodico_migration.sql` | Creación de tablas e índices. | Nuevo |
| `op/staff/periodico_importar_legacy.php` | Importador de una sola ejecución para el template actual. Se elimina o desactiva tras usarlo. | Nuevo |

Los nuevos JS se cargan con query string de versión y no contienen variables
que empiecen por `$`, por la evaluación de templates de MyBB.

## 3. Datos y migración SQL

### 3.1 `mybb_op_periodicos`

```sql
CREATE TABLE mybb_op_periodicos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(100) NOT NULL,
  numero INT UNSIGNED NOT NULL,
  titulo VARCHAR(255) NOT NULL,
  subtitulo VARCHAR(500) NOT NULL DEFAULT '',
  portada VARCHAR(500) NOT NULL DEFAULT '',
  tema VARCHAR(32) NOT NULL DEFAULT 'clasico',
  estado ENUM('borrador','publicado','archivado') NOT NULL DEFAULT 'borrador',
  fecha_publicacion DATETIME NULL,
  autor_uid INT UNSIGNED NOT NULL,
  firma_visible VARCHAR(120) NOT NULL DEFAULT 'Big News Morgans',
  fecha_creacion DATETIME NOT NULL,
  fecha_actualizacion DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug),
  UNIQUE KEY numero (numero),
  KEY estado_fecha (estado, fecha_publicacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`numero` se escribe manualmente, pero la restricción única es la autoridad
final: el formulario valida primero y el servidor captura un duplicado de
base para devolver un mensaje entendible.

### 3.2 Páginas, bloques y auditoría

```sql
CREATE TABLE mybb_op_periodico_paginas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  periodico_id INT UNSIGNED NOT NULL,
  orden SMALLINT UNSIGNED NOT NULL,
  titulo VARCHAR(255) NOT NULL DEFAULT '',
  tema VARCHAR(32) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY periodico_orden (periodico_id, orden),
  KEY periodico_id (periodico_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mybb_op_periodico_bloques (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pagina_id INT UNSIGNED NOT NULL,
  orden SMALLINT UNSIGNED NOT NULL,
  tipo VARCHAR(32) NOT NULL,
  contenido_json MEDIUMTEXT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY pagina_orden (pagina_id, orden),
  KEY pagina_id (pagina_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mybb_op_periodico_auditoria (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  periodico_id INT UNSIGNED NOT NULL,
  pagina_id INT UNSIGNED NULL,
  bloque_id INT UNSIGNED NULL,
  uid INT UNSIGNED NOT NULL,
  accion VARCHAR(32) NOT NULL,
  detalle_json MEDIUMTEXT NOT NULL,
  fecha DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY periodico_fecha (periodico_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Las claves foráneas no se añaden en la primera versión porque las tablas MyBB
existentes no garantizan InnoDB de forma uniforme. La biblioteca borra bloques
antes de páginas y páginas antes de una edición si alguna operación futura lo
necesita.

## 4. Biblioteca `inc/plugins/lib/periodico.php`

La biblioteca concentra reglas que no deben quedar duplicadas entre staff y
render público:

```php
opg_periodico_puede_editar($uid)
opg_periodico_buscar_publicado($edicion, $slug)
opg_periodico_cargar($id, $con_paginas, $con_bloques)
opg_periodico_validar_edicion($input, $actual)
opg_periodico_validar_bloque($tipo, $contenido)
opg_periodico_guardar_edicion($datos, $uid)
opg_periodico_guardar_paginas_y_bloques($periodico_id, $paginas, $uid)
opg_periodico_publicar($periodico_id, $uid)
opg_periodico_archivar($periodico_id, $uid)
opg_periodico_auditar($periodico_id, $uid, $accion, $anterior, $posterior)
opg_periodico_render_edicion($periodico)
opg_periodico_render_bloque($bloque)
```

`opg_periodico_puede_editar()` llama a `is_staff($uid)` o `is_narra($uid)`. Todas las rutas
staff lo usan antes de consultar o escribir contenido.

La biblioteca usa `$db->escape_string()` y transacciones para guardar una
edición completa. Un fallo al guardar una página o bloque revierte todos los
cambios de ese submit.

### 4.1 Validación de contenido

`opg_periodico_validar_bloque()` recibe un tipo conocido y un array, nunca un
JSON crudo de confianza. Devuelve el array normalizado o una lista de errores.
Luego el endpoint serializa ese resultado con `json_encode()`.

Reglas comunes:

- textos con límites de longitud por campo;
- imágenes locales bajo `/images/op/uploads/` o URL absoluta `https`;
- sin atributos HTML ni JavaScript en campos de texto;
- cuerpo de artículo como texto BBCode; el renderer lo procesa con el parser
  de MyBB;
- `enlace_relacionado` acepta un `tid` positivo o URL interna relativa que
  comience con `/`;
- wanted requiere nombre, recompensa, imagen y tipo; no consulta la ficha al
  renderizar.

## 5. Contratos de bloques

El JSON queda normalizado y con estas claves. Campos opcionales se guardan
como cadena vacía o lista vacía, no se omiten arbitrariamente.

```json
{
  "portada": {
    "titular": "",
    "bajada": "",
    "cuerpo": "",
    "imagen": "",
    "pie": "",
    "autor": "",
    "etiqueta": ""
  },
  "noticia": {
    "titular": "",
    "entradilla": "",
    "cuerpo": "",
    "imagen": "",
    "pie": "",
    "enlace_relacionado": ""
  },
  "wanted": {
    "nombre": "",
    "recompensa": "",
    "imagen": "",
    "enlace": "",
    "tipo": "jugador",
    "estado": "vivo"
  }
}
```

`portada` solo es válida en posición `(página 1, bloque 1)`. Al crear una
edición se generan automáticamente una primera página y una portada vacía. El
endpoint rechaza un submit sin portada o con más de una.

## 6. Rutas y flujos

### 6.1 Listado staff: `op/staff/periodico.php`

- `GET`: carga borradores, publicadas y archivadas en una tabla OPG.
- `POST action=crear`: crea una edición borrador con número, slug, metadatos,
  primera página y portada vacía dentro de una transacción.
- `POST action=duplicar`: duplica metadatos, páginas y bloques; crea número y
  slug nuevos, ambos obligatorios.
- `POST action=publicar`: valida la edición completa, archiva la publicación
  actual y publica la elegida en una única transacción.
- `POST action=archivar`: pasa una publicación a archivo. Si es la única
  edición publicada, pide confirmación reforzada y deja la portada pública sin
  edición hasta que se publique otra.

Todos los POST verifican `post_code` y redirigen con un mensaje MyBB.

### 6.2 Editor: `op/staff/periodico_editar.php?id=N`

- `GET`: carga una edición y construye los campos de páginas/bloques.
- `POST action=guardar`: valida y persiste metadatos, páginas y bloques.
- `POST action=preview`: valida el payload sin escribir, renderiza una preview
  temporal en la misma respuesta y no deja una URL pública.
- `POST action=eliminar_bloque` y reordenamientos se resuelven como parte de
  `guardar`, no como endpoints separados: menos superficie CSRF y una sola
  transacción.

El formulario envía páginas y bloques como arrays estructurados. JavaScript
solo mantiene el orden y el estado visual; el servidor recalcula `orden` al
persistir, por lo que un cliente manipulado no puede crear huecos ni duplicados.

### 6.3 Público: `op/periodico.php`

- Sin parámetros: consulta la última fila con `estado='publicado'` por
  `fecha_publicacion DESC, id DESC`.
- `?edicion=N`: carga por `numero`; `?slug=X`: carga por slug. Solo acepta
  `publicado` o `archivado`.
- Si no encuentra edición: muestra un estado vacío editorial, sin errores PHP.
- El archivo se obtiene con consulta paginada y filtro opcional `anio=YYYY`.

La página no necesita JavaScript para mostrar contenido. JavaScript puede
mejorar la navegación de páginas, pero todos los datos se entregan renderizados
por el servidor.

## 7. Templates y diseño

`staff_periodico_listado.html` y `staff_periodico_editor.html` usan el marco
OPG de tres fondos. La pantalla de editor tiene:

- cabecera editorial con estado y metadatos;
- pestañas de páginas;
- constructor vertical sin tarjetas anidadas;
- una preview que pasa debajo del editor en móvil;
- `Publicar edición` como único CTA naranja;
- `Guardar borrador`, `Duplicar` y `Archivar` como acciones secundarias.

El renderer público conserva el tono periodístico, pero recibe clases y datos
de `opg_periodico_render_bloque()`. No conserva los estilos inline actuales ni
el objeto `wantedData` hardcodeado en `periodico.js`.

`periodico_editor.js` usa variables como `editorBloques`, nunca identificadores
con `$`. Se carga después del HTML del formulario, por ejemplo:

```html
<script src="{$mybb->asset_url}/jscripts/periodico_editor.js?v=1"></script>
```

## 8. Auditoría

Cada mutación crea una fila de auditoría. `detalle_json` tiene esta forma:

```json
{
  "antes": { "estado": "borrador", "titulo": "..." },
  "despues": { "estado": "publicado", "titulo": "..." }
}
```

Para bloques, la instantánea contiene `tipo`, `orden` y `contenido_json` antes
y después. El JSON se limita a los campos modificados cuando sea posible;
publicar y archivar registran el cambio de estado completo. Esto permite saber
quién editó una noticia publicada sin bloquear el flujo editorial directo.

## 9. Importación del periódico actual

1. Copiar `op_periodico.html` antes de modificarlo.
2. Inventariar sus diez `.newspaper-page`: orden, títulos, cuerpo, imágenes,
   themes y wanted.
3. Construir un payload de importación que cree una edición publicada con esos
   datos congelados.
4. Ejecutar el importador solo como staff y registrar la operación en auditoría.
5. Comparar el renderer nuevo con el template: mismo orden de páginas,
   titulares, cuerpos, imágenes, enlaces y wanted.
6. Solo tras la validación, reemplazar las noticias estáticas por el renderer
   público y retirar `wantedData` de `periodico.js`.

El importador debe rechazar ejecutarse si ya existe una edición con el mismo
número o slug; no se permite una importación accidental duplicada.

## 10. Pruebas y entrega

1. Probar permisos: invitado y usuario normal reciben `error_no_permission()`;
   `is_staff($uid)` o `is_narra($uid)` puede operar el panel.
2. Crear una edición, guardar un borrador, duplicarla y comprobar que los
   bloques no comparten IDs ni JSON mutable.
3. Validar errores de número/slug duplicados, portada ausente, JSON inválido y
   URL de imagen no permitida.
4. Publicar una edición y comprobar que la anterior se archiva en la misma
   transacción.
5. Editar directamente una publicada y comprobar su cambio público más la fila
   de auditoría con antes/después.
6. Comprobar que `?edicion=` y `?slug=` no muestran borradores.
7. Probar renderer y editor en escritorio y ≤700px.
8. Verificar BBCode, imágenes, wanted congelados y enlaces relacionados en el
   importado antes de eliminar contenido del template anterior.
