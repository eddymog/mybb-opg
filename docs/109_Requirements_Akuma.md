# Requisitos: Akumas (inactividad, avisos y reservas)

Estado: **borrador para revisión**. Aún no hay diseño técnico ni implementación.

Depende de [108_Requirements_Notificaciones.md](108_Requirements_Notificaciones.md)
para los avisos, y de una tarea programada propia (ver apartado 6).

## 1. Propósito

Hoy el staff revisa a mano `op/staff/akumas_inactivas.php` para saber quién ha
dejado de postear y retira las akumas una por una. Este documento define:

1. El **aviso automático** al usuario cuando se acerca la pérdida de su akuma.
2. La **pérdida automática** de la akuma cuando pasan 61 días sin postear.
3. La **detección de uso abusivo** (usuarios que postean lo mínimo para conservar
   la akuma), que **no** es automática: genera una lista de candidatos para que
   el staff decida.
4. La **pausa por justificación**, que un moderador puede conceder.
5. El **sistema de reservas de akuma** (apartado 10): petición de reserva, duración máxima de un mes, y penalización si expira sin haberse postulado.

## 2. Principios

- **Dos reglas distintas:** la pérdida automática depende solo de si hay actividad
  o no (regla objetiva). La sospecha de abuso es una señal para el staff, nunca
  retira una akuma por sí sola.
- **El usuario siempre es avisado antes** de perder la akuma y después de perderla.
- **Idempotente:** reintentar la tarea no repite avisos ni pierde dos veces la
  misma akuma.
- **Auditable:** cada pérdida, aviso importante, pausa y decisión del staff deja
  constancia.
- **Reversible por el staff:** una pérdida automática errónea puede corregirse
  con las herramientas de ficha que ya existen.
- **Parámetros configurables:** los plazos, avisos y umbrales son constantes en
  un único lugar, no valores repartidos por el código.

## 3. Alcance

### 3.1 Incluido

- Tarea programada diaria que evalúa a todos los poseedores de akuma.
- Avisos a los 15, 30, 45 y 60 días sin postear.
- Pérdida automática a los 61 días sin postear.
- Notificación al usuario de la pérdida.
- Lista de candidatos por posible abuso en `akumas_inactivas.php`.
- Pausa del plazo concedida por un moderador (desde `akumas_inactivas.php`) a partir de una petición normal del usuario.
- Modo simulación para la primera puesta en marcha.

- Petición de reserva de akuma, con vigencia de 30 días y penalización por expirar
  sin postularse (apartado 10).

### 3.2 Fuera de alcance (primera fase)

- Cambios en estadísticas, técnicas o virtudes ligadas a la akuma al perderla
  (ver apartado 5.3).
- Retirada automática de akumas por la señal de abuso.
- Reasignación o sorteo automático de la akuma liberada.
- Avisos por correo, MP o Discord.

## 4. Definiciones

- **Poseedor:** ficha con `akuma` y `akuma_subnombre` no vacíos, cuya akuma en
  `mybb_op_akumas` no es NPC (`es_npc = 0`), no pertenece a un uid de sistema
  (`uid` distinto de `1`, `0` y de los que contienen `NPC`). Es el mismo
  conjunto que ya usa `akumas_inactivas.php`.
- **Post de rol:** un mensaje del personaje en un foro que cuelga de los foros
  `10` o `246`, igual que el reporte actual.
- **Días sin postear:** días completos transcurridos desde el último post de rol
  (zona horaria Europe/Madrid, como el resto del foro).
- **Ciclo de inactividad:** el periodo contado desde el último post de rol.
- **Pausa:** extensión del plazo concedida por un moderador (apartado 8).
- **Candidato:** poseedor que cumple la señal de posible abuso (apartado 7).

## 5. Regla automática de pérdida

### 5.1 Plazos

1. Los avisos se envían al cumplir **15, 30, 45 y 60** días sin postear.
2. La akuma se pierde al cumplir **61** días sin postear. El aviso del día 60
   es, por tanto, el último día para postear.
3. Un post de rol reinicia el contador desde ese momento.
4. La pausa (apartado 8) suma días al plazo; mientras esté activa no se envían
   avisos nuevos ni se pierde la akuma.

