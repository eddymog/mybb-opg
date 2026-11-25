# Historial público de objetos: especificación visual

> Rol: UI Designer. Depende de: [112_Requirements_HistorialObjetos.md](112_Requirements_HistorialObjetos.md).
> Referencias: [212_DesignPlan_HistorialTecnicas.md](212_DesignPlan_HistorialTecnicas.md) §7, `templates/One_Piece_Gaiden_Templates/op_historial_tecnicas.html`, `op/historial_tecnicas.php`, `templates/One_Piece_Gaiden_Templates/op_tecnicas.html` (`.ht-cta`), `templates/One_Piece_Gaiden_Templates/op_objetos.html`, [style.md](style.md) §3, §5 y §7.
> Estado: borrador para el Developer. Las decisiones de la sección 11 necesitan el visto bueno del usuario; mientras no lo tengan, se implementa la opción recomendada.

Este documento dice **cómo se ve** `op/historial_objetos.php`. Qué se muestra (copias, invisibles, precio, `crafteo_usuarios`) lo deciden el usuario y el Game Designer en el documento 112 (D-1 a D-13); aquí solo se diseña el hueco de cada cosa.

---

## 1. Principio

1. **Misma familia que el historial de técnicas.** Un jugador que ya usó `historial_tecnicas.php` debe reconocer la página al instante: misma cabecera ciruela con franja naranja, mismo panel de filtros crema, misma línea de tiempo con encabezados de día, misma entrada con `details` y misma paginación.
2. **Se reutilizan las clases `ct-*`** (estructura y nombres) para todo lo que ya existe en técnicas: `ct-pagina`, `ct-cabecera*`, `ct-btn*`, `ct-filtros*`, `ct-campo`, `ct-busqueda`, `ct-filtros-avanzados*`, `ct-filtros-activos`, `ct-filtro-chip`, `ct-limpiar`, `ct-resultados-barra`, `ct-total`, `ct-controles`, `ct-control`, `ct-lista`, `ct-grupo-fecha`, `ct-entrada*`, `ct-accion`, `ct-meta`, `ct-campos`, `ct-chip`, `ct-chip--id`, `ct-fecha`, `ct-detalles*`, `ct-chevron`, `ct-diferencia`, `ct-comparacion`, `ct-valor*`, `ct-vacio`, `ct-nota-historica`, `ct-legacy`, `ct-entrada__pie`, `ct-sin-resultados`, `ct-paginacion`.
3. **Las clases `ct-*` viven inline en `op_historial_tecnicas.html`**, no en una hoja compartida. La plantilla nueva `op_historial_objetos.html` copia en su `<style>` solo las reglas `ct-*` que usa, **sustituyendo cada hex suelto por su token** según la tabla de la sección 7.2 (ver decisión V-1).
4. **Lo nuevo lleva prefijo `ho-`** y solo usa tokens `--opg-*` (más `#fff` y `rgba(0,0,0,.x)` para filetes, ver 7.3). Añadir `ho-` a la tabla "Prefijos por página" de `style.md` §7 queda como tarea del UI Designer al cerrar la issue.
5. **Componentes genéricos** donde ya existen: `.aviso.err` para el error de carga, `.opg-chip` para acciones secundarias dentro de la ficha del objeto, `.opg-vacio` no se usa (el estado vacío reutiliza `ct-sin-resultados`, que es su equivalente en esta familia).
6. **Sin JavaScript obligatorio.** Todo funciona con `GET` y elementos nativos (`details`, `select`, enlaces). El JS solo añade: expandir y contraer todos, abrir el `details` de la entrada enlazada por `#cambio-ID`, y el estado "Aplicando..." del botón.
7. Marco: `{$headerinclude}` → `.mainBackground` → `.secondBackground` → `main.thirdBackground`, con `width: 100%; max-width: 1120px` como en técnicas.

---

## 2. Maqueta por zonas

### 2.1 Escritorio (1120px de contenido)

```text
{$header}
┌─ .ct-cabecera (ciruela, franja inferior naranja 6px, sombra desplazada) ───────────────┐
│ CATÁLOGO DE OBJETOS (eyebrow naranja)                      [Catálogo] [Historial de    │
│ Historial de Objetos (h1, moonGetHeavy 31px blanco)                     técnicas]      │
│ Consulta qué objetos se añadieron, cambiaron o se retiraron. Desde el 02/12/2026 cada  │
│ cambio guarda el valor anterior y el nuevo de cada campo.                              │
└────────────────────────────────────────────────────────────────────────────────────────┘
┌─ form.ct-filtros (crema clara, borde 2px, radio md) ───────────────────────────────────┐
│ OBJETO                                  PERIODO        ORDEN                           │
│ [🔍 Nombre, ID actual o anterior...  ]  [90 días ▾]    [Más recientes ▾]  [Aplicar]    │
│                                                                                        │
│ TIPO DE CAMBIO                                                                         │
│ (Todos 132) (+ Nuevos 18) (✎ Actualizados 101) (⇄ Renombrados 4) (▣ Retirados 0)       │
│ (↺ Registros antiguos 9)                                                               │
│ ─────────────────────────────────────────────────────────────────────────────────────  │
│ ⚙ Filtros avanzados ▾                                                                  │
│   CATEGORÍA    SUBCATEGORÍA    TIER        CAMPO MODIFICADO                            │
│   [Todas ▾]    [Todas ▾]       [Todos ▾]   [Todos ▾]                                   │
│   RESPONSABLE  TIPO DE REGISTRO  RELACIÓN CON MI PERSONAJE (solo con sesión)           │
│   [Todos ▾]    [Todos ▾]         [Todos los objetos ▾]                                 │
│ [Categoría: Armas ✕] [Tier: 3 ✕] Limpiar todos                                         │
└────────────────────────────────────────────────────────────────────────────────────────┘
 Mostrando 1 a 25 de 132 cambios · 410 cambios en los últimos 90 días  [Expandir todos][Contraer todos]
════════════════════════════════════════════ (filete naranja 4px) ══════════════════════
 Hoy ────────────────────────────────────────────────────────────────────────────────────
┌─ article.ct-entrada.ho-entrada--modificacion#cambio-23511 ─────────────────────────────┐
┃ [img 56] (✎) ACTUALIZADO                                          24/11/2026         │
┃          Katana de Wano (h3)                                       10:32             │
┃          [KAT001] [Armas] [Espadas] [Tier 3] [✓ Lo tienes]                           │
┃          Berries · Daño · Descripción · +2                                           │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ ⇆ Ver cambios (5 campos)                                                          ▾   │
└────────────────────────────────────────────────────────────────────────────────────────┘
 Ayer ───────────────────────────────────────────────────────────────────────────────────
 ... más entradas ...
 [← Anterior]                       Página 1 de 6                        [Siguiente →]
{$footer}
```

`┃` = franja izquierda de 6px con el color del tipo de acción (ver 3.2).

### 2.2 Orden de las zonas

| # | Zona | Elemento | Notas |
|---|---|---|---|
| 1 | Cabecera | `header.ct-cabecera` | Eyebrow "Catálogo de objetos", `h1` "Historial de Objetos", párrafo con la fecha de inicio del registro detallado (`{$ho_fecha_inicio_registro}`). Enlaces a la derecha (ver 8.3). |
| 2 | Búsqueda y periodo | `.ct-filtros-principales` | Tres campos y botón: Objeto (`q`), Periodo (`periodo`), Orden (`orden`), `Aplicar`. La acción **sale del `select`** y pasa a los chips de la zona 3. Rejilla: `grid-template-columns: minmax(250px,2fr) repeat(2,minmax(140px,.8fr)) auto`. |
| 3 | Chips de tipo de cambio | `nav.ho-chips` dentro del formulario | Ver 2.3. |
| 4 | Filtros avanzados | `details.ct-filtros-avanzados` | Abierto (`open`) si algún filtro avanzado está activo (req. 25). Rejilla de 4 columnas. |
| 5 | Filtros activos | `.ct-filtros-activos` | Etiquetas `ct-filtro-chip` con `✕` y `Limpiar todos`, como técnicas. |
| 6 | Resumen | `.ct-resultados-barra` | `p.ct-total` + `ct-controles` (Expandir y Contraer todos, solo si hay resultados). `aria-live` no hace falta: la página recarga. |
| 7 | Línea de tiempo | `section.ct-lista` | `h2.ct-grupo-fecha` antes del primer cambio de cada día (`Hoy`, `Ayer`, `lunes 17/11/2026`) y `article.ct-entrada` por cambio. 25 por página. |
| 8 | Paginación | `nav.ct-paginacion` | Igual que técnicas. Solo se pinta si hay más de una página. |

