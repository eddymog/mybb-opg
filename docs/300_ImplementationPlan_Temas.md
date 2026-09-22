# Bitacora de rol: plan de implementacion

> Requisitos: [100_Requirements_Temas.md](100_Requirements_Temas.md)
>
> Diseno: [200_DesignPlan_Temas.md](200_DesignPlan_Temas.md)

## 1. Objetivo de este documento

Este plan convierte el diseno aprobado en tareas de codigo ordenadas y
verificables. Define archivos, funciones, consultas, contratos y pruebas, pero
no implementa todavia el plugin.

La implementacion se considera terminada cuando:

- publicar en una zona de rol crea o actualiza el seguimiento;
- las rondas se calculan con todos los participantes esperados;
- la pagina `/op/bitacora.php` permite administrar el tracker completo;
- los temas cerrados permanecen visibles hasta su retirada manual;
- los temas inaccesibles o fuera de rol no aparecen;
- el header muestra los dos conteos activos del personaje actual;
- todas las mutaciones validan propiedad, permisos y `post_key`;
- no existen consultas N+1 por tema o participante.

## 2. Archivos

### 2.1 Crear

| Archivo | Responsabilidad |
|---|---|
| `inc/plugins/op_bitacora.php` | Ciclo de vida del plugin, hooks y header |
| `inc/plugins/op_bitacora/functions.php` | Motor de seguimiento y rondas |
| `op/bitacora.php` | Controlador de pagina, acciones y fragmentos HTMX |
| `op/temas.php` | Redireccion compatible para enlaces antiguos |
| `docs/bitacora_migration.sql` | Creacion manual e idempotente de las tablas del tracker |
| `templates/One_Piece_Gaiden_Templates/op_bitacora.html` | Pagina completa |
| `templates/One_Piece_Gaiden_Templates/op_bitacora_contenido.html` | Region HTMX con resumen, pestanas y listas |
| `templates/One_Piece_Gaiden_Templates/op_bitacora_header.html` | Barra compacta del header |
| `templates/One_Piece_Gaiden_Templates/stylesheets/op_bitacora.css` | Estilos especificos |

### 2.2 Modificar

| Archivo | Cambio |
|---|---|
| `templates/One_Piece_Gaiden_Templates/header.html` | Insertar `{$op_temas_header}` cerca de `#peticiones_staff` |

No se modifican `global.php`, `mybb_posts`, `mybb_threads`,
`mybb_op_thread_personaje` ni Account Switcher.

## 3. Convenciones de implementacion

- Prefijo PHP: `op_bitacora_`.
- Tablas pasadas a `$db`: `op_temas_seguidos` y
  `op_temas_participantes`, sin prefijo hardcodeado.
