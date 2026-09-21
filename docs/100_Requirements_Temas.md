# Requisitos: Seguimiento de Temas de Rol

## 1. Proposito

Crear una herramienta para personajes con ficha que permita conocer en que
temas de rol participan y, sobre todo, si les corresponde responder.

La herramienta debe reducir la necesidad de revisar manualmente cada tema y
ofrecer un resumen permanente en el header del foro.

Este documento define requisitos funcionales. La arquitectura, el modelo de
datos, los endpoints y el diseno detallado se definiran posteriormente en
`200_DesignPlan_Temas.md`.

La incorporacion automatica y la herramienta de seguimiento deben plantearse
como partes de un plugin de MyBB, integrado mediante los hooks apropiados.

## 2. Alcance

El sistema debe permitir que cada personaje:

- vea todos los temas de rol que esta siguiendo;
- distinga los temas en los que debe responder;
- distinga los temas en los que espera una respuesta ajena;
- empiece a seguir automaticamente los temas de rol en los que publica;
- agregue manualmente un tema mediante su TID;
- deje de seguir un tema cuando ya no quiera verlo;
- consulte un resumen de turnos desde cualquier pagina mediante el header.

El seguimiento pertenece al personaje activo, identificado por su FID/UID, y
no debe mezclarse con el de otros personajes asociados a la misma cuenta
principal.

## 3. Definiciones

### 3.1 Tema de rol

Un tema es elegible cuando pertenece a la zona de rol. La zona de rol se
identifica exclusivamente mediante:

```sql
f.parentlist LIKE '10,%'
```

Los temas de otros foros no deben incorporarse al tracker.

### 3.2 Tema seguido

Un tema seguido es una relacion persistente entre el personaje y el TID. Seguir
un tema no modifica el tema, sus participantes ni las suscripciones nativas de
MyBB.

### 3.3 Debes responder

Es el turno del personaje cuando todos los participantes esperados hayan
publicado al menos una vez durante la ronda actual, o cuando el personaje lo
indique mediante un override manual.

### 3.4 Esperando respuesta

El personaje espera a los demas mientras al menos uno de los participantes
esperados no haya publicado durante la ronda actual, salvo que el personaje
fuerce manualmente su turno.

El tracker calcula rondas completas. El ultimo autor es informacion contextual,
pero no determina por si solo a quien le toca.

## 4. Incorporacion automatica

Cuando un personaje publique un post visible en un tema elegible de la zona de
rol, el sistema debe empezar a seguir ese tema automaticamente.

El alta automatica debe:

- usar el TID real del post;
- asociarse al FID/UID del autor;
- evitar registros duplicados;
- dejar el tema en `Esperando respuesta`, porque el personaje acaba de
  publicar;
- usar esa publicacion como inicio de una nueva ronda y limpiar el override de
  turno que se hubiera aplicado a la ronda anterior;
- reflejarse en la herramienta y en el contador del header.

La creacion del primer post de un tema de rol tambien cuenta como participacion
y debe activar el seguimiento.

Si un personaje deja de seguir un tema y posteriormente vuelve a publicar en
el, el alta automatica debe activarlo de nuevo. El usuario debera volver a
retirarlo si realmente no desea seguirlo.

## 5. Incorporacion manual

La herramienta debe incluir un campo para introducir un TID. Esta accion se
denomina `agregar manualmente` y permite empezar a seguir un tema sin esperar a
publicar en el.

Este flujo debe cubrir el caso en que otro usuario crea un tema para el
personaje actual. Aunque el personaje todavia no haya publicado, debe poder
agregar el TID e indicar `Me toca responder` para que aparezca desde ese
momento en su tracker.

`Me toca responder` es una declaracion explicita del estado inicial. Es
especialmente necesaria cuando el personaje aun no tiene ningun post desde el
que el sistema pueda calcular una ronda.

Al enviarlo, el sistema debe validar que:

- el TID sea numerico y positivo;
- el tema exista;
- el tema sea visible para el personaje;
- el tema pertenezca a la zona de rol;
- el personaje no lo este siguiendo ya;
- el estado inicial indicado sea valido.

Si es valido, el tema debe aparecer inmediatamente. Cuando se agrega como un
tema creado para el personaje, su estado inicial sera `Debes responder`. Tras
la primera publicacion del personaje, esa publicacion abrira una nueva ronda y
el sistema esperara a todos los participantes configurados.

Los errores deben explicar el motivo sin revelar informacion de temas que el
personaje no pueda ver.

## 6. Retirada del seguimiento

Cada tema seguido debe ofrecer una accion clara para dejar de seguirlo.

Al retirarlo:

