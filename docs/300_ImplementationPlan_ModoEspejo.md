# Modo Espejo: plan de implementación

> Requisitos: [100_Requirements_ModoEspejo.md](100_Requirements_ModoEspejo.md)
>
> Diseño: [200_DesignPlan_ModoEspejo.md](200_DesignPlan_ModoEspejo.md)

## 1. Objetivo de este documento

Este plan convierte el diseño aprobado en tareas de código ordenadas y
verificables. Define archivos, funciones, cookies y pruebas, pero no
implementa todavía nada.

La implementación se considera terminada cuando:

- FID 315, y solo FID 315, puede activar el modo espejo sobre cualquier
  otra cuenta desde `/op/staff/modo_espejo.php`;
- al activarlo, la sesión del navegador pasa a ser la del usuario
  objetivo sin haber tocado su contraseña ni su `loginkey`;
- el botón "Volver" (en el banner o en la propia página) restaura la
  sesión de FID 315 en cualquier momento, revalidando su `loginkey`
  contra la base antes de restaurar;
- sin acción manual, el modo espejo expira solo a las 2 horas — tanto por
  vencimiento de cookies como por el chequeo activo del lado servidor;
- un aviso permanece visible en el sitio (debajo de `#peticiones_staff`)
  mientras el modo espejo está activo, sin importar el grupo del usuario
  reflejado;
- cerrar sesión con el logout nativo de MyBB no deja cookies huérfanas;
- cada activación y cada retorno (manual o forzado) queda en
  `mybb_op_audit_general` vía `log_audit()`;
- `global.php` no se modifica en absoluto.

## 2. Archivos

### 2.1 Crear

| Archivo | Responsabilidad |
|---|---|
| `inc/plugins/op_modo_espejo.php` | Hooks: banner del header, expiración forzada, limpieza en logout |
| `op/staff/modo_espejo.php` | Página + acciones `activar`/`volver` |

### 2.2 Modificar

| Archivo | Cambio |
|---|---|
| `templates/One_Piece_Gaiden_Templates/header.html` | Insertar `{$op_modo_espejo_banner}` justo después del `</if>` que cierra el bloque `#peticiones_staff` (línea 999), **fuera** de ese `<if>` |

### 2.3 No se toca

- `global.php` — el banner y la expiración forzada corren enteros desde
  el plugin, vía hook `global_intermediate`.
- No hay tablas ni migraciones SQL nuevas — todo el estado vive en
  cookies (ver diseño §3.2).
- `inc/plugins/accountswitcher.php` — se evaluó y se descartó, no se
  integra ni se modifica.

## 3. Convenciones de implementación

- Prefijo PHP: `op_modo_espejo_`.
- UID de administrador: constante, no repetir el literal `315` suelto en
  el código.

```php
if (!defined('OP_MODO_ESPEJO_ADMIN_UID')) {
    define('OP_MODO_ESPEJO_ADMIN_UID', 315);
}
if (!defined('OP_MODO_ESPEJO_TTL')) {
    define('OP_MODO_ESPEJO_TTL', 7200); // 2 horas, en segundos
}
```

- Nombres de cookies como constantes también:

```php
define('OP_MODO_ESPEJO_COOKIE_RETORNO', 'modo_espejo_retorno');
define('OP_MODO_ESPEJO_COOKIE_ACTIVO', 'modo_espejo_activo');
```

- Separador `|` para los campos compuestos dentro de una cookie (nunca
  `_`, que ya lo usa el formato nativo `uid_loginkey` de `mybbuser`).
- Todas las cookies de esta feature: `httponly = true`. Nunca exponer
  `loginkey` (propio ni ajeno) en HTML, URL o JavaScript.
- `IN_MYBB` guard al inicio de ambos archivos nuevos, igual que el resto
  del repo.
- IDs (`objetivo_fid`): enteros antes de entrar en cualquier query.
- Todo POST exige `verify_post_check($mybb->get_input('my_post_key'))`.

## 4. Tarea 1: plugin (`inc/plugins/op_modo_espejo.php`)

### 4.1 Registro

```php
if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

define('OP_MODO_ESPEJO_ADMIN_UID', 315);
define('OP_MODO_ESPEJO_TTL', 7200);
define('OP_MODO_ESPEJO_COOKIE_RETORNO', 'modo_espejo_retorno');
define('OP_MODO_ESPEJO_COOKIE_ACTIVO', 'modo_espejo_activo');

$plugins->add_hook('global_intermediate', 'op_modo_espejo_hook_header');
$plugins->add_hook('member_logout_end', 'op_modo_espejo_hook_logout');
```

