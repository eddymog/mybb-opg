# Página Bélica (`op/belico.php`): plan de implementación

> Requisitos: [100_Requirements_Belico.md](100_Requirements_Belico.md)
>
> Diseño: [200_DesignPlan_Belico.md](200_DesignPlan_Belico.md)

## 1. Objetivo de este documento

Convierte el diseño en tareas ordenadas y verificables. Todo el trabajo es
un archivo nuevo, `op/belico.php`; cada tarea lo extiende y termina con el
archivo funcionando.

La implementación se considera terminada cuando:

- `op/belico.php` carga la ficha según `fid`/`tid` (snapshot del hilo o
  actual), dice cuál está usando, y muestra el título del hilo solo si el
  usuario puede ver ese foro;
- todos los cálculos de 100_Requirements §3.2-§3.4 dan resultado, en PHP, y
  se recalculan con htmx sin recargar la página;
- las fórmulas que ya existían en `calc.js` dan **los mismos números** que
  `calc.js` (sin regresiones), y las nuevas coinciden con los ejemplos
  numéricos de la Guía Bélica (§6.2);
- el registro de acciones se copia al post como un bloque BBCode;
- ningún otro archivo del sitio cambió.

## 2. Punto de partida

- `op/belico_data.php` — ya resuelve snapshot vs ficha actual, recursos
  máximos, técnicas e inventario. Se **copia** su lógica (no se incluye ni se
  modifica), cambiando `$uid` por `$fid`.
- `jscripts/belico/calc.js` — fórmulas de golpe básico, técnica, varias
  armas y estadísticas bélicas. Se **portan a PHP** respetando fórmula y
  redondeo.
- `op/punkrecords/guias/b01_guia_belica.md` — reglas nuevas, con número de
  línea en 100_Requirements §3.3.
- `jscripts/vendor/htmx-2.0.10.min.js` — ya vendorizado.

## 3. Archivos

### 3.1 Crear

| Archivo | Contenido |
|---|---|
| `op/belico.php` | Página completa + fragmentos htmx (200_DesignPlan §3) |

### 3.2 No se toca

- `op/belico_data.php`, `jscripts/belico/panel.js`, `jscripts/belico/calc.js`.
- Plantillas (`newreply`, `newthread`, ninguna otra). La página no usa
  plantillas, así que **no hay paso de `import_templates.php`**.
- `inc/`, `global.php`, `op/functions/op_functions.php`.

## 4. Convenciones

- **Sin plantilla, sin `eval()`.** Partes fijas en nowdoc (`<<<'CSS'`,
  `<<<'HTML'`, `<<<'JS'`); todo lo dinámico armado por funciones
  `belico_render_*` con `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')`. Nunca
  un valor interpolado dentro de un nowdoc.
- **Entradas:** todo `$mybb->get_input(..., MyBB::INPUT_INT)` o `(float)`;
  `tid`/`fid` siempre enteros. Ningún valor de usuario entra crudo a SQL.
- **htmx:** solo `hx-get` (la página no escribe nada, 200_DesignPlan §4.5).
  Cada bloque hace `hx-include="#belico-atributos"`.
- **Separación:** `belico_cargar_*` (consultas), `belico_calc_*` (números,
  sin HTML, sin `$db`), `belico_render_*` (HTML, sin cálculos). Así los
  `belico_calc_*` se pueden probar sueltos (§6.2).
- **Reglas:** ningún número de la Guía escrito fuera de `$BELICO_REGLAS`.
- Español neutro en todos los textos de la página.
- Diseño: `docs/style.md` — marco de tres fondos, `.barra-op`/
  `.barra-espacio-op`, `.af-field`, `.btn-op`, `.opg-chip`, variables
  `--opg-*`.
- `php -l op/belico.php` al final de cada tarea.

## 5. Tareas

### Tarea 1: esqueleto y ruteo

1. Bootstrap (200_DesignPlan §3).
2. `$BELICO_REGLAS` completo (200_DesignPlan §6), incluida la tabla de
   reacciones de 100_Requirements §3.4 **con el rango 21-30** (no 21-31).
3. `belico_es_htmx()` y el ruteo: con htmx, `switch ($accion)` que por ahora
   devuelve un aviso para todo; sin htmx, página completa con el marco de
   tres fondos vacío, `$headerinclude`/`$header`/`$footer`, y el `<script>` de
   htmx.
4. Acceso: invitado → aviso "Debes iniciar sesión para usar la
   calculadora." (también en peticiones htmx, antes de cualquier consulta).

**Verificación:** `php -l`. Abrir `op/belico.php` logueado (marco vacío) y
deslogueado (aviso). `curl -H 'HX-Request: true' '.../op/belico.php?accion=x'`
devuelve solo un fragmento, sin `<html>`.

### Tarea 2: carga de datos

