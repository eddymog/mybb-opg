# Registro de aventuras (Tablón de aventuras): plan de implementación

## Fase Narradores & Narrados compacto (2026-10-08)

Estado: **implementada y verificada localmente; pendiente de despliegue**.

1. Añadir el conteo de propuestas personales pendientes y usarlo en la insignia;
   retirar de ella el total de solicitudes sin narrador.
2. Omitir por completo `Propuestas para narrar` cuando el conteo sea cero y probar
   invitaciones, candidaturas y propuestas ya resueltas.
3. Refactorizar la oferta propia a una franja compacta, manteniendo las acciones y
   eventos HTMX actuales.
4. Adaptar las ofertas ajenas a una cuadrícula de hasta tres columnas y mantener
   Discord como texto seleccionable, sin acción adicional.
5. Crear la fila horizontal de aventuras sin narrador con identidad `Solicitud
   #ID`, metadatos compactos, extracto de dos líneas y acción principal estable.
6. No mostrar datos ausentes ni usarlos para bloquear una candidatura.
7. Unificar las cabeceras y contadores informativos de ambas secciones y reducir
   el espaciado vertical a 20-28 px.
8. Ajustar responsive a una columna bajo 700 px y verificar que textos, contadores,
   Discord y acciones no desborden.
9. Ampliar pruebas de página, dominio y HTMX. Cubrir estados vacíos, ofertas largas,
   datos incompletos, permisos y actualización de conteos.
10. No añadir filtros ni modificar el esquema SQL en esta fase.

**Criterio de cierre:** ninguna sección vacía reserva altura; la insignia representa
solo acciones personales; todas las ofertas y aventuras siguen accesibles; y la
interfaz pasa revisión visual en 375, 700, 1030 y 1440 px.

## Fase autonarradas (2026-10-08)

Estado: **implementada y verificada localmente; pendiente de migración y
despliegue**.

La implementación vive en el dominio compartido de aventuras, el formulario del
tablón, `newthread.php` y la pestaña Entregas de la consola. La herramienta
antigua `op/staff/entregar_recompensas.php` ya no procesa pagos y redirige a la
cola unificada.

### A1. Congelar comportamiento y datos

1. Añadir fixtures para niveles 1, 15 y 16, cambios de nivel, límites de semana,
   temas de 799/800 palabras, temas ajenos y pagos interrumpidos.
2. Extraer un informe de filas que cumplen la heurística histórica de autonarrada
   y obtener confirmación del staff antes del backfill.
3. Registrar conteos y checksums de solicitudes antes de migrar.

### A2. Migración

1. Añadir `tipo` e índice a `op_peticionAventuras`.
2. Crear `op_aventuras_autonarradas` con relación uno a uno, unicidad semanal,
   unicidad de tema y estado recuperable de pago.
3. Migrar solo las filas históricas confirmadas.
4. Verificar conteos, checksums, índices y compatibilidad con MySQL 5.7.

### A3. Dominio semanal y elegibilidad

1. Implementar semana de Madrid con corte lunes 06:00 y pruebas de DST.
2. Obtener el nivel desde la fuente canónica de la ficha y guardar el nivel de
   solicitud.
3. Implementar disponibilidad: nivel 1–15, ninguna solicitud en la misma semana
   y ninguna autonarrada anterior sin publicar.
4. Añadir bloqueo transaccional para solicitudes concurrentes.

### A4. Formulario y creación

1. Añadir el selector Aventura/Autonarrada solo para fichas elegibles.
2. Crear la variante condicional del formulario con identidad fija, isla, día y
   comentario, sin tier, inframundo, participantes extra ni narrador externo.
3. Mostrar las reglas de un post, 800 palabras y un cupo semanal.
4. Validar de nuevo todo en `crear`; ignorar o rechazar campos incompatibles
   enviados manualmente.
5. Adaptar edición para impedir convertir tipos después de crear.

### A5. Aprobación y publicación

1. Adaptar las tarjetas y transiciones de Solicitudes en la consola.
2. Incluir autonarradas aprobadas en el selector de `newthread.php` únicamente
   hasta nivel 15.
3. Validar vínculo, propiedad, tema único y nivel en servidor.
4. Guardar `tid`, `publicada_at`, `nivel_publicacion` y conteo preliminar.
5. Implementar el recuento autoritativo de palabras y la regla de un solo post.

### A6. Tablón e históricos

1. Crear el bloque Autonarradas en Mis aventuras sin alterar cupos normales.
2. Añadir tipo, semana, vínculo, palabras y estados a tarjetas y modo vista.
3. Sustituir la heurística por `tipo` en `aventuras_personaje.php` y su copia de
   staff.
4. Revisar estadísticas, filtros, paginación y búsquedas para ambos tipos.

### A7. Entregas integradas en consola

1. Ampliar Entregas con filtro Todas/Aventuras/Autonarradas y URL canónica.
2. Crear la tarjeta autonarrada con validaciones, recompensa fija y orientación
   de reputación.
3. Implementar análisis y revalidación en servidor.
4. Añadir la entrega reducida del autor con reputación positiva o negativa y
   guardar `entregada_at`; no encolar solo por vincular el tema.
5. Mostrar la reputación declarada como solo lectura para Staff.
6. Implementar el pago reclamable e idempotente por componentes.
7. Finalizar solicitud, cerrar tema y escribir ambos historiales.
8. Mostrar `Completar recompensa` únicamente para pagos interrumpidos.

### A8. Retirada de la herramienta antigua

1. Desactivar POST en `op/staff/entregar_recompensas.php`.
2. Redirigir GET a Entregas de la consola, trasladando filtros compatibles.
3. Actualizar `consola_mod.php`, enlaces, documentación y templates.
4. Confirmar que no queda otra ruta capaz de pagar autonarradas.

### A9. Verificación y despliegue

1. Ejecutar sintaxis, dominio puro, dominio DB, concurrencia, página del Tablón,
   Consola, recompensas y regresión completa.
2. Probar explícitamente nivel 16 por interfaz y petición directa, semanas de
   invierno/verano, doble clic, dos moderadores y fallos parciales.
3. Verificar visualmente 375, 700, 1030 y 1440 px, teclado y foco.
4. Desplegar primero migración, después dominio/controladores y al final templates.
5. Mantener apagada la creación autonarrada hasta verificar backfill, índices y
   redirección de la herramienta anterior en producción.

## Modo vista del tablón (2026-10-08)

Estado: **implementado**.

1. Añadir `buscar_modo_vista` a las acciones GET y devolver resultados enlazados
   por `modo_vista=FID`, limitados a fichas aprobadas.
2. Incorporar el selector desplegable en la cabecera y una pantalla externa de
   solo lectura con retorno, sin pestañas ni Novedades. Actualizar el slot de
   Novedades fuera de banda para que solo exista en Mis aventuras.
