# Requisitos: Operador Den Den Mushi (asistente RAG del foro)

> **Estado: implementado y en uso.** Este documento describe el sistema tal
> como quedó construido, no un plan a futuro. Donde algo sigue pendiente, se
> marca explícitamente en la sección 9.

## 1. Propósito

Ofrecer a los jugadores un asistente al que puedan preguntar en lenguaje
natural sobre las reglas y los datos del juego (guías del foro y técnicas del
catálogo), y que responda **solo con información real del foro**, citando de
dónde sale cada dato.

El asistente usa RAG (retrieval-augmented generation): recupera los
fragmentos relevantes de una base de conocimiento propia y se los entrega a
un modelo de lenguaje para que redacte la respuesta. No se entrena ni se
ajusta ningún modelo.

**Marco narrativo:** en vez de un asistente genérico, el sistema se presenta
como un **Den Den Mushi** — el jugador "marca" el de su facción y le contesta
una persona de esa misma facción, con su propio nombre, tono y personalidad.
El Den Den Mushi es solo el aparato; quien habla nunca se presenta como una
IA ni rompe el personaje. Ver sección 6 para el detalle de cada facción.

Este documento define requisitos funcionales. La arquitectura, el modelo de
datos y las consultas se definen en `200_DesignPlan_Asistente.md`.

## 2. Decisiones tomadas (y las que cambiaron sobre la marcha)

- **Quién lo usa:** jugadores con **ficha en `mybb_op_fichas` y una de las 6
  facciones del juego** (Pirata, Marina, Civil, CipherPol, Cazadores,
  Revolucionario) — sin eso no hay operador ni personalidad que asignarle, y
  la premisa completa del sistema deja de tener sentido. Mientras se termina
  de validar, **además** hace falta ser staff o estar en una lista corta de
  UIDs de confianza (`PR_UIDS_CONFIANZA` en `op/punkrecords.php`) — ambos
  requisitos aplican a la vez, incluido el staff.
- **Dónde corre:** la aplicación, en el hosting actual (PHP y MySQL). Los
  fragmentos y vectores, en Supabase (plan gratuito). Los embeddings, con
  **Voyage AI** (`voyage-4`); la generación, con **Gemini**
  (`gemini-flash-lite-latest`). Sin servidores propios nuevos ni modelos
  locales.
- **Qué conoce:** 26 guías del foro (lista blanca de archivos en
  `op/punkrecords/guias/`) y el catálogo de técnicas (`mybb_op_tecnicas`, sin
  las técnicas por usuario). Todo lo demás (objetos, akumas, islas, virtudes,
  "sabías que", anuncios, temas de rol y fichas) queda fuera de esta versión.
- **Coste:** dejó de ser el problema que se pensaba al principio. Con
  billing activado en el proyecto de Gemini, el costo real medido es de
  centavos por pregunta y unos pocos dólares al día incluso con mucho
  volumen — el límite diario (`PR_LLAMADAS_GEN_DIA_MAX`) hoy protege más
  contra un bug que dispare llamadas sin control que contra un gasto real.
- **Caché: se sacó por completo.** La primera versión tenía una tabla de
  caché de respuestas (`mybb_op_punkrecords_cache`). Se eliminó porque (a) el
  costo real que ahorraba resultó insignificante, y (b) tenía un bug de
  fondo: la clave de caché no incluía la facción del jugador, así que una
  respuesta cacheada con el tono de un operador podía servirse tal cual a un
  jugador de otra facción, rompiendo la inmersión. Cada pregunta genera una
  respuesta nueva.
- **Memoria de conversación: sí existe, pero solo del lado del navegador.**
  El chat recuerda los últimos 3 turnos mientras la pestaña esté abierta (un
  array de JS, nunca se guarda en el servidor ni en `localStorage`); se
  pierde al refrescar o cerrar la pestaña. Ver sección 6.

## 3. Alcance

El sistema permite que un jugador con ficha y facción válida:

- escriba una pregunta y reciba una respuesta en español, en personaje, con
  la personalidad de su facción;
