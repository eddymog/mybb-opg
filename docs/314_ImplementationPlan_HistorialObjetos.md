# Historial público de objetos: plan de implementación

> Depende de:
> [112_Requirements_HistorialObjetos.md](112_Requirements_HistorialObjetos.md) (requisitos, revisión 2, "112-R"),
> [112_Reglas_HistorialObjetos.md](112_Reglas_HistorialObjetos.md) (reglas de juego, "112-GD"),
> [214_UI_HistorialObjetos.md](214_UI_HistorialObjetos.md) (especificación visual, "214-UI") y
> [214_DesignPlan_HistorialObjetos.md](214_DesignPlan_HistorialObjetos.md) (diseño técnico, "214-DP").
> Modelo: [308_ImplementationPlan_Notificaciones.md](308_ImplementationPlan_Notificaciones.md).
>
> **Estado: propuesta del PM. Las issues NO están creadas en GitHub**: el usuario aún no ha aprobado los requisitos. Los identificadores HO-1 a HO-11 son provisionales; al crear las issues se sustituyen por los números reales en todo el documento. La issue existente #33 se reorienta (sección 3) y la #34 es dependencia externa (sección 4).
>
> Aviso para quien lance agentes: `docs/` está fuera de git salvo `docs/agents/` y los archivos forzados con `git add -f`. Un Developer en su propio worktree **no tendrá** 112-R, 112-GD, 214-UI, 214-DP ni este documento. Por eso cada issue contiene sus propios criterios, y el prompt de cada agente debe indicar la ruta del checkout principal (`/Users/eddymogollon/Documents/Code/mybb-opg/docs/...`) o copiar las secciones necesarias.

## 1. Resumen y orden de entrega

La funcionalidad se entrega en **11 issues nuevas** más la **#33 reorientada**, en cuatro fases que se despliegan por separado:

| Fase | Issues | Qué consigue | ¿Toca interfaz? |
|---|---|---|---|
| A. Registro | HO-1, HO-2, HO-3, #33, HO-4 | Desde el despliegue de HO-2, cada cambio del catálogo deja su antes y su después. Nadie fuera del staff ve nada nuevo todavía | Solo HO-4 (botón del editor del staff) |
| B. Lectura pura | HO-5, HO-6, HO-7 | Interpretar el pasado, unir renombres, decidir qué ve cada visor, índices | No |
| C. Página | HO-8, HO-9 | La página pública | Sí |
| D. Enlaces y cierre | HO-10, HO-11 | Tarjeta en el catálogo, enlace desde técnicas, guía del staff | HO-10 sí |

Principios del orden:

1. **El registro va primero y no espera a las decisiones de visibilidad.** Se registra todo (también copias, invisibles y campos reservados) y la visibilidad se decide al leer (112-R, principio de la sección 1). Así la primera issue solo necesita dos cosas del usuario (sección 5.3).
2. **Contrato primero.** HO-1 define la librería `inc/plugins/lib/objetos_cambios.php` y todas las funciones que usan las páginas del staff. Después, HO-2, HO-3 y HO-5 van en paralelo sin tocar el mismo archivo.
3. **Lo que toca interfaz va al final**, cuando las reglas ya están probadas sin base de datos, y entra el UI Designer antes de empezar.
4. **La página no se publica hasta cerrar la #34** (fuga de datos del catálogo). El desarrollo no espera: el registro sigue acumulando historial mientras tanto.

### 1.1 Cambios respecto a las nueve issues propuestas por el Developer (214-DP 14)

| Developer | PM | Motivo |
|---|---|---|
| I-1 Librería de registro | **HO-1** | Igual, pero absorbe además el intérprete del formato nuevo, el texto legible para el log del staff y las dos funciones de apoyo de `objetos_modificar.php` (leer filas duplicadas y comprobar id ocupado). Así HO-2, HO-3 y HO-5 no tocan la librería a la vez |
| I-2 Registro en `objetos_modificar.php` + I-5 Log del staff | **HO-2** | Se unen. Si se despliega el registro sin el log legible, el staff ve JSON crudo en su log durante días. Juntos son 3 archivos, un cambio coherente y un solo despliegue |
| I-3 Registro en `barco_unico.php` | **HO-3** | Igual. Prueba con MySQL en un archivo propio (`barco_unico_db.php`) para no compartir `registro_db.php` con HO-1 y HO-2 |
| I-4 `objetos_crear.php` según D-13 | **#33 reorientada** + **HO-4** | Se parte en dos. La redirección (#33) cierra el agujero de seguridad ya, sin esperar a nada y sin tocar archivos compartidos. La acción "Retirar" en `objetos_modificar.php` (HO-4) va después de HO-2 porque comparte archivo. Hoy hay 0 objetos retirados, así que perder la retirada unos días no afecta a nadie |
| I-6 Parser antiguo, renombres, visibilidad | **HO-5** + **HO-6** | Se parte en dos para que el QA pueda revisar cada mitad contra sus ejemplos: HO-5 (antiguos y renombres, sin decisiones del usuario) y HO-6 (visibilidad por visor, con los ejemplos E-1 a E-15). Van en serie porque comparten la librería |
| I-9 Migración de índices | **HO-7** | Igual. Prueba propia (`indices_db.php`) en lugar de compartir `consulta_db.php` con la página. Se puede hacer desde el primer día |
| I-7 Página (grande) | **HO-8** + **HO-9** | Se parte: HO-8 genera el HTML de cada entrada en una librería pura (las pruebas de no fuga viven aquí) y HO-9 hace la página, los filtros, las consultas y la plantilla. Cada una queda en carril normal |
| I-8 Tarjeta y enlace | **HO-10** | Igual. Depende además de la #34, que también modifica `op_objetos.html` |
| (no estaba) | **HO-11** | Guía de operación para el staff y cierre de documentación, como la #16 de notificaciones |

Otro cambio: la constante `OPG_OBJETOS_CAMBIO_INICIO` (fecha desde la que hay registro detallado) **pasa de I-2 a HO-9**. Solo la usa la página, y en HO-9 ya se conoce la fecha real del despliegue de HO-2, así que no hay que adivinarla ni rehacer un commit tras desplegar.

### 1.2 Grafo de dependencias

```
HO-1 ──┬──> HO-2 ──┬──────────────> HO-4 (también tras #33)
       │           │
       ├──> HO-3   │ (HO-3 se despliega con o después de HO-2)
       │
       └──> HO-5 ──> HO-6 ──> HO-8 ──> HO-9 ──> HO-10 ──> HO-11
                                         ^        ^
#33 (independiente) ──> HO-4             │        │
HO-7 (independiente) ··· recomendado ····┘        │
#34 (externa) ···· bloquea PUBLICAR HO-9 ·········┴·· y bloquea HO-10 (archivo compartido)
```

### 1.3 Paralelismo (máximo 3 agentes, archivos disjuntos)

