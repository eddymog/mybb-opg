<?php
/**
 * Punk Records: validación de la pregunta, caché y límites de uso para la
 * página real (Fase 4). Ver docs/200_DesignPlan_Asistente.md, secciones
 * 4.2-4.4 y 6.1, y docs/100_Requirements_Asistente.md, sección 5.3.
 */

/**
 * 5 a 500 caracteres, sin caracteres de control. Devuelve null si es
 * válida, o el mensaje de error para mostrar si no lo es.
 */
function pr_validar_pregunta($pregunta)
{
    $pregunta = trim($pregunta);
    $len = mb_strlen($pregunta, 'UTF-8');

    if ($len < 5) {
        return 'La pregunta es demasiado corta (mínimo 5 caracteres).';
    }
    if ($len > 500) {
        return 'La pregunta es demasiado larga (máximo 500 caracteres).';
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $pregunta)) {
        return 'La pregunta contiene caracteres no permitidos.';
    }

    return null;
}

/**
 * Parsea y sanea el historial de conversación que manda el cliente (JSON de
 * un array en memoria del navegador — nunca se guarda en el servidor, ver
 * PR_HISTORIAL_* en punkrecords_config.php). Nunca confía en el contenido:
 * es dato del jugador como cualquier otro campo del POST, así que se recorta
 * a un tamaño y cantidad de turnos razonables antes de usarse. Devuelve un
 * array de ['pregunta' => ..., 'respuesta' => ...], o [] si viene vacío,
 * mal formado, o pasado de tamaño (payload sospechoso: se ignora entero en
 * vez de intentar rescatar una parte).
 */
function pr_parsear_historial($json)
{
    if (!is_string($json) || $json === '' || mb_strlen($json, 'UTF-8') > PR_HISTORIAL_JSON_CHARS_MAX) {
        return [];
    }

    $datos = json_decode($json, true);
    if (!is_array($datos)) {
        return [];
    }

    $limpio = [];
    foreach (array_slice($datos, -PR_HISTORIAL_TURNOS_MAX) as $turno) {
        if (!is_array($turno) || !isset($turno['pregunta'], $turno['respuesta'])) {
            continue;
        }
        $pregunta = trim(mb_substr((string) $turno['pregunta'], 0, 500, 'UTF-8'));
        $respuesta = trim(mb_substr((string) $turno['respuesta'], 0, PR_HISTORIAL_RESPUESTA_CHARS_MAX, 'UTF-8'));
        if ($pregunta === '' || $respuesta === '') {
            continue;
        }
        $limpio[] = ['pregunta' => $pregunta, 'respuesta' => $respuesta];
    }

    return $limpio;
}

/**
 * Límites por usuario (5.3 de los requisitos): PR_PREGUNTAS_USUARIO_HORA y
 * PR_PREGUNTAS_USUARIO_DIA, contando mybb_op_punkrecords_log. Devuelve null
 * si puede preguntar, o el mensaje amable si superó el límite.
 */
function pr_limite_usuario($db, $uid)
{
    $uid = (int) $uid;
    $ahora = TIME_NOW;
    $hace_1h = $ahora - 3600;
    $hoy_inicio = strtotime('today', $ahora);

    $q = $db->simple_select('op_punkrecords_log', 'COUNT(*) AS total', "uid = {$uid} AND creado_en >= {$hace_1h}");
    if ((int) $db->fetch_field($q, 'total') >= PR_PREGUNTAS_USUARIO_HORA) {
        return 'Has hecho demasiadas preguntas en la última hora. Espera un poco e inténtalo de nuevo.';
    }

    $q = $db->simple_select('op_punkrecords_log', 'COUNT(*) AS total', "uid = {$uid} AND creado_en >= {$hoy_inicio}");
    if ((int) $db->fetch_field($q, 'total') >= PR_PREGUNTAS_USUARIO_DIA) {
        return 'Has agotado tus preguntas de hoy. Vuelve mañana.';
    }

    return null;
}

/**
 * Preguntas que le quedan hoy al jugador (para mostrarlas en la página,
 * antes de que se tope con el límite) — mismo conteo que usa
 * pr_limite_usuario() para el día, nunca negativo.
 */
function pr_restantes_dia($db, $uid)
{
    $uid = (int) $uid;
    $hoy_inicio = strtotime('today', TIME_NOW);
    $q = $db->simple_select('op_punkrecords_log', 'COUNT(*) AS total', "uid = {$uid} AND creado_en >= {$hoy_inicio}");
    $usadas = (int) $db->fetch_field($q, 'total');
    return max(0, PR_PREGUNTAS_USUARIO_DIA - $usadas);
}

