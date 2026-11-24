<?php
/**
 * Entorno de pruebas con MySQL real para [consumir] (issue #32). Reutiliza el doble del $db de MyBB y las utilidades de
 * tests/notificaciones/bootstrap_db.php (OpgNotifTestDb, nt_prueba, nt_ok, nt_resumen, nt_sql, nt_una, nt_valor,
 * nt_conectar, nt_borrar) y crea su propia base con las tablas que tocan los plugins, copiadas de la estructura de
 * produccion (docs/rovddqmy_op.sql): mismo motor, juego de caracteres y claves.
 *
 * MySQL 5.7 desechable (sin datos de produccion), por ejemplo:
 *   docker run -d --name opg-dev32-mysql --platform linux/amd64 -e MYSQL_ROOT_PASSWORD=opgtest -e MYSQL_DATABASE=opg_test \
 *     -p 33332:3306 --tmpfs /var/lib/mysql mysql:5.7 --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
 *   OPG_TEST_DB_PORT=33332 php tests/consumir/consumo_db.php
 * Sin MySQL, termina con codigo 2 y "Sin MySQL de pruebas".
 *
 *   require __DIR__ . '/bootstrap_db.php';
 *   $db = cs_preparar('opg_consumir_x');
 *   ...
 *   nt_borrar(); nt_resumen('consumir x');
 */

require_once __DIR__ . '/../notificaciones/bootstrap_db.php';

