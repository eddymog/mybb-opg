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

> La URL externa de arriba es la que usan algunas plantillas viejas, pero ya no es la
> fuente real: el CSS compilado del tema (`cache/themes/theme3/fonts.css`) carga las
> fuentes en local, `/images/op/fonts/moon_get-Heavy.otf`. Para código nuevo, usá la
> ruta local (más rápida y no depende de que ese dominio siga en pie).

**Regla de oro del titular** (plantillas existentes; para código nuevo ver §7) — `moonGetHeavy` casi nunca va "pelado": siempre
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
- [ ] Código nuevo: usá las variables de `jscripts/opg-tokens.css` (ya cargado por
      `{$headerinclude}`, no hace falta un `<link>` propio) y su botón único `.btn-op`
      en vez de copiar hex o inventar otra clase de botón (ver §7).
- [ ] Herramienta de staff nueva: mirá `op_upload` o `staff_consola_mod` como referencia,
      no `op/staff/banners.php` — ese es el que quedó atrás, con estilos propios en vez
      de `opg-tokens.css` (ver §7 "Dónde se usa ya").

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
- **Hay un punto de partida para centralizar esto: ver §7.** Sus variables y clases
  están disponibles en todo el foro (se cargan desde `headerinclude.html`), pero
  nada obliga a usarlas — no es una migración de lo que ya existe, es la opción para
  lo que se escriba de ahora en adelante.

---

## 7. Sistema de tokens (para código nuevo)

Los §§1-6 son una guía *descriptiva*: documentan lo que el foro ya hace, hex sueltos
para copiar a mano. `jscripts/opg-tokens.css` es el mismo contenido pero como
variables CSS reales — un primer paso *prescriptivo*, sin migrar nada de lo que ya
existe (ver §6). Ya lo carga `headerinclude.html`, así que sus variables y clases
están disponibles en **todas las páginas del foro**, no solo en las que las usan hoy
(`op_upload`, `staff_consola_mod`, y parcialmente `banners.php`) — nadie tiene que
agregar un `<link>` propio.

```html
<!-- En headerinclude.html, después de {$stylesheets}. El ?ver= es manual — ver
     "Cache" abajo — y ya está puesto ahí, no hace falta repetirlo en cada página. -->
<link rel="stylesheet" href="/jscripts/opg-tokens.css?ver=6">
```

Si estás escribiendo algo standalone que **no** pasa por `{$headerinclude}` (poco
común — las páginas de `op/staff/` sí pasan), cargalo a mano con el mismo `?ver=`.

### Cache

El dominio está detrás de Cloudflare, que cachea `opg-tokens.css` de forma agresiva e
**ignora el `Cache-Control` del origin** (confirmado con `curl -D` contra el sitio
real: `cache-control: max-age=2592000`, 30 días). `jscripts/.htaccess` igual le pone
`Cache-Control: no-cache` al archivo, como respaldo para el caso de que algo le pegue
directo al origin sin pasar por Cloudflare — pero el mecanismo real es otro.

**Cuando edites `opg-tokens.css`, subí el `?ver=N` en dos lugares y nada más:** el
comentario "VERSIÓN ACTUAL" del propio archivo, y el `<link>` en `headerinclude.html`
(mismo patrón que `jquery.js?ver=1823` ahí al lado). Cambiar la URL revienta cualquier
caché sin depender de que Cloudflare respete nada. Como el archivo se carga una sola
vez desde `headerinclude.html`, es una edición en un solo lugar — nunca "cada página
que lo usa".

El servidor tampoco declara `charset` en `text/css`, así que el navegador adivina la
codificación del texto. Arreglado con `AddCharset UTF-8 .css .svg` en
`jscripts/.htaccess` más `@charset "UTF-8";` como primera línea del archivo — si en
algún momento aparecen tildes rotas (`Ã©`, `â€”`) en otro CSS del foro, es la misma causa.

### Qué resuelve

La guía documenta valores canónicos, pero en la práctica cada plantilla los
reescribe a mano y con el tiempo aparecen variantes: 12 radios de esquina distintos,
10 combinaciones de `transition` casi idénticas, tres sistemas de botón que hacen lo
mismo (`.btn`, `.testDraw`/`.realDraw`/`.blockedDraw`, y el `.btn-op` que salió de
`op/staff/banners.php`). Nada de eso está mal — es lo esperable sin un lugar único
donde poner los valores. `opg-tokens.css` es ese lugar.