- debe desaparecer de la lista activa;
- debe dejar de contar en el header;
- no debe eliminar posts, suscripciones ni participaciones;
- la accion debe afectar unicamente al personaje que la ejecuta.

## 7. Estados visibles

### 7.1 Debes responder

El sistema estima que le toca publicar al personaje. Sin una configuracion
especial, esto ocurre cuando todos los participantes esperados han publicado
durante la ronda actual. Tambien puede resultar de una confirmacion manual.

### 7.2 Esperando respuesta

El sistema estima que el personaje todavia no debe publicar. Sin una
configuracion especial, esto ocurre mientras falte algun participante esperado
por responder en la ronda. Tambien puede resultar de una correccion manual.

### 7.3 Turno corregido manualmente

El personaje puede indicar `No me toca responder` aunque la estimacion
automatica diga lo contrario. Tambien puede declarar formalmente que ya le toca
en cualquier momento, aunque aun no se hayan cumplido las condiciones
configuradas para la ronda.

La interfaz debe dejar claro cuando el estado procede de una regla automatica
y cuando fue corregido o confirmado por el usuario.

### 7.4 Cerrado o finalizado

Un tema se considera finalizado cuando esta cerrado en MyBB. En ese momento
debe permanecer visible en el tracker con un aviso claro de que esta cerrado.
El personaje es responsable de dejar de seguirlo manualmente.

Los temas cerrados deben mostrarse en una seccion propia y no formar parte de
los conteos activos de `Debes responder` y `Esperando respuesta`.

### 7.5 Fuera de la zona de rol

Si un tema seguido se mueve fuera de la zona de rol, debe dejar de mostrarse
en la herramienta y de formar parte de sus conteos. El tracker es exclusivo
de la zona de rol.

### 7.6 Tema no disponible

El tema seguido ya no puede consultarse normalmente, por ejemplo porque fue
eliminado u ocultado. No debe mostrarse en la herramienta ni incluirse en sus
conteos.

## 8. Pagina de seguimiento

Debe existir una pagina propia accesible para personajes con sesion iniciada.

La pagina debe mostrar tres grupos principales:

1. `Debes responder`;
2. `Esperando respuesta`;
3. `Cerrados`, mientras el personaje no los retire manualmente.

Cada tema debe mostrar, como minimo:

- titulo;
- enlace al ultimo post o al primer post no leido;
- TID;
- foro o localizacion;
- autor del ultimo post;
- fecha del ultimo post;
- estado de turno;
- aviso de tema cerrado cuando corresponda;
- accion para dejar de seguirlo.

La pagina debe incluir:

- formulario para agregar un TID;
- conteo de los dos estados activos y de los temas cerrados;
- estado vacio comprensible para cada grupo;
- confirmacion visible despues de agregar o retirar un tema;
- orden por actividad mas reciente de forma predeterminada.

## 9. Resumen en el header

Los personajes con sesion iniciada deben ver una barra compacta inspirada en el
patron visual de `#peticiones_staff`.

El contenido minimo sera:

```text
Temas: Debes responder: 5 | Esperando respuesta: 7
```

La barra debe:

- aparecer para usuarios normales, no solo para Staff;
- usar los conteos del personaje activo;
- enlazar a la pagina de seguimiento;
- actualizar sus cifras despues de publicar, seguir o dejar de seguir;
- ocultarse para invitados;
- mantener una altura compacta y no romper el header en movil.

El conteo `Debes responder` debe tener mayor prioridad visual.

La barra debe permanecer visible cuando ambos contadores sean cero y mostrar
un mensaje positivo indicando que el personaje no debe ninguna respuesta.

Durante el desarrollo, esta barra es la ultima prioridad. Primero deben quedar
correctos el seguimiento, sus estados y la pagina principal.

## 10. Calculo del turno

El estado automatico debe derivarse de datos actuales del tema, pero puede ser
ajustado mediante condiciones y decisiones del personaje.

Para cada tema activo:

1. localizar el post visible que inicio la ronda actual;
2. obtener los participantes esperados del seguimiento;
3. comprobar cuales publicaron despues del inicio de la ronda;
4. aplicar, cuando exista, el override manual vigente;
5. clasificarlo como `Debes responder` o `Esperando respuesta`.

Si se elimina o desmodera un post relevante para la ronda, el sistema debe
recalcular que participantes siguen contando como respondidos.

### 10.1 Condiciones de la ronda

Cada seguimiento debe permitir modificar las condiciones que determinan
cuando vuelve a tocarle al personaje.

El caso principal es un tema con varios participantes. Al comenzar el
seguimiento, el sistema debe incorporar automaticamente como participantes
esperados a todos los personajes distintos del personaje actual que ya hayan
publicado en el tema. La interfaz debe mostrarlos y permitir agregar
manualmente a alguien que todavia no haya publicado.

