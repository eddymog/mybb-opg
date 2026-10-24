# Requisitos: Sistema global de notificaciones

Estado: **borrador para revisión**. Aún no hay diseño técnico ni implementación.

## 1. Propósito

Hoy cada sistema avisa a su manera: el Tablón de aventuras tiene su propia
tabla de novedades (`mybb_op_aventuras_notificaciones`), la Bitácora tiene
lecturas y un contador cacheado en el header, y la mayoría de las
herramientas de gestión (fichas, recompensas, técnicas, objetos, peticiones...)
cambian cosas del usuario sin avisarle.

Este documento define **un único sistema de notificaciones** para todo el foro.
Reúne en un solo lugar lo mejor de `bitacora.php` (contador barato en el header,
marca de lectura, caché con invalidación) y de `tablon_de_aventuras.php`
(eventos con clave única, lista de novedades con historial), de modo que
cualquier módulo pueda avisar a un usuario con una sola llamada.

Regla de producto: **cualquier cambio que se haga sobre un usuario o sobre algo
suyo mediante gestión debe generar una notificación para ese usuario.**

## 2. Principios

- **Un solo lugar:** una tabla, una librería de envío y una página de lectura.
  Los módulos no crean tablas de avisos propias.
- **Lo global es solo el contador:** lo único que se calcula en cada página es
  el número de notificaciones sin ver. Todo lo demás se carga al abrir
  `/op/notificaciones.php`.
- **Barato en cada recarga:** el header no hace consultas pesadas ni uniones.
- **No rompe la acción original:** si falla el aviso, la acción de gestión
  continúa. Una notificación nunca bloquea un pago, una aprobación o un cambio
  de ficha.
- **Idempotente:** reintentar la misma acción no duplica el aviso.
- **Data-driven:** los tipos de notificación (texto, icono, categoría, enlace)
  se declaran en un catálogo, no con condiciones repartidas por el código.
- **Privado:** cada usuario solo ve las suyas.

## 3. Alcance

### 3.1 Incluido

- Tabla única de notificaciones y librería de envío reutilizable.
- Página `/op/notificaciones.php` con la lista completa del usuario.
- Contador de no leídas en el header, cacheado.
- Catálogo de tipos y categorías.
- Importancia de las notificaciones y sección de importantes en la página.
- Marcado como leída solo cuando el usuario pulsa "Visto" (una a una o todas a la vez).
- Migración de las notificaciones del Tablón de aventuras al sistema nuevo.
- Conexión del Tablón de aventuras y de la Bitácora (los orígenes ya existentes).
- Limpieza automática de notificaciones antiguas.

### 3.2 Fuera de alcance (primera fase)

- Envío por correo, MP o Discord. El diseño debe permitirlo después (columna
  de envío pendiente, como la que ya tiene el Tablón), pero no se construye ahora.
- Avisos de tiempo (entrenamiento, oficio o crafteo completado, recompensa
  diaria). El header ya los resuelve en `global.php` y siguen como están.
- Avisos masivos a todo el foro (anuncios). Se tratan como una herramienta
  aparte en el futuro (ver decisión 1 del apartado 12).
- Conexión del resto de herramientas de gestión del apartado 7 (fichas,
  recompensas, técnicas y objetos, cofres, peticiones, tripulación, sistema). Se
  hará más adelante, herramienta por herramienta; el sistema queda preparado para
  ello.
- Preferencias por usuario para silenciar categorías.
- Sustituir las alertas internas de MyBB (menciones, citas, MP).

## 4. Conceptos

- **Notificación:** un hecho, dirigido a un usuario, con un tipo, un enlace
  opcional y unos datos mínimos para escribir su texto.