### 5.2 Base de cálculo

5. Si el poseedor no tiene ningún post de rol, la base es la fecha en que se
   obtuvo la akuma si se conoce. Si no se conoce, la base es la primera vez que
   la tarea lo detecta, y se guarda para no moverla en ejecuciones posteriores.
   Así nadie pierde la akuma el primer día solo por no tener historial.
6. La fecha de obtención se toma de `mybb_op_tirada_akumas` cuando existe un
   registro real de la akuma; las akumas asignadas por el staff sin ese
   registro usan la base de la regla anterior.

### 5.3 Qué significa perder la akuma

7. En la ficha (`mybb_op_fichas`): `akuma` y `akuma_subnombre` quedan vacíos. En
   la página de la ficha, los campos de akuma aparecen vacíos.
8. En `mybb_op_akumas`: `ocupada` pasa a `0`. Si esa akuma tiene reservas activas,
   pasa a `2` en su lugar (ver R26).
9. No se modifican otros datos (estadísticas, técnicas, virtudes, `uid` de la
   akuma). Si en el futuro se quiere retirar también algo ligado a la akuma,
   se define aparte.
10. La pérdida es una única función compartida (`quitar akuma`) que usan tanto
    la tarea automática como el botón de retirada manual del staff, para que
    ambos caminos hagan siempre lo mismo.
11. Cada pérdida se registra con `log_audit`, indicando si fue automática o
    manual, el usuario, la akuma y los días sin postear. Recuerda que cualquier
    `UPDATE` sobre `mybb_op_fichas` dispara el trigger `u_fichas_triggers`, que
    deja su instantánea en `mybb_audit_op_fichas`.
12. La tarea comprueba de nuevo, justo antes de quitarla, que la akuma sigue
    siendo del usuario y que sigue sin actividad (para no actuar sobre datos
    que cambiaron mientras tanto).

### 5.4 Avisos

13. Cada aviso es una notificación **importante** (apartado 9) con origen
    **sistema**. Indica la akuma, los días sin postear y cuántos quedan para
    perderla.
14. La clave de evento identifica la akuma, el tramo y el último post (por
    ejemplo `akuma:ID:aviso30:PID`), de modo que un mismo ciclo no repite el
    aviso y un ciclo nuevo sí vuelve a avisar.
15. Si la tarea no corrió algunos días, envía solo el aviso del tramo más
    reciente alcanzado, no todos los atrasados.
16. Al perder la akuma, el usuario recibe una notificación importante de
    pérdida.
17. Si el usuario vuelve a postear, los avisos pendientes de esa akuma se
    marcan como leídos automáticamente, para que la campana no avise de algo
    que ya no aplica.

## 6. Tarea programada

18. Una tarea propia (por ejemplo `akumas_inactividad`) se registra en
    `inc/plugins/op_tasks.php`, igual que `op_worldtick`, `aventurasux` y
    `op_xp_snapshot`.
19. **No se añade a `op_worldtick`.** Worldtick corre cada 5 minutos y recorre todas
    las fichas para rango, fama y nivel; los plazos de la akuma se miden en
    días y solo necesitan una ejecución diaria. Mezclarlas haría más lento
    worldtick y acoplaría sus fallos.
20. Frecuencia: una vez al día, de madrugada (hora de Madrid).
21. La tarea procesa en lotes y no depende de una sola petición de usuario.
22. Si falla para un poseedor, continúa con los demás y deja el error en el
    registro de la tarea.
23. **Primera puesta en marcha:** se ejecuta primero en **modo simulación**, que
    no quita ni avisa y solo lista a quién afectaría. El staff limpia esa lista
    (quienes ya están por encima de 61 días) antes de activar la pérdida real.

## 7. Detección de posible abuso (no automática)

El objetivo es identificar a quienes postean lo mínimo para conservar la akuma
(por ejemplo, un post cada 60 días, dos ciclos seguidos). **Esta señal nunca
retira la akuma por sí sola**: solo genera una lista para que el staff decida.

### 7.1 Ventanas de evaluación

La regla automática del apartado 5 no cambia: un solo post en 60 días basta
para conservar la akuma. La detección compara la actividad de **dos ventanas
consecutivas de 60 días**:

- **Ventana actual:** los últimos 60 días.
- **Ventana anterior:** los 60 días previos a esa (días 61 a 120).

### 7.2 Señales

24. Se calculan, para cada poseedor, estas cuentas:
    - Posts de rol en la ventana actual.
    - Posts de rol en la ventana anterior.
    - Días distintos con posts en la ventana actual.
25. Un poseedor es **candidato** si tiene **10 posts o menos en la ventana actual y
    10 o menos en la anterior**. Es el patrón de quien se salva de una ventana y repite
    en la siguiente. El umbral es el mismo para todas las akumas, sin distinción de tier.
26. El umbral (10 posts), la longitud de las ventanas (60 días) y el número de ventanas
    son constantes configurables, no valores fijos en el código.
27. Una **señal adicional**, solo informativa (se muestra junto al candidato pero no
    decide si lo es): **actividad concentrada**, es decir, 3 o menos días distintos con
    posts en la ventana actual. Detecta a quien publica varios mensajes el mismo día para
    superar el umbral y luego no vuelve a postear.
27b. Todo post en la zona de rol cuenta igual, sea largo o corto. No se distingue entre
     posts "válidos" y de poco valor.
28. Los candidatos nunca reciben el mensaje de pérdida ni pierden la akuma
    automáticamente. El usuario no recibe ninguna notificación por estar en esta
    lista.

### 7.3 Vista del staff

29. `akumas_inactivas.php` añade la sección **Candidatas a revisión** con, por
    cada una, el usuario, la akuma, los posts de cada ventana, los días con
    actividad y la fecha del último post.
30. El staff puede retirar la akuma desde esa sección con el botón de retirada
    manual (la misma función compartida del apartado 5.3).
31. Un candidato **permanece en la lista mientras siga cumpliendo los requisitos de
    candidato**. No existe la opción de descartarlo ni ninguna exención temporal: si
    el staff considera que no es un caso de abuso, simplemente no retira la akuma. Sale
    de la lista solo cuando deja de cumplir el criterio (por ejemplo, porque postea más).
32. La sección muestra también las **pausas activas** (apartado 8).

## 8. Pausa por justificación

33. El usuario envía su justificación (viaje, enfermedad, vacaciones) como una
    **petición normal** en el sistema de peticiones existente. No hay formulario
    nuevo.
34. El staff lee la petición. Si la acepta, en `akumas_inactivas.php` (o en la
    herramienta de reservas) escribe el **ID de la petición** y los días de pausa,
    con un máximo de **30**. Eso congela el plazo: durante la pausa no se envían
    avisos ni se pierde la akuma, y al terminar el contador continúa donde se quedó.
35. La pausa no puede superar 30 días en total por ciclo de inactividad. Si el
    usuario vuelve a postear, el ciclo se reinicia y podría pedir otra más adelante.
36. Cada pausa registra el moderador que la concede, el ID de la petición, los días,
    la fecha de inicio y la de fin.
37. Si el staff rechaza la petición, simplemente no registra ninguna pausa y resuelve
    la petición como de costumbre.
38. El usuario recibe una notificación cuando se le concede una pausa.
39. Un moderador puede finalizar una pausa antes de tiempo.

## 9. Notificaciones importantes (cambios en el sistema global)

El sistema de notificaciones debe distinguir una **importancia** para estos avisos.
Este apartado es la fuente de la que se actualiza `108_Requirements_Notificaciones.md`.

39. Cada notificación tiene una importancia: `normal` o `importante`. Se declara
    en el catálogo del tipo.
40. En la página de notificaciones, las importantes no leídas aparecen en una
    sección propia arriba de todo, antes de las normales.
41. La campana del header puede distinguir visualmente si entre las no leídas hay
    alguna importante (por ejemplo, con otro color), sin cambiar el número.
42. Las importantes se siguen marcando como leídas con **Visto**, como cualquier otra.
43. Los avisos de la akuma son los primeros tipos importantes: `akuma_aviso_15`,
    `akuma_aviso_30`, `akuma_aviso_45`, `akuma_aviso_60`, `akuma_perdida`,
    `akuma_pausa_aceptada` y `akuma_pausa_rechazada`.

