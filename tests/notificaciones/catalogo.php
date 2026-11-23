<?php
define('IN_MYBB', 1);
define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
require_once MYBB_ROOT . 'inc/plugins/lib/notificaciones_catalogo.php';

$failures = array();
function nc_expect($condition, $message)
{
    global $failures;
    if (!$condition) $failures[] = $message;
}

$cat = opg_notif_catalogo();

// Todos los tipos pedidos existen
$tipos = array(
    'invitacion_nueva', 'candidatura_nueva', 'propuesta_aceptada', 'propuesta_rechazada', 'propuesta_retirada',
    'propuesta_cancelada', 'propuesta_vencida', 'aventura_aprobada', 'aventura_denegada', 'narrador_salio',
    'entrega_rechazada', 'recompensas_entregadas', 'oferta_por_vencer', 'entrega_enviada',
    'bitacora_post', 'bitacora_ronda_iniciada', 'bitacora_narrador_asignado', 'bitacora_narrador_cambiado',
    'bitacora_narrador_retirado',
);
foreach ($tipos as $t) {
    nc_expect(isset($cat[$t]), "Falta el tipo $t en el catalogo.");
}
nc_expect(count($cat) === count($tipos), 'El catalogo tiene tipos que no se esperaban: ' . implode(',', array_diff(array_keys($cat), $tipos)));

// Estructura de cada tipo
$categorias = array('aventuras', 'ficha', 'recompensas', 'tecnicas', 'peticiones', 'temas');
foreach ($cat as $t => $def) {
    nc_expect(in_array($def['categoria'], $categorias, true), "$t: categoria invalida.");
    nc_expect(in_array($def['origen'], array('gestion', 'usuario', 'sistema'), true), "$t: origen invalido.");
    nc_expect(in_array($def['importancia'], array('normal', 'importante'), true), "$t: importancia invalida.");
    nc_expect(is_bool($def['agrupable']), "$t: agrupable debe ser booleano.");
    nc_expect(is_string($def['icono']) && $def['icono'] !== '', "$t: falta icono.");
    nc_expect(is_callable($def['texto']), "$t: texto no es invocable.");
    nc_expect(is_callable($def['url']), "$t: url no es invocable.");
    // El texto y el enlace no fallan con datos vacios
    $txt = opg_notif_texto($t, array(), 1);
    nc_expect(is_string($txt) && $txt !== '', "$t: texto vacio con datos vacios.");
    $u = opg_notif_url($t, array('peticion_id' => 5, 'tid' => 9, 'propuesta_id' => 3));
    nc_expect($u === '' || opg_notif_url_interna($u), "$t: el enlace no es interno ($u).");
}
nc_expect(!in_array('tripulacion', array_column($cat, 'categoria'), true), 'No debe existir la categoria tripulacion.');

// Solo bitacora_post es agrupable
foreach ($cat as $t => $def) {
    nc_expect($def['agrupable'] === ($t === 'bitacora_post'), "$t: agrupable inesperado.");
}
nc_expect($cat['bitacora_post']['categoria'] === 'temas' && $cat['bitacora_post']['origen'] === 'usuario', 'bitacora_post: categoria temas, origen usuario.');
nc_expect($cat['entrega_rechazada']['categoria'] === 'aventuras' && $cat['entrega_rechazada']['origen'] === 'gestion', 'entrega_rechazada: aventuras, gestion.');
nc_expect($cat['recompensas_entregadas']['categoria'] === 'recompensas', 'recompensas_entregadas: categoria recompensas.');
nc_expect($cat['oferta_por_vencer']['origen'] === 'sistema', 'oferta_por_vencer: origen sistema.');

// Tipo desconocido: "Actualizacion", sin enlace, sin error (req. 25)
nc_expect(opg_notif_tipo('no_existe') === null, 'Un tipo desconocido no tiene definicion.');
nc_expect(opg_notif_texto('no_existe', array('x' => 1), 1) === 'Actualización', 'Tipo desconocido: texto "Actualización".');
nc_expect(opg_notif_url('no_existe', array('peticion_id' => 3)) === '', 'Tipo desconocido: sin enlace.');
nc_expect(opg_notif_texto('', null, 0) === 'Actualización', 'Tipo vacio y datos nulos: "Actualización".');
nc_expect(opg_notif_icono('no_existe') !== '', 'Tipo desconocido: tiene un icono generico.');
nc_expect(opg_notif_importancia('no_existe') === 'normal', 'Tipo desconocido: importancia normal.');

// Escape de comillas y etiquetas (req. 26 y 27)
$txt = opg_notif_texto('aventura_aprobada', array('peticion_id' => 7, 'actor' => '<script>alert("x")</script>', 'tema' => "O'Neil & <b>Co</b>"), 1);
nc_expect(strpos($txt, '<') === false && strpos($txt, '>') === false, 'El texto no debe contener etiquetas: ' . $txt);
nc_expect(strpos($txt, '"') === false && strpos($txt, "'") === false, 'El texto no debe contener comillas sin escapar: ' . $txt);
nc_expect(strpos($txt, '&lt;script&gt;') !== false, 'La etiqueta script debe quedar escapada.');
nc_expect(strpos($txt, '&amp;') !== false, 'El ampersand debe quedar escapado.');
// Sin doble escape: el dato se escapa una sola vez
nc_expect(strpos($txt, '&amp;lt;') === false && strpos($txt, '&amp;amp;') === false, 'No debe haber doble escape.');
$mot = opg_notif_texto('bitacora_post', array('tid' => 4, 'tema' => '"><img src=x onerror=1>'), 2);
nc_expect(strpos($mot, '<img') === false && strpos($mot, '&quot;&gt;&lt;img') !== false, 'bitacora_post escapa el titulo del tema.');