- **Destinatario:** el usuario que recibe el aviso (`uid`).
- **Actor:** quien causó el cambio (staff, otro usuario o el sistema, `0`).
- **Origen:** quién provoca el aviso. Hay dos tipos principales:
  - **Gestión:** acciones de staff o moderadores sobre algo del usuario, como las
    del Tablón de aventuras (aprobar, denegar, rechazar una entrega, pagar).
  - **Usuario:** acciones de otros usuarios que afectan al destinatario, como las
    de la Bitácora (alguien publica en un tema que sigues, se inicia una ronda
    narrada, se asigna o se retira un narrador).
  Un tercer valor, **sistema**, cubre los avisos automáticos sin actor (por
  ejemplo, una propuesta que vence).
- **Importancia:** `normal` o `importante`. Las importantes avisan de algo que el
  usuario debe atender (por ejemplo, perder una akuma por inactividad) y se muestran
  en una sección propia arriba de la lista. Los primeros avisos importantes se
  definen en [109_Requirements_Akuma.md](109_Requirements_Akuma.md).
- **Categoría:** agrupación temática para filtrar la lista: Aventuras, Ficha,
  Recompensas, Técnicas y objetos, Peticiones, Temas y rol (Bitácora),
  Tripulación, Sistema.
- **Leída:** una notificación pasa a *leída* únicamente cuando el usuario la
  marca como **Visto**. Abrir la página, verla en pantalla o seguir su enlace
  no la marca por sí solo.
- **Contador:** número de notificaciones del usuario que siguen sin marcar como
  vistas.

## 5. Requisitos funcionales

### 5.1 Creación

1. Existe una función única de envío, por ejemplo
   `opg_notificar($destinatarios, $tipo, $datos, $opciones)`, que acepta uno o
   varios `uid`.
2. Cada notificación lleva: destinatario, tipo, actor, enlace interno
   opcional, datos en JSON, clave de evento y fecha.
3. La **clave de evento** es obligatoria y única por destinatario (por ejemplo
   `entrega:812:entregada`). Si ya existe, no se inserta de nuevo.
4. Por defecto no se notifica al actor sobre su propia acción. Una opción
   explícita permite saltarse esta regla (por ejemplo, para que el staff reciba
   la confirmación de un lote).
5. Si el destinatario no existe o es `0`, la llamada no hace nada y no falla.
6. El envío a varios destinatarios se hace en una sola sentencia.
7. Un error de base de datos se registra y se devuelve como `false`; nunca
   lanza excepción hacia el módulo que llama.
8. Si la acción de gestión corre dentro de una transacción, la notificación se
   inserta dentro de ella, para que no exista un aviso de algo que se revirtió.

**Agrupación (eventos repetitivos):**

8a. Un tipo de notificación puede declararse como *agrupable*. Las publicaciones
    nuevas en un tema seguido de la Bitácora lo son.
8b. Mientras exista una notificación agrupable **no leída** del mismo tipo y
    tema para ese destinatario, un nuevo evento no crea otra fila: actualiza la
    existente (suma uno a su cantidad, guarda el último autor y el último post y
    renueva su fecha para que suba al principio de la lista).
8c. Si la notificación ya fue marcada como **Visto**, el siguiente evento crea
    una nueva, con cantidad 1.
8d. El contador de la campana cuenta notificaciones, no eventos: un tema con
    diez publicaciones nuevas suma uno. La clave de evento de las agrupables
    incluye el tema (por ejemplo `bitacora:post:TID`) y se reutiliza mientras
    la fila esté no leída, de modo que el evento nuevo se resuelve en una sola
    sentencia atómica (`INSERT ... ON DUPLICATE KEY UPDATE`), sin leer antes de
    escribir. Al marcarla como Visto, su clave se reescribe (por ejemplo
    `bitacora:post:TID:leida:ID`) para liberar la clave original y que el
    siguiente evento cree una fila nueva sin chocar con la restricción de unicidad.

### 5.2 Página de notificaciones

9. `/op/notificaciones.php` muestra las notificaciones del usuario conectado,
   de la más reciente a la más antigua, y es lo único que las carga.
