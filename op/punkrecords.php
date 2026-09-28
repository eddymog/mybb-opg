<?php
/**
 * Punk Records: asistente de preguntas sobre guías y técnicas del foro
 * (RAG — búsqueda híbrida en Supabase + generación con Gemini).
 *
 * Diseño: docs/200_DesignPlan_Asistente.md
 * Requisitos: docs/100_Requirements_Asistente.md
 *
 * Página pública en la ruta; acceso restringido por ahora a staff y a un
 * grupo reducido de UIDs de confianza mientras se termina de validar el
 * sistema (sección 5 del diseño del MVP) — un usuario sin acceso ve el
 * mismo error_no_permission() que en cualquier herramienta restringida.
 */
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'punkrecords.php');

require_once "./../global.php";
require_once "./functions/op_functions.php";
require_once "./functions/punkrecords_config.php";
require_once "./functions/punkrecords_rag.php";
require_once "./functions/punkrecords_supabase.php";
require_once "./functions/punkrecords_proveedores.php";
require_once "./functions/punkrecords_limites.php";

global $templates, $mybb, $db;

// UIDs de confianza mientras el sistema termina de validarse, además de
// staff. Ampliar esta lista (o cambiar a does_ficha_exist()) es el único
// cambio necesario para abrir la página a todos los jugadores más adelante.
const PR_UIDS_CONFIANZA = [11, 1064, 1017];

$uid = (int) $mybb->user['uid'];
$tiene_acceso = $uid > 0 && (is_staff($uid) || in_array($uid, PR_UIDS_CONFIANZA, true));
if (!$tiene_acceso) {
    error_no_permission();
}

// El personaje del jugador tiene que existir Y tener una de las 6 facciones
// del juego — sin eso no hay operador ni personalidad que asignarle, y toda
// la premisa del Den Den Mushi (alguien de tu facción te contesta) deja de
// tener sentido. Aplica también a staff/UIDs de confianza a propósito: así
// prueban la página tal como la vería cualquier jugador.
$faccion_jugador = pr_obtener_faccion($db, $uid);
if (!array_key_exists($faccion_jugador, PR_OPERADORES_POR_FACCION)) {
    error_no_permission();
}

$post_key = generate_post_check();

$pregunta_esc = '';
$respuesta_html = '';
$error_esc = '';
$fuentes_html = '';
$mostrar_respuesta = false;
$mostrar_fuentes = false;

// El nombre de quien contesta el Den Den Mushi se calcula siempre (no solo
// al responder una pregunta), para que el título de la página ya lo muestre
// desde que el jugador entra.
$operador_esc = htmlspecialchars(pr_obtener_operador($faccion_jugador), ENT_QUOTES, 'UTF-8');
$placeholder_vacio_esc = htmlspecialchars(pr_obtener_placeholder_vacio($faccion_jugador), ENT_QUOTES, 'UTF-8');
$color_faccion_esc = htmlspecialchars(pr_obtener_color_faccion($faccion_jugador), ENT_QUOTES, 'UTF-8');

// Avatares del chat: uno por facción para el operador (círculo del Den Den
// Mushi) y el avatar real de MyBB para el jugador — ambos se calculan una
// sola vez (no cambian entre preguntas de la misma conversación) y se
// reusan en cada turno vía pr_renderizar_turno().
$avatar_faccion_url = pr_obtener_avatar_faccion($faccion_jugador);
$avatar_operador_html = pr_renderizar_avatar(
    $avatar_faccion_url !== null ? htmlspecialchars($mybb->settings['bburl'] . $avatar_faccion_url, ENT_QUOTES, 'UTF-8') : null,
    $operador_esc
);
$avatar_usuario_info = format_avatar($mybb->user['avatar']);
$avatar_usuario_html = pr_renderizar_avatar($avatar_usuario_info['image'], htmlspecialchars($mybb->user['username'], ENT_QUOTES, 'UTF-8'));
$avatar_usuario_url_esc = $avatar_usuario_info['image']; // ya escapado por format_avatar() — para el data-attribute del JS

