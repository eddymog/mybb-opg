<?php
/**
 * Punk Records (RAG): configuración — lista blanca de guías y límites.
 *
 * Sin secretos aquí (esos van en inc/config.php). Ver
 * docs/200_DesignPlan_Asistente.md, secciones 4.4 y 5.2.
 */

// Lista blanca de guías indexables. Cualquier archivo fuera de esta lista se
// ignora aunque exista en op/punkrecords/guias/ (ver 100_Requirements, 4.2).
const PR_GUIAS_PERMITIDAS = [
    '00_introduccion_creacion_personaje.md' => [
        'titulo' => '0. Guía de Introducción y Creación de Personaje',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3728',
    ],
    '01_progreso_personaje.md' => [
        'titulo' => '1. Guía de Progreso de Personaje',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3729',
    ],
    '02_temporalidad_tipos_tema.md' => [
        'titulo' => '2. Guía de Temporalidad y Tipos de Tema',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3733',
    ],
    '03_razas.md' => [
        'titulo' => '3. Guía Razas',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3734',
    ],
    '04_oficios.md' => [
        'titulo' => '4. Guía de Oficios',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3736',
    ],
    '05_reputacion.md' => [
        'titulo' => '5. Guía de Reputación',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3741',
    ],
    '06_facciones.md' => [
        'titulo' => '6. Guía de Facciones',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3742',
    ],
    '07_narradores.md' => [
        'titulo' => '7. Guía de Narradores',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3745',
    ],
    '08_normativa_conductas_sanciones.md' => [
        'titulo' => 'Guía de la Normativa del Foro, Conductas y Sanciones',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3754',
    ],

    // Guías Bélicas (fid 37)
    'b01_guia_belica.md' => [
        'titulo' => '1. Guía Bélica',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3731',
        // Reglas de combate muy interconectadas (bloques de acción, movimiento,
        // interrupción, reflejos acumulados...), a diferencia de un catálogo de
        // ítems independientes. Fragmentar hasta #### (nivel 4, el default)
        // separa reglas que se necesitan entre sí para razonar bien. Con
        // nivel_max=2 se queda solo en ##, agrupando cada bloque temático
        // completo (INICIO DEL COMBATE, FUNDAMENTOS DEL COMBATE...) en fragmentos
        // más grandes pero coherentes.
        'nivel_max' => 2,
    ],
    'b02_estilo_lucha.md' => [
        'titulo' => '2. Guía de Estilo de Lucha',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3730',
    ],
    'b03_akuma_no_mi.md' => [
        'titulo' => '3. Guía de Akuma no Mi',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3732',
    ],
    'b04_haki.md' => [
        'titulo' => '4. Guía de Haki',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3735',
    ],
    'b05_tecnicas.md' => [
        'titulo' => '5. Guía de Técnicas',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3740',
    ],
    'b06_objetos.md' => [
        'titulo' => '6. Guía de Objetos',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3746',
    ],
    'b07_inventario_intercambios.md' => [
        'titulo' => '7. Guía de Inventario e Intercambios',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3747',
    ],
    'b08_cyborgs.md' => [
        'titulo' => '8. Guía de Cyborgs',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3748',
    ],
    'b09_conquistas.md' => [
        'titulo' => '9. Guía de Conquistas',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3752',
    ],

    // Otras Guías (fid 38)
    'o01_viajes.md' => [
        'titulo' => '1. Guía de Viajes',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3737',
    ],
    'o02_barcos_mejoras.md' => [
        'titulo' => '2. Guía de Barcos y Mejoras',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3738',
    ],
    'o03_batallas_navales.md' => [
        'titulo' => '3. Batallas Navales',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3739',
    ],
    'o04_inframundo.md' => [
        'titulo' => '4. Guía de Inframundo',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=6127',
    ],
    'o05_mascotas_npcs.md' => [
        'titulo' => '5. Guía de Mascotas y NPCs',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3744',
    ],
    'o07_multicuentas_resurreccion.md' => [
        'titulo' => '7. Guía de Multicuentas y Resurrección',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3750',
    ],
    'o08_recompensas_especiales.md' => [
        'titulo' => '8. Guía de Recompensas Especiales',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3751',
    ],
    'o09_codigos_rol.md' => [
        'titulo' => '9. Guía de Códigos de Rol',
        'url' => 'https://onepiecegaiden.com/showthread.php?tid=3753',
    ],
];

