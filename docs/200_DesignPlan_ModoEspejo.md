# Modo Espejo: plan de diseño

> Depende de: [100_Requirements_ModoEspejo.md](100_Requirements_ModoEspejo.md)

## 1. Objetivo

Diseñar una herramienta exclusiva para FID 315 que permita entrar a la
sesión de cualquier usuario (para debug), sin tocar su contraseña, sin
crear ningún vínculo persistente en la base de datos, y sin que ese
usuario obtenga ninguna capacidad nueva sobre la cuenta de administrador.

Todo el estado de "modo espejo activo" vive en **cookies**, no en tablas
nuevas — no hay nada que instalar en la base de datos para esta feature.

## 2. Estado actual relevante

- MyBB identifica una sesión logueada con la cookie `mybbuser`, formato
  `"<uid>_<loginkey>"` (`inc/class_session.php`, `load_user()`,
  líneas ~88-154). `loginkey` es un token de sesión persistente en
  `mybb_users.loginkey`, no la contraseña.
- `my_setcookie($name, $value, $expires, $httponly, $samesite)`
  (`inc/functions.php:2251`) — importante: **`$expires` es relativo**, en
  segundos desde ahora (`TIME_NOW + $expires`), no un timestamp absoluto.
  `$expires` vacío/null equivale a un año.
- `my_unsetcookie($name)` (`inc/functions.php:2325`) — pone `$expires` a
  `-3600` para forzar el borrado inmediato.
- `log_audit($uid, $username, $categoria, $log)`
  (`op/functions/op_functions.php:61`) — 4 parámetros exactos. No tiene un
  parámetro separado de "usuario objetivo"; el detalle va en `$log` como
  texto libre.
- El **Enhanced Account Switcher** (`inc/plugins/accountswitcher.php`) se
  descartó por ser bidireccional (ver 100_Requirements_ModoEspejo.md).
- `inc/plugins/op_bitacora.php` / `op_solicitudes_creacion.php` son el
  precedente para "variable global calculada por request, insertada en el
  header sin tocar `global.php`" (hook `global_intermediate` +
  `find_replace`/`str_replace` sobre la plantilla `header`).
- `templates/One_Piece_Gaiden_Templates/header.html` (línea 993-999) tiene
  el bloque `<if $g_is_staff then> ... #peticiones_staff ... </if>`. El
  aviso de modo espejo **no puede vivir dentro de ese `<if>`** — cuando se
  refleja la sesión de un usuario normal (no staff), `$g_is_staff` es
  falso para esa sesión y el bloque entero no se renderiza. El aviso tiene
  que insertarse justo después de ese `</if>` (línea 999), para que se
  muestre sin importar el grupo del usuario reflejado.

## 3. Decisiones de arquitectura

### 3.1 Componentes

| Componente | Responsabilidad |
|---|---|
| `op/staff/modo_espejo.php` | Página + acciones `activar`/`volver` |
| `inc/plugins/op_modo_espejo.php` | Hook de header: banner + expiración forzada del lado servidor |

No hay `functions.php` aparte ni tabla nueva — la lógica cabe entera en
estos dos archivos porque no hay queries complejas ni estado más allá de
cookies. El plugin expone dos funciones pequeñas y reutilizables
(`op_modo_espejo_parsear_retorno()` y
`op_modo_espejo_loginkey_admin_valido()`, ver §6) que la página consume
vía `require_once` — evita duplicar la lógica de parseo/validación de la
cookie de retorno entre "Volver" (página) y la expiración forzada
(plugin).

### 3.2 Todo el estado vive en cookies, nunca en la base

A diferencia de `solicitudes_creacion` (que sí necesitó una tabla para el
historial), acá no se persiste nada: el estado "modo espejo activo, con
tal cuenta de retorno, vence a tal hora" vive completo en las cookies del
navegador. Esto es intencional — simplifica el diseño y evita dejar
registros de sesión sensibles en la base de datos más allá del log de
auditoría (que sí es deseable, ver §11).

### 3.3 Defensa en profundidad para la expiración de 2 horas

El requisito pedía confirmar si alcanza con que las cookies expiren solas
a las 2 horas, o si hace falta invalidar del lado servidor. Decisión: se
hacen **las dos cosas**:

1. Las cookies (`mybbuser` sobrescrita y la de retorno) se crean con TTL
   de 7200 segundos — el navegador las descarta solo.
2. Además, la cookie de retorno lleva un timestamp de expiración
   embebido, y el hook del plugin (que ya corre en cada request para
   mostrar el banner) lo revisa en cada página: si ya venció, fuerza la
   restauración de la sesión de FID 315 en ese mismo momento, sin esperar
   a que el navegador borre la cookie por su cuenta.

Esto cubre el caso de reloj desincronizado, manipulación manual de
`Expires`, o cualquier diferencia entre cuándo el navegador decide
"esta cookie venció" y cuándo el servidor lo decide.

## 4. Formato de las cookies

| Cookie | httponly | TTL | Contenido |
|---|---|---|---|
| `mybbuser` | sí (ya lo es, cookie nativa de MyBB) | 7200s durante el modo espejo | `"<uid_objetivo>_<loginkey_objetivo>"` — el valor real de sesión de MyBB, sobrescrito temporalmente |
| `modo_espejo_retorno` | sí | 7200s | `"<mybbuser_original_de_315>\|<expira_ts>"` — el valor completo y ya armado de la cookie `mybbuser` de FID 315 (capturado tal cual antes de sobrescribirla), más el timestamp de expiración |
| `modo_espejo_activo` | sí | 7200s | `"<uid_objetivo>\|<username_objetivo>\|<expira_ts>"` — solo para que el hook arme el texto del banner sin tener que consultar la base en cada request |

Separador `|` (distinto del `_` que ya usa el formato nativo de
`mybbuser`) para poder partir los campos sin ambigüedad.

`modo_espejo_activo` no es información sensible por sí sola (no lleva
ningún `loginkey`) — existe solo para pintar el banner sin una consulta
extra a la base en cada carga de página. Igual se marca `httponly` porque
no hay ninguna razón para que JavaScript la necesite.

## 5. Flujo: Activar

**Archivo:** `op/staff/modo_espejo.php`, acción `activar` (POST).

```php
if ($mybb->request_method === 'post' && $mybb->get_input('accion') === 'activar') {
    verify_post_check($mybb->get_input('my_post_key'));

    if ((int) $mybb->user['uid'] !== 315) {
        // Defensa redundante: el gate de la página ya bloqueó esto antes,
        // pero la mutación también se valida sola (mismo patrón que
        // op_solicitudes_creacion_resolver()).
        error_no_permission();
    }

    $objetivoFid = (int) $mybb->get_input('objetivo_fid', MyBB::INPUT_INT);
    if ($objetivoFid <= 0 || $objetivoFid === 315) {
        // error: FID invalido o intento de reflejarse a si mismo
    }

    $objetivo = $db->fetch_array($db->simple_select(
        'users', 'uid,username,loginkey', "uid='{$objetivoFid}'", array('limit' => 1)
    ));
    if (!$objetivo) {
        // error: usuario no existe
    }

    $expiraEn = TIME_NOW + 7200;
    $retornoValor = $mybb->cookies['mybbuser'] . '|' . $expiraEn;
    my_setcookie('modo_espejo_retorno', $retornoValor, 7200, true);
    my_setcookie('modo_espejo_activo', $objetivo['uid'] . '|' . $objetivo['username'] . '|' . $expiraEn, 7200, true);
    my_setcookie('mybbuser', $objetivo['uid'] . '_' . $objetivo['loginkey'], 7200, true);

    log_audit(315, $mybb->user['username'], '[ModoEspejo][Activado]',
        "Objetivo: FID {$objetivo['uid']} ({$objetivo['username']}). Expira: " . my_date('d/m/Y H:i', $expiraEn) . '.');

    header('Location: ' . $mybb->settings['bburl'] . '/index.php');
    exit;
}
```

Puntos clave:

- `$mybb->cookies['mybbuser']` ya contiene el valor crudo de la cookie
  entrante (MyBB lo puebla en el bootstrap antes de correr esta página) —
  se captura tal cual, no se reconstruye a mano con el `loginkey` de FID
  315, para no arriesgarse a armarlo mal.
- El `loginkey` del objetivo se lee de la base en el momento, nunca se
  cachea ni se pasa por el cliente en ningún formulario.
