# Alternativa para responder a un hilo

Propuesta para comparar. Los archivos `.html` y `.css` de este directorio no
son plantillas activas. El HTML conserva las interpolaciones, campos ocultos,
mensajes y puntos de extensión que aparecen en `newreply.html` actual.
El diseño de SCEditor se aplica también a `newthread.html` y `editpost.html`;
el marco de 1100px y la columna de 900px se aplican a las tres páginas.
El HTML alternativo sigue siendo específico de responder.

## Decisiones

- Ancho exterior máximo de 1100px, con una columna de escritura de 900px.
  Se comparte entre responder, crear y editar. La línea de texto queda
  cómoda para posts largos sin extenderse a 1300px.
- SCEditor ocupa el ancho del formulario y arranca con una altura mínima de
  480 a 760px según el viewport. En móvil, de 420 a 620px. `min-height`
  permite seguir agrandándolo con el tirador del editor.
- La toolbar envuelve sus grupos en pantallas estrechas y mantiene botones
  de 36px. El foco y el estado activo se distinguen con bordes de tinta y
  naranja, sin sombras difusas. El contenido editable usa Inter, fondo crema
  e interlineado amplio mediante la hoja del iframe.
- El contador es texto de estado junto a "Mensaje". Los mínimos quedan debajo
  del editor porque son información, no comandos.
- Spoiler, Dado y Técnica siguen como chips debajo de SCEditor. Al pulsar uno,
  sus campos aparecen en un panel compacto dentro de la página. La escritura
  permanece a la vista mientras se eligen los datos.
- Enviar queda como acción primaria; vista previa, borrador y recuperación son
  secundarias. `{$savedraftbutton}` trae un `input.button` desde otra plantilla,
  por eso el CSS lo adapta dentro de esta página.
- La clave de respaldo local `user-post` y su comportamiento quedan como están.

## Integración pendiente

1. Reemplazar `newreply.html` por el HTML propuesto y copiar el CSS a
   `jscripts/newpost-frame.css`. Esta hoja ya se carga en `newthread.html`
   y `editpost.html`: los selectores `.newpost-pagina` cubren las tres
   pantallas, incluidos el marco de 1100px, el formulario de 900px y
   SCEditor. Subir a `?ver=2` las referencias en crear y editar para que
   el navegador reciba el CSS nuevo. Revisar las filas de campos de crear
   y editar dentro del ancho nuevo antes de publicarlo.
2. `codebuttons.html` conserva su toolbar actual. Los tres chips externos
   conservan los IDs y atributos que usa `post_editor.js`, así que su lógica
   de inserción sigue funcionando aunque el contenedor deje de ser un modal.
3. Corregir en `post_editor.js` el cierre con una técnica sin ID: antes de
   llamar a `_insertarBBCode(tipoActual)`, comprobar si
   `pe-tecnica-tid` está vacío. Si lo está, enfocar ese campo y salir del
   handler sin ocultar `pe-bbcode-overlay`. Conviene retirar el listener que
   cierra al pulsar el fondo del contenedor, porque ahora es un panel en la
   página; el botón de cerrar y Escape ya cubren el cierre. El campo
   "Título del spoiler" debe generar la apertura `[spoiler="título"]`
   y dejar el contenido entre esa apertura y `[/spoiler]`; actualmente el
   JS inserta `[spoiler]título[/spoiler]`, que oculta el título en vez de
   mostrarlo en la cabecera. El parser de `BBCustom_spoiler.php` reconoce
   la variante con título.
4. El contenido enriquecido vive dentro de un iframe. Copiar
   `newreply-editor-iframe.css` a
   `/jscripts/sceditor/styles/opg-post-writing.css` e importarlo al iniciar
   el editor desde `post_editor.js`, que ya se carga en las tres plantillas:

   ```js
   editor.css('@import url("/jscripts/sceditor/styles/opg-post-writing.css?ver=1");');
   ```

   `css()` está presente en esta versión local de SCEditor. Así se evita
   modificar la hoja base del tema, que podría afectar otros editores del
   foro. El CSS de la página solo alcanza borde, toolbar e iframe exterior.
5. Importar las plantillas con el flujo habitual de MyBB. Revisar las tres
   pantallas a 375px, 700px y escritorio, en modo visual y fuente; probar
   envío, vista previa, borrador, redimensionado y los chips BBCode.

## Hallazgo funcional en la versión actual

El panel actual se cierra después de pulsar "Insertar" aun cuando el ID de
la técnica esté vacío, porque `_insertarBBCode()` vuelve sin insertar y el
handler oculta el contenedor igualmente. Ese detalle se corrige en el paso 3.
El contador también merece prueba en modo fuente: hoy lee `getBody()`, el
contenido visual del iframe, así que puede quedarse desactualizado mientras
se edita el BBCode en ese modo. No se cambia la regla existente de 300
palabras; se debe evitar que el indicador parezca actualizado cuando no lo
está.
