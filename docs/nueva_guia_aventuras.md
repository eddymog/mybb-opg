# Nueva guía de aventuras

> Propuesta de sistema todavía no implantado.

## Objetivo

El sistema de aventuras debe permitir que cualquier usuario desarrolle a su
personaje y, cuando lo desee, pueda influir en el mundo de One Piece Gaiden.
Debe ser sencillo para jugadores, requerir poco trabajo administrativo a los
narradores y dejar un registro claro de los cambios canónicos.

El sistema se apoya en cuatro principios:

1. Jugar sin cambiar el mundo debe requerir el mínimo de trámites.
2. Cambiar el mundo debe ser posible, pero sus límites se pactan antes de jugar.
3. El impacto no se decide subjetivamente al final: se comprueban condiciones
   acordadas de antemano.
4. El foro automatiza cálculos, avisos, permisos y registros siempre que sea
   posible.

## Dos tipos de aventura

Al crear una aventura solo hay que responder una pregunta:

> ¿Quieres que esta aventura pueda modificar el lore general?

### Aventura personal

Es la opción predeterminada. Sirve para desarrollar al personaje, combatir,
entrenar un oficio, obtener recompensas, investigar rumores o vivir historias
que no alteren permanentemente el mundo.

- No necesita aprobación previa de lore.
- Puede dirigirla cualquier narrador habilitado.
- Puede incluir NPC y lugares originales de importancia menor.
- Puede afectar al lore personal de los participantes.
- No puede cambiar elementos oficiales o persistentes de una isla.
- Si durante la partida surge una oportunidad de impacto, puede solicitarse la
  conversión antes de narrar ese cambio.

### Aventura de impacto

Busca producir un cambio persistente en una isla, mar, facción o en el mundo.

- Requiere una propuesta previa.
- El Tier máximo y sus condiciones se pactan antes de abrir el tema.
- Solo puede dirigirla un narrador con el rango necesario.
- Tiene un moderador de lore asignado.
- Al finalizar genera una entrada en la cronología si se cumple un desenlace
  canónico aprobado.

Elegir una aventura personal nunca reduce la recompensa base. Influir en el
mundo es una posibilidad narrativa, no una obligación para progresar.

## Qué es impacto de lore

Existe impacto cuando la aventura cambia un elemento establecido del mundo y
ese cambio debe respetarse en futuras narraciones.

Para considerarlo impacto deben cumplirse estas cuatro condiciones:

1. **Cambio comprobable:** pueden describirse un estado anterior y otro
   posterior diferentes.
2. **Persistencia:** la consecuencia continúa después de cerrar el tema.
3. **Alcance colectivo:** afecta a una comunidad, zona o institución, no solo a
   los participantes.
4. **Continuidad:** las futuras aventuras relacionadas deben reconocerlo.

No son impacto por sí solos:

- que la aventura sea difícil o espectacular;
- que aparezca un NPC oficial;
- que un personaje corra peligro de muerte;
- descubrir información sin actuar sobre ella;
- destruir algo que se reconstruirá sin consecuencias;
- conseguir un ascenso, propiedad o reconocimiento personal.

## Tier de Lore

El Tier de Lore (`TL`) mide la sensibilidad y complejidad narrativa de una
aventura. No depende únicamente del territorio afectado: también considera el
poder de los NPC, su autoridad, su importancia canónica y las consecuencias que
pueden producirse.

| Tier | Tipo de intervención |
| --- | --- |
| `TL-0` | Lore personal, NPC propios y ninguna consecuencia canónica |
| `TL-1` | NPC menores, conflictos cotidianos y consecuencias reversibles |
| `TL-2` | NPC reconocidos, autoridades menores o elementos de relevancia moderada |
| `TL-3` | NPC importantes, organizaciones establecidas o consecuencias difíciles de revertir |
| `TL-4` | Altos mandos, líderes, NPC oficiales importantes o cambios estructurales |
| `TL-5` | Figuras centrales, secretos críticos o acontecimientos capaces de cambiar el escenario general |

