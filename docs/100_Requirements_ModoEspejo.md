# Requisito: Modo Espejo (sesión reflejada de otro usuario, solo cuenta administradora)

## Objetivo

El administrador del foro (FID 315, su propia cuenta) necesita poder
entrar a la sesión de cualquier usuario para hacer debug, sin cambiar la
contraseña de ese usuario y sin dejar ningún rastro de acceso permanente
en su cuenta.

Ningún otro usuario ni cuenta de staff debe tener esta capacidad — es
exclusiva de FID 315, verificado por UID hardcodeado, no por grupo ni por
`is_staff()`/`is_user()`. FID 315 puede activar el modo espejo sobre
cualquier cuenta del foro, sin excepciones (ni siquiera otras cuentas de
staff/admin quedan fuera del alcance).

## Por qué no sirve el Account Switcher ya instalado

El plugin **Enhanced Account Switcher** (`inc/plugins/accountswitcher.php`,
ya activo en el sitio) permite vincular dos cuentas como "master" /
"attached" desde el Admin CP, sin que el usuario objetivo intervenga. Se
evaluó como opción, pero se descartó: el vínculo es **bidireccional** — el
mismo mecanismo que le permite al master cambiar a la cuenta attached
también expone la opción de cambio del lado de la cuenta attached. Si el
usuario objetivo inicia sesión normalmente con su propia contraseña,
podría ver la opción de convertirse en la cuenta de administrador. Eso es
inaceptable para este caso de uso.

## Mecanismo técnico confirmado

Verificado leyendo `inc/class_session.php` (líneas ~88-154), no asumido:

- MyBB identifica una sesión logueada con la cookie `mybbuser`, cuyo valor
  tiene el formato `"<uid>_<loginkey>"`.
- `load_user($uid, $loginkey)` valida la sesión comparando ese `loginkey`
  contra la columna `loginkey` de `mybb_users` para ese `uid`. Si coincide,
  la sesión queda logueada como esa cuenta.
- `loginkey` **no es la contraseña** — es un token de sesión persistente
  que ya existe en cada fila de `mybb_users` independientemente de esta
  feature (es lo que sostiene "recordar sesión" en cualquier cuenta).

Esto permite armar una sesión válida de cualquier usuario objetivo
leyendo su `loginkey` actual de la base y escribiendo la cookie `mybbuser`
con ese valor — **sin tocar la contraseña ni el `loginkey` de nadie**, y
sin crear ningún vínculo persistente en la base de datos (a diferencia del
Account Switcher).

## Nombre y rutas

- Feature: **Modo espejo**.
- Herramienta: `/op/staff/modo_espejo.php`.
- Acciones: `accion=activar` (entrar a la sesión reflejada) y
  `accion=volver` (restaurar la sesión de FID 315).
- Plugin del aviso de header: `inc/plugins/op_modo_espejo.php` (mismo
  patrón que `op_bitacora` / `op_solicitudes_creacion`).

## Requisitos funcionales

1. **Activar modo espejo**: desde `/op/staff/modo_espejo.php`, la cuenta
   FID 315 ingresa el FID/UID de un usuario objetivo y, al confirmar, su
   sesión pasa a ser la de ese usuario (misma cookie `mybbuser`, sin tocar
   su contraseña). No hay restricción sobre qué cuentas pueden reflejarse.
2. **Volver**: en cualquier momento con el modo espejo activo, un
   botón/acción restaura la sesión original de FID 315 — sin necesidad de
   volver a loguearse a mano.
3. **Gate exclusivo, con dos chequeos distintos**: la acción de *activar*
   el modo espejo se valida con `$mybb->user['uid'] === 315` a secas, no
   con `is_staff()`, `is_mod()` ni `is_user()` (que dan acceso a más
   cuentas de las que corresponde acá). La acción de *volver* **no puede
   usar ese mismo chequeo** — con el modo espejo activo,
   `$mybb->user['uid']` ya no es 315, es el del usuario objetivo. "Volver"
   se valida en cambio por la presencia y validez de la cookie de retorno
   (ver §Restricciones de seguridad), sin importar qué UID esté activo en
   ese momento.
