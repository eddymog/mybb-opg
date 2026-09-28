# Operador Den Den Mushi (RAG): plan de diseño

> Depende de: [100_Requirements_Asistente.md](100_Requirements_Asistente.md)
>
> **Estado: implementado.** Describe la arquitectura tal como quedó
> construida. Las secciones marcadas "(pendiente)" son la excepción.

## 1. Objetivo

Un asistente RAG presentado como un Den Den Mushi por facción. La aplicación
(página, límites, registro) corre en el hosting actual con PHP y MySQL; los
fragmentos y sus vectores viven en Supabase; **Voyage AI** calcula los
embeddings y **Gemini** redacta las respuestas.

## 2. Estado real del código (referencia rápida)

- `op/punkrecords.php` — página y endpoint de preguntas (jugadores).
- `op/functions/punkrecords_config.php` — lista blanca de guías, límites,
  nombres de modelo.
- `op/functions/punkrecords_rag.php` — fragmentos, prompt de sistema,
  personas por facción, filtro de relevancia, citas.
- `op/functions/punkrecords_proveedores.php` — clientes HTTP de Gemini y
  Voyage AI.
- `op/functions/punkrecords_supabase.php` — cliente de Supabase (buscar,
  subir por lotes, listar hashes, eliminar).
- `op/functions/punkrecords_limites.php` — validación, límites, registro,
  render de turnos del chat y de la cuota.
- `templates/One_Piece_Gaiden_Templates/op_punkrecords.html` — plantilla del
  chat (htmx, sin Alpine.js — ver 8.4).
- `op/punkrecords_test_fase1.php` / `_test_fase2.php` — arnés de pruebas e
  indexado por lotes, gateado a staff (reemplaza la "página de staff" que el
  diseño original planeaba como algo aparte y más pulido — ver 100_Requirements §9).
- `op/functions/punkrecords_mvp.php` — versión MVP original, **huérfana**
  (nada la referencia desde que se reescribió `punkrecords.php`); candidata a
  borrar.
- `op/legacy/` — scripts de exportación usados una sola vez para extraer el
  texto de las guías desde la base de datos (truncado por phpMyAdmin);
  movidos ahí en vez de borrados, por si hace falta reextraer algo.

## 3. Decisiones de arquitectura

### 3.1 Qué vive dónde

| Componente | Dónde | Por qué |
|---|---|---|
| Fragmentos, vectores y búsqueda | **Supabase** (Postgres + `pgvector`) | Búsqueda de texto en español y por similitud dentro de la base, sin cargar vectores en PHP |
| Registro de preguntas y límites por usuario | **MySQL del foro** (`mybb_op_punkrecords_log`, `mybb_op_punkrecords_cuota`) | Contienen el `uid` y las preguntas de los jugadores; necesitan contadores atómicos de baja latencia |
| Aplicación (páginas, prompts, límites) | Hosting actual, PHP | Sin servicios propios nuevos |
| Embeddings | **Voyage AI** (`voyage-4`, 1024 dim) | Mejor soporte de español documentado y mejor costo que Gemini Embedding; **migrado** desde `gemini-embedding-001` (768 dim) — los vectores de un modelo y otro no son compatibles, migrar obligó a reindexar todo |
| Generación de respuestas | **Gemini** (`gemini-flash-lite-latest`) | Sin cambios desde el diseño original |

PHP habla con Supabase y con los dos proveedores por HTTP (`curl`); no hace
falta ningún driver de Postgres en el hosting.

**Ya no hay caché de respuestas.** La tabla `mybb_op_punkrecords_cache` y las
funciones `pr_cache_leer()`/`pr_cache_guardar()`/`pr_normalizar_pregunta()`
se eliminaron: el ahorro de costo era insignificante frente al gasto real
medido, y la clave de caché no distinguía por facción del jugador, así que
una respuesta cacheada con el tono de un operador podía filtrarse a un
jugador de otra facción.

### 3.2 Recuperación híbrida, filtrada por relevancia real

