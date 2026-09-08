<?php
/**
 * OPG - Facciones y rangos (BD)
 *
 * Crea las tablas `mybb_op_facciones` y `mybb_op_facciones_rangos`, que
 * gestiona la pestaña "Facciones" de Configuración del foro
 * (admin/modules/forumconfig/facciones.php). Al instalarlo, siembra ambas
 * tablas con las facciones y rangos que ya existen hoy — hardcodeados en
 * varios sitios (op/personaje.php, op/ficha_crear.php, las plantillas
 * staff_ficha_atributos*.html, etc.) — para que el panel refleje el estado
 * real del juego desde el primer momento.
 *
 * IMPORTANTE: esta primera versión es solo la capa de gestión/datos. Crear
 * una facción o rango nuevo aquí NO lo añade todavía a los desplegables de
 * creación de ficha ni del editor de staff (siguen siendo listas fijas en
 * las plantillas) — eso es un paso posterior, deliberadamente separado por
 * el riesgo de tocar esos flujos en producción. Lo que sí funciona ya de
 * inmediato es subir/reemplazar la imagen de un rango EXISTENTE desde este
 * panel, porque se guarda exactamente en la ruta que la ficha ya lee
 * (/images/op/rangos/{valor}_One_Piece_Gaiden_Foro_Rol.webp).
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

function op_facciones_info()
{
    return array(
        'name'          => 'OPG - Facciones y rangos (BD)',
        'description'   => 'Tablas de facciones/rangos del juego, gestionables desde Configuración del foro → Facciones.',
        'website'       => '',
        'author'        => 'Cascabelles',
        'authorsite'    => '',
        'version'       => '1.0',
        'compatibility' => '18*',
    );
}

/**
 * Facciones y rangos tal y como existen hoy en el juego (ver
 * op/personaje.php, op/ficha_crear.php y
 * templates/One_Piece_Gaiden_Templates/staff_ficha_atributos*.html).
 * Clave = valor exacto guardado en mybb_op_fichas.faccion/.rango.
 */
