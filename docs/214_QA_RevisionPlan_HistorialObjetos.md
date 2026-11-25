# QA: revisión adversarial del plan del historial de objetos

> Rol: QA Tester. Revisión **previa a la implementación** de `op/historial_objetos.php`.
> Documentos revisados completos: [112_Requirements_HistorialObjetos.md](112_Requirements_HistorialObjetos.md) (112-R), [112_Reglas_HistorialObjetos.md](112_Reglas_HistorialObjetos.md) (112-GD), [214_UI_HistorialObjetos.md](214_UI_HistorialObjetos.md) (214-UI) y [214_DesignPlan_HistorialObjetos.md](214_DesignPlan_HistorialObjetos.md) (214-DP).
> Código contrastado (solo lectura): `op/historial_tecnicas.php`, `inc/plugins/lib/tecnicas_cambios.php`, `op/staff/objetos_modificar.php`, `op/staff/objetos_crear.php`, `op/barco_unico.php`, `op/objetos.php`, `op/staff/log_consola_mod.php`, `op/mercado_negro.php` (control de acceso), `op/tienda.php`, `op/crafteo.php`, `inc/db_mysqli.php`, `inc/class_error.php`, `templates/One_Piece_Gaiden_Templates/op_objetos.html`, `rovddqmy_op.sql` (solo esquema; no trae datos de estas tablas), `tests/tecnicas/cambios.php`, `tests/notificaciones/bootstrap_db.php`, `tests/aventuras/bootstrap_db.php`, `tests/run_all.sh`.
> No se ejecutó nada contra ninguna base de datos ni contra producción. No se usaron credenciales.

---

## 1. Veredicto

**LISTO CON CAMBIOS.**

La arquitectura es buena y sigue el patrón probado de técnicas: librerías puras, una página fina, filtrado de campos en el servidor antes de construir el HTML, recuentos calculados sobre vistas ya filtradas y una prueba de no fuga con centinelas. La trazabilidad cubre los 63 requisitos con una sección y una prueba nominal.

No obstante, hay **dos hallazgos críticos** y **seis altos** que cambian el formato del registro (que no se puede corregir después de escribirlo) o el orden de las escrituras en el staff. Por eso el plan no se aprueba tal cual: hay que corregirlos antes de empezar I-1 e I-2. Ninguno obliga a rehacer el diseño: son cambios localizados en las secciones 3, 4, 7 y 12 de 214-DP.

### Cambios necesarios antes de empezar

1. **Congelar el esquema v1 del JSON con una instantánea completa del estado después** (los 32 campos, con el mismo abreviado), no solo los campos cambiados, y pintar "Nuevo en el catálogo", el nombre, la imagen, la categoría y el tier de la cabecera **desde el registro**, nunca desde la fila actual salvo que la fila actual sea pública para quien mira (QA-01).
2. **Renombre: mantener el `UPDATE` de `mybb_op_inventario` antes del cambio del objeto** (como hoy) y ampliar la validación del renombre para rechazar también si el inventario ya tiene filas con el id nuevo (clave única `(objeto_id, uid)`) (QA-02).
3. **Conciliación de registros `pendiente`**: guardar en el pendiente la fila "antes" completa y el `id` (también en altas, tras el `INSERT`), y añadir una función que los cierre (confirmado, sin cambios o fallido) en el siguiente guardado del mismo objeto y desde el log del staff (QA-03).
4. **Bloqueo con `GET_LOCK('opg_objeto_<id>')`** alrededor de leer, abrir, escribir y cerrar en las tres rutas, para eliminar el doble envío y la carrera de dos editores (QA-04).
5. **Sacar la lógica de guardado de `objetos_modificar.php` y `barco_unico.php`, y los pasos 5 a 13 de 6.3, a funciones de librería** que reciben `$db` o datos ya leídos, para que T-DB y la prueba de no fuga ejerzan el código real (QA-05).
6. **Completar la regla de acceso al mercado negro** con ficha aprobada y personaje no muerto (QA-06).
7. **Bootstrap de pruebas propio** para `tests/objetos/` (patrón de `tests/notificaciones/bootstrap_db.php`), con `mybb_op_objetos` MyISAM, `sql_mode` y juego de caracteres de la conexión iguales a los de producción tras la calibración 13.4 (QA-07).
8. **La pertenencia ("Lo tienes", `alcance=mios`) se decide por el id interno** del objeto, resuelto a su `objeto_id` actual y a sus alias, no por el id escrito en cada registro (QA-09).
9. **Ejecutar la calibración 13.4 de 214-DP y 10 de 112-GD antes de I-2**, añadiendo `SHOW CREATE TABLE` de las tres tablas, el valor de `errortypemedium` y la lista de duplicados de `objeto_id` sin distinguir mayúsculas (QA-08, QA-15, QA-16).
10. **Decisiones que tocan el formato del registro**: el usuario debe cerrar U-1 y U-2 al menos de forma provisional; el resto (V-*, D-10, D-12) puede esperar a I-7.

---

## 2. Hallazgos

Gravedad según `docs/agents/roles/qa-tester.md`: crítico (pérdida o corrupción de datos, fallo de seguridad o privacidad), alto (un requisito no se cumple o no se puede probar), medio (caso límite incorrecto), bajo (texto o mejora).

### Críticos

