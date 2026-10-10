# Turno bélico (`op/belico.php`, tab "Turno"): plan de implementación

> Requisitos: [104_Requirements_TurnoBelico.md](104_Requirements_TurnoBelico.md).
> Diseño: [204_DesignPlan_TurnoBelico.md](204_DesignPlan_TurnoBelico.md).

## 1. Estado

**Fase 1 implementada** en `op/belico.php` y `inc/plugins/lib/belico_calculos.php`. Probada con scripts PHP; el JavaScript compila y
arranca con un DOM simulado, pero **no se ha probado en el navegador**. Fases 2 y 3 pendientes.

## 2. Fase 1: tareas

1. **Librería:** `opg_belico_es_instantanea()`, rol de técnica en PHP, coste de una técnica según clase y si es
   mantenida nueva o que continúa (`opg_belico_coste_tecnica($tecnica, $modo)`).
2. **Cálculo del turno:** `belico_calcular_turno()` (§3 del diseño), sin HTML, devuelve una estructura con bloques,
   acumulados, avisos y texto. Probar con scripts PHP como en 303.
3. **Ruta `accion=turno`** (POST, JSON acotado) que pinta el turno calculado.
4. **Interfaz:** cabecera (equipo, pasivas, recursos, mantenidas que continúan, instantáneas), bloques con botones
   rápidos y selector de técnica, barra fija, resumen y vista previa editable.
5. **Estado en el navegador:** objeto turno, guardado en `localStorage`, envío con retardo (300 ms) en cada cambio.
6. **Integración:** Ataque y Pasivas leen el equipo y las pasivas del turno; retirar el Constructor anterior.
7. **Estilos y móvil.**

### 2.1 Qué quedó hecho
- Librería: `opg_belico_es_instantanea`, `opg_belico_es_mantenida`, `opg_belico_rol_tecnica`, `opg_belico_coste_tecnica`, `opg_belico_acciones_turno`.
- `belico_normalizar_turno()` y `belico_calcular_turno()` (costes, recursos, daño, TA, avisos, texto), ruta `accion=turno` (POST, devuelve JSON).
- Tab Turno: cabecera (nombre, equipo, recursos, acciones extra), listas (pasivas del turno, mantenidas que continúan, instantáneas),
  bloques con pasos, movimiento, pasivas por bloque y detalle, resumen, avisos, texto editable y copiar. Estado en `localStorage`.
- "Añadir al turno" desde Ataque; se eliminó el Constructor anterior; Turno es la pestaña inicial.
- Fase 2 hecha: reacciones al turno anterior como bloques `anterior` (TA del rival, distancia, sorpresa, Reflejos extra, "corté su cadena" y +5 acumulativo).
- Corregido de paso un error previo de `belico_calc_reaccion()`: la distancia **subía** la TA del atacante en vez de bajarla (-5 por cada 20 m).
- Fase 3 hecha: bloques interrumpidos (no cuentan costes ni movimiento) y "Pasar al siguiente turno" (mantenidas y recursos finales al turno nuevo).
- Hecho además: Ataque sigue el equipo del turno, plantillas de turno guardadas y limpieza del CSS y JS del Constructor anterior.
- Hecho: enfriamientos entre turnos (bloqueada N turnos tras el uso) y Wazamono como comodín de tipo de arma.
- Pendiente: la prueba en el navegador real, commits y despliegue.

## 3. Pruebas de la fase 1

- Probado con un script PHP (Nivel 29, Fuerza 80, katana de filo): mantenida que continúa cobra su coste por turno (-8 energía); instantánea de haki
  a inicio de turno (-13 haki); instantánea elemental dentro de un bloque sin contar para sus límites; mantenida nueva sin primer coste cobra su coste
  por turno con aviso; tres golpes básicos en un bloque y cuatro acciones adicionales con avisos; técnica repetida con enfriamiento con aviso;
  recursos negativos con aviso en el bloque correcto; movimiento total por encima del máximo con aviso; bloque vacío con aviso; entradas basura sin errores.
- Casos de referencia de la fase 1:
  - mantenida que continúa paga coste por turno; mantenida nueva paga el primer coste; sin primer coste, aviso;
  - instantánea dentro de un bloque no cuenta para sus límites ni para acciones adicionales;
  - dos golpes básicos = 2 acciones adicionales; cuarta acción = aviso;
  - movimiento acumulado por encima del máximo = aviso;
  - recursos que bajan de cero = aviso en el bloque correcto;
  - técnica repetida con enfriamiento = aviso.
- Con los JSON del repo: todas las técnicas `BUSO`, `KENB`, `HAO` y los cinco recubrimientos se detectan como instantáneas.
- Navegador: armar el turno del ejemplo de la guía (personaje A, turno 1) y comparar el texto copiado.
- **Script del repo:** `php tests/belico/calculos_y_turno.php` cubre el cálculo del turno (ver 303 §3.1.1).
- **Huecos por tipo de bloque (jsdom):** 27 comprobaciones nuevas: un único hueco de ataque y de complemento en el bloque con técnica, dos acciones en el sin técnica, dos utilidades y un bloqueo en el de utilidad, selectores filtrados por rol, instantáneas fuera de los huecos, cambio de tipo, reacción, texto, siguiente turno y descarte del formato antiguo.
- **DOM simulado (jsdom), hecho:** se generó la página con las funciones de `belico.php` y se ejecutó el JavaScript de Turno con `fetch` apuntando al
  cálculo real en PHP. 37 comprobaciones sin errores de script: inputs de arma según el equipo, bloques y selectores por rol, resultado con daño,
  texto con `[tecnica=ID]`, reacciones primero, bloque interrumpido, mantenidas e instantáneas de inicio, pasivas del turno y por bloque, texto editable,
  Ataque que sigue al turno, plantillas, siguiente turno, "Turno nuevo" y guardado en `localStorage`. Esto no sustituye ver la página real (estilos, móvil, htmx).

## 4. Fases siguientes
- **Fase 2:** reacciones al turno anterior (TA del rival, +5 de Reflejos), bonos de utilidad sobre el ataque, pasivas por bloque.
- **Fase 3:** "Pasar al siguiente turno", cambio de arma dentro del turno, plantillas, bloques interrumpidos.

## 5. Despliegue
Solo `op/belico.php` e `inc/plugins/lib/belico_calculos.php`. Sin migraciones ni plantillas.
Rollback: restaurar ambos archivos desde git.
