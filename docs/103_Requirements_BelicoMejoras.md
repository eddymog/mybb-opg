# Requisitos: mejoras de la Página Bélica (`belico.php`)

> Continúa [100_Requirements_Belico.md](100_Requirements_Belico.md). Diseño:
> [203_DesignPlan_BelicoMejoras.md](203_DesignPlan_BelicoMejoras.md). Plan:
> [303_ImplementationPlan_BelicoMejoras.md](303_ImplementationPlan_BelicoMejoras.md).
>
> **Estado: implementado, pendiente de despliegue y de prueba en navegador.**

## 1. Propósito

La primera versión de `belico.php` (100/200/300 Belico) calculaba golpe, tasa de
acierto y reacción con reglas propias de la página. Esta iteración hace tres cosas:

1. **Una sola fuente de cálculo.** El tab Ataque usa los mismos cálculos que
   `tecnicas_show.php` (`inc/plugins/lib/belico_calculos.php`), con el mismo formato.
2. **Valores calculados dentro del texto de las técnicas**, tanto en efectos como en
   descripciones, con el desglose a la vista y un interruptor Sin cálculo / Con cálculo.
3. **Un tab Pasivas útil de verdad:** saber qué pasivas aplican con el arma en mano,
   sumar sus bonos y copiarlas al post.

## 2. Decisiones ya tomadas

- **Librería compartida.** Las reglas de cálculo viven en `inc/plugins/lib/belico_calculos.php`.
  `belico.php` y `tecnicas_show.php` la usan. No hay dos copias de la lógica.
- **Ataque como en `tecnicas_show.php`:** configuración de armas (sin arma, una arma, una arma
  con ambas manos, dos armas, tres armas), no el selector antiguo de 1 a 4 armas con TA por arma.
  Se muestran siempre Golpe básico, Bloqueo básico y Tasa de acierto (ya no hay modo ataque/defensa).
- **La TA sale del arma**, no de un selector: campo `escalado` (`[Tasa de Acierto = Destreza o Puntería]`,
  con varias opciones se toma el atributo más alto) y modificador `+N Tasa de Acierto` del campo `efecto`.
  Sin arma: Agilidad. Si el arma no indica TA: Agilidad, y el desglose lo dice.
- **Reglas de armas vigentes** (las que pegó el equipo, que reemplazan a la guía en el repo):
  una arma a dos manos +50% al daño y bloqueo base; dos armas 75% de cada una y **sin penalización de TA**;
  más de dos armas 60% de cada una y -5 de TA por arma; con TA distintas se usa la más baja.
- **Pasivas "Senda" ignoradas.** Son los bonos fijos de atributo de cada disciplina y ya están en la ficha.
- **Acumulación de tiers.** Fundamentos, Iniciado y Maestro se acumulan: se muestran todas.
- **Atributo suelto no se calcula.** `[Voluntad]`, `[VOL]` son nombres de estadística. Solo se calcula
  una fórmula con operador (`[VOL/3]`, `[VOLx2]`, `Nivel/2`). `[Nivel]` suelto sí cuenta como valor.
- **Sin columna JSON en `mybb_op_tecnicas` por ahora.** Se valoró (condiciones estructuradas por técnica) y
  se pospuso. Las condiciones se detectan por texto y son solo informativas.
- **Sin cambios de base de datos.** Todo se guarda en `localStorage` del navegador.

## 3. Requisitos funcionales

### 3.1 Tab Ataque
- Selector de configuración de armas y un selector por arma (máximo 3), con nombre o apodo.
- Casillas Golpe básico, Bloqueo básico y Tasa de acierto, cada una con desglose.
- Campo Técnica: buscador, filtro "Solo con cálculo", grupos (Ofensivas, Defensivas, Ambientales,
  Utilidad, Otras) ordenados por tier, marca ◆ en las que tienen valores que calcular. Sin pasivas.
- Vista previa de la técnica elegida: tipo, tier, coste y enfriamiento, efectos y descripción con valores
  calculados, y botón "Añadir al Constructor del turno".
- La configuración de armas y la técnica se recuerdan en el navegador.

#### 3.1.1 Resumen y avisos del Ataque (segunda pasada)
- **Resumen de la ofensiva:** daño total (de la técnica o, si no hay daño calculable, el golpe básico), daño verdadero aparte,
  tasa de acierto final y coste de la técnica (energía, haki y enfriamiento frente a los recursos máximos, con aviso si los supera).
  Las pasivas marcadas se suman encima ("→ 153 con pasivas"). Incluye "Ver cálculo paso a paso".
