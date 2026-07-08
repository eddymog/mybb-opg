<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'mercado_negro.php');

global $templates, $mybb, $db;

require_once "./../global.php";
require_once "./functions/op_functions.php";

/* Fuerza UTC en la sesión SQL para evitar desplazamientos */
@$db->query("SET time_zone = '+00:00'");

$uid = $mybb->user['uid'];
$username = $mybb->user['username'];
$accion = $_POST["accion"];
$comprasTotal = 0;
$listaDeCompras = $_POST["listaDeCompras"];
$listaDeComprasKeys = $_POST["listaDeComprasKeys"];
$ficha = null;

// === DEBUG TEMPORAL ===

    // ini_set('display_errors', '1');           // mostrar en pantalla
    // ini_set('display_startup_errors', '1');
    // ini_set('log_errors', '1');               // log a fichero
    // ini_set('error_log', __DIR__.'/mercado_negro.log');  // archivo local
    // error_reporting(E_ALL);

    // set_error_handler(function($errno, $errstr, $errfile, $errline){
    //     error_log("PHP[$errno] $errstr en $errfile:$errline");
    //     return false; // deja que PHP siga su flujo normal
    // });
    // set_exception_handler(function($ex){
    //     error_log("EXCEPTION: ".$ex->getMessage()." @ ".$ex->getFile().":".$ex->getLine()."\n".$ex->getTraceAsString());
    // });
    // register_shutdown_function(function(){
    //     $e = error_get_last();
    //     if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])){
    //         error_log("FATAL: {$e['message']} @ {$e['file']}:{$e['line']}");
    //         // Opcional: mostrar algo legible en pantalla durante debug:
    //         echo "<pre style='white-space:pre-wrap;color:#c00;background:#fee;padding:10px;border:1px solid #caa'>FATAL: {$e['message']} @ {$e['file']}:{$e['line']}</pre>";
    //     }
    // });

// === FIN DEBUG TEMPORAL ===

//================= HELPERS CONTRABANDO =================
// #region HELPERS CONTRABANDO
    // Helper para obtención de objetos con Negro = 1 en base de datos
    function get_objetos_negro($db, $orderBy = "categoria, subcategoria, tier, nombre")
    {
        $objetosMap  = []; // ["OBJID" => [fila1, fila2...], ...]
        $objetosList = []; // ["OBJID", "OBJID2", ...]
        $objetosRows = []; // [fila, fila, ...]

        $orderSql = $orderBy ? " ORDER BY {$orderBy}" : "";

        $query = $db->query("
            SELECT *
            FROM `mybb_op_objetos`
            WHERE `negro` = '1' AND `tier` BETWEEN 1 AND 5
            {$orderSql}
        ");

        while ($row = $db->fetch_array($query)) {
            $objetoId = $row['objeto_id'];

            // Excluir objetos que empiezan por números o terminan con guión y números
            if (preg_match('/^[0-9]/', $objetoId) || preg_match('/-\d+$/', $objetoId)) {
                continue; // Saltar este objeto
            }

            if (!isset($objetosMap[$objetoId])) {
                $objetosMap[$objetoId] = [];
                $objetosList[] = $objetoId;
            }

            $objetosMap[$objetoId][] = $row; // incluye todas las columnas
            $objetosRows[] = $row;
        }

        return [
            'map'  => $objetosMap,
            'list' => $objetosList,
            'rows' => $objetosRows,
        ];
    }

    // Helper para obtener todos los objetos comerciables (para el bazar)
    function get_objetos_comerciables($db, $orderBy = "categoria, subcategoria, tier, nombre")
    {
        $objetosMap  = []; // ["OBJID" => [fila1, fila2...], ...]
        $objetosList = []; // ["OBJID", "OBJID2", ...]
        $objetosRows = []; // [fila, fila, ...]

        $orderSql = $orderBy ? " ORDER BY {$orderBy}" : "";

        $query = $db->query("
            SELECT *
            FROM `mybb_op_objetos`
            WHERE (
                (
                    `comerciable` = '0' 
                    AND `negro` = '1'
                )
                OR `objeto_id` REGEXP '^[0-9]'
            )
            {$orderSql}
        ");

        while ($row = $db->fetch_array($query)) {
            $objetoId = $row['objeto_id'];

            if (!isset($objetosMap[$objetoId])) {
                $objetosMap[$objetoId] = [];
                $objetosList[] = $objetoId;
            }

            $objetosMap[$objetoId][] = $row; // incluye todas las columnas
            $objetosRows[] = $row;
        }

        return [
            'map'  => $objetosMap,
            'list' => $objetosList,
            'rows' => $objetosRows,
        ];
    }

    // Helper para clasificación de los items en tiers hasta el 5
    function clasificar_objetos_por_tier(array $objetosMap)
    {
        $tiers = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => [],
        ];

        foreach ($objetosMap as $objetoId => $rows) {
            foreach ($rows as $row) {
                $tier = (int)$row['tier'];
                if (isset($tiers[$tier])) {
                    $tiers[$tier][] = $row;
                }
            }
        }

        return $tiers;
    }

    // Helper que devuelve un identificador ISO de semana
    function current_week_key(): string
    {
        $dt = new DateTime('now', new DateTimeZone('Europe/Madrid')); // <-- TZ local
        $year = $dt->format('o'); // año ISO
        $week = $dt->format('W'); // semana ISO (01-53)
        return "{$year}-W{$week}";
    }

    // Helper que deduplica por objeto_id
    // #region BORRAR SI REALMENTE NO SE TIENE QUE USAR
        function normalize_unique_rows(array $rows): array
        {
            $byId = [];
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $oid = $row['objeto_id'] ?? null;
                if (!$oid) continue;
                // guarda una fila representativa por objeto_id
                if (!isset($byId[$oid])) {
                    $byId[$oid] = $row;
                }
            }
            return array_values($byId);
        }
    // #endregion BORRAR SI REALMENTE NO SE TIENE QUE USAR

    // Helper que forma la pool semanal
    function get_or_build_weekly_pool(array $objetos_por_tier, bool $force = false): array
    {
        global $cache;

        $cache_key = 'op_pool_semana';
        $this_week = current_week_key();

        if (!$force) {
            $cached = $cache->read($cache_key);
            if (is_array($cached) && ($cached['week'] ?? null) === $this_week) {
                return $cached; // ← estable toda la semana
            }
        }

        $pool = [
            'week'         => $this_week,
            'generated_at' => time(),
            'tiers'        => [1 => [], 2 => [], 3 => [], 4 => [], 5 => []],
            'blocks'       => [],        // ← NUEVO: guardamos los bloques
            'block_ids'    => [],        // ← opcional: sólo IDs para el front
        ];

        $T1 = array_values($objetos_por_tier[1] ?? []);
        $T2 = array_values($objetos_por_tier[2] ?? []);
        $T3 = array_values($objetos_por_tier[3] ?? []);
        $T4 = array_values($objetos_por_tier[4] ?? []);
        $T5 = array_values($objetos_por_tier[5] ?? []);

        $used = [];

        // Selección semanal (una sola vez)
        $b1 = blend_random_multi([$T1],             4, $used);
        $b2 = blend_random_multi([$T1, $T2],        4, $used);
        $b3 = blend_random_multi([$T1, $T2, $T3],   4, $used);
        $b4 = blend_random_multi([$T2, $T3, $T4],   4, $used);
        $b5 = blend_random_multi([$T4, $T5],        4, $used);

        // Guardar bloques tal cual para uso posterior
        $pool['blocks'] = [
            1 => $b1, // Alimaña
            2 => $b2, // Operativo
            3 => $b3, // Capo
            4 => $b4, // Broker
            5 => $b5, // Broker Estrella / Emperador
        ];
        // También IDs (útil si quieres enviar arrays de IDs al front)
        foreach ($pool['blocks'] as $k => $block) {
            $pool['block_ids'][$k] = array_values(array_unique(array_map(
                fn($row) => $row['objeto_id'] ?? null,
                $block
            )));
        }

        // Además, rellenamos por tiers (si lo sigues usando en el front)
        foreach (array_merge($b1, $b2, $b3, $b4, $b5) as $row) {
            $t = (int)($row['tier'] ?? 0);
            if ($t >= 1 && $t <= 5) {
                $pool['tiers'][$t][] = $row;
            }
        }

        $cache->update($cache_key, $pool);
        return $pool;
    }

    // Helper que normaliza los rangos del inframundo a valor numérico 0..5 (consultar con los del inframundo luego)
    function rango_to_level(string $rango): int
    {
        $map = [
            '' => 0, 'sin rango' => 0,
            'alimaña' => 1,
            'operativo' => 2,
            'capo' => 3,
            'broker' => 4,
            'broker estrella' => 5,
            'emperador' => 5,
            'emperador del inframundo' => 5,
        ];
        $key = mb_strtolower(trim($rango), 'UTF-8');
        return $map[$key] ?? 0;
    }

    // Helper que convierte el nivel de sub-especialización Contrabandista al nivel efectivo del inframundo
    // Contrabandista rango 1 (sub nivel 1) = Capo (nivel 3)
    // Contrabandista rango 2 (sub nivel 2) = Broker (nivel 4)
    // Contrabandista rango 3 (sub nivel 3) = Broker Estrella/Emperador (nivel 5)
    function contrabandista_to_level(int $sub_nivel): int
    {
        if ($sub_nivel <= 0) return 0;
        if ($sub_nivel == 1) return 3; // Capo
        if ($sub_nivel == 2) return 4; // Broker
        return 5;                      // Broker Estrella / Emperador
    }

    // Helper de mezcla de arrays para los distintos rangos de inframundo
    function blend_random_multi(array $sources, int $take, array &$used, ?array $weights = null): array
    {
        // 1) Normaliza y deduplica por objeto_id dentro de cada fuente
        $pools = []; // idx => [rows...]
        foreach ($sources as $idx => $src) {
            $src = is_array($src) ? array_values($src) : [];
            if (!$src) continue;
            $seen = [];
            $norm = [];
            foreach ($src as $row) {
                if (!is_array($row)) continue;
                $oid = $row['objeto_id'] ?? null;
                if (!$oid || isset($seen[$oid])) continue;
                $seen[$oid] = true;
                $norm[] = $row;
            }
            if ($norm) $pools[$idx] = $norm;
        }
        if (!$pools || $take <= 0) return [];

        // 2) Activos + pesos
        $active = array_keys($pools);

        // Pesos por fuente (por defecto iguales)
        if ($weights === null) {
            $weights = [];
            foreach ($active as $i) $weights[$i] = 1;
        } else {
            // Mantén sólo claves activas
            $w = [];
            foreach ($active as $i) $w[$i] = (float)($weights[$i] ?? 1);
            $weights = $w;
        }
        $normalize = function(array $w) {
            $sum = array_sum($w);
            if ($sum <= 0) {
                $n = count($w);
                foreach ($w as $k => $_) $w[$k] = 1.0 / $n;
            } else {
                foreach ($w as $k => $v) $w[$k] = $v / $sum;
            }
            return $w;
        };
        $weights = $normalize($weights);

        $pickIdx = function(array $w) {
            $r = mt_rand() / mt_getrandmax();
            $acc = 0.0;
            foreach ($w as $i => $p) {
                $acc += $p;
                if ($r <= $acc) return $i;
            }
            end($w);
            return key($w); // fallback por precisión
        };

        // 3) Bucle de selección
        $out = [];
        while (count($out) < $take && $active) {
            $srcIdx = $pickIdx($weights);

            // Si se agotó esa fuente, elimínala y re-normaliza
            if (empty($pools[$srcIdx])) {
                unset($pools[$srcIdx], $weights[$srcIdx]);
                $active = array_keys($pools);
                if ($active) $weights = $normalize($weights);
                continue;
            }

            // Elige un elemento aleatorio de esa fuente
            $k = array_rand($pools[$srcIdx]);
            $candidate = $pools[$srcIdx][$k];
            unset($pools[$srcIdx][$k]); // quítalo del pool para no repetir

            $oid = $candidate['objeto_id'] ?? null;
            if (!$oid || isset($used[$oid])) {
                // Duplicado o inválido: sigue intentándolo en el siguiente ciclo
                continue;
            }

            $used[$oid] = true;
            $out[] = $candidate;
        }

        return $out;
    }

    // Helper que construye la visión final del usuario
    function build_user_visible_pool(array $pool_semanal, string $rango_inframundo, int $nivel_override = 0): array
    {
        $lvl = max(rango_to_level($rango_inframundo), $nivel_override);
        // Sin rango → nada
        if ($lvl <= 0) return ['items' => []];

        // Si hay bloques, usar SÓLO lo ya elegido semanalmente
        $blocks = $pool_semanal['blocks'] ?? null;

        if (is_array($blocks) && $blocks) {
            // Mapa de nivel → qué bloques incluye
            // Alimaña => b1
            // Operativo => b1+b2
            // Capo => b1+b2+b3
            // Broker => b1+b2+b3+b4
            // Broker Estrella/Emperador => b1+b2+b3+b4+b5
            $take_up_to = min($lvl, 5);
            $items = [];
            for ($i = 1; $i <= $take_up_to; $i++) {
                foreach ($blocks[$i] as $row) {
                    $items[] = $row;
                }
            }
            return ['items' => $items];
        }

        // Fallback: si por algún motivo no hay blocks (no debería pasar),
        // devolvemos comportamiento determinista: tomar secuencialmente de tiers.
        $tiers = $pool_semanal['tiers'] ?? [1=>[],2=>[],3=>[],4=>[],5=>[]];
        for ($t=1;$t<=5;$t++) $tiers[$t] = array_values($tiers[$t] ?? []);
        $used = [];
        $take_from = function(array $src, int $n) use (&$used) {
            $out = [];
            foreach ($src as $row) {
                $oid = $row['objeto_id'] ?? null;
                if (!$oid || isset($used[$oid])) continue;
                $used[$oid] = true;
                $out[] = $row;
                if (count($out) >= $n) break;
            }
            return $out;
        };

        $result = [];
        if ($lvl >= 1) $result = array_merge($result, $take_from($tiers[1], 4));
        if ($lvl >= 2) $result = array_merge($result, $take_from(array_merge($tiers[1], $tiers[2]), 4));
        if ($lvl >= 3) $result = array_merge($result, $take_from(array_merge($tiers[1], $tiers[2], $tiers[3]), 4));
        if ($lvl >= 4) $result = array_merge($result, $take_from(array_merge($tiers[2], $tiers[3], $tiers[4]), 4));
        if ($lvl >= 5) $result = array_merge($result, $take_from(array_merge($tiers[4], $tiers[5]), 4));

        return ['items' => $result];
    }
