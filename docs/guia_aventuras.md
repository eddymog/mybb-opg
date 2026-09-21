# Sistema de aventuras y recompensas

Este documento resume la propuesta para eliminar los tiers de aventura y
reemplazarlos por recompensas calculadas según publicaciones e hitos cumplidos.
También recoge las reglas relacionadas con objetos, narradores, fama e
invasiones.

> Estado: nuevo sistema todavía no implantado. Este documento describe su
> propuesta funcional; los puntos marcados como pendientes necesitan revisión
> o una definición más precisa antes de implementarse.

## Qué es una aventura

Una aventura es un tema on-rol situado en el presente. Un narrador omnisciente
crea y dirige la trama y representa a los NPC que interactúan con los
personajes. Puede abordar combates, comercio, política, tesoros, misterios y
otros sucesos, y puede jugarse individualmente o en grupo.

En el primer post deben incluirse los códigos correspondientes:

- `[narrador]` para el usuario que desempeña esa función;
- `[ficha]` para el personaje que participa en la aventura.

## Principio general

Los tiers de aventura se eliminan. La recompensa de una aventura se divide en:

1. Una recompensa base por cada post válido.
2. Recompensas adicionales por los hitos que cumpla cada usuario.

La recompensa base por post para un jugador es:

| Recurso | Cantidad |
| --- | ---: |
| EXP | 15 |
| Reputación | 4 |
| Nikas | 2 |
| Berries | 1 M |

## Duración de las aventuras

- Toda aventura debe tener un mínimo de 5 rondas.
- Se recompensan como máximo 20 rondas.
- La aventura puede continuar después de la ronda 20, pero los posts
  adicionales no generan recompensa.
- Los posts posteriores a la ronda 20 tampoco cuentan para los extras del hito
  bélico.
- Si un enfrentamiento bélico no termina dentro de las 20 rondas y se deja sin
  finalizar, se contabiliza como una derrota.
- El enfrentamiento puede continuar después de la ronda 20 y todavía puede ser
  superado, aunque esas rondas adicionales no sean recompensadas.

## Hitos por usuario

Las recompensas adicionales se calculan individualmente según los hitos
alcanzados por cada participante.

Un hito es un acontecimiento o punto de referencia clave dentro del desarrollo
de una historia. Se valora al terminar la aventura, no solo por haber sido
planeado. Si no se cumple ninguno, se entrega únicamente la recompensa base.

Solo se pueden seleccionar dos de los cinco hitos. Si el staff determina que
uno de los seleccionados no se cumplió, no se paga ese extra y no puede
sustituirse por otro hito después de la evaluación.

| Recompensa | Bélico | Lore | Impacto en lore | Entrenar oficio | Inframundo |
| --- | --- | --- | --- | --- | --- |
| EXP | Fórmula bélica | 5 % o 10 % | 0 | 0 | 0 |
| Reputación | Fórmula bélica, máximo 10 % | 5 % o 10 % | 5 % a 20 % | 5 % | 0 |
| Nikas | Posts bélicos / 2 | 0 | 2 | 0 | 0 |
| Berries | 0 | 10 % | 5 % si se gana on-rol | 15 % | 25 % |
| Puntos de oficio | 0 | 0 | 0 | 500 | 0 |

### Bélico

Comprende aventuras con un combate contra un NPC importante o un grupo grande
de NPC. El enfrentamiento debe seguir la Guía Bélica; un combate resuelto solo
de manera narrativa no cumple este hito.

### Hallazgo de lore

Es el descubrimiento de información relacionada con la crónica de una isla o
con un grupo de NPC relevantes para la historia de One Piece Gaiden. Los
objetos de lore pueden proporcionar un punto de partida, pero no revelan por sí
solos toda la información: sus pistas deben verificarse o desarrollarse en la
narración.

El porcentaje depende de la novedad del descubrimiento:

- 5 % si otro usuario ya realizó el mismo descubrimiento.
- 10 % si es un descubrimiento nuevo.

### Impacto en lore

No todo hallazgo de lore cambia el mundo. Este hito requiere una repercusión
relevante sobre una isla, un mar, una facción, NPC oficiales o el mundo. No
exige que exista un combate.

Los porcentajes se clasifican así:

- 5 %: impacto local o sobre una isla pequeña.
- 10 %: impacto sobre una isla mediana o grande completa.
- 15 %: impacto sobre un mar entero o una parte de una facción.
- 20 %: impacto global o sobre el rumbo de una facción completa.

Debe mantenerse la separación entre:

- impacto en el lore general;
- impacto en el lore personal del personaje.

El lore personal incluye sucesos que afectan a la historia del personaje, como
un ascenso, la muerte de un NPC cercano, una condecoración, una propiedad o el
reconocimiento de alguien a quien admira. El lore general comprende sucesos que
afectan a One Piece Gaiden a escala de isla, mar o mundo.

Un acontecimiento personal solo cuenta como hito de impacto si repercute de
forma relevante en el lore general. La mera aparición de un NPC oficial o un
riesgo elevado no convierte un suceso personal en impacto general.

Las aventuras que traten lore general deben consultarse previamente con los
Narradores Oficiales. Sin esa consulta, el narrador no obtiene extras por hitos
y los acontecimientos no se consideran canónicos.

### Entrenamiento de oficio

Comprende temas desarrollados alrededor del oficio del personaje: entrenar en
una forja, crear vínculos comerciales o estudiar una ruta marítima, entre otros.
Permite desarrollar el lore personal sin exigir un cambio importante en el
mundo.

### Inframundo

Es exclusivo de personajes del bajo mundo y se centra en la realización de
servicios para los capos del inframundo.

### Hito bélico

El hito bélico ajusta la recompensa según la dificultad real del combate,
comparando los niveles enemigos con el nivel del jugador más fuerte.

La fórmula recibida es:

```text
MB = EXP_total × [1 + (N1 - Jugador_max)/100 + ... + (Nn - Jugador_max)/100]
```

Forma abreviada:

```text
MB = EXP_total × [1 + Σ((Ni - Jugador_max) / 100)]
```

Donde:

- `EXP_total` es la EXP base acumulada en los posts recompensables.
- `Ni` es el nivel de cada enemigo adicional.
- `Jugador_max` es el nivel del jugador de mayor nivel.
- `MB` es la EXP final después de aplicar el ajuste bélico.

Aunque se denomina multiplicador bélico, el multiplicador propiamente dicho es
el contenido entre corchetes. `MB`, tal como está escrita la fórmula, representa
la recompensa final.

Ejemplo:

```text
EXP_total = 300
Jugador_max = 20
Enemigos = 25, 22 y 18

Multiplicador = 1 + 0.05 + 0.02 - 0.02 = 1.05
MB = 300 × 1.05 = 315 EXP
```

Con la fórmula actual, un enemigo de nivel inferior reduce la recompensa. Si la
intención es que los enemigos débiles simplemente no otorguen un bono, debería
usarse esta variante:

```text
MB = EXP_total × [1 + Σ(max(0, Ni - Jugador_max) / 100)]
```

La fórmula de reputación sigue el mismo principio, pero el bono bélico tiene un
tope del 10 %. Debe aclararse si ese límite afecta solo al bono positivo o al
resultado completo.

La recompensa de Nikas por el hito bélico es:

```text
Nikas = número de posts bélicos / 2
```

Está pendiente definir el método de redondeo cuando el número de posts sea
impar.

## Resumen obligatorio del narrador

Una aventura no se paga si el narrador no completa correctamente este resumen:

```text
Resumen general:
¿Descubre algo de lore?:
¿El descubrimiento ya está en la lista de eventos de la isla?:
¿Aparece algún NPC oficial?:
¿Qué impacto tiene en la isla esta aventura?:
¿Ha entrenado su oficio?:
¿Es una cadena para obtener un objeto?:
```

El narrador o un participante debe presentar la aventura finalizada en el tema
de Revisión de Aventuras y Recompensas de Gestión de Aventuras. El formulario
debe responderse de forma específica y completa; las preguntas omitidas o
insuficientemente explicadas pueden invalidar los hitos solicitados.

Si una aventura termina antes del mínimo de cinco rondas, jugadores y narrador
reciben únicamente la recompensa base proporcional a sus posts, sin extras por
hitos. Si una de las partes abandona la aventura, la parte que abandona no
recibe recompensa.

## Solicitud y simultaneidad

Antes de registrar una aventura deben acordarse el narrador y la idea general.
Si el usuario no tiene narrador, puede solicitar disponibilidad en el canal de
Narradores y Narrados de Discord. Un narrador también puede proponer allí una
aventura y abrir un número concreto de plazas.

Se permiten narraciones entre integrantes de la misma tripulación, pero pueden
revisarse y sancionarse cuando existan indicios demostrables de que se redujeron
premeditadamente los riesgos para favorecer a los participantes.

No existe un máximo de aventuras simultáneas, aunque deben respetarse la
cronología y las consecuencias del personaje. Una cadena que siga la misma
trama no puede jugarse en paralelo: cada parte debe terminar antes de comenzar
la siguiente.

No se permite fragmentar artificialmente una historia en aventuras mínimas de
cinco rondas ni encadenarlas en el mismo día cambiando únicamente la hora. Cada
tema debe tener inicio y una conclusión positiva o negativa. Puede conservar un
cabo suelto para una aventura futura, pero no aplazar una conclusión que
corresponde al tema actual para dividir indebidamente las recompensas.

## Objetos de lore

Los objetos de lore sirven como preámbulo para una aventura de investigación.
Su uso y la información buscada deben acordarse con el narrador antes de
registrar la narración. Si un narrador de la comunidad necesita lore general o
de una isla, debe abrir un ticket de Discord para el Narrador Oficial de la isla
o para el equipo de narración general, indicando el objeto y la clase de
información solicitada.

Los objetos acordados deben obtenerse antes de abrir el tema y consumirse en el
primer post, salvo la Piedra de los Susurros. La cantidad y fiabilidad de la
información dependen de la descripción y rareza del objeto.

### Tipos conocidos

- **Piedra de los Susurros:** proporciona rumores populares que pueden ser
  verdaderos o creencias sin fundamento. Es un objeto T1.
- **Informes confidenciales:** proporcionan información dirigida y exigen que
  la investigación tenga una justificación previa para evitar metarol. Pueden
  tratar sobre mandatarios, lugares arqueológicos ocultos, especialistas de
  renombre o piratas encubiertos, entre otros asuntos.

### Límites de uso

- Puede utilizarse como máximo un informe confidencial de cada tier de rareza
  en un tema. Por ejemplo: uno T3, uno T4, uno T5 y la Piedra de los Susurros.
- La información se entrega progresivamente desde el tier inferior hasta el
  superior, enlazando las pistas.
- El límite es compartido por todo el tema aunque participen varios usuarios.
- La información pasiva de la especialización de periodista cuenta como un
  objeto de su tier, aunque no consuma un informe.
- La virtud `información jugoza` es la única excepción indicada: permite recibir
  información y usar dos objetos adicionales en la aventura.

## Obtención de objetos

- Los objetos se consiguen mediante una aventura o cadena destinada a ese
  objeto.
- La aventura o cadena debe generar al menos el 150 % del precio del objeto en
  tienda.
- Solo se pueden obtener objetos crafteables mediante oficios.
- Al obtener el objeto, este sustituye la recompensa monetaria de las aventuras.
- Si el objeto se saquea durante la narración, su valor se descuenta de la
  recompensa de Berries bajo la misma regla.

Algunas zonas pueden contener secretos de lore desbloqueables mediante pautas
específicas. Pueden conceder nuevas pistas, técnicas, armas u objetos. Aunque
algunas recompensas pueden repetirse por su naturaleza, la mayoría de estos
secretos se desbloquean una sola vez.

## Narradores

### Rangos

| Rango | Requisitos | Temas permitidos |
| --- | --- | --- |
| Narrador Aprendiz | Cualquier miembro de la comunidad | Temas para niveles bajos, poco lore y baja peligrosidad |
| Narrador Estudioso | 5 aventuras completadas | Temas sencillos y de bajo impacto; deben utilizarse sistemas como el bélico y el naval |
| Narrador Ilustre | 15 aventuras completadas y haber tocado una vez todos los hitos | Puede utilizar NPC oficiales tras consultar a Narradores Oficiales |
| Narrador Erudito | 25 aventuras completadas y haber tocado tres veces todos los hitos | Pendiente de concretar |

### Recompensa base por post

| Rango | EXP | Nikas | Berries |
| --- | ---: | ---: | ---: |
| Narrador Aprendiz | 15 | 2 | 1 M |
| Narrador Estudioso | 16 | 3 | 1.100 M |
| Narrador Ilustre | 18 | 4 | 1.210 M |
| Narrador Erudito | 20 | 5 | 1.331 M |

### Cofres

| Cofre | Posts requeridos |
| --- | ---: |
| Básico | 5 |
| Decente | 10 |
| Gigante | 15 |
| Cobrizo | 20 |

Al completar hitos, el narrador puede obtener recompensas adicionales, pero
debe elegir como máximo dos hitos.

La tabla original vincula los hitos a mejoras de cofre o nivel, pero su
correspondencia exacta no queda suficientemente clara para implementarla sin
una revisión adicional. Las referencias recibidas son:

- Bélico ajustado: `x2 Ud.`
- Bélico nuevo: `+1 nivel`
- Lore del 5 %, 10 %, 15 % o 20 %: `+1`
- Impacto, oficio e inframundo: referencias a `nivel +1` y `nivel +1 en base al rango`

Existe además una propuesta pendiente de revisión: pagar al narrador lo mismo
que al usuario, sumando un 10 % por rango, redondeado hacia abajo y sin tope, y
eliminar el máximo de aventuras simultáneas por usuario.

## Aventuras autonarradas

- Mantienen su coste de recompensa.
- Deben tener una casilla propia para quien reparte la recompensa.
- Si se selecciona `autonarrada`, no puede seleccionarse ningún otro hito.

## Aventuras especiales

Estas aventuras tienen reglas o recompensas propias y deben dirigirlas
Narradores Oficiales o miembros del staff:

- **Conquista:** invasión y conquista de una isla o territorio para un bando o
  facción; se rige por la Guía de Conquistas.
- **Servicio:** encargos del bajo mundo con una recompensa adicional y mayores
  consecuencias por fracaso, incluida la posibilidad de prisión directa; se
  rige por la Guía del Inframundo.
- **Akuma no Mi:** aventura destinada a encontrar una fruta y sujeta a la Guía
  de Akuma no Mi.
- **Ascenso de facción:** requisito para progresar en determinadas jerarquías;
  se rige por la Guía de Facciones e incluye la cacería de NPC con wanted para
  los cazadores.

## Akuma no Mi

Los tiers de Akuma se mantienen. Lo que se reemplaza es la referencia a tiers de
aventura por niveles de dificultad:

| Tier de Akuma | Dificultad | Requisito propuesto |
| --- | --- | --- |
| T1 y T2 | Sin dificultad | Se mantiene el requisito propio de obtención |
| T3 | Leve | 2 aventuras con impacto de lore del 5 % |
| T4 | Media | 3 aventuras con impacto de lore del 10 % |
| T5 | Difícil | 4 aventuras con impacto de lore del 15 % |
| T6 | Pendiente de nombre | 5 aventuras con impacto de lore del 15 % |

- El desbloqueo de la aventura de Akuma queda a discreción del narrador.
- Para T4, T5 y T6, cada isla distinta reduce en 5 puntos porcentuales el
  impacto requerido, hasta un mínimo del 5 %.
- Las hojas de enciclopedia ayudan a reducir la dificultad de la aventura.
- Las reservas de Akuma duran dos meses.

## Fama y recompensa

| Rango | Reputación | Extra sugerido por aventura |
| --- | ---: | ---: |
| Desconocido | 0-25 | Sin extra |
| Iniciado | 26-50 | Sin extra |
| Novato | 51-100 | Sin extra |
| Rumor | 101-300 | 5 % |
| Aspirante | 301-600 | 5 % |
| Popular | 601-1500 | 10 % |
| Famoso | 1501-3000 | 10 % |
| Icono | 3001-5000 | 15 % |
| Leyenda | 5001+ | 15 % |

## Invasiones y misiones forzosas

Se propone un sistema de invasiones forzosas según reputación y mar:

- Piratas, civiles y revolucionarios pueden recibir invasiones.
- Para Marines, Cazarrecompensas y CP, se consideran misiones obligatorias.
- Los revolucionarios pueden recibir aleatoriamente una misión o una invasión.
- En misiones de Marines, Cazarrecompensas, Revolucionarios y CP, el objetivo se
  elige mediante una tirada entre piratas o facciones opuestas.
- La diferencia entre los niveles promedio de invasores e invadidos no puede
  superar 5 niveles.

### Probabilidad según reputación y mar

| Probabilidad | Blues | Paraíso | Nuevo Mundo |
| ---: | ---: | ---: | ---: |
| 1 % | 0-25 | 101-300 | 1501-3000 |
| 2 % | 26-50 | 301-600 | 3001-5000 |
| 3 % | 51-100 | 601-1500 | 5001-7500 |
| 4 % | 101-300 | 1501-3000 | 7501-10500 |
| 5 % | 301-600 | 3001-5000 | 10501-14500 |
| 10 % | 601-1500 | 5001-7500 | 14501-19000 |
| 15 % | 1501-3000 | 7501-10500 | 19001-24000 |
| 20 % | 3001-5000 | 10501-14500 | 24001-29500 |
| 25 % | 5001+ | 14501+ | 29500+ |

## Guías y sistemas afectados

- **Guía de oficios:** eliminar referencias a `AVENTURA TIER 1`, revisar costes
  y añadir los 500 puntos por entrenar el oficio en aventuras.
- **Guía de temas y temporalidad:** revisar aventuras especiales, autonarradas,
  temas bélicos e intervención e invasiones.
- **Misiones de ascenso:** actualizar descripciones y enlazar la lista de
  facciones.
- **Misiones de temporada:** eliminar la sección antigua; los narradores fijan
  al inicio las recompensas disponibles y al final determinan si se cumplió el
  objetivo.
- **Guía de inframundo:** eliminar tiers de requisitos y referencias a tiers de
  aventura; ligar el tier de subastas al nivel de inframundo y revisar cargos,
  servicios y recompensas.
- **Acceso al inframundo:** sustituir los mínimos por tier por cuatro aventuras
  encargadas por un grupo criminal de la isla y una quinta misión de ingreso
  entregada por el capo.
- **Guía de Akuma no Mi:** conservar los tiers de Akuma y usar dificultad para
  las aventuras de obtención.
- **Guía de reputación:** incorporar la escala de fama y sus extras.
- **Guía de facciones:** eliminar misiones de temporada y cambiar requisitos de
  jerarquía basados en aventuras de cierto tier.
- **Guía de narradores:** actualizar requisitos y recompensas y eliminar la
  obligación de narrar el primer post.

## Decisiones pendientes antes de implementar

1. Confirmar si los enemigos de menor nivel deben reducir la recompensa bélica
   o aportar cero.
2. Precisar si cada `Ni` representa a todos los enemigos o solamente a enemigos
   adicionales, y qué enemigo queda fuera en ese segundo caso.
3. Definir qué ocurre si el multiplicador bélico llega a cero o es negativo.
4. Aclarar cómo se aplica el máximo del 10 % a la reputación bélica.
5. Definir el redondeo de Nikas y de todas las recompensas porcentuales.
6. Determinar sobre qué valor base se aplica cada porcentaje de los hitos.
7. Aclarar la tabla de mejoras de cofres para narradores.
8. Confirmar si los extras de fama se acumulan con los hitos y en qué orden se
    calculan.
9. Precisar si la recompensa base se calcula por post individual o por ronda
   completa, ya que ambos términos aparecen en la propuesta.
10. Confirmar la aparente excepción que permite recompensa proporcional cuando
    una aventura finaliza con menos de cinco rondas, pese a que también se dice
    que cinco rondas son necesarias para que sea válida.