**QA-01. Fuga del estado oculto a través de entradas públicas.**
- Dónde: 214-DP 7.3 (fila `no | sí`: "Valores **actuales** de la fila (Q3)"), 7.9 (`objeto` de la vista sin origen definido), 11.3 (V-7: "la imagen sale de la fila actual"), 8 (`categoria` "comparación ... con la fila actual"), 6.3 paso 9 (opciones de tier y subcategoría).
- Por qué importa: 112-GD 2.4 dice que una entrada que fue pública sigue siéndolo aunque el objeto se oculte después. Si esa entrada pinta datos de la fila **actual**, revela lo que el staff hizo mientras el objeto estaba oculto. Escenario: `EVT020` se publica (entrada "Nuevo en el catálogo"), se oculta ("Deja de estar en el catálogo") y el staff lo edita tres veces preparando la versión 2 con otro nombre e imagen. La entrada "Nuevo en el catálogo", pública para siempre, muestra hoy los valores, el nombre y la imagen de la versión 2. Lo mismo pasa con la cabecera de cualquier entrada pública antigua de un objeto ahora oculto o renombrado mientras estaba oculto, y con los filtros `categoria`, `subcategoria` y `tier`: filtrar por la categoría actual de un objeto oculto hace aparecer su entrada antigua, lo que sirve de oráculo. Viola req. 4, E-6 y el principio 1 de 112-GD.
- Cambio: (a) el registro v1 guarda `estado_despues` con los 32 campos (mismo abreviado de 3.7); (b) "Nuevo en el catálogo" se pinta con `estado_despues` del propio registro; (c) la cabecera (nombre, id, categoría, subcategoría, tier, imagen) y los filtros de clasificación usan los datos del registro; (d) la fila actual (Q3) solo alimenta la ficha compacta y "Ver en el catálogo", y solo si la fila actual es pública para ese visor. Añadir el escenario "publicar, ocultar, editar" a T-VIS y a la prueba de no fuga 12.3 con un nombre centinela en la edición oculta.

**QA-02. El nuevo orden del renombre deja inventarios huérfanos sin forma de repararlos.**
- Dónde: 214-DP 3.9 paso 5 ("se mueve **después** de cambiar el objeto; hoy va antes") y la afirmación "el staff ve el aviso y puede repetir el guardado, que completará el inventario".
- Por qué importa: la afirmación es falsa con el código real. En `objetos_modificar.php` (líneas 96 a 103) el renombre solo se detecta si el id viejo **existe** en `mybb_op_objetos`. Si la petición se corta entre el paso 4 y el 5, el objeto ya se llama `KATF001`; al repetir el envío, `es_rename` es falso (no existe `KAT001`), la página hace un `UPDATE` normal de `KATF001` y **nunca** mueve las filas de `mybb_op_inventario` que siguen en `KAT001`. Los jugadores pierden el vínculo con su objeto. Además, el paso 5 puede fallar por la clave única `(objeto_id, uid)` de `mybb_op_inventario` (volcado, línea 3963) si un jugador tiene una fila residual con el id nuevo (por ejemplo, de un objeto retirado, porque la retirada no actualiza el inventario, o por una diferencia de mayúsculas, ya que la colación no las distingue); MyBB corta la petición con el objeto ya renombrado. Con el orden de hoy (inventario primero), un corte deja el objeto sin renombrar y repetir el envío sí lo completa, porque el id viejo sigue existiendo y el `UPDATE` de inventario es idempotente.
- Cambio: mantener el inventario antes del objeto; ampliar la validación de 4.1 para rechazar el renombre si `mybb_op_inventario` tiene alguna fila con el id nuevo ("Hay inventarios con el ID X; revísalos antes de renombrar"); prueba T-DB nueva: corte simulado entre inventario y objeto, y reintento que completa el renombre; prueba con fila residual que provoca el rechazo sin escribir nada.

### Altos

**QA-03. Un cambio real puede quedar fuera del historial público para siempre.**
- Dónde: 214-DP 3.9 pasos 4 a 7 y principio "nunca pierde el rastro".
- Por qué importa: si la petición muere tras escribir el objeto y antes de confirmar, el registro queda `pendiente`. La página pública lo ignora y repetir el guardado no genera nada (el formulario ya coincide con la base, paso 2). El rastro existe solo en el log del staff; el historial público pierde el cambio sin aviso, que es justo lo que req. 35 quiere evitar. En un alta, el pendiente ni siquiera tiene `id` (la fila aún no existe en el paso 3), así que no se puede conciliar.
- Cambio: el pendiente guarda la fila "antes" completa; en un alta se reescribe el `id` justo tras el `INSERT` (o se usa `objeto_id` hasta entonces). Nueva `opg_objetos_registro_conciliar($db, $registro)`: relee por `id`; si la fila coincide con el "antes", marca `fallido`; si difiere, construye el payload real y marca `confirmado`. Se llama al abrir el formulario de ese objeto y en un botón del log del staff. Pruebas T-DB para los tres desenlaces.

**QA-04. Doble envío y dos editores simultáneos duplican o atribuyen mal los cambios.**
- Dónde: 214-DP 3.9, párrafo "Concurrencia" ("haría falta `LOCK TABLES` sobre tres tablas").
- Por qué importa: un doble clic envía dos `POST` iguales; si se solapan, los dos leen el mismo "antes" y escriben dos entradas idénticas. Con dos editores, A relee después del `UPDATE` de B y su registro confirmado incluye los campos de B atribuidos a A. La afirmación de que hace falta `LOCK TABLES` es incorrecta: `GET_LOCK()` de MySQL es un bloqueo con nombre que no depende del motor, no bloquea tablas y se libera solo si la conexión se cae.
- Cambio: `GET_LOCK('opg_objeto_' . <id interno o objeto_id normalizado>, 5)` antes del paso 1 y `RELEASE_LOCK` tras el paso 7 (o al fallar), en las tres rutas. Si no se obtiene el bloqueo: "Otro guardado de este objeto está en curso; inténtalo de nuevo." Pruebas de dos procesos con `nt_conectar()`: dos guardados a la vez del mismo objeto dan un solo registro por cambio real y cada uno con su autor.