/** Crea la base $base desde cero con las tablas de [consumir] y de los otros codigos que reescriben el mensaje. */
function cs_preparar($base)
{
    if (!preg_match('/^opg_[a-z0-9_]+$/', $base)) {
        fwrite(STDERR, "Nombre de base de pruebas no valido: $base\n");
        exit(2);
    }
    nt_estricto(false);
    $db = new OpgNotifTestDb();
    $db->query("DROP DATABASE IF EXISTS `$base`");
    $db->query("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->usar($base);
    $GLOBALS['nt_base'] = $base;
    $GLOBALS['db'] = $db;

    // La tabla de inventario de produccion tiene un DEFAULT '0000-00-00' que el modo estricto de 5.7 no deja crear.
    nt_sql("SET SESSION sql_mode = ''");
    $tablas = array(
        "CREATE TABLE `mybb_posts` (`pid` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `tid` INT UNSIGNED NOT NULL DEFAULT 0,
            `uid` INT UNSIGNED NOT NULL DEFAULT 0, `username` VARCHAR(80) NOT NULL DEFAULT '', `message` TEXT NOT NULL,
            `visible` TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE `mybb_op_inventario` (`id` INT(3) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `objeto_id` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL, `uid` INT(10) NOT NULL, `cantidad` INT(10) NOT NULL,
            `tiempo` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `imagen` VARCHAR(500) COLLATE utf8_unicode_ci NOT NULL DEFAULT '', `apodo` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `autor` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '', `autor_uid` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `oficios` JSON DEFAULT NULL, `especial` INT(10) NOT NULL DEFAULT '0', `editado` INT(10) NOT NULL DEFAULT '0',
            `usado` INT(11) NOT NULL DEFAULT '0', `vendidoReciente` TIMESTAMP(6) NOT NULL DEFAULT '0000-00-00 00:00:00.000000',
            `bautizado` TINYINT(1) NOT NULL DEFAULT '0', UNIQUE KEY `objeto_id` (`objeto_id`, `uid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
        "CREATE TABLE `mybb_op_objetos` (`id` INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `objeto_id` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL, `categoria` TEXT NOT NULL,
            `subcategoria` TEXT NOT NULL, `nombre` TEXT NOT NULL, `tier` INT(10) NOT NULL DEFAULT '0',
            `imagen_id` INT(10) NOT NULL DEFAULT '0', `imagen_avatar` TEXT NOT NULL, `dano` TEXT NOT NULL, `efecto` TEXT NOT NULL,
            `requisitos` TEXT NOT NULL, `escalado` TEXT NOT NULL, `descripcion` TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=latin1",
        "CREATE TABLE `mybb_op_consumir` (`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `tid` INT(11) NOT NULL,
            `pid` INT(11) NOT NULL, `uid` INT(11) NOT NULL, `counter` INT(11) NOT NULL, `objeto_id` VARCHAR(255) NOT NULL,
            `content` TEXT CHARACTER SET utf8 NOT NULL, `tiempo_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=MyISAM DEFAULT CHARSET=latin1",
        "CREATE TABLE `mybb_op_audit_general` (`id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `tiempo` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `uid` TEXT COLLATE utf8_unicode_ci NOT NULL,
            `username` TEXT COLLATE utf8_unicode_ci NOT NULL, `user_uid` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '9999',
            `categoria` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL, `log` TEXT COLLATE utf8_unicode_ci NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
        "CREATE TABLE `mybb_op_equipamiento_personaje` (`id` INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY, `tid` INT(5) NOT NULL,
            `pid` INT(5) NOT NULL DEFAULT 0, `uid` INT(10) NOT NULL, `equipamiento` JSON NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8",
        "CREATE TABLE `mybb_op_fichas` (`fid` INT NOT NULL PRIMARY KEY, `equipamiento` TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        "CREATE TABLE `mybb_op_hide` (`hid` INT AUTO_INCREMENT PRIMARY KEY, `tid` INT, `pid` INT, `uid` INT, `hide_counter` INT,
            `show_hide` TINYINT DEFAULT 0, `hide_uids` VARCHAR(500) NOT NULL DEFAULT '', `hide_content` MEDIUMTEXT) CHARSET=utf8mb4",
        "CREATE TABLE `mybb_op_dados` (`did` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `tid` INT NOT NULL, `pid` INT NOT NULL,
            `uid` INT NOT NULL, `dado_counter` INT NOT NULL, `dado_content` TEXT CHARACTER SET utf8mb4 NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=latin1",
            /* utf8mb4 en la prueba: el HTML del dado lleva emojis que el modo estricto no deja en una columna utf8 */
        "CREATE TABLE `mybb_op_tecnicas_mantenidas` (`id` INT AUTO_INCREMENT PRIMARY KEY, `uid` INT NOT NULL, `tid` INT NOT NULL,
            `tecnica_id` VARCHAR(100) NOT NULL, `pid_inicio` INT NOT NULL, `turnos` INT DEFAULT 1, `activa` TINYINT(1) DEFAULT 1)",
        "CREATE TABLE `mybb_op_tecnicas` (`tid` VARCHAR(100) NOT NULL PRIMARY KEY, `nombre` VARCHAR(255) NOT NULL DEFAULT '')",
        "CREATE TABLE `mybb_op_mantenidas_html` (`id` INT AUTO_INCREMENT PRIMARY KEY, `pid` INT NOT NULL, `html_content` MEDIUMTEXT,
            UNIQUE KEY `pid` (`pid`))",
    );
    foreach ($tablas as $sql) {
        nt_sql($sql);
    }
    // El resto de la prueba, con el modo por defecto del servidor (estricto en 5.7)
    nt_sql("SET SESSION sql_mode = DEFAULT");
    return $db;
}

/** Objeto de catalogo minimo. */
function cs_objeto($objeto_id, $nombre, $extra = array())
{
    $f = array_merge(array('categoria' => 'Consumibles', 'subcategoria' => 'Consumibles', 'tier' => 1, 'imagen_id' => 1,
        'imagen_avatar' => '', 'dano' => '', 'efecto' => 'Cura', 'requisitos' => '', 'escalado' => '', 'descripcion' => 'Desc'), $extra);
    $db = $GLOBALS['db'];
    $cols = array('objeto_id' => $objeto_id, 'nombre' => $nombre) + $f;
    $vals = array();
    foreach ($cols as $v) {
        $vals[] = "'" . $db->escape_string($v) . "'";
    }
    nt_sql('INSERT INTO mybb_op_objetos (`' . implode('`,`', array_keys($cols)) . '`) VALUES (' . implode(',', $vals) . ')');
}

/** Deja exactamente $cantidad unidades de $objeto_id en el inventario de $uid (0 = sin fila). */
function cs_inventario($uid, $objeto_id, $cantidad)
{
    $db = $GLOBALS['db'];
    $oid = $db->escape_string($objeto_id);
    nt_sql("DELETE FROM mybb_op_inventario WHERE uid = " . (int)$uid . " AND objeto_id = '$oid'");
    if ($cantidad > 0) {
        nt_sql("INSERT INTO mybb_op_inventario (objeto_id, uid, cantidad) VALUES ('$oid', " . (int)$uid . ", " . (int)$cantidad . ")");
    }
}

/** Cantidad en el inventario (null si no hay fila). */
function cs_cantidad($uid, $objeto_id)
{
    $v = nt_valor("SELECT cantidad FROM mybb_op_inventario WHERE uid = " . (int)$uid . " AND objeto_id = '" . $GLOBALS['db']->escape_string($objeto_id) . "'");
    return $v === null ? null : (int)$v;
}