El Tier solicitado es el máximo nivel de intervención autorizado para la
aventura. El Tier obtenido registra el desenlace que realmente ocurrió. Aprobar
una propuesta `TL-3` no garantiza que termine produciendo una consecuencia de
ese nivel.

### Dimensiones de evaluación

Cada propuesta valora cuatro dimensiones de 0 a 5:

| Dimensión | 0-1 | 2 | 3 | 4 | 5 |
| --- | --- | --- | --- | --- | --- |
| Poder del NPC | Civil o amenaza menor | Combatiente competente | Combatiente de élite | Alto mando | Poder excepcional |
| Autoridad | Sin influencia | Influencia local | Dirige una organización | Gobierna o manda una gran división | Decide sobre grandes facciones |
| Relevancia canónica | NPC original | NPC local registrado | NPC oficial secundario | NPC oficial importante | Figura central del lore |
| Consecuencia | Personal o reversible | Persistente y limitada | Difícil de revertir | Cambio estructural | Cambio fundamental del escenario |

Como regla general, el Tier recomendado es el valor más alto alcanzado:

```text
TL recomendado = máximo(
    poder del NPC,
    autoridad,
    relevancia canónica,
    consecuencia
)
```

Usar el valor máximo evita que varios factores poco importantes oculten un solo
elemento especialmente delicado. El moderador puede ajustar la recomendación,
pero debe dejar registrado el motivo.

### NPC poderosos e importantes

El poder de combate y la importancia narrativa no son equivalentes:

- Un combatiente extremadamente fuerte pero aislado puede tener escasa
  influencia en el lore.
- Un rey físicamente débil puede tener gran relevancia por su autoridad.
- La aparición breve de una figura central puede requerir supervisión oficial
  aunque no cambie nada en el mundo.

Por ello, la propuesta conserva por separado:

```text
Tier de Lore: sensibilidad narrativa y autoridad requerida
Dificultad Bélica: peligro real de los enfrentamientos
```

Ejemplos:

```text
Reunión diplomática con un rey:
Tier de Lore: TL-4
Dificultad Bélica: baja

Cacería de una bestia extremadamente poderosa sin relevancia canónica:
Tier de Lore: TL-1
Dificultad Bélica: extrema
```

El poder del NPC contribuye a recomendar el Tier porque aumenta la complejidad
de la intervención, pero la Dificultad Bélica sigue determinando por separado
el peligro y los cálculos del combate.

### Comprobación de las consecuencias

La propuesta debe responder:

```text
¿Qué era cierto antes de la aventura?
¿Qué podría dejar de ser cierto?
¿Quiénes resultarían afectados?
¿Durante cuánto tiempo?
¿Qué futuras narraciones tendrían que reconocer el cambio?
```

El alcance geográfico forma parte de las consecuencias, pero no define por sí
solo el Tier. Una aventura puede ser `TL-3` por involucrar información protegida
o un NPC importante aunque ocurra en una sola habitación.

## Rangos de narrador

El rango determina el Tier máximo que un narrador puede dirigir. No concede
autoridad para modificar el canon sin aprobación.

| Rango | Tier que puede dirigir |
| --- | --- |
| Narrador Aprendiz | `TL-0` |
| Narrador Estudioso | Hasta `TL-1` |
| Narrador Ilustre | Hasta `TL-2` |
| Narrador Erudito | Hasta `TL-3`, con aprobación |
| Narrador Oficial | `TL-4` y `TL-5` |

Un narrador puede proponer una aventura superior a su rango. En ese caso, el
sistema permite asignar un codirector habilitado en vez de rechazar la idea.

## Crear una aventura personal

El formulario solicita únicamente:

```text
Título
Participantes
Narrador
Resumen de la idea
Hitos que podrían aparecer (opcional)
```

Al confirmar, el sistema:

1. crea el registro de aventura;
2. genera o vincula el tema del foro;
3. inserta los códigos de narrador y ficha necesarios;
4. comienza a contar rondas y posts válidos;
5. muestra al narrador una lista breve de los datos que deberá confirmar al
   finalizar.

No se abre una revisión de lore ni un chat con el staff.

## Proponer una aventura de impacto

La propuesta utiliza un asistente de pasos cortos.