define('PR_GUIAS_DIR', __DIR__ . '/../punkrecords/guias/');

// Límites (sección 4.4 del diseño completo).
const PR_LLAMADAS_DIA_MAX = 1000;
const PR_LLAMADAS_GEN_DIA_MAX = 450;
const PR_LLAMADAS_EMB_DIA_MAX = 550;
const PR_LLAMADAS_POR_MINUTO_MAX = 10;
const PR_PREGUNTAS_USUARIO_DIA = 100;
const PR_PREGUNTAS_USUARIO_HORA = 30;

// Se probó gemma-4-31b-it (500 consistente, confirmado con curl directo),
// gemma-4-26b-a4b-it (funciona, pero es modelo con razonamiento: gasta
// tokens/tiempo extra en un borrador interno antes de la respuesta real —
// 16.6s medidos con un prompt real, muy cerca del timeout de 20s) y
// gemini-3.1-flash-lite (funciona bien, 5.5s). Vuelto a gemini-3.5-flash-lite:
// es el modelo del que hay cupo gratis diario confirmado en el panel de
// AI Studio (500/día) — 'latest' podía apuntar a otra versión con cupo
// distinto o sin confirmar.
const PR_MODELO_GENERACION = 'gemini-3.5-flash-lite';
const PR_MODELO_EMBEDDING = 'voyage-4'; // antes gemini-embedding-001; migrado por soporte de español documentado
const PR_EMBEDDING_DIMENSIONES = 1024; // dimensión por defecto de voyage-4 (antes 768 con Gemini)

// Memoria de conversación (solo dura lo que dura la pestaña abierta — vive
// en un array de JS en el navegador, nunca se guarda en el servidor; ver
// pr_parsear_historial() y el script del template). Turnos previos que el
// cliente manda de vuelta en cada pregunta, para que el operador entienda
// referencias tipo "y la otra" o "eso" sin tener que repetir todo. Límites
// para no inflar el costo de tokens ni permitir un payload enorme.
const PR_HISTORIAL_TURNOS_MAX = 3;
const PR_HISTORIAL_RESPUESTA_CHARS_MAX = 400;
const PR_HISTORIAL_JSON_CHARS_MAX = 6000;

// Filtro de relevancia mínima (pr_filtrar_fragmentos_relevantes() en
// punkrecords_rag.php): buscar_hibrido() devuelve los k mejores fragmentos
// por POSICIÓN relativa (RRF), no por relevancia real — algo puede "ganar"
// el ranking solo por ser lo menos malo entre lo peor (caso real: la
// pregunta "Hola Teniente Shark!" trajo técnicas de tiburones solo por la
// coincidencia de la palabra, sin relación real con el saludo). Un fragmento
// se conserva si CUALQUIERA de las dos búsquedas lo respalda con fuerza real:
// similitud de embedding alta, o quedó entre los primerísimos puestos de la
// búsqueda por texto (no solo "entró en el top 20").
const PR_SIMILITUD_MINIMA = 0.35;
const PR_RANK_TEXTO_MAXIMO = 3;

/**
 * Log propio de Punk Records, aparte del error_log general de PHP (que
 * depende de una configuración del hosting que no controlamos desde el
 * código). Escribe directo al archivo con error_log($msg, 3, $archivo) —
 * eso NO toca el ini_set('error_log', ...) global, así que no afecta
 * ningún otro log del sitio en la misma petición.
 *
 * op/logs/ ya existe con un .htaccess "Deny from all" (no servible por
 * web); esto agrega el primer archivo que efectivamente escribe ahí.
 */
function pr_log($mensaje)
{
    $archivo = __DIR__ . '/../logs/punkrecords.log';
    $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . "\n";
    @error_log($linea, 3, $archivo);
}
