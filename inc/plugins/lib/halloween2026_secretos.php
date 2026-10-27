<?php
/**
 * Halloween 2026: secretos ocultos.
 *
 * Los secretos son globales: cualquiera puede descubrirlos. El primero en descubrir cada uno recibe un Cofre
 * Calabaza (CFRHLW); quienes lo descubren despues, 1 Calabaza Maldita. Ver docs/106_DesignPlan_Halloween.md, seccion 21.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

require_once MYBB_ROOT . 'inc/plugins/lib/halloween2026.php';

const OPG_HLW_SECRET_TOTAL = 30;
const OPG_HLW_SECRET_FIRST_OBJECT = 'CFRHLW';

/**
 * Catalogo de secretos activos. La clave es opaca (es lo que viaja en el HTML y en la peticion), asi no revela
 * cuantos hay ni donde estan los demas.
 *   numero : posicion 1..OPG_HLW_SECRET_TOTAL (para la pagina del evento)
 *   nombre : titulo que ve SOLO quien lo ha descubierto
 *   pista  : donde estaba, tambien solo para quien lo descubrio
 */
function opg_hlw_secret_catalog()
{
    return array(
        'hs-k7q2m9' => array(
            'numero' => 1,
            'nombre' => 'El Luffy gris',
            'pista' => 'Botón gris de Luffy en el índice, junto al de Discord.',
            // Donde vive el secreto y como lo encuentra el script (id neutro puesto en la plantilla).
            'pagina' => 'index.php',
            // Como se encuentra el elemento en la plantilla, sin ponerle ningun id ni atributo.
            'selector' => 'button.button-op-custom[style*="grayscale(0.8)"]',
        ),
        'hs-p4x8d1' => array(
            'numero' => 2,
            'nombre' => 'El título escondido',
            'pista' => 'Imagen del título de la quinta categoría del índice.',
            'pagina' => 'index.php',
            'selector' => 'img[src$="Titulo05_One_Piece_Gaiden_Foro_Rol.jpg"]',
        ),
        // Primer puesto del podio de "Mas experiencia". Es un enlace al perfil: se reclama y despues se sigue al enlace.
        'hs-r9t3b6' => array(
            'numero' => 4,
            'nombre' => 'El primer puesto',
            'pista' => 'Clic en el primer puesto del podio de «Más experiencia», en las estadísticas.',
            'pagina' => 'estadisticas.php',
            'cuando' => array('accion' => 'mas-experiencia'),
            'selector' => 'a.ranking-podio__puesto--1',
            'navegar' => true,
            'imagen' => '/images/op/uploads/TituloDestacado_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // Objeto Calabaza Podrida en la pagina de Objetos. Las tarjetas se crean con JavaScript despues de cargar la
        // pagina, asi que el clic se escucha en todo el documento ('delegar') y se comprueba si cae en el objeto.
        'hs-d3k7a9' => array(
            'numero' => 5,
            'nombre' => 'La calabaza podrida',
            'pista' => 'Clic en el objeto Calabaza Podrida, en la página de Objetos.',
            'pagina' => 'objetos.php',
            'selector' => '#CALPOD',
            'delegar' => true,
            'imagen' => 'https://onepiecegaiden.com/images/op/uploads/calpodd.jpeg',
        ),
        // Objeto Granada de Humo (expl001, en minusculas) en la pagina de Objetos. Tarjeta creada con JavaScript: clic delegado.
        'hs-q4x7n2' => array(
            'numero' => 8,
            'nombre' => 'La granada de humo',
            'pista' => 'Clic en el objeto Granada de Humo, en la página de Objetos.',
            'pagina' => 'objetos.php',
            'selector' => '#expl001',
            'delegar' => true,
            'imagen' => 'https://onepiecegaiden.com/images/op/uploads/Explosivo_1_One_Piece_Gaiden_Foro_Rol.jpg',
        ),
        // Objeto Dial Abrasador (DFUE003) en la pagina de Objetos. Tarjeta creada con JavaScript: clic delegado.
        'hs-w9m3k6' => array(
            'numero' => 9,
            'nombre' => 'El dial abrasador',
            'pista' => 'Clic en el objeto Dial Abrasador, en la página de Objetos.',
            'pagina' => 'objetos.php',
            'selector' => '#DFUE003',
            'delegar' => true,
            'imagen' => 'https://onepiecegaiden.com/images/op/iconos/diales_3_One_Piece_Gaiden_Foro_Rol.jpg',
        ),
        // Banner de la cabecera de la pagina de NPCs (op/compas.php). Esta en la plantilla desde el principio: clic directo.
        // La imagen del modal sale del fondo del propio banner.
        'hs-b1y6s4' => array(
            'numero' => 11,
            'nombre' => 'El banner de los NPCs',
            'pista' => 'Clic en el banner de la cabecera de la página de NPCs.',
            'pagina' => 'compas.php',
            // op/companeros.php declara el mismo THIS_SCRIPT: se exige el nombre real del archivo.
            'archivo' => 'compas.php',
            'selector' => 'div.akumaBanner',
        ),
        // Imagen decorativa del carrito de la Tienda del evento. La tienda se carga por htmx dentro de la pagina, asi que el
        // clic se delega. op/staff/halloween2026.php declara el mismo THIS_SCRIPT y se llama igual: se compara la ruta.
        'hs-c8z2f7' => array(
            'numero' => 12,
            'nombre' => 'El adorno del carrito',
            'pista' => 'Clic en la imagen que decora el carrito de la Tienda del evento.',
            'pagina' => 'halloween2026.php',
            'archivo' => '/op/halloween2026.php',
            'selector' => 'img.hlw-cart__decoration',
            'delegar' => true,
            'imagen' => 'https://i.imgur.com/G5okouN.png',
        ),
        // Comprar un Cofre Calabaza en la tienda. Se reclama en el servidor al completarse la compra y la felicitacion sale al
        // recargarse la pagina del evento. Quien lo compra primero se lleva el premio de "primero"; los demas, 1 Calabaza.
        'hs-s9p3v1' => array(
            'numero' => 13,
            'nombre' => 'El primer cofre de la tienda',
            'pista' => 'Comprar un Cofre Calabaza en la Tienda del evento.',
            'tipo' => 'compra',
            'pagina' => 'halloween2026.php',
            'archivo' => '/op/halloween2026.php',
            'vigilar' => '.hlw-panel',
            'item' => 'cofre-calabaza',
            'imagen' => 'https://i.imgur.com/87wW3Xt.jpeg',
        ),
        // Enlace de usuario del encabezado (#ficha). 'pagina' => '*': vale en TODAS las paginas, porque el encabezado esta en todas.
        // Es el menu desplegable del usuario, asi que el clic se escucha sin bloquearlo ('delegar').
        'hs-f3d8m1' => array(
            'numero' => 14,
            'nombre' => 'El nombre del encabezado',
            'pista' => 'Clic en tu nombre, en el encabezado del foro.',
            'pagina' => '*',
            'selector' => '#ficha',
            'delegar' => true,
            'imagen' => '/images/op/uploads/luffyicon.png',
        ),
        // Imagen de Deep Ocean en el indice. El enlace al subforo es el <a> que la envuelve: se reclama y despues se sigue ese
        // enlace ('navegar'). El titulo que se superpone a la imagen es otro elemento y no cuenta.
        'hs-j4h8t5' => array(
            'numero' => 16,
            'nombre' => 'El mar profundo',
            'pista' => 'Clic en la imagen de Deep Ocean, en el índice.',
            'pagina' => 'index.php',
            'selector' => 'img[src$="20DeapOcean_One_Piece_Gaiden_Foro_Rol.jpg"]',
            'navegar' => true,
        ),
        // Fruta Batto Batto no Mi en la pagina de Akumas. Las tarjetas las crea JavaScript y su onclick usa la POSICION en la lista
        // (clickAkuma(9)), que cambiaria si se anaden o reordenan frutas: por eso se exige tambien el nombre en el texto ('texto').
        'hs-a2e6c9' => array(
            'numero' => 17,
            'nombre' => 'La fruta murciélago',
            'pista' => 'Clic en la Batto Batto no Mi, en la página de Akumas.',
            'pagina' => 'akumas.php',
            'selector' => 'div.akumaItem',
            'texto' => 'Batto Batto no Mi',
            'delegar' => true,
            'imagen' => '/images/op/misc/TipoZoan_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // Fruta Kumo Kumo no Mi en la pagina de Akumas: mismo caso que la Batto Batto no Mi (tarjeta creada con JavaScript, se
        // distingue por el nombre). Comparte selector con ella; cada secreto lleva su propio codigo.
        'hs-u6r1q8' => array(
            'numero' => 18,
            'nombre' => 'La fruta araña',
            'pista' => 'Clic en la Kumo Kumo no Mi, en la página de Akumas.',
            'pagina' => 'akumas.php',
            'selector' => 'div.akumaItem',
            'texto' => 'Kumo Kumo no Mi',
            'delegar' => true,
            'imagen' => '/images/op/misc/TipoZoan_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // Defecto Pesimista (D046) en el registro de virtudes. Las tarjetas las crea JavaScript: clic delegado, y se distingue por
        // su ID (D046), que es mas estable que el nombre. Sin 'archivo': ningun otro archivo usa este THIS_SCRIPT.
        'hs-y3l8e5' => array(
            'numero' => 19,
            'nombre' => 'El defecto pesimista',
            'pista' => 'Clic en el defecto Pesimista, en el registro de virtudes.',
            'pagina' => 'registro_virtudes.php',
            'selector' => 'div.virtudCaja',
            'texto' => 'D046',
            'delegar' => true,
            'imagen' => '/images/op/uploads/TituloDestacado_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // Boton "North Blue" del mapa del mundo. Comparte clase con los botones de los otros mapas, asi que se distingue por el
        // texto. El clic se escucha sin bloquearlo: el boton sigue cambiando de mapa. (Hay otro elemento de North Blue dentro del
        // propio mapa; no cuenta, solo el boton.)
        'hs-v7n2z4' => array(
            'numero' => 20,
            'nombre' => 'El mar del norte',
            'pista' => 'Clic en el botón «North Blue» del mapa del mundo.',
            'pagina' => 'mapa_mundo.php',
            'selector' => 'button.boton-mapa',
            'texto' => 'North Blue',
            'delegar' => true,
            'imagen' => '/images/op/uploads/TituloDestacado_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // Segundo avatar de la ficha del NPC Barbanieves en la pagina de NPCs. Es una unica <img id="npcAvatar2"> cuyo src cambia con
        // JavaScript segun el NPC abierto: solo vale cuando el src es el de Barbanieves, y eso se comprueba al hacer clic (clic delegado).
        'hs-z5q9d2' => array(
            'numero' => 21,
            'nombre' => 'El segundo rostro de Barbanieves',
            'pista' => 'Clic en el segundo avatar de la ficha de Barbanieves, en la página de NPCs.',
            'pagina' => 'compas.php',
            'archivo' => 'compas.php',
            'selector' => 'img#npcAvatar2[src$="Barbanieves1.gif"]',
            'delegar' => true,
            'imagen' => 'https://onepiecegaiden.com/images/op/uploads/Barbanieves1.gif',
        ),
        // Apartado "Apariencia" de la ficha del NPC Lisstez M. Shingehardz. El apartado (#aparienciaBox) es el mismo para todos los NPCs: solo
        // vale cuando el nombre mostrado en la ficha (#npcNombre) es el suyo ('condicion' => selector y texto), y se comprueba al hacer clic.
        'hs-b4t6x9' => array(
            'numero' => 22,
            'nombre' => 'La apariencia de Lisstez',
            'pista' => 'Clic en el apartado «Apariencia» de la ficha de Lisstez M. Shingehardz, en la página de NPCs.',
            'pagina' => 'compas.php',
            'archivo' => 'compas.php',
            'selector' => '#aparienciaBox',
            'condicion' => array('#npcNombre', 'Lisstez M. Shingehardz'),
            'delegar' => true,
            'imagen' => '/images/op/uploads/TituloDestacado_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // Imagen del capitan Lance Turner en una noticia de la Prensa Rosa. Es unica en la pagina (se identifica por su archivo). Clic escuchado
        // sin bloquearlo, por si la imagen tuviera algun comportamiento propio.
        'hs-e8m5j3' => array(
            'numero' => 23,
            'nombre' => 'El capitán de la portada',
            'pista' => 'Clic en la imagen de Lance Turner, en la página de la Prensa Rosa.',
            'pagina' => 'prensarosa.php',
            'selector' => 'img[src$="bab46b493579593fd6eef846bc3c9869.jpg"]',
            'delegar' => true,
            'imagen' => 'https://i.pinimg.com/736x/ba/b4/6b/bab46b493579593fd6eef846bc3c9869.jpg',
        ),
        // Recompensa de los 30 dias de la pagina de recompensas diarias. Las tarjetas son estaticas, pero el JavaScript de la pagina les
        // añade la clase al cargar: se escucha el clic sin bloquearlo y se distingue por el texto ('30 dias:'). La ruta se compara porque
        // op/recompensas.php, op/recompensas2.php y opg/recompensas.php comparten THIS_SCRIPT.
        'hs-k2d7p6' => array(
            'numero' => 24,
            'nombre' => 'La recompensa de los treinta días',
            'pista' => 'Clic en la recompensa de 30 días, en la página de recompensas.',
            'pagina' => 'recompensas.php',
            'archivo' => '/op/recompensas.php',
            'selector' => 'div.reward-item',
            'texto' => '30 días:',
            'delegar' => true,
            'imagen' => '/images/op/uploads/TituloDestacado_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // Icono de Puntos de Oficio de la portada de la ficha (op_ficha_portada.html), que muestra personaje.php. Es la imagen dentro
        // del circulo blanco; es unica en la plantilla y se identifica por su archivo. Clic escuchado sin bloquearlo.
        'hs-m9c4h2' => array(
            'numero' => 25,
            'nombre' => 'El icono del oficio',
            'pista' => 'Clic en el icono de Puntos de Oficio de la portada de una ficha, en la página de personaje.',
            'pagina' => 'personaje.php',
            'selector' => 'img[src$="5421605-200.png"]',
            'delegar' => true,
            'imagen' => 'https://static.thenounproject.com/png/5421605-200.png',
        ),
        // Icono de Kuros de "Saldo actual" en la tienda de Kuros. La misma imagen (img.nikab) sale en otros recuadros y en cada precio de la
        // lista, asi que el selector exige la que sigue justo a #saldoActual. tienda_kuros.php declara por error THIS_SCRIPT = tienda_nika.php
        // (copia de otra pagina): se compara tambien la ruta. Clic escuchado sin bloquearlo.
        'hs-r6u3b8' => array(
            'numero' => 26,
            'nombre' => 'El saldo de los Kuros',
            'pista' => 'Clic en el icono de Kuros del «Saldo actual», en la tienda de Kuros.',
            'pagina' => 'tienda_nika.php',
            'archivo' => '/op/tienda_kuros.php',
            'selector' => '#saldoActual + img.nikab',
            'delegar' => true,
            'imagen' => '/images/op/uploads/KuroPoint_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // Secreto de enlace: se descubre siguiendo un enlace normal puesto en un mensaje del foro (op/descubrir.php?c=CODIGO). Sin pagina
        // ni selector. El enlace puede repartirse sin problema: cada usuario lo descubre una sola vez. Abrirlo no reclama nada: hay que
        // pulsar un boton en esa pagina.
        'hs-l5g7y1' => array(
            'numero' => 27,
            'nombre' => 'El enlace escondido',
            'pista' => 'Seguir un enlace escondido en un mensaje del foro.',
            'tipo' => 'link',
            'codigo' => 'ficha-belica-asfixia',
            'imagen' => '/images/op/uploads/TituloDestacado_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // Segundo secreto de enlace (ver el 27): op/descubrir.php?c=beta-opg.
        'hs-v4d9s2' => array(
            'numero' => 28,
            'nombre' => 'La puerta de la beta',
            'pista' => 'Seguir un enlace escondido en un mensaje del foro.',
            'tipo' => 'link',
            'codigo' => 'beta-opg',
            'imagen' => '/images/op/uploads/TituloDestacado_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // Tercer secreto de enlace (ver el 27): op/descubrir.php?c=enciclopedia-razas. Pensado para ponerlo en la enciclopedia de razas.
        'hs-x2j6c4' => array(
            'numero' => 29,
            'nombre' => 'El secreto de las razas',
            'pista' => 'Seguir un enlace escondido en la enciclopedia de razas.',
            'tipo' => 'link',
            'codigo' => 'enciclopedia-razas',
            'imagen' => '/images/op/uploads/TituloDestacado_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // Cuarto secreto de enlace (ver el 27): op/descubrir.php?c=la-muerte-mas-epica. Relacionado con el tema "El Despertar de Norfeo".
        'hs-m8q2t7' => array(
            'numero' => 30,
            'nombre' => 'La muerte más épica',
            'pista' => 'Seguir un enlace escondido en el tema de El Despertar de Norfeo.',
            'tipo' => 'link',
            'codigo' => 'la-muerte-mas-epica',
            'imagen' => '/images/op/uploads/TituloDestacado_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // Icono del fantasma de la cabecera de la pagina del evento. Esta en la plantilla, fuera del panel que cambia con htmx
        // (existe desde que carga la pagina): clic directo. La consola de staff comparte THIS_SCRIPT: se compara la ruta.
        'hs-n5k9r3' => array(
            'numero' => 15,
            'nombre' => 'El fantasma de la cabecera',
            'pista' => 'Clic en el icono del fantasma, en la cabecera de la página del evento.',
            'pagina' => 'halloween2026.php',
            'archivo' => '/op/halloween2026.php',
            'selector' => 'div.hlw-header__mark',
            'imagen' => '/images/op/halloween2026/cofre-calabaza.jpeg',
        ),
        // Boton "Tirada de prueba" de la pagina del Haki del Rey. 'archivo' porque opg/tirada_cofre.php declara por
        // error el mismo THIS_SCRIPT (tirada_rey.php) y tambien tiene un boton .testDraw.
        'hs-h6j2q4' => array(
            'numero' => 6,
            'nombre' => 'La tirada de prueba',
            'pista' => 'Clic en «Tirada de prueba» en la página del Haki del Rey.',
            'pagina' => 'tirada_rey.php',
            'archivo' => 'tirada_rey.php',
            'selector' => '.testDraw',
            'delegar' => true,
            'imagen' => '/images/op/uploads/TituloHakiRey_One_Piece_Gaiden_Foro_Rol.png',
        ),
        // GIF de cofres de la pagina de tiradas de cofres. THIS_SCRIPT de opg/tirada_cofre.php es 'tirada_rey.php' (error de
        // copia), asi que se distingue por el nombre real del archivo.
        'hs-g7w4e8' => array(
            'numero' => 7,
            'nombre' => 'El baúl animado',
            'pista' => 'Clic en la imagen animada de los cofres, en la página de tiradas de cofres.',
            'pagina' => 'tirada_rey.php',
            'archivo' => 'tirada_cofre.php',
            'selector' => 'img[src$="GifCofres_One_Piece_Foro_Rol.gif"]',
            'delegar' => true,
            'imagen' => '/images/op/uploads/GifCofres_One_Piece_Foro_Rol.gif',
        ),
        // Secreto del chat: no se pulsa nada, se detecta en el servidor al escribir la palabra en el chat del indice.
        'hs-m2c6w5' => array(
            'numero' => 3,
            'nombre' => 'La palabra mágica',
            'pista' => 'Escribir «calabaza» en el chatbox del índice.',
            'tipo' => 'chat',
            'pagina' => 'index.php',
            'vigilar' => '.chatbox_div',
            // Expresion sobre el mensaje ya en minusculas y sin tildes. Aqui vale tambien "calabazas" (plural).
            'patron' => '/(^|[^a-z0-9])calabaza/',
            'imagen' => '/images/op/uploads/Chatbox_One_Piece_Gaiden_Foro_Rol.jpg',
        ),
        // Segunda palabra del chat: la palabra completa, sin letras ni numeros pegados delante o detras.
        'hs-t2u5l9' => array(
            'numero' => 10,
            'nombre' => 'La contraseña del evento',
            'pista' => 'Escribir «halloween2026» en el chatbox del índice.',
            'tipo' => 'chat',
            'pagina' => 'index.php',
            'vigilar' => '.chatbox_div',
            'patron' => '/(^|[^a-z0-9])halloween2026($|[^a-z0-9])/',
            'imagen' => '/images/op/uploads/Chatbox_One_Piece_Gaiden_Foro_Rol.jpg',
        ),
    );
}

function opg_hlw_secret_by_number($number)
{
    foreach (opg_hlw_secret_catalog() as $key => $secret) {
        if ((int)$secret['numero'] === (int)$number) {
            return array_merge($secret, array('clave' => $key));
        }
    }
    return null;
}

/** Secreto de enlace por el codigo que lleva su direccion (op/descubrir.php?c=CODIGO), o null. Compara sin dar pistas por tiempo. */
function opg_hlw_secret_by_code($code)
{
    $code = (string)$code;
    if ($code === '' || strlen($code) > 64) {
        return null;
    }
    foreach (opg_hlw_secret_catalog() as $key => $secret) {
        if (($secret['tipo'] ?? '') === 'link' && isset($secret['codigo']) && hash_equals((string)$secret['codigo'], $code)) {
            return array_merge($secret, array('clave' => $key));
        }
    }
    return null;
}

function opg_hlw_secrets_ready()
{
    return opg_hlw_tables_ready() && opg_hlw_table_exists('secretos');
}

function opg_hlw_secrets_alerts_ready()
{
    return opg_hlw_tables_ready() && opg_hlw_table_exists('secretos_alertas');
}

/**
 * Donde escribir el script en una pagina: antes de </body>; si la plantilla no lo tiene (hay paginas propias sin cierre, como la
 * Prensa Rosa), antes de </html>; y si tampoco, al final, pero solo si parece HTML. Devuelve false si no es una pagina HTML
 * (JSON, texto, etc.), para no ensuciar esas respuestas.
 */
function opg_hlw_secret_insert_position($page)
{
    if (!is_string($page) || $page === '') {
        return false;
    }
    foreach (array('</body>', '</html>') as $closing) {
        $position = strripos($page, $closing);
        if ($position !== false) {
            return $position;
        }
    }
    $start = ltrim($page);
    if ($start !== '' && ($start[0] === '{' || $start[0] === '[')) {
        return false;
    }
    if (stripos($page, '<head') !== false || stripos($page, '<div') !== false || stripos($page, '<script') !== false || stripos($page, '<html') !== false) {
        return strlen($page);
    }
    return false;
}

/**
 * Secretos que viven en una pagina (por THIS_SCRIPT): clave => fila del catalogo. Si el secreto solo vale en una vista
 * de la pagina ('cuando' => parametro => valor), se comprueba contra los parametros de la peticion ($_GET por defecto).
 * Si se indica 'archivo', tiene que coincidir con el archivo PHP que se esta ejecutando (por nombre o por ruta final).
 */
function opg_hlw_secret_page_entries($page, $params = null, $file = null)
{
    $params = $params === null ? $_GET : $params;
    $file = $file === null ? (string)($_SERVER['SCRIPT_NAME'] ?? '') : (string)$file;
    $found = array();
    foreach (opg_hlw_secret_catalog() as $key => $secret) {
        // 'pagina' => '*' son secretos globales (viven en el encabezado u otra parte comun a todas las paginas).
        if (!isset($secret['pagina']) || ($secret['pagina'] !== '*' && $secret['pagina'] !== $page)) {
            continue;
        }
        // Algunas paginas comparten THIS_SCRIPT: 'archivo' exige ademas el archivo PHP real. Sin barras (tirada_rey.php)
        // se compara el nombre; con barras (/op/halloween2026.php) se compara el final de la ruta, para distinguir
        // archivos con el mismo nombre en carpetas distintas.
        if (isset($secret['archivo'])) {
            $wanted = (string)$secret['archivo'];
            $matches = strpos($wanted, '/') !== false
                ? substr($file, -strlen($wanted)) === $wanted
                : basename($file) === $wanted;
            if (!$matches) {
                continue;
            }
        }
        foreach ((array)($secret['cuando'] ?? array()) as $name => $value) {
            if (!isset($params[$name]) || (string)$params[$name] !== (string)$value) {
                continue 2;
            }
        }
        $found[$key] = $secret;
    }
    return $found;
}

/** Cuentas que pueden descubrir secretos para probarlos mientras el descubrimiento esta deshabilitado. */
const OPG_HLW_SECRET_TESTERS = array(315, 172);

/** Interruptor de la consola de staff: apagado (valor por defecto), solo las cuentas de prueba pueden descubrir. */
function opg_hlw_secrets_enabled($config = null)
{
    $config = $config === null ? opg_hlw_config() : $config;
    return !empty($config['settings']['secrets_enabled']);
}

function opg_hlw_secrets_is_tester($uid)
{
    return in_array((int)$uid, OPG_HLW_SECRET_TESTERS, true);
}

/**
 * ¿Puede este usuario descubrir secretos ahora?
 *  - Descubrimiento deshabilitado: solo las cuentas de prueba (315 y 172), en cualquier momento.
 *  - Habilitado: todos, mientras el evento este activo y dentro de la ventana de obtencion.
 * Ver los secretos en la pagina del evento no depende de esto.
 */
function opg_hlw_secrets_open($config = null, $uid = 0)
{
    $config = $config === null ? opg_hlw_config() : $config;
    if (!$config) {
        return false;
    }
    if (opg_hlw_secrets_is_tester($uid)) {
        return true;
    }
    if (!opg_hlw_secrets_enabled($config)) {
        return false;
    }
    if ((string)($config['status'] ?? 'draft') !== 'active') {
        return false;
    }
    return opg_hlw_timestamp_in_earning_window(time(), $config);
}

/** UTC de la base de datos -> "d/m/Y H:i" hora de Madrid. */
function opg_hlw_secret_date($utc)
{
    try {
        $date = new DateTimeImmutable((string)$utc, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone('Europe/Madrid'))->format('d/m/Y H:i');
    } catch (Exception $e) {
        return (string)$utc;
    }
}

/**
 * Reclama un secreto. Devuelve array('estado' => ..., 'mensaje' => ..., ...).
 * Estados: first, new, already, closed, ineligible, unknown, error.
 */
function opg_hlw_secret_claim($uid, $key)
{
    global $db;
    $uid = (int)$uid;
    $key = (string)$key;
    $catalog = opg_hlw_secret_catalog();

    // 'motivo' no se muestra al usuario: el script lo escribe en la consola del navegador para poder diagnosticar.
    if ($uid <= 0) {
        return array('estado' => 'closed', 'mensaje' => '', 'motivo' => 'sin_sesion');
    }
    if (!opg_hlw_secrets_ready()) {
        return array('estado' => 'closed', 'mensaje' => '', 'motivo' => 'falta_tabla_secretos');
    }
    if (!isset($catalog[$key])) {
        return array('estado' => 'unknown', 'mensaje' => '', 'motivo' => 'clave_desconocida');
    }
    $config = opg_hlw_config();
    if (!opg_hlw_secrets_open($config, $uid)) {
        return array('estado' => 'closed', 'mensaje' => '', 'motivo' => 'descubrimiento_deshabilitado_o_evento_cerrado');
    }
    if (!opg_hlw_eligible_character($uid)) {
        return array('estado' => 'ineligible', 'mensaje' => '', 'motivo' => 'sin_ficha_aprobada_o_muerto');
    }

    $table = opg_hlw_table('secretos');
    $keySql = $db->escape_string($key);
    $now = opg_hlw_utc_now();

    $db->write_query('START TRANSACTION');
    try {
        $query = $db->write_query("SELECT id FROM `{$table}` WHERE secreto='{$keySql}' AND uid='{$uid}' LIMIT 1 FOR UPDATE");
        if ($db->fetch_array($query)) {
            $db->write_query('ROLLBACK');
            return array('estado' => 'already', 'mensaje' => 'Ya habías descubierto este secreto.');
        }

        // El indice unico (secreto, primero_marca) decide quien es el primero aunque dos clics lleguen a la vez.
        $db->write_query("INSERT IGNORE INTO `{$table}` (`secreto`,`uid`,`es_primero`,`primero_marca`,`premio`,`created_at`)
            VALUES ('{$keySql}',{$uid},1,1,'cofre','{$now}')");
        $first = $db->affected_rows() === 1;
        if (!$first) {
            $db->write_query("INSERT IGNORE INTO `{$table}` (`secreto`,`uid`,`es_primero`,`primero_marca`,`premio`,`created_at`)
                VALUES ('{$keySql}',{$uid},0,NULL,'calabaza','{$now}')");
            if ($db->affected_rows() !== 1) {
                $db->write_query('ROLLBACK');
                return array('estado' => 'already', 'mensaje' => 'Ya habías descubierto este secreto.');
            }
        }
        $rowId = (int)$db->insert_id();

        $movementId = 0;
        if ($first) {
            $objectSql = $db->escape_string(OPG_HLW_SECRET_FIRST_OBJECT);
            $query = $db->simple_select('op_objetos', 'objeto_id', "objeto_id='{$objectSql}'", array('limit' => 1));
            if (!$db->fetch_field($query, 'objeto_id')) {
                throw new RuntimeException('missing_object');
            }
            $inventory = $db->table_prefix . 'op_inventario';
            $db->write_query("INSERT INTO `{$inventory}` (`objeto_id`,`uid`,`cantidad`)
                VALUES ('{$objectSql}',{$uid},1)
                ON DUPLICATE KEY UPDATE cantidad=cantidad+VALUES(cantidad)");
        } else {
            $movement = opg_hlw_add_movement(array(
                'uid' => $uid, 'amount' => 1, 'bucket' => 'earned',
                'source_type' => 'secret', 'source_id' => $key,
                'idempotency_key' => 'halloween2026:secret:' . $key . ':' . $uid,
                'note' => 'Secreto #' . (int)$catalog[$key]['numero'] . ' descubierto',
            ), false);
            if (empty($movement['ok'])) {
                throw new RuntimeException('movement_failed');
            }
            $movementId = (int)($movement['movement_id'] ?? 0);
        }
        if ($movementId > 0) {
            $db->write_query("UPDATE `{$table}` SET movement_id='{$movementId}' WHERE id='{$rowId}'");
        }
        $db->write_query('COMMIT');
    } catch (Exception $e) {
        $db->write_query('ROLLBACK');
        return array('estado' => 'error', 'mensaje' => 'No se pudo registrar el secreto. Inténtalo de nuevo.');
    }

    $number = (int)$catalog[$key]['numero'];
    $name = (string)$catalog[$key]['nombre'];
    $image = (string)($catalog[$key]['imagen'] ?? '');
    // Los textos del modal los pone el servidor: el script del navegador no lleva ninguno.
    $labels = array('kicker' => 'Secreto #' . $number . ' descubierto', 'boton' => '¡Genial!');
    if ($first) {
        return array(
            'estado' => 'first', 'primero' => true, 'numero' => $number, 'nombre' => $name, 'imagen' => $image,
            'premio' => 'Premio: 1 Cofre Calabaza', 'titulo' => '¡Enhorabuena, fuiste la primera persona!',
            'mensaje' => '¡Fuiste la primera persona en encontrarlo! Como premio especial, te llevas 1 Cofre Calabaza.',
        ) + $labels;
    }
    return array(
        'estado' => 'new', 'primero' => false, 'numero' => $number, 'nombre' => $name, 'imagen' => $image,
        'premio' => 'Premio: 1 Calabaza Maldita', 'titulo' => '¡Felicidades!',
        'mensaje' => 'Has encontrado uno de los secretos ocultos del foro. Ganas 1 Calabaza Maldita.',
    ) + $labels;
}

/** Secretos descubiertos por el usuario: clave => fila. */
function opg_hlw_secret_mine($uid)
{
    global $db;
    $rows = array();
    if (!opg_hlw_secrets_ready()) {
        return $rows;
    }
    $uid = (int)$uid;
    $query = $db->simple_select('op_halloween2026_secretos', 'secreto,es_primero,premio,created_at', "uid='{$uid}'", array('order_by' => 'created_at'));
    while ($row = $db->fetch_array($query)) {
        $rows[$row['secreto']] = $row;
    }
    return $rows;
}

/** Primera persona que descubrio cada secreto, y cuantas lo han descubierto en total: clave => fila. */
function opg_hlw_secret_firsts()
{
    global $db;
    $rows = array();
    if (!opg_hlw_secrets_ready()) {
        return $rows;
    }
    $table = opg_hlw_table('secretos');
    $users = $db->table_prefix . 'users';
    $fichas = $db->table_prefix . 'op_fichas';
    $query = $db->write_query("SELECT s.secreto, s.uid, s.created_at, COALESCE(NULLIF(f.nombre,''), u.username) AS nombre
        FROM `{$table}` s
        LEFT JOIN `{$users}` u ON u.uid = s.uid
        LEFT JOIN `{$fichas}` f ON f.fid = s.uid
        WHERE s.es_primero = 1");
    while ($row = $db->fetch_array($query)) {
        $rows[$row['secreto']] = $row + array('total' => 1);
    }
    $query = $db->write_query("SELECT secreto, COUNT(*) AS total FROM `{$table}` GROUP BY secreto");
    while ($row = $db->fetch_array($query)) {
        if (isset($rows[$row['secreto']])) {
            $rows[$row['secreto']]['total'] = (int)$row['total'];
        }
    }
    return $rows;
}

// ── Prueba de visita, señuelos y alertas ─────────────────────────────────────────────────────────────────────

/** Señuelos por pagina: elementos ocultos que ningun usuario puede pulsar; reclamar uno delata un script. */
const OPG_HLW_SECRET_DECOYS = 3;
/** Un humano tarda en ver la pagina y pulsar: un reclamo mas rapido que esto se rechaza. */
const OPG_HLW_SECRET_MIN_SECONDS = 1.5;
/** Caducidad del codigo de visita. */
const OPG_HLW_SECRET_TOKEN_TTL = 43200;

function opg_hlw_secret_crypto_key()
{
    $mybb = isset($GLOBALS['mybb']) ? $GLOBALS['mybb'] : null;
    $db = is_object($mybb) && isset($mybb->config['database']) ? $mybb->config['database'] : array();
    $material = (string)($db['password'] ?? '') . '|' . (string)($db['database'] ?? '') . '|'
        . (string)($db['table_prefix'] ?? '') . '|halloween2026-secretos';
    return sodium_crypto_generichash($material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
}

function opg_hlw_secret_b64($raw)
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/** Cifra y autentica el contenido del codigo: el navegador solo ve texto opaco (ni la clave ni si es señuelo). */
function opg_hlw_secret_token_seal(array $payload)
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = sodium_crypto_secretbox(json_encode($payload), $nonce, opg_hlw_secret_crypto_key());
    return opg_hlw_secret_b64($nonce . $cipher);
}

function opg_hlw_secret_token_open($token)
{
    $raw = base64_decode(strtr((string)$token, '-_', '+/'), true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, opg_hlw_secret_crypto_key());
    if ($plain === false) {
        return null;
    }
    $payload = json_decode($plain, true);
    return is_array($payload) && isset($payload['u'], $payload['s'], $payload['i'], $payload['d']) ? $payload : null;
}

/**
 * Codigos para una carga de pagina: uno por cada secreto real que el usuario aun no ha descubierto, mas los
 * señuelos. Devuelve una lista mezclada de array('t' => codigo, 's' => selector).
 */
function opg_hlw_secret_issue($uid, $page, $alreadyFound = array(), $now = null, $params = null, $file = null)
{
    $now = $now === null ? time() : (int)$now;
    $entries = array();
    foreach (opg_hlw_secret_page_entries($page, $params, $file) as $key => $secret) {
        // Los secretos de chat, visita y compra no se pulsan: no llevan codigo ni selector.
        if (isset($alreadyFound[$key]) || in_array(($secret['tipo'] ?? ''), array('chat', 'visita', 'compra'), true)) {
            continue;
        }
        $entries[] = array(
            't' => opg_hlw_secret_token_seal(array('u' => (int)$uid, 's' => $key, 'i' => $now, 'd' => 0)),
            's' => (string)$secret['selector'],
            // 'n' = es un enlace: el script reclama y despues sigue al enlace. Los señuelos llevan el campo tambien.
            'n' => !empty($secret['navegar']) ? 1 : 0,
            // 'g' = el elemento no existe al cargar: el clic se escucha en todo el documento. Los señuelos llevan el campo tambien.
            'g' => !empty($secret['delegar']) ? 1 : 0,
            // 'x' = texto que debe contener el elemento (para distinguir uno entre muchos iguales). Siempre presente, vacio si no hace falta.
            'x' => (string)($secret['texto'] ?? ''),
            // 'y' = condicion sobre OTRO elemento de la pagina: [selector, texto que debe contener]. Vacio si no hace falta.
            'y' => !empty($secret['condicion']) ? array_values((array)$secret['condicion']) : '',
        );
    }
    if (!$entries) {
        return array();
    }
    // Señuelos: elementos que el script crea ocultos, con un id distinto en cada visita (no hay ninguno fijo que buscar).
    for ($n = 0; $n < OPG_HLW_SECRET_DECOYS; $n++) {
        $entries[] = array(
            't' => opg_hlw_secret_token_seal(array('u' => (int)$uid, 's' => 'd-' . bin2hex(random_bytes(4)), 'i' => $now, 'd' => 1)),
            's' => '#v' . bin2hex(random_bytes(3)),
            'n' => random_int(0, 1),
            'g' => random_int(0, 1),
            'x' => '',
            'y' => '',
        );
    }
    shuffle($entries);
    return $entries;
}

/**
 * Reclama los secretos de compra que correspondan a una compra ya completada: uno por cada secreto cuyo articulo
 * ('item', el slug de la tienda) este en la compra. Devuelve la lista de resultados que hay que felicitar (first / new).
 * Se llama DESPUES de confirmar la compra, nunca dentro de su transaccion.
 */
function opg_hlw_secret_claim_purchase($uid, $purchaseId)
{
    global $db;
    $results = array();
    $uid = (int)$uid;
    if (!opg_hlw_secrets_ready() || !opg_hlw_table_exists('compras') || !opg_hlw_table_exists('compra_lineas') || !opg_hlw_table_exists('tienda_items')
        || opg_hlw_secret_is_blocked($uid)) {
        return $results;
    }
    $query = $db->write_query('SELECT DISTINCT i.slug FROM `' . opg_hlw_table('compra_lineas') . '` l
        INNER JOIN `' . opg_hlw_table('compras') . "` p ON p.id=l.purchase_id AND p.uid='{$uid}' AND p.estado='completed'
        INNER JOIN `" . opg_hlw_table('tienda_items') . "` i ON i.id=l.item_id
        WHERE l.purchase_id='" . (int)$purchaseId . "'");
    $slugs = array();
    while ($row = $db->fetch_array($query)) {
        $slugs[] = (string)$row['slug'];
    }
    foreach (opg_hlw_secret_catalog() as $key => $secret) {
        if (($secret['tipo'] ?? '') === 'compra' && in_array((string)($secret['item'] ?? ''), $slugs, true)) {
            $result = opg_hlw_secret_claim($uid, $key);
            if (in_array($result['estado'], array('first', 'new'), true)) {
                $results[] = $result;
            }
        }
    }
    return $results;
}

/** Deja los resultados (first / new) en una cookie de 90 s: el script de la pagina siguiente la recoge y felicita. */
function opg_hlw_secret_set_result_cookie(array $results)
{
    if (!$results || headers_sent()) {
        return;
    }
    $compact = array_map(function ($result) { return opg_hlw_secret_client_result($result); }, array_values($results));
    $value = rtrim(strtr(base64_encode(json_encode($compact, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    setcookie('ei_r', $value, array('expires' => time() + 90, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => false, 'samesite' => 'Lax'));
}

/**
 * Numeros de los secretos que, segun la cookie ei_g, el navegador ya sabe que ha descubierto. Sirve solo de atajo para no
 * consultar la base de datos en cada pagina por un secreto global ya descubierto: si la cookie miente, solo se perjudica a si mismo.
 */
function opg_hlw_secret_cookie_found()
{
    $raw = isset($_COOKIE['ei_g']) ? (string)$_COOKIE['ei_g'] : '';
    return $raw === '' ? array() : array_values(array_unique(array_map('intval', explode('.', $raw))));
}

/** Entradas de la pagina que aun faltan segun la cookie (los secretos que no son globales no se descartan nunca por la cookie). */
function opg_hlw_secret_pending_by_cookie($entries)
{
    $seen = opg_hlw_secret_cookie_found();
    return array_filter($entries, function ($secret) use ($seen) {
        return !(($secret['pagina'] ?? '') === '*' && in_array((int)$secret['numero'], $seen, true));
    });
}

/** Selector de la zona a vigilar en esta pagina, si queda algun secreto de chat o de compra sin descubrir. */
function opg_hlw_secret_page_watch($page, $alreadyFound = array(), $file = null)
{
    foreach (opg_hlw_secret_page_entries($page, null, $file) as $key => $secret) {
        if (in_array(($secret['tipo'] ?? ''), array('chat', 'compra'), true) && !isset($alreadyFound[$key]) && !empty($secret['vigilar'])) {
            return (string)$secret['vigilar'];
        }
    }
    return '';
}

/** Mensaje del chat en minusculas y sin tildes; null si no puede contar (vacio o comando que empieza por "/"). */
function opg_hlw_chat_normalize($message)
{
    $message = trim((string)$message);
    if ($message === '' || $message[0] === '/') {
        return null;
    }
    return strtr(mb_strtolower($message, 'UTF-8'), array('á' => 'a', 'à' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u', 'ü' => 'u'));
}

/** Claves de los secretos de chat cuya palabra lleva este mensaje (tambien con mayusculas, tildes o signos). */
function opg_hlw_chat_matching_keys($message)
{
    $normalized = opg_hlw_chat_normalize($message);
    $keys = array();
    if ($normalized === null) {
        return $keys;
    }
    foreach (opg_hlw_secret_catalog() as $key => $secret) {
        if (($secret['tipo'] ?? '') === 'chat' && !empty($secret['patron']) && preg_match($secret['patron'], $normalized) === 1) {
            $keys[] = $key;
        }
    }
    return $keys;
}

/** ¿Este mensaje del chat lleva la palabra de algun secreto? */
function opg_hlw_chat_message_matches($message)
{
    return opg_hlw_chat_matching_keys($message) !== array();
}

/**
 * Se ha escrito alguna palabra de secreto en el chat: reclama cada secreto de chat que corresponda (un mensaje puede
 * llevar mas de una palabra). Devuelve la lista de resultados que hay que felicitar (first / new); vacia si no procede.
 * Lo llama el hook rt_chat_commit_message_insert, es decir, con el mensaje ya guardado.
 */
function opg_hlw_secret_claim_chat($uid, $message)
{
    $results = array();
    $keys = opg_hlw_chat_matching_keys($message);
    if (!$keys || !opg_hlw_secrets_ready() || opg_hlw_secret_is_blocked($uid)) {
        return $results;
    }
    foreach ($keys as $key) {
        $result = opg_hlw_secret_claim((int)$uid, $key);
        if (in_array($result['estado'], array('first', 'new'), true)) {
            $results[] = $result;
        }
    }
    return $results;
}

function opg_hlw_secret_alert($uid, $type, $detail = '')
{
    global $db;
    if (!opg_hlw_secrets_alerts_ready()) {
        return;
    }
    $ip = function_exists('get_ip') ? (string)get_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $agent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $table = opg_hlw_table('secretos_alertas');
    $db->write_query("INSERT INTO `{$table}` (`uid`,`tipo`,`detalle`,`ip`,`user_agent`,`created_at`) VALUES ("
        . (int)$uid . ",'" . $db->escape_string(substr((string)$type, 0, 16)) . "','" . $db->escape_string(substr((string)$detail, 0, 255)) . "','"
        . $db->escape_string(substr($ip, 0, 45)) . "','" . $db->escape_string(substr($agent, 0, 255)) . "','" . opg_hlw_utc_now() . "')");
}

/** Quien reclamo un señuelo queda bloqueado para todos los secretos (las cuentas de prueba nunca se bloquean). */
function opg_hlw_secret_is_blocked($uid)
{
    global $db;
    if (opg_hlw_secrets_is_tester($uid) || !opg_hlw_secrets_alerts_ready()) {
        return false;
    }
    $table = opg_hlw_table('secretos_alertas');
    $query = $db->write_query("SELECT id FROM `{$table}` WHERE uid='" . (int)$uid . "' AND tipo='senuelo' LIMIT 1");
    return (bool)$db->fetch_array($query);
}

function opg_hlw_secret_alerts_list($limit = 50)
{
    global $db;
    $rows = array();
    if (!opg_hlw_secrets_alerts_ready()) {
        return $rows;
    }
    $table = opg_hlw_table('secretos_alertas');
    $users = $db->table_prefix . 'users';
    $fichas = $db->table_prefix . 'op_fichas';
    $query = $db->write_query("SELECT a.*, COALESCE(NULLIF(f.nombre,''), u.username) AS nombre
        FROM `{$table}` a LEFT JOIN `{$users}` u ON u.uid=a.uid LEFT JOIN `{$fichas}` f ON f.fid=a.uid
        ORDER BY a.id DESC LIMIT " . max(1, min(200, (int)$limit)));
    while ($row = $db->fetch_array($query)) {
        $rows[] = $row;
    }
    return $rows;
}

function opg_hlw_secret_alerts_forgive($uid)
{
    global $db;
    if (!opg_hlw_secrets_alerts_ready()) {
        return;
    }
    $db->write_query('DELETE FROM `' . opg_hlw_table('secretos_alertas') . "` WHERE uid='" . (int)$uid . "'");
}

/**
 * Reclamo con prueba de visita. Valida el codigo y, si es correcto, reclama el secreto real.
 *  - Codigo invalido, de otro usuario o caducado: se ignora (alerta suave).
 *  - Señuelo: alerta, bloqueo y respuesta identica a "cerrado".
 *  - Reclamo antes de OPG_HLW_SECRET_MIN_SECONDS desde la carga: se rechaza (alerta suave).
 * La respuesta nunca dice al usuario por que se rechazo; 'motivo' solo lo ve quien pruebe (cuentas 315 y 172).
 */
function opg_hlw_secret_claim_token($uid, $token, $now = null)
{
    $uid = (int)$uid;
    $now = $now === null ? microtime(true) : (float)$now;
    $payload = opg_hlw_secret_token_open($token);
    if (!$payload || (int)$payload['u'] !== $uid || $now - (int)$payload['i'] > OPG_HLW_SECRET_TOKEN_TTL || !in_array((int)$payload['d'], array(0, 1), true)) {
        opg_hlw_secret_alert($uid, 'codigo', 'Codigo de visita invalido o caducado');
        return array('estado' => 'closed', 'mensaje' => '', 'motivo' => 'codigo_invalido_o_caducado');
    }
    if ((int)$payload['d'] === 1) {
        opg_hlw_secret_alert($uid, 'senuelo', 'Reclamo del señuelo ' . (string)$payload['s']);
        return array('estado' => 'closed', 'mensaje' => '', 'motivo' => 'senuelo');
    }
    if (opg_hlw_secret_is_blocked($uid)) {
        return array('estado' => 'closed', 'mensaje' => '', 'motivo' => 'bloqueado_por_alerta');
    }
    if ($now - (int)$payload['i'] < OPG_HLW_SECRET_MIN_SECONDS) {
        opg_hlw_secret_alert($uid, 'rapido', 'Reclamo a los ' . round($now - (int)$payload['i'], 2) . ' s de cargar la pagina');
        return array('estado' => 'closed', 'mensaje' => '', 'motivo' => 'demasiado_rapido');
    }
    return opg_hlw_secret_claim($uid, (string)$payload['s']);
}

// ── Lo que ve el navegador: billete, configuracion cifrada y resultados compactos ─────────────────────────────────

/**
 * Resultado para el navegador, en una lista sin nombres de campo:
 *  0 estado, 1 numero, 2 nombre, 3 mensaje, 4 premio, 5 imagen, 6 rotulo superior, 7 titulo, 8 boton, 9 motivo (solo pruebas).
 */
function opg_hlw_secret_client_result(array $result, $withReason = false)
{
    return array(
        (string)($result['estado'] ?? 'closed'), (int)($result['numero'] ?? 0), (string)($result['nombre'] ?? ''), (string)($result['mensaje'] ?? ''),
        (string)($result['premio'] ?? ''), (string)($result['imagen'] ?? ''), (string)($result['kicker'] ?? ''), (string)($result['titulo'] ?? ''),
        (string)($result['boton'] ?? ''), $withReason ? (string)($result['motivo'] ?? '') : '',
    );
}

/** Parametros de la peticion que alguna entrada del catalogo necesita ('cuando'): se guardan en el billete. */
function opg_hlw_secret_condition_params()
{
    $params = array();
    foreach (opg_hlw_secret_catalog() as $secret) {
        foreach (array_keys((array)($secret['cuando'] ?? array())) as $name) {
            if (isset($_GET[$name]) && !is_array($_GET[$name])) {
                $params[$name] = (string)$_GET[$name];
            }
        }
    }
    return $params;
}

/** Billete de esta carga de pagina: opaco para el navegador. Con el se pide despues la configuracion. d = 2 lo distingue de los codigos. */
function opg_hlw_secret_ticket($uid, $page, $file, array $params, $now = null)
{
    return opg_hlw_secret_token_seal(array(
        'u' => (int)$uid, 's' => 't', 'i' => $now === null ? time() : (int)$now, 'd' => 2,
        'p' => (string)$page, 'f' => (string)$file, 'q' => $params,
    ));
}

/**
 * Configuracion para una carga de pagina, a partir del billete: lista de entradas [codigo, selector, enlace, delegado, texto].
 * Devuelve null si el billete no vale (falso, de otro usuario o caducado) y una lista vacia si el usuario no puede descubrir ahora.
 */
function opg_hlw_secret_client_config($uid, $ticket)
{
    $payload = opg_hlw_secret_token_open($ticket);
    if (!$payload || (int)$payload['d'] !== 2 || (int)$payload['u'] !== (int)$uid || time() - (int)$payload['i'] > OPG_HLW_SECRET_TOKEN_TTL
        || !isset($payload['p'], $payload['f'])) {
        return null;
    }
    if (!opg_hlw_secrets_ready() || !opg_hlw_secrets_open(opg_hlw_config(), $uid) || !opg_hlw_eligible_character($uid)) {
        return array();
    }
    $entries = opg_hlw_secret_issue($uid, (string)$payload['p'], opg_hlw_secret_mine($uid), (int)$payload['i'], (array)($payload['q'] ?? array()), (string)$payload['f']);
    return array_map(function ($entry) {
        return array($entry['t'], $entry['s'], $entry['n'], $entry['g'], $entry['x'], $entry['y']);
    }, $entries);
}

/** Cifra la configuracion con una clave derivada del billete (XOR) y la codifica: no se parece a un JSON en base64. */
function opg_hlw_secret_config_encode(array $config, $ticket)
{
    $plain = json_encode($config, JSON_UNESCAPED_UNICODE);
    $key = substr((string)$ticket, 0, 16);
    $out = '';
    for ($i = 0, $n = strlen($plain); $i < $n; $i++) {
        $out .= chr(ord($plain[$i]) ^ ord($key[$i % 16]));
    }
    return opg_hlw_secret_b64($out);
}

function opg_hlw_secret_config_decode($encoded, $ticket)
{
    $raw = base64_decode(strtr((string)$encoded, '-_', '+/'), true);
    if ($raw === false) {
        return null;
    }
    $key = substr((string)$ticket, 0, 16);
    $plain = '';
    for ($i = 0, $n = strlen($raw); $i < $n; $i++) {
        $plain .= chr(ord($raw[$i]) ^ ord($key[$i % 16]));
    }
    $decoded = json_decode($plain, true);
    return is_array($decoded) ? $decoded : null;
}