La función SQL `buscar_hibrido(consulta, embedding, k)` en Supabase
(`docs/punkrecords_supabase.sql`):

1. Busca por texto (`tsvector` en español, con las palabras interrogativas
   quitadas de la consulta) los 20 mejores fragmentos.
2. Busca por similitud coseno (índice HNSW) los 20 mejores.
3. Los fusiona con Reciprocal Rank Fusion (RRF) y devuelve los `k` mejores
   (10 por defecto), con `puntaje` (RRF), `similitud` (coseno real) y
   **`rank_texto`** (posición en el ranking por texto, o `null` si no entró
   por ahí — agregado en una migración posterior).

**El problema que `rank_texto` resuelve:** el `puntaje` de RRF es
puramente posicional — algo puede "ganar" el ranking solo por ser lo menos
malo entre lo peor, sin relación real con la pregunta. Caso real: la
pregunta "¡Hola Teniente Shark!" (un saludo) trajo técnicas de tiburones
solo porque "Shark" coincidía con el nombre del operador. `pr_filtrar_fragmentos_relevantes()`
(en `punkrecords_rag.php`) descarta cualquier fragmento que no tenga **ni**
similitud de embedding alta (`PR_SIMILITUD_MINIMA = 0.35`) **ni** un puesto
muy alto en el texto (`PR_RANK_TEXTO_MAXIMO = 3`) — se aplica después de
`buscar_hibrido()`, antes de armar el contexto para el modelo. Si el campo
`rank_texto` no viene (Supabase sin migrar), el filtro no descarta nada, para
no romper un despliegue a medias.

Si la API de embeddings no responde, se llama a `buscar_hibrido` con el
embedding vacío y solo se usa la rama de texto.

### 3.3 Proveedores

`op/functions/punkrecords_proveedores.php`:

- `pr_embeber(array $textos, string $tarea): array` — Voyage AI
  (`voyage-4`), hasta 1.000 textos por lote, `input_type` mapeado desde
  `RETRIEVAL_DOCUMENT`/`RETRIEVAL_QUERY` a `document`/`query`.
- `pr_generar($pregunta, $fragmentos, $faccion, $historial): array` — Gemini
  `generateContent`, con `systemInstruction` (prompt de sistema con la
  persona de la facción), `contents` armado como turnos reales
  `user`/`model` a partir del historial de conversación más el turno actual,
  `generationConfig` con temperatura 0.2 y ~1000 tokens de salida.

Reintentos cortos dentro de una misma petición HTTP (Cloudflare corta
peticiones a los ~100s); un reintento más largo ante un 429 es
responsabilidad de quien llama en una petición nueva, no de esperar dentro
de la misma.

**Verificado con Voyage y Gemini reales:** con billing activado en el
proyecto de Gemini, la cuota de Tier 1 es de ~150.000 peticiones/día y
~4.000/minuto para `gemini-flash-lite` — muy por encima de los límites
conservadores que sigue usando `punkrecords_config.php` (450/día). El costo
medido por pregunta ronda los $0.0015 (entrada ~$0.30/millón de tokens,
salida ~$2.50/millón).

### 3.4 Acceso a Supabase, Voyage y Gemini

- Claves en `inc/config.php` (`$config['punkrecords']`), gitignorado; nunca
  en el navegador ni en el repositorio.
- Supabase: RLS activa en todas las tablas, sin políticas públicas; solo la
  clave `service_role` accede.
- Voyage: cabecera `Authorization: Bearer`. Gemini: cabecera
  `x-goog-api-key` (no en la URL).

## 4. Modelo de datos

### 4.1 Supabase: `punkrecords_fragmentos` y `buscar_hibrido`

Definidos en `docs/punkrecords_supabase.sql`, con dos migraciones aplicadas
sobre el diseño original: `embedding vector(1024)` (antes 768, por el cambio
de proveedor) y la función `buscar_hibrido` recreada para devolver
`rank_texto` (3.2). El resto del esquema (extensión `vector`, tabla, índices
GIN/HNSW, RLS sin políticas) no cambió respecto al diseño original.