No hace falta instalar tablas ni plantillas — es un plugin sin ciclo de
instalación real. `op_modo_espejo_info()` alcanza para que aparezca en la
lista de plugins del ACP:

```php
function op_modo_espejo_info()
{
    return array(
        'name' => 'OPG - Modo espejo',
        'description' => 'Permite a FID 315 reflejar la sesión de cualquier usuario para debug, sin tocar contraseñas.',
        'website' => '',
        'author' => 'OPG',
        'authorsite' => '',
        'version' => '1.0',
        'compatibility' => '18*',
    );
}
```

### 4.2 Helper: parsear la cookie de retorno

Función compartida entre el hook de expiración y el flujo de "Volver" de
la página (ambos necesitan parsear/validar `modo_espejo_retorno`):

```php
function op_modo_espejo_parsear_retorno($valor)
{
    if (empty($valor) || strpos($valor, '|') === false) {
        return null;
    }

    list($mybbuserOriginal, $expiraTs) = explode('|', $valor, 2);
    list($adminUid, $adminLoginkey) = array_pad(explode('_', $mybbuserOriginal, 2), 2, '');

    if ((int) $adminUid !== OP_MODO_ESPEJO_ADMIN_UID || $adminLoginkey === '') {
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

    $admin = $db->fetch_array($db->simple_select(
        'users', 'loginkey', "uid='" . OP_MODO_ESPEJO_ADMIN_UID . "'", array('limit' => 1)
    ));

    return $admin && hash_equals((string) $admin['loginkey'], (string) $adminLoginkey);
}
```

`hash_equals()` en vez de `===` para comparar el `loginkey` — es una
comparación de igualdad de strings potencialmente sensibles a timing, y
ya que PHP lo trae nativo no cuesta nada usarlo acá en vez de un `===`
simple.

### 4.3 Hook de header: banner + expiración forzada

```php
function op_modo_espejo_hook_header()
{
    global $mybb, $op_modo_espejo_banner;
    $op_modo_espejo_banner = '';

    $retornoRaw = $mybb->cookies[OP_MODO_ESPEJO_COOKIE_RETORNO] ?? '';
    $activoRaw = $mybb->cookies[OP_MODO_ESPEJO_COOKIE_ACTIVO] ?? '';
    if ($retornoRaw === '' || $activoRaw === '') {
        return;
    }

    $retorno = op_modo_espejo_parsear_retorno($retornoRaw);
    if (!$retorno) {
        // Cookie corrupta/manipulada: no confiar, limpiar y salir.
        my_unsetcookie(OP_MODO_ESPEJO_COOKIE_RETORNO);
        my_unsetcookie(OP_MODO_ESPEJO_COOKIE_ACTIVO);
        return;
    }

    if (TIME_NOW > $retorno['expira_ts']) {
        op_modo_espejo_forzar_retorno($retorno);
        return;
    }

    list($objetivoUid, $objetivoUsername) = array_pad(explode('|', $activoRaw, 2), 2, '');
    $usernameEsc = htmlspecialchars((string) $objetivoUsername, ENT_QUOTES, 'UTF-8');
    $postKey = generate_post_check();

    $op_modo_espejo_banner = '<div id="modo_espejo_aviso" style="text-align:center;width:1100px;margin:auto;'
        . 'background-color:#7a1f1f;color:#fff;padding:6px 0;">'
        . 'Modo espejo activo &mdash; Estas viendo el foro como <strong>' . $usernameEsc . '</strong>. '
        . '<form method="post" action="' . $mybb->settings['bburl'] . '/op/staff/modo_espejo.php" style="display:inline;">'
        . '<input type="hidden" name="my_post_key" value="' . $postKey . '">'
        . '<input type="hidden" name="accion" value="volver">'
        . '<button type="submit">Volver a tu cuenta</button>'
        . '</form></div>';
}

function op_modo_espejo_forzar_retorno($retorno)
{
    my_unsetcookie(OP_MODO_ESPEJO_COOKIE_RETORNO);
    my_unsetcookie(OP_MODO_ESPEJO_COOKIE_ACTIVO);

    if (op_modo_espejo_loginkey_admin_valido($retorno['admin_loginkey'])) {
        my_setcookie('mybbuser', $retorno['mybbuser_original'], 0, true);
    } else {
        my_unsetcookie('mybbuser');
    }
}
```

