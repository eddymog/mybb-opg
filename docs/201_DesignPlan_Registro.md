# Registro de aventuras (Tablón de aventuras): plan de diseño

## Diseño compacto de Narradores & Narrados (2026-10-08)

Estado: **implementado y verificado localmente; pendiente de despliegue**.

La revisión es de presentación y consulta agregada; no requiere una tabla nueva ni
modifica el modelo de propuestas u ofertas.

- El controlador calcula `propuestas_pendientes_usuario` por destinatario y estado.
  Ese total alimenta la insignia de la pestaña y reemplaza el conteo anterior de
  aventuras sin narrador. Con cero, la vista omite tanto insignia como sección.
- Los conteos de ofertas vigentes y solicitudes en estado 4 se conservan por
  separado y se inyectan en las cabeceras de sus secciones.
- `opg_av_v_mi_oferta` genera una franja de gestión; el listado ajeno continúa
  excluyendo al usuario conectado y se renderiza en una cuadrícula adaptable.
- La fila de aventura sin narrador recibe una variante propia y horizontal. No se
  reutiliza la tarjeta expandida de Mis aventuras. El nombre de respaldo pasa de
  `Sin tema vinculado` a `Solicitud #ID`.
- Los datos incompletos continúan disponibles para la gestión, pero el mercado no
  los muestra ni los usa para bloquear candidaturas.
- La respuesta HTMX mantiene los mismos IDs estables y eventos `ofertaCambiada` y
  `aventuraCambiada`, por lo que los conteos y bloques se actualizan sin recargar.
- No se incorporan parámetros de búsqueda o filtros en esta iteración.

La prueba visual debe cubrir 375, 700, 1030 y 1440 px, cero y varias propuestas,
una oferta propia larga, 1/3/20 narradores y aventuras con datos completos e
incompletos.

## Diseño de crónicas autonarradas (2026-10-08)

Estado: **implementado en código; pendiente de aplicar la migración en producción**.

Migraciones: `docs/aventuras_autonarradas_migration.sql` y, después,
`docs/aventuras_autonarradas_entrega_migration.sql`. No realizan backfill
automático: el informe comentado debe revisarse con staff antes de clasificar
registros históricos.

### Modelo persistente

La solicitud conserva los estados comunes, pero deja de inferir su clase por
narrador, jugadores o tier:

```sql
ALTER TABLE mybb_op_peticionAventuras
  ADD COLUMN tipo ENUM('normal','autonarrada') NOT NULL DEFAULT 'normal',
  ADD INDEX idx_tipo_estado (tipo, estado);
```

Los datos exclusivos viven en una relación uno a uno:

```sql
CREATE TABLE mybb_op_aventuras_autonarradas (
  peticion_id INT UNSIGNED NOT NULL,
  fid INT UNSIGNED NOT NULL,
  semana_inicio INT UNSIGNED NOT NULL,
  nivel_solicitud SMALLINT UNSIGNED NOT NULL,
  tid INT UNSIGNED NULL,
  publicada_at INT UNSIGNED NULL,
  nivel_publicacion SMALLINT UNSIGNED NULL,
  palabras INT UNSIGNED NULL,
  reputacion ENUM('positiva','negativa') NULL,
  entregada_at INT UNSIGNED NULL,
  estado_pago ENUM('pendiente','aplicando','aplicado','dudoso')
    NOT NULL DEFAULT 'pendiente',
  xp_aplicada TINYINT(1) NOT NULL DEFAULT 0,
  nikas_aplicadas TINYINT(1) NOT NULL DEFAULT 0,
  reputacion_aplicada TINYINT(1) NOT NULL DEFAULT 0,
  berries_aplicados TINYINT(1) NOT NULL DEFAULT 0,
  pagada_por INT UNSIGNED NULL,
  pagada_at INT UNSIGNED NULL,
  PRIMARY KEY (peticion_id),
  UNIQUE KEY uq_auto_fid_semana (fid, semana_inicio),
  UNIQUE KEY uq_auto_tid (tid),
  KEY idx_auto_pago (estado_pago, publicada_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`semana_inicio` es el timestamp UTC del lunes a las 06:00 calculado mediante
`DateTimeZone('Europe/Madrid')`. Se obtiene de `created_at` y nunca se recalcula
por aprobación, publicación o pago.

Los cuatro indicadores de aplicación permiten recuperar un pago interrumpido sin
repetir componentes sobre tablas que no garanticen un rollback transaccional.

### Migración de datos anteriores

1. Generar primero un informe de candidatas con la heurística antigua: un
   participante, ese participante igual a `narrador_fid` y tier 1 a 3.
2. Revisar la lista con staff para evitar falsos positivos.
3. Marcar solo las confirmadas con `tipo='autonarrada'` y crear su fila relacionada.
4. No inventar semana, nivel o pago si la fuente histórica no permite conocerlos;
   admitir valores migrados documentados o una marca de legado durante el backfill.
5. Sustituir la heurística en `aventuras_personaje.php` y su versión de staff por
   `tipo='autonarrada'`.

### Dominio

- `opg_av_semana_madrid($timestamp)` devuelve el inicio de semana vigente. Debe
  cubrir horario estándar, horario de verano y exactamente lunes 05:59/06:00.
- `opg_av_autonarrada_disponibilidad($fid)` devuelve nivel, semana, solicitud de
  esa semana y solicitud aún no publicada.
- Crear bloquea con `SELECT ... FOR UPDATE` la ficha y la fila semanal antes de
  insertar, de modo que dos solicitudes simultáneas no ganen el mismo cupo.
- La validación fuerza un participante, narrador igual a `fid`, estado inicial 0,
  `tipo=autonarrada`, sin tier funcional, sin inframundo y sin propuesta.
- Por compatibilidad con columnas antiguas no nulas, el almacenamiento puede usar
  un valor técnico neutral en `tier_seleccionado`; ninguna regla, vista o pago lo
  interpreta como tier de autonarrada.

### Publicación

- El buscador de aventuras de `newthread.php` incluye autonarradas aprobadas,
  sin `tid`, propiedad del usuario y con nivel actual menor o igual a 15.
- La acción de vínculo repite esas condiciones en servidor y valida foro, autor
  y tema no usado. Ocultar la opción no constituye seguridad.
- Al vincular se toma `dateline` del primer post, nivel actual y conteo preliminar
  de palabras. El cupo continúa anclado a `semana_inicio`.
- La validación final exige un único post visible, autor igual a `fid`, al menos
  800 palabras Unicode tras retirar BBCode/HTML y nivel de publicación hasta 15.
- Subir a nivel 16 después de publicar no invalida una autonarrada ya publicada.

### Consultas y límites

- Los conteos normales excluyen `tipo='autonarrada'` de solitarias, grupales,
  jugador y narrador cuando esos valores representan cupos convencionales.
- Mis aventuras obtiene las autonarradas con una consulta propia y las muestra en
  su bloque separado.
- Estadísticas globales pueden incluir un contador Autonarradas únicamente cuando
  esté rotulado como tipo, nunca mezclado con `Como narrador`.
- Otras aventuras, modo vista y páginas históricas usan el tipo persistido.

### Consola y pago

- Solicitudes reutiliza aprobación, denegación, notas e historial, adaptando la
  tarjeta al tipo.
- El autor entrega la autonarrada desde Mis aventuras y elige reputación positiva
  o negativa. La fecha queda en `entregada_at`; vincular el tema no equivale a
  entregarlo.
- Entregas combina dos fuentes: entregas convencionales y autonarradas que ya
  tienen `entregada_at`. Un filtro `tipo` conserva la URL y la paginación.
- El análisis autonarrado es determinista: 30 XP, 2 Nikas, 5 reputación y 300.000
  Berries, sin bonus ni multiplicadores. La orientación de reputación la declara
  el autor y Staff la ve como dato de solo lectura.
- El pago bloquea la fila autonarrada, reclama `estado_pago`, aplica cada componente
  pendiente y marca su indicador. Al completar, audita, finaliza la solicitud y
  cierra el tema.
- Si se interrumpe, `estado_pago='dudoso'` muestra `Completar recompensa`; la
  reanudación omite los indicadores ya aplicados.
- `entregar_recompensas.php` no procesa POST. Sus GET redirigen a la consola y
  traducen `fid`/`tid` a filtros cuando sea posible.

### Seguridad y pruebas críticas

- Nivel, autor, posts, palabras, semana y estado se calculan en servidor.
- Restricciones únicas y bloqueo evitan dobles solicitudes y dobles pagos.
- Probar lunes 05:59 y 06:00 en invierno y verano, dos solicitudes concurrentes,
  nivel 15/16, 799/800 palabras, tema ajeno, dos posts, `tid` repetido, doble pago
  y fallo después de cada componente de recompensa.

## Consulta de activas por personaje (2026-10-08)

- Reutilizar `opg_av_activas_listar()` para conservar una única definición de
  aventura activa y de los papeles jugador/narrador.
- Parametrizar el resumen compacto para cambiar el título y desactivar sus
  refrescos HTMX en modo de solo lectura.
- Resolver el personaje exclusivamente desde fichas aprobadas. El typeahead se
  despliega desde un botón en la cabecera, acepta tres letras o un FID exacto y
  navega a una URL recargable.
- Renderizar Novedades solo si la pestaña activa es `mias`; los cambios HTMX de
  pestaña actualizan su slot fuera de banda.
- En `modo_vista`, sustituir el panel normal y dejar vacíos `tabs_html` y
  `novedades_html`, sin exponer acciones de edición.
- Construir el enlace al tema con el `tid` validado y el nombre filtrado por
  visibilidad. Sin ambos datos, renderizar texto plano.

## Revisión UX y relacional (2026-10-07)

Esta revisión está **implementada y pendiente de despliegue**. Prevalece
sobre los apartados posteriores que describen `jugadores_json` como única fuente,
la asignación unilateral de narrador, estadísticas accionables o filtros sin URL.

### Nuevas tablas

#### `op_aventuras_participantes`

```sql
CREATE TABLE `{prefix}op_aventuras_participantes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `peticion_id` INT UNSIGNED NOT NULL,
  `uid` INT UNSIGNED NULL,
  `nombre_snapshot` VARCHAR(255) NOT NULL DEFAULT '',
  `orden` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_peticion_uid` (`peticion_id`, `uid`),
  UNIQUE KEY `uq_peticion_orden` (`peticion_id`, `orden`),
  KEY `idx_uid_peticion` (`uid`, `peticion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

No se añaden claves foráneas porque las tablas MyBB relacionadas pueden usar
motores distintos. El nombre es un snapshot histórico; los enlaces usan `uid`.
MySQL permite varias filas con `NULL` en una clave única, por lo que participantes
legacy sin UID no se pierden; `orden` los distingue dentro de una petición.

#### `op_aventuras_propuestas_narrador`

```sql
CREATE TABLE `{prefix}op_aventuras_propuestas_narrador` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `peticion_id` INT UNSIGNED NOT NULL,
  `narrador_uid` INT UNSIGNED NOT NULL,
  `iniciada_por_uid` INT UNSIGNED NOT NULL,
  `tipo` ENUM('invitacion','candidatura') NOT NULL,
  `estado` ENUM('pendiente','aceptada','rechazada','retirada','cancelada','vencida') NOT NULL,
  `mensaje` VARCHAR(500) NOT NULL DEFAULT '',
  `created_at` INT UNSIGNED NOT NULL,
  `expires_at` INT UNSIGNED NOT NULL,
  `resolved_at` INT UNSIGNED NULL,
  `resolved_by_uid` INT UNSIGNED NULL,
  `pendiente_narrador_uid` INT UNSIGNED GENERATED ALWAYS AS
    (CASE WHEN `estado` = 'pendiente' THEN `narrador_uid` ELSE NULL END) STORED,
  PRIMARY KEY (`id`),
  KEY `idx_peticion_estado` (`peticion_id`, `estado`),
  KEY `idx_narrador_estado` (`narrador_uid`, `estado`),
  UNIQUE KEY `uq_pendiente_par` (`peticion_id`, `pendiente_narrador_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

La columna generada aprovecha que una clave única admite múltiples `NULL`: solo
una propuesta pendiente por pareja, pero cualquier cantidad de filas históricas
resueltas. MySQL 5.7 admite la columna generada almacenada. El dominio también
bloquea la solicitud y comprueba el duplicado dentro de la transacción.

#### `op_aventuras_notificaciones`

```sql
CREATE TABLE `{prefix}op_aventuras_notificaciones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave_evento` VARCHAR(96) NOT NULL,
  `peticion_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `uid` INT UNSIGNED NOT NULL,
  `actor_uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `tipo` VARCHAR(40) NOT NULL,
  `datos_json` TEXT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  `read_at` INT UNSIGNED NULL,
  `sent_at` INT UNSIGNED NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evento_destinatario` (`clave_evento`, `uid`),
  KEY `idx_uid_read` (`uid`, `read_at`, `created_at`),
  KEY `idx_envio` (`sent_at`, `attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

La fila es a la vez bandeja interna y outbox idempotente. `sent_at` representa
el canal MyAlerts/MP opcional; `read_at` representa la lectura dentro del tablón.

### Migración de participantes

1. Crear la tabla sin cambiar lecturas.
2. Recorrer solicitudes por lotes, decodificar `jugadores_json` válido e insertar
   una fila por elemento, conservando el orden. UID ausente se guarda como NULL.
3. Registrar IDs con JSON inválido, estructura no reconocida o UID positivo
   repetido. No borrarlos ni inferir una ficha.
4. Comparar por solicitud cantidad y conjunto de UIDs entre JSON y relación.
5. Activar lectura preferente relacional con fallback a JSON solo para IDs
   reportados como no migrados.
6. Activar escritura dual dentro de la transacción de crear/editar.
7. Añadir a las rutas legacy que todavía cambian `jugadores_json` una llamada
   posterior a `opg_av_sincronizar_participantes($peticion_id)`. Un task repara
   filas ausentes como segunda red de seguridad.
8. Tras desactivar la escritura de la calculadora antigua, ejecutar un último
   backfill y checksum. Solo entonces convertir la relación en fuente de verdad.
   `jugadores_json` permanece como snapshot compatible hasta una retirada posterior.

`opg_av_sql_participa()` deja de generar `JSON_CONTAINS` y pasa a `EXISTS` sobre
la relación. Mis/Otras, estadísticas, límites, permisos y conflictos de interés
usan la misma expresión relacional e índices.

### Consultas de estadísticas y navegación

- `opg_av_condiciones_listado` separa dimensiones: vista, estado, papel,
  búsqueda y ámbito.
- `opg_av_estadisticas` recibe las mismas condiciones validadas que el listado.
  Los conteos respetan la vista, búsqueda, estado y papel seleccionados. La vista
  determina qué estados se calculan y renderizan.
- `vista=activas|historial|todas` se valida en servidor y se incorpora a todos los
  enlaces y formularios.
- Los filtros HTMX usan `hx-push-url` con una URL canónica. La página completa y
  el fragmento producen el mismo resultado para los mismos parámetros.
- El paginador recibe una ventana de hasta cinco páginas y conserva todos los
  parámetros. Para ofertas de narrador se añade `pagina_narradores`; para cargas
  sin narrador, `pagina_sin_narrador`.
- El cambio de resultados devuelve un encabezado enfocable con `tabindex="-1"` y
  una señal para desplazar solo el contenedor del listado. Si HTMX no está
  disponible, los controles siguen siendo enlaces GET completos.

### Cupos anticipados

Se añade una consulta en lote `opg_av_cupos_participantes($uids, $candidato)` que
devuelve solitarias, totales, máximo y motivo de bloqueo por UID. No se ejecuta
una consulta por chip.

La acción GET `validar_cupos` recibe papel, participantes y si la aventura es
solitaria/grupal. Devuelve:

- chips o metadatos de cupo;
- aviso general de elegibilidad;
- botón de envío habilitado o deshabilitado;
- atributos OOB para actualizar las tres zonas sin reconstruir el formulario.

Cambiar rol o participantes dispara esta validación. `opg_av_crear` conserva el
bloqueo `FOR UPDATE` y vuelve a validar antes del INSERT.

### Propuestas bilaterales

Nuevas operaciones de dominio:

- `opg_av_propuesta_crear_candidatura($peticion_id,$narrador_uid,$mensaje)`;
- `opg_av_propuesta_crear_invitacion($peticion_id,$creador_uid,$narrador_uid,$mensaje)`;
- `opg_av_propuesta_aceptar($id,$uid)`;
- `opg_av_propuesta_rechazar($id,$uid)`;
- `opg_av_propuesta_retirar($id,$uid)`;
- `opg_av_propuestas_vencer($ahora)`.

Una candidatura solo la acepta/rechaza el creador. Una invitación solo la
acepta/rechaza el narrador invitado. Retirar corresponde a quien la inició.
Aceptar inicia transacción, bloquea propuesta y solicitud, verifica estado 4 y
ausencia de narrador, asigna UID/ficha/nombre, cambia a estado 0, acepta una y
cancela las demás. Luego audita y encola notificaciones. Dos aceptaciones
simultáneas no pueden asignar narradores distintos.

Crear como narrador conserva la asignación inmediata propia. Crear como jugador
con narrador crea solicitud estado 4 + invitación en la misma transacción.
`postular_narrador` se reemplaza por candidatura; se mantiene temporalmente como
alias incompatible solo durante la migración de rutas, sin asignar directamente.

### Notificaciones fiables

Las operaciones de dominio agregan filas a la outbox dentro de su transacción.
El canal garantizado en esta entrega es la bandeja interna del tablón:

1. Bandeja del tablón siempre disponible.
2. MyAlerts puede añadirse cuando el plugin y su tipo estén confirmados en
   producción.
3. Mensaje privado queda como adaptador opcional; no se activa sin configuración
   para evitar duplicar avisos a los usuarios.

Un task periódico procesa vencimientos, backfill y avisos de oferta. El fallo de
un canal externo futuro no revertirá una propuesta, transición ni pago.
`clave_evento + uid` evita duplicados al reintentar peticiones o completar pagos.

### Historial seguro

`opg_av_historial_usuario($peticion_id,$uid)` exige que el UID sea creador,
narrador actual/anterior auditado o participante. Mapea acciones de auditoría a
un DTO permitido; nunca devuelve `detalle_json` crudo. Las vistas reciben
etiqueta, fecha, actor visible y enlace, sin notas privadas.

### Cambios de renderizado

- `opg_av_v_tarjeta` recibe el nombre de tema en la consulta base o en un lote,
  y genera enlaces de perfil con `member.php?action=profile&uid=N`.
- La visibilidad de idea, narrador ausente y faltantes depende de estado/contexto.
- Los estados conservan sus claves CSS, pero usan las etiquetas de jugador de
  los requisitos.
- `opg_av_v_estadisticas` produce indicadores estáticos; no genera controles GET,
  estados `aria-pressed` ni efectos de selección.
- `opg_av_v_paginador` sirve listas y narradores, con ventana numérica.
- Nuevos fragmentos: propuestas recibidas/enviadas, panel persistente de
  notificaciones e historial. Marcar una notificación reemplaza únicamente el
  panel de novedades mediante HTMX.

### Compatibilidad

- Solicitudes existentes con narrador se consideran aceptadas de hecho.
- No se fabrican propuestas históricas para ellas.
- Las URLs antiguas sin `vista` abren el valor predeterminado de cada pestaña.
- Los enlaces `?id=N` localizan la página correcta aunque la vista predeterminada
  no contenga el estado; el router cambia a la vista adecuada y conserva `id`.
- La consola de staff continúa usando los códigos 0..5 y recibe participantes
  desde la relación normalizada sin cambiar sus permisos.
- La calculadora antigua conserva su interfaz, pero sus escrituras de
  participantes llaman al sincronizador relacional. Si ese puente falla, la fila
  sigue disponible por JSON y queda marcada para el task de reparación.

### Archivos adicionales previstos

- `docs/aventuras_ux_migration.sql`: las tres tablas nuevas y sus índices.
- `inc/plugins/lib/aventuras.php`: outbox, bandeja y adaptadores internos.
- `inc/tasks/aventurasux.php`: backfill, vencimientos y avisos periódicos.
- `inc/plugins/aventuras_alerts.php`: registro opcional del formatter MyAlerts.
- `tests/aventuras/migracion_participantes.php`: backfill, paridad y reentrada.

## Revisión visual (2026-10-06)

El rediseño de lectura y operación se implementa siguiendo, en orden,
[102_TablonDiseno.md](102_TablonDiseno.md): tarjetas y metadatos compactos,
jerarquía de acciones, cabecera coherente con el foro, ofertas comparables,
contador integrado, colores semánticos y selector de densidad.

La vista concentra el cambio en `opg_av_v_tarjeta` y en el template del tablón.
Los comentarios públicos usan un checkbox accesible para expandirse sin estado de
servidor; el selector de densidad también es CSS y permanece fuera del fragmento
HTMX que refresca el listado. La clase `ta-pagina-tablon` identifica la página,
pero no oculta elementos ni añade espacio a la cabecera compartida.

Las ofertas añaden `frecuencia`, `tiers` y `estilo` mediante una migración
incremental. El dominio normaliza y valida los tiers 1 a 10 y acepta valores
vacíos para filas antiguas. El orden de despliegue es: migración, PHP y nueva
importación de `op_tablon_de_aventuras`.

## Revisión de interfaz (2026-10-05)

El desglose de activas separa las del personaje (solitarias / 3 y total / 4)
de las narradas (sin límite). `opg_av_activas_listar` recupera ambos roles y
marca `es_narrador`; la vista los presenta en secciones distintas. El cupo y
su validación siguen contando exclusivamente aventuras como jugador.

Esta revisión prevalece sobre las referencias posteriores al mercado en Otras
y a tres pestañas. Orden: `mias`, `otras`, `solicitud`, **`narradores`**
(Narradores & Narrados). La nueva pestaña contiene las ofertas y solicitudes sin
narrador; su contador conserva `contar_sin_narrador`. `?id=N` ajeno en estado 4
abre esta pestaña y localiza la solicitud, aunque esté más allá de las primeras 10.

`opg_av_estadisticas($filtros)` agrega en SQL el total y estados 0..4 usando
exactamente `opg_av_condiciones_listado`, sin LIMIT. Conserva internamente los
conteos de narrador y jugador, pero `opg_av_v_estadisticas` solo los presenta en
Mis aventuras; Otras aventuras muestra exclusivamente total y estados.
`listar` actualiza esa banda fuera de banda y devuelve tarjetas y paginador.
En Mis, la banda separa seis contadores de estado a la izquierda y roles a la
derecha. En Otras, los seis estados ocupan todo el ancho. En móvil, estados se
distribuyen en 2 columnas y los roles de Mis forman una franja aparte debajo.
La paginación se mantiene en 20; las cifras nunca dependen de la página.

Los filtros incluyen `q`: en Mis acepta únicamente `#N` o `N` para una solicitud;
en Otras acepta ese número o texto de ficha, que se resuelve contra fichas aprobadas
y filtra aventuras donde esas fichas participan o narran. La entrada dispara el
listado tras 250 ms, y el mismo filtro alimenta estadísticas y paginación.

`opg_av_activas_listar($uid)` devuelve las solicitudes como jugador en estados
0, 1 y 4, excluyendo `narrador_fid=uid`. Mis activas separa solitarias y grupales,
con título del hilo vinculado, fecha de registro y de rol (si existe), estado y
enlace, y muestra límites 3/4. Los títulos se consultan en lote en `threads`,
excluyendo temas no visibles y foros inaccesibles. Se refresca al crear o cambiar una
aventura, independientemente de los filtros. Solicitar conserva el indicador
compacto; la creación rechaza superar límites antes de insertar.

Selección progresiva sin JS: `form_participante` abre el buscador; seleccionar
devuelve el chip y reemplaza el buscador fuera de banda por Añadir participante.
El rol narrador sin participantes muestra el buscador. El slot de narrador
contiene buscador o chip, nunca ambos; `quitar_narrador` restaura el buscador.
El campo Día muestra `placeholder="1-90"`.
El buscador de isla va antes del rótulo y los chips de Isla para alinearse con Día
y Tier. Comentario público lleva `required` y validación de texto no vacío;
placeholder de descripción breve. La idea pide preferencias de narración.
Crear abre una transacción, bloquea fichas de participantes ordenadas por fid,
valida límites usando una solicitud candidata de id 0 y solo entonces inserta.
Un exceso devuelve 422 al aviso del formulario. Se conserva la comprobación al aprobar.
El slot de tema sigue el mismo patrón de selección única: buscador o chip,
nunca ambos, tanto al vincular como al entregar. `elegir_tema` devuelve solo
el chip y `quitar_tema` restaura el buscador, revalidando el permiso. Esta acción
solo cambia el formulario; el enlace guardado cambia al enviarlo.

El formulario de solicitud se presenta en tres bandas sin contenedores anidados:
Personas, Datos de la aventura y Descripción. Rol y disponibilidad de narrador
comparten una cuadrícula en escritorio; participantes queda debajo. Comentario
público precede a la propuesta condicional para narradores, controlada con
`:has()` a partir de los radios existentes, sin JavaScript adicional.

En Narradores & Narrados, `opg_av_narradores_disponibles` acepta un UID opcional
que se excluye de la consulta. La página lo usa para evitar que la oferta propia
se repita bajo su franja de gestión; las llamadas sin segundo argumento conservan
el listado completo. El directorio usa `auto-fit` para ocupar el ancho disponible
y las solicitudes sin narrador aplican la variante compacta de tarjeta.

`opg_av_puede_vincular($av,$uid)` exige estado 1 y ser narrador asignado o
participante, no basta ser creador. Se usa en tarjeta, formulario, typeahead y
escritura. El UPDATE revalida estado y rol; la entrega conserva su permiso propio.

Diseño: bandas de estadísticas y activas sin tarjetas contenedoras, filtros
compactos, títulos con raya de acento, tipografía y colores de `style.md`, foco
visible y lectura a 375/700/1030px. No se añade JS de aplicación.

> Depende de: [101_Requirements_Registro.md](101_Requirements_Registro.md)
> Sistema visual: [style.md](style.md)
>
> **Estado: implementado, pendiente de despliegue.** `op/Calculadora_Tiers.php`
> sigue funcionando sin cambios (salvo el arreglo de permisos de §10) hasta
> completar la migración y validación con staff.

## 1. Objetivo

Reemplazar la calculadora de tiers como registro de solicitudes de aventura por
dos páginas con una sola responsabilidad cada una:

- `/op/tablon_de_aventuras.php`: lo que usan los jugadores. Cuatro pestañas,
  en este orden: Mis aventuras, Solicitar aventura, Narradores & Narrados y
  Otras aventuras. Sin cálculo de dificultad y sin gestión.
- `/op/staff/consola_aventuras.php`: lo que usan mods, narradores y staff.
  Listado de gestión, cambio de estado, notas, isla y día, y borrado.

Es un rediseño completo. **No se reutiliza nada del HTML, CSS ni JS de la
calculadora actual** (`op_Calculadora_Tiers.html`,
`jscripts/calculadoraDificultad/`): los templates y estilos se escriben de cero
con `style.md`, y la interacción se hace con **HTMX**. El servidor responde con
fragmentos HTML y el navegador los inserta donde corresponde. No hay JavaScript
propio de aplicación.

Las dos páginas comparten una biblioteca de dominio y la misma tabla
`mybb_op_peticionAventuras`, por lo que ninguna solicitud existente pierde su
número (requisitos §4.2).

## 2. Archivos

| Archivo | Responsabilidad | Estado |
|---|---|---|
| `inc/plugins/lib/aventuras.php` | Constantes, estados y transiciones, límites, validación, escritura, auditoría y lectura normalizada. Única fuente de reglas. | Nuevo |
| `inc/plugins/lib/aventuras_vistas.php` | Renderers de fragmentos HTML (tarjeta, lista, fila de consola, chips, avisos). Todo el texto se escapa aquí. | Nuevo |
| `op/tablon_de_aventuras.php` | Página de jugadores y sus acciones HTMX. | Nuevo |
| `op/staff/consola_aventuras.php` | Consola de gestión y sus acciones HTMX. | Nuevo |
| `op/staff/consola_mod.php` | Agrega la tarjeta "Aventuras" a `$consola_grupos`. | Editado |
| `templates/One_Piece_Gaiden_Templates/op_tablon_de_aventuras.html` | Marco de la página, pestañas y formulario de solicitud. | Nuevo |
| `templates/One_Piece_Gaiden_Templates/staff_consola_aventuras.html` | Marco de la consola y contenedor del listado. | Nuevo |
| `inc/plugins/lib/recompensas_aventura.php` | Análisis de un tema (quién narró, posts válidos), porcentajes, recompensas, tope mensual, mensaje y cierre del tema. Reproduce en el servidor la lógica de `op/staff/recompensasAventuras.php`. | Nuevo |
| `inc/plugins/lib/aventuras_entregas.php` | Entrega de aventura: temas elegibles, envío, análisis, pago único, rechazo y reanudación. | Nuevo |
| `op/staff/aventuras_recompensas.php` | Página nueva y rediseñada de pago de recompensas de una entrega: análisis del tema, ajustes, previsualización, pago, rechazo y reanudación. Sigue la lógica de `recompensasAventuras.php`. | Nuevo |
| `templates/One_Piece_Gaiden_Templates/staff_aventuras_recompensas.html` | Marco y contenedores de `aventuras_recompensas.php`. | Nuevo |
| `op/staff/recompensasAventuras.php` | **No cambia** (tope mensual de 675 incluido). Sigue funcionando mientras conviva con la calculadora y se retira con ella. | Sin cambios |
| `jscripts/htmx/htmx.min.js` | Librería HTMX 2.x, versión fija, servida desde el sitio. | Nuevo |
| `docs/aventuras_migration.sql` | Columnas, índices, defaults y tabla de auditoría. | Nuevo |
| `op/Calculadora_Tiers.php` | Redirige `?peticion=N` a la página nueva al retirarla. | Editado al final |

No hay archivos JS de aplicación: los comportamientos (typeaheads, filtros,
paginación, acciones por tarjeta, confirmaciones, contadores) se declaran con
atributos `hx-*` en el HTML que genera el servidor. Los fragmentos nunca
contienen `<script>`.

Los templates de MyBB solo contienen el marco de cada página. Los fragmentos
los generan las funciones de `aventuras_vistas.php`, como ya hace el periódico
con `opg_periodico_render_bloque()`: es PHP que construye HTML con escape
estricto, no templates sueltos con `eval()`, así que no hay riesgo de `$` en los
fragmentos ni hace falta importar decenas de templates a la base.

Los CSS van en un `<style>` dentro de cada template, con el orden de
`style.md` §7 (marco, componentes compartidos ajustados, específico de la
herramienta, `@media`). Prefijos: `ta-` para `op_tablon_de_aventuras` y `ca-`
para `staff_consola_aventuras`; deben agregarse a la tabla de prefijos de
`style.md`.

## 3. Datos y migración SQL

Todo el SQL vive en `docs/aventuras_migration.sql`. Antes de aplicarlo se
respaldan `mybb_op_peticionAventuras` y `mybb_op_peticionAventuras_meta`, y se
guardan el `COUNT(*)` y un checksum de la lista de `id` para comprobarlos
después. Antes de agregar los índices se ejecuta `SHOW INDEX` en la tabla para
no duplicar los que ya existan.

### 3.1 Columnas nuevas

```sql
ALTER TABLE mybb_op_peticionAventuras
  ADD COLUMN isla_fid SMALLINT UNSIGNED NULL AFTER narrador_nombre,
  ADD COLUMN dia TINYINT UNSIGNED NULL AFTER isla_fid,
  ADD COLUMN motivo_denegacion VARCHAR(500) NULL AFTER comentario_staff,
  ADD COLUMN idea_narrador VARCHAR(500) NULL AFTER motivo_denegacion,
  ADD COLUMN motivo_renuncia VARCHAR(500) NULL AFTER idea_narrador,
  ADD COLUMN editado_at INT UNSIGNED NULL AFTER motivo_renuncia;
```

`isla_fid` es el `fid` de `mybb_forums`; `dia` va de 1 a 90 y lo valida el
servidor, no una restricción de base de datos. `idea_narrador` es la nota con la
que el creador "vende su idea" cuando la solicitud no tiene narrador, y
`motivo_renuncia` es el motivo que escribe el narrador al salirse de la
aventura. `editado_at` es la fecha (Unix) de la última edición hecha por el
creador y solo se escribe al editar.

### 3.2 Columnas existentes que el insert nuevo no llena

Estas columnas son `NOT NULL` sin valor por defecto en el dump. Para que un
insert de la versión nueva no tenga que escribirlas, se les da un valor por
defecto o se permite `NULL`. Las filas existentes no cambian. Las columnas
`TEXT` no admiten `DEFAULT` en todas las versiones de MySQL/MariaDB, por eso
esas pasan a nulas:

```sql
ALTER TABLE mybb_op_peticionAventuras
  MODIFY tier_seleccionado TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- el tier SÍ se usa (1 a 10)
  MODIFY dificultad_texto VARCHAR(20) NOT NULL DEFAULT '',
  MODIFY dificultad_color VARCHAR(7) NOT NULL DEFAULT '',
  MODIFY descripcion_tier TEXT NULL,
  MODIFY enemigos_json MEDIUMTEXT NULL,
  MODIFY inframundo TINYINT(1) NOT NULL DEFAULT 0;
```

`nivel_promedio`, `num_jugadores` y `ratio_poder` ya tienen valor por defecto.
La calculadora actual sigue insertando todas estas columnas, por lo que no se
rompe. Su lectura ya usa `?? '[]'` para los JSON, así que tolera `NULL`.

`num_jugadores` se mantiene actualizado por la versión nueva con la cantidad de
participantes, porque otros módulos podrían leerla.

### 3.3 Índices

> **Transición:** el bloque siguiente documentaba la primera versión basada en
> JSON. La revisión 2026-10-07 añade los índices de
> `op_aventuras_participantes` y sustituye estas lecturas después del backfill.

```sql
ALTER TABLE mybb_op_peticionAventuras
  ADD INDEX idx_estado (estado),
  ADD INDEX idx_narrador_uid (narrador_uid),
  ADD INDEX idx_isla_fid (isla_fid),
  ADD INDEX idx_created_at (created_at);
```

Durante el backfill, `JSON_CONTAINS` solo se conserva como fallback de filas no
migradas. Las escrituras de la calculadora antigua sincronizan la relación y un
task repara divergencias. Después del checksum final, el filtro por jugador usa
el índice `(uid, peticion_id)` de `op_aventuras_participantes`.

### 3.4 Auditoría

```sql
CREATE TABLE mybb_op_audit_aventuras (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  peticion_id INT UNSIGNED NOT NULL,
  uid INT UNSIGNED NOT NULL,
  accion VARCHAR(32) NOT NULL,
  detalle_json MEDIUMTEXT NULL,
  fecha INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_peticion (peticion_id),
  KEY idx_uid_fecha (uid, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

La tabla `mybb_op_peticionAventuras_meta` ya existe en producción. El archivo de
migración la crea solo si falta (`CREATE TABLE IF NOT EXISTS`, con la
estructura que hoy crea el runtime), y esa creación se elimina del código de
las peticiones.

### 3.5 Narradores disponibles

```sql
CREATE TABLE mybb_op_narradores_disponibles (
  uid INT UNSIGNED NOT NULL,
  discord VARCHAR(64) NOT NULL,
  mensaje VARCHAR(200) NOT NULL DEFAULT '',
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  PRIMARY KEY (uid),
  KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Una fila por usuario: la presencia de la fila es la oferta. `created_at` es
cuándo se ofreció por primera vez y `updated_at` cuándo la renovó o cambió el
mensaje por última vez; la oferta es vigente mientras
`updated_at >= ahora - 30 días`. La tabla es nueva, así que no afecta a ningún
dato existente ni a la calculadora actual.

### 3.6 Entregas y pagos

```sql
CREATE TABLE mybb_op_aventuras_entregas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  peticion_id INT UNSIGNED NOT NULL,
  tid INT UNSIGNED NOT NULL,
  tid_activo INT UNSIGNED NULL,
  tema_nombre VARCHAR(255) NOT NULL,
  resumen TEXT NOT NULL,
  reputacion ENUM('positiva','negativa') NOT NULL,
  estado ENUM('pendiente','rechazada','entregada') NOT NULL DEFAULT 'pendiente',
  tier TINYINT UNSIGNED NULL,
  infra TINYINT(1) NULL,
  ajustes_json MEDIUMTEXT NULL,
  intentos SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  narrador_uid INT UNSIGNED NOT NULL DEFAULT 0,
  enviado_por INT UNSIGNED NOT NULL,
  enviado_at INT UNSIGNED NOT NULL,
  revisado_por INT UNSIGNED NULL,
  revisado_at INT UNSIGNED NULL,
  motivo_rechazo VARCHAR(500) NULL,
  procesando_por INT UNSIGNED NULL,
  procesando_at INT UNSIGNED NULL,
  entregado_at INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_peticion (peticion_id),
  UNIQUE KEY uq_tid_activo (tid_activo),
  KEY idx_estado (estado, enviado_at),
  KEY idx_narrador_uid (narrador_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mybb_op_aventuras_entregas_pagos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entrega_id INT UNSIGNED NOT NULL,
  uid INT UNSIGNED NOT NULL,
  rol ENUM('jugador','narrador') NOT NULL,
  porcentaje DECIMAL(6,2) NOT NULL,
  detalle_json MEDIUMTEXT NULL,
  aplicado_at INT UNSIGNED NULL,
  xp_cantidad INT UNSIGNED NOT NULL DEFAULT 0,
  xp_estado ENUM('pendiente','aplicando','aplicado','dudoso') NOT NULL DEFAULT 'pendiente',
  PRIMARY KEY (id),
  UNIQUE KEY uq_entrega_uid (entrega_id, uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Una sola entrega por solicitud (`uq_peticion`). `tid_activo` vale el `tid` mientras
la entrega está `pendiente` o `entregada` y `NULL` cuando está `rechazada`; como
los `NULL` no chocan en un índice único, `uq_tid_activo` impide que un mismo
tema esté en dos entregas vigentes sin bloquear al tema de una entrega
rechazada. `tier` guarda el tier con el que se pagó (por defecto el de la
solicitud) y `ajustes_json` los ajustes del staff (§4.11), congelados en el primer
intento de pago para que reanudar no cambie montos. El `UNIQUE (entrega_id, uid)`
de los pagos garantiza que cada destinatario cobre una sola vez. Las dos tablas
son nuevas.

## 4. Bibliotecas

### 4.1 `inc/plugins/lib/aventuras.php`: dominio

Concentra todas las reglas para que la página de jugadores y la consola no las
dupliquen. Ningún archivo de página escribe SQL de negocio.

Constantes:

```php
const OPG_AV_ANO = 725;
const OPG_AV_ESTACION = 'Verano';
const OPG_AV_DIAS = 90;              // igual a CRON_DIAS en op/cronologia.php
const OPG_AV_ISLA_PADRES = [15, 16, 17, 18];
const OPG_AV_MAX_COMENTARIO = 1000;
const OPG_AV_MAX_NOTA_STAFF = 200;
const OPG_AV_MAX_MOTIVO = 500;
const OPG_AV_LIMITES_ACTIVOS = false; // false: sin límite de aventuras activas (octubre de 2026); true: aplica los dos siguientes
const OPG_AV_MAX_SOLITARIAS = 3;
const OPG_AV_MAX_TOTAL = 4;
const OPG_AV_ESTADOS_ACTIVOS = [0, 1, 4];
const OPG_AV_POR_PAGINA = 20;
const OPG_AV_MAX_MENSAJE_OFERTA = 200;
const OPG_AV_MAX_DISCORD = 64;
const OPG_AV_MAX_IDEA = 500;
const OPG_AV_MAX_MOTIVO_OFERTA = 200;  // motivo al retirar una oferta (consola)
const OPG_AV_ESTADOS_EDITABLES = [0, 4];
const OPG_AV_OFERTA_DIAS = 30;       // vigencia de una oferta de narrador
const OPG_AV_HOSTS_EXTRA = [];       // hosts de URL aceptados además del foro
```

`op/cronologia.php` no se modifica: ya define `CRON_DIAS = 90` y
`CRON_ANO_DEFECTO = 725`. Una prueba de entrega (§11) compara ambos valores
para que no diverjan. Cuando el calendario deje de ser "verano de 725" el
cambio es de constantes.

Funciones:

```php
// Permisos
opg_av_puede_gestionar($uid)      // is_mod || is_staff || is_narra
opg_av_puede_eliminar($uid)       // is_staff
opg_av_puede_resolver($av, $uid)  // cualquier usuario con permiso de gestión
opg_av_puede_editar($av, $uid)    // creador y estado en OPG_AV_ESTADOS_EDITABLES

// Estados y transiciones
opg_av_estados()                  // catálogo 0..5 (clave, etiqueta)
opg_av_transicion_valida($de, $a)
opg_av_cambiar_estado($id, $a, $uid, $motivo)
opg_av_pedir_borrado($id, $uid)
opg_av_eliminar($id, $uid)

// Creación y validación
opg_av_validar_solicitud($entrada, $uid)    // devuelve [datos, errores]
opg_av_crear($datos, $uid)
opg_av_postular_narrador($id, $uid, $confirmo)  // $confirmo debe ser verdadero
opg_av_renunciar_narrador($id, $uid, $confirmo, $motivo) // el narrador asignado se sale
opg_av_vincular($id, $url, $uid)
opg_av_actualizar_datos($id, $campos, $uid) // nota, comentario, inframundo, isla, día (consola)
opg_av_editar($id, $entrada, $uid)          // el creador edita en estados 0 y 4

// Fecha e isla
opg_av_validar_dia($valor)
opg_av_islas_buscar($texto)                 // foros con pid en OPG_AV_ISLA_PADRES
opg_av_isla_valida($fid)

// Fichas
opg_av_fichas_buscar($texto)                // máximo 20, nombre o apodo
opg_av_ficha($fid)                          // fid y nombre, o null

// Límites
opg_av_contar_activas($fid, $excluir_id)    // [solitarias, total]
opg_av_validar_limites($peticion)           // lista de jugadores que excederían

// Mercado de narradores
opg_av_sin_narrador_listar($uid, $pagina)   // estado 4 que no son mías (§4.10)
opg_av_sin_narrador_contar($uid)
opg_av_narradores_disponibles($pagina)      // ofertas vigentes con sus conteos
opg_av_oferta_obtener($uid)                 // la oferta del usuario, vigente o vencida
opg_av_oferta_guardar($uid, $discord, $mensaje) // crea o actualiza, y renueva
opg_av_oferta_renovar($uid)
opg_av_oferta_retirar($uid)
opg_av_ofertas_admin_listar($pagina)        // todas las ofertas, vigentes y vencidas (consola)
opg_av_oferta_retirar_admin($uid_oferta, $uid_staff, $motivo)

// Consola
opg_av_conteo_estados()                     // [estado => n], una consulta GROUP BY
opg_av_historial($id)                       // filas de auditoría de una solicitud

// Lectura
opg_av_listar($filtros, $pagina, $es_consola)  // $filtros: ambito, estado, rol, uid (§4.10)
opg_av_obtener($id, $es_consola)
opg_av_normalizar_fila($fila, $es_consola)  // maneja filas antiguas

// Auditoría
opg_av_auditar($id, $uid, $accion, $antes, $despues)
```

Todas las funciones escapan con `$db->escape_string()` o casts enteros, toman el
prefijo de tabla de `$db` (nunca escriben `mybb_` a mano) y devuelven arrays de
errores en vez de hacer `exit` o escribir salida HTTP: la página decide cómo
responder.

### 4.2 `inc/plugins/lib/aventuras_vistas.php`: fragmentos

Funciones puras que reciben datos ya normalizados y devuelven HTML. Todo texto
de usuario pasa por `htmlspecialchars($t, ENT_QUOTES, 'UTF-8')`; los atributos
`hx-*` se construyen con enteros y rutas fijas, nunca con texto de usuario.

```php
opg_av_v_tarjeta($av, $rol_vista)           // .opg-card de una solicitud
opg_av_v_lista($items, $total, $pagina, $filtros)   // tarjetas + paginador
opg_av_v_fila_consola($av, $puede_eliminar) // fila de la consola
opg_av_v_chip_ficha($ficha, $destino)       // chip con <input type=hidden>
opg_av_v_resultados_fichas($fichas, $destino)
opg_av_v_resultados_islas($islas)
opg_av_v_slot_isla($isla)                   // isla elegida + hidden + "cambiar"
opg_av_v_campos_rol($rol)                   // campos según "narrador" o "jugador"
opg_av_v_indicador_activas($solitarias, $total, $bloquea_envio)
opg_av_v_aviso($tipo, $mensaje_o_lista)     // .aviso.ok / .aviso.err
opg_av_v_bloque_sin_narrador($items, $total, $pagina) // bloque destacado
opg_av_v_tarjeta_narrador($narrador)        // tarjeta de un narrador disponible
opg_av_v_mi_oferta($oferta)                 // oferta propia: renovar, retirar o formulario
opg_av_v_form_oferta($oferta)               // mensaje de hasta 200 caracteres
opg_av_v_contador_pestana($n)               // número junto al título de la pestaña
opg_av_v_modal_postular($av)                // ventana de confirmación de la postulación
opg_av_v_modal_renunciar($av)               // ventana de confirmación de "Dejar de narrar"
opg_av_v_vacio_mis_aventuras()              // recomendación de pedir una aventura
opg_av_v_form_solicitud($entrada, $modo)    // formulario de crear o de editar (con datos)
opg_av_v_historial($filas)                  // historial de una solicitud
opg_av_v_conteos_estado($conteos, $activo)  // chips de estado con su número
opg_av_v_fila_oferta_admin($oferta)         // fila de la sección Narradores de la consola
opg_av_v_form_retirar_oferta($uid)          // motivo (200) para retirar una oferta
opg_av_v_form_denegar($id)
opg_av_v_form_editar($av)
opg_av_v_form_vincular($id)
```

### 4.3 Reglas de validación en `opg_av_validar_solicitud`

El `uid` del creador viene de la sesión, nunca de la entrada. La entrada trae
`rol` (`narrador` o `jugador`), `participantes[]` (lista de `fid`),
`narrador_fid` opcional, `isla_fid`, `dia`, `comentario` e `inframundo`.

**Modo edición.** `opg_av_editar` reutiliza esta misma función con el id de la
solicitud. Cambia lo siguiente: no se aplica el antirrebote del punto 9; el
`uid` del creador es el de la fila, que debe coincidir con el de la sesión; el
`rol` no viene de la entrada sino de la fila (`narrador_uid = uid` es la forma A,
cualquier otro caso es la forma B), de modo que no se puede cambiar; y el estado
resultante lo decide el narrador (con narrador, 0; sin narrador, 4) en lugar de
ser un estado inicial. El resto de reglas (participantes, narrador, isla, día,
comentario, idea) es idéntico.

1. El creador debe tener ficha (`does_ficha_exist`).
2. `participantes`: lista de enteros positivos, sin repetir, cada uno con ficha
   existente. Sin límite de cantidad.
3. Si `rol = narrador`: se ignora `narrador_fid` de la entrada. El narrador es
   el creador. Debe haber al menos un participante y el creador no puede estar
   entre ellos. Estado inicial 0.
4. Si `rol = jugador`: el creador se añade a los participantes si falta. La
   entrada trae `tiene_narrador` (casilla o selector).
   - Con `tiene_narrador` verdadero, `narrador_fid` es obligatorio, debe tener
     ficha y no estar entre los participantes; estado inicial 0. `idea` se
     ignora y no se guarda.
   - Con `tiene_narrador` falso, los campos de narrador quedan nulos, el estado
     inicial es 4 y `idea` (opcional, `trim`, máximo `OPG_AV_MAX_IDEA`) se guarda
     en `idea_narrador`.
   El servidor no ofrece ni valida "elegir de la lista de narradores
   disponibles": `narrador_fid` es solo el narrador que el usuario ya pactó y
   puede ser cualquier ficha.
5. `isla_fid`: obligatorio y válido según `opg_av_isla_valida`.
6. `dia`: obligatorio, entero de 1 a `OPG_AV_DIAS`.
6b. `tier`: obligatorio, entero de 1 a 10, que se guarda en `tier_seleccionado`.
7. `comentario`: texto obligatorio, `trim` no vacío, máximo `OPG_AV_MAX_COMENTARIO`. Se guarda sin
   transformar; se escapa al renderizar.
8. `inframundo`: casilla; presente es verdadero, ausente es falso.
9. Antirrebote: se rechaza (409) una solicitud con los mismos participantes,
   isla y día creada por el mismo usuario en los últimos 60 segundos, además de
   deshabilitar el botón mientras viaja la petición (§6.2).

El nombre de cada participante y del narrador se lee de `mybb_op_fichas`, nunca
de la entrada. `jugadores_json` se guarda como `[{"uid": fid, "nombre": "..."}]`.

La creación **bloquea** solicitudes que excederían límites: el límite se aplica
al crear y al aprobar (requisitos §6.1). El servidor valida cada participante
antes de insertar y devuelve 422 si excedería 3 solitarias o 4 activas totales.

### 4.4 Isla válida

```sql
SELECT fid, name FROM mybb_forums
WHERE pid IN (15,16,17,18) AND active = 1 AND fid = N
```

Se usa `pid` (padre directo), no `parentlist`, porque `parentlist` incluiría a
los subforos. `opg_av_islas_buscar` aplica el mismo `WHERE` más
`name LIKE '%texto%'` con `ORDER BY disporder, name`, hasta 30 resultados. El
nombre de la isla se obtiene con `LEFT JOIN mybb_forums` en cada lectura; no se
copia a la solicitud.

### 4.5 Límites de aventuras activas

`opg_av_contar_activas($fid, $excluir_id)`:

```sql
SELECT pa.id, COUNT(ap.id) AS num_jugadores
FROM {tabla} pa
JOIN {prefix}op_aventuras_participantes ap ON ap.peticion_id = pa.id
WHERE pa.estado IN (0,1,4)
  AND pa.id <> {excluir_id}
  AND EXISTS (
    SELECT 1 FROM {prefix}op_aventuras_participantes propio
    WHERE propio.peticion_id = pa.id AND propio.uid = {uid}
  )
GROUP BY pa.id
```

En PHP, una fila es solitaria cuando `num_jugadores = 1` y grupal en otro caso.
Las entradas legacy con `uid=NULL` permanecen visibles, pero no coinciden con el
`EXISTS` ni consumen cupo hasta que se resuelva su identidad. Durante la
transición, solo las peticiones declaradas no migradas usan el fallback JSON.

`opg_av_validar_limites($peticion)` recorre los participantes con `uid > 0` y
para cada uno suma la solicitud que se aprueba:

- solitaria: `solitarias + 1 > 3` o `total + 1 > 4` excede;
- grupal: `total + 1 > 4` excede.

Devuelve la lista `[fid, nombre, solitarias, total, motivo]` de quienes
exceden. La consola la muestra tal cual en el mensaje de error. El indicador del
formulario usa la misma función de conteo con `excluir_id = 0`, de modo que el
indicador y la aprobación nunca discrepen (hoy sí discrepan).

Una solicitud en estado 0, 1 o 4 cuenta como activa **desde que se envía**, no
desde que se aprueba. Las solicitudes antiguas cuentan igual que las nuevas
(requisitos §4.2). No hay efecto retroactivo: la función nunca modifica ni
deniega solicitudes existentes.

### 4.6 Cambio de estado

`opg_av_cambiar_estado($id, $a, $uid, $motivo)` se ejecuta dentro de una
transacción:

1. `SELECT ... FOR UPDATE` de la fila (InnoDB; la tabla ya lo es).
2. Comprueba permiso: para 1, 2 y 3, `opg_av_puede_resolver($av, $uid)` exige
   permiso de gestión. El gestor puede moderar solicitudes propias o en las que
   participe; para 5 continúa exigiéndose ser el creador.
3. `opg_av_transicion_valida($actual, $a)` según la tabla de requisitos §4.1:
   `0→1`, `0→2`, `4→2`, `1→2`, `1→3`, `4→0` (solo vía postulación),
   `cualquiera→5` (solo creador) y `5→borrado`.
4. Aprobar (`→1`): exige narrador asignado (`narrador_uid` no nulo) y que
   `opg_av_validar_limites` no devuelva excedidos.
   Finalizar (`1→3`) a mano se rechaza (`409`) si la solicitud tiene una entrega
   `pendiente` (§4.12): se resuelve entregando o rechazando esa entrega. La
   transición `1→3` también la ejecuta `opg_ent_entregar` al cerrar una entrega.
5. Denegar (`→2`): exige `motivo` con `trim` no vacío y de hasta 500
   caracteres; lo guarda en `motivo_denegacion`.
6. `UPDATE ... WHERE id = N AND estado = {actual}` y verifica
   `affected_rows`, para no pisar un cambio concurrente.
7. Audita y hace `COMMIT`. Cualquier fallo hace `ROLLBACK`.

`opg_av_postular_narrador` usa un solo `UPDATE` condicionado:

```sql
UPDATE {tabla}
SET narrador_uid = U, narrador_fid = U, narrador_nombre = '...', estado = 0,
    motivo_renuncia = NULL
WHERE id = N AND estado = 4 AND narrador_uid IS NULL
```

y comprueba `affected_rows`; así dos narradores simultáneos no se pisan. Antes
de ejecutar valida que el usuario tenga ficha, que no sea creador ni
participante y que `$confirmo` sea verdadero (el POST trae el campo
`confirmo=1` de la casilla de la ventana; sin él responde `422` y no cambia
nada). `form_postular` solo muestra la ventana y no modifica datos.

`opg_av_renunciar_narrador` también usa un solo `UPDATE` condicionado:

```sql
UPDATE {tabla}
SET narrador_uid = NULL, narrador_fid = NULL, narrador_nombre = NULL, estado = 4,
    motivo_renuncia = '...'
WHERE id = N AND narrador_uid = U AND estado IN (0, 1)
```

y comprueba `affected_rows`: si es 0 (la solicitud cambió, ya no es suya o está
en otro estado) responde `409`. Valida que `$confirmo` sea verdadero y que
`$motivo` tenga `trim` no vacío y como máximo `OPG_AV_MAX_MOTIVO` caracteres
(`422` en caso contrario), y no necesita permisos de gestión: solo el narrador asignado
puede salirse, y se identifica por `narrador_uid` de la fila, no por la entrada.
No toca `aventura_url` ni `idea_narrador`. Tras el `UPDATE` audita
`renunciar_narrador` con `antes: {narrador_uid, estado}` y
`despues: {narrador_uid: null, estado: 4, motivo: "..."}`. La tabla de transiciones de
`opg_av_transicion_valida` incluye `0→4` y `1→4`, que solo acepta esta función.
`form_renunciar` solo muestra la ventana y no modifica datos.

`opg_av_editar($id, $entrada, $uid)` se ejecuta en una transacción:

1. `SELECT ... FOR UPDATE` de la fila.
2. `opg_av_puede_editar`: el `uid` de la sesión es el creador y el estado está en
   `OPG_AV_ESTADOS_EDITABLES` (0 o 4). Si no es el creador, `403`; si el estado
   ya no es editable (por ejemplo, acaba de aprobarse), `409` con la tarjeta
   actualizada.
3. Valida la entrada con `opg_av_validar_solicitud` en modo edición (§4.3).
4. Calcula el estado nuevo: con narrador, 0; sin narrador, 4. Si hay narrador
   nuevo, `motivo_renuncia` pasa a `NULL`.
5. `UPDATE` de `jugadores_json`, `num_jugadores`, `narrador_uid`, `narrador_fid`,
   `narrador_nombre`, `idea_narrador`, `isla_fid`, `dia`, `tier_seleccionado`, `comentario_publico`,
   `inframundo`, `estado` y `editado_at = ahora`, con
   `WHERE id = N AND estado IN (0, 4)`; si `affected_rows` es 0, `409`.
6. Audita `editar` con solo los campos que cambiaron
   (`antes`/`despues` de cada uno) y hace `COMMIT`.

No refleja nada en `_meta`, porque `editar` no toca la nota de staff. El
comentario público de la columna manda sobre el de `_meta`; si el creador edita
el comentario de una solicitud que lo tenía solo en `_meta` (filas antiguas), la
columna nueva lo reemplaza.

`opg_av_eliminar` exige `is_staff` y estado 5, y borra la fila y su `_meta` en
una transacción. No toca `aventurasActivas`.

### 4.7 Vinculación del tema

`opg_av_vincular($id, $entrada, $uid)` acepta como `entrada` un tema elegido en el
typeahead, un enlace pegado o un `tid` escrito, y los trata igual:

1. **Extraer el `tid`** con `opg_av_tid_de_entrada($texto)`: un entero positivo
   solo; o un enlace del propio foro (host igual al de `$mybb->settings['bburl']`
   o de `OPG_AV_HOSTS_EXTRA`, comparado en minúsculas) del que se toma el valor de
   `tid=N`. Cualquier otro texto, o un enlace de otro host, se rechaza.
2. **Validar el tema** con `opg_ent_tema_valido($tid, $av, $uid)` (§4.12): existe,
   visible para el usuario, prefijo Aventura, en el foro de la isla o sus subforos
   (en solicitudes antiguas sin isla, en un foro de rol), abierto, sin el mensaje de
   recompensas y no vinculado a otra solicitud ni en otra entrega vigente.
3. **Guardar** `aventura_url = {bburl}/showthread.php?tid=N` (normalizada: es el
   formato que buscan `Calculadora_Tiers.php` y `recompensasAventuras.php` con
   `LIKE '%tid=N%'`), con `UPDATE ... WHERE id = N AND estado = 1 AND
   (narrador_uid = U OR {predicado de opg_av_sql_participa(U)})`. El helper
   genera un `EXISTS` sobre `op_aventuras_participantes`; durante la transición
   añade fallback solo para filas reportadas como no migradas. El permiso se
   valida antes y en el UPDATE. Máximo 255 caracteres.
4. Audita `vincular` con el `tid` y el nombre del tema.

Al abrir el formulario de entrega, `opg_ent_form` lee `aventura_url`, extrae el
`tid` con la misma función y precarga el chip del tema si todavía es válido; si
dejó de serlo (el tema se cerró o se movió), muestra el motivo y deja el campo
vacío para elegir otro.

### 4.8 Lectura y compatibilidad con filas antiguas

`opg_av_normalizar_fila` produce el arreglo que consumen las vistas y es el único
lugar que conoce el formato antiguo:

- `jugadores`: de `jugadores_json` con `json_decode`; solo conserva `uid` y
  `nombre` (ignora `nivel`). Un JSON inválido o `NULL` da lista vacía.
- `comentario_publico`: usa la columna; si está vacía, usa
  `mybb_op_peticionAventuras_meta.public_comment` (igual que la calculadora).
- `isla`: `{fid, nombre}` o `null` si `isla_fid` es nulo (se muestra "sin isla").
- `dia`: entero o `null` (se muestra "sin día").
- `fecha_texto`: `"Verano 725, día N"` o `null`.
- `motivo_denegacion`: texto o, si el estado es 2 y está vacío,
  `"Sin motivo registrado"`.
- `tier`: entero de 1 a 10 desde `tier_seleccionado`, o `null` si es 0 (solicitudes
  que no lo tenían).
- `idea_narrador`: texto o `null`.
- `editado_at`: marca de tiempo o `null`; las vistas la muestran como "Editada el
  ..." solo en la consola.
- `puede_editar`: verdadero si el usuario que consulta es el creador y el estado
  es 0 o 4, para decidir si la tarjeta muestra "Editar".
- `motivo_renuncia`: texto o `null`; solo se expone mientras el estado es 4 (el
  `UPDATE` de postularse ya lo vacía).
- `nota_staff`: solo se incluye cuando `$es_consola` es verdadero.
- No se devuelven `enemigos_json`, `detalles_json`, tier, ratio ni dificultad,
  aunque la fila los tenga.

### 4.9 Auditoría

`opg_av_auditar` inserta en `mybb_op_audit_aventuras` y además llama a
`log_audit($uid, $username, 'aventuras', $texto)` con un resumen corto, para que
las acciones aparezcan también en el log general. `$texto` se escapa antes,
porque `log_audit` interpola directamente. `detalle_json`:

```json
{ "antes": { "estado": 0 }, "despues": { "estado": 2, "motivo": "..." } }
```

Se registran: `crear`, `editar`, `postular_narrador`, `renunciar_narrador`,
`cambiar_estado`, `vincular`, `pedir_borrado`, `actualizar_datos`,
`enviar_entrega`, `rechazar_entrega`, `pagar_entrega` (con tier,
destinatarios y montos) y `eliminar` (con la fila completa en `antes`, para poder reconstruirla si fuera necesario).
Las acciones del mercado (`ofrecer_narrar`, `renovar_oferta`, `retirar_oferta` y
`retirar_oferta_admin`) usan `peticion_id = 0`. En `retirar_oferta_admin`, `uid`
es el de quien retira y `detalle_json` lleva el `uid` del ofertante y el motivo.

`opg_av_historial($id)` lee `mybb_op_audit_aventuras` de esa solicitud, de la más
reciente a la más antigua, con el nombre de usuario de quien actuó (un `JOIN`
con `mybb_users`, sin consultas en bucle) y un máximo de 100 filas. La vista
`opg_av_v_historial` muestra fecha, usuario, acción en lenguaje claro y un
resumen del `antes`/`despues` (campo, valor anterior, valor nuevo, y el motivo
cuando existe), con todo el texto escapado y los valores largos recortados a 200
caracteres. Sin filas, muestra "Sin historial anterior a la migración".

### 4.10 Consultas de Mis aventuras, Otras aventuras y el mercado

Todas parten del predicado "mía" (`$mia`) para el usuario U: es creador,
narrador o participante.

```sql
(pa.uid = U
 OR pa.narrador_uid = U
 OR EXISTS (
   SELECT 1 FROM {prefix}op_aventuras_participantes ap
   WHERE ap.peticion_id = pa.id AND ap.uid = U
 ))
```

Y de su negación (`$no_mia`), que cuida el `NULL` de `narrador_uid`: sin esa
precaución, una solicitud sin narrador desaparecería de ambos lados porque
`NULL <> U` no es verdadero.

```sql
(pa.uid <> U
 AND (pa.narrador_uid IS NULL OR pa.narrador_uid <> U)
 AND NOT EXISTS (
   SELECT 1 FROM {prefix}op_aventuras_participantes ap
   WHERE ap.peticion_id = pa.id AND ap.uid = U
 ))
```

Las páginas de jugadores nunca muestran el estado 5.

| Vista | `WHERE` |
|---|---|
| Mis aventuras | `pa.estado <> 5 AND $mia` |
| Aventuras sin narrador | `pa.estado = 4 AND $no_mia` |
| Otras aventuras (listado) | `pa.estado IN (0,1,2,3) AND $no_mia` |

El bloque "Aventuras sin narrador" y el listado de Otras aventuras no se
solapan (estado 4 frente a 0 a 3) y, junto con Mis aventuras, cubren todos los
estados visibles. La unión de las tres vistas es exactamente todo lo visible, sin
repetidos ni huecos.

`opg_av_listar` recibe `ambito` (`mias` u `otras`), `estado`, `rol` y `uid`, y el
`uid` del usuario que consulta. Filtros adicionales, todos con `AND`:

| Filtro | Aplica en | Condición |
|---|---|---|
| `estado=creado` | Mis y Otras | `estado = 0` |
| `estado=pendiente-narrador` | Solo Mis | `estado = 4` |
| `estado=aprobado` | Mis y Otras | `estado = 1` |
| `estado=denegado` | Mis y Otras | `estado = 2` |
| `estado=finalizado` | Mis y Otras | `estado = 3` |
| `rol=narrador` | Solo Mis | `narrador_uid = U` |
| `rol=jugador` | Solo Mis | `EXISTS` en participantes para `uid = U` |
| `uid=N` | Solo Otras | narrador `N` o `EXISTS` en participantes para `uid = N` |

El servidor ignora `rol` en Otras y `uid` en Mis, y valida `estado` contra el
catálogo (un valor desconocido se descarta, y `pendiente-narrador` se descarta en
Otras porque esas solicitudes están en su bloque propio). Las solicitudes
antiguas, donde el creador podría no figurar en `jugadores_json`, quedan
igualmente en Mis aventuras por el `pa.uid = U` del predicado. Orden de Mis y de
Otras: `ORDER BY pa.created_at DESC, pa.id DESC`. El total para el paginador
usa el mismo `WHERE` en un `COUNT(*)`.

**Aventuras sin narrador.** `opg_av_sin_narrador_listar` usa la fila de la tabla
anterior con `ORDER BY pa.created_at ASC, pa.id ASC` (la que más espera, primero)
y `LIMIT 10 OFFSET` con un enlace "Ver más". `opg_av_sin_narrador_contar` es el
`COUNT(*)` con el mismo `WHERE` y alimenta el contador de la pestaña.

**Narradores disponibles.** `opg_av_narradores_disponibles`:

```sql
SELECT nd.uid, nd.discord, nd.mensaje, nd.created_at, nd.updated_at, f.nombre, f.apodo
FROM {narradores_disponibles} nd
JOIN {fichas} f ON f.fid = nd.uid
WHERE nd.updated_at >= {ahora - OPG_AV_OFERTA_DIAS * 86400}
```

El `JOIN` con la ficha descarta ofertas de usuarios sin ficha. Con la lista de
`uid` obtenida se hace una segunda consulta agrupada, no una por narrador:

```sql
SELECT narrador_uid, estado, COUNT(*) AS n
FROM {tabla}
WHERE narrador_uid IN (...) AND estado IN (0, 1, 3)
GROUP BY narrador_uid, estado
```

(usa `idx_narrador_uid`). `en_curso` es la suma de los estados 0 y 1 y
`finalizadas` el estado 3. Se ordena en PHP por `en_curso` ascendente y, a
igual valor, por `created_at` ascendente, y se pagina de 20 en 20.

`opg_av_oferta_obtener($uid)` devuelve la oferta del propio usuario aunque esté
vencida, con la marca `vencida`, para que pueda renovarla. Todas las funciones de
oferta exigen ficha. `opg_av_oferta_guardar` valida `discord` (obligatorio, `trim`, de 2 a
`OPG_AV_MAX_DISCORD` caracteres, sin saltos de línea ni caracteres de control) y
`mensaje` (`trim`, hasta 200 caracteres), hace `INSERT ... ON DUPLICATE KEY UPDATE` (conserva `created_at` y
actualiza `mensaje` y `updated_at`), y audita `ofrecer_narrar`.
`opg_av_oferta_renovar` actualiza solo `updated_at` (audita `renovar_oferta`) y
`opg_av_oferta_retirar` borra la fila del propio `uid` (audita `retirar_oferta`);
ninguna acepta un `uid` de la entrada, siempre el de la sesión.

Rendimiento: el predicado `EXISTS` usa `idx_uid_peticion`; los listados conservan
`idx_created_at` para orden y paginación. El fallback `JSON_CONTAINS` queda
limitado a las excepciones registradas durante la migración y se retira tras el
checksum final. El mercado de narradores mantiene su paginación independiente.

La consola (`$es_consola = true`) no usa `ambito` ni excluye el estado 5: lista
todo con los filtros de estado y UID de §5.3.

### 4.11 `inc/plugins/lib/recompensas_aventura.php`: análisis y pago

Lleva al servidor, **sin cambiar reglas ni montos**, lo que hoy hace
`op/staff/recompensasAventuras.php` con la ayuda de JavaScript en su template
(`op_staff_recompensasAventuras.html`). Una diferencia deliberada: hoy los
porcentajes los calcula el navegador y el servidor se fía de ellos; aquí los
**recalcula siempre el servidor** y descarta lo que mande el cliente.

Constantes (las tablas de recompensas se **extraen** de las constantes
`RECOMPENSAS_NARRADOR_APRENDIZ|ESTUDIOSO|ILUSTRE|ERUDITO` y `RECOMPENSAS_JUGADOR`
de esa página, sin retipearlas):

```php
const OPG_REC_POSTS_TIER = [1=>4, 2=>5, 3=>6, 4=>7, 5=>8, 6=>9, 7=>10, 8=>10, 9=>11, 10=>12];
const OPG_REC_MIN_CARACTERES = 300;         // un post vale si tiene 300 o más caracteres sin etiquetas
const OPG_REC_BASE_MAX = 150;
const OPG_REC_FINAL_MAX = 200;
const OPG_REC_EXTRA_JUGADORES = [0, 10, 20, 30, 40, 50];
const OPG_REC_EXP_NARRADOR_MENSUAL = 675;   // el mismo tope que recompensasAventuras.php
const OPG_REC_NIVELES = ['Aprendiz'=>1, 'Estudioso'=>2, 'Ilustre'=>3, 'Erudito'=>4];
const OPG_REC_UID_PUBLICACION = 5;          // usuario que publica el mensaje, como hoy
// OPG_REC_NARRADOR[nivel][tier] y OPG_REC_JUGADOR[tier]: experiencia, nikas, cofre/reputación/berries
```

Funciones:

```php
opg_rec_analizar_tema($tid, $narradores)
opg_rec_porcentaje_base($validos, $esperados)
opg_rec_calcular_porcentajes($analisis, $tier, $ajustes)
opg_rec_minimos($analisis, $tier, $n_participantes)
opg_rec_nivel_narrador($ficha)
opg_rec_calcular_narrador($ficha, $porcentaje, $tier, $exp_mensual_actual)
opg_rec_calcular_jugador($ficha, $porcentaje, $tier, $infra, $rep_tipo)
opg_rec_reset_mensual()
opg_rec_tema_pagado($tid)
opg_rec_mensaje_resumen($datos)
opg_rec_publicar_y_cerrar($tid, $mensaje)
```

**Análisis (`opg_rec_analizar_tema`).** Lee todos los posts del tema por fecha y
recibe el narrador explícito conservado por la entrega. Para cada autor cuenta los
posts totales y los **válidos**: `strlen(strip_tags(mensaje)) >= 300`. El narrador
mantiene ese rol aunque no haya escrito y, en tal caso, aparece con cero posts y
una advertencia. Devuelve también la cantidad de posts no válidos. Un tema sin
posts es un error. No se busca ni se interpreta la etiqueta `[Narrador]`.

**Porcentajes.**

- `opg_rec_porcentaje_base($validos, $esperados)`: con `$esperados <= 0`, 100. Con
  `$validos <= $esperados`, `$validos / $esperados * 100`. Con más, `100 +
  ($validos - $esperados) * (100 / $esperados / 2)`. Resultado limitado a
  0..`OPG_REC_BASE_MAX`.
- `opg_rec_calcular_porcentajes`: para cada destinatario, `posts ajustados =
  posts válidos + variación` (la variación del staff va de -100 a 100 y se suma a
  los posts, no al porcentaje). **Narradores:** base de los posts ajustados más el
  extra por jugadores (uno de `OPG_REC_EXTRA_JUGADORES`), limitado a
  0..`OPG_REC_FINAL_MAX`. **Jugadores:** base de los posts ajustados, limitado a
  0..`OPG_REC_FINAL_MAX`.
- `opg_rec_minimos`: `posts esperados del tier x participantes`. El número de
  participantes es el detectado (narradores más jugadores con posts) y el staff lo
  puede ajustar de 1 a 20. Devuelve el mínimo, el total de posts válidos y si se
  cumple.

**Recompensas de un narrador (`opg_rec_calcular_narrador`).** Con el nivel de su
ficha (`nivelnarrador`; si es desconocido, Aprendiz y un aviso) y el tier:
`experiencia = round(exp_base * pct / 100)`, `nikas = round(nikas_base * pct / 100)`,
cofre fijo del tier. **Tope mensual:** `disponible = max(0, 675 -
expNarradorMensualActual)`, `exp_a_entregar = min(experiencia, disponible)`,
`excedente = experiencia - exp_a_entregar`, `kuros = floor(excedente / 2)`. Se
suman nikas y `exp_a_entregar` a `expNarradorMensualActual`, y los kuros a `kuro`.

**Recompensas de un jugador (`opg_rec_calcular_jugador`).** Con los oficios de su
ficha: Contrabandista (nivel 0 a 3 multiplica las berries por 1,25, 1,50, 1,75 o
2,00) y Arqueólogo (nivel 1 multiplica la experiencia por 1,10; nivel 2 suma 1
nika). `experiencia = round(exp_base * (pct * mult_xp) / 100)`,
`nikas = round((nikas_base + nikas_extra) * pct / 100)`,
`reputacion = 0` si el tipo es `ninguna` y `round(rep_base * pct / 100)` en otro
caso, y `berries = round(berries_base * pct / 100 * mult_contrabandista *
(infra ? 1,25 : 1))`. Con inframundo, las berries también se suman a
`movidoInframundo`. La reputación se suma a `reputacion` y a `reputacion_positiva`
o `reputacion_negativa` según el tipo.

**Reinicio mensual (`opg_rec_reset_mensual`).** Como hoy: con la zona horaria
`Europe/Madrid`, si el mes (`Y-m`) difiere del valor del ajuste
`exp_narrador_ultimo_reset`, pone `expNarradorMensualActual = 0` en todas las fichas
y guarda el mes. Se ejecuta una vez antes de cada pago, no por destinatario.

**Tema pagado (`opg_rec_tema_pagado`).** Verdadero si el tema está cerrado o ya
tiene un post con el texto `RECOMPENSAS PROCESADAS`: las mismas dos comprobaciones
de `recompensasAventuras.php`.

**Mensaje y cierre.** `opg_rec_mensaje_resumen` genera el BBCode que publica hoy
la página (cabecera "RECOMPENSAS PROCESADAS", tier, fecha de pago, quién procesó,
y un recuadro por narrador y por jugador con sus recompensas y bonificaciones).
`opg_rec_publicar_y_cerrar` lo publica con `PostDataHandler` como el usuario
`OPG_REC_UID_PUBLICACION` y pone `closed = 1` en el tema. Como el mensaje y el
cierre son los mismos que usa la herramienta antigua, ambas **se protegen entre
sí**: cualquiera rechaza un tema que la otra ya pagó.

**Parity.** Una prueba carga los arreglos de recompensas de
`recompensasAventuras.php` por expresión regular y los compara con las constantes
de la biblioteca, y verifica con casos fijos que porcentajes, recompensas y topes
son idénticos (§11).

### 4.12 `inc/plugins/lib/aventuras_entregas.php`: la entrega

```php
// Elegibilidad
opg_ent_puede_enviar($av, $uid)            // narrador asignado y estado 1
opg_ent_temas_buscar($texto, $av, $uid)    // typeahead (mínimo 3 caracteres)
opg_ent_tema_valido($tid, $av, $uid)       // validación completa del tema
opg_av_tid_de_entrada($texto)              // entero, enlace del foro o `tid=N`

// Envío
opg_ent_form($peticion_id, $uid)           // datos del formulario, tema precargado
opg_ent_enviar($peticion_id, $entrada, $uid)
opg_ent_obtener($peticion_id)

// Consola
opg_ent_listar($estado, $pagina)
opg_ent_conteos()
opg_ent_analizar($entrega_id, $ajustes)    // análisis + recompensas por destinatario, sin escribir
opg_ent_pagar($entrega_id, $ajustes, $uid_staff)
opg_ent_completar($entrega_id, $uid_staff)
opg_ent_rechazar($entrega_id, $motivo, $uid_staff)
```

**Temas elegibles (`opg_ent_temas_buscar` / `opg_ent_tema_valido`).**

```sql
SELECT t.tid, t.subject, t.year, t.estacion, t.day, f.name AS foro
FROM {threads} t JOIN {forums} f ON f.fid = t.fid
WHERE t.visible = 1 AND t.closed = 0 AND t.prefix = 3
  AND t.subject LIKE '%texto%'
  AND FIND_IN_SET({isla_fid}, f.parentlist)          -- con isla
  -- o: f.parentlist LIKE '10,%'                      -- solicitud antigua sin isla
  AND t.fid NOT IN ({foros inaccesibles})
  AND NOT EXISTS (SELECT 1 FROM {entregas} e WHERE e.tid_activo = t.tid)
  AND NOT EXISTS (SELECT 1 FROM {tabla} pa WHERE pa.aventura_url LIKE CONCAT('%tid=', t.tid)
                  AND pa.id <> {id_solicitud})
ORDER BY t.dateline DESC LIMIT 20
```

`prefix = 3` es el tipo **Aventura**. La búsqueda exige **al menos 3 caracteres**:
con menos, el servidor devuelve un fragmento vacío con la indicación "Escribe al
menos 3 caracteres" (el cliente no es la barrera). `get_unviewable_forums` excluye
los foros que el usuario no puede ver, como `cronologia.php`; `FIND_IN_SET` sobre
`parentlist` incluye los subforos de la isla. `opg_ent_tema_valido` ejecuta la
misma consulta con `t.tid = N` y además comprueba `opg_rec_tema_pagado` (sin el
mensaje de recompensas). `elegir_tema` devuelve el chip (nombre, foro, fecha del
tema) con un campo oculto `tid`; el nombre se toma de la base, nunca de la entrada.

**Envío (`opg_ent_enviar`).** En una transacción:

1. `SELECT ... FOR UPDATE` de la solicitud: debe estar en estado 1 y el usuario ser
   el narrador asignado (`403` en otro caso, `409` si cambió de estado).
2. Entrega previa: si no existe, se crea; si existe `rechazada`, se actualiza (se
   incrementa `intentos`); si está `pendiente` o `entregada`, `409`.
3. Valida el tema con `opg_av_tid_de_entrada` y `opg_ent_tema_valido`, `resumen`
   (`trim`, 1 a 2000 caracteres) y `reputacion` (`positiva` o `negativa`).
4. Escribe `tid`, `tid_activo = tid`, `tema_nombre` (leído del tema), `resumen`,
   `reputacion`, `estado = 'pendiente'`, `narrador_uid`, `enviado_por`,
   `enviado_at`, y limpia `motivo_rechazo`. `narrador_uid` es la instantánea del
   narrador asignado; `enviado_por` conserva al actor. Si el tema difiere del
   vinculado, actualiza `aventura_url` al formato normalizado (§4.7).
5. Audita `enviar_entrega` y hace `COMMIT`.

Los participantes y el tier se leen de la solicitud al analizar y pagar. El
narrador sí se guarda en la entrega para que el rol recompensado no cambie si la
asignación se modifica después del envío. Las filas antiguas se rellenan desde
`enviado_por` y mantienen ese valor como fallback de lectura.

**Rechazo (`opg_ent_rechazar`).** Exige `opg_av_puede_resolver` (permiso de
gestión) y una entrega `pendiente`. `motivo` de 1 a 500 caracteres.
Pone `estado = 'rechazada'`, `tid_activo = NULL`, `motivo_rechazo`, `revisado_por`
y `revisado_at`, con `WHERE estado = 'pendiente'`. Audita `rechazar_entrega`.

**Análisis (`opg_ent_analizar`).** Recibe los ajustes del staff:

```json
{ "tier": 4, "extra_jugadores": 20, "n_participantes": 5, "confirma_minimos": false,
  "destinatarios": { "12": { "incluir": true, "variacion": 0, "rep": "positiva" } } }
```

Sin ajustes usa los valores por defecto: `tier` = `tier_seleccionado` de la
solicitud, `extra_jugadores` 0, `n_participantes` el detectado, y por destinatario
`incluir` verdadero, `variacion` 0 y `rep` la reputación de la entrega. Llama a
`opg_rec_analizar_tema` y `opg_rec_calcular_porcentajes`, lee la ficha de cada
destinatario (el nivel del narrador sale de `nivelnarrador`) y devuelve, por
destinatario, su rol, posts totales y válidos, porcentaje y recompensas, más el
mínimo de posts y las advertencias: usuarios que escribieron sin estar en la
solicitud, usuarios de la solicitud sin posts, narrador de la entrega sin posts,
destinatarios sin ficha, nivel de narrador desconocido y tema ya
pagado. **No escribe nada.** Valida los ajustes (tier de 1 a 10, extra dentro de
`OPG_REC_EXTRA_JUGADORES`, variación de -100 a 100, `rep` en `positiva`,
`negativa` o `ninguna`) y descarta cualquier porcentaje enviado por el cliente.

**Pago (`opg_ent_pagar`).** `mybb_op_fichas` y el inventario son InnoDB, pero
`mybb_users` (donde está `newpoints`, la experiencia) es MyISAM y no admite
`ROLLBACK`. Por eso el pago va por destinatario, con un registro que garantiza el
pago único y permite reanudar:

1. **Permiso y condiciones.** `opg_av_puede_resolver` (permiso de gestión,
   aunque sea parte de la solicitud); si hay narradores detectados;
   `opg_rec_tema_pagado` falso (con
   texto "El tema está cerrado o ya tiene el mensaje de recompensas: si lo pagó la
   herramienta antigua, rechaza esta entrega; si lo cerró otra persona, ábrelo");
   y, si no se cumplen los posts mínimos, `confirma_minimos` verdadero.
2. **Reclamar la entrega.**
   ```sql
   UPDATE {entregas}
   SET procesando_por = S, procesando_at = ahora
   WHERE id = N AND estado = 'pendiente'
     AND (procesando_at IS NULL OR procesando_at < ahora - 120)
   ```
   Si `affected_rows` es 0, otro miembro del staff la procesa o ya no está
   pendiente: `409`. Se guardan `tier`, `infra` (del indicador de la solicitud) y
   `ajustes_json` la primera vez; si ya estaban, se **reutilizan**, de modo que
   reanudar no cambie montos.
3. **Reinicio mensual** (`opg_rec_reset_mensual`), una vez.
4. **Por cada destinatario incluido:**
   1. `INSERT IGNORE` en `entregas_pagos (entrega_id, uid, rol, porcentaje)`. Si
      la fila ya existe con `aplicado_at` no nulo, ya cobró: se omite.
   2. Transacción InnoDB: `SELECT ... FOR UPDATE` de su ficha; calcula con los
      valores actuales (incluido `expNarradorMensualActual` para el tope);
      `UPDATE pagos SET aplicado_at = ahora, xp_cantidad = ..., detalle_json = ...
      WHERE entrega_id = N AND uid = U AND aplicado_at IS NULL` (con
      `affected_rows` 0 se omite); `UPDATE mybb_op_fichas` (nikas, berries,
      reputación, `movidoInframundo`, `expNarradorMensualActual`, `kuro`) y, para
      el narrador, el cofre en `mybb_op_inventario`; `COMMIT`.
   3. Fuera de la transacción: `xp_estado = 'aplicando'`;
      `UPDATE mybb_users SET newpoints = newpoints + xp_cantidad` (relativo, como
      hoy, para no pisar la experiencia que otro proceso sume mientras tanto);
      `xp_estado = 'aplicado'`. Si la escritura falla, queda `aplicando`.
   4. Log con `log_audit` y la misma categoría y texto que hoy
      (`[Entregas][Aventura Narradores]` o `[Entregas][Aventura Usuario]`), más
      la línea "Petición #P (entrega #N)".
5. **Cierre.** Cuando todos los incluidos tienen `aplicado_at` y `xp_estado =
   'aplicado'`: `opg_rec_publicar_y_cerrar` (mensaje y cierre del tema); luego
   `UPDATE {entregas} SET estado = 'entregada', ... WHERE id = N AND estado =
   'pendiente'` y `UPDATE {tabla} SET estado = 3 WHERE id = P AND estado = 1`.
   Audita `pagar_entrega` con el tier, los destinatarios y los montos.
6. **Si algo falla a mitad,** la entrega queda `pendiente` con `procesando_at`
   caducando a los 120 segundos. La página `aventuras_recompensas.php` muestra quién cobró y quién falta, y
   `opg_ent_completar` repite los pasos 4 y 5 solo para quien no tenga
   `aplicado_at` o `xp_estado = 'aplicado'`, y reintenta la publicación del mensaje
   si faltó. Un pago con `xp_estado = 'aplicando'` (se interrumpió justo entre el
   incremento y la marca) no se repite automáticamente: se marca `dudoso` y el
   staff, tras comprobar la experiencia del usuario, elige "La experiencia ya se
   aplicó" o "Aplicar la experiencia", y la elección queda auditada.

`mybb_op_fichas` tiene un disparador `AFTER UPDATE` que copia stats y monedas a
`mybb_audit_op_fichas`, y `mybb_users` otro que registra los cambios de
`newpoints`; cada pago los dispara, como ocurre hoy con `recompensasAventuras.php`.
El fallo parcial que hoy existe (se paga antes de publicar el mensaje, sin
registro de qué se pagó) queda cubierto por este registro por destinatario.

## 5. Rutas y flujos

Ambas páginas siguen el patrón de las páginas de `/op/`:

```php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tablon_de_aventuras.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/op_functions.php";
require_once MYBB_ROOT."inc/plugins/lib/aventuras.php";
require_once MYBB_ROOT."inc/plugins/lib/aventuras_vistas.php";
```

(`consola_aventuras.php` usa `./../../` y `THIS_SCRIPT = 'consola_aventuras.php'`.)

Ninguna define `display_errors`.

### 5.1 Contrato HTMX común

- **Página completa y fragmentos en la misma URL.** Una petición sin cabecera
  `HX-Request` a la URL de la página devuelve la página completa; con la
  cabecera, la acción pedida devuelve solo el fragmento. Las acciones (`?action=`)
  sin `HX-Request` responden `404`: un fragmento suelto nunca se muestra como
  página. Todas las respuestas llevan `Cache-Control: no-store` y
  `Vary: HX-Request`.
- **Lista blanca de acciones por página.** Una acción que no esté en la lista
  de esa página responde `404`, sea cual sea el rol de quien llama. Así una
  acción de gestión enviada a `tablon_de_aventuras.php` no encuentra nada,
  aunque el usuario sea staff.
- **Sesión.** Todas las acciones exigen sesión. Sin sesión (caducada) la
  respuesta es `200` con cabecera `HX-Redirect` hacia el inicio de sesión, para
  que HTMX navegue en vez de insertar un error en un panel.
- **CSRF.** El `<body>` lleva
  `hx-headers='{"X-Post-Key":"{$mybb->post_code}"}'`, por lo que HTMX añade el
  `post_code` de MyBB a todas las peticiones. Los POST (y cualquier acción que
  cambie datos) lo verifican con `verify_post_check($_SERVER['HTTP_X_POST_KEY'] ?? '', true)`
  y responden `403` si falla. No se compara `Origin`/`Referer`.
- **Códigos y errores.**
  - `200`: éxito. El cuerpo es el fragmento nuevo (por ejemplo, la tarjeta
    actualizada).
  - `422`: error de validación o de regla (límite excedido, falta el motivo...).
    El cuerpo es un `.aviso.err` y la respuesta lleva `HX-Retarget` y
    `HX-Reswap: innerHTML` hacia el contenedor de avisos de ese formulario o
    tarjeta (`#ta-avisos`, `#aviso-{id}`), de modo que el HTML de origen no
    necesita atributos extra para los errores.
  - `409`: conflicto de estado (la solicitud cambió). El cuerpo es el aviso más
    la tarjeta actualizada, con `HX-Retarget` a la tarjeta y
    `HX-Reswap: outerHTML`.
  - `403` y `404`: permisos y rutas inexistentes; HTMX no inserta nada y un
    manejador de `htmx:responseError` muestra un mensaje genérico (§6.2).
- **Eventos.** Las acciones que cambian datos devuelven `HX-Trigger` con un
  evento (`aventuraCreada`, `aventuraCambiada`, `ofertaCambiada`) que otros bloques escuchan con
  `hx-trigger="... from:body"` para refrescarse (listas de Mis y Otras aventuras, contador de la pestaña,
  bloque de sin narrador, indicador de activas).
- **Configuración global** (una etiqueta `<meta name="htmx-config">`):

  ```html
  <meta name="htmx-config" content='{"allowEval":false,"allowScriptTags":false,"selfRequestsOnly":true,"historyCacheSize":0,"defaultSwapStyle":"innerHTML","responseHandling":[{"code":"204","swap":false},{"code":"[23]..","swap":true},{"code":"409|422","swap":true,"error":false},{"code":"403|404|5..","swap":true,"target":"#ta-avisos","error":true}]}'>
  ```

- **Sin `hx-on`.** Con `allowEval: false` HTMX no ejecuta código de atributos, así
  que no se usan `hx-on:*` ni filtros de `hx-trigger` con expresiones. Todo
  comportamiento se resuelve con peticiones al servidor y CSS. Los errores
  `403`, `404` y `5xx` se insertan en `#ta-avisos` con el mensaje genérico que
  devuelve el servidor, sin manejador de JavaScript.
- Los atributos `hx-*` de los fragmentos se construyen con enteros y rutas
  fijas. Los nombres de variables del template no empiezan por `$`, salvo las
  interpolaciones `{$variable}` de MyBB.

### 5.2 `op/tablon_de_aventuras.php`

| Acción | Método | Permiso | Respuesta |
|---|---|---|---|
| (sin acción) | GET | Sesión | Página completa. `?tab=mias\|otras\|solicitud` elige la pestaña (por defecto `mias`); `?id=N` abre la pestaña de la solicitud N y la resalta. |
| `tab` | GET | Sesión | Panel de una pestaña (para el cambio de pestaña). |
| `campos_rol` | GET | Sesión | Campos del formulario según `rol=narrador\|jugador`. |
| `buscar_fichas` | GET | Ficha | Lista de resultados (máximo 20) para `destino=participante\|narrador`. |
| `elegir_ficha` | GET | Ficha | Chip validado de una ficha (`fid`, `destino`). |
| `buscar_islas` | GET | Ficha | Lista de resultados (hasta 30). |
| `elegir_isla` | GET | Ficha | Isla validada (`fid`) con su campo oculto. |
| `mis_activas` | GET | Sesión | Indicador compacto o desglose de solitarias y grupales con `detalle=1`. |
| `crear` | POST | Ficha | Valida (§4.3), inserta y devuelve el aviso de éxito. |
| `listar` | GET | Sesión | Lista de tarjetas y paginador de Mis aventuras o del listado de Otras aventuras (`ambito`), filtrada. |
| `sin_narrador` | GET | Sesión | Bloque "Aventuras sin narrador" con su paginación ("Ver más"). |
| `contar_sin_narrador` | GET | Sesión | Contador de la pestaña Narradores & Narrados. |
| `narradores_disponibles` | GET | Sesión | Tarjetas de los narradores con oferta vigente. |
| `form_oferta` | GET | Ficha | Formulario de Discord (obligatorio) y mensaje (200 caracteres) dentro del bloque de narradores. |
| `ofrecerme_narrador` | POST | Ficha | Crea o actualiza la oferta del usuario. Devuelve su tarjeta de oferta. |
| `renovar_oferta` | POST | Ficha | Renueva la oferta propia. Devuelve su tarjeta de oferta. |
| `retirar_oferta` | POST | Ficha | Elimina la oferta propia. Devuelve el botón "Ofrecerme como narrador". |
| `ver` | GET | Sesión | Una tarjeta. |
| `form_postular` | GET | Ficha | Ventana de confirmación "¿Quieres narrar a este usuario?" con la casilla requerida. |
| `form_editar` | GET | Creador | Formulario de edición con los datos actuales, dentro de la tarjeta. Solo estados 0 y 4. |
| `editar` | POST | Creador | §4.6. Devuelve la tarjeta actualizada. `409` si el estado ya no es editable. |
| `buscar_temas` | GET | Narrador asignado o participante de solicitud aprobada | Typeahead de temas elegibles (§4.12), máximo 20, desde 3 caracteres y solo del tipo Aventura. Entregar sigue reservado al narrador. |
| `elegir_tema` | GET | Narrador asignado o participante de solicitud aprobada | Valida un tema, un enlace o un `tid` pegado (`opg_av_tid_de_entrada`) y devuelve el chip con el campo oculto `tid`. |
| `form_entrega` | GET | Narrador asignado | Formulario de entrega (o de corrección si fue rechazada), dentro de la tarjeta. Solo estado 1. |
| `enviar_entrega` | POST | Narrador asignado | `opg_ent_enviar`. Devuelve la tarjeta actualizada. `409` si la entrega ya está pendiente o entregada. |
| `form_renunciar` | GET | Narrador asignado | Ventana de confirmación "¿Quieres dejar de narrar esta aventura?" con la casilla requerida y un `textarea` de motivo (500, `required`). |
| `renunciar_narrador` | POST | Narrador asignado | Exige `confirmo=1` y `motivo` (hasta 500). Solo estados 0 y 1. La solicitud pasa a estado 4 y se avisa (ver abajo). |
| `postular_narrador` | POST | Ficha | Exige `confirmo=1`. Quita la tarjeta del bloque y avisa (ver abajo). |
| `quitar_chip` | GET | Sesión | Responde vacío; sirve para quitar un chip de participante sin JS. |
| `form_vincular` | GET | Narrador asignado o participante | Formulario de vinculación de solicitud aprobada. |
| `vincular_aventura` | POST | Narrador asignado o participante | `opg_av_vincular` (§4.7). Solo aprobada. Devuelve la tarjeta actualizada. |
| `form_participante` | GET | Sesión | Abre el buscador progresivo de participantes. |
| `quitar_narrador` | GET | Sesión | Restaura el buscador al quitar el chip de narrador. |
| `pedir_borrado` | POST | Creador | Devuelve la tarjeta actualizada. |

Parámetros de `listar`: `ambito` (`mias` u `otras`, por defecto `mias`),
`pagina`, `estado` (clave del catálogo), `rol` (`narrador` o `jugador`, solo en
Mis aventuras) y `uid` (narrador o participante, solo en Otras aventuras). Los
filtros se traducen a SQL como describe §4.10, con `LIMIT 20 OFFSET`. El estado
Pendiente de borrado no existe en las pestañas de jugadores.

La URL admite `?tab=otras` para abrir directamente una pestaña. Con `?id=N`, el
servidor decide en qué pestaña está la solicitud N (Mis aventuras si es creador,
narrador o participante; Otras aventuras en caso contrario; si el estado es 4 y
no es mía, Narradores & Narrados), abre esa pestaña, localiza la página
donde cae y resalta la tarjeta.

`postular_narrador`, una vez confirmada la ventana, ya no devuelve la tarjeta
actualizada en el bloque de sin narrador, porque al asignarse el narrador la solicitud deja de pertenecer a esa
vista. Responde con un fragmento vacío que quita la tarjeta, un aviso fuera de
banda en `#ta-avisos` ("Ahora narras la solicitud #N. La encuentras en Mis
aventuras") y `HX-Trigger: aventuraCambiada`, que refresca el contador de la
pestaña y el bloque.

`renunciar_narrador`, una vez confirmada la ventana, devuelve el resultado según
a quién pertenece ahora la solicitud. El servidor vuelve a evaluar el predicado
"mía" (§4.10) tras el `UPDATE`:

- Si ya no es del usuario (lo normal: era solo narrador), responde con un
  fragmento vacío que quita la tarjeta de Mis aventuras, un aviso fuera de banda
  en `#ta-avisos` ("Has dejado de narrar la solicitud #N. Vuelve a la lista de
  aventuras sin narrador. Avisa a los participantes por Discord") y
  `HX-Trigger: aventuraCambiada`.
- Si sigue siendo suya (era también la creadora, forma A), responde con la
  tarjeta actualizada en estado 4 y el mismo aviso.

En ambos casos `aventuraCambiada` refresca el contador de la pestaña Otras
aventuras y el bloque de sin narrador, donde la solicitud aparece de inmediato.

`buscar_fichas` busca por nombre o apodo y no sugiere ni marca a los narradores
disponibles: la lista del mercado es solo de consulta y el contacto se hace por
Discord. El narrador del formulario es el que el usuario ya pactó.

`elegir_ficha` y `elegir_isla` no confían en lo que escribió el usuario: validan
el `fid` en el servidor y devuelven el nombre leído de la base. El formulario
solo envía los `fid` de los campos ocultos.

### 5.3 `op/staff/consola_aventuras.php`

El acceso se comprueba al principio, con el mismo bloque que `consola_mod.php`:

```php
if (!opg_av_puede_gestionar($uid)) {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
    exit;
}
```

| Acción | Método | Permiso | Respuesta |
|---|---|---|---|
| (sin acción) | GET | Gestión | Página completa. `?tab=solicitudes\|entregas\|narradores` elige la sección (por defecto `solicitudes`). |
| `listar` | GET | Gestión | Filas compactas de la consola, resumen, paginador y filtros. Sin filtro de estado, usa `creado` y orden ascendente por antigüedad. Incluye nota de staff, idea, motivo de renuncia y `editado_at` en el detalle. |
| `buscar_personas` | GET | Gestión | Typeahead de `#ID`, UID o nombre; devuelve coincidencias de creador, narrador y participante con identificadores estables. |
| `form_denegar` | GET | Gestión | Formulario del motivo dentro de la fila. |
| `cambiar_estado` | POST | Gestión | Devuelve la fila actualizada. `motivo` obligatorio al denegar. Rechaza (`403`) si quien actúa es parte de la solicitud. |
| `form_editar` | GET | Gestión | Panel de edición de la fila. |
| `actualizar_datos` | POST | Gestión | Nota de staff (200), comentario (1000), inframundo, isla y día. Devuelve la fila. |
| `historial` | GET | Gestión | Historial de la solicitud (§4.9) como línea temporal en el panel accesible de historial. |
| `narradores` | GET | Gestión | Filas de todas las ofertas, vigentes y vencidas, paginadas. |
| `form_retirar_oferta` | GET | Gestión | Formulario del motivo (200, obligatorio) dentro de la fila de la oferta. |
| `retirar_oferta` | POST | Gestión | Borra la oferta, audita `retirar_oferta_admin` y devuelve vacío para quitar la fila. |
| `entregas` | GET | Gestión | Filas de la sección Entregas (lista por estado con conteos; se abre en `pendiente`), cada una con el enlace "Revisar y pagar" a `aventuras_recompensas.php?id=N`. No analiza ni paga. |
| `eliminar` | POST | `is_staff` | Solo estado 5. Devuelve vacío para quitar la fila. |

El permiso "Gestión" es `opg_av_puede_gestionar` (mod, staff o `is_narra`). Los
narradores usuarios sin esos permisos no entran. `opg_av_puede_resolver` aplica
ese mismo permiso aunque el gestor sea creador, narrador o participante.

El conteo por estado de `listar` sale de `opg_av_conteo_estados()`, una sola
consulta `SELECT estado, COUNT(*) ... GROUP BY estado` que usa `idx_estado`.
La respuesta completa construye un contexto `ca_resumen` con los conteos por estado
y por entrega. `ca_panel()` y `opg_av_v_c_tabs()` reciben el mismo contexto; no se
renderiza una banda general adicional ni se repite una consulta ya calculada.

`actualizar_datos` acepta solo los campos presentes y valida cada uno con las
mismas funciones que la creación. La nota de staff se guarda en
`comentario_staff` y se refleja en `_meta.staff_note`, igual que hoy, para que
la calculadora actual siga viendo el mismo dato. El comentario público se
refleja en `_meta.public_comment` por la misma razón.

Se agrega la tarjeta a `$consola_grupos` de `consola_mod.php`:

```php
array('fa-map-location-dot', 'Aventuras', 'Solicitudes de aventura: estado, motivos, notas e isla y día.', array(
    array('Gestionar', 'consola_aventuras.php', 'narra'),
)),
```

en el grupo que ya agrupa las herramientas de narración, con el permiso
`narra`. La acción de eliminar no se oculta con un permiso de tarjeta: la
consola la renderiza solo si `opg_av_puede_eliminar($uid)`, y el servidor la
vuelve a comprobar.

#### 5.3.A Rediseño operativo de la consola

Este diseño no requiere una tabla nueva. La búsqueda de participantes usa
`op_aventuras_participantes` (`idx_uid_peticion`), las ofertas continúan en
`op_narradores_disponibles` y las colas se derivan de los estados existentes.

**Consulta y URL.** Solicitudes acepta `estado`, `orden`, `persona`, `q` y
`pagina`. `q=#N` busca una solicitud exacta; `persona`
es el UID elegido por el typeahead. La condición por persona incluye
`pa.uid`, `pa.narrador_uid` y `EXISTS` sobre participantes. `orden=recientes` es
el predeterminado para cualquier estado; `orden=antiguas` se elige explícitamente.
Todos los controles se serializan en `HX-Push-Url` y el paginador conserva los
parámetros.

**Datos incompletos.** `opg_av_datos_faltantes($av)` devuelve claves estables de
`isla`, `dia`, `tier`, `comentario`, `narrador` y `participantes`. Se usa para el
filtro, el chip accesible y la acción principal. `opg_av_cambiar_estado`, dentro
de la transacción y después del bloqueo de fila, rechaza aprobar con `422` si la
lista no está vacía. La interfaz nunca es la única barrera.

**Composición.** La página añade `body.ca-pagina-consola`, encabezado compacto
con `h1` y `nav.ca-tabs` de tres columnas con iconos. Cada
resultado es un `article`; el resumen visible ocupa una fila estable y Detalles
contiene los textos largos. La acción primaria se calcula en la vista y las demás
se agrupan en `<details class="ca-mas-acciones">` sin cambiar permisos.

**Historial y foco.** El endpoint `historial` devuelve un diálogo/panel con
`role="dialog"`, título asociado y línea temporal. El control de apertura conserva
el elemento invocador; cerrar devuelve el foco. Tras filtros o paginación se enfoca
`N resultados · Página X de Y`. Los avisos siguen en regiones `aria-live`.

**Narradores.** `ca_panel('narradores')` pasa la página solicitada a
`opg_av_ofertas_admin_listar($pagina, $filtros)`. La consulta incluye
`frecuencia`, `tiers` y `estilo`; una consulta agrupada obtiene carga en curso y
finalizadas. La vista añade búsqueda, vigencia y `opg_av_v_paginador`. No se
ordena o pagina en PHP después de descartar filas.

**CSS.** Crear una hoja compartida del sistema de aventuras con tokens y patrones
de encabezado, pestañas, tarjetas, estados, chips, acciones, filtros, modal,
vacío, foco y paginación. `op_tablon_de_aventuras`,
`staff_consola_aventuras` y `staff_aventuras_recompensas` cargan esa hoja y
mantienen solo composición propia. No se cambia `header.html` ni se oculta el
banner global.

**Responsive.** Los contenedores aplican `width:100%`, `max-width`, `min-width:0`
y `box-sizing:border-box`. A 700 px las pestañas pueden formar dos filas, filtros
y metadatos se apilan y el panel de historial ocupa el ancho. Se prueba también
el ancho del documento (`scrollWidth <= clientWidth`), no solo una captura visual.

### 5.3.1 `op/staff/aventuras_recompensas.php`

Página nueva de pago de una entrega, con la misma estructura que
`consola_aventuras.php`: define `IN_MYBB` y `THIS_SCRIPT = 'aventuras_recompensas.php'`,
requiere `global.php`, `inc/config.php`, `op_functions.php` y las bibliotecas
`aventuras.php`, `aventuras_entregas.php` y `recompensas_aventura.php`, y comprueba
el permiso al principio con el mismo bloque de redirección (`op_redireccion`). No
reutiliza el código ni el template de `recompensasAventuras.php`; solo su lógica,
que vive en `recompensas_aventura.php` (§4.11). No define `display_errors`.

Entrada: `?id=<id de la entrega>` o `?tid=<tema>`, que resuelve la entrega vigente
con `tid_activo`. Sin entrega, muestra un aviso ("Las aventuras se entregan desde el
Tablón de aventuras") con un enlace a la consola, y no ofrece pagar un tema suelto.

| Acción | Método | Permiso | Respuesta |
|---|---|---|---|
| (sin acción) | GET | Gestión | Página completa: datos de la entrega, análisis con los valores por defecto y las acciones. |
| `analizar` | POST | Gestión | `opg_ent_analizar`: análisis del tema y recompensas por destinatario con los ajustes enviados. No escribe nada. |
| `pagar` | POST | Gestión | `opg_ent_pagar` (§4.12). `403` si quien actúa es parte de la solicitud; `422` con las razones si no se puede pagar. |
| `form_rechazar` | GET | Gestión | Formulario del motivo (500, obligatorio). |
| `rechazar` | POST | Gestión | `opg_ent_rechazar`. Devuelve la página actualizada. |
| `completar` | POST | Gestión | `opg_ent_completar`: termina una entrega interrumpida sin repetir pagos. |
| `xp_dudosa` | POST | Gestión | Resuelve un pago con experiencia dudosa: `aplicada` o `aplicar`. Auditado. |

Se aplica el contrato HTMX de §5.1: cabecera `HX-Request` en las acciones, `X-Post-Key`
en las que cambian datos, listas blancas por página y los mismos códigos de
respuesta. Una acción de esta página enviada a `consola_aventuras.php` o a
`tablon_de_aventuras.php`, y al revés, responde `404`.

### 5.4 Qué pasa exactamente al enviar una solicitud

**Antes de enviar (el formulario).**

1. El usuario abre `/op/tablon_de_aventuras.php`. El servidor comprueba la
   sesión y devuelve la página completa con la pestaña Mis aventuras. El
   usuario abre Solicitar aventura (`?tab=solicitud`). Si no tiene ficha, el
   formulario no se muestra y se explica por qué.
2. El indicador de activas se carga solo (`hx-get action=mis_activas`,
   `hx-trigger="load"`) y muestra "Solitarias n / 3" y "Total n / 4".
3. El usuario elige el rol. Cada cambio pide `campos_rol` y reemplaza los
   campos de participantes y narrador (en rol narrador no hay selector de
   narrador). En rol jugador elige "Tengo narrador" o "No tengo narrador": cada
   opción pide su fragmento, que muestra o el buscador del narrador pactado o el
   campo de idea ("vendiendo su idea", 500 caracteres) para los posibles
   narradores.
4. Al escribir en un buscador, HTMX pide `buscar_fichas` o `buscar_islas` con
   `hx-trigger="input changed delay:250ms"` y cancela la petición anterior. Al
   pulsar un resultado se pide `elegir_ficha` o `elegir_isla`; el servidor valida
   el `fid` y devuelve el chip (con su campo oculto) o la isla elegida. Los
   participantes se acumulan con `hx-swap="beforeend"`; el chip se quita con un
   `hx-get` a `quitar_chip` sobre `closest .opg-chip` con `hx-swap="outerHTML"`
   (el servidor responde vacío), sin JS propio.
5. El día es un `<input type="number" min="1" max="90">` con la etiqueta fija
   "Verano 725". El comentario es un `textarea` con `maxlength="1000"` y una
   indicación fija del límite.

**Al pulsar "Enviar solicitud".**

6. HTMX envía un `POST` a `?action=crear` con los campos del formulario y las
   cabeceras `HX-Request` y `X-Post-Key`. El botón queda deshabilitado
   (`hx-disabled-elt`) mientras viaja.
7. El servidor comprueba, en orden: que es una petición HTMX, que hay sesión,
   que el `post_code` es válido y que el usuario tiene ficha. Si falla algo, 403.
8. `opg_av_validar_solicitud` valida todo según §4.3: forma A o B, participantes
   existentes y sin repetir, isla válida, día de 1 a 90, comentario de hasta 1000
   caracteres y antirrebote. Los nombres se leen de `mybb_op_fichas`.
9. **Si hay errores:** responde `422` con un `.aviso.err` que lista cada error, y
   `HX-Retarget: #ta-avisos`. El formulario conserva lo escrito y nada se guarda.
10. **Si todo es válido:** inserta una fila en `mybb_op_peticionAventuras` con
    `uid` del creador, `jugadores_json`, narrador (si hay), `isla_fid`, `dia`,
    `comentario_publico`, `inframundo`, `num_jugadores`, `created_at` y
    `estado` 0 (con narrador) o 4 (sin narrador). Las columnas de dificultad
    quedan en sus valores por defecto. La solicitud recibe un `id` posterior al
    mayor existente. Escribe la fila de auditoría `crear` y una línea en
    `log_audit`.
11. Responde `200` con el aviso "Solicitud #N enviada" y un enlace a Mis aventuras, y
    la cabecera `HX-Trigger: aventuraCreada`. El formulario se reemplaza por ese
    aviso y un botón "Enviar otra" (`hx-get` a la pestaña Solicitud).
12. El evento `aventuraCreada` refresca el indicador de activas, el contador de la
    pestaña Narradores & Narrados, el bloque de sin narrador y las listas, si están
    cargadas.

**Después (lo que ocurre con la solicitud).**

- **Visibilidad.** La solicitud aparece de inmediato en Mis aventuras de su
  creador, su narrador y sus participantes, en Otras aventuras de todos los demás
  usuarios con sesión (sin la nota de staff) y en la consola de gestión.
- **Cuenta como activa desde ya.** Estados 0 y 4 cuentan para el límite de 3
  solitarias y 4 en total de cada participante, aunque aún no esté aprobada.
- **Sin narrador (estado 4).** Se muestra de forma destacada en el bloque
  "Aventuras sin narrador" de la pestaña Narradores & Narrados de cualquier usuario con
  ficha que no sea el creador ni participante, que ve "Postularme como
  narrador". Al aceptar, pasa a estado 0 y el narrador queda asignado; dos
  postulaciones simultáneas no se pisan. Los narradores que se ofrecieron en el
  mercado también aparecen sugeridos a quien crea la solicitud.
- **Revisión (estado 0).** Un mod, narrador o staff abre la consola y aprueba o
  deniega. Al aprobar, el servidor exige narrador asignado y vuelve a validar el
  límite de cada participante; si alguno lo excede, la aprobación falla con un
  aviso que nombra a quién y con cuántas aventuras. Al denegar, el motivo es
  obligatorio y queda visible en Mis aventuras para que el usuario sepa por qué y
  envíe otra solicitud.
- **Aprobada (estado 1).** El narrador asignado vincula la URL del hilo. Al
  terminar, la consola la pasa a finalizada (estado 3) y deja de contar como
  activa.
- **El creador** puede pedir el borrado en cualquier momento (estado 5); la
  solicitud deja de contar como activa y el staff decide si la elimina.
- **Qué no ocurre.** No se envía ningún MP, aviso del foro ni mensaje de Discord,
  y no se crea ningún hilo (fuera de alcance). El narrador y los participantes se
  enteran porque la solicitud está en su pestaña Mis aventuras, con el filtro "Soy
  narrador" o "Soy jugador".

### 5.5 Qué pasa exactamente al vincular, entregar y pagar una aventura

**Vincular (opcional, antes de entregar).**

1. Creado el tema de la aventura, el narrador pulsa "Vincular aventura" en su
   tarjeta (estado 1). La tarjeta carga `form_vincular` con un campo de tema.
2. Puede escribir en el typeahead (a partir de 3 caracteres; solo temas del tipo
   Aventura), o pegar el enlace o el `tid`. El servidor lo valida igual en los tres
   casos (§4.7) y guarda `aventura_url = showthread.php?tid=N`.

**Entregar (el narrador).**

3. El narrador pulsa "Entregar aventura" y la tarjeta se reemplaza por el
   formulario (`form_entrega`). El campo del tema aparece **ya relleno** si había
   vinculado el tema (y sigue siendo válido).
4. El formulario muestra, no editables, el número de petición, los participantes
   por nombre de personaje, el narrador con su nivel (`nivelnarrador`) y el tier de
   la solicitud. Pide el tema (si no estaba vinculado, o para cambiarlo), el
   resumen (2000 caracteres) y la reputación (Positiva o Negativa).
5. "Enviar entrega" hace `POST enviar_entrega`. El servidor valida (§4.12), guarda la
   entrega como pendiente y, si el tema es distinto del vinculado, actualiza la
   vinculación. La tarjeta pasa a mostrar "Entrega pendiente de revisión".

**Revisar y pagar (el staff).**

6. En la consola, la sección Entregas (con un contador de pendientes) lista la
   entrega y el staff pulsa "Revisar y pagar", que abre
   `aventuras_recompensas.php?id=N`. La página pide el análisis (`analizar`, sin
   ajustes): el servidor lee el tema, usa el narrador guardado en la entrega, cuenta los posts
   válidos de cada usuario, calcula los porcentajes con el tier de la solicitud y
   las recompensas de cada destinatario, y muestra advertencias.
7. Si hace falta, ajusta el tier (opcional), el extra por jugadores, la variación de
   posts de cada usuario, la reputación de cada jugador y a quién incluir; cada
   cambio vuelve a pedir el análisis, y el servidor recalcula todo.
8. Si algo no cuadra, "Rechazar" con un motivo: la entrega pasa a rechazada, el tema
   queda libre y el narrador la ve con el motivo para corregirla y reenviarla.
9. Si cuadra, "Pagar" (`pagar`), con la casilla de confirmación si no se
   cumplen los posts mínimos. El servidor sigue los pasos de §4.12 para cada
   destinatario, una vez cada uno; al terminar publica el mensaje de "RECOMPENSAS
   PROCESADAS" en el tema, lo cierra, deja la entrega entregada y pasa la solicitud
   a estado 3. La página muestra lo que recibió cada uno.

**Después.** Los pagos aparecen en el log `[Entregas]` de siempre, la solicitud sale
del conteo de aventuras activas de todos (estado 3), el tema queda cerrado con el
mensaje, y la tarjeta del narrador y de los participantes dice "Recompensas
entregadas el ...". Si alguien abre ese mismo tema en la herramienta antigua
("Pagar" de la calculadora), esta lo rechaza por estar cerrado.

## 6. Interfaz y estilo

Las dos páginas son páginas de juego de `/op/`: usan el marco de tres fondos,
cargan `{$headerinclude}` (que ya trae `opg-tokens.css` y `opg-components.css`) y
no enlazan CSS propio de tokens. Cargan además `jscripts/htmx/htmx.min.js` con
`defer`. Ancho: `.thirdBackground` se redefine en la página como
`width: 100%; max-width: 1030px;`, con `text-align: left` porque el tema centra
el texto, y padding reducido en `@media (max-width: 700px)`.

```html
{$header}
<div class="indice ta-tablon">
<div class="mainBackground">
<div class="secondBackground">
<div class="thirdBackground">
  <div><a class="opg-volver" href="...">&larr; ...</a></div>
  <div>
    <div class="barra-op bbox ta-titulo"><span class="barra-texto-op">Tablón de aventuras</span></div>
    <div class="barra-espacio-op bbox ta-descripcion">...</div>
  </div>
  ...
</div></div></div></div>
{$footer}
```

### 6.1 Reglas visuales aplicadas

| Elemento | Decisión |
|---|---|
| Título de la página | Encabezado compacto de herramienta con icono, un único `h1`, apoyo breve y línea naranja. El Tablón y la Consola comparten el patrón; no se usa una `.barra-op` pesada. |
| Tipografía | `moonGetHeavy` solo en el título, títulos de sección y de tarjeta y cifras (contadores de activas). Todo lo demás en Inter. Contorno negro solo sobre fondos de color; sobre crema va en `--opg-ciruela` y sin contorno. |
| Campos | `.af-field` agrupados en filas `display: flex; flex-wrap: wrap; gap: var(--opg-espacio-4)` (`.ta-row`), nunca en grilla de columnas fijas. El `textarea` del comentario lleva `flex-basis` mayor (260px). |
| Acción principal | Un solo `.btn-op.btn-op--primario` por formulario o fila operativa: Enviar, Aprobar, Completar datos o Revisar y pagar según contexto. |
| Acciones secundarias | `.opg-chip` dentro de `Más acciones`; denegar y eliminar usan tratamiento peligroso y confirmación cuando corresponda. |
| Listado | `article` compacto por solicitud, entrega u oferta, con acento por estado (§6.4), resumen estable y detalle expandible. Sin tarjetas anidadas. |
| Vacío | `.opg-vacio` ("No hay solicitudes con estos filtros"). |
| Avisos | `.aviso.ok` / `.aviso.err` para el resultado de una acción. |
| Typeahead | `.opg-buscar-caja`, `.opg-resultados`, `.opg-resultado`, `.opg-resultado-vacio`, y `.opg-dropdown-abierto` en `.thirdBackground` mientras el desplegable está abierto. |
| Iconos | Font Awesome 6 con `fontawesome.min.css` y `solid.min.css` de cdnjs 6.4.0; nunca `all.min.css`. |
| Enlaces | `a.clase:link, a.clase:visited` para los enlaces con estilo propio (por ejemplo "Ver aventura →"). |
| Movimiento | `transition` con `var(--opg-rapido)`. Hover de `.opg-card` heredado de `opg-tokens.css`. |
| Responsive | Sin desbordamiento a 375, 700, 1030 y 1440 px. Contenedores con `min-width:0`; filas y pestañas cambian de composición, no solo reducen padding. |

No se usan degradados que crucen de matiz, texto en degradado, sombras difusas
ni `--opg-recorte-rasgado`. Las tarjetas usan la sombra desplazada
(`--opg-sombra-offset`) de `.opg-card`.

### 6.2 Patrones HTMX

| Necesidad | Patrón |
|---|---|
| Cambio de pestaña | `<a href="?tab=otras" hx-get="?action=tab&tab=otras" hx-target="#ta-panel" hx-push-url="?tab=otras">`, uno por pestaña (`mias`, `otras`, `solicitud`). Con JS desactivado o al abrir el enlace, `?tab=` renderiza la página completa con esa pestaña. |
| Contador de la pestaña | `<span hx-get="?action=contar_sin_narrador" hx-trigger="load, aventuraCambiada from:body, aventuraCreada from:body" hx-swap="innerHTML">`. |
| Bloque de sin narrador | `<section id="ta-sin-narrador" hx-get="?action=sin_narrador" hx-trigger="load, aventuraCambiada from:body, aventuraCreada from:body">`. "Ver más" con `hx-get` a `sin_narrador&pagina=N` y `hx-swap="beforeend"`. |
| Ofrecerme como narrador | El botón carga `form_oferta` dentro del bloque de narradores con `hx-get`. El formulario envía `ofrecerme_narrador` con `hx-post` y reemplaza la tarjeta propia. "Renovar" y "Retirar oferta" usan `hx-post`; retirar añade `hx-confirm` y los tres disparan `ofertaCambiada`. |
| Lista de narradores | `<div id="ta-narradores" hx-get="?action=narradores_disponibles" hx-trigger="load, ofertaCambiada from:body">`. |
| Typeahead | `<input name="q" hx-get="?action=buscar_fichas&destino=participante" hx-trigger="input changed delay:250ms, focus" hx-target="#ta-res-part" hx-sync="this:replace">`. Al elegir un resultado, `hx-get` a `elegir_ficha`. |
| Filtros de Mis y Otras aventuras | `<form hx-get="?action=listar" hx-trigger="change, submit" hx-target="#ta-lista" hx-push-url="false">` con un campo oculto `ambito` (`mias` u `otras`). Los chips de filtro son `<label>` con `<input type="radio">`, uno por grupo, con una primera opción "Todas" marcada por defecto. |
| Paginación | Enlaces del paginador con `hx-get` a `listar&pagina=N`, `hx-target="#ta-lista"`. |
| Acción por tarjeta | `<button hx-post="?action=postular_narrador&id=N" hx-target="closest .opg-card" hx-swap="outerHTML" hx-disabled-elt="this">`. Pedir borrado y eliminar añaden `hx-confirm`. |
| Formularios inline | "Vincular" y "Denegar" cargan su formulario con `hx-get` dentro de la tarjeta o fila y lo envían con `hx-post`. |
| Indicador de activas | `hx-get="?action=mis_activas"`; se refresca con `aventuraCreada` y `aventuraCambiada`. Con `detalle=1`, devuelve el desglose en Mis; no modifica el botón de envío. |
| Carga | La clase `.htmx-request` muestra una barra o un texto "Cargando..." (`.ta-cargando`), sin spinner de terceros. |
| Errores 403/404/5xx | La configuración `responseHandling` (§5.1) inserta el aviso que devuelve el servidor en `#ta-avisos`. No hay manejador de JS. |
| Ventana de confirmación | "Postularme como narrador" (y "Dejar de narrar", con `form_renunciar` y `renunciar_narrador`) hace `hx-get` a `form_postular&id=N` con `hx-target="#ta-modal"`. El fragmento es un `<div class="ta-modal" role="dialog" aria-modal="true" aria-labelledby="...">` sobre un velo fijo con la casilla requerida "Confirmo que me puse de acuerdo con {creador}" y dos botones: Cancelar (`hx-get` a una acción que devuelve vacío sobre `#ta-modal`) y "Confirmar postulación" (`hx-post` a `postular_narrador`). El botón de confirmar solo se envía con la casilla marcada (`required` en un `<form>`) y el servidor lo vuelve a comprobar. Sin JS no hay Esc ni trampa de foco; el velo y Cancelar cierran la ventana. |
| Chip de participante | La "x" es un `<button hx-get="?action=quitar_chip" hx-target="closest .opg-chip" hx-swap="outerHTML">`. |
| Doble envío | `hx-disabled-elt="this"` en todo botón que envía, más el antirrebote del servidor (§4.3). |

Cada región que HTMX actualiza lleva `aria-live="polite"` (listas, avisos e
indicador). Los resultados de typeahead son botones enfocables y se pueden
recorrer con el teclado.

### 6.3 `tablon_de_aventuras.php`: pestañas

Cuatro pestañas: Mis aventuras, Solicitar aventura, Narradores & Narrados y
Otras aventuras, en una barra segmentada de enlaces con iconos, en ese orden. Al
abrir la página sin `?tab=` se muestra **Mis aventuras**. La pestaña activa usa
fondo blanco y subrayado naranja; la inactiva usa el crema del panel. La pestaña
Narradores & Narrados lleva junto a su título un
contador (`.opg-chip` pequeño, en `--opg-morado-cta` con texto blanco) con el
número de aventuras sin narrador, que solo se muestra si es mayor que cero. No
existe ningún marcado ni endpoint de Consola en este template.

**Solicitar aventura**, de arriba abajo:

1. Selector de rol: dos `.opg-chip` conmutables ("Soy el narrador", "Soy
   jugador") que piden `campos_rol`.
2. Fila `.ta-row`: buscador de participantes (`.af-field`) con la lista de
   elegidos como `.opg-chip` con una "x" para quitar. En el rol jugador el
   creador aparece como chip fijo no removible.
3. En el rol jugador, un selector de dos `.opg-chip` conmutables, "Tengo
   narrador" y "No tengo narrador":
   - con narrador, un buscador de fichas (`.af-field`) para nombrar al que ya
     pactó;
   - sin narrador, un `.af-field` con un `textarea` (500 caracteres, con la
     indicación "¿Cómo quieres que te narren? ¿Qué te gustaría que pasara? ¿Qué tipo de narración o narrador buscas? ¿Con qué frecuencia piensas postear?")
     para vender su idea.
   En el rol narrador, texto fijo con el nombre del usuario.
4. Fila `.ta-row`: buscador de isla (`.opg-buscar-caja`) arriba y etiqueta Isla con chips debajo, el campo numérico de
   día, con la etiqueta fija "Verano 725" al lado, y un `<select>` de tier del 1 al
   10 con la opción de cada tier rotulada "Tier N (M posts por participante)", con
   M de `OPG_REC_POSTS_TIER`.
5. `.af-field--ancho-completo`: comentario público obligatorio, con placeholder
   "Describe brevemente de qué trata la aventura (hasta 1000 caracteres)."
6. Casilla de inframundo.
7. Indicador de aventuras activas: dos cifras en `moonGetHeavy` ("Solitarias
   n / 3", "Total n / 4"), con el color de `--opg-rojo-error` cuando se alcanza
   el tope.
8. `#ta-avisos` y el `.btn-op--primario` "Enviar solicitud", habilitado aunque
   se alcancen los límites del usuario para permitir su rol de narrador. El servidor
   rechaza el envío si cualquier participante excedería su cupo; también valida al aprobar.

**Mis aventuras**, de arriba abajo:

Antes del encabezado: banda de estadísticas filtradas. A continuación, Mis activas
con total / 4 y listado de solitarias / 3 y grupales, independiente de los filtros.

1. Fila de filtros con `.opg-chip`: estado (Creadas, Pendiente, Aprobadas,
   Denegadas, Finalizadas) y rol ("Soy narrador", "Soy jugador"). Son dos grupos
   de un solo elegido, cada uno con su opción "Todas"; se pueden combinar entre
   sí.
2. Lista de `.opg-card`. El acento de la franja depende del estado (§6.5). Cada
   tarjeta muestra, con `moonGetHeavy`, el id y la isla, y en Inter el resto:
   creador, narrador, participantes, "Verano 725, día N" (o "sin día"), "Tier N",
   comentario público, la nota de idea si la hay (bloque con etiqueta "Idea"), el
   motivo de renuncia mientras esté sin narrador (bloque con etiqueta "El
   narrador anterior se salió" y borde `--opg-morado`),
   un `.opg-chip` "Inframundo" si aplica, el enlace a la aventura y, en
   denegadas, el motivo en un bloque con borde `--opg-rojo-error`.
3. Acciones por tarjeta según rol, como `.opg-chip`: "Vincular aventura"
   (narrador asignado o participante, estado 1), "Dejar de narrar" (narrador asignado, estados 0
   y 1; abre la ventana de confirmación con el motivo obligatorio), "Editar"
   (creador, estados 0 y 4) y "Pedir borrado" (creador, no en estado 5).
   "Vincular aventura" (narrador asignado o participante, estado 1) carga `form_vincular&id=N`
   dentro de la tarjeta con el campo de tema (typeahead, enlace o `tid`).
   "Entregar aventura" (narrador asignado, estado 1) carga `form_entrega&id=N`
   dentro de la misma tarjeta (`hx-target="closest .opg-card"`,
   `hx-swap="outerHTML"`). Ambos usan el mismo campo de tema: un
   `.af-field` con `.opg-buscar-caja` y `hx-trigger="input changed delay:250ms"` sobre
   `buscar_temas`, que ofrece nombre, foro y fecha del tema solo a partir de 3
   caracteres (con menos muestra "Escribe al menos 3 caracteres"), y que también
   acepta pegar un enlace o un `tid`, que `elegir_tema` valida. En la entrega, el
   tema vinculado viene precargado como chip. Además, un `.af-field` con el
   `textarea` del resumen (2000 caracteres), dos `.opg-chip` conmutables "Positiva"
   y "Negativa" para la reputación, y un `.btn-op--primario` "Enviar entrega"
   (`hx-post` a `enviar_entrega`, con `hx-disabled-elt`). Arriba, en texto plano,
   los datos que no se editan: petición, participantes, narrador con su nivel y
   tier. Una entrega pendiente se muestra como un `.opg-chip` "Entrega pendiente de
   revisión"; una rechazada, con el bloque de motivo (borde `--opg-rojo-error`) y el
   botón "Corregir y reenviar"; una entregada, con "Recompensas entregadas el ...".
   "Editar" carga `form_editar&id=N` con `hx-target="closest .opg-card"` y
   `hx-swap="outerHTML"`: la tarjeta se reemplaza por el mismo formulario de
   Solicitar aventura, con los datos actuales (`opg_av_v_form_solicitud` en modo
   edición), sin selector de rol, con un `.btn-op--primario` "Guardar cambios" y
   un `.opg-chip` "Cancelar" que vuelve a pedir la tarjeta (`ver&id=N`). Al
   guardar, `editar` devuelve la tarjeta actualizada y dispara
   `aventuraCambiada`. Si el servidor responde `409`, muestra el aviso y la
   tarjeta tal como está ahora (ya aprobada, por ejemplo).
4. Paginador con `.opg-chip` ("Anterior", "Siguiente") y el número de página.
5. Estado vacío:
   - **Sin ninguna aventura** (el usuario no tiene ninguna solicitud en la que sea
     creador, narrador o participante): en lugar de la lista, una `.opg-card`
     con acento `--opg-naranja`, un texto ("Todavía no participas en ninguna
     aventura. ¡Pide una!") y un solo `.btn-op--primario` "Solicitar una
     aventura" que enlaza a `?tab=solicitud` con `hx-get` a la pestaña. Se
     decide con un `COUNT` sin filtros, no con la lista filtrada.
   - **Con aventuras pero sin resultados para los filtros:** solo
     `.opg-vacio` ("No hay aventuras con estos filtros").

**Narradores & Narrados** reúne los siguientes dos bloques (ofertas primero):

1. **Bloque "Aventuras sin narrador"**, el elemento que más debe llamar la
   atención de la pestaña. Se destaca sin romper `style.md`:
   - una cabecera con fondo `--opg-morado-cta` (el morado de llamada a la acción,
     que `style.md` reserva para lo urgente), texto `moonGetHeavy` blanco con
     contorno negro y el número de aventuras en una cifra grande;
   - cuerpo en `--opg-crema-clara` con borde de tinta de 2px y sombra desplazada
     (`--opg-sombra-offset`), sin degradados;
   - las tarjetas internas son `.opg-card` con acento `--opg-morado`, con el
     mismo contenido que en Mis aventuras y, en primer plano, la nota de idea del
     creador (bloque con etiqueta "Idea", en Inter) y, si lo hay, el motivo del
     narrador anterior ("El narrador anterior se salió: ..."), de modo que quien
     se postula sepa por qué quedó libre; en cada una el `.opg-chip`
     "Postularme como narrador", que abre la ventana de confirmación;
   - paginación "Ver más" de 10 en 10, de la que más tiempo lleva esperando a la
     más reciente;
   - con cero solicitudes, un `.opg-vacio` ("Ahora mismo todas las aventuras
     tienen narrador") en lugar de desaparecer.
   No se usan `.opg-en-curso` ni `--opg-recorte-rasgado`: uno de los dos más el
   color alcanzaría para llamar la atención, pero la guía pide usarlos solo en
   uno o dos paneles destacados y este ya lleva la cabecera morada.
2. **Bloque "Narradores disponibles"**, con título de sección en `moonGetHeavy`
   con raya de acento (patrón de `staff_consola_mod`):
   - la acción primaria de la pestaña, un solo `.btn-op--primario` "Ofrecerme
     como narrador" (si el usuario ya está en la lista, en su lugar aparece su
     propia tarjeta de oferta con "Renovar" y "Retirar oferta" como `.opg-chip`,
     y los días que le quedan o la marca "Vencida");
   - el formulario de la oferta se abre dentro del propio bloque, no en una
     página aparte: un `.af-field` "Tu usuario de Discord" (obligatorio, 64
     caracteres) y otro "Mensaje" (opcional, 200 caracteres);
   - una grilla flexible de `.opg-card` por narrador, con acento
     `--opg-verde-exito`: nombre de ficha en `moonGetHeavy`, mensaje en Inter,
     "Disponible desde ...", "En curso: n" y "Finalizadas: n" en cifras
     pequeñas, y el usuario de Discord como texto seleccionable, con el icono de
     Font Awesome `fa-brands fa-discord` solo si se enlaza `brands.min.css`. No
     hay botón de elegir ni enlace de contacto: quien busca narrador copia el
     Discord y lo contacta fuera de la herramienta;
   - `.opg-vacio` ("Nadie se ha ofrecido como narrador todavía") si la lista está
     vacía.
**Otras aventuras** muestra estadísticas filtradas antes del encabezado y:

1. **Listado "Otras aventuras"**:
   - fila de filtros con `.opg-chip`: estado (Creadas, Aprobadas, Denegadas,
     Finalizadas) y un `.af-field` para buscar por UID;
   - lista de `.opg-card`, igual que en Mis aventuras pero sin acciones de
     propietario (no hay "Vincular aventura" ni "Pedir borrado");
   - paginador y `.opg-vacio` ("No hay aventuras con estos filtros").

Ninguna `.opg-card` va anidada dentro de otra: las tarjetas del bloque 1 y del
bloque 2 se apoyan directamente en sus bloques, que son contenedores con título,
no tarjetas.

### 6.4 `consola_aventuras.php`

Es una herramienta, no un menú: el título va en `.barra-op`, no en el header con
halftone. Lleva `.opg-volver` hacia `consola_mod.php`. Tiene dos secciones, en una
barra de dos enlaces con el estilo de pestañas de §6.3 (`?tab=solicitudes`, por
defecto, y `?tab=narradores`, cargadas con `hx-get` y `hx-push-url`).

**Solicitudes**

- **Cola de revisión.** Fila de filtros con `.opg-chip` por estado, cada uno con
  su conteo ("En revisión (3)", "Aprobadas (12)"...), incluido "Pendientes de
  borrado", y un `.af-field` de UID. Se abre con "En revisión" marcado. Los conteos
  se refrescan con `HX-Trigger` tras cada acción.
- Una fila por solicitud, con las filas alternando `#ffe59b` y `#ffeed2` (patrón
  de listado de `style.md` §3), en vez de tarjetas, porque la consola es de
  trabajo denso: id, isla, día, narrador, participantes, estado y nota de staff.
  Debajo, en Inter y más pequeño, la nota de idea del creador, el motivo de
  renuncia si lo hay y, si el creador la modificó, un `.opg-chip` "Editada el
  ..." en `--opg-morado-texto` (no solo color: lleva texto).
- Acciones por fila con `.opg-chip`: Aprobar, Denegar, Finalizar, Editar datos,
  Historial y Eliminar (`.chip-peligro`, solo si `opg_av_puede_eliminar`).
  Aprobar, Denegar y Finalizar exigen `opg_av_puede_resolver`; ser creador,
  narrador o participante no las deshabilita. El servidor comprueba el permiso.
- **Denegar** pide `form_denegar`: un `.af-field` con un `textarea` de motivo
  (500 caracteres, obligatorio, `required`) y un `.btn-op--sm.btn-op--peligro`
  "Confirmar denegación". El servidor rechaza igualmente un motivo vacío.
- **Aprobar** muestra, si el servidor devuelve `422`, un `.aviso.err` con la
  lista: "Nombre: tiene n solitarias y n en total".
- **Editar datos** pide `form_editar`: nota de staff (200), comentario (1000),
  inframundo, isla y día (para completar solicitudes antiguas), y un único
  `.btn-op--primario` "Guardar". No incluye participantes ni narrador: esos los
  corrige el creador.
- **Historial** pide `historial&id=N` y despliega bajo la fila una lista en Inter:
  fecha, usuario y acción, con el resumen de lo que cambió y el motivo cuando
  existe. Sin registros, `.opg-vacio` ("Sin historial anterior a la migración").
- **Eliminar** usa `hx-confirm` y solo se ofrece en filas en estado 5.

**Entregas**

- Tercera pestaña de la consola (`?tab=entregas`), con un contador de pendientes.
  Filtros `.opg-chip` por estado con conteo (Pendientes, Rechazadas, Entregadas);
  se abre en Pendientes.
- Una fila por entrega, con el patrón de filas alternas: número de petición, tema
  (enlace al hilo), narrador con su nivel, participantes, "Tier N", una marca
  "Inframundo" si aplica y, si es un reenvío, los intentos. A la derecha, un
  `a.opg-chip` "Revisar y pagar" que enlaza a `aventuras_recompensas.php?id=N`
  (con `a.opg-chip:link, a.opg-chip:visited`). Las entregadas muestran la fecha y
  el mismo enlace como "Ver detalle". Esta sección no analiza ni paga.
- `.opg-vacio` ("No hay entregas pendientes") si la lista está vacía.

**Narradores disponibles**

- Una fila por oferta, con el mismo patrón de filas alternas: nombre de ficha,
  Discord (texto), mensaje, "Ofrecido el ..." y "Renovado el ...". Las ofertas
  vencidas llevan un `.opg-chip` "Vencida" en `--opg-gris-texto`.
- Acción **Retirar oferta** (`.opg-chip.chip-peligro`): pide `form_retirar_oferta`,
  un `.af-field` con un `textarea` de motivo (200 caracteres, obligatorio,
  `required`) y un `.btn-op--sm.btn-op--peligro` "Confirmar retirada". El
  servidor rechaza un motivo vacío o de más de 200 caracteres. Al confirmar, la
  fila desaparece.
- `.opg-vacio` ("No hay ofertas de narrador") si la lista está vacía.

### 6.4.1 `aventuras_recompensas.php`

Es una herramienta, no un menú: cabecera compacta con el nombre del tema como
título y los números de entrega y petición como metadatos, `.opg-volver` hacia la sección Entregas de la consola, marco de tres
fondos con `width: 100%; max-width: 1030px; text-align: left`, prefijo de clases
`ar-` (a añadir a la tabla de prefijos de `style.md`) y HTMX con la misma
configuración de §5.1. Los bloques, de arriba abajo:

1. **Entrega**, sin tarjeta anidada y con acento `--opg-naranja`: tema
   (enlace), "Tier N" y la marca de inframundo como `.opg-chip`, el narrador con su
   nivel, los participantes, la reputación indicada por el narrador y el resumen en
   un bloque en Inter (escapado). Si es un reenvío, "Intento N" y, en un bloque con
   borde `--opg-rojo-error`, el motivo del rechazo anterior.
2. **Criterios y actividad**: una cuadrícula compacta de ajustes (`.af-field`): `<select>` de tier rotulado "Tier N
   (M posts por participante)", con el de la solicitud marcado "por defecto";
   `<select>` de extra por jugadores (0 a 50); número de participantes para los
   mínimos. La misma sección muestra posts totales, válidos, mínimo requerido y
   estado "Cumple" o "No cumple". Cada cambio actualiza automáticamente el panel
   mediante HTMX. (El botón "Actualizar vista previa" se quitó: la vista previa ya se recalcula sola al cambiar cualquier ajuste.)
3. **Destinatarios y previsualización**: una fila por destinatario, narradores
   primero, con el patrón de filas alternas (`#ffe59b`, `#ffeed2`): casilla
   "Incluir", nombre de ficha, rol, posts válidos, porcentaje y recompensas
   calculadas (experiencia, nikas, berries, reputación, cofre, kuros por exceso).
   A la derecha, el ajuste propio de cada uno: variación de posts (-100 a 100) y,
   para jugadores, `<select>` de reputación (positiva, negativa, ninguna). Los
   avisos propios de una fila (sin ficha, nivel de narrador desconocido, escribió
   sin estar en la solicitud, sin posts) salen en la misma fila con texto.
4. **Validación y acciones**: advertencias y bloqueos aparecen en un único resumen,
   indicando cuáles impiden pagar. Si no se cumplen los posts mínimos, una casilla "Confirmo pagar
   aunque no se cumplen los posts mínimos". El único `.btn-op--primario`: "Pagar"
   abre un diálogo propio que resume la acción; la confirmación dentro del diálogo
   ejecuta `pagar`. El botón queda deshabilitado, en `--opg-gris-bloqueado` y sin
   hover, mientras existan bloqueos, y muestra el motivo al lado. Una barra de
   acciones adherente mantiene visibles actualizar, pagar y rechazar. Un
   `.opg-chip.chip-peligro` "Rechazar" carga `form_rechazar` con un `textarea` de
   motivo (500, obligatorio) y un `.btn-op--sm.btn-op--peligro` "Confirmar rechazo".
   El gestor puede usar ambas acciones aunque forme parte de la solicitud.
6. **Entrega interrumpida**: la lista de destinatarios con "Pagado" o "Falta" (texto,
   no solo color), el botón "Completar entrega" (`completar`) y, si hay un pago con
   experiencia dudosa, las dos opciones "La experiencia ya se aplicó" y "Aplicar la
   experiencia" (`xp_dudosa`).
6. **Entrega ya pagada**: lo recibido por cada destinatario, el tier y la fecha, sin
   acciones; los controles de los bloques 3 a 5 no se renderizan.

Las etiquetas de campo usan mayúsculas normales, se reducen bordes anidados y, a
700 px o menos, cada fila de destinatario se convierte en una tarjeta vertical.

### 6.5 Acento por estado

El acento de cada tarjeta o franja usa la paleta de `style.md` §2, no el naranja
para todo (el naranja queda para la marca y el título):

| Estado | Acento |
|---|---|
| 0 Creado | `--opg-naranja` |
| 1 Aprobado | `--opg-verde-exito` |
| 2 Denegado | `--opg-rojo-error` |
| 3 Finalizado | `--opg-gris-texto` |
| 4 Pendiente narrador | `--opg-morado` |
| 5 Pendiente de borrado | `--opg-gris-bloqueado` |

El estado nunca se comunica solo por color: cada tarjeta lleva su etiqueta de
texto ("Aprobado", "Denegado"...). El texto sobre crema usa
`--opg-morado-texto`, `--opg-marron` o `--opg-gris-texto`, que cumplen contraste
AA; `--opg-morado` y `--opg-naranja` se usan en bordes y franjas, no como texto
de cuerpo. El botón `.btn-op--primario` conserva su borde y su sombra de texto
negros, que son los que hacen legible el texto blanco sobre naranja.

## 7. Seguridad

- Cada acción comprueba sesión y rol con la biblioteca antes de leer o escribir.
- Las acciones que cambian datos exigen `HX-Request` y el `post_code` en
  `X-Post-Key`. La consola y los jugadores usan el mismo token.
- Todos los valores van escapados o convertidos a entero. El texto de usuario se
  escapa en `aventuras_vistas.php` con `htmlspecialchars(..., ENT_QUOTES)`; es el
  único lugar que genera HTML de datos, y nunca se concatena texto de usuario
  dentro de un atributo `hx-*`.
- HTMX se configura con `allowEval: false`, `allowScriptTags: false` y
  `selfRequestsOnly: true`: un fragmento no puede ejecutar código ni pedir otro
  origen. Los fragmentos nunca incluyen `<script>`.
- El servidor ignora cualquier `uid`, `narrador_uid`, `estado` o `nombre` que
  mande el cliente para identificar al creador, al narrador o a los jugadores.
- `historyCacheSize: 0` y `Cache-Control: no-store` evitan que el historial del
  navegador guarde fragmentos con datos, incluida la nota de staff.

## 8. Rendimiento

- `listar` pagina con `LIMIT 20` y usa `idx_estado`, `idx_narrador_uid` e
  `idx_created_at`. Una sola consulta con `LEFT JOIN mybb_users` y
  `LEFT JOIN mybb_forums`, sin consultas en bucle.
- El conteo de límites restringe primero por `estado IN (0,1,4)` y resuelve la
  participación mediante `idx_uid_peticion`; las excepciones de migración usan
  el fallback JSON de manera temporal y explícita.
- No hay `CREATE TABLE` ni `ALTER TABLE` en tiempo de ejecución.
- Los typeaheads limitan resultados (20 fichas, 30 islas), usan *debounce* de
  250 ms y cancelan la petición anterior (`hx-sync`).
- Cada acción devuelve solo el fragmento que cambió, no la página.

## 9. Compatibilidad y convivencia

- Las dos páginas leen y escriben la misma tabla. `num_jugadores` se mantiene al
  crear. `_meta` se mantiene al editar nota y comentario.
- Las solicitudes antiguas se leen con `opg_av_normalizar_fila` (§4.8): sin isla,
  sin día, sin datos de dificultad, con "Sin motivo registrado" si estaban
  denegadas.
- Mientras convivan, una solicitud creada en la calculadora actual aparece en el
  Mis aventuras, Otras aventuras y la consola nuevos. Una solicitud creada en la nueva aparece en la
  calculadora actual con el comentario y el estado, sin enemigos ni dificultad
  (se mostrará vacía o "-", sin romper la página).
- Ninguna operación de la versión nueva escribe en `aventurasActivas`. Si la
  calculadora actual sigue incrementándola, esa columna deja de ser fiable. Se
  comprueba antes de la entrega que ningún otro módulo la lee.
- Al retirar la calculadora, `Calculadora_Tiers.php` redirige
  `?peticion=N` a `tablon_de_aventuras.php?tab=registro&id=N`.
- Las páginas manuales `entregar_recompensas_aventuras.php`,
  `entregar_recompensas_narradores.php` y `entregar_recompensas.php` no cambian:
  entregan montos fijos a un solo personaje y siguen sirviendo para entregas
  manuales. Las aventuras se entregan con la herramienta nueva.
- Mientras conviva la calculadora, su botón "Pagar" lleva a
  `recompensasAventuras.php`, que sigue funcionando sin cambios y con el tope de 675. La
  herramienta nueva y esa página se protegen entre sí: ambas publican el mensaje
  "RECOMPENSAS PROCESADAS" y cierran el tema, y ambas rechazan un tema cerrado o con
  ese mensaje. Si la calculadora se usa para pagar una aventura que ya tiene una
  entrega pendiente en el Tablón, esa entrega queda con el tema cerrado y su pago
  se rechaza con el aviso de §4.12, y el staff la rechaza. Al retirar la
  calculadora se retira también el acceso a `recompensasAventuras.php`.
- `op/staff/test1.php` es una copia idéntica de `recompensasAventuras.php` (1307
  líneas, mismo template) sin referencias en el código. No se toca; conviene
  retirarla al mismo tiempo para que no quede una segunda herramienta de pago con
  el tope antiguo.
- Una solicitud en estado 1 al migrar ya tiene `tier_seleccionado` y puede
  entregarse por el Tablón; una finalizada antes no tiene entrega y no la necesita.
- La calculadora actual no se modifica ni se reutiliza: ni su template, ni su
  CSS, ni sus JS. HTMX se carga solo en las páginas nuevas.

## 10. Corrección previa de permisos en `Calculadora_Tiers.php`

Es independiente del rediseño y debe hacerse primero (requisitos §9, punto 1).
Cambio mínimo, sin tocar nada más del archivo:

- Reactivar la comprobación de rol en `update_meta` (gestión), `delete` (staff),
  `claim_narrador`, `update_enemigos` y `get`, con las mismas condiciones que hoy
  están comentadas.
- Exigir sesión (`$uid > 0`) en `save` y `update_meta`.
- Añadir `global $db;` a `resolve_fid_by_name`.

Se prueba con una petición directa sin sesión, con un usuario sin rol y con
staff antes de dar el arreglo por bueno.

## 11. Pruebas y entrega

1. **Permisos.** Invitado y usuario normal: `crear`, `postular_narrador`,
   `vincular_aventura` y las acciones de gestión responden `403` o `404`. Usuario
   con ficha: puede crear y postularse. Mod, narrador y staff: abren la consola;
   solo staff elimina. Abrir `consola_aventuras.php` sin rol muestra la
   redirección.
2. **Aislamiento.** Enviar `cambiar_estado`, `actualizar_datos`, `eliminar`,
   `form_denegar` o `listar` de gestión a `tablon_de_aventuras.php` responde `404`
   incluso con sesión staff. El template de jugadores no contiene el texto
   "Consola".
3. **Contrato HTMX.** Una acción sin cabecera `HX-Request` responde `404`. Una
   acción que cambia datos sin `X-Post-Key` válido responde `403`. Con sesión
   caducada la respuesta lleva `HX-Redirect`. Los errores de regla responden `422`
   con `HX-Retarget` y los conflictos `409` con la tarjeta actualizada.
4. **Creación.** Forma A y forma B con datos válidos. Casos que se rechazan:
   narrador sin participantes, narrador entre los participantes, participante
   repetido, creador que no es participante ni narrador, isla que es subforo,
   isla inactiva, día 0 y 91, comentario de 1001 caracteres, ficha inexistente,
   un `fid` inventado en el campo oculto, y dos envíos idénticos en menos de 60
   segundos. En forma B: "tiene narrador" sin `narrador_fid`, una idea de 501
   caracteres, y una idea enviada junto con "tiene narrador" (se ignora y no se
   guarda). "No tengo narrador" con y sin idea crea la solicitud en estado 4 y la
   idea se guarda solo en el primer caso. Un doble clic en el botón envía una
   sola petición.
5. **Límites.** Con un jugador de 3 solitarias activas: aprobar una cuarta
   solitaria falla y nombra al jugador; aprobar una grupal funciona. Con 4
   grupales activas: aprobar una quinta de cualquier tipo falla. Un jugador que
   ya excede por solicitudes antiguas conserva todas, pero no puede aprobar otra.
   El indicador del formulario y el error de la consola dan los mismos números.
6. **Mis aventuras y Otras aventuras.** La página se abre en Mis aventuras. Con
   tres usuarios A, B y C y solicitudes donde A es creador, narrador,
   participante o ajeno: Mis aventuras de A contiene solo las suyas; Otras
   aventuras de A no contiene ninguna de ellas. La unión de Mis, Otras y las
   solicitudes sin narrador de Narradores & Narrados es todo lo visible sin
   repetidos. Una solicitud sin narrador aparece en Mis o en Narradores & Narrados.
   "Soy narrador" y "Soy jugador" filtran bien y se combinan con el filtro de
   estado. El filtro UID funciona solo en Otras y `rol` se ignora en Otras. El
   estado `pendiente-narrador` se ignora en el listado de Otras. Una solicitud en
   estado 5 no aparece en ninguna pestaña. Una solicitud antigua cuyo creador no
   figura en `jugadores_json` queda en Mis aventuras del creador. `?id=N` abre la
   pestaña correcta.
7. **Mercado de narradores.** El contador de la pestaña coincide con las
   solicitudes en estado 4 que no son del usuario y desaparece con cero. El
   bloque las ordena de la más antigua a la más reciente y muestra el aviso vacío
   con cero. Postularse quita la tarjeta del bloque, baja el contador y lleva la
   solicitud a Mis aventuras del narrador. Ofrecerse con Discord y 200 caracteres
   de mensaje funciona; sin Discord, con 65 caracteres de Discord, con 201 de
   mensaje, o sin ficha, falla. Ofrecerse dos veces deja una sola fila y
   conserva `created_at`. Una oferta de 31 días no aparece en la lista pero su
   autor la ve como vencida y puede renovarla. Retirar o renovar la oferta de
   otro usuario por petición directa no es posible (siempre se usa el `uid` de la
   sesión). Los conteos "En curso" y "Finalizadas" coinciden con las solicitudes
   del narrador en los estados 0 y 1, y 3. El orden es de menos a más aventuras
   en curso. La tarjeta muestra el Discord como texto y no hay botón de elegir ni enlace
   de contacto. La nota de idea de las solicitudes aparece en las tarjetas del
   bloque. Una oferta de un usuario sin ficha no aparece. El texto de la oferta con `<script>`
   se muestra como texto.
8. **Edición por el creador.** `editar` funciona para el creador en estados 0 y 4
   y devuelve `403` para otro usuario (incluido el narrador asignado que no es
   creador) y `409` para los estados 1, 2, 3 y 5. No se puede cambiar el rol. Con
   la forma B, quitar el narrador de una solicitud en 0 la deja en 4 y nombrar uno
   en una en 4 la deja en 0 y vacía `motivo_renuncia`. Las validaciones (isla,
   día, participantes, narrador no participante, idea de 501) fallan igual que en
   `crear`, sin antirrebote. Se guarda `editado_at`, la auditoría registra solo los
   campos que cambiaron y la consola muestra "Editada el ...". Editar justo
   después de que otro usuario apruebe devuelve `409` y no cambia nada.
   Editar el comentario de una solicitud antigua cuyo comentario estaba en
   `_meta` lo reemplaza por el de la columna.
9. **Resolución en la consola.** `cambiar_estado` devuelve `403` si el usuario es
   creador, narrador o participante de la solicitud, sea mod, narrador del staff
   o staff; las acciones salen deshabilitadas con su explicación. Otro usuario con
   acceso sí puede. Un usuario sin permisos de consola (incluido un narrador
   usuario) no puede abrir la consola ni llamar a sus acciones.
10. **Cola, historial y mercado en la consola.** La consola se abre con Creadas
   marcado y los conteos por estado coinciden con la base. El historial lista,
   con usuario y fecha, creación, ediciones, postulaciones, salidas con motivo,
   cambios de estado con motivo, vinculación y borrado, y una solicitud anterior
   a la migración muestra "Sin historial anterior a la migración". La sección
   Narradores disponibles lista las ofertas vigentes y las vencidas (marcadas);
   retirar una exige motivo de 1 a 200 caracteres, la borra, audita
   `retirar_oferta_admin` con el motivo y el uid del ofertante, y el usuario puede
   volver a ofrecerse. Sin permisos, retirar por petición directa se rechaza.
11. **Vinculación y envío de la entrega.** "Vincular aventura" y "Entregar
   aventura" solo aparecen al narrador asignado en estado 1. El typeahead no devuelve
   nada con 1 o 2 caracteres (también por petición directa) y con 3 o más solo
   temas del prefijo Aventura, visibles, abiertos, de la isla (o de un foro de rol si
   la solicitud no tiene isla) y no usados; un tema de otro prefijo, de otra isla,
   cerrado, de un foro inaccesible, con el mensaje de recompensas, inexistente o ya
   usado se rechaza. Un `tid` suelto, un enlace `showthread.php?tid=N` del foro y
   un enlace de otro host: los dos primeros se validan, el tercero se rechaza.
   Vincular guarda `showthread.php?tid=N`. Al abrir el formulario de entrega el tema
   viene precargado, y si dejó de ser válido se explica y el campo queda vacío. El
   formulario muestra petición, participantes, narrador con su `nivelnarrador` y
   tier no editables. Sin tema, sin resumen, con 2001 caracteres o sin reputación
   falla. Enviar crea una entrega pendiente y la solicitud sigue en 1. Una segunda
   entrega, o el mismo tema en otra solicitud, devuelve `409`. Una rechazada
   libera el tema, se corrige, se reenvía con `intentos` incrementado y vuelve a
   pendiente. Quien no es el narrador asignado recibe `403`.
12. **Análisis y pago (consola).** Con un tema de prueba con posts de 299, 300 y
   más caracteres y etiquetas HTML: el narrador se toma de la entrega, los
   posts válidos cuentan solo desde 300 caracteres sin etiquetas, y los
   porcentajes coinciden con la función `calcularPorcentajeBase` del template para
   los tiers 1 a 10 (con menos, igual y más posts de los esperados; límites de 150 y
   de 200; extra por jugadores de 0 a 50; variación de posts de -100 a 100). La
   etiqueta `[Narrador]`, si aparece en un mensaje antiguo, se ignora. El tier
   por defecto es el de la solicitud y se puede cambiar. El nivel del narrador sale
   de su ficha. Con posts mínimos sin
   cumplir hace falta la confirmación, tanto en la interfaz como en el servidor.
   Excluir a un destinatario lo deja fuera del pago. Un porcentaje enviado por el
   cliente se ignora. La reputación por defecto es la del narrador y se puede
   cambiar por jugador, incluida "ninguna".
13. **Recompensas y tope.** Los montos de narradores y jugadores coinciden con los de
   `recompensasAventuras.php` para las mismas fichas y el mismo tema, incluidos
   oficios (Contrabandista 0 a 3, Arqueólogo 1 y 2), inframundo, cofres y kuros por
   exceso. El tope mensual es 675 (el de `recompensasAventuras.php`, que no cambia):
   con 625 acumulados y 100 de experiencia por pagar, se entregan 50 y 25 kuros; con
   675, ninguna y todo el exceso en kuros. El
   contador se reinicia el primer pago del mes. Los arreglos de recompensas de la
   biblioteca son idénticos a los de la página antigua (comparación por expresión
   regular). `recompensasAventuras.php` sigue con 675 y sin cambios.
14. **Pago único, mensaje y convivencia.** Pagar paga una sola vez a cada
   destinatario con dos peticiones concurrentes o un doble clic. Se publica el
   mensaje de "RECOMPENSAS PROCESADAS" como el usuario 5 y el tema se cierra; la
   entrega queda entregada y la solicitud en estado 3. Un fallo forzado a mitad
   deja a los pagados como pagados y `completar` (en `aventuras_recompensas.php`) paga solo al resto (sin
   repetir nikas, berries, cofres ni experiencia) y reintenta la publicación si faltó.
   Un pago con experiencia dudosa no se repite y obliga a elegir. La herramienta
   antigua rechaza un tema que la nueva pagó, y la nueva rechaza uno que la antigua
   pagó. Un gestor puede pagar o rechazar aunque participe. Rechazar sin motivo
   falla. Un usuario sin permisos no puede llamar a las acciones de entrega.
15. **Ventana de postulación.** "Postularme como narrador" abre la ventana con los
   datos de la solicitud. Enviar sin la casilla (petición directa con
   `confirmo` ausente o distinto de 1) responde `422` y no cambia nada. Cancelar
   cierra la ventana sin cambios. Confirmar con la casilla marcada asigna al
   narrador y lleva la solicitud a estado 0. El usuario creador o participante
   no puede postularse. La ventana tiene `role="dialog"` y `aria-modal`.
16. **Pestañas y vacío.** Las pestañas están en el orden Mis aventuras, Solicitar
   aventura, Narradores & Narrados y Otras aventuras, y la página se abre en Mis aventuras. Un
   usuario sin ninguna aventura ve la recomendación con el botón hacia Solicitar
   aventura; un usuario con aventuras y filtros sin resultados ve solo la lista
   vacía. Con el estado 5 como única solicitud del usuario, se le recomienda
   pedir una.
17. **Estados.** Cada transición válida funciona y cualquier otra devuelve `409`.
   Denegar sin motivo o con motivo en blanco falla. Una denegada no vuelve a otro
   estado. Dos `postular_narrador` simultáneos: solo uno obtiene `affected_rows`.
   `renunciar_narrador` solo funciona para el narrador asignado en los estados 0 y
   1, con `confirmo=1` y un motivo de 1 a 500 caracteres; con motivo vacío o de 501,
   de otro estado, de otro usuario o sin confirmación se
   rechaza. Tras salirse, `narrador_uid`, `narrador_fid` y `narrador_nombre`
   quedan nulos, el estado es 4, `aventura_url` e `idea_narrador` se conservan y
   la salida queda auditada. Una aventura aprobada que pierde a su narrador y gana
   otro por postulación queda en estado 0, y la consola la aprueba de nuevo.
18. **Compatibilidad.** Tras la migración, `COUNT(*)` y el checksum de los `id`
   coinciden con los guardados. Una solicitud antigua se muestra en Mis aventuras u Otras aventuras
   con "sin isla" y "sin día" y se gestiona desde la consola. Una solicitud nueva
   recibe un `id` mayor que el máximo anterior. La calculadora actual sigue
   guardando solicitudes después de la migración de §3.2.
19. **Fecha.** Una prueba compara `OPG_AV_DIAS` y `OPG_AV_ANO` con `CRON_DIAS` y
   `CRON_ANO_DEFECTO` de `op/cronologia.php`.
20. **Auditoría.** Cada acción de la lista de §4.9 deja una fila con el usuario
   correcto y el `antes`/`despues` esperado.
21. **XSS.** Un nombre de ficha, un comentario, una idea, un Discord, un resumen de entrega, el nombre de un tema y un motivo con `<script>` y
    comillas se muestran como texto en Mis aventuras, Otras aventuras, la consola y los chips, y no
    se ejecutan. Ningún fragmento contiene `<script>`.
22. **Visual.** Revisar la lista de verificación de `style.md` §5 en ambas páginas,
    a 1030px y a 375px: títulos en `moonGetHeavy`, cuerpo en Inter, bordes negros,
    un solo `.btn-op--primario`, `.opg-chip` para lo secundario, `.opg-vacio` en
    listas vacías, sin sombras difusas ni degradados entre matices y sin
    desbordamiento horizontal. Comprobar que las pestañas, los bloques y los
    filtros funcionan abriendo directamente `?tab=otras` y con el botón atrás.
23. **Templates.** Verificar que ningún identificador del template empiece con
    `$` salvo `{$variable}`, y que los templates se importen a la base con
    `import_templates.php` (el cambio local no se refleja hasta importarlo).
24. Marcar `Estado: implementado` en la cabecera de este documento y retirar la
    calculadora según §9 solo tras validar con staff.