### 4.2 `mybb_op_punkrecords_log`

`id`, `uid`, `username` (VARCHAR 100, guardado tal cual al momento de
preguntar — no vía JOIN, así el registro conserva el nombre de ese momento
aunque el jugador se lo cambie después), `pregunta` (VARCHAR 500),
`resultado` (`ok`, `sin_datos`, `limite`, `error` — **ya no existe** el
valor `cache`), `fuentes` (VARCHAR: fuente:ref separados por coma), `ms`,
`creado_en`. Sin retención definida todavía (100_Requirements §9.3).

### 4.3 `mybb_op_punkrecords_cuota`

Sin cambios respecto al diseño original: `dia` (DATE, PK), `llamadas_gen`,
`llamadas_emb`, `llamadas_staff`. Los límites por usuario se calculan
contando filas de `mybb_op_punkrecords_log` (sin tabla extra).

### 4.4 `mybb_op_punkrecords_cache` — eliminada

`DROP TABLE IF EXISTS mybb_op_punkrecords_cache;` al final de
`docs/punkrecords_migration.sql`. Ver 3.1 para el porqué.

## 5. Base de conocimiento

### 5.1 Construcción de fragmentos

- **Técnicas** (`pr_fragmentar_tecnicas()`): una por fila de
  `mybb_op_tecnicas` con `exclusiva = 0`. El texto lleva el `tid`, nombre y
  todos los campos relevantes al principio; si el `tid` matchea `^\d+U`
  (técnica única), la cabecera lo marca explícitamente. Se divide por
  párrafos si supera ~1.500 caracteres, repitiendo `tid`/nombre en cada
  continuación.
- **Guías** (`pr_fragmentar_guias()`): 26 archivos de la lista blanca, cada
  uno con un `nivel_max` configurable (por defecto 4, hasta `####`) que
  controla cuán fino se fragmenta. `b01_guia_belica.md` usa `nivel_max = 2`
  a propósito: sus reglas de combate están muy interconectadas (bloques de
  acción, movimiento, reflejos) y fragmentar más fino las separaba de una
  forma que empeoraba el razonamiento sobre interacciones entre reglas —
  confirmado con una pregunta real del conjunto de pruebas que solo pasó
  después de este ajuste. El resto de guías (catálogos más independientes:
  estilos, oficios, objetos) usa el default más fino, porque ahí sí ayuda
  aislar cada ítem en su propio fragmento.

### 5.2 Indexación incremental

Sin cambios de diseño respecto a la versión original: hash SHA-1 del texto
(más título y URL, para que un cambio de metadata también fuerce
reindexado), comparación contra lo ya subido, lotes de hasta 100 textos,
`upsert` por `(fuente, ref, parte)`, eliminación de lo que ya no existe en
el origen. Lo que cambió es **dónde vive esto operativamente**: no hay una
página de staff dedicada todavía (100_Requirements §9.1); el indexado se
corre desde `op/punkrecords_test_fase1.php`/`_test_fase2.php`, pensados
para peticiones cortas y resumibles (una petición por lote, el propio
cliente pide el siguiente) por el límite de ~100s de Cloudflare.

## 6. Flujo de una pregunta

1. **Acceso:** ficha con facción válida **y** (staff o UID de confianza) —
   ver 100_Requirements §5.1. Sin eso, `error_no_permission()`.
2. **CSRF:** `verify_post_check()`, con el segundo parámetro `true` para que
   un token vencido no reviente con la pantalla de error genérica de MyBB en
   medio de un fragmento htmx — se maneja como cualquier otro error de la
   pregunta.
3. **Validación:** 5 a 500 caracteres, sin caracteres de control
   (`pr_validar_pregunta()`).
4. **Memoria de conversación:** el cliente manda el historial (últimos 3
   turnos) en un campo oculto; el servidor lo vuelve a sanear y acotar
   (`pr_parsear_historial()`) sin confiar en lo que mande el navegador.