3. Reutilizar el resumen de activas sin sus refrescos propios y distinguir las
   aventuras jugadas de las narradas.
4. Enlazar cada identidad directamente al tema visible con `target="_blank"` y
   `rel="noopener"`; usar texto plano cuando no exista vínculo utilizable.
5. Cubrir búsqueda por nombre/FID, aislamiento de la vista y ambas variantes del
   título con pruebas HTTP.

## Fase UX aprobada (2026-10-07)

Estado: **implementada y verificada, pendiente de despliegue**. Esta fase amplía
las fases existentes y conserva las pruebas de permisos, concurrencia, entrega y
recompensas.

### U1. Contrato y fixtures

1. Congelar fixtures de solicitudes antiguas: JSON válido, inválido, UID repetido,
   participante sin UID o sin ficha y solicitudes con narrador ya asignado.
2. Añadir casos con más de 20 narradores, más de 40 aventuras y enlaces directos
   a estados activos e históricos.
3. Definir eventos notificables y textos sin información privada.
4. Confirmar disponibilidad de MyAlerts en producción y mantener bandeja interna
   como canal obligatorio aunque no exista el plugin.

**Salida:** dataset reproducible y matriz de compatibilidad antes de migrar.

### U2. Migración relacional

1. Añadir a una migración nueva, separada de las ya desplegadas:
   `op_aventuras_participantes`, `op_aventuras_propuestas_narrador` y
   `op_aventuras_notificaciones`, con índices de `201_DesignPlan_Registro.md`.
2. Escribir migrador reentrante de `jugadores_json` por lotes y reporte de
   anomalías. No editar automáticamente JSON inválido.
3. Comparar por petición orden, nombres, UIDs anulables y conteos; guardar checksum
   y lista de excepciones.
4. Implementar lectura relacional con fallback controlado y escritura dual.
5. Añadir sincronización a las escrituras legacy y un task de reparación. Mantener
   fallback mientras la calculadora antigua pueda escribir.
6. Cambiar límites, permisos, Mis/Otras, estadísticas y conflicto de interés a
   `EXISTS` relacional, con fallback solo para excepciones no migradas.
7. Retirar el fallback únicamente después de apagar escritura legacy y superar
   el checksum final.

**Pruebas:** paridad exacta JSON/relación, migración ejecutada dos veces, filas
antiguas y concurrencia. Ningún cambio visual en esta fase.

### U3. Estado de URL, vistas y etiquetas

1. Incorporar `vista`, `estado`, `rol`, `q`, `pagina` y `densidad` al parser y a
   una única función de URL canónica.
2. Implementar Activas/Historial/Todas en Mis y Actuales/Historial/Todas en Otras.
3. Cambiar solo las etiquetas visibles: En revisión, Aprobada, Denegada,
   Finalizada y Buscando narrador. Mantener códigos y claves internas.
4. Hacer que enlaces directos `?id=N` elijan vista y página correctas.
5. Añadir degradación GET completa para filtros y paginadores.

**Pruebas:** recarga, compartir URL, Atrás/Adelante, parámetros inválidos, enlace
directo histórico y ausencia de pérdida de filtros.

### U4. Estadísticas informativas y listas

1. Calcular estadísticas con las mismas condiciones validadas de vista, búsqueda,
   estado y papel que utiliza el listado.
2. Renderizar estadísticas como indicadores estáticos, sin selección, `hover` ni
   `aria-pressed`; añadir `Limpiar filtros` junto a los filtros explícitos.
3. Reducir el resumen de activas a título, fechas y estado.
4. Mostrar nombre de tema en todas las tarjetas y enlazar personas a perfiles/MP.
5. Aplicar visibilidad contextual de idea, narrador ausente y datos incompletos.

**Pruebas:** combinaciones vista/estado/papel/búsqueda, conteos solapados, tema
inaccesible, filas legacy incompletas y salida móvil.

### U5. Paginación completa

1. Generalizar el paginador con primera, anterior, ventana de cinco, siguiente y
   última, preservando URL.
2. Aplicarlo a Mis, Otras y Narradores disponibles.
3. Conservar carga incremental para sin narrador y representar su avance en URL.
4. Tras swap, enfocar el resumen y desplazar el comienzo del listado.

**Pruebas:** 1, 2, 20, 21 y más de 100 resultados; primera/última; filtros en
página inexistente; más de 20 ofertas accesibles; teclado y foco.

### U6. Cupos anticipados

> Con `OPG_AV_LIMITES_ACTIVOS = false` (estado actual) no hay topes: `validar_cupos` no
> bloquea a nadie, los chips muestran solo los conteos y `opg_av_validar_limites()`
> devuelve vacío. Lo que sigue describe el comportamiento con los límites activados.

1. Implementar consulta de cupos en lote y acción GET `validar_cupos`.
2. Añadir cifras a chips de participantes y aviso de bloqueo con nombre/motivo.
3. Recalcular al cambiar papel, composición de participantes o edición.
4. Deshabilitar envío en modo jugador bloqueado; modo narrador muestra mensaje
   neutral y permanece habilitable.
5. Mantener la validación transaccional definitiva sin reutilizar datos del
   chequeo previo.

**Pruebas:** 3/3 solitaria, 4/4 total, grupal permitida, participante añadido con
cupo lleno, cambio a narrador y carrera por la última plaza.

### U7. Propuestas bilaterales

1. Implementar dominio y auditoría de invitación, candidatura, aceptación,
   rechazo, retirada, cancelación y vencimiento.
2. Cambiar creación con narrador ajeno a solicitud estado 4 + invitación.
3. Sustituir asignación inmediata de `Postularme` por `Me interesa narrar`.
4. Renderizar propuestas recibidas/enviadas y controles autorizados.
5. Aceptar bajo bloqueo transaccional, asignar un único narrador, cambiar a 0 y
   cancelar las propuestas restantes.
6. Crear task de vencimiento a 7 días.

**Pruebas:** permisos de cada contraparte, duplicado, dos aceptaciones simultáneas,
solicitud que deja de estar disponible, vencimiento y narrador propio sin propuesta.

### U8. Notificaciones

1. Implementar outbox y bandeja interna con contador no leído.
2. Encolar eventos después de calcular destinatarios únicos dentro de la misma
   transacción de negocio.
3. Registrar integración MyAlerts si está instalada y fallback configurable a PM.
4. Añadir task de reintento y aviso de oferta a 3 días de vencer.
5. Renderizar las novedades como panel persistente bajo las pestañas, con las
   pendientes visibles y las leídas en un historial reciente desplegable.
6. Marcar leída una notificación actualizando solo el panel, sin borrar
   auditoría ni historial y sin utilizar un modal.

**Pruebas:** deduplicación, actor excluido, destinatarios correctos, fallo de canal
sin rollback, reintento idempotente y ausencia de datos privados.

### U9. Historial de usuario

