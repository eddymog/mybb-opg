# Historial público de objetos: plan de diseño técnico

> Rol: Software Developer (arquitectura). Depende de: [112_Requirements_HistorialObjetos.md](112_Requirements_HistorialObjetos.md) (PM), [112_Reglas_HistorialObjetos.md](112_Reglas_HistorialObjetos.md) (Game Designer) y [214_UI_HistorialObjetos.md](214_UI_HistorialObjetos.md) (UI Designer). Revisado por el QA en [214_QA_RevisionPlan_HistorialObjetos.md](214_QA_RevisionPlan_HistorialObjetos.md).
> Modelo: [212_DesignPlan_HistorialTecnicas.md](212_DesignPlan_HistorialTecnicas.md), `op/historial_tecnicas.php`, `inc/plugins/lib/tecnicas_cambios.php`, `tests/tecnicas/cambios.php`.
> Estado: **versión 2**, corregida tras la revisión del QA (veredicto LISTO CON CAMBIOS). Las correcciones están en la sección 20; los hallazgos no aceptados o aceptados en parte, en la sección 19. Las decisiones abiertas no se resuelven aquí: se diseña con la recomendación de cada documento y cada punto que depende de una decisión lleva la marca **DECISIÓN D-x / U-x / V-x / N-x** (las N son nuevas de esta versión), con lo que cambiaría con cada opción (sección 17).

Abreviaturas: "112-R" es el documento de requisitos, "112-GD" el de reglas, "214-UI" la especificación visual, "QA-n" un hallazgo del informe del QA. "Req. n" es el requisito número n de 112-R.

---

## 0. Puntos de partida que condicionan el diseño

Hechos comprobados en el código y en el volcado `rovddqmy_op.sql` (que puede estar desfasado respecto a producción; por eso la calibración de 13.4 es **bloqueante**):

| # | Hecho | Consecuencia en el diseño |
|---|---|---|
| H-1 | `mybb_op_objetos` es **MyISAM** en el volcado; `mybb_op_audit_consola_mod` e `mybb_op_inventario` son InnoDB. | Sin transacción que una objeto, inventario y registro. Orden de escrituras con registro en dos fases, bloqueo con nombre y conciliación (3.9, 3.10). |
| H-2 | `mybb_op_audit_consola_mod.log` es `TEXT` (65.535 bytes) en `utf8` de 3 bytes; `razon` y `staff` son `TEXT` (`staff` guarda el UID). Solo índice primario. | Presupuesto de tamaño (3.7), escape de caracteres de 4 bytes (3.8). La consulta pública nunca selecciona `razon` ni `staff` (9.4). |
| H-3 | `mybb_op_objetos` solo tiene índice en `id`; `objeto_id` no es único ni está indexado; su colación es `utf8_unicode_ci` (no distingue mayúsculas, acentos ni espacios finales). | El registro guarda el `id` interno y se lee por clave primaria. En PHP, toda clave de id se normaliza (3.11). Índice opcional (6.2). |
| H-4 | `objetos_modificar.php` hace `UPDATE ... WHERE objeto_id=...`: con filas duplicadas cambia todas. Un renombre hacia un id existente crea un duplicado. | Un registro por fila afectada y rechazo del renombre hacia un id existente o con inventario (4.1). |
| H-5 | `objetos_modificar.php` recorta todo y manda cadenas vacías a columnas enteras; lo que hace MySQL depende del `sql_mode` (no estricto: `0`; estricto: error). | El después se **relee de la base**; la comparación ignora espacios de los extremos y `\r\n` frente a `\n` (3.5). El modo real se mide antes de empezar (13.4). |
| H-6 | `op/barco_unico.php` crea y edita filas `custom=0` de Transportes sin log; `insert_query` y `update_query` de MyBB no escapan. | Tercera ruta registrada; la normalización usa los valores sin escapar (4.2). |
| H-7 | Retirada antigua de objetos: `Remover técnica ID X (nombre). Nuevo ID de la técnica: BORRX. Tipo: borrada.`; técnicas termina en `estilo: borrada.`; akumas y virtudes sin sufijo. | Se reconoce por el sufijo; solo staff (5.4). |
| H-8 | `scripts/deploy_changed.sh` nunca borra archivos remotos. | Con D-13 A, `objetos_crear.php` se sustituye por una redirección, no se borra (4.3). |
| H-9 | El acceso al mercado negro se decide en línea en `op/mercado_negro.php`: personaje no muerto (`muerto != '1'`, línea 1397), ficha aprobada (`does_ficha_exist`: existe y `aprobada_por != 'sin_aprobar'`, línea 1732), y rango del inframundo distinto de vacío y de `Sin rango`, o Mercader nivel 2 o más con sub `Contrabandista` de nivel 1 o más (líneas 1403 a 1432). | Función pura con **las tres condiciones** y su prueba (7.1). No se refactoriza `mercado_negro.php`. |
| H-10 | `op_objetos.html` pinta cada objeto en `div.item-outer` con `id` igual al `objeto_id`, por JavaScript, sin contenedor para Transportes. | Ancla con desplazamiento tras el pintado; sin enlace al catálogo para Transportes (11.1). |
| H-11 | `op/objetos.php` envía al navegador la fila completa de los 1.204 objetos de catálogo (salvo `CFF010%`), invisibles y columnas reservadas incluidos. | Fuera de esta funcionalidad (U-5), pero bloquea la publicación de la página (11.5). |
| H-12 | `mybb_op_inventario` tiene clave única `(objeto_id, uid)` con colación sin mayúsculas, y ningún índice que empiece por `uid`. | El renombre se rechaza si ya hay inventario con el id nuevo (4.1); la consulta de inventario por `uid` recorre la tabla (6.2). |
| H-13 | `$db->query($sql, 1)` de MyBB (`hide_errors`) devuelve `false` en lugar de cortar la petición; sin ese argumento, `inc/class_error.php` puede mostrar la consulta completa según `errortypemedium`. | Las consultas de la página pública van con `hide_errors` (9.7, QA-15). |
| H-14 | `crafteo.php` interpreta `crafteo_usuarios` con `FIND_IN_SET(uid, REPLACE(crafteo_usuarios, ' ', ''))` y trata vacío o `NULL` como receta abierta. | La librería replica esa lectura (7.4, QA-30). |

---

## 1. Arquitectura

Misma forma que el historial de técnicas, con dos diferencias: el **registro** es tan importante como la lectura, y la visibilidad depende de quién mira. Para que lo delicado se pueda probar sin cargar `global.php` (QA-05), toda la lógica de guardado y de construcción de la página vive en librerías; las páginas solo comprueban permisos y CSRF, leen la entrada, llaman y responden.

```text
Herramientas del staff                           Página pública
objetos_modificar.php ─┐                         op/historial_objetos.php (permisos, entrada, plantilla)
barco_unico.php ───────┼─> objetos_cambios.php        │
objetos_crear.php ─────┘   guardar_*, retirar,        ├─> objetos_historial.php
log_consola_mod.php ─────> registro, conciliar,       │     hist_consultar($db, ...)  Q1 a Q5
                           parser, visibilidad        │     hist_construir(filas, visor, params, ahora)
                                 │                    │       -> todas las variables {$ho_*}
                                 v                    └─> objetos_historial_vista.php (HTML)
                     mybb_op_audit_consola_mod (marca + JSON)
```

| Capa | Archivo | Qué contiene | Pura |
|---|---|---|---|
| Dominio | `inc/plugins/lib/objetos_cambios.php` | Catálogo de campos (etiqueta, grupo, tipo, visibilidad), normalización, clave de id, instantánea, construcción y serialización del payload, parser de los formatos nuevo y antiguos, mapa de alias, base de una copia, reglas de visibilidad por visor, filtrado de campos, líneas derivadas, acceso al mercado negro, texto legible para el log del staff. **Además, con `$db`**: `opg_objetos_guardar_desde_formulario()`, `opg_objetos_barco_guardar_objeto()`, `opg_objetos_retirar()`, `opg_objetos_registro_abrir()`, `opg_objetos_registro_cerrar()`, `opg_objetos_registro_conciliar()`, `opg_objetos_bloquear()` y `opg_objetos_desbloquear()`. | Sí, salvo las funciones con `$db`, probadas con MySQL real. |
| Lectura | `inc/plugins/lib/objetos_historial.php` | `opg_objetos_hist_consultar($db, $params, $visor_base)` ejecuta Q1 a Q5 (el mismo SQL que prueba T-DBQ) y devuelve las filas. `opg_objetos_hist_construir($filas, $visor, $params, $politica, $ahora)` hace los pasos 5 a 15 de 6.3 y devuelve **todas** las variables `{$ho_*}` de la plantilla. | `consultar` usa `$db`; `construir` es pura. |
| Presentación | `inc/plugins/lib/objetos_historial_vista.php` | HTML de entrada, ficha, chips, filtros activos, paginación, estados de 214-UI; formateo de valores. | Sí. |
| Páginas | `op/historial_objetos.php`, `op/staff/objetos_modificar.php`, `op/barco_unico.php`, `op/staff/objetos_crear.php`, `op/staff/log_consola_mod.php` | Permisos, CSRF, lectura de la entrada, una llamada a la librería, respuesta. | No |
| Plantilla | `op_historial_objetos.html` | Marco, filtros, estilos, script progresivo. Recibe HTML ya escapado. | n/a |

Principios:

1. **El navegador solo recibe lo que el visor puede ver.** El filtrado se hace antes de construir el HTML; no se emite el payload, ni `data-*` con valores, ni `id` de entradas ocultas.
2. **Una entrada se pinta con los datos de su registro**, nunca con el estado actual del objeto, salvo la ficha compacta y "Ver en el catálogo", que usan la fila actual **solo si es visible para quien mira** (QA-01).
3. **Una sola fuente de verdad para las reglas.** Las mismas funciones deciden la entrada, sus campos, la búsqueda, los recuentos, las opciones de filtros y los autores.
4. **Las decisiones del usuario son configuración**: `opg_objetos_hist_politica()` (7).
5. **Denegación por defecto**: un campo o una versión que la librería no conoce es "solo staff" o se ignora (QA-10).

---

## 2. Archivos nuevos y modificados

| Archivo | Nuevo o cambio | Qué se hace | Issue (14) | Despliegue |
|---|---|---|---|---|
| `inc/plugins/lib/objetos_cambios.php` | Nuevo | Dominio y guardado (1). | I-1, I-2, I-3, I-6 | Código |
| `inc/plugins/lib/objetos_historial.php` | Nuevo | Consultas y construcción de la página (1). | I-7 | Código |
| `inc/plugins/lib/objetos_historial_vista.php` | Nuevo | Presentación (1). | I-7 | Código |
| `op/historial_objetos.php` | Nuevo | Página pública fina. | I-7 | Código, tras la plantilla |
| `templates/One_Piece_Gaiden_Templates/op_historial_objetos.html` | Nuevo | Plantilla (214-UI). | I-7 | **Plantilla: paso acoplado** |
| `op/staff/objetos_modificar.php` | Cambio | El bloque `POST` pasa a llamar a `opg_objetos_guardar_desde_formulario()`. Aviso de guardado sin confirmar en el `GET`. Con D-13 A, acción `retirar`. | I-2 (I-4 si D-13 A) | Código |
| `templates/One_Piece_Gaiden_Templates/staff_objetos_modificar.html` | Cambio | Aviso "Este objeto tiene un guardado sin confirmar" con botón `POST` de conciliación; con D-13 A, botón "Retirar objeto". | I-2, I-4 | **Plantilla: paso acoplado** |
| `op/barco_unico.php` | Cambio | `crear` y `editar` llaman a `opg_objetos_barco_guardar_objeto()` para la fila de `mybb_op_objetos`. | I-3 | Código |
| `op/staff/objetos_crear.php` | Cambio según D-13 | A: redirección. B: reescritura segura con la librería. C: solo registro. | I-4 | Código |
| `templates/One_Piece_Gaiden_Templates/staff_objetos_crear.html` | Solo si D-13 B | `my_post_key`. | I-4 | **Plantilla: paso acoplado** |
| `op/staff/log_consola_mod.php` | Cambio | Texto legible del JSON de objetos; marca "Cambio sin confirmar" y botón `POST` "Conciliar" (CSRF) en los pendientes. | I-5 | Código |
| `templates/One_Piece_Gaiden_Templates/staff_log_consola.html` | Cambio | Formulario del botón "Conciliar" con `my_post_key` (si el botón no puede generarse desde el PHP dentro de `$logs`). | I-5 | **Plantilla: paso acoplado** (solo si hace falta) |
| `templates/One_Piece_Gaiden_Templates/op_objetos.html` | Cambio | `.ht-cta`, ancla y `.ho-objeto-destacado` (214-UI 8.1 y 8.2), con `try/catch` en `decodeURIComponent` (QA-25). | I-8 | **Plantilla: paso acoplado** |
| `templates/One_Piece_Gaiden_Templates/op_historial_tecnicas.html` | Cambio mínimo | Enlace "Historial de objetos". | I-8 | **Plantilla: paso acoplado** |
| `docs/214_historial_objetos_indices.sql` | Nuevo | Migración idempotente de índices (6.2). | I-9 | **SQL: paso acoplado** |
| `tests/objetos/bootstrap_db.php` | Nuevo | Entorno MySQL propio (12.4, QA-07). | I-1 | Pruebas |
| `tests/objetos/cambios.php`, `antiguos.php`, `visibilidad.php`, `renombres.php`, `vista.php`, `filtros.php`, `construir.php`, `log_staff.php` | Nuevos | Pruebas puras (12). | I-1 a I-7 | Pruebas |
| `tests/objetos/registro_db.php`, `concurrencia_db.php`, `consulta_db.php` | Nuevos | Pruebas con MySQL real (12). | I-1 a I-3, I-7, I-9 | Pruebas `--db` |

No se tocan `global.php`, el núcleo de MyBB, `inc/config.php`, `op/objetos.php` (salvo lo que decida U-5 en su propia issue) ni las rutas de jugadores.