Nota de estilo (ver `docs/style.md`/convención del proyecto en
`header.html`): el ancho fijo `1100px` y la paleta de colores deben
alinearse con `#peticiones_staff` (que ya usa ese mismo ancho) — ajustar
en la Tarea 3 según lo que se vea mejor en pantalla, esto es solo un
punto de partida funcional, no el diseño visual final.

### 4.4 Hook de logout

```php
function op_modo_espejo_hook_logout()
{
    global $mybb;

    if (!empty($mybb->cookies[OP_MODO_ESPEJO_COOKIE_RETORNO]) || !empty($mybb->cookies[OP_MODO_ESPEJO_COOKIE_ACTIVO])) {
        my_unsetcookie(OP_MODO_ESPEJO_COOKIE_RETORNO);
        my_unsetcookie(OP_MODO_ESPEJO_COOKIE_ACTIVO);
    }
}
```

### 4.5 Verificación

```bash
php -l inc/plugins/op_modo_espejo.php
```

En MyBB:
1. instalar/activar el plugin desde el ACP (sin tablas, debería ser
   instantáneo);
2. confirmar que aparece en la lista de plugins activos;
3. sin cookies de modo espejo, confirmar que ninguna página muestra el
   banner ni hace queries de más (el hook debe salir en la primera
   condición).

## 5. Tarea 2: página `op/staff/modo_espejo.php`

### 5.1 Bootstrap y gate de "Activar"

```php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'modo_espejo.php');
require_once "./../../global.php";
require_once "./../functions/op_functions.php";
require_once MYBB_ROOT . 'inc/plugins/op_modo_espejo.php';

global $templates, $mybb, $db;

$accion = $mybb->get_input('accion', MyBB::INPUT_STRING);

// "volver" se procesa ANTES del gate de uid===315, porque durante el modo
// espejo el uid activo ya no es 315 (ver diseno Seccion 6).
if ($mybb->request_method === 'post' && $accion === 'volver') {
    // ver 5.3
}

if ((int) $mybb->user['uid'] !== OP_MODO_ESPEJO_ADMIN_UID) {
    eval("\$page = \"".$templates->get("sin_permisos")."\";");
    output_page($page);
    exit;
}
```

Importante: `require_once` del plugin (no de un `functions.php` aparte,
ya que este plugin es lo bastante chico para no separar el motor —
decisión de diseño §3.1) para reusar `op_modo_espejo_parsear_retorno()` y
`op_modo_espejo_loginkey_admin_valido()` sin duplicar código.

### 5.2 Acción `activar` (dentro del gate `uid === 315`)

```php
if ($mybb->request_method === 'post' && $accion === 'activar') {
    verify_post_check($mybb->get_input('my_post_key'));

    $objetivoFid = (int) $mybb->get_input('objetivo_fid', MyBB::INPUT_INT);
    $error = '';
    if ($objetivoFid <= 0) {
        $error = 'Falta el FID objetivo.';
    } elseif ($objetivoFid === OP_MODO_ESPEJO_ADMIN_UID) {
        $error = 'No podes activar el modo espejo sobre tu propia cuenta.';
    }

    $objetivo = null;
    if ($error === '') {
        $objetivo = $db->fetch_array($db->simple_select(
            'users', 'uid,username,loginkey', "uid='{$objetivoFid}'", array('limit' => 1)
        ));
        if (!$objetivo) {
            $error = 'Ese FID no existe.';
        }
    }

    if ($error === '') {
        $expiraEn = TIME_NOW + OP_MODO_ESPEJO_TTL;
        $retornoValor = $mybb->cookies['mybbuser'] . '|' . $expiraEn;

        my_setcookie(OP_MODO_ESPEJO_COOKIE_RETORNO, $retornoValor, OP_MODO_ESPEJO_TTL, true);
        my_setcookie(OP_MODO_ESPEJO_COOKIE_ACTIVO, $objetivo['uid'] . '|' . $objetivo['username'] . '|' . $expiraEn, OP_MODO_ESPEJO_TTL, true);
        my_setcookie('mybbuser', $objetivo['uid'] . '_' . $objetivo['loginkey'], OP_MODO_ESPEJO_TTL, true);

        log_audit(
            OP_MODO_ESPEJO_ADMIN_UID,
            $mybb->user['username'],
            '[ModoEspejo][Activado]',
            "Objetivo: FID {$objetivo['uid']} ({$objetivo['username']}). Expira: " . my_date('d/m/Y H:i', $expiraEn) . '.'
        );

        header('Location: ' . $mybb->settings['bburl'] . '/index.php');
        exit;
    }
    // si $error !== '', cae al render normal de la pagina con el mensaje
}
```

