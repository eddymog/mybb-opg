# Turno bélico (`op/belico.php`, tab "Turno"): plan de diseño

> Requisitos: [104_Requirements_TurnoBelico.md](104_Requirements_TurnoBelico.md).
> Base: [203_DesignPlan_BelicoMejoras.md](203_DesignPlan_BelicoMejoras.md).

## 1. Idea general

El turno es un **objeto de estado en el navegador**. Cada cambio envía el turno completo a PHP
(`accion=turno`), que devuelve el turno **calculado**: resultados por bloque, acumulados, avisos y el texto para
copiar. El servidor es una función pura (no guarda nada) y la librería sigue siendo la única fuente de cálculo.
No se portan cálculos a JavaScript.

## 2. Estado del turno (JSON en el navegador)

Los `tid` y números de este documento son de ejemplo.

```json
{
  "version": 1,
  "nombre": "Turno 3",
  "equipo": { "configuracion": "una_mano", "armas": ["ACF003"] },
  "pasivas": ["DCO003", "DCZ002"],
  "recursos": { "vida": 500, "energia": 120, "haki": 50 },
  "mantenidas_continuan": ["BUSO301", "PIR301"],
  "instantaneas_inicio": ["KENB201"],
  "reacciones": [ { "ta_rival": 85, "no_cortado": true, "acciones": [] } ],
  "bloques": [
    {
      "movimiento_previo": 20,
      "pasos": [
        { "tipo": "tecnica", "tid": "DSA101", "rol": "utilidad", "aplicar_bonos": true },
        { "tipo": "tecnica", "tid": "DEP201", "rol": "principal" },
        { "tipo": "instantanea", "tid": "BUSO101" },
        { "tipo": "accion", "accion": "golpe" }
      ],
      "movimiento_posterior": 0,
      "pasivas_mas": [], "pasivas_menos": ["DCZ002"],
      "detalle": "Si se mueve, lo sigo."
    }
  ]
}
```

- El **tipo de bloque no se guarda**: se deduce de los pasos (principal = bloque con técnica; solo acciones = sin
  técnica; solo utilidades/mantenidas = utilidad). Así el jugador nunca elige un tipo abstracto.
- Clave de guardado: `opg_belico_turno_{uid}_{fid}`.

## 2.0 Modelo de bloque con huecos (reemplaza a la lista libre de `pasos`)

En el navegador cada bloque guarda solo lo que su tipo permite:
```json
{ "tipo": "tecnica | sin_tecnica | utilidad", "principal": "DEP201", "complemento": {"t":"tecnica","tid":"DSA101"},
  "acciones": ["golpe", ""], "utilidades": ["", ""], "bloqueo": false, "aplicar_bonos": true,
  "instantaneas": ["BUSO101"], "mov_previo": 20, "mov_posterior": 0, "pasivas_mas": [], "pasivas_menos": [], "detalle": "" }
```
Al enviar, `belicoTurnoPasosDe()` lo convierte en la lista `pasos` que ya entiende el servidor (instantáneas, complemento de utilidad, principal,
acción adicional). El servidor no cambia y sigue avisando de cualquier violación. Un turno guardado con el formato anterior se descarta al cargar.
El selector de tipo está en la cabecera de cada bloque; los botones rápidos son: Bloque con técnica, Bloque sin técnica, Bloque de utilidad,
Reaccionar al turno anterior, Golpe básico y Moverse.

## 2.1 Cambios respecto al borrador (implementación de la fase 1)

- **Equipo propio del tab Turno:** configuración y hasta tres armas (campos `belico-turno-config` y `belico-turno_arma_*`).
  Las **pasivas del turno** son las marcadas en el tab Pasivas; el tab Turno las muestra como chips y tiene el botón "Elegir pasivas".
  Ataque conserva su propio formulario (calculadora rápida) y no lee el equipo del turno.
- **Pasivas por bloque** ya están en la fase 1: cada pasiva del turno se puede quitar solo en un bloque (`pasivas_menos`) y se
  pueden añadir otras solo en ese bloque (`pasivas_mas`). Las que tienen condición de arma se descartan si no aplican al equipo.