La publicacion del personaje seguido abre una nueva ronda. A partir de ese
momento, cada participante esperado debe publicar al menos una vez. Mientras
falte alguno, el estado sera `Esperando respuesta`. Cuando todos hayan
publicado, cambiara automaticamente a `Debes responder`.

Si el personaje ya habia publicado antes de incorporar manualmente el tema, su
ultimo post visible sera el inicio de la ronda actual y se evaluaran los posts
posteriores. Si nunca publico, no existe un inicio de ronda calculable y debe
declarar el estado inicial, normalmente `Me toca responder`.

Cuando una persona nueva publique en el tema, debe incorporarse automaticamente
a los participantes esperados. Si su primera publicacion ocurre durante la
ronda actual, esa misma publicacion cuenta como su respuesta en la ronda.

Cuando alguien abandona el tema o deja de tener que responder, el personaje
debe poder retirarlo manualmente de sus participantes esperados sin eliminar el
seguimiento. Si una persona retirada vuelve a publicar, se considera que se ha
reincorporado y vuelve a agregarse automaticamente.

El sistema debe poder distinguir entre:

- participantes que ya respondieron en la ronda actual;
- participantes cuya respuesta sigue pendiente;
- participantes que ya no forman parte de las condiciones del turno;
- confirmacion manual de que ya le toca al personaje;
- declaracion manual de que todavia no le toca.

La lista de participantes esperados pertenece a cada seguimiento individual.
Por tanto, dos personajes del mismo tema pueden configurar condiciones de
ronda distintas sin afectarse entre si.

### 10.2 Override manual del turno

El personaje puede usar `Me toca responder` para forzar inmediatamente ese
estado, aunque todavia falten participantes esperados. El override permanece
vigente hasta que el personaje publique o vuelva a modificarlo.

Cuando el personaje publique despues de ese override:

- el override queda consumido;
- esa publicacion inicia la siguiente ronda;
- las condiciones de participantes esperados vuelven a aplicarse normalmente.

El personaje tambien puede indicar `No me toca responder`. Esta decision se
mantiene hasta que todos los participantes esperados hayan publicado en la
ronda o hasta que el propio personaje aplique el override `Me toca responder`.

La interfaz debe mostrar si el estado actual procede del calculo de la ronda o
de un override manual.

### 10.3 Ronda dirigida por narrador

Cada personaje puede seleccionar manualmente un narrador para uno de sus
seguimientos. La seleccion es personal y no modifica las condiciones que otros
personajes hayan configurado para el mismo tema.

Cuando existe un narrador, su primer post visible posterior a
`ronda_inicio_pid` habilita el turno del personaje aunque los demas
participantes todavia no hayan publicado. Los posts de otros participantes no
bloquean ni habilitan ese turno.

El ultimo post del narrador tambien inicia la ronda compartida que se muestra
en la interfaz. Un participante solo figura como respondido si publico despues
de esa narracion; cualquier post suyo anterior vuelve a quedar fuera de la
ronda actual.

El narrador puede cambiarse o quitarse. Al quitarlo, el seguimiento vuelve a
exigir la respuesta de todos los participantes esperados. Eliminar o
desmoderar el post del narrador recalcula el estado en la siguiente lectura.

## 11. Permisos y privacidad

- Un personaje solo puede administrar sus propios seguimientos.
- Cualquier visitante puede consultar el tracker de un personaje mediante
  `modo_vista=<FID>`. Esta modalidad es exclusivamente de lectura.
- La pagina debe ofrecer un selector publico para localizar otra ficha por
  nombre, apodo o FID y abrir directamente su Modo vista.
- No se debe agregar ni consultar mediante el tracker un tema sin permisos de
  lectura. La vista publica solo muestra los temas que el visitante actual
  puede ver segun los permisos de MyBB.
- Los conteos del header son privados para la sesion actual.
- Las acciones que modifican seguimiento deben validar sesion y `post_key`.
- Todos los TID y FID deben validarse en servidor.
- Titulos, nombres y URLs deben escaparse antes de generar HTML.

## 12. Integracion con MyBB

El sistema debe convivir con:

- la publicacion normal de temas y respuestas;
- el cambio de personaje mediante Account Switcher;
- los permisos de foros de MyBB;
- temas movidos, cerrados, moderados o eliminados;
- el header y sus barras existentes;
- las suscripciones nativas, sin sustituirlas ni modificarlas.

El header debe mostrar exclusivamente los conteos del personaje activo, no la
suma de personajes vinculados mediante Account Switcher.

Narradores y NPC deben seguir las mismas reglas siempre que tengan una ficha.

No se realizara una importacion automatica de temas anteriores al lanzamiento.
Un personaje podra incorporar un tema antiguo mediante el formulario por TID.