/**
 * Widget de cuota (texto + barra de progreso) — una sola función para que el
 * render inicial de la página y la actualización en vivo (htmx OOB, después
 * de cada pregunta) queden siempre exactamente iguales. $oob=true agrega
 * hx-swap-oob="true" para que htmx lo actualice fuera de #pr-mensajes (ver
 * punkrecords.php).
 */
function pr_renderizar_cuota($restantes, $max, $oob = false)
{
    $restantes = max(0, (int) $restantes);
    $max = max(1, (int) $max); // evita división por cero si algún día el límite se pone en 0
    $porcentaje = max(0, min(100, round(($restantes / $max) * 100)));
    $clase_baja = $restantes <= 10 ? ' pr-cuota-barra__relleno--baja' : '';
    $oob_attr = $oob ? ' hx-swap-oob="true"' : '';

    return '<div id="pr-cuota"' . $oob_attr . '>'
        . '<div class="pr-cuota-linea"><span>Preguntas disponibles</span><strong>' . $restantes . '/' . $max . '</strong></div>'
        . '<div class="pr-cuota-barra" role="progressbar" aria-label="Preguntas disponibles hoy" aria-valuemin="0" aria-valuemax="' . $max . '" aria-valuenow="' . $restantes . '"><div class="pr-cuota-barra__relleno' . $clase_baja . '" style="width: ' . $porcentaje . '%;"></div></div>'
        . '</div>';
}

/**
 * Guarda por minuto sobre el total de preguntas del foro (no por proveedor,
 * sino sobre nuestro propio registro) — sección 5.3 de los requisitos.
 */
function pr_limite_global_minuto($db)
{
    $hace_1min = TIME_NOW - 60;
    $q = $db->simple_select('op_punkrecords_log', 'COUNT(*) AS total', "creado_en >= {$hace_1min}");
    if ((int) $db->fetch_field($q, 'total') >= PR_LLAMADAS_POR_MINUTO_MAX) {
        return 'Hay demasiadas preguntas en este momento. Inténtalo de nuevo en un minuto.';
    }
    return null;
}

/**
 * Reserva atómicamente una llamada de un tipo ('gen' o 'emb') contra la
 * cuota diaria global, ANTES de hacer la llamada real — sección 4.4 del
 * diseño. Devuelve true si sigue dentro del tope (ya reservada), false si
 * ya se superó (no se debe llamar al proveedor).
 */