5. **Límites:** por usuario (100/día, 30/hora, `pr_limite_usuario()`) y
   guarda global por minuto (`pr_limite_global_minuto()`).
6. **Recuperación:** embedding de la pregunta con Voyage
   (`RETRIEVAL_QUERY`), una llamada a `buscar_hibrido` (texto + similitud +
   RRF, `k = 10`), y el filtro de relevancia real (3.2). Puede devolver una
   lista vacía — eso ya **no** corta el flujo con un mensaje fijo (ver
   siguiente paso).
7. **Generación:** siempre se llama a Gemini, incluso con el contexto de
   fragmentos vacío — el prompt de sistema (7) distingue "no encontré
   información" de "esto es charla casual" o "esto es ambiguo, pregunto
   antes de adivinar", y ninguno de esos casos debería sonar a mensaje
   robótico genérico.
8. **Markdown → HTML:** `pr_markdown_a_html()` escapa todo primero y recién
   después convierte `**negrita**`/listas a etiquetas reales — el modelo
   genera Markdown por naturaleza; antes se mostraba tal cual (asteriscos
   literales en pantalla).
9. **Citas:** `pr_insertar_citas()` quita las marcas `[F#]` del texto
   (`insertar_notas = false`) y devuelve qué fragmentos se citaron
   realmente, en el orden de aparición. `pr_renderizar_sugerencia_fuentes()`
   arma una frase natural por cada guía citada ("Visita la Guía X en la
   sección de Y."), con link real solo si es guía (las técnicas no tienen
   página individual todavía). No es una lista de notas al pie — se
   descartó ese diseño por sentirse "a documento técnico" en vez de a
   charla con un personaje.
10. **Registro:** `pr_registrar()` guarda uid, username, pregunta,
    resultado, fuentes y duración, siempre (ya no hay resultado `cache` que
    saltear).
11. **Salida:** el servidor detecta si la petición vino de htmx
    (`HTTP_HX_REQUEST`) y responde solo con el turno nuevo (para agregarlo al
    chat sin recargar) más un `<span hx-swap-oob="true">` con la cuota
    actualizada. Sin JS, el mismo POST cae al render completo de la página
    con el mismo resultado.

## 7. Prompt de sistema

`pr_instrucciones_sistema($faccion)` arma el prompt en secciones (más fácil
de mantener que un párrafo corrido; cada sección se puede ajustar sin releer
todo el bloque):

- **PERSONAJE:** nombre del operador, que el Den Den Mushi es solo el
  aparato, que nunca se presenta como IA, tono de la facción.
- **ESTILO Y FORMATO:** extensión calibrada a la complejidad real de la
  pregunta; Markdown permitido (se renderiza de verdad, ver 6.8); nada de
  encabezados/tablas/código (no tienen sentido en una burbuja de chat).
- **USO DE LOS FRAGMENTOS Y CITAS:** el historial de conversación es solo
  para contexto, nunca fuente de datos del juego; usar solo los fragmentos
  del turno actual para cualquier cifra o dato concreto; citar en el punto
  exacto donde se usa el dato, no solo al final; cubrir cada elemento por
  separado en una comparación; incluir siempre datos concretos y
  verificables (requisitos, costes, exclusividad), no solo la descripción
  temática.
- **TÉCNICAS ÚNICAS:** marcarlas siempre explícitamente; regla de orden
  mecánica (normales primero, únicas al final, sin excepción salvo que se
  pidan específicamente).
- **LISTAS, PROPUESTAS Y CONTRADICCIONES:** avisar si una lista es parcial,
  si un sistema es una propuesta no implantada, o si dos fragmentos se
  contradicen.
- **CUÁNDO PREGUNTAR EN VEZ DE ADIVINAR:** si la pregunta es ambigua y
  adivinar mal daría información incorrecta, hacer una única pregunta
  aclaratoria corta en vez de forzar una respuesta — sin abusar de esto,
  solo para ambigüedad real. Distinto de "no sé": si se entendió la
  pregunta pero falta información, sigue la regla de "no lo sé".