// #endregion HELPERS CONTRABANDO
//================= FIN HELPERS CONTRABANDO =================

//================= HELPER CUENTAS VINCULADAS =================
/**
 * Verifica si dos usuarios son cuentas vinculadas (account switcher)
 * El plugin Account Switcher usa el campo as_uid en mybb_users:
 * - as_uid = 0: Cuenta maestra o sin vincular
 * - as_uid > 0: Cuenta secundaria (el valor es el UID de la cuenta maestra)
 * 
 * @param object $db Database object
 * @param int $uid1 Primer usuario
 * @param int $uid2 Segundo usuario
 * @return bool True si son cuentas vinculadas
 */
function son_cuentas_vinculadas($db, int $uid1, int $uid2): bool {
    if ($uid1 === $uid2) return true;
    
    // Verificar si existe el campo as_uid en la tabla users
    if (!$db->field_exists('as_uid', 'users')) {
        error_log("[MERCADO NEGRO] Campo as_uid no existe - plugin Account Switcher no instalado o versión incorrecta");
        return false; // Si no existe el campo, permitir la transacción
    }
    
    // Obtener los valores de as_uid para ambos usuarios
    $query1 = $db->simple_select('users', 'as_uid', "uid={$uid1}");
    $user1 = $db->fetch_array($query1);
    
    $query2 = $db->simple_select('users', 'as_uid', "uid={$uid2}");
    $user2 = $db->fetch_array($query2);
    
    if (!$user1 || !$user2) {
        error_log("[MERCADO NEGRO] No se pudieron obtener datos de usuarios {$uid1} o {$uid2}");
        return false;
    }
    
    $as_uid1 = (int)($user1['as_uid'] ?? 0);
    $as_uid2 = (int)($user2['as_uid'] ?? 0);
    
    // Determinar la cuenta maestra de cada usuario
    // Si as_uid = 0, el usuario es su propia cuenta maestra
    $master1 = ($as_uid1 === 0) ? $uid1 : $as_uid1;
    $master2 = ($as_uid2 === 0) ? $uid2 : $as_uid2;
    
    // Son vinculadas si comparten la misma cuenta maestra
    $son_vinculadas = ($master1 === $master2);
    
    if ($son_vinculadas) {
        error_log("[MERCADO NEGRO] Cuentas vinculadas detectadas: UID{$uid1} (master:{$master1}) y UID{$uid2} (master:{$master2})");
    }
    
    return $son_vinculadas;
}
//================= FIN HELPER CUENTAS VINCULADAS =================

