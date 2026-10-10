# Requisitos: Turno bélico (`belico.php`, tab "Turno")

> Continúa [103_Requirements_BelicoMejoras.md](103_Requirements_BelicoMejoras.md). Diseño:
> [204_DesignPlan_TurnoBelico.md](204_DesignPlan_TurnoBelico.md). Plan:
> [304_ImplementationPlan_TurnoBelico.md](304_ImplementationPlan_TurnoBelico.md).
>
> **Estado: fase 1 implementada (sin probar en navegador).** Reglas tomadas de la Guía Bélica (Acciones, Acción Adicional) y de
> las decisiones del equipo de esta conversación.

## 1. Propósito

Hoy el jugador arma su turno en tres sitios que no se hablan: el arma en Ataque, el tipo de arma otra vez en
Pasivas y los bloques en el Constructor, que no muestra ningún número. Al final copia dos bloques distintos y
completa los daños a mano.

El tab **Turno** reemplaza al Constructor: en un solo lugar el jugador arma el turno completo, en el mismo orden en
que se escribe el post, ve los daños, costes y recursos de cada bloque, y copia todo listo para pegar, ajustando lo
mínimo posible.

## 2. Decisiones ya tomadas

- **Turno reemplaza al Constructor** y es el tab principal. Ataque queda como calculadora rápida de una técnica y
  Pasivas como catálogo donde se marcan las pasivas del turno.
- **Un solo equipo para toda la página:** el arma y las pasivas se definen en el turno; Ataque y Pasivas los leen.
- **El post usa `[tecnica=ID]`** para cada técnica, seguido de sus números calculados.
- **Recursos actuales:** al empezar el turno se piden vida, energía y haki actuales; cada bloque muestra lo que gasta
  y lo que queda.
- **Pasivas:** activas para todo el turno, y en cada bloque se pueden añadir o quitar para ese bloque concreto.
- **Reglas de la guía = avisos.** Nunca impiden copiar.
- **Coste de las mantenidas:** el turno en que se activa por primera vez paga el **primer coste** (`energia` / `haki`);
  cada turno siguiente en que continúa paga el **coste por turno** (`energia_turno` / `haki_turno`), normalmente más
  bajo. La herramienta debe saber si una mantenida es **nueva** o **continúa** del turno anterior.
- **Técnicas instantáneas:** las de haki (código que empieza por `BUSO`, `KENB` o `HAO`) y los recubrimientos
  elementales (`AER301`, `AQU301`, `CRY301`, `ELE301`, `PIR301`, Recubrimiento Aero, Aqua, Cryo, Electro y Piro).
  Gastan su recurso pero **no ocupan espacio**: pueden ir antes de los bloques o dentro de cualquier bloque sin contar
  para sus límites ni para las acciones adicionales.
- **Cálculos en PHP** con la librería compartida (`belico_calculos.php`); el estado del turno vive en el navegador.

## 3. Reglas de la guía que la herramienta aplica

- **Bloque con técnica ofensiva/defensiva:** un único ataque o defensa con técnica, más **una** técnica de utilidad
  (activa o mantenida) **o** una acción adicional que lo afecte. Movimiento previo y posterior opcional.
- **Bloque sin técnica:** hasta **dos** acciones que no sean técnicas (golpe o bloqueo básico, lanzar un objeto, dial,
  consumible, agarre...). Movimiento previo y posterior opcional.
- **Bloque de utilidad:** hasta **dos** técnicas de utilidad (activas o mantenidas), y opcionalmente un bloqueo básico.
  Movimiento previo y posterior opcional.
- **Acciones adicionales:** 3 por turno (más las que den las pasivas). Consumen una: golpe o bloqueo básico, consumible,
  agarre, lanzamiento sin técnica, cambiar tipo de arma dual, equipar arma, dial (con movimiento); levantarse tras
  derribo, recuperar arma tras desarme, poner trampa (sin movimiento); soltarse de un agarre (sin movimiento previo).
- **Movimiento:** el total del turno no puede superar el movimiento del personaje.
- **Reacciones al turno anterior:** se escriben primero. Por cada bloque del rival que no se pudo cortar: +5 de
  Reflejos acumulativo hasta cortar la cadena.
- **Instantáneas** de haki y elementales: fuera de los bloques (ver §2).

## 4. Requisitos funcionales

### 4.1 Cabecera del turno
- Nombre del turno, **equipo** (configuración y armas, como en Ataque) y **pasivas del turno** (de la pestaña Pasivas).
- **Recursos actuales:** vida, energía y haki, con los máximos del personaje como referencia.
- **Mantenidas que continúan** del turno anterior: se eligen de la lista, no ocupan bloque y pagan su coste por turno.
  Se pueden desactivar.
- **Instantáneas antes de los bloques.**

### 4.2 Reacciones al turno anterior (opcional, hecho)
- Uno o más bloques de reacción con la TA del rival; se muestra el resultado con la tabla de reacciones existente y se
  aplica el +5 de Reflejos por cada bloque no cortado.

### 4.3 Bloques