- Identidad del personaje: `(int)$mybb->user['uid']`.
- Identidad de ficha: `op_fichas.fid`, igual al UID de MyBB.
- Tiempo: `TIME_NOW`.
- IDs: enteros positivos antes de entrar en SQL.
- Texto de salida: `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Arrays compatibles con el estilo PHP existente del proyecto.
- El motor devuelve datos y no concatena HTML.
- El controlador contiene helpers de presentacion para tarjetas, participantes
  y resultados repetidos.
- Solo se registran tres plantillas: pagina, fragmento HTMX y header.

Constantes previstas:

```php
define('OP_TEMAS_OVERRIDE_AUTO', 'auto');
define('OP_TEMAS_OVERRIDE_DUE', 'me_toca');
define('OP_TEMAS_OVERRIDE_WAIT', 'no_me_toca');
define('OP_TEMAS_THEME_TID', 3);
define('OP_TEMAS_ROLE_PARENT', '10,%');
```

## 4. Tarea 1: esqueleto y ciclo de vida del plugin

**Archivo:** `inc/plugins/op_bitacora.php`

### 4.1 Registro

Agregar la guarda `IN_MYBB`, cargar `functions.php` y registrar:

```php
$plugins->add_hook('datahandler_post_insert_post_end', 'op_temas_hook_post');
$plugins->add_hook('datahandler_post_insert_thread_end', 'op_temas_hook_post');
$plugins->add_hook('class_moderation_approve_posts', 'op_temas_hook_approve_posts');
$plugins->add_hook('class_moderation_approve_threads', 'op_temas_hook_approve_threads');
$plugins->add_hook('global_intermediate', 'op_temas_hook_header');
```

Funciones de ciclo de vida:

- `op_bitacora_info()`;
- `op_bitacora_is_installed()`;
- `op_bitacora_install()`;
- `op_bitacora_uninstall()`;
- `op_bitacora_activate()`;
- `op_bitacora_deactivate()`.

`is_installed()` debe exigir las dos tablas, no solo una.

### 4.2 Instalacion de tablas

Usar `$db->table_prefix` y `$db->build_create_table_collation()`. La creacion
debe ser idempotente mediante `$db->table_exists()`.

Tabla principal:

```sql
CREATE TABLE {prefix}op_temas_seguidos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    personaje_uid INT UNSIGNED NOT NULL,
    tid INT UNSIGNED NOT NULL,
    ronda_inicio_pid INT UNSIGNED NOT NULL DEFAULT 0,
    override_estado VARCHAR(12) NOT NULL DEFAULT 'auto',
    narrador_uid INT UNSIGNED NOT NULL DEFAULT 0,
    creado_en INT UNSIGNED NOT NULL,
    actualizado_en INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY personaje_tema (personaje_uid, tid),
    KEY tema (tid),
    KEY personaje_actualizado (personaje_uid, actualizado_en)
) ENGINE=InnoDB {collation};
```

Tabla de participantes:

```sql
CREATE TABLE {prefix}op_temas_participantes (
    seguimiento_id INT UNSIGNED NOT NULL,
    participante_uid INT UNSIGNED NOT NULL,
    origen VARCHAR(10) NOT NULL DEFAULT 'auto',
    creado_en INT UNSIGNED NOT NULL,
    PRIMARY KEY (seguimiento_id, participante_uid),
    KEY participante (participante_uid)
) ENGINE=InnoDB {collation};
```

No agregar claves foraneas hacia tablas core MyISAM.

### 4.3 Plantillas y stylesheet

Crear una funcion `op_bitacora_template_definitions()` con el mapa de nombres y
rutas fuente. Durante la instalacion:

1. leer las plantillas versionadas;
2. registrar una copia maestra `sid = -2` si no existe;
3. registrar o actualizar la copia del template set OPG;
4. leer `op_bitacora.css`;
5. insertar o actualizar su fila para `tid = 3`;
6. ejecutar `cache_stylesheet()` y `update_theme_stylesheet_list()`.

La instalacion debe fallar con un mensaje administrativo claro si falta un
archivo fuente; no debe insertar una plantilla vacia.

### 4.4 Placeholder del header

La copia versionada de `header.html` incluye una sola vez:

```html
{$op_temas_header}
```

`activate()` comprueba e inserta el placeholder en las plantillas de base de
datos que aun no lo tengan. El placeholder puede permanecer al desactivar: sin
el hook su valor es vacio y no produce HTML. Esto evita que `template_sync`
vuelva a introducir un cambio que `deactivate()` trate de retirar.

`uninstall()` elimina tablas, plantillas y stylesheet. La desinstalacion es la
unica operacion destructiva; `deactivate()` conserva los datos.

### 4.5 Verificacion

```bash
php -l inc/plugins/op_bitacora.php
php -l inc/plugins/op_bitacora/functions.php
```

En MyBB:

1. instalar y activar;
2. comprobar ambas tablas e indices;
3. confirmar una sola aparicion de `{$op_temas_header}`;
4. desactivar y reactivar sin perder tablas;
5. no desinstalar despues de introducir datos reales salvo backup previo.

## 5. Tarea 2: utilidades y contratos del motor

**Archivo:** `inc/plugins/op_bitacora/functions.php`

Implementar primero helpers sin efectos laterales:

```php
op_bitacora_escape($value): string
op_bitacora_override_valido($value): bool
op_bitacora_es_cerrado($closed): bool
op_bitacora_es_redirect($closed): bool
op_bitacora_resolver_estado(array $seguimiento): string
op_bitacora_agrupar_por_estado(array $seguimientos): array
```

`op_bitacora_resolver_estado()` recibe como minimo:

```php
array(
    'visible_tracker' => true,
    'cerrado' => false,
    'override_estado' => 'auto',
    'esperados' => 3,
    'respondieron' => 2,
)
```

Contrato de salida:

- `oculto`;
- `cerrado`;
- `debes_responder_manual`;
- `esperando_manual`;
- `debes_responder`;
- `esperando`.

Reglas importantes:

- cero participantes nunca completa una ronda;
- cerrado gana sobre cualquier override;
- `me_toca` gana sobre la ronda;
- `no_me_toca` solo es efectivo mientras la ronda corregida este incompleta;
- todos los participantes respondieron cuando
  `esperados > 0 && respondieron >= esperados`.

Agregar helpers de infraestructura:

```php
op_bitacora_tablas_listas(): bool
op_bitacora_personaje_tiene_ficha(int $uid): bool
op_bitacora_cargar_tema(int $tid): ?array
op_bitacora_es_foro_rol(int $fid): bool
op_bitacora_puede_ver_foro(int $fid): bool
op_bitacora_cargar_seguimiento(int $id, int $ownerUid): ?array
```

`op_bitacora_es_cerrado()` debe distinguir un cierre real de un redirect
`moved|...`. `op_bitacora_es_redirect()` identifica esos redirects y la lectura
los trata como ocultos, no como temas activos ni cerrados.

### Verificacion

Crear un script temporal o una prueba ligera que cubra la tabla de verdad del
resolver. El script no se despliega y debe eliminarse cuando las pruebas
queden documentadas.

## 6. Tarea 3: operaciones de escritura

Todas las mutaciones viven en `functions.php`. Devuelven un resultado
estructurado, por ejemplo:

```php
array('ok' => true, 'code' => 'tema_agregado', 'id' => 15)
```

o:

```php
array('ok' => false, 'code' => 'tema_no_disponible')
```

No devuelven HTML ni llaman `redirect()`.

### 6.1 Procesar un post visible

Funcion principal:

```php
op_bitacora_procesar_post(int $pid): void
```

Debe releer el post desde base de datos. No depender de nombres internos del
`PostDataHandler` aparte del PID retornado por el hook.

Secuencia:

1. cargar `posts.pid`, `tid`, `fid`, `uid`, `visible`;
2. salir si no es visible, el UID es cero o no tiene ficha;
3. cargar thread y forum;
4. salir si el thread no es visible o no pertenece a rol;
5. localizar el seguimiento del autor;
6. insertar si no existe;
7. si existe y el PID es posterior a su punto de ronda, fijar
   `ronda_inicio_pid = pid`, `override_estado = auto` y timestamp;
8. si era alta, sembrar los participantes historicos;
9. agregar al autor como participante de los otros trackers de ese TID.

La comparacion del paso 7 evita que aprobar tarde un post antiguo rebobine una
ronda mas reciente o consuma un override posterior. El upsert debe apoyarse en
`UNIQUE(personaje_uid, tid)`. Tras un insert, releer el ID mediante
`$db->insert_id()` o por la clave unica.

### 6.2 Sembrar participantes

```php
op_bitacora_sembrar_participantes(int $seguimientoId, int $tid, int $ownerUid): void
```

Consulta autores distintos con posts visibles, ficha existente y UID distinto
al propietario. Insertar con `INSERT IGNORE` y `origen = auto`.

No usar `op_thread_personaje`: la participacion real sale de `posts`.

### 6.3 Propagar un participante nuevo

```php
op_bitacora_propagar_participante(int $tid, int $autorUid): void
```

Inserta al autor en todos los seguimientos del TID cuyo propietario sea otro
personaje. El post que dispara esta funcion ya queda despues del inicio de
ronda y contara como respuesta.

### 6.4 Agregar tema manualmente

```php
op_bitacora_agregar_tema(int $ownerUid, int $tid, string $estadoInicial): array
```

Validar existencia, rol, visibilidad y permisos antes de insertar. Despues:

1. obtener el ultimo PID visible del propietario en el tema;
2. usarlo como `ronda_inicio_pid` si existe;
3. si no existe, exigir `me_toca` o `no_me_toca` como estado inicial;
4. crear el seguimiento;
5. sembrar participantes;
6. devolver el ID creado.

Un duplicado devuelve `ya_seguido` sin alterar la ronda existente.

### 6.5 Dejar de seguir

```php
op_bitacora_dejar_seguir(int $ownerUid, int $seguimientoId): array
```

Cargar por `id + personaje_uid`, iniciar transaccion, eliminar participantes y
eliminar seguimiento. Si el personaje publica despues, el hook lo crea otra
vez.

### 6.6 Overrides

```php
op_bitacora_marcar_me_toca(int $ownerUid, int $seguimientoId): array
op_bitacora_marcar_no_me_toca(int $ownerUid, int $seguimientoId): array
```

`me_toca` cambia solo el override y timestamp.

`no_me_toca` obtiene el mayor PID visible actual del tema, lo guarda como
`ronda_inicio_pid` y cambia el override. Si no existe ningun post visible,
rechaza la accion.

Los overrides no se aplican a temas cerrados, ocultos o fuera de rol.

### 6.7 Administrar participantes

```php
op_bitacora_agregar_participante(
    int $ownerUid,
    int $seguimientoId,
    int $participanteUid
): array