### Paso 1: intención

```text
Isla, mar o facción afectada
Estado actual
Cambio que se desea intentar
Motivación de los personajes
```

### Paso 2: Tier sugerido

El formulario pregunta por los NPC, su nivel, autoridad e importancia, los
elementos canónicos y las posibles consecuencias. Con esas respuestas propone
un Tier de Lore y una Dificultad Bélica independientes. El narrador puede
solicitar otro Tier, pero debe justificarlo.

### Paso 3: desenlaces

Toda propuesta define resultados comprobables:

```text
Victoria completa:
Victoria parcial:
Derrota:
Límites que no pueden alterarse:
```

Cada desenlace indica el Tier de la consecuencia que produciría. Por ejemplo:

```text
Tier solicitado: TL-3

Victoria completa (TL-3):
La isla se incorpora oficialmente al Gobierno Mundial.

Victoria parcial (TL-2):
Se firma un acuerdo comercial, pero la isla conserva su neutralidad.

Derrota (TL-0):
No se firma ningún acuerdo y la situación política no cambia.
```

Una derrota también puede producir una consecuencia canónica. Si esto es
posible, debe pactarse como un desenlace adicional antes de empezar.

### Paso 4: revisión

El sistema comprueba automáticamente:

- que el narrador tenga el rango necesario;
- que no falten campos obligatorios;
- que los participantes no estén jugando en paralelo otra parte de la misma
  cadena;
- que los NPC y elementos oficiales estén identificados;
- que la isla no tenga demasiadas propuestas de impacto activas;
- que el Tier coincida razonablemente con los NPC, la autoridad, el canon y las
  consecuencias indicadas;
- que la Dificultad Bélica se haya calculado separadamente.

Si todo es correcto, asigna la propuesta al responsable apropiado.

## Conversación de la propuesta

Cada propuesta de impacto tiene un hilo interno entre narrador, codirector y
moderador. No es necesario utilizar Discord para conservar decisiones
importantes fuera del foro.

El hilo permite:

- mensajes fechados;
- preguntas y respuestas;
- solicitudes de cambios;
- enlaces a guías o antecedentes;
- incorporación de otros responsables;
- historial de estados y aprobaciones.

Los mensajes ayudan a discutir, pero no constituyen por sí solos el acuerdo.
Cuando las partes terminan, el moderador guarda un bloque visible llamado
**Condiciones aprobadas**:

```text
Tier de Lore máximo:
Dificultad Bélica:
Elementos oficiales permitidos:
Condiciones de victoria completa:
Condiciones de victoria parcial:
Consecuencias de derrota:
Límites narrativos:
Entradas de cronología relacionadas:
```

Una modificación posterior crea una nueva versión. El historial anterior no se
borra.

## Estados del proceso

```text
Borrador
  → Pendiente de revisión
  → Cambios solicitados
  → Aprobada
  → En curso
  → Pendiente de resultado
  → Validada
  → Registrada en cronología
```

También pueden utilizarse estos estados de cierre:

- **Retirada:** el narrador cancela la propuesta antes de comenzar.
- **Caducada:** la aventura aprobada no comienza dentro del plazo establecido.
- **Abandonada:** una de las partes deja una aventura ya iniciada.
- **Rechazada:** la propuesta no puede realizarse dentro de sus límites.

El sistema registra quién produjo cada cambio de estado y cuándo ocurrió.

## Durante la aventura

El panel muestra únicamente información útil:

- ronda actual;
- posts recompensables por participante;
- hitos seleccionados;
- condiciones de impacto aprobadas;
- avisos pendientes;
- acceso al tema y a la conversación interna.

El sistema envía avisos automáticos al acercarse a las rondas 5 y 20. Desde la
ronda 21 marca los posts como no recompensables, aunque permite continuar el
tema.

Si la historia necesita superar los límites aprobados, el narrador pulsa
**Solicitar ampliación**. La escena que produciría el nuevo cambio no debe
narrarse hasta recibir respuesta. El resto de la aventura puede continuar.

## Finalización

El narrador pulsa **Finalizar aventura** y el sistema genera un resumen con los
datos que ya conoce:

```text
Participantes y posts válidos
Número de rondas
Hitos seleccionados
Combates registrados
Objetos consumidos u obtenidos
Condiciones de impacto aprobadas
```

El narrador solo completa:

```text
Resumen del desenlace
Desenlace pactado que se alcanzó
Hallazgos de lore
NPC oficiales que intervinieron
Entrenamiento de oficio realizado
Observaciones excepcionales
```

### Aventura personal

Si no existen alertas, el sistema calcula la recompensa y envía la solicitud al
staff para una comprobación rápida. No requiere validación de lore.

### Aventura de impacto

El moderador recibe una comparación entre las condiciones pactadas y el
desenlace declarado. Sus acciones son:

- **Validar:** acepta el desenlace declarado.
- **Solicitar aclaración:** pide un dato concreto al narrador.
- **Seleccionar otro desenlace pactado:** corrige la clasificación explicando
  el motivo.

No puede asignar un impacto inventado al final. Debe escoger uno de los
desenlaces aprobados, salvo que eleve el caso para una revisión excepcional.

## Registro automático en la cronología

Al validar una consecuencia canónica, el sistema prepara una entrada con:

```text
Fecha on-rol
Lugar
Título del evento
Resumen público
Alcance del impacto
Personajes participantes
NPC involucrados
Enlace a la aventura
Propuesta y moderador responsables
Estado anterior y estado posterior
```

El moderador puede editar el resumen público y pulsar **Validar y registrar**.
La entrada se añade automáticamente a la cronología correspondiente y queda
vinculada a la aventura.

Si el cambio afecta varias islas o una facción, el sistema crea referencias en
cada cronología afectada sin duplicar el evento principal.

## Hitos y recompensas

Cada participante recibe una recompensa base por sus posts válidos. Los hitos
añaden recompensas cuando realmente se cumplen:

- Bélico.
- Hallazgo de lore.
- Impacto en lore.
- Entrenamiento de oficio.
- Inframundo.

Solo pueden seleccionarse dos hitos por aventura. La selección se realiza antes
de finalizar el tema para que el narrador conozca qué datos debe registrar.

El sistema debe calcular automáticamente:

- posts y rondas recompensables;
- recompensa base por participante;
- bonificación por hallazgo y por Tier de Lore obtenido;
- multiplicador bélico a partir de los niveles registrados;
- límites máximos aplicables;
- Nikas, Berries, reputación y puntos de oficio;
- bonos por fama;
- recompensa del narrador según su rango;
- valor acumulado para obtener un objeto.

Antes de confirmar, debe mostrar una explicación legible del cálculo, no solo
el resultado final.

## Reglas de rondas

- Una aventura estándar necesita al menos 5 rondas completas.
- Se recompensan como máximo 20 rondas.
- Puede continuar después de la ronda 20, pero los posts posteriores no generan
  recompensa ni aumentan el hito bélico.
- Un combate no terminado dentro de las 20 rondas cuenta como derrota si no se
  continúa.
- No se permite fragmentar una historia en temas mínimos para multiplicar sus
  recompensas.
- Cada tema debe tener inicio y conclusión, aunque deje cabos para el futuro.

El sistema marca posibles fragmentaciones para revisión, pero no las sanciona
automáticamente.

## Objetos como recompensa

La aventura puede destinar su recompensa de Berries a obtener un objeto
crafteable de oficio.

- La aventura o cadena debe acumular el 150 % del precio del objeto.
- El objeto y su precio se fijan al aprobar el objetivo.
- El sistema muestra el progreso acumulado de la cadena.
- Si el objeto se obtiene mediante saqueo, su precio se descuenta de los
  Berries generados.
- Al alcanzar el requisito, el sistema sustituye automáticamente la recompensa
  monetaria correspondiente por el objeto.

## Herramientas para reducir trabajo

### Para narradores

- Formularios cortos que muestran campos adicionales solo cuando hacen falta.
- Plantillas de desenlace reutilizables.
- Resumen final parcialmente completado por el sistema.
- Contador automático de posts y rondas.
- Cálculo automático de recompensas.
- Avisos solo cuando se necesita una acción.
- Botón para convertir una aventura personal en propuesta de impacto.
- Panel único con todas las aventuras y su próximo paso.