### 5.3 Acción `volver` (fuera del gate, antes de él en el archivo)

```php
if ($mybb->request_method === 'post' && $accion === 'volver') {
    verify_post_check($mybb->get_input('my_post_key'));

    $retorno = op_modo_espejo_parsear_retorno($mybb->cookies[OP_MODO_ESPEJO_COOKIE_RETORNO] ?? '');
    $objetivoUidQueSeDejaba = (int) $mybb->user['uid'];

    my_unsetcookie(OP_MODO_ESPEJO_COOKIE_RETORNO);
    my_unsetcookie(OP_MODO_ESPEJO_COOKIE_ACTIVO);

    if ($retorno && op_modo_espejo_loginkey_admin_valido($retorno['admin_loginkey'])) {
        my_setcookie('mybbuser', $retorno['mybbuser_original'], 0, true);
        log_audit(
            OP_MODO_ESPEJO_ADMIN_UID,
            'FID ' . OP_MODO_ESPEJO_ADMIN_UID,
            '[ModoEspejo][Finalizado]',
            "Se dejo de reflejar FID {$objetivoUidQueSeDejaba}."
        );
    } else {
        my_unsetcookie('mybbuser');
    }

    header('Location: ' . $mybb->settings['bburl'] . '/index.php');
    exit;
}
```

Nota: como esta acción corre antes del `require_once` de plantillas /
gate, tiene que ir después del `require_once "./../../global.php"` (para
tener `$db`, `$mybb`, `verify_post_check`, `my_setcookie` disponibles) pero
antes de cualquier chequeo de permisos — ver el orden completo armado en
§5.1.

### 5.4 GET: formulario

```php
$yaActivo = !empty($mybb->cookies[OP_MODO_ESPEJO_COOKIE_ACTIVO]);
$post_key = generate_post_check();

// Render simple: si $yaActivo, mostrar "Ya estas reflejando a X" + boton volver.
// Si no, formulario con input numerico objetivo_fid + boton activar (confirm() en el submit).
```

Página mínima — no hace falta una plantilla `.html` aparte necesariamente;
se puede resolver con HTML inline en el propio archivo PHP (mismo nivel
de sofisticación que otras herramientas chicas de `/op/staff/`), o con
una plantilla `staff_modo_espejo` si se prefiere consistencia con el
resto del panel. Definir en el momento de escribir el código — no
bloquea el resto del plan.

### 5.5 Verificación

```bash
php -l op/staff/modo_espejo.php
```

## 6. Tarea 3: `header.html`

Insertar, inmediatamente después de la línea 999 (el `</if>` que cierra
`<if $g_is_staff then>` de `#peticiones_staff`) y antes de la línea 1000
(`<!-- {op_temas_header} -->`):

```html
{$op_modo_espejo_banner}
```

**Fuera** de cualquier `<if $g_is_staff then>` — el banner debe mostrarse
sin importar el grupo del usuario reflejado (ver diseño §2).

## 7. Tarea 4: pruebas

### 7.1 Comprobaciones estáticas

```bash
php -l inc/plugins/op_modo_espejo.php
php -l op/staff/modo_espejo.php
git diff --check
```

Buscar el UID hardcodeado suelto fuera de la constante:

```bash
rg -n "uid.{0,3}=.{0,3}315|315.{0,3}==" inc/plugins/op_modo_espejo.php op/staff/modo_espejo.php
```

Cualquier `315` que no sea la definición de `OP_MODO_ESPEJO_ADMIN_UID` es
una señal de que se coló hardcodeado en vez de usar la constante.

### 7.2 Matriz funcional mínima

Usar dos cuentas de prueba (nunca las de producción): A = FID 315 (o una
copia de prueba), B = un usuario cualquiera.

