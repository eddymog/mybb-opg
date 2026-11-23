<?php
/**
 * Canje de los Tickets de Arma (TARM001 a TARM005) desde el inventario de la ficha.
 *
 * El numero del ticket es el Tier del arma: TARM003 se canjea por cualquiera de las armas canjeables de Tier 3. Cada arma
 * canjeable tiene un ID base que termina en 001 (Tier 1); su version de Tier N lleva el mismo ID con el numero final N
 * (AMUM001 -> AMUM003). El ticket se gasta y el arma se suma al inventario.
 *
 * Aqui solo hay logica pura (sin base de datos) para poder probarla; el endpoint esta en op/personaje.php.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

/** Prefijos de las armas canjeables, en el orden en que se muestran (cada linea es una categoria). */
function opg_arma_canje_familias()
{
    return array(
        'AMUM', 'AMUD',
        'AAC', 'ACD',
        'ARF', 'ARP',
        'ACG', 'ACP',
        'ACCP', 'ACL',
        'LL', 'LC',
        'AFG', 'AFP',
        'AMD', 'ALA',
        'AL', 'AC',
        'ESC', 'GES',
        'ABYV', 'LYF',
        'INSP', 'INSG',
    );
}

/** Tier (1 a 5) de un ticket TARM00N, o null si no es un ticket de arma. */
function opg_arma_canje_tier($ticketId)
{
    if (is_string($ticketId) && preg_match('/^TARM00([1-5])$/', $ticketId, $m)) {
        return (int)$m[1];
    }
    return null;
}

/** ID de una familia en un Tier concreto: ('AMUM', 3) => 'AMUM003'. */
function opg_arma_canje_id($familia, $tier)
{
    return $familia . '00' . (int)$tier;
}

/** IDs de armas que se pueden obtener con un ticket (array vacio si el ticket no es valido). */
function opg_arma_canje_opciones($ticketId)
{
    $tier = opg_arma_canje_tier($ticketId);
    if ($tier === null) {
        return array();
    }
    $ids = array();
    foreach (opg_arma_canje_familias() as $familia) {
        $ids[] = opg_arma_canje_id($familia, $tier);
    }
    return $ids;
}

/**
 * Valida la eleccion. array('ok' => bool, 'error' => string, 'tier' => int, 'objeto_id' => string)
 */
function opg_arma_canje_validar($ticketId, $armaId)
{
    $fallo = array('ok' => false, 'error' => '', 'tier' => 0, 'objeto_id' => '');
    $tier = opg_arma_canje_tier($ticketId);
    if ($tier === null) {
        $fallo['error'] = 'Ese objeto no es un ticket de arma.';
        return $fallo;
    }
    if (!is_string($armaId) || $armaId === '') {
        $fallo['error'] = 'Elige qué arma quieres obtener.';
        return $fallo;
    }
    if (!in_array($armaId, opg_arma_canje_opciones($ticketId), true)) {
        $fallo['error'] = 'Ese arma no se puede obtener con este ticket (solo armas de Tier ' . $tier . ').';
        return $fallo;
    }
    return array('ok' => true, 'error' => '', 'tier' => $tier, 'objeto_id' => $armaId);
}
