<?php
/**
 * Consola de Staff — Gestión de Afiliados (One Piece Gaiden)
 * Ver docs/afiliados_implementacion_opg.md §6
 *
 * Acciones AJAX (JSON): agregar, editar, toggle, eliminar. CSRF via my_post_key.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'gestionar_afiliados.php');
require_once "./../../global.php";
require "./../../inc/config.php";
require_once "./../functions/op_functions.php";
require_once "./../functions/afiliados_functions.php";

global $templates, $mybb, $db;
$uid = $mybb->user['uid'];

// --- Gate de acceso ---
if (!is_staff($uid) && !is_mod($uid)) {
    $mensaje_redireccion = "No tienes acceso para entrar a esta página. ¿Seguro no te perdiste?";
    eval('$page = "'.$templates->get('op_redireccion').'";');
    output_page($page);
    exit;
}

$TIPOS = array('hermano', 'grande', 'pequeno');

// =====================================================================
// Endpoint AJAX
// =====================================================================
if ($mybb->request_method === 'post' && $mybb->get_input('accion') !== '') {
    header('Content-Type: application/json; charset=utf-8');

    // CSRF
    $post_key = $mybb->get_input('my_post_key', MyBB::INPUT_STRING);
    if (!verify_post_check($post_key, true)) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'error' => 'CSRF inválido'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $accion = $mybb->get_input('accion', MyBB::INPUT_STRING);

    // --- toggle activo ---
    if ($accion === 'toggle') {
        $id = (int) $mybb->get_input('id', MyBB::INPUT_INT);
        if ($id <= 0) { echo json_encode(array('success' => false, 'error' => 'ID inválido')); exit; }
        $db->query("UPDATE `mybb_op_afiliados` SET `activo` = 1 - `activo` WHERE `id` = {$id} LIMIT 1");
        $row = $db->fetch_array($db->query("SELECT `activo` FROM `mybb_op_afiliados` WHERE `id` = {$id} LIMIT 1"));
        echo json_encode(array('success' => true, 'id' => $id, 'activo' => (int) $row['activo']), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- eliminar ---
    if ($accion === 'eliminar') {
        $id = (int) $mybb->get_input('id', MyBB::INPUT_INT);
        if ($id <= 0) { echo json_encode(array('success' => false, 'error' => 'ID inválido')); exit; }
        $db->query("DELETE FROM `mybb_op_afiliados` WHERE `id` = {$id} LIMIT 1");
        echo json_encode(array('success' => true, 'id' => $id), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- agregar / editar (validación compartida) ---
    if ($accion === 'agregar' || $accion === 'editar') {
        $tipo        = $mybb->get_input('tipo', MyBB::INPUT_STRING);
        $nombre      = trim($mybb->get_input('nombre', MyBB::INPUT_STRING));
        $url         = trim($mybb->get_input('url', MyBB::INPUT_STRING));
        $imagen      = trim($mybb->get_input('imagen', MyBB::INPUT_STRING));
        $descripcion = trim($mybb->get_input('descripcion', MyBB::INPUT_STRING));
        $orden       = (int) $mybb->get_input('orden', MyBB::INPUT_INT);

        // Validaciones server-side
        $errores = array();
        if (!in_array($tipo, $TIPOS, true)) { $errores[] = 'Nivel inválido.'; }
        if ($nombre === '' || mb_strlen($nombre) > 120) { $errores[] = 'El nombre es obligatorio (máx. 120).'; }
        if ($url === '' || mb_strlen($url) > 255) { $errores[] = 'La URL es obligatoria (máx. 255).'; }
        if ($imagen === '' || mb_strlen($imagen) > 255) { $errores[] = 'La imagen es obligatoria (máx. 255).'; }
        if (mb_strlen($descripcion) > 255) { $errores[] = 'La descripción no puede superar 255.'; }

        if (!empty($errores)) {
            echo json_encode(array('success' => false, 'error' => implode(' ', $errores)), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $tipo_esc   = $db->escape_string($tipo);
        $nombre_esc = $db->escape_string($nombre);
        $url_esc    = $db->escape_string($url);
        $imagen_esc = $db->escape_string($imagen);
        $desc_esc   = $db->escape_string($descripcion);

        if ($accion === 'agregar') {
            $agregado = $db->escape_string($mybb->user['username']);
            $db->query("INSERT INTO `mybb_op_afiliados`
                (`tipo`,`nombre`,`url`,`imagen`,`descripcion`,`orden`,`activo`,`agregado_por`)
                VALUES ('{$tipo_esc}','{$nombre_esc}','{$url_esc}','{$imagen_esc}','{$desc_esc}',{$orden},1,'{$agregado}')");
            $id = (int) $db->insert_id();
        } else {
            $id = (int) $mybb->get_input('id', MyBB::INPUT_INT);
            if ($id <= 0) { echo json_encode(array('success' => false, 'error' => 'ID inválido')); exit; }
            $db->query("UPDATE `mybb_op_afiliados` SET
                `tipo`='{$tipo_esc}', `nombre`='{$nombre_esc}', `url`='{$url_esc}',
                `imagen`='{$imagen_esc}', `descripcion`='{$desc_esc}', `orden`={$orden}
                WHERE `id` = {$id} LIMIT 1");
        }

        $row = $db->fetch_array($db->query("SELECT * FROM `mybb_op_afiliados` WHERE `id` = {$id} LIMIT 1"));
        echo json_encode(array('success' => true, 'afiliado' => $row), JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(array('success' => false, 'error' => 'Acción desconocida'), JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================================================
// Render de la consola
// =====================================================================

// Conteo activos vs tope por nivel (aviso de tope duro §6)
$conteos = array('hermano' => 0, 'grande' => 0, 'pequeno' => 0);
$cq = $db->query("SELECT tipo, COUNT(*) AS n FROM `mybb_op_afiliados` WHERE activo=1 GROUP BY tipo");
while ($cr = $db->fetch_array($cq)) {
    if (isset($conteos[$cr['tipo']])) { $conteos[$cr['tipo']] = (int) $cr['n']; }
}
$topes = array('hermano' => 1, 'grande' => OP_AFILIADOS_SLOTS_GRANDE, 'pequeno' => OP_AFILIADOS_SLOTS_PEQUENO);

function af_admin_badge($n, $tope) {
    $color = ($n > $tope) ? '#c0392b' : '#27ae60';
    return "<span style=\"font-weight:700;color:{$color}\">{$n}/{$tope}</span>";
}

// Filas existentes (todas, activas e inactivas)
$tarjetas = '';
$rows = $db->query("SELECT * FROM `mybb_op_afiliados` ORDER BY tipo, orden, id");
while ($r = $db->fetch_array($rows)) {
    $id     = (int) $r['id'];
    $tipo   = htmlspecialchars($r['tipo'], ENT_QUOTES);
    $nombre = htmlspecialchars($r['nombre'], ENT_QUOTES);
    $url    = htmlspecialchars($r['url'], ENT_QUOTES);
    $img    = htmlspecialchars($r['imagen'], ENT_QUOTES);
    $desc   = htmlspecialchars((string) $r['descripcion'], ENT_QUOTES);
    $orden  = (int) $r['orden'];
    $activo = (int) $r['activo'];
    $ratio  = ($tipo === 'pequeno') ? '1/1' : '16/9';
    $estado_txt = $activo ? 'Activo' : 'Inactivo';
    $estado_col = $activo ? '#27ae60' : '#999';

    $tarjetas .= "<div class=\"af-item\" id=\"af-item-{$id}\" data-id=\"{$id}\" data-tipo=\"{$tipo}\" data-nombre=\"{$nombre}\" data-url=\"{$url}\" data-imagen=\"{$img}\" data-descripcion=\"{$desc}\" data-orden=\"{$orden}\" data-activo=\"{$activo}\">
        <div class=\"af-thumb\" style=\"aspect-ratio:{$ratio}\"><img src=\"{$img}\" alt=\"\" onerror=\"this.style.opacity=0.2\"></div>
        <div class=\"af-meta\">
            <div class=\"af-name\">{$nombre} <span class=\"af-tag\">{$tipo}</span></div>
            <div class=\"af-sub\">orden: {$orden} · <span style=\"color:{$estado_col}\">{$estado_txt}</span></div>
            <div class=\"af-actions\">
                <button type=\"button\" class=\"af-edit\" data-id=\"{$id}\">Editar</button>
                <button type=\"button\" class=\"af-toggle\" data-id=\"{$id}\">".($activo ? 'Desactivar' : 'Activar')."</button>
                <button type=\"button\" class=\"af-del\" data-id=\"{$id}\">Eliminar</button>
            </div>
        </div>
    </div>";
}
if ($tarjetas === '') { $tarjetas = '<p class="af-empty">Todavía no hay afiliados cargados.</p>'; }

$post_key = htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8');

$gestionar_afiliados_contenido = '
<style>
  /* Consola de gestión de afiliados — sigue docs/style.md (paleta OPG). */
  .af-console { max-width: 1000px; margin: 0 auto; padding: 16px; font-family: InterRegular, sans-serif; color: #3b1300; }
  .af-console * { box-sizing: border-box; }
  .af-console h2 {
      margin: 0 0 4px; font-family: moonGetHeavy, Arial, sans-serif;
      text-transform: uppercase; letter-spacing: 1px; color: #ff8900;
      text-shadow: 1px 1px 1px #000;
  }
  .af-counts { margin: 8px 0 18px; font-size: 14px; }
  .af-counts span.lbl { font-family: moonGetHeavy, Arial, sans-serif; text-transform: uppercase; letter-spacing: 1px; font-size: 12px; color: #8f59f7; margin-right: 4px; }

  .af-form {
      border: 2px solid #000; border-radius: 10px; padding: 18px; margin-bottom: 22px;
      background: #ffedd2; box-shadow: 0px 0px 8px rgba(0,0,0,.35);
  }
  .af-form h3 {
      margin: 0 0 12px; font-family: moonGetHeavy, Arial, sans-serif;
      text-transform: uppercase; letter-spacing: 1px; color: #8f59f7;
      text-shadow: 1px 1px 1px #000;
  }
  .af-row { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 10px; }
  .af-field { display: flex; flex-direction: column; gap: 4px; flex: 1 1 220px; }
  .af-field label { font-family: moonGetHeavy, Arial, sans-serif; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: #8f59f7; }
  .af-field input, .af-field select, .af-field textarea {
      padding: 8px 10px; border: 2px solid #000; border-radius: 8px;
      background: #fff; color: #1a1423; font: inherit; transition: all 0.2s ease;
  }
  .af-field input:focus, .af-field select:focus, .af-field textarea:focus {
      outline: none; border-color: #ff8900; box-shadow: 0px 0px 6px rgba(255,137,0,.5);
  }
  .af-preview { width: 140px; flex: 0 0 auto; }
  .af-preview .af-thumb { width: 140px; border: 2px solid #000; border-radius: 8px; overflow: hidden; }
  .af-thumb { background: #ffe59b; }
  .af-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .af-buttons { display: flex; gap: 10px; margin-top: 6px; }
  .af-buttons button {
      padding: 9px 18px; border: 2px solid #000; border-radius: 8px; cursor: pointer;
      font-family: moonGetHeavy, Arial, sans-serif; text-transform: uppercase;
      letter-spacing: 1px; text-shadow: 1px 1px 1px #000; transition: all 0.25s ease;
  }
  .af-save { background: #ff8900; color: #fff; }
  .af-save:hover { background: #dc822a; transform: scale(1.05); }
  .af-cancel { background: #a13838; color: #fff; }
  .af-cancel:hover { background: #ff8900; transform: scale(1.05); }
  .af-msg { margin: 8px 0; font-size: 14px; min-height: 18px; font-weight: bold; }
  .af-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
  .af-item {
      display: flex; gap: 12px; border: 2px solid #000; border-radius: 8px; padding: 10px;
      background: #ffe59b; box-shadow: 0px 0px 4px rgba(0,0,0,.3); transition: all 0.2s ease;
  }
  .af-item:hover { box-shadow: 0px 0px 8px #000; }
  .af-item .af-thumb { width: 96px; flex: 0 0 auto; border: 2px solid #000; border-radius: 6px; overflow: hidden; }
  .af-meta { min-width: 0; flex: 1 1 auto; }
  .af-name { font-weight: 700; margin-bottom: 2px; word-break: break-word; color: #1a1423; }
  .af-tag { font-family: moonGetHeavy, Arial, sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #ff8900; margin-left: 4px; }
  .af-sub { font-size: 12px; color: #5e5e5e; margin-bottom: 8px; }
  .af-actions { display: flex; flex-wrap: wrap; gap: 6px; }
  .af-actions button {
      padding: 5px 12px; border: 2px solid #000; border-radius: 6px; background: #fcecd2;
      color: #1a1423; cursor: pointer; font-size: 12px; font-weight: bold; transition: all 0.2s ease;
  }
  .af-actions button:hover { background: #ff8900; color: #fff; }
  .af-empty { padding: 20px; text-align: center; color: #5e5e5e; }
</style>

<div class="af-console">
  <h2>Gestión de Afiliados</h2>
  <div class="af-counts">
    <span class="lbl">Almirante</span> '.af_admin_badge($conteos['hermano'], $topes['hermano']).' &nbsp;·&nbsp;
    <span class="lbl">Capitanes</span> '.af_admin_badge($conteos['grande'], $topes['grande']).' &nbsp;·&nbsp;
    <span class="lbl">Piratas</span> '.af_admin_badge($conteos['pequeno'], $topes['pequeno']).'
    <div style="font-size:12px;opacity:.65;margin-top:4px">Si un nivel supera su tope, los sobrantes (por orden) no se muestran en el índice.</div>
  </div>

  <div class="af-form">
    <h3 id="af-form-title">Agregar afiliado</h3>
    <input type="hidden" id="af-id" value="">
    <div class="af-row">
      <div class="af-field">
        <label>Nivel</label>
        <select id="af-tipo">
          <option value="hermano">Afiliado Almirante (hermano)</option>
          <option value="grande" selected>Afiliados Capitanes (grande)</option>
          <option value="pequeno">Afiliados Piratas (pequeno)</option>
        </select>
      </div>
      <div class="af-field">
        <label>Nombre del foro</label>
        <input type="text" id="af-nombre" maxlength="120" placeholder="Nombre visible (queda en el title)">
      </div>
      <div class="af-field">
        <label>Orden</label>
        <input type="number" id="af-orden" value="0">
      </div>
    </div>
    <div class="af-row">
      <div class="af-field">
        <label>URL del foro</label>
        <input type="text" id="af-url" maxlength="255" placeholder="https://...">
      </div>
      <div class="af-field">
        <label>URL de la imagen</label>
        <input type="text" id="af-imagen" maxlength="255" placeholder="https://... (súbela antes con el uploader)">
      </div>
      <div class="af-preview">
        <label style="font-size:12px;opacity:.75">Preview</label>
        <div class="af-thumb" id="af-preview-thumb" style="aspect-ratio:16/9"><img id="af-preview-img" src="" alt=""></div>
      </div>
    </div>
    <div class="af-row">
      <div class="af-field" style="flex-basis:100%">
        <label>Descripción (opcional · nota interna de Staff, no se muestra en público)</label>
        <input type="text" id="af-descripcion" maxlength="255">
      </div>
    </div>
    <div class="af-msg" id="af-msg"></div>
    <div class="af-buttons">
      <button type="button" class="af-save" id="af-save">Guardar</button>
      <button type="button" class="af-cancel" id="af-cancel" style="display:none">Cancelar edición</button>
    </div>
  </div>

  <div class="af-list" id="af-list">'.$tarjetas.'</div>
</div>

<script>
(function(){
  var POST_KEY = "'.$post_key.'";
  var ENDPOINT = "/op/staff/gestionar_afiliados.php";

  var el = function(id){ return document.getElementById(id); };
  var fId=el("af-id"), fTipo=el("af-tipo"), fNombre=el("af-nombre"), fOrden=el("af-orden"),
      fUrl=el("af-url"), fImg=el("af-imagen"), fDesc=el("af-descripcion"),
      msg=el("af-msg"), title=el("af-form-title"), cancelBtn=el("af-cancel"),
      prevImg=el("af-preview-img"), prevThumb=el("af-preview-thumb"), list=el("af-list");

  function ratioFor(t){ return t === "pequeno" ? "1/1" : "16/9"; }
  function refreshPreview(){
    prevImg.src = fImg.value || "";
    prevThumb.style.aspectRatio = ratioFor(fTipo.value);
  }
  fImg.addEventListener("input", refreshPreview);
  fTipo.addEventListener("change", refreshPreview);

  function resetForm(){
    fId.value=""; fNombre.value=""; fUrl.value=""; fImg.value=""; fDesc.value=""; fOrden.value="0";
    fTipo.value="grande"; title.textContent="Agregar afiliado"; cancelBtn.style.display="none";
    msg.textContent=""; msg.style.color="";
    refreshPreview();
  }
  cancelBtn.addEventListener("click", resetForm);

  function post(data, cb){
    data.my_post_key = POST_KEY;
    var body = Object.keys(data).map(function(k){
      return encodeURIComponent(k) + "=" + encodeURIComponent(data[k]);
    }).join("&");
    fetch(ENDPOINT, {
      method:"POST",
      headers:{"Content-Type":"application/x-www-form-urlencoded"},
      credentials:"same-origin",
      body: body
    }).then(function(r){ return r.json(); }).then(cb).catch(function(){
      msg.style.color="#c0392b"; msg.textContent="Error de red.";
    });
  }

  function esc(s){ var d=document.createElement("div"); d.textContent=s==null?"":s; return d.innerHTML; }

  function cardHtml(a){
    var ratio = a.tipo === "pequeno" ? "1/1" : "16/9";
    var activo = parseInt(a.activo,10);
    var estado = activo ? "Activo" : "Inactivo";
    var col = activo ? "#27ae60" : "#999";
    return \'<div class="af-item" id="af-item-\'+a.id+\'" data-id="\'+a.id+\'" data-tipo="\'+esc(a.tipo)+\'" data-nombre="\'+esc(a.nombre)+\'" data-url="\'+esc(a.url)+\'" data-imagen="\'+esc(a.imagen)+\'" data-descripcion="\'+esc(a.descripcion||"")+\'" data-orden="\'+esc(a.orden)+\'" data-activo="\'+activo+\'">\'
      + \'<div class="af-thumb" style="aspect-ratio:\'+ratio+\'"><img src="\'+esc(a.imagen)+\'" alt="" onerror="this.style.opacity=0.2"></div>\'
      + \'<div class="af-meta"><div class="af-name">\'+esc(a.nombre)+\' <span class="af-tag">\'+esc(a.tipo)+\'</span></div>\'
      + \'<div class="af-sub">orden: \'+esc(a.orden)+\' · <span style="color:\'+col+\'">\'+estado+\'</span></div>\'
      + \'<div class="af-actions"><button type="button" class="af-edit" data-id="\'+a.id+\'">Editar</button>\'
      + \'<button type="button" class="af-toggle" data-id="\'+a.id+\'">\'+(activo?"Desactivar":"Activar")+\'</button>\'
      + \'<button type="button" class="af-del" data-id="\'+a.id+\'">Eliminar</button></div></div></div>\';
  }

  el("af-save").addEventListener("click", function(){
    var id = fId.value.trim();
    var accion = id ? "editar" : "agregar";
    msg.style.color=""; msg.textContent="Guardando...";
    post({
      accion: accion, id: id, tipo: fTipo.value, nombre: fNombre.value,
      url: fUrl.value, imagen: fImg.value, descripcion: fDesc.value, orden: fOrden.value
    }, function(res){
      if(!res || !res.success){ msg.style.color="#c0392b"; msg.textContent=(res&&res.error)||"Error."; return; }
      var html = cardHtml(res.afiliado);
      var tmp = document.createElement("div"); tmp.innerHTML = html;
      var newCard = tmp.firstChild;
      var existing = el("af-item-"+res.afiliado.id);
      if(existing){ existing.parentNode.replaceChild(newCard, existing); }
      else {
        var empty = list.querySelector(".af-empty"); if(empty){ empty.remove(); }
        list.insertBefore(newCard, list.firstChild);
      }
      msg.style.color="#27ae60"; msg.textContent="Guardado.";
      resetForm();
    });
  });

  list.addEventListener("click", function(e){
    var b = e.target.closest("button"); if(!b) return;
    var id = b.getAttribute("data-id");
    var item = el("af-item-"+id); if(!item) return;

    if(b.classList.contains("af-edit")){
      fId.value=id;
      fTipo.value=item.getAttribute("data-tipo");
      fNombre.value=item.getAttribute("data-nombre");
      fUrl.value=item.getAttribute("data-url");
      fImg.value=item.getAttribute("data-imagen");
      fDesc.value=item.getAttribute("data-descripcion");
      fOrden.value=item.getAttribute("data-orden");
      title.textContent="Editar afiliado #"+id;
      cancelBtn.style.display="inline-block";
      refreshPreview();
      window.scrollTo({top:0, behavior:"smooth"});
      return;
    }
    if(b.classList.contains("af-toggle")){
      post({accion:"toggle", id:id}, function(res){
        if(res && res.success){
          var a = {
            id:id, tipo:item.getAttribute("data-tipo"), nombre:item.getAttribute("data-nombre"),
            url:item.getAttribute("data-url"), imagen:item.getAttribute("data-imagen"),
            descripcion:item.getAttribute("data-descripcion"), orden:item.getAttribute("data-orden"),
            activo:res.activo
          };
          var tmp=document.createElement("div"); tmp.innerHTML=cardHtml(a);
          item.parentNode.replaceChild(tmp.firstChild, item);
        }
      });
      return;
    }
    if(b.classList.contains("af-del")){
      if(!confirm("¿Eliminar este afiliado? Esta acción no se puede deshacer.")) return;
      post({accion:"eliminar", id:id}, function(res){
        if(res && res.success){ item.remove(); }
      });
      return;
    }
  });

  refreshPreview();
})();
</script>
';

eval('$page = "'.$templates->get('staff_gestionar_afiliados').'";');
output_page($page);
