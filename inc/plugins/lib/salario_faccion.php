<?php
/** Dominio compartido del salario semanal de facción. */

const OPG_SF_ZONA = 'Europe/Madrid';

/** Clave de la semana cuyo corte es el lunes a las 06:00 de Madrid. */
function opg_sf_semana_madrid($timestamp = null)
{
    $zona = new DateTimeZone(OPG_SF_ZONA);
    $fecha = new DateTimeImmutable('@' . ($timestamp === null ? time() : (int) $timestamp));
    $fecha = $fecha->setTimezone($zona)->modify('-6 hours')->modify('monday this week')->setTime(6, 0, 0);
    return $fecha->format('Y-m-d');
}

/** La tarea solo puede pagar el lunes desde las 06:00, según Madrid. */
function opg_sf_ventana_automatica($timestamp = null)
{
    $zona = new DateTimeZone(OPG_SF_ZONA);
    $fecha = new DateTimeImmutable('@' . ($timestamp === null ? time() : (int) $timestamp));
    $fecha = $fecha->setTimezone($zona);
    return $fecha->format('N') === '1' && $fecha->format('Hi') >= '0600';
}

/** Próximo lunes a las 06:00 de Madrid. */
function opg_sf_proximo_pago_madrid($timestamp = null)
{
    $zona = new DateTimeZone(OPG_SF_ZONA);
    $ahora = new DateTimeImmutable('@' . ($timestamp === null ? time() : (int) $timestamp));
    $ahora = $ahora->setTimezone($zona);
    $proximo = $ahora->modify('monday this week')->setTime(6, 0, 0);
    if ($ahora >= $proximo) {
        $proximo = $proximo->modify('+1 week');
    }
    return $proximo;
}

function opg_sf_tabla($nombre)
{
    global $db;
    return '`' . $db->table_prefix . $nombre . '`';
}

function opg_sf_resultado($ok, $codigo, $mensaje, $extra = array())
{
    return array_merge(array('ok' => (bool) $ok, 'codigo' => $codigo, 'mensaje' => $mensaje), $extra);
}

function opg_sf_query($sql)
{
    global $db;
    $resultado = $db->query($sql);
    if ($resultado === false) {
        throw new RuntimeException('Falló una consulta del salario de facción.');
    }
    return $resultado;
}

/**
 * Entrega el salario de una semana completa una sola vez.
 *
 * @param int    $ejecutorUid UID de staff o 0 para el sistema.
 * @param string $ejecutor    Nombre visible del ejecutor.
 * @param string $origen      automatico|manual.
 * @param int    $timestamp   Instante usado para determinar la semana.
 */
