<?php
/**
 * Halloween 2026: pagina a la que llevan los enlaces de secretos puestos en mensajes del foro.
 *
 * El enlace (op/descubrir.php?c=CODIGO) puede compartirse sin problema: cada usuario lo descubre una sola vez. Abrirlo NO reclama nada
 * (una vista previa del enlace o una imagen que apunte aqui no deben dar premios): la pagina enseña un boton y solo se reclama
 * cuando la persona lo pulsa. La logica del premio es la de siempre (opg_hlw_secret_claim).
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'descubrir.php');
require_once './../global.php';
require_once './functions/op_functions.php';
require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026_secretos.php';

global $mybb, $headerinclude, $header, $footer;

$uid = (int)$mybb->user['uid'];
if ($uid <= 0) {
    error_no_permission();
}

function opg_hlw_link_card($title, $bodyHtml, $imageUrl = '', $first = false)
{
    $image = $imageUrl !== ''
        ? '<div class="ei-img" style="background-image:url(\'' . opg_hlw_escape($imageUrl) . '\')"></div>' : '';
    return '<section class="ei-card' . ($first ? ' ei-card--first' : '') . '"><h1>' . opg_hlw_escape($title) . '</h1>' . $image . $bodyHtml . '</section>';
}

$secret = opg_hlw_secrets_ready() ? opg_hlw_secret_by_code($mybb->get_input('c')) : null;

if (!$secret) {
    $card = opg_hlw_link_card('Enlace sin efecto', '<p>Este enlace no lleva a ningún sitio.</p>');
} elseif (!opg_hlw_secrets_open(opg_hlw_config(), $uid) || opg_hlw_secret_is_blocked($uid)) {
    $card = opg_hlw_link_card('Todavía no', '<p>Todavía no es el momento de descubrir esto.</p>');
} elseif (!opg_hlw_eligible_character($uid)) {
    $card = opg_hlw_link_card('Necesitas un personaje', '<p>Para descubrir secretos necesitas un personaje aprobado y vivo.</p>');
} elseif (isset(opg_hlw_secret_mine($uid)[$secret['clave']])) {
    $card = opg_hlw_link_card('Ya lo habías descubierto', '<p class="ei-name">' . opg_hlw_escape($secret['nombre']) . '</p><p>Este secreto ya es tuyo.</p>', (string)($secret['imagen'] ?? ''));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_post_check($mybb->get_input('my_post_key'), true)) {
    $result = opg_hlw_secret_claim($uid, $secret['clave']);
    if (in_array($result['estado'], array('first', 'new'), true)) {
        $card = opg_hlw_link_card($result['titulo'], '<p class="ei-kicker">' . opg_hlw_escape($result['kicker']) . '</p><p class="ei-name">' . opg_hlw_escape($result['nombre'])
            . '</p><p>' . opg_hlw_escape($result['mensaje']) . '</p><p class="ei-prize">' . opg_hlw_escape($result['premio']) . '</p>', (string)$result['imagen'], $result['estado'] === 'first');
    } elseif ($result['estado'] === 'already') {
        $card = opg_hlw_link_card('Ya lo habías descubierto', '<p>Este secreto ya es tuyo.</p>');
    } else {
        $card = opg_hlw_link_card('No ha podido ser', '<p>No se ha podido registrar el secreto. Inténtalo de nuevo más tarde.</p>');
    }
} else {
    $card = opg_hlw_link_card('¡Has encontrado algo!', '<p>Hay un secreto escondido aquí. ¿Quieres descubrirlo?</p>'
        . '<form method="post" action=""><input type="hidden" name="my_post_key" value="' . opg_hlw_escape($mybb->post_code) . '">'
        . '<button type="submit" class="ei-btn">Revelar el secreto</button></form>', (string)($secret['imagen'] ?? ''));
}

$style = '<style>
.ei-wrap{display:flex;justify-content:center;padding:36px 16px}
.ei-card{box-sizing:border-box;width:min(460px,100%);padding:28px 24px 24px;border:3px solid #000;border-radius:10px;background:#24202b;color:#f7f2ed;text-align:center;box-shadow:6px 6px 0 #000;font-family:InterRegular,Arial,sans-serif}
.ei-card--first{border-color:#efc15c}
.ei-card h1{margin:0 0 6px;font:28px moonGetHeavy,Arial,sans-serif;line-height:1.1;color:#fff}
.ei-img{width:128px;height:128px;margin:14px auto;border:3px solid #000;border-radius:50%;background:#19151f center/70% no-repeat;box-shadow:0 0 0 4px #efc15c,0 0 28px 6px rgba(239,193,92,.55)}
.ei-kicker{margin:0 0 4px;color:#f07824;font-weight:800;letter-spacing:.06em;text-transform:uppercase;font-size:12px}
.ei-name{margin:0;font-size:18px;font-weight:800;color:#efc15c}
.ei-card p{line-height:1.5;color:#e8dfe4}
.ei-prize{display:inline-block;margin:10px 0 0;padding:8px 14px;border:2px solid #000;border-radius:6px;background:#3aa980;color:#0d231d!important;font-weight:800}
.ei-card--first .ei-prize{background:#efc15c;color:#19151f!important}
.ei-btn{margin-top:14px;padding:10px 26px;border:2px solid #000;border-radius:6px;background:#f07824;color:#fff;font-weight:800;font-size:15px;cursor:pointer}
.ei-btn:hover{background:#c95b12}
</style>';

$page = '<!DOCTYPE html><html><head><title>Un hallazgo</title>' . $headerinclude . $style . '</head><body>' . $header
    . '<div class="indice"><div class="mainBackground"><div class="secondBackground"><div class="thirdBackground"><div class="ei-wrap">' . $card . '</div></div></div></div></div>'
    . $footer . '</body></html>';
output_page($page);