op_bitacora_retirar_participante(
    int $ownerUid,
    int $seguimientoId,
    int $participanteUid
): array
```

Antes de escribir:

- confirmar propiedad del seguimiento;
- confirmar que el tema sigue siendo administrable;
- confirmar ficha del participante;
- impedir usar al propietario como participante.

Agregar usa clave unica y origen `manual`. Retirar elimina solo la fila del
seguimiento indicado.

### 6.8 Configurar narrador

```php
op_bitacora_establecer_narrador(int $ownerUid, int $seguimientoId, int $narradorUid): array
op_bitacora_quitar_narrador(int $ownerUid, int $seguimientoId): array
```

Seleccionar valida propiedad, tema administrable y ficha, incorpora al
narrador como participante y guarda su UID. Quitar guarda `0` y recupera la
regla normal. Si se retira al narrador desde participantes, ambas operaciones
se realizan en la misma transaccion.

### 6.9 Transacciones y concurrencia

Las tablas nuevas son InnoDB. Usar transacciones en operaciones de varias
escrituras, especialmente alta manual, retirada y siembra inicial.

Las claves unicas resuelven carreras entre dos requests. Un error duplicado no
debe convertirse en error de usuario si el estado final ya es correcto.

## 7. Tarea 4: hooks de publicacion y aprobacion

**Archivo:** `inc/plugins/op_bitacora.php`

Implementar un unico callback para ambos hooks:

```php
function op_bitacora_hook_post(&$handler)
{
    $pid = (int)($handler->return_values['pid'] ?? 0);
    if ($pid > 0) {
        op_bitacora_procesar_post($pid);
    }
}
```

El servicio relee visibilidad, foro y ficha. Por ello, el hook puede salir sin
duplicar validaciones y funciona igual para thread y reply.

Agregar tambien:

```php
function op_bitacora_hook_approve_posts($pids)
function op_bitacora_hook_approve_threads($tids)
```

`class_moderation_approve_posts` se ejecuta despues de que MyBB marque los
posts como visibles. El callback normaliza los IDs, los ordena por PID y llama
`op_bitacora_procesar_post()` para cada uno.

`class_moderation_approve_threads` recibe TID. El callback consulta todos sus
posts visibles, ordenados por PID ascendente, y los procesa. Asi quedan
incorporados tanto el autor del primer post como las respuestas que ya
existieran dentro del thread moderado.

Los callbacks deben aceptar arrays vacios, IDs repetidos y threads fuera de rol
sin producir errores. La comparacion de PID del motor impide que una aprobacion
tardia rebobine una ronda posterior.

### Verificacion integrada

1. publicar fuera de rol: cero filas nuevas;
2. crear tema de rol: seguimiento con ronda iniciada y cero participantes;
3. responder con B: B obtiene tracker y A incorpora a B;
4. responder con C: todos los trackers afectados incorporan a C;
5. dejar de seguir con A y volver a publicar: se crea de nuevo;
6. publicar un post moderado: no cambia el tracker mientras sea invisible;
7. aprobar ese post: crea o actualiza el tracker y la ronda;
8. aprobar un thread con varias respuestas: procesa sus posts en orden sin
   duplicar participantes.

## 8. Tarea 5: lectura en bloque y motor de vista

**Archivo:** `inc/plugins/op_bitacora/functions.php`

### 8.1 Consulta base

```php
op_bitacora_listar(int $ownerUid): array
```

Primera consulta:

- seguimiento;
- thread visible;
- forum y `parentlist`;
- ultimo autor y fecha;
- nombre del foro.

Conservar temporalmente filas fuera de rol o sin permiso solo para marcarlas
como ocultas; nunca enviarlas a las plantillas ni a los conteos.

Agrupar FID y usar `forum_permissions($fid)` una vez por foro distinto.

### 8.2 Participantes en una segunda consulta

Con los IDs de seguimiento visibles, ejecutar una sola consulta que devuelva:

- UID, nombre, apodo y avatar del participante;
- origen automatico/manual;
- mayor PID visible del participante posterior a `ronda_inicio_pid`.

Ejemplo de forma SQL:

```sql
SELECT tp.seguimiento_id,
       tp.participante_uid,
       tp.origen,
       f.nombre,
       f.apodo,
       u.avatar,
       MAX(p.pid) AS respuesta_pid