1. Añadir endpoint GET `historial_usuario` y DTO seguro desde auditoría.
2. Autorizar creador, participantes y narradores pertinentes.
3. Renderizar línea temporal accesible con fallback pre-migración.
4. Gestionar foco al abrir/cerrar y excluir payloads técnicos/notas de staff.

**Pruebas:** cada rol, usuario ajeno, acciones permitidas, acción desconocida,
historial vacío y XSS en detalles históricos.

### U10. Verificación y despliegue

1. Actualizar pruebas de dominio, página, consola y concurrencia antes de capturas.
2. Verificar 375, 700, 1030 y 1440 px; navegación solo con teclado y lectores de
   nombres/estados de controles.
3. Medir consultas y tiempos con 10 000 solicitudes y 1 000 ofertas simuladas.
4. Desplegar en orden: tablas, backfill, lectura/fallback, escritura dual, UX,
   propuestas, notificaciones y tasks.
5. Mantener una bandera para desactivar propuestas/notificaciones nuevas sin
   revertir las tablas ni perder solicitudes.

**Criterio de cierre:** todas las ofertas son paginables; filtros sobreviven a
recarga; ninguna asignación ajena es unilateral; cupos se anticipan y se revalidan;
notificaciones no se duplican; historial no filtra datos privados; JSON y relación
mantienen paridad durante la convivencia.

## Revisión de interfaz (2026-10-05)

Separar las activas del personaje de las narradas en el desglose, conservando
títulos y fechas en ambos. Solo las del personaje muestran y consumen límites;
las narradas tienen un conteo independiente sin límite.

Añadir `q` a los filtros conservados por paginación: número de solicitud en Mis,
y número o nombre de ficha en Otras. El formulario HTMX se refresca al teclear,
por lo que listado y estadísticas comparten la misma búsqueda reactiva.

Esta revisión sustituye la distribución en tres pestañas de las fases 8 a 10.

1. Añadir estadísticas SQL reutilizando las condiciones del listado, desglose
   de activas y permiso compartido de vinculación para narrador y participantes.
   Validar límites también al crear, bloqueando fichas de participantes en una
   transacción para evitar exceder el cupo con solicitudes simultáneas.
2. Añadir `tab=narradores` al router y mover allí sin narrador y ofertas; trasladar
   el contador y resolver enlaces directos de solicitudes ajenas en estado 4.
3. Renderizar estadísticas antes de los encabezados de Mis/Otras; actualizarlas
   con los filtros y eventos. Mantener 20 registros por página y total visible.
   Separar visualmente estados a la izquierda y roles en una columna derecha;
   en móvil, mostrar los roles en una franja independiente inferior.
4. Añadir Mis activas con listado separado de solitarias y grupales, independiente
   del filtro, con título del tema y fechas de registro y de rol. Consultar títulos
   en lote, con fallback sin tema y escapado. Mantener el indicador compacto en Solicitar.
5. Añadir acciones GET `form_participante` y `quitar_narrador`, buscadores
   progresivos, selección única de narrador y placeholder 1-90 para Día.
   El selector de tema también alterna buscador/chip; GET `quitar_tema` restaura
   el buscador sin modificar el enlace guardado, en vinculación y entrega.
   Buscar isla arriba, chip debajo y alineación con Día/Tier. Exigir comentario
   público descriptivo y orientar el placeholder de idea a preferencias de narración.
6. Rediseñar el template conforme a `style.md`, con bandas sin anidación de
   tarjetas, tokens compartidos, jerarquía tipográfica y controles accesibles.
7. Verificar permisos de vinculación, filtros y agregados completos, paginación
   >20, enlaces de pendientes fuera de las primeras 10, chips y sus swaps HTMX.
   Ejecutar pruebas existentes y revisión visual en escritorio y 375px.

Esta afirmación correspondía solo a la revisión visual 2026-10-05. La fase UX
2026-10-07 sí requiere la migración relacional U2 antes de activar propuestas,
notificaciones y lecturas indexadas. El template se publica después del
backfill y la escritura dual; se conservan las pruebas de recompensas y consola.

> Depende de:
> [101_Requirements_Registro.md](101_Requirements_Registro.md),
> [201_DesignPlan_Registro.md](201_DesignPlan_Registro.md) y
> [style.md](style.md)
>
> **Estado: implementado, pendiente de despliegue.** Ninguna fase cambia la calculadora actual, salvo la
> fase 1 (arreglo de permisos). `recompensasAventuras.php` no se modifica en ninguna
> fase y conserva su tope mensual de experiencia de narrador de **675**, que es
> también el de la página nueva. Las referencias `§N` son secciones del documento de
> diseño.

## 1. Preparación

1. Confirmar en la base de desarrollo, con `SHOW CREATE TABLE`, los motores y las
   columnas que da por hechos el diseño: `mybb_op_peticionAventuras` (InnoDB),
   `mybb_op_fichas` e inventario (InnoDB), `mybb_users` y `mybb_threads` (MyISAM),
   la columna `nivelnarrador` de `mybb_op_fichas`, que `id` tenga `AUTO_INCREMENT`
   y qué índices existen ya (`SHOW INDEX`).
2. Guardar un respaldo restaurable de `mybb_op_peticionAventuras` y
   `mybb_op_peticionAventuras_meta`, y anotar `COUNT(*)`, el `MAX(id)` y un checksum
   de la lista de `id`.
3. Descargar HTMX 2.x, fijar la versión exacta y colocarla en
   `jscripts/htmx/htmx.min.js`; anotar versión y suma de comprobación en un
   comentario del propio template que la carga.
4. Reunir los casos de verificación de las recompensas (fase 6): 4 o 5 temas reales
   ya pagados con `recompensasAventuras.php`, con su tier, los posts por usuario
   y lo que recibió cada quien (sacado del log `[Entregas]`), y fichas con y sin
   oficios Contrabandista y Arqueólogo y con cada nivel de narrador.
5. Confirmar `is_staff`, `is_narra`, `is_mod` y `does_ficha_exist` de
   `op/functions/op_functions.php` como las comprobaciones de acceso, y el patrón de
   redirección `op_redireccion` de `op/staff/consola_mod.php`.
6. Añadir las plantillas nuevas (`op_tablon_de_aventuras`, `staff_consola_aventuras`
   y `staff_aventuras_recompensas`) al flujo de `export_templates.php` e
   `import_templates.php` antes de apuntar rutas a ellas. Recordar que un `.html`
   local no se refleja en producción hasta importarlo.
7. Crear la carpeta `tests/aventuras/` con los scripts de verificación de las
   fases siguientes, excluida del despliegue.

**Salida de fase:** respaldo y checksums guardados, HTMX fijado, casos de pago
reunidos y un listado confirmado de motores e índices.

## 2. Corrección previa de permisos en la calculadora actual

Es independiente del rediseño y se hace primero (§10). Solo en
`op/Calculadora_Tiers.php`:

1. Reactivar la comprobación de rol, hoy comentada, en `update_meta` (mod, staff o
   narrador), `delete` (mod o staff), `claim_narrador`, `update_enemigos`, `get` y
   `search_fichas`, con las mismas condiciones que están escritas en los
   comentarios.
2. Exigir sesión (`$uid > 0`) en `save` y `update_meta`.
3. Añadir `global $db;` a `resolve_fid_by_name`.
4. No tocar nada más, ni el JS ni el template.

**Verificación:** una petición directa sin sesión, otra con un usuario sin rol y otra
con staff a cada acción: solo la de staff cambia estado o borra. La calculadora
sigue guardando y listando solicitudes con un usuario normal.

## 3. Base de datos

Crear `docs/aventuras_migration.sql`, ejecutable de una sola vez, con este orden:

1. Columnas nuevas de `mybb_op_peticionAventuras` (§3.1): `isla_fid`, `dia`,
   `motivo_denegacion`, `idea_narrador`, `motivo_renuncia` y `editado_at`.
2. Valores por defecto o nulabilidad de las columnas que el insert nuevo no llena
   (§3.2): `tier_seleccionado`, `dificultad_texto`, `dificultad_color`,
   `descripcion_tier`, `enemigos_json` e `inframundo`. `tier_seleccionado` sigue
   usándose, de 1 a 10.
3. Índices (§3.3): `estado`, `narrador_uid`, `isla_fid` y `created_at`, omitiendo los
   que la fase 1 haya encontrado ya.
4. Tablas nuevas: `mybb_op_audit_aventuras` (§3.4),
   `mybb_op_narradores_disponibles` (§3.5), `mybb_op_aventuras_entregas` y
   `mybb_op_aventuras_entregas_pagos` (§3.6) y, si falta,
   `mybb_op_peticionAventuras_meta` con `CREATE TABLE IF NOT EXISTS` y la estructura
   que hoy crea el runtime. Todas InnoDB, `utf8mb4`.
5. Ninguna sentencia modifica ni borra filas existentes, y no hay cambio de
   comentario en `expNarradorMensualActual`.

Aplicar primero en desarrollo con una copia de la base de producción.

**Verificación:** el `COUNT(*)`, el `MAX(id)` y el checksum de `id` coinciden con los
guardados en la fase 1; la calculadora actual sigue guardando una solicitud nueva
(con los valores por defecto); un `INSERT` mínimo del código nuevo funciona sin
llenar las columnas antiguas; los índices únicos de entregas y pagos rechazan un
segundo registro por solicitud, por `tid_activo` y por destinatario.

## 4. Biblioteca de dominio

Crear `inc/plugins/lib/aventuras.php` (§4.1) antes de escribir páginas. Orden de
implementación:

1. Constantes (`OPG_AV_*`), con la fecha, los topes y los estados activos. Las de
   fecha deben coincidir con `CRON_DIAS` y `CRON_ANO_DEFECTO` de
   `op/cronologia.php`; el script de verificación las compara.
2. Catálogo de estados y `opg_av_transicion_valida` con la tabla de §4.1 de los
   requisitos (`0→1`, `0/4→2`, `1→2`, `1→3`, `4→0`, `0/1→4`, `0↔4` por edición,
   `→5`, `5→borrado`).
3. Permisos: `opg_av_puede_gestionar`, `opg_av_puede_eliminar`,
   `opg_av_puede_resolver` (gestión, incluso en aventuras propias) y
   `opg_av_puede_editar`.
4. Lectura: `opg_av_normalizar_fila` con todo el tratamiento de filas antiguas
   (§4.8), `opg_av_obtener` y `opg_av_listar` con las consultas de §4.10 y sus
   filtros.
5. Fichas, islas y fecha: `opg_av_fichas_buscar`, `opg_av_ficha`,
   `opg_av_isla_valida`, `opg_av_islas_buscar` y `opg_av_validar_dia`.
6. Límites: `opg_av_contar_activas` y `opg_av_validar_limites` (§4.5).
7. Validación y escritura: `opg_av_validar_solicitud` con sus modos de crear y
   editar (§4.3), `opg_av_crear`, `opg_av_editar`, `opg_av_postular_narrador`,
   `opg_av_renunciar_narrador`, `opg_av_cambiar_estado`, `opg_av_pedir_borrado`,
   `opg_av_eliminar`, `opg_av_actualizar_datos` y `opg_av_vincular` con
   `opg_av_tid_de_entrada`.
8. Mercado: `opg_av_oferta_*`, `opg_av_narradores_disponibles`,
   `opg_av_ofertas_admin_listar`, `opg_av_oferta_retirar_admin`,
   `opg_av_sin_narrador_listar` y `opg_av_sin_narrador_contar`.
9. Consola: `opg_av_conteo_estados` y `opg_av_historial`.
10. Auditoría: `opg_av_auditar`, que escribe en `mybb_op_audit_aventuras` y en
    `log_audit` con el texto ya escapado.

Reglas para todo el archivo: escapar con `$db->escape_string()` o casts enteros,
tomar el prefijo de tablas de `$db`, usar transacciones donde el diseño lo indica
(`UPDATE ... WHERE` condicionado con `affected_rows`) y devolver arreglos de errores
en vez de hacer `exit`.

**Verificación:** `tests/aventuras/dominio.php` ejecuta, contra la base de desarrollo,
casos de transiciones válidas e inválidas, los límites (3 solitarias, 4 en total, 4
grupales sí, 5 no), el predicado "mía" y su negación (unión sin repetidos ni
huecos, incluida una solicitud sin narrador), la validación de crear y editar y la
normalización de filas antiguas.

## 5. Biblioteca de fragmentos

Crear `inc/plugins/lib/aventuras_vistas.php` (§4.2): funciones puras que devuelven
HTML.

1. Un único helper de escape (`htmlspecialchars` con `ENT_QUOTES` y UTF-8) que usan
   todas; los atributos `hx-*` se arman con enteros y rutas fijas.
2. Piezas pequeñas primero: avisos, chips de ficha, resultados de typeahead, slot de
   isla y tema, indicador de activas, contador de pestaña, estados vacíos.
3. Formularios: solicitar y editar (`opg_av_v_form_solicitud`), campos por rol,
   vincular, oferta, denegar, editar datos, retirar oferta, rechazar.
4. Tarjeta (`opg_av_v_tarjeta`) con todas sus variantes: acento por estado, idea,
   motivo de renuncia, motivo de denegación, tier, estado de la entrega y las acciones
   según rol y estado.
5. Listas, bloque de sin narrador, tarjeta de narrador disponible, fila de consola y
   fila de oferta, historial y ventanas de confirmación (postular y dejar de narrar).

**Verificación:** `tests/aventuras/vistas.php` pasa textos con `<script>`, comillas y
caracteres de control por cada función y comprueba que la salida los muestra como
texto, que no hay `<script>` y que ningún atributo `hx-*` contiene texto de
usuario.

