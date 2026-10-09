# Mejoras de la Página Bélica (`op/belico.php`): plan de diseño

> Requisitos: [103_Requirements_BelicoMejoras.md](103_Requirements_BelicoMejoras.md).
> Base: [200_DesignPlan_Belico.md](200_DesignPlan_Belico.md).

## 1. Archivos

| Archivo | Cambio |
|---|---|
| `inc/plugins/lib/belico_calculos.php` | Librería compartida: selección de armas, TA, cálculo y sustitución de fórmulas, bonos |
| `op/belico.php` | Tab Ataque rehecho, campo Técnica, tab Pasivas, interruptor de cálculo, JS y CSS |
| `op/tecnicas_show.php` | Usa la librería para armas y para reemplazar fórmulas; sin cambios de pantalla |
| `op/armas_todas.php` | Nuevo. JSON de armas, mismo patrón que `op/tecnicas_belicas_todas.php` |

No se tocan plantillas, tablas ni `global.php`.

## 2. Librería (`belico_calculos.php`)

**Armas y TA**
- `opg_belico_seleccionar_armas($disponibles, $config, $ids)`: valida la configuración, respeta la cantidad que
  tiene el jugador de cada arma y cae a `sin_arma` si no hay ninguna válida. Devuelve `configuracion`,
  `solicitada` (la pedida, para que el selector no se reinicie), `armas` y `cantidad`.
- `opg_belico_calcular_tasa_acierto($armas, $atributos)`: lee `escalado` y `efecto` de cada arma; más de dos
  armas resta 5 por arma. `opg_belico_nombre_arma()` usa el apodo si está editado.

**Reconocimiento de fórmulas** (`opg_belico_calcular_efectos_contextuales`)
- Patrón de daño: `[+-]? token (+ token)*` seguido de `Daño` (hasta 2 palabras que no sean conectores),
  `Mitigación` o `Bloqueo`. El signo inicial va dentro de la fórmula.
- Patrón de efecto: signo, número o fórmula, `%` opcional y un nombre entre corchetes o una palabra. El nombre
  no puede empezar por conectores o artículos (`de del y o e el la los las que un una su sus`), puede terminar en
  `. , ; | )`, salto de línea o `<`, y el valor no puede ir precedido de "de " (es el nombre de otro efecto).
- `$formula_token`: con corchetes o sin ellos (`Nivel/2`). Un atributo solo sin operador no es fórmula; `[Nivel]`/`[NIV]` sí.
- `opg_belico_expresion_tiene_variable()`: una expresión solo se calcula si depende de Golpe/Bloqueo básico, Nivel o una
  fórmula de atributo con operador.

**Sustitución en texto**
- `opg_belico_sustituciones($resultados, ...)` devuelve, de más larga a más corta, `expresion`, `numero` (signo + valor), `etiqueta` y `pasos`.
- `opg_belico_formulas_sueltas($texto, ...)`: fórmulas con operador en cualquier parte (descripciones).
- `opg_belico_sustituciones_texto($texto, ...)`: une las dos anteriores (la suelta solo se añade si queda fuera de lo ya reemplazado).
- `opg_belico_aplicar_valores($texto, $sustituciones)`: texto plano con valores (para copiar).
- `opg_belico_detalle_resultado()`: etiqueta y pasos de un resultado; las unidades (metros, turnos) van sin "de".
- Los pasos usan nombres completos de atributo, muestran la suma y el redondeo solo si hay decimales.

**Bonos** (`opg_belico_extraer_bonos($texto, $atributos)`): bonos con signo y nombre claro. Devuelve `k` (clave sin tildes),
`l` (etiqueta), `v` (valor con signo) y `p` (porcentaje). `TA` se unifica con `Tasa de acierto`.

## 3. `belico.php`

**Rutas htmx (`accion=`)**: `ataque` (resultados), `ataque_form` (vuelve a dibujar el formulario al cambiar la configuración),
`pasivas` (vuelve a dibujar la lista con los atributos actuales y lo ya marcado, vía `pasivas[]`).

**Tab Ataque**
- Formulario con `contexto_arma`, `arma_uno|dos|tres` y `tecnica`. Resultados: tres casillas con `data-belico-tile`
  (`golpe`, `bloqueo`, `ta`) y `data-belico-valor`, y el bloque de técnica con efectos y descripción.
- Campo Técnica: `belico_render_campo_tecnica()` (servidor, agrupado por tier) más `belicoFiltrarTecnicas()` (cliente, reconstruye el
  selector conservando la elegida). Datos de cada técnica: `grupo` (`belico_grupo_tecnica`), `calcula` (`belico_tecnica_calcula`, mira efectos y descripción).

**Tipos de arma**
- `belico_tipos_por_subcategoria()`: mapa exacto de la subcategoría (`Armas de Filo` a `filo`, `Armas Defensivas` a `defensiva`,
  `Amuletos` a `amuleto`, `Musical` a `musical`, `Wazamono` a `wazamono`, etc.). Si no se reconoce, se buscan palabras clave
  en la subcategoría y el nombre (`belico_palabras_tipos_arma`). Las flexibles se deducen por nombre dentro de las exóticas.