## 10. Reservas de akuma

### 10.1 Situación actual

- La reserva es **manual**. En `mybb_op_akumas` hay dos columnas `varchar(255)`:
  `reservas` (según `DATABASE.md`, los UIDs de los aspirantes) y `reservasFecha` (las
  fechas de sus reservas). Parecen dos listas paralelas, pero el formato exacto
  (separador y formato de fecha) no está documentado ni en el volcado del repositorio,
  que solo contiene la estructura. Solo se editan a mano en `akumas_modificar.php` y se
  leen en `listado_akumas.php`; ninguna página pública las usa. No hay petición, fecha
  de vencimiento ni cuenta atrás.
- Las columnas relacionadas con el estado de una akuma son: `ocupada` (0, 1 o 2),
  `uid` (propietario, `varchar(20)`, por defecto `'1'`), `es_npc`, `es_oculta`,
  `reservas` y `reservasFecha`. La clave primaria es solo `akuma_id`.
- Con 255 caracteres, la lista de reservas admite pocos aspirantes por akuma, otra
  razón para pasar a un registro propio.
- El estado de una akuma está en `mybb_op_akumas.ocupada`: **0 libre, 1 ocupada,
  2 reservada** (lo muestran `op/akumas.php` y `akumas_modificar.php`). La tirada
  aleatoria (`opg/tirada_akuma.php`) solo elige akumas con `ocupada = 0`, así que
  una akuma reservada (`ocupada = 2`) ya queda fuera de la tirada, como dicen las guías.
- Como `ocupada` es un único valor, no recuerda **quiénes** reservaron ni **cuándo**
  (varios aspirantes pueden reservar la misma fruta). Eso se guarda hoy como texto
  libre en `reservas` y `reservasFecha`.
- Reglas de la guía que se mantienen: desde el nivel 8, sin Camino de la Voluntad,
  no se puede reservar si ya se hizo la tirada aleatoria (ni tirar con una reserva),
  una reserva activa por usuario, varios aspirantes pueden reservar la misma fruta
  y el ganador es quien la obtenga primero con su aventura.
- La guía también dice que la reserva se pierde con 1 mes de inactividad.

### 10.2 Petición de reserva

R1. Un usuario pide reservar una akuma no ocupada desde el Registro de Akumas
    (`/op/akumas.php`) con un botón **Reservar**, que crea una petición de reserva.
R2. La petición solo se puede crear si se cumplen las condiciones, que el sistema
    comprueba (nivel 8 o más, sin akuma, sin tirada real hecha, sin
    reserva activa, no estar penalizado, akuma no ocupada) y muestra al staff.
R3. La petición se **aprueba sola** cuando el sistema comprueba que se cumplen todas las
    condiciones de R2. Si alguna no se cumple, se rechaza al momento y el usuario ve el
    motivo. El staff no interviene. La condición del Camino de la Voluntad se comprueba
    con la columna `camino` de la ficha, si ese dato lo permite; hay que confirmarlo al
    diseñar.
R4. El usuario recibe una notificación con el resultado. El staff puede cancelar una
    reserva después (R23).
R5. Mientras exista al menos una reserva activa, la akuma tiene `ocupada = 2`. Eso ya
    la excluye de la tirada aleatoria, sin cambios en `opg/tirada_akuma.php`.

### 10.3 Duración máxima de un mes

R6. Una reserva dura como máximo **30 días** desde que se concede. Al cumplirse,
    pasa a **expirada**.
R7. El usuario recibe avisos importantes cuando le quedan 7 días y 2 días, y otro
    al expirar. Se envían desde la misma tarea diaria de akumas (apartado 6).
R8. Una reserva también termina, sin penalización, cuando otro usuario obtiene esa
    akuma, o cuando el propio usuario la obtiene con su aventura.
R9. Una reserva puede cancelarla el usuario en cualquier momento; ver R14 sobre
    cuándo penaliza.

### 10.4 Postularse a la aventura

El usuario confirma que se postuló indicando el **TID del tema** de su aventura
por la akuma. El sistema comprueba el TID y, a partir de ahí, la reserva deja de
tener fecha límite.

R10. En la ficha de su reserva, el usuario tiene un campo **TID del tema** (y un botón
     para confirmar). Mientras no lo indique, la reserva corre sus 30 días.