function op_facciones_seed_data()
{
    return array(
        'Pirata' => array(
            'orden' => 10,
            'usergroup' => 8,
            'colores' => array(
                'faccion' => '#ff0000', 'rombo' => '#ff0000', 'border_tag' => '#ff0000',
                'rango' => 'linear-gradient(42deg, #950000 20%, #ff0000 50%, #950000 80%)',
                'border' => '#f63030', 'border_pill' => '#fd0202', 'texto' => '#8d0101', 'chat' => '#ff7c7c',
            ),
            'rangos' => array(
                'Pirata' => 'Pirata',
                'CapitanPirata' => 'Capitán',
                'Corsario' => 'Corsario',
                'Bucanero' => 'Bucanero',
                'LoboDeMar' => 'Lobo de Mar',
                'Supernova' => 'Supernova',
                'PirataAfamado' => 'Pirata Famoso',
                'CapitanFamoso' => 'Capitán Famoso',
                'ViceCapitanFamoso' => 'Vicecapitán Famoso',
                'GranPirata' => 'Gran Pirata',
                'GranCapitan' => 'Gran Capitán',
                'GranViceCapitan' => 'Gran Vicecapitán',
                'ComandanteP' => 'Comandante',
                'PrimerComandante' => 'Primer Comandante',
                'Yonkou' => 'Yonkou',
                'LeyendaDelMar' => 'Leyenda del Mar',
                'Shichibukai' => 'Shichibukai',
                'AlaDelRey' => 'Ala del Rey',
                'ReyPirata' => 'Rey de los Piratas',
            ),
        ),
        'Marina' => array(
            'orden' => 20,
            'usergroup' => 9,
            'colores' => array(
                'faccion' => '#00bafc', 'rombo' => '#0039ed', 'border_tag' => '#00bafc',
                'rango' => 'linear-gradient(42deg, #002282 20%, #00b8fa 50%, #002282 80%)',
                'border' => '#0055bb', 'border_pill' => '#0038c7', 'texto' => '#006d94', 'chat' => '#84bbff',
            ),
            'rangos' => array(
                'ReclutaM' => 'Recluta',
                'SoldadoM' => 'Soldado Raso',
                'SargentoM' => 'Sargento',
                'Suboficial' => 'Suboficial',
                'Alferez' => 'Alférez',
                'Teniente' => 'Teniente',
                'ComandanteM' => 'Comandante',
                'Capitan' => 'Capitán',
                'Comodoro' => 'Comodoro',
                'ContraAlmirante' => 'Contraalmirante',
                'Vicealmirante' => 'Vicealmirante',
                'Almirante' => 'Almirante',
                'AlmiranteFlota' => 'Almirante de Flota',
                'Inspector' => 'Inspector',
                'Instructor' => 'Instructor',
                'HeroeDeLaMarina' => 'Heroe de la marina',
            ),
        ),
        'CipherPol' => array(
            'orden' => 30,
            'usergroup' => 11,
            'colores' => array(
                'faccion' => '#08002c', 'rombo' => '#6534aa', 'border_tag' => '#08002c',
                'rango' => 'linear-gradient(42deg, #1b1424 20%, #9577ba 50%, #1b1424 80%)',
                'border' => '#ac30d9', 'border_pill' => '#861fac', 'texto' => '#3e528f', 'chat' => '#464646',
            ),
            'rangos' => array(
                'CP1' => 'CP1',
                'CP2' => 'CP2',
                'CP3' => 'CP3',
                'CP4' => 'CP4',
                'CP5' => 'CP5',
                'CP6' => 'CP6',
                'CP7' => 'CP7',
                'CP8' => 'CP8',
                'CP9' => 'CP9',
                'CPAegis0' => 'CP0',
                'CPComisario' => 'Comisario de Cipher Pol',
                'CPMasquerade' => 'CP0 Masquerade',
                'CPComandanteEjecutivo' => 'Comandante Ejecutivo',
                'CPCaballeroDivino' => 'Caballero Divino',
                'CPComandanteSupremo' => 'Comandante Supremo',
            ),
        ),
        'Revolucionario' => array(
            'orden' => 40,
            'usergroup' => 12,
            'colores' => array(
                'faccion' => '#be9d6f', 'rombo' => '#7d6452', 'border_tag' => '#be9d6f',
                'rango' => 'linear-gradient(42deg, #4e3e2c 20%, #e9c696 50%, #4e3e2c 80%)',
                'border' => '#9d8771', 'border_pill' => '#937e67', 'texto' => '#be9d6f', 'chat' => '#ffcd85',
            ),
            'rangos' => array(
                'ReclutaR' => 'Recluta',
                'SoldadoR' => 'Soldado',
                'SargentoR' => 'Sargento',
                'AgenteR' => 'Agente',
                'Oficial' => 'Oficial',
                'Mariscal' => 'Mariscal',
                'General' => 'General',
                'ComandanteAdjunto' => 'Comandante Adjunto',
                'ComandanteR' => 'Comandante',
                'JefePersonal' => 'Jefe de Personal',
                'ComandanteSupremo' => 'Comandante Supremo',
            ),
        ),
        'Cazadores' => array(
            'orden' => 50,
            'usergroup' => 10,
            'colores' => array(
                'faccion' => '#00c200', 'rombo' => '#00ab00', 'border_tag' => '#00c200',
                'rango' => 'linear-gradient(42deg, #0f2313 20%, #46af70 50%, #0f2313 80%)',
                'border' => '#00d506', 'border_pill' => '#007400', 'texto' => '#007500', 'chat' => '#85ff97',
            ),
            'rangos' => array(
                'Cazador' => 'Cazador',
                'CazadorZeta' => 'Cazador Zeta',
                'CazadorEpsilon' => 'Cazador Épsilon',
                'CazadorDelta' => 'Cazador Delta',
                'CazadorGamma' => 'Cazador Gamma',
                'CazadorBeta' => 'Cazador Beta',
                'CazadorAlpha' => 'Cazador Alfa',
                'CazadorOmega' => 'Cazador Omega',
                'CazadorRenegado' => 'Cazador Renegado',
                'ReyCazador' => 'Rey de los Cazadores',
            ),
        ),
        'Civil' => array(
            'orden' => 60,
            'usergroup' => 13,
            'colores' => array(
                'faccion' => '#ff0283', 'rombo' => '#c6005c', 'border_tag' => '#ff0283',
                'rango' => 'linear-gradient(42deg, #950044 20%, #f40277 50%, #950044 80%)',
                'border' => '#e0428d', 'border_pill' => '#c30041', 'texto' => '#ac0359', 'chat' => '#f58bff',
            ),
            'rangos' => array(
                'Ciudadano' => 'Civil',
                'Operativo' => 'Operativo',
                'EmperadorDelInframundo' => 'Emperador Del Inframundo',
                'Broker' => 'Broker',
                'BrokerEstrella' => 'Broker Estrella',
            ),
        ),
        'Staff' => array(
            'orden' => 70,
            'rangos' => array(
                'Administrador' => 'Administrador',
                'ConsejeroReal' => 'ConsejeroReal',
                'Moderador' => 'Moderador',
                'Narrador' => 'Narrador',
                'WebMaster' => 'Web Master',
            ),
        ),
    );
}

