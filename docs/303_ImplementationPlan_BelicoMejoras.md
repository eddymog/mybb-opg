# Mejoras de la Página Bélica (`op/belico.php`): plan de implementación

> Requisitos: [103_Requirements_BelicoMejoras.md](103_Requirements_BelicoMejoras.md).
> Diseño: [203_DesignPlan_BelicoMejoras.md](203_DesignPlan_BelicoMejoras.md).

## 1. Estado

Todas las tareas están hechas y probadas con scripts de PHP y con los JSON del repo. **No se probó en el navegador ni contra la
base de datos real.** Esa prueba es lo que queda antes de desplegar.

## 2. Tareas

1. **Librería de cálculo** (`belico_calculos.php`): selección de armas, TA desde el arma, tipo de daño normalizado, desglose con suma y redondeo.
2. **Ataque en `belico.php`:** configuración de armas, tres casillas con desglose, bloque de técnica.
3. **Campo Técnica:** buscador, filtro con cálculo, grupos por tier, marca ◆, añadir al Constructor, recordar en el navegador.
4. **Reconocimiento de fórmulas:** signo dentro de la fórmula, nombre de efecto sin conectores, `|`, "o", fórmulas sin corchetes, atributo suelto
   no calcula, fórmulas sueltas en descripciones. Sustitución solo de la fórmula.
5. **Interruptor Sin cálculo / Con cálculo** en Ataque y Pasivas, y estilos para que el valor vaya en línea.
6. **Tab Pasivas:** lista agrupada sin "Senda", efecto y descripción calculados, tipos de arma, filtros, selección recordada, copiar al post.
7. **Mejoras de Pasivas:** bonos sumados y reflejados en Ataque y Reacción; barra fija con chips; aviso de copiado y vista previa; filtros
   extra; vista compacta; conjuntos guardados; Turno nuevo; Marcar las que aplican con Deshacer; etiquetas de condición.
8. **Cambios menores:** se quitó Iniciativa de Utilidades (interfaz, ruta y funciones sin uso); regla de TA sin penalización con dos armas.
9. **`op/armas_todas.php`** (JSON de armas) y refactor de `tecnicas_show.php` para usar la librería.

## 3. Pruebas

### 3.1 Estáticas
```
php -l inc/plugins/lib/belico_calculos.php
php -l op/belico.php
php -l op/tecnicas_show.php
php -l op/armas_todas.php
```
El JS de `belico_js_ataque` y `belico_js_turno` se extrajo y se revisó con `node --check`.

### 3.1.1 Script de pruebas del repo
```
php tests/belico/calculos_y_turno.php
```
Comprueba fórmulas, bonos, armas, TA, avisos y todo el cálculo del turno (mantenidas, instantáneas, interrumpidos, reacciones, siguiente turno y entradas
inválidas). Sale con código 1 si algo falla. Ejecutarlo antes de cada despliegue.

### 3.2 Ejemplos de cálculo (Nivel 29, Voluntad 85, Fuerza 80 salvo indicación)

| Texto | Resultado esperado |
|---|---|
| `+[Nivel] de Daño y 10 [Tasa de Acierto]` | `+29 de Daño y 10 [Tasa de Acierto]` |
| `+[FUE/3] de [Daño] y +[RES/3] de Defensa Pasiva` (RES 40) | `+27 de [Daño] y +14 de Defensa Pasiva` |
| `+[VOLx0,15] de [Reflejos] \| Área: [VOLx7] metros.` | `+13 de [Reflejos] \| Área: 595 metros.` |
| `-Nivel/2 [Puntos de Energía]` | `-15 [Puntos de Energía]` |
| `igual a la [VOL/3] del usuario, alcance [Nivel/2] metros` | `igual a la 29 del usuario, alcance 15 metros` |
| `Inflige [Golpe Básico] + [2 x Nivel] Daño Cortante.` (golpe 40) | `Inflige 98 Daño Cortante.` |
| `-10 [Voluntad]`, `-10 [VOL]`, `+3 [FUE] en la comparativa`, `+16 de [Tasa de Acierto]` | sin cambios |