- **El servidor devuelve JSON**, no HTML: `inicio_html`, `bloques[{tipo, html, avisos}]`, `avisos`, `resumen_html`, `bbcode`. El navegador reparte cada
  parte en su sitio.
- **Pestañas:** Turno es la primera y la activa por defecto; Ataque, Pasivas, Reacción y Utilidades siguen. Se eliminó el Constructor anterior
  (`belico_render_bloque_registro` y su JS).
- **Nombre del turno** sin `[ ] =` y detalle de bloque sin corchetes, para no romper el BBCode.
- **Reacciones al turno anterior (fase 2, hecha):** son bloques normales marcados `anterior: true` con un objeto `reaccion`
  (`ta`, `distancia`, `sorpresa`, `ref_extra`, `corto`). Van siempre antes de los bloques de este turno (aviso si no) y se numeran
  aparte ("Reacción 1 (turno anterior)"). El servidor calcula la reacción con `belico_calc_reaccion()`: Reflejos del personaje más bono
  de pasivas y extra, más +5 por cada reacción anterior cuya cadena no se cortó (`corto: true` reinicia el contador). Dentro de la
  reacción se pueden poner pasos (utilidad, bloqueo...) como en cualquier bloque.
- **Sin nombre de turno:** el spoiler se llama siempre "Turno".
- **Bloques interrumpidos (hecho):** campo `interrumpido`. El bloque se muestra, pero no cuenta recursos, movimiento, acciones adicionales,
  mantenidas ni enfriamientos (el servidor restaura el estado que tenía al empezar el bloque) y el texto lo marca "(interrumpido)".
- **Pasar al siguiente turno (hecho):** la respuesta incluye `siguiente` (`continuan`, `energia`, `haki`). El botón deja las mantenidas que
  continúan (más las nuevas), los recursos finales (mínimo 0) y vacía bloques e instantáneas; conserva equipo y acciones extra.
- **Ataque sigue al turno (hecho):** al cambiar la configuración o las armas en Turno, el formulario de Ataque se actualiza
  (`belicoAtaqueFijar`). Es de una sola dirección; cambiar Ataque no toca el turno.
- **Plantillas (hecho):** equipo, mantenidas, instantáneas, acciones extra y bloques con nombre en `localStorage`
  (`opg_belico_turno_plantillas_{uid}_{fid}`); no incluyen los recursos actuales.
- **Enfriamientos entre turnos (hecho):** el estado lleva `enfriamientos` (`tid` a turnos que faltan) y la respuesta incluye
  `siguiente.enfriamientos` con la regla de 104 §6.5.
- **Wazamono** cumplen cualquier condición de tipo de arma al evaluar pasivas y requisitos de técnica.
- (nota histórica) Antes no había arrastre de enfriamientos entre turnos (pendiente de definir cuánto dura "enfriamiento N" tras usar la técnica).

## 3. Servidor: `accion=turno`

- Petición **POST** con el JSON (puede ser largo para GET). Sin efectos en la base, así que no necesita `my_post_key`;
  se valida y se limita el tamaño (por ejemplo 64 KB y 30 bloques).
- Respuesta: fragmento HTML del turno calculado más el texto BBCode en un `<textarea>` o en `data-` (para la vista previa).
- Pasos del cálculo (`belico_calcular_turno($estado, $ficha, $tecnicas, $armas, $atributos, $recursos)`):
  1. Normalizar el estado: ids desconocidos fuera, números acotados.
  2. Equipo: `opg_belico_seleccionar_armas()`, golpe, bloqueo y TA (como Ataque).
  3. Recursos iniciales.
  4. Mantenidas que continúan: restar **coste por turno**.
  5. Instantáneas de inicio: restar su coste (si es mantenida nueva, primer coste).
  6. Por cada bloque, en orden: pasivas efectivas (turno ± bloque), bonos (`opg_belico_extraer_bonos`), daño de la
     principal (`opg_belico_calcular_efectos_contextuales`) más bonos de la utilidad si `aplicar_bonos`, costes
     (activa: `energia`/`haki`; mantenida **nueva**: primer coste; instantánea: igual que su clase), recursos restantes,
     movimiento acumulado, acciones adicionales usadas, tipo de bloque deducido y avisos.
  7. Resumen y texto BBCode.
