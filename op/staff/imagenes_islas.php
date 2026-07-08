<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'imagenes_islas.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";
require_once MYBB_ROOT . "inc/functions_upload.php";

$uid = $mybb->user['uid'];
if (!is_staff($uid)) {
    die("Acceso denegado.");
}

$uploads_abs = "/home4/rovddqmy/public_html/images/op/uploads";
$uploads_url = "/images/op/uploads";
$ver_file    = MYBB_ROOT . "images/op/uploads/_ver";

$msg = '';

// --- POST: forzar refresco ---
if (isset($_POST['action']) && $_POST['action'] === 'refresh') {
    verify_post_check($mybb->get_input('my_post_key'));
    file_put_contents($ver_file, time());
    $msg = '<p class="ok">Caché refrescada correctamente.</p>';
}

// --- POST: subir imagen ---
if (isset($_POST['action']) && $_POST['action'] === 'upload') {
    verify_post_check($mybb->get_input('my_post_key'));

    $fid  = (int)$_POST['fid'];
    $tipo = $_POST['tipo']; // 'small' o 'large'

    $errors = [];

    if (!$fid) {
        $errors[] = "ID de foro inválido.";
    }
    if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "No se recibió ningún archivo.";
    }

    if (!$errors) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $errors[] = "Formato no permitido. Usa JPG, PNG, WEBP o GIF.";
        }
        if ($_FILES['imagen']['size'] > 5000000) {
            $errors[] = "El archivo supera los 5 MB.";
        }
    }

    if (!$errors) {
        $prefix   = ($tipo === 'large') ? 'IslaGrande' : 'Isla';
        $filename = $prefix . $fid . '_One_Piece_Gaiden_Foro_Rol.jpg';
        $dest     = $uploads_abs . '/' . $filename;

        // Convertir a JPG si no lo es, o simplemente mover el archivo
        if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $dest)) {
            $errors[] = "Error al guardar el archivo en el servidor.";
        } else {
            file_put_contents($ver_file, time());
            $ver = trim(file_get_contents($ver_file));
            $msg = '<p class="ok">Imagen subida: <code>' . htmlspecialchars($filename) . '</code>. Caché actualizada.</p>';
        }
    }

    if ($errors) {
        $msg = '<p class="err">' . implode('<br>', array_map('htmlspecialchars', $errors)) . '</p>';
    }
}

// --- Cargar lista de foros ---
$ver = file_exists($ver_file) ? trim(file_get_contents($ver_file)) : '1';
$forums = [];
$q = $db->simple_select('forums', 'fid, name, pid', '', ['order_by' => 'pid ASC, disporder ASC, name ASC']);
while ($row = $db->fetch_array($q)) {
    $forums[] = $row;
}

// Filtrar: mostrar foros que tengan imagen ya o que sean de primer nivel (pid=0)
// Además marcar cuáles tienen imagen actualmente
foreach ($forums as &$f) {
    $f['has_small'] = file_exists($uploads_abs . '/Isla'      . $f['fid'] . '_One_Piece_Gaiden_Foro_Rol.jpg');
    $f['has_large'] = file_exists($uploads_abs . '/IslaGrande' . $f['fid'] . '_One_Piece_Gaiden_Foro_Rol.jpg');
}
unset($f);