### 2.3 Chips de tipo de cambio (`.ho-chips`)

Fila de enlaces que filtran por tipo, siempre visible bajo la búsqueda. Sustituye al `select` de acción.

| Chip | Icono FA | Parámetros del enlace | Activo cuando |
|---|---|---|---|
| Todos | `fa-layer-group` | quita `accion` y `formato` | `accion=todas` y `formato=todos` |
| Nuevos | `fa-plus` | `accion=creado` | `accion=creado` |
| Actualizados | `fa-pen` | `accion=modificado` | `accion=modificado` |
| Renombrados | `fa-arrow-right-arrow-left` | `accion=renombrado` | `accion=renombrado` |
| Retirados | `fa-box-archive` | `accion=retirado` | `accion=retirado` |
| Registros antiguos | `fa-clock-rotate-left` | `formato=legacy` (y quita `accion`) | `formato=legacy` |

Reglas:

- Cada chip es un `<a class="ho-chip-accion">` con la URL ya calculada que **conserva todos los demás filtros** y vuelve a la página 1 (req. 28). Se puede compartir.
- El formulario lleva `<input type="hidden" name="accion" value="{$ho_accion}">` para que `Aplicar` no pierda el tipo elegido.
- Junto al texto, el número de cambios de ese tipo en el periodo y con los demás filtros: `<span class="ho-chip-accion__n">18</span>`. Si es 0, el chip se pinta como `<span class="ho-chip-accion ho-chip-accion--vacia" aria-disabled="true">` (no es enlace).
- El chip activo lleva `aria-current="true"` y la clase `ho-chip-accion--activa`.
- Los cuatro chips de acción llevan su color de tipo (sección 3.2) **solo en el icono**, dentro de un círculo de 22px con borde de tinta; el texto siempre en `--opg-ciruela`.

```html
<nav class="ho-chips" aria-label="Filtrar por tipo de cambio">
  <span class="ho-chips__titulo" id="ho-chips-titulo">Tipo de cambio</span>
  <a class="ho-chip-accion ho-chip-accion--activa" aria-current="true" href="...">
    <span class="ho-insignia ho-insignia--todas"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
    Todos <span class="ho-chip-accion__n">132</span>
  </a>
  <a class="ho-chip-accion" href="...?accion=creado">
    <span class="ho-insignia ho-insignia--alta"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
    Nuevos <span class="ho-chip-accion__n">18</span>
  </a>
  ...
</nav>
```

Medidas de `.ho-chip-accion`: `display: inline-flex; align-items: center; gap: var(--opg-espacio-2); min-height: 44px; padding: 6px 14px 6px 8px; border: 2px solid var(--opg-tinta); border-radius: 999px; background: #fff; color: var(--opg-ciruela); font: 12px InterMedium, var(--opg-fuente-cuerpo); text-decoration: none; transition: background var(--opg-rapido), transform var(--opg-rapido)`. Enlaces con `a.ho-chip-accion:link, a.ho-chip-accion:visited` (style.md §7, "Enlaces").
Hover: `background: var(--opg-crema-dorada); transform: scale(1.05)`.
Activa: `background: var(--opg-ciruela); color: #fff; box-shadow: 2px 2px 0 var(--opg-tinta)`; el contador pasa a `color: #fff`.
Vacía: `border-color: var(--opg-gris-bloqueado); color: var(--opg-gris-texto); background: transparent; cursor: default`; sin hover.
Contador `.ho-chip-accion__n`: `font: 11px InterMedium; color: var(--opg-gris-texto); min-width: 20px; text-align: center`.
Contenedor `.ho-chips`: `display: flex; flex-wrap: wrap; align-items: center; gap: var(--opg-espacio-2); margin-top: var(--opg-espacio-3)`. Título `.ho-chips__titulo`: mismo estilo que `.ct-campo label` y `flex-basis: 100%`.

### 2.4 Filtros avanzados

| Campo | `name` | Contenido | Notas |
|---|---|---|---|
| Categoría | `categoria` | Todas, Armas, Materiales, Utensilios, Consumibles, Especiales, Estructuras, Transportes | Valores reales del catálogo (req. 24). |
| Subcategoría | `subcategoria` | Todas + `<optgroup label="Armas">...</optgroup>` por categoría | Sin JS no se puede encadenar con Categoría; los `optgroup` hacen la lista larga manejable. |
| Tier | `tier` | Todos, Tier 1 a Tier N | |
| Campo modificado | `campo` | Todos, Clasificación, Economía, Combate, Fabricación, Inventario, Presentación, Visibilidad | Nombres pendientes del PM y Game Designer (req. 23). |
| Responsable | `autor` | Todos + usernames del staff | Etiqueta "Responsable", como técnicas. |
| Tipo de registro | `formato` | Todos, Comparación, Registro antiguo | Sincronizado con el chip "Registros antiguos". |
| Relación con mi personaje | `alcance` | Todos los objetos, Mis objetos | Solo con sesión (`{$ho_campo_personal}`). |

Todos con `div.ct-campo > label[for] + select[id]`. Ids con prefijo `ho-` (`ho-categoria`, `ho-subcategoria`...).

---

## 3. La entrada del historial

### 3.1 Estructura común

```html
<article class="ct-entrada ho-entrada ho-entrada--modificacion" id="cambio-23511" aria-labelledby="cambio-23511-titulo">
  <header class="ct-entrada__cabecera">
    <div class="ho-cabecera">
      <span class="ho-miniatura">
        <img src="{imagen}" alt="" width="56" height="56" loading="lazy" decoding="async">
      </span>
      <div class="ho-cabecera__texto">
        <span class="ct-accion ho-accion">
          <span class="ho-insignia ho-insignia--modificacion"><i class="fa-solid fa-pen" aria-hidden="true"></i></span>
          Actualizado
        </span>
        <h3 id="cambio-23511-titulo">Katana de Wano</h3>
        <div class="ct-meta">
          <span class="ct-chip ct-chip--id">KAT001</span>
          <span class="ct-chip"><i class="fa-solid fa-khanda" aria-hidden="true"></i> Armas</span>
          <span class="ct-chip">Espadas</span>
          <span class="ct-chip">Tier 3</span>
          <span class="ct-chip ho-chip--tuyo"><i class="fa-solid fa-check" aria-hidden="true"></i> Lo tienes</span>
        </div>
        <div class="ct-campos" aria-label="Campos afectados"><span>Berries</span><span>Daño</span><span>Descripción</span><span>+2</span></div>
      </div>
    </div>
    <div class="ct-fecha"><strong><time datetime="2026-11-24T10:32">24/11/2026</time></strong><span>10:32</span></div>
  </header>
  <details class="ct-detalles">
    <summary>
      <span><i class="fa-solid fa-code-compare" aria-hidden="true"></i> Ver cambios (5 campos)</span>
      <i class="fa-solid fa-chevron-down ct-chevron" aria-hidden="true"></i>
    </summary>
    <div class="ct-detalles__cuerpo">
      <!-- 1. ficha compacta del objeto actual (3.3) -->
      <!-- 2. bloque propio del tipo (3.4 a 3.8) -->
      <footer class="ct-entrada__pie">
        <span>Actualizado por Gretta</span>
        <nav aria-label="Enlaces de este cambio">
          <a href="#cambio-23511"><i class="fa-solid fa-link" aria-hidden="true"></i> Enlace permanente</a>
          <a href="/op/staff/objetos_modificar.php?objeto_id=KAT001"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Editar (staff)</a>
        </nav>
      </footer>
    </div>
  </details>
</article>
```

Jerarquía visual, de más a menos peso:

1. **Nombre** (`h3`, moonGetHeavy 20px, `--opg-ciruela`, sin contorno porque el fondo es claro).
2. **Tipo de acción** (insignia de color + texto en mayúsculas 11px `--opg-ciruela`) y **franja izquierda** de 6px del mismo color.
3. **Miniatura** del objeto (56px).
4. Chips de identificación (`ct-meta`): ID (ciruela con texto blanco), categoría con su icono, subcategoría, tier, "Lo tienes", "Solo staff".
5. Campos afectados (`ct-campos`, Inter 10px gris, máximo 4 y "+N").
6. Fecha y hora (derecha, gris; el día en `--opg-marron`).

Reglas de la cabecera:

- `.ho-cabecera`: `display: flex; align-items: flex-start; gap: var(--opg-espacio-3); min-width: 0`.
- `.ho-cabecera__texto`: `min-width: 0` (evita que un nombre largo empuje la fecha fuera).
- `.ho-miniatura`: `flex: 0 0 56px; width: 56px; height: 56px; display: grid; place-items: center; border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-sm); background: var(--opg-crema); overflow: hidden`. La `img`: `width: 100%; height: 100%; object-fit: contain`.
- Sin imagen (campo vacío o error): el `span.ho-miniatura` lleva dentro el icono de la categoría (`<i class="fa-solid fa-khanda" aria-hidden="true">`, 22px, `--opg-marron`) en lugar de `img`. El Developer decide la imagen en PHP: `imagen_avatar` si existe; si no, la ruta de `/images/op/iconos/` que ya calcula `op_objetos.html`; si tampoco, el icono.
- `alt=""` en la miniatura: el nombre ya está en el `h3` justo al lado; repetirlo haría que el lector de pantalla lo diga dos veces.
- La franja: `.ho-entrada { border-left-width: 6px; border-left-color: var(--ho-acento); }`. Cada modificador solo define `--ho-acento` (3.2).
- El `summary` dice cuántos campos: "Ver cambios (5 campos)", "Ver valores iniciales (9 campos)", "Ver detalle del renombre", "Ver detalle de la retirada", "Ver registro antiguo". Nunca "Ver comparación" en un registro antiguo.
- Orden dentro de `ct-detalles__cuerpo`: ficha del objeto actual, bloque del tipo, pie. Así quien abre una entrada ve primero "cómo es ahora" y después "qué cambió".

### 3.2 Colores e iconos por tipo

El color nunca va solo: cada tipo tiene **icono, texto y color**. El texto del tipo va siempre en `--opg-ciruela` (los verdes y rojos del foro no pasan AA como texto pequeño, ver sección 6.2).

| Tipo | Clase de la entrada | `--ho-acento` | Insignia (`.ho-insignia--x`): fondo / icono | Icono FA | Texto del tipo |
|---|---|---|---|---|---|
| Alta | `ho-entrada--alta` | `var(--opg-verde-exito)` | `--opg-verde-exito` / `--opg-tinta` | `fa-plus` | Nuevo objeto |
| Modificación | `ho-entrada--modificacion` | `var(--opg-naranja)` | `--opg-naranja` / `--opg-tinta` | `fa-pen` | Actualizado |
| Renombrado | `ho-entrada--renombrado` | `var(--opg-azul)` | `--opg-azul` / `#fff` | `fa-arrow-right-arrow-left` | Renombrado |
| Retirada | `ho-entrada--retirada` | `var(--opg-rojo-error)` | `--opg-rojo-error` / `#fff` | `fa-box-archive` | Retirado |
| Registro antiguo | `ho-entrada--antiguo` | `var(--opg-marron)` | `--opg-crema-dorada` / `--opg-marron` | `fa-clock-rotate-left` | Registro antiguo · Alta o Registro antiguo · Actualización |
| Publicado (D-3) | `ho-entrada--publicado` | `var(--opg-morado-texto)` | `--opg-morado-texto` / `#fff` | `fa-eye` | Objeto publicado |
| Todos (solo chip) | (no aplica) | (no aplica) | `--opg-ciruela` / `#fff` | `fa-layer-group` | Todos |

`.ho-insignia`: `display: inline-grid; place-items: center; flex: 0 0 22px; width: 22px; height: 22px; border: 2px solid var(--opg-tinta); border-radius: 50%; font-size: 10px`. Dentro de `.ho-accion` (`display: inline-flex; align-items: center; gap: 6px; color: var(--opg-ciruela)`), sustituye al color verde fijo de `ct-accion--nueva`, que **no se copia**.

Marcas añadidas a una entrada de cualquier tipo:

| Marca | Clase | Aspecto | Texto |
|---|---|---|---|
| Lo tienes | `ct-chip ho-chip--tuyo` | `border-color: var(--opg-verde-exito); background: color-mix(in srgb, var(--opg-verde-exito) 15%, white); color: var(--opg-ciruela)` + `fa-check` | "Lo tienes" |
| Solo staff | `ct-chip ho-chip--staff` | `border-color: var(--opg-tinta); background: var(--opg-gris-bloqueado); color: #fff` + `fa-lock` | "Solo staff" (y el motivo en `title` y texto oculto: "objeto invisible" o "copia de jugador") |
| Entrada solo staff | `ho-entrada--solo-staff` | `border-style: dashed` en el borde general (la franja izquierda sigue sólida: `border-left-style: solid`) | (la marca anterior) |
| Registro antiguo en una entrada moderna | no aplica | | |

### 3.3 Ficha compacta del objeto actual

Primer bloque dentro de `ct-detalles__cuerpo`, solo si el objeto **existe y es visible** (o si quien mira es staff). No aparece en retiradas.

```text
┌ .ho-ficha ───────────────────────────────────────────────────────────────┐
│ ESTADO ACTUAL                                                            │
│ [img 72]  Katana de Wano                     [Ver en el catálogo ↗]      │
│           KAT001 · Armas · Espadas · Tier 3  [Editar (staff)]            │
│           Berries 1.500 · Espacios 2 · Daño 2d6 · Alcance Corto          │
└──────────────────────────────────────────────────────────────────────────┘
```

```html
<section class="ho-ficha" aria-label="Estado actual del objeto">
  <span class="ho-ficha__titulo">Estado actual</span>
  <div class="ho-ficha__cuerpo">
    <span class="ho-miniatura ho-miniatura--grande"><img src="..." alt="" width="72" height="72" loading="lazy"></span>
    <div class="ho-ficha__datos">
      <strong class="ho-ficha__nombre">Katana de Wano</strong>
      <span class="ho-ficha__clasificacion">KAT001 · Armas · Espadas · Tier 3</span>
      <dl class="ho-ficha__stats">
        <div><dt>Berries</dt><dd>1.500</dd></div>
        <div><dt>Espacios</dt><dd>2</dd></div>
        <div><dt>Daño</dt><dd>2d6</dd></div>
      </dl>
    </div>
    <div class="ho-ficha__enlaces">
      <a class="opg-chip" href="/op/objetos.php#KAT001"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Ver en el catálogo</a>
    </div>
  </div>
</section>
```

- `.ho-ficha`: `display: grid; gap: var(--opg-espacio-2); padding: var(--opg-espacio-3); border: 2px solid var(--opg-tinta); border-radius: var(--opg-radio-md); background: var(--opg-crema-clara)`. Sin sombra (es una banda interna, no una tarjeta flotante: 212 §7.4 "no se introducen tarjetas anidadas").
- `.ho-ficha__titulo`: Inter Medium 10px mayúsculas `--opg-ciruela` (igual que `.ct-campo label`).
- `.ho-ficha__cuerpo`: `display: flex; flex-wrap: wrap; align-items: flex-start; gap: var(--opg-espacio-3)`.
- `.ho-miniatura--grande`: 72px en lugar de 56px.
- `.ho-ficha__datos`: `flex: 1 1 220px; min-width: 0; display: grid; gap: 4px`.
- `.ho-ficha__nombre`: moonGetHeavy 16px `--opg-ciruela`, `overflow-wrap: anywhere`.
- `.ho-ficha__clasificacion`: Inter 12px `--opg-gris-texto`.
- `.ho-ficha__stats`: `display: flex; flex-wrap: wrap; gap: 4px 14px; margin: 0`. `dt`: Inter Medium 10px mayúsculas `--opg-gris-texto`, en línea con su `dd` (cada par en un `div` con `display: flex; gap: 4px; align-items: baseline`). `dd`: Inter Medium 12px `--opg-marron`, `margin: 0`.
- Campos de la ficha, en este orden y **solo si tienen valor**: Berries (con separador de miles `1.500`), Espacios, Daño, Bloqueo, Alcance. Nunca descripción, efecto, requisitos ni escalado (son textos largos con HTML guardado). Cada valor en texto plano, sin etiquetas y escapado, cortado a 60 caracteres con "...".
- `.ho-ficha__enlaces`: `display: flex; flex-wrap: wrap; gap: var(--opg-espacio-2); align-items: flex-start`. Enlaces `.opg-chip` con `min-height: 44px` en esta página (override en el bloque 2 del `<style>`, "componentes compartidos ajustados"). Staff: segundo chip "Editar (staff)" a `objetos_modificar.php?objeto_id=ID`.
- Si el objeto ya no existe o no es visible (y quien mira no es staff): en lugar de la ficha, `<p class="ho-nota"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Este objeto ya no está en el catálogo público.</p>`.
- Categoría Transportes: `op_objetos.html` no tiene contenedor para esa categoría, así que el objeto no se pinta en el catálogo. Mientras no se corrija, la ficha de un transporte **no** muestra "Ver en el catálogo" (ver el hallazgo al final de la sección 10).

### 3.4 Modificación (formato moderno): comparación Antes y Ahora