10. Las importantes no leídas aparecen en una sección propia arriba de todo. Las no leídas
    aparecen destacadas y arriba del historial; las leídas aparecen
    atenuadas. Cada no leída tiene un botón **Visto**.
11. Cada fila muestra icono, texto, quién lo causó (si aplica), fecha relativa
    y un enlace a donde ocurrió el cambio.
12. Filtro por categoría y por origen (Todas, De gestión, De usuarios). El filtro
    no recarga el contador del header.
13. Paginación por el id de la última fila (carga más), no por número de página.
    Tamaño de página: 30.
14. Estado vacío claro: "No tienes notificaciones".
15. Abrir la página **no** marca nada como leído. El usuario marca una
    notificación con su botón **Visto**, o todas con **Marcar todas como vistas**
    (respetando el filtro de categoría activo si lo hay). Ambas acciones
    actualizan la fila y el contador del header sin recargar la página (HTMX).
    Una notificación leída no puede volver a marcarse como no leída.
16. Las filas con enlace van directamente a la página relevante; las que
    apuntan a algo ya borrado muestran el texto sin enlace.
17. La página usa el mismo estilo visual y las mismas convenciones HTMX del
    Tablón de aventuras.

### 5.3 Contador en el header

18. El header muestra una campana con el número de no leídas, oculto cuando es
    cero y limitado a "99+". Si entre las no leídas hay alguna importante, la campana
    lo indica visualmente sin cambiar el número. Ocupa el espacio del header que usa la Bitácora.
19. El valor se inyecta mediante un hook del plugin (`global_intermediate`), no
    editando `global.php`, siguiendo el patrón de `op_bitacora.php`.
20. Los invitados no ejecutan ninguna consulta.
21. El enlace de la campana lleva a `/op/notificaciones.php`.
22. El contador es accesible: etiqueta de texto con el número, no solo color.
23. El header no muestra textos de notificaciones, solo el número.

### 5.4 Catálogo de tipos

24. Cada tipo se declara una vez: clave, categoría, icono, plantilla de texto y
    función que construye el enlace a partir de los datos.
25. Si una fila tiene un tipo que ya no está en el catálogo, se muestra con un
    texto genérico ("Actualización") y no rompe la página.
26. Los datos guardados contienen solo lo necesario para escribir el texto
    (nombres, cantidades, motivo); no se guardan textos ya formateados ni HTML.
27. Todo texto se escapa al mostrarse.

### 5.5 Retención

28. Se conservan como máximo **90 días** de notificaciones por usuario y un
    máximo de **500** filas por usuario; lo más antiguo se elimina primero. Esto
    incluye las no leídas: una notificación sin leer también se elimina al
    cumplir los 90 días.
29. La limpieza se ejecuta en una tarea programada (la tarea de mantenimiento
    ya existente del Tablón es un buen sitio), nunca durante una petición de usuario.

## 6. Rendimiento y caché

Este es el requisito más importante del sistema.

30. **Header:** como máximo **una** consulta por página, por clave primaria, sin
    uniones y sin `COUNT(*)` sobre la tabla de notificaciones.
31. **Contador materializado:** una tabla pequeña con una fila por usuario
    guarda el número de no leídas. Se incrementa en el mismo momento en que se
    escribe una notificación y se reduce cuando el usuario la marca como vista
    (en una sola sentencia, solo si pasó de no leída a leída). Así el header
    nunca recuenta.
32. **Caché por petición:** el valor se lee una sola vez por petición, aunque lo
    pidan varias partes de la página.
33. **Autoreparación:** si falta la fila del contador, o su última
    actualización supera las 24 horas, se recalcula con un `COUNT` acotado por
    los índices y se vuelve a guardar. Un desfase nunca debe persistir.
34. **Lista:** una única consulta indexada por `(uid, id)` (y `(uid, leida_en, id)`
    para las no leídas), sin uniones; los
    nombres que se necesitan para el texto se guardan en los datos al crear la
    notificación.
