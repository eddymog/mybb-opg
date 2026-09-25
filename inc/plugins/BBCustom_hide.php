<?php
/**
 * BBCustom Hide
 * Procesa BBCode [hide] para contenido oculto
 * 
 * Solo muestra el contenido a:
 * - El autor del post
 * - Cuando el thread está cerrado
 * - Cuando alguien ya clickeó "Mostrar Contenido Oculto" (queda revelado para todos)
 *
 * Staff no tiene ningún acceso especial: ven el hide en las mismas
 * condiciones que cualquier otro usuario que no sea el autor.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// Cargar librería de spoilers
require_once MYBB_ROOT . "inc/plugins/lib/spoiler.php";
require_once MYBB_ROOT . "inc/class_parser.php";

// Hook para procesar DESPUÉS de MyBB
$plugins->add_hook("postbit", "BBCustom_hide_run");
$plugins->add_hook('datahandler_post_insert_post_end', 'BBCustom_hide_newpost');
$plugins->add_hook('datahandler_post_insert_thread_end', 'BBCustom_hide_newpost');

function BBCustom_hide_info()
{
    return array(
        "name"          => "Hide BBCode",
        "description"   => "BBCode [hide] para contenido oculto modular (a ver si de paso ahora va bien)",
        "website"       => "",
        "author"        => "Cascabelles - Kurosame",
        "authorsite"    => "",
        "version"       => "2.0",
        "codename"      => "BBCustom_hide",
        "compatibility" => "*"
    );
}

function BBCustom_hide_activate() {}
function BBCustom_hide_deactivate() {}

function BBCustom_hide_run(&$post)
{
    global $mybb, $db, $thread;
    
    // Protección contra procesamiento múltiple
    static $processed_pids = array();
    $current_pid = isset($post['pid']) ? $post['pid'] : md5($post['message']);
    
    if (in_array($current_pid, $processed_pids)) {
        return;
    }
    
    $message = &$post['message'];
    $user_uid = $mybb->user['uid'];
    $post_uid = $post['uid'];
    $pid = $post['pid'];
    $tid = $post['tid'];
    
    // Verificar si el thread está cerrado
    $is_closed = (isset($thread['closed']) && $thread['closed'] == 1);
    
    // Verificar si el usuario es el autor
    $is_author = ($user_uid == $post_uid);

    // Parser para el contenido
    $parser = new postParser;
    $parser_options = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_imgcode" => 1,
        "allow_videocode" => 1,
    );
    
    // Procesar todos los [hide=X] en el mensaje
    while (preg_match('#\[hide=(\d+)\]#si', $message, $matches))
    {
        $hide_counter = $matches[1];
        
        // Buscar el hide en la base de datos
        $query_hide = $db->query("
            SELECT * FROM mybb_op_hide 
            WHERE pid='$pid' AND tid='$tid' AND hide_counter='$hide_counter'
            LIMIT 1
        ");
        
        $hide = $db->fetch_array($query_hide);
        
        if (!$hide) {
            // Si no existe el hide, eliminar el marcador y los <br> alrededor
            $message = preg_replace('#(<br\s*/?>)*\s*\[hide=' . preg_quote($hide_counter, '#') . '\]\s*(<br\s*/?>)*#si', '', $message, 1);
            continue;
        }
        
        $hide_id = $hide['hid'];
        $hide_content = $hide['hide_content'];
        $show_hide = $hide['show_hide'];
        
        // Determinar si el usuario puede ver el contenido
        $can_see = ($is_author || $is_closed || $show_hide);

        if (!$can_see) {
            // Si el usuario NO tiene permiso, eliminar el hide completamente incluyendo <br> alrededor
            $message = preg_replace('#(<br\s*/?>)*\s*\[hide=' . preg_quote($hide_counter, '#') . '\]\s*(<br\s*/?>)*#si', '', $message, 1);
            continue;
        }

        // El usuario tiene permiso - generar el contenido
        $content_visible = ($is_closed || $show_hide);
        
        if ($content_visible) {
            // Mostrar el contenido directamente
            $contenido = $parser->parse_message($hide_content, $parser_options);
        } else {
            // Mostrar botón para revelar — pide confirmación porque es
            // irreversible: una vez revelado queda visible para siempre,
            // para cualquier usuario que lo vea despues.
            $confirm_msg = '¿Revelar este contenido oculto? Esta acción es irreversible: quedará visible para todos los usuarios de forma permanente.';
            $hide_button = '<div style="text-align: center; margin: 10px 0;">
                <button class="hide-button" onclick="javascript: if (confirm(\''.$confirm_msg.'\')) { document.getElementById(\'hideform'.$hide_id.'\').submit(); }"
                        style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
                    🔓 Mostrar Contenido Oculto
                </button>
            </div>';
            
            $hidden_form = '<div style="display: none">
                <form id="hideform'.$hide_id.'" method="post" action="/op/hide.php">
                    <input type="hidden" name="hid" value="'.$hide_id.'" />
                    <input type="hidden" name="show_hide" value="1" />
                    <input type="hidden" name="tid" value="'.$tid.'" />
                </form>
            </div>';
            
            $contenido = $hidden_form . $hide_button . '<hr />' . $parser->parse_message($hide_content, $parser_options);
        }
        
        // Para el autor, el titulo del spoiler indica si el hide ya fue
        // descubierto (show_hide) o sigue oculto para el resto. Un no-autor
        // solo llega a ver el bloque cuando ya esta revelado (can_see y
        // content_visible usan la misma formula para el, sin el termino de
        // autor) — para el, siempre esta ya revelado cuando lo ve.
        if ($is_author) {
            if ($show_hide) {
                $spoiler_title = "Contenido Descubierto";
                $spoiler_icon = '🔓';
            } else {
                $spoiler_title = "Contenido Oculto";
                $spoiler_icon = '🔒';
            }
        } else {
            $spoiler_title = "Contenido Revelado";
            $spoiler_icon = '🔓';
        }

        // Generar spoiler usando la librería nueva
        $spoiler_html = create_custom_spoiler($spoiler_title, $contenido, array(
            'icon' => $spoiler_icon,
            'bg_color' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'text_color' => 'white',
            'open' => $content_visible  // Abrir automáticamente si ya es visible
        ));
        
        // Reemplazar el marcador con el spoiler
        $message = preg_replace(
            '#\[hide=' . preg_quote($hide_counter, '#') . '\]#si',
            $spoiler_html,
            $message,
            1
        );
    }
    
    $processed_pids[] = $current_pid;
}

