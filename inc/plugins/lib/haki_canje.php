<?php
/**
 * Canje de la Tirada de Haki del Rey (THR001) desde el inventario de la ficha.
 *
 * - Sin Haki del Rey (hao = -1): suma 1 a hao_chance ("Lanzadas Haki del Rey").
 * - Con Haki del Rey obtenido (hao >= 0): sube 1 nivel un Haki despertado (nivel 1 o mas), sin gastar Nikas, siempre que se
 *   cumpla el requisito de nivel del personaje. Es la misma regla que la subida con Nikas de op/personaje.php.
 *
 * Aqui solo hay logica pura (sin base de datos) para poder probarla; el endpoint esta en op/personaje.php.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

const OPG_HAKI_CANJE_ITEM = 'THR001';
const OPG_HAKI_CANJE_MAX = 8;
const OPG_HAKI_CANJE_COLUMNAS = array('kenbun', 'buso', 'hao');

/**
 * Nivel de personaje que hace falta para pasar un Haki de $actual a $actual + 1 (misma regla que la subida con Nikas).
 * Devuelve null si no se puede subir (no despertado o nivel maximo).
 */
function opg_haki_canje_nivel_requerido($actual, $caminoHaki)
{
    $actual = (int)$actual;
    // [camino Haki, resto de caminos]; el 666 del nivel 7 al 8 es el valor que ya usa la subida con Nikas
    $tabla = array(
        1 => array(10, 15),
        2 => array(15, 20),
        3 => array(20, 25),
        4 => array(25, 30),
        5 => array(30, 35),
        6 => array(45, 50),
        7 => array(60, 666),
    );
    if ($actual < 1 || $actual >= OPG_HAKI_CANJE_MAX || !isset($tabla[$actual])) {
        return null;
    }
    return $tabla[$actual][$caminoHaki ? 0 : 1];
}

/**
 * Que puede hacer el jugador con el ticket segun su ficha.
 * Devuelve array('modo' => 'tirada'|'subir', 'hao_chance' => int, 'opciones' => [col => [actual, siguiente, ok, motivo]], 'puede' => bool)
 */
function opg_haki_canje_opciones(array $ficha)
{
    $nivel = (int)($ficha['nivel'] ?? 0);
    $caminoHaki = (($ficha['camino'] ?? '') === 'Haki');
    $hao = (int)($ficha['hao'] ?? -1);

    if ($hao === -1) {
        return array('modo' => 'tirada', 'hao_chance' => (int)($ficha['hao_chance'] ?? 0), 'opciones' => array(), 'puede' => true);
    }

    $opciones = array();
    $puede = false;
    foreach (OPG_HAKI_CANJE_COLUMNAS as $col) {
        $actual = (int)($ficha[$col] ?? 0);
        $op = array('actual' => $actual, 'siguiente' => $actual + 1, 'ok' => false, 'motivo' => '');
        if ($actual < 1) {
            $op['motivo'] = 'No despertado';
        } elseif ($actual >= OPG_HAKI_CANJE_MAX) {
            $op['motivo'] = 'Nivel máximo';
        } else {
            $req = opg_haki_canje_nivel_requerido($actual, $caminoHaki);
            if ($nivel < $req) {
                $op['motivo'] = 'Requiere nivel ' . $req . ' de personaje';
            } else {
                $op['ok'] = true;
                $puede = true;
            }
        }
        $opciones[$col] = $op;
    }
    return array('modo' => 'subir', 'hao_chance' => (int)($ficha['hao_chance'] ?? 0), 'opciones' => $opciones, 'puede' => $puede);
}

/**
 * Valida la eleccion del jugador y dice que hay que escribir en la ficha.
 * Devuelve array('ok' => bool, 'error' => string, 'modo' => 'tirada'|'subir', 'col' => string, 'actual' => int, 'nuevo' => int)
 */
function opg_haki_canje_validar(array $ficha, $eleccion)
{
    $info = opg_haki_canje_opciones($ficha);
    if ($info['modo'] === 'tirada') {
        $actual = $info['hao_chance'];
        return array('ok' => true, 'error' => '', 'modo' => 'tirada', 'col' => 'hao_chance', 'actual' => $actual, 'nuevo' => $actual + 1);
    }

    $fallo = array('ok' => false, 'error' => '', 'modo' => 'subir', 'col' => '', 'actual' => 0, 'nuevo' => 0);
    if (!in_array($eleccion, OPG_HAKI_CANJE_COLUMNAS, true)) {
        $fallo['error'] = 'Elige qué Haki quieres subir.';
        return $fallo;
    }
    $op = $info['opciones'][$eleccion];
    if (!$op['ok']) {
        $fallo['error'] = 'No puedes subir ese Haki: ' . mb_strtolower($op['motivo'], 'UTF-8') . '.';
        return $fallo;
    }
    return array('ok' => true, 'error' => '', 'modo' => 'subir', 'col' => $eleccion, 'actual' => $op['actual'], 'nuevo' => $op['siguiente']);
}