35. **Escritura:** cada aviso cuesta un `INSERT` y un `UPDATE` del contador del
    destinatario. Un lote de N destinatarios usa una sola inserción múltiple.
36. **Sin bloqueos largos:** el contador se incrementa con una sentencia
    atómica (`sin_leer = sin_leer + 1`), sin leer antes de escribir.
37. **Tabla ausente:** si las tablas aún no existen (despliegue a medias), el
    header muestra cero y no lanza error. Es el mismo criterio que usa
    `opg_av_tabla_existe()`.
38. Objetivo medible: con 10.000 usuarios y 500.000 notificaciones, el header
    añade menos de 2 ms por petición y la página de notificaciones responde
    en menos de 100 ms en el servidor.

## 7. Orígenes de notificaciones

Todo lo que se hace desde las herramientas de gestión y afecta a un usuario debe
avisarle. En la primera entrega solo están conectados el **Tablón de aventuras** y
la **Bitácora**; el resto de filas de esta lista son el objetivo para entregas
posteriores. Lista inicial, a completar al diseñar:

| Categoría | Evento | Origen en el código |
|---|---|---|
| Aventuras | Invitaciones, candidaturas, aprobación, denegación, entrega rechazada, recompensas entregadas, narrador que sale, ofertas por vencer | Ya existe en `inc/plugins/lib/aventuras*.php`; se migra |
| Aventuras | Se envía una entrega (aviso a los demás participantes; el staff usa su contador de entregas) | `opg_ent_enviar` |
| Ficha | Ficha aprobada o rechazada | `fichas_en_cola.php`, `solicitudes_creacion.php` |
| Ficha | Cambios hechos por staff en atributos, pasivas o ficha | `modificar_ficha.php`, `ficha_atributos*.php`, `ficha_recalcular_pasivas.php` |
| Recompensas | Experiencia, Nikas, Berries, Kuros o reputación entregados | `entregar_recompensas*.php`, `berries_masivo.php`, `salario_*.php`, `recompensasAventuras.php` |
| Técnicas y objetos | Técnica, virtud u objeto otorgado, modificado o retirado | `tecnicas_aprender.php`, `tecnicas_ficha.php`, `virtudes_ficha.php`, `objetos_ficha.php`, `tecnicas_bulk_update.php` |
| Técnicas y objetos | Akuma asignada, expirada o inactivada | `akumas_*.php` |
| Cofres | Cofres repartidos | `cofres_repartir.php`, `cofres_gestion.php` |
| Peticiones | Petición o bug respondido, aceptado o cerrado | `peticiones_admin.php`, `peticiones_bugs.php` |
| Peticiones | Resurrección concedida | `resurreciones.php` |
| Temas y rol (origen usuario) | Alguien publica en un tema que sigues; se inicia una ronda narrada; se asigna, cambia o retira el narrador de un tema | Eventos de `op_bitacora_registrar_evento()` en `inc/plugins/op_bitacora/functions.php` |
| Tripulación | Cambios hechos por staff en tu tripulación o afiliado | `gestionar_afiliados.php`, módulo de tripulaciones |
| Sistema | Avisos de staff dirigidos a un usuario | `avisos.php`, `consola_mod.php` |

Reglas de este apartado:

39. Un módulo no se considera conectado hasta que cada acción de escritura que
    modifica algo del usuario llama al envío.
40. Las acciones masivas (por ejemplo `berries_masivo.php`) envían **una** notificación
    por destinatario con el resumen de su cambio, no una por cada fila de la ficha.
41. Las acciones internas sin efecto para el usuario (reparaciones de datos,
    migraciones, recálculos silenciosos) no generan aviso. Para ellas existe
    la opción `silenciosa`, y cada uso debe justificarse en el código.

## 8. Migración del Tablón de aventuras