## 6. Biblioteca de recompensas

Crear `inc/plugins/lib/recompensas_aventura.php` (§4.11). Es la fase de más riesgo:
mueve dinero del juego. Orden:

1. Extraer las tablas de recompensas de narrador por nivel y de jugador de
   `op/staff/recompensasAventuras.php` por copia literal, sin retipearlas. Añadir
   los posts esperados por tier, los umbrales (300 caracteres, límites de 150 y 200,
   extras de 0 a 50) y el tope `OPG_REC_EXP_NARRADOR_MENSUAL = 675`.
2. `opg_rec_analizar_tema`, `opg_rec_porcentaje_base`, `opg_rec_calcular_porcentajes`
   y `opg_rec_minimos`, reproduciendo la lógica del PHP y del JavaScript de la
   página antigua, con el recálculo siempre en el servidor.
3. `opg_rec_nivel_narrador`, `opg_rec_calcular_narrador` (con el tope y los kuros por
   exceso) y `opg_rec_calcular_jugador` (oficios, inframundo y reputación).
4. `opg_rec_reset_mensual`, con la misma zona horaria y el mismo ajuste que hoy.
5. `opg_rec_tema_pagado`, `opg_rec_mensaje_resumen` y `opg_rec_publicar_y_cerrar`,
   que publica con `PostDataHandler` como el usuario 5 y cierra el tema, con el mismo
   texto de "RECOMPENSAS PROCESADAS" que la página antigua.

**Verificación (obligatoria antes de seguir):**

- `tests/aventuras/paridad_constantes.php` extrae con expresión regular los arreglos
  de `recompensasAventuras.php` y los compara con las constantes de la biblioteca.
- `tests/aventuras/paridad_calculo.php` calcula, con los casos reunidos en la fase 1,
  porcentajes y recompensas con la biblioteca y compara con lo registrado por la
  página antigua para el mismo tema, tier y ajustes.
- Casos fijos de la función de porcentaje: validos menores, iguales y mayores que los
  esperados para los 10 tiers, límites de 150 y 200, variación de -100 a 100,
  extra de 0 a 50, y tope mensual (625 acumulados con 100 por pagar da 50 y 25
  kuros; 675 acumulados da 0 y todo en kuros).
- Un tema con posts de 299 y 300 caracteres y con etiquetas HTML cuenta solo los
  válidos.

No se continúa si algún caso difiere de la página antigua.

## 7. Biblioteca de entregas

Crear `inc/plugins/lib/aventuras_entregas.php` (§4.12). Orden:

1. `opg_av_tid_de_entrada`, `opg_ent_temas_buscar` (mínimo de 3 caracteres, prefijo
   Aventura, abiertos, visibles, de la isla, no usados) y `opg_ent_tema_valido`.
2. `opg_ent_puede_enviar`, `opg_ent_form` (con el tema precargado desde la URL
   vinculada) y `opg_ent_enviar` con su transacción.
3. `opg_ent_listar` y `opg_ent_conteos`.
4. `opg_ent_analizar`, con la validación de los ajustes y la lista de advertencias.
5. `opg_ent_rechazar`.
6. `opg_ent_pagar` con el reclamo de la entrega, el reinicio mensual, el pago por
   destinatario (transacción InnoDB, marca de aplicado y experiencia fuera de la
   transacción con sus estados), el cierre y la reanudación en `opg_ent_completar`,
   más la resolución de experiencia dudosa.

**Verificación:** `tests/aventuras/entregas.php`: el typeahead no devuelve nada con 1
o 2 caracteres; temas de otro prefijo, cerrados, de otra isla, ya usados o con el
mensaje de recompensas se rechazan; una segunda entrega y el mismo tema en otra
solicitud devuelven conflicto; el pago es único con dos llamadas concurrentes
(dos procesos en paralelo); un fallo forzado en el tercer destinatario deja a los dos
primeros pagados y `opg_ent_completar` paga solo al resto sin repetir nikas, berries,
cofres ni experiencia; un tema ya cerrado o con el mensaje no se paga. Este script
trabaja solo con fichas de prueba, nunca con las reales.

## 8. Página de jugadores: estructura y Mis aventuras

1. Crear `op/tablon_de_aventuras.php` con el patrón de las páginas de `/op/` (§5):
   `IN_MYBB`, `THIS_SCRIPT`, `global.php`, `config.php`, `op_functions.php` y las
   bibliotecas de las fases 4 y 5. Sin `display_errors`.
2. Implementar el contrato HTMX común (§5.1): lista blanca de acciones, `404` para
   toda acción no listada o sin `HX-Request`, `HX-Redirect` sin sesión,
   verificación de `X-Post-Key` en los POST, cabeceras `no-store` y `Vary`, y los
   códigos `200`, `403`, `404`, `409` y `422` con `HX-Retarget` en los errores.
3. Crear el template `op_tablon_de_aventuras` con el marco de tres fondos
   (`{$headerinclude}`, `.mainBackground` a `.thirdBackground` de ancho completo y
   máximo 1030px), encabezado compacto de herramienta, la etiqueta `htmx-config`, el
   `hx-headers` con el `post_code` y el `<script defer>` de HTMX. El `<style>` sigue
   el orden de `style.md` §7 con el prefijo `ta-`. Sin identificadores que empiecen
   por `$` salvo `{$variable}`.
4. Barra de cuatro pestañas en el orden Mis aventuras, Solicitar aventura,
   Narradores & Narrados y Otras aventuras, con `?tab=`, apertura en Mis aventuras y carga de cada panel con
   `hx-get` y `hx-push-url`.
5. Pestaña **Mis aventuras**: filtros de estado y de rol con la opción "Todas",
   lista paginada, estado vacío con recomendación cuando el usuario no tiene
   ninguna aventura, `?id=N` para abrir y resaltar una solicitud.

**Verificación:** invitado y usuario sin ficha no pueden hacer nada que cambie datos;
una acción de gestión enviada a esta página responde `404` incluso con sesión staff;
las pestañas funcionan abriendo `?tab=otras` directamente y con el botón atrás; el
template no contiene el texto "Consola"; no hay `$` sueltos.

## 9. Solicitar aventura, edición y acciones de la tarjeta

1. Pestaña **Solicitar aventura**: selector de rol, buscadores de participantes y
   narrador con sus chips (`quitar_chip`), selector "Tengo narrador" o "No tengo
   narrador" con el campo de idea, isla con typeahead, día, tier con los posts
   esperados, comentario, inframundo e indicador de activas con el botón
   deshabilitable.
2. Acciones: `campos_rol`, `buscar_fichas`, `elegir_ficha`, `buscar_islas`,
   `elegir_isla`, `mis_activas` y `crear`, con la respuesta de éxito y la de error
   `422` retargeteada a `#ta-avisos`.