// Cuota restante del día — se calcula siempre (no solo al responder), para
// que se vea desde que el jugador entra a la página; se recalcula después
// de registrar cada pregunta (ver abajo) para que baje en vivo sin recargar.
$restantes_dia = pr_restantes_dia($db, $uid);
$preguntas_dia_max = PR_PREGUNTAS_USUARIO_DIA;
$cuota_html = pr_renderizar_cuota($restantes_dia, $preguntas_dia_max);

if ($mybb->request_method === 'post') {
    $pregunta = trim($mybb->get_input('pregunta'));
    $pregunta_esc = htmlspecialchars($pregunta, ENT_QUOTES, 'UTF-8');
    $inicio_ms = microtime(true);
    $resultado = 'error';
    $fuentes = [];

    // segundo parámetro true: no queremos que un token vencido reviente con
    // la pantalla de error genérica de MyBB en medio de un fragmento htmx —
    // se maneja como cualquier otro error de la pregunta.
    if (!verify_post_check($mybb->get_input('my_post_key', MyBB::INPUT_STRING), true)) {
        $error = 'Tu sesión de esta página caducó. Recarga e inténtalo de nuevo.';
    } else {
        $error = pr_validar_pregunta($pregunta);
    }

    // Memoria de conversación: turnos previos de esta misma pestaña, que el
    // cliente manda de vuelta en cada pregunta (nunca se guarda en el
    // servidor, ver pr_parsear_historial()).
    $historial = pr_parsear_historial($mybb->get_input('historial', MyBB::INPUT_STRING));

    if ($error === null) {
        $error = pr_limite_usuario($db, $uid);
    }

    if ($error === null) {
        $error = pr_limite_global_minuto($db);
    }

    if ($error === null) {
        if (!pr_reservar_llamada($db, 'emb')) {
            $error = 'El asistente alcanzó su límite de preguntas por hoy. Vuelve mañana.';
        }
    }

    if ($error === null) {
        $faccion = $faccion_jugador;
        $embedding_pregunta = pr_embeber_pregunta($pregunta);

        if ($embedding_pregunta === null) {
            $error = 'El asistente no está disponible en este momento. Inténtalo de nuevo en unos minutos.';
        } else {
            $resultado_busqueda = pr_supabase_buscar($pregunta, $embedding_pregunta);

            if (!$resultado_busqueda['ok']) {
                $error = 'El asistente no está disponible en este momento. Inténtalo de nuevo en unos minutos.';
            } else {
                // Filtra los fragmentos que solo ganaron el ranking por
                // posición relativa (RRF) sin relevancia real — ver
                // pr_filtrar_fragmentos_relevantes(). Puede quedar vacío —
                // eso ya NO se corta acá con un mensaje fijo (ver abajo):
                // siempre se genera con el modelo, para que un "no sé nada de
                // eso" o una charla casual salgan con la personalidad del
                // operador, en vez de un texto robótico genérico.
                $fragmentos = pr_filtrar_fragmentos_relevantes($resultado_busqueda['data'] ?? []);

                if (!pr_reservar_llamada($db, 'gen')) {
                    $error = 'El asistente alcanzó su límite de preguntas por hoy. Vuelve mañana.';
                } else {
                    $resultado_generacion = pr_generar($pregunta, $fragmentos, $faccion, $historial);

                    if (!$resultado_generacion['ok']) {
                        $error = 'El asistente tuvo un problema al responder. Inténtalo de nuevo.';
                    } else {
                        $texto_bruto = $resultado_generacion['texto'];

                        // El HTML se genera con las marcas [F#] TODAVÍA
                        // dentro del texto (sobreviven intactas al escapado:
                        // no son caracteres especiales de HTML) — se quitan
                        // (insertar_notas=false) y en su lugar se arma una
                        // frase natural invitando a visitar cada guía citada
                        // al final del mensaje — ver pr_renderizar_sugerencia_fuentes().
                        $citas = pr_insertar_citas(pr_markdown_a_html($texto_bruto), $fragmentos, '', false);
                        $sugerencia_fuentes = pr_renderizar_sugerencia_fuentes($citas['fuentes']);
                        $respuesta_html = $citas['html'] . $sugerencia_fuentes;
                        $mostrar_fuentes = false;
                        $fuentes_html = '';
                        $fuentes = $citas['fuentes'];

                        $mostrar_respuesta = true;
                        $resultado = (stripos($texto_bruto, 'no lo sé') !== false
                            || stripos($texto_bruto, 'no encontré información') !== false)
                            ? 'sin_datos'
                            : 'ok';
                    }
                }
            }
        }
    }

    if ($error !== null) {
        $error_esc = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        $resultado = ($error === 'La pregunta es demasiado corta (mínimo 5 caracteres).'
            || $error === 'La pregunta es demasiado larga (máximo 500 caracteres).'
            || $error === 'La pregunta contiene caracteres no permitidos.')
            ? 'error'
            : (stripos($error, 'límite') !== false || stripos($error, 'demasiad') !== false ? 'limite' : 'error');
    }

    // $fuentes sin condicionar a $mostrar_fuentes: ya no hay una lista de
    // fuentes aparte en la UI (ver pr_renderizar_sugerencia_fuentes()), pero
    // el registro de qué se citó sigue siendo útil para revisar calidad
    // después, se haya mostrado o no en pantalla.
    $ms = (int) round((microtime(true) - $inicio_ms) * 1000);
    pr_registrar($db, $uid, $mybb->user['username'], $pregunta, $resultado, $ms, $fuentes);

    // Se recalcula después de registrar, para reflejar la pregunta que se
    // acaba de contar (baja de a uno en vivo, sin recargar la página).
    $restantes_dia = pr_restantes_dia($db, $uid);

    // Petición de htmx (el formulario normal, JS activo): se responde solo
    // con el turno nuevo (pregunta + respuesta) para que htmx lo agregue al
    // final del chat, sin recargar la página. Sin JS, el form hace un POST
    // normal y cae al render completo de abajo, con el mismo resultado.
    // El <span hx-swap-oob="true"> viaja en la misma respuesta pero se
    // actualiza aparte, fuera de #pr-mensajes (hx-target) — htmx escanea
    // toda la respuesta por elementos "fuera de banda" como este.
    if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
        header('Content-Type: text/html; charset=utf-8');
        echo pr_renderizar_turno($pregunta_esc, $respuesta_html, $error_esc, $operador_esc, $mostrar_fuentes, $fuentes_html, $avatar_operador_html, $avatar_usuario_html)
            . pr_renderizar_cuota($restantes_dia, $preguntas_dia_max, true);
        exit;
    }
}

// Fallback sin JS: si htmx no llegó a interceptar el submit, este es un POST
// normal con recarga completa — el turno se muestra igual dentro del chat,
// como si hubiera llegado por htmx, para que ambos caminos se vean iguales.
$turno_inicial_html = $mostrar_respuesta
    ? pr_renderizar_turno($pregunta_esc, $respuesta_html, '', $operador_esc, $mostrar_fuentes, $fuentes_html, $avatar_operador_html, $avatar_usuario_html)
    : '';

// Contenido inicial de #pr-mensajes: el turno de la recarga sin JS si hubo
// uno, o si no, el estado vacío con la clase canónica del sistema de diseño
// (.opg-vacio, docs/style.md — "para cuando un listado no tiene nada que
// mostrar todavía"), con id para que el JS la saque apenas se manda la
// primera pregunta.
$mensajes_inicial_html = $turno_inicial_html !== ''
    ? $turno_inicial_html
    : '<p class="opg-vacio pr-vacio-chat" id="pr-vacio">' . $placeholder_vacio_esc . '</p>';

eval('$page = "' . $templates->get('op_punkrecords') . '";');
output_page($page);