Puerta de verde: `necesita_db()` de `tests/run_all.sh` marca como prueba con base de datos todo archivo que contenga `bootstrap_db`, `mysqli_connect`, `DbShim` o `Sin MySQL de pruebas`. Las pruebas puras que necesiten un doble de `$db` (por ejemplo, para contar consultas) lo llaman `ObjetosDbFalsa`, nunca `DbShim`, para no quedar omitidas sin `--db`.

---

## 3. Modelo de registro futuro

### 3.1 Dónde y con qué marca

**DECISIÓN D-11** A (adoptada): en `mybb_op_audit_consola_mod`.

- `log` = `'[CAMBIO_OBJETO] '` (16 caracteres exactos, constante `OPG_OBJETOS_CAMBIO_MARCA`) + JSON.
- `staff` = UID de la sesión, `username` = nombre de la sesión escapado, `razon` = `'Cambio de objeto'`.
- `tiempo` = valor por defecto de la tabla. La calibración comprueba con `SHOW CREATE TABLE` que no tiene `ON UPDATE CURRENT_TIMESTAMP`; si lo tuviera, el paso de confirmación cambiaría la hora del registro, y el JSON guardaría entonces su propia hora (`"t"`) en el `abrir`.

Con D-11 B (tabla propia): mismo payload en una columna, consultas indexadas sin `LIKE`, migración `CREATE TABLE IF NOT EXISTS` bloqueante antes de I-2.

### 3.2 Esquema congelado del JSON (versión 1)

**El esquema v1 queda congelado en esta versión del plan** (QA-01): lo que se escriba no se puede corregir después. Guarda una **instantánea completa del estado después del cambio**, de modo que cada entrada se pinte solo con su registro.

Orden de claves fijo (lo construye la librería y lo comprueba T-REG-11). Las seis primeras forman la **cabecera**, que se puede leer con una expresión regular sobre los primeros 1.200 caracteres sin decodificar el JSON entero (Q2 y búsqueda de pendientes):

```json
{
  "v": 1,
  "accion": "actualizacion",
  "estado": "confirmado",
  "id": 5123,
  "objeto_id_antes": "KAT001",
  "objeto_id": "KAT001",
  "origen": "objetos_modificar",
  "autor": "Gretta",
  "vis_antes": {"custom":0,"invisible":0,"exclusivo":0,"tier":2,"comerciable":0,"negro":0,"oficio_vacio":0,"crafteo_vacio":1,"categoria":"Armas"},
  "despues": {"objeto_id":"KAT001","nombre":"Katana Kiku","categoria":"Armas","subcategoria":"Armas de Filo","tier":"2","dano":"14+[FUEx1]", "...": "los 32 campos", "custom":"0"},
  "abreviado_despues": ["descripcion"],
  "cambios": [
    {"campo":"dano","antes":"12+[FUEx1]"},
    {"campo":"berries","antes":"5000"},
    {"campo":"descripcion","antes":"...","abreviado":true}
  ]
}
```

| Clave | Tipo | Valores | Notas |
|---|---|---|---|
| `v` | entero | `1` | El parser exige el entero `1` (no la cadena `"1"`). |
| `accion` | cadena | `alta`, `actualizacion`, `renombrado`, `retirada`, `publicado` | Acción técnica (3.6). La visibilidad no depende de esta clave. |
| `estado` | cadena | `pendiente`, `confirmado`, `sin_cambios`, `fallido` | 3.9 y 3.10. La página pública solo usa `confirmado`. |
| `id` | entero | `mybb_op_objetos.id`; `0` en un alta pendiente antes del `INSERT` | Nunca se muestra. |
| `objeto_id_antes` | cadena | id antes; `""` en un alta | |
| `objeto_id` | cadena | id después | |
| `origen` | cadena | `objetos_modificar`, `barco_unico`, `objetos_crear`, `conciliacion` | Lista fija. |
| `autor` | cadena | `username` de la sesión | La página muestra el `username` de la **columna**; si no coincide con este (sin distinguir mayúsculas), el registro se trata como sospechoso: solo staff, con el motivo "registro con autor inconsistente" (QA-27). El UID no va en el JSON. |
| `vis_antes` | objeto o `null` | instantánea compacta del antes (3.4) | `null` en un alta. |
| `despues` | objeto | **los 32 campos registrados más `custom`**, como cadenas, con el abreviado de 3.7 | Estado completo después. La instantánea del después se deriva de aquí (`opg_objetos_instantanea($despues)`). |
| `abreviado_despues` | lista | nombres de campo abreviados en `despues` | |
| `cambios` | lista | `{campo, antes[, abreviado]}` | Solo los campos que cambiaron; el valor nuevo de cada uno es `despues[campo]`. En un alta, lista vacía (los valores iniciales son `despues`). |

Solo en el estado `pendiente` (3.9) hay además dos claves al final, que se eliminan al confirmar:

| Clave | Contenido |
|---|---|
| `antes_completo` | Los 32 campos más `custom` de la fila antes del cambio (`null` en un alta), con el abreviado de 3.7. Sirve para conciliar (QA-03). |
| `esperado` | Objeto `campo -> valor` con los valores normalizados del formulario que se esperan cambiar. |

Reconstrucción del antes en un registro confirmado: `antes = despues` sobrescrito con `cambios[].antes`. Se usa para la cabecera de "Deja de estar en el catálogo" (7.3).

Serialización: `json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)` más el escape de 3.8. Todos los valores de campos son cadenas.

### 3.3 Campos que se registran

Los 32 campos de req. 39 en los **nueve grupos** de 112-GD 3.1. El orden de la tabla es el orden de las filas en la entrada.

| Grupo (`campo=`) | Columna | Etiqueta | Tipo | Clase de visibilidad (7.4) |
|---|---|---|---|---|
| Identidad (`identidad`) | `objeto_id` | ID | corto | pública (en renombres, banda de 214-UI 3.6) |
| | `nombre` | Nombre | corto | pública |
| | `categoria` | Categoría | corto | pública |
| | `subcategoria` | Subcategoría | corto | pública |
| | `tier` | Tier | entero | pública |
| Combate y uso (`combate`) | `dano` | Daño | corto | pública |
| | `bloqueo` | Bloqueo | corto | pública |
| | `efecto` | Efecto | largo | pública |
| | `alcance` | Alcance | corto | pública |
| | `escalado` | Escalado | largo | pública |
| | `requisitos` | Requisitos de uso | largo | pública |
| Precio y comercio (`precio`) | `berries` | Valor en berries | entero, con porcentaje | pública |
| | `comerciable` | Se puede intercambiar | sí/no invertido (`0` = Sí) | pública |
| Fabricación (`fabricacion`) | `oficio` | Oficio | corto | pública |
| | `nivel` | Nivel de oficio | entero | pública |
| | `berriesCrafteo` | Coste de fabricación | entero, con porcentaje | pública |
| | `tiempo_creacion` | Tiempo de fabricación | segundos en h y min | pública |
| | `desbloquear` | Puntos para desbloquear la receta | entero | pública |
| | `crafteo_usuarios` | Quién puede fabricarlo | lista de UID | **privada** |
| Inventario (`inventario`) | `espacios` | Espacios | entero | pública |
| | `cantidadMaxima` | Cantidad máxima | entero | pública |
| Presentación (`presentacion`) | `descripcion` | Descripción | largo | pública |
| | `imagen` | Imagen | imagen | pública, solo "Imagen actualizada" |
| | `imagen_avatar` | Imagen de avatar | imagen | pública, solo "Imagen actualizada" |
| | `imagen_id` | Color de rareza | enumerado | pública |
| | `editable` | Personalizable | sí/no | pública |
| Disponibilidad (`disponibilidad`) | `exclusivo` | Se vende en la tienda | sí/no invertido con tier | pública |
| | `invisible` | Aparece en el catálogo | transición | **solo como transición** |
| Mercado negro (`mercado_negro`) | `negro` | En el mercado negro | sí/no | **mercado negro** |
| | `negro_berries` | Precio en el mercado negro | entero, `9999999` = "Sin precio fijado" | **mercado negro** |
| Reservado (`reservado`) | `fusion_tipo` | Tipo de fusión | corto | **solo staff** |
| | `engastes` | Engastes | entero | **solo staff** |

No se registran como cambio: `id` (clave propia), `custom` (va en `despues` y en la instantánea, pero no se compara: ninguna herramienta lo edita), `desbloqueado` y `fecha_creacion`.

Las estadísticas de `mybb_op_barcos` no son columnas de `mybb_op_objetos` y quedan fuera (16).

### 3.4 Instantánea compacta (`vis_antes`)

`custom`, `invisible`, `exclusivo`, `tier`, `comerciable`, `negro` (enteros), `oficio_vacio`, `crafteo_vacio` (`1` si vacío o `NULL` tras quitar espacios, H-14) y `categoria` (máximo 60 caracteres). La del después se deriva de `despues` con la misma función. La "creación única de personaje" se deduce del id.

### 3.5 Cálculo del antes y del después

1. **Antes**: con el bloqueo tomado (3.10), `SELECT <32 columnas>, id, custom FROM mybb_op_objetos WHERE objeto_id = '<id escapado>'` (en un renombre, el id viejo). Sin fila: alta. Varias filas (H-4): una entrada por `id`; para los antiguos, ver 5.3.
2. **Esperado**: valores del formulario normalizados como los guardaría MySQL en el modo medido (enteros con `(string)(int)`, vacío en entero como `'0'`, textos con `trim` y `\r\n` a `\n`). Si no hay diferencias con el antes: **no se escribe nada** (req. 38, E-14).
3. **Escrituras** (3.9).
4. **Después**: relectura por `id` (en un alta, `$db->insert_id()`).
5. **Diferencia real**: comparación normalizada campo a campo; un cambio solo de espacios en los extremos no cuenta.

### 3.6 Acción guardada

Por prioridad: sin fila antes, `alta`; id después con prefijo `BORR` y `categoria = borrada` sin serlo antes, `retirada`; `objeto_id` cambió **con otra clave normalizada** (3.11), `renombrado`; `invisible` de 1 a 0, `publicado`; resto (incluido un cambio de id solo de mayúsculas), `actualizacion`.

### 3.7 Tamaño máximo y abreviado

Límite del log completo: **60.000 bytes** (`OPG_OBJETOS_CAMBIO_MAX_BYTES`). Con `despues` completo, un campo largo cambiado aparece dos veces (antes y después), así que la escalera es más agresiva y **incluye los campos cortos** (QA-23):

| Escalón | Largos (`descripcion`, `efecto`, `escalado`, `requisitos`, `imagen`, `imagen_avatar`) | Cortos (resto de texto) | `nombre` |
|---|---|---|---|
| 1 | 3.000 | 500 | 255 |
| 2 | 1.500 | 255 | 255 |
| 3 | 500 | 100 | 255 |
| 4 | 200 | 60 | 120 |

Se serializa en el escalón 1 y se baja mientras supere el límite. Cada valor cortado se marca (`abreviado` en `cambios`, nombre en `abreviado_despues`). Si el escalón 4 no basta (solo posible en un pendiente con muchos emoji, cada uno de 12 bytes tras el escape), se guarda una versión mínima con la cabecera, `vis_antes` y `cambios` sin valores; en un pendiente, también `antes_completo` reducido a la instantánea, y la conciliación lo trata como "no conciliable automáticamente" (solo staff). Nunca se escribe un JSON truncado.

### 3.8 Escape y serialización segura

- En la base: log y `username` con `$db->escape_string()`.
- Caracteres de 4 bytes: tras `json_encode`, cada carácter fuera del plano básico se sustituye por su par sustituto JSON (`😀`).
- UTF-8 inválido: reintento con `JSON_INVALID_UTF8_SUBSTITUTE` (PHP 7.2 o superior; la calibración mide la versión) o limpieza con `mb_convert_encoding`; si falla, versión mínima.
- El JSON nunca llega al navegador.

### 3.9 Orden de las escrituras

Todo ocurre dentro del bloqueo de 3.10. Corrige el orden de la versión 1 (QA-02): **el inventario se mueve antes que el objeto**, como hoy.

| Paso | Qué | Si la petición muere aquí |
|---|---|---|
| 0 | `opg_objetos_bloquear()` (3.10). Conciliar los pendientes de este objeto (3.10). | Nada escrito. |
| 1 | Lectura del antes (3.5.1). | Nada escrito. |
| 2 | Validaciones (4.1): renombre hacia id existente o con inventario, retirada repetida. | Nada escrito. |
| 3 | Esperado; si no hay cambios, desbloquear y fin. | Nada escrito. |
| 4 | `INSERT` del registro `pendiente` con `antes_completo`, `esperado` e `id` (0 en un alta). Se guarda su `audit_id`. | Nada en el catálogo. |
| 5 | Solo renombre: `UPDATE mybb_op_inventario SET objeto_id = '<nuevo>' WHERE objeto_id = '<viejo>'`. | Inventario en el id nuevo, objeto con el viejo. **Reintentar completa el renombre**: el id viejo sigue existiendo, `es_rename` es verdadero y el `UPDATE` de inventario es idempotente. Antes de reintentar, la conciliación del paso 0 detecta el pendiente y lo cierra (3.10). |
| 6 | `UPDATE` o `INSERT` del objeto (sentencias de hoy). | Igual que 5, o pendiente sin cambio real. |
| 7 | Solo alta: `UPDATE` del registro pendiente con el `id` nuevo (`insert_id`) (QA-03). | Pendiente de alta con `id = 0`: la conciliación lo busca por `objeto_id` (3.10). |
| 8 | Relectura del después por `id`. | Pendiente con cambio real: lo cierra la conciliación. |
| 9 | `UPDATE` del registro: payload real, `estado = confirmado` (o `sin_cambios`), sin `antes_completo` ni `esperado`. | Igual que 8. |
| 10 | `opg_objetos_desbloquear()`. | MySQL libera el bloqueo al cerrar la conexión (salvo conexiones persistentes, 13.1). |