FROM mybb_op_temas_participantes tp
JOIN mybb_op_temas_seguidos s ON s.id = tp.seguimiento_id
JOIN mybb_op_fichas f ON f.fid = tp.participante_uid
JOIN mybb_users u ON u.uid = tp.participante_uid
LEFT JOIN mybb_posts p
       ON p.tid = s.tid
      AND p.uid = tp.participante_uid
      AND p.visible = 1
      AND p.pid > s.ronda_inicio_pid
WHERE s.personaje_uid = {ownerUid}
  AND tp.seguimiento_id IN (...)
GROUP BY tp.seguimiento_id, tp.participante_uid;
```

Usar los nombres de tabla construidos con `$db->table_prefix`, no copiar el
prefijo del ejemplo.

### 8.3 Ensamblado

Por cada seguimiento, calcular:

- `participantes`;
- `respondieron`;
- `pendientes`;
- `esperados_total`;
- `respondieron_total`;
- `estado`;
- `estado_es_manual`;
- URL del tema;
- URL del personaje del ultimo autor;
- fecha formateable.

Ordenar cada grupo por `threads.lastpost DESC` y devolver:

```php
array(
    'debes_responder' => array(),
    'esperando' => array(),
    'conteos' => array(
        'debes_responder' => 0,
        'esperando' => 0,
    ),
)
```

### 8.4 Normalizacion del override de espera

Si una fila tiene `override_estado = no_me_toca` pero todos respondieron, el
estado efectivo es `debes_responder`. No escribir durante una lectura. La
normalizacion a `auto` puede ocurrir en la siguiente mutacion del seguimiento.

## 9. Tarea 6: controlador `/op/bitacora.php`

### 9.1 Bootstrap

```php
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'bitacora.php');
require_once './../global.php';
require_once MYBB_ROOT . 'inc/plugins/op_bitacora/functions.php';
```

Antes de procesar acciones:

- en modo propio, exigir usuario autenticado y ficha para el UID activo;
- con `modo_vista=<FID>`, validar la ficha solicitada y activar solo lectura;
- exigir tablas instaladas;
- cargar lenguaje general de MyBB si se usan errores estandar.

El modo vista puede ser usado por invitados. Toda consulta conserva los
permisos MyBB del visitante y todo `POST` o typeahead de edicion se rechaza en
servidor, aunque se construya fuera de la interfaz.

### 9.2 Router

Separar acciones por metodo:

```text
GET  pagina
GET  modo_vista=<FID>
GET  buscar_modo_vista
GET  buscar_personajes
GET  fragmento_tracker
POST agregar_tema
POST dejar_seguir
POST me_toca
POST no_me_toca
POST agregar_participante
POST retirar_participante
```

Todo POST ejecuta `verify_post_check()` antes de llamar al motor.

### 9.3 Respuestas

Para una request tradicional:

1. ejecutar mutacion;
2. redirigir a `/op/bitacora.php?resultado={codigo}`;
3. traducir solo codigos incluidos en una lista blanca.

Para `HX-Request: true`:

1. ejecutar la misma mutacion;
2. reconstruir el tracker completo;
3. devolver `op_bitacora_contenido`;
4. usar un mensaje `aria-live` dentro del fragmento.

No aceptar texto de mensajes desde query string.

### 9.4 Typeahead

`buscar_personajes`:

- minimo tres caracteres, incluido un FID numerico;
- busca `op_fichas.fid` y `nombre`;
- excluye el propietario;
- limita a 12 resultados;
- devuelve botones construidos por `op_bitacora_render_resultado_busqueda()` con
  UID y etiqueta escapada;
- no agrega nada por GET; la eleccion rellena un campo oculto y el alta real
  sigue siendo POST.

Los typeaheads de configuracion usan `fetch()` con un debounce de 220 ms y un
`AbortController` por input. Cada cambio limpia inmediatamente la lista,
cancela tanto el temporizador como la consulta anterior y solo programa otra
peticion cuando el termino conserva al menos tres caracteres. Los resultados
se posicionan de forma absoluta y el CSS del tracker no depende de hojas de
estilo compartidas para construir el dropdown.

### 9.5 Render principal

Agregar breadcrumb `Bitácora de rol`, preparar variables y ejecutar:

```php
eval("\$page = \"".$templates->get('op_bitacora')."\";");
output_page($page);
```

El template completo incluye `$headerinclude`, `$header`, `$footer`, scripts
locales de HTMX/Alpine y el fragmento `$op_bitacora_contenido`.

## 10. Tarea 7: plantillas

### 10.1 `op_bitacora.html`

Contiene el documento MyBB y un unico `main`. No incluye CSS inline. Carga:

```html
<script defer src="/jscripts/vendor/htmx-2.0.10.min.js"></script>
<script defer src="/jscripts/vendor/alpinejs-3.17.3.min.js"></script>
```

### 10.2 `op_bitacora_contenido.html`

Debe ser reemplazable como una sola unidad por HTMX y contener:

- mensaje de resultado con `aria-live="polite"`;
- guia colapsable sobre estados, rondas narradas y ajustes manuales;
- cabecera y tres contadores;
- formulario de TID;
- pestanas con conteos;
- tres listas;
- estados vacios independientes.

El estado inicial de la pestana se conserva mediante un parametro o un campo
HTMX, no mediante datos globales del servidor.

### 10.3 Fragmentos repetidos desde PHP

`op/bitacora.php` implementa helpers de presentacion equivalentes al patron usado
en `op/aventuras_personaje.php`:

```php
op_bitacora_render_tarjeta(array $tema): string
op_bitacora_render_participante(array $participante, array $tema): string
op_bitacora_render_configuracion(array $tema): string
op_bitacora_render_resultado_busqueda(array $ficha): string
```

Estos helpers reciben datos ya autorizados y escapados en el punto de salida.
No consultan la base de datos ni contienen reglas para decidir turnos.

Cada formulario incluye `my_post_key` y `seguimiento_id`. Nunca incluye un UID
propietario editable. La configuracion se abre desde un boton con icono y
tooltip, y muestra `Ya respondieron`, `Pendientes`, typeahead y overrides.

Cada participante enlaza a:

```text
/op/personaje.php?uid={participante_uid}
```

Los avatares usan `loading="lazy"` y `decoding="async"`.

Los strings resultantes se agrupan en `$op_temas_lista_debes` y
`$op_temas_lista_esperando`, consumidas por `op_bitacora_contenido.html`. La
interfaz los presenta como `Tu turno` y `Al dia`; los temas cerrados se
renderizan dentro de Al dia con su etiqueta propia.

### 10.4 Header

`op_temas_header` contiene un enlace unico a `/op/bitacora.php` y dos cifras. Si
ambas son cero, muestra un mensaje positivo. Los cerrados se incluyen en la
cifra de Esperando.

## 11. Tarea 8: CSS y comportamiento visual

**Archivo:**
`templates/One_Piece_Gaiden_Templates/stylesheets/op_bitacora.css`

Reutilizar tokens y componentes de `opg-tokens.css` y `opg-components.css`.
Agregar solo clases `op-temas-*` especificas.

### 11.1 Estructura

- ancho contenido consistente con herramientas `/op/`;
- dos bandas activas claramente diferenciadas;
- cerrados dentro de Esperando con una banda de estado neutral;
- tarjetas con radio maximo de 8px;
- bordes negros y sombra offset OPG;
- controles compactos y escaneables;
- sin cards dentro de cards.

### 11.2 Responsive

- escritorio: metadatos, progreso y acciones en columnas estables;
- movil: una columna, acciones en una fila que pueda envolver;
- nombres largos con `overflow-wrap: anywhere`;
- botones con dimensiones estables;
- typeahead dentro del viewport;
- ningun texto debe solaparse con contadores o acciones.

### 11.3 Accesibilidad

- foco visible;
- pestanas con `role="tablist"`, `role="tab"` y `aria-selected`;
- paneles asociados con `aria-controls`;
- tooltips complementarios, nunca unica fuente del nombre de una accion;
- estados expresados con texto, no solo color;
- confirmacion de retirada accesible por teclado.

### 11.4 Alpine y HTMX

Alpine se limita a:

- pestana activa;
- apertura del panel de configuracion;
- confirmacion de `Dejar de seguir`;
- seleccion visual del typeahead.

HTMX se limita a enviar formularios y reemplazar `#op-temas-tracker`. Tras un
swap debe conservar o restaurar la pestana activa. Sin JavaScript, los mismos
formularios completan una navegacion normal.

