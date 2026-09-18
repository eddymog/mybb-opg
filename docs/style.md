# STYLE.md — Sistema de diseño de One Piece Gaiden

Guía de referencia del lenguaje visual del foro, extraída de los patrones reales
en `templates/` (miles de reglas analizadas). Sirve para que cualquier página o
componente nuevo **se sienta parte del foro** y no un injerto.

> **Estética base:** manga / cómic pirata de One Piece. Fondos de pergamino
> cálido, naranja y morado saturados, **bordes negros gruesos**, texto de
> titular con contorno negro, y micro-interacciones de "pop" (zoom al hover).
> Todo es alto contraste, físico y con "peso" de tinta — nada plano ni minimal.

---

## 1. Tipografía

Las fuentes se cargan por `@font-face` desde `https://onepiecegaiden.com/images/op/fonts/`.

| Rol | Fuente | Uso | Fallback |
|---|---|---|---|
| **Display / titulares** | `moonGetHeavy` | Lo más usado del foro (~1900 apariciones). Títulos, botones, labels, nav. Va **siempre con `text-shadow` negro**. | `Arial, sans-serif` |
| **Cuerpo de texto** | `InterRegular` / `InterMedium` | Párrafos, formularios, contenido legible largo. | `sans-serif` |
| **Display secundario** | `LemonMilkMedium` | Acentos y subtítulos ocasionales. | `sans-serif` |
| Decorativas | `naruto`, `Progress`, `Montserrat` | Usos puntuales / temáticos. | — |
| Serif clásica | `Georgia`, `Times New Roman` | Textos "de documento" o citas in-character. | `serif` |

```css
@font-face {
  font-family: 'moonGetHeavy';
  src: url('https://onepiecegaiden.com/images/op/fonts/moon_get-Heavy.otf') format('opentype');
}
```

**Regla de oro del titular** — `moonGetHeavy` casi nunca va "pelado": siempre
lleva contorno o sombra negra. El patrón más limpio (contorno 4 direcciones) es
la clase `.text-moon`:

```css
.text-moon {
  color: white;
  font-family: moonGetHeavy;
  text-shadow: -1px 0 black, 0 1px black, 1px 0 black, 0 -1px black;
}
```

Sombras de texto más comunes (de más a menos usadas):
`1px 1px 2px black` · `1px 1px 1px black` · `2px 2px 0px black` · `1px 1px 0px black`.

---

## 2. Paleta

El foro no tiene tokens centralizados (ver §6), pero los hex se agrupan en
familias muy consistentes. Estos son los valores canónicos por rol:

### Marca — Naranja (primario)
| Hex | Uso |
|---|---|
| `#ff8900` | **Naranja principal** — botones, acentos, pestañas |
| `#ff7e00` / `#ff7b00` | Variantes de bordes y rellenos fuertes |
| `#dc822a` | **Hover** del naranja (un paso más oscuro) |
| `#faa500` / `#ffa600` / `#ff7019` | Gradientes y estados |
| `#d26500` → `#ff9b00` (hover) | **Botón de acción naranja** sobre páginas con barras naranjas (`.realDraw`, 9 plantillas) |

### Secundario — Morado
| Hex | Uso |
|---|---|
| `#9664e0` | **Morado principal** (el hex más usado del foro) |
| `#8f59f7` | Bordes y realces morados |
| `#6e67d1` | Morado apagado — bordes de paneles (ej. cajón lateral) |
| `#cf44ff` | Morado brillante / neón para destacar |
| `#e0d2fd` / `#e8d9ff` | Morado muy claro — fondos suaves, elemento seleccionado |
| `rgba(70,12,110,1)` (`#460c6e`) → `rgba(126,32,191,1)` (hover) | **Morado de llamada a la acción** — botón principal grande (`.testDraw`, "ENTREGAR"; 8 plantillas) |
| `#6c10ab` / `#4b1aae` | Morado oscuro para texto y enlaces sobre crema (receptor, ítems en Intercambios) |

### Fondos — Pergamino / crema
| Hex | Uso |
|---|---|
| `#ffedd2` / `#ffeed2` | **Fondo crema base** de paneles y carruseles |
| `#ffe59b` | Crema dorada — bordes gruesos y realces |
| `#ffe3a0` | Fondo cuerpo de drawers |
| `#fcecd2` | Crema de botones/links en paneles |
| `#fff3e0` → `#ffe8c2` | Gradiente crema típico |

### Tinta / oscuros
| Hex | Uso |
|---|---|
| `#000000` | **Bordes y contornos** (omnipresente) |
| `#1a1423` / `#1e1224` | Ciruela muy oscuro — headers oscuros, fondos de contraste |
| `#3b1300` | Marrón oscuro para texto sobre crema |

### Acentos y semánticos
| Hex | Rol |
|---|---|
| `#ffd700` | Dorado / tesoro — destacados premium |
| `#0055bb` | Azul (marina / enlaces temáticos) |
| `#a3180b` / `#a13838` | Rojo facción / peligro suave |
| `#dc3545` / `#e74c3c` | **Rojo error** (validaciones, borrar) |
| `#27ae60` / `#4dfe45` | **Verde éxito** |
| `#5e5e5e` / `#666` / `#333` | Neutros de texto secundario |
| `#71706f` | **Bloqueado / deshabilitado** — botones sin acción disponible (`.blockedDraw`, 8 plantillas) |

---

## 3. Componentes y patrones

### Bordes negros gruesos (la firma visual)
El elemento más característico del foro. Cualquier "caja" lleva borde negro:

| Patrón | Frecuencia | Uso |
|---|---|---|
| `border: 2px solid black` | ★★★ (más usado) | Botones, tarjetas, avatares |
| `border: 1px solid black` | ★★ | Elementos menores |
| `border: 3px solid black` | ★★ | Tarjetas destacadas, miniaturas |
| `border: 4px solid #ffe59b` | ★ | Marcos crema-dorados |
| `border: 5px solid #ff7e00` | ★ | Marcos naranjas fuertes |

### Radios de esquina
Redondeado suave y "de cómic": `8px` y `10px` son los defaults; `15px`/`20px`
para tarjetas grandes; `4px`/`5px` para inputs y chips.

### Sombras
- **Glow negro** (lo más típico): `box-shadow: 0px 0px 10px black` — da el efecto
  "recortado sobre la página".
- Drop shadow suave: `box-shadow: 0 4px 8px rgba(0,0,0,.3)`.

### Botón canónico (`.btn`)
```css
.btn {
  border: 2px solid black;
  border-radius: 8px;
  padding: 8px 12px;
  cursor: pointer;
  font-family: moonGetHeavy;
  text-shadow: 1px 1px 1px black;
  transition: all 0.25s ease;
}
.btn--primary { background: #ff8900; color: #fff; }
.btn--primary:hover { background: #dc822a; }   /* naranja un paso más oscuro */
.btn--ghost { background: #a13838; color: #111; }
.btn--ghost:hover { background: #ff8900; color: #fff; }
```

### Tarjeta "fondo blanco" sobre imagen (`.fondo-blanco`)
Patrón para formularios/paneles legibles sobre una imagen de fondo (definido en
el template `op_peticiones`, reutilizado por `op/peticion_afiliados.php`): imagen
de fondo + una capa `::before` blanca casi opaca (`rgba(255,255,255,.94)`) +
contenido en `z-index: 2`. Bordes redondeados grandes (`20px`) y sombra profunda.

### Página de juego: marco de tres fondos
Es el layout estándar de las páginas de `/op/` (aparece en ~54 plantillas: Intercambios,
Crafteo, Entrenamiento, Mercado Negro, Coliseo…). Las clases viven en el CSS del tema
(`op_global.css`), así que solo funcionan si la página carga `{$headerinclude}`:

```html
{$header}
<div class="indice">
  <div class="mainBackground">        <!-- #ffe3a0, padding 10px -->
    <div class="secondBackground">    <!-- #fcecd2, borde 3px negro -->
      <div class="thirdBackground">   <!-- #ffe3a0, borde 2px negro, 1030px, columna -->
        …contenido…
      </div>
    </div>
  </div>
</div>
{$footer}
```

`.thirdBackground` tiene **ancho fijo de 1030px**. En páginas que deban verse en móvil,
cambialo en la propia página por `width: 100%; max-width: 1030px;`.

### Barra + cuerpo de formulario (`.barra-op` / `.barra-espacio-op`)
El patrón de campo del juego: una **barra naranja** con el título y, pegado debajo, un
**cuerpo crema** con el input. Ambos con borde negro de 2px; la barra redondeada arriba (10px).

```html
<div class="barra-op bbox">                        <!-- #ff8900, radio 10px arriba -->
  <span class="barra-texto-op" style="font-size: 15px;">Justificación*</span><br>
  <span class="barra-texto-op" style="font-size: 9px;">(Escribe razón del intercambio)</span>
</div>
<textarea class="barra-espacio-op texto-regular-op bbox"></textarea>   <!-- #fcecd2, sin borde arriba -->
```

- `.barra-texto-op` = `moonGetHeavy` blanco con `text-shadow: 1px 1px 1px black`.
- Subtítulo opcional en 9px entre paréntesis para instrucciones.
- Existe `.barra-op-abajo` (redondeada abajo) para cerrar un bloque.
- Evitá darle `height` fija a la barra si lleva dos líneas: depende del `line-height` del tema y se corta.

### Botón principal grande (`.testDraw` / `.realDraw` / `.blockedDraw`)
El botón de "acción de la página" (ENTREGAR, TIRAR…): grande, centrado y con tipografía de titular.

```css
.testDraw, .realDraw, .blockedDraw {
  width: 450px; height: 65px;
  color: white; font-family: moonGetHeavy; font-size: 1.5rem; letter-spacing: 2px;
  border: 2px solid black; border-radius: 15px;
  filter: drop-shadow(0 0 4px black);
  transition: all .5s ease-out; cursor: pointer;
}
.testDraw    { background: rgba(70,12,110,1); }  .testDraw:hover { background: rgba(126,32,191,1); }
.realDraw    { background: #d26500; }            .realDraw:hover { background: #ff9b00; }
.blockedDraw { background: #71706f; }            /* sin hover: no hay acción */
```

### Barra de sección / listado (`.akumaTypeRow` + `.categoriaBox`)
Cabecera de una lista o historial ("HISTORIAL DE INTERCAMBIOS"): barra `#ff7b00` con borde
negro de 2px y radio `10px 10px 0 0`, texto `moonGetHeavy` blanco en mayúsculas a 14px.
Debajo, un contenedor con borde negro de 2px **sin borde superior**. Las filas del listado
alternan `#ffe59b` y `#ffeed2`. Si hay varias pestañas, la activa usa `#ffa600` (`.box-active`).

Ejemplo de referencia completo: `templates/One_Piece_Gaiden_Templates/op_intercambio.html`.
Ejemplo de herramienta de staff que lo sigue: `op/staff/banners.php`.

---

## 4. Interacción y movimiento

El foro es **muy táctil**: casi todo reacciona al hover.

- **Zoom / pop:** `transform: scale(1.05)` (sutil) o `scale(1.1)` (fuerte) al
  hover. Es el gesto por defecto para botones, íconos y miniaturas.
- **Transiciones:** `transition: all 0.3s ease` o `all 0.2s ease-out` (a veces
  `0.25s ease`). Siempre suaves, nunca instantáneas.
- **Filtros al hover:** imágenes pasan de `grayscale(.2)` + `opacity(.9)` a color
  pleno; algunos títulos usan `filter: hue-rotate(...)` para "encenderse".

```css
.index-image { transition: all 0.25s ease; opacity: 0.9; filter: grayscale(0.2); }
.index-image:hover { transform: scale(1.1); opacity: 1; filter: grayscale(0); }
```

---

## 5. Checklist para que algo "se sienta OPG"

Al crear una página/componente nuevo, revisá:

- [ ] Titulares en `moonGetHeavy` **con `text-shadow` negro** (o `.text-moon`).
- [ ] Cuerpo de texto en `InterRegular`.
- [ ] Cajas con **borde negro** (`2px solid black` por defecto) y radio `8–10px`.
- [ ] Naranja `#ff8900` como acción primaria; hover a `#dc822a`. **Excepción:** en páginas de
      juego donde las barras ya son naranjas (`.barra-op`), el botón principal va en el morado
      de llamada a la acción `rgba(70,12,110,1)` (`.testDraw`) para no perderse entre ellas.
- [ ] Morado `#9664e0` / `#8f59f7` como secundario/realce.
- [ ] Fondos de panel en crema (`#ffedd2` / `#ffe59b`), no blanco puro plano.
- [ ] Hover con `scale(1.05)` + `transition: all .25s ease`.
- [ ] Rojo `#dc3545` para errores, verde `#27ae60` para éxito, gris `#71706f` para bloqueado.
- [ ] Páginas de `/op/`: dentro del marco `.mainBackground` → `.thirdBackground` y con
      campos `.barra-op` + `.barra-espacio-op` (ver §3), cargando `{$headerinclude}`.

---

## 6. Estado real e inconsistencias (importante)

- **No hay un design system centralizado.** Los estilos viven inline en cada
  template y los hex se repiten a mano. Existen intentos parciales de tokens con
  CSS custom properties (`--rosa-primary`, `--card-game-*`, `--newspaper-*`,
  `--border`, `--accent-color`), pero conviven varios sistemas distintos sin una
  fuente única. **No asumas que hay un `:root` global** con estas variables:
  antes de usar `var(--x)`, confirmá que el template donde estás lo define.
- **Duplicación de valores:** el mismo naranja aparece como `#ff8900`, `#ff7e00`
  y `#ff7b00` según el template. Al elegir, usá los canónicos de §2.
- **Caso afiliados:** el CSS de la sección de afiliados
  (`op/functions/afiliados_functions.php`) usa colores **translúcidos neutros**
  (`rgba(128,128,128,...)`) a propósito, para ser theme-agnóstico y no romper. Si
  se quiere alinear del todo a esta guía, habría que reemplazarlos por el
  naranja/morado/crema + borde negro de §2. Es una decisión pendiente, no un bug.

---

_Generado analizando `templates/` (mybb_templates, op_templates,
One_Piece_Gaiden_Templates). Si el diseño evoluciona, re-analizá y actualizá los
hex canónicos de §2._