3. Edición: `form_editar` y `editar` en la tarjeta, solo para el creador en los
   estados 0 y 4.
4. Postular y salirse: `form_postular` y `postular_narrador` (ventana con casilla
   requerida), `form_renunciar` y `renunciar_narrador` (casilla y motivo de 500).
5. Pedir borrado con `hx-confirm`.
6. Estilo de las ventanas de confirmación (`.ta-modal`, `role="dialog"`,
   `aria-modal`) con velo y botón Cancelar que cierra, sin JavaScript propio.

**Verificación:** casos de §11 del diseño (creación y edición): forma A y B, rol que
no se puede cambiar, idea solo sin narrador, tier de 1 a 10 (0 y 11 se rechazan),
`409` al editar tras una aprobación, postulaciones simultáneas con un solo ganador,
salida con y sin motivo, `confirmo` ausente rechazado. Revisión visual contra la
lista de `style.md` §5 a 1030px y a 375px.

## 10. Otras aventuras y Narradores & Narrados

1. Pestaña **Narradores & Narrados** con los bloques de ofertas y aventuras sin narrador (`sin_narrador`,
   `contar_sin_narrador`, "Ver más") y narradores disponibles. **Otras aventuras**
   conserva el listado con filtros de estado, búsqueda por número o nombre de
   personaje y estadísticas informativas.
2. Contador de la pestaña que se refresca con los eventos `aventuraCreada` y
   `aventuraCambiada`.
3. Ofertas: `form_oferta`, `ofrecerme_narrador` (Discord obligatorio y mensaje),
   `renovar_oferta` y `retirar_oferta`, con vencimiento a 30 días y la oferta propia
   visible aunque haya vencido.
4. `narradores_disponibles` con conteos de aventuras en curso y finalizadas en una
   sola consulta agrupada y el orden de menos a más en curso.

**Verificación:** con tres usuarios de prueba, Mis aventuras, Otras aventuras y el
bloque de sin narrador no se solapan y su unión es todo lo visible; una solicitud sin narrador aparece en una
sola pestaña; el contador coincide con la base; ofrecerse con 200 o 201 caracteres
de mensaje, sin Discord y sin ficha; ofertas de 31 días no se muestran pero su autor
las ve como vencidas; los conteos coinciden con las solicitudes del narrador.

## 11. Vincular y entregar (jugadores)

1. `form_vincular` y `vincular_aventura` con el campo de tema (typeahead desde 3
   caracteres, enlace o `tid`), `buscar_temas` y `elegir_tema`.
2. `form_entrega` y `enviar_entrega`, con los datos ya conocidos no editables, el
   tema precargado desde la vinculación, el resumen de 2000 caracteres y la
   reputación positiva o negativa.
3. Estados de la entrega en la tarjeta: pendiente, rechazada con motivo y botón para
   corregir y reenviar, y entregada.

**Verificación:** casos de vinculación y envío del diseño (§11, prueba 11): el
typeahead no devuelve nada con menos de 3 caracteres, también por petición directa;
un enlace de otro host o un tema de otro prefijo, cerrado o ya usado se rechaza;
vincular guarda `showthread.php?tid=N` y la entrega precarga el tema; una entrega
rechazada libera el tema y se reenvía.

## 12. Consola de aventuras

1. Crear `op/staff/consola_aventuras.php` y el template `staff_consola_aventuras`
   (prefijo `ca-`): acceso con el bloque de `consola_mod.php`, `?tab=` con
   Solicitudes, Entregas y Narradores disponibles.
2. Solicitudes: `listar` con la cola abierta en "En revisión", conteos por estado, filas
   alternas, nota de idea, motivo de renuncia y marca "Editada el ...";
   `cambiar_estado` con `opg_av_puede_resolver` y acciones deshabilitadas con su
   explicación; `form_denegar`; `form_editar` y `actualizar_datos`; `historial`;
   `eliminar` solo para `is_staff`.
3. Entregas: `entregas` con la lista por estado, conteos y el enlace "Revisar y
   pagar" a `aventuras_recompensas.php`.
4. Narradores disponibles: `narradores`, `form_retirar_oferta` y `retirar_oferta` con
   motivo de 200.
5. Añadir la tarjeta "Aventuras" a `$consola_grupos` de `op/staff/consola_mod.php`
   con el permiso `narra` (§5.3). Es el único cambio en ese archivo.

**Verificación:** un usuario normal y un narrador usuario sin `is_narra` no pueden
entrar ni llamar a las acciones; mods, narradores del staff y staff sí, incluso
cuando sean parte de la solicitud; denegar sin
motivo falla; "Finalizar" se rechaza con una entrega pendiente; retirar una oferta
exige motivo y queda auditado; el historial de una solicitud antigua dice "Sin
historial anterior a la migración".

### 12.1 Rediseño operativo de la consola (implementado)

Implementar y verificar estrictamente en este orden. Cada punto debe conservar las
pruebas anteriores antes de comenzar el siguiente.

1. **Responsive.** Añadir la clase de página, neutralizar anchos mínimos heredados
   y acotar todos los contenedores. Probar `scrollWidth <= clientWidth` a 375, 700,
   1030 y 1440 px en Solicitudes, Entregas, Narradores, formularios e historial.
2. **Encabezado.** Reemplazar barra y caja descriptiva por el encabezado compacto
   con icono, `h1`, apoyo breve y línea de acento, conservando íntegra la cabecera
   global del foro.
3. **Conteos.** Crear un contexto único para los estados de Solicitudes y el
   contador de Entregas, sin renderizar una banda general redundante.
4. **Integridad.** Implementar `opg_av_datos_faltantes`, usarlo en tarjetas/filtros
   y volver a validarlo bajo bloqueo al aprobar. Añadir pruebas directas para cada
   campo ausente y para una solicitud completa.
5. **Filas compactas.** Separar resumen y Detalles, añadir título y antigüedad,
   truncar visualmente textos largos sin perderlos y convertir cada resultado en
   `article` con encabezado asociado.
6. **Pestañas.** Convertirlas en barra segmentada de tres columnas, añadir iconos
   Font Awesome y mantener el contador de Entregas estable y accesible.
7. **Acciones.** Calcular una acción primaria contextual; mover Editar, Historial y
   Finalizar a `Más acciones`; mantener Denegar/Eliminar como peligrosas y aplicar
   `hx-disabled-elt` durante toda mutación.
8. **Filtros.** Mantener estado, búsqueda y orden; abrir En revisión con las más
   recientes y conservar esos valores en URL y paginación.
9. **Búsqueda.** Añadir `buscar_personas`, typeahead por `#ID`, UID o nombre y
   condición que cubra creador, narrador y participantes. Probar XSS, coincidencias,
   ausencia de resultados y navegación atrás.
10. **Historial.** Renderizar línea temporal en panel/diálogo, permitir solo uno
    abierto y gestionar foco de apertura, cierre y retorno en escritorio y móvil.