Garantías: ningún cambio queda sin rastro (el registro existe antes de escribir); el público nunca ve un cambio no confirmado; los pendientes no se quedan pendientes para siempre (3.10); un corte en un renombre se repara reintentando.

### 3.10 Bloqueo y conciliación

**Bloqueo** (QA-04): `SELECT GET_LOCK('<nombre>', 5)`, con `<nombre>` = `'opg_obj_' . md5(clave)` (40 caracteres, dentro del límite de 64), y `clave` = `'i' . id` si la fila existe o `'o' . clave_normalizada(objeto_id)` en un alta. En un renombre se toman dos bloqueos: el del `id` y el del id nuevo normalizado (MySQL 5.7.5 o superior admite varios; se toman siempre en orden alfabético del nombre para evitar abrazos mortales). Resultado `1`: seguir; `0` (5 s agotados) o `NULL`: "Otro guardado de este objeto está en curso; inténtalo de nuevo." sin escribir nada. `RELEASE_LOCK` en el paso 10 y también en una función de cierre registrada con `register_shutdown_function` para los cortes de PHP. Mismo bloqueo en las tres rutas del staff y en la conciliación. Así, un doble clic hace que el segundo envío espere y no encuentre cambios, y dos editores se serializan (cada registro con su autor real).

**Conciliación** (QA-03): `opg_objetos_registro_conciliar($db, $audit_id, $meta)`, con el bloqueo del objeto tomado:

1. Lee el pendiente. Si `id > 0`, relee la fila por `id`; si `id = 0` (alta), busca por `objeto_id` (`WHERE objeto_id = '<id>'`).
2. Alta sin fila, o fila igual a `antes_completo` (comparación normalizada): `estado = fallido`. Si era un renombre y el inventario ya tiene filas con el id nuevo mientras el objeto conserva el viejo, se **revierte** el paso 5 (`UPDATE mybb_op_inventario SET objeto_id = '<viejo>' WHERE objeto_id = '<nuevo>'`) y el registro lo anota (`"inventario_revertido": true`). Es seguro porque la validación del paso 2 garantizó que no había inventario con el id nuevo antes del renombre y el id nuevo no existía como objeto.
3. Fila distinta del antes: se construye el payload real (antes de `antes_completo`, después de la fila), `estado = confirmado` (o `sin_cambios`), `origen` se conserva y se añade `"conciliado": true`. El `autor` sigue siendo el del pendiente.
4. Pendiente no conciliable (versión mínima de 3.7): queda `pendiente` y el log del staff lo muestra con el aviso "Revisar a mano".

Cuándo se llama:

- En el paso 0 de cada guardado de ese objeto (las funciones de guardado buscan sus pendientes con Q-P, 6.1).
- Con el botón `POST` "Conciliar" del log del staff (CSRF y permiso de staff).
- **No** al abrir el formulario con `GET`: el `GET` solo muestra el aviso "Este objeto tiene un guardado sin confirmar" con un botón `POST` (ver 19, QA-03 aceptado en parte).

### 3.11 Clave normalizada de id (QA-08)

`opg_objetos_clave_id($id) = mb_strtoupper(rtrim(trim($id)), 'UTF-8')`. Se usa en todas las claves de arrays de PHP (alias, pertenencia, búsqueda) y en la comparación del renombre. Un cambio que solo difiere en mayúsculas es una actualización del campo `objeto_id`, sin validación de existencia (la fila es la misma para MySQL). Limitación conocida: la colación de MySQL tampoco distingue acentos; los ids del catálogo son ASCII en la práctica, y la calibración cuenta los que no lo son.

---

## 4. Cobertura de las rutas que cambian el catálogo

| Ruta | Quién | Qué escribe | ¿Se registra? |
|---|---|---|---|
| `op/staff/objetos_modificar.php` | Staff | Alta, edición, renombre, copias `custom=1` | **Sí** (4.1) |
| `op/barco_unico.php` | Staff | Alta y edición de barcos `custom=0` | **Sí** (4.2) |
| `op/staff/objetos_crear.php` | Staff | Alta y retirada | **Según D-13** (4.3) |
| `crafteo.php`, `inventario.php`, `personaje.php`, `barco_bautizar.php`, `suriprueba.php` | Jugadores | Copias `custom=1` | **No** (D-5 A) |
| `mybb_op_cambioid` | Herramienta antigua | Cambios masivos de id | **No** en esta fase |

### 4.1 `objetos_modificar.php`

El bloque `POST`, tras `verify_post_check` y la validación de obligatorios de hoy, llama a `opg_objetos_guardar_desde_formulario($db, $form, $meta)`, que devuelve `array('tipo' => 'ok'|'err', 'texto' => ...)` para la cookie de mensaje de hoy. La función hace los pasos 0 a 10 de 3.9 y estas validaciones:

1. Renombre (claves normalizadas distintas) con el id nuevo ya presente en `mybb_op_objetos`: "Ya existe otro objeto con el ID X."
2. Renombre con filas en `mybb_op_inventario` con el id nuevo (`SELECT COUNT(*) ... WHERE objeto_id = '<nuevo>'`, que usa la clave única): "Hay inventarios con el ID X; revísalos antes de renombrar." (QA-02).
3. Sin cambios: "No había cambios que guardar."
4. Bloqueo no obtenido: mensaje de 3.10.

Se elimina el log antiguo `Objeto X (... por Y): ...`. Las ediciones de copias se registran igual.

### 4.2 `barco_unico.php`

En `crear` y `editar`, la escritura en `mybb_op_barcos` se queda en la página; la de `mybb_op_objetos` pasa a `opg_objetos_barco_guardar_objeto($db, $accion, $valores, $meta)`, que recibe los valores **sin escapar** (`trim($_POST[...])`, más los enteros ya calculados con `_bu_int`), escapa ella misma y hace 3.9. La respuesta JSON no cambia; si el bloqueo no se obtiene, `{"error": "Otro guardado de este objeto está en curso; inténtalo de nuevo."}`. Orden: primero `mybb_op_barcos` (como hoy), después el objeto.

### 4.3 `objetos_crear.php` (DECISIÓN D-13)

| Opción | Diseño |
|---|---|
| **A (recomendada por 112-R)** | Redirección 301 a `objetos_modificar.php` (patrón de `op/ficha.php`; no se borra, H-8). Nueva acción `POST accion=retirar` en `objetos_modificar.php` con CSRF, que llama a `opg_objetos_retirar($db, $objeto_id, $meta)`: id destino `'BORR' . objeto_id` (no el id interno; QA-20); rechazo si el objeto ya empieza por `BORR` o su categoría es `borrada` ("Este objeto ya está retirado.") y si ya existe una fila con el id destino ("Ya existe un objeto retirado con el ID BORRX; cambia antes ese ID."); `UPDATE ... SET objeto_id = 'BORR...', categoria = 'borrada' WHERE id = <id>` con 3.9 (acción `retirada`). |
| B | Reescritura con `escape_string`, `verify_post_check` y la librería; el alta rechaza un id existente ("Ya existe un objeto con el ID X.", QA-21) y la retirada aplica las mismas reglas que en A. |
| C | Solo se añade el registro con la librería (que escapa lo que recibe), incluido el rechazo de alta duplicada (QA-21); los `INSERT` y `UPDATE` propios siguen sin escapar. No recomendable. |

En las tres, la retirada no toca `mybb_op_inventario` (como hoy). Hallazgo para el PM (16).

---

## 5. Registros antiguos (430)

### 5.1 Formatos reconocidos

`opg_objetos_interpretar_log($log)` devuelve el modelo común o `null`, sin avisos de PHP ante tipos inesperados (QA, sección 8.1: `cambios` no lista, `antes` numérico, `null` o array, `id` como cadena, `v` como cadena: todos dan `null`).

| Formato | Expresión | Resultado |
|---|---|---|
| Nuevo | prefijo exacto de la marca; JSON con `v === 1`, `id` entero, `objeto_id` cadena no vacía, `despues` objeto, `cambios` lista, `estado` de la lista fija | `estructurado` |
| Alta de `objetos_crear.php` (133) | `/^Nuevo objeto creado de objeto ID\s+(\S+)\s+\((.*?)\)\.\s*\nEl nuevo objeto posee:\s*\n(.*)$/su`; líneas `/^(oid|nombre|categoria|calidad|dano|berries|cantidadMaxima|unidades|imagen|descripcion)=(.*?),?$/u`; `descripcion` se queda con el resto. **Sin `stripslashes`**: MySQL ya consumió las barras al guardar (QA-22). | `legacy`, `alta`, `valores` sin `calidad` ni `unidades` |
| Alta o edición de `objetos_modificar.php` (49 y 248) | `/^Objeto\s+(\S+)\s+\((creado|modificado) por (.*?)\):\s*nombre=(.*), categoria=(.*?), subcategoria=(.*)\.\s*$/su` | `legacy`, `alta` o `actualizacion`, `estado_tras` |
| Retirada antigua | `/^Remover técnica ID\s+(\S+)\s+\((.*)\)\. Nuevo ID de la técnica: BORR\1\. Tipo: borrada\.\s*$/su` | `legacy`, `retirada`, `dudoso` |

### 5.2 Resolución del objeto actual

1. Mapa de alias (6.1 Q2): si el id viejo tiene **varias** entradas (id reutilizado, QA-12), se elige la de un renombre **posterior** a la fecha del registro antiguo más cercano a ella; si ninguna lo es o hay empate, sin resolver.
2. Si no, la fila o filas actuales con ese `objeto_id`.
3. Sin fila o sin resolver: solo staff.

### 5.3 Visibilidad de los antiguos

Por el estado actual (112-GD 6.1). Con varias filas actuales para el mismo id, **la más restrictiva**: si alguna no es pública, solo staff (QA-18). Copia (`custom=1` o patrón de copia sin fila): solo staff. **Sin excepción de dueño** (C-16, pendiente de confirmar por el Game Designer). Cabecera: nombre, categoría y subcategoría del propio log (`estado_tras` o `valores`); tier e imagen de la fila actual, que es pública por definición si la entrada se muestra. **DECISIÓN D-12**: A, visibles; B, solo con `formato=legacy`; C, no se consultan.

### 5.4 Retiradas antiguas

Solo staff, "Retirada antigua (sin confirmar)", y solo si existe hoy una fila `BORR<id>`.

### 5.5 Aviso único

"Antes del [fecha] no se guardaba qué cambiaba en cada objeto. Esos registros aparecen como Registro antiguo.", una vez, cuando hay antiguos en los resultados. Fecha: constante `OPG_OBJETOS_CAMBIO_INICIO`, fijada en I-2. El diseño visual de este aviso falta en 214-UI (C-23).

---

## 6. Flujo de consulta

### 6.1 Consultas exactas

Todas en `opg_objetos_hist_consultar()` (o en las funciones de guardado, Q-P), con `$db->query($sql, 1)` (`hide_errors`) y comprobación de `false` (QA-15). Valores enteros con `(int)`, cadenas con `escape_string`, patrones `LIKE` con `escape_string_like`.

**Q1. Candidatos del periodo** (siempre):

```sql
SELECT `id`, `tiempo`, `username`, `log`
FROM `mybb_op_audit_consola_mod`
WHERE (`log` LIKE '[CAMBIO\_OBJETO] %'
    OR `log` LIKE 'Nuevo objeto creado de objeto ID %'
    OR `log` LIKE 'Objeto % (creado por %'
    OR `log` LIKE 'Objeto % (modificado por %'
    /* solo staff: */ OR `log` LIKE 'Remover t_cnica ID % Tipo: borrada.')
  AND `tiempo` >= DATE_SUB(NOW(), INTERVAL 90 DAY)   /* 7, 30 o 90 como entero; se omite con periodo=todo */
  /* con ?cambio=N: AND `id` = N, sin condición de periodo */
ORDER BY `id` DESC
LIMIT 5001
```

- Sin `razon` ni `staff`. `t_cnica` usa `_` como comodín a propósito (tilde). La colación ignora mayúsculas y acentos; el parser vuelve a comprobar el formato exacto.
- Tope de 5.000: si se alcanza, se usan los 5.000 más recientes; el aviso "Hay más cambios de los que caben en una consulta; acota el periodo" se muestra **solo al staff** (QA-29). El orden `antiguos` se aplica en PHP.
- Con D-12 C, sin patrones antiguos.

**Q2. Cabeceras de renombres y retiradas** (sin periodo; **no se ejecuta con `periodo=todo`**, porque entonces el mapa se deriva de Q1, QA-17):

```sql
SELECT `id`, `tiempo`, LEFT(`log`, 1200) AS `cabecera`
FROM `mybb_op_audit_consola_mod`
WHERE `log` LIKE '[CAMBIO\_OBJETO] {"v":1,"accion":"renombrado","estado":"confirmado"%'
   OR `log` LIKE '[CAMBIO\_OBJETO] {"v":1,"accion":"retirada","estado":"confirmado"%'
ORDER BY `id` ASC
```

Lee solo los primeros 1.200 caracteres (la cabecera de 3.2 cabe: dos ids de 255 caracteres con escape incluidos), sin leer los blobs completos. `opg_objetos_leer_cabecera()` extrae `id`, `objeto_id_antes` y `objeto_id` con una expresión regular anclada y decodifica cada cadena con `json_decode`. Mapa resultante: por clave normalizada de id viejo, **lista** de `(id interno, audit_id, tiempo)` (QA-12); por id interno, lista ordenada de ids.

**Q3. Estado actual de los objetos referidos** (una consulta; solo si hay candidatos):

```sql
SELECT `id`, `objeto_id`, `nombre`, `categoria`, `subcategoria`, `tier`, `imagen`, `imagen_avatar`, `imagen_id`,
       `berries`, `espacios`, `dano`, `bloqueo`, `alcance`, `oficio`, `exclusivo`, `invisible`, `custom`,
       `comerciable`, `negro`, `crafteo_usuarios`
FROM `mybb_op_objetos`
WHERE `id` IN (5123, 5124, ...)
   OR `objeto_id` IN ('KAT001', ...)   /* solo ids de registros antiguos */
```