- Las tres cookies se escriben con el mismo TTL (7200s) para que expiren
  juntas.

## 6. Flujo: Volver

**Archivo:** `op/staff/modo_espejo.php`, acción `volver` (POST).

Esta acción **no valida `$mybb->user['uid'] === 315`** — durante el modo
espejo, ese UID es el del usuario objetivo, no 315. Se valida por la
cookie de retorno.

```php
if ($mybb->request_method === 'post' && $mybb->get_input('accion') === 'volver') {
    verify_post_check($mybb->get_input('my_post_key'));

    // op_modo_espejo_parsear_retorno() y op_modo_espejo_loginkey_admin_valido()
    // viven en el plugin (inc/plugins/op_modo_espejo.php) y las reusa tanto
    // "Volver" como la expiracion forzada del hook (ver Seccion 7.2) — una
    // sola implementacion de "parsear y validar la cookie de retorno".
    $retorno = op_modo_espejo_parsear_retorno($mybb->cookies['modo_espejo_retorno'] ?? '');
    $objetivoUidQueSeDejaba = (int) $mybb->user['uid']; // todavia es el reflejado en este request

    my_unsetcookie('modo_espejo_retorno');
    my_unsetcookie('modo_espejo_activo');

    if (!$retorno) {
        // error: no habia modo espejo activo (o la cookie estaba corrupta/manipulada)
        my_unsetcookie('mybbuser');
    } elseif (!op_modo_espejo_loginkey_admin_valido($retorno['admin_loginkey'])) {
        // La sesion original ya no es valida (ej. la contrasena de FID 315
        // cambio y regenero el loginkey desde que se activo el modo espejo).
        // No se restaura nada invalido: se pide iniciar sesion de nuevo.
        my_unsetcookie('mybbuser');
    } else {
        my_setcookie('mybbuser', $retorno['mybbuser_original'], 0, true); // 0 => TTL por defecto (1 ano, ver my_setcookie)
        log_audit(315, 'FID 315', '[ModoEspejo][Finalizado]',
            "Se dejo de reflejar FID {$objetivoUidQueSeDejaba}.");
    }

    header('Location: ' . $mybb->settings['bburl'] . '/index.php');
    exit;
}
```

Nota sobre el TTL al restaurar: `my_setcookie('mybbuser', $valor, 0, ...)`
usa el default de `my_setcookie()` (~1 año, ver §2) en vez de otros 7200
segundos — la sesión de FID 315 vuelve a comportarse como una sesión
normal "recordada", no como una sesión de 2 horas.

## 7. Hook del plugin: banner, expiración forzada y limpieza en logout

### 7.1 Logout nativo no limpia las cookies propias

Confirmado leyendo `member.php` (líneas 1950-1984): la acción
`action=logout` corre `my_unsetcookie("mybbuser")` y dispara el hook
`member_logout_end` justo antes de redirigir. Ese flujo no sabe nada de
`modo_espejo_retorno` / `modo_espejo_activo` — si se cierra sesión con el
logout normal de MyBB mientras el modo espejo está activo, esas dos
cookies quedan huérfanas (el botón "Volver" del banner seguiría
funcionando igual, porque no depende de estar logueado, pero el banner
quedaría mostrando "activo" durante un momento confuso hasta que se
presione "Volver").

Se engancha `member_logout_end` para limpiar esas dos cookies en el mismo
momento en que se cierra sesión:

```php
$plugins->add_hook('member_logout_end', 'op_modo_espejo_hook_logout');

function op_modo_espejo_hook_logout()
{
    global $mybb;

    if (!empty($mybb->cookies['modo_espejo_retorno']) || !empty($mybb->cookies['modo_espejo_activo'])) {
        my_unsetcookie('modo_espejo_retorno');
        my_unsetcookie('modo_espejo_activo');
    }
}
```

No hace falta revalidar nada acá (a diferencia de "Volver" manual/forzado)
— cerrar sesión ya deja al navegador sin `mybbuser`, así que no hay ningún
estado de sesión que restaurar; solo se limpia el rastro de las cookies
propias de la feature.

### 7.2 Banner + expiración forzada

**Archivo:** `inc/plugins/op_modo_espejo.php`