### Colores

Los mismos hex canónicos de §2, con nombre: `--opg-naranja`, `--opg-naranja-hover`,
`--opg-morado`, `--opg-morado-cta` (la llamada a la acción grande, `.testDraw`),
`--opg-crema`, `--opg-tinta`, `--opg-rojo-error`, `--opg-verde-exito`,
`--opg-gris-bloqueado`, etc. Cero cambio visual: es el mismo color con nombre en vez
de hex pegado.

### Degradados

El foro ya tiene una convención de degradados bastante consistente (`grep` sobre
`templates/` lo confirma): siempre **dentro de la misma familia de color** — más
oscuro o más claro del mismo tono, nunca un cambio de matiz — y casi siempre a
**135°**. Tres pares que ya se repetían sin estar tokenizados:

| Par | Uso | Frecuencia |
|---|---|---|
| `--opg-crema-degradado-inicio/fin` (`#fff3e0 → #ffe8c2`) | crema base | 12 plantillas |
| `--opg-crema-oscura-degradado-inicio/fin` (`#ffe8c2 → #ffdca3`) | crema un paso más oscuro | 7 plantillas |
| `--opg-morado-claro` → `--opg-morado-claro-degradado-fin` (`#e0d2fd → #d7b5ff`) | paneles morados suaves | 5 plantillas |
| `--opg-naranja-quemado-degradado-inicio/fin` (`#7e3a00 → #ff7e00`) | acentos naranjas con más peso | 5 plantillas |

```css
background: linear-gradient(135deg, var(--opg-crema-degradado-inicio), var(--opg-crema-degradado-fin));
```

**Dónde sí ayuda:** la franja de acento de `.opg-card` es un degradado real
(izquierda→derecha, el acento contra una versión más clara de sí mismo vía
`color-mix(in srgb, var(--opg-card-acento) 70%, white)`), no el truco de "color
contra sí mismo" de antes. Funciona con cualquier `--opg-card-acento` que una página
pase, sin pedirle también un "acento-claro" a mano — nada que dependa de que alguien
se acuerde de dar un segundo valor.

**Dónde NO ayuda — y por qué no está en ningún lado más:**
- **Ningún degradado cruza de matiz** (naranja a morado, azul a rosa): es la marca
  registrada del "SaaS genérico" y tira directamente en contra del trabajo plano de
  alto contraste que ya se hizo (`.opg-card`, la consola). Si algo pide "modernizar",
  la respuesta de esta guía es la tarjeta viñeta y la sombra desplazada, no un wash
  de color.
- **Sin degradados en texto** (fill de texto en degradado): pelea directo con la regla
  de `moonGetHeavy` + contorno negro sólido de §1, que existe justamente para que el
  texto se lea sin ambigüedad.
- **No en todas las superficies a la vez.** Lo que hace que un panel se lea como un
  panel separado (identidad de viñeta de cómic) es que sea un color limpio con borde
  negro; si todo tiene un degradado sutil, los paneles dejan de distinguirse entre sí.

### Escalas nuevas

No estaban documentadas porque no existían como regla, solo como costumbre. Colapsan
la sopa de valores sueltos a 2-3 opciones:

| Escala | Variables | Reemplaza |
|---|---|---|
| Radio | `--opg-radio-sm` (6px) · `--opg-radio-md` (10px) · `--opg-radio-lg` (18px) | Los 12 valores de §3 (3/4/5/6/8/10/12/15/20/25px) |
| Sombra | `--opg-sombra-dura` (glow negro) · `--opg-sombra-suave` (drop shadow) | Las variaciones de "casi el mismo glow" |
| Movimiento | `--opg-rapido` (.2s, hover) · `--opg-lento` (.35s, paneles) | Los 10 `transition` de §4 |
| Espaciado | `--opg-espacio-1` a `--opg-espacio-6` (grid de 4px, 4→24px) | Paddings/márgenes sueltos sin motivo aparente |
| Foco | `--opg-foco` (halo dorado) | No existía: los `:focus` que hay en 25 plantillas son de click, no de teclado |
| Sombra de viñeta | `--opg-sombra-offset` (`4px 4px 0` negro) · `--opg-sombra-offset-hover` (`6px 6px 0`) | El glow difuso en tarjetas: la sombra dura sin difuminar es más "cómic" y más limpia |

