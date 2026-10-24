# Requisitos: Sistema permanente de logros

## 1. Propósito

Este documento define un sistema permanente y general de logros para One
Piece Gaiden. El sistema tendrá su propia página y su propio modelo de datos;
no pertenecerá a Halloween ni a ningún otro evento concreto.

Los distintos sistemas del foro podrán conceder emblemas mediante una
interfaz común. Halloween 2026 será únicamente el primer consumidor conocido:
concederá un emblema permanente a quienes participen en el evento, según lo
definido en `106_Requirements_Halloween_2026.md`.

## 2. Principios

- **Independiente:** `/op/logros.php` funciona aunque no haya un evento activo.
- **Permanente:** un logro histórico no desaparece al terminar la actividad
  que lo originó.
- **Reutilizable:** eventos, aventuras, concursos y otros sistemas podrán
  conceder logros sin crear tablas propias.
- **Auditable:** cada concesión o retirada deja constancia de su origen y de
  quién la ejecutó.
- **Idempotente:** un personaje no puede recibir dos veces el mismo logro por
  reintentos o acciones simultáneas.
- **Data-driven:** nombres, textos, imágenes, visibilidad y orden se configuran
  como datos, no mediante condiciones repartidas por el código.

## 3. Alcance

### 3.1 Incluido

- Página pública de logros propios y de otros personajes.
- Catálogo de logros y emblemas.
- Registro permanente de logros obtenidos.
- Logros visibles, ocultos y secretos.
- Categorías y filtros.
- Concesión automática desde otros sistemas.
- Concesión o retirada manual por staff con auditoría.
- Primer logro conmemorativo de Halloween 2026.
- Diseño preparado para cientos de logros sin degradar la página.

### 3.2 Fuera de alcance inicial

- Mostrar emblemas en la portada o firma de la ficha.
- Puntos, niveles o recompensas económicas por coleccionar logros.
- Rankings competitivos por cantidad de logros.
- Intercambiar, regalar o vender emblemas.
- Permitir que los usuarios oculten logros obtenidos individualmente.

Estas funciones podrán añadirse después sin cambiar el registro histórico.

## 4. Página `/op/logros.php`

### 4.1 Consulta

- Sin parámetros muestra los logros del personaje conectado.
- `?uid={uid}` muestra la colección pública de otro personaje.
- Si el UID no tiene ficha, se muestra un estado vacío claro.
- La página sigue siendo accesible aunque el personaje todavía no tenga
  logros.

### 4.2 Cabecera

La cabecera mostrará:

- nombre y avatar del personaje;
- cantidad de logros obtenidos;
- cantidad total de logros públicos disponibles;
- fecha del logro más reciente, si existe;
- selector para consultar otro personaje, siguiendo el patrón visual de la
  bitácora y el Tablón de aventuras.

### 4.3 Colección

- Cuadrícula responsive de emblemas.
- Filtros por categoría, estado y periodo.
- Orden por fecha de obtención, orden del catálogo o nombre.
- Los logros obtenidos muestran emblema, nombre, descripción y fecha.
- Los disponibles pero no obtenidos muestran su condición cuando sea pública.
- Los secretos no obtenidos muestran una silueta y un texto genérico, sin
  revelar nombre, imagen ni condición.
- Un logro histórico puede seguir visible aunque ya no sea obtenible.
- Al abrir un logro se muestra su detalle sin abandonar la colección.

### 4.4 Estados

- **Obtenido:** visible a color y con fecha.
- **Disponible:** todavía puede conseguirse.
- **Bloqueado:** existe, pero su periodo o requisito aún no permite obtenerlo.
- **Histórico:** ya no se puede conseguir, pero permanece en las colecciones
  de quienes lo obtuvieron.
- **Secreto:** oculta su identidad hasta ser descubierto.
- **Inactivo:** retirado del catálogo público sin borrar concesiones pasadas.

## 5. Definición de un logro

Cada logro tendrá:

- identificador interno;
- `slug` único y estable;
- nombre;
- descripción pública;
- texto bloqueado opcional;
- imagen o emblema;
- categoría;
- rareza opcional;
- orden de presentación;
- fecha inicial y final opcionales;
- condición descriptiva;
- tipo de visibilidad;
- estado activo/inactivo;
- metadatos limitados para su sistema de origen.