```php
// Helpers compartidos con la accion "volver" de op/staff/modo_espejo.php
// (que los consume via require_once de este mismo plugin — ver Seccion 6).
function op_modo_espejo_parsear_retorno($valor)
{
    if (empty($valor) || strpos($valor, '|') === false) {
        return null;
    }

    list($mybbuserOriginal, $expiraTs) = explode('|', $valor, 2);
    list($adminUid, $adminLoginkey) = array_pad(explode('_', $mybbuserOriginal, 2), 2, '');

    if ((int) $adminUid !== 315 || $adminLoginkey === '') {
        return null;
    }

    return array(
        'mybbuser_original' => $mybbuserOriginal,
        'admin_loginkey' => $adminLoginkey,
        'expira_ts' => (int) $expiraTs,
    );
}

function op_modo_espejo_loginkey_admin_valido($adminLoginkey)
{
    global $db;

    $admin = $db->fetch_array($db->simple_select('users', 'loginkey', "uid='315'", array('limit' => 1)));
    return $admin && hash_equals((string) $admin['loginkey'], (string) $adminLoginkey);
}

$plugins->add_hook('global_intermediate', 'op_modo_espejo_hook_header');

function op_modo_espejo_hook_header()
{
    global $mybb, $op_modo_espejo_banner;
    $op_modo_espejo_banner = '';

    if (empty($mybb->cookies['modo_espejo_retorno']) || empty($mybb->cookies['modo_espejo_activo'])) {
        return;
    }

    $retorno = op_modo_espejo_parsear_retorno($mybb->cookies['modo_espejo_retorno']);
    if (!$retorno) {
        my_unsetcookie('modo_espejo_retorno');
        my_unsetcookie('modo_espejo_activo');
        return;
    }

    if (TIME_NOW > $retorno['expira_ts']) {
        op_modo_espejo_forzar_retorno($retorno);
        return;
    }

    list($objetivoUid, $objetivoUsername) = array_pad(explode('|', $mybb->cookies['modo_espejo_activo'], 2), 2, '');
    $post_key_espejo = generate_post_check();
    $username_esc = htmlspecialchars((string) $objetivoUsername, ENT_QUOTES, 'UTF-8');

    $op_modo_espejo_banner = '<div class="modo-espejo-aviso">'
        . 'Modo espejo activo &mdash; Estas viendo el foro como <strong>' . $username_esc . '</strong>. '
        . '<form method="post" action="' . $mybb->settings['bburl'] . '/op/staff/modo_espejo.php" style="display:inline;">'
        . '<input type="hidden" name="my_post_key" value="' . $post_key_espejo . '">'
        . '<input type="hidden" name="accion" value="volver">'
        . '<button type="submit">Volver a tu cuenta</button>'
        . '</form></div>';
}

function op_modo_espejo_forzar_retorno($retorno)
{
    my_unsetcookie('modo_espejo_retorno');
    my_unsetcookie('modo_espejo_activo');

    if (op_modo_espejo_loginkey_admin_valido($retorno['admin_loginkey'])) {
        my_setcookie('mybbuser', $retorno['mybbuser_original'], 0, true);
    } else {
        my_unsetcookie('mybbuser'); // sesion de origen ya no es valida, no dejar al usuario reflejado logueado
    }
}
```

Notas:

- Este hook corre en **todas** las páginas (`global_intermediate`), igual
  que `op_bitacora`/`op_solicitudes_creacion` — el costo es mínimo: sin
  cookies de modo espejo, la función devuelve de inmediato sin tocar la
  base. Con cookies presentes, es solo un `explode()` salvo que haya que
  forzar el retorno (un `SELECT` puntual).
- `op_modo_espejo_forzar_retorno()` cambia las cookies para el **próximo**
  request — el request actual, que ya cargó `$mybb->user` como el
  objetivo antes de que corriera este hook, sigue viéndose como el
  objetivo por esta única carga de página. La siguiente carga ya refleja
  la sesión restaurada (o de invitado, si el `loginkey` de FID 315 ya no
  es válido). Aceptable: es el mismo comportamiento de "hasta el próximo
  request" que ya tiene cualquier cambio de cookie en PHP.

## 8. Página `/op/staff/modo_espejo.php`