Dos piezas, según la longitud del campo:

**a) Campos cortos** (números, enumeraciones, sí/no, ids, nombre): una tabla real.

```html
<table class="ho-tabla">
  <caption class="ho-oculto">Cambios de Katana de Wano</caption>
  <thead><tr><th scope="col">Campo</th><th scope="col">Antes</th><th scope="col">Ahora</th></tr></thead>
  <tbody>
    <tr>
      <th scope="row">Berries</th>
      <td><span class="ho-tabla__etiqueta">Antes</span><span class="ct-valor ct-valor--antes">1.200</span></td>
      <td><span class="ho-tabla__etiqueta">Ahora</span><span class="ct-valor ct-valor--nuevo">1.500 <span class="ho-delta">(+300)</span></span></td>
    </tr>
    <tr>
      <th scope="row">Quién puede fabricarlo</th>
      <td colspan="2"><span class="ho-privado"><i class="fa-solid fa-lock" aria-hidden="true"></i> Cambió quién puede fabricarlo</span></td>
    </tr>
  </tbody>
</table>
```

- `.ho-tabla`: `width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0 6px; font: 12px/1.5 var(--opg-fuente-cuerpo)`. Columnas con `<colgroup>`: 28% / 36% / 36%.
- `thead th`: Inter Medium 9px mayúsculas `--opg-gris-texto`, `text-align: left`, `padding: 0 8px`.
- `tbody th[scope=row]`: Inter Medium 11px mayúsculas `--opg-ciruela`, `padding: 8px; vertical-align: top; overflow-wrap: anywhere`.
- `td`: `padding: 0 4px; vertical-align: top; min-width: 0`. Dentro, `.ct-valor` con `display: block` (copiado de técnicas: franja izquierda de 4px y fondo suave).
- `.ho-tabla__etiqueta`: en escritorio, oculta con la misma receta que `.ho-oculto` (solo para lectores de pantalla); en móvil se hace visible (sección 5).
- `.ct-valor--antes` y `.ct-valor--nuevo` con los tokens de 7.2 (no los hex de técnicas).
- Valor vacío: `<span class="ct-vacio">Sin valor</span>` (como técnicas).
- Sí/No (exclusivo, invisible, editable, comerciable, negro): texto "Sí" o "No", nunca `1`/`0` ni un icono solo.
- `.ho-delta`: solo en campos numéricos puros (berries, `berriesCrafteo`, `negro_berries`, `cantidadMaxima`, espacios, nivel, tier, `tiempo_creacion`). Texto con signo, Inter 11px `--opg-gris-texto`, sin color de "bueno/malo" (que suba un precio no es bueno ni malo para todos). Ver decisión V-4.
- `.ho-privado` (`crafteo_usuarios`, D-9): Inter 12px cursiva `--opg-gris-texto`, con icono de candado. Ocupa las dos columnas de valor.

**b) Campos largos** (descripción, efecto, requisitos, escalado, engastes): debajo de la tabla, el bloque de técnicas `ct-diferencia` + `ct-comparacion` (Antes, flecha, Ahora), con el título del campo en `strong`. Texto en plano, escapado, con `nl2br`. Si el registro lo abrevió (req. 43), al final del valor: `<span class="ho-abreviado">(texto abreviado en el registro)</span>`, Inter 10px cursiva `--opg-gris-texto`.

Orden de las filas: el de los grupos del filtro "Campo modificado" (clasificación, economía, combate, fabricación, inventario, presentación, visibilidad), no el alfabético; dentro del grupo, el orden del formulario de `objetos_modificar.php`.

### 3.5 Alta (formato moderno): valores iniciales

- `summary`: "Ver valores iniciales (N campos)".
- Misma `ho-tabla`, pero con **dos columnas**: `Campo` | `Valor inicial` (`<colgroup>` 32% / 68%). Clase adicional `ho-tabla--alta`. El valor va en `.ct-valor.ct-valor--nuevo`.
- Solo campos con valor (no se listan los vacíos ni los `0` por defecto; los sí/no sí se listan).
- Campos largos en bloques `ct-diferencia` con un solo `.ct-valor--nuevo` (como hace técnicas con las altas).
- Si el alta llega con el objeto invisible y quien mira es staff: marca "Solo staff" (3.2).

### 3.6 Renombrado

Arriba del cuerpo (después de la ficha), una banda que explica el cambio de id:

```html
<div class="ho-renombre">
  <span class="ho-renombre__titulo">El ID del objeto cambió</span>
  <div class="ho-renombre__ids">
    <span class="ct-chip ct-chip--id"><span class="ho-oculto">ID anterior: </span>KATW001</span>
    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
    <span class="ct-chip ct-chip--id"><span class="ho-oculto">ID nuevo: </span>KAT001</span>
  </div>
  <p>El historial de los dos ID es el mismo. Buscar cualquiera de ellos muestra todos sus cambios.</p>
</div>
```

- `.ho-renombre`: `display: grid; gap: 6px; padding: 10px 12px; border-left: 4px solid var(--opg-azul); background: color-mix(in srgb, var(--opg-azul) 8%, white); color: var(--opg-marron); font: 12px/1.45 var(--opg-fuente-cuerpo)`.
- `.ho-renombre__titulo`: Inter Medium 11px mayúsculas `--opg-ciruela`.
- `.ho-renombre__ids`: `display: flex; flex-wrap: wrap; align-items: center; gap: 8px`; la flecha en `--opg-azul`.
- Si además cambiaron otros campos en el mismo guardado, debajo va la tabla de 3.4 sin la fila `objeto_id` (ya está en la banda).
- En la cabecera, el chip de ID muestra **el id nuevo**; en `ct-campos` aparece "ID".

### 3.7 Retirada

- La miniatura de la cabecera lleva `ho-miniatura--retirada`: `filter: grayscale(1); opacity: .7`. Con el texto del tipo "Retirado" basta; no se superpone ningún icono.
- No hay ficha ni "Ver en el catálogo" (req. 34). El pie solo tiene "Enlace permanente" (y "Editar (staff)" si aplica).
- Bloque del cuerpo:

```html
<div class="ho-retirada">
  <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
  <div>
    <strong>Objeto retirado del catálogo</strong>
    <span>Su ID pasó de <span class="ct-chip ct-chip--id">KAT001</span> a <span class="ct-chip ct-chip--id">BORRKAT001</span>. Ya no se puede conseguir.</span>
  </div>
</div>
```

- `.ho-retirada`: `display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border-left: 4px solid var(--opg-rojo-error); background: color-mix(in srgb, var(--opg-rojo-error) 8%, white); color: var(--opg-marron); font: 12px/1.45 var(--opg-fuente-cuerpo)`. Icono en `--opg-rojo-error`, 16px. `strong` en `--opg-ciruela`, `display: block`.
- El texto "Ya no se puede conseguir." solo si el Game Designer lo confirma; si no, se omite (es una afirmación de reglas).

### 3.8 Registro antiguo

Los 430 registros anteriores al sistema nuevo. **Nunca** muestran "Antes" ni "Ahora".

- Insignia y franja de "Registro antiguo" (3.2), con el texto "Registro antiguo · Actualización" o "Registro antiguo · Alta".
- Chip extra en `ct-meta`: no hace falta (el tipo ya lo dice); se elimina el `ct-chip--historico` de técnicas para no repetir.
- `ct-campos` en un registro antiguo de actualización: "Detalle no conservado".
- Cuerpo:
  1. `ct-nota-historica` (con tokens, 7.2): "Registro antiguo: el objeto se actualizó, pero en esa fecha no se guardaba qué cambió." Para altas: "Registro antiguo: se conservan solo algunos valores iniciales."
  2. Actualización: lista "Estado tras el cambio" (nombre, categoría, subcategoría):

     ```html
     <dl class="ho-estado-tras">
       <dt>Nombre</dt><dd>Katana de Wano</dd>
       <dt>Categoría</dt><dd>Armas</dd>
       <dt>Subcategoría</dt><dd>Espadas</dd>
     </dl>
     ```

     `.ho-estado-tras`: `display: grid; grid-template-columns: max-content minmax(0,1fr); gap: 4px 12px; margin: 0; padding: 8px 10px; background: var(--opg-crema); font: 12px/1.45 var(--opg-fuente-cuerpo)`. `dt`: Inter Medium 10px mayúsculas `--opg-gris-texto`. `dd`: `--opg-marron`, `margin: 0`, `overflow-wrap: anywhere`.
     Encima, un rótulo `<span class="ho-ficha__titulo">Estado tras el cambio</span>`.
  3. Alta antigua de `objetos_crear.php`: la misma `ho-tabla--alta` de 3.5 con rótulo "Valores conservados", sin los campos vacíos (`calidad`, `unidades`).
