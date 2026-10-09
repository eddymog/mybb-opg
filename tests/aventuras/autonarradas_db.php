<?php
/** Pruebas de dominio de cronicas autonarradas contra MySQL 5.7. */
require __DIR__ . '/bootstrap_db.php';
preparar_esquema($db);
require MYBB_ROOT . 'inc/plugins/lib/aventuras.php';
require MYBB_ROOT . 'inc/plugins/lib/recompensas_aventura.php';
require MYBB_ROOT . 'inc/plugins/lib/aventuras_entregas.php';

$GLOBALS['roles'] = array('mod' => array(), 'staff' => array(50), 'narra' => array());
crear_usuario(15, 'Nivel Quince', array('nivel' => 15, 'oficios' => json_encode(array(
    'Mercader' => array('sub' => array('Contrabandista' => 3)),
    'Investigador' => array('sub' => array('Arqueologo' => 2)),
))));
crear_usuario(16, 'Nivel Dieciseis', array('nivel' => 16));
crear_usuario(50, 'Staff');

$db->query("SET SESSION sql_mode = ''", 1);
sql("INSERT INTO mybb_forums (fid, name, pid, active, type, parentlist) VALUES
    (15, 'Mar', 0, 1, 'c', '15'), (40, 'Isla Alfa', 15, 1, 'f', '15,40')");
$db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);

// Corte semanal de Madrid, incluido el cambio entre CET y CEST.
$antes_invierno = (new DateTime('2026-01-05 05:59:00', new DateTimeZone('Europe/Madrid')))->getTimestamp();
$corte_invierno = (new DateTime('2026-01-05 06:00:00', new DateTimeZone('Europe/Madrid')))->getTimestamp();
$antes_verano = (new DateTime('2026-07-06 05:59:00', new DateTimeZone('Europe/Madrid')))->getTimestamp();
$corte_verano = (new DateTime('2026-07-06 06:00:00', new DateTimeZone('Europe/Madrid')))->getTimestamp();
prueba('semana Madrid cambia exactamente el lunes a las 06:00 en invierno',
    array(opg_av_semana_madrid($antes_invierno), opg_av_semana_madrid($corte_invierno)),
    array($corte_invierno - 7 * 86400, $corte_invierno));
prueba('semana Madrid cambia exactamente el lunes a las 06:00 en verano',
    array(opg_av_semana_madrid($antes_verano), opg_av_semana_madrid($corte_verano)),
    array($corte_verano - 7 * 86400, $corte_verano));

$entrada = array('tipo' => 'autonarrada', 'isla_fid' => 40, 'dia' => 12, 'comentario' => 'Una historia breve.');
$v = opg_av_validar_solicitud($entrada, 15);
prueba('nivel 15 puede preparar una autonarrada sin tier ni narrador externo',
    array($v['errores'], $v['datos']['tipo'], $v['datos']['rol'], $v['datos']['tier'],
        $v['datos']['participantes'][0]['uid'], count($v['datos']['participantes']), $v['datos']['narrador']['uid']),
    array(array(), 'autonarrada', 'jugador', 0, 15, 1, 15));
prueba('nivel 16 no puede solicitar una autonarrada', !empty(opg_av_validar_solicitud($entrada, 16)['errores']), true);

