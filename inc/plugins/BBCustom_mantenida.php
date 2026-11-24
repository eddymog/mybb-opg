<?php
/**
 * BBCustom Mantenida
 * Procesa BBCode [mantenida=tecnica_id] y [fin=tecnica_id]
 * 
 * Sistema de técnicas mantenidas:
 * - [mantenida=tecnica_id] inicia el contador de una técnica mantenida
 * - Desde ese momento, cada post del usuario muestra el contador de turnos
 * - [fin=tecnica_id] detiene el contador de esa técnica
 * 
 * Tabla necesaria: mybb_op_tecnicas_mantenidas
 * CREATE TABLE mybb_op_tecnicas_mantenidas (
 *   id INT AUTO_INCREMENT PRIMARY KEY,
 *   uid INT NOT NULL,
 *   tid INT NOT NULL,
 *   tecnica_id VARCHAR(100) NOT NULL,
 *   pid_inicio INT NOT NULL,
 *   turnos INT DEFAULT 1,
 *   activa TINYINT(1) DEFAULT 1,
 *   INDEX(uid, tid, activa)
 * );
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

/**
 * Función de logging para debug
 */
function BBCustom_mantenida_log($message, $data = null) {
    $log_file = MYBB_ROOT . '/inc/plugins/logs/BBCustom_mantenida_log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    
    if ($data !== null) {
        $log_message .= " | Data: " . print_r($data, true);
    }
    
    $log_message .= "\n";
    
    @file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Hooks
$plugins->add_hook("postbit", "BBCustom_mantenida_run");
$plugins->add_hook('datahandler_post_insert_post_end', 'BBCustom_mantenida_newpost');
$plugins->add_hook('datahandler_post_insert_thread_end', 'BBCustom_mantenida_newpost');

function BBCustom_mantenida_info()
{
    return array(
        "name"          => "Mantenida BBCode",
        "description"   => "BBCode [mantenida=tecnica_id] y [fin=tecnica_id] para técnicas mantenidas",
        "website"       => "",
        "author"        => "Cascabelles",
        "authorsite"    => "",
        "version"       => "2.0",
        "codename"      => "BBCustom_mantenida",
        "compatibility" => "*"
    );
}

function BBCustom_mantenida_activate()
{
    global $db;
    
    BBCustom_mantenida_log('ACTIVANDO PLUGIN BBCustom_mantenida');
    
    try {
        // Crear tabla de técnicas mantenidas si no existe
        $db->query("
            CREATE TABLE IF NOT EXISTS mybb_op_tecnicas_mantenidas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                uid INT NOT NULL,
                tid INT NOT NULL,
                tecnica_id VARCHAR(100) NOT NULL,
                pid_inicio INT NOT NULL,
                turnos INT DEFAULT 1,
                activa TINYINT(1) DEFAULT 1,
                INDEX(uid, tid, activa)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        BBCustom_mantenida_log('Tabla mybb_op_tecnicas_mantenidas creada');
        
        // Crear tabla para guardar HTML de técnicas mantenidas
        $db->query("
            CREATE TABLE IF NOT EXISTS mybb_op_mantenidas_html (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pid INT NOT NULL,
                html_content TEXT,
                UNIQUE KEY(pid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        BBCustom_mantenida_log('Tabla mybb_op_mantenidas_html creada');
        BBCustom_mantenida_log('PLUGIN ACTIVADO CORRECTAMENTE');
        
    } catch (Exception $e) {
        BBCustom_mantenida_log('ERROR AL ACTIVAR PLUGIN', array(
            'error' => $e->getMessage()
        ));
    }
}

function BBCustom_mantenida_deactivate() {}

function BBCustom_mantenida_run(&$post)
{
    global $db;
    
    try {
        BBCustom_mantenida_log('Iniciando BBCustom_mantenida_run');
        
        // Protección contra procesamiento múltiple
        static $processed_pids = array();
        $current_pid = isset($post['pid']) ? $post['pid'] : md5($post['message']);
        
        if (in_array($current_pid, $processed_pids)) {
            BBCustom_mantenida_log('Post ya procesado, saltando', array('pid' => $current_pid));
            return;
        }
        
        BBCustom_mantenida_log('Procesando post', array(
            'pid' => $post['pid'],
            'uid' => $post['uid'],
            'tid' => $post['tid']
        ));
    
        $message = &$post['message'];
        $uid = $post['uid'];
        $tid = $post['tid'];
        $pid = $post['pid'];
        
        // Procesar [mantenida=tecnica_id] o [mantenida=tid1,tid2,tid3] - convertir a marcador invisible
        while (preg_match('#\[mantenida=([^\]]+)\]#si', $message, $matches))
        {
            $tecnicas_raw = $matches[1];
            $tecnicas_list = array_map('trim', explode(',', $tecnicas_raw));
            BBCustom_mantenida_log('Encontrado [mantenida]', array('tecnicas' => $tecnicas_list));
            
            // Convertir cada técnica a comentario invisible
            $comentarios = array();
            foreach ($tecnicas_list as $tid) {
                if (!empty($tid)) {
                    $comentarios[] = '<!-- mantenida:' . $tid . ' -->';
                }
            }
            
            // Reemplazar con todos los comentarios
            $message = preg_replace(
                '#\[mantenida=' . preg_quote($tecnicas_raw, '#') . '\]#si',
                implode('', $comentarios),
                $message,
                1
            );
        }
        
        // Procesar [fin=tecnica_id] o [fin=tid1,tid2,tid3] - convertir a marcador invisible
        while (preg_match('#\[fin=([^\]]+)\]#si', $message, $matches))
        {
            $tecnicas_raw = $matches[1];
            $tecnicas_list = array_map('trim', explode(',', $tecnicas_raw));
            BBCustom_mantenida_log('Encontrado [fin]', array('tecnicas' => $tecnicas_list));
            
            // Convertir cada técnica a comentario invisible
            $comentarios = array();
            foreach ($tecnicas_list as $tid) {
                if (!empty($tid)) {
                    $comentarios[] = '<!-- fin:' . $tid . ' -->';
                }
            }
            
            // Reemplazar con todos los comentarios
            $message = preg_replace(
                '#\[fin=' . preg_quote($tecnicas_raw, '#') . '\]#si',
                implode('', $comentarios),
                $message,
                1
            );
        }
        
        // Procesar [mantenida_guardado=pid] - mostrar HTML guardado
        while (preg_match('#\[mantenida_guardado=(\d+)\]#si', $message, $matches))
        {
            $guardado_pid = $matches[1];
            BBCustom_mantenida_log('Encontrado [mantenida_guardado]', array('pid' => $guardado_pid));
            
            // Buscar el HTML guardado en mybb_op_mantenidas_html
            $query = $db->query("
                SELECT html_content 
                FROM mybb_op_mantenidas_html 
                WHERE pid = '$guardado_pid'
                LIMIT 1
            ");
            
            $guardado = $db->fetch_array($query);
            
            if ($guardado && !empty($guardado['html_content'])) {
                $html_guardado = $guardado['html_content'];
            } else {
                $html_guardado = '';
            }
            
            // Reemplazar con el HTML guardado
            $message = preg_replace(
                '#\[mantenida_guardado=' . $guardado_pid . '\]#si',
                $html_guardado,
                $message,
                1
            );
        }
        
        $processed_pids[] = $current_pid;
        BBCustom_mantenida_log('Finalizado BBCustom_mantenida_run exitosamente');
        
    } catch (Exception $e) {
        BBCustom_mantenida_log('ERROR en BBCustom_mantenida_run', array(
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ));
    }
}

/**
 * Hook para cuando se crea un nuevo post
 * Procesa [mantenida] y [fin] actualizando la BD
 */
function BBCustom_mantenida_newpost(&$data)
{
    global $db;
    
    BBCustom_mantenida_log('=== HOOK NEWPOST LLAMADO ===');
    
    try {
        BBCustom_mantenida_log('Iniciando BBCustom_mantenida_newpost');
        
        // Verificar estructura de $data
        if (!isset($data->post_insert_data) || !isset($data->return_values)) {
            BBCustom_mantenida_log('ERROR: Estructura de $data incorrecta', array(
                'tiene_post_insert_data' => isset($data->post_insert_data),
                'tiene_return_values' => isset($data->return_values)
            ));
            return;
        }
        
        $uid = $data->post_insert_data['uid'];
        $pid = $data->return_values['pid'];
        $tid = $data->post_insert_data['tid'];
        $message = $data->post_insert_data['message'];
        
        BBCustom_mantenida_log('Datos del nuevo post', array(
            'uid' => $uid,
            'pid' => $pid,
            'tid' => $tid,
            'tiene_mantenida' => (strpos($message, '[mantenida') !== false),
            'tiene_fin' => (strpos($message, '[fin') !== false)
        ));
    
    // Procesar [mantenida=tecnica_id] o [mantenida=tid1,tid2,tid3] - iniciar técnica(s) mantenida(s)
    if (preg_match_all('#\[mantenida=([^\]]+)\]#si', $message, $matches_mantenida, PREG_SET_ORDER)) {
        BBCustom_mantenida_log('Encontrados [mantenida] en newpost', array('count' => count($matches_mantenida)));
        
        foreach ($matches_mantenida as $match) {
            $tecnicas_raw = $match[1];
            $tecnicas_list = array_map('trim', explode(',', $tecnicas_raw));
            
            BBCustom_mantenida_log('Procesando lista de mantenidas', array('tecnicas' => $tecnicas_list));
            
            foreach ($tecnicas_list as $tecnica_id) {
                if (empty($tecnica_id)) continue;
                
                BBCustom_mantenida_log('Procesando mantenida individual', array('tecnica_id' => $tecnica_id));
                
                // Verificar si ya existe una técnica activa con este ID
                $query_existe = $db->query("
                    SELECT id FROM mybb_op_tecnicas_mantenidas 
                    WHERE uid='$uid' AND tid='$tid' AND tecnica_id='$tecnica_id' AND activa=1
                    LIMIT 1
                ");
                
                $existe = $db->fetch_array($query_existe);
                
                if (!$existe) {
                    BBCustom_mantenida_log('Insertando nueva técnica mantenida', array('tecnica_id' => $tecnica_id));
                    // Insertar nueva técnica mantenida
                    $db->query("
                        INSERT INTO mybb_op_tecnicas_mantenidas 
                        (uid, tid, tecnica_id, pid_inicio, turnos, activa) 
                        VALUES ('$uid', '$tid', '$tecnica_id', '$pid', 1, 1)
                    ");
                } else {
                    BBCustom_mantenida_log('Técnica ya existe, no se inserta', array('tecnica_id' => $tecnica_id));
                }
            }
        }
    }
    
    // Procesar [fin=tecnica_id] o [fin=tid1,tid2,tid3] - detener técnica(s) mantenida(s)
    $tecnicas_finalizadas = array();
    if (preg_match_all('#\[fin=([^\]]+)\]#si', $message, $matches_fin, PREG_SET_ORDER)) {
        BBCustom_mantenida_log('Encontrados [fin] en newpost', array('count' => count($matches_fin)));
        
        foreach ($matches_fin as $match) {
            $tecnicas_raw = $match[1];
            $tecnicas_list = array_map('trim', explode(',', $tecnicas_raw));
            
            BBCustom_mantenida_log('Procesando lista de finalizaciones', array('tecnicas' => $tecnicas_list));
            
            foreach ($tecnicas_list as $tecnica_id) {
                if (empty($tecnica_id)) continue;
                
                BBCustom_mantenida_log('Finalizando técnica individual', array('tecnica_id' => $tecnica_id));
                
                // Obtener datos de la técnica antes de desactivarla
                $query_tecnica = $db->query("
                    SELECT t.tecnica_id, t.turnos, tec.nombre
                    FROM mybb_op_tecnicas_mantenidas t
                    LEFT JOIN mybb_op_tecnicas tec ON tec.tid = t.tecnica_id
                    WHERE t.uid='$uid' AND t.tid='$tid' AND t.tecnica_id='$tecnica_id' AND t.activa=1
                    LIMIT 1
                ");
                
                $tecnica_finalizada = $db->fetch_array($query_tecnica);
                if ($tecnica_finalizada) {
                    $tecnicas_finalizadas[] = $tecnica_finalizada;
                }
                
                // Desactivar la técnica mantenida
                $db->query("
                    UPDATE mybb_op_tecnicas_mantenidas 
                    SET activa = 0 
                    WHERE uid='$uid' AND tid='$tid' AND tecnica_id='$tecnica_id' AND activa=1
                ");
            }
        }
    }
    
    // Incrementar turnos de todas las técnicas activas de este usuario en este thread
    // (excepto las que se acaban de iniciar en este mismo post)
    BBCustom_mantenida_log('Incrementando turnos de técnicas activas');
    $db->query("
        UPDATE mybb_op_tecnicas_mantenidas 
        SET turnos = turnos + 1 
        WHERE uid='$uid' AND tid='$tid' AND activa=1 AND pid_inicio != '$pid'
    ");
    
    // Generar HTML del contador de técnicas activas y guardarlo
    $query_activas = $db->query("
        SELECT t.tecnica_id, t.turnos, tec.nombre
        FROM mybb_op_tecnicas_mantenidas t
        LEFT JOIN mybb_op_tecnicas tec ON tec.tid = t.tecnica_id
        WHERE t.uid = '$uid' AND t.tid = '$tid' AND t.activa = 1
        ORDER BY t.id ASC
    ");
    
    $tecnicas_activas = array();
    while ($tecnica = $db->fetch_array($query_activas)) {
        $tecnicas_activas[] = $tecnica;
    }
    
    BBCustom_mantenida_log('Técnicas activas para guardar', array('count' => count($tecnicas_activas)));
    BBCustom_mantenida_log('Técnicas finalizadas para mostrar', array('count' => count($tecnicas_finalizadas)));
    
    // Generar HTML combinado en un solo bloque
    if (!empty($tecnicas_activas) || !empty($tecnicas_finalizadas)) {
        $html_completo = BBCustom_mantenida_generate_contador_combinado($tecnicas_activas, $tecnicas_finalizadas);
        
        // Guardar en la tabla de HTML
        $html_escaped = $db->escape_string($html_completo);
        $db->query("
            INSERT INTO mybb_op_mantenidas_html (pid, html_content) 
            VALUES ('$pid', '$html_escaped')
            ON DUPLICATE KEY UPDATE html_content = '$html_escaped'
        ");
        
        // Agregar [mantenida_guardado=pid] al final del mensaje guardado. Se añade en la propia base de datos (CONCAT)
        // en vez de reescribir el mensaje entero desde $data->post_insert_data: ese valor llega ya escapado para SQL
        // (escaparlo otra vez dejaba barras visibles) y no incluye lo que hayan reescrito otros plugins en este mismo
        // post (BBCustom_dado, BBCustom_consumir); reescribirlo deshacía sus reemplazos (issue #32).
        $pid_sql = (int)$pid;
        $sufijo = $db->escape_string("\n[mantenida_guardado={$pid_sql}]");
        $db->query("
            UPDATE mybb_posts
            SET message = CONCAT(message, '$sufijo')
            WHERE pid = '$pid_sql'
        ");
        
        BBCustom_mantenida_log('HTML guardado y mensaje actualizado', array('pid' => $pid));
    }
    
    BBCustom_mantenida_log('Finalizado BBCustom_mantenida_newpost exitosamente');
    
    } catch (Exception $e) {
        BBCustom_mantenida_log('ERROR en BBCustom_mantenida_newpost', array(
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ));
    }
}

/**
 * Genera el HTML del contador de técnicas mantenidas
 * @param array $tecnicas Array de técnicas
 * @param bool $finalizada Si es true, muestra como "FINALIZADA"
 */
function BBCustom_mantenida_generate_contador($tecnicas, $finalizada = false)
{
    $items_html = '';
    
    foreach ($tecnicas as $tecnica) {
        $tecnica_id = htmlspecialchars($tecnica['tecnica_id']);
        $nombre = htmlspecialchars($tecnica['nombre'] ? $tecnica['nombre'] : $tecnica_id);
        $turnos = intval($tecnica['turnos']);
        
        // Color según número de turnos
        if ($turnos >= 5) {
            $color = '#e74c3c'; // Rojo - muchos turnos
        } else if ($turnos >= 3) {
            $color = '#f39c12'; // Naranja - varios turnos
        } else {
            $color = '#3498db'; // Azul - pocos turnos
        }
        
        // Si está finalizada, usar color gris y agregar indicador
        if ($finalizada) {
            $color = '#95a5a6'; // Gris para finalizadas
            $estado_badge = "<div style='background: #7f8c8d; color: white; padding: 4px 10px; border-radius: 12px; font-size: 10px; margin-top: 4px; display: inline-block;'>✓ FINALIZADA</div>";
            $turnos_text = "Duró $turnos turno" . ($turnos != 1 ? 's' : '');
        } else {
            $estado_badge = '';
            $turnos_text = "$turnos turno" . ($turnos != 1 ? 's' : '');
        }
        
        $items_html .= "
            <div style='background: rgba(255,255,255,0.1); 
                        border-left: 4px solid $color; 
                        border-radius: 8px; 
                        padding: 10px 15px; 
                        margin-bottom: 8px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;" . ($finalizada ? " opacity: 0.7;" : "") . "'>
                <div>
                    <div style='color: white; font-weight: bold; font-size: 14px;'>$nombre</div>
                    <div style='color: rgba(255,255,255,0.7); font-size: 11px;'>ID: $tecnica_id</div>
                    $estado_badge
                </div>
                <div style='background: $color; 
                            color: white; 
                            padding: 8px 15px; 
                            border-radius: 20px; 
                            font-weight: bold; 
                            font-size: 16px;
                            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);'>
                    $turnos_text
                </div>
                </div>
            </div>
        ";
    }
    
    // Título según el tipo
    if ($finalizada) {
        $titulo = '🔴 TÉCNICAS FINALIZADAS';
        $border_color = '#95a5a6';
    } else {
        $titulo = '⚡ TÉCNICAS MANTENIDAS';
        $border_color = '#9b59b6';
    }
    
    $html = "
        <div style='background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
                    padding: 20px; 
                    border-radius: 12px; 
                    margin-top: 20px; 
                    border-top: 3px solid $border_color;
                    box-shadow: 0 6px 12px rgba(0,0,0,0.3);'>
            <div style='text-align: center; margin-bottom: 15px;'>
                <h3 style='color: $border_color; 
                           font-size: 18px; 
                           margin: 0;
                           text-shadow: 1px 1px 2px rgba(0,0,0,0.5);'>
                    $titulo
                </h3>
            </div>
            $items_html
        </div>
    ";
    
    return $html;
}

/**
 * Genera el HTML combinado de técnicas activas y finalizadas en un solo bloque
 */
function BBCustom_mantenida_generate_contador_combinado($tecnicas_activas, $tecnicas_finalizadas)
{
    $items_html = '';
    
    // Procesar técnicas activas
    if (!empty($tecnicas_activas)) {
        foreach ($tecnicas_activas as $tecnica) {
            $tecnica_id = htmlspecialchars($tecnica['tecnica_id']);
            $nombre = htmlspecialchars($tecnica['nombre'] ? $tecnica['nombre'] : $tecnica_id);
            $turnos = intval($tecnica['turnos']);
            
            // Color según número de turnos
            if ($turnos >= 5) {
                $color = '#e74c3c'; // Rojo - muchos turnos
            } else if ($turnos >= 3) {
                $color = '#f39c12'; // Naranja - varios turnos
            } else {
                $color = '#3498db'; // Azul - pocos turnos
            }
            
            $turnos_text = "$turnos turno" . ($turnos != 1 ? 's' : '');
            
            $items_html .= "
                <div style='background: rgba(255,255,255,0.1); 
                            border-left: 4px solid $color; 
                            border-radius: 8px; 
                            padding: 10px 15px; 
                            margin-bottom: 8px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;'>
                    <div>
                        <div style='color: white; font-weight: bold; font-size: 14px;'>$nombre</div>
                        <div style='color: rgba(255,255,255,0.7); font-size: 11px;'>ID: $tecnica_id</div>
                    </div>
                    <div style='background: $color; 
                                color: white; 
                                padding: 8px 15px; 
                                border-radius: 20px; 
                                font-weight: bold; 
                                font-size: 16px;
                                text-shadow: 1px 1px 2px rgba(0,0,0,0.5);'>
                        $turnos_text
                    </div>
                </div>
            ";
        }
    }
    
    // Procesar técnicas finalizadas
    if (!empty($tecnicas_finalizadas)) {
        foreach ($tecnicas_finalizadas as $tecnica) {
            $tecnica_id = htmlspecialchars($tecnica['tecnica_id']);
            $nombre = htmlspecialchars($tecnica['nombre'] ? $tecnica['nombre'] : $tecnica_id);
            $turnos = intval($tecnica['turnos']);
            
            $color = '#95a5a6'; // Gris para finalizadas
            $turnos_text = "Duró $turnos turno" . ($turnos != 1 ? 's' : '');
            
            $items_html .= "
                <div style='background: rgba(255,255,255,0.1); 
                            border-left: 4px solid $color; 
                            border-radius: 8px; 
                            padding: 10px 15px; 
                            margin-bottom: 8px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            opacity: 0.7;'>
                    <div>
                        <div style='color: white; font-weight: bold; font-size: 14px;'>$nombre</div>
                        <div style='color: rgba(255,255,255,0.7); font-size: 11px;'>ID: $tecnica_id</div>
                        <div style='background: #7f8c8d; color: white; padding: 4px 10px; border-radius: 12px; font-size: 10px; margin-top: 4px; display: inline-block;'>✓ FINALIZADA</div>
                    </div>
                    <div style='background: $color; 
                                color: white; 
                                padding: 8px 15px; 
                                border-radius: 20px; 
                                font-weight: bold; 
                                font-size: 16px;
                                text-shadow: 1px 1px 2px rgba(0,0,0,0.5);'>
                        $turnos_text
                    </div>
                </div>
            ";
        }
    }
    
    $html = "
        <div style='background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
                    padding: 20px; 
                    border-radius: 12px; 
                    margin-top: 20px; 
                    border-top: 3px solid #9b59b6;
                    box-shadow: 0 6px 12px rgba(0,0,0,0.3);'>
            <div style='text-align: center; margin-bottom: 15px;'>
                <h3 style='color: #9b59b6; 
                           font-size: 18px; 
                           margin: 0;
                           text-shadow: 1px 1px 2px rgba(0,0,0,0.5);'>
                    ⚡ TÉCNICAS MANTENIDAS
                </h3>
            </div>
            $items_html
        </div>
    ";
    
    return $html;
}