Con la versión 2, Q3 solo alimenta: la ficha compacta, "Ver en el catálogo", la visibilidad de los antiguos y la pertenencia. Por eso selecciona menos columnas (las de la ficha y las de la instantánea). Ninguna entrada se pinta con estos datos (QA-01).

**Q4. Inventario del visor con su id interno** (solo con sesión y candidatos; sustituye a Q4 y Q6 de la versión 1, QA-09 y QA-26):

```sql
SELECT i.`objeto_id`, o.`id` AS `id_objeto`, o.`custom`, o.`fecha_creacion`
FROM `mybb_op_inventario` i
LEFT JOIN `mybb_op_objetos` o ON o.`objeto_id` = i.`objeto_id`
WHERE i.`uid` = 261 AND i.`cantidad` > 0
```

**Q5. Acceso al mercado negro** (solo con sesión, si U-2 es A y alguna entrada candidata toca el grupo o la vía de mercado negro):

```sql
SELECT `rango_inframundo`, `oficios`, `muerto`, `aprobada_por` FROM `mybb_op_fichas` WHERE `fid` = 261
```

**Q-P. Pendientes de un objeto** (en las funciones de guardado, paso 0):

```sql
SELECT `id`, `log` FROM `mybb_op_audit_consola_mod`
WHERE `log` LIKE '[CAMBIO\_OBJETO] {"v":1,"accion":"%","estado":"pendiente","id":5123,%'
   OR `log` LIKE '[CAMBIO\_OBJETO] {"v":1,"accion":"alta","estado":"pendiente","id":0,"objeto\_id\_antes":"","objeto\_id":"KAT001"%'
```

(El id de objeto entra codificado con `json_encode` y después con `escape_string_like`.)

Las opciones de los filtros salen de las vistas visibles; los valores de filtro no tienen que pertenecer a esas listas (8, QA-13).

### 6.2 Índices y migración (paso acoplado del usuario)

`docs/214_historial_objetos_indices.sql`, idempotente con el patrón de `docs/bitacora_migration.sql`:

```sql
-- 1. Auditoria: prefijo de 48 caracteres del log + tiempo.
--    48 caracteres cubren '[CAMBIO_OBJETO] {"v":1,"accion":"renombrado","es', asi que Q2 y Q-P
--    son rangos estrechos y no recorren todos los registros de objetos (QA-17).
SET @op_hobj_idx_log_sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mybb_op_audit_consola_mod` ADD INDEX `idx_hobj_log_tiempo` (`log`(48), `tiempo`)',
    'SELECT 1')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mybb_op_audit_consola_mod'
    AND INDEX_NAME = 'idx_hobj_log_tiempo'
);
PREPARE op_hobj_idx_log_stmt FROM @op_hobj_idx_log_sql;
EXECUTE op_hobj_idx_log_stmt;
DEALLOCATE PREPARE op_hobj_idx_log_stmt;

-- 2. Catalogo: indice no unico por objeto_id. SOLO si la calibracion no encontro duplicados
--    de objeto_id (sin distinguir mayusculas) o el usuario acepto el riesgo de QA-16.
SET @op_hobj_idx_obj_sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mybb_op_objetos` ADD INDEX `idx_objeto_id` (`objeto_id`)',
    'SELECT 1')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mybb_op_objetos'
    AND COLUMN_NAME = 'objeto_id'
    AND SEQ_IN_INDEX = 1
);
PREPARE op_hobj_idx_obj_stmt FROM @op_hobj_idx_obj_sql;
EXECUTE op_hobj_idx_obj_stmt;
DEALLOCATE PREPARE op_hobj_idx_obj_stmt;

-- Para deshacer:
-- ALTER TABLE `mybb_op_audit_consola_mod` DROP INDEX `idx_hobj_log_tiempo`;
-- ALTER TABLE `mybb_op_objetos` DROP INDEX `idx_objeto_id`;
```

- Índice 1: 48 caracteres en `utf8` son 144 bytes más 4 del `timestamp`. Con prefijo de 48, el `LIKE` de la marca de Q1 es un rango y el periodo se filtra por *index condition pushdown*; correcto con el volumen previsto.
- Índice 2 (QA-16): con filas duplicadas de `objeto_id`, las consultas del juego sin `ORDER BY` podrían empezar a leer otra de las copias. Por eso la migración solo se ejecuta tras la calibración de duplicados; si hay, se resuelven antes en otra issue o el usuario acepta el riesgo por escrito en la issue I-9. Sin este índice, Q3 recorre la tabla solo con antiguos en el periodo, y la unión de Q4 recorre `mybb_op_objetos` en cada visita con sesión (MySQL usa el *block nested loop*: una pasada por llenado del búfer).
- `mybb_op_inventario` por `uid`: se mide con `EXPLAIN` (13.4); un índice, si hace falta, va en otra issue.
- La página funciona sin la migración; se recomienda antes de publicar la página (I-7). `ALTER TABLE` sobre MyISAM bloquea la tabla unos segundos: fuera de la hora punta.

### 6.3 Pasos de la página

La página (`op/historial_objetos.php`) hace solo 1, 2 y 16; el resto es `opg_objetos_hist_consultar()` (3, 4, 6, 7) y `opg_objetos_hist_construir()` (5 y 8 a 15), de modo que las pruebas ejercen el código real (QA-05).

1. Leer y validar parámetros (8).
2. Visor base: `uid` de la sesión, `es_staff = is_mod($uid) || is_staff($uid)`.
3. Q1 (o solo la entrada `cambio=N`).
4. Q2 (si `periodo` no es `todo`).
5. Interpretar Q1 y construir el mapa de alias; descartar lo que no esté `confirmado` (salvo antiguos).
6. Q3.
7. Si hay sesión: Q4 y, si procede, Q5.
8. Construir el visor completo (7.1) y la pertenencia por id interno (7.5).
9. Vista de cada registro (`opg_objetos_vista_para`): `null` o vista filtrada. Desde aquí solo vistas.
10. Autores, subcategorías y tiers (para las opciones) y total del periodo visible para ese visor.
11. Recuentos de los chips (todos los filtros salvo `accion` y `formato`).
12. Filtros y búsqueda sobre texto visible.
13. Orden por `audit_id`; desempate imposible (clave primaria).
14. Paginación (25), `pagina` limitada a `[1, paginas]`.
15. HTML con `objetos_historial_vista.php` y variables `{$ho_*}`. Días agrupados y fechas pintadas con la **misma** función (`my_date` con el reloj inyectado `$ahora`), para que "Hoy" y la fecha coincidan (QA-24).
16. `eval` de la plantilla y `output_page`. Si `consultar` devolvió error: estado de error de 214-UI 4, sin resumen ni paginación, y sin ningún id en el HTML.

### 6.4 Modelo común en memoria

```php
array(
    'audit_id' => 23511, 'tiempo' => '2026-12-05 10:32:00', 'username' => 'Gretta',
    'formato' => 'estructurado',          // o 'legacy'
    'accion' => 'actualizacion', 'estado' => 'confirmado',
    'id_objeto' => 5123,                  // 0 en antiguos sin resolver
    'objeto_id_antes' => 'KAT001', 'objeto_id' => 'KAT001',
    'origen' => 'objetos_modificar',
    'vis_antes' => array(...), 'vis_despues' => array(...),   // la segunda, derivada de 'despues'
    'despues' => array(...), 'antes' => array(...),           // 'antes' reconstruido (3.2)
    'cambios' => array(array('campo' => 'dano', 'antes' => '...', 'despues' => '...')),
    'valores' => array(), 'estado_tras' => array(), 'dudoso' => false,
    'autor_consistente' => true,
)
```

---

## 7. Reglas de visibilidad en el servidor

`opg_objetos_hist_politica()`:

```php
array(
    'invisibles'      => 'B', // U-1
    'mercado_negro'   => 'A', // U-2
    'copias_dueno'    => 'C', // U-4
    'copias'          => 'A', // D-2
    'mis_objetos'     => 'A', // D-7
    'dueno_por_copia' => 'no',// N-1: la excepción de dueño solo con el id exacto
    'staff'           => 'A', // D-8
    'staff_uids'      => 'si',// D-9 (detalle)
    'antiguos'        => 'A', // D-12
    'excluir_cff010'  => 'si',// N-2
    'precio'          => 'B', // D-6 (resuelta por 112-GD)
)
```

### 7.1 El visor

`opg_objetos_visor()` con: `uid`, `es_staff`, `poseidos` y `copias` (7.5), y `acceso_mn = opg_objetos_acceso_mercado_negro($ficha)` que replica **las tres condiciones** de `mercado_negro.php` (QA-06): existe la ficha, `aprobada_por != 'sin_aprobar'`, `muerto != '1'`, y rango del inframundo válido o Contrabandista de nivel 1 o más con Mercader nivel 2 o más. Sin ficha: sin acceso. Con D-8 B, el staff es un usuario normal.

### 7.2 Objeto público para un visor

`opg_objetos_es_publico($snap, $objeto_id, $visor, $politica)`, sobre una instantánea (registros nuevos) o una fila actual (antiguos, ficha):

1. `custom = 1`: no.
2. Prefijo `BORR` o `categoria = borrada`: no.
3. **DECISIÓN N-2**: `objeto_id` con prefijo `CFF010` (que `op/objetos.php` excluye del catálogo): no (recomendado, por coherencia con el catálogo; QA-28).
4. Id que empieza por número e `invisible = 1`: no.
5. `invisible = 0`: sí.
6. **DECISIÓN U-1**: A, no; C, sí; **B**, sí si en circulación pública (`exclusivo = 0` y `tier <= 3`, o `oficio_vacio = 0` y `crafteo_vacio = 1`) o en el mercado negro (`negro = 1`, tier 1 a 5) con acceso según U-2.

Para la ficha y "Ver en el catálogo" se usa `opg_objetos_visible_ahora($fila, $visor)` = público para el visor, o visor staff, o visor dueño exacto de esa fila (112-GD 2.3, columna "Dueño"; ver 19).

### 7.3 Tipos y contenido por visor (QA-19)

Con `pa` = público con `vis_antes` y `objeto_id_antes` (falso en un alta) y `pd` = público con `vis_despues` y `objeto_id`:

| `pa` | `pd` | Público (y usuario sin relación) | Dueño exacto (excepción, 7.5) | Staff (D-8 A) |
|---|---|---|---|---|
| sí | sí | Tipo de la acción; filas `Antes / Ahora` filtradas y líneas derivadas | Igual, con "Lo tienes" | Igual, con todos los campos |
| no | sí | "Nuevo en el catálogo": valores de **`despues` del propio registro**, sin `Antes / Ahora` | Si ya veía las entradas ocultas: tipo de la acción con `Antes / Ahora` ("Lo tienes"); si no, igual que el público | Tipo de la acción con `Antes / Ahora` y chip "Publicado" |
| sí | no | "Deja de estar en el catálogo": cabecera con el **antes reconstruido**, sin valores nuevos | Tipo de la acción con `Antes / Ahora` y "Lo tienes · no aparece en el catálogo" | Tipo de la acción con `Antes / Ahora` y "Solo staff" (motivo) |
| sí | retirada | "Retirado" (banda de 214-UI 3.7) | Igual | Igual |
| no | no | Nada | Tipo de la acción con `Antes / Ahora` y "Lo tienes · no aparece en el catálogo" | Tipo de la acción, "Solo staff" con motivo |
| (alta) | no | Nada | Valores iniciales y "Lo tienes · no aparece en el catálogo" | Valores iniciales, "Solo staff" |

Reglas comunes:

- **Cabecera** (nombre, id, categoría, subcategoría, tier, imagen) y **filtros de clasificación** usan los datos del registro: `despues`, salvo en "Deja de estar en el catálogo", que usa el antes reconstruido (QA-01). Nunca la fila actual.
- Una entrada pública sigue siéndolo aunque el objeto se oculte después; como se pinta con su registro, no revela nada posterior.
- Los cambios hechos mientras no era público no aparecen nunca para el público (req. 4, E-6), tampoco tras ocultar y volver a publicar (escenario "publicar, ocultar, editar" en T-VIS y 12.3).

### 7.4 Campos visibles por visor

| Clase | Visitante y usuario | Con acceso al mercado negro | Staff |
|---|---|---|---|
| pública | Fila | Fila | Fila |
| imagen | "Imagen actualizada", sin URL | igual | igual |
| transición (`invisible`) | Nunca como fila | igual | Fila "Aparece en el catálogo: Sí/No" |
| privada (`crafteo_usuarios`) | Texto de 112-GD 3.4 (vacía a no vacía, no vacía a vacía; nada entre dos listas). Línea personal "Ahora puedes / Ya no puedes fabricar este objeto" si el UID del visor entra o sale, con la lectura de `crafteo.php`: quitar espacios, partir por comas, comparación exacta de cada elemento, `NULL` como vacía (QA-30) | igual | Valores (**DECISIÓN D-9**, detalle) |
| mercado negro | Nunca | Fila | Fila |
| solo staff | Nunca | Nunca | Fila |
| **campo desconocido** (no está en la tabla 3.3) | Nunca | Nunca | Fila con la etiqueta "Campo no reconocido" (QA-10) |

Líneas derivadas (112-GD 5.3) con `vis_antes` y `vis_despues`. Si no queda ninguna fila ni línea, la entrada no existe para ese visor. Nunca se dice cuántos campos se ocultaron; "Ver cambios (N campos)" y el `+N` de `ct-campos` cuentan solo visibles.

**DECISIÓN D-6** resuelta por 112-GD (B ampliada).

### 7.5 Pertenencia, dueño y copias (QA-09, QA-11, QA-26)

La pertenencia se decide por **id interno**, no por el id escrito en cada registro:

- `poseidos_ids`: `id_objeto` de las filas de Q4 con `custom = 0`, más las de `custom = 1` (copias propias, para U-4).
- `copias_base_ids`: para cada fila de Q4 con `custom = 1` (el `custom` se lee de la base, no se deduce del id), se toma el prefijo con `/^(.+)-\d+-\d+$/` o `/^(.+)-\d+$/` y se resuelve a un id interno: primero por el mapa de alias, eligiendo la entrada cuyo renombre sea posterior a la `fecha_creacion` de la copia (QA-12); si no, por una fila de Q3 con esa clave normalizada; si no se puede decidir, sin base.
- Inventario sin fila en `mybb_op_objetos` (`id_objeto` nulo): no cuenta.
- Una entrada estructurada pertenece al visor si su `id_objeto` está en esas listas; una antigua, si su id resuelto (5.2) lo está. Así, tras `KAT001 -> KATF001`, las entradas de antes y de después muestran "Lo tienes".

Marcas: "Lo tienes" (exacto), "Tienes una copia" (copia; D-7 A), "Lo tienes · no aparece en el catálogo" (excepción), "Cambio del staff en tu objeto" (U-4 C).

Excepción de dueño (112-GD 2.3): **solo con el id exacto** mientras no decidan el Game Designer y el usuario (**DECISIÓN N-1**, QA-11). Con N-1 "sí", también por copia (comportamiento de la versión 1). La copia sigue dando "Tienes una copia" en las entradas públicas.

Copias editadas por el staff: **DECISIÓN U-4** (C, el dueño las ve; A, solo staff). Aviso de copia (112-GD 5.4.2, provisional) con `fecha_creacion` de Q4. **U-3** y **D-7** como en la sección 17. `alcance=mios`: solo vistas con alguna marca de pertenencia.

### 7.6 Búsqueda y alias

`q` busca en el texto visible de la vista (incluido el de la ficha compacta solo si se muestra) y en los alias **visibles** (ids de entradas de renombre visibles para ese visor). Nunca en campos filtrados, `crafteo_usuarios`, `razon` ni el payload crudo. Comparación sin distinguir mayúsculas (`mb_stripos`).

### 7.7 Staff

**DECISIÓN D-8** A: todas las entradas; las no públicas con `ho-entrada--solo-staff` y motivo: "objeto invisible", "fuera de circulación", "copia de jugador", "solo mercado negro", "campos reservados", "registro antiguo sin objeto actual", "retirada antigua sin confirmar", "registro con autor inconsistente" o "Campo no reconocido". "Editar (staff)" a `objetos_modificar.php?objeto_id=ID` (con `rawurlencode`). El visitante no recibe nunca el `title` del motivo ni ese enlace.

### 7.8 Catálogo frente a copias

Catálogo por 7.2 y 7.3; copias nunca públicas (D-2 A), dueño según U-4, staff siempre.

### 7.9 La vista

```php
array(
    'audit_id' => 23511, 'tiempo' => '...', 'autor' => 'Gretta',   // autor de la columna username
    'tipo' => 'actualizacion', // alta, actualizacion, renombrado, retirada, nuevo_catalogo, deja_catalogo, antiguo_alta, antiguo_actualizacion, antiguo_retirada
    'accion_url' => 'modificado', 'formato' => 'estructurado',
    'titulo' => 'Ajuste de combate',
    'cabecera' => array('objeto_id' => 'KAT001', 'nombre' => '...', 'categoria' => '...', 'subcategoria' => '...', 'tier' => '2', 'imagen' => '...'), // del registro (7.3)
    'filas' => array(), 'lineas' => array(), 'grupos' => array('combate'),
    'marcas' => array('tuyo'), 'motivo_staff' => '',
    'ficha' => null,           // datos de la fila actual solo si opg_objetos_visible_ahora()
    'enlace_catalogo' => false, 'enlace_staff' => false,
    'alias' => array('KAT001'), 'avisos' => array(),
)
```

---

## 8. Parámetros de la URL

`GET`, leídos con `$mybb->get_input()`; un valor que llega como array se trata como vacío (`INPUT_STRING` lo convierte a cadena vacía). Ninguno llega a SQL salvo `periodo` (lista fija) y `cambio` (entero).

| Parámetro | Valores | Neutro | Validación | Si no es válido |
|---|---|---|---|---|
| `q` | texto | `''` | `trim`, máximo 120 caracteres, UTF-8 válido | Vacío |
| `periodo` | `7`, `30`, `90`, `todo` | `90` | lista fija | Neutro y nota |
| `accion` | `todas`, `creado`, `modificado`, `renombrado`, `retirado` | `todas` | lista fija | Neutro y nota |
| `orden` | `recientes`, `antiguos` | `recientes` | lista fija | Neutro y nota |
| `categoria` | las siete categorías | `''` | lista fija; comparación sin mayúsculas con la cabecera de la vista | Neutro y nota |
| `subcategoria` | texto | `''` | **solo forma**: máximo 80 caracteres, sin caracteres de control (QA-13) | Neutro y nota solo si la forma es inválida; un valor inexistente u oculto da el estado vacío normal |
| `tier` | entero | `''` | `ctype_digit`, máximo 2 cifras | Igual |
| `campo` | `identidad`, `combate`, `precio`, `fabricacion`, `inventario`, `presentacion`, `disponibilidad`; `mercado_negro` con acceso o staff; `reservado` solo staff | `''` | lista fija por visor | Neutro y nota (la nota es la misma para "no existe" y "no lo puedes ver") |
| `autor` | texto | `''` | solo forma (máximo 100, sin control) | Igual que `subcategoria` |
| `formato` | `todos`, `estructurado`, `legacy` | `todos` | lista fija | Neutro y nota |
| `alcance` | `todos`, `mios` | `todos` | lista fija; `mios` sin sesión: neutro y "Inicia sesión para ver los cambios de tus objetos." | |
| `cambio` | entero mayor que 0 | ausente | `INPUT_INT` | Ausente (QA-14) |
| `pagina` | entero | `1` | `max(1)`, `min(paginas)` | |

`cambio=N` muestra solo esa entrada si es visible para el visor; si no existe o no es visible, el mismo estado vacío ("Sin cambios encontrados."), sin distinguir. "Enlace permanente" apunta a `/op/historial_objetos.php?cambio=N#cambio-N`. Con `cambio`, el resto de filtros se ignora y no se pintan chips ni paginación.

Correspondencia de tipos y `accion=`: `alta`, `nuevo_catalogo`, `antiguo_alta` a `creado`; `actualizacion`, `antiguo_actualizacion`, `deja_catalogo` a `modificado` (provisional, C-4); `renombrado`; `retirada` y `antiguo_retirada` (solo staff) a `retirado`.

Enlaces con `opg_objetos_hist_url()` (omite neutros y `pagina`, `http_build_query` con `&amp;`). Cambiar un filtro vuelve a la página 1.

---

## 9. Seguridad

### 9.1 Solo lectura

La página solo atiende `GET`, sin escrituras ni cookies propias: no necesita CSRF. Las acciones de escritura del staff (guardar, retirar, conciliar) son `POST` con `verify_post_check` o `post_code`.

### 9.2 Consultas

Enteros con `(int)`, listas de ids internos con `array_map('intval')`, `objeto_id` con `escape_string`, `LIKE` con `escape_string_like`, `periodo` de lista fija. Ningún parámetro de la URL se concatena en SQL.

### 9.3 Salida

- Texto con `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`; textos largos con `nl2br` tras el escape; en la ficha, `strip_tags` y escape, cortado a 60 caracteres.
- Atributos: `href` con `rawurlencode` del `objeto_id` (también en el ancla `objetos.php#ID`), `title` y `datetime` escapados. Pruebas con `"`, `<`, `'`, `` ` ``, `${x}`, `{$mybb->user['uid']}`, `</script>` y `&amp;` (sin doble escape), en nombre, `objeto_id`, autor, descripción y efecto (QA, sección 3, req. 60).
- Imágenes: solo `https://`, `http://` o `/` sin `//` inicial; si no, icono.
- Variables de plantilla con HTML ya escapado; el `eval` de la plantilla no reinterpreta su contenido (se prueba con `${` y `{$...}` en un nombre).

### 9.4 Lo que nunca sale de la base

Q1 no selecciona `razon` ni `staff`; el UID del staff no está en el JSON; `crafteo_usuarios`, `negro`, `negro_berries` (sin acceso), `fusion_tipo`, `engastes`, `invisible` como valor y las entradas no visibles nunca llegan al HTML del público.

### 9.5 Nada oculto en el navegador

Sin payload, sin `data-*` con valores, sin comentarios con datos, sin `id="cambio-N"` de entradas ocultas. Recuentos, opciones, `+N`, "Página X de Y" y avisos sobre vistas ya filtradas.

### 9.6 Permisos

Staff = `is_mod($uid) || is_staff($uid)` de la sesión; "Mis objetos" con el UID de la sesión.

### 9.7 Errores

Consultas con `hide_errors`; ante `false`, estado de error de 214-UI 4 ("No se pudo cargar el historial. Inténtalo de nuevo en unos minutos.") y ningún dato en el HTML (QA-15). En las rutas del staff, un error de base se comunica con el mensaje genérico de la ruta y deja el pendiente para la conciliación.

### 9.8 Registros falsificados

El autor mostrado es la columna `username`; un JSON con `autor` distinto queda solo para el staff (QA-27). Hoy ningún escritor de la tabla empieza el log con texto de jugador, así que el riesgo es bajo.

---

## 10. Rendimiento

| Consulta | Sin índices | Con la migración |
|---|---|---|
| Q1 | Recorrido de unas 23.000 filas | `range` sobre `idx_hobj_log_tiempo` |
| Q2 | Segundo recorrido (salvo `periodo=todo`, que no la ejecuta) | `range` estrecho sobre renombres y retiradas, leyendo 1.200 caracteres por fila |
| Q3 | Clave primaria; recorrido de 16.240 filas solo con antiguos | Clave primaria e `idx_objeto_id` |
| Q4 | Recorrido de `mybb_op_inventario` y unión con recorrido de `mybb_op_objetos`, solo con sesión | Recorrido de inventario (medir) y `ref` sobre `idx_objeto_id` |
| Q5 | Clave primaria | Igual |
| Q-P | Recorrido, solo al guardar en el staff | `range` |

Volumen esperado: 430 antiguos y unos 30 a 60 registros nuevos al mes. Con `despues` completo, un registro típico ocupa entre 2 y 6 KB (T-DBQ lo mide). Umbral fijado para req. 63: **20.000 registros de objetos** o un `log` medio de objetos mayor de 20 KB; al superarlo, D-11 B en otra fase. Sin caché.

---

## 11. Integración

### 11.1 Con `op/objetos.php` (D-10, V-5)

- **D-10 A**: ficha compacta con la fila actual solo si `opg_objetos_visible_ahora()`; "Ver en el catálogo" solo si además `invisible = 0`, no es Transportes y no empieza por `BORR`. C: sin ficha. B: fuera de alcance.
- Ancla en `op_objetos.html` (214-UI 8.2) con `decodeURIComponent` dentro de `try/catch` (QA-25).
- Tarjeta `.ht-cta` arriba (**V-5** A).
- **U-5** B: la corrección de la fuga del catálogo es otra issue y bloquea I-7 e I-8 (comparte archivos con I-8: en serie).

### 11.2 Con el historial de técnicas

Un enlace en `op_historial_tecnicas.html`; "Catálogo" e "Historial de técnicas" en la cabecera nueva.

### 11.3 Plantilla nueva

Según 214-UI con **V-1** B, **V-2** A, **V-3** A, **V-4** A (porcentaje en dinero, ver C-3), **V-6** A y **V-7** A (imagen de la cabecera desde el registro). Variables: `{$ho_fecha_inicio_registro}`, `{$ho_q}`, `{$ho_periodo_opciones}`, `{$ho_orden_opciones}`, `{$ho_accion}`, `{$ho_chips}`, `{$ho_opciones_categoria}`, `{$ho_opciones_subcategoria}`, `{$ho_opciones_tier}`, `{$ho_opciones_campo}`, `{$ho_opciones_autor}`, `{$ho_opciones_formato}`, `{$ho_campo_personal}`, `{$ho_avanzados_open}`, `{$ho_nota}`, `{$ho_filtros_activos}`, `{$ho_resumen}`, `{$ho_controles}`, `{$ho_aviso_antiguos}`, `{$ho_filas}`, `{$ho_paginacion}`. Todas las produce `opg_objetos_hist_construir()`.

### 11.4 Despliegue de plantillas

Subir una plantilla la activa (`template_sync.php`, unos 10 segundos), y `scripts/deploy_changed.sh` las sube: el Orchestrator retiene cada una y avisa al usuario. Orden:

1. `op_historial_objetos.html` (inerte) y después `op/historial_objetos.php` con sus librerías.
2. `op_objetos.html` y `op_historial_tecnicas.html` cuando la página esté publicada, y tras U-5 B.
3. `staff_objetos_modificar.html`: después de `objetos_modificar.php` (el manejador antes que el botón).
4. `staff_objetos_crear.html` (D-13 B) a la vez que `objetos_crear.php`.
5. `staff_log_consola.html`, si hace falta, después de `log_consola_mod.php`.

Reversión: subir la versión anterior desde git.

### 11.5 Condición de publicación

La página no se publica hasta que: la calibración 13.4 esté hecha (G-1 y G-2), U-5 resuelta según su opción, y la migración ejecutada o descartada por escrito.

---

## 12. Estrategia de pruebas

### 12.1 Archivos

| Código | Archivo | Tipo |
|---|---|---|
| T-REG | `tests/objetos/cambios.php` | Puro |
| T-ANT | `tests/objetos/antiguos.php` | Puro |
| T-VIS | `tests/objetos/visibilidad.php` | Puro |
| T-REN | `tests/objetos/renombres.php` | Puro |
| T-VISTA | `tests/objetos/vista.php` | Puro (HTML) |
| T-FIL | `tests/objetos/filtros.php` | Puro |
| T-CONS | `tests/objetos/construir.php` | Puro: `opg_objetos_hist_construir()` con filas simuladas; contiene la prueba de no fuga de 12.3 |
| T-STAFF | `tests/objetos/log_staff.php` | Puro |
| T-DB | `tests/objetos/registro_db.php` | MySQL: guardado real de las funciones de librería |
| T-CONC | `tests/objetos/concurrencia_db.php` | MySQL: dos conexiones |
| T-DBQ | `tests/objetos/consulta_db.php` | MySQL: `opg_objetos_hist_consultar()` y la migración |
| QA-M | Revisión manual del QA, incluido `curl` sin cookie sobre la página desplegada | Manual |
| U-CAP | Capturas del usuario (214-UI 10) | Manual |

