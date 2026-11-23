<?php
/**
 * Canje del Pergamino de Estilo Elemental (PEESP001) desde el inventario de la ficha.
 *
 * Desbloquea un elemento que el personaje no tenga (fichas.elementos, JSON con 1 = desbloqueado) y le ensena sus dos
 * tecnicas del estilo Elemental: Dominio (pasiva) y Recubrimiento. Si ya tiene los 5, no se puede canjear.
 *
 * Aqui solo hay logica pura (sin base de datos) para poder probarla; el endpoint esta en op/personaje.php.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

const OPG_ELEMENTO_CANJE_ITEM = 'PEESP001';

/** Elemento => [tid de Dominio, tid de Recubrimiento] */
function opg_elemento_canje_catalogo()
{
    return array(
        'Piro' => array('PIR001', 'PIR301'),
        'Aqua' => array('AQU001', 'AQU301'),
        'Aero' => array('AER001', 'AER301'),
        'Electro' => array('ELE001', 'ELE301'),
        'Cryo' => array('CRY001', 'CRY301'),
    );
}

/** Lee fichas.elementos (JSON o array) y devuelve elemento => bool (true = ya desbloqueado). */
function opg_elemento_canje_estado($elementos)
{
    if (is_string($elementos)) {
        $elementos = json_decode($elementos, true);
    }
    if (!is_array($elementos)) {
        $elementos = array();
    }
    $estado = array();
    foreach (array_keys(opg_elemento_canje_catalogo()) as $nombre) {
        $estado[$nombre] = isset($elementos[$nombre]) && (int)$elementos[$nombre] === 1;
    }
    return $estado;
}

/** Elementos que todavia puede desbloquear. Vacio = no se puede canjear. */
function opg_elemento_canje_disponibles($elementos)
{
    $libres = array();
    foreach (opg_elemento_canje_estado($elementos) as $nombre => $tiene) {
        if (!$tiene) {
            $libres[] = $nombre;
        }
    }
    return $libres;
}

/**
 * Valida la eleccion y devuelve que hay que escribir.
 * array('ok' => bool, 'error' => string, 'elemento' => string, 'elementos_json' => string, 'tids' => [dominio, recubrimiento])
 * elementos_json conserva lo que ya hubiera en la ficha y deja los 5 elementos definidos (0 o 1).
 */
function opg_elemento_canje_validar($elementos, $eleccion)
{
    $fallo = array('ok' => false, 'error' => '', 'elemento' => '', 'elementos_json' => '', 'tids' => array());
    $catalogo = opg_elemento_canje_catalogo();
    $libres = opg_elemento_canje_disponibles($elementos);

    if (!$libres) {
        $fallo['error'] = 'Ya tienes los 5 elementos desbloqueados.';
        return $fallo;
    }
    if (!is_string($eleccion) || !isset($catalogo[$eleccion])) {
        $fallo['error'] = 'Elige qué elemento quieres desbloquear.';
        return $fallo;
    }
    if (!in_array($eleccion, $libres, true)) {
        $fallo['error'] = 'Ya tienes desbloqueado el elemento ' . $eleccion . '.';
        return $fallo;
    }

    $nuevo = array();
    foreach (opg_elemento_canje_estado($elementos) as $nombre => $tiene) {
        $nuevo[$nombre] = ($tiene || $nombre === $eleccion) ? 1 : 0;
    }
    return array(
        'ok' => true,
        'error' => '',
        'elemento' => $eleccion,
        'elementos_json' => json_encode($nuevo),
        'tids' => $catalogo[$eleccion],
    );
}