42. Las filas de `mybb_op_aventuras_notificaciones` se copian a la tabla nueva
    conservando fecha y estado de lectura (`read_at` pasa a `leida_en`).
43. `opg_av_notificar()` y `opg_av_notificar_partes()` pasan a ser envoltorios
    de la función global, sin cambiar su firma, para no tocar a los llamadores.
44. El panel "Novedades" de Mis aventuras se mantiene, pero lee de la tabla
    nueva filtrando por la categoría Aventuras, o se sustituye por un enlace a
    la página global (ver decisión 3 del apartado 12).
45. La tabla antigua no se borra hasta confirmar la migración en producción.
46. Las claves de evento existentes se conservan, de modo que no se duplican
    avisos al reejecutar la migración.

## 9. Modelo de datos (orientativo)

El diseño final corresponde a `208_DesignPlan_Notificaciones.md`. Como mínimo:

```
mybb_op_notificaciones
  id            INT UNSIGNED AUTO_INCREMENT PK
  uid           INT UNSIGNED       destinatario
  tipo          VARCHAR(48)        clave del catálogo
  categoria     VARCHAR(24)        copia del catálogo para filtrar sin unir
  origen        VARCHAR(8)         gestion | usuario | sistema (copia del catálogo)
  importancia   VARCHAR(10)        normal | importante (copia del catálogo)
  actor_uid     INT UNSIGNED       0 = sistema
  clave_evento  VARCHAR(96)
  url           VARCHAR(255) NULL  enlace interno
  cantidad      INT UNSIGNED       1; sube en las notificaciones agrupables
  datos_json    TEXT NULL
  created_at    INT UNSIGNED
  leida_en      INT UNSIGNED NULL  NULL = no leída; la fija el botón Visto
  UNIQUE (uid, clave_evento)
  KEY    (uid, id)
  KEY    (uid, categoria, id)
  KEY    (uid, leida_en, id)

mybb_op_notificaciones_contador
  uid           INT UNSIGNED PK
  sin_leer      INT UNSIGNED       contador materializado de no leídas
  actualizado   INT UNSIGNED
```

Los nombres de tabla siguen la convención `mybb_op_*`. No se usan claves
foráneas, como en el resto del proyecto. La creación se entrega en un
`docs/notificaciones_migration.sql` idempotente.

## 10. Seguridad y privacidad

47. Un usuario solo puede leer sus notificaciones; el `uid` siempre sale de la
    sesión, nunca de un parámetro.
48. Las acciones de la página (cargar más, filtrar) validan sesión; las que
    modifican estado validan CSRF con `verify_post_check()`.
49. Los enlaces guardados deben ser rutas internas. Cualquier URL externa se
    descarta al crear la notificación.
50. Las notificaciones no incluyen datos que el destinatario no podría ver en
    el foro (por ejemplo, el motivo interno de un staff sobre otro usuario).
51. El modo espejo no tiene tratamiento especial: lo que el usuario puede hacer con
    sus notificaciones, también se puede hacer desde ese modo.
52. Las cuentas con cambio de personaje (account switcher) usan el `uid` activo.

## 11. Criterios de aceptación

- [ ] Una acción de cada módulo conectado en la primera entrega (Tablón y Bitácora) genera su notificación.
- [ ] Reintentar la misma acción no duplica la notificación.
- [ ] El header muestra el número correcto, lo oculta en cero y muestra "99+" desde 100.
- [ ] El header ejecuta una sola consulta por clave primaria y ninguna para invitados.
- [ ] Abrir `/op/notificaciones.php` no cambia el contador.
- [ ] Pulsar **Visto** en una notificación baja el contador en uno, y volver a pulsarlo no lo baja de nuevo.
- [ ] **Marcar todas como vistas** deja el contador en cero (o descuenta solo la categoría filtrada).
- [ ] Las notificaciones nuevas llegadas mientras la página estaba abierta siguen
      contando al recargar, y las no leídas permanecen aunque se visite la página.