### 12.2 Pruebas por grupo

**T-REG**: (1) alta con `despues` completo y `cambios` vacía; (2) `berries` 5000 a 6000; (3) sin cambios devuelve `null`; (4) solo espacios o `\r\n`; (5) renombre; (6) retirada; (7) publicado; (8) `vis_antes` con sus nueve claves y `vis_despues` derivada; (9) escalera de tamaño con largos de 20.000 caracteres y con diez cortos de 500 caracteres llenos de emoji (QA-23): siempre 60.000 bytes o menos y legible; (10) emoji como par sustituto; (11) orden exacto de claves y prefijo `[CAMBIO_OBJETO] {"v":1,"accion":"actualizacion","estado":"confirmado","id":`; (12) campos no registrados; (13) acceso al mercado negro: rango, `Sin rango`, Contrabandista 0 y 1, Mercader nivel 1, **muerto con rango y ficha sin aprobar con rango (sin acceso)**, sin ficha (QA-06); (14) clave normalizada y renombre solo de mayúsculas como `actualizacion`; (15) lectura de cabecera con `LEFT(..., 1200)` e ids de 255 caracteres con comillas; (16) lectura de `crafteo_usuarios` (`"352, 1044"`, `"35"` frente a `"352"`, comas finales, `NULL`).

**T-ANT**: los tres formatos con ejemplos reales; descripción con comas, saltos de línea y `a\b` (se conserva la barra, QA-22); nombre con comas; retirada con `Tipo: borrada.` frente a técnicas, akumas y virtudes; logs ajenos ignorados; JSON roto, `v` como cadena, `cambios` no lista, `despues` ausente, `antes` como array: `null` sin avisos.

**T-VIS**: E-1 a E-15 de 112-GD con visitante, usuario, dueño exacto, dueño de copia, usuario con mercado negro y staff, y las columnas de 7.3; U-1 A, B y C en E-7 y E-8; U-2 A y B en E-3; U-4 A y C en E-10; D-7 A y B; N-1 "no" y "sí"; **"publicar, ocultar y editar con un nombre centinela" (QA-01)**; pública antes y oculta después; creación única invisible; `CFF010` (N-2); campo desconocido `receta_secreta` solo staff (QA-10); antiguos con una fila, con dos filas de distinta visibilidad (QA-18), sin fila y copia; autor inconsistente.

**T-REN**: cadena de tres renombres; ciclo; cadena de 21 saltos sin resolver (solo staff); id reutilizado `A -> B`, luego nuevo `A -> C` (QA-12), con antiguos y copias de fechas distintas; copia `KAT001-261-3` resuelta por id interno; barco `BARCO01-4`; barco de catálogo `BARCO-2` con `custom = 0` (no es copia, QA-26); poseedor tras un renombre ve "Lo tienes" en las entradas de antes y de después (QA-09); inventario `kat001` frente a catálogo `KAT001` (QA-08); búsqueda por id viejo y nuevo.

**T-VISTA**: clase, icono y texto de cada tipo; antiguos sin "Antes", "Ahora", `ct-comparacion` ni tabla de tres columnas; retiradas sin `objetos.php#` ni `.ho-ficha`; `id="cambio-N"` y `aria-labelledby`; "Lo tienes" con icono; contextos de escape de 9.3; `javascript:` en imagen; porcentajes, miles, `comerciable` invertido, `9999999`, `tiempo_creacion` 0, 59 y 10.000 segundos; `berries` antes 0 ("antes sin valor"); agrupación por día con reloj inyectado a las 23:59 y 00:01 (QA-24).

**T-FIL**: cada parámetro válido, inválido, manipulado y como array; `subcategoria` y `autor` inexistentes (estado vacío sin nota, QA-13); `campo=reservado` sin staff; `alcance=mios` sin sesión; `cambio=N` visible, oculto e inexistente (misma salida, QA-14); página 999; chips y recuentos; avanzados abiertos si hay alguno activo.

**T-CONS** (construcción completa, con `ObjetosDbFalsa` si hace falta contar consultas): visitante sin sesión (req. 6), periodo vacío (no se piden Q3 ni Q4), un solo recorrido de datos por petición, y la prueba de no fuga de 12.3.

**T-STAFF**: alta, modificación, renombre, retirada, `pendiente` ("Cambio sin confirmar"), `fallido`, `conciliado`.

**T-DB** (sobre `opg_objetos_guardar_desde_formulario`, `opg_objetos_barco_guardar_objeto`, `opg_objetos_retirar` y `opg_objetos_registro_conciliar`): (1) guardado completo confirmado con el después releído; (2) entero vacío según el `sql_mode` medido; (3) sin cambios: ni `INSERT` ni `UPDATE` (se cuentan sentencias); (4) fallo simulado tras el pendiente: conciliación da `fallido`; (5) fallo tras escribir el objeto: conciliación da `confirmado`; (6) alta cortada antes de escribir el `id` en el pendiente: conciliación la encuentra por `objeto_id`; (7) renombre cortado entre inventario y objeto: reintento que completa el renombre, y conciliación previa que revierte el inventario si se concilia antes (QA-02); (8) renombre con fila residual de inventario con el id nuevo: rechazo sin escribir nada; (9) renombre hacia id existente: rechazo; renombre solo de mayúsculas: permitido; (10) log de 60.000 bytes en `TEXT utf8`; (11) duplicados de `objeto_id`: un registro por fila; (12) `barco_unico` con comilla en el nombre: sin cambio fantasma; (13) retirada: `BORRKAT001`, retirada repetida y destino existente rechazados; (14) alta duplicada en `objetos_crear` con D-13 B o C.

**T-CONC** (dos conexiones con `nt_conectar()`): doble envío (un solo registro); dos editores con campos distintos (dos registros, cada uno con su autor y solo sus campos); bloqueo agotado (mensaje y sin escrituras); renombre mientras otra conexión inserta inventario del id viejo; lectura de la página con un pendiente abierto (no aparece).

**T-DBQ**: Q1 con la marca escapada (no coincide con `[CAMBIOXOBJETO]`); periodo con filas de 6, 29, 89 y 91 días; Q2 lee solo cabeceras confirmadas de renombres y retiradas; `periodo=todo` no ejecuta Q2; tope de 5.001; Q4 con inventario sin fila de objeto y con `cantidad = 0`; Q5 con ficha muerta; error forzado con `hide_errors` (resultado `false`, sin datos en la salida); `EXPLAIN` sin índices y con la migración (`range` sobre `idx_hobj_log_tiempo` en Q1, Q2 y Q-P); migración ejecutada dos veces y revertida; tamaño medio del `log` de objetos.

### 12.3 Pruebas de no fuga (en T-CONS, sobre la salida completa de `opg_objetos_hist_construir`)

Con centinelas: `crafteo_usuarios = 'UIDCENTINELA352,1044'`, `negro_berries = 7777777`, `fusion_tipo = 'FUSIONCENTINELA'`, `engastes = 9`, `razon = 'RAZONCENTINELA'`, `staff = 999`, un campo desconocido `receta_secreta = 'RECETACENTINELA'`, y el escenario "publicar, ocultar, editar" con nombre `NOMBRECENTINELA` e imagen `IMGCENTINELA` en la edición oculta:

- Ninguna variable `{$ho_*}` para visitante y usuario sin acceso contiene ninguna centinela, ni `999`, ni `7.777.777`, ni el `id="cambio-N"` de una entrada oculta, ni el `title` de un motivo de staff, ni "Editar (staff)".
- `q`, `subcategoria`, `tier` y `categoria` con valores de la edición oculta devuelven el mismo estado vacío que un valor inventado.
- Los recuentos (chips, resumen, `+N`, "Página X de Y", total del periodo) son iguales con y sin las entradas ocultas presentes en Q1.
- El aviso del tope no aparece para el público.
- Un dueño de una copia de la base oculta no ve las entradas ocultas (N-1 "no").
- Un error forzado no deja ids en la salida.
- El staff ve los reservados y nunca `RAZONCENTINELA`.

### 12.4 Entorno MySQL propio (QA-07)

`tests/objetos/bootstrap_db.php`, con el patrón de `tests/notificaciones/bootstrap_db.php` (base propia por prueba, `nt_conectar()` o equivalente para varias conexiones), que:

- Extrae del volcado el `CREATE TABLE` de `mybb_op_objetos` (MyISAM), `mybb_op_audit_consola_mod`, `mybb_op_inventario` y `mybb_op_fichas`, ajustado a lo que diga `SHOW CREATE TABLE` en la calibración.
- Crea la base con el juego de caracteres de producción y fija `SET NAMES` de la conexión y `sql_mode` de sesión con los valores medidos (no los de `tests/aventuras/bootstrap_db.php`, que usa `utf8mb4` y modo estricto).
- Si la calibración no es concluyente sobre el modo, T-DB-2 se ejecuta en los dos modos.
- No usa datos de producción.

---

## 13. Riesgos y reversión

### 13.1 Riesgos técnicos

| Riesgo | Mitigación |
|---|---|
| MyISAM sin transacciones | Dos fases, inventario primero, bloqueo y conciliación (3.9, 3.10). |
| Conexiones persistentes de MySQL en MyBB: un bloqueo de un script muerto no se libera al cerrar | `RELEASE_LOCK` en la función de cierre; la calibración confirma si `pconnect` está activo (lo comprueba el usuario en `inc/config.php` sin compartirlo). Con `pconnect`, el bloqueo usa `IS_USED_LOCK` y un tiempo de espera corto. |
| Log truncado | Escalera de 60.000 bytes con cortos incluidos; pares sustitutos. |
| Cambios fantasma | Después releído; comparación normalizada; `sql_mode` medido. |
| Duplicados de `objeto_id`, también por mayúsculas | Un registro por fila; regla restrictiva en antiguos; índice condicionado (QA-16). |
| Fuga del estado actual en entradas públicas | Entradas pintadas desde el registro (QA-01). |
| Renombre a medias | Inventario primero, rechazo con inventario residual, reintento o reversión (QA-02). |
| Orden de claves del JSON cambiado en el futuro | Prueba del prefijo exacto; `v` fija el formato. |
| La página publica lo que el catálogo ya filtra | U-5 bloquea la publicación. |
| Plantilla y PHP desacompasados | Orden de 11.4. |
| Borrar `objetos_crear.php` en local | Redirección. |
| Ambigüedad de alias por reutilización de id | Listas con fecha; sin resolver, solo staff (QA-12). |
| Error SQL que muestra la consulta | `hide_errors` (QA-15). |
| `tiempo` con `ON UPDATE` | Calibración; hora propia en el JSON si hiciera falta. |

### 13.2 Reversión por pieza

| Pieza | Cómo se deshace | Efecto sobre los datos |
|---|---|---|
| Registro en las rutas del staff | Revertir el commit y desplegar el archivo anterior | Los registros escritos se quedan. Los `pendiente` que queden se concilian antes con el botón del log; sin la librería, el log del staff los enseña como JSON crudo. |
| `log_consola_mod.php` | Revertir | Ninguno. |
| Página pública | Revertir y desplegar una redirección a `op/objetos.php` | Ninguno. |
| Plantillas | Subir la versión anterior desde git | Ninguno. |
| Migración | `DROP INDEX` del final del archivo | Ninguno. |
| `objetos_crear.php` (D-13 A) | Restaurar desde git | Ninguno. |

### 13.3 Pasos acoplados para el usuario

1. **Calibración bloqueante** (13.4): G-1 antes de I-1 e I-2; G-2 antes de I-9 e I-7 (la issue de consulta).
2. **Plantillas**: `op_historial_objetos`, `op_objetos`, `op_historial_tecnicas`, `staff_objetos_modificar` (aviso de pendiente y, con D-13 A, retirar), `staff_objetos_crear` (D-13 B) y, si hace falta, `staff_log_consola`. Orden en 11.4.
3. **SQL**: `docs/214_historial_objetos_indices.sql` tras G-2; el índice 2 solo sin duplicados o con el riesgo aceptado.
4. **Archivo sustituido**: `objetos_crear.php` con D-13 A.
5. **Dependencia**: U-5 antes de publicar.
6. **Constante de fecha** `OPG_OBJETOS_CAMBIO_INICIO`.
7. **Decisiones** de la sección 17 antes de la issue que las usa.

### 13.4 Calibración (solo lectura, la ejecuta el usuario; bloqueante)

Por la regla de acceso a producción, se ejecuta como script PHP de solo lectura por `ssh` que carga el `inc/config.php` del servidor (así las variables de sesión son las de la conexión de MyBB). El resultado se anota en la issue correspondiente.

**G-1, antes de I-1 e I-2** (formato del entorno de pruebas y del registro):

```sql
SHOW CREATE TABLE `mybb_op_objetos`;
SHOW CREATE TABLE `mybb_op_audit_consola_mod`;   -- motor, juego de caracteres, ON UPDATE de tiempo
SHOW CREATE TABLE `mybb_op_inventario`;
SHOW CREATE TABLE `mybb_op_fichas`;
SELECT @@SESSION.sql_mode, @@GLOBAL.sql_mode, @@version, @@time_zone, NOW();
SHOW SESSION VARIABLES LIKE 'character\_set\_%';
SHOW SESSION VARIABLES LIKE 'collation\_%';
SELECT `name`, `value` FROM `mybb_settings` WHERE `name` IN ('errortypemedium', 'errorloglocation', 'errorlogmedium');
SELECT COUNT(*) FROM `mybb_op_objetos` WHERE `custom` = '0';                 -- req. 1: se esperan 1.204
SELECT MAX(LENGTH(`descripcion`)), MAX(LENGTH(`efecto`)), MAX(LENGTH(`requisitos`)), MAX(LENGTH(`escalado`)), MAX(LENGTH(`imagen`)) FROM `mybb_op_objetos`;
```