- **CHARLA CASUAL VS. PREGUNTA REAL:** distingue manipulación real (se
  rechaza) de charla casual (se responde en personaje, sin fragmentos ni
  citas) — incluye la regla explícita de ignorar coincidencias de palabra
  suelta entre un saludo y contenido recuperado.

Además, hay una regla que avisa si los fragmentos solo cubren un caso
particular de un sistema más general que lo que se preguntó (ej. batallas
navales cuando la pregunta era sobre "acciones en el bélico" en general) —
nace de un fallo real detectado en pruebas: la búsqueda trajo el fragmento
equivocado (de una guía distinta, más específica) por tener un título más
parecido textualmente a la pregunta que el fragmento correcto, que estaba
diluido en un bloque grande por el `nivel_max = 2` de esa guía (5.1). La
regla del prompt es un parche razonable; el arreglo de fondo (fragmentación
por sección dentro de la misma guía, no toda la guía con el mismo
`nivel_max`) queda pendiente.

## 8. Interfaz (`op_punkrecords.html`)

### 8.1 Layout general

Marco de tres fondos de `style.md`, con `.thirdBackground` a `100%` (pisa el
ancho fijo de 1030px) para que el chat ocupe la mayor parte de la pantalla.
Panel de chat (`.pr-chat`) con altura fija según el viewport (`78vh`, mínimo
480px), cabecera fija con el nombre del operador (no se pierde de vista al
scrollear), área de mensajes con scroll propio, y formulario fijo abajo.

### 8.2 Chat

- Burbujas con "colita" (triangulito apuntando al emisor, truco de borde
  transparente) y sombra dura desplazada (`--opg-sombra-offset` — la firma
  de viñeta de cómic del sistema de diseño; sin ella las burbujas se leían a
  chat genérico).
- Avatar circular junto a cada burbuja: el operador usa el Den Den Mushi de
  su facción (`images/op/misc/denden_*.png`), el jugador usa su avatar real
  de MyBB (`format_avatar()`).
- Color de fondo de la burbuja del operador y de la cabecera: tinte suave
  (`color-mix()` en CSS) de un color **real** de la paleta de `style.md` por
  facción (azul para Marina, morado apagado para CipherPol, etc.), no un
  pastel inventado.
- Burbuja de error suavizada (tinte claro + borde/texto rojo + ícono de
  advertencia) en vez de un rectángulo rojo sólido, con botón "Reintentar"
  que reenvía la misma pregunta sin tener que reescribirla.
- Estado vacío con `.opg-vacio` (la clase canónica del sistema para
  "listado sin nada que mostrar todavía"), con un mensaje distinto por
  facción.
- Indicador de "escribiendo..." con el avatar y nombre del operador,
  controlado por la clase `htmx-request` que agrega htmx automáticamente
  durante el POST.
- Burbuja optimista: la pregunta del jugador aparece en el chat de
  inmediato al enviar, sin esperar la respuesta del servidor — se construye
  con el DOM (`textContent`, nunca `innerHTML`) y se reemplaza cuando llega
  el turno real.
- Enter envía la pregunta; Shift+Enter hace salto de línea.
- Auto-scroll condicional: enviar la propia pregunta siempre baja el chat;
  la respuesta que llega solo hace scroll si el jugador ya estaba cerca del
  final (si subió a releer algo, no lo interrumpe).
- Botón "Reiniciar conversación": borra el chat y la memoria de esta
  pestaña (nada que limpiar del lado del servidor).

### 8.3 Cuota y accesos

Barra de progreso con "Preguntas restantes hoy: X/100" (`pr_renderizar_cuota()`,
compartida entre el render inicial y la actualización en vivo por
`hx-swap-oob`), que se pone roja con ≤10 preguntas restantes.

### 8.4 Sin Alpine.js: por qué