| Ola | Issues en paralelo | Archivos de cada una (sin solapes) |
|---|---|---|
| 0 | HO-1, #33, HO-7 | HO-1: `inc/plugins/lib/objetos_cambios.php`, `tests/objetos/cambios.php`, `tests/objetos/log_staff.php`, `tests/objetos/registro_db.php`. #33: `op/staff/objetos_crear.php`. HO-7: `docs/214_historial_objetos_indices.sql`, `tests/objetos/indices_db.php` |
| 1 | HO-2, HO-3, HO-5 | HO-2: `op/staff/objetos_modificar.php`, `op/staff/log_consola_mod.php`, `tests/objetos/modificar_db.php`. HO-3: `op/barco_unico.php`, `tests/objetos/barco_unico_db.php`. HO-5: `inc/plugins/lib/objetos_cambios.php`, `tests/objetos/antiguos.php`, `tests/objetos/renombres.php` |
| 2 | HO-4, HO-6 (y la #34 si hay hueco) | HO-4: `op/staff/objetos_modificar.php`, `staff_objetos_modificar.html`, `tests/objetos/retirar_db.php`. HO-6: `inc/plugins/lib/objetos_cambios.php`, `tests/objetos/visibilidad.php`. #34: `op/objetos.php`, `op_objetos.html`, `op/armas_todas.php`, `op/objetos_armas_todas.php` |
| 3 | HO-8 (y la #34 si sigue abierta) | HO-8: `inc/plugins/lib/objetos_historial_vista.php`, `tests/objetos/vista.php` |
| 4 | HO-9 | `op/historial_objetos.php`, `op_historial_objetos.html`, `tests/objetos/filtros.php`, `tests/objetos/consulta_db.php` (y amplía `objetos_historial_vista.php`) |
| 5 | HO-10 | `op_objetos.html`, `op_historial_tecnicas.html` |
| 6 | HO-11 | Solo documentos |

Archivos compartidos que obligan a ir en serie:
- `inc/plugins/lib/objetos_cambios.php`: HO-1, HO-5 y HO-6, en ese orden.
- `op/staff/objetos_modificar.php`: HO-2 y HO-4, en ese orden.
- `inc/plugins/lib/objetos_historial_vista.php`: HO-8 y HO-9, en ese orden.
- `templates/One_Piece_Gaiden_Templates/op_objetos.html` y `op/objetos.php`: #34 y HO-10, en ese orden.
- `tests/aventuras/bootstrap_db.php`: **ninguna issue lo modifica**; las pruebas con MySQL lo cargan y crean sus propias tablas.

## 2. Índice de issues

| ID | Título | Carril | Interfaz | Fase | Depende de | Requisitos de 112-R | Paralelo con |
|---|---|---|---|---|---|---|---|
| HO-1 | [Historial de objetos] Crear la librería de registro de cambios | normal | no | A | ninguna | 36 a 41, 43, 45, 67; apoyo de 44, 64, 65 | #33, HO-7 |
| HO-2 | [Historial de objetos] Registrar el antes y el después en el editor de objetos y verlo en el log del staff | normal | no | A | HO-1 | 35, 37, 38, 44, 64 a 68 | HO-3, HO-5 |
| HO-3 | [Historial de objetos] Registrar los cambios de barcos únicos | normal | no | A | HO-1 | 35, 37, 38, 39, 67 | HO-2, HO-5 |
| #33 | [Historial de objetos] Sustituir objetos_crear.php por una redirección (reorientada) | normal | no | A | ninguna | 42 (parte), D-13 | HO-1, HO-7 |
| HO-4 | [Historial de objetos] Añadir la acción Retirar al editor de objetos | normal | **sí** | A | HO-2, #33 | 35, 42 | HO-6 |
| HO-5 | [Historial de objetos] Interpretar los registros antiguos y unir los renombres | normal | no | B | HO-1 | 7, 14, 46 a 50 | HO-2, HO-3 |
| HO-6 | [Historial de objetos] Decidir qué ve cada visor | normal | no | B | HO-5 | 1 a 4, 9, 13, 18 a 21, 49, 69 a 75, 79 | HO-4 |
| HO-7 | [Historial de objetos] Migración idempotente de índices | normal | no | B | ninguna | 57, 58, 63 | cualquiera |
| HO-8 | [Historial de objetos] Pintar las entradas del historial | normal | **sí** | C | HO-6 | 11 a 19, 21, 31, 33, 34, 47, 48, 54, 60, 75, 77, 78, 79 | #34 |
| HO-9 | [Historial de objetos] Crear la página pública con filtros y su plantilla | normal | **sí** | C | HO-8 (HO-7 recomendada); publicar tras #34 | 6, 8, 10, 16, 22 a 30, 51 a 53, 55 a 59, 61, 62, 76, 80 | ninguna |
| HO-10 | [Historial de objetos] Enlazar el historial desde el catálogo y desde técnicas | normal | **sí** | D | HO-9 publicada, #34 | 31, 32 | ninguna |
| HO-11 | [Historial de objetos] Guía de operación y cierre de la documentación | trivial | no | D | todas | n/a | ninguna |

### 2.1 Trazabilidad de requisitos (112-R revisión 2)

| Requisitos | Issue |
|---|---|
| 1 (regla de catálogo; recuento por calibración) | HO-6 |
| 2, 3, 4, 9, 20, 21, 69 a 74 (reglas) | HO-6 |
| 5 (sin movimientos de inventario) | HO-5 (patrones), HO-9 (consulta) |
| 6, 8, 10, 22 a 30, 76 | HO-9 |
| 7 (copias como base, con renombres) | HO-5 (base), HO-6 (marcas), HO-9 (alcance) |
| 11 a 19, 33, 34, 54, 60, 75, 77, 78, 79 (presentación) | HO-8 |
| 31 (ficha y ancla) | HO-8 (ficha), HO-10 (ancla en el catálogo) |
| 32 (tarjeta) | HO-10 |
| 35 (rutas del staff) | HO-2, HO-3, HO-4 (y #33 con D-13 B) |
| 36 a 41, 43, 45, 67 | HO-1 (y HO-2, HO-3 usan el contrato) |
| 42 (retirada) | HO-1 (acción), HO-4 (ruta) |
| 44 (log del staff) | HO-1 (texto), HO-2 (página del log) |
| 46 a 50 (pasado) | HO-5 (interpretar), HO-6 (visibilidad), HO-8 (mostrar) |
| 51 a 53, 55, 56 (accesibilidad) | HO-9 (plantilla), HO-8 (marcado) |
| 57 a 59, 61 a 63 | HO-9; índices en HO-7 |
| 64 a 66, 68 | HO-2 (con funciones de HO-1) |
| 80 (publicación tras #34) | Puerta de despliegue de HO-9 (Orchestrator) |

Los criterios de aceptación globales de 112-R sección 14 se reparten entre las issues y se vuelven a comprobar en HO-11.

### 2.2 Pruebas y entorno (común a todas)

- Las pruebas viven en `tests/objetos/` (carpeta nueva; `tests/run_all.sh` la recorre sola). Las puras corren con `sh tests/run_all.sh`; las de MySQL cargan `tests/aventuras/bootstrap_db.php` y se lanzan con `sh tests/run_all.sh --db --area objetos` (las de `tests/aventuras/` siguen en rojo conocido, issue #27).
- En las pruebas con MySQL, `mybb_op_objetos` se crea **MyISAM** y `mybb_op_audit_consola_mod` con `log TEXT` en juego **`utf8` de 3 bytes**, como en el volcado de producción, aunque el contenedor de pruebas use `utf8mb4` por defecto. Si no, las pruebas de tamaño y de emoji no prueban nada.
- Ningún agente usa la base de datos de producción.

## 3. Texto de las issues

Texto listo para pegar en GitHub (título en el encabezado, cuerpo debajo). Plantilla de `docs/agents/process.md`. Todas las issues son de tipo `enhancement` salvo HO-11 (`documentation`) y la #33 (ya existe). Al crearlas, el PM sustituye "HO-n" por "#número" en los textos.

---

### HO-1 [Historial de objetos] Crear la librería de registro de cambios

**Carril:** normal
**Interfaz:** no

**Contexto.** Primera pieza del historial público de objetos (`op/historial_objetos.php`). Hoy los cambios del catálogo no guardan el valor anterior de ningún campo. Esta issue crea el contrato del registro: cómo se describe un cambio, cómo se guarda en `mybb_op_audit_consola_mod` y cómo se lee de vuelta. No cambia ningún comportamiento: la librería no tiene consumidores hasta HO-2 y HO-3. Documentos (locales, fuera de git): `docs/112_Requirements_HistorialObjetos.md` sección 9, `docs/112_Reglas_HistorialObjetos.md` sección 3 y `docs/214_DesignPlan_HistorialObjetos.md` secciones 3, 5.1 (solo el formato nuevo) y 12.

**Requisitos que cubre.** 36 a 41, 43, 45 y 67 de 112-R; funciones de apoyo para 44, 64 y 65.

**Alcance.**
- Crea `inc/plugins/lib/objetos_cambios.php` con:
  - Constantes `OPG_OBJETOS_CAMBIO_MARCA` (`'[CAMBIO_OBJETO] '`, 16 caracteres exactos con el espacio) y `OPG_OBJETOS_CAMBIO_MAX_BYTES` (60000).
  - Catálogo de los 32 campos registrados: columna, etiqueta para el jugador, grupo (uno de los nueve: Identidad, Combate y uso, Precio y comercio, Fabricación, Inventario, Presentación, Disponibilidad, Mercado negro, Reservado), tipo de comparación y clase de visibilidad (pública, imagen, transición, privada, mercado negro, solo staff), según 214-DP 3.3.
  - Normalización de valores, instantánea de disponibilidad, decisión de la acción, construcción del payload versión 1, serialización con abreviado.
  - Registro en dos fases: `opg_objetos_registro_abrir()` y `opg_objetos_registro_cerrar()` (reciben `$db`).
  - Lectura de todas las filas con un `objeto_id` y comprobación de si un `objeto_id` de destino ya existe.
  - Rama del formato estructurado de `opg_objetos_interpretar_log()` (los formatos antiguos llegan en HO-5).
  - Texto legible de un registro estructurado para el log del staff.
- Crea `tests/objetos/cambios.php` (puro), `tests/objetos/log_staff.php` (puro) y `tests/objetos/registro_db.php` (MySQL de pruebas).
- Fuera de alcance: cualquier página (HO-2, HO-3), formatos antiguos y renombres (HO-5), reglas de visibilidad (HO-6). No se modifica `tests/aventuras/bootstrap_db.php`.

**Criterios de aceptación.**
- [ ] El payload sigue el esquema versión 1 (`v`, `accion`, `id`, `objeto_id_antes`, `objeto_id`, `nombre`, `origen`, `autor`, `estado`, `vis_antes`, `vis_despues`, `cambios`) **en ese orden**; una prueba comprueba el prefijo exacto `[CAMBIO_OBJETO] {"v":1,"accion":"`.
- [ ] El UID del staff no está en el JSON (va solo en la columna `staff`).
- [ ] Se registran los 32 campos de la lista (`objeto_id`, `nombre`, `descripcion`, `categoria`, `subcategoria`, `tier`, `imagen_id`, `imagen_avatar`, `imagen`, `berries`, `berriesCrafteo`, `cantidadMaxima`, `dano`, `bloqueo`, `efecto`, `alcance`, `escalado`, `requisitos`, `exclusivo`, `invisible`, `espacios`, `crafteo_usuarios`, `desbloquear`, `oficio`, `nivel`, `tiempo_creacion`, `editable`, `comerciable`, `negro`, `negro_berries`, `fusion_tipo`, `engastes`), cada uno con su grupo de los nueve; `custom`, `desbloqueado` y `fecha_creacion` nunca aparecen en `cambios`.
- [ ] `vis_antes` y `vis_despues` llevan siempre las nueve claves (`custom`, `invisible`, `exclusivo`, `tier`, `comerciable`, `negro`, `oficio_vacio`, `crafteo_vacio`, `categoria`) aunque no cambien; `vis_antes` es `null` en un alta; la instantánea nunca contiene UID.
- [ ] La acción se decide con esta prioridad: sin fila antes, `alta`; id pasa a `BORR...` y categoría a `borrada`, `retirada`; cambia `objeto_id`, `renombrado`; `invisible` pasa de 1 a 0, `publicado`; si no, `actualizacion`.
- [ ] Sin diferencias, la construcción devuelve `null`. Añadir o quitar espacios en los extremos o cambiar `\r\n` por `\n` no es una diferencia.
- [ ] En un alta, `cambios` incluye todos los campos con `antes = ""`. Todos los valores de `antes` y `despues` son cadenas.
- [ ] Serialización: valores largos (`descripcion`, `efecto`, `escalado`, `requisitos`, imágenes) cortados a 3.000 caracteres, cortos a 500 y `nombre` a 255, con `"abreviado": true`; si el total supera 60.000 bytes se reintenta con límites menores y, como último recurso, una versión mínima. Nunca se escribe un JSON truncado ni de más de 60.000 bytes.
- [ ] Los caracteres de 4 bytes (emoji) se guardan como pares sustitutos JSON y el JSON sigue siendo válido; un UTF-8 inválido no rompe la serialización.
- [ ] `abrir` devuelve 0 sin escribir nada si no hay cambios esperados; si los hay, inserta el registro con `"estado":"pendiente"`, `razon = 'Cambio de objeto'`, `staff` = UID de la sesión y `username` escapado, y devuelve su id.
- [ ] `cerrar` relee la fila por `id` interno y sustituye el log por el payload real con `"estado":"confirmado"`, o `"sin_cambios"` si la relectura no muestra diferencias.
- [ ] Todo valor que llega a SQL es un entero o pasa por `$db->escape_string()`.
- [ ] `opg_objetos_interpretar_log()` devuelve el modelo común de 214-DP 6.4 para un registro estructurado válido, y `null` (sin avisos de PHP) para JSON roto, versión distinta de 1, claves que faltan o cualquier texto sin la marca.
- [ ] El texto legible para el staff cubre alta, modificación (`Campo: antes → después`), renombre, retirada y `pendiente` ("Cambio sin confirmar").
- [ ] Pruebas con MySQL (tablas con el motor y el juego de caracteres de producción, ver 2.2): flujo completo `abrir`, `UPDATE`, `cerrar` con registro confirmado; una cadena vacía en una columna entera se relee como `0` y no aparece como cambio en el siguiente guardado; un fallo simulado entre `abrir` y la escritura deja un registro `pendiente` y el catálogo sin cambios; un log de 60.000 bytes se guarda entero y se vuelve a leer; con dos filas del mismo `objeto_id` hay un registro por fila; la comprobación de id ocupado detecta un destino existente.
- [ ] `sh tests/run_all.sh` y `sh tests/run_all.sh --db --area objetos` terminan en VERDE.

**Pruebas esperadas.** `tests/objetos/cambios.php`, `tests/objetos/log_staff.php` y `tests/objetos/registro_db.php` (`php tests/objetos/<archivo>.php`).

**Depende de.** Ninguna. Antes de empezar: el usuario confirma D-11 A (registro en `mybb_op_audit_consola_mod` con marca y JSON) y aprueba la sección 9 de los requisitos (35 a 45 y 64 a 68).

**Para el usuario.** Nada que subir ni ejecutar (la librería no tiene consumidores). Recomendado antes de empezar: las consultas de calibración de solo lectura de 214-DP 13.4 (motor de las tablas, `sql_mode`, `objeto_id` duplicados y longitudes máximas), para que las pruebas reproduzcan producción.

**Plan de reversión.** Revertir el commit. No toca plantillas, SQL ni datos.

---

### HO-2 [Historial de objetos] Registrar el antes y el después en el editor de objetos y verlo en el log del staff

**Carril:** normal
**Interfaz:** no (solo cambia el texto de mensajes que ya existen en el editor y en el log del staff; no toca plantillas)

**Contexto.** Con esta issue empieza a acumularse historial: cada guardado de `op/staff/objetos_modificar.php` deja el valor anterior y el nuevo de cada campo. Es la primera pieza que toca una herramienta en uso. El log interno del staff aprende a leer el formato nuevo en la misma issue, para que el staff nunca vea JSON crudo. Documentos (locales): 112-R sección 9; 214-DP 3.9 y 4.1.

**Requisitos que cubre.** 35 (ruta `objetos_modificar.php`), 37, 38, 44, 64, 65, 66, 67 y 68 de 112-R; criterios 2, 3, 12, 17 y 20 de su sección 14.

**Alcance.**
- Modifica `op/staff/objetos_modificar.php` (solo el bloque `POST`) y `op/staff/log_consola_mod.php`.
- Crea `tests/objetos/modificar_db.php`.
- Fuera de alcance: la acción Retirar (HO-4), plantillas, `barco_unico.php` (HO-3), rutas de jugadores, `objetos_crear.php` (#33).

**Criterios de aceptación.**
- [ ] La comprobación de la clave CSRF y de permisos sigue ocurriendo antes de cualquier lectura o escritura.
- [ ] Al guardar: se leen todas las filas con el `objeto_id` de partida, se abre un registro por fila con `opg_objetos_registro_abrir()`, se escribe el objeto con la sentencia de hoy, en un renombre se actualiza `mybb_op_inventario` **después** del `UPDATE` del objeto, y se cierra cada registro con `opg_objetos_registro_cerrar()`.
- [ ] Guardar sin cambios no escribe ni el objeto ni el registro y muestra "No había cambios que guardar." en lugar de "Objeto guardado.".
- [ ] Renombrar hacia un `objeto_id` que ya existe se rechaza con "Ya existe otro objeto con el ID X." sin escribir nada.
- [ ] Ya no se insertan los logs antiguos `Objeto X (creado por Y): ...` ni `Objeto X (modificado por Y): ...`; la razón de los registros nuevos es "Cambio de objeto" (no "Apertura").
- [ ] Las ediciones de copias de jugadores (`custom=1`) se registran igual que las del catálogo.
- [ ] `log_consola_mod.php` muestra los registros `[CAMBIO_OBJETO]` como texto legible y los `pendiente` como "Cambio sin confirmar"; el resto de logs (técnicas incluidas) se ven exactamente igual que antes; todo lo que pinta está escapado.
- [ ] Prueba con MySQL que reproduce la secuencia de la página: `berries` de 5000 a 6000 deja un registro confirmado con un único cambio; renombrar `KAT001` a `KATF001` conserva el `id` interno y mueve el inventario; renombrar hacia un id ocupado no escribe nada; guardar sin cambios no escribe nada.
- [ ] `php -l` de los archivos tocados, `sh tests/run_all.sh` y `sh tests/run_all.sh --db --area objetos` en VERDE.

**Pruebas esperadas.** `tests/objetos/modificar_db.php`; siguen en verde las de HO-1.

**Depende de.** HO-1.

**Para el usuario.** Sin plantillas ni SQL. Se despliega junto con HO-1 (si no se desplegó antes). Tras subirlo: editar un objeto de prueba, comprobar que el log del staff muestra el cambio legible y avisar al staff de los dos mensajes nuevos. **El Orchestrator anota en un comentario de la issue la fecha y hora del despliegue**: es la fecha que usará HO-9 en el aviso "Antes del [fecha] no se guardaba qué cambiaba".

**Plan de reversión.** Revertir el commit y volver a desplegar los dos archivos anteriores. Los registros ya escritos se quedan (son filas añadidas a una tabla de auditoría); si además se retira la librería, el log del staff los enseña como texto crudo.

---

### HO-3 [Historial de objetos] Registrar los cambios de barcos únicos

**Carril:** normal
**Interfaz:** no

**Contexto.** `op/barco_unico.php` crea y edita barcos del catálogo (`custom=0`, categoría Transportes) y hoy no deja ningún rastro. Sin esta issue, el historial tendría huecos en los barcos. Documentos (locales): 112-R requisito 35; 214-DP 4.2.

**Requisitos que cubre.** 35 (ruta `barco_unico.php`), 37, 38, 39 y 67 de 112-R; criterio 16 de su sección 14.

**Alcance.**
- Modifica `op/barco_unico.php` (acciones `crear` y `editar`, solo alrededor de la escritura en `mybb_op_objetos`).
- Crea `tests/objetos/barco_unico_db.php`.
- Fuera de alcance: las estadísticas de `mybb_op_barcos` (vitalidad, velocidad...), que no son columnas del objeto.

**Criterios de aceptación.**
- [ ] `crear` y `editar` envuelven la escritura de `mybb_op_objetos` con `abrir` y `cerrar`, con `origen = 'barco_unico'`.
- [ ] La lectura del antes sustituye a la comprobación de existencia actual (no se añaden consultas innecesarias).
- [ ] El valor esperado se calcula con los valores **sin escapar** del formulario: un nombre con comilla no genera un cambio fantasma.
- [ ] La respuesta JSON de la página y la comprobación de `post_code` no cambian.
- [ ] Guardar un barco sin cambios no genera registro.
- [ ] Prueba con MySQL: alta y edición de un barco dejan registros confirmados; la comilla en el nombre no genera cambio en un segundo guardado idéntico.
- [ ] `sh tests/run_all.sh` y `sh tests/run_all.sh --db --area objetos` en VERDE.

**Pruebas esperadas.** `tests/objetos/barco_unico_db.php`.

**Depende de.** HO-1.

**Para el usuario.** Sin plantillas ni SQL. Se despliega con HO-2 o después (si va antes, el log del staff enseñaría los registros de barcos como texto crudo).

**Plan de reversión.** Revertir el commit y desplegar el archivo anterior. Los registros ya escritos se quedan.

---

### #33 (reorientada) [Historial de objetos] Sustituir objetos_crear.php por una redirección

Comentario que el PM deja en la #33 al reorientarla: "Estado: lista. Se reorienta según la decisión D-13 A del usuario y el diseño 214-DP 4.3: el archivo no se borra (el despliegue nunca borra en el servidor, y un archivo borrado en local seguiría vivo en remoto), se sustituye por una redirección. La acción de retirar objetos pasa a `objetos_modificar.php` en la issue HO-4."

Cuerpo nuevo (para D-13 A):

**Carril:** normal (seguridad en una herramienta del staff)
**Interfaz:** no

**Contexto.** `op/staff/objetos_crear.php` ya no está enlazada desde la consola del staff y no cumple las reglas de seguridad de `AGENTS.md`. `op/staff/objetos_modificar.php` ya cubre crear y modificar objetos. Se sustituye por una redirección, siguiendo el patrón de `op/ficha.php`. Hoy hay 0 objetos retirados, así que la retirada puede quedar sin herramienta unos días hasta HO-4.

**Requisitos que cubre.** 42 (parte) de 112-R y decisión D-13 A.

**Alcance.**
- Modifica `op/staff/objetos_crear.php`.
- Fuera de alcance: la acción Retirar en `objetos_modificar.php` (HO-4); la plantilla `staff_objetos_crear.html` se deja como está (sin uso).

**Criterios de aceptación.**
- [ ] El archivo responde con una redirección 301 a `/op/staff/objetos_modificar.php`, como `op/ficha.php`.
- [ ] Ninguna rama del archivo lee datos del formulario ni escribe en la base de datos.
- [ ] El archivo no se borra del repositorio.
- [ ] `php -l` y `sh tests/run_all.sh` en VERDE.

**Pruebas esperadas.** Revisión del QA del archivo (sin lógica que probar) y comprobación manual de la redirección.

**Depende de.** Ninguna (solo de la decisión D-13).

**Para el usuario.** Subir el archivo. **No hay que borrar nada en el servidor** (esto corrige la nota anterior de la issue). Avisar al staff de que retirar objetos estará disponible en el editor de objetos cuando se cierre HO-4.

**Plan de reversión.** Restaurar la versión anterior desde git y desplegarla. No toca plantillas, SQL ni datos. (Devolvería la página insegura: solo si hay una urgencia del staff.)

Si el usuario elige **D-13 B** en lugar de A, el cuerpo cambia a: reescribir `objetos_crear.php` con escape de todos los valores, `verify_post_check` y registro estructurado con `abrir` y `cerrar` en alta y retirada; añadir `my_post_key` a `staff_objetos_crear.html` (Interfaz: sí, plantilla acoplada que se sube **a la vez** que el PHP); depende de HO-1; HO-4 desaparece.

---

### HO-4 [Historial de objetos] Añadir la acción Retirar al editor de objetos

**Carril:** normal
**Interfaz:** sí (botón y confirmación en `staff_objetos_modificar.html`; el UI Designer añade los criterios visuales antes de empezar)

**Contexto.** Con D-13 A, la retirada de objetos (que vivía en `objetos_crear.php`) pasa al editor de objetos, con seguridad y registro estructurado. Documentos (locales): 112-R requisito 42; 214-DP 4.3.

**Requisitos que cubre.** 35 y 42 de 112-R; criterio 5 de su sección 14 (la parte del registro).

**Alcance.**
- Modifica `op/staff/objetos_modificar.php` (nueva acción `POST accion=retirar`) y `templates/One_Piece_Gaiden_Templates/staff_objetos_modificar.html` (botón "Retirar objeto" con confirmación y `my_post_key`).
- Crea `tests/objetos/retirar_db.php`.
- Fuera de alcance: actualizar `mybb_op_inventario` al retirar (hoy no se hace; queda en el backlog).

**Criterios de aceptación.**
- [ ] La acción exige `verify_post_check` y permiso de staff (`is_mod` o `is_staff` del UID de la sesión).
- [ ] Localiza el objeto por `id` interno y aplica la misma sentencia de hoy: `objeto_id` pasa a `BORR` + `objeto_id` actual y `categoria` a `borrada`, con valores escapados.
- [ ] La retirada queda registrada con `abrir` y `cerrar`, acción `retirada`.
- [ ] Retirar un objeto que ya empieza por `BORR` se rechaza con un mensaje y no escribe nada.
- [ ] El botón pide confirmación antes de enviar y funciona aunque falle el JavaScript (el formulario lleva su propia confirmación o un segundo paso).
- [ ] Criterios visuales del UI Designer cumplidos.
- [ ] Prueba con MySQL: la retirada deja un registro confirmado con acción `retirada`; una segunda retirada se rechaza.
- [ ] `sh tests/run_all.sh` y `sh tests/run_all.sh --db --area objetos` en VERDE.

**Pruebas esperadas.** `tests/objetos/retirar_db.php`.

**Depende de.** HO-2 (mismo archivo) y #33.

**Para el usuario.** **Paso acoplado (plantilla).** Subir primero `objetos_modificar.php` (el manejador puede existir antes que el botón) y después `staff_objetos_modificar.html`; subir la plantilla la activa en unos 10 segundos. Guardar antes la versión actual de la plantilla.

**Plan de reversión.** Subir la versión anterior de `staff_objetos_modificar.html` desde git y revertir el commit del PHP. Las retiradas hechas no se deshacen solas: si hiciera falta, el staff renombra el objeto de vuelta desde el editor (y ese cambio también queda registrado).

---

### HO-5 [Historial de objetos] Interpretar los registros antiguos y unir los renombres

**Carril:** normal
**Interfaz:** no

**Contexto.** Los 430 registros antiguos de objetos no tienen antes ni después, pero sí fechas y estado final. Esta issue los reconoce de forma tolerante, construye el mapa de renombres (para que el historial de un objeto renombrado sea uno solo) y calcula la base de una copia de jugador siguiendo la cadena de renombres. No depende de ninguna decisión pendiente. Documentos (locales): 112-R sección 10 y requisito 7; 112-GD 5.4.3 y 6; 214-DP 5, 6.1 (Q2) y 7.5.

**Requisitos que cubre.** 7 (cálculo de la base), 14, 46, 47, 48, 49 (resolución del objeto actual) y 50 de 112-R.

**Alcance.**
- Modifica `inc/plugins/lib/objetos_cambios.php` (ramas antiguas de `opg_objetos_interpretar_log()`, mapa de alias a partir de las filas de la consulta de renombres, resolución del objeto actual de un registro antiguo, base de una copia).
- Crea `tests/objetos/antiguos.php` y `tests/objetos/renombres.php`.
- Fuera de alcance: reglas de visibilidad (HO-6), consultas a la base (HO-9).

**Criterios de aceptación.**
- [ ] Reconoce los tres formatos antiguos (`Nuevo objeto creado de objeto ID ...`, `Objeto X (creado por Y): ...`, `Objeto X (modificado por Y): ...`) con las expresiones de 214-DP 5.1, con ejemplos reales: descripción con comas, saltos de línea y `\'`; nombre con comas.
- [ ] Descarta `calidad` y `unidades` del alta antigua.
- [ ] Reconoce la retirada antigua que termina en `Tipo: borrada.` y la marca como dudosa (solo para el staff); no reconoce las de técnicas (`estilo: borrada.`), akumas ni virtudes.
- [ ] Los logs de técnicas, de fichas, JSON roto o versión 2 devuelven `null` sin avisos de PHP.
- [ ] Nunca lee `razon` ni `staff`.
- [ ] El mapa de alias se construye solo con registros estructurados `confirmado` de renombre y retirada.
- [ ] La cadena `KAT001 → KATF001 → KATG001` resuelve al id actual; un ciclo `A → B → A` termina (máximo 20 saltos y conjunto de visitados).
- [ ] La copia `KAT001-261-3` cuenta como copia de `KATF001` tras el renombre; el barco `BARCO01-4` resuelve su base; si el prefijo existe hoy como objeto de catálogo, gana la fila actual.
- [ ] Un registro antiguo se une a su objeto actual por el mapa de alias o por `objeto_id`, y queda sin objeto si no existe.
- [ ] Las pruebas de HO-1 siguen en verde (la librería ya está en uso por el editor del staff).
- [ ] `sh tests/run_all.sh` en VERDE.

**Pruebas esperadas.** `tests/objetos/antiguos.php` y `tests/objetos/renombres.php`.

**Depende de.** HO-1.

**Para el usuario.** Nada (la parte nueva no tiene consumidores hasta HO-9). Opcional y de solo lectura, a petición expresa: contar en producción cuántos de los 430 registros antiguos reconoce el intérprete (se esperan 133, 49 y 248).

**Plan de reversión.** Revertir el commit. Cualquier despliegue de la librería afecta al editor del staff, así que la reversión se despliega igual que el cambio.

---

### HO-6 [Historial de objetos] Decidir qué ve cada visor

**Carril:** normal
**Interfaz:** no

**Contexto.** El corazón de las reglas: para cada registro y cada visor (visitante, usuario, dueño del objeto, usuario con acceso al mercado negro, staff), decidir si la entrada existe y qué campos y líneas muestra. Las decisiones del usuario son **configuración**: `opg_objetos_hist_politica()` devuelve la opción vigente de cada una, y las pruebas cubren todas las opciones. Documentos (locales): 112-GD completo (sobre todo 2, 3, 5 y los ejemplos E-1 a E-15 de la sección 7); 214-DP 7.

**Requisitos que cubre.** 1 a 4, 9, 13, 18 a 21, 49, 69 a 75 y 79 de 112-R; criterios 6, 7, 9, 18 y 19 de su sección 14.

**Alcance.**
- Modifica `inc/plugins/lib/objetos_cambios.php`: política con los valores recomendados (U-1 B, U-2 A, U-4 C, D-2 A, D-7 A, D-8 A, D-12 A, D-6 B ampliada), visor, acceso al mercado negro (misma regla que `op/mercado_negro.php`), objeto público para un visor, transiciones, filtrado de campos, líneas "Qué cambia para ti", excepción de dueño, marcas, construcción de la vista (sin el payload original) y texto de búsqueda.
- Crea `tests/objetos/visibilidad.php`.
- Fuera de alcance: HTML (HO-8), consultas (HO-9). No se refactoriza `op/mercado_negro.php`.

**Criterios de aceptación.**
- [ ] Un caso de prueba por ejemplo E-1 a E-15 de 112-GD, con visitante, usuario, dueño, usuario con acceso al mercado negro y staff, y el resultado que dice el documento.
- [ ] Las tres opciones de U-1 en E-7 y E-8; U-2 A y B en E-3; U-4 A y C en E-10; D-7 A y B. Cambiar una decisión es cambiar un valor de la política.
- [ ] Un registro nuevo se evalúa con su instantánea, no con el estado actual; una entrada que fue pública sigue siéndolo aunque el objeto se oculte después; al publicarse un objeto aparece "Nuevo en el catálogo" sin revelar cambios previos; al dejar de ser público, "Deja de estar en el catálogo" sin valores nuevos.
- [ ] Una creación única de personaje (id que empieza por número) invisible no es pública; una copia (`custom=1`) nunca es pública.
- [ ] Los registros antiguos se evalúan por el estado actual; si el objeto no existe o es una copia, solo los ve el staff; no tienen excepción de dueño.
- [ ] Si tras filtrar no queda ninguna fila ni línea visible, la vista es `null` para ese visor; la vista nunca contiene el payload original ni campos filtrados.
- [ ] `crafteo_usuarios`: textos exactos de 112-R requisito 21; nada público si cambia de una lista no vacía a otra; línea personal si el UID del visor entra o sale; el staff ve los UID (detalle de D-9).
- [ ] `negro` y `negro_berries` solo con acceso al mercado negro o staff; `fusion_tipo` y `engastes` solo staff; `invisible` nunca como fila para quien no es staff.
- [ ] El acceso al mercado negro reproduce la regla de `op/mercado_negro.php`: rango del inframundo distinto de vacío y de "Sin rango", o Mercader de nivel 2 o más con Contrabandista de nivel 1 o más (pruebas con rango, "Sin rango", Contrabandista 0 y 1, Mercader nivel 1).
- [ ] Las pruebas de HO-1 y HO-5 siguen en verde.
- [ ] `sh tests/run_all.sh` en VERDE.

**Pruebas esperadas.** `tests/objetos/visibilidad.php`.

**Depende de.** HO-5 (mismo archivo). Antes de empezar: el usuario confirma D-1 (si eligiera C, página solo para staff, esta issue se reduce a casi nada).

**Para el usuario.** Nada que desplegar por separado. Antes de **publicar** HO-9 debe confirmar los valores de la política (U-1, U-2, U-4, D-2, D-6, D-7, D-8, D-9, D-12); si cambia alguno, es un valor y su prueba.

**Plan de reversión.** Revertir el commit (se despliega con la librería, ver HO-5).

---

### HO-7 [Historial de objetos] Migración idempotente de índices

**Carril:** normal
**Interfaz:** no

**Contexto.** La página pública lee `mybb_op_audit_consola_mod` en cada visita (también los bots). Sin índices funciona, pero recorre unas 23.000 filas. La migración es opcional para el funcionamiento y recomendada antes de publicar. Documentos (locales): 214-DP 6.2 y 10.

**Requisitos que cubre.** 57, 58 y 63 de 112-R (rendimiento).

**Alcance.**
- Crea `docs/214_historial_objetos_indices.sql` (se versiona con `git add -f`, como `docs/notificaciones_migration.sql`, porque la prueba lo lee y los worktrees no tienen los archivos ignorados) y `tests/objetos/indices_db.php`.
- Fuera de alcance: índice en `mybb_op_inventario` (primero se mide).

**Criterios de aceptación.**
- [ ] Crea `idx_hobj_log_tiempo` sobre `mybb_op_audit_consola_mod (log(16), tiempo)` y `idx_objeto_id` sobre `mybb_op_objetos (objeto_id)` solo si no existen ya (patrón `information_schema` y `PREPARE` de `docs/bitacora_migration.sql`).
- [ ] Se puede ejecutar dos veces seguidas sin error.
- [ ] Termina con el SQL para deshacerla, comentado (dos `DROP INDEX`).
- [ ] La cabecera del archivo dice que se ejecute fuera de la hora punta (bloquea `mybb_op_objetos` unos segundos) y que el índice 2 se puede omitir.
- [ ] La prueba con MySQL ejecuta la migración dos veces, comprueba con `EXPLAIN` que la consulta con la marca y el periodo usa `range` sobre `idx_hobj_log_tiempo` (y `ALL` sin el índice) y que la reversión funciona.
- [ ] `sh tests/run_all.sh --db --area objetos` en VERDE.

**Pruebas esperadas.** `tests/objetos/indices_db.php`.

**Depende de.** Ninguna.

**Para el usuario.** **Paso acoplado (SQL).** Ejecutar `docs/214_historial_objetos_indices.sql` en producción fuera de la hora punta, en cualquier momento antes de publicar HO-9. Puede omitir el índice de `mybb_op_objetos` si no quiere tocar esa tabla. Después, si quiere, un `EXPLAIN` de solo lectura para confirmar `range`.

**Plan de reversión.** Ejecutar los dos `DROP INDEX` comentados al final del archivo. Solo índices: no afecta a los datos.

---

### HO-8 [Historial de objetos] Pintar las entradas del historial

**Carril:** normal
**Interfaz:** sí (el UI Designer añade sus criterios visuales antes de empezar y revisa antes del QA)

**Contexto.** Librería pura que recibe vistas ya filtradas (HO-6) y devuelve el HTML de cada entrada, la ficha compacta del objeto y las marcas, siguiendo la especificación visual. Aquí viven las pruebas de no fuga: el HTML del público nunca contiene datos ocultos. Documentos (locales): 214-UI secciones 3, 6 y 7; 112-GD 4 y 5; 214-DP 7.9, 9.3 y 12.3.

**Requisitos que cubre.** 11 a 19, 21, 31 (ficha), 33, 34, 47, 48, 54, 60, 75, 77, 78 y 79 de 112-R; criterios 5, 8 y 11 de su sección 14.

**Alcance.**
- Crea `inc/plugins/lib/objetos_historial_vista.php` y `tests/objetos/vista.php`.
- Fuera de alcance: la página, los filtros, la plantilla y su CSS (HO-9).

**Criterios de aceptación.**
- [ ] Cada tipo de entrada (alta, actualización, renombrado, retirada, nuevo en el catálogo, deja de estar en el catálogo, registro antiguo de alta, registro antiguo de actualización) tiene su clase modificadora, su icono Font Awesome con `aria-hidden="true"` y su texto escrito.
- [ ] Cada entrada es un `article` con `id="cambio-N"` (id entero de auditoría) y `aria-labelledby` a su `h3` con el nombre; el `details` empieza cerrado.
- [ ] El `summary` cuenta solo los campos visibles ("Ver cambios (N campos)"); nunca se menciona un campo oculto.
- [ ] Los campos cortos van en una tabla con `caption` oculto, `th scope="col"` y `th scope="row"`; los largos en bloques de comparación; los abreviados llevan la nota "(texto abreviado en el registro)".
- [ ] Los registros antiguos no contienen "Antes", "Ahora", `ct-comparacion` ni tabla de tres columnas; muestran "Estado tras el cambio" o "Valores conservados".
- [ ] Las retiradas no contienen enlace a `objetos.php#` ni ficha.
- [ ] La ficha aparece solo cuando la vista lo permite; "Ver en el catálogo" solo si el objeto es visible, no es de Transportes y no está retirado; "Editar (staff)" solo para el staff.
- [ ] Marcas con texto e icono: "Lo tienes", "Tienes una copia", "Lo tienes · no aparece en el catálogo", "Cambio del staff en tu objeto", "Mercado negro", "Solo staff" con su motivo; aviso de copia del requisito 79.
- [ ] Formato: miles con punto, porcentaje con signo en `berries`, `berriesCrafteo` y `negro_berries` y diferencia absoluta en las demás cifras, siempre sin color; `tiempo_creacion` 10.000 como "2 h 47 min"; `9999999` como "Sin precio fijado"; `comerciable` invertido; `imagen_id` como nombre de color; Sí/No con la semántica del código.
- [ ] Escape: `<script>` en nombre, descripción y efecto sale como texto; una URL de imagen `javascript:` o que empiece por `//` no se pinta (se usa el icono de la categoría).
- [ ] No fuga (214-DP 12.3): con un registro con centinelas (`crafteo_usuarios = 'UIDCENTINELA352,1044'`, `negro_berries = 7777777`, `fusion_tipo = 'FUSIONCENTINELA'`, `engastes = 9`, razón `RAZONCENTINELA`, staff 999), el HTML del visitante y del usuario sin acceso al mercado negro no contiene ninguna centinela, ni `999`, ni `7.777.777`; el staff ve los reservados y nunca la razón.
- [ ] Criterios visuales del UI Designer cumplidos.
- [ ] `sh tests/run_all.sh` en VERDE.

**Pruebas esperadas.** `tests/objetos/vista.php`.

**Depende de.** HO-6. Antes de empezar, el UI Designer actualiza 214-UI con las resoluciones de las contradicciones C-2, C-4, C-5, C-9 y C-12 (sección 6.2 de este plan) y añade sus criterios a la issue.

**Para el usuario.** Nada (librería sin consumidores hasta HO-9).

**Plan de reversión.** Revertir el commit. No toca plantillas, SQL ni datos.

---

### HO-9 [Historial de objetos] Crear la página pública con filtros y su plantilla

**Carril:** normal
**Interfaz:** sí (el UI Designer añade sus criterios visuales antes de empezar y revisa antes del QA; capturas del usuario tras publicar)

**Contexto.** La página `op/historial_objetos.php`: lee y valida los parámetros, hace como máximo seis consultas, aplica la visibilidad, filtra, cuenta, pagina y rellena la plantilla `op_historial_objetos`. Documentos (locales): 112-R secciones 4 a 7 y 11 a 12; 214-UI secciones 2, 4, 5 y 9; 214-DP 6, 8, 9, 10 y 11.3.

**Requisitos que cubre.** 6, 8, 10, 16, 22 a 30, 51 a 53, 55 a 59, 61, 62, 76 y 80 de 112-R; criterios 1, 4, 10, 14 y 21 de su sección 14.

**Alcance.**
- Crea `op/historial_objetos.php`, `templates/One_Piece_Gaiden_Templates/op_historial_objetos.html`, `tests/objetos/filtros.php` y `tests/objetos/consulta_db.php`.
- Puede ampliar `inc/plugins/lib/objetos_historial_vista.php` (chips de tipo, filtros activos, paginación, estados y validación de parámetros).
- Define `OPG_OBJETOS_CAMBIO_INICIO` con la fecha del despliegue de HO-2 que anotó el Orchestrator.
- Fuera de alcance: `op/objetos.php`, `op_objetos.html` y `op_historial_tecnicas.html` (HO-10).

**Criterios de aceptación.**
- [ ] La página solo atiende `GET`, no lee `$_POST` y no escribe en ninguna tabla.
- [ ] Cada parámetro se valida contra la lista de 214-DP 8; uno inválido o manipulado (`categoria=Armas' OR 1=1`, `periodo=999`, `tier=abc`, `campo=reservado` sin ser staff, `alcance=mios` sin sesión) vuelve a su valor neutro y muestra "Algún filtro de la dirección no era válido y se ha restablecido." (o la nota de inicio de sesión en el caso de `alcance`).
- [ ] Solo `periodo` llega a SQL, como entero de una lista fija; los `LIKE` de la marca usan `escape_string_like`; las consultas no seleccionan `razon` ni `staff`.
- [ ] Como máximo seis consultas por visita (contadas con `DbShim` en la prueba); la del estado actual de los objetos va por clave primaria salvo para registros antiguos; el inventario solo se consulta con sesión y si hay candidatas; tope de 5.000 candidatos con el aviso de 214-DP 6.1.
- [ ] Recuentos, chips, opciones de los filtros (autores, subcategorías, tiers) y búsqueda se calculan sobre vistas ya filtradas para ese visor.
- [ ] Los chips de tipo son enlaces que conservan los demás filtros y vuelven a la página 1; un chip sin resultados no es enlace; la URL omite los parámetros neutros; la paginación (25 por página) conserva todos los filtros.
- [ ] Los estados de 214-UI 4 existen con sus textos exactos (vacío, sin resultados, mis objetos sin coincidencias, filtro no válido, mis objetos sin sesión, error).
- [ ] El aviso de registros antiguos aparece una sola vez, con la fecha de `OPG_OBJETOS_CAMBIO_INICIO`, solo si hay registros antiguos en los resultados.
- [ ] Plantilla: cumple los criterios de 214-UI 9 que le aplican (solo `#fff` y `rgba(0,0,0,.x)` como literales de color, clases `ct-*` reutilizadas y `ho-*` nuevas, 44 px de objetivo táctil, `:focus-visible` con `var(--opg-foco)`, un `@media` por breakpoint al final, sin `prefers-color-scheme` ni otro enlace a Font Awesome, sin JSON incrustado).
- [ ] Prueba con MySQL de las consultas: marca escapada (no coincide con `[CAMBIOXOBJETO]`), periodo con filas de 6, 29, 89 y 91 días, consulta de renombres sin periodo, tope.
- [ ] El HTML completo de la página para un visitante, con los datos centinela de HO-8, no contiene ninguna centinela.
- [ ] `sh tests/run_all.sh` y `sh tests/run_all.sh --db --area objetos` en VERDE.

**Pruebas esperadas.** `tests/objetos/filtros.php` y `tests/objetos/consulta_db.php`; siguen en verde las de HO-1 a HO-8.

**Depende de.** HO-8. HO-7 recomendada (no bloquea el desarrollo). **No se publica hasta que la #34 esté cerrada y desplegada** y el usuario haya confirmado los valores de la política (ver HO-6).

**Para el usuario.** **Pasos acoplados (plantilla y orden).** 1) Subir `op_historial_objetos.html` (inerte: nadie la usa todavía). 2) Subir `op/historial_objetos.php` y las librerías (`objetos_cambios.php` con HO-5 y HO-6, `objetos_historial_vista.php`); si la página llegara antes que la plantilla, saldría en blanco. 3) Comprobar la página sin sesión, con sesión y como staff. 4) Capturas de 214-UI sección 10 (1280, 768, 375 y 320 px). Antes: ejecutar la migración de HO-7 (recomendado).

**Plan de reversión.** La página no se puede borrar del servidor: se despliega una versión que redirige a `op/objetos.php`. La plantilla nueva puede quedarse sin uso. Ningún dato cambia.

---

### HO-10 [Historial de objetos] Enlazar el historial desde el catálogo y desde técnicas

**Carril:** normal (añade un script al catálogo)
**Interfaz:** sí (el UI Designer revisa antes del QA)

**Contexto.** Hacer que se encuentre la página: tarjeta de acceso en el catálogo de objetos, ancla `objetos.php#ID` que desplaza y resalta el objeto, y un enlace desde el historial de técnicas. Documentos (locales): 112-R requisitos 31 y 32; 214-UI sección 8.

**Requisitos que cubre.** 31 (ancla en el catálogo) y 32 de 112-R.

**Alcance.**
- Modifica `templates/One_Piece_Gaiden_Templates/op_objetos.html` y `templates/One_Piece_Gaiden_Templates/op_historial_tecnicas.html`.
- Fuera de alcance: `op/objetos.php` (lo cambia la #34), pintar la categoría Transportes (backlog).

**Criterios de aceptación.**
- [ ] `op_objetos.html` tiene un bloque `<style>` tras `{$headerinclude}` con la copia literal de `.ht-cta` de `op_tecnicas.html` y la tarjeta como primer hijo de `#objetos` (posición según V-5, recomendada arriba) con el texto de 214-UI 8.1.
- [ ] Tras pintar los objetos, un script lee `location.hash`, desplaza al elemento con ese id y le añade `ho-objeto-destacado`; si el id no existe, no hace nada ni muestra error; sin nombres de variable con `$`.
- [ ] `op_historial_tecnicas.html` solo cambia por el enlace "Historial de objetos" en su cabecera.
- [ ] Se parte de la versión de `op_objetos.html` que dejó la #34.
- [ ] Criterios visuales de 214-UI 9 que aplican a estas dos plantillas.
- [ ] `sh tests/run_all.sh` en VERDE.

**Pruebas esperadas.** Revisión del QA y del UI Designer de las dos plantillas; capturas del usuario (tarjeta, ancla y resaltado).

**Depende de.** HO-9 publicada (si no, enlaces rotos) y #34 (mismo archivo).

**Para el usuario.** **Paso acoplado (dos plantillas).** Guardar las versiones actuales y subir las dos plantillas solo cuando la página ya esté publicada.

**Plan de reversión.** Subir las versiones anteriores de las dos plantillas desde git.

---

### HO-11 [Historial de objetos] Guía de operación y cierre de la documentación

**Carril:** trivial
**Interfaz:** no

**Contexto.** Cierre de la funcionalidad: lo que el staff debe saber y la documentación al día.

**Requisitos que cubre.** Ninguno nuevo; comprueba de nuevo los criterios globales de 112-R sección 14.

**Alcance.**
- Crea `docs/112_Operacion_HistorialObjetos.md` (guía para el staff: preparar objetos con `invisible=1` y publicar una sola vez, porque cada publicación deja una entrada que no se borra; mensajes nuevos del editor; qué hacer con un "Cambio sin confirmar"; aviso de que la ayuda del formulario está invertida en `comerciable` y `exclusivo` mientras no se corrija; consultas de seguimiento de la sección 6 de este plan).
- Actualiza 112-R, 112-GD, 214-UI, 214-DP y este plan con lo realmente hecho y las decisiones tomadas; añade el prefijo `ho-` a la tabla de prefijos de `docs/style.md`; corrige en `docs/agents/DATABASE.md` la descripción de `requisitos` (son requisitos de uso) y anota los índices si se ejecutó HO-7.
- Fuera de alcance: código.

**Criterios de aceptación.**
- [ ] Cada criterio de 112-R sección 14 tiene su evidencia (issue y prueba o comprobación del QA).
- [ ] La guía existe y cubre los puntos del alcance.
- [ ] `docs/agents/DATABASE.md` corregido (es el único archivo versionado de esta issue).
- [ ] Sin rayas largas ni voseo; `sh tests/run_all.sh` en VERDE.

**Pruebas esperadas.** Ninguna nueva.

**Depende de.** Todas las anteriores.

**Para el usuario.** Leer la guía y pasarla al staff.

**Plan de reversión.** No aplica.

## 4. Pasos acoplados para el usuario y orden de despliegue

### 4.1 Por issue

| Issue | Plantillas | SQL | Archivos especiales | Otros pasos | ¿Retener el despliegue? |
|---|---|---|---|---|---|
| HO-1 | No | No | No | Calibración de solo lectura recomendada antes de empezar (214-DP 13.4) | No (inerte) |
| HO-2 | No | No | No | Probar un guardado y avisar al staff; el Orchestrator anota la fecha y hora del despliegue (fecha de `OPG_OBJETOS_CAMBIO_INICIO`) | No, pero se despliega con HO-1 y se avisa al usuario de que empieza el registro |
| HO-3 | No | No | No | Desplegar con HO-2 o después | No |
| #33 | No | No | `objetos_crear.php` se sustituye, **no se borra** | Avisar al staff | No |
| HO-4 | `staff_objetos_modificar.html` | No | No | PHP primero, plantilla después | **Sí** |
| HO-5, HO-6, HO-8 | No | No | No | La librería `objetos_cambios.php` está en uso por el editor del staff: cada despliegue suyo afecta al staff | No (recomendado desplegarlas juntas con HO-9 salvo `objetos_cambios.php`, que puede ir antes) |
| HO-7 | No | `docs/214_historial_objetos_indices.sql` | No | Fuera de la hora punta; índice 2 opcional | **Sí** (lo ejecuta el usuario) |
| HO-9 | `op_historial_objetos.html` | No | No | Plantilla primero, PHP después; requiere #34 desplegada y la política confirmada; capturas | **Sí** |
| HO-10 | `op_objetos.html`, `op_historial_tecnicas.html` | No | No | Solo con la página ya publicada y la #34 desplegada | **Sí** |
| HO-11 | No | No | No | Ninguno | No hay despliegue |

### 4.2 Orden de despliegue en producción

1. **HO-1 + HO-2** juntos. Desde este momento se acumula historial. Anotar fecha y hora.
2. **HO-3**.
3. **#33** (en cuanto el usuario decida D-13; puede ir antes que los pasos 1 y 2).
4. **HO-4**: PHP, después la plantilla `staff_objetos_modificar.html`.
5. **HO-7**: el usuario ejecuta la migración de índices fuera de la hora punta (cualquier momento antes del paso 7).
6. **#34** desplegada (con sus propios pasos).
7. **HO-9** (con HO-5, HO-6 y HO-8): plantilla `op_historial_objetos.html`, después PHP y librerías. Comprobación como visitante, usuario y staff.
8. **HO-10**: las dos plantillas.

Fechas: no se fijan aquí. Entre el paso 1 y el paso 7 conviene dejar al menos unas semanas: así la página nace con entradas estructuradas reales en el periodo por defecto de 90 días, y no solo con registros antiguos (que con 90 días ni siquiera aparecen).

## 5. Dependencias externas

### 5.1 Issue #34: filtrar en el servidor los datos de objetos que se envían al navegador

- **Bloquea publicar HO-9** (requisito 80 de 112-R) y **bloquea HO-10** (las dos modifican `op_objetos.html`, y la #34 también `op/objetos.php`).
- **No bloquea** el desarrollo de HO-1 a HO-9: el registro y la página se construyen mientras tanto.
- Propuesta del PM para que la #34 no espere a U-1: que el servidor envíe exactamente lo que el catálogo ya enseña hoy en pantalla (solo objetos con `invisible=0` y sin las columnas `crafteo_usuarios`, `negro`, `negro_berries`, `fusion_tipo` ni `engastes`), y que los JSON públicos de armas apliquen el mismo filtro o pidan sesión. La interfaz del catálogo no cambia. Así la decisión U-5 queda en B (issue aparte) sin depender de cuántos invisibles se publiquen en el historial.
- Puede ir en paralelo con las olas 2 y 3 (no comparte archivos con HO-4, HO-6 ni HO-8). Conviene empezarla pronto: es la que más probablemente retrase la publicación.
- Al hacer grooming de la #34, el PM le añade "Interfaz: no" (la interfaz no cambia) y la referencia a esta sección.

### 5.2 Issue #33: retirar o endurecer `objetos_crear.php` (decisión D-13)

- Se reorienta como se describe en la sección 3 (redirección con D-13 A). Corrige además una nota de la issue actual: con la redirección no hay que borrar nada en el servidor.
- Con D-13 A: independiente, se puede hacer desde la ola 0; HO-4 depende de ella.
- Con D-13 B: depende de HO-1, toca `staff_objetos_crear.html` (Interfaz: sí, plantilla acoplada a la vez que el PHP) y HO-4 desaparece.
- Hasta que se cierre, los cambios hechos por esa página no quedan registrados. Como ya no está enlazada desde la consola del staff, el hueco es pequeño, pero existe.

## 6. Consolidación de decisiones

Regla para las contradicciones: en reglas de juego manda el Game Designer, en aspecto visual el UI Designer y en cuestiones técnicas el Developer. Las decisiones D, U y V son formalmente del usuario; la columna "¿Usuario?" distingue entre **elegir** (hay alternativas con peso real), **confirmar** (basta aceptar la recomendación) y **no** (lo resuelve un rol).

### 6.1 Decisiones del usuario (D-1 a D-13, U-1 a U-5, V-1 a V-7)

| ID | Tema | Recomendación de origen | Propuesta del PM | ¿Usuario? | Bloquea | Plazo |
|---|---|---|---|---|---|---|
| D-1 | Qué se muestra públicamente | A (todo salvo `crafteo_usuarios`) | A, con las restricciones de campo del Game Designer (mercado negro, reservados) | Confirmar | Inicio de HO-6 (con C la página sería solo para staff) | Antes de HO-6 |
| D-2 | Copias de jugadores | A (fuera de la vista pública) | A para el público; el dueño, según U-4 | Confirmar | Publicar HO-9 | Antes de publicar |
| D-3 | Invisibles y exclusivos | A (ocultar invisibles, mostrar exclusivos) | **Consolidada con U-1.** La parte de `exclusivo` queda resuelta (no oculta nada); la de `invisible` es U-1 | No (se decide en U-1) | Ver U-1 | Ver U-1 |
| D-4 | Movimientos de inventario | A (fuera) | A | Confirmar | Nada (no hay nada que construir) | Cuando quiera |
| D-5 | Cambios hechos por jugadores | A (no se registran), con visto bueno del Game Designer | A (el Game Designer ya la respaldó, 112-GD 2.5) | Confirmar | Nada (con B o C sería otra funcionalidad) | Cuando quiera |
| D-6 | Precio y tier | B (sin mercado negro) | B ampliada del Game Designer: `berries`, `berriesCrafteo` y `tier` públicos; mercado negro por U-2 | Confirmar | Publicar HO-9 | Antes de publicar |
| D-7 | Copias en `Mis objetos` | A si las copias se ven afectadas | A con aviso: el código confirma que las copias no cambian, y la entrada lo dice | Confirmar | Publicar HO-9 (HO-5 lo construye igual) | Antes de publicar |
| D-8 | Vista ampliada del staff | A | A | Confirmar | Publicar HO-9 | Antes de publicar |
| D-9 | `crafteo_usuarios` | A (sin valores) | A con los textos exactos del Game Designer; el staff ve los UID (detalle técnico del Developer) | Confirmar | Publicar HO-9 | Antes de publicar |
| D-10 | Dónde se ve el objeto actual | A (ficha más ancla) | A | Confirmar | Nada (con C, HO-8 se simplifica) | Antes de HO-8, idealmente |
| D-11 | Dónde se guardan los registros | A (`audit_consola_mod`) | A (el diseño la adopta) | **Confirmar** | **Inicio de HO-1** (con B cambia la primera issue y hace falta una migración antes de registrar) | **Ya** |
| D-12 | Registros antiguos de modificación | A (mostrar como "Registro antiguo") | A | Confirmar | Publicar HO-9 | Antes de publicar |
| D-13 | `objetos_crear.php` | A (retirar) | A: redirección (no borrado) y retirada en el editor | **Elegir** | **Inicio de #33 y de HO-4** | Pronto (no bloquea HO-1 a HO-3) |
| U-1 | Invisibles (661, el 55 %) | B (solo fuera de circulación se ocultan) | B, tras ver la calibración 112-GD 10.1 y 10.2; si casi todos están fuera de circulación, A es más simple y da lo mismo | **Elegir** (con datos) | Publicar HO-9 (HO-6 prueba las tres opciones) | Antes de publicar |
| U-2 | Mercado negro (`negro`, `negro_berries`) | A (con acceso y staff) | A | **Elegir** | Publicar HO-9 | Antes de publicar |
| U-3 | Copias ya fabricadas | A (conservan sus valores; el historial lo dice) | A | Confirmar | Texto del aviso en HO-8 (no bloquea: es un texto) | Antes de HO-8, idealmente |
| U-4 | Cambios del staff en la copia de un jugador | C (los ve el dueño) | C | **Elegir** | Publicar HO-9 | Antes de publicar |
| U-5 | Fuga del catálogo | B (issue aparte antes de publicar) | B: ya existe la #34 y es dependencia de publicación (sección 5.1) | Confirmar | Publicar HO-9 | Ya está en marcha |
| V-1 | Reutilizar `ct-*` | B (copiar las usadas y tokenizar) | B; la hoja compartida (C) al backlog | Confirmar | Nada (se aplica B por defecto) | Antes de HO-9, idealmente |
| V-2 | Color de rareza | A (no usarlo) | A | Confirmar | Nada | Antes de HO-8, idealmente |
| V-3 | Chips de tipo en móvil | A (`flex-wrap`) | A | Confirmar | Nada | Antes de HO-9 |
| V-4 | Diferencia numérica | A (en gris) | A, con el porcentaje del Game Designer en los precios (C-3) | Confirmar | Nada | Antes de HO-8 |
| V-5 | Posición de la tarjeta en el catálogo | A (arriba) | A | Confirmar | Nada | Antes de HO-10 |
| V-6 | Chips como enlaces o como opciones | A (enlaces) | A | Confirmar | Nada | Antes de HO-9 |
| V-7 | Miniatura en cada entrada | A (sí) | A | Confirmar | Nada | Antes de HO-8 |

Además, **aprobar 112-R revisión 2** es requisito para crear las issues. Para no bloquear el registro, el PM propone aprobarla en dos tiempos: primero la **sección 9** (requisitos 35 a 45 y 64 a 68, que bastan para HO-1 a HO-4) y después el resto.

### 6.2 Contradicciones entre documentos (C-1 a C-15)

| # | Tema | Quién manda | Propuesta del PM (resuelve) | ¿Usuario? | Afecta a |
|---|---|---|---|---|---|
| C-1 | Visibilidad por estado actual (112-R) o por instantánea (112-GD) | Game Designer (regla de juego: "no adelantar") | Instantánea para los registros nuevos; estado actual solo para los antiguos. Ya corregido en 112-R requisito 3 | No (queda cubierta al aprobar 112-R revisión 2 y U-1) | HO-6 |
| C-2 | Grupos de campos (7 de 112-R, 9 de 112-GD; 214-UI usaba los 7) | Game Designer (nombres que entiende el jugador) | Los nueve de 112-GD. El UI Designer actualiza 214-UI 2.4 (filtro "Campo modificado") y 3.4 (orden de filas) | No | HO-1, HO-8, HO-9 |
| C-3 | Diferencia numérica (porcentaje de 112-GD, absoluta de 214-UI) | Contenido: Game Designer; forma: UI Designer | Porcentaje con signo en `berries`, `berriesCrafteo` y `negro_berries`; diferencia absoluta en las demás cifras; siempre en gris, sin color (V-4 A). Recogido en 112-R requisito 77 | No (V-4 sigue siendo del usuario) | HO-8 |
| C-4 | "Objeto publicado" (112-R, 214-UI) frente a "Nuevo en el catálogo" y "Deja de estar en el catálogo" (112-GD) | Texto y tipos: Game Designer; estilo: UI Designer; filtro: Developer | Textos y tipos de 112-GD. "Nuevo en el catálogo" usa la clase `ho-entrada--publicado` de 214-UI. El UI Designer diseña "Deja de estar en el catálogo" (clase, icono y color). En el filtro, "Nuevo en el catálogo" cuenta como `creado` y "Deja de estar" como `modificado` | No (necesita trabajo del UI Designer antes de HO-8) | HO-6, HO-8, HO-9 |
| C-5 | Sí/No directos (214-UI) frente a etiquetas que siguen al código (112-GD) | Game Designer (sigue al código) | 112-GD: `invisible` nunca como fila; `comerciable` como "Se puede intercambiar" invertido; `exclusivo` como "Se vende en la tienda". El UI Designer ajusta 214-UI 3.4. Recogido en 112-R requisito 75 | No | HO-1 (etiquetas), HO-8 |
| C-6 | Etiqueta del precio ("precio", "Valor en berries", "Berries") | Game Designer (texto para el jugador) | "Valor en berries" en la tabla de cambios; la ficha compacta puede abreviar a "Valor" (decisión visual) | No | HO-1, HO-8 |
| C-7 | Cuándo hay ficha y enlace al catálogo | Regla: Game Designer; presencia visual: UI Designer; condiciones: Developer | Ficha si el objeto es público para quien mira (o es staff o dueño); enlace al catálogo solo si `invisible=0`, no es Transportes y no está retirado. Recogido en 112-R requisito 18 | No | HO-8 |
| C-8 | Texto de la retirada ("Ya no se puede conseguir." pendiente en 214-UI) | Game Designer (afirmación de reglas) | Texto de 112-GD: "Este objeto ya no forma parte del catálogo." Se omite "Ya no se puede conseguir." | No | HO-8 |
| C-9 | Marcas que faltan en 214-UI ("Tienes una copia", "Lo tienes · no aparece en el catálogo", "Mercado negro", "Cambio del staff en tu objeto", bloque "Qué cambia para ti", aviso de copia) | Cuáles existen: Game Designer; cómo se ven: UI Designer | Existen las de 112-GD. El UI Designer las diseña antes de HO-8 (bloquea HO-8, no al usuario) | No | HO-8 |
| C-10 | Motivos de "Solo staff" (2 en 214-UI) | Developer (información técnica para el staff) | Los siete de 214-DP 7.7. Recogido en 112-R requisito 9 | No | HO-6, HO-8 |
| C-11 | `engastes` como campo largo (214-UI) | Developer (es `int(10)`) | Campo corto, solo staff | No | HO-1, HO-8 |
| C-12 | Título de la entrada (nombre en `h3` de 214-UI frente a "Ajuste de combate: Katana Kiku" de 112-GD) | Jerarquía visual: UI Designer; texto: Game Designer | Propuesta del Developer: insignia y `h3` con el nombre (214-UI) y el título de 112-GD como texto del tipo ("Ajuste de combate" en lugar de "Actualizado"). Lo confirma el UI Designer antes de HO-8 | No | HO-8 |
| C-13 | Cifras (1.304 / 14.936 frente a 1.204 / 15.036) | Datos medidos (Game Designer) | 1.204, 543, 661, 792 y 15.036. Corregido en 112-R | No | HO-6 (calibración) |
| C-14 | Tamaño táctil (40 px en 112-R, 44 px en 214-UI) | UI Designer | 44 px (cumple ambos). Corregido en 112-R requisito 53 | No | HO-8, HO-9 |
| C-15 | Recomendación sobre invisibles (D-3 A frente a U-1 B) | Game Designer (regla de juego) | U-1 B como recomendación; **la decisión sigue siendo del usuario**, con la calibración 112-GD 10.1 | **Sí (U-1)** | Publicar HO-9 |

### 6.3 Qué bloquea el inicio de cada issue

| Issue | Decisiones que deben estar tomadas antes de empezar | Lo que puede decidirse después (sin bloquear el desarrollo) |
|---|---|---|
| HO-1 | **D-11** confirmada; **sección 9 de 112-R** aprobada | Todo lo demás |
| HO-2 | Las de HO-1 (incluye los requisitos 64 a 66, que cambian mensajes del editor) | Todo lo demás |
| HO-3 | Las de HO-1 | Todo lo demás |
| #33 | **D-13** | |
| HO-4 | **D-13 = A**; criterios visuales del UI Designer | |
| HO-5 | Las de HO-1 | D-7, D-12 |
| HO-6 | **D-1** confirmada | U-1, U-2, U-4, D-2, D-6, D-7, D-8, D-9, D-12 (son valores de la política; deben estar antes de **publicar** HO-9) |
| HO-7 | Ninguna | Si se crea o no el índice de `mybb_op_objetos` (lo decide el usuario al ejecutar) |
| HO-8 | UI Designer: 214-UI actualizado con C-2, C-4, C-5, C-9 y C-12 y criterios visuales en la issue | D-10, U-3, V-2, V-4, V-7 (se aplica la recomendación) |
| HO-9 | Criterios visuales del UI Designer | V-1, V-3, V-6. **Para publicar:** #34 cerrada y desplegada; U-1, U-2, U-4, D-2, D-6 a D-9 y D-12 confirmadas |
| HO-10 | HO-9 publicada; #34 cerrada | V-5 (se aplica A) |
| HO-11 | Ninguna | |

## 7. Riesgos del plan y qué se mide en cada fase

### 7.1 Riesgos

| # | Riesgo | Probabilidad | Mitigación |
|---|---|---|---|
| R-1 | Las decisiones de visibilidad tardan y retrasan el registro | Media | Principio "registrar todo y decidir después"; aprobación de 112-R en dos tiempos (sección 9 primero); solo D-11 bloquea HO-1 |
| R-2 | El registro rompe el guardado del editor del staff (si falla el `INSERT` del registro, MyBB corta la petición) | Baja | Pruebas con MySQL real que reproducen la tabla de producción; prueba manual tras desplegar; reversión inmediata del commit |
| R-3 | Producción no se parece al volcado (motor InnoDB en lugar de MyISAM, `sql_mode` estricto, duplicados de `objeto_id`, textos más largos) | Media | Calibración de solo lectura de 214-DP 13.4 antes de HO-1; el diseño funciona con ambos motores |
| R-4 | Cada despliegue de `objetos_cambios.php` (HO-5, HO-6) afecta a una herramienta en uso | Media | Las pruebas de HO-1 deben seguir en verde en HO-5 y HO-6; `php -l` en la puerta; desplegar la librería solo tras QA |
| R-5 | Conflictos entre agentes por archivos compartidos | Media | Tabla de paralelismo (1.3); series obligatorias para la librería, el editor, la librería de vista y `op_objetos.html` |
| R-6 | Los agentes en worktree no tienen los documentos (`docs/` está ignorado) | Alta si no se avisa | Issues autosuficientes; el prompt incluye la ruta del checkout principal o el texto necesario; la migración se versiona con `git add -f` |
| R-7 | La #34 se retrasa y la página no se puede publicar | Media | El registro sigue acumulando; la #34 se lanza en la ola 2; propuesta de alcance acotado que no depende de U-1 (5.1) |
| R-8 | Fuga indirecta (recuentos, opciones de filtro, búsqueda, huecos en los anclajes) | Media | Pruebas de no fuga con centinelas en HO-8 y HO-9; recuentos sobre vistas filtradas; los huecos de `#cambio-ID` se aceptan (la tabla mezcla herramientas) |
| R-9 | Plantilla y PHP desplegados en mal orden (página en blanco, enlaces rotos) | Media | Orden de la sección 4.2 y retención del despliegue por el Orchestrator |
| R-10 | Publicación accidental por el staff (poner `invisible=0` para probar) deja entradas imborrables | Media | Guía del staff (HO-11); aviso al staff al desplegar HO-2 |
| R-11 | La ayuda invertida del formulario hace que el staff marque mal `comerciable` y `exclusivo`, y el historial lo hace visible | Media | El historial etiqueta según el código; corregir la ayuda en el backlog (sección 8) cuanto antes |
| R-12 | Fecha de inicio del registro equivocada en el aviso | Baja | Se toma del despliegue real de HO-2 anotado en la issue y se fija en HO-9 |
| R-13 | Tráfico de bots sobre la página pública | Baja | Índices de HO-7; caché para visitantes en otra issue si hace falta |
| R-14 | La #33 se ejecuta con su texto actual (borrar el archivo) | Media | Reorientarla antes de asignarla (sección 3); con borrado, el archivo seguiría vivo en el servidor |
| R-15 | La puerta con `--db` sale en rojo por las pruebas conocidas del Tablón | Alta | Usar `--db --area objetos` (issue #27) |

### 7.2 Qué se mide en cada fase

Las consultas en producción son de solo lectura, las ejecuta el usuario o el agente principal a petición expresa, y nunca un agente automatizado.

| Fase | Qué se mide | Cómo | Valor esperado |
|---|---|---|---|
| Antes de A | Motor de las tablas, `sql_mode`, duplicados de `objeto_id`, longitudes máximas, recuento `custom='0'` | Calibración de 214-DP 13.4 | Confirmar 1.204 y conocer duplicados |
| A (tras HO-2) | Registros `[CAMBIO_OBJETO]` por estado (`pendiente`, `confirmado`, `sin_cambios`) y por origen | `SELECT` agrupado sobre `mybb_op_audit_consola_mod` cada semana durante el primer mes | `pendiente` = 0 (cualquier `pendiente` se investiga); `sin_cambios` raro |
| A | Que ya no se escriben logs antiguos de `objetos_modificar.php` después de la fecha de despliegue | `COUNT` de `Objeto % (modificado por %` con `tiempo` posterior | 0 |
| A | Tamaño máximo del log nuevo | `MAX(LENGTH(log))` de los registros con marca | Muy por debajo de 60.000 bytes |
| A | Incidencias del staff con el editor | Comentarios del staff | Ninguna |
| B | Reconocimiento de los registros antiguos | Pruebas de HO-5; opcional, recuento de solo lectura en producción | 133, 49 y 248 reconocidos |
| B | Cobertura de reglas | Pruebas de HO-6 con E-1 a E-15 y las opciones de cada decisión | Todas en verde |
| B | Datos para U-1 | Calibración 112-GD 10.1 a 10.7 | Cuántos invisibles están en circulación |
| C | Consultas por visita y plan de ejecución | `DbShim` en las pruebas; `EXPLAIN` tras HO-7 | 6 o menos; `range` sobre `idx_hobj_log_tiempo` |
| C | No fuga | Pruebas de centinelas y revisión del QA del código fuente HTML como visitante | Ninguna centinela |
| C (tras publicar) | Errores y tiempo de respuesta | Registro de errores del servidor y prueba manual | Sin errores; respuesta comparable al historial de técnicas |
| C y D | Aspecto | Capturas del usuario (214-UI 10) | Visto bueno del usuario |

## 8. Backlog derivado (no se crea ahora)

Hallazgos de los cuatro documentos que no forman parte de esta funcionalidad. El PM los crea como issues aparte cuando el usuario apruebe los requisitos (o antes si alguno es de seguridad, como la #33 y la #34):

1. Corregir la ayuda invertida de `comerciable` y `exclusivo` en el formulario del staff (trivial, plantilla; conviene pronto, ver R-11).
2. `op/suriprueba.php` activo y sin escape de valores (seguridad; normal).
3. `op_objetos.html` no pinta la categoría Transportes (normal, plantilla).
4. La retirada no actualiza `mybb_op_inventario` (normal; decisión de reglas del Game Designer: qué pasa con los objetos retirados que tienen los jugadores).
5. Excepción de Akuma no Mi en `intercambio.php` que nunca se cumple (bug; reglas).
6. Mover las clases `ct-*` a `opg-components.css` (V-1 C; plantillas acopladas de los dos historiales).
7. Tokenizar los colores de rareza (V-2 C).
8. Índice por `uid` en `mybb_op_inventario`, si el `EXPLAIN` lo pide.
9. `mybb_op_cambioid` cambia ids sin registro.
10. Caché de la página para visitantes, si hay carga de bots.

## 9. Problemas detectados en el plan

1. **La #33 dice que hay que borrar el archivo del servidor**, y el diseño (y este plan) lo sustituyen por una redirección. Hay que reorientarla antes de asignarla.
2. **Los documentos de la funcionalidad no están en git.** Ningún agente en worktree los tiene. Afecta a cómo se lanzan los agentes, no al plan.
3. **214-UI está desfasado** respecto a 112-GD en grupos, etiquetas, marcas y tipos (C-2, C-4, C-5, C-9, C-12). El UI Designer debe actualizarlo antes de HO-8; es trabajo de rol, no del usuario.
4. **El desglose por categoría de 112-R no es fiable** (sumaba 1.304). No bloquea nada, pero las opciones del filtro de categoría salen de los datos y no de esa tabla.
5. **La librería del dominio está en uso por el staff desde HO-2**, y HO-5 y HO-6 la modifican después: cada despliegue de esas issues toca una herramienta en producción.
6. **Dos requisitos nuevos cambian el comportamiento visible del editor del staff** (64 y 66). Están en la sección 9 de 112-R: el usuario los aprueba al aprobar esa sección.
7. **Hasta cerrar la #33, `objetos_crear.php` puede cambiar el catálogo sin registro estructurado.** Hueco pequeño (no está enlazada), pero conviene hacer la #33 en la ola 0.