**Cada bloque tiene un tipo y la interfaz solo ofrece los huecos que la guía permite para ese tipo** (no se pueden acumular acciones):
- **Con técnica:** un hueco de técnica ofensiva o defensiva y **un único** complemento (utilidad, mantenida o acción adicional).
- **Sin técnica:** hasta dos acciones que no son técnicas.
- **De utilidad:** hasta dos técnicas de utilidad o mantenidas y, opcionalmente, un bloqueo básico.
- **Instantáneas:** no ocupan hueco y se pueden poner en cualquier bloque.
- Cambiar el tipo reinicia los huecos (pide confirmación si había algo). El servidor sigue avisando por si llega un turno inválido.

- Se añaden por **lo que se hace** ("Atacar con técnica", "Golpe básico", "Moverse", "Consumible", "Activar
  mantenida"...); la herramienta decide el tipo de bloque de la guía y lo nombra. Se pueden reordenar y quitar.
- Cada bloque muestra: pasos en orden (movimiento previo, utilidad, principal, movimiento posterior), **daño, TA y
  coste** calculados con el arma y las pasivas del bloque, y los recursos que quedan después.
- Los bonos de la técnica de utilidad del bloque ("+X de Daño") se suman al ataque del mismo bloque, con un interruptor
  para quitarlos.
- **Pasivas por bloque:** se puede añadir o quitar una pasiva solo para ese bloque.
- Mantenidas activadas en un bloque: se marcan como **nuevas** (primer coste).
- Instantáneas dentro del bloque, en su orden, sin contar para los límites.
- Campo libre de detalle o narración por bloque.

### 4.4 Resumen y avisos
- Recursos al final del turno, movimiento usado de su máximo, acciones adicionales usadas, enfriamientos activados y
  mantenidas que pasan al siguiente turno.
- Avisos de reglas: límites de cada tipo de bloque, acciones adicionales, movimiento, recursos insuficientes, técnica
  repetida estando en enfriamiento, y los avisos de armas y requisitos que ya existen en Ataque.

### 4.5 Copiar al post
- Un único bloque BBCode con `[tecnica=ID]`, números calculados, recursos y movimiento. Vista previa **editable** antes
  de copiar y aviso "Copiado ✓".

### 4.6 Guardado
- El turno se guarda en el navegador (por usuario y personaje) y sobrevive a recargar la página.

## 5. Fases
1. **MVP:** cabecera (equipo, pasivas, recursos, mantenidas que continúan, instantáneas), bloques con cálculo, gastos y
   recursos restantes, avisos, copiar con vista previa.
2. Reacciones al turno anterior, bonos de utilidad sobre el ataque, pasivas por bloque.
3. "Pasar al siguiente turno" (las nuevas pasan a continuar, enfriamientos bajan, recursos y bloques sin cortar se
   arrastran), cambio de arma dentro del turno y plantillas de turno.

## 6. Decisiones sobre las preguntas abiertas

1. **Mantenidas sin primer coste** (267 de energía solo tienen `energia_turno`): el turno de activación paga ese único
   coste y se avisa "sin coste de activación en la ficha". **Confirmado.**
2. **Técnicas activas con coste por turno** (11, mal cargadas en la ficha de técnicas). **Se ignoran:** una técnica activa
   solo cobra `energia` y `haki`, y su `energia_turno` no se usa. Lista de referencia:

   | tid | Nombre | Estilo / rama | tipo | energía | por turno |
   |---|---|---|---|---|---|
   | 9U303 | Magma Sphere | Akuma no Mi / Única | Defensiva | - | 42 |
   | 932U401 | Dong | Akuma no mi / Única | **Mantenida** | - | 58 |
   | 9U401 | Magma Zoom | Akuma no mi / Única | Utilidad | - | 51 |
   | 333U301 | Palma de acero | Artista Marcial / Única | Utilidad | - | 44 |
   | 261U202E | ¿Falta aire? | ashigara dokoi / Única | Utilidad | - | 18 |
   | EFUN801 | Chokkaku Senko: Sutto Katto Sekai | Estilo Canon / Funekiri | Ofensiva | 106 | 117 |
   | EHKS901 | Hakai | Estilo Canon / Hakai Shin | Ofensiva | - | 87 |
   | EKNK502 | Kanpo Kenpo: Kakki no Kōraininjin | Estilo Canon / Kanpo Kenpo | **Mantenida** | - | 69 |
   | 136U302 | Pescar | Estilo Único / Única | Ofensiva | - | 45 |
   | 338U701 | Miku Miku Beam! | Estilo Único / Única | Ofensiva | 65 | 72 |
   | DFR701 | Una Bala Una Muerte | Tirador / Francotirador | Ofensiva | - | 98 |

   Dong y Kanpo Kenpo tienen `tipo` "Mantenida" con `clase` "Activa": probablemente la clase está mal. EFUN801 y 338U701
   tienen un coste por turno mayor que el de activación, lo contrario de la regla.
3. **Bloques interrumpidos:** sí. Se podrá marcar un bloque como interrumpido y sus costes no se cuentan (fase 3).
4. **Recursos negativos:** solo aviso.
5. **Enfriamiento N** (hecho): la técnica queda bloqueada durante los N turnos siguientes al uso. Al pasar de turno, lo usado queda N turnos y
   lo que ya se arrastraba baja uno; al llegar a 0 se libera. Los bloques interrumpidos no dejan enfriamiento. Usar una técnica bloqueada solo avisa.
   La lista "Técnicas en enfriamiento" se puede editar a mano (para turnos anteriores que la herramienta no vio).