## 12. Tarea 9: barra del header

Esta tarea se implementa despues de que la pagina y el motor sean estables.

### 12.1 Hook

```php
function op_bitacora_hook_header()
```

Flujo de salida temprana:

1. inicializar `$op_temas_header = ''`;
2. salir para invitado;
3. salir si faltan tablas;
4. salir si el UID activo no tiene ficha;
5. obtener resumen del personaje;
6. renderizar `op_bitacora_header`.

El resumen reutiliza `op_bitacora_listar()` en modo ligero o una funcion
`op_bitacora_resumen()` que comparta la misma consulta y resolver. No debe existir
una segunda implementacion de las reglas de estado.

### 12.2 Rendimiento

Antes de activar globalmente:

- registrar temporalmente cantidad y tiempo de consultas en una cuenta de
  prueba;
- ejecutar `EXPLAIN` sobre participantes/posts;
- confirmar que los permisos se calculan una vez por FID distinto;
- comparar conteos del header con los de `/op/bitacora.php`.

No introducir cache en la primera entrega. Si la medicion lo exige, disenar la
invalidacion en un cambio separado.

## 13. Tarea 10: pruebas

### 13.1 Comprobaciones estaticas

```bash
php -l inc/plugins/op_bitacora.php
php -l inc/plugins/op_bitacora/functions.php
php -l op/bitacora.php
git diff --check
```