La primera versión del formulario usó Alpine.js para el contador de
caracteres, el estado de envío y el llenado de preguntas de ejemplo. Se
sacó por completo y se reemplazó con JavaScript plano usando los mecanismos
nativos de htmx (`hx-disabled-elt`, la clase `.htmx-request` para el estado
visual de "enviando"). Motivo: **`$templates->get()` de MyBB aplica
`addslashes()` al contenido antes de devolverlo**, y como el template entero
se renderiza con `eval("\$page = \"" . ... . "\";")`, cualquier `$palabra`
suelta en el HTML o el JS (no solo el `{$variable}` intencional) se
interpreta como una variable PHP — incluyendo las magias de Alpine
(`$event`, `$el`, etc.). Peor: por cómo `addslashes()` duplica cada
backslash, es **matemáticamente imposible** escapar un `$event` a mano
desde el archivo fuente (el número de backslashes resultante siempre es
par, nunca el impar que PHP necesita para tratarlo como escape). La única
solución real es no depender de `$` en el JS de ningún template de MyBB —
ahora documentado en `style.md` como regla general para código nuevo.

## 9. Seguridad

Sin cambios de fondo respecto al diseño original (claves fuera del código,
CSRF, entradas validadas y escapadas, salida siempre escapada antes de
insertar HTML de verdad). Dos adiciones:

- **Historial de conversación:** el servidor nunca confía en lo que manda
  el cliente — se recorta a tamaño y cantidad de turnos razonables
  (`pr_parsear_historial()`) antes de usarse, y un payload sospechosamente
  grande se descarta entero.
- **Enlaces de fuentes:** `target="_blank" rel="noopener noreferrer"` — no
  solo por seguridad estándar, sino porque abrir en la misma pestaña
  borraría la memoria de conversación (vive solo en JS de esa pestaña).

## 10. Archivos

Ver sección 2 para el inventario completo y actualizado. Los nombres de
archivo (`punkrecords_*`) y la ruta (`/op/punkrecords.php`) se mantuvieron
sin cambios a pesar del rebranding a "Operador Den Den Mushi" — cambiar una
URL ya en uso no valía la pena por un cambio que es puramente de
presentación al jugador.

## 11. Casos límite y fallos

| Caso | Comportamiento |
|---|---|
| Sin fragmentos relevantes | Se genera igual (ya no hay atajo sin modelo) — el prompt decide si es "no sé" o charla casual |
| Gemini devuelve 429 o 5xx | Mensaje de error claro, botón de reintentar, se registra `error` |
| Voyage no disponible | Recuperación solo por texto (embedding vacío) |
| Supabase no responde | Mensaje "no disponible"; se registra |
| Petición htmx falla sin completarse (sin conexión) | La burbuja optimista se limpia (`htmx:sendError`/`htmx:responseError`), no queda fantasma |
| Pregunta ambigua | El operador pregunta antes de adivinar, en vez de forzar una respuesta |
| Fragmentos solo cubren un caso particular | El operador lo aclara en vez de presentarlo como regla general |
| Coincidencia de palabra suelta (saludo con el nombre del operador) | Se ignora, se responde como charla |
| Indexación interrumpida | Reanudable: el hash evita repetir lo ya procesado |
| `[F#]` inexistente citado por el modelo | Se ignora esa cita puntual; las fuentes salen solo de lo recuperado real |

## 12. Riesgos y pendientes

- **Fragmentación de `b01_guia_belica.md`:** el `nivel_max = 2` uniforme es
  un compromiso — mantiene interconectadas las reglas que lo necesitan pero
  diluye la precisión de recuperación para conceptos puntuales dentro de esa
  guía (7). Un mecanismo de `nivel_max` por sección (no por guía completa)
  lo arreglaría sin volver a romper el caso que motivó el valor bajo.
- **Sin página de técnica individual:** bloquea que las técnicas citadas
  sean un link real, a diferencia de las guías.
- **Sin retención definida del registro de preguntas.**
- **Sin feedback estructurado (👍/👎) ni detección automática de huecos de
  contenido** — ambos sugeridos, ninguno implementado.
- **`op/functions/punkrecords_mvp.php` huérfano** — confirmado sin
  referencias, candidato a borrar.