Más, en el mismo script: `PHP_VERSION`, `date_default_timezone_get()` y si la conexión de MyBB es persistente (`pconnect`), informando solo de sí o no.

**G-2, antes de I-9 e I-7** (consulta e índices):

```sql
SELECT UPPER(TRIM(`objeto_id`)) AS `clave`, COUNT(*) AS `n`, GROUP_CONCAT(`id`) AS `ids`
FROM `mybb_op_objetos` GROUP BY `clave` HAVING `n` > 1;                       -- duplicados sin distinguir mayúsculas
SELECT COUNT(*) FROM `mybb_op_objetos` WHERE `custom` = '0' AND `objeto_id` REGEXP '-[0-9]+$';   -- QA-26
SELECT COUNT(*) FROM `mybb_op_objetos` WHERE `objeto_id` LIKE 'CFF010%';     -- N-2
SELECT COUNT(*) FROM `mybb_op_objetos` WHERE `objeto_id` REGEXP '[^ -~]';    -- ids no ASCII
SELECT COUNT(*), MIN(`tiempo`) FROM `mybb_op_audit_consola_mod`;
EXPLAIN SELECT `id` FROM `mybb_op_audit_consola_mod` WHERE `log` LIKE '[CAMBIO\_OBJETO] %' AND `tiempo` >= DATE_SUB(NOW(), INTERVAL 90 DAY);
EXPLAIN SELECT `objeto_id` FROM `mybb_op_inventario` WHERE `uid` = 1;
```

Más las siete consultas de 112-GD 10 (necesarias para cerrar U-1).

---

## 14. Propuesta de corte en issues (para el PM)

| Issue | Contenido | Carril | Depende de | Acoplado |
|---|---|---|---|---|
| G-1 | Calibración de entorno (usuario) | n/a | - | Bloqueante |
| I-1 | `objetos_cambios.php`: campos, normalización, clave de id, payload v1 congelado, serialización, `abrir`, `cerrar`, `conciliar`, bloqueo; `tests/objetos/bootstrap_db.php`; T-REG y T-DB 1 a 6, 10 | Normal | G-1 | No |
| I-2 | `opg_objetos_guardar_desde_formulario()` y `objetos_modificar.php` (con aviso de pendiente); constante de fecha; T-DB 7 a 9, 11; T-CONC | Normal | I-1 | Plantilla `staff_objetos_modificar` |
| I-3 | `opg_objetos_barco_guardar_objeto()` y `barco_unico.php`; T-DB 12 | Normal | I-1 | No |
| I-5 | `log_consola_mod.php` legible y botón "Conciliar"; T-STAFF | Normal | I-1 | Posible plantilla |
| I-4 | `objetos_crear.php` según D-13 (y `opg_objetos_retirar()`); T-DB 13 y 14 | Normal | I-2 | Plantilla según D-13 |
| I-6 | Parser antiguo, alias, visibilidad, filtrado, pertenencia; T-ANT, T-VIS, T-REN | Normal | I-1; U-1, U-2, N-1 al menos provisionales | No |
| G-2 | Calibración de consulta (usuario) | n/a | - | Bloqueante |
| I-9 | Migración de índices; T-DBQ (`EXPLAIN`) | Normal | G-2 | SQL |
| I-7 | `objetos_historial.php`, `objetos_historial_vista.php`, página y plantilla; T-VISTA, T-FIL, T-CONS, T-DBQ | Grande | I-6, G-2, I-9 (o descarte), U-5 | Plantilla |
| I-8 | `op_objetos.html` y `op_historial_tecnicas.html` | Normal | I-7, U-5 | Plantillas |

I-2, I-3 e I-5 en paralelo tras I-1 (archivos distintos); I-2 e I-4 en serie.

---

## 15. Trazabilidad

| Req. | Resumen | Sección | Prueba |
|---|---|---|---|
| 1 | Catálogo `custom='0'`, recuento | 7.2, 13.4 G-1 | Calibración (1.204), anotada en I-2; `CFF010` según N-2 |
| 2 | Sin copias públicas | 7.2, 7.8 | T-VIS, 12.3 |
| 3 | Sin invisibles públicos | 7.2, 7.3 | T-VIS E-6 a E-8, E-13, "publicar, ocultar, editar" |
| 4 | Publicación sin revelar el pasado | 7.3 | T-VIS, 12.3 (QA-01) |
| 5 | Sin movimientos de inventario | 4, 6.1 | T-ANT, T-DBQ |
| 6 | Pública sin sesión | 6.3, 9.6 | T-CONS (visitante), QA-M con `curl` |
| 7 | `Mis objetos` con copias | 7.5 | T-REN (renombre, mayúsculas), T-VIS |
| 8 | Solo lectura | 9.1 | QA-M, T-DBQ |
| 9 | Staff ve más | 7.3, 7.7 | T-VIS, T-VISTA |
| 10 | Vista única, 90 días | 6.1, 8 | T-FIL |
| 11 | Datos de cada entrada (del registro) | 7.3, 7.9 | T-VISTA, 12.3 |
| 12 | Detalles contraídos | 11.3 | T-VISTA |
| 13 | `Antes / Ahora`; alta con iniciales | 3.2, 7.3 | T-VISTA |
| 14 | Renombre une el historial (desde `OPG_OBJETOS_CAMBIO_INICIO`) | 6.1 Q2, 7.5, 7.6 | T-REN |
| 15 | "Registro antiguo" | 5 | T-VISTA |
| 16 | Agrupado por día | 6.3.15 | T-VISTA (reloj inyectado) |
| 17 | Ancla estable | 8 (`cambio=N`) | T-FIL |
| 18 | `Ver objeto actual` | 7.2, 11.1 | T-VISTA |
| 19 | "Lo tienes" con texto | 7.5 | T-VISTA, T-REN |
| 20 | Precio y tier | 7.4 | T-VIS E-1, E-2 |
| 21 | `crafteo_usuarios` sin valores | 7.4 | T-VIS E-5, T-REG-16, 12.3 |
| 22 | Filtros principales | 8 | T-FIL |
| 23 | Filtros avanzados | 3.3, 8 | T-FIL |
| 24 | Validación de filtros | 8 | T-FIL (forma; valor inexistente) |
| 25 | Avanzados abiertos | 8 | T-FIL |
| 26 | Filtros en la URL | 8 | T-FIL |
| 27 | Etiquetas y `Limpiar filtros` | 8, 11.3 | T-FIL, T-VISTA |
| 28 | Página 1 al cambiar filtro | 8 | T-FIL |
| 29 | Resumen (cambios visibles para ti) | 6.3.10 | T-CONS, 12.3 |
| 30 | Estado vacío | 11.3 | T-VISTA |
| 31 | `Ver objeto actual` y ancla | 11.1 | T-VISTA, U-CAP |
| 32 | Tarjeta en el catálogo | 11.1 | QA-M, U-CAP |
| 33 | Enlace de staff | 7.7 | T-VISTA, 12.3 |
| 34 | Retirado sin objeto actual | 7.3, 11.1 | T-VISTA |
| 35 | Registro de todas las rutas del staff | 3, 4 | T-DB sobre las funciones de guardado |
| 36 | `audit_consola_mod` con marca | 3.1 | T-REG-11, T-DB-1 |
| 37 | Antes (y después) de la base | 3.5 | T-DB-1, T-DB-2 |
| 38 | Sin cambios, sin entrada | 3.5 | T-REG-3, T-DB-3 (sin sentencias) |
| 39 | Los 32 campos | 3.3 | T-REG-1, T-REG-12 |
| 40 | Instantánea | 3.2, 3.4 | T-REG-8 |
| 41 | Renombre con id interno | 3.2, 3.6 | T-REG-5, T-REN |
| 42 | Retirada | 3.6, 4.3 | T-REG-6, T-DB-13 |
| 43 | Abreviado | 3.7 | T-REG-9 |
| 44 | Log del staff legible | 3.10, 2 (I-5) | T-STAFF |
| 45 | Librería pura | 1, 12 | Todas |
| 46 | Formatos antiguos | 5.1 | T-ANT |
| 47 | Estado tras el cambio | 5.1, 5.3 | T-VISTA |
| 48 | Alta antigua | 5.1 | T-ANT (`a\b`) |
| 49 | Antiguos por estado actual | 5.3 | T-VIS (una y dos filas) |
| 50 | Sin `razon` | 5.1, 9.4 | 12.3 |
| 51 | `label` | 11.3 | QA-M |
| 52 | Nativos sin JS | 11.3 | QA-M |
| 53 | 40 px y foco (44 px en 214-UI) | 11.3 | QA-M |
| 54 | Texto además de color | 7.9, 11.3 | T-VISTA, QA-M |
| 55 | Móvil | 11.3 | QA-M, U-CAP |
| 56 | Expandir y contraer | 11.3 | QA-M |
| 57 | Consulta acotada | 6.1 | T-DBQ |
| 58 | Catálogo no cargado entero | 6.1 Q3 | T-CONS, T-DBQ |
| 59 | Inventario una vez | 6.1 Q4 | T-CONS (periodo vacío), T-DBQ |
| 60 | Escape | 9.3 | T-VISTA (contextos) |
| 61 | URL validada | 8, 9.2 | T-FIL |
| 62 | Sin `razon` ni UID del staff | 9.4 | 12.3 |
| 63 | Umbral de volumen (20.000 registros o 20 KB de media) | 10 | T-DBQ (medida) |

Criterios de aceptación de 112-R 14: 1 (T-CONS, QA-M), 2 (T-DB-1), 3 (T-DB-3), 4 (T-REN), 5 (T-DB-13, T-VISTA), 6 (T-VIS), 7 (T-VIS "publicar, ocultar, editar"), 8 (T-ANT, T-VISTA), 9 (T-REN), 10 (T-FIL), 11 (12.3), 12 (T-STAFF), 13 (T-REG, T-ANT, T-VIS), 14 (QA-M, U-CAP), 15 (`sh tests/run_all.sh`).

---

## 16. Cambios que este plan exige a los requisitos 112

1. Cifras de 112-GD 0 (1.204 de catálogo, 15.036 copias, 543 visibles, 661 invisibles, 792 exclusivos); req. 1 espera 1.204 y decide `CFF010` (N-2).
2. Req. 35: añadir `op/barco_unico.php`.
3. Req. 37: el después también se relee de la base; comparación normalizada (req. 38).
4. Req. 40: instantánea compacta del antes y **estado completo después** en cada registro (formato v1 congelado).
5. Req. 39: `custom`, `desbloqueado` y `fecha_creacion` no se comparan; `id` es clave propia.
6. Req. 3 y 4: visibilidad por instantánea; cada entrada se pinta con los datos de su registro.
7. Req. 7: pertenencia por id interno, base de copia siguiendo renombres, ids sin distinguir mayúsculas.
8. Nuevo: excepción de dueño (solo id exacto mientras no se decida N-1).
9. Nuevo: entradas sin campos visibles no existen; recuentos tras filtrar.
10. Req. 23: nueve grupos.
11. Req. 20 y D-6: resuelto por 112-GD; mercado negro según U-2 con la regla completa de acceso (ficha aprobada, no muerto).
12. Req. 21: textos de 112-GD 3.4 con la lectura de `crafteo.php`.
13. Req. 9: motivos de "Solo staff" ampliados.
14. Req. 11 y 13: tipos "Nuevo en el catálogo" y "Deja de estar en el catálogo"; tipos de staff y dueño (7.3).
15. Nuevo: rechazo de renombre hacia id existente **o con inventario existente**; renombre solo de mayúsculas permitido.
16. Nuevo: un registro por fila duplicada.
17. Req. 36: el registro no requiere migración; consulta con migración de índices opcional tras calibración.
18. Req. 42 y D-13: redirección; retirada a `BORR` + `objeto_id` con rechazos.
19. Req. 44: pendientes, conciliación y botón "Conciliar".
20. Req. 46: retirada antigua reconocida, solo staff; sin `stripslashes`.
21. Req. 57: Q2 solo de cabeceras y omitida con `periodo=todo`; tope de 5.000 con aviso solo para el staff.
22. Req. 58: id interno por clave primaria; `objeto_id IN` solo para antiguos.
23. Req. 53: igualar 40 y 44 px.
24. Nuevo, dependencia: la página no se publica antes de U-5 y de la calibración.
25. `DATABASE.md`: `requisitos` son de uso.
26. **Req. 14**: limitar "todo el historial" a los registros posteriores a `OPG_OBJETOS_CAMBIO_INICIO` (los renombres anteriores no se pueden seguir).
27. **Req. 17**: ancla estable como `?cambio=N`.
28. **Req. 24**: `subcategoria`, `tier` y `autor` se validan por forma, no contra datos reales (QA-13); `categoria` sigue con lista fija.
29. **Req. 29**: "cambios visibles para ti en el periodo", no "cambios reconocidos".
30. **Req. 63**: umbral de 20.000 registros de objetos o 20 KB de media.
31. Nuevo: bloqueo con nombre en las tres rutas del staff; mensaje si hay otro guardado en curso.
32. Nuevo: errores de consulta sin datos en la página (`hide_errors`).

Pendientes para el PM (fuera de alcance): (1) la retirada no actualiza `mybb_op_inventario`, y un alta posterior con el mismo id "hereda" esos inventarios; (2) `op/suriprueba.php` activo y sin escape; (3) ayuda invertida de `comerciable` y `exclusivo`; (4) `mybb_op_cambioid` sin registro; (5) `op_objetos.html` no pinta Transportes; (6) estadísticas de `mybb_op_barcos` sin historial; (7) `barco_unico.php` en `crear` sobrescribe nombre, imagen, descripción, oficio y receta de cualquier objeto con el mismo id aunque no sea un transporte (QA-31); (8) 214-UI debe diseñar las marcas, bloques y avisos que faltan (C-9, C-23) antes de I-7.