Editar el nombre o la descripción actualiza el catálogo, pero no altera la
fecha ni el origen de las concesiones existentes. El `slug` no debe cambiar
una vez usado por otro sistema.

## 6. Concesión

### 6.1 Servicio común

Los demás módulos llamarán a un único servicio, con una operación equivalente
a:

```php
grant_achievement($uid, $slug, $sourceType, $sourceId, $metadata = []);
```

La firma definitiva se decidirá en el plan técnico, pero debe:

- validar que el usuario y el logro existen;
- comprobar fechas y estado del logro;
- insertar solo si no existe la combinación logro/personaje;
- registrar origen, referencia y fecha;
- devolver si fue concedido, ya existía o fue rechazado;
- funcionar correctamente bajo concurrencia.

La protección definitiva será una restricción única en base de datos, no solo
una consulta previa en PHP.

### 6.2 Concesión automática

Cada sistema externo decide cuándo se cumple su condición y llama al servicio.
El módulo de logros no debe conocer las reglas internas de posts, aventuras,
cofres o eventos.

Esto evita que `/op/logros.php` recorra tablas grandes para descubrir logros
durante cada carga.

### 6.3 Concesión manual

Staff podrá conceder un logro indicando:

- personaje;
- logro;
- motivo;
- referencia opcional.

La acción queda auditada con UID y fecha. Conceder de nuevo un logro existente
no crea un duplicado.

### 6.4 Retirada

- Retirar requiere un motivo obligatorio.
- La retirada no borra el registro original: crea un estado o movimiento de
  revocación auditable.
- Un logro revocado puede volver a concederse posteriormente, conservando el
  historial de ambas acciones.
- Las retiradas automáticas quedan fuera del alcance inicial.

## 7. Emblema de Halloween 2026

Halloween solo se relacionará con este sistema mediante una concesión.

- Slug propuesto: `halloween-2026-participante`.
- Nombre propuesto: **Participante de la Cosecha Maldita**.
- Categoría: eventos.
- Tipo: histórico y visible.
- Puede obtenerse una sola vez por personaje.
- Su condición exacta y las actividades que cuentan se definen en
  `106_Requirements_Halloween_2026.md`.
- El logro permanece en la colección después del cierre del evento.
- Una compra, reembolso, ajuste administrativo o regalo masivo no basta por sí
  solo para obtenerlo.
- La imagen definitiva del emblema queda pendiente.

La página de logros puede mostrar que pertenece a Halloween 2026, pero no debe
depender de `/op/halloween2026.php` para funcionar ni para recuperar sus datos.
La página de Halloween no mostrará ningún enlace o acceso visible a la página
de logros; la relación entre ambos sistemas será únicamente la concesión
interna del emblema.

## 8. Herramienta de staff

Se creará una herramienta propia para administrar logros, separada de las
consolas de eventos.

Debe permitir:

- crear y editar logros;
- subir o seleccionar el emblema;
- definir categoría, visibilidad, fechas y orden;
- activar o retirar un logro del catálogo;
- buscar concesiones por personaje o logro;
- conceder o revocar con motivo;
- consultar el historial completo;
- exportar catálogo y concesiones a CSV.

Eliminar físicamente un logro que ya tenga concesiones no estará permitido.
Podrá marcarse como inactivo.

## 9. Modelo de datos propuesto

### 9.1 Catálogo

`mybb_op_logros`

- `id`;
- `slug` único;
- `nombre`, `descripcion`, `texto_bloqueado`;
- `imagen`;
- `categoria`, `rareza`, `visibilidad`;
- `fecha_inicio`, `fecha_fin`;
- `orden`, `activo`;
- `metadata` limitada;
- fechas de creación y actualización.

### 9.2 Colección del personaje

`mybb_op_logros_usuario`

- `id`;
- `achievement_id`, `uid`;
- `earned_at`;
- `source_type`, `source_id`;
- `metadata` limitada;
- `granted_by_uid` cuando sea manual;
- estado actual.