**QA-05. Lo más delicado no se puede probar tal como está repartido.**
- Dónde: 214-DP 1 (página "fina" pero con los pasos 1 a 15 de 6.3), 4.1 y 4.2 (lógica en las páginas), 12.2 T-DB-6, T-DB-7 y T-DB-8, 12.3 ("El HTML completo de la página").
- Por qué importa: `objetos_modificar.php` y `barco_unico.php` cargan `global.php` y no se pueden ejecutar en las pruebas. T-DB-6 (un registro por fila duplicada), T-DB-7 (valores sin escapar en `barco_unico`) y T-DB-8 (renombre hacia id existente) probarían una reimplementación, no el código desplegado. La prueba de no fuga sobre "la página completa" tampoco es posible si la unión de Q1, Q2, Q3 y Q4 con las vistas, los recuentos y las opciones vive en `historial_objetos.php`. El requisito de privacidad más importante quedaría sin evidencia real.
- Cambio: `opg_objetos_guardar_desde_formulario($db, $form, $meta)` y `opg_objetos_barco_guardar($db, $post, $meta)` en `objetos_cambios.php`, con las páginas reducidas a permisos, CSRF, llamada y respuesta. Para la lectura, `opg_objetos_hist_construir($filas_q1, $filas_q2, $filas_q3, $inventario, $ficha, $visor, $params)` devuelve todas las variables `{$ho_*}`; la prueba de no fuga recorre su salida completa. Las consultas Q1 a Q6 también como funciones de la librería, para que T-DBQ ejecute el mismo SQL.

**QA-06. La regla de acceso al mercado negro es más amplia que la del juego.**
- Dónde: 214-DP H-9, 6.1 Q5, 7.1; 112-GD 5.5.
- Por qué importa: `op/mercado_negro.php` además exige ficha aprobada (`does_ficha_exist`, línea 1732) y que el personaje no esté muerto (`muerto == '1'`, línea 1397). Q5 solo lee `rango_inframundo` y `oficios`. Un personaje muerto o con la ficha sin aprobar vería en el historial los precios y la rotación del mercado negro que el juego le niega.
- Cambio: Q5 lee también `muerto` y `aprobada_por`; `opg_objetos_acceso_mercado_negro()` replica las tres condiciones. T-REG-13 añade "muerto con rango" y "sin aprobar con rango" (ambos sin acceso).

