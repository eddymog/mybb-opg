# Proceso de trabajo

Cómo se pasa de una idea a una funcionalidad entregada en One Piece Gaiden. Lo siguen por igual una sola persona, un solo agente o varios agentes coordinados. Las reglas técnicas están en [AGENTS.md](../../AGENTS.md) y las responsabilidades de cada rol en [roles/](roles/).

Reglas de oro:
- **Las tareas son issues de GitHub, una a la vez.**
- **Se leen los criterios de aceptación antes de empezar y antes de cerrar.**
- **Se hace commit con regularidad.**
- **"Verde" significa que `sh tests/run_all.sh` termina en VERDE.** Nada se entrega ni se aprueba con la puerta en rojo.

## 1. Visión general

```
Idea del usuario
   |
   v
[1] Requisitos (PM)  ->  [1b] Reglas de juego (Game Designer, si aplica)  ->  [2] Diseño (PM + Developer)
                                                                       |
                                                                       v
                                                          [3] Issues de GitHub (PM)
                                                                  |
                                              el Orchestrator elige la siguiente issue
                                                                  |
                                                                  v
                                       [4] Desarrollo en bucle (Developer)  <--+
                                                                  |            |
                                                                  v            | rechazo
                                                       [5] Puerta de calidad (QA) -+
                                                                  |
                                                                  v
                                              [6] Entrega al usuario  ->  [7] Cierre de la issue
```

El usuario decide en los puntos marcados como **decisión del usuario**. El resto lo conducen los roles, organizados por el Orchestrator.

## 2. Roles

| Rol | Archivo | Qué hace en una frase |
|---|---|---|
| **Orchestrator** | [roles/orchestrator.md](roles/orchestrator.md) | Organiza este proceso y a los demás roles: elige la siguiente issue, asigna roles, hace cumplir las puertas y lleva el estado |
| **Program Manager** | [roles/program-manager.md](roles/program-manager.md) | Escribe requisitos, diseña el plan y crea las issues con sus criterios de aceptación |
| **Game Designer** | [roles/game-designer.md](roles/game-designer.md) | Convierte las ideas de reglas en reglas objetivas y equilibradas, con valores y ejemplos, y vigila que la guía coincida con el código |
| **UI Designer** | [roles/ui-designer.md](roles/ui-designer.md) | En issues que tocan interfaz, define los criterios visuales y revisa el resultado contra `docs/style.md` |
| **Software Developer** | [roles/software-developer.md](roles/software-developer.md) | Implementa una issue a la vez, con pruebas y commits regulares |
| **QA Tester** | [roles/qa-tester.md](roles/qa-tester.md) | Verifica de forma independiente contra los criterios de aceptación y decide si la issue se aprueba |

Cómo se reparten el trabajo:
- **El PM prepara, el Game Designer define las reglas, el Developer construye, el QA decide.** El Orchestrator solo mueve el trabajo entre ellos y vigila que se cumpla el proceso.
- El Game Designer interviene solo en funcionalidades que incluyen reglas de juego (puntajes, costes, recompensas, equilibrio). Las tareas puramente técnicas no pasan por él.
- El UI Designer interviene solo en issues que tocan interfaz visible (plantillas, hojas de estilo, páginas con HTML nuevo). Antes del desarrollo añade criterios visuales a la issue; después, y antes del QA, revisa el resultado contra `docs/style.md`. No ve la página renderizada: lo que solo se comprueba en un navegador lo decide el usuario con una captura. El PM marca esas issues con "Interfaz: sí".
- El Orchestrator es el dueño de este archivo y de los roles: los mantiene coherentes y propone cambios al usuario cuando detecta fricción. Nadie más los modifica sin su visto bueno y el del usuario.
- Un mismo agente puede ejercer varios roles en tareas distintas, pero **nunca el Developer y el QA de la misma issue**.

## 3. Documentos y numeración