### Botón único (`.btn-op`)

Converge los tres sistemas de botón en uno, con tamaño y color como modificadores:

```html
<button class="btn-op btn-op--primario">Guardar</button>              <!-- naranja, tamaño normal -->
<button class="btn-op btn-op--sm btn-op--peligro">Eliminar</button>   <!-- rojo, chico -->
<button class="btn-op btn-op--hero btn-op--secundario">ENTREGAR</button> <!-- morado cta, grande -->
<button class="btn-op btn-op--primario" disabled>Bloqueado</button>  <!-- gris, sin hover -->
```

Colores: `--primario` (naranja), `--secundario` (morado cta — usalo cuando la página
ya tiene barras naranjas, misma excepción que en el checklist de §5), `--exito`,
`--peligro`. Tamaños: por defecto, `--sm` (inline, dentro de tarjetas) y `--hero`
(la acción principal de la página, tipo "ENTREGAR"/"SUBIR").

### Tarjeta viñeta (`.opg-card`) y chips (`.opg-chip`)

Para listados de cosas (herramientas, elementos, resultados), en vez de la tarjeta
con barra naranja `.barra-op` de §3:

- **`.opg-card`**: fondo `--opg-crema-clara`, borde de tinta de 2px y **sombra dura
  desplazada** (`--opg-sombra-offset`), como una viñeta de cómic. Si es un `<a>`, se
  "levanta" al pasar el ratón — se corre y gira un poco (`translate(-3px,-5px)
  rotate(-1.2deg)`) y la sombra crece, para sentirse una tarjeta física levantada de
  la mesa, no solo un elemento que cambia de lugar. Lleva una franja superior
  de color y un icono cuadrado con el **acento** del grupo, que se cambia con
  `--opg-card-acento` (y `--opg-card-acento-texto` para el color del icono) en la
  tarjeta o en un contenedor. `.opg-card--media` tiene el mismo hover.
- **`.opg-chip`**: acción secundaria compacta en Inter, en forma de píldora con borde
  de tinta; naranja al pasar el ratón. Para varias acciones dentro de una tarjeta.
  La acción principal de una página sigue siendo `.btn-op`.
- **`.opg-card--media`**: variante sin el `padding` ni el icono+título de `.opg-card`,
  para grillas de miniaturas (imágenes, archivos) donde el contenido tiene que ir a
  sangre en el borde. Mismo marco (borde, radio, sombra desplazada, hover) — ver el
  comentario en `jscripts/opg-tokens.css` para el ejemplo completo.
- **`.opg-vacio`**: para cuando un listado no tiene nada que mostrar todavía
  ("No hay banners activos", "Todavía no hay imágenes subidas"…). Reemplaza el
  `.vacio` que `banners.php` y `op_upload` tenían cada uno por su lado.
- **`.opg-volver`**: el enlace "← Consola" que repiten las herramientas de staff.
  Reemplaza `.banners-volver` (que había quedado en `moonGetHeavy`) y `.hosting-volver`
  — iba camino a una tercera copia con la próxima herramienta.

**`.btn-op` vs. `.opg-chip` no es "¿navega o no?", es jerarquía:** `.btn-op` es *la*
acción por la que existe la página (subir, guardar, entregar) — una o dos por página,
como mucho. `.opg-chip` es todo lo demás, aunque técnicamente lleve a otra URL: enlaces
a otras herramientas (`staff_consola_mod`), copiar o paginar sin navegar (`op_upload`).

```html
<section style="--opg-card-acento: var(--opg-morado);">
  <div class="opg-card">
    <span class="opg-card__cabecera">
      <span class="opg-card__icono"><i class="fa-solid fa-box-open"></i></span>
      <span class="opg-card__titulo">Inventario</span>
    </span>
    <span class="opg-card__desc">Lo que tiene asignado un personaje.</span>
    <span><a class="opg-chip" href="…">Objetos</a> <a class="opg-chip" href="…">Técnicas</a></span>
  </div>
</section>
```

Reglas que salieron de rediseñar la consola de staff:

- **Menos cajas anidadas.** Un grupo es un título con raya de acento, no otra caja
  con borde: las tarjetas se apoyan directamente en el marco de tres fondos.