Buscar accidentalmente:

```bash
rg -n "mybb_op_temas|personaje_uid.*input|uid.*INPUT_INT" \
  inc/plugins/op_bitacora.php \
  inc/plugins/op_bitacora/functions.php \
  op/bitacora.php
```

Revisar manualmente cualquier prefijo hardcodeado y cualquier UID recibido del
cliente que pudiera usarse como propietario.

### 13.2 Matriz funcional minima

Usar tres personajes con ficha: A, B y C.

| Paso | Accion | Resultado esperado |
|---|---|---|
| 1 | A crea tema de rol | A: esperando, 0 participantes |
| 2 | B responde | A: debes responder; B: esperando |
| 3 | A responde | A: esperando a B; B incorpora a A |
| 4 | C responde | A espera a B, C ya respondio; C obtiene tracker |
| 5 | B responde | A: debes responder, 2/2 |
| 6 | A marca `No me toca` | Nuevo punto de espera, 0/2 |
| 7 | A marca `Me toca` | Debes responder manual |
| 8 | A publica | Override consumido, nueva ronda |
| 9 | A retira a C | C deja de condicionar a A |
| 10 | C vuelve a publicar | C se reincorpora automaticamente |
| 11 | Cierre del tema | Aparece en Esperando con la etiqueta Tema cerrado |
| 12 | A deja de seguir | Desaparece del tracker de A |