- `belico_condicion_arma_pasiva()`: tipos que menciona la pasiva en su texto, incluidas las enumeraciones
  ("Armas de Tensión, de Fuego, de Asalto o Arrojadizas").

**Tab Pasivas**
- `belico_render_lista_pasivas()`: grupos por origen (`belico_origen_pasiva`), sin `Senda` (`belico_es_senda`). Cada tarjeta lleva:
  `data-buscar`, `data-bonos` (JSON), `data-valor`, `data-armas` y una casilla `name="pasivas[]"` con `data-belico-pasiva-origen` y
  `data-belico-pasiva-efecto` (texto con valores para copiar).
- `belico_condiciones_texto()`: etiquetas informativas (Vitalidad, Distancia, Movimiento previo, Estado del objetivo, Dura el resto del combate,
  Por turno, Acumulable con máximo, Dado). Se detectan por palabras clave; el mensaje de ayuda avisa de que hay que confirmar en la descripción.
- Estado en el cliente (`belico_js_ataque`): `belicoEvaluarArmasPasivas` pone `data-aplica` (`si`/`no`); `belicoBonosActivos` suma los
  bonos de lo marcado excluyendo `aplica="no"`; `belicoActualizarPasivas` repinta contador, chips, bonos, vista previa y las líneas
  de Ataque (`belicoAplicarPasivasAtaque`) y Reacción (`#belico-nota-reflejos`).

**`localStorage`** (la clave lleva `uid_fid`)

| Clave | Contenido |
|---|---|
| `opg_belico_vista` | `calculos` o `codigos` |
| `opg_belico_densidad` | `tarjetas` o `compacta` |
| `opg_belico_ataque_{uid}_{fid}` | configuración de armas y técnica |
| `opg_belico_pasivas_{uid}_{fid}` | pasivas marcadas |
| `opg_belico_pasivas_tipos_{uid}_{fid}` | tipos de arma marcados |
| `opg_belico_pasivas_conjuntos_{uid}_{fid}` | conjuntos guardados |

### 3.1 Ataque: resumen, avisos y armas (segunda pasada)

**Librería**
- `opg_belico_penalizacion_distancia($m)` y el parámetro `$distancia` de `opg_belico_calcular_tasa_acierto()` (-5 por cada 20 m, ya en `belico_reglas`).
- Los tipos de arma pasaron a la librería (`opg_belico_tipos_arma`, `opg_belico_etiquetas_tipos_arma`, `opg_belico_tipos_sin_bono_dos_manos`) porque el cálculo del
  bono a dos manos los necesita; `belico.php` conserva envoltorios con los nombres anteriores.
- `opg_belico_seleccionar_armas()` devuelve además `ignoradas` (repetidas sin copias).
- `opg_belico_requisitos_arma($arma, $atributos)` lee "tener N de [Atributo]" y compara; `opg_belico_avisos_armas($seleccion, $atributos)` arma la lista de avisos
  (`nivel` = `aviso` o `info`); `opg_belico_tipos_requeridos_tecnica($tecnica)` traduce disciplina o estilo al tipo de arma que exige
  (`null` = cualquiera o desconocido, `array()` = no usa armas).

**`belico.php`**
- `belico_render_resultado_ataque_html($mybb, $armas, $tecnicas, $atributos, $recursos)` ahora arma, en orden: avisos, tarjetas de armas
  (`belico_render_tarjetas_armas`), las tres casillas, el **resumen** (`#belico-resumen` con `data-base-dano`, `data-base-verdadero`, `data-base-ta`), el
  desglose paso a paso y el bloque de la técnica. Los recursos máximos llegan desde la ruta (`$recursos`).
- El resumen suma por tipo los resultados de daño de la técnica; el verdadero va aparte. Si la técnica no inflige daño calculable, usa el golpe básico.
- `belicoAplicarPasivasAtaque()` rellena en el navegador las celdas `[data-belico-resumen]` con el total "con pasivas" (daño, daño verdadero, TA) y mantiene
  la nota de Reflejos en Reacción. Las pasivas que no aplican con el arma no suman.
- `belicoActualizarLlevas()` pinta `#belico-llevas` leyendo el formulario de Ataque y la selección de Pasivas.
- El formulario de Ataque tiene el campo `distancia`; el resultado se actualiza con `change` o `input` (300 ms) sobre él.

## 4. Casos límite
- Configuración con armas pero sin ninguna válida: los resultados usan `sin_arma`, pero el selector sigue mostrando lo pedido.
- Arma sin `escalado`: TA = Agilidad, avisado en el desglose. Arma sin subcategoría reconocida: sin tipo (no se puede evaluar).
- Pasiva con condición de arma sin tipo elegido: `data-aplica="no"` y texto "(marca el tipo de arma arriba...)"; no suma bonos.
- Fórmula repetida o contenida en otra: se reemplaza por marcadores, de la más larga a la más corta.
- Cambiar atributos redibuja la lista: se conserva lo marcado (vía `pasivas[]`), se reevalúan las armas y se recalcula todo.
- Texto mal escrito (`[Nivel/5] -[Reflejos]`) no se calcula bien; es un problema del dato, no del cálculo.