- [ ] La lista pagina por id y mantiene el filtro de categoría.
- [ ] Un fallo al insertar una notificación no impide completar el pago, la
      aprobación ni el cambio de ficha que la originó.
- [ ] Con las tablas ausentes el foro carga con normalidad y la campana muestra cero.
- [ ] Las notificaciones del Tablón de aventuras se ven en la página global con su
      fecha y su estado originales.
- [ ] El contador se autorepara si se altera manualmente.
- [ ] La tarea de limpieza respeta 90 días y 500 filas por usuario.
- [ ] La página funciona en móvil, con modo oscuro y sin desbordamiento horizontal.

## 12. Decisiones y pendientes

Decisiones tomadas:

1. **Avisos masivos:** quedan fuera de la primera base. Más adelante podrá
   crearse una herramienta de avisos para esto (por ejemplo, una tabla de
   anuncios con una sola fila y marca de visto por usuario), pero no es necesaria ahora.
2. **Seguir el enlace:** no marca la notificación como leída. Solo la marca el
   botón **Visto**.
3. **Novedades del Tablón:** el panel de Mis aventuras se conserva en la primera
   fase, filtrado por la categoría Aventuras, y se retira cuando la página global
   esté asentada.
4. **Dónde va la campana:** en el espacio del header que hoy ocupa la Bitácora
   (`{$op_temas_header}`). El diseño debe definir cómo conviven ambos contadores
   en ese bloque y comprobar el ancho en móvil.
6. **Solo para usuarios:** las notificaciones existen para la experiencia del
   usuario. El staff no las necesita para trabajar: sus contadores de trabajo
   pendiente (fichas en cola, peticiones, bugs, aventuras, entregas) se mantienen
   aparte y no generan notificaciones. Un miembro del staff solo recibe
   notificaciones como usuario normal (por ejemplo, si participa en una aventura).
7. **Retención:** 90 días y 500 filas por usuario, ajustables con constantes. Las
   no leídas también se eliminan al cumplirse el plazo.
9b. **Orden de la lista:** una notificación agrupada que recibe un evento nuevo sube
   al principio de la lista (se ordena por la fecha de su última actividad).
5. **Qué es "de gestión" y qué es "de usuario":** las acciones de moderadores a
   notificar son las de herramientas como el Tablón de aventuras (ver
   apartado 7). Las acciones de usuarios a notificar son las de la Bitácora.
   No se usan los hooks de moderación general de MyBB (editar, mover o cerrar
   temas) en la primera fase; el responsable del proyecto indicará si más
   adelante se añaden otras acciones.
9. **La Bitácora usa el sistema global para sus eventos.** Los eventos de
   `mybb_op_bitacora_eventos` (publicación en un tema seguido, inicio de ronda
   narrada, asignación, cambio o retiro de narrador) pasan a notificaciones de
   origen usuario, y su cursor de lectura y el aviso de "novedad pendiente"
   se sustituyen por el contador global. Se mantiene en la Bitácora todo lo que
   no es un evento: el estado de turno (`debes_responder` y `esperando`), que se
   calcula a partir de los mensajes y de los cambios manuales, y sus conteos
   propios. La lista "Historial de actividad" de cada tema también se mantiene
   en la Bitácora.

8. **Agrupación de publicaciones de la Bitácora:** las publicaciones nuevas en
   un tema seguido se agrupan en una sola notificación por tema y destinatario
   (ver requisitos 8a a 8d).

Sin pendientes por ahora.

## 13. Documentos relacionados

- `docs/bitacora_migration.sql` y `inc/plugins/op_bitacora/functions.php`
  (patrón de caché del header y de invalidación).
- `docs/aventuras_ux_migration.sql` y `inc/plugins/lib/aventuras.php`
  (notificaciones del Tablón que se migran).
- Siguientes documentos previstos: `208_DesignPlan_Notificaciones.md` y
  `308_ImplementationPlan_Notificaciones.md`.