| Tipo | Archivo | Quién lo escribe |
|---|---|---|
| Requisitos | `docs/1xx_Requirements_<Tema>.md` | PM |
| Diseño | `docs/2xx_DesignPlan_<Tema>.md` | PM con el Developer |
| Plan de implementación | `docs/3xx_ImplementationPlan_<Tema>.md` (índice de las issues) | PM |
| Reglas de juego | `docs/1xx_Reglas_<Tema>.md` | Game Designer |
| Guía para jugadores | `docs/<Tema>_BBCode.md` | Game Designer |
| Operación o guía | `docs/<nn>_Operacion_<Tema>.md` | Quien cierra la funcionalidad |
| Migraciones | `docs/<tema>_migration.sql` | Developer |

El mismo `<Tema>` y el mismo sufijo numérico enlazan los tres documentos de una funcionalidad (por ejemplo `108_Requirements_Notificaciones.md`, `208_DesignPlan_Notificaciones.md` y `308_ImplementationPlan_Notificaciones.md`). `docs/` está fuera de git: son documentos locales de trabajo, así que **la issue debe contener sus propios criterios de aceptación** y no depender de que quien la lea tenga el documento.

## 4. Tareas: issues de GitHub

Cada cosa que se va a implementar es una **issue de GitHub** en el repositorio del proyecto. Es la única fuente de verdad del estado de una tarea.

### Reglas
1. **Una issue a la vez.** Cada agente trabaja en una sola issue y no empieza otra hasta cerrar o bloquear la actual.
2. **Leer los criterios de aceptación antes de empezar y antes de cerrar.** Al empezar, para saber qué hay que construir. Al cerrar, para comprobar uno por uno que se cumplen. Una issue no se cierra con un criterio sin cumplir o sin evidencia.
3. **Commit regular.** Commits pequeños, cada uno con las pruebas en verde, y con el número de la issue en el mensaje. No se acumula trabajo sin guardar.
4. **Solo el QA aprueba y solo tras la aprobación se cierra.** Cerrar con `Closes #n` solo ocurre después de un veredicto APROBADA.
5. **Una issue, un cambio coherente.** Si durante el trabajo aparece algo fuera del alcance, se abre otra issue; no se mezcla.

### Plantilla de issue

```
Título: [Área] Verbo + objeto   (por ejemplo: [Notificaciones] Crear la librería de envío)
Carril: trivial | normal | grande
Interfaz: sí | no   (sí si toca plantillas, CSS o HTML visible: entra el UI Designer, ver roles/ui-designer.md)

## Contexto
Para qué sirve y enlace o nombre del documento de requisitos y diseño.

## Requisitos que cubre
Números de los requisitos (por ejemplo 1 a 8, 24 a 27).

## Alcance
- Archivos que crea o modifica (y nada más).
- Qué queda fuera.

## Criterios de aceptación
- [ ] Criterio comprobable 1
- [ ] Criterio comprobable 2

## Pruebas esperadas
Qué pruebas existirán al terminar y cómo se ejecutan.

## Depende de
#n, #m (issues que deben estar cerradas antes)

## Para el usuario
Qué tendrá que hacer al final (subir templates, ejecutar SQL, recargar caché), si aplica.

## Plan de reversión
Cómo deshacerlo si algo sale mal: qué plantilla anterior restaurar, qué SQL inverso ejecutar (comentado al final de la migración) o qué commit revertir. Poner "No aplica" solo si el cambio no toca plantillas, SQL ni datos.
```

### Etiquetas
- **Tipo:** se usan las etiquetas que ya trae el repositorio: `enhancement` (funcionalidad o mejora), `bug` y `documentation`.
- **Estado y área:** no hay etiquetas propias (no se han creado). El estado se anota con un comentario que empieza por `Estado:` (`lista`, `en-curso`, `en-revisión`, `bloqueada`), y el área va en el título (`[Notificaciones] ...`). Si el usuario crea etiquetas `estado:*` y `area:*` en el futuro, se pasan a usar.

El cierre de la issue (estado cerrado en GitHub) significa "aprobada y entregada".

### Comandos habituales (`gh`)
```
gh issue create --title "[Área] ..." --body-file cuerpo.md --label enhancement
gh issue list
gh issue view <n>
gh issue edit <n> --body-file cuerpo.md
gh issue comment <n> --body "Estado: en-curso"
gh issue close <n> --comment "Aprobada por QA. Cubre los requisitos X."
```