/**
 * Umbrales de ascenso automático (reputación/nivel mínimos para PROMOCIONAR
 * A este rango), keyed por el 'valor' del rango — solo para los rangos que
 * ya tenían esta regla hardcodeada en op/personaje.php (Marina/CipherPol/
 * Revolucionario). El resto de rangos se siembran sin umbral (NULL = no
 * asciende solo, hace falta un cambio manual de staff), exactamente el
 * comportamiento de siempre para esos.
 */
function op_facciones_seed_ascenso_thresholds()
{
    return array(
        'SoldadoM'   => array(26, 4),  'SargentoM' => array(51, 8),  'Suboficial' => array(51, 11),
        'CP2'        => array(26, 4),  'CP3'       => array(26, 7),  'CP4'        => array(51, 10),
        'CP5'        => array(51, 13), 'CP6'       => array(101, 16), 'CP7'       => array(101, 19),
        'SoldadoR'   => array(26, 4),  'SargentoR' => array(51, 8),  'AgenteR'    => array(51, 11),
    );
}

/**
 * Sueldo semanal en berries por rango (valor => berries), antes un array
 * hardcodeado dentro de op/staff/salario_faccion.php. Un rango que no
 * aparezca aquí (o cuya columna sueldo_semanal esté en 0) no cobra sueldo.
 */
function op_facciones_seed_sueldos()
{
    return array(
        // Marina
        'ReclutaM'        => 10000,
        'SoldadoM'        => 25000,
        'SargentoM'       => 50000,
        'Suboficial'      => 100000,
        'Alferez'         => 250000,
        'Teniente'        => 350000,
        'ComandanteM'     => 500000,
        'Capitan'         => 2000000,
        'Comodoro'        => 5000000,
        'ContraAlmirante' => 10000000,
        'Vicealmirante'   => 25000000,
        'Almirante'       => 50000000,
        'AlmiranteFlota'  => 100000000,
        // CipherPol
        'CP1'             => 25000,
        'CP2'             => 50000,
        'CP3'             => 120000,
        'CP4'             => 250000,
        'CP5'             => 350000,
        'CP6'             => 500000,
        'CP7'             => 1500000,
        'CP8'             => 3000000,
        'CP9'             => 25000000,
        'CPAegis0'        => 50000000,
        // Revolucionario
        'ReclutaR'          => 5000,
        'SoldadoR'          => 10000,
        'SargentoR'         => 25000,
        'AgenteR'           => 50000,
        'Oficial'           => 100000,
        'Mariscal'          => 250000,
        'General'           => 300000,
        'ComandanteAdjunto' => 2000000,
        'ComandanteR'       => 5000000,
        'JefePersonal'      => 10000000,
        'ComandanteSupremo' => 10000000,
    );
}

/**
 * Sueldos semanales configurados hoy (valor => berries), solo los rangos con
 * sueldo_semanal > 0. Usado por op/staff/salario_faccion.php para pagar sin
 * depender de un array hardcodeado.
 */
function op_facciones_sueldos_semanales()
{
    global $db;
    $sueldos = array();
    $query = $db->simple_select('op_facciones_rangos', 'valor, sueldo_semanal', 'sueldo_semanal > 0');
    while ($fila = $db->fetch_array($query)) {
        $sueldos[$fila['valor']] = (int)$fila['sueldo_semanal'];
    }
    return $sueldos;
}

/**
 * Nombres de columna de color en mybb_op_facciones (columna DB => clave del
 * array 'colores' de op_facciones_seed_data()).
 */
function op_facciones_color_columns()
{
    return array(
        'color_faccion'     => 'faccion',
        'color_rombo'       => 'rombo',
        'color_border_tag'  => 'border_tag',
        'color_rango'       => 'rango',
        'color_border'      => 'border',
        'color_border_pill' => 'border_pill',
        'color_texto'       => 'texto',
        'color_chat'        => 'chat',
    );
}