### Para moderadores

- Cola separada por isla, facción y Tier de Lore.
- Asignación automática según responsabilidad y carga activa.
- Filtros para ver únicamente propuestas que requieren atención.
- Comparación automática entre propuesta y resultado.
- Respuestas rápidas para solicitar datos faltantes.
- Registro de cronología generado automáticamente.
- Reasignación cuando el responsable está ausente.
- Panel de capacidad para evitar demasiados cambios simultáneos en una isla.

### Para jugadores

- Explicación breve de las consecuencias de elegir impacto o aventura personal.
- Estado visible de la solicitud.
- Recompensa estimada durante la aventura.
- Historial de aventuras e impacto conseguido.
- Enlace directo desde la ficha del personaje a los eventos canónicos en los
  que participó.

## Notificaciones

Las notificaciones deben agruparse y enviarse solo cuando haya una acción útil:

- propuesta enviada o asignada;
- cambios solicitados;
- propuesta aprobada;
- aventura próxima a caducar;
- ronda 5 o 20 alcanzada;
- resumen final pendiente;
- resultado validado;
- evento registrado en la cronología.

Los recordatorios repetidos deben agruparse en un único aviso periódico.

## Permisos y trazabilidad

- Solo el narrador asignado puede enviar o modificar un borrador.
- Solo responsables autorizados pueden aprobar impacto.
- Las condiciones aprobadas quedan bloqueadas y versionadas.
- Todo cambio manual de recompensas requiere un motivo.
- Las conversaciones y decisiones permanecen vinculadas a la aventura.
- Los jugadores pueden consultar las condiciones públicas, pero no información
  secreta que sus personajes aún no conocen.
- El staff puede auditar autores, fechas, versiones y cambios de estado.

## Información pública y privada

La propuesta distingue tres niveles:

- **Público:** título, participantes, estado, Tier máximo y cronología final.
- **Participantes:** condiciones conocidas por los personajes y progreso.
- **Narración y staff:** secretos, desenlaces alternativos, NPC ocultos y chat
  de coordinación.

Esto permite registrar el proceso sin revelar anticipadamente la trama.

## Métricas del sistema

Para saber si el proceso funciona deben medirse:

- tiempo medio hasta aprobar una propuesta;
- número de propuestas esperando revisión;
- aclaraciones solicitadas por aventura;
- aventuras abandonadas o caducadas;
- distribución de impactos por isla y narrador;
- tiempo dedicado por moderadores;
- porcentaje de registros de cronología generados sin correcciones manuales.

Estas métricas sirven para simplificar el proceso y redistribuir carga, no para
premiar la cantidad de impacto producido.

## Decisiones pendientes

Antes de programar deben confirmarse:

1. Recompensas base y fórmulas definitivas.
2. Método de redondeo de porcentajes y monedas.
3. Plazos para revisar, comenzar y finalizar propuestas.
4. Número máximo recomendado de impactos activos por isla.
5. Quién puede validar cada isla, mar y facción.
6. Reglas para sustituir a narradores o moderadores ausentes.
7. Tratamiento de recompensas cuando una aventura termina antes de cinco
   rondas.
8. Visibilidad exacta de propuestas con contenido secreto.
9. Procedimiento de apelación ante una validación disputada.
10. Migración de aventuras que estén en curso al implantar el sistema.

## Resumen del recorrido ideal

```text
¿Quieres cambiar el lore general?
│
├─ No
│  Crear aventura → Jugar → Resumen breve → Recompensa automática
│
└─ Sí
   Proponer cambio
      → Pactar desenlaces y Tier máximo
      → Aprobar
      → Jugar
      → Elegir el desenlace ocurrido
      → Validar
      → Recompensa y cronología automáticas
```

La complejidad aparece solo cuando el usuario decide cambiar el mundo. Incluso
en ese caso, la mayor parte del trabajo se realiza una vez, antes de comenzar,
y el cierre consiste en comprobar condiciones ya acordadas.