- **Coste de mantenida nueva sin primer coste en la ficha:** se usa el coste por turno y se avisa (104 §6.1).
- **Activa con solo coste por turno** (104 §6.2): se ignora; una activa solo cobra `energia` y `haki`.
- **Bloque interrumpido** (fase 3): campo `interrumpido: true` en el bloque; sus costes no se restan y el texto lo indica.
- **Recursos por debajo de cero:** solo aviso; el cálculo sigue.

## 4. Detección de técnicas

- **Instantánea:** `preg_match('/^(BUSO|KENB|HAO)/i', $tid)` o `tid` en `AER301, AQU301, CRY301, ELE301, PIR301`.
  Función `opg_belico_es_instantanea($tecnica)` en la librería.
- **Rol:** principal = activa ofensiva/defensiva/ambiental; utilidad = utilidad activa o mantenida (las funciones JS
  `belicoEsPrincipal` / `belicoEsUtilidad` se pasan a PHP, `belico_rol_tecnica()`).
- **Mantenida:** `clase` contiene "Mantenida".

## 5. Reglas como avisos (`belico_avisos_bloque`)

| Regla | Aviso |
|---|---|
| Más de una principal en un bloque | "Un bloque con técnica admite un único ataque o defensa" |
| Principal + más de una utilidad o acción | "Solo una utilidad o acción adicional puede acompañar al ataque" |
| Más de dos acciones sin técnica | "Hasta dos acciones por bloque sin técnica" |
| Más de dos utilidades o bloqueo extra | "Hasta dos utilidades y un bloqueo básico" |
| Acciones adicionales > límite | "Usas N de M acciones adicionales" |
| Movimiento total > máximo | "Movimiento N de M m" |
| Recurso < 0 | "No te alcanza la energía en el bloque N" |
| Técnica repetida con enfriamiento > 0 | "X está en enfriamiento desde el bloque N" |
| Avisos de Ataque | arma, requisitos, amuletos, requisito de arma de la técnica |

## 6. Interfaz

- Botones rápidos: "Atacar con técnica", "Golpe básico", "Bloqueo básico", "Moverse", "Consumible", "Activar
  mantenida", "Activar utilidad", "Instantánea". Cada uno añade el paso al bloque actual o abre uno nuevo si ya no cabe.
- Selector de técnica con el mismo buscador y agrupación del campo Técnica de Ataque, filtrado por el rol del paso.
- Por bloque: pasos reordenables, número del bloque, tipo deducido, chips de pasivas (activas del turno, con X y "+"),
  resultado (daño, TA, coste) y recursos tras el bloque.
- Barra fija del turno: recursos actuales, movimiento y acciones adicionales usadas, y botón Copiar.
- Vista previa editable del BBCode y "Copiado ✓". Interruptor Sin cálculo / Con cálculo ya existente.

## 7. Texto que se copia (ejemplo)

```
[spoiler=Turno 3]
Equipo: Katana (una mano). Pasivas del turno: [tecnica=DCO003] (+27 de Daño)
Mantenidas que continúan: [tecnica=BUSO301] (-9 haki)
Instantánea: [tecnica=KENB201] (-12 haki)
[b]Bloque 1[/b] (técnica): Movimiento 20 m → [tecnica=DSA101] → [tecnica=DEP201]: 145 de daño cortante, TA 114. Coste: 35 de energía.
[b]Bloque 2[/b] (sin técnica): 2 golpes básicos (60 de daño cada uno).
Recursos tras el turno: Vida 500/500 · Energía 47/120 · Haki 29/50. Movimiento 20/60 m. Acciones adicionales 2/3.
[/spoiler]
```

## 8. Relación con los otros tabs
- **Ataque** lee el equipo del turno como valor inicial (puede cambiarlo para explorar).
- **Pasivas** marca las pasivas del turno (misma selección).
- El Constructor actual y su JS (`belicoCrearBloque`, etc.) se retiran al terminar la fase 1.