R11. El sistema valida el TID antes de aceptarlo:
     - el tema existe y es visible (no borrado ni oculto);
     - lo inició el propio usuario (no basta con ser participante);
     - tiene el prefijo (tag) **Aventura** (`mybb_threads.prefix = 3`);
     - se creó a partir de la fecha de concesión de la reserva, para que no se pueda
       usar un tema antiguo;
     - no está ya asociado a otra reserva.
     Si no cumple, se muestra el motivo y la reserva sigue corriendo.
R12. Con un TID válido, la reserva pasa a estado **postulada**: ya **no expira por
     tiempo**. La akuma sigue reservada (`ocupada = 2`) hasta que ocurra una de estas
     dos cosas: el usuario obtiene la akuma, o un moderador la quita manualmente.
R13. La comprobación del TID es formal; que el tema sea de verdad la aventura por esa
     akuma lo valora el staff. El enlace al tema aparece en la vista del staff (R22)
     para revisarlo.
R13b. El usuario puede cambiar el TID por otro válido mientras la reserva no se
      resuelva (por ejemplo, si cambió de tema). Cada cambio queda en el historial.

### 10.5 Penalización por no postularse

R14. Si una reserva **expira o se cancela sin haberse postulado**, el usuario no
     puede reservar otra akuma hasta **30 días después** de la expiración o
     cancelación. Si cancela habiéndose postulado, o si la reserva termina por
     causas ajenas (R8), no hay penalización.
R15. Cancelar poco antes de expirar cuenta como no postularse; así no se evita la
     penalización cancelando el último día.
R16. El usuario ve en su ficha de reserva la fecha desde la que podrá reservar de nuevo.
R17. El staff puede levantar una penalización con una nota (por ejemplo, si el
     usuario justificó una ausencia).
R18. Esto sustituye el límite de la guía de reservar "solo una vez por personaje":
     no hay máximo de reservas por personaje; se puede volver a reservar pasada la penalización o tras una reserva
     resuelta (la akuma obtenida o quitada por un moderador).

### 10.6 Inactividad y reservas

R19. La regla de 1 mes de inactividad de la guía queda cubierta por la duración
     máxima de 30 días mientras la reserva no tenga TID. Una reserva **postulada** no
     expira sola: si su dueño pasa 30 días sin postear, la reserva aparece en la lista
     del staff (R22), con los días transcurridos desde que se abrió el tema, para que un
     moderador decida si la quita (R12).
R20. Las pausas por justificación (apartado 8) detienen también el plazo de la
     reserva.

### 10.7 Avisos

R21. Se añaden estos tipos de notificación: `akuma_reserva_aprobada`,
     `akuma_reserva_rechazada`, `akuma_reserva_por_expirar_7`,
     `akuma_reserva_por_expirar_2` (importantes), `akuma_reserva_expirada`
     (importante, con la fecha desde la que podrá reservar de nuevo),
     `akuma_reserva_obtenida_por_otro` y `akuma_penalizacion_terminada`.

### 10.8 Vista del staff

R22. `akumas_inactivas.php` aloja también la herramienta de reservas y muestra:
     - reservas activas con días restantes y reservas por expirar;
     - **reservas postuladas**: usuario, akuma, enlace al tema (TID), **días transcurridos
       desde que se abrió el tema** y días desde el último post del usuario, ordenadas de
       más antiguas a más recientes, para que el staff valore cuáles quitar;
     - usuarios penalizados y fecha de fin de la penalización.
R23. El staff puede cancelar una reserva, cambiar su fecha o levantar una penalización,
     con motivo y auditoría.

### 10.9 Registro propio de reservas

R27. Las reservas viven en una **tabla propia** (por ejemplo `mybb_op_akumas_reservas`),
     con una fila por reserva. Es la única fuente de verdad de las reservas; las
     columnas `reservas` y `reservasFecha` de `mybb_op_akumas` dejan de ser la fuente.
R28. Cada fila guarda como mínimo: usuario, akuma, estado (`pendiente`, `activa`,
     `postulada`, `expirada`, `cancelada`, `resuelta`, `perdida_por_otro`), fecha
     de petición, fecha de concesión, fecha de vencimiento, TID del tema de la
     postulación, fecha y motivo de fin, si penaliza, y quién la
     gestionó.
