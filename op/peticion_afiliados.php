<?php
/**
 * Formulario público de solicitud de afiliación (One Piece Gaiden)
 * Ver docs/afiliados_implementacion_opg.md §7
 *
 * SIN gate de sesión. Antispam: honeypot + rate-limit por IP.
 * La solicitud entra a mybb_op_peticiones con uid=0 y categoria='afiliados',
 * y Staff la revisa desde op/staff/peticiones_admin.php.
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'peticion_afiliados.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once "./functions/afiliados_functions.php";

global $templates, $mybb, $db;

$af_feedback = '';   // mensaje de resultado
$af_ok = false;

if ($mybb->request_method === 'post') {
    // Honeypot: campo oculto que un humano no completa. Si viene lleno,
    // respondemos "éxito" pero NO insertamos (no delatamos el filtro).
    $honeypot = trim($mybb->get_input('website', MyBB::INPUT_STRING));

    $nombre_foro = trim($mybb->get_input('nombre_foro', MyBB::INPUT_STRING));
    $url_foro    = trim($mybb->get_input('url_foro', MyBB::INPUT_STRING));
    $mensaje     = trim($mybb->get_input('mensaje', MyBB::INPUT_STRING));

    // IP del solicitante
    $ip = function_exists('get_ip') ? get_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');

    if ($honeypot !== '') {
        // Bot: fingimos éxito.
        $af_ok = true;
        $af_feedback = '¡Gracias! Tu solicitud fue recibida. El Staff la revisará pronto.';
    } else {
        // Validaciones
        $errores = array();
        if ($nombre_foro === '' || mb_strlen($nombre_foro) > 255) { $errores[] = 'El nombre del foro es obligatorio.'; }
        if ($url_foro === '' || mb_strlen($url_foro) > 255) { $errores[] = 'La URL del foro es obligatoria.'; }
        if ($mensaje === '' || mb_strlen($mensaje) > 60000) { $errores[] = 'El mensaje es obligatorio: añade ahí la información para la afiliación.'; }

        if (!empty($errores)) {
            $af_feedback = implode(' ', $errores);
        } elseif (!op_afiliados_rate_limit_ok($ip, 10)) {
            $af_feedback = 'Ya has enviado una solicitud hace poco. Espera unos minutos antes de volver a intentarlo.';
        } else {
            // Inserta como una petición más (uid=0, categoria='afiliados').
            // NO se pasa por op/peticiones.php porque ahí se exige uid != 0.
            // La columna `nombre` es varchar(20): guardamos el nombre del foro recortado.
            $nombre_esc  = $db->escape_string(mb_substr($nombre_foro, 0, 20));  // "nombre" = nombre del foro (recortado)
            $resumen_esc = $db->escape_string($nombre_foro);   // "resumen" = nombre del foro
            $desc_esc    = $db->escape_string($mensaje);       // "descripcion" = mensaje libre
            $url_esc     = $db->escape_string($url_foro);      // "url" = URL del foro

            $db->query("INSERT INTO `mybb_op_peticiones`
                (`uid`,`nombre`,`categoria`,`resumen`,`descripcion`,`url`)
                VALUES ('0','{$nombre_esc}','afiliados','{$resumen_esc}','{$desc_esc}','{$url_esc}')");

            $af_ok = true;
            $af_feedback = '¡Gracias! Tu solicitud fue recibida. El Staff la revisará y se pondrá en contacto para coordinar el logo y el nivel de afiliación.';
        }
    }
}

$af_feedback_html = '';
if ($af_feedback !== '') {
    $color = $af_ok ? '#27ae60' : '#c0392b';
    $af_feedback_html = '<div class="afp-msg" style="border-left:4px solid '.$color.'">'.htmlspecialchars($af_feedback, ENT_QUOTES).'</div>';
}

$peticion_afiliados_contenido = '
<style>
  /* Formulario de afiliación — sigue docs/style.md (paleta OPG). */
  .afp-wrap { max-width: 620px; margin: 0 auto; padding: 20px; }
  .afp-wrap * { box-sizing: border-box; }
  .afp-card {
      border: 2px solid #000; border-radius: 10px; padding: 26px;
      background: #ffedd2; box-shadow: 0px 0px 10px rgba(0,0,0,.4);
      font-family: InterRegular, sans-serif; color: #3b1300;
  }
  .afp-card h2 {
      margin: 0 0 6px; font-family: moonGetHeavy, Arial, sans-serif;
      text-transform: uppercase; letter-spacing: 1px; color: #ff8900;
      text-shadow: 1px 1px 1px #000;
  }
  .afp-card .afp-intro { font-size: 14px; margin-bottom: 12px; color: #5e5e5e; }
  .afp-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
  .afp-field label {
      font-size: 12px; text-transform: uppercase; letter-spacing: .5px;
      font-family: moonGetHeavy, Arial, sans-serif; color: #8f59f7;
  }
  .afp-field input, .afp-field textarea {
      padding: 9px 11px; border: 2px solid #000; border-radius: 8px;
      background: #fff; color: #1a1423; font: inherit; width: 100%;
      transition: all 0.2s ease;
  }
  .afp-field input:focus, .afp-field textarea:focus {
      outline: none; border-color: #ff8900; box-shadow: 0px 0px 6px rgba(255,137,0,.5);
  }
  .afp-field textarea { min-height: 110px; resize: vertical; }
  .afp-hp { position: absolute; left: -9999px; top: -9999px; height: 0; width: 0; overflow: hidden; }
  .afp-submit {
      padding: 10px 24px; border: 2px solid #000; border-radius: 8px;
      background: #ff8900; color: #fff; cursor: pointer;
      font-family: moonGetHeavy, Arial, sans-serif; text-transform: uppercase;
      letter-spacing: 1px; text-shadow: 1px 1px 1px #000;
      box-shadow: 0px 0px 6px rgba(0,0,0,.35); transition: all 0.25s ease;
  }
  .afp-submit:hover { background: #dc822a; transform: scale(1.05); }
  .afp-msg { padding: 12px 14px; border-radius: 8px; border: 2px solid #000; background: #ffe59b; margin-bottom: 18px; font-size: 14px; color: #1a1423; }
  .afp-note { font-size: 12px; color: #5e5e5e; margin-top: 14px; }
  .afp-intro a { text-decoration: none; }
  .afp-link { color: #8f59f7; font-weight: bold; }
  .afp-link:hover { text-decoration: underline; }
</style>


<div class="fondo-blanco">
<div class="afp-wrap">
  <div class="afp-card">
    <h2>Solicitar afiliación</h2>
    <div class="afp-intro">En One Piece Gaiden nos gusta mucho hacer nakamas, y nos encantaría afiliarnos con otros foros que quieran que la comunidad de rol crezca y sea más divertida. ¡Animaos a afiliaros con nosotros! No hace falta registrarse para completar este formulario. Podéis entrar a nuestro <a class="afp-link" href="https://discord.gg/5Mgcvv3ysk" target="_blank" rel="noopener noreferrer">Discord</a> con total libertad y contactar a cualquiera de nuestros administradores (Gorosei); por defecto, podéis escribirle a @lance_op.</div>
     '.$af_feedback_html.'
    <form method="post" action="/op/peticion_afiliados.php" autocomplete="off">
      <div class="afp-field">
        <label>Nombre del foro *</label>
        <input type="text" name="nombre_foro" maxlength="255" required>
      </div>
      <div class="afp-field">
        <label>URL del foro *</label>
        <input type="text" name="url_foro" maxlength="255" placeholder="https://..." required>
      </div>
      <div class="afp-field">
        <label>Mensaje *</label>
        <textarea name="mensaje" placeholder="Cuéntanos sobre tu foro y tu comunidad, cómo podemos contactarte (Discord, usuario…) y cualquier detalle que quieras compartir con nosotros. ¡Bienvenido a bordo, nakama! ⚓" required></textarea>
      </div>
      <!-- honeypot: no completar -->
      <div class="afp-hp" aria-hidden="true">
        <label>No completes este campo</label>
        <input type="text" name="website" tabindex="-1" autocomplete="off">
      </div>
      <button type="submit" class="afp-submit">Enviar solicitud</button>
    </form>
  </div>
</div>
</div>
';

eval('$page = "'.$templates->get('op_peticion_afiliados').'";');
output_page($page);