**QA-07. El entorno de pruebas con MySQL propuesto no reproduce producción.**
- Dónde: 214-DP 2 (último párrafo) y 12.2 T-DB.
- Por qué importa: el plan dice que las pruebas cargan `tests/aventuras/bootstrap_db.php`. Ese archivo es del tablón: comparte la base `opg_test`, no crea `mybb_op_objetos` ni `mybb_op_audit_consola_mod`, pone `sql_mode = 'STRICT_TRANS_TABLES,...'` (con eso, una cadena vacía en una columna entera **falla** en lugar de guardarse como `0`, así que T-DB-2 no probaría H-5) y conecta en `utf8mb4` (con eso, un emoji se comporta distinto que con una conexión `utf8`, así que T-DB-5 y T-REG-10 no probarían H-2). Además, el área `aventuras` tiene pruebas con base de datos en rojo conocido (issue #27).
- Cambio: `tests/objetos/bootstrap_db.php` con el patrón de `tests/notificaciones/bootstrap_db.php` (una base propia por prueba, `nt_conectar()` para dos sesiones), que extrae del volcado el `CREATE TABLE` de `mybb_op_objetos`, `mybb_op_audit_consola_mod`, `mybb_op_inventario` y `mybb_op_fichas`, y fija `sql_mode` y `SET NAMES` con los valores medidos en la calibración. Una prueba explícita en modo estricto y otra en no estricto si la calibración no es concluyente.

**QA-08. Los id se comparan con mayúsculas en PHP y sin ellas en MySQL.**
- Dónde: 214-DP 4.1 (validación del renombre), 6.1 Q2 (mapa de alias), 7.5 (`poseidos`, `copias`).
- Por qué importa: `objeto_id` usa `utf8_unicode_ci` y `objetos_modificar.php` compara con `!==`. Renombrar `kat001` a `KAT001` hoy es un `UPDATE` sobre la misma fila; con la validación nueva, "existe el viejo y también el nuevo" se cumple (MySQL los considera iguales) y se rechaza un cambio legítimo. En sentido contrario, si el inventario guarda `kat001` y el catálogo `KAT001`, el juego los trata como el mismo objeto, pero "Lo tienes" y el mapa de alias (claves de array de PHP) no.
- Cambio: normalizar con `mb_strtoupper(trim())` toda clave de id en PHP (alias, poseídos, copias, comparación del renombre); tratar el cambio solo de mayúsculas como actualización del campo `objeto_id` sin validación de existencia. Pruebas en T-REN y T-DB.

**QA-09. "Lo tienes" y `Mis objetos` se pierden en las entradas anteriores a un renombre.**
- Dónde: 214-DP 7.5 ("`poseidos`: ids exactos del inventario").
- Por qué importa: tras `KAT001 -> KATF001`, el inventario se actualiza a `KATF001`, pero las entradas anteriores llevan `objeto_id = KAT001`. Comparadas por id exacto, no muestran "Lo tienes" y `alcance=mios` no las devuelve, aunque req. 14 y el criterio 4 dicen que el historial del objeto es uno solo. Afecta a criterios 4 y 9.
- Cambio: la pertenencia se calcula por `id_objeto` (interno) en los registros estructurados, mapeando cada id del inventario a su id interno con Q3 o con el mapa de alias; en los antiguos, por el id resuelto en 5.2. Caso en T-REN: renombre más poseedor, las entradas de antes y de después muestran "Lo tienes".

### Medios

**QA-10. Campos desconocidos y versiones futuras: falta la denegación por defecto.** 214-DP 7.4 aplica la clase de la tabla 3.3, pero no dice qué hacer con un `campo` que no está en la tabla (un campo añadido en el futuro, un JSON manipulado a mano en la base o un error). Si el valor por defecto es "pública", se filtra. Cambio: cualquier campo no listado es "solo staff"; prueba en T-VIS con `{"campo":"receta_secreta"}`.

**QA-11. La excepción de dueño por copia expone el historial oculto de la base.** 214-DP 7.5 extiende la excepción a "copia con D-7 A". 112-GD 2.3 concede la excepción a quien tiene el objeto, y 5.4 dice que la copia conserva sus valores y no le afectan los cambios de la base. Quien tiene una copia vieja de una recompensa de evento vería los cambios que el staff prepara en secreto sobre la base. Cambio: decisión explícita del Game Designer y del usuario; mientras tanto, la excepción solo con el id exacto (la copia sigue dando "Tienes una copia" en entradas públicas).

**QA-12. El mapa de alias no soporta la reutilización de ids.** 214-DP 6.1 Q2 construye `objeto_id viejo -> id interno`. Si el objeto 1 pasa de `A` a `B` y después se crea un objeto 2 con `A` que pasa a `C`, la clave `A` apunta a dos objetos y una sobrescribe a la otra: los antiguos de `A` y las copias `A-261-3` se atribuyen al objeto equivocado. Cambio: el mapa guarda, por id viejo, una lista con `(id interno, audit_id, tiempo)`, y se resuelve por fecha (el registro o la `fecha_creacion` de la copia anterior al renombre). Si no se puede decidir, solo staff. Casos en T-REN.

**QA-13. Validar filtros contra las vistas visibles rompe los enlaces compartidos.** 214-DP 8 (`subcategoria`, `tier`, `autor`: "lista calculada" sobre las vistas del periodo). Un enlace `?subcategoria=Espadas&periodo=7` sin entradas de espadas esa semana restablece el filtro, muestra la nota "no era válido" y enseña resultados **sin filtrar**. Como estos valores nunca llegan a SQL, no hace falta la lista: basta validar la forma (longitud máxima, sin caracteres de control) y aplicarlos en PHP; un valor inexistente u oculto da el mismo estado vacío, así que no hay oráculo. Esto contradice la letra de req. 24 y hay que cambiar el requisito.

**QA-14. El ancla `#cambio-ID` no es estable con paginación.** Req. 17 pide un ancla estable, pero `href="#cambio-N"` solo funciona si la entrada está en la página cargada con los mismos filtros; con cada cambio nuevo la entrada se desplaza de página. Cambio: "Enlace permanente" a `?cambio=N` (entero), que muestra solo esa entrada si es visible para el visor y el estado vacío normal si no (mismo texto para inexistente y oculto).

**QA-15. Un error SQL puede publicar ids de objetos ocultos.** `inc/class_error.php` (líneas 505 a 520) muestra la consulta completa si `errortypemedium` lo permite. Q3 lleva en el `IN (...)` los `objeto_id` de registros antiguos de objetos que el visor no puede ver. 214-UI 4 define un estado de error, pero 214-DP no dice que las consultas usen `$hide_errors`. Cambio: Q1 a Q6 con `hide_errors = 1`, comprobación del resultado y estado de error de 214-UI; prueba que fuerza un fallo y comprueba que el HTML no contiene ningún id.

**QA-16. El índice en `objeto_id` puede cambiar qué fila duplicada lee el juego.** 214-DP 6.2 afirma "sin cambio de comportamiento". Con filas duplicadas (H-4), las consultas del juego `WHERE objeto_id = ...` sin `ORDER BY` devuelven la primera fila según el plan de ejecución; con el índice, el orden puede cambiar y la tienda, el crafteo o el inventario pueden leer la otra copia. Cambio: la calibración de duplicados (sin distinguir mayúsculas) se ejecuta antes; si hay duplicados, se resuelven en otra issue antes de crear el índice, o la migración lo documenta como riesgo.

**QA-17. Q2 crece con todos los registros de objetos y lee blobs grandes.** 214-DP 6.1 Q2 y 6.2: el prefijo de 16 caracteres solo cubre la marca, así que con el índice Q2 recorre **todos** los registros `[CAMBIO_OBJETO]` (cada `log` puede ocupar hasta 60.000 bytes) para quedarse con los renombres. Sin la migración, cada visita hace dos recorridos completos de `mybb_op_audit_consola_mod` (Q1 y Q2), el doble que técnicas. Cambio: índice `log(48)` o similar que cubra `{"v":1,"accion":"renombrado"`, o derivar el mapa de alias de Q1 cuando `periodo=todo` y no ejecutar Q2 en ese caso. Medir el tamaño medio del `log` de objetos en T-DBQ.

**QA-18. Registros antiguos de un id duplicado: regla no definida.** 214-DP 5.2 paso 2 ("la fila actual con ese `objeto_id`") no dice qué hacer si Q3 devuelve varias filas (una `custom=0` y otra `custom=1`, o una visible y otra invisible). Cambio: si hay varias, se aplica la más restrictiva (si alguna no es pública, solo staff). Caso en T-VIS.

**QA-19. Faltan los tipos que ven el staff y el dueño.** 214-DP 7.3 define los tipos solo "para un visor que no es staff". No está definido qué tipo y qué contenido ve el staff en `no -> no`, en `publicado` o en un alta oculta, ni el dueño en `no -> no` (se supone `Antes / Ahora`). Cambio: añadir dos columnas a la tabla de 7.3 (staff y dueño) y casos en T-VIS.

**QA-20. La retirada con D-13 A es ambigua.** 214-DP 4.3: "`objeto_id = 'BORR' + id`" puede leerse como el id interno (`BORR5123`), que rompe 112-GD 4 y 214-UI 3.7 (`BORRKAT001`). Tampoco se trata la colisión (ya existe `BORRKAT001` porque se retiró y se volvió a crear `KAT001`) ni retirar dos veces (`BORRBORR...`). Cambio: `'BORR' . objeto_id`, rechazo si el destino ya existe o si el objeto ya está retirado, y pruebas.

**QA-21. Alta duplicada en `objetos_crear.php` con D-13 B o C.** El `INSERT` de `objetos_crear.php` (línea 55) no comprueba si el id existe. Si el usuario elige B o C, la reescritura debe rechazarlo, o el historial tendrá dos objetos con el mismo id desde su alta. Con D-13 A no aplica.

### Bajos

- **QA-22.** 214-DP 5.1 aplica `stripslashes` a la descripción de las altas antiguas "porque se guardó con `addslashes`". No es así: `objetos_crear.php` interpola el valor con barras dentro de un literal SQL y MySQL las consume al guardar. `stripslashes` borraría barras legítimas. Quitarlo y probar con `a\b`.
- **QA-23.** 214-DP 3.7 dice que superar el límite es "imposible con 32 campos". La escalera solo reduce los campos largos; el payload **pendiente** (que sale del formulario y sí puede llevar emoji, a 12 bytes cada uno con el par sustituto) con diez campos cortos `TEXT` de 500 caracteres pasa de 60.000 bytes y cae a la versión mínima. El confirmado no, porque relee columnas `utf8`. Bajar también los cortos en la escalera.
- **QA-24.** Agrupación por día y zona horaria: técnicas agrupa con `date()` (zona del servidor) y muestra con `my_date()` (zona del usuario). Cerca de medianoche, una entrada puede salir bajo "Hoy" con fecha de ayer. Usar la misma función en las dos cosas e inyectar el reloj en T-VISTA.
- **QA-25.** El script de `op_objetos.html` (214-UI 8.2) usa `decodeURIComponent(hash)` sin `try/catch`: un `#100%` lanza `URIError` y corta el script.
- **QA-26.** `Mis objetos` deduce las copias con expresiones regulares (`-\d+-\d+$`, `-\d+$`). `barco_unico.php` admite guiones en ids de catálogo (`^[A-Z0-9_\-]{1,60}$`), así que un barco `BARCO-2` podría tomarse por copia de `BARCO`. Mejor leer `custom` de la base para los ids del inventario.
- **QA-27.** Defensa en profundidad contra registros falsificados: la página debe mostrar el autor de la columna `username` y descartar un JSON cuyo `autor` no coincida. Hoy ningún escritor de `mybb_op_audit_consola_mod` empieza el log con texto de jugador (revisados `ficha_descripcion.php`, `aniversario_gacha*.php` y las herramientas de staff), así que el riesgo es bajo.
- **QA-28.** Req. 1 dice "la misma regla que `op/objetos.php` (`custom='0'`)", pero `op/objetos.php` también excluye `objeto_id LIKE 'CFF010%'`. Decidir si el historial también lo excluye.
- **QA-29.** El aviso "Hay más cambios de los que caben en una consulta" (6.1, Q1) se calcula antes del filtro de visibilidad: revela que existen muchos registros ocultos. Contarlo sobre vistas o mostrarlo solo al staff.
- **QA-30.** La línea personal "Ahora puedes fabricar" debe interpretar `crafteo_usuarios` igual que `crafteo.php` (`FIND_IN_SET` tras quitar espacios, líneas 460 y 463). Probar `"352, 1044"`.
- **QA-31.** `barco_unico.php` en `crear` sobrescribe nombre, imagen, descripción, oficio y receta de **cualquier** objeto con el mismo id, aunque no sea un transporte (líneas 93 a 103). Es previo a esta funcionalidad; el historial lo hará visible. Hallazgo para el PM.

---

## 3. Testabilidad por requisito

Todos los requisitos tienen fila en la trazabilidad de 214-DP 15. Los que no tienen hallazgo están bien cubiertos: **2, 5, 8, 9, 10, 12, 13, 15, 19, 20, 21, 22, 23, 25, 26, 27, 28, 30, 33, 34, 36, 39, 40, 41, 43, 44, 45, 46, 47, 49, 50, 57, 61, 62**.

| Req. | Estado | Problema | Cambio |
|---|---|---|---|
| 1 | No automatizable | Solo calibración en producción; no excluye `CFF010%` (QA-28) | Anotar el resultado de la calibración en la issue I-2 como evidencia |
| 3 | Ambiguo | Contradicción C-1 sin resolver; además QA-01 | Reescribir el requisito con la regla de instantáneas y el escenario publicar, ocultar y editar |
| 4 | Débil | No prueba que una publicación posterior a una ocultación no revele lo hecho mientras estaba oculto | Caso nuevo en T-VIS y en 12.3 (QA-01) |
| 6 | Débil | T-FIL es puro y no prueba la página sin sesión | Con QA-05, prueba de `opg_objetos_hist_construir` con visitante más QA-M con `curl` sin cookie |
| 7 | Débil | Falta la pertenencia tras un renombre (QA-09) y con ids que solo difieren en mayúsculas (QA-08) | Casos en T-REN |
| 11 | Ambiguo | No dice si categoría, subcategoría y tier son los del momento del cambio o los actuales | Del registro (QA-01) |
| 14 | Ambiguo | Los renombres anteriores al despliegue no se pueden seguir (214-DP 5.2); el criterio 4 promete "todo el historial" | Limitar el requisito y el criterio a registros posteriores a `OPG_OBJETOS_CAMBIO_INICIO` |
| 16 | Débil | Depende del reloj y de la zona horaria (QA-24) | Reloj inyectable en T-VISTA |
| 17 | Ambiguo | El ancla no es estable con paginación (QA-14) | `?cambio=N` |
| 18 | Débil | C-7 abierta | Cerrar C-7 |
| 24 | Contradicción | Validar contra datos reales y restablecer rompe enlaces (QA-13) | Cambiar el requisito |
| 29 | Ambiguo | "cambios reconocidos" choca con el principio 5 de 112-GD (técnicas cuenta todos los reconocidos) | "cambios visibles para ti en el periodo" |
| 31, 32 | Solo manual | Dependen del navegador (214-UI 10) | Aceptado: U-CAP |
| 35 | Débil | No hay prueba de que las tres rutas llamen al registro (QA-05) | Funciones de guardado en la librería y T-DB sobre ellas |
| 37, 38 | Débil | Probados en la librería, no en la ruta | Igual que 35; en 38, contar sentencias: sin cambios no hay ni `INSERT` ni `UPDATE` |
| 42 | Depende | Depende de D-13 y de QA-20 | Cerrar D-13 antes de I-4 |
| 48 | Débil | El `stripslashes` corrompe barras (QA-22) | Corregir y probar |
| 51 a 56 | Solo manual | QA-M y capturas | Aceptado; en 53 igualar 40 px y 44 px (C-14) |
| 58, 59 | Débil | Contar consultas solo vale si la tubería real es una función (QA-05) | Igual que 6 |
| 60 | Débil | Faltan contextos de atributo y URL | Probar `"`, `<`, `'`, `` ` ``, `${`, `{$mybb->user['uid']}` en `objeto_id` dentro de `href` (con `rawurlencode`), `title` y `datetime` |
| 63 | No medible | "decenas de miles" no es un umbral | Fijar uno (por ejemplo, 20.000 registros de objetos, el de 214-DP 10) y medirlo en T-DBQ |

Criterios de aceptación de 112-R 14: el 1 queda débil por la misma razón que req. 6; el 4 y el 9 necesitan QA-09; el 7 necesita el caso de QA-01; el 14 es manual. El resto tiene prueba nominal correcta.

---

## 4. Contradicciones

### C-1 a C-15 del Developer

| # | Resultado | Comprobación |
|---|---|---|
| C-1 | **Confirmada** | Req. 3 combina estado actual **o** estado en el momento; 112-GD 2.4 solo instantánea. Además, la regla de 112-GD solo es segura si la entrada se pinta con datos del registro (QA-01). |
| C-2 | **Confirmada** | 112-R 23 y 214-UI 2.4 tienen siete grupos (Clasificación, Economía, Combate, Fabricación, Inventario, Presentación, Visibilidad); 112-GD 3.1 tiene nueve. 214-UI 3.4 ordena las filas por los siete. |
| C-3 | **Confirmada** | 112-GD 5.1 pide porcentaje; 214-UI 3.4 y V-4 piden diferencia absoluta. |
| C-4 | **Confirmada** | 214-UI 3.2 solo tiene "Objeto publicado"; no hay estilo ni chip para "Deja de estar en el catálogo". |
| C-5 | **Confirmada** | 214-UI 3.4 lista `exclusivo`, `invisible` y `comerciable` como Sí/No directos; el código (`tienda.php` línea 57, `intercambio.php`) da la razón a 112-GD. |
| C-6 | **Confirmada** | 214-UI usa "Berries" en tabla y ficha. |
| C-7 | **Confirmada, y se amplía** | 214-DP 11.1 muestra la ficha también al dueño, que ninguno de los otros documentos contempla. Con QA-01, la ficha solo con la fila actual pública para el visor. |
| C-8 | **Confirmada** (menor) | 214-UI deja el texto pendiente del Game Designer; 112-GD ya lo fija. |
| C-9 | **Confirmada, y se amplía** | Además faltan en 214-UI: el bloque "Qué cambia para ti", el aviso de copia, "Cambio del staff en tu objeto", el aviso único de registros antiguos (214-DP 5.5), el aviso del tope de 5.000 y la "Retirada antigua (sin confirmar)". |
| C-10 | **Confirmada** | 214-UI 3.2: dos motivos. |
| C-11 | **Confirmada** | `engastes int(10)` en el volcado (línea 2141). |
| C-12 | **Confirmada** | |
| C-13 | **Confirmada** | 112-GD sustituye las cifras; 214-UI usa 1.204 como ejemplo de recuento, que es una coincidencia sin importancia. |
| C-14 | **Confirmada** | |
| C-15 | **Confirmada** | |

### Contradicciones que faltaban

| # | Tema | Documentos | Propuesta |
|---|---|---|---|
| C-16 | Excepción de dueño en registros antiguos | 112-GD 2.3 concede al dueño ver lo no público; 112-GD 6.1 decide los antiguos solo por estado actual; 214-DP 5.3 elige "sin excepción" | Confirmarlo en 112-GD 6 |
| C-17 | Excepción de dueño por copia | 112-GD 2.3 y 5.4 frente a 214-DP 7.5 | QA-11 |
| C-18 | Validación de filtros | Req. 24 (contra datos reales del catálogo) frente a 214-DP 8 (contra vistas visibles) frente a 214-UI 2.4 (tier "1 a N" fijo) | QA-13 |
| C-19 | Resumen | Req. 29 "cambios reconocidos" frente al principio 5 de 112-GD y 214-UI 4 | Cambiar req. 29 |
| C-20 | Acceso al mercado negro | 112-GD 5.5 y 214-DP H-9 citan solo rango y Contrabandista; el código exige además ficha aprobada y no estar muerto | QA-06 |
| C-21 | Pertenencia | Req. 14 (un solo historial) frente a 214-DP 7.5 (ids exactos) | QA-09 |
| C-22 | Tipos del staff y del dueño | 214-UI 3.2 y 214-DP 7.3 no los definen | QA-19 |
| C-23 | Aviso de registros antiguos | 112-GD 6.6 y 214-DP 5.5 lo piden una vez por página; 214-UI solo tiene la nota dentro de cada entrada | Diseño en 214-UI |

---

## 5. Seguridad y privacidad: resumen

| Vector | Estado del plan | Hallazgo |
|---|---|---|
| Búsqueda `q` | Bien: texto visible de la vista y alias visibles (7.6) | Añadir la ficha compacta solo si se muestra |
| Recuentos de chips, resumen, "Ver cambios (N campos)", `+N` de `ct-campos` | Bien en el diseño; `+N` no está en 12.3 | Añadir `+N` y "Página X de Y" a la prueba de no fuga |
| Opciones de filtros y autores | Bien: salen de vistas visibles | QA-13 para el comportamiento con valores ausentes |
| Filtros de clasificación | **Fuga**: usan la fila actual | QA-01 |
| Anclas `#cambio-N` | Huecos aceptados por 112-GD 8 | Ninguna entrada oculta debe tener `id` en el HTML (añadir a 12.3) |
| Mensajes de error | **Posible fuga** de ids en el error SQL de MyBB | QA-15 |
| Ordenación | Por `audit_id`; no filtra nada | Ninguno |
| JSON del registro | No llega al navegador (9.5) | Correcto; mantener la prueba |
| Cabecera y "Nuevo en el catálogo" | **Fuga** de estado actual | QA-01 |
| Campos nuevos o desconocidos | Sin regla | QA-10 |
| Mercado negro | Regla incompleta | QA-06 |
| "Solo staff" | `is_mod` o `is_staff` de la sesión (9.6) | Correcto; la prueba de no fuga debe comprobar también que el visitante no recibe el `title` con el motivo ni "Editar (staff)" |
| Objetos borrados o renombrados | Mapa de alias y 5.2 | QA-08, QA-09, QA-12, QA-18 |
| Parser de antiguos | Expresiones ancladas y tolerantes; el texto se escapa al pintar | Sin inyección posible: el contenido nunca se ejecuta ni se concatena en SQL. Corregir QA-22 |
| XSS en nombre, autor y textos largos | `htmlspecialchars` y `nl2br` tras el escape (9.3); URLs de imagen con lista blanca | Ampliar las pruebas a atributos y URLs (tabla de la sección 3, req. 60) |
| Plantilla con `eval` | Las variables ya escapadas no se vuelven a interpretar; no hay JSON dentro de la plantilla | Probar igualmente `${` y `{$...}` en un nombre (sección 3, req. 60) |

---

## 6. Integridad del registro: escenarios de fallo

| Escenario | Con el plan actual | Con los cambios |
|---|---|---|
| Corte entre `abrir` y escribir el objeto | Pendiente sin cambio; nunca se aclara | `conciliar` lo marca `fallido` (QA-03) |
| Corte tras escribir el objeto | Pendiente con cambio real, invisible para el público para siempre | `conciliar` lo confirma (QA-03) |
| Corte en mitad de un renombre | Objeto renombrado, inventario en el id viejo, sin reparación (QA-02) | Inventario primero; reintentar completa el renombre |
| Clave única de inventario duplicada en el renombre | Objeto renombrado y la petición muere (QA-02) | Rechazo previo sin escribir nada |
| Doble clic | Dos entradas iguales (QA-04) | `GET_LOCK`: la segunda no encuentra cambios |
| Dos editores a la vez | Cambios duplicados o atribuidos al autor equivocado (QA-04) | `GET_LOCK` los serializa |
| Renombre hacia un id existente | Rechazado (bien) | También hacia un id con filas de inventario, y sin rechazar los cambios de solo mayúsculas (QA-08) |
| Filas duplicadas de `objeto_id` | Un registro por fila (bien) | Más la regla de QA-18 para los antiguos |
| JSON de más de 60.000 bytes | Escalera y versión mínima | Incluir los campos cortos en la escalera (QA-23) |
| Registro sin cambios tras releer | `sin_cambios` (bien) | Sin cambios |
| Alta pendiente | Sin `id` | `id` escrito justo tras el `INSERT` (QA-03) |

---

## 7. Rendimiento

| Consulta | Sin la migración | Con la migración (EXPLAIN esperado) |
|---|---|---|
| Q1 | `type=ALL` sobre unas 23.000 filas con `LIKE` sobre `TEXT` | `type=range` sobre `idx_hobj_log_tiempo`: unión de cuatro o cinco intervalos del prefijo (marca, `Nuevo objeto cre`, `Objeto `, `Remover t`), `tiempo` filtrado por *index condition pushdown*, `Using filesort` por `ORDER BY id`. Correcto con el volumen previsto |
| Q2 | Segundo `type=ALL` en la misma visita | `type=range`, pero sobre **todos** los registros con la marca, leyendo sus blobs (QA-17) |
| Q3 | `PRIMARY` por `id`; `ALL` sobre 16.240 filas MyISAM solo si hay antiguos | `index_merge` (unión) de `PRIMARY` e `idx_objeto_id` |
| Q4 | `ALL` sobre `mybb_op_inventario` (su clave única empieza por `objeto_id`) en cada visita con sesión | Igual: el plan no añade índice por `uid` y lo deja a medición |
| Q5 | `PRIMARY` de fichas | Igual |
| Q6 | `ALL` sobre 16.240 filas, solo con copias en la página | `range` sobre `idx_objeto_id` |

Si el usuario **no** ejecuta la migración, la página funciona pero cada visita hace dos recorridos completos de la auditoría y, con sesión, uno del inventario. Con el tráfico de hoy es asumible; con bots, no se sabe. Recomendación: con QA-17 (Q2 derivada de Q1 en `periodo=todo`) se queda en un recorrido, como técnicas. Antes de publicar I-7, el usuario ejecuta los `EXPLAIN` de 13.4 en producción y el resultado se anota en la issue.

---

## 8. Casos límite que las pruebas deben cubrir y el plan no menciona

1. JSON con `cambios` nulo o que no es una lista; `antes` o `despues` como número, `null` o array; `vis_antes` presente en un alta; `vis_despues` ausente; `id` como cadena `"5123"`; `v` como cadena `"1"`. Todos deben dar `null` o tratarse de forma definida, sin avisos de PHP.
2. Campo desconocido en `cambios` (QA-10).
3. Textos con `"`, `'`, `\`, `` ` ``, `${x}`, `{$mybb->user['uid']}`, `</script>`, `&amp;` ya escapado (no debe salir `&amp;amp;` en el título si no corresponde) y saltos `\r\n`, en nombre, `objeto_id`, autor, descripción y efecto.
4. `objeto_id` con espacios internos (la expresión antigua usa `\S+`), con `#`, `%`, `?` o `&` (en `href` y en el ancla del catálogo), y de 255 caracteres.
5. Emoji en el formulario: el pendiente lo guarda escapado y el confirmado refleja lo que MySQL dejó en la fila (truncado en una columna `utf8`).
6. Fechas: entradas a las 23:59 y 00:01 con distinta zona de PHP y de MySQL; justo en el límite de 7, 30 y 90 días; `tiempo` igual en dos registros (desempate por `audit_id`).
7. Objetos con id duplicado y duplicado solo por mayúsculas (QA-08, QA-18).
8. Objetos sin categoría, con categoría fuera de las siete (`borrada`, minúsculas), con `tier` 0 (los barcos de `barco_unico.php` se crean con tier 0, que `tienda.php` considera vendible) y negativo.
9. Cadenas de renombres de 21 saltos (debe quedar sin resolver, solo staff, no resuelta a medias), ciclo `A -> B -> A`, reutilización de id (QA-12), renombre solo de mayúsculas.
10. Periodo vacío: no se ejecutan Q3 ni Q4; `alcance=mios` con periodo vacío; página 999.
11. `crafteo_usuarios` con espacios, comas finales y el UID del visor como subcadena de otro (`35` frente a `352`).
12. `berries` 0 en el antes ("antes sin valor"), `negro_berries = 9999999` en los dos lados, `tiempo_creacion` 0 y de 59 segundos.
13. Parámetros como array (`periodo[]=7`, `q[]=x`) y con bytes UTF-8 inválidos.
14. Personaje muerto o sin aprobar con rango del inframundo (QA-06); usuario con sesión y sin ficha.
15. Inventario con `cantidad = 0` y con una fila residual de un objeto retirado.

---

## 9. Estrategia de pruebas: lo que falta

La estrategia es amplia y está bien orientada (casos E-1 a E-15, centinelas, `EXPLAIN` con y sin índice, idempotencia de la migración). Le faltan:

- **Pruebas sobre el código real** de guardado y de construcción de la página (QA-05). Sin eso, T-DB-6 a T-DB-8 y 12.3 no son evidencia.
- **Pruebas de dos procesos** con dos conexiones: doble envío, dos editores sobre el mismo objeto, renombre mientras otro proceso inserta inventario del id viejo, la página leyendo mientras existe un pendiente (no debe mostrarlo) y conciliación de un pendiente (QA-03, QA-04).
- **Pruebas de fallo a mitad** para cada paso de 3.9 con el orden corregido, incluido el reintento del renombre (QA-02).
- **No fuga ampliada**: publicar, ocultar y editar (QA-01); filtros de clasificación como oráculo; `+N`; ids de entradas ocultas en el HTML; `title` del motivo de staff; error SQL forzado (QA-15); aviso del tope (QA-29); dueño de una copia (QA-11); campo desconocido (QA-10).
- **Entorno fiel**: `sql_mode` y juego de caracteres de producción (QA-07).
- **Nota para la puerta de verde**: `necesita_db()` de `tests/run_all.sh` marca como prueba con base de datos cualquier archivo que contenga `DbShim`. Si una prueba pura usa un doble con ese nombre para contar consultas, se omitirá sin `--db`. Llamar de otra forma a los dobles de las pruebas puras.

---

## 10. Comprobado en el código

| Afirmación de 214-DP | Resultado |
|---|---|
| H-1: `mybb_op_objetos` MyISAM, `mybb_op_audit_consola_mod` InnoDB | Cierto en el volcado (líneas 2144 y 962). En producción, sin verificar |
| H-2: `log` es `TEXT` `utf8`, solo índice primario | Cierto. Además `staff` es `TEXT`, no entero |
| H-3: `objeto_id` sin índice | Cierto (solo `PRIMARY` y `UNIQUE` sobre `id`) |
| H-4: `UPDATE ... WHERE objeto_id` cambia todas las filas; renombre hacia id existente crea duplicado | Cierto (`objetos_modificar.php` líneas 111 a 113) |
| H-5: `trim` de todo y cadenas vacías en enteros | Cierto para `objetos_modificar.php`; el resultado en MySQL depende del `sql_mode`, sin verificar |
| H-6: `barco_unico.php` escribe en el catálogo sin log | Cierto, y `insert_query` y `update_query` de MyBB no escapan (`quote_val`, `inc/db_mysqli.php` línea 966) |
| H-7: formato de la retirada antigua | Cierto (`objetos_crear.php` línea 71, `tecnicas_crear.php` línea 65, `akumas_crear.php` y `virtudes_crear.php` sin sufijo) |
| H-9: regla del mercado negro | **Incompleta** (QA-06) |
| H-10 y H-11: ids del catálogo, sin Transportes, `SELECT *` al navegador | Ciertos (`op_objetos.html` líneas 10 a 28, 38 y 169; `op/objetos.php` línea 22) |
| "Repetir el guardado completará el inventario" (3.9) | **Falso** (QA-02) |
| "Haría falta `LOCK TABLES`" (3.9) | **Falso**: basta `GET_LOCK` (QA-04) |
| "La descripción se guardó con `addslashes`" (5.1) | **Falso** en lo que importa: MySQL consume las barras (QA-22) |
| "Sin cambio de comportamiento" del índice en `objeto_id` (6.2) | **No garantizado** con duplicados (QA-16) |
| `escape_string_like` y el `\_` de la marca | Correcto (`inc/db_mysqli.php` línea 1050) |

---

## 11. Lo que no se puede verificar sin ejecutar

- Motor, juego de caracteres, `sql_mode`, zona horaria e índices reales de producción, y si `tiempo` tiene `ON UPDATE` (afecta a la hora del registro tras el paso 7). Necesita la calibración 13.4 más `SHOW CREATE TABLE`.
- El juego de caracteres de la conexión de MyBB (está en `inc/config.php`, que no se lee) y el valor de `errortypemedium` (QA-15).
- Si hay duplicados de `objeto_id`, también por mayúsculas, y cuántos ids de catálogo terminan en `-número` (QA-16, QA-26).
- El `EXPLAIN` real de Q1 a Q6 con el volumen de producción y el tamaño medio de los `log` de objetos (QA-17).
- La versión de PHP de producción (`JSON_INVALID_UTF8_SUBSTITUTE` necesita 7.2 o superior).
- Los recuentos de 112-R y de 112-GD (1.304 frente a 1.204): el volcado del repositorio no trae datos de estas tablas.
- Que `objetos_crear.php` ya no esté enlazado desde la consola del staff (112-R D-13): no se revisaron las plantillas del menú de staff.
- Todo lo visual de 214-UI 10 (navegador y capturas del usuario).
- El comportamiento real de MyISAM con dos procesos: solo se puede afirmar después de las pruebas de la sección 9.