function pr_reservar_llamada($db, $tipo)
{
    $hoy = date('Y-m-d', TIME_NOW);
    $columna = $tipo === 'gen' ? 'llamadas_gen' : 'llamadas_emb';
    $tope = $tipo === 'gen' ? PR_LLAMADAS_GEN_DIA_MAX : PR_LLAMADAS_EMB_DIA_MAX;

    $db->query("
        INSERT INTO mybb_op_punkrecords_cuota (dia, {$columna})
        VALUES ('{$hoy}', 1)
        ON DUPLICATE KEY UPDATE {$columna} = {$columna} + 1
    ");

    $q = $db->simple_select('op_punkrecords_cuota', $columna, "dia = '{$hoy}'");
    $usado = (int) $db->fetch_field($q, $columna);

    return $usado <= $tope;
}

/**
 * Registra una fila en mybb_op_punkrecords_log.
 * $resultado: ok, sin_datos, limite, error.
 * $username: se guarda tal cual al momento de preguntar, no vía JOIN — si el
 * jugador cambia de nombre después, el registro viejo conserva el nombre de
 * ese momento (útil para auditar quién preguntó qué).
 */
function pr_registrar($db, $uid, $username, $pregunta, $resultado, $ms, array $fuentes = [])
{
    $fuentes_str = implode(',', array_map(fn($f) => $f['fuente'] . ':' . $f['ref'], $fuentes));
    $db->insert_query('op_punkrecords_log', [
        'uid' => (int) $uid,
        'username' => $db->escape_string(mb_substr((string) $username, 0, 100, 'UTF-8')),
        'pregunta' => $db->escape_string($pregunta),
        'resultado' => $db->escape_string($resultado),
        'fuentes' => $db->escape_string(mb_substr($fuentes_str, 0, 255, 'UTF-8')),
        'ms' => (int) $ms,
        'creado_en' => TIME_NOW,
    ]);
}

/**
 * Avatar (círculo) para una burbuja del chat. $url_esc ya viene escapado
 * (o null/'' para el genérico — sin facción reconocida no hay imagen de
 * Den Den Mushi que mostrar, ver pr_obtener_avatar_faccion()); el ícono de
 * teléfono cubre ese caso en vez de romper con un <img> a una ruta vacía.
 */
function pr_renderizar_avatar($url_esc, $alt_esc)
{
    if ($url_esc === null || $url_esc === '') {
        return '<span class="pr-avatar pr-avatar--generica" aria-hidden="true"><i class="fa-solid fa-phone"></i></span>';
    }
    return '<img class="pr-avatar" src="' . $url_esc . '" alt="' . $alt_esc . '">';
}

/**
 * Un turno de chat (pregunta del jugador + respuesta) como fragmento HTML,
 * para la respuesta htmx de un POST — se agrega al final de #pr-mensajes
 * (hx-swap="beforeend"), sin recargar la página. Todos los parámetros ya
 * vienen escapados/listos para imprimir (respuesta_html y fuentes_html se
 * generan con nl2br(htmlspecialchars(...)), nunca texto crudo).
 * $avatar_operador_html/$avatar_usuario_html: HTML ya armado (ver
 * pr_renderizar_avatar()) — se arma una sola vez por página en
 * punkrecords.php y se reusa en cada turno, no cambia entre preguntas de
 * la misma conversación.
 */
function pr_renderizar_turno(
    $pregunta_esc,
    $respuesta_html,
    $error_esc,
    $operador_esc,
    $mostrar_fuentes,
    $fuentes_html,
    $avatar_operador_html = '',
    $avatar_usuario_html = ''
) {
    $es_error = $error_esc !== '';
    $clase_operador = $es_error ? 'pr-burbuja--operador pr-burbuja--error' : 'pr-burbuja--operador';
    $texto_operador = $es_error ? $error_esc : $respuesta_html;
    $autor = $es_error ? 'Interferencia en la línea' : $operador_esc;

    // data-pregunta/data-respuesta: texto plano (sin las etiquetas HTML del
    // Markdown ya renderizado) para que el JS de memoria de conversación
    // (ver op_punkrecords.html) pueda leer el turno recién agregado al DOM
    // sin tener que volver a limpiar HTML del lado del cliente. Nunca se
    // agregan en un turno de error: un error no es parte real de la
    // conversación, no debe quedar en la memoria de turnos siguientes.
    $atributos_historial = '';
    if (!$es_error) {
        $respuesta_plana = trim(html_entity_decode(strip_tags($respuesta_html), ENT_QUOTES, 'UTF-8'));
        $atributos_historial = ' data-pregunta="' . $pregunta_esc . '"'
            . ' data-respuesta="' . htmlspecialchars($respuesta_plana, ENT_QUOTES, 'UTF-8') . '"';
    }

    // .pr-fila: envuelve avatar + burbuja como una fila; el orden de los
    // dos hijos (avatar antes o después de la burbuja) define de qué lado
    // queda el avatar — ver flex-direction en el CSS del template.
    $html = '<div class="pr-turno"' . $atributos_historial . '>'
        . '<div class="pr-fila pr-fila--usuario">'
        . '<div class="pr-burbuja pr-burbuja--usuario"><div class="pr-burbuja__texto">' . $pregunta_esc . '</div></div>'
        . $avatar_usuario_html
        . '</div>'
        . '<div class="pr-fila pr-fila--operador">'
        . $avatar_operador_html
        . '<div class="pr-burbuja ' . $clase_operador . '">'
        . '<div class="pr-burbuja__autor">' . $autor . '</div>'
        . '<div class="pr-burbuja__texto">' . $texto_operador . '</div>';

    if ($mostrar_fuentes) {
        $html .= '<div class="pr-guias-consultadas">' . $fuentes_html . '</div>';
    }

    if ($es_error) {
        // Botón de reintentar: manda la pregunta original en un
        // data-attribute (ya viene escapado para HTML), el JS la vuelve a
        // poner en el textarea y reenvía — así el jugador no tiene que
        // reescribirla después de un error transitorio.
        $html .= '<button type="button" class="pr-reintentar" data-pregunta="' . $pregunta_esc . '">Reintentar</button>';
    }

    $html .= '</div></div></div>';

    return $html;
}