//================= HELPERS BAZAR =================
// #region HELPERS BAZAR

    // Helper que parsea la fecha correctamente
    function parse_utc_mysql_datetime(?string $s) {
        if (!$s) return false;
        $s = trim($s);
        if ($s === '' || $s === '0000-00-00 00:00:00' || $s === 'null' || $s === '0') return false;
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $s, new DateTimeZone('UTC'));
        if (!$dt) return false;
        return $dt; // DateTime en UTC
    }

    // Helper que obtiene el inventario del usuario
    function get_user_inventory($db, int $uid): array
    {
        $uid = (int)$uid;
        $inv = $db->table_prefix.'op_inventario';
        $obj = $db->table_prefix.'op_objetos';


        // Queremos obtener los objetos TRADEABLES y DEL MERCADO NEGRO, los demás no.
        // Puedo entender que las creaciones únicas no tengan el campo de tradeable o de mercado negro, 
        // pero para eso buscamos una solución alternativa
        $sql = "
            SELECT 
                inv.id              AS inv_id,
                inv.objeto_id       AS inv_objeto_id,
                inv.uid             AS inv_uid,
                inv.cantidad        AS inv_cantidad,
                inv.tiempo          AS inv_tiempo,
                inv.imagen          AS inv_imagen,
                inv.apodo           AS inv_apodo,
                inv.autor           AS inv_autor,
                inv.autor_uid       AS inv_autor_uid,
                inv.oficios         AS inv_oficios,
                inv.especial        AS inv_especial,
                inv.editado         AS inv_editado,
                inv.usado           AS inv_usado,

                obj.objeto_id       AS obj_objeto_id,
                obj.nombre          AS obj_nombre,
                obj.descripcion     AS obj_descripcion,
                obj.categoria       AS obj_categoria,
                obj.subcategoria    AS obj_subcategoria,
                obj.tier            AS obj_tier,
                obj.imagen_id       AS obj_imagen_id,
                obj.imagen_avatar   AS obj_imagen_avatar,
                obj.espacios        AS obj_espacios,
                obj.negro           AS obj_negro,
                obj.negro_berries   AS obj_negro_berries,
                obj.comerciable     AS obj_comerciable
            FROM {$inv} inv
            INNER JOIN {$obj} obj 
                ON obj.objeto_id = inv.objeto_id
            WHERE inv.uid = {$uid}
                AND (
                    (
                        obj.comerciable = 0
                        AND obj.negro = 1
                    )
                    OR obj.objeto_id REGEXP '^[0-9]'   
                )
            ORDER BY inv.tiempo DESC, inv.id DESC
        ";

        $res = $db->query($sql);
        $items = [];
        while ($row = $db->fetch_array($res)) {
            $items[] = [
                'inv' => [
                    'id'        => (int)$row['inv_id'],
                    'objeto_id' => $row['inv_objeto_id'],
                    'uid'       => (int)$row['inv_uid'],
                    'cantidad'  => (int)$row['inv_cantidad'],
                    'tiempo'    => $row['inv_tiempo'],
                    'imagen'    => $row['inv_imagen'],
                    'apodo'     => $row['inv_apodo'],
                    'autor'     => $row['inv_autor'],
                    'autor_uid' => $row['inv_autor_uid'],
                    'oficios'   => $row['inv_oficios'],
                    'especial'  => (int)$row['inv_especial'],
                    'editado'   => (int)$row['inv_editado'],
                    'usado'     => (int)$row['inv_usado'],
                ],
                'obj' => [
                    'objeto_id'     => $row['obj_objeto_id'],
                    'nombre'        => $row['obj_nombre'],
                    'descripcion'   => $row['obj_descripcion'],
                    'categoria'     => $row['obj_categoria'],
                    'subcategoria'  => $row['obj_subcategoria'],
                    'tier'          => $row['obj_tier'] !== null ? (int)$row['obj_tier'] : null,
                    'imagen_id'     => $row['obj_imagen_id'],
                    'imagen_avatar' => $row['obj_imagen_avatar'],
                    'espacios'      => $row['obj_espacios'],
                    'negro'         => $row['obj_negro'] !== null ? (int)$row['obj_negro'] : null,
                    'negro_berries' => $row['obj_negro_berries'] !== null ? (int)$row['obj_negro_berries'] : null,
                    'comerciable'   => (int)$row['obj_comerciable'],
                ],
            ];
        }
        return $items;
    }

    // Helper de listing en el inframundo con validación de existencia y viabilidad
    function crear_listing_inframundo(
        $db,
        int $vendedor_uid,
        string $objeto_id,
        int $precio_minimo,
        ?int $precio_compra,
        string $fecha_final_subasta, // 'Y-m-d H:i:s' (UTC desde el front)
        ?string $notas,
        int $cantidad
    ) {
        // Tablas
        $tabla_market = 'mybb_Objetos_Inframundo';
        $tabla_inv    = 'mybb_op_inventario';
        $tabla_objs   = 'mybb_op_objetos';

        // Validaciones básicas
        if ($cantidad <= 0) return ['ok'=>false,'id'=>null,'error'=>'Cantidad inválida'];
        if ($precio_minimo < 0) return ['ok'=>false,'id'=>null,'error'=>'Precio mínimo inválido'];
        if (!empty($precio_compra) && $precio_compra < $precio_minimo) {
            return ['ok'=>false,'id'=>null,'error'=>'El precio de compra no puede ser menor que el precio mínimo'];
        }

        // Nota: La validación de cuentas vinculadas se hace al momento de comprar/pujar,
        // no al crear el listing, ya que no sabemos quién comprará

        // Valida objeto y comerciabilidad
        $resObj = $db->query("
            SELECT objeto_id, comerciable
            FROM {$tabla_objs}
            WHERE objeto_id = '".$db->escape_string($objeto_id)."'
            LIMIT 1
        ");
        $rowObj = $db->fetch_array($resObj);
        if (!$rowObj) return ['ok'=>false,'id'=>null,'error'=>'Objeto inexistente'];
        if ((int)$rowObj['comerciable'] == 1) return ['ok'=>false,'id'=>null,'error'=>'Este objeto no es comerciable'];

        // === Preparar fecha final (UTC) y precio_actual inicial ===
        $fecha_final_sql   = 'NULL';
        $precio_actual_sql = (int)$precio_minimo; // por defecto

        if (!empty($fecha_final_subasta)) {
            // El front envía 'Y-m-d H:i:s' en UTC
            $dt_fin = parse_utc_mysql_datetime($fecha_final_subasta); // ← devuelve DateTime en UTC o false
            if (!$dt_fin) {
                return ['ok'=>false,'id'=>null,'error'=>'Fecha final de subasta inválida'];
            }
            if ($dt_fin->getTimestamp() <= time()) {
                return ['ok'=>false,'id'=>null,'error'=>'Fecha final de subasta en el pasado'];
            }
            // Guardar exactamente ese instante en UTC
            $fecha_final_sql   = "'".$db->escape_string($dt_fin->format('Y-m-d H:i:s'))."'";
            $precio_actual_sql = (int)$precio_minimo; // inicio de puja
            error_log("INSERT SUBASTA UTC: fecha_final_sql={$fecha_final_sql}, precio_actual={$precio_actual_sql}");
        } else {
            // Venta directa: precio_actual será el fijo de venta
            $precio_actual_sql = $precio_compra !== null ? (int)$precio_compra : (int)$precio_minimo;
            error_log("INSERT VENTA DIRECTA: fecha_final_sql=NULL, precio_actual={$precio_actual_sql}");
        }
        
        // === Generar timestamp disponible_desde con delay aleatorio de 10-20 minutos ===
        $delay_minutos = rand(10, 20);
        $disponible_desde_ts = time() + ($delay_minutos * 60);
        $disponible_desde_dt = new DateTime('@' . $disponible_desde_ts, new DateTimeZone('UTC'));
        $disponible_desde_sql = "'" . $db->escape_string($disponible_desde_dt->format('Y-m-d H:i:s')) . "'";
        error_log("LISTING: disponible_desde en {$delay_minutos} minutos: {$disponible_desde_sql}");

        // === INICIO TRANSACCIÓN ===
        $db->write_query("START TRANSACTION");
        try {
            // 1) Stock total del usuario para ese objeto
            $resInv = $db->query("
                SELECT SUM(cantidad) AS total
                FROM {$tabla_inv}
                WHERE uid = ".(int)$vendedor_uid."
                  AND objeto_id = '".$db->escape_string($objeto_id)."'
                FOR UPDATE
            ");
            $rowInv   = $db->fetch_array($resInv);
            $qtyTotal = (int)($rowInv['total'] ?? 0);
            if ($qtyTotal < $cantidad) {
                $db->write_query("ROLLBACK");
                return ['ok'=>false,'id'=>null,'error'=>'No tienes suficiente cantidad en el inventario'];
            }

            // 1.1) Verificar cooldown de vendidoReciente (1 mes = 30 días)
            $cooldown_seconds = 30 * 24 * 60 * 60; // 30 días en segundos
            $resCheck = $db->query("
                SELECT id, cantidad, vendidoReciente
                FROM {$tabla_inv}
                WHERE uid = ".(int)$vendedor_uid."
                  AND objeto_id = '".$db->escape_string($objeto_id)."'
                  AND vendidoReciente IS NOT NULL
                  AND UNIX_TIMESTAMP(vendidoReciente) > UNIX_TIMESTAMP(NOW()) - {$cooldown_seconds}
                LIMIT 1
            ");
            if ($db->num_rows($resCheck) > 0) {
                $blocked = $db->fetch_array($resCheck);
                $vendido_ts = strtotime($blocked['vendidoReciente']);
                $disponible_ts = $vendido_ts + $cooldown_seconds;
                $dias_restantes = ceil(($disponible_ts - time()) / (24 * 60 * 60));
                $db->write_query("ROLLBACK");
                error_log("[BAZAR] Cooldown activo para {$objeto_id} del usuario {$vendedor_uid}. Días restantes: {$dias_restantes}");
                return ['ok'=>false,'id'=>null,'error'=>"Este objeto fue comprado recientemente en el bazar. Podrás venderlo nuevamente en {$dias_restantes} día(s)."];
            }

            // 2) Descuento FIFO del inventario (tiempo ASC, id ASC)
            $toReserve = (int)$cantidad;
            $resLotes = $db->query("
                SELECT id, cantidad
                FROM {$tabla_inv}
                WHERE uid = ".(int)$vendedor_uid."
                  AND objeto_id = '".$db->escape_string($objeto_id)."'
                ORDER BY tiempo ASC, id ASC
                FOR UPDATE
            ");

            while ($toReserve > 0 && ($lote = $db->fetch_array($resLotes))) {
                $loteId  = (int)$lote['id'];
                $loteQty = (int)$lote['cantidad'];
                if ($loteQty <= 0) continue;

                if ($loteQty > $toReserve) {
                    $nuevo = $loteQty - $toReserve;
                    $db->write_query("
                        UPDATE {$tabla_inv}
                        SET cantidad = {$nuevo}
                        WHERE id = {$loteId}
                        LIMIT 1
                    ");
                    $toReserve = 0;
                } else {
                    $db->write_query("
                        DELETE FROM {$tabla_inv}
                        WHERE id = {$loteId}
                        LIMIT 1
                    ");
                    $toReserve -= $loteQty;
                }
            }

            if ($toReserve > 0) {
                $db->write_query("ROLLBACK");
                return ['ok'=>false,'id'=>null,'error'=>'No fue posible reservar el stock (concurrencia)'];
            }

            // 3) Insert del anuncio con disponible_desde (delay 10-20 minutos)
            $now               = gmdate('Y-m-d H:i:s'); // UTC
            $objeto_id_sql     = $db->escape_string($objeto_id);
            $notas_sql         = $notas !== null ? "'".$db->escape_string($notas)."'" : "NULL";
            $precio_compra_sql = $precio_compra !== null ? (int)$precio_compra : "NULL";
            
            // Generar timestamp disponible_desde con delay aleatorio de 60-480 minutos
            $delay_minutos = rand(60, 480);
            $disponible_desde_ts = time() + ($delay_minutos * 60);
            $disponible_desde_dt = new DateTime('@' . $disponible_desde_ts, new DateTimeZone('UTC'));
            $disponible_desde = $disponible_desde_dt->format('Y-m-d H:i:s');
            error_log("[BAZAR] Listing creado, disponible en {$delay_minutos} minutos: {$disponible_desde}");

            $db->write_query("
                INSERT INTO {$tabla_market} (
                    objeto_id, vendedor_uid, ultimo_ofertante_uid, comprador_uid,
                    precio_minimo, precio_actual, precio_compra,
                    fecha_final_subasta, disponible_desde, estado, notas, cantidad, fecha_creacion
                ) VALUES (
                    '{$objeto_id_sql}', ".(int)$vendedor_uid.", NULL, NULL,
                    ".(int)$precio_minimo.", {$precio_actual_sql}, {$precio_compra_sql},
                    {$fecha_final_sql}, '{$disponible_desde}', 'activa', {$notas_sql}, ".(int)$cantidad.", '{$now}'
                )
            ");
            $newId = (int)$db->insert_id();

            $db->write_query("COMMIT");
            return ['ok'=>true,'id'=>$newId,'error'=>null];

        } catch (Throwable $e) {
            $db->write_query("ROLLBACK");
            error_log("Error en crear_listing_inframundo: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
            return ['ok'=>false,'id'=>null,'error'=>'Error al crear el anuncio: '.$e->getMessage()];
        }
    }

    // Helper que obtiene los listings activos del inframundo
    // Si se proporciona un $usuario_uid, el vendedor verá todos sus listings inmediatamente
    function get_listings_activos_inframundo($db, $usuario_uid = 0): array
    {
        $tabla_market = 'mybb_Objetos_Inframundo';
        $tabla_objs   = 'mybb_op_objetos';

        $query = $db->query("
            SELECT m.id, m.objeto_id, m.vendedor_uid, m.ultimo_ofertante_uid, m.comprador_uid,
                   m.precio_minimo, m.precio_actual, m.precio_compra, m.estado, m.notas, m.cantidad,
                   m.fecha_creacion, m.disponible_desde,
                   CASE 
                       WHEN m.fecha_final_subasta IS NULL THEN NULL
                       ELSE m.fecha_final_subasta 
                   END as fecha_final_subasta,
                   o.nombre, o.tier, o.categoria, o.subcategoria, o.comerciable, o.negro_berries
            FROM {$tabla_market} AS m
            INNER JOIN {$tabla_objs} AS o ON o.objeto_id = m.objeto_id
            WHERE m.estado = 'activa'
              AND (
                  m.disponible_desde IS NULL 
                  OR m.disponible_desde <= NOW()
                  OR m.vendedor_uid = ".(int)$usuario_uid."
              )
            ORDER BY 
                CASE 
                    WHEN m.fecha_final_subasta IS NULL THEN 0 
                    ELSE 1 
                END,
                m.fecha_final_subasta ASC
        ");

        $listings = [];
        while ($row = $db->fetch_array($query)) {
            $listings[] = $row;
        }
        return $listings;
    }

    // Helper para manejar las pujas con retención de berries
    function pujar_con_descuento_berries($db, int $listing_id, int $bidder_uid, int $monto): array
    {
        error_log("=== INICIO PUJAR_CON_DESCUENTO_BERRIES ===");
        error_log("listing_id: {$listing_id}, bidder_uid: {$bidder_uid}, monto: {$monto}");
        
        $tabla_market = 'mybb_Objetos_Inframundo';
        $tabla_fichas = 'mybb_op_fichas';

        // Iniciar transacción
        $db->query("START TRANSACTION");

        try {
            // 1) Lock del listing
            $listing_q = $db->query("
                SELECT id, objeto_id, vendedor_uid, ultimo_ofertante_uid, precio_minimo, precio_actual,
                    estado, fecha_final_subasta, cantidad
                FROM {$tabla_market}
                WHERE id = '".(int)$listing_id."'
                FOR UPDATE
            ");
            $listing = $db->fetch_array($listing_q);

            if (!$listing) {
                $db->query("ROLLBACK");
                return ['success' => false, 'message' => 'El anuncio no existe.', 'data' => null];
            }
            if ($listing['estado'] !== 'activa') {
                $db->query("ROLLBACK");
                return ['success' => false, 'message' => 'El anuncio no está activo.', 'data' => $listing];
            }
            
            // VALIDACIÓN: Verificar que no sean cuentas vinculadas
            $vendedor_uid = (int)$listing['vendedor_uid'];
            if (son_cuentas_vinculadas($db, $vendedor_uid, $bidder_uid)) {
                $db->query("ROLLBACK");
                error_log("Puja rechazada: cuentas vinculadas (vendedor: {$vendedor_uid}, comprador: {$bidder_uid})");
                return ['success' => false, 'message' => 'No puedes pujar por objetos de tus cuentas vinculadas.', 'data' => null];
            }
            
            $end_ts = null;
            if (!empty($listing['fecha_final_subasta'])) {
                $dtUTC = DateTime::createFromFormat('Y-m-d H:i:s', $listing['fecha_final_subasta'], new DateTimeZone('UTC'));
                if ($dtUTC) $end_ts = $dtUTC->getTimestamp();
            }
            if ($end_ts !== null && time() >= $end_ts) {
                $db->query("ROLLBACK");
                return ['success' => false, 'message' => 'La subasta ya ha finalizado.', 'data' => $listing];
            }
            if ((int)$listing['vendedor_uid'] === (int)$bidder_uid) {
                $db->query("ROLLBACK");
                return ['success' => false, 'message' => 'No puedes pujar en tu propio anuncio.', 'data' => $listing];
            }

            $precio_minimo = (int)$listing['precio_minimo'];
            $precio_actual = is_null($listing['precio_actual']) ? null : (int)$listing['precio_actual'];

            // Si no hay pujas aún, permite >= mínimo
            if ($precio_actual === null) {
                if ($monto < $precio_minimo) {
                    $db->query("ROLLBACK");
                    return ['success' => false, 'message' => 'La primera puja debe ser mayor o igual que el precio mínimo.', 'data' => $listing];
                }
            } else {
                if ($monto <= $precio_actual) {
                    $db->query("ROLLBACK");
                    return ['success' => false, 'message' => 'La puja debe ser mayor que la puja actual.', 'data' => $listing];
                }
            }

            // Evitar auto-sobrepuja
            if (!empty($listing['ultimo_ofertante_uid']) && (int)$listing['ultimo_ofertante_uid'] === $bidder_uid) {
                $db->query("ROLLBACK");
                return ['success' => false, 'message' => 'Ya eres el último ofertante.', 'data' => $listing];
            }

            // 2) Lock ficha del pujador y verificar saldo
            $ficha_bidder_q = $db->query("
                SELECT fid, berries
                FROM {$tabla_fichas}
                WHERE fid = '".(int)$bidder_uid."'
                FOR UPDATE
            ");
            $ficha_bidder = $db->fetch_array($ficha_bidder_q);
            if (!$ficha_bidder) {
                $db->query("ROLLBACK");
                return ['success' => false, 'message' => 'No se encontró la ficha del pujador.', 'data' => null];
            }

            $berries_bidder = (int)$ficha_bidder['berries'];
            if ($berries_bidder < $monto) {
                $db->query("ROLLBACK");
                return ['success' => false, 'message' => 'No tienes berries suficientes para esta puja.', 'data' => $listing];
            }

            // 3) Si había anterior ofertante, reembolsar
            if ($precio_actual !== null && !empty($listing['ultimo_ofertante_uid'])) {
                $prev_uid = (int)$listing['ultimo_ofertante_uid'];
                // Lock ficha anterior ofertante
                $ficha_prev_q = $db->query("
                    SELECT fid, berries
                    FROM {$tabla_fichas}
                    WHERE fid = '{$prev_uid}'
                    FOR UPDATE
                ");
                $ficha_prev = $db->fetch_array($ficha_prev_q);
                if ($ficha_prev) {
                    $nuevo_prev_berries = (int)$ficha_prev['berries'] + $precio_actual;
                    $db->query("
                        UPDATE {$tabla_fichas}
                        SET berries = '{$nuevo_prev_berries}'
                        WHERE fid = '{$prev_uid}'
                        LIMIT 1
                    ");
                }
            }

            // 4) Descontar al pujador
            $nuevo_berries_bidder = $berries_bidder - $monto;
            $db->query("
                UPDATE {$tabla_fichas}
                SET berries = '{$nuevo_berries_bidder}'
                WHERE fid = '".(int)$bidder_uid."'
                LIMIT 1
            ");

            // 5) Actualizar listing con la nueva puja
            $db->query("
                UPDATE {$tabla_market}
                SET precio_actual = '{$monto}',
                    ultimo_ofertante_uid = '".(int)$bidder_uid."'
                WHERE id = '".(int)$listing_id."'
                LIMIT 1
            ");

            // 6) Commit y devolver estado actualizado
            $db->query("COMMIT");

            $fresh_q = $db->query("
                SELECT id, objeto_id, vendedor_uid, ultimo_ofertante_uid, precio_minimo, precio_actual,
                    estado, fecha_final_subasta, cantidad
                FROM {$tabla_market}
                WHERE id = '".(int)$listing_id."'
                LIMIT 1
            ");
            $fresh = $db->fetch_array($fresh_q);
            
            // Log de debug post-puja
            error_log("POST-PUJA - Listing ID: {$listing_id}");
            error_log("POST-PUJA - fecha_final_subasta: " . ($fresh['fecha_final_subasta'] ?? 'NULL'));
            error_log("POST-PUJA - precio_actual: " . ($fresh['precio_actual'] ?? 'NULL'));
            error_log("POST-PUJA - estado: " . ($fresh['estado'] ?? 'NULL'));

            // auditar movimiento de berries
            if (function_exists('log_audit_currency')) {
                log_audit_currency($bidder_uid, '[Puja Inframundo]', $bidder_uid, '[Puja][Berries]', 'berries', $nuevo_berries_bidder);
            }

            return [
                'success' => true,
                'message' => 'Puja registrada y berries descontados correctamente.',
                'data'    => $fresh
            ];

        } catch (Throwable $e) {
            $db->query("ROLLBACK");
            return ['success' => false, 'message' => 'Error interno al procesar la puja.', 'data' => ['error' => $e->getMessage()]];
        }
    }

    // Helper para finalizar subastas
    // #region PENDIENTE DE CREARLE UN POST PARA USAR
        function finalizar_subasta($db, int $listing_id): array
        {
            $tabla_market = 'mybb_Objetos_Inframundo';
            $tabla_fichas = 'mybb_op_fichas';
            $tabla_inv    = 'mybb_op_inventario';

            $db->query("START TRANSACTION");
            try {
                // 1) Lock del listing
                $q = $db->query("
                    SELECT id, objeto_id, vendedor_uid, ultimo_ofertante_uid, comprador_uid,
                           precio_minimo, precio_actual, precio_compra,
                           fecha_final_subasta, estado, cantidad
                    FROM {$tabla_market}
                    WHERE id = '".(int)$listing_id."'
                    FOR UPDATE
                ");
                $listing = $db->fetch_array($q);
                if (!$listing) throw new Exception('Anuncio inexistente');

                if ($listing['estado'] !== 'activa') {
                    throw new Exception('La subasta ya no está activa.');
                }

                // 2) Comprobar que YA venció (UTC)
                $dt_end = parse_utc_mysql_datetime($listing['fecha_final_subasta']);
                $end_ts = $dt_end ? $dt_end->getTimestamp() : false;
                if ($end_ts === false || time() < $end_ts) {
                    throw new Exception('La subasta aún no ha vencido.');
                }

                $objeto_id_sql = $db->escape_string($listing['objeto_id']);
                $vendedor_uid  = (int)$listing['vendedor_uid'];
                $cantidad      = (int)$listing['cantidad'];
                $precio_actual = $listing['precio_actual'] !== null ? (int)$listing['precio_actual'] : null;
                $ganador_uid   = !empty($listing['ultimo_ofertante_uid']) ? (int)$listing['ultimo_ofertante_uid'] : null;

                if ($ganador_uid === null || $precio_actual === null) {
                    // 3) Sin pujas: devolver stock al vendedor
                    $qInv = $db->query("
                        SELECT id, cantidad FROM {$tabla_inv}
                        WHERE uid = {$vendedor_uid} AND objeto_id = '{$objeto_id_sql}'
                        LIMIT 1
                    ");
                    $invRow = $db->fetch_array($qInv);
                    if ($invRow) {
                        $cantNueva = (int)$invRow['cantidad'] + $cantidad;
                        $db->query("UPDATE {$tabla_inv} SET cantidad = {$cantNueva} WHERE id = ".(int)$invRow['id']." LIMIT 1");
                    } else {
                        $db->query("
                            INSERT INTO {$tabla_inv} (objeto_id, uid, cantidad)
                            VALUES ('{$objeto_id_sql}', {$vendedor_uid}, {$cantidad})
                        ");
                    }

                    $db->query("
                        UPDATE {$tabla_market}
                        SET estado = 'finalizada_sin_venta'
                        WHERE id = ".(int)$listing['id']."
                        LIMIT 1
                    ");

                    $db->query("COMMIT");
                    return ['ok'=>true, 'status'=>'sin_venta', 'message'=>'Subasta finalizada sin pujas. Stock devuelto al vendedor.'];
                }

                // 4) Con ganador: liquidar
                // Lock fichas vendedor y ganador
                $fqV = $db->query("SELECT fid, berries FROM {$tabla_fichas} WHERE fid = {$vendedor_uid} FOR UPDATE");
                $fV  = $db->fetch_array($fqV);
                if (!$fV) throw new Exception('No se encontró la ficha del vendedor.');

                $fqG = $db->query("SELECT fid, berries FROM {$tabla_fichas} WHERE fid = {$ganador_uid} FOR UPDATE");
                $fG  = $db->fetch_array($fqG);
                if (!$fG) throw new Exception('No se encontró la ficha del ganador.');

                // El ganador ya tiene descontado precio_actual al pujar líder; abonamos al vendedor
                $berriesV_nuevo = (int)$fV['berries'] + $precio_actual;
                $db->query("UPDATE {$tabla_fichas} SET berries = {$berriesV_nuevo} WHERE fid = {$vendedor_uid} LIMIT 1");

                // Registrar movidoInframundo del vendedor
                $db->query("
                    UPDATE {$tabla_fichas}
                    SET movidoInframundo = COALESCE(movidoInframundo, 0) + {$precio_actual}
                    WHERE fid = {$vendedor_uid}
                    LIMIT 1
                ");

                // 5) Transferir objeto al ganador y marcar vendidoReciente
                $now_timestamp = gmdate('Y-m-d H:i:s'); // UTC timestamp
                
                $qInvG = $db->query("
                    SELECT id, cantidad FROM {$tabla_inv}
                    WHERE uid = {$ganador_uid} AND objeto_id = '{$objeto_id_sql}'
                    LIMIT 1
                ");
                $invG = $db->fetch_array($qInvG);
                if ($invG) {
                    $cantNueva = (int)$invG['cantidad'] + $cantidad;
                    $db->query("
                        UPDATE {$tabla_inv} 
                        SET cantidad = {$cantNueva}, vendidoReciente = '{$now_timestamp}'
                        WHERE id = ".(int)$invG['id']." 
                        LIMIT 1
                    ");
                } else {
                    $db->query("
                        INSERT INTO {$tabla_inv} (objeto_id, uid, cantidad, vendidoReciente)
                        VALUES ('{$objeto_id_sql}', {$ganador_uid}, {$cantidad}, '{$now_timestamp}')
                    ");
                }

                // 6) Cerrar anuncio
                $db->query("
                    UPDATE {$tabla_market}
                    SET estado='vendida', comprador_uid={$ganador_uid}
                    WHERE id = ".(int)$listing['id']."
                    LIMIT 1
                ");

                // 7) Auditoría opcional
                if (function_exists('log_audit_currency')) {
                    log_audit_currency($vendedor_uid, '[Subasta Liquidada]', $vendedor_uid, '[Berries]', 'berries', $berriesV_nuevo);
                }

                $db->query("COMMIT");
                return ['ok'=>true, 'status'=>'vendida', 'message'=>'Subasta liquidada: berries abonados al vendedor y objeto entregado al ganador.'];

            } catch (Throwable $e) {
                $db->query("ROLLBACK");
                return ['ok'=>false, 'message'=>$e->getMessage()];
            }
        }
    // #endregion PENDIENTE DE CREARLE UN POST PARA USAR

    // Helper para manejar las compras directas y transacción de inventario
    function comprar_ahora($db, int $listing_id, int $buyer_uid): array
    {
        $tabla_market = 'mybb_Objetos_Inframundo';
        $tabla_fichas = 'mybb_op_fichas';
        $tabla_inv    = 'mybb_op_inventario';

        $db->query("START TRANSACTION");
        try {
            // 1) Lock del listing
            $q = $db->query("
                SELECT id, objeto_id, vendedor_uid, ultimo_ofertante_uid, comprador_uid,
                    precio_minimo, precio_actual, precio_compra,
                    fecha_final_subasta, estado, cantidad
                FROM {$tabla_market}
                WHERE id = '".(int)$listing_id."'
                FOR UPDATE
            ");
            $listing = $db->fetch_array($q);
            if (!$listing) throw new Exception('Anuncio inexistente');
            if ($listing['estado'] !== 'activa') throw new Exception('El anuncio no está activo');

            // Determinar si es venta directa o subasta
            $fecha_subasta = $listing['fecha_final_subasta'];
            $fechasInvalidas  = [null, '', '0000-00-00 00:00:00', 'null', '0'];
            $tieneFechaValida = !in_array($fecha_subasta, $fechasInvalidas, true);

            if ($tieneFechaValida) {
                $dt_end = parse_utc_mysql_datetime($fecha_subasta);
                if (!$dt_end) {
                    throw new Exception('Fecha de subasta inválida en el anuncio.');
                }
                if (time() >= $dt_end->getTimestamp()) {
                    throw new Exception('La subasta ya ha finalizado.');
                }

                // Subasta activa con compra inmediata
                $precio_compra = $listing['precio_compra'] !== null ? (int)$listing['precio_compra'] : null;
                if ($precio_compra === null) {
                    throw new Exception('Este anuncio no tiene compra directa');
                }

                $precio_actual = $listing['precio_actual'] !== null ? (int)$listing['precio_actual'] : null;
                if ($precio_actual !== null && $precio_actual >= $precio_compra) {
                    throw new Exception('Compra directa no disponible: la puja actual alcanza o supera el precio de compra');
                }

                $precio_final = $precio_compra;
            } else {
                // Venta directa pura (sin fecha)
                $precio_final = (int)$listing['precio_actual'];
                if ($precio_final <= 0) {
                    throw new Exception('Precio de venta inválido');
                }
                // MUY IMPORTANTE: en venta directa no hay pujas a reembolsar
                $precio_actual = null;
            }

            // 1.1) Evitar autocompra
            if ((int)$listing['vendedor_uid'] === (int)$buyer_uid) {
                throw new Exception('No puedes comprar tu propio anuncio');
            }
            
            // 1.2) VALIDACIÓN: Evitar compra entre cuentas vinculadas
            $vendedor_uid = (int)$listing['vendedor_uid'];
            if (son_cuentas_vinculadas($db, $vendedor_uid, $buyer_uid)) {
                error_log("Compra rechazada: cuentas vinculadas (vendedor: {$vendedor_uid}, comprador: {$buyer_uid})");
                throw new Exception('No puedes comprar objetos de tus cuentas vinculadas');
            }

            // 2) Lock fichas del comprador y vendedor + validar saldo
            $fqC = $db->query("
                SELECT fid, berries
                FROM {$tabla_fichas}
                WHERE fid = '".(int)$buyer_uid."'
                FOR UPDATE
            ");
            $fichaC = $db->fetch_array($fqC);
            if (!$fichaC) throw new Exception('No se encontró la ficha del comprador');

            $fqV = $db->query("
                SELECT fid, berries
                FROM {$tabla_fichas}
                WHERE fid = '".(int)$listing['vendedor_uid']."'
                FOR UPDATE
            ");
            $fichaV = $db->fetch_array($fqV);
            if (!$fichaV) throw new Exception('No se encontró la ficha del vendedor');

            $berriesC = (int)$fichaC['berries'];
            if ($berriesC < $precio_final) {
                throw new Exception('No tienes berries suficientes para la compra');
            }

            // 3) Reembolso a último ofertante (solo si veníamos de subasta)
            $ultimo_uid = !empty($listing['ultimo_ofertante_uid']) ? (int)$listing['ultimo_ofertante_uid'] : null;
            if ($precio_actual !== null && $ultimo_uid !== null) {
                $fqPrev = $db->query("
                    SELECT fid, berries
                    FROM {$tabla_fichas}
                    WHERE fid = '{$ultimo_uid}'
                    FOR UPDATE
                ");
                $fichaPrev = $db->fetch_array($fqPrev);
                if ($fichaPrev) {
                    $nuevo_prev_berries = (int)$fichaPrev['berries'] + $precio_actual;
                    $db->query("
                        UPDATE {$tabla_fichas}
                        SET berries = '{$nuevo_prev_berries}'
                        WHERE fid = '{$ultimo_uid}'
                        LIMIT 1
                    ");
                    if (function_exists('log_audit_currency')) {
                        log_audit_currency($ultimo_uid, '[Compra directa de otro]', $ultimo_uid, '[Devolución puja][Berries]', 'berries', $nuevo_prev_berries);
                    }
                }
            }

            // 4) Mover berries
            $berriesC_nuevo = $berriesC - $precio_final;
            $berriesV_nuevo = (int)$fichaV['berries'] + $precio_final;

            $db->query("UPDATE {$tabla_fichas} SET berries = '{$berriesC_nuevo}' WHERE fid = '".(int)$buyer_uid."' LIMIT 1");
            $db->query("UPDATE {$tabla_fichas} SET berries = '{$berriesV_nuevo}' WHERE fid = '".(int)$listing['vendedor_uid']."' LIMIT 1");

            // 4.1) Registrar importe movido en el Inframundo (vendedor)
            $db->query("
                UPDATE {$tabla_fichas}
                SET movidoInframundo = COALESCE(movidoInframundo, 0) + {$precio_final}
                WHERE fid = '".(int)$listing['vendedor_uid']."'
                LIMIT 1
            ");

            // 5) Transferir stock al comprador y marcar vendidoReciente
            $cantidad = (int)$listing['cantidad'];
            $objeto_id_sql = $db->escape_string($listing['objeto_id']);
            $now_timestamp = gmdate('Y-m-d H:i:s'); // UTC timestamp

            $qInv = $db->query("
                SELECT id, cantidad FROM {$tabla_inv}
                WHERE uid = '".(int)$buyer_uid."' AND objeto_id = '{$objeto_id_sql}'
                LIMIT 1
            ");
            $invRow = $db->fetch_array($qInv);
            if ($invRow) {
                $cantNueva = (int)$invRow['cantidad'] + $cantidad;
                $db->query("
                    UPDATE {$tabla_inv} 
                    SET cantidad = {$cantNueva}, vendidoReciente = '{$now_timestamp}'
                    WHERE id = ".(int)$invRow['id']." 
                    LIMIT 1
                ");
            } else {
                $db->query("
                    INSERT INTO {$tabla_inv} (objeto_id, uid, cantidad, vendidoReciente)
                    VALUES ('{$objeto_id_sql}', '".(int)$buyer_uid."', {$cantidad}, '{$now_timestamp}')
                ");
            }

            // 6) Cerrar anuncio
            $db->query("
                UPDATE {$tabla_market}
                SET estado='vendida', comprador_uid='".(int)$buyer_uid."', precio_actual='{$precio_final}'
                WHERE id = '".(int)$listing_id."' LIMIT 1
            ");

            // 7) Auditoría
            if (function_exists('log_audit_currency')) {
                log_audit_currency($buyer_uid, '[Compra Directa]', $buyer_uid, '[Berries]', 'berries', $berriesC_nuevo);
                log_audit_currency($listing['vendedor_uid'], '[Venta Directa]', $listing['vendedor_uid'], '[Berries]', 'berries', $berriesV_nuevo);
            }

            $db->query("COMMIT");
            return ['ok'=>true, 'message'=>'Compra directa realizada correctamente'];

        } catch (Throwable $e) {
            $db->query("ROLLBACK");
            return ['ok'=>false, 'message'=>$e->getMessage()];
        }
    }

    // Helper para recuperar/cancelar un listing del propietario
    function recuperar_listing($db, int $listing_id, int $owner_uid): array
    {
        $tabla_market = 'mybb_Objetos_Inframundo';
        $tabla_fichas = 'mybb_op_fichas';
        $tabla_inv    = 'mybb_op_inventario';

        $db->query("START TRANSACTION");
        try {
            // 1) Lock del listing
            $q = $db->query("
                SELECT id, objeto_id, vendedor_uid, ultimo_ofertante_uid, comprador_uid,
                    precio_minimo, precio_actual, precio_compra,
                    fecha_final_subasta, estado, cantidad
                FROM {$tabla_market}
                WHERE id = '".(int)$listing_id."'
                LIMIT 1
            ");
            $listing = $db->fetch_array($q);
            
            if (!$listing) throw new Exception('Anuncio inexistente');
            if ($listing['estado'] !== 'activa') throw new Exception('El anuncio no está activo');
            if ((int)$listing['vendedor_uid'] !== (int)$owner_uid) throw new Exception('Solo el propietario puede recuperar el objeto');
            
            // 2) Si hay pujas, reembolsar al último ofertante
            $ultimo_uid = !empty($listing['ultimo_ofertante_uid']) ? (int)$listing['ultimo_ofertante_uid'] : null;
            $precio_actual = $listing['precio_actual'] !== null ? (int)$listing['precio_actual'] : null;
            
            if ($precio_actual !== null && $ultimo_uid !== null && $ultimo_uid !== $owner_uid) {
                $fqPrev = $db->query("SELECT fid, berries FROM {$tabla_fichas} WHERE fid = '{$ultimo_uid}' LIMIT 1");
                $fichaPrev = $db->fetch_array($fqPrev);
                if ($fichaPrev) {
                    $berries_actual = isset($fichaPrev['berries']) ? (int)$fichaPrev['berries'] : 0;
                    $nuevo_prev_berries = $berries_actual + $precio_actual;
                    $db->query("
                        UPDATE {$tabla_fichas}
                        SET berries = '{$nuevo_prev_berries}'
                        WHERE fid = '{$ultimo_uid}'
                        LIMIT 1
                    ");
                    
                    // Log de auditoría de reembolso
                    if (function_exists('log_audit_currency')) {
                        log_audit_currency($ultimo_uid, '[Reembolso por Cancelación]', $ultimo_uid, '[Berries]', 'berries', $nuevo_prev_berries);
                    }
                }
            }
            
            // 3) Devolver objeto al inventario del propietario
            $objeto_id = $listing['objeto_id'];
            $cantidad = (int)$listing['cantidad'];
            
            // Verificar si ya tiene el objeto en inventario
            $invQuery = $db->query("
                SELECT id, cantidad
                FROM {$tabla_inv}
                WHERE uid = '".(int)$owner_uid."' AND objeto_id = '".$db->escape_string($objeto_id)."'
                LIMIT 1
            ");
            $invRow = $db->fetch_array($invQuery);
            
            if ($invRow) {
                // Ya tiene el objeto, incrementar cantidad
                $nueva_cantidad = (int)$invRow['cantidad'] + $cantidad;
                $db->query("
                    UPDATE {$tabla_inv}
                    SET cantidad = '{$nueva_cantidad}'
                    WHERE id = '".(int)$invRow['id']."'
                    LIMIT 1
                ");
            } else {
                // No tiene el objeto, crear nueva entrada
                $db->query("
                    INSERT INTO {$tabla_inv} (objeto_id, uid, cantidad)
                    VALUES ('".$db->escape_string($objeto_id)."', '".(int)$owner_uid."', '{$cantidad}')
                ");
            }
            
            // 4) Marcar listing como cancelado
            $db->query("
                UPDATE {$tabla_market}
                SET estado='cancelada'
                WHERE id = '".(int)$listing_id."'
                LIMIT 1
            ");
            
            $db->query("COMMIT");
            
            $mensaje = $precio_actual ? "Objeto recuperado y berries reembolsados al último pujador." : "Objeto recuperado exitosamente.";
            return ['ok'=>true, 'message'=>$mensaje];

        } catch (Throwable $e) {
            $db->query("ROLLBACK");
            // Agregar más información de debug
            error_log("Error en recuperar_listing: " . $e->getMessage() . " - Line: " . $e->getLine());
            return ['ok'=>false, 'message'=>'Error en la base de datos: ' . $e->getMessage()];
        }
    }

// #endregion HELPERS BAZAR
//================= FIN HELPERS BAZAR =================

// Inicio página
// #region Displays
    if ($g_ficha['muerto'] == '1') {
        $mensaje_redireccion = "Estás muerto, no puedes acceder a esta página.";
        eval("\$page = \"".$templates->get("op_redireccion")."\";");
        output_page($page);
        return;
    }

    // Verificar rango del inframundo y nivel de Contrabandista
    $query_rango_inframundo = $db->query("SELECT rango_inframundo, oficios FROM mybb_op_fichas WHERE fid='$uid'");
    $rango_inframundo = '';
    $nivel_contrabandista_sub = 0; // nivel de sub-especialización 0-3
    while ($q = $db->fetch_array($query_rango_inframundo)) {
        $rango_inframundo = $q['rango_inframundo'];
        if (!empty($q['oficios'])) {
            $oficios_obj = json_decode($q['oficios']);
            if (
                isset($oficios_obj->{'Mercader'}->{'nivel'}) &&
                (int)$oficios_obj->{'Mercader'}->{'nivel'} >= 2 &&
                isset($oficios_obj->{'Mercader'}->{'sub'}->{'Contrabandista'})
            ) {
                $nivel_contrabandista_sub = (int)$oficios_obj->{'Mercader'}->{'sub'}->{'Contrabandista'};
            }
        }
    }
    $nivel_efectivo_contrabandista = contrabandista_to_level($nivel_contrabandista_sub);

    // Sin rango en el inframundo NI Contrabandista → sin acceso
    $tiene_rango_inframundo = !empty($rango_inframundo) && $rango_inframundo != 'Sin rango';
    $tiene_acceso_contrabandista = $nivel_contrabandista_sub >= 1;
    if (!$tiene_rango_inframundo && !$tiene_acceso_contrabandista) {
        $mensaje_redireccion = "Necesitas tener un rango en el inframundo o ser Contrabandista para acceder al mercado negro.";
        eval("\$page = \"".$templates->get("op_redireccion")."\";");
        output_page($page);
        return;
    }

    // Si el Contrabandista no tiene rango propio, proyectar su nivel efectivo como rango
    // para que el template JS (que puede chequear {$rango_inframundo}) funcione igual.
    if (!$tiene_rango_inframundo && $tiene_acceso_contrabandista) {
        $rangos_por_nivel = [0 => '', 1 => 'Alimaña', 2 => 'Operativo', 3 => 'Capo', 4 => 'Broker', 5 => 'Broker Estrella'];
        $rango_inframundo = $rangos_por_nivel[$nivel_efectivo_contrabandista] ?? 'Capo';
    }
// #endregion Displays

// ============= CONTRABANDO =============
// #region CONTRABANDO
    $query_ficha = $db->query("SELECT * FROM mybb_op_fichas WHERE fid='$uid'");
    while ($q = $db->fetch_array($query_ficha)) { $ficha = $q; }
    $berries = $ficha['berries'];   

    $oficio1 = $ficha['oficio1'];
    $oficio2 = $ficha['oficio2'];

    $movido_inframundo = isset($ficha['movidoInframundo']) ? (int)$ficha['movidoInframundo'] : 0;
    $q_v019 = $db->query("SELECT 1 FROM mybb_op_virtudes_usuarios WHERE uid='$uid' AND virtud_id='V019' LIMIT 1");
    if ($db->fetch_array($q_v019)) { $movido_inframundo += 50000000; }

    $negro = get_objetos_negro($db);       // ← usamos la función para objetos del mercado negro
    $objetos = $negro['map'];              // mismo formato que se usaba (pero más limpito)
    $objetos_array = $negro['list'];       // lista de IDs

    // Obtener todos los objetos comerciables para el bazar
    $comerciables = get_objetos_comerciables($db);
    $objetos_comerciables = $comerciables['map'];

    $objetos_array_json = json_encode($objetos_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $objetos_json       = json_encode($objetos,       JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $objetos_comerciables_json = json_encode($objetos_comerciables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Llamamos a la función que saca los arrays ordenados
    $objetos_por_tier = clasificar_objetos_por_tier($objetos);

    // Ahora tenemos arrays por tier:
    $objetos_tier1 = $objetos_por_tier[1];
    $objetos_tier2 = $objetos_por_tier[2];
    $objetos_tier3 = $objetos_por_tier[3];
    $objetos_tier4 = $objetos_por_tier[4];
    $objetos_tier5 = $objetos_por_tier[5];

    $objetos_tier1_json = json_encode($objetos_tier1, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $objetos_tier2_json = json_encode($objetos_tier2, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $objetos_tier3_json = json_encode($objetos_tier3, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); 
    $objetos_tier4_json = json_encode($objetos_tier4, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $objetos_tier5_json = json_encode($objetos_tier5, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Ahora sacamos la pool semanal (o la creamos si no existe o es de otra semana)
    $pool_semanal = get_or_build_weekly_pool($objetos_por_tier);

    $pool_semanal_json = json_encode($pool_semanal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Ahora sacamos la visión del usuario según su rango (o nivel de Contrabandista)
    $pool_visible = build_user_visible_pool($pool_semanal, $rango_inframundo, $nivel_efectivo_contrabandista);

    // Para pasar al JS
    $pool_visible_json = json_encode($pool_visible, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // --- Acción: regenerar pool semanal (sólo staff) ---
    if ($accion === 'regenerar_pool') {

        // Recalcula la clasificación por tiers (si aún no lo tienes arriba)
        if (!isset($objetos_por_tier)) {
            $negro = get_objetos_negro($db);
            $objetos_por_tier = clasificar_objetos_por_tier($negro['map']);
        }

        $pool = get_or_build_weekly_pool($objetos_por_tier, true); // ← forzado

        header('Content-type: application/json');
        echo json_encode(['ok' => true, 'pool' => $pool]);
        exit;
    }


    if ($accion == 'comprar' && $uid != '0') {

        $should_cancel = false;

        header('Content-type: application/json');
        $response = array();
        $log_tienda = '';

        foreach ($listaDeComprasKeys as $objKey) {
            if (intval($listaDeCompras[$objKey]['cantidad']) <= 0) { $should_cancel = true; }
        }
        if ($should_cancel == true) { return; }

        foreach ($listaDeComprasKeys as $objKey) {

            $costoBerries = intval($objetos[$objKey][0]['negro_berries']);
            $comprasTotal += ($costoBerries * $listaDeCompras[$objKey]['cantidad']);   
        }

        // echo($comprasTotal);

        $logObj = "";
        if ((intval($berries) >= $comprasTotal) && $comprasTotal != 0) {
            foreach ($listaDeComprasKeys as $objKey) {

                $cantidadActual = '0';
                $cantidadExtra = $listaDeCompras[$objKey]['cantidad'];
                $has_objeto = false;
                $inventario_actual = $db->query("SELECT * FROM mybb_op_inventario WHERE uid='$uid' AND objeto_id='$objKey'");
                while ($q = $db->fetch_array($inventario_actual)) {  $has_objeto = true; $cantidadActual = $q['cantidad']; }
        

                $log_tienda = "Compra: $objKey, Cantidad: $cantidadExtra\n";

                $should_cancel = false;

                if ($should_cancel == true) {
                    $log_tienda .= "Te han estafado: $objKey, Cantidad: $cantidadExtra\n";

                    if ($has_objeto) {

                    }


                } else {
                    if ($has_objeto) {
                        $cantidadNueva = intval($cantidadActual) + intval($cantidadExtra);
                        $db->query(" 
                            UPDATE `mybb_op_inventario` SET `cantidad`='$cantidadNueva' WHERE objeto_id='$objKey' AND uid='$uid'
                        ");
                    } else {
                        $db->query(" 
                            INSERT INTO `mybb_op_inventario` (`objeto_id`, `uid`, `cantidad`) VALUES 
                            ('$objKey', '$uid', '$cantidadExtra');
                        ");
                    }
                    $log_tienda .= "Compra realizada: $objKey, Cantidad: $cantidadExtra\n";
                }


        
                $logObj .= "$objKey: $cantidadExtra;";
                // echo $listaDeCompras[$objKey]['cantidad'];
            }
        
            $nuevosBerries = intval($berries) - intval($comprasTotal);
            log_audit($uid, $username, '[Mercado Negro]', "Berries: $berries->$nuevosBerries (Gasto: $comprasTotal). $logObj");
            
                
            // $db->query(" UPDATE `mybb_op_fichas` SET berries='$nuevosBerries' WHERE `fid`='$uid'; ");
            log_audit_currency($uid, $username, $uid, '[Mercado Negro][Berries]', 'berries', $nuevosBerries);
        }

        $response[0] = array(
            'log_tienda' => $log_tienda
        );

        echo json_encode($response); 
        return;
    }
// #endregion CONTRABANDO 
// ============ FIN CONTRABANDO ===========

// ============ BAZAR =============
// #region BAZAR
    // Devolvemos el inventario tradeable del usuario para el front
    $inventario_usuario = get_user_inventory($db, $uid);
    
    // Transformar la estructura para JavaScript - aplanar inv + obj
    $inventario_plano = [];
    foreach ($inventario_usuario as $item) {
        $inventario_plano[] = array_merge(
            [
                'id' => $item['inv']['id'],
                'objeto_id' => $item['inv']['objeto_id'],
                'uid' => $item['inv']['uid'],
                'cantidad' => $item['inv']['cantidad'],
                'tiempo' => $item['inv']['tiempo'],
                'imagen' => $item['inv']['imagen'],
                'apodo' => $item['inv']['apodo'],
                'autor' => $item['inv']['autor'],
                'autor_uid' => $item['inv']['autor_uid'],
                'oficios' => $item['inv']['oficios'],
                'especial' => $item['inv']['especial'],
                'editado' => $item['inv']['editado'],
                'usado' => $item['inv']['usado'],
            ],
            $item['obj'] // Agregar directamente los datos del objeto
        );
    }
    
    $inventario_js = json_encode($inventario_plano, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Acción para vender items
    if ($accion === 'venta_fin' && $uid != 0) {
        $objeto_id           = $_POST['objeto_id'] ?? '';
        $precio_minimo       = (int)($_POST['precio_minimo'] ?? 0);
        $precio_compra       = isset($_POST['precio_compra']) && $_POST['precio_compra'] !== '' ? (int)$_POST['precio_compra'] : null;
        $fecha_final_subasta = $_POST['fecha_final_subasta'] ?? ''; // 'Y-m-d H:i:s'
        $notas               = $_POST['notas'] ?? null;
        $cantidad            = (int)($_POST['cantidad'] ?? 0);

        header('Content-Type: application/json');

        // Log de debugging
        error_log("Venta solicitada - UID: $uid, Objeto: $objeto_id, Cantidad: $cantidad, Precio min: $precio_minimo, Precio compra: " . ($precio_compra ?? 'null') . ", Fecha subasta: '$fecha_final_subasta'");

        $res = crear_listing_inframundo(
            $db,
            (int)$uid,
            $objeto_id,
            $precio_minimo,
            $precio_compra,
            $fecha_final_subasta,
            $notas,
            $cantidad
        );

        // Log resultado
        error_log("Resultado venta - OK: " . ($res['ok'] ? 'true' : 'false') . ", Error: " . ($res['error'] ?? 'none') . ", ID: " . ($res['id'] ?? 'null'));

        // Convertir formato de respuesta para compatibilidad con JavaScript
        $response = [
            [
                'success' => $res['ok'],
                'message' => $res['error'] ?: 'Operación completada exitosamente',
                'listing_id' => $res['id']
            ]
        ];

        echo json_encode($response);
        exit;
    }

    $listings_activos = get_listings_activos_inframundo($db, $uid);

    // Para pasar al JS
    $listings_activos_json = json_encode($listings_activos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Acción para pujar por un objeto listado
    if ($accion === 'pujar' && $uid != 0) {
        header('Content-type: application/json');

        $listing_id = (int)($_POST['listing_id'] ?? 0);
        $monto      = (int)($_POST['puja'] ?? 0);
        
        // Debug: registrar valores recibidos
        error_log("PUJAR - listing_id recibido: {$listing_id}, monto recibido: {$monto}");
        error_log("PUJAR - POST data: " . print_r($_POST, true));

        $res = pujar_con_descuento_berries($db, $listing_id, $uid, $monto);
        
        // El JavaScript espera un array de objetos, así que envolvemos el resultado
        $response = [$res];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Acción para compra directa
    if ($accion === 'comprar_ahora' && $uid != 0) {
        header('Content-type: application/json');
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        $res = comprar_ahora($db, $listing_id, (int)$uid);
        
        // Convertir formato de respuesta para compatibilidad con JavaScript
        $response = [
            [
                'success' => $res['ok'] ?? false,
                'message' => $res['ok'] ? ($res['message'] ?? 'Compra realizada exitosamente') : ($res['message'] ?? $res['error'] ?? 'Error desconocido'),
            ]
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Acción para recuperar objeto (cancelar listing)
    if ($accion === 'recuperar_objeto' && $uid != 0) {
        header('Content-type: application/json');
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        $res = recuperar_listing($db, $listing_id, (int)$uid);
        
        // Convertir formato de respuesta para compatibilidad con JavaScript
        $response = [
            [
                'success' => $res['ok'] ?? false,
                'message' => $res['ok'] ? ($res['message'] ?? 'Objeto recuperado exitosamente') : ($res['message'] ?? 'Error desconocido'),
            ]
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

// #endregion BAZAR
// ============ FIN BAZAR ============

$reload_js = "";

echo "<script>window.FECHAS_BAZAR_EN_UTC = true;</script>";

if (does_ficha_exist($uid)) {
    // Variable del UID para JavaScript
    $usuario_uid = (int)$uid;

    eval("\$page = \"".$templates->get("op_mercado_negro")."\";");
    output_page($page);
} else {
    $mensaje_redireccion = "Para acceder a esta página debes tener tu ficha aprobada.";
    eval("\$page = \"".$templates->get("op_redireccion")."\";");
    output_page($page);
}