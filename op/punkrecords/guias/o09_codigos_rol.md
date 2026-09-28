# 9. Guía de Códigos de Rol

**Estado:** Vigente (extraída del foro el 27 de septiembre de 2026)

En One Piece Gaiden hemos creado una variedad de códigos para enriquecer el rol. Puedes usar diversos códigos que facilitarán los combates y permitirán organizar mejor las acciones y añadir datos relevantes.

Códigos de rol

Spoiler (Da exp)
Sirve para poner un texto, imagen, o cualquier cosa importante que sea parte de un post y no sea repetitiva o mera información dentro de una caja desplegable. Abajo mostramos un ejemplo y el code para usarlo:

Nota: En este código se permite meter la cuentas/cálculos de tus post ya que si son un trabajo del usuario.
[ spoiler]Texto

 (Sin el espacio al principio)
[ spoiler=Título de spoiler]Soy un spoiler

#### Título de spoiler

Soy un spoiler

Extra (No da exp)
Es como el spoiler pero sirve para poner copias/pega de explicaciones de virtudes/defectos u otras cosas que creas que es necesario que quede plasmado en cada post, dentro de una caja desplegable. Abajo mostramos un ejemplo y el code para usarlo:
[ extra]Texto (Sin el espacio al principio)
[ extra=Título que quieras]Soy un spoiler

Soy un extra

Técnica
Con este code muestras que técnica estás usando. Es obligatorio ponerlo al usar una técnica para que todo el mundo pueda ver que hace la técnica que estás haciendo, el daño que hace, cuánto cuesta, etc. Cuando pones en un hide una técnica oculta, debes poner tambien dentro del hide este code. El ID de las técnicas sale arriba del todo y centrado en la ficha de la técnica. También este mismo code sirve para las especializaciones y maestrías.
[ tecnica=COM101] (Sin el espacio al principio)
[ tecnica=HAOS201]

Mantenida
Con este code cuentas los turnos que van pasando de la técnica mantenida automáticamente.
[ mantenida=tecnica_id] (Sin el espacio al principio)
[ fin=tecnica_id] (Sin el espacio al principio)

Ficha
El código ficha sirve para poner tus estadísticas, tu inventario, tus virtudes y tus defectos en un tema. Una vez pongas el código, tus estadísticas no aumentarán dentro de ese tema a pesar de que en tu ficha si lo hagan. Este código es obligatorio de usar en todos los temas que no sean diarios o sociales. Una vez pongas el código, tu personaje se fija en ese estado para el resto del tema, por lo que tampoco podrás usar técnicas o armas que hayas aprendido después. Se usa con el siguiente code.
[ ficha] (Sin el espacio al principio)

Ficha seguido de una fecha
El código ficha seguido de una fecha sirve para poner tus estadísticas, tu inventario, tus virtudes y tus defectos en un tema en una fecha en específico, así tu personaje se adecua a ese momento en concreto. Una vez pongas el código, tus estadísticas serán las de día elegido. Se usa con el siguiente code.
[ ficha=19/04/2026] (Sin el espacio al principio)

Ficha seguido de un número
El código ficha seguido de un número sirve para poner tus estadísticas, tu inventario, tus virtudes y tus defectos en un tema en un nivel en específico, así tu personaje se adecua a ese momento en concreto. Una vez pongas el código, tus estadísticas serán las de tu última ficha guardada en el nivel elegido. Se usa con el siguiente code.
[ ficha=23] (Sin el espacio al principio)

Hide
El hide es similar al spoiler, pero con una diferencia fundamental. Cuando mandes un post con un hide, únicamente tú podrás ver dicho hide. El resto de usuarios no podrán leer nada. Después puedes publicar los hide para que todo el mundo pueda verlo de nuevo. Esto es útil para realizar acciones ocultas sin que los demás las vean. Los hides tienen una norma extra, y es que al cerrar un tema, todos los hide se deben publicar. De esta manera se evita que se puedan hacer trampas con ellos. Aquí debajo vamos a poner dos hide, el primero no lo vamos a publicar, para que vean lo que ve la gente cuando haces un hide, y el segundo sí lo vamos a publicar. En el segundo haremos un ejemplo de que uso se le podría dar.
[ hide]Katakuri utiliza el Kenbunshoku Haki para aumentar sus reflejos +20. (Sin el espacio al principio)

Dados
El código de dados te permite lanzar X cantidad de dados con Y cantidad de caras. [ XdY]
Plantilla: [ XdY]
[ 3d20] (Sin el espacio al principio)

Para que funcione el código debes añadir la letra d con la cantidad de dados que quieres lanzar a la izquierda (X) y con el número de caras que quieres que tenga cada dado a la derecha (Y).

Al hacer un nuevo post con este código, el resultado del dado se va a guardar con el post, así que se mantendría siempre. Además, cada código de dado dentro un post es único y tiene un ID. De tal modo, que si utilizas varias veces el código de dado en un mismo post, cada uno tendrá un ID específico y se revelará. Además, el código de dado indica directamente si el post fue editado o no. La máxima cantidad de dados que se pueden lanzar en un mismo código es de 20 y la máxima cantidad de caras es de 100000.

Cerrado
Con este code se cerrará un tema, de manera que nadie más podrá volver a responder a él. Este code debe usarse para cerrar una linea temporal. Para utilizarlo poner el siguiente code.
[ cerrado] (Sin el espacio al principio)

Recursos (vida, energía, haki)
Existen códigos de recursos que sirven como referencia visual. Los códigos de recursos son vida, energía y haki.

Para usar estos códigos, se utiliza la palabra recurso y se añaden dos valores actuales separados por un barra (/).
[ recursos=vida actual/energía actual/haki actual]

[ recursos=1000/1000/1000]

Objeto
Este código permite mostrar objetos visualmente en posts tal cual como lucen en tu inventario o la tienda.

#### Referencia visual

[ objeto=TTUN010](objeto: TTUN010)

[ objeto=<ID_de_objeto> ] (Sin el espacio al principio)
[ objeto=TTUN010]

Consumir
Este código permite consumir objetos/items que están en tu inventario para apoyarte en aventuras. Cuidado al usar este codigo pues consume una unidad del objeto directamente del inventario y lo pierdes.
[ consumir=<ID_de_objeto> ] (Sin el espacio al principio)
[ consumir=TTUN010]

Ficha Secreta
El código de fichasecreta muestra toda la información de tu personaje en un desplegable. Muestra el nombre de tu ficha de TOPSECRET, nivel, stats, equipamiento, las Virtudes y Defectos respectivamente.
[ fichasecreta] (Sin el espacio al principio)

Personaje Secreto
El código de personajesecreto modifica el post que estas escribiendo para que se muestre la información he imagen de tu ficha de TOPSECRET como si fuera el que envia el post, se debe utilizar en todos los post que rolees con tu identidad de TOPSECRET.
[ personajesecreto] (Sin el espacio al principio)

NPC
El código de npc muestra en el post la ficha de un NPC.
[ npc=ID] (Sin el espacio al principio)

#### no van

Personaje de otro tema
En algunos casos cambiarás de un tema a otro, y necesitarás pasar tus estadísticas del primer tema al segundo. Para eso se utiliza este code, que es una variación del primero.

[ personaje=idDelTema] (Sin el espacio al principio)

Para saber cuál es la ID del tema del que queremos transportar el personaje, debemos mirar la URL del tema original. Por ejemplo, si quisiésemos llevar las estadisticas de nuestro personaje de este tema (Tutorial 12), miraríamos la URL:

https://onepiecegaiden.net/showthread.php?tid=1048

La ID del tema, es el número que aparece después de tid= que en este caso es 1048. Para dejarlo claro, vamos a ver un caso más difícil, en el que también sale la ID del Post, el cual no nos interesa:

https://onepiecegaiden.net/showthread.php?tid=1048&pid=5015#pid5015

De nuevo, la ID del tema es el número después de tid=, que en este caso es 1048. El resto de números no nos interesan, pues son la ID de un post.

Virtudes

El código de Virtudes muestra todas las virtudes y defectos de tu personaje en un post. Muestra las Virtudes y Defectos respectivamente, en orden alfabético. Sirve de referencia para narradores y usuarios con los que roleas.

Nota: Este código se debe poner en un , y de tener Alergias, Vicios, etc. Indicarlos abajo.

[ virtudes] (Sin el espacio al principio)
Movilidad, Salto y Trepar

Para facilitar la calidad de vida del usuario y los cálculos, hemos creado tres códigos mencionados en la Guía Bélica del foro. Para usar estos códigos, se utiliza el nombre de la acción y se añade los valores correspondientes de las estadísticas separados por una coma (,).

[ movilidad=<Agi>,<Res>] (sin el espacio)
[ salto=<Agi>,<Fue>]
[ trepar=<Agi>,<Des>]

#### Referencia visual

(movilidad: 50,50)
(salto: 50,50)
(trepar: 50,50)

Tiempo
Si quieres indicar un límite de tiempo en horas para que los usuarios puedan saber cuánto tiempo tienen para postear en una misión o evento, puedes utilizar el código de tiempo. El cual recibe un número en horas (con un mínimo de 1 hora y un máximo de 24*365 horas) y luego te indicará cuánto tiempo queda sobre ese límite, de acuerdo a las horas que ha indicado el narrador.

[ tiempo=48] (Sin el espacio al principio)
[ tiempo=72]

Viaje
Este código lo puedes usar tras hacer un viaje en la sección de Viajes. Al utilizar este código en un post saldrán los detalles del viaje en particular, los personajes involucrados y el resultado de la tirada de viaje.

#### Referencia visual

(viaje: 1)

[ viaje=<ID_de_viaje>] (Sin el espacio al principio)
[ viaje=10]