### Commits
- Mensaje corto en español, con el área y el número de la issue: `Notificaciones: crear tabla y plugin de instalación (#12)`.
- Un commit por paso coherente y verde (por ejemplo: pruebas, luego implementación, luego plantilla).
- Los commits se hacen en la rama de trabajo de la issue (`issue-<n>-<tema>`) cuando varios agentes trabajan a la vez, y en la rama actual si se trabaja en solitario.
- **Fusión y push (permiso permanente del usuario).** Los Developers solo hacen commit en la rama de su issue. Cuando el QA aprueba y `sh tests/run_all.sh` está en verde, el Orchestrator fusiona en `main` y hace el push. Nunca se hace `push --force`.
- **Despliegue.** Tras la fusión, el Orchestrator publica los archivos de la issue con `scripts/deploy_changed.sh` (primero `--dry-run` para revisar la lista y después `--yes` con los archivos de la issue), como pide `AGENTS.md`. **Lo retiene y avisa al usuario** si la issue tiene pasos acoplados: plantillas (al subirlas, `template_sync` las importa a la base en unos 10 segundos y quedan activas), migraciones SQL, activar un plugin, un script de migración de datos o una puesta en marcha conjunta con otra issue. Ese orden lo decide el usuario. Los Developers nunca despliegan.

## 5. Carriles según el tamaño del cambio

No todo cambio necesita el proceso completo. El PM (o el Orchestrator) asigna un carril al crear la issue y lo anota en ella.

| Carril | Cuándo | Qué se hace | Quién aprueba |
|---|---|---|---|
| **Trivial** | Texto, ortografía, estilo (CSS), comentarios o un valor de configuración. Hasta 3 archivos, sin lógica nueva, sin datos, sin migración, sin seguridad y sin reglas de juego | El Developer hace el cambio, ejecuta `sh tests/run_all.sh` y hace commit. Basta una issue de una línea o la petición del usuario en la conversación. Sin Game Designer ni QA aparte | El usuario, comprobando el resultado |
| **Normal** | Cualquier cambio de lógica, de datos, de una página, de seguridad, o un bug con lógica. Hasta unos 8 archivos | Proceso completo desde la issue con criterios: Developer, QA y entrega | QA y usuario |
| **Grande** | Funcionalidad nueva, tablas o migraciones nuevas, reglas de juego nuevas, o varias issues relacionadas | Todas las fases (requisitos, reglas de juego si aplica, diseño, issues, desarrollo, QA, entrega) | QA y usuario |

Reglas:
- **En caso de duda, el carril superior.**
- **Se sube de carril** en cuanto aparece lógica, datos, seguridad, una migración o más de 3 archivos en un cambio trivial. Se avisa en la issue y se sigue con el proceso del carril nuevo.
- **Nunca es trivial** un cambio que toque archivos prohibidos, una migración o código que modifique datos de personajes.
- Los carriles reducen pasos, no las barreras: la puerta de verde, el no tocar archivos prohibidos y la prohibición de escribir en producción valen en todos.
- Un cambio de plantilla (texto o estilo) puede ser trivial, pero el usuario tiene que subirla igualmente.

## 6. Fases

### Fase 1. Requisitos (PM)
- Recoger lo que el usuario quiere y redactarlo como requisitos numerados y comprobables.
- Separar el alcance de la primera fase de lo que queda fuera.
- Listar las preguntas abiertas con opciones y una recomendación.
- **Salida:** requisitos revisados. **Decisión del usuario:** aprobarlos o corregirlos.

### Fase 1b. Reglas de juego (Game Designer, solo si aplica)
- Cuando la funcionalidad incluye reglas (valores, umbrales, fórmulas, recompensas), el Game Designer las escribe en `docs/1xx_Reglas_<Tema>.md` con tablas de valores, ejemplos resueltos y los abusos previstos.
- Revisa el equilibrio y la coherencia interna, y calibra con los datos que el usuario le pase. Lo que aún no esté calibrado se marca como provisional.
- Si hay una guía para jugadores, la redacta o la ajusta para que coincida.
- **Salida:** documento de reglas y lista de decisiones pendientes. **Decisión del usuario:** elegir entre las opciones que le plantea y aprobar los valores.

### Fase 2. Diseño (PM y Developer)
- Proponer archivos, tablas, API interna, flujos y riesgos. Seguir los patrones del código existente.
- Anotar cómo se probará lo que sea delicado (concurrencia, rendimiento, comportamiento de MySQL).
- **Salida:** plan de diseño. **Decisión del usuario:** aprobarlo, sobre todo si cambia reglas de juego o interfaz.

### Fase 3. Issues de GitHub (PM)
Dividir el diseño en issues pequeñas, usando la plantilla de la sección 4, y crearlas en GitHub. El documento `3xx_ImplementationPlan_<Tema>.md` queda como índice: lista de issues con su número, su orden y sus dependencias.

Reglas:
- Cada issue se puede entregar y desplegar sola.
- Primero las issues que definen contratos (librerías, tablas). Después las que dependen de ellas.
- Dos issues que comparten archivos no van en paralelo.
- Todas empiezan con `estado:lista` y criterios de aceptación comprobables.
- **Permiso permanente del usuario:** el PM crea, edita y reorganiza las issues en GitHub **sin pedir aprobación previa**. El usuario puede revisarlas y cambiarlas cuando quiera.

#### Grooming (PM)
El PM mantiene el backlog limpio de forma continua, sin esperar a que se le pida:
- Antes de asignar una issue y después de cerrar cada una, revisa las siguientes: criterios comprobables, dependencias actualizadas, carril correcto y plan de reversión.
- Parte las issues demasiado grandes y une las que no tienen sentido por separado.
- Cierra como duplicadas u obsoletas las que lo sean, con un comentario que lo explique.
- Actualiza las issues cuando cambian los requisitos o el diseño, y deja un comentario con el cambio.
- Mantiene el orden y las dependencias, y actualiza el índice `3xx_ImplementationPlan_<Tema>.md` con los números reales.

### Fase 4. Desarrollo en bucle (Developer)
El Orchestrator asigna una issue. Por cada una, en este orden:
1. **Leer los criterios de aceptación de la issue**, AGENTS.md, el rol, los requisitos, el diseño y el código vecino. Marcar la issue como `estado:en-curso`.
2. Escribir las pruebas de la lógica pura a partir de los criterios y verlas fallar. Commit.
3. Implementar. Commits pequeños y verdes.
4. Ejecutar `sh tests/run_all.sh` (la puerta de verde: archivos prohibidos, sintaxis, credenciales y pruebas) y las pruebas específicas de la issue. Si falla, corregir y repetir.
5. **Detectar la falta de progreso.** Hay progreso si, respecto a la vuelta anterior, fallan menos pruebas o el fallo es distinto. **Si dos vueltas seguidas dan la misma salida de fallo, se cambia de enfoque** (releer el criterio y el código vecino, reducir el alcance o dejar la duda en la issue) en lugar de repetir. Si la tercera vuelta sigue igual, parar y marcar `estado:bloqueada` aunque no se hayan gastado las 5 vueltas.
6. **Máximo 5 vueltas.** Si no llega a verde, parar, marcar `estado:bloqueada` y reportar en un comentario de la issue.
7. **Releer los criterios de aceptación** y comprobarlos uno por uno antes de entregar.
8. Entregar al QA con el informe de entrega (como comentario de la issue) y marcar `estado:en-revisión`.

### Fase 5. Puerta de calidad (QA)
- El QA **lee los criterios de aceptación de la issue**, ejecuta de nuevo todo, incluido `sh tests/run_all.sh` (no se fía del informe del Developer), traza cada requisito a su evidencia y revisa con la lista de seguridad e integridad de su rol, y comprueba que existe el plan de reversión cuando la issue toca plantillas, SQL o datos. Si la issue implementa reglas de juego, contrasta el código con la tabla de valores y los ejemplos resueltos del Game Designer.
- Publica el informe de revisión como comentario de la issue, con el veredicto: APROBADA, APROBADA CON OBSERVACIONES o RECHAZADA.
- Si rechaza, la issue vuelve a `estado:en-curso` con la lista de defectos. **Máximo 2 rechazos por issue**; a la tercera, el Orchestrator escala al usuario.