| Paso | Acción | Resultado esperado |
|---|---|---|
| 1 | A activa modo espejo sobre B | La sesión pasa a ser la de B (avatar, username, permisos de B) |
| 2 | Revisar cookies del navegador | `mybbuser` = B; `modo_espejo_retorno` y `modo_espejo_activo` presentes, `httponly` |
| 3 | Revisar `mybb_users` de B | `loginkey` de B sin cambios respecto a antes del paso 1 |
| 4 | Click en "Volver" del banner | La sesión vuelve a ser A, con sus permisos completos |
| 5 | A intenta `objetivo_fid` = 315 | Rechazado, mensaje de error, sin cambios de cookie |
| 6 | A intenta `objetivo_fid` inexistente | Rechazado, mensaje de error |
| 7 | Cualquier otra cuenta (staff o no) visita `modo_espejo.php` | `sin_permisos` |
| 8 | A activa modo espejo, cierra sesión con el logout normal (no "Volver") | `mybbuser` se limpia; `modo_espejo_retorno`/`modo_espejo_activo` también se limpian (Tarea 4.4) |
| 9 | Repetir paso 1, esperar (o simular) 2 horas, hacer un request cualquiera | El hook fuerza el retorno a A antes de renderizar la página |
| 10 | Repetir paso 1, cambiar la contraseña de A desde otra sesión, click "Volver" | No se restaura una sesión inválida; se limpia todo y se pide loguearse de nuevo |

### 7.3 Casos de seguridad

- POST `activar`/`volver` sin `my_post_key`;
- cookie `modo_espejo_retorno` manipulada a mano con un `uid` distinto de
  315 — `op_modo_espejo_parsear_retorno()` debe rechazarla;
- cookie `modo_espejo_retorno` con `loginkey` incorrecto — el retorno
  (manual o forzado) no debe restaurar la sesión;
- confirmar que el usuario B, logueado normalmente con su propia
  contraseña en otra sesión/navegador, no ve ninguna opción nueva ni
  ningún rastro del modo espejo en su cuenta.

### 7.4 UI

- confirmar que el banner aparece debajo de `#peticiones_staff` tanto
  para un B con `$g_is_staff` verdadero como falso;
- confirmar que el formulario de `activar` funciona en escritorio y
  móvil;
- confirmar que sin JavaScript el flujo completo (activar → banner →
  volver) sigue funcionando (todo es POST/redirect normal, sin fetch ni
  AJAX).

## 8. Tarea 5: despliegue

### 8.1 Antes de subir

1. revisar diff completo;
2. ejecutar `php -l` sobre los dos archivos nuevos y `git diff --check`;
3. probar el flujo completo en un ambiente de prueba con cuentas que no
   sean de producción, antes de tocar `header.html` en vivo;
4. confirmar el FID real de la cuenta administradora (315) contra la
   base de producción una vez más antes de desplegar.

### 8.2 Orden de despliegue

1. subir el plugin (`op_modo_espejo.php`);
2. subir la página (`modo_espejo.php`);
3. instalar/activar el plugin desde el ACP;
4. probar `activar`/`volver` en producción **sin** el banner todavía
   (antes de tocar `header.html`), para validar el mecanismo de cookies
   de forma aislada;
5. subir `header.html` con el placeholder;
6. validar que el banner aparece y el logout lo limpia correctamente.

### 8.3 Rollback

1. desactivar el plugin (el banner deja de renderizarse; el hook de
   expiración forzada deja de correr, pero las cookies igual expiran
   solas a las 2 horas por su TTL nativo);
2. si hace falta revertir ya mismo una sesión reflejada activa: borrar a
   mano la cookie `mybbuser` del navegador en cuestión, o simplemente
   esperar el TTL;
3. no hay tablas que journalear ni revertir — el rollback es solo de
   archivos.

## 9. Orden recomendado de commits

1. `Add op_modo_espejo plugin: header banner, forced expiry, logout cleanup`
2. `Add /op/staff/modo_espejo.php with activate/return actions`
3. `Insert modo espejo banner placeholder in header.html`

Cada commit debe pasar `php -l` y dejar el mecanismo en un estado
coherente. No mezclar esto con cambios no relacionados de `header.html`.

## 10. Definición de terminado por fase

### Fase 1: mecanismo de sesión
- `activar`/`volver` funcionando de punta a punta;
- gate exclusivo por UID verificado;
- ningún cambio en `mybb_users.loginkey` ni `.password` de ninguna
  cuenta.

### Fase 2: expiración
- TTL de 2 horas en las tres cookies;
- expiración forzada del lado servidor verificada con un timestamp
  manipulado.

### Fase 3: banner, logout y auditoría
- banner visible debajo de `#peticiones_staff`, para objetivos staff y
  no-staff;
- logout nativo limpia las cookies propias;
- `log_audit()` registrando cada activación y cada retorno.