### 8.1 Acceso

Gate **exclusivo**, no reutiliza `is_staff()`/`is_mod()`/`is_user()`:

```php
if ((int) $mybb->user['uid'] !== 315) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}
```

Este gate cubre la carga de la página y la acción `activar`. La acción
`volver` vive en el mismo archivo pero **fuera** de este `if` (ver §6),
porque debe funcionar incluso cuando `$mybb->user['uid']` ya no es 315.

### 8.2 Composición

Página mínima, sin necesidad de plantilla elaborada:

1. Si `modo_espejo_activo` está seteada: aviso "Ya estás reflejando a
   {usuario}" + botón "Volver" (aunque el banner del header ya cubre
   esto, tenerlo también acá evita confusión si alguien llega
   directamente a esta URL).
2. Si no: formulario simple — input numérico "FID del usuario objetivo" +
   botón "Activar modo espejo", con `confirm()` en el submit dado lo
   sensible de la acción.

## 9. Contrato HTTP

| Método | Acción | Resultado |
|---|---|---|
| `GET` | página | Formulario (si `uid===315`) o estado actual |
| `POST` | `accion=activar&objetivo_fid=X` | Sobrescribe `mybbuser`, crea cookies de retorno/activo, loguea |
| `POST` | `accion=volver` | Restaura `mybbuser` desde la cookie de retorno, la limpia, loguea |

Ambas acciones exigen `verify_post_check($mybb->get_input('my_post_key'))`.

## 10. Validaciones

**Activar:**
1. `$mybb->user['uid'] === 315` (dos veces: gate de página y dentro de la
   propia mutación).
2. `my_post_key` válido.
3. `objetivo_fid` entero positivo, distinto de 315.
4. El usuario objetivo existe en `mybb_users`.

**Volver:**
1. `my_post_key` válido (CSRF, con el post_code de la sesión actual —
   sea cual sea el UID activo en ese momento).
2. Existe la cookie `modo_espejo_retorno`.
3. El UID embebido en esa cookie es 315.
4. El `loginkey` embebido coincide con el `loginkey` **actual** de FID
   315 en la base — si no coincide (contraseña cambiada desde que se
   activó el modo espejo), no se restaura nada inválido; se limpia todo y
   se informa que hay que iniciar sesión de nuevo.

## 11. Seguridad

- `modo_espejo_retorno` y `modo_espejo_activo`: siempre `httponly`.
- El `loginkey` de FID 315 se revalida contra la base en cada "Volver"
  (manual o forzado por expiración) — nunca se confía ciegamente en el
  contenido de la cookie.
- TTL de 2 horas en las tres cookies relevantes, más el chequeo activo en
  cada request vía el hook (§3.3, §7) como defensa en profundidad.
- El gate de "Activar" es un UID hardcodeado (315), no un grupo ni
  `is_staff()` — nadie más puede activar el modo espejo aunque tenga
  permisos de staff o admin.
- `log_audit()` en cada activación y cada retorno (manual o forzado),
  con el UID objetivo en el texto del log.
- Ningún dato sensible (contraseña, `loginkey` del objetivo) se expone en
  HTML, URL ni JavaScript — todo el manejo de `loginkey` pasa por cookies
  `httponly` y consultas server-side.
- No se toca `global.php`: el banner y la expiración forzada corren desde
  el plugin (`global_intermediate`), igual que `op_bitacora` /
  `op_solicitudes_creacion`.

## 12. Casos límite

- **FID 315 intenta reflejarse a sí mismo**: rechazado explícitamente
  (`objetivo_fid === 315`).
- **FID objetivo no existe / fue eliminado**: rechazado en `activar`.
- **FID 315 cambia su contraseña mientras el modo espejo está activo**:
  su `loginkey` se regenera: al intentar "Volver" (manual o forzado), la
  validación de `loginkey` falla y no se restaura una sesión rota — se
  limpia todo y se pide iniciar sesión de nuevo con normalidad.
- **El navegador nunca vuelve a hacer un request dentro de las 2 horas**:
  las cookies expiran solas del lado del navegador; no queda ningún
  estado en el servidor que necesite limpieza (no hay tabla).