`mybb_op_thread_personaje` puede aportar informacion contextual sobre
participaciones, pero no debe asumirse como registro de seguimiento sin una
decision explicita en el plan de diseno.

## 13. Rendimiento

- El header no debe ejecutar una consulta independiente por cada tema.
- Los conteos deben obtenerse en una cantidad acotada de consultas.
- La pagina debe resolver estados de turno en bloque.
- Deben existir indices para buscar seguimientos por personaje y tema.
- No se debe recorrer el historial completo de posts en cada carga cuando el
  ultimo post visible pueda obtenerse directamente.

## 14. Presentacion y accesibilidad

La interfaz debe seguir `docs/style.md`:

- estructura compacta y orientada a uso frecuente;
- jerarquia clara entre accion requerida y espera;
- colores semanticos acompanados por texto;
- bordes negros, sombras desplazadas y superficies OPG;
- controles reconocibles y etiquetas explicitas;
- funcionamiento en escritorio y movil sin solapamientos;
- foco visible y acciones utilizables con teclado.

## 15. Criterios de aceptacion

1. Publicar en un tema visible de la zona de rol activa su seguimiento para el
   personaje autor.
2. Publicar fuera de la zona de rol no activa seguimiento.
3. Agregar manualmente un TID valido incorpora el tema una sola vez.
   No es necesario que el personaje haya publicado previamente en el tema.
4. Un TID inexistente, inaccesible o ajeno a la zona de rol es rechazado.
5. La publicacion del personaje inicia una ronda en `Esperando respuesta`.
6. El tema solo cambia automaticamente a `Debes responder` cuando todos los
   participantes esperados hayan publicado durante esa ronda.
7. Dejar de seguir elimina el tema de la lista y de los conteos personales.
   Si el personaje vuelve a publicar, el tema se incorpora otra vez.
8. La barra del header muestra los mismos totales que la pagina.
9. Cambiar de personaje muestra el seguimiento del personaje activo.
10. Un personaje no puede modificar el seguimiento de otro.
11. Los estados se recalculan si se elimina o desmodera un post que contaba en
    la ronda actual.
12. La herramienta no altera las suscripciones nativas de MyBB.
13. Un tema cerrado o finalizado permanece en una seccion propia del tracker
    hasta que el personaje lo retire manualmente. No cuenta como turno activo.
14. No se importan automaticamente participaciones anteriores al lanzamiento.
15. Narradores y NPC con ficha usan las mismas reglas de seguimiento.
16. El personaje puede declarar `No me toca responder` aunque las condiciones
    de la ronda indiquen lo contrario, sin dejar de seguir el tema.
17. El personaje puede declarar `Me toca responder` aunque todavia falten
    participantes esperados.
18. El personaje puede seleccionar que participantes deben publicar antes de
    que vuelva a tocarle y retirar de esa condicion a quien abandone el tema.
19. Las condiciones de ronda de un personaje no modifican las de los demas.
20. Un tema movido fuera de la zona de rol o que deja de estar disponible no se
    muestra ni se incluye en los conteos.
21. Los conteos del header incluyen exclusivamente los datos del personaje
    activo.
22. Despues de que el personaje publique, el tema inicia una nueva ronda en
    `Esperando respuesta` y el override anterior queda consumido.
23. Cuando todos los participantes esperados hayan publicado al menos una vez
    durante la ronda, el tema cambia automaticamente a `Debes responder`.
24. `Me toca responder` fuerza ese estado hasta que el personaje publique o lo
    cambie manualmente.
25. `No me toca responder` permanece hasta que se cumplan las condiciones de
    la ronda o el personaje indique manualmente que ya le toca.
26. Los personajes que ya publicaron aparecen en la configuracion de la ronda
    y se puede agregar manualmente a alguien sin posts previos.
27. Un participante nuevo se incorpora automaticamente a las condiciones. Un
    participante que abandona debe retirarse manualmente y, si vuelve a
    publicar, se incorpora otra vez.
28. Al iniciar el seguimiento, todos los demas personajes que ya publicaron en
    el tema se incorporan automaticamente como participantes esperados.
29. Si se selecciona un narrador, su siguiente post habilita el turno sin
    esperar a los demas participantes; quitarlo restaura la ronda normal.
30. `modo_vista=<FID>` muestra el tracker del personaje solicitado a usuarios
    autenticados o invitados, respetando los permisos de lectura del visitante.
31. En modo vista no aparecen controles de alta, turno, participantes,
    narrador ni retirada, y ninguna peticion puede modificar el tracker ajeno.
32. El selector de personaje empieza a buscar tras tres caracteres o acepta un
    FID numerico, y cada resultado navega a `modo_vista=<FID>`.