/**
 * Hook para cuando se crea un nuevo post
 * Convierte [hide]contenido[/hide] en [hide=1], [hide=2], etc.
 * y guarda el contenido en la base de datos
 */
function BBCustom_hide_newpost(&$data)
{
    global $db;
    
    $uid = $data->post_insert_data['uid'];
    $pid = $data->return_values['pid'];
    $tid = $data->post_insert_data['tid'];
    $message = $data->post_insert_data['message'];
    $hide_counter = 0;
    
    // Procesar todos los [hide]...[/hide]
    while (preg_match('#\[hide\](.*?)\[\/hide\]#si', $message, $matches))
    {
        $hide_content = $matches[1];
        $hide_counter += 1;
        
        // Guardar en la base de datos
        $db->query("
            INSERT INTO `mybb_op_hide` 
            (`tid`, `pid`, `uid`, `hide_counter`, `show_hide`, `hide_content`) 
            VALUES ('$tid', '$pid', '$uid', '$hide_counter', 0, '$hide_content')
        ");
        
        // Reemplazar [hide]...[/hide] con [hide=X]
        $message = preg_replace(
            '#\[hide\](.*?)\[\/hide\]#si',
            "[hide=$hide_counter]",
            $message,
            1
        );
    }
    
    // Si se procesó algún hide, actualizar el mensaje en la BD
    if ($hide_counter > 0) {
        $db->query("
            UPDATE `mybb_posts` 
            SET message='$message' 
            WHERE pid='$pid'
        ");
    }
}