- **Se supera la marca de 2 horas y el usuario sigue navegando**: el hook
  del plugin lo detecta en el primer request posterior a la expiración y
  fuerza el retorno antes de mostrar la página normalmente.
- **FID objetivo es baneado o su cuenta es eliminada mientras dura el modo
  espejo**: no se agrega manejo especial — el comportamiento que resulte
  de que MyBB cargue esa cuenta baneada/eliminada en `load_user()` en el
  siguiente request es el mismo que tendría esa cuenta si estuviera
  logueada normalmente. Fuera de alcance revisar esto en el MVP.
- **Se usa el "Cerrar sesión" normal de MyBB en vez del botón "Volver"
  mientras el modo espejo está activo**: el logout nativo limpia
  `mybbuser` pero no las dos cookies propias de esta feature. Se resuelve
  enganchando `member_logout_end` (ver §7.1) para limpiarlas también —
  después de este fix no quedan cookies huérfanas en ningún caso.

## 13. Estrategia de pruebas

- **Activar**: confirmar que la sesión pasa a ser la del objetivo (avatar,
  username, permisos correctos) sin haber tocado su fila en `mybb_users`
  más allá de la lectura.
- **Volver manual**: confirmar que la sesión vuelve a FID 315 con sus
  permisos completos.
- **Expiración pasiva**: activar, esperar (o simular) que pasen las 2
  horas, confirmar que un nuevo request sin cookies válidas cae a
  invitado o a la sesión restaurada según corresponda.
- **Expiración forzada**: manipular manualmente el timestamp embebido en
  `modo_espejo_retorno` a un valor pasado y confirmar que el hook
  restaura la sesión en el siguiente request, sin esperar el TTL nativo
  de la cookie.
- **Cambio de contraseña de FID 315 en medio del modo espejo**: confirmar
  que "Volver" (manual y forzado) no restaura una sesión inválida y en
  cambio limpia todo pidiendo login nuevo.
- **Intento de acceso de cualquier otra cuenta**: confirmar que ningún
  otro UID, ni siquiera con `is_staff()`/`is_user()` en true, puede
  activar el modo espejo.
- **El usuario reflejado no ve nada nuevo**: confirmar que, iniciando
  sesión normalmente como el usuario que fue reflejado (con su propia
  contraseña, en otro navegador), no aparece ninguna opción de cambiar a
  la cuenta de FID 315 ni rastro del modo espejo en su cuenta.
- **Banner**: confirmar que aparece debajo de `#peticiones_staff` tanto
  para objetivos con `$g_is_staff` verdadero como falso.
- **CSRF**: confirmar que `activar` y `volver` rechazan un POST sin
  `my_post_key` válido.
- **Logout nativo durante el modo espejo**: activar, usar el "Cerrar
  sesión" normal de MyBB (no el botón "Volver"), confirmar que
  `modo_espejo_retorno` y `modo_espejo_activo` quedan limpias y el banner
  desaparece.

## 14. Fases de implementación

### Fase 1: mecanismo de sesión
- `op/staff/modo_espejo.php` con `activar`/`volver`, sin banner todavía.
- Verificación manual del cambio de sesión y el retorno.

### Fase 2: expiración
- TTL de 2 horas en las tres cookies.
- Hook del plugin con la expiración forzada del lado servidor.

### Fase 3: banner y auditoría
- `inc/plugins/op_modo_espejo.php`: banner en el header, insertado justo
  después de `#peticiones_staff`.
- `log_audit()` en activar/volver (manual y forzado).
- Hook `member_logout_end` para limpiar las cookies propias si se cierra
  sesión con el logout nativo en vez del botón "Volver".

## 15. Fuera de alcance

- Cualquier forma de que el usuario reflejado sepa que está siendo
  reflejado (el diseño es explícitamente invisible para esa cuenta).
- Permitir que alguna otra cuenta, aunque sea de máxima jerarquía de
  staff, use esta herramienta — es exclusiva de FID 315 por diseño.
- Manejo especial para cuentas baneadas/eliminadas como objetivo.
- Historial/UI de "modos espejo pasados" más allá de lo que ya queda en
  `mybb_op_audit_general` vía `log_audit()`.
- Ajustar el tiempo de expiración desde alguna pantalla de configuración
  — las 2 horas quedan como constante en código.