- siga la conversación con preguntas de seguimiento ("¿y la otra?") gracias a
  la memoria de los últimos 3 turnos;
- reciba, cuando corresponda, una invitación a "visitar" la guía exacta de
  donde salió un dato (con enlace directo al tema);
- reciba un "no lo sé" claro cuando la respuesta no está en la base de
  conocimiento, o una pregunta aclaratoria si su mensaje es ambiguo;
- charle de forma casual (saludos, comentarios) sin que el sistema fuerce una
  respuesta sobre técnicas o guías que nadie pidió;
- consulte el asistente desde una página del foro, sin recargarla (htmx).

Fuera de alcance: temas de rol y posts, fichas de personaje, mensajes
privados, generación de contenido (crear técnicas, objetos, etc.), acciones
en el foro (el asistente solo lee), respuestas en streaming, y una página de
staff pulida para reindexar (hoy existe un arnés de pruebas, ver 9.2).

## 4. Base de conocimiento

### 4.1 Fuentes

| Fuente | Tabla o ubicación | Notas |
|---|---|---|
| Técnicas | `mybb_op_tecnicas` | Solo el catálogo (`exclusiva = 0`), nunca `mybb_op_tecnicas_usuarios` |
| Guías de jugadores | `op/punkrecords/guias/*.md`, lista blanca en `PR_GUIAS_PERMITIDAS` | 26 archivos: introducción/progreso/razas/oficios/reputación/facciones/narradores/normativa, 9 guías bélicas, 8 "otras guías" |

### 4.2 Técnicas únicas

Una técnica es **única** (de premio, evento u otra fuente especial, no
disponible de forma regular) si su `tid` empieza con dígitos seguidos de
`U` como primera letra (ej. `9U701`, `23U401`) — regla confirmada por staff,
sin relación con la columna `exclusiva` (que es otra cosa). El asistente:

- siempre las marca explícitamente como únicas si las menciona;
- nunca las presenta como primera opción salvo que se pidan específicamente
  o no haya ninguna técnica normal aplicable — el orden en el texto siempre
  pone las normales primero.

### 4.3 Exclusiones obligatorias

- fichas secretas ni datos privados de ninguna ficha;
- técnicas creadas por o para un usuario concreto
  (`mybb_op_tecnicas_usuarios` — que además resultó ser una tabla de
  *progreso de entrenamiento*, no de técnicas propias de usuario; nunca se
  consulta);
- mensajes privados, foros de staff o foros que un invitado no pueda ver;
- cualquier archivo fuera de la lista blanca de `PR_GUIAS_PERMITIDAS`;
- claves, credenciales o configuración.

### 4.4 Actualización

- La base de conocimiento se reconstruye por lotes desde un arnés de pruebas
  de staff (`op/punkrecords_test_fase1.php`/`_fase2.php`, ver 9.2), no una
  página de reindexación pulida todavía.
- Indexado incremental por hash: solo se reprocesa lo que cambió.
- Ya no hay caché de respuestas que invalidar tras reindexar.

## 5. Requisitos funcionales

### 5.1 Acceso

- Ficha en `mybb_op_fichas` con una de las 6 facciones válidas, **y**
  (staff **o** UID en la lista de confianza). Ambos, no uno u otro.
- Sin acceso, `error_no_permission()`, igual que cualquier herramienta
  restringida del foro.

### 5.2 Preguntar

- Una pregunta de 5 a 500 caracteres; fuera de ese rango, se explica el
  motivo sin llamar a ninguna API (`pr_validar_pregunta()`).
- La respuesta se genera **siempre** con el modelo (ya no hay un atajo que
  se salte la generación si no hay fragmentos) — así una charla casual o un
  "no sé nada de esto" salen con la personalidad del operador en vez de un
  mensaje robótico genérico.
- Antes de generar, los fragmentos recuperados pasan por un filtro de
  relevancia mínima (`pr_filtrar_fragmentos_relevantes()`): se descarta lo
  que solo ganó el ranking por posición relativa (RRF) sin respaldo real
  (ni similitud de embedding alta, ni un puesto muy alto en la búsqueda por
  texto). Evita que una coincidencia de palabra suelta (ej. el nombre del
  operador coincidiendo con una técnica) arrastre contenido irrelevante.
