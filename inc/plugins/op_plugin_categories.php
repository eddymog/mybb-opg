<?php
/**
 * OPG - Categorías de plugins (BD)
 *
 * Crea la tabla `mybb_op_plugin_categories` (codename, category,
 * subcategory) que usa admin/modules/config/plugins.php para agrupar
 * visualmente los plugins en el panel. Al instalarlo, siembra la tabla con
 * lo que ya hubiera en admin/inc/plugin_categories.php (si existe), para no
 * perder la configuración anterior — a partir de ahí, todo se gestiona
 * desde Configuración → Plugins → Categorías, sin tocar ningún archivo.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

function op_plugin_categories_info()
{
    return array(
        'name'          => 'OPG - Categorías de plugins (BD)',
        'description'   => 'Tabla de categorías/subcategorías de plugins, gestionable desde el propio panel de administración.',
        'website'       => '',
        'author'        => 'Cascabelles',
        'authorsite'    => '',
        'version'       => '1.0',
        'compatibility' => '18*',
    );
}

if (defined('IN_ADMINCP')) {
    function op_plugin_categories_install()
    {
        global $db;

        if (!$db->table_exists('op_plugin_categories')) {
            $db->write_query("
                CREATE TABLE mybb_op_plugin_categories (
                    codename VARCHAR(64) NOT NULL,
                    category VARCHAR(190) NOT NULL,
                    subcategory VARCHAR(190) NOT NULL DEFAULT '',
                    PRIMARY KEY (codename)
                ) ENGINE=InnoDB
            ");
        }

        // Siembra desde el mapa estático anterior, si existe, para no perder
        // lo que ya estuviera configurado ahí.
        $seed_file = MYBB_ROOT.'admin/inc/plugin_categories.php';
        if (file_exists($seed_file)) {
            require $seed_file;
            if (!empty($op_plugin_categories) && is_array($op_plugin_categories)) {
                foreach ($op_plugin_categories as $codename => $raw) {
                    $codename_esc = $db->escape_string($codename);
                    $already = $db->fetch_field(
                        $db->simple_select('op_plugin_categories', 'codename', "codename='{$codename_esc}'"),
                        'codename'
                    );
                    if ($already) {
                        continue;
                    }

                    $parts = array_map('trim', explode('>', $raw, 2));
                    $db->insert_query('op_plugin_categories', array(
                        'codename'    => $codename_esc,
                        'category'    => $db->escape_string($parts[0]),
                        'subcategory' => isset($parts[1]) ? $db->escape_string($parts[1]) : '',
                    ));
                }
            }
        }
    }

    function op_plugin_categories_is_installed()
    {
        global $db;
        return $db->table_exists('op_plugin_categories');
    }

    function op_plugin_categories_uninstall()
    {
        global $db;
        $db->drop_table('op_plugin_categories');
    }

    function op_plugin_categories_activate() {}
    function op_plugin_categories_deactivate() {}
}