### Fase 6. Entrega
- Reunir lo que el usuario tiene que hacer: subir templates a MyBB, ejecutar SQL, recargar caché, probar en el navegador.
- **Copia de seguridad y reversión.** Antes de subir plantillas o ejecutar SQL, el usuario guarda lo que va a reemplazar (las plantillas actuales de la base, o el export de las tablas afectadas). La entrega repite el plan de reversión de la issue. Toda migración termina con el SQL para deshacerla, comentado (por ejemplo `-- ALTER TABLE ... DROP COLUMN ...`).
- Presentar el resultado en español neutral, con la lista de archivos modificados y creados.
- **Decisión del usuario:** probar y dar el visto bueno. Los cambios de template y las migraciones los ejecuta el usuario, nunca el agente.

### Fase 7. Cierre de la issue
- **Releer los criterios de aceptación** y comprobar que todos están marcados con evidencia.
- El Orchestrator cierra la issue con un comentario que enlaza el commit final (`Closes #n`), solo si el QA aprobó.
- El PM actualiza los documentos (requisitos, diseño, plan) con lo que realmente se hizo y las decisiones tomadas, y la guía de operación si el staff necesita saber algo nuevo.
- El Orchestrator elige la siguiente issue.

## 7. Definiciones

**Lista para empezar (Definition of Ready).** Los requisitos están redactados sin dudas abiertas, los criterios de aceptación son comprobables y están en el cuerpo de la issue, tiene carril asignado y plan de reversión (si aplica), las dependencias están cerradas y no se toca ningún archivo prohibido.

**Terminada (Definition of Done).**
- `sh tests/run_all.sh` termina en VERDE y se ha revisado cada aviso.
- Las pruebas específicas de la issue pasan.
- Cada criterio de aceptación tiene evidencia, comprobada al cerrar.
- El QA aprobó.
- Hay commits con el número de la issue.
- El informe de entrega dice qué debe hacer el usuario.
- Si toca plantillas, SQL o datos, el plan de reversión está escrito.
- Los documentos reflejan lo hecho.

## 8. Escalada al usuario

El Orchestrator pasa al usuario, con el contexto mínimo para decidir, cuando:
- Hay una decisión de reglas del juego (la plantea el Game Designer con opciones y una recomendación), de diseño visual o de producto.
- Un cambio exige ejecutar SQL, subir un template o tocar producción.
- Una issue falla 3 veces en el bucle, no muestra progreso en 3 vueltas o el QA la rechaza 2.
- Los requisitos son ambiguos o se contradicen.
- Algo obligaría a modificar `global.php`, `inc/config.php`, el núcleo de MyBB o `admin/`.
- Se propone cambiar este proceso o un rol.

## 9. Trabajo con varios agentes

Cuando una funcionalidad se reparte entre agentes, el Orchestrator coordina.

El proceso y los roles no dependen de la herramienta. Lo que cambia es cómo se lanza cada rol:

| Rol | Cómo se ejerce | Notas |
|---|---|---|
| Orchestrator | La sesión principal (o un script que lance las demás) | No escribe código |
| PM, planificación | La sesión principal, en modo de solo lectura para planificar | Devuelve el plan; las issues las crea quien coordina |
| Game Designer | La sesión principal o una sesión aparte | Trabaja con documentos y datos que le pasa el usuario; sin acceso a producción |
| UI Designer | Una sesión de solo lectura, antes y después del Developer | Lee `docs/style.md` y el código; no escribe lógica |
| Mapa de patrones del código | Una sesión de búsqueda rápida y de solo lectura | Localiza cómo se hace algo hoy |
| Developer | Una sesión por issue, en su propio worktree | Archivos propios |
| QA | Una sesión distinta, con contexto limpio y de solo lectura | Nunca la misma que implementó |

### Con Claude Code
- Orchestrator: la sesión principal, o un agente propio con permisos para lanzar a otros.
- PM, planificación: el agente `Plan` (solo lectura).
- Game Designer: la sesión principal o `general-purpose`.
- UI Designer: `general-purpose` con instrucciones de solo lectura y el rol `docs/agents/roles/ui-designer.md`.
- Mapa de patrones: `Explore`.
- Developer: `general-purpose` con `isolation: "worktree"`.
- QA: `general-purpose` con instrucciones de solo lectura y contexto limpio, o un agente propio en `.claude/agents/` con permisos limitados.