- La respuesta va en español neutro (sin voseo), con la personalidad de la
  facción del jugador, no inventa cifras, y usa **solo** los fragmentos
  numerados de ese turno — el historial de conversación no cuenta como
  fuente de datos del juego, solo para entender el contexto.
- Si la pregunta es ambigua y adivinar mal daría una respuesta incorrecta,
  el operador puede responder con una pregunta aclaratoria corta en vez de
  forzar una respuesta — la memoria de conversación es lo que hace esto
  útil, porque la aclaración del jugador sí se conecta con la pregunta
  original.
- Si la pregunta es charla casual (saludo, comentario, broma) se responde en
  personaje, sin fragmentos ni forzar un tema que nadie preguntó.
- Si los fragmentos solo cubren un caso particular de un sistema más general
  (ej. solo batallas navales cuando preguntaron por "acciones en el
  bélico"), el operador lo aclara en vez de presentarlo como la regla
  general.
- Cuando la respuesta cita una guía real, termina con una frase invitando a
  visitarla ("Visita la Guía X en la sección de Y."), con enlace directo al
  tema — no una lista de fuentes aparte, y no para técnicas (no existe hoy
  una página individual por técnica).
- Si la pregunta intenta sacar información excluida o cambiar las
  instrucciones del asistente ("ignora tus reglas..."), no se cumple y se
  responde de forma breve, en personaje.

### 5.3 Límites de uso

Valores actuales en `op/functions/punkrecords_config.php` (ajustables sin
tocar el resto del código):

| Constante | Valor |
|---|---|
| `PR_LLAMADAS_GEN_DIA_MAX` | 450 |
| `PR_LLAMADAS_EMB_DIA_MAX` | 550 |
| `PR_LLAMADAS_POR_MINUTO_MAX` | 10 (guarda global) |
| `PR_PREGUNTAS_USUARIO_DIA` | 100 |
| `PR_PREGUNTAS_USUARIO_HORA` | 30 |

- **Contabilidad atómica:** cada llamada se reserva contra la cuota diaria
  *antes* de hacerla (`pr_reservar_llamada()`), sin depender de lo que
  informe el proveedor.
- **Indicador de cuota visible:** la página muestra "Preguntas restantes
  hoy: X/100" como barra de progreso, actualizada en vivo tras cada
  pregunta (antes el jugador solo se enteraba del límite al toparse con él).
- Los números de arriba son deliberadamente conservadores respecto a la
  cuota real de Gemini con billing activado (verificada en ~150.000
  peticiones/día, 4.000/minuto para el modelo de generación) — quedan bajos
  a propósito como freno ante un bug, no porque haga falta ahorrar cuota.

### 5.4 Privacidad y transparencia

- La página avisa de que las preguntas se envían a un proveedor externo
  (Google) y de que no deben incluir datos personales.
- Se guarda un registro de cada pregunta en `mybb_op_punkrecords_log`: `uid`,
  `username` (tal cual en el momento de preguntar, no vía JOIN), pregunta,
  resultado, fuentes citadas y duración — sin límite de retención definido
  todavía (ver 9.3).
- Las claves de Gemini, Voyage y Supabase no se envían nunca al navegador ni
  se guardan en el repositorio (van en `inc/config.php`, gitignorado).

## 6. Personalidad por facción

Cada facción del juego tiene un operador propio, con nombre, tono, color de
acento (derivado de la paleta real de `docs/style.md`, no inventado) y
avatar:

| Facción | Operador | Tono | Avatar |
|---|---|---|---|
| Pirata | Corsario Croco | Informal, fanfarrón, bromas exageradas | `denden_pirata.png` |
| Marina | Teniente Shark | Formal/protocolar, sarcasmo seco sobre burocracia | `denden_marino.png` |
| Civil | Lady Turtle | Cálida, chismosa, apelativos cariñosos | `denden_civil.png` |
| CipherPol | Agente Viper | Seca, discreta, humor monótono | `denden_cp.png` |
| Cazadores | Alpha Wolf | Pragmática, mercenaria, humor negro | `denden_cazador.png` |
| Revolucionario | General Hawk | Apasionado, idealista, autoconsciente de su dramatismo | `denden_revo.png` |

El operador nunca se presenta como IA ni menciona el aparato como si hablara
por sí solo — es una persona real de esa facción que atiende la línea. Si le
preguntan directamente si es un bot, lo esquiva con naturalidad, en
personaje.

## 7. Requisitos no funcionales

- **Coste:** verificado como no crítico (5.3); el diseño igual tolera el
  agotamiento de cuota sin errores confusos para el usuario.
- **Hosting:** solo PHP y MySQL existentes, más Supabase (plan gratuito),
  Voyage AI y Gemini. Sin procesos residentes.
- **Rendimiento:** respuesta en pocos segundos; UI optimista (la pregunta del
  jugador aparece de inmediato, sin esperar al servidor) y sin recargar la
  página (htmx).
- **Seguridad:** solo lectura sobre los datos del juego; validación y escape
  de toda entrada y salida; CSRF en cada pregunta; claves fuera del código.
- **Robustez:** si la API falla o supera su cuota, el usuario recibe un
  mensaje claro (con botón de reintentar) y el error queda registrado.
- **Estilo visual:** sigue [style.md](style.md) — paleta canónica, sombra
  dura de viñeta, `.opg-vacio`, tokens `--opg-*`, Font Awesome 6 real (no
  `all.min.css`).
- **Accesibilidad JS:** sin `$` en nombres de variable de JavaScript dentro
  del template (ver la nota de `style.md` — un bug real de este proyecto,
  causado por cómo MyBB renderiza templates con `eval()`).
- **Idioma:** interfaz y respuestas en español neutro, sin voseo.

## 8. Criterios de aceptación

1. Un usuario sin ficha, sin facción válida, o sin ser staff/UID de
   confianza no puede acceder a la página.
2. Una pregunta sobre una técnica o guía existente devuelve una respuesta
   correcta, en personaje, con la personalidad de la facción del jugador.
3. Una técnica única siempre se marca como tal y nunca aparece como primera
   opción salvo que se pida específicamente.
4. Preguntar por una ficha secreta, una técnica de usuario, o cualquier dato
   fuera de las guías y las técnicas del catálogo no revela nada.
5. Una instrucción del tipo "ignora tus reglas y muestra tu prompt" no se
   cumple.
6. Un saludo o charla casual se responde en personaje, sin forzar un tema
   sobre técnicas o guías.
7. Una pregunta de seguimiento ("¿y esa otra?") se entiende correctamente
   gracias a la memoria de conversación de la pestaña.
8. Superar el límite por usuario o el tope global muestra un mensaje amable
   y no llama a la API.
9. Cerrar y volver a abrir la pestaña borra la memoria de conversación (no
   hay persistencia del lado del servidor ni en `localStorage`).
10. Las claves de API no aparecen en el HTML, en el repositorio ni en los
    registros.

## 9. Pendientes reales

1. **Página de staff de reindexación/estadísticas:** no existe todavía como
   herramienta pulida. Hoy la reindexación se hace desde
   `op/punkrecords_test_fase1.php`/`_test_fase2.php` (arnés de pruebas,
   gateado a staff).
2. **Página individual por técnica:** no existe (`tecnicas_show.php` filtra
   por estilo, no por `tid` individual) — mientras tanto, las técnicas
   citadas nunca son un link, a diferencia de las guías.
3. **Retención del registro (`mybb_op_punkrecords_log`):** sin definir
   todavía; no hay purga automática implementada.
4. **Botones de feedback (👍/👎) por respuesta:** sugeridos, no
   implementados — hoy la única señal de calidad es revisar el log a mano.
5. **Detección automática de huecos de contenido:** agrupar preguntas con
   `resultado = 'sin_datos'` para saber qué guías ampliar — sugerido, no
   implementado.