4. **Sin capacidad recíproca**: en ningún momento el usuario reflejado
   obtiene ninguna opción nueva en su propia cuenta ni ve que fue
   "vinculado" a nada — a diferencia del Account Switcher.
5. **Expiración automática a las 2 horas**: si no se pulsa "Volver" antes,
   el modo espejo debe terminar solo a las 2 horas de haberse activado y
   la sesión debe volver a ser la de FID 315. La forma más simple de
   lograrlo sin lógica de servidor adicional: la cookie de retorno (y la
   cookie `mybbuser` sobrescrita) se crean con una expiración de 2 horas —
   al vencer, el navegador las descarta y MyBB cae de nuevo a estado de
   invitado/pide login, en vez de quedar reflejando la sesión
   indefinidamente. Confirmar en diseño si además hace falta invalidar el
   modo espejo del lado servidor (por si el navegador nunca vuelve a
   hacer un request durante esas 2 horas) o si alcanza con la expiración
   de la cookie.
6. **Aviso visible durante el modo espejo**: mientras la sesión esté
   actuando como otro usuario, debe haber un aviso permanente en el sitio
   indicando "Modo espejo activo — Estás viendo el foro como {usuario} —
   Volver a tu cuenta", para no terminar posteando como otra persona sin
   darse cuenta. Ubicación: justo debajo de la barra naranja
   `#peticiones_staff` del header (ver
   `templates/One_Piece_Gaiden_Templates/header.html`), no dentro de ella.
7. **Registro de auditoría**: cada inicio y fin de modo espejo queda
   registrado (quién, a quién, cuándo) vía `log_audit()` o equivalente —
   es una acción sensible y debe quedar trazada igual que otras acciones
   de staff en el sistema.
8. **Sin tocar `global.php`**: el aviso del header se resuelve con un
   plugin nuevo (`global_intermediate`, mismo patrón ya usado en
   `op_bitacora` / `op_solicitudes_creacion`), no agregando lógica a
   `global.php`.

## Restricciones de seguridad

- La cookie de "retorno" (que guarda la sesión original de FID 315
  mientras dura el modo espejo) debe ser `httponly` — no debe ser legible
  por JavaScript.
- Antes de restaurar la sesión original con "Volver", hay que validar el
  `loginkey` guardado contra el valor actual de `mybb_users` para FID 315
  — no confiar ciegamente en el contenido de la cookie, aunque sea
  `httponly`, como defensa adicional ante manipulación de cookies a nivel
  de red/navegador.
- El modo espejo en curso bloquea naturalmente volver a usar la
  herramienta para activarlo de nuevo: como el gate es `uid === 315` y con
  el modo espejo activo la sesión es la del usuario objetivo (no la 315),
  la cuenta 315 no puede encadenar un segundo modo espejo sin antes volver
  a su propia cuenta. No hace falta lógica extra para esto — es una
  consecuencia directa del diseño del gate.
- Las cookies del modo espejo (retorno y la `mybbuser` sobrescrita) deben
  fijarse con una expiración de 2 horas (ver requisito #5), no como
  cookies de sesión sin vencimiento.
- CSRF: toda acción (`activar`, `volver`) debe ir protegida con
  `verify_post_check($mybb->get_input('my_post_key'))`, igual que el resto
  de acciones POST de `/op/staff/`.

## Pendiente antes de implementar

- [x] ¿Expira solo? — Sí, a las 2 horas si no se pulsa "Volver" (ver
      requisito #5).
- [x] ¿Alguna cuenta fuera de alcance? — No, FID 315 puede activar el modo
      espejo sobre cualquier cuenta sin excepción.
- [x] Ubicación del aviso — debajo de la barra `#peticiones_staff` del
      header (ver requisito #6). Falta afinar el texto/estilo visual
      exacto en la etapa de diseño.
- [x] Nombre final — **Modo espejo** (`/op/staff/modo_espejo.php`).