R29. Lo que hoy el staff edita a mano en `akumas_modificar.php` pasa a hacerse con una
     herramienta sobre la nueva tabla (crear, cancelar, cambiar fecha, levantar
     penalización; R23), con auditoría. Los dos campos de texto de esa pantalla dejan de
     ser editables y se muestran solo de lectura mientras dure la migración.
R30. `mybb_op_akumas.ocupada` queda como un **resumen derivado** del registro (R26): ningún
     código de reservas escribe `reservas` ni `reservasFecha` después de la migración.
R31. Un usuario no puede tener más de una reserva `activa` o `postulada`; una restricción
     en la tabla lo garantiza aunque dos peticiones lleguen a la vez.
R32. Cada cambio de estado deja una fila de historial (quién, cuándo, de qué estado a cuál,
     motivo), para poder explicar a un usuario por qué perdió su reserva o está penalizado.

### 10.10 Migración

R24. Las reservas actuales (las listas en `reservas` y `reservasFecha`) se migran a
     registros reales. Antes de escribir la migración hay que consultar los datos de
     producción para conocer el formato real de ambas columnas (separador, orden y
     formato de fecha) y comprobar que las dos listas tienen la misma longitud. Como
     el texto no es fiable, el staff revisa el resultado y decide la fecha de inicio de
     cada reserva. Las columnas antiguas se
     conservan solo de lectura hasta confirmar la migración.
R26. El valor de `ocupada` se mantiene así: al aprobar la primera reserva, pasa a `2`.
     Al terminar una reserva (expira, se cancela o se resuelve), si no quedan otras
     reservas activas sobre esa akuma, vuelve a `0`; si quedan, sigue en `2`. Cuando
     alguien obtiene la akuma pasa a `1` y las demás reservas terminan sin penalización
     (R8). Todo cambio de `ocupada` lo hace una única función compartida.
R25. Una vez en producción, hay que actualizar las guías (`b03_akuma_no_mi.md` y
     `mas_guias.md`) con las nuevas reglas de duración y penalización.

## 11. Datos necesarios

El diseño final corresponde a un documento de diseño aparte. Como mínimo se
necesitarán:

- Un registro de seguimiento por akuma para guardar la base de cálculo (apartado
  5.2) y el último aviso enviado.
- Un registro de pausas con usuario, moderador, ID de la petición, días, inicio y fin.
- Un registro de reservas de akuma, que sustituye a los textos `reservas` y
  `reservasFecha` (usuario, akuma, estado, fecha de concesión, fecha
  de vencimiento, TID del tema de la postulación, motivo de fin y si
  penaliza). La penalización se obtiene de las reservas terminadas sin postulación; no
  hace falta una tabla aparte.
- La importancia en la tabla de notificaciones.

Las tablas siguen la convención `mybb_op_*` y sin claves foráneas.

## 12. Seguridad y privacidad

44. Solo moderadores y staff (`is_mod`, `is_staff`) pueden ver los candidatos, conceder
    pausas y retirar akumas.
45. Un usuario solo puede ver sus propios avisos y su propia solicitud de pausa.
46. Los candidatos son información interna del staff: el usuario no ve que está en
    la lista.
47. Las acciones del staff validan sesión y CSRF (`verify_post_check`).
48. El texto de las peticiones lo gestiona el sistema de peticiones existente; este módulo
    solo guarda el ID de la petición asociada a cada pausa.

## 13. Criterios de aceptación

- [ ] Un poseedor sin posts de rol durante 15, 30, 45 y 60 días recibe cada aviso
      una sola vez como notificación importante.
- [ ] A los 61 días, la ficha queda con `akuma` y `akuma_subnombre` vacíos y
      `ocupada = 0` en `mybb_op_akumas`.
- [ ] El usuario recibe la notificación de pérdida y queda una entrada de auditoría.
- [ ] Si postea antes del día 61, el contador se reinicia y no pierde la akuma.
- [ ] Al volver a postear, los avisos pendientes de esa akuma se marcan como leídos.
- [ ] Ejecutar la tarea dos veces el mismo día no duplica avisos ni pérdidas.
- [ ] Si la tarea no corrió varios días, envía solo el aviso del tramo vigente.
- [ ] Un poseedor sin posts ni fecha de obtención conocida cuenta desde la primera
      detección de la tarea.