1. `belico_cargar_ficha($db, $fid, $tid)` — snapshot (`mybb_op_thread_personaje`,
   con `pid` y `nombre`) o actual (`mybb_op_fichas`) (200_DesignPlan §4.2).
2. Atributos finales y recursos máximos copiados de `belico_data.php:43-80`,
   con `$fid` en las consultas de virtudes.
3. `belico_cargar_tecnicas($db, $fid)` y `belico_cargar_armas($db, $fid)`
   (`belico_data.php:82-108`, solo `categoria = 'armas'`, sin `LLST001`).
4. `belico_cargar_hilo($tid)` — `get_thread()` + `canview` del foro
   (200_DesignPlan §4.3).
5. Cabecera (200_DesignPlan §5.2): nombre, origen, enlace al post del
   snapshot, aviso de "sin estadísticas fijadas en este hilo", y el
   formulario GET para cambiar `tid`/`fid`.
6. Avisos de `fid` inexistente / sin ficha propia.

**Verificación:** con tu `fid` sin `tid` → "Estadísticas actuales"; con un
`tid` donde usaste `[ficha]` → "fijadas en *hilo*" y el enlace lleva a ese
post; con un `tid` donde no posteaste → actuales + aviso; con el `fid` de
otro usuario → su ficha; con un `fid` inventado → aviso. Comparar
vida/energía/haki máximos contra lo que muestra `[ficha]` en el foro para
el mismo personaje.

### Tarea 3: atributos y estadísticas bélicas

1. Formulario `#belico-atributos` (Base / Modificador / Final), con `tid` y
   `fid` ocultos.
2. `belico_calc_estadisticas($finales)` — portado de `calc.js`
   `_actualizarEstadisticas()`, mismas fórmulas y mismo redondeo
   (200_DesignPlan §5.5).
3. Bloque de estadísticas que se recalcula al cambiar un modificador
   (`hx-trigger` con `from:#belico-atributos`).

**Verificación:** con modificadores en 0, los números coinciden con los del
panel "BÉLICO" actual para la misma ficha (abrir los dos lado a lado).
Ejemplos de la Guía (§6.2).

### Tarea 4: ataque

1. Selectores de arma por espacio, "a dos manos" y atributo de TA por arma;
   agregar/quitar espacio con `accion=ataque&espacios=N`.
2. Técnica con filtro Todas/Activas/Mantenidas (portado de
   `_filtrarTecnicas()`).
3. `belico_calc_danio_arma()` (parseo `[XXXx1,5]` de `calc.js`
   `_calcularDanoArma()` con `preg_match_all`), `belico_calc_golpe_basico()`
   (1 arma 100%, 2 → 75%, 3+ → 60%, + dos manos ×1.5),
   `belico_calc_danio_tecnica()` (`[NxNivel]`), total (mismas reglas de
   ataque/defensa que `_actualizarDanoTotal()`), y
   `belico_calc_tasa_acierto()` (−10 / −5 por arma / la más baja / Agilidad
   sin arma).
4. Modo Ataque/Defensa.

**Verificación:** con el mismo personaje, armas y técnica, el golpe básico,
el daño de técnica y el total coinciden con el panel actual. Dos manos con
una sola arma: +50%. Dos armas: TA −10.

### Tarea 5: reacción

`belico_calc_reaccion($ref, $ta, $metros, $sorpresa, $bloques, $mantenida)`
siguiendo 200_DesignPlan §5.7, en ese orden. Devuelve la fila de la tabla y
los ajustes aplicados, para mostrarlos.

**Verificación:** casos límite de cada fila: diferencias +31, +30, +21, +20,
+11, +10, 0, −1, −15, −16, −30, −31, −45, −46, −60, −61, −75, −76.
Distancia 40 m → TA −10. Sorpresa sin detectar → "Sin reacción" sin
importar los Reflejos. Mantenida: con diferencia previa −45 se suma, con
−46 no.

### Tarea 6: daño recibido

`belico_calc_danio_recibido()` (200_DesignPlan §5.8): mitigación por tipo,
perforante, dos tipos, verdadero, regla de los efectos (`ceil(daño / 3)`,
no `× 0.33`), efectos por tipo
(niveles por cada 300/200/250, amputación ≥50%, fractura/vértigo ≥40%),
concentración y umbral de dolor.

**Verificación:** ejemplos de la Guía (§6.2) y casos propios: 200 de daño
con 0 de mitigación → efectos sí; con 134 mitigados (66 recibidos) → no;
600 cortante → 2 niveles de hemorragia.

### Tarea 7: recursos y utilidades

1. `accion=estado`: vida/energía/haki actuales → estado (Guía 412-432).
2. Caída, asfixia, lanzamiento, agarre, iniciativa (200_DesignPlan §5.9).

**Verificación:** ejemplos de la Guía (§6.2). Vida en 0 → derrotado pero
consciente; en −(VOL+RES)−1 → inconsciente; en −3×(VOL+RES)−1 → muerte.