$creada = opg_av_crear($v['datos'], 15);
$id = $creada['id'];
$fila = una("SELECT pa.tipo, aa.fid, aa.semana_inicio, aa.nivel_solicitud
    FROM mybb_op_peticionAventuras pa JOIN mybb_op_aventuras_autonarradas aa ON aa.peticion_id = pa.id WHERE pa.id = $id");
prueba('crear reserva tipo, ficha, semana y nivel', array($creada['ok'], $fila['tipo'], (int) $fila['fid'], (int) $fila['nivel_solicitud']),
    array(true, 'autonarrada', 15, 15));
prueba('una segunda solicitud semanal queda bloqueada', opg_av_validar_solicitud($entrada, 15)['errores'] !== array(), true);
prueba('la autonarrada no consume los cupos de aventura normal', opg_av_contar_activas(15), array(0, 0));

sql("UPDATE mybb_op_peticionAventuras SET estado = 1 WHERE id = $id");
$db->query("SET SESSION sql_mode = ''", 1);
sql("INSERT INTO mybb_threads (tid, fid, subject, prefix, uid, username, closed, visible, dateline)
    VALUES (900, 40, 'Cronica corta', 9, 15, 'Nivel Quince', 0, 1, " . TIME_NOW . "),
           (901, 40, 'Cronica valida', 9, 15, 'Nivel Quince', 0, 1, " . TIME_NOW . ")");
sql("INSERT INTO mybb_posts (pid, tid, fid, uid, username, dateline, message, visible)
    VALUES (900, 900, 40, 15, 'Nivel Quince', " . TIME_NOW . ", '" . $db->escape_string(trim(str_repeat('palabra ', 799))) . "', 1),
           (901, 901, 40, 15, 'Nivel Quince', " . TIME_NOW . ", '" . $db->escape_string(trim(str_repeat('palabra ', 800))) . "', 1)");
$db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'", 1);
prueba('799 palabras no permiten vincular', opg_av_vincular($id, '900', 15)['codigo'], 422);
sql('UPDATE mybb_op_fichas SET nivel = 16 WHERE fid = 15');
prueba('subir a nivel 16 antes de publicar bloquea el enlace', opg_av_vincular($id, '901', 15)['codigo'], 422);
sql('UPDATE mybb_op_fichas SET nivel = 15 WHERE fid = 15');
$vinculo = opg_av_vincular($id, '901', 15);
$auto = opg_av_autonarrada($id);
prueba('800 palabras vinculan y congelan publicación y nivel',
    array($vinculo['ok'], (int) $auto['tid'], (int) $auto['palabras'], (int) $auto['nivel_publicacion']),
    array(true, 901, 800, 15));
prueba('vincular no envía todavía la autonarrada a la consola', opg_av_autonarradas_entregas_conteo('pendiente'), 0);
prueba('otro usuario no puede entregar la autonarrada', opg_av_autonarrada_entregar($id, 'negativa', 16)['codigo'], 403);
$entrega_auto = opg_av_autonarrada_entregar($id, 'negativa', 15);
$auto = opg_av_autonarrada($id);
prueba('el autor entrega y fija la reputación antes de revisión',
    array($entrega_auto['ok'], $auto['reputacion'], (int) $auto['entregada_at'] > 0, opg_av_autonarradas_entregas_conteo('pendiente')),
    array(true, 'negativa', true, 1));

// Los oficios de nivel alto no modifican la recompensa fija.
$analisis = opg_av_autonarrada_analizar($id);
prueba('recompensa fija sin bonus profesionales', $analisis['recompensa'],
    array('xp' => 30, 'nikas' => 2, 'reputacion' => 5, 'berries' => 300000));
prueba('mensaje positivo habla de respeto y admiración',
    strpos(opg_av_autonarrada_mensaje_pago('positiva', $id, $auto['semana_inicio']), 'inspira respeto y admiración.') !== false, true);
prueba('mensaje negativo habla de miedo y respeto e identifica la solicitud',
    array(
        strpos(opg_av_autonarrada_mensaje_pago('negativa', $id, $auto['semana_inicio']), 'inspira miedo y respeto.') !== false,
        strpos(opg_av_autonarrada_mensaje_pago('negativa', $id, $auto['semana_inicio']), 'Solicitud #' . $id) !== false,
    ), array(true, true));

$pago = opg_av_autonarrada_pagar($id, 50);
$ficha_pagada = una('SELECT nika, berries, reputacion, reputacion_positiva, reputacion_negativa FROM mybb_op_fichas WHERE fid = 15');
$usuario_pagado = una('SELECT newpoints FROM mybb_users WHERE uid = 15');
$auto_pagada = opg_av_autonarrada($id);
prueba('pago integrado aplica una sola recompensa fija y finaliza', array(
    $pago['ok'], (float) $usuario_pagado['newpoints'], (int) $ficha_pagada['nika'], (int) $ficha_pagada['berries'],
    (int) $ficha_pagada['reputacion'], (int) $ficha_pagada['reputacion_positiva'], (int) $ficha_pagada['reputacion_negativa'],
    $auto_pagada['estado_pago'], (int) fila($id)['estado'],
), array(true, 30.0, 2, 300000, 5, 0, 5, 'aplicado', 3));
prueba('un segundo pago queda bloqueado', opg_av_autonarrada_pagar($id, 50)['codigo'], 409);

resumen();