/**
 * Paleta de colores de facción [faccionColor, romboColor, borderTagColor,
 * rangoColor, borderColor, borderPillColor], antes hardcodeada de forma
 * idéntica en op/personaje.php, op/banda.php (6 valores) y
 * op/coliseo_ficha.php (subconjunto [0, 3, 4]). Cae al valor de Civil si la
 * facción no existe o la tabla/columnas todavía no están creadas, igual que
 * el `?? $faccion_colors['Civil']` que ya usaban esos archivos.
 */
function op_faccion_colors($faccion)
{
    global $db;

    $fallback = array('#ff0283', '#c6005c', '#ff0283', 'linear-gradient(42deg, #950044 20%, #f40277 50%, #950044 80%)', '#e0428d', '#c30041');

    if ($db->table_exists('op_facciones') && $db->field_exists('color_faccion', 'op_facciones')) {
        $row = $db->fetch_array($db->simple_select('op_facciones', 'color_faccion, color_rombo, color_border_tag, color_rango, color_border, color_border_pill', "nombre='".$db->escape_string($faccion)."'"));
        if ($row && $row['color_faccion'] !== '') {
            return array($row['color_faccion'], $row['color_rombo'], $row['color_border_tag'], $row['color_rango'], $row['color_border'], $row['color_border_pill']);
        }
    }

    return $fallback;
}

/**
 * Color de texto de facción (usado para colorear el nombre de usuario),
 * antes hardcodeado en member.php con una paleta más oscura que
 * op_faccion_colors() — es intencional, no es un duplicado de esa.
 */
function op_faccion_color_texto($faccion)
{
    global $db;

    $fallback = '#ac0359';

    if ($db->table_exists('op_facciones') && $db->field_exists('color_texto', 'op_facciones')) {
        $row = $db->fetch_array($db->simple_select('op_facciones', 'color_texto', "nombre='".$db->escape_string($faccion)."'"));
        if ($row && $row['color_texto'] !== '') {
            return $row['color_texto'];
        }
    }

    return $fallback;
}

/**
 * Color de fondo de mensaje de chat (rt_chat), resuelto por USERGROUP en vez
 * de por nombre de facción — un mensaje de chat solo trae el usergroup del
 * autor, no su facción. Antes era una lista <if usergroup==X> fija en
 * templates/One_Piece_Gaiden_Templates/rtchat_chat_message.html.
 *
 * Los usergroups 14/4/2 no corresponden a ninguna facción del juego
 * (Narrador, moderación, registrado...) — se mantienen como estaban en la
 * plantilla original, ya que esos grupos no tienen entrada en
 * mybb_op_facciones.
 */
function op_faccion_color_chat_by_usergroup($usergroup_id)
{
    global $db;

    if ($db->table_exists('op_facciones') && $db->field_exists('color_chat', 'op_facciones')) {
        $row = $db->fetch_array($db->simple_select('op_facciones', 'color_chat', "usergroup='".(int)$usergroup_id."'"));
        if ($row && $row['color_chat'] !== '') {
            return $row['color_chat'];
        }
    }

    $non_faccion_groups = array(14 => '#c295ff', 4 => '#c295ff', 2 => '#d7d7d7');

    return isset($non_faccion_groups[(int)$usergroup_id]) ? $non_faccion_groups[(int)$usergroup_id] : '';
}

/**
 * Resuelve el ascenso automático de rango: si el SIGUIENTE rango de esta
 * facción (por orden) tiene umbral de reputación/nivel configurado y el
 * personaje lo cumple, devuelve ese rango nuevo; si no, devuelve el mismo
 * de siempre. Usada tanto por op/personaje.php (síncrono, al ver la ficha)
 * como por inc/tasks/op_worldtick.php (en segundo plano) — antes cada uno
 * tenía su propia copia (personaje.php con una escalera fija de solo 3
 * facciones, worldtick ya con esta misma lógica genérica), lo que podía
 * desincronizarse si se cambiaba un umbral desde el panel.
 */
function op_facciones_resolver_ascenso($faccion, $rango_actual, $nivel, $reputacion)
{
    global $db;

    $actual_row = $db->fetch_array($db->simple_select(
        'op_facciones_rangos', 'orden',
        "faccion='".$db->escape_string($faccion)."' AND valor='".$db->escape_string($rango_actual)."'",
        array('limit' => 1)
    ));
    if (!$actual_row) {
        return $rango_actual;
    }

    $siguiente = $db->fetch_array($db->simple_select(
        'op_facciones_rangos', 'valor, reputacion_min, nivel_min',
        "faccion='".$db->escape_string($faccion)."' AND orden > '".(int)$actual_row['orden']."'",
        array('order_by' => 'orden', 'limit' => 1)
    ));
    if (!$siguiente || is_null($siguiente['reputacion_min']) || is_null($siguiente['nivel_min'])) {
        return $rango_actual;
    }

    if ($reputacion >= $siguiente['reputacion_min'] && intval($nivel) >= $siguiente['nivel_min']) {
        return $siguiente['valor'];
    }

    return $rango_actual;
}