### Tarea 8: registro y copiar al post

1. `accion=registrar` devuelve una fila `.belico-fila` con el texto y un
   `data-bbcode`, agregada con `hx-swap="beforeend"`.
2. Quitar fila con `hx-on:click`.
3. JS de copiar (nowdoc, unas 10 líneas): arma
   `[spoiler=Cálculos bélicos]...[/spoiler]` y usa
   `navigator.clipboard.writeText`, con `<textarea>` de respaldo.

**Verificación:** registrar tres acciones, quitar la del medio, copiar,
pegar en un post de prueba y previsualizar: el spoiler se ve bien.

### Tarea 9: estilos y mobile

CSS en nowdoc: marco con `max-width: 1100px`, bloques `.barra-op`, filas
flex de `.af-field`, resultados destacados con `.opg-chip`. Breakpoint
≤700px apila todo en una columna.

**Verificación:** recorrer la página en escritorio y en ≤700px (devtools).

## 6. Pruebas

### 6.1 Comprobaciones estáticas

- `php -l op/belico.php`.
- `grep` de `$db->query` en el archivo: toda variable dentro de una
  consulta es un entero ya forzado (`$fid`, `$tid`).
- `grep` de `echo`/concatenaciones con datos de la base: todas pasan por
  `htmlspecialchars`.
- `git status` muestra un solo archivo nuevo y nada modificado.

### 6.2 Ejemplos numéricos de la Guía

La propia Guía Bélica trae ejemplos con resultado — sirven como casos de
prueba directos para los `belico_calc_*`:

| Regla | Entrada | Resultado esperado | Guía |
|---|---|---|---|
| Movimiento | AGI 15, RES 15 | 11 m | 335 |
| Salto | FUE 30, AGI 30 | 13 m | 341 |
| Trepar | AGI 30, DES 30 | 7 m/s | 345 |
| Nadar | Movimiento 10 | 5 m | 349 |
| Límite de caída | FUE 50, RES 50 | 27 m | 374 |
| Regla de los Efectos | Técnica de 200 | Hace falta recibir 67 o más | 317 |
| Daño con dos tipos | 80 cortante + 20 fuego | 50 cortante + 50 fuego | 238 |
| Iniciativa | Atributo más bajo: A 3, B 5 | Gana B (empieza) | 52 |

Además, `docs/punkrecords_conjunto_pruebas.md:109-171` tiene preguntas y
respuestas sobre la Guía Bélica que sirven para revisar casos de reglas.

### 6.3 Con el navegador

| # | Caso | Esperado |
|---|---|---|
| 1 | `op/belico.php` sin parámetros | Tu ficha actual |
| 2 | `?tid=X` de un hilo con tu `[ficha]` | Estadísticas fijadas + enlace al post |
| 3 | `?tid=X` sin tu `[ficha]` | Actuales + aviso |
| 4 | `?fid=Y` de otro usuario | Su ficha |
| 5 | `?tid=X` de un foro sin permiso de lectura | "hilo #X", sin título |
| 6 | Deslogueado | Aviso de iniciar sesión |
| 7 | Cambiar un modificador | Todos los resultados se actualizan sin recargar |
| 8 | Ataque, reacción, daño, utilidades | Resultados de §6.2 y de cada tarea |
| 9 | Registrar, quitar, copiar | Spoiler BBCode correcto en un post de prueba |
| 10 | ≤700px | Una columna, todo usable |
| 11 | Panel "BÉLICO" en `newreply.php` | Sigue igual que antes |

## 7. Despliegue

1. Subir `op/belico.php`. No hay plantillas que importar ni archivos
   estáticos nuevos.
2. Probar con la matriz de §6.3 en producción con una ficha real.

### Rollback

Borrar `op/belico.php`. Nada más depende de él (el panel viejo sigue usando
`belico_data.php`).

## 8. Orden recomendado de commits

1. `docs: requisitos, diseño e implementación de la página bélica`.
2. `feat: op/belico.php — carga de ficha por tid/fid y estadísticas bélicas`
   (Tareas 1-3).
3. `feat: belico.php — ataque, reacción y daño recibido` (Tareas 4-6).
4. `feat: belico.php — utilidades, registro y estilos` (Tareas 7-9).

No commit sin que el usuario lo pida.

## 9. Fuera de alcance

- Reemplazar el panel "BÉLICO" de `newreply`/`newthread` por un enlace a
  `belico.php?tid=X&fid=X` — decidido para más adelante (100_Requirements §2).
- Identidades secretas (`[fichasecreta]`/`[personajesecreto]`) — decisión
  explícita del usuario.
- Elegir una reacción peor que la que da la tabla (100_Requirements §3.4,
  nota 3).
- Guardar el registro de acciones entre recargas.
- Precargar el atributo de TA y las manos de cada arma — pendiente de saber
  dónde está ese dato (200_DesignPlan §8).