11. **Paginación de narradores.** Pasar `pagina` desde controlador, renderizar el
    paginador y conservar página/filtros con carga completa y HTMX. Probar 21 ofertas.
12. **Contexto de narradores.** Ampliar consulta y tarjeta administrativa con
    frecuencia, tiers, estilo, vigencia, carga actual y finalizadas; añadir búsqueda
    y filtro de vigencia.
13. **Consultas.** Obtener conteos una vez por respuesta y pasarlos a resumen,
    pestañas y panel. Medir el número de consultas en pruebas para impedir regresión.
14. **CSS compartido.** Extraer componentes comunes a una hoja del sistema de
    aventuras, cargarla en los tres templates y retirar duplicados sin alterar el
    Tablón ni Recompensas.
15. **Accesibilidad.** Completar jerarquía `h1/h2/article`, nombres, foco visible,
    teclado, regiones vivas y enfoque del resumen tras filtrar/paginar. Ejecutar la
    matriz responsive y una revisión manual con teclado.

**Verificación final de la fase:** sintaxis PHP; suites `dominio_puro`,
`dominio_db`, `pagina_tablon`, `pagina_consola`, `pagina_recompensas` y
`concurrencia`; prueba de consultas; capturas e interacciones a cuatro anchos; sin
errores de consola, IDs duplicados ni desbordamiento. No hay migración SQL nueva.

## 13. Página de recompensas

Crear `op/staff/aventuras_recompensas.php` y `staff_aventuras_recompensas`
(prefijo `ar-`), con la estructura de §5.3.1:

1. Acceso con el mismo bloque que la consola; entrada por `?id=` o `?tid=`; sin
   entrega, aviso que lleva a la consola, sin pagar un tema suelto.
2. Datos de la entrega, análisis con valores por defecto y ajustes (tier con el de la
   solicitud como valor por defecto, extra por jugadores, participantes), filas por
   destinatario con sus ajustes y la previsualización que se recalcula con `analizar`.
3. `pagar` con la casilla de posts mínimos, el `hx-confirm` y el botón deshabilitado
   mientras falte; `form_rechazar` y `rechazar`; `completar` y `xp_dudosa`.
4. Estados de la página: pendiente, interrumpida y ya pagada.
5. No reutilizar código ni template de `recompensasAventuras.php`.

**Verificación:**

- Los montos de la previsualización y del pago coinciden con los de
  `recompensasAventuras.php` para los casos reunidos en la fase 1 (se repite aquí, con
  la página completa).
- Pagar dos veces, o dos sesiones a la vez, paga una vez a cada destinatario.
- Tras pagar, el tema tiene el mensaje de "RECOMPENSAS PROCESADAS", queda cerrado, la
  entrega queda entregada y la solicitud en estado 3.
- Un tema ya pagado con la herramienta antigua (cerrado o con el mensaje) no se
  puede pagar aquí, y un tema pagado aquí no se puede pagar allí.
- Mods, staff y narradores con permiso pueden pagar o rechazar aunque participen.
- Prueba de interrupción: forzar un error a mitad del pago y completar la entrega.

## 14. Documentación y estilo

1. Añadir a la tabla de prefijos de `style.md` los prefijos `ta-`, `ca-` y `ar-`, con
   sus templates.
2. Actualizar `docs/DATABASE.md` con las tablas y columnas nuevas de la fase 3. No
   cambiar el comentario de `expNarradorMensualActual`, que sigue en 675.
3. Revisar que `CLAUDE.md` y `docs/CLAUDE.md` no necesiten una entrada nueva; añadir
   la de la herramienta solo si el equipo la quiere.

## 15. Pruebas de regresión y entrega

Repetir, ya sobre las páginas completas, los casos de §11 del diseño:

1. Permisos como invitado, usuario sin ficha, usuario con ficha, narrador usuario,
   mod, narrador del staff y staff, incluido el aislamiento de acciones entre las tres
   páginas.
2. Contrato HTMX: `404` sin `HX-Request`, `403` sin `X-Post-Key` válido,
   `HX-Redirect` con sesión caducada, `422` y `409` con sus retargets.
3. Creación, edición, límites, estados, postulación, salida y borrado, con
   concurrencia.
4. Mis aventuras y Otras aventuras, mercado de narradores y contador.
5. Vinculación, entrega, análisis, pago único, interrupción y convivencia con
   `recompensasAventuras.php`.
6. Compatibilidad: filas antiguas, mismo número de filas e `id`, `id` siguiente mayor
   que el máximo anterior, y la calculadora actual funcionando.
7. XSS en todo texto libre (comentario, idea, Discord, mensaje de oferta, motivos,
   resumen, nombre de tema y nombre de ficha).
8. Visual a 1030px y 375px, y que no haya `$` sueltos en los templates.
9. Importar los tres templates con `import_templates.php` y comprobar que lo
   exportado coincide con lo local.

## 16. Orden de despliegue

1. Fase 2 (permisos de la calculadora) en producción, de inmediato y por separado.
2. Respaldo y migración SQL (fase 3) en producción. La calculadora actual sigue
   funcionando sin cambios.
3. Bibliotecas (fases 4 a 7) y `htmx.min.js`, sin enrutar ninguna página.
4. Páginas de jugadores (fases 8 a 11), importando el template, y verificación solo
   con staff antes de enlazarla desde el menú.
5. Consola de aventuras y tarjeta en `consola_mod.php` (fase 12).
6. `aventuras_recompensas.php` (fase 13), probada primero con una entrega de prueba
   sobre un tema de prueba.
7. Convivencia con staff durante un período acordado: la calculadora actual y
   `recompensasAventuras.php` siguen vivos y se protegen entre sí.
8. Enlazar el Tablón desde el menú.

**Vuelta atrás.** Hasta el paso 7, la experiencia de usuarios y staff sigue siendo la
calculadora actual. Las columnas y tablas nuevas son aditivas, así que no hace falta
revertirlas. Si hay un fallo grave en una página nueva, se desenlaza del menú y se
sigue con la herramienta antigua.

## 17. Retirada de las herramientas antiguas

Solo después de validar con staff y con usuarios:

1. Convertir `op/Calculadora_Tiers.php` en una redirección:
   `?peticion=N` lleva a `tablon_de_aventuras.php?tab=mias&id=N`, y el resto a
   `tablon_de_aventuras.php`.
2. Retirar el acceso a `op/staff/recompensasAventuras.php` (y borrar su copia sin uso
   `op/staff/test1.php`) cuando ya no quede ninguna aventura por pagar con ella.
3. Retirar `op_Calculadora_Tiers.html` y `jscripts/calculadoraDificultad/`, que no se
   reutilizaron.
4. Comprobar que ningún otro módulo lee la columna legacy `aventurasActivas` y, si es
   así, planificar su retirada aparte.
5. Mantener `entregar_recompensas*.php` para entregas manuales.

