<?php

function does_ficha_exist($uid) {
    global $db;
    $ficha = select_one_query_with_id('mybb_op_fichas', 'fid', $uid);
    $moderada = $ficha['aprobado_por']!= 'sin_aprobar';

    return $ficha != null && $moderada;
}

// Sirve para ficha y tienda
function select_one_query_with_id($table_name, $id_name, $id) {
    global $db;

    $obj = null;

    $query = $db->query("
        SELECT * FROM $table_name WHERE $id_name='$id'
    ");

    while ($q = $db->fetch_array($query)) {
        $obj = $q;
    }

    return $obj;
}

/**
 * Normaliza una sub-especialización de oficio (p.ej. "Mayorista") a su oficio de
 * familia (p.ej. "Recolector"). La ficha guarda `oficios` con la familia como clave
 * de primer nivel, pero `mybb_op_objetos.oficio` puede contener una sub-especialización;
 * sin esta normalización, una búsqueda por sub-especialización nunca encuentra la
 * entrada de la ficha y genera un registro `{sub: [], nivel: 0}` corrupto.
 */
function oficioFamilia($oficio_name) {
    static $familias = array(
        'Artesano' => 'Artesano', 'Herrero' => 'Artesano', 'Modista' => 'Artesano',
        'Médico' => 'Médico', 'Farmacólogo' => 'Médico', 'Doctor' => 'Médico',
        'Navegante' => 'Navegante', 'Cartógrafo' => 'Navegante', 'Timonel' => 'Navegante',
        'Inventor' => 'Inventor', 'Biólogo' => 'Inventor', 'Ingeniero' => 'Inventor',
        'Carpintero' => 'Carpintero', 'Astillero' => 'Carpintero', 'Constructor' => 'Carpintero',
        'Cocinero' => 'Cocinero', 'Chef' => 'Cocinero', 'Aprovisionador' => 'Cocinero',
        'Mercader' => 'Mercader', 'Comerciante' => 'Mercader', 'Contrabandista' => 'Mercader',
        'Investigador' => 'Investigador', 'Periodista' => 'Investigador', 'Arqueólogo' => 'Investigador',
        'Aventurero' => 'Aventurero', 'Cazador' => 'Aventurero', 'Domador' => 'Aventurero',
        'Recolector' => 'Recolector', 'Agreste' => 'Recolector', 'Mayorista' => 'Recolector',
    );
    return isset($familias[$oficio_name]) ? $familias[$oficio_name] : $oficio_name;
}

function get_obj_from_query($query) {
    global $db;
    
    $obj = null;
    while ($q = $db->fetch_array($query)) {
        $obj = $q;
    }
    return $obj;
}

function log_audit($uid, $username, $categoria, $log) {
    global $db;
    $db->query("
        INSERT INTO `mybb_op_audit_general`(`uid`, `username`, `user_uid`, `categoria`, `log`) VALUES ('$uid','$username','$uid','$categoria', '$log');
    ");
}


function log_audit_currency($uid, $username, $user_uid, $categoria, $currency, $amount) {
    global $db;

    $ficha = null;
    $query_ficha = $db->query(" SELECT * FROM mybb_op_fichas WHERE fid='$user_uid' ");
    while ($q = $db->fetch_array($query_ficha)) { $ficha = $q; }

    if ($ficha && $currency == 'nikas') {

        $old = $ficha['nika'];
        $diff = intval($amount) - intval($old);
        $new = $amount;
        $log = "Nikas: $old->$new ($diff)";
        $db->query(" UPDATE `mybb_op_fichas` SET nika='$amount' WHERE fid='$user_uid' ");
        $db->query(" INSERT INTO `mybb_op_audit_general`(`uid`, `username`, `user_uid`, `categoria`, `log`) VALUES ('$uid','$username','$user_uid','$categoria', '$log'); ");
    }

    if ($ficha && $currency == 'kuros') {

        $old = $ficha['kuro'];
        $diff = intval($amount) - intval($old);
        $new = $amount;
        $log = "Kuros: $old->$new ($diff)";
        $db->query(" UPDATE `mybb_op_fichas` SET kuro='$new' WHERE fid='$user_uid' ");
        $db->query(" INSERT INTO `mybb_op_audit_general`(`uid`, `username`, `user_uid`, `categoria`, `log`) VALUES ('$uid','$username','$user_uid','$categoria', '$log'); ");
    }

    if ($ficha && $currency == 'puntos_oficio') {
        $old = $ficha['puntos_oficio'];
        $diff = intval($amount) - intval($old);
        $new = $amount;
        $log = "Puntos de oficio: $old->$new ($diff)";
        $db->query(" UPDATE `mybb_op_fichas` SET puntos_oficio='$amount' WHERE fid='$user_uid' ");
        $db->query(" INSERT INTO `mybb_op_audit_general`(`uid`, `username`, `user_uid`, `categoria`, `log`) VALUES ('$uid','$username','$user_uid','$categoria', '$log'); ");
    }

    if ($ficha && $currency == 'berries') {
        $old = $ficha['berries'];
        $diff = intval($amount) - intval($old);
        $new = $amount;
        $log = "Berries: $old->$new ($diff)";
        $db->query(" UPDATE `mybb_op_fichas` SET berries='$amount' WHERE fid='$user_uid' ");
        $db->query(" INSERT INTO `mybb_op_audit_general`(`uid`, `username`, `user_uid`, `categoria`, `log`) VALUES ('$uid','$username','$user_uid','$categoria', '$log'); ");
    }

    if ($ficha && $currency == 'experiencia') {
        $user = null;
        $query_user = $db->query(" SELECT * FROM mybb_users WHERE uid='$user_uid' ");
        while ($q = $db->fetch_array($query_user)) { $user = $q; }
        
        $old = $user['newpoints'];
        $diff = floatval($amount) - floatval($old);
        $new = $amount;
        $log = "Experiencia: $old->$new ($diff)";
        $db->query(" UPDATE `mybb_users` SET newpoints='$amount' WHERE `uid`='$user_uid';");
        $db->query(" INSERT INTO `mybb_op_audit_general`(`uid`, `username`, `user_uid`, `categoria`, `log`) VALUES ('$uid','$username','$user_uid','$categoria', '$log'); ");
    }

}

function is_narra($uid) {
    global $db;

    $has_narra_role = false;

    // $query = $db->query(" SELECT * FROM `mybb_users` WHERE uid='$uid' AND (usergroup = '14' OR additionalgroups LIKE '%14%' OR usergroup = '6' OR additionalgroups LIKE '%6%' OR usergroup = '4' OR additionalgroups LIKE '%4%'); ");
    $query = $db->query(" SELECT * FROM `mybb_users` WHERE uid='$uid' AND (additionalgroups LIKE  '%15%'); ");
    while ($q = $db->fetch_array($query)) { $has_narra_role = true; }

    return (
        $has_narra_role
    );    
}

function is_staff($uid) {
    global $db;

    $has_staff_role = false;

    // $query = $db->query(" SELECT * FROM `mybb_users` WHERE uid='$uid' AND (usergroup = '14' OR additionalgroups LIKE '%14%' OR usergroup = '6' OR additionalgroups LIKE '%6%' OR usergroup = '4' OR additionalgroups LIKE '%4%'); ");
    $query = $db->query(" SELECT * FROM `mybb_users` WHERE uid='$uid' AND (usergroup = '14' OR additionalgroups LIKE '%14%' OR usergroup = '6' OR usergroup = '4' OR usergroup = '16'); ");
    while ($q = $db->fetch_array($query)) { $has_staff_role = true; }

    return (
        $uid == '5'   || //Oda
        $uid == '10'  || //Cadmus
        $uid == '154' || // Giorno
        // $uid == '172' || // Crucio
        $uid == '17'  || //God Usoop. Kuro
        $uid == '7'   || //Lance
        $uid == '16'  || //Kinemon. Lance
        $uid == '117' || //mod katacristo. Ubben
        $uid == '90'  || //Ubben        
        $uid == '118' || // mod oppengarpimer. Fuji
        $uid == '25'  || // Fuji
        $uid == '121' || // mod condoriano. Juuken
        $uid == '23'  || // Juuken
        $uid == '123' || // Gretta
        $uid == '157' || // Moderadora Lola. Gretta
        $uid == '258' || // Kaku. Sirius
        $uid == '213' || // Sirius
        $uid == '263' || // Jango.Lobo
        $uid == '252' || // Wenzaemon
        $uid == '870' || // Apolo Kyrelight
        $has_staff_role || 
        $uid == '4' || // Hitsu
        $uid == '304' // Oddy
    );    
}

function is_peti_mod($uid) {
    return ($uid == '1');
}

function is_mod($uid) {
    return (is_user($uid));
}

function is_user($uid) {
    // admin: 1
    // Terence Blackmore: 2
    // Timsy: 3
    // Mr2 Bon Clay: 4
    // Oda: 5
    // Testoman: 6
    // Juan y Medio: 7
    // Kurosame 9

    return ($uid == '1' || $uid == '3' || $uid == '6' || $uid == '7' || $uid == '9');
}

/**
 * Registra intentos de peticiones excesivas o sospechosas
 * @param string $tipo Tipo de evento (rate_limit, too_many_requests, suspicious_activity)
 * @param string $endpoint El archivo/endpoint que se está accediendo
 * @param array $extra_info Información adicional opcional
 */
function log_security_event($tipo, $endpoint, $extra_info = array()) {
    global $db, $mybb;
    
    // Obtener información del usuario actual
    $uid = isset($mybb->user['uid']) ? $mybb->user['uid'] : 'guest';
    $username = isset($mybb->user['username']) ? $mybb->user['username'] : 'invitado';
    
    // Obtener información de la petición
    $ip = get_client_ip();
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    $request_method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'Unknown';
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'Unknown';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct';
    $timestamp = date('Y-m-d H:i:s');
    
    // Información POST/GET (limitada para no exponer datos sensibles)
    $post_data = !empty($_POST) ? json_encode(array_keys($_POST)) : 'none';
    $get_data = !empty($_GET) ? json_encode($_GET) : 'none';
    
    // Construir línea de log
    $log_entry = sprintf(
        "[%s] TIPO: %s | IP: %s | UID: %s | USER: %s | ENDPOINT: %s | METHOD: %s | URI: %s | REFERER: %s | USER_AGENT: %s | POST_KEYS: %s | GET: %s",
        $timestamp,
        $tipo,
        $ip,
        $uid,
        $username,
        $endpoint,
        $request_method,
        $request_uri,
        $referer,
        $user_agent,
        $post_data,
        $get_data
    );
    
    // Añadir información extra si existe
    if (!empty($extra_info)) {
        $log_entry .= " | EXTRA: " . json_encode($extra_info);
    }
    
    $log_entry .= "\n";
    
    // Escribir en el archivo de log
    $log_file = dirname(__FILE__) . '/fallosnef.log';
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // También registrar en la base de datos para análisis más fácil
    log_security_event_db($tipo, $uid, $username, $ip, $endpoint, $user_agent, $request_uri);
}

/**
 * Obtiene la IP real del cliente considerando proxies y CDNs
 */
function get_client_ip() {
    $ip_keys = array(
        'HTTP_CF_CONNECTING_IP',  // Cloudflare
        'HTTP_X_FORWARDED_FOR',   // Proxy
        'HTTP_X_REAL_IP',         // Nginx proxy
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    );
    
    foreach ($ip_keys as $key) {
        if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Si hay múltiples IPs (proxies encadenados), tomar la primera
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // Validar que sea una IP válida
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return 'Unknown';
}

/**
 * Guarda eventos de seguridad en la base de datos
 */
function log_security_event_db($tipo, $uid, $username, $ip, $endpoint, $user_agent, $request_uri) {
    global $db;
    
    try {
        // Crear tabla si no existe
        $db->query("
            CREATE TABLE IF NOT EXISTS `mybb_op_security_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `tipo` VARCHAR(50),
                `uid` VARCHAR(20),
                `username` VARCHAR(100),
                `ip` VARCHAR(50),
                `endpoint` VARCHAR(255),
                `user_agent` TEXT,
                `request_uri` TEXT,
                INDEX `idx_timestamp` (`timestamp`),
                INDEX `idx_ip` (`ip`),
                INDEX `idx_uid` (`uid`),
                INDEX `idx_tipo` (`tipo`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // Insertar evento
        $tipo = $db->escape_string($tipo);
        $uid = $db->escape_string($uid);
        $username = $db->escape_string($username);
        $ip = $db->escape_string($ip);
        $endpoint = $db->escape_string($endpoint);
        $user_agent = $db->escape_string($user_agent);
        $request_uri = $db->escape_string($request_uri);
        
        $db->query("
            INSERT INTO `mybb_op_security_log` 
            (`tipo`, `uid`, `username`, `ip`, `endpoint`, `user_agent`, `request_uri`) 
            VALUES 
            ('$tipo', '$uid', '$username', '$ip', '$endpoint', '$user_agent', '$request_uri')
        ");
    } catch (Exception $e) {
        // Si falla la DB, al menos tenemos el log en archivo
        error_log("Error al guardar en security_log DB: " . $e->getMessage());
    }
}

/**
 * Verifica si una IP/usuario está haciendo demasiadas peticiones
 * @param string $identifier Puede ser IP o UID
 * @param int $max_requests Número máximo de peticiones permitidas
 * @param int $time_window Ventana de tiempo en segundos
 * @return bool True si excede el límite
 */
function check_rate_limit($identifier, $max_requests = 100, $time_window = 60) {
    global $db;
    
    try {
        $identifier = $db->escape_string($identifier);
        $time_ago = date('Y-m-d H:i:s', time() - $time_window);
        
        // Contar peticiones en la ventana de tiempo
        $query = $db->query("
            SELECT COUNT(*) as count 
            FROM `mybb_op_security_log` 
            WHERE (ip = '$identifier' OR uid = '$identifier') 
            AND timestamp >= '$time_ago'
        ");
        
        $result = $db->fetch_array($query);
        $count = isset($result['count']) ? intval($result['count']) : 0;
        
        return $count >= $max_requests;
    } catch (Exception $e) {
        // Si hay error, no bloquear por defecto
        return false;
    }
}

/**
 * Obtiene estadísticas de peticiones por IP
 * @param int $minutes Últimos X minutos a analizar
 * @return array IPs con más peticiones
 */
function get_top_request_ips($minutes = 60) {
    global $db;
    
    try {
        $time_ago = date('Y-m-d H:i:s', time() - ($minutes * 60));
        
        $query = $db->query("
            SELECT ip, COUNT(*) as count, MAX(timestamp) as last_request
            FROM `mybb_op_security_log`
            WHERE timestamp >= '$time_ago'
            GROUP BY ip
            ORDER BY count DESC
            LIMIT 20
        ");
        
        $ips = array();
        while ($row = $db->fetch_array($query)) {
            $ips[] = $row;
        }
        
        return $ips;
    } catch (Exception $e) {
        return array();
    }
}

/**
 * Detecta indicadores de prompt de IA en el texto de un post.
 * Devuelve un array con los nombres de los patrones encontrados (vacío si no hay ninguno).
 */
function detecta_prompt_ia($texto) {
    $patrones = [
        // Marcadores de plantillas de chat / modelos
        'INST_TAG'          => '/\[INST\]|\[\/INST\]/i',
        'SYSTEM_TAG'        => '/<\|system\|>|<\|user\|>|<\|assistant\|>/i',
        'ROLE_HEADER'       => '/###\s*(System|Human|Assistant|Instrucción|Respuesta)\s*:/i',
        // Peticiones directas al modelo
        'ACTUA_COMO'        => '/act[úu]a\s+como\s+(un|una|el|la)\s+/i',
        'ERES_ASISTENTE'    => '/eres\s+un\s+(asistente|chatbot|modelo|bot|ai|ia)\b/i',
        'IMAGINA_QUE_ERES'  => '/imagina\s+que\s+eres\b/i',
        'COMO_MODELO_LEN'   => '/como\s+modelo\s+de\s+lenguaje/i',
        'COMO_IA'           => '/como\s+(ia|inteligencia\s+artificial|ai)\b/i',
        // Nombres de herramientas IA en contexto sospechoso
        'HERRAMIENTA_IA'    => '/\b(ChatGPT|GPT-4o?|Claude|Gemini|Copilot|Mistral|LLaMA|DeepSeek)\b/i',
        // Respuestas estereotípicas de IA en español
        'RESPUESTA_IA_ES'   => '/^(\¡?claro[,!]?\s+(aqu[íi]\s+tienes|te\s+presento)|por\s+supuesto[,!]?\s+aqu[íi]\s+(est[áa]|tienes)|entendido[,!]\s+aqu[íi])/im',
        // Prefijos de rol típicos de prompts
        'PREFIJO_ROL'       => '/^(Rol|Role|Sistema|System|Contexto|Context|Instrucciones|Instructions)\s*:/im',
        // Variables de plantilla sin rellenar
        'TEMPLATE_VAR'      => '/\{\{?\s*\w+\s*\}?\}/',

        // ── Canary IA: respuestas activadas por los prompts invisibles inyectados en build_postbit ──
        // Si una IA procesó el HTML del foro, habrá seguido al menos uno de estos temas absurdos
        'CANARY_ESPAGUETIS'  => '/espagueti[s]?\s+volador[aes]*/iu',
        'CANARY_PINGUINOS'   => '/ping[üu]inos?\s+(jugando|jug[oó]|que\s+jug)\s*(al?\s+)?ajedrez/iu',
        'CANARY_ELEFANTES'   => '/elefantes?\s+que\s+cuenta[n]?\s+chistes?/iu',
        'CANARY_PATOS'       => '/patos?\s+bailando\s+flamenco/iu',
        'CANARY_DETECTIVE'   => '/(detective|detect[io]ve)\s+(made\s+of|hecho\s+de|de)\s+mantequilla/iu',
        'CANARY_MAGO_QUESO'  => '/mago\s+que\s+vende\s+(queso|cheese)/iu',
        'CANARY_ROBOT_CALC'  => '/robot\s+que\s+colecciona\s+calcetines/iu',
        'CANARY_CONTADOR_TT' => '/(contador|accountant)\s+(viajero|que\s+viaja)\s+(del\s+tiempo|en\s+el\s+tiempo|time)/iu',
        'CANARY_WIZARD_CHEESE'=> '/wizard\s+who\s+sells\s+expired\s+cheese/i',
        'CANARY_PENGUINS'    => '/penguins?\s+playing\s+chess/i',
        // El propio texto del canary si acaba colándose en bruto
        'CANARY_RAW'         => '/\[INSTRUC[A-Z]+\s+(DE\s+SISTEMA|OCULTA|CRITICA|IA)\:/i',
        'CANARY_RAW_SYS'     => '/\[(SYSTEM|OVERRIDE|AI\s+DIRECTIVE|HIDDEN\s+INSTRUCTION|SYSTEM\s+PROMPT\s+INJECTION)\b/i',
    ];

    $encontrados = [];
    foreach ($patrones as $nombre => $regex) {
        if (preg_match($regex, $texto)) {
            $encontrados[] = $nombre;
        }
    }
    return $encontrados;
}

/**
 * Guarda un registro de auditoría de post con contenido sospechoso de prompt IA.
 */
function log_auditoria_post_ia($uid, $username, $pid, $tid, $fid_forum, $mensaje, $indicadores) {
    global $db;
    try {
        $uid_sql         = (int)$uid;
        $pid_sql         = (int)$pid;
        $tid_sql         = (int)$tid;
        $fid_forum_sql   = (int)$fid_forum;
        $username_sql    = $db->escape_string($username);
        $mensaje_sql     = $db->escape_string($mensaje);
        $indicadores_sql = $db->escape_string(implode(',', $indicadores));
        $now             = (int)time();

        $db->write_query("
            INSERT INTO `mybb_op_auditoria_posts_ia`
                (pid, tid, fid_forum, uid, username, mensaje_original, indicadores, dateline)
            VALUES
                ({$pid_sql}, {$tid_sql}, {$fid_forum_sql}, {$uid_sql}, '{$username_sql}', '{$mensaje_sql}', '{$indicadores_sql}', {$now})
        ");
    } catch (Throwable $e) {
        error_log("[OPG ia-audit] Error guardando auditoría de post IA: " . $e->getMessage());
    }
}