$post_key = generate_post_check();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Imágenes de Islas — OPG Staff</title>
<style>
* { box-sizing: border-box; }
body { font-family: sans-serif; background: #1a1a2e; color: #eee; margin: 0; padding: 20px; }
h1 { font-size: 1.4rem; margin-bottom: 10px; }
.toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.btn { padding: 7px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: .9rem; }
.btn-refresh { background: #4a90d9; color: #fff; }
.btn-upload  { background: #27ae60; color: #fff; font-size: .8rem; padding: 5px 10px; }
.ok  { background: #1e4d2b; border: 1px solid #27ae60; padding: 10px; border-radius: 4px; margin-bottom: 16px; }
.err { background: #4d1e1e; border: 1px solid #c0392b; padding: 10px; border-radius: 4px; margin-bottom: 16px; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
.card { background: #16213e; border: 1px solid #2a2a4a; border-radius: 8px; padding: 14px; }
.card h3 { margin: 0 0 4px; font-size: 1rem; }
.card small { color: #888; font-size: .8rem; }
.imgs { display: flex; gap: 10px; margin: 10px 0; }
.img-box { flex: 1; text-align: center; }
.img-box label { display: block; font-size: .75rem; color: #aaa; margin-bottom: 4px; }
.img-box img { width: 100%; max-height: 90px; object-fit: cover; border-radius: 4px; border: 1px solid #333; background: #0a0a1a; }
.img-box .noimg { height: 60px; display: flex; align-items: center; justify-content: center; font-size: .75rem; color: #555; border: 1px dashed #333; border-radius: 4px; }
.upload-row { display: flex; align-items: center; gap: 6px; margin-top: 6px; }
.upload-row input[type=file] { flex: 1; font-size: .75rem; color: #ccc; }
select.tipo { background: #0a0a1a; color: #ccc; border: 1px solid #444; padding: 4px 6px; border-radius: 4px; font-size: .8rem; }
.filter-bar { margin-bottom: 14px; }
.filter-bar input { background: #16213e; color: #eee; border: 1px solid #444; padding: 6px 10px; border-radius: 4px; width: 260px; }
</style>
</head>
<body>
<h1>Imágenes de Islas</h1>

<?= $msg ?>

<div class="toolbar">
    <form method="post">
        <input type="hidden" name="action" value="refresh">
        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($post_key) ?>">
        <button type="submit" class="btn btn-refresh">Forzar refresco de caché</button>
    </form>
    <small style="color:#888">Token actual: <code><?= htmlspecialchars($ver) ?></code></small>
</div>

<div class="filter-bar">
    <input type="text" id="buscar" placeholder="Filtrar por nombre o fid…" oninput="filtrar(this.value)">
    <label style="margin-left:14px;font-size:.85rem">
        <input type="checkbox" id="soloImagen" onchange="filtrar(document.getElementById('buscar').value)">
        Solo foros con imagen
    </label>
</div>

<div class="grid" id="grid">
<?php foreach ($forums as $f):
    $fid  = $f['fid'];
    $name = htmlspecialchars($f['name']);
    $small_url = $uploads_url . '/Isla'       . $fid . '_One_Piece_Gaiden_Foro_Rol.jpg?v=' . $ver;
    $large_url = $uploads_url . '/IslaGrande' . $fid . '_One_Piece_Gaiden_Foro_Rol.jpg?v=' . $ver;
    $has_any   = $f['has_small'] || $f['has_large'];
?>
<div class="card" data-fid="<?= $fid ?>" data-name="<?= strtolower($f['name']) ?>" data-has="<?= $has_any ? '1' : '0' ?>">
    <h3><?= $name ?></h3>
    <small>fid = <?= $fid ?> · pid = <?= $f['pid'] ?></small>

    <div class="imgs">
        <div class="img-box">
            <label>Pequeña (Isla<?= $fid ?>…)</label>
            <?php if ($f['has_small']): ?>
                <img src="<?= $small_url ?>" alt="">
            <?php else: ?>
                <div class="noimg">sin imagen</div>
            <?php endif; ?>
        </div>
        <div class="img-box">
            <label>Grande (IslaGrande<?= $fid ?>…)</label>
            <?php if ($f['has_large']): ?>
                <img src="<?= $large_url ?>" alt="">
            <?php else: ?>
                <div class="noimg">sin imagen</div>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action"      value="upload">
        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($post_key) ?>">
        <input type="hidden" name="fid"         value="<?= $fid ?>">
        <div class="upload-row">
            <select name="tipo" class="tipo">
                <option value="small">Pequeña</option>
                <option value="large">Grande</option>
            </select>
            <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.webp,.gif">
            <button type="submit" class="btn btn-upload">Subir</button>
        </div>
    </form>
</div>
<?php endforeach; ?>
</div>

<script>
function filtrar(q) {
    q = q.toLowerCase();
    var soloImg = document.getElementById('soloImagen').checked;
    document.querySelectorAll('#grid .card').forEach(function(c) {
        var match = c.dataset.name.includes(q) || c.dataset.fid.includes(q);
        var imgOk = !soloImg || c.dataset.has === '1';
        c.style.display = (match && imgOk) ? '' : 'none';
    });
}
</script>
</body>
</html>