- La ficha del objeto actual (3.3) sí aparece encima si el objeto existe y es visible: es lo más útil de un registro que no dice qué cambió.

---

## 4. Estados

| Estado | Cuándo | Aspecto | Texto exacto |
|---|---|---|---|
| Vacío | Sin filtros (o solo el periodo) y ningún cambio en el periodo | `ct-sin-resultados` con icono `fa-box-open`; botón `ct-btn--primario` "Ver todo el historial" (`?periodo=todo`). Si ya es `todo`, sin botón | **Sin cambios en este periodo.** "Todavía no hay cambios del catálogo en los últimos 90 días." / con `todo`: "Todavía no hay cambios registrados." |
| Sin resultados con filtros | Hay filtros activos y nada coincide | `ct-sin-resultados` con `fa-filter-circle-xmark`; los filtros activos siguen visibles arriba; botón `ct-btn--primario` "Limpiar filtros" | **Sin cambios encontrados.** "Prueba a quitar algún filtro o consulta todo el historial." |
| Mis objetos sin coincidencias | `alcance=mios` y nada coincide | Igual que el anterior, con `fa-briefcase` | **Ninguno de tus objetos cambió en este periodo.** |
| Filtro no válido | Un parámetro de la URL no es válido y se restablece (req. 24) | `p.ho-nota` dentro de `ct-filtros`, encima de los filtros activos | "Algún filtro de la dirección no era válido y se ha restablecido." |
| Mis objetos sin sesión | `alcance=mios` llega sin sesión | `p.ho-nota` con enlace | "Inicia sesión para ver los cambios de tus objetos." (enlace a `/member.php?action=login`) |
| Error | Falla la lectura de la base de datos | `p.aviso.err` con `role="alert"` en lugar de la lista; no se pinta resumen ni paginación | "No se pudo cargar el historial. Inténtalo de nuevo en unos minutos." |
| Carga | Página renderizada en servidor: no hay esqueletos ni spinners | Botón `Aplicar` al enviar (JS opcional): `aria-disabled="true"`, texto "Aplicando..." e icono `fa-spinner fa-spin`. Las miniaturas reservan su caja de 56px con fondo crema, para que no salten al cargar | "Aplicando..." |
| Sin permiso | La página es pública; el único contenido restringido es el de staff | Los visitantes nunca ven entradas "Solo staff", ni el enlace "Editar (staff)", ni la opción de alcance sin sesión. No hay pantalla de "sin permiso" | (no aplica) |

`.ho-nota`: `display: flex; align-items: flex-start; gap: 8px; margin: var(--opg-espacio-3) 0 0; padding: 8px 10px; border-left: 4px solid var(--opg-morado-texto); background: #fff; color: var(--opg-marron); font: 12px/1.45 var(--opg-fuente-cuerpo)`. Icono `fa-circle-info` en `--opg-morado-texto`. Enlaces dentro: `--opg-morado-texto` subrayado.

Texto del resumen (`ct-total`), con separador de miles:
- Con resultados: "Mostrando 1 a 25 de 132 cambios · 410 cambios en los últimos 90 días".
- Sin filtros activos y con todo el periodo visible en una página: "12 cambios en los últimos 30 días".
- `periodo=todo`: "... · 1.204 cambios en todo el historial".

---

## 5. Responsive

Breakpoints: los de técnicas (900px y 700px) más uno de 420px para la ficha. Un solo bloque `@media` por breakpoint, al final del `<style>` (style.md §7, "Orden dentro de un `<style>`").

| Elemento | Escritorio (más de 900px) | Tablet (701 a 900px) | Móvil (700px o menos; probar a 320px) |
|---|---|---|---|
| Marco | `max-width: 1120px` | igual | `.mainBackground`, `.secondBackground`, `.thirdBackground`: `width: 100%; min-width: 0; padding-inline: 8px` (copiado de técnicas) |
| Cabecera | 2 columnas (texto, enlaces) | igual | 1 columna; enlaces debajo, alineados a la izquierda, con `flex-wrap` |
| Búsqueda, periodo, orden | 1 fila | 2 columnas; búsqueda a todo el ancho | 1 columna; `Aplicar` a todo el ancho |
| Chips de tipo | `flex-wrap`, 1 o 2 filas | `flex-wrap` | `flex-wrap` (2 o 3 filas a 320px). Ver decisión V-3 |
| Filtros avanzados | 4 columnas | 2 columnas | 1 columna |
| Resumen | texto a la izquierda, controles a la derecha | igual | apilados; controles a la izquierda |
| Cabecera de entrada | miniatura, texto, fecha a la derecha | igual | `ct-entrada__cabecera` en columna: la fecha pasa debajo del texto, alineada a la izquierda (`text-align: left`), y la miniatura baja a 44px |
| Ficha compacta | imagen, datos, enlaces en fila | igual | a 420px o menos: `ho-ficha__enlaces` a todo el ancho (`flex-basis: 100%`) |
| Tabla Antes/Ahora | 3 columnas (28/36/36) | igual | **se apila**: ver abajo |
| Comparación de textos largos | Antes, flecha, Ahora | igual | 1 columna, flecha girada 90° (copiado de técnicas) |
| Paginación | Anterior, "Página X de Y", Siguiente | igual | en columna, botones a todo el ancho |

Tabla apilada a 700px o menos (sin desbordar a 320px):

```css
/* especificación, no código final */
.ho-tabla, .ho-tabla tbody, .ho-tabla tr, .ho-tabla th, .ho-tabla td { display: block; width: 100%; }
.ho-tabla thead, .ho-tabla colgroup { display: none; }
.ho-tabla tr { padding: 8px 0; border-top: 1px solid rgba(0,0,0,.14); }
.ho-tabla tbody th[scope=row] { padding: 0 0 4px; }
.ho-tabla td { padding: 0 0 6px; }
.ho-tabla__etiqueta { position: static; width: auto; height: auto; margin: 0 0 2px; clip: auto; overflow: visible; white-space: normal; display: block; font: 9px InterMedium, var(--opg-fuente-cuerpo); text-transform: uppercase; color: var(--opg-gris-texto); }
```

Cada `td` de valor empieza por `<span class="ho-tabla__etiqueta">Antes</span>` (o "Ahora"). En escritorio esa etiqueta usa la receta de `.ho-oculto` (la cabecera de la tabla ya dice qué columna es); a 700px o menos se hace visible con la regla de arriba. Así el lector de pantalla siempre oye "Antes 1.200, Ahora 1.500" aunque el `display: block` borre la semántica de tabla en algún navegador, y no se usa contenido generado con `::before`.

Desbordamiento a 320px: todos los contenedores flexibles con `min-width: 0`, todo texto de usuario con `overflow-wrap: anywhere` (nombres, ids, valores), ninguna anchura fija mayor de 288px (320 menos marcos), miniaturas con `flex: 0 0 auto`. Los ids largos (`BORRKAT001-1044-3`) van en `ct-chip` que también llevan `overflow-wrap: anywhere; max-width: 100%`.

Objetivos táctiles (44px) en esta página, aunque técnicas use menos:

| Elemento | Técnicas hoy | Aquí |
|---|---|---|
| `.ct-btn` (Aplicar, enlaces de cabecera, paginación, Limpiar filtros) | 39px | `min-height: 44px` |
| `.ct-campo input`, `.ct-campo select` | 39px | `min-height: 44px` |
| `.ho-chip-accion` | (no existe) | `min-height: 44px` |
| `.ct-filtros-avanzados > summary` | sin mínimo | `min-height: 44px` |
| `.ct-detalles summary` | ~38px | `min-height: 44px` |
| `.ct-control` (Expandir y Contraer) | 31px | `min-height: 44px` |
| `.ct-filtro-chip` (filtros activos) y `.ct-limpiar` | 28px | `min-height: 32px` en escritorio, `44px` a 700px o menos (`.ct-limpiar` con `display: inline-flex; align-items: center`) |
| `.opg-chip` de la ficha | ~26px | `min-height: 44px` |
| Enlaces de `ct-entrada__pie` | texto | `display: inline-flex; align-items: center; min-height: 44px` |

---

## 6. Accesibilidad

### 6.1 Foco visible

Todos los elementos interactivos de la página reciben `outline: none; box-shadow: var(--opg-foco);` en `:focus-visible`, ampliando la regla de técnicas con: `.ho-chip-accion`, `.ho-ficha a`, `.ct-entrada__pie a`, `.ct-limpiar`, `.ct-paginacion a`, `.ho-nota a`. El halo dorado se ve sobre crema y sobre ciruela (cabecera). Sobre el chip activo (fondo ciruela) se mantiene el mismo halo.

`ct-entrada:target`: `outline: 4px solid var(--opg-naranja); outline-offset: 3px` (como técnicas). No depende solo del color: además, el `details` de esa entrada se abre (JS, 6.4).

### 6.2 Contraste (WCAG, sobre combinaciones de esta página)

| Combinación | Ratio aprox. | AA normal | Uso |
|---|---|---|---|
| `--opg-ciruela` sobre `--opg-crema-clara` | 16,5 | Sí | Nombre, tipo de acción, títulos |
| `--opg-marron` sobre `--opg-crema-clara` | 14,3 | Sí | Valores, cuerpo |
| `--opg-gris-texto` sobre `--opg-crema-clara` | 5,9 | Sí | Fecha, contadores, campos |
| `--opg-gris-texto` sobre `#fff` | 6,5 | Sí | Etiquetas de tabla |
| `--opg-morado-texto` sobre `#fff` | 9,1 | Sí | Filtros activos, enlaces del pie |
| `--opg-morado-texto` sobre `--opg-crema-dorada` | 7,4 | Sí | `summary` de detalles |
| `--opg-marron` sobre Antes (`color-mix` rojo 10%) | 14,0 | Sí | Valor anterior |
| `--opg-marron` sobre Ahora (`color-mix` verde 12%) | 14,5 | Sí | Valor nuevo |
| `#fff` sobre `--opg-ciruela` (chip activo, ID) | 18,1 | Sí | |
| `#fff` sobre `--opg-gris-bloqueado` (Solo staff) | 4,9 | Sí (justo) | |
| `--opg-rojo-faccion` sobre `--opg-crema-clara` | 6,1 | Sí | `.ct-limpiar` |
| Icono `--opg-tinta` sobre `--opg-verde-exito` | 7,3 | (icono, pide 3:1) | Insignia de alta |
| Icono `--opg-tinta` sobre `--opg-naranja` | 8,8 | (icono) | Insignia de modificación |
| Icono `#fff` sobre `--opg-azul` | 7,0 | (icono) | Insignia de renombre |
| Icono `#fff` sobre `--opg-rojo-error` | 4,5 | (icono) | Insignia de retirada |
| Icono `--opg-marron` sobre `--opg-crema-dorada` | 12,2 | (icono) | Insignia de registro antiguo |
| `#fff` sobre `--opg-naranja-accion` (`ct-btn--primario`) | 3,75 | No (solo texto grande) | Ver nota |

Nota: `ct-btn--primario` de técnicas no pasa AA con texto de 12px. En esta página se le añade `text-shadow: var(--opg-sombra-titular)` y `font-size: 13px`, la misma compensación que `.btn-op` (style.md §7, "Contraste"). Las franjas de color de 6px no llegan a 3:1 contra la crema en todos los tipos (el verde se queda en 2,6), y por eso **nunca** son el único indicador: el tipo siempre está escrito y tiene icono.

Lo que no se usa como texto: `--opg-verde-exito`, `--opg-rojo-error`, `--opg-naranja` y `--opg-morado` (fallan AA como texto pequeño sobre crema).

### 6.3 Nombres accesibles y estructura

- `h1` (título de página) → `h2.ct-grupo-fecha` (día) → `h3` (objeto). La ficha y los bloques internos no usan encabezados (son `section` con `aria-label` o rótulos `span`), para no romper la jerarquía de 25 entradas.
- `article` con `aria-labelledby` al `h3`.
- `nav.ho-chips` con `aria-label="Filtrar por tipo de cambio"`; chip activo con `aria-current="true"`; chip vacío con `aria-disabled="true"` y sin `href`. El contador va dentro del enlace, de modo que el nombre accesible queda "Nuevos 18".
- `.ct-filtros-activos` con `aria-label="Filtros activos"`; cada `ct-filtro-chip` con `aria-label="Quitar filtro: Categoría Armas"` (el `✕` es solo un icono).
- `section.ct-lista` con `aria-label="Historial de cambios"`; `nav.ct-paginacion` con `aria-label="Páginas del historial"` y el enlace actual descrito como "Página 2 de 6".
- Todos los iconos con `aria-hidden="true"`. Ningún botón es solo un icono; si alguno lo fuera, llevaría `aria-label`.
- `time datetime` en la fecha de cada entrada.
- La tabla lleva `caption` oculto (`.ho-oculto`), `th scope="col"` y `th scope="row"`.
- `.ho-oculto` (texto solo para lectores): `position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0` (misma receta que `.sr-only` de `op_estadisticas.html`).
- "Lo tienes", "Solo staff", "Registro antiguo", "Retirado" y "Objeto publicado" son texto visible, no solo color (req. 19 y 54).

### 6.4 Orden de lectura y teclado

1. Cabecera (título, descripción, enlaces). 2. Búsqueda, periodo, orden, Aplicar. 3. Chips de tipo. 4. Filtros avanzados. 5. Filtros activos. 6. Resumen y Expandir o Contraer. 7. Entradas (en cada una: tipo, nombre, chips, campos, fecha, `summary`; dentro: ficha, cambios, pie). 8. Paginación.

El orden del DOM es el visual en todos los tamaños (en móvil la fecha baja con `flex-direction: column`, no con `order`). Sin `tabindex` positivos.

JS progresivo (todo opcional, la página funciona sin él):
- Expandir y Contraer todos: copiar el script de técnicas con `data-ct-details`.
- Al cargar, si `location.hash` empieza por `#cambio-`, abrir el `details` de esa entrada.
- Al enviar el formulario, estado "Aplicando..." del botón (sección 4).
- Nombres de variable sin `$` (style.md §7, "JS dentro de un template").

---

## 7. Mapa de tokens y componentes

### 7.1 Elementos de la página

| Elemento | Clase | Tokens |
|---|---|---|
| Marco | `.mainBackground` › `.secondBackground` › `main.thirdBackground` + `.ct-pagina.ho-pagina` | `--opg-espacio-5` (gap) |
| Cabecera | `.ct-cabecera` | fondo `--opg-ciruela`, borde `--opg-tinta`, franja `--opg-naranja`, `--opg-sombra-offset`, `--opg-fuente-titular` |
| Botones | `.ct-btn--primario`, `.ct-btn--secundario` | `--opg-naranja-accion`, `--opg-ciruela`, `--opg-tinta`, `--opg-radio-sm`, `--opg-sombra-titular` |
| Panel de filtros | `.ct-filtros` | `--opg-crema-clara`, `--opg-tinta`, `--opg-radio-md`, `--opg-sombra-offset` |
| Campos | `.ct-campo` | `--opg-ciruela` (label), `--opg-marron` (valor), `--opg-radio-sm` |
| Chips de tipo | `.ho-chips`, `.ho-chip-accion` (`--activa`, `--vacia`), `.ho-insignia--x` | `--opg-ciruela`, `--opg-crema-dorada`, `--opg-gris-bloqueado`, `--opg-gris-texto`, colores de 3.2, `--opg-rapido` |
| Filtros activos | `.ct-filtro-chip`, `.ct-limpiar` | `--opg-morado-texto`, `--opg-crema-dorada`, `--opg-rojo-faccion` |
| Resumen | `.ct-resultados-barra`, `.ct-total`, `.ct-control` | `--opg-naranja` (filete), `--opg-gris-texto`, `--opg-ciruela` |
| Día | `.ct-grupo-fecha` | `--opg-ciruela`, `--opg-fuente-titular` |
| Entrada | `.ct-entrada.ho-entrada.ho-entrada--x` | `--opg-crema-clara`, `--opg-tinta`, `--opg-radio-md`, `--opg-sombra-offset`, `--ho-acento` |
| Miniatura | `.ho-miniatura` (`--grande`, `--retirada`) | `--opg-crema`, `--opg-tinta`, `--opg-radio-sm`, `--opg-marron` (icono) |
| Chips de la entrada | `.ct-chip`, `.ct-chip--id`, `.ho-chip--tuyo`, `.ho-chip--staff` | `--opg-marron`, `--opg-ciruela`, `--opg-verde-exito`, `--opg-gris-bloqueado` |
| Detalles | `.ct-detalles`, `.ct-detalles__cuerpo` | `--opg-crema-dorada` (summary), `--opg-morado-texto` |
| Ficha actual | `.ho-ficha*` + `.opg-chip` | `--opg-crema-clara`, `--opg-ciruela`, `--opg-gris-texto`, `--opg-marron` |
| Tabla | `.ho-tabla`, `.ho-tabla--alta`, `.ho-delta`, `.ho-privado` | `--opg-ciruela`, `--opg-gris-texto` |
| Valores | `.ct-valor`, `--antes`, `--nuevo`, `.ct-vacio` | ver 7.2 |
| Renombre | `.ho-renombre*` | `--opg-azul`, `--opg-marron`, `--opg-ciruela` |
| Retirada | `.ho-retirada` | `--opg-rojo-error`, `--opg-marron`, `--opg-ciruela` |
| Registro antiguo | `.ct-nota-historica`, `.ho-estado-tras` | `--opg-marron`, `--opg-crema`, `--opg-crema-dorada`, `--opg-gris-texto` |
| Notas | `.ho-nota` | `--opg-morado-texto`, `--opg-marron` |
| Error | `.aviso.err` (opg-components.css) | `--opg-rojo-error` |
| Vacío | `.ct-sin-resultados` | `--opg-gris-texto`, `--opg-morado-texto`, `--opg-ciruela` |
| Paginación | `.ct-paginacion` + `.ct-btn--secundario` | `--opg-gris-texto` |
| Foco | todos | `--opg-foco` |
| Accesible oculto | `.ho-oculto` | (sin color) |
| Tarjeta de acceso | `.ht-cta*` (en `op_objetos.html`) | `--opg-morado-cta`, `--opg-morado-cta-hover`, `--opg-naranja`, `--opg-tinta`, `--opg-radio-md` |
| Objeto enlazado | `.ho-objeto-destacado` (en `op_objetos.html`) | `--opg-naranja`, `--opg-radio-sm` |

### 7.2 Hex de técnicas que se sustituyen en la copia

| Regla `ct-*` | Hex en técnicas | En `op_historial_objetos.html` |
|---|---|---|
| `.ct-accion--nueva` | `#176b34` | no se copia (lo sustituye `.ho-accion` + `.ho-insignia`) |
| `.ct-chip--aprendida` | `#25763f`, `#dff2e5`, `#176b34` | no se copia (lo sustituye `.ho-chip--tuyo`) |
| `.ct-chip--historico` | `#8b6d36`, `#f5e7bd`, `#665020` | no se copia (3.8) |
| `.ct-valor--antes` | `#a3180b`, `#fde8e5` | `border-left-color: var(--opg-rojo-error); background: color-mix(in srgb, var(--opg-rojo-error) 10%, white)` |
| `.ct-valor--nuevo` | `#176b34`, `#e3f5e9` | `border-left-color: var(--opg-verde-exito); background: color-mix(in srgb, var(--opg-verde-exito) 12%, white)` |
| `.ct-nota-historica` | `#8b6d36`, `#fff6dc`, `#665020` | `border-left-color: var(--opg-marron); background: var(--opg-crema); color: var(--opg-marron)` |
| `.ct-limpiar` | `#9a271d` | `color: var(--opg-rojo-faccion)` |

`color-mix()` ya se usa en `.opg-card` (style.md §7, "Degradados"), así que no es una dependencia nueva.

### 7.3 Literales permitidos

Solo `#fff` (texto sobre fondos oscuros y fondo de campos, como `.opg-chip` y `.btn-op`) y `rgba(0,0,0,.14)` a `rgba(0,0,0,.35)` para filetes y separadores (los mismos de técnicas). Nada más.

### 7.4 Lo que NO hay que hacer

- **Modo oscuro** ni `prefers-color-scheme`: el foro no tiene temas.
- **Hex sueltos** fuera de 7.3, incluidos los colores de rareza de `op_objetos.html` (`#808080`, `#4dfe45`, `#457bfe`, `#cf44ff`, `#febb46`, el arcoíris) salvo que el usuario elija la opción B de V-2.
- **Botones inventados**: nada de `.ho-btn`. Botones `ct-btn` (familia del historial) o `.btn-op`; acciones secundarias `.opg-chip` o `ct-control`.
- **Tarjetas anidadas con sombra**: dentro de la entrada solo bandas sin sombra (212 §7.4).
- **Degradados** entre matices o en texto (style.md §7).
- **Texto en `--opg-verde-exito`, `--opg-rojo-error`, `--opg-naranja` o `--opg-morado`** a tamaño de cuerpo.
- **Color como único indicador** de tipo, de "Lo tienes" o de "Solo staff".
- **HTML guardado sin escapar**: descripción, efecto y similares se muestran como texto plano escapado, aunque en la base tengan etiquetas.
- **Otro `<link>` a Font Awesome**: `headerinclude.html` ya carga la 6.4.0.
- **`moonGetHeavy` con contorno sobre fondo claro**: solo en la cabecera ciruela y en `.ht-cta`.
- **Pestañas**: una sola vista cronológica (req. 10).
- **Tablas con anchura fija o `white-space: nowrap`** en valores: desbordan a 320px.
- **`text-align: center` heredado**: `.thirdBackground` lleva `text-align: left` (copiado de técnicas).
- **Variables JS con `$`** en el script de la plantilla.

---

## 8. Tarjeta de acceso y enlaces cruzados

### 8.1 Tarjeta en `op_objetos.html`

Se reutiliza `.ht-cta` tal cual existe en `op_tecnicas.html` (morado de llamada a la acción `--opg-morado-cta`, borde 3px, sombra desplazada, icono circular naranja, título moonGetHeavy con contorno, flecha).

- `.ht-cta` vive inline en `op_tecnicas.html`. `op_objetos.html` no tiene bloque `<style>`: se añade uno después de `{$headerinclude}` con **copia literal** del bloque `.ht-cta` (reglas `.ht-cta`, `:hover`, `:focus-visible`, `__icono`, `__texto`, `__titulo`, `__desc`, `__flecha` y el `@media (max-width: 520px)`). Mismo nombre de clase: es el mismo componente.
- Posición: **arriba**, como primer hijo de `#objetos`, antes de "CONSUMIBLES" (decisión V-5). El catálogo pinta unos 543 objetos; al final de la página la tarjeta no la vería casi nadie.
- Contenido:

```html
<a class="ht-cta" href="/op/historial_objetos.php">
  <span class="ht-cta__icono"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
  <span class="ht-cta__texto">
    <strong class="ht-cta__titulo">Historial de objetos</strong>
    <span class="ht-cta__desc">Consulta qué objetos se añadieron, cambiaron o se retiraron del catálogo.</span>
  </span>
  <span class="ht-cta__flecha"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
</a>
```

- `margin` de la tarjeta: en `op_objetos.html` usar `margin: 0 auto 24px` (override local `#objetos > .ht-cta`), porque arriba ya está el `padding` de `.fondo-blanco`.

### 8.2 Desplazamiento a `op/objetos.php#ID`

El catálogo se pinta con JS, así que el navegador no encuentra el ancla al cargar. Tras el bucle que pinta los objetos, un script corto: leer `location.hash`, buscar el elemento con `document.getElementById(decodeURIComponent(hash.slice(1)))`, hacer `scrollIntoView({ block: 'center' })` y añadirle la clase `ho-objeto-destacado`:

- `.ho-objeto-destacado`: `outline: 4px solid var(--opg-naranja); outline-offset: 3px; border-radius: var(--opg-radio-sm)`. Mismo lenguaje que `ct-entrada:target`.
- Si el id no existe (invisible, transporte, retirado), no se hace nada y no se muestra error.

### 8.3 Enlaces cruzados entre los dos historiales

| Página | Dónde | Enlace |
|---|---|---|
| `op_historial_objetos.html` | `nav.ct-cabecera__enlaces` | `ct-btn ct-btn--secundario`: "Catálogo" (`fa-box-open`, `/op/objetos.php`) y "Historial de técnicas" (`fa-clock-rotate-left`, `/op/historial_tecnicas.php`) |
| `op_historial_tecnicas.html` | `nav.ct-cabecera__enlaces`, al final | `ct-btn ct-btn--secundario`: "Historial de objetos" (`fa-boxes-stacked`, `/op/historial_objetos.php`) |

Cambio mínimo en técnicas: solo se añade ese `<a>`; nada más de su plantilla se toca. `aria-label` del `nav` de objetos: "Enlaces de objetos".

---

## 9. Criterios visuales de aceptación (para el QA)

Todos se comprueban leyendo `templates/One_Piece_Gaiden_Templates/op_historial_objetos.html`, `op_objetos.html`, `op_historial_tecnicas.html` y el HTML que genera `op/historial_objetos.php`.

- [ ] La plantilla carga `{$headerinclude}` y usa `.mainBackground` › `.secondBackground` › `main.thirdBackground` con `max-width: 1120px`.
- [ ] Las clases reutilizadas conservan el nombre `ct-*`; toda clase nueva empieza por `ho-` (salvo `.ht-cta`, que es copia literal).
- [ ] En el `<style>` de la página nueva no hay más literales de color que `#fff` y `rgba(0,0,0,.x)`; todo lo demás es `var(--opg-*)`, `var(--ho-acento)` o `color-mix()` sobre tokens. (`grep -E "#[0-9a-fA-F]{3,6}"` solo devuelve `#fff`.)
- [ ] Cada tipo de entrada (alta, modificación, renombrado, retirada, registro antiguo, publicado) tiene clase modificadora, icono FA y texto escrito; el texto del tipo usa `--opg-ciruela`.
- [ ] Ninguna regla pone en `color` los tokens `--opg-verde-exito`, `--opg-rojo-error`, `--opg-naranja` ni `--opg-morado` sobre texto de menos de 18px.
- [ ] Los registros antiguos no contienen las palabras "Antes" ni "Ahora" ni la clase `ct-comparacion` ni `ho-tabla` con tres columnas.
- [ ] Las retiradas no contienen enlace a `objetos.php#` ni `.ho-ficha`.
- [ ] Cada `article.ct-entrada` tiene `id="cambio-N"` y `aria-labelledby`; la página tiene un solo `h1`, los días son `h2` y los objetos `h3`.
- [ ] Las miniaturas llevan `alt=""`, `width`, `height` y `loading="lazy"`.
- [ ] La tabla de comparación usa `<table>` con `caption`, `th scope="col"` y `th scope="row"`, `table-layout: fixed`, y tiene una regla en `@media (max-width: 700px)` que la apila (`display: block`).
- [ ] Ningún valor de usuario lleva `white-space: nowrap`; los contenedores de texto llevan `overflow-wrap: anywhere` y los hijos flex `min-width: 0`.
- [ ] `min-height: 44px` en `.ct-btn`, campos, `.ho-chip-accion`, ambos `summary`, `.ct-control`, `.opg-chip` de la ficha y enlaces del pie; `.ct-filtro-chip` llega a 44px dentro del `@media (max-width: 700px)`.
- [ ] `.ho-chips` usa `flex-wrap: wrap` y no hay `overflow-x` en la página (si el usuario elige V-3 B, el `overflow-x: auto` está solo en `.ho-chips`).
- [ ] Todo elemento interactivo tiene regla `:focus-visible` con `var(--opg-foco)`.
- [ ] Todos los `<i class="fa-...">` llevan `aria-hidden="true"`; chips de tipo con `aria-current` o `aria-disabled`; `nav` con `aria-label`.
- [ ] "Lo tienes" y "Solo staff" son texto visible dentro del chip, con icono.
- [ ] Los cinco estados de la sección 4 existen en el PHP con los textos exactos.
- [ ] No hay `prefers-color-scheme`, ni `<link>` a Font Awesome, ni clase de botón nueva.
- [ ] Los `@media` están al final del `<style>`, uno por breakpoint (900, 700, 420).
- [ ] `op_objetos.html` tiene la tarjeta `.ht-cta` como primer hijo de `#objetos`, con el bloque CSS copiado igual que en `op_tecnicas.html`, y el script de `#ID` con `.ho-objeto-destacado`.
- [ ] `op_historial_tecnicas.html` solo cambia por el enlace "Historial de objetos" en su cabecera; `op_historial_objetos.html` enlaza a "Catálogo" y a "Historial de técnicas".
- [ ] Textos en español neutro, sin voseo y sin rayas largas.
- [ ] El Developer avisa de que hay que subir las tres plantillas (`op_historial_objetos`, `op_objetos`, `op_historial_tecnicas`) y de que subirlas las activa.

## 10. No verificable sin navegador

Lo que el usuario debe mirar en capturas una vez desplegado (escritorio a 1280px, tablet a 768px y móvil a 320px y 375px):

1. Que la página se reconozca como hermana del historial de técnicas (capturas una al lado de la otra).
2. Que las franjas y las insignias de color se distingan entre sí de un vistazo, en especial modificación (naranja) frente a registro antiguo (marrón y dorado), y que el verde de las altas no se pierda sobre la crema.
3. La tabla Antes/Ahora a 320px: sin barra de desplazamiento horizontal y legible apilada; y en escritorio con un texto largo (una descripción entera) en una celda.
4. Las miniaturas reales: objetos con `imagen_avatar` externa, GIF, imagen rota y sin imagen (icono de categoría). Que el `object-fit: contain` no deje miniaturas demasiado pequeñas.
5. Los chips de tipo a 320px: cuántas filas ocupan y si se ve bien el activo.
6. El halo de foco con el teclado (Tab) sobre la cabecera ciruela, los chips y los `summary`.
7. Que `#cambio-ID` desplace a la entrada sin que el header del foro la tape (`scroll-margin-top: 12px` puede quedarse corto si el header es fijo).
8. La tarjeta `.ht-cta` arriba del catálogo de objetos y que `objetos.php#KAT001` desplace al objeto y lo resalte.
9. El estado "Aplicando..." y el vacío con el periodo de 7 días.
10. Lectura con un lector de pantalla (VoiceOver) de una entrada con tabla en móvil, para confirmar que no repite "Antes" dos veces.

Hallazgo para el PM: `op_objetos.html` no tiene contenedor para la categoría Transportes (solo seis categorías), así que esos objetos no se pintan en el catálogo y su enlace `#ID` no llevaría a ningún sitio. Por eso 3.3 oculta "Ver en el catálogo" para transportes hasta que se corrija.

---

## 11. Decisiones que necesitan el visto bueno del usuario

**V-1. Cómo se reutilizan las clases `ct-*`.**
- A. Copiar el bloque `ct-*` de técnicas tal cual, hex incluidos. Identidad exacta, pero la página nueva nace con 15 hex sueltos.
- B. Copiar solo las reglas usadas y cambiar sus hex por tokens (tabla 7.2). Diferencia visual mínima (los verdes y rojos suaves cambian un tono) y la página nueva cumple el sistema de tokens.
- C. Mover `ct-*` a `opg-components.css` ya tokenizado y que lo usen las dos páginas. Lo más limpio, pero toca una hoja del tema y la página de técnicas, con despliegue acoplado.
- **Recomendación: B**, y abrir después una issue pequeña para C.

**V-2. Color de rareza de los objetos.** El catálogo pinta cada objeto con un color según `imagen_id` (gris, verde, azul, morado, dorado, arcoíris) que los jugadores reconocen.
- A. No usarlo: el tier va como texto (`Tier 3`) en un chip neutro.
- B. Usarlo como borde de la miniatura, como excepción justificada de hex.
- C. Tokenizar esos seis colores primero en `opg-tokens.css` y luego usarlos.
- **Recomendación: A** en esta fase; C si el usuario quiere el guiño visual (es un cambio de hoja del tema aparte).

**V-3. Chips de tipo en móvil.**
- A. Se parten en varias filas (`flex-wrap`).
- B. Una sola fila con desplazamiento horizontal dentro de la caja.
- **Recomendación: A.** Todo queda a la vista y no hay riesgo de que alguien no vea "Retirados" o "Registros antiguos".

**V-4. Diferencia numérica en los cambios de número (`+300`).**
- A. Mostrarla en gris neutro junto al valor nuevo.
- B. Mostrarla con color (verde si sube, rojo si baja).
- C. No mostrarla.
- **Recomendación: A.** Ayuda a leer el cambio sin juzgarlo. B sugiere que subir es bueno, cosa que no vale para un precio. Si el Game Designer decide (D-6) que no se muestre algún precio, su fila desaparece entera.

**V-5. Posición de la tarjeta de acceso en el catálogo de objetos.**
- A. Arriba, antes de Consumibles.
- B. Abajo, como en técnicas.
- **Recomendación: A.** El catálogo tiene cientos de objetos y abajo casi nadie llega; en técnicas la tarjeta funciona abajo porque la página es corta.

**V-6. Chips de tipo como enlaces o como opciones del formulario.**
- A. Enlaces: aplican el filtro al instante y sin JS; un texto escrito en la búsqueda y no enviado se pierde al pulsarlos.
- B. Botones de opción (`radio`) del formulario: se combinan con la búsqueda, pero necesitan pulsar `Aplicar` (o JS para enviar solos).
- **Recomendación: A**, igual que los filtros activos de técnicas, que ya son enlaces.

**V-7. Miniatura en la cabecera de cada entrada.**
- A. Miniatura de 56px en cada entrada (25 imágenes por página, con carga diferida).
- B. Sin miniatura en la cabecera; imagen solo en la ficha al abrir el detalle.
- **Recomendación: A.** Los objetos se reconocen por la imagen mucho antes que por el nombre o el ID.