### 13.3 Casos de seguridad

- POST sin `post_key`;
- seguimiento de otro personaje;
- participante igual al propietario;
- TID inexistente;
- foro privado sin permiso;
- thread no visible;
- tema fuera de `parentlist LIKE '10,%'`;
- typeahead con comodines SQL y HTML;
- cambio de UID en campos ocultos manipulados.

### 13.4 Moderacion

- cerrar y reabrir;
- mover fuera y devolver a rol;
- eliminar/desmoderar el post que completaba una ronda;
- restaurarlo y confirmar recuento;
- borrar el tema y comprobar que no se filtra informacion.

### 13.5 UI

Probar como minimo:

- 1440 x 900;
- 1024 x 768;
- 390 x 844;
- nombres y titulos largos;
- listas vacias;
- 20 o mas temas;
- JavaScript activado y desactivado;
- navegacion completa con teclado.

## 14. Tarea 11: despliegue

### 14.1 Antes de subir

1. revisar diff completo;
2. ejecutar comprobaciones PHP y `git diff --check`;
3. respaldar tablas y plantilla `header` de produccion;
4. confirmar que Alpine y HTMX locales responden sin 404;
5. confirmar el TID real del tema OPG (`3`).

### 14.2 Orden de despliegue

1. subir plugin y `functions.php`;
2. subir pagina, plantillas y CSS;
3. subir `header.html` con el placeholder;
4. instalar plugin desde Admin CP;
5. activar plugin;
6. esperar el ciclo de template/stylesheet sync;
7. probar con cuentas de prueba;
8. verificar logs PHP y consultas lentas;
9. habilitar el header solo despues de validar la pagina.