if (defined('IN_ADMINCP')) {
    function op_facciones_install()
    {
        global $db;

        if (!$db->table_exists('op_facciones')) {
            $db->write_query("
                CREATE TABLE mybb_op_facciones (
                    nombre VARCHAR(20) NOT NULL,
                    orden INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (nombre)
                ) ENGINE=InnoDB
            ");
        }

        if (!$db->table_exists('op_facciones_rangos')) {
            $db->write_query("
                CREATE TABLE mybb_op_facciones_rangos (
                    id INT NOT NULL AUTO_INCREMENT,
                    faccion VARCHAR(20) NOT NULL,
                    valor VARCHAR(60) NOT NULL,
                    nombre_visible VARCHAR(100) NOT NULL DEFAULT '',
                    imagen VARCHAR(100) NOT NULL DEFAULT '',
                    orden INT NOT NULL DEFAULT 0,
                    reputacion_min INT NULL DEFAULT NULL,
                    nivel_min INT NULL DEFAULT NULL,
                    sueldo_semanal INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (id),
                    KEY faccion (faccion)
                ) ENGINE=InnoDB
            ");
        }

        // Columnas añadidas en una cuarta pasada: umbral de reputación/nivel
        // para ascender A este rango automáticamente (antes hardcodeado en
        // op/personaje.php para solo 3 facciones). NULL = sin umbral
        // configurado, no asciende solo — mismo comportamiento de siempre
        // para cualquier rango que no tuviera ya esta regla.
        if (!$db->field_exists('reputacion_min', 'op_facciones_rangos')) {
            $db->add_column('op_facciones_rangos', 'reputacion_min', "INT NULL DEFAULT NULL");
        }
        if (!$db->field_exists('nivel_min', 'op_facciones_rangos')) {
            $db->add_column('op_facciones_rangos', 'nivel_min', "INT NULL DEFAULT NULL");
        }

        // Columna añadida en una quinta pasada: sueldo semanal en berries de
        // este rango (antes un array hardcodeado dentro de
        // op/staff/salario_faccion.php). 0 = sin sueldo, ese rango no cobra.
        if (!$db->field_exists('sueldo_semanal', 'op_facciones_rangos')) {
            $db->add_column('op_facciones_rangos', 'sueldo_semanal', "INT NOT NULL DEFAULT 0");
        }

        // Columna añadida en una segunda pasada (mapeo facción → usergroup
        // de MyBB, antes hardcodeado en varios sitios). Si el plugin ya
        // estaba instalado de antes, esto la añade sin tocar lo demás.
        if (!$db->field_exists('usergroup', 'op_facciones')) {
            $db->add_column('op_facciones', 'usergroup', "INT NOT NULL DEFAULT 0");
        }

        // Columnas de color añadidas en una tercera pasada (antes hardcodeadas
        // en op/personaje.php, op/banda.php, op/coliseo_ficha.php y
        // member.php — este último con una paleta de texto distinta a las
        // otras tres, de ahí la columna aparte color_texto).
        foreach (op_facciones_color_columns() as $db_col => $seed_key) {
            if (!$db->field_exists($db_col, 'op_facciones')) {
                $db->add_column('op_facciones', $db_col, "VARCHAR(120) NOT NULL DEFAULT ''");
            }
        }

        // Siembra solo si la tabla de facciones está vacía, para no duplicar
        // si el plugin se reinstala tras haber sido personalizado desde el
        // panel — pero el backfill de usergroup de abajo sí se repite
        // siempre, para poder rellenar la columna nueva en instalaciones
        // que ya tenían las facciones creadas.
        $existing = $db->fetch_field($db->simple_select('op_facciones', 'nombre', '', array('limit' => 1)), 'nombre');

        if (!$existing) {
            foreach (op_facciones_seed_data() as $faccion => $data) {
                $insert = array(
                    'nombre'    => $db->escape_string($faccion),
                    'orden'     => (int)$data['orden'],
                    'usergroup' => isset($data['usergroup']) ? (int)$data['usergroup'] : 0,
                );
                foreach (op_facciones_color_columns() as $db_col => $seed_key) {
                    $insert[$db_col] = isset($data['colores'][$seed_key]) ? $db->escape_string($data['colores'][$seed_key]) : '';
                }
                $db->insert_query('op_facciones', $insert);

                $rango_orden = 10;
                $umbrales = op_facciones_seed_ascenso_thresholds();
                $sueldos = op_facciones_seed_sueldos();
                foreach ($data['rangos'] as $valor => $nombre_visible) {
                    $insert_rango = array(
                        'faccion'        => $db->escape_string($faccion),
                        'valor'          => $db->escape_string($valor),
                        'nombre_visible' => $db->escape_string($nombre_visible),
                        'imagen'         => $db->escape_string($valor),
                        'orden'          => $rango_orden,
                        'sueldo_semanal' => isset($sueldos[$valor]) ? (int)$sueldos[$valor] : 0,
                    );
                    if (isset($umbrales[$valor])) {
                        $insert_rango['reputacion_min'] = (int)$umbrales[$valor][0];
                        $insert_rango['nivel_min']      = (int)$umbrales[$valor][1];
                    }
                    $db->insert_query('op_facciones_rangos', $insert_rango);
                    $rango_orden += 10;
                }
            }
        } else {
            // Backfill: solo toca facciones que ya existían con
            // usergroup=0 (el valor por defecto de la columna nueva), para
            // no pisar un valor que el staff ya hubiera personalizado desde
            // el panel.
            foreach (op_facciones_seed_data() as $faccion => $data) {
                if (!isset($data['usergroup'])) {
                    continue;
                }
                $db->update_query(
                    'op_facciones',
                    array('usergroup' => (int)$data['usergroup']),
                    "nombre='".$db->escape_string($faccion)."' AND usergroup=0"
                );
            }

            // Mismo criterio para los colores: solo rellena columnas que
            // sigan a '' (valor por defecto de la columna nueva), nunca pisa
            // un color ya personalizado desde el panel.
            foreach (op_facciones_seed_data() as $faccion => $data) {
                if (!isset($data['colores'])) {
                    continue;
                }
                foreach (op_facciones_color_columns() as $db_col => $seed_key) {
                    if (!isset($data['colores'][$seed_key])) {
                        continue;
                    }
                    $db->update_query(
                        'op_facciones',
                        array($db_col => $db->escape_string($data['colores'][$seed_key])),
                        "nombre='".$db->escape_string($faccion)."' AND {$db_col}=''"
                    );
                }
            }

            // Mismo criterio para los umbrales de ascenso: solo rellena
            // rangos que sigan con reputacion_min NULL (el valor por defecto
            // de la columna nueva), nunca pisa un umbral ya personalizado
            // desde el panel.
            foreach (op_facciones_seed_ascenso_thresholds() as $valor => $umbral) {
                $db->update_query(
                    'op_facciones_rangos',
                    array('reputacion_min' => (int)$umbral[0], 'nivel_min' => (int)$umbral[1]),
                    "valor='".$db->escape_string($valor)."' AND reputacion_min IS NULL"
                );
            }

            // Mismo criterio para los sueldos semanales: solo rellena rangos
            // que sigan en 0 (el valor por defecto de la columna nueva),
            // nunca pisa un sueldo ya personalizado desde el panel.
            foreach (op_facciones_seed_sueldos() as $valor => $sueldo) {
                $db->update_query(
                    'op_facciones_rangos',
                    array('sueldo_semanal' => (int)$sueldo),
                    "valor='".$db->escape_string($valor)."' AND sueldo_semanal=0"
                );
            }
        }
    }

    function op_facciones_is_installed()
    {
        global $db;
        return $db->table_exists('op_facciones') && $db->table_exists('op_facciones_rangos') && $db->field_exists('usergroup', 'op_facciones') && $db->field_exists('color_texto', 'op_facciones') && $db->field_exists('color_chat', 'op_facciones') && $db->field_exists('reputacion_min', 'op_facciones_rangos') && $db->field_exists('nivel_min', 'op_facciones_rangos') && $db->field_exists('sueldo_semanal', 'op_facciones_rangos');
    }

    function op_facciones_uninstall()
    {
        global $db;
        $db->drop_table('op_facciones_rangos');
        $db->drop_table('op_facciones');
    }

    function op_facciones_activate() {}
    function op_facciones_deactivate() {}
}