## 18. Riesgos y puntos de atención

- **Pagos.** Un error en la biblioteca de recompensas se traduce en recompensas
  erróneas. Por eso la fase 6 no se supera sin la comparación con la página antigua y
  la fase 7 se prueba con fichas de prueba.
- **Motores mixtos.** `mybb_users` (experiencia) es MyISAM y no admite `ROLLBACK`; el
  diseño de pago por destinatario con estados de experiencia es la mitigación, y
  conviene revisar con calma el caso de experiencia dudosa.
- **Plantillas con `eval()`.** Cualquier `$` suelto en el HTML de un template se
  interpreta como variable PHP.
- **HTMX con `allowEval: false`.** No se pueden usar `hx-on:*` ni filtros de
  `hx-trigger` con expresiones.
- **Coexistencia.** Las dos páginas de jugadores comparten tabla con la calculadora
  durante la transición: las solicitudes nuevas se ven allí sin enemigos ni
  dificultad, y las antiguas aquí sin isla ni día.
- **Cierre de temas.** Un tema cerrado no se puede entregar. Si el narrador lo cierra
  antes, el staff debe reabrirlo.
- **Caché de assets.** Si se sirve `htmx.min.js` desde `/jscripts/`, recordar que
  Cloudflare cachea esa ruta de forma agresiva; versionar el archivo al actualizarlo.

## Notas de la implementación

### Revisión de recompensas (2026-10-08)

Estado: **implementada y verificada, pendiente de migración e importación del
template en producción**.

1. Añadir `narrador_uid` a `op_aventuras_entregas`, rellenar entregas antiguas
   desde `enviado_por` y conservar fallback durante el despliegue.
2. Guardar el narrador asignado al crear o reenviar una entrega. Pasarlo de forma
   explícita al analizador y retirar cualquier lectura de `[Narrador]`.
3. Actualizar las pruebas de dominio, base de datos y página para cubrir narrador
   con posts, narrador sin posts y cambio posterior de asignación.
4. Sustituir el banner y estilos duplicados del template por una cabecera compacta
   y estilos `ar-` compartidos en `jscripts/aventuras.css`.
5. Reorganizar la vista en Entrega, Criterios y actividad, Destinatarios y
   Validación/acciones; unificar avisos y explicar bloqueos junto al pago.
6. Activar vista previa HTMX al cambiar controles, mantener el botón manual,
   añadir barra adherente y diálogo propio de confirmación.
7. Mostrar recompensas como valores separados y convertir destinatarios en
   bloques verticales a 700 px o menos.
8. Ejecutar suites de recompensas, dominio y páginas, validar HTML y revisar
   visualmente los anchos 375, 700, 1030 y 1440 px.

Verificación automatizada completada: dominio puro 136/0, paridad de recompensas
10293/0, dominio DB 400/0, Tablón 159/0, Consola 84/0, concurrencia 19/0 y página
de recompensas 57/0. La comprobación visual final queda para el template importado
en el foro, donde están disponibles la cabecera global y los assets reales.

Diferencias respecto a lo planificado, ya aplicadas en el código:

- **Fase 2 (permisos de Calculadora_Tiers.php):** solo se reactivaron `update_meta` (exige gestión) y `delete` (exige mod o staff); `claim_narrador`, `update_enemigos`, `vincular_aventura` y `save` exigen sesión. La calculadora antigua sigue funcionando.
- **Acciones extra en la página de jugadores:** `quitar_chip` y `cerrar_modal` (devuelven vacío) para vaciar un chip o cerrar la ventana sin JavaScript.
- **Consola (`op/staff/consola_aventuras.php`):** acciones `tab`, `listar`, `entregas`, `narradores`, `buscar_personas`, `form_denegar`, `cambiar_estado`, `form_editar`, `actualizar_datos`, `buscar_islas`, `elegir_isla`, `historial`, `eliminar`, `form_retirar_oferta`, `retirar_oferta` y `cerrar`. Filtros y paginación publican una URL canónica recargable.
- **Rediseño de Consola del 2026-10-07:** completados los quince pasos de §12.1 sin
  una migración SQL nueva. Verificado con las suites de dominio y páginas, revisión
  visual y `scrollWidth = clientWidth` a 375, 700, 1030 y 1440 px.
- **Pago (`op/staff/aventuras_recompensas.php`):** acciones `analizar`, `pagar`, `completar`, `form_rechazar`, `rechazar`, `xp_dudosa` y `cerrar`. Si el pago queda a medias se muestra el estado real con el motivo y "Completar entrega".
- **Tope mensual del narrador:** 675, igual que `recompensasAventuras.php` (que no cambia).
- **Pruebas:** `tests/aventuras/` (`dominio_puro`, `recompensas`, `dominio_db`, `concurrencia`, `pagina_tablon`, `pagina_consola`, `pagina_recompensas`). Las de base de datos usan un MySQL 5.7 desechable (ver `bootstrap_db.php`).
- **Templates nuevos a importar:** `op_tablon_de_aventuras`, `staff_consola_aventuras`, `staff_aventuras_recompensas` (con `import_templates.php`).
- **Revisión de interfaz 2026-10-05:** implementadas las cuatro pestañas,
  estadísticas filtradas, desglose de activas, paginación de 20, selección
  progresiva de participantes y narrador y vinculación por participantes.
  Verificado en MySQL 5.7 desechable: dominio puro (135), dominio DB (371),
  concurrencia (16, incluye dos solicitudes para la última plaza), tablón
  (139, incluye filtros reactivos por solicitud y personaje, separación por rol, límites, descripción obligatoria, títulos, fechas y selección única de tema), consola (76), página de recompensas (56) y paridad de recompensas
  (10293), sin fallos. Playwright/Chrome verifica filtros, paginación, refresco
  por eventos, chips y ausencia de desbordes a 375, 700 y 1030px.
  El formulario se verifica además a 375, 1030 y 1440px: alineación de isla,
  día y tier, chips debajo, descripción obligatoria y nuevos placeholders.
  Pendiente de importar el template y validar en el foro con staff.
- **Revisión visual 2026-10-06:** implementados en el orden documentado en
  `102_TablonDiseno.md` los ocho cambios del tablón: tarjetas compactas y
  descripciones expandibles, metadatos agrupados, acción principal contextual,
  cabecera coherente con el foro, preferencias estructuradas de narrador, contador sin
  narrador integrado, colores semánticos y densidad Compacta/Expandida. La tabla
  de ofertas se amplía con `aventuras_tablon_diseno_migration.sql`; desplegar la
  migración antes del PHP e importar después `op_tablon_de_aventuras`.
- **Verificación visual 2026-10-06:** Playwright/Chrome comprueba ausencia de
  desbordes y las interacciones nuevas a 375, 700, 1030 y 1440 px. Dominio DB
  pasa 384 pruebas y la página del tablón 155 pruebas, sin fallos.