TA con armas (Destreza 11, Agilidad 15, Puntería 110): dos armas con TA 11 y 15 dan 11 (sin penalización); tres armas dan 11 - 15 = -4.

Bonos: `+[FUE/3] de [Daño] y +[RES/3] de Defensa Pasiva` da Daño +27 y Defensa Pasiva +14; `+25% de [Daño Verdadero]` da 25 con `p=1`.

### 3.3 Con los datos del repo
- `docs/objetos_armas_todas.md`: las 239 armas reciben tipo salvo las 4 de `ESPECIAL MT`.
- `docs/tecnicas_belicas_todas.md`: 196 pasivas sin "Senda"; 76 con condición de arma; Balas Negras detecta tensión, arrojadizas, asalto y fuego.

### 3.4 En el navegador (pendiente)
- Ataque: cambiar la configuración de armas no reinicia el selector; Golpe, Bloqueo y TA coinciden con `tecnicas_show.php`.
- Campo Técnica: el buscador conserva la técnica elegida; el filtro "Solo con cálculo" respeta la marca ◆.
- Interruptor: ambas vistas en Ataque y Pasivas; el valor aparece en la misma línea que el texto.
- Pasivas: marcar tipo de arma cambia los avisos y el filtro; la barra fija se mantiene al hacer scroll; los bonos suman y excluyen las que no aplican.
- Cambiar un atributo recalcula la lista sin perder lo marcado, y las líneas de Ataque y Reacción.
- Conjuntos: guardar, aplicar y borrar; recargar la página recupera selección, tipos, vista y conjuntos.
- Copiar: aparece "Copiado ✓" y el texto coincide con la vista previa. En móvil, sin desbordes.

### 3.5 Ataque: resumen, avisos y armas (segunda pasada)

Hecho y probado con scripts (sin navegador):
- Katana de filo a dos manos con Fuerza 20 y Destreza 8: golpe 60, bloqueo 15 (con el +50%), TA 13 - 10 (a 45 m) = 3, aviso "Destreza 8 de 10".
- Técnica "[Golpe Básico] + [2 x Nivel] Daño Cortante" con golpe 60 y Nivel 29: daño 118; coste "Energía 200 (máx. 120) ⚠ supera tu máximo".
- Pistola a dos manos: aviso de que no recibe el bono y se calcula como una mano. Tres armas con dos amuletos: avisos de repetida y de amuletos.
- `una_mano` sin arma elegida: aviso de que se calcula sin arma.

Pendiente en navegador: tira "Llevas:", suma de pasivas en el resumen, distancia que se recuerda, tarjetas en móvil y el desglose plegable.

## 4. Despliegue

Subir estos archivos. No hay migraciones ni plantillas que importar:
- `op/belico.php`
- `op/tecnicas_show.php`
- `inc/plugins/lib/belico_calculos.php`
- `op/armas_todas.php`

### Rollback
Restaurar los cuatro archivos desde git. Al no haber cambios de base de datos, no hay nada más que deshacer. `localStorage` solo queda con claves sin uso.

## 5. Orden recomendado de commits
1. Librería de cálculo y regla de TA.
2. Ataque y campo Técnica en `belico.php`, más el refactor de `tecnicas_show.php`.
3. Reconocimiento de fórmulas y sustituciones (efectos y descripciones) e interruptor Sin cálculo / Con cálculo.
4. Tab Pasivas (lista, tipos de arma, filtros, copiar).
5. Mejoras de Pasivas (bonos, barra fija, conjuntos, vista compacta, etiquetas).
6. `armas_todas.php` y estos documentos.

## 6. Fuera de alcance
- Columna JSON en `mybb_op_tecnicas` y evaluación real de condiciones (vitalidad, distancia, movimiento previo).
- Excepciones del bono a dos manos y límite de armas (ver las preguntas abiertas de 103).
- Actualizar la guía bélica en `op/punkrecords/guias/`.