Debe existir una combinación única `(achievement_id, uid)` para los logros no
repetibles.

### 9.3 Historial

`mybb_op_logros_historial`

- logro y personaje;
- acción: concesión, revocación o restauración;
- origen y referencia;
- motivo;
- UID responsable;
- fecha.

### 9.4 Categorías

Las categorías pueden comenzar como valores controlados por el catálogo. Si
se necesita administrarlas con textos o imágenes propias, se normalizarán en
una tabla separada durante el plan técnico.

## 10. Seguridad y permisos

- La colección pública es de solo lectura.
- Crear, editar, conceder y revocar requiere permisos de staff definidos con
  los helpers existentes del proyecto.
- Toda acción POST requiere token CSRF de MyBB.
- Slugs, UID, fechas, rutas y metadatos se validan en servidor.
- El nombre de archivo del emblema no puede permitir rutas arbitrarias.
- La interfaz nunca será la única barrera de permisos.
- El historial administrativo no se puede editar desde la interfaz.

## 11. Rendimiento

- La colección se consulta mediante joins indexados entre usuario y catálogo.
- Índices mínimos: `slug` único, `(uid, earned_at)`,
  `(achievement_id, uid)` único y `(categoria, activo, orden)`.
- No se recalculan condiciones al cargar la página.
- Emblemas con dimensiones reservadas, formatos optimizados y carga diferida.
- Filtros simples pueden funcionar en cliente cuando el catálogo sea pequeño;
  con catálogos grandes se paginarán en servidor.
- Los totales de cabecera se obtienen en una consulta agregada indexada o se
  almacenan en caché breve.

## 12. Accesibilidad y diseño

- Seguir `docs/style.md` y los componentes actuales del foro.
- No comunicar rareza o estado únicamente mediante color.
- Cada emblema tendrá texto alternativo.
- Foco visible y navegación por teclado en filtros y detalles.
- El nombre y la descripción nunca deben quedar ocultos detrás de un tooltip.
- Las imágenes no deben desplazar la cuadrícula mientras cargan.
- En móvil se mantendrá legible el nombre del logro sin reducirlo a iconos
  indescifrables.

## 13. Archivos previstos

- `op/logros.php`: página pública.
- `op/staff/logros.php`: catálogo, concesiones e historial.
- `inc/plugins/lib/logros.php`: servicio común.
- plantillas MyBB de colección, detalle y administración.
- CSS y JavaScript propios y versionados.
- migración SQL reversible con el logro inicial de Halloween 2026.

## 14. Pruebas mínimas

- Conceder un logro nuevo.
- Repetir la misma concesión sin duplicarlo.
- Dos concesiones simultáneas del mismo logro.
- Consultar colección propia y ajena.
- Logro público, bloqueado, histórico, secreto e inactivo.
- Revocar y restaurar conservando historial.
- Intentos de administración sin permisos o sin CSRF.
- Catálogo vacío y personaje sin logros.
- Colección extensa en escritorio y móvil.
- Concesión desde Halloween sin que la página de logros conozca sus reglas.

## 15. Criterios de aceptación

1. `/op/logros.php` funciona como página independiente y permanente.
2. Un sistema externo puede conceder un logro usando un `slug` estable sin
   escribir directamente en las tablas.
3. Un personaje no puede tener duplicado un logro no repetible.
4. Los logros secretos no revelan información antes de obtenerse.
5. Staff puede conceder o revocar y siempre queda un historial auditable.
6. Desactivar un logro no elimina las concesiones históricas.
7. El emblema de Halloween 2026 permanece visible después del evento.
8. Ninguna carga de la colección recorre tablas de posts o eventos para
   recalcular condiciones.

## 16. Decisiones pendientes

- imagen definitiva del emblema de Halloween 2026;
- nombre definitivo del logro de participación;
- categorías iniciales del catálogo;
- rarezas y si tendrán efecto puramente visual;
- grupos exactos con acceso a la herramienta de staff;
- si los logros bloqueados no secretos se mostrarán siempre o solo durante su
  periodo de obtención;
- futura integración de emblemas en la ficha del personaje.
