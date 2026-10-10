<?php
/**
 * BBCustom Dado
 * Procesa BBCode [XdY]
 * 
 * Lanza X dados de Y caras y muestra el resultado
 * Ejemplo: [2d20] lanza 2 dados de 20 caras
 * 
 * Al crear el post, convierte [XdY] en [dado_guardado=X]
 * donde X es el contador en la tabla mybb_op_dados
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// Cargar librería de spoilers
require_once MYBB_ROOT . "inc/plugins/lib/spoiler.php";

// Hooks. Las prioridades importan porque [XdY] puede ir dentro de [hide] (BBCustom_hide):
//  - Al crear el post, los dados se lanzan ANTES (prioridad 5) que el hide guarde su contenido (15),
//    para que el hide guarde [dado_guardado=N] y no el texto [XdY] sin lanzar.
//  - Al mostrarlo, el hide se despliega PRIMERO (prioridad 10) y los dados se sustituyen DESPUÉS (20),
//    así también se resuelven los que quedaron dentro del contenido del hide.
$plugins->add_hook("postbit", "BBCustom_dado_run", 20);
$plugins->add_hook('datahandler_post_insert_post_end', 'BBCustom_dado_newpost', 5);
$plugins->add_hook('datahandler_post_insert_thread_end', 'BBCustom_dado_newpost', 5);

function BBCustom_dado_info()
{
    return array(
        "name"          => "Dado BBCode",
        "description"   => "BBCode [XdY] para lanzar dados (ejemplo: [2d20])",
        "website"       => "",
        "author"        => "Cascabelles - Kurosame",
        "authorsite"    => "",
        "version"       => "2.0",
        "codename"      => "BBCustom_dado",
        "compatibility" => "*"
    );
}

function BBCustom_dado_activate() {}
function BBCustom_dado_deactivate() {}

function BBCustom_dado_run(&$post)
{
    global $db;
    
    // Protección contra procesamiento múltiple
    static $processed_pids = array();
    $current_pid = isset($post['pid']) ? $post['pid'] : md5($post['message']);
    
    if (in_array($current_pid, $processed_pids)) {
        return;
    }
    
    $message = &$post['message'];
    $pid = $post['pid'];
    $tid = $post['tid'];
    $is_edited = $post['edittime'];
    
    // Eliminar saltos de línea entre dados consecutivos
    $message = preg_replace('#(\[dado_guardado=\d+\])\s*(<br\s*/?>|\r\n|\n)+\s*(\[dado_guardado=\d+\])#si', '$1$3', $message);
    
    // Procesar [dado_guardado=X] - mostrar el resultado ya guardado
    while (preg_match('#\[dado_guardado=(\d+)\]#si', $message, $matches))
    {
        $dado_counter = $matches[1];
        
        // Buscar en la base de datos
        $query = $db->query("
            SELECT * FROM mybb_op_dados 
            WHERE pid='$pid' AND tid='$tid' AND dado_counter='$dado_counter'
            LIMIT 1
        ");
        
        $dado = $db->fetch_array($query);
        
        if ($dado) {
            $dado_content = $dado['dado_content'];
            
            // Mensaje de advertencia si el post fue editado
            $post_editado = "";
            if ($is_edited) {
                $post_editado = '<div style="background: #f39c12; color: white; padding: 8px; border-radius: 5px; margin-bottom: 10px; text-align: center; font-weight: bold;">
                    ⚠️ Este post ha sido editado
                </div>';
            }
            
            $content = $post_editado . $dado_content;
        } else {
            $content = '<div style="background: #f44336; color: white; padding: 10px; border-radius: 5px; text-align: center;">
                ⚠️ Resultado de dado no encontrado
            </div>';
        }
        
        // Reemplazar con el contenido guardado
        $message = preg_replace(
            '#\[dado_guardado=' . preg_quote($dado_counter, '#') . '\]#si',
            $content,
            $message,
            1
        );
    }
    
    $processed_pids[] = $current_pid;
}

/**
 * Hook para cuando se crea un nuevo post
 * Lanza los dados y guarda el resultado
 */