- **Distancia al objetivo (m):** resta 5 de TA por cada 20 m completos. Se recuerda junto con la configuración.
- **Tarjeta por arma elegida:** tipo, tier, alcance, daño y bloqueo base, efecto y cuánto aporta a la configuración (75%, 60% o +50%).
- **Avisos:** configuración que cayó a "sin arma", armas repetidas sin copias, dos o más amuletos, requisitos de atributo del arma sin cumplir
  ("Destreza 8 de 10"), arma que no recibe el bono de dos manos y técnica de un estilo que exige otro tipo de arma.
- **Bono a dos manos:** no se aplica a amuletos, arrojadizas ni armas de fuego (decisión: se sigue el paréntesis explícito de la regla; el rifle de la Nota 1
  queda como excepción pendiente).
- **Tira "Llevas:"** bajo las pestañas, visible en todos los tabs: configuración y armas, técnica, tipo de arma de las pasivas y pasivas con sus bonos.

### 3.2 Valores calculados en el texto
- Solo se reemplaza la **fórmula**, con su signo; el resto de la frase queda igual
  (`+[Nivel] de Daño y 10 [Tasa de Acierto]` pasa a `+29 de Daño y 10 [Tasa de Acierto]`).
- No se calcula un número fijo (`+16 de [Tasa de Acierto]`) ni un atributo suelto.
- Las fórmulas sueltas en descripciones (`igual a la [VOL/3] del usuario`, `alcance [Nivel/2] metros`)
  también se calculan. Redondeo siempre hacia arriba.
- Interruptor **Sin cálculo** (fórmula tal cual) / **Con cálculo** (valores), recordado en el navegador.

### 3.3 Tab Pasivas
- Lista de pasivas aprendidas, sin "Senda", agrupada por origen (Disciplinas, Estilos y únicas, Raciales, Especiales),
  con efecto calculado, descripción plegable y etiquetas de condición.
- **Arma en mano:** fichas por **tipo de arma** que hay en el inventario (filo, contundente, asta, tensión,
  asalto, fuego, corporal, arrojadiza, defensiva, exótica, flexible, amuleto, musical, Wazamono), independientes
  de Ataque y del Constructor. Cada pasiva con condición de arma muestra si aplica o no. Botón "Marcar las que aplican" con Deshacer.
- **Barra fija** con lo marcado (cada pasiva con una X para quitarla), los **bonos sumados** por estadística y las acciones.
- **Bonos en otros tabs:** el daño y la TA de las pasivas marcadas se muestran junto a Golpe básico y TA en
  Ataque; los Reflejos, en Reacción, con un botón "Usar este valor". Las pasivas que no aplican con el arma no suman.
- **Filtros:** buscador, solo las que aplican con mi arma, solo marcadas, solo con valor calculado.
- **Vista:** Tarjetas o Compacta.
- **Conjuntos guardados** con nombre (aplicar, guardar, borrar) y botón "Turno nuevo".
- **Copiar al post:** texto agrupado por origen con los valores calculados, vista previa visible y aviso "Copiado ✓".
- La selección, los tipos de arma, la vista y los conjuntos se recuerdan en el navegador.

### 3.4 Datos auxiliares
- `op/armas_todas.php`: JSON público con todas las armas base del foro y un resumen de subcategorías.

## 4. Requisitos no funcionales
- Sin dependencias nuevas; htmx ya estaba vendorizado.
- Todo texto de la base se escapa antes de pintarlo. El HTML de cálculo se arma con marcadores para que una
  fórmula corta no se reemplace dentro de lo ya insertado.
- Si `localStorage` no está disponible, la página funciona igual sin recordar nada.
- La clasificación de tipos de arma y de condiciones es informativa: nunca bloquea una acción.

## 5. Decisiones sobre las preguntas abiertas
1. **Bono a dos manos:** el rifle no recibe el bono. Quedan excluidos amuletos, arrojadizas y armas de fuego.
2. **Wazamono:** son una excepción a la regla. Aplican varios tipos a la vez, así que cumplen **cualquier** requisito de tipo de arma
   (técnicas y pasivas). Función `opg_belico_tipos_con_comodin()`.
3. **Flexibles:** se deducen por nombre dentro de las exóticas (flexible, látigo, cadena). Confirmado.
4. **Número de armas:** tres es suficiente.
5. **Técnicas con el coste mal cargado (11):** se ignoran.
6. **Enfriamiento N:** la técnica queda bloqueada durante los N turnos siguientes al uso (ver 104 §6).
7. Las 4 armas de la subcategoría "ESPECIAL MT" no tienen tipo de arma.
8. **Condiciones comprobables** (vitalidad, distancia, movimiento previo, estado del objetivo): hoy solo se etiquetan; evaluarlas pediría datos
   del combate que la página no tiene (ver el análisis de la columna JSON).
9. Actualizar `op/punkrecords/guias/b01_guia_belica.md`, que aún dice -10 de TA con dos armas y "arma ligera".