- [ ] En modo simulación no se quita ninguna akuma ni se envía ningún aviso, y se
      obtiene la lista de afectados.
- [ ] Con 10 posts o menos en cada una de las dos últimas ventanas de 60 días, el
      poseedor aparece como candidato; la akuma no se pierde sola.
- [ ] El staff puede retirar la akuma de un candidato; mientras la conserve y siga
      cumpliendo el criterio, el candidato sigue en la lista.
- [ ] El umbral de candidato es 10 posts para todas las akumas, sin importar el tier.
- [ ] La señal de actividad concentrada se muestra junto al candidato sin afectar a si
      lo es.
- [ ] Un post corto en la zona de rol cuenta igual que uno largo.
- [ ] Una pausa aceptada detiene avisos y pérdida por el tiempo concedido (máximo
      30 días) y el usuario recibe la decisión.
- [ ] La pérdida manual y la automática producen el mismo resultado en la ficha y en
      `mybb_op_akumas`.
- [ ] `akumas_inactivas.php` sigue mostrando sus listas actuales.
- [ ] Una petición de reserva aprobada crea una reserva que expira a los 30 días.
- [ ] Una akuma con reserva activa tiene `ocupada = 2` y no sale en la tirada aleatoria.
- [ ] El usuario recibe avisos a 7 días, a 2 días y al expirar.
- [ ] Si expira sin TID, no puede reservar otra durante 30 días; la
      ficha le muestra desde cuándo.
- [ ] Cancelar sin postularse penaliza igual que expirar.
- [ ] Un TID válido deja la reserva en estado postulada y deja de expirar por tiempo.
- [ ] Un TID inexistente, de otro usuario, anterior a la reserva o ya usado se rechaza con
      un motivo claro.
- [ ] Una reserva postulada se mantiene hasta que el usuario obtiene la akuma o un moderador
      la quita.
- [ ] Si otro usuario obtiene la akuma, la reserva termina sin penalización.
- [ ] El staff puede levantar una penalización y cancelar una reserva con auditoría.

## 14. Decisiones y pendientes

Decisiones tomadas:

1. **Justificación de pausa:** una petición normal; el staff registra su ID y los días
   de pausa en la herramienta de reservas, que vive dentro de `akumas_inactivas.php`
   (apartado 8).
2. **Candidatos:** no se descartan. Permanecen en la lista mientras cumplan el
   criterio (apartado 7.3).
3. **Criterios de sospecha:** 10 posts para todas las akumas, sin dependencia del tier,
   y todo post en la zona de rol cuenta igual. Se mantiene la señal informativa de
   actividad concentrada (apartado 7.2).
4. **Akumas sin fecha de obtención y sin posts:** cuentan desde la primera vez que la
   tarea las detecta (apartado 5.2).
5. **Foros de rol:** los que cuelgan de los foros `10` y `246`, como hoy. Un subforo nuevo
   dentro de esos cuenta solo; si se crea un foro de rol fuera de esos dos, hay que
   añadirlo a la constante de la lista.
6. **Aprobación de reservas:** automática (R3).
7. **Reservar varias veces:** sin máximo. Tras la penalización se puede volver a
   reservar (R18).
8. **Reservas postuladas de usuarios inactivos:** las decide siempre un moderador (R19).

Sin pendientes por ahora. Antes de escribir la migración de reservas hay que consultar
en producción el formato real de las columnas `reservas` y `reservasFecha`.

## 15. Documentos relacionados

- [108_Requirements_Notificaciones.md](108_Requirements_Notificaciones.md): sistema de
  notificaciones que usan estos avisos.
- `op/staff/akumas_inactivas.php` y `op/staff/akumas_expirar.php`: reporte actual de
  inactividad.
- `inc/plugins/op_tasks.php` y `inc/tasks/op_worldtick.php`: registro de tareas
  programadas.
- `opg/tirada_akuma.php`: asignación de la akuma al sortearla.