function BBCustom_dado_newpost(&$data)
{
    global $db;
    
    $uid = (int) $data->post_insert_data['uid'];
    $pid = (int) $data->return_values['pid'];
    $tid = (int) $data->post_insert_data['tid'];
    $dado_counter = 0;

    // Se parte del mensaje guardado en la base de datos y no de $data->post_insert_data: ahí
    // llega ya escapado para SQL (escaparlo otra vez dejaba barras antes de las comillas) y
    // además refleja lo que hayan reescrito antes otros plugins.
    $fila_post = $db->fetch_array($db->query("SELECT message, username FROM mybb_posts WHERE pid='$pid' LIMIT 1"));
    if (!$fila_post) {
        return;
    }
    $message = $fila_post['message'];
    $username = $fila_post['username'];
    
    // Procesar todos los [XdY]
    while (preg_match('#\[(\d+d\d+)\]#si', $message, $matches))
    {
        $dados_texto = $matches[1];
        $dados_arr = explode("d", $dados_texto);
        
        // Validar formato XdY
        if (count($dados_arr) != 2) {
            $message = preg_replace(
                "#\[" . preg_quote($dados_texto, '#') . "\]#si",
                "[dadoinvalido=$dados_texto]",
                $message,
                1
            );
            continue;
        }
        
        $num_dados = intval($dados_arr[0]);
        $num_caras = intval($dados_arr[1]);
        
        // Validar límites (máximo 20 dados de 100000 caras)
        if ($num_dados <= 0 || $num_dados > 20 || $num_caras <= 0 || $num_caras > 100000) {
            $message = preg_replace(
                "#\[" . preg_quote($dados_texto, '#') . "\]#si",
                "[dadoinvalido=$dados_texto]",
                $message,
                1
            );
            continue;
        }
        
        // Lanzar los dados
        $dado_counter += 1;
        $resultados = array();
        $total = 0;
        
        for ($i = 1; $i <= $num_dados; $i++) {
            $resultado = rand(1, $num_caras);
            $resultados[] = array('num' => $i, 'valor' => $resultado);
            $total += $resultado;
        }
        
        // Generar HTML del resultado
        $dado_html = BBCustom_dado_generate_html($username, $num_dados, $num_caras, $resultados, $total, $dado_counter);
        
        // Guardar en la base de datos
        $dado_content = $db->escape_string($dado_html);
        $db->query("
            INSERT INTO mybb_op_dados 
            (tid, pid, uid, dado_counter, dado_content) 
            VALUES ('$tid', '$pid', '$uid', '$dado_counter', '$dado_content')
        ");
        
        // Reemplazar [XdY] con [dado_guardado=X]
        $message = preg_replace(
            "#\[" . preg_quote($dados_texto, '#') . "\]#si",
            "[dado_guardado=$dado_counter]",
            $message,
            1
        );
    }
    
    // Si se procesó algún dado, actualizar el mensaje
    if ($dado_counter > 0) {
        $message_escaped = $db->escape_string($message);
        $db->query("
            UPDATE mybb_posts 
            SET message='$message_escaped' 
            WHERE pid='$pid'
        ");
    }
}

/**
 * Genera el HTML del resultado de los dados
 */
function BBCustom_dado_generate_html($username, $num_dados, $num_caras, $resultados, $total, $dado_id)
{
    // Color según el resultado promedio
    $promedio = $total / $num_dados;
    $porcentaje = ($promedio / $num_caras) * 100;
    
    if ($porcentaje >= 75) {
        $color = '#27ae60'; // Verde - resultado alto
        $emoji = '🎲✨';
    } else if ($porcentaje >= 50) {
        $color = '#3498db'; // Azul - resultado medio-alto
        $emoji = '🎲';
    } else if ($porcentaje >= 25) {
        $color = '#f39c12'; // Amarillo - resultado medio-bajo
        $emoji = '🎲';
    } else {
        $color = '#e74c3c'; // Rojo - resultado bajo
        $emoji = '🎲💔';
    }
    
    // Generar lista de resultados
    $resultados_html = '';
    foreach ($resultados as $r) {
        $valor = $r['valor'];
        $num = $r['num'];
        
        // Color individual del dado
        $dado_porcentaje = ($valor / $num_caras) * 100;
        if ($dado_porcentaje >= 75) {
            $dado_color = '#2ecc71';
        } else if ($dado_porcentaje >= 50) {
            $dado_color = '#3498db';
        } else if ($dado_porcentaje >= 25) {
            $dado_color = '#f39c12';
        } else {
            $dado_color = '#e67e22';
        }
        
        $resultados_html .= "
            <div style='display: inline-block; 
                        background: linear-gradient(135deg, {$dado_color} 0%, " . BBCustom_dado_darken_color($dado_color) . " 100%); 
                        padding: 15px 20px; 
                        border-radius: 10px; 
                        margin: 5px; 
                        min-width: 60px;
                        text-align: center;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.3);'>
                <div style='color: rgba(255,255,255,0.7); font-size: 10px; font-weight: bold;'>DADO {$num}</div>
                <div style='color: white; font-size: 28px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);'>{$valor}</div>
            </div>
        ";
    }
    
    // HTML principal
    $html = "
    <div style='background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); 
                padding: 20px; 
                border-radius: 12px; 
                margin: 15px 0; 
                border-left: 5px solid {$color};
                box-shadow: 0 6px 12px rgba(0,0,0,0.3);'>
        
        <!-- Encabezado -->
        <div style='text-align: center; margin-bottom: 15px;'>
            <div style='color: white; font-size: 16px; font-weight: bold; margin-bottom: 5px;'>
                {$emoji} <span style='color: {$color};'>{$username}</span> lanzó {$num_dados} dado(s) de {$num_caras} caras
            </div>
            <div style='color: #95a5a6; font-size: 12px;'>ID: #{$dado_id}</div>
        </div>
        
        <!-- Resultados de los dados -->
        <div style='text-align: center; margin: 20px 0;'>
            {$resultados_html}
        </div>
        
        <!-- Total -->
        <div style='text-align: center; 
                    background: rgba(0,0,0,0.3); 
                    padding: 12px; 
                    border-radius: 8px; 
                    margin-top: 15px;'>
            <div style='color: rgba(255,255,255,0.7); font-size: 12px; margin-bottom: 5px;'>TOTAL</div>
            <div style='color: {$color}; font-size: 32px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);'>{$total}</div>
            <div style='color: rgba(255,255,255,0.5); font-size: 11px; margin-top: 5px;'>Promedio: " . number_format($promedio, 2) . "</div>
        </div>
    </div>
    ";
    
    return $html;
}

/**
 * Oscurece un color hexadecimal para gradientes
 */
function BBCustom_dado_darken_color($color, $percent = 20)
{
    $color = str_replace('#', '', $color);
    
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    
    $r = (int) max(0, $r - ($r * $percent / 100));
    $g = (int) max(0, $g - ($g * $percent / 100));
    $b = (int) max(0, $b - ($b * $percent / 100));
    
    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) 
              . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) 
              . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}