- **Una acción → la tarjeta entera es el enlace.** Varias → fila de `.opg-chip`.
- **Un color de acento por grupo** (de la paleta de §2), no naranja para todo: el
  naranja queda para la marca y el título de la página.
- **El morado de llamada a la acción es para lo urgente**, no para cada botón.
- **Iconos:** Font Awesome 6 (`fa-solid fa-*`). No lo carga el tema: la página que lo
  use enlaza `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css`,
  como ya hace `op_mercado_negro`.
- **El tema centra todo el texto** (`text-align: center` en el `body` de
  `global.css`): en páginas de tarjetas, poné `text-align: left` en el contenedor.
- **Enlaces:** las reglas `a:link`/`a:visited` del tema ganan a una clase simple. Para
  enlaces con estilo propio usá `a.clase:link, a.clase:visited` (como ya hacen
  `.opg-card` y `.opg-chip`) o se verán en azul.

### Contraste

Nadie había calculado esto antes; son los números reales (fórmula WCAG, sobre las
combinaciones que ya se usan). AA pide 4.5:1 para texto normal y 3.0:1 para texto
grande/negrita:

| Combinación | Ratio | AA texto normal | AA texto grande |
|---|---|---|---|
| `--opg-morado-texto` sobre crema | 7.96–8.33 | ✅ | ✅ |
| `--opg-marron` sobre crema | 14.30 | ✅ | ✅ |
| `--opg-gris-texto` sobre crema | 5.65 | ✅ | ✅ |
| `--opg-morado-cta` con texto blanco (`.btn-op--secundario`) | 13.65 | ✅ | ✅ |
| `--opg-rojo-error` con texto blanco (`.btn-op--peligro`) | 4.53 | ✅ (al límite) | ✅ |
| `--opg-morado` **como texto** sobre crema | 3.52 | ❌ | ✅ |
| `--opg-naranja` con texto blanco (`.btn-op--primario`) | 2.38 | ❌ | ❌ |
| `--opg-verde-exito` con texto blanco (`.btn-op--exito`) | 2.87 | ❌ | ❌ |

Dos conclusiones, no una:

- **`--opg-morado` (`#9664e0`) no es para texto de cuerpo** — es para bordes, acentos
  o texto grande. Para texto real sobre crema, `--opg-morado-texto` (`#6c10ab`), que
  es justo el que ya usan `.opg-chip`, `.opg-volver` y los enlaces de "Ver →".
- **`.btn-op--primario` y `.btn-op--exito` fallan el contraste en frío**, y aun así son
  legibles en la práctica: el borde de tinta de 2px + el `text-shadow` negro de
  `.btn-op` hacen el trabajo que el color no hace — es la misma firma de "contorno
  negro" de §1, no un accidente. La consecuencia práctica es que ese borde y esa
  sombra no son decorativos en estos dos casos: si alguna vez se simplifica `.btn-op`
  quitándoselos "porque total no se nota", estos dos botones se vuelven realmente
  difíciles de leer. Si se agrega una variante de color nueva, mantené el mismo borde
  y la misma sombra en vez de asumir que alcanza con el `color: #fff`.

### Texturas y micro-animaciones (para código nuevo)

Nacen de un muestrario de ideas armado para modernizar el look sin perder el
espíritu (manga/cómic, "peso de tinta") — ver §6, ya no hay que copiar los valores
a mano, están en `jscripts/opg-tokens.css`:

- **`--opg-textura-trama`**: trama de puntos (halftone) vía `radial-gradient`, sin
  imagen. Se combina con el fondo propio de cada componente, no lo reemplaza:
  `background: var(--opg-textura-trama), var(--opg-crema-clara);`. Sirve para que un
  panel grande deje de sentirse un color plano de design-system y pase a sentirse
  "impreso".
- **`--opg-recorte-rasgado`**: un `clip-path` de borde irregular ("mapa rasgado"),
  para **1-2 paneles destacados como mucho** — en todos lados deja de leerse como
  "destacado" y pasa a ser ruido visual.
- **`.opg-grano`**: grano de papel sutil, filtro SVG (`jscripts/opg-grano.svg`,
  referenciado por URL — no hace falta pegar un `<svg>` oculto en cada página). El
  elemento necesita `position: relative` (o `absolute`/`fixed`); sumale
  `overflow: hidden` si tiene esquinas redondeadas. Muy sutil a propósito: es
  textura de fondo, no un efecto que se note a simple vista.
- **`.opg-en-curso`**: borde punteado animado ("marching ants"), para lo que se
  está siguiendo activamente — un reset de build pendiente, un log procesándose.
  No es error ni éxito, es "todavía en marcha".

Quedaron afuera de esta tanda (documentadas igual, para cuando se necesiten): un
botón con hover de "tinta" (`clip-path` creciendo desde el centro, pensado como
modificador `.btn-op--tinta` que se suma a un color existente) y una ráfaga de
éxito junto al mensaje de confirmación (factible sin JS porque `banners.php` y
`op_upload` recargan la página entera por Post/Redirect/Get — el elemento se monta
una sola vez por carga, así que una animación no-`infinite` alcanza). Las dos
necesitan elegir dónde usarse (qué botones, qué mensajes de éxito), no son solo
CSS suelto como las cuatro de arriba.

### Tipografía en código nuevo: `moonGetHeavy` solo para lo relevante

En los §§1-6, `moonGetHeavy` está en casi todo (~1900 apariciones) y siempre con
contorno negro. Para código nuevo:

- **`moonGetHeavy`**: título de la página, títulos de sección, títulos de tarjeta y
  cifras destacadas (contadores). Nada más.
- **Inter** (`InterRegular`/`InterMedium`): descripciones, acciones secundarias
  (`.opg-chip`), etiquetas, formularios y enlaces del tipo "Ver →".
- **Contorno negro solo sobre fondo de color** (naranja, morado). Sobre fondos claros,
  `moonGetHeavy` va en oscuro (`--opg-ciruela`) y sin contorno: ahí el contorno no
  aporta legibilidad, solo ruido.

### Dónde se usa ya

Desde que se agregó a `headerinclude.html`, el archivo llega a **toda página del
foro** — lo de abajo es qué páginas además *usan* sus variables/clases, no cuáles lo
cargan (eso ya lo hacen todas):

- `staff_consola_mod` (la portada de `/op/staff/`): `.opg-card` (con la franja de
  acento en degradado vía `color-mix()` y el tilt al hacer hover), `.opg-chip`,
  acentos por grupo, panel de contadores, la regla tipográfica de §7 y el halftone
  de la cabecera. Es la referencia de este sistema.
- `op_upload` (hosting de imágenes): `.btn-op`, `.opg-chip` para copiar URL/`[img]` y
  paginar, `.opg-vacio`, `.opg-volver`. Su galería (`.subida`) usa los tokens de
  sombra desplazada a mano porque se escribió antes de que existiera
  `.opg-card--media` — es candidata a migrar, no hace falta apurarlo.
- `op/staff/banners.php`: solo `.opg-volver` y `.opg-vacio` — el resto de la página
  sigue con estilos propios y `.barra-op` de §3, no `.opg-card`/`.opg-chip`. Es el
  más viejo de los tres y el que **no** hay que copiar como referencia para una
  herramienta nueva (ver checklist §5).
- `--opg-textura-trama`, `--opg-recorte-rasgado`, `.opg-grano` y `.opg-en-curso`
  están en `opg-tokens.css` pero **ninguna página los usa todavía** — son para la
  próxima vez que un panel destacado, una textura de fondo o un estado "en curso"
  hagan falta.

### Qué NO hace este archivo

- **No cambia nada visualmente por sí solo.** Se carga en todo el foro, pero una
  plantilla vieja que nunca referencia sus variables o clases se ve exactamente
  igual que antes — cargar el archivo y usarlo son cosas distintas.
- No declara `@font-face`: asume que `{$headerinclude}` ya cargó las fuentes (páginas
  del foro) o que la propia página declara las suyas (páginas standalone que no pasan
  por `{$headerinclude}`, si alguna vez hace falta escribir una).
- No reemplaza `.btn`, `.testDraw`/`.realDraw`/`.blockedDraw` ni `.barra-op` donde ya
  se usan — esos siguen siendo válidos y están documentados en §3. Es la opción para
  lo que se escriba de ahora en adelante, no una migración de lo viejo.

---

_Generado analizando `templates/` (mybb_templates, op_templates,
One_Piece_Gaiden_Templates). Si el diseño evoluciona, re-analizá y actualizá los
hex canónicos de §2._