function opg_sf_repartir($ejecutorUid, $ejecutor, $origen, $timestamp = null)
{
    global $db;

    $ejecutorUid = (int) $ejecutorUid;
    $ejecutor = trim((string) $ejecutor);
    $origen = $origen === 'automatico' ? 'automatico' : 'manual';
    $timestamp = $timestamp === null ? time() : (int) $timestamp;
    $semana = opg_sf_semana_madrid($timestamp);

    if (!$db->table_exists('op_salario_faccion_repartos') || !$db->table_exists('op_salario_faccion_pagos')) {
        return opg_sf_resultado(false, 'sin_migracion', 'Falta ejecutar la migración del salario automático.', array('semana' => $semana));
    }
    if (!function_exists('op_facciones_sueldos_semanales')) {
        $ruta = defined('MYBB_ROOT') ? MYBB_ROOT . 'inc/plugins/op_facciones.php' : '';
        if ($ruta !== '' && is_file($ruta)) {
            require_once $ruta;
        }
    }
    if (!function_exists('op_facciones_sueldos_semanales')) {
        return opg_sf_resultado(false, 'sin_configuracion', 'El plugin OPG Facciones no está disponible.', array('semana' => $semana));
    }

    $salarios = array_filter(op_facciones_sueldos_semanales(), function ($monto) {
        return (int) $monto > 0;
    });
    if (!$salarios) {
        return opg_sf_resultado(false, 'sin_salarios', 'No hay rangos con sueldo semanal configurado.', array('semana' => $semana));
    }

    $tablaRepartos = opg_sf_tabla('op_salario_faccion_repartos');
    $tablaPagos = opg_sf_tabla('op_salario_faccion_pagos');
    $tablaFichas = opg_sf_tabla('op_fichas');
    $tablaAudit = opg_sf_tabla('op_audit_general');
    $tablaConsola = opg_sf_tabla('op_audit_consola_mod');
    $semanaSql = $db->escape_string($semana);
    $ejecutorSql = $db->escape_string($ejecutor !== '' ? $ejecutor : 'Sistema automático');
    $origenSql = $db->escape_string($origen);

    try {
        opg_sf_query('START TRANSACTION');
        opg_sf_query("INSERT IGNORE INTO {$tablaRepartos}
            (`semana_inicio`,`origen`,`ejecutor_uid`,`ejecutor`,`estado`)
            VALUES ('{$semanaSql}','{$origenSql}',{$ejecutorUid},'{$ejecutorSql}','procesando')");
        if ((int) $db->affected_rows() !== 1) {
            opg_sf_query('ROLLBACK');
            return opg_sf_resultado(true, 'ya_repartido', 'El salario de esta semana ya había sido repartido.', array(
                'semana' => $semana, 'pagados' => 0, 'total' => 0,
            ));
        }

        $rangosSql = array();
        foreach (array_keys($salarios) as $rango) {
            $rangosSql[] = "'" . $db->escape_string($rango) . "'";
        }
        $query = opg_sf_query("SELECT `fid`,`nombre`,`faccion`,`rango`,`berries`
            FROM {$tablaFichas} WHERE `rango` IN (" . implode(',', $rangosSql) . ') ORDER BY `fid` FOR UPDATE');

        $pagados = 0;
        $total = 0;
        while ($ficha = $db->fetch_array($query)) {
            $fid = (int) $ficha['fid'];
            $rango = (string) $ficha['rango'];
            $monto = isset($salarios[$rango]) ? (int) $salarios[$rango] : 0;
            if ($fid <= 0 || $monto <= 0) {
                continue;
            }
            $antes = (int) $ficha['berries'];
            $despues = $antes + $monto;
            $faccionSql = $db->escape_string((string) $ficha['faccion']);
            $rangoSql = $db->escape_string($rango);

            opg_sf_query("UPDATE {$tablaFichas} SET `berries`=`berries`+{$monto} WHERE `fid`={$fid}");
            if ((int) $db->affected_rows() !== 1) {
                throw new RuntimeException("No se pudo actualizar la ficha {$fid}.");
            }
            opg_sf_query("INSERT INTO {$tablaPagos}
                (`semana_inicio`,`fid`,`faccion`,`rango`,`monto`,`saldo_antes`,`saldo_despues`,`origen`,`ejecutor_uid`,`ejecutor`)
                VALUES ('{$semanaSql}',{$fid},'{$faccionSql}','{$rangoSql}',{$monto},{$antes},{$despues},'{$origenSql}',{$ejecutorUid},'{$ejecutorSql}')");

            $categoria = $db->escape_string("[Salarios][{$ficha['faccion']}][Semana {$semana}]");
            $log = $db->escape_string("Berries: {$antes}->{$despues} (+{$monto})");
            opg_sf_query("INSERT INTO {$tablaAudit} (`uid`,`username`,`user_uid`,`categoria`,`log`)
                VALUES ('{$ejecutorUid}','{$ejecutorSql}','{$fid}','{$categoria}','{$log}')");
            $pagados++;
            $total += $monto;
        }

        opg_sf_query("UPDATE {$tablaRepartos} SET `estado`='completado',`pagados`={$pagados},`total`={$total},
            `completado_at`=CURRENT_TIMESTAMP WHERE `semana_inicio`='{$semanaSql}'");
        $logTexto = $db->escape_string("[Salario Rangos][Semana {$semana}] {$origen} por {$ejecutor} ({$ejecutorUid}): {$pagados} personajes, {$total} berries.");
        opg_sf_query("INSERT INTO {$tablaConsola} (`staff`,`username`,`razon`,`log`)
            VALUES ('{$ejecutorUid}','{$ejecutorSql}','Salario Facción','{$logTexto}')");
        opg_sf_query('COMMIT');

        return opg_sf_resultado(true, 'repartido', "Salarios entregados a {$pagados} personajes.", array(
            'semana' => $semana, 'pagados' => $pagados, 'total' => $total,
        ));
    } catch (Throwable $e) {
        $db->query('ROLLBACK');
        return opg_sf_resultado(false, 'error', 'El reparto falló y se revirtió por completo.', array(
            'semana' => $semana, 'pagados' => 0, 'total' => 0,
        ));
    }
}

