<?php
/**
 * BBCustom Consumir
 * Procesa BBCode [consumir=OBJETO_ID]
 *
 * Consume un objeto del inventario del autor del post y muestra:
 * - Información del objeto consumido
 * - Cantidad restante
 * - Tooltip con detalles completos
 *
 * Al crear el post y al editarlo, cada [consumir=ID] nueva se convierte en [consumido=X], donde X es el contador en la
 * tabla mybb_op_consumir (en una edición sigue al mayor ya usado en el post), o en [consumibleinvalido=ID] si no hay
 * unidades. Al mostrar el post, [consumir=ID] cruda y [consumibleinvalido=ID] se ven como una nota discreta.
 * La lógica está en inc/plugins/lib/consumir.php (issue #32).
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

require_once MYBB_ROOT . "inc/plugins/lib/consumir.php";

// Hooks
$plugins->add_hook("postbit", "BBCustom_consumir_run");
$plugins->add_hook('datahandler_post_insert_post_end', 'BBCustom_consumir_newpost');
$plugins->add_hook('datahandler_post_insert_thread_end', 'BBCustom_consumir_newpost');
// Al editar: corre antes del UPDATE de MyBB, así el mensaje se guarda ya con los reemplazos (sin un UPDATE extra)
$plugins->add_hook('datahandler_post_update', 'BBCustom_consumir_editpost');

function BBCustom_consumir_info()
{
    return array(
        "name"          => "Consumir BBCode",
        "description"   => "BBCode [consumir=ID] para consumir objetos del inventario",
        "website"       => "",
        "author"        => "Cascabelles - Kurosame",
        "authorsite"    => "",
        "version"       => "2.1",
        "codename"      => "BBCustom_consumir",
        "compatibility" => "*"
    );
}

function BBCustom_consumir_activate() {}
function BBCustom_consumir_deactivate() {}

function BBCustom_consumir_run(&$post)
{
    global $db;

    // Protección contra procesamiento múltiple
    static $processed_pids = array();
    $current_pid = isset($post['pid']) ? $post['pid'] : md5($post['message']);

    if (in_array($current_pid, $processed_pids)) {
        return;
    }

    $message = &$post['message'];
    $pid = (int)$post['pid'];
    $tid = (int)$post['tid'];

    // Procesar [consumido=X] - mostrar el objeto ya consumido
    while (preg_match('#\[consumido=(\d+)\]#si', $message, $matches))
    {
        $counter = (int)$matches[1];

        // Buscar en la base de datos
        $query = $db->query("
            SELECT * FROM mybb_op_consumir
            WHERE pid='$pid' AND tid='$tid' AND counter='$counter'
            LIMIT 1
        ");

        $consumir = $db->fetch_array($query);

        if ($consumir) {
            $content = $consumir['content'];
        } else {
            $content = '<div style="background: #f44336; color: white; padding: 10px; border-radius: 5px; text-align: center;">
                ⚠️ Objeto consumido no encontrado
            </div>';
        }

        // Reemplazar con el contenido guardado (con callback: un "$1" o una barra del contenido no se interpretan)
        $message = preg_replace_callback(
            '#\[consumido=' . preg_quote($matches[1], '#') . '\]#si',
            function () use ($content) { return $content; },
            $message,
            1
        );
    }

    // [consumir=ID] sin procesar y [consumibleinvalido=ID]: nota discreta en vez de código (también en posts viejos)
    $ids = opg_consumir_ids_en_html($message);
    if ($ids) {
        $message = opg_consumir_renderizar_notas($message, opg_consumir_nombres($db, $ids));
    }

    $processed_pids[] = $current_pid;
}

/**
 * Post guardado (pid, tid, uid, username, message, visible). Se lee de la base de datos y no de $data: en
 * post_insert_data el mensaje llega ya escapado para SQL (escaparlo otra vez dejaba barras visibles) y sin lo que hayan
 * reescrito antes otros plugins (BBCustom_dado); al publicar un borrador ni siquiera existe post_insert_data.
 */
function BBCustom_consumir_post($pid)
{
    global $db;
    $pid = (int)$pid;
    if ($pid <= 0) {
        return null;
    }
    return $db->fetch_array($db->query("SELECT pid, tid, uid, username, message, visible FROM mybb_posts WHERE pid='$pid' LIMIT 1"));
}

/**
 * Hook para cuando se crea un post (o se publica un borrador).
 * Consume los objetos del inventario del autor y guarda el HTML.
 */
function BBCustom_consumir_newpost(&$data)
{
    global $db;

    $pid = isset($data->return_values['pid']) ? (int)$data->return_values['pid'] : (int)$data->pid;
    $post = BBCustom_consumir_post($pid);
    // Un borrador no consume: lo hará al publicarse (este mismo hook vuelve a correr)
    if (!$post || (int)$post['visible'] === -2 || stripos($post['message'], '[consumir=') === false) {
        return;
    }

    $message = opg_consumir_procesar_mensaje($db, $post['message'], '', array(
        'uid' => (int)$post['uid'],
        'username' => $post['username'],
        'pid' => $pid,
        'tid' => (int)$post['tid'],
    ));

    // Se escapa una sola vez: el mensaje leído de la base de datos es el texto tal cual
    if ($message !== $post['message']) {
        $db->query("
            UPDATE mybb_posts
            SET message='" . $db->escape_string($message) . "'
            WHERE pid='$pid'
        ");
    }
}

/**
 * Hook de edición (completa o rápida). Solo cuentan las etiquetas [consumir] que no estaban en el mensaje anterior, y
 * consumen del inventario del autor del post aunque edite otra persona.
 */
function BBCustom_consumir_editpost(&$data)
{
    global $db;

    if (!isset($data->data['message'], $data->post_update_data['message']) || stripos($data->data['message'], '[consumir=') === false) {
        return;
    }
    $pid = (int)$data->data['pid'];
    // El hook corre antes del UPDATE de MyBB: aquí la base de datos aún tiene el mensaje anterior
    $anterior = BBCustom_consumir_post($pid);
    if (!$anterior || (int)$anterior['visible'] === -2) {
        return;
    }

    // $data->data['message'] es el texto tal cual; post_update_data['message'] es ese mismo texto escapado para SQL
    $nuevo = (string)$data->data['message'];
    $message = opg_consumir_procesar_mensaje($db, $nuevo, $anterior['message'], array(
        'uid' => (int)$anterior['uid'],
        'username' => $anterior['username'],
        'pid' => $pid,
        'tid' => (int)$anterior['tid'],
        'editor_uid' => isset($data->data['edit_uid']) ? (int)$data->data['edit_uid'] : 0,
    ));

    if ($message !== $nuevo) {
        $data->data['message'] = $message;
        $data->post_update_data['message'] = $db->escape_string($message);
    }
}