// Textos existentes del Tablon se conservan
nc_expect(opg_notif_texto('oferta_por_vencer', array('dias' => 3), 1) === 'Tu oferta como narrador vence en 3 días. Renuévala para seguir apareciendo.', 'Texto de oferta_por_vencer igual al actual.');
nc_expect(strpos(opg_notif_texto('propuesta_cancelada', array('peticion_id' => 7), 1), 'Se asignó otro narrador a la aventura #7') === 0, 'Texto de propuesta_cancelada igual al actual.');
nc_expect(opg_notif_texto('recompensas_entregadas', array('peticion_id' => 7, 'actor' => 'Ana', 'tema' => 'Isla'), 1) === 'Ana entregó las recompensas de «Isla». Revisa tu ficha.', 'Texto de recompensas_entregadas igual al actual.');
nc_expect(opg_notif_texto('aventura_aprobada', array('peticion_id' => 7), 1) === 'El staff aprobó la solicitud de la aventura #7. Ya puede jugarse.', 'Sin actor ni tema: "El staff" y "la aventura #id".');

// Motivo opcional en los tipos que lo llevan
$m = opg_notif_texto('entrega_rechazada', array('peticion_id' => 7, 'motivo' => 'Falta <i>detalle</i>'), 1);
nc_expect(strpos($m, 'Motivo: Falta &lt;i&gt;detalle&lt;/i&gt;') !== false, 'El motivo se muestra escapado.');

// Cantidad en agrupables
$uno = opg_notif_texto('bitacora_post', array('tid' => 4, 'tema' => 'Mi tema'), 1);
$tres = opg_notif_texto('bitacora_post', array('tid' => 4, 'tema' => 'Mi tema'), 3);
nc_expect($uno === 'Nueva publicación en «Mi tema».', 'Con cantidad 1: texto singular. Obtenido: ' . $uno);
nc_expect($tres === '3 publicaciones nuevas en «Mi tema».', 'Con cantidad 3: texto plural con el numero. Obtenido: ' . $tres);
nc_expect(opg_notif_texto('bitacora_post', array('tid' => 4), 0) === 'Nueva publicación en el tema #4.', 'Cantidad 0 se trata como 1.');
nc_expect(strpos(opg_notif_texto('bitacora_post', array('tid' => 4), '5abc'), '5 publicaciones') === 0, 'La cantidad se fuerza a entero.');
nc_expect(strpos(opg_notif_texto('aventura_aprobada', array('peticion_id' => 7), 9), '9') === false, 'Los no agrupables ignoran la cantidad.');

// Enlaces internos
$tabl = opg_notif_url('entrega_rechazada', array('peticion_id' => 12));
nc_expect($tabl === 'op/tablon_de_aventuras.php?id=12', 'Enlace de entrega_rechazada: ' . $tabl);
nc_expect(opg_notif_url('bitacora_post', array('tid' => '4; DROP')) === 'showthread.php?tid=4&action=newpost', 'tid se fuerza a entero en el enlace.');
nc_expect(opg_notif_url('bitacora_post', array()) === '', 'Sin tid no hay enlace.');
nc_expect(opg_notif_url('oferta_por_vencer', array()) === 'op/tablon_de_aventuras.php?tab=narradores', 'Enlace de oferta_por_vencer.');
nc_expect(opg_notif_url('bitacora_narrador_asignado', array('tid' => 8)) === 'op/bitacora.php', 'Enlace de la Bitacora.');
nc_expect(opg_notif_url('invitacion_nueva', array()) === '', 'Sin peticion_id no hay enlace de aventura.');

nc_expect(opg_notif_url_interna('op/bitacora.php') === true, 'Ruta relativa es interna.');
nc_expect(opg_notif_url_interna('/op/bitacora.php?x=1') === true, 'Ruta absoluta del sitio es interna.');
nc_expect(opg_notif_url_interna('showthread.php?tid=1&action=newpost') === true, 'Ruta con consulta es interna.');
foreach (array('http://evil.com/x', 'https://evil.com', '//evil.com/x', 'javascript:alert(1)', 'data:text/html,x', '\\\\evil.com', "op/a\nb", ' http://x', '', 'mailto:a@b.c') as $mala) {
    nc_expect(opg_notif_url_interna($mala) === false, 'No debe ser interna: ' . json_encode($mala));
}
nc_expect(opg_notif_url_interna(null) === false && opg_notif_url_interna(array()) === false, 'Valores que no son texto no son internos.');

if ($failures) {
    foreach ($failures as $m) echo "FALLO: $m\n";
    exit(1);
}
echo "OK notificaciones_catalogo\n";
