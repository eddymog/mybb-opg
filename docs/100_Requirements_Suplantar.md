# Requisito: suplantación de identidad (solo cuenta administradora)

## Objetivo

El administrador del foro (FID 10, su propia cuenta) necesita poder entrar
a la sesión de cualquier usuario para hacer debug, sin cambiar la
contraseña de ese usuario y sin dejar ningún rastro de acceso permanente
en su cuenta.

Ningún otro usuario ni cuenta de staff debe tener esta capacidad — es
exclusiva de FID 10, verificado por UID hardcodeado, no por grupo ni por
`is_staff()`/`is_user()`.

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

## Requisitos funcionales

1. **Suplantar**: desde una herramienta nueva, la cuenta FID 10 ingresa el
   FID/UID de un usuario objetivo y, al confirmar, su sesión pasa a ser la
   de ese usuario (misma cookie `mybbuser`, sin tocar su contraseña).
2. **Volver**: en cualquier momento durante la suplantación, un botón/acción
   restaura la sesión original de FID 10 — sin necesidad de volver a
   loguearse a mano.
3. **Gate exclusivo, con dos chequeos distintos**: la acción de *empezar*
   a suplantar se valida con `$mybb->user['uid'] === 10` a secas, no con
   `is_staff()`, `is_mod()` ni `is_user()` (que dan acceso a más cuentas
   de las que corresponde acá). La acción de *volver* **no puede usar ese
   mismo chequeo** — durante la suplantación, `$mybb->user['uid']` ya no
   es 10, es el del usuario objetivo. "Volver" se valida en cambio por la
   presencia y validez de la cookie de retorno (ver §Restricciones de
   seguridad), sin importar qué UID esté activo en ese momento.
4. **Sin capacidad recíproca**: en ningún momento el usuario suplantado
   obtiene ninguna opción nueva en su propia cuenta ni ve que fue
   "vinculado" a nada — a diferencia del Account Switcher.
5. **Aviso visible durante la suplantación**: mientras la sesión esté
   actuando como otro usuario, debe haber un aviso permanente en el sitio
   (ej. en el header) indicando "Estás actuando como {usuario} — Volver a
   tu cuenta", para no terminar posteando como otra persona sin darse
   cuenta.
6. **Registro de auditoría**: cada inicio y fin de suplantación queda
   registrado (quién, a quién, cuándo) vía `log_audit()` o equivalente —
   es una acción sensible y debe quedar trazada igual que otras acciones
   de staff en el sistema.
7. **Sin tocar `global.php`**: el aviso del header se resuelve con un
   plugin nuevo (`global_intermediate`, mismo patrón ya usado en
   `op_bitacora` / `op_solicitudes_creacion`), no agregando lógica a
   `global.php`.

## Restricciones de seguridad

- La cookie de "retorno" (que guarda la sesión original de FID 10 mientras
  dura la suplantación) debe ser `httponly` — no debe ser legible por
  JavaScript.
- Antes de restaurar la sesión original con "Volver", hay que validar el
  `loginkey` guardado contra el valor actual de `mybb_users` para FID 10
  — no confiar ciegamente en el contenido de la cookie, aunque sea
  `httponly`, como defensa adicional ante manipulación de cookies a nivel
  de red/navegador.
- La suplantación en curso bloquea naturalmente volver a usar la
  herramienta de suplantación: como el gate es `uid === 10` y durante la
  suplantación la sesión activa es la del usuario objetivo (no la 10), la
  cuenta 10 no puede encadenar una segunda suplantación sin antes volver a
  su propia cuenta. No hace falta lógica extra para esto — es una
  consecuencia directa del diseño del gate.
- CSRF: toda acción (`suplantar`, `volver`) debe ir protegida con
  `verify_post_check($mybb->get_input('my_post_key'))`, igual que el resto
  de acciones POST de `/op/staff/`.

## Pendiente antes de implementar

- [ ] 1. ¿La suplantación debería expirar sola después de un tiempo (ej. 30
      minutos), o queda activa indefinidamente hasta que se pulse
      "Volver"?
- [ ] 2. ¿Hay alguna cuenta que FID 10 NO debería poder suplantar (ej. otras
      cuentas de staff/admin), o al ser la cuenta de mayor jerarquía puede
      suplantar a cualquiera sin restricción?
- [ ] 3. Texto y ubicación exacta del aviso en el header — confirmar diseño
      visual antes de escribir el plugin del banner.
- [ ] Nombre final de la ruta: se usó `/op/staff/suplantar.php` como
      referencia en la conversación — confirmar si ese es el nombre
      definitivo.