### Con Codex
- Codex lee `AGENTS.md` de la raíz y no tiene los tipos de agente anteriores: cada rol es una sesión o tarea independiente a la que se le indica el rol (`docs/agents/roles/<rol>.md`), la issue y los documentos.
- Orchestrator: una sesión principal o un script que lance las demás en modo no interactivo (por ejemplo con `codex exec`; comprobar la sintaxis vigente en su documentación).
- Developer y QA: sesiones distintas, cada una en su worktree o copia del repositorio. El QA se lanza en modo de solo lectura (sin permisos de escritura).
- La puerta de verde (`sh tests/run_all.sh`), las issues con `gh` y los informes son los mismos.

Reglas:
- **Contrato primero.** La issue que define las firmas de las librerías la hace un solo agente. Hasta que esté cerrada, no se lanza nada en paralelo que dependa de ella.
- **Archivos disjuntos.** Cada agente es dueño de sus archivos. Un archivo compartido (por ejemplo `header.html`) lo toca un solo agente.
- **Máximo 3 agentes en paralelo.** Más genera conflictos de integración y gasto sin ganancia.
- **Cada agente en su propio worktree y rama de issue.** La integración se hace de una en una, ejecutando todas las pruebas tras cada fusión.
- **Verificar, no fiarse.** El Orchestrator vuelve a ejecutar `sh tests/run_all.sh` tras cada entrega en lugar de creer el informe.
- **Nadie revisa su propio trabajo.**
- **Sin producción.** Los agentes automatizados usan una base de datos local desechable. El acceso de solo lectura a producción lo usa una persona o el agente principal, bajo petición expresa del usuario.
- **El prompt de cada agente es completo,** porque arranca sin memoria: rol, AGENTS.md, número de la issue con sus criterios, documentos y archivos permitidos.

### Estado de las issues
Cada issue pasa por: `estado:lista`, `estado:en-curso`, `estado:en-revisión`, cerrada (aprobada y entregada) o `estado:bloqueada`. El Orchestrator mantiene las etiquetas, cuenta los intentos y deja un comentario por cada decisión relevante.

## 10. Formatos de traspaso

Todos los informes se publican como comentarios de la issue, para que el historial quede junto a la tarea.

**Informe de entrega (Developer a QA).** Qué hace el cambio (3 líneas máximo), criterios de aceptación cubiertos, archivos modificados y creados, commits, cómo se probó (comandos y resultado real), qué debe hacer el usuario, cómo deshacerlo y decisiones o dudas abiertas.

**Informe de revisión (QA al Orchestrator y al PM).** Veredicto, tabla de evidencia por criterio de aceptación y por requisito, defectos (gravedad, dónde, cómo reproducirlo, esperado y real) y lo que no se pudo verificar.

**Comunicación al usuario (Orchestrator o PM).** Español neutral y breve: qué está hecho, qué falta, qué necesita del usuario y lista de archivos modificados.

## 11. Cosas que nunca se hacen

- Ejecutar migraciones o escribir en producción sin aprobación expresa para esa acción concreta.
- Fusionar o hacer push a la rama principal sin la aprobación del QA y la puerta de verde, o hacer `push --force`.
- Desplegar a producción antes de la aprobación del QA y la fusión, desplegar un cambio con pasos acoplados (plantillas, SQL, activación de plugins, migraciones de datos) sin que el usuario lo decida, o ejecutar SQL en producción.
- Imprimir, guardar o versionar credenciales.
- Cerrar una issue con un criterio de aceptación sin cumplir o sin evidencia.
- Trabajar en dos issues a la vez o acumular trabajo sin commit.
- Ampliar el alcance de una issue sin abrir otra.
- Aprobar una issue que el QA no ha ejecutado.
- Dar por entregado algo con la puerta de verde en rojo.
- Añadir una prueba a `tests/known_failures.txt` para esquivar un fallo nuevo: esa lista solo se amplía con permiso expreso del usuario.
