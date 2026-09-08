<?php
/**
 * Categorías visuales para agrupar plugins en el panel de administración
 * (Configuración → Plugins). Puramente cosmético: no afecta a instalación,
 * activación, desinstalación ni a ninguna otra lógica de MyBB — solo decide
 * en qué tabla (con su propio título, a modo de "carpeta") aparece cada
 * plugin dentro de la lista de activos/inactivos.
 *
 * Clave = codename del plugin (el nombre de archivo sin ".php").
 * Cualquier plugin que no aparezca aquí cae en "Otros / sin categorizar",
 * así que nunca desaparece uno de la lista por no haberlo añadido todavía.
 *
 * Para añadir uno nuevo: solo hay que añadir una línea aquí, no hace falta
 * tocar admin/modules/config/plugins.php de nuevo.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

$op_plugin_categories = array(

    // --- OPG: sistema de juego / infraestructura propia ---
    'op_tasks'                  => 'OPG - Sistema de juego',
    'cierre_definitivo_foro'    => 'OPG - Sistema de juego',
    'op_imgver'                 => 'OPG - Sistema de juego',
    'template_sync'             => 'OPG - Sistema de juego',
    'newpoints'                 => 'OPG - Sistema de juego',

    // Subcategoría de ejemplo: cosas relacionadas con la ficha, dentro de
    // "OPG - Sistema de juego". Usa " > " para anidar dentro de una
    // categoría de nivel superior ya existente.
    'postbit_ficha'             => 'OPG - Sistema de juego > Ficha',

    // --- BBCode personalizado ---
    'BBCustom_ficha'            => 'OPG - BBCode personalizado',
    'BBCustom_fichasecreta'     => 'OPG - BBCode personalizado',
    'BBCustom_tecnica'          => 'OPG - BBCode personalizado',
    'BBCustom_consumir'         => 'OPG - BBCode personalizado',
    'BBCustom_akuma'            => 'OPG - BBCode personalizado',
    'BBCustom_npc'              => 'OPG - BBCode personalizado',
    'BBCustom_personajesecreto' => 'OPG - BBCode personalizado',
    'BBCustom_objeto'           => 'OPG - BBCode personalizado',
    'BBCustom_cerrado'          => 'OPG - BBCode personalizado',
    'BBCustom_mantenida'        => 'OPG - BBCode personalizado',
    'BBCustom_hide'             => 'OPG - BBCode personalizado',
    'BBCustom_dado'             => 'OPG - BBCode personalizado',
    'BBCustom_spoiler'          => 'OPG - BBCode personalizado',
    'BBCustom_recursos'         => 'OPG - BBCode personalizado',
    'BBCustom_tabla'            => 'OPG - BBCode personalizado',

    // --- Terceros: comunicación ---
    'rt_chat'                   => 'Terceros - Comunicación',
    'rt_discord_webhooks'       => 'Terceros - Comunicación',
    'rt_extendedcache'          => 'Terceros - Comunicación',

    // --- Terceros: utilidades varias ---
    'accountswitcher'           => 'Terceros - Utilidades',
    'copyphpcode'               => 'Terceros - Utilidades',
    'recentthread'              => 'Terceros - Utilidades',
    'onlinetoday'               => 'Terceros - Utilidades',
    'phptpl'                    => 'Terceros - Utilidades',
    'styleUsernames'            => 'Terceros - Utilidades',
    'fastyle'                   => 'Terceros - Utilidades',
    'hello'                     => 'Terceros - Utilidades',
    'hello_pl'                  => 'Terceros - Utilidades',
    'pluginlibrary'             => 'Terceros - Utilidades',

    // --- Legado / candidatos a revisar ---
    'OLD_codigo_hide'           => 'Legado (revisar)',
    'OLD_codigos_rol'           => 'Legado (revisar)',
);