---

## 17. Decisiones abiertas y cómo cambia el diseño

Con el estado completo después en cada registro (3.2), **el formato v1 ya no depende de ninguna decisión**: U-1, U-2, D-6, D-7 y N-1 se aplican al leer. Por eso basta con cerrarlas (al menos de forma provisional) antes de I-6, no antes de I-1 (ver 19).

| Decisión | Diseño con la recomendación | Si se elige otra opción | Necesaria antes de |
|---|---|---|---|
| **D-1** | A | B: `fabricacion` y `mercado_negro` a solo staff. C: página solo staff. | I-6 |
| **D-2** | A | B: se quita 7.2.1. C: igual que U-4 C. | I-6 |
| **D-3 / U-1** | B | A o C: un valor de la política. | I-6 |
| **D-4** | A | B: fuera de este plan. | n/a |
| **D-5** | A | B o C: fuera de este plan. | n/a |
| **D-6** | B ampliada (112-GD) | C: precio y tier a solo staff. | I-6 |
| **D-7** | A | B: sin `copias_base_ids` ni aviso de copia. | I-6 |
| **D-8** | A | B: staff como usuario. | I-6 |
| **D-9** | A con textos de 112-GD; el staff ve UID | Staff sin UID; B: resolver a nombres (no recomendado); C: sin fila. | I-6 |
| **D-10** | A | B: fuera. C: sin ficha. | I-7 |
| **D-11** | A | B: tabla propia, migración antes de I-2. | I-1 |
| **D-12** | A | B: solo con `formato=legacy`. C: sin antiguos en Q1. | I-7 |
| **D-13** | A | B o C: 4.3. | I-4 |
| **U-2** | A (regla completa de acceso) | B: solo staff, sin Q5. C: pública. | I-6 |
| **U-3** | A | B: aviso sin "no la modifica". C: fuera. | I-6 |
| **U-4** | C | A: solo staff. | I-6 |
| **U-5** | B | A: primera issue. C: U-1 pasa a C. | I-7 |
| **V-1 a V-7** | Como en 11.3 | Ver 214-UI 11. | I-7 |
| **N-1 (nueva)** Excepción de dueño por copia (QA-11) | **No**: solo con el id exacto | Sí: quien tiene una copia ve también las entradas ocultas de la base (como la versión 1 del plan). Decide el Game Designer con el usuario. | I-6 |
| **N-2 (nueva)** Objetos `CFF010%` (QA-28) | Fuera del historial público, como en el catálogo | Tratarlos como cualquier objeto. Conviene saber qué son (calibración G-2). | I-6 |
| **N-3 (nueva)** Excepción de dueño en registros antiguos (C-16) | No | Sí: el dueño exacto ve también los antiguos no públicos (solo nombre, categoría y subcategoría). Decide el Game Designer. | I-6 |

---

## 18. Contradicciones entre los documentos de entrada

Confirmadas por el QA (C-1 a C-15) y ampliadas (C-16 a C-23):

| # | Tema | Propuesta de este plan |
|---|---|---|
| C-1 | Req. 3 (estado actual) frente a 112-GD 2.4 (instantánea) | Instantánea, y solo es segura porque cada entrada se pinta con su registro (QA-01). |
| C-2 | Siete grupos (112-R, 214-UI) frente a nueve (112-GD) | Nueve; 214-UI 2.4 y 3.4 deben actualizarse. |
| C-3 | Diferencia absoluta (214-UI) frente a porcentaje (112-GD) | Porcentaje en los tres campos de dinero, diferencia absoluta en el resto, siempre en gris. |
| C-4 | "Objeto publicado" frente a "Nuevo en el catálogo"; falta "Deja de estar" en 214-UI | Texto de 112-GD; "Deja de estar" necesita estilo y chip (provisionalmente `modificado`). |
| C-5 | Sí/No directos (214-UI) frente a semántica del código (112-GD) | 112-GD. |
| C-6 | "Berries" frente a "Valor en berries" | "Valor en berries". |
| C-7 | Ficha "si visible" frente a U-1 B; el QA añade que el plan la mostraba al dueño | Ficha con `opg_objetos_visible_ahora()` (público, staff o dueño exacto); ver 19. |
| C-8 | Texto de retirada | 112-GD. |
| C-9 | Marcas, bloques y avisos que faltan en 214-UI (incluidos el aviso único de antiguos, el aviso del tope y la retirada antigua) | UI antes de I-7. |
| C-10 | Motivos de "Solo staff" | Los de 7.7. |
| C-11 | `engastes` largo en 214-UI | Entero, solo staff. |
| C-12 | Título por grupo frente a nombre | Insignia de 214-UI y título de 112-GD como texto del tipo. |
| C-13 | Cifras | 112-GD. |
| C-14 | 40 frente a 44 px | 44 px. |
| C-15 | D-3 A frente a U-1 B | U-1 B, pendiente. |
| C-16 | Excepción de dueño en antiguos | N-3 (no, por ahora). |
| C-17 | Excepción de dueño por copia | N-1 (no, por ahora). |
| C-18 | Validación de filtros: req. 24 frente a este plan frente a 214-UI ("tier 1 a N") | Por forma (8); cambia req. 24. |
| C-19 | Req. 29 "reconocidos" frente al principio 5 | "Visibles para ti". |
| C-20 | Regla del mercado negro incompleta en 112-GD 5.5 | Regla completa del código (7.1). |
| C-21 | Req. 14 frente a pertenencia por id exacto | Por id interno (7.5). |
| C-22 | Tipos de staff y dueño sin definir | Tabla de 7.3. |
| C-23 | Aviso único de antiguos sin diseño en 214-UI | UI antes de I-7. |

---

## 19. Hallazgos del QA no aceptados o aceptados en parte (en discusión)

| Hallazgo | Qué se aplicó | Qué no y por qué | Estado |
|---|---|---|---|
| **QA-03** (conciliación "al abrir el formulario") | Pendiente con antes completo e `id` (también en altas), función de conciliación con los tres desenlaces, llamada en cada guardado y desde el log del staff. | No se concilia en el `GET` del formulario: una conciliación escribe (estado del registro y, en un renombre fallido, el inventario), y una escritura en `GET` se puede disparar desde fuera con una imagen o una precarga del navegador y salta el CSRF. En su lugar, el `GET` muestra el aviso con un botón `POST`. El efecto práctico es el mismo: el siguiente guardado concilia antes de escribir. | **Discusión** con el QA. |
| **Punto 10 del QA** (cerrar U-1 y U-2 antes de I-1) | Las decisiones se piden antes de I-6. | Con el estado completo después en cada registro (QA-01), el formato ya no depende de U-1 ni de U-2: todo lo necesario para aplicar cualquier opción queda guardado. Pedirlas antes de I-1 retrasaría el registro (req. 35) sin ganar nada. | **Discusión**. |
| **C-7 ampliada por el QA** (la ficha solo si la fila actual es pública para el visor) | La ficha nunca la ve el público si el objeto no es público hoy. | Se mantiene para el **dueño exacto**: 112-GD 2.3 le concede ver las entradas de su objeto aunque no sea público, y su ficha actual no revela nada que no vea ya en su inventario. No aplica al dueño de una copia (N-1). | **Discusión**; si el Game Designer no lo comparte, se quita con un valor de la política. |
| **QA-27** ("descartar" el JSON con autor inconsistente) | El autor mostrado es siempre la columna `username`. | En lugar de descartar el registro, queda solo para el staff con el motivo "registro con autor inconsistente": así el staff ve el intento sin que el público lo vea. | Aceptado con un ajuste menor. |
| **QA-04** (`GET_LOCK` libera el bloqueo si la conexión se cae) | `GET_LOCK` en las tres rutas y en la conciliación. | Matiz: si MyBB usa conexiones persistentes, la conexión no se cierra al morir el script y el bloqueo puede quedar tomado. Se añade `RELEASE_LOCK` en una función de cierre y la comprobación de `pconnect` en G-1. | Aceptado con un riesgo añadido (13.1). |

No hay ningún otro hallazgo rechazado. Los hallazgos ALTOS (QA-03 a QA-09) y MEDIOS (QA-10 a QA-21) se aplicaron con las salvedades de esta tabla; los BAJOS (QA-22 a QA-31) también, y QA-31 pasa al PM como hallazgo.

---

## 20. Registro de cambios del plan

| Versión | Cambio | Origen | Secciones |
|---|---|---|---|
| 2 | JSON v1 congelado con `despues` completo (32 campos y `custom`) y `abreviado_despues`; `cambios` solo con el antes; `vis_despues` derivada; orden de claves con cabecera legible (`v`, `accion`, `estado`, `id`, `objeto_id_antes`, `objeto_id`). | Cambio 1, QA-01, QA-17 | 3.2, 3.4, 3.7 |
| 2 | Cabecera, "Nuevo en el catálogo", "Deja de estar" y filtros de clasificación desde el registro; fila actual solo para ficha y catálogo si es visible para el visor. | Cambio 1, QA-01 | 1, 6.1, 7.2, 7.3, 7.9, 11.1 |
| 2 | Inventario antes que el objeto en el renombre; rechazo con inventario existente en el id nuevo; reintento y reversión. | Cambio 2, QA-02 | 3.9, 3.10, 4.1 |
| 2 | Pendiente con `antes_completo`, `esperado` e `id` también en altas; `opg_objetos_registro_conciliar()` con `fallido`, `confirmado` y `sin_cambios`; botón en el log del staff. | Cambio 3, QA-03 | 3.2, 3.9, 3.10, 2 |
| 2 | `GET_LOCK` por objeto en las tres rutas y en la conciliación; se retira la afirmación sobre `LOCK TABLES`. | Cambio 4, QA-04 | 3.10, 4 |
| 2 | Guardado de `objetos_modificar.php`, `barco_unico.php` y retirada en funciones de librería; nueva librería `objetos_historial.php` con Q1 a Q5 (`hist_consultar`) y los pasos 5 a 15 (`hist_construir`); prueba de no fuga sobre su salida. | Cambio 5, QA-05 | 1, 2, 4, 6.3, 12 |
| 2 | Regla del mercado negro con ficha aprobada y personaje no muerto; Q5 lee `muerto` y `aprobada_por`. | Cambio 6, QA-06 | 0 (H-9), 6.1, 7.1, 12.2 |
| 2 | `tests/objetos/bootstrap_db.php` propio con base propia, MyISAM, `sql_mode` y juego de caracteres medidos; nombre de dobles puros distinto de `DbShim`. | Cambio 7, QA-07, nota de la sección 9 del QA | 2, 12.4 |
| 2 | Pertenencia por id interno con Q4 unida a `mybb_op_objetos`; `custom` leído de la base; clave normalizada sin mayúsculas. | Cambio 8, QA-08, QA-09, QA-26 | 3.11, 6.1, 7.5 |
| 2 | Calibración bloqueante en dos puertas (G-1 antes de I-1 e I-2; G-2 antes de I-9 e I-7) con `SHOW CREATE TABLE`, `errortypemedium`, duplicados sin mayúsculas, `pconnect`, versión de PHP y zona horaria. | Cambio 9, QA-08, QA-15, QA-16 | 13.3, 13.4, 14 |
| 2 | Denegación por defecto para campos desconocidos. | QA-10 | 1, 7.4 |
| 2 | Excepción de dueño solo con id exacto; decisión N-1. | QA-11 | 7.5, 17 |
| 2 | Mapa de alias con listas y resolución por fecha. | QA-12 | 5.2, 6.1, 7.5 |
| 2 | Filtros `subcategoria`, `tier` y `autor` validados por forma. | QA-13 | 8, 16 |
| 2 | Enlace permanente `?cambio=N`. | QA-14 | 8, 16 |
| 2 | Consultas con `hide_errors` y estado de error sin datos. | QA-15 | 0 (H-13), 6.1, 9.7 |
| 2 | Índice de `objeto_id` condicionado a la calibración de duplicados. | QA-16 | 6.2 |
| 2 | Índice `log(48)`; Q2 lee 1.200 caracteres y no se ejecuta con `periodo=todo`; medida del tamaño del log. | QA-17 | 6.1, 6.2, 10 |
| 2 | Antiguos con varias filas: la más restrictiva. | QA-18 | 5.3 |
| 2 | Tipos y contenido para staff y dueño. | QA-19 | 7.3 |
| 2 | Retirada `'BORR' . objeto_id` con rechazos de retirada repetida y destino existente. | QA-20 | 4.3 |
| 2 | Rechazo de alta duplicada en `objetos_crear.php` con D-13 B o C. | QA-21 | 4.3 |
| 2 | Sin `stripslashes` en las altas antiguas. | QA-22 | 5.1 |
| 2 | Escalera de tamaño con campos cortos. | QA-23 | 3.7 |
| 2 | Día y fecha con la misma función y reloj inyectado. | QA-24 | 6.3 |
| 2 | `try/catch` en el script del ancla. | QA-25 | 2, 11.1 |
| 2 | Autor de la columna `username`; inconsistentes solo para el staff. | QA-27 | 3.2, 9.8 |
| 2 | Decisión N-2 sobre `CFF010%`. | QA-28 | 7.2, 17 |
| 2 | Aviso del tope solo para el staff. | QA-29 | 6.1 |
| 2 | Lectura de `crafteo_usuarios` como `crafteo.php`. | QA-30 | 0 (H-14), 3.4, 7.4 |
| 2 | Hallazgo de `barco_unico.php` para el PM. | QA-31 | 16 |
| 2 | Casos límite de la sección 8 del QA añadidos a las pruebas; T-CONS y T-CONC nuevas. | QA sección 8 y 9 | 12 |
| 2 | Trazabilidad, cambios a 112-R (puntos 26 a 32), decisiones N-1 a N-3, contradicciones C-16 a C-23. | Revisión QA | 15 a 18 |
| 1 | Primera versión. | | Todas |