Para respetar la prioridad del header, su hook puede quedar temporalmente
deshabilitado mediante una constante durante las primeras pruebas:

```php
define('OP_TEMAS_HEADER_ENABLED', false);
```

Cambiarla a `true` en la Fase 4, sin alterar datos.

### 14.3 Rollback

Rollback no destructivo:

1. poner `OP_TEMAS_HEADER_ENABLED` en `false`;
2. desactivar el plugin;
3. retirar el enlace o acceso a `/op/bitacora.php` si se hubiese publicado;
4. conservar tablas para diagnostico y reactivacion.

No usar `uninstall()` como rollback ordinario porque elimina seguimientos.

## 15. Orden recomendado de commits

1. `Add role topic tracker schema and state engine`
2. `Track role rounds from MyBB post hooks`
3. `Add server-rendered role topic tracker page`
4. `Add participant and turn controls`
5. `Enhance topic tracker with HTMX and Alpine`
6. `Add active role topic counts to header`

Cada commit debe pasar `php -l` y dejar el tracker en un estado coherente. No
mezclar el motor de rondas con cambios visuales no relacionados.

## 16. Definicion de terminado por fase

### Fase 1: persistencia y motor

- plugin instalable e idempotente;
- tablas e indices correctos;
- resolver de estados cubierto;
- altas por post y thread funcionando;
- rondas calculadas sin HTML.

### Fase 2: pagina funcional

- alta manual y retirada;
- overrides;
- participantes automaticos y manuales;
- tres estados visibles;
- permisos y PRG completos;
- usable sin JavaScript.

### Fase 3: experiencia interactiva

- HTMX sin duplicar reglas;
- Alpine limitado a interfaz;
- typeahead server-side;
- CSS responsive acorde a `style.md`;
- verificacion visual sin solapamientos.

### Fase 4: header

- conteos del personaje activo;
- cero consultas N+1;
- mensaje positivo en cero;
- coste medido y aceptable;
- coincidencia con los conteos de la pagina.
