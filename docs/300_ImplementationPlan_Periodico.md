# Periódico: plan de implementación

> Depende de:
> [100_Requirements_Periodico.md](100_Requirements_Periodico.md) y
> [200_DesignPlan_Periodico.md](200_DesignPlan_Periodico.md)
>
> **Estado: por implementar.** Ninguna fase cambia el periódico público hasta
> que la fase 6 haya validado la importación del contenido estático actual.

## 1. Preparación

1. Crear una copia de
   `templates/One_Piece_Gaiden_Templates/op_periodico.html` fuera del template
   activo.
2. Inventariar las diez `.newspaper-page` actuales: orden, tema, titulares,
   textos, imágenes, pies, enlaces y carteles wanted.
3. Confirmar que `is_staff($uid)` e `is_narra($uid)` de
   `op/functions/op_functions.php` son las comprobaciones de acceso editorial.
4. Añadir las plantillas nuevas a la exportación/importación de templates del
   proyecto antes de apuntar rutas a ellas.

**Salida de fase:** una lista verificable del contenido que se debe migrar y
una copia restaurable del template público actual.

## 2. Base de datos

1. Crear `docs/periodico_migration.sql` con las cuatro tablas e índices del
   diseño: `mybb_op_periodicos`, `mybb_op_periodico_paginas`,
   `mybb_op_periodico_bloques` y `mybb_op_periodico_auditoria`.
2. Aplicar la migración en desarrollo y comprobar que el charset es `utf8mb4`.
3. Verificar índices y restricciones únicas de `numero`, `slug` y orden de
   páginas/bloques.
4. Probar una transacción de inserción y rollback para confirmar que el motor
   de las nuevas tablas soporta la atomicidad requerida.

**Verificación:** las cuatro tablas existen, los duplicados de número/slug se
rechazan y un rollback no deja páginas o bloques huérfanos.

## 3. Biblioteca de dominio

Crear `inc/plugins/lib/periodico.php` antes de escribir las pantallas. Debe
contener estas responsabilidades, en este orden:

1. `opg_periodico_puede_editar($uid)` usando `is_staff($uid)` o `is_narra($uid)`.
2. Lectura de edición, páginas, bloques y archivo público.
3. Normalización de datos de edición: título, subtítulo, número manual, slug,
   firma visible, fecha, estado, portada y tema permitido.
4. Validadores estrictos de cada bloque JSON: `portada`, `noticia`,
   `dos_columnas`, `recuadro`, `wanted`, `galeria` y `separador`.
5. Validador de URL de imagen: ruta local bajo `/images/op/uploads/` o URL
   absoluta HTTPS. Rechazar esquemas, hosts y atributos no permitidos.
6. Validador de enlace relacionado: `tid` positivo o URL relativa interna.
7. Persistencia transaccional de edición, páginas y bloques.
8. Auditoría con JSON de antes/después para cada cambio relevante.
9. Renderer compartido de edición, página y bloque.

Los helpers que renderizan escapan texto no BBCode. Para el cuerpo editorial,
usar el parser de MyBB que corresponda a un post normal y no interpolar HTML
de usuarios.

**Verificación:** pruebas manuales por función con datos válidos e inválidos;
un bloque inválido no se serializa ni llega a la base.

## 4. Listado staff

1. Crear `op/staff/periodico.php` e incluir `global.php`, las utilidades OPG y
   la biblioteca del periódico.
2. Antes de toda consulta, denegar acceso si `opg_periodico_puede_editar()` es
   falso.
3. Implementar `GET` para cargar listado paginado de borradores, publicadas y
   archivadas.
4. Implementar POST protegidos por `post_code`:
   - `crear`: genera borrador, primera página y portada vacía;
   - `duplicar`: clona páginas y bloques con IDs nuevos;
   - `publicar`: valida y archiva la edición pública anterior en transacción;
   - `archivar`: confirma y archiva una edición publicada.
5. Crear `staff_periodico_listado.html` con `.opg-card`, `.btn-op`,
   `.opg-chip` y estados visuales claros.

**Verificación:** usuario normal no puede abrir ni enviar acciones; staff crea,
duplica, publica y archiva sin dejar datos a medio guardar.

## 5. Editor staff

1. Crear `op/staff/periodico_editar.php?id=N`; cargar solo una edición válida
   que el staff pueda editar.
2. Crear `staff_periodico_editor.html` con:
   - metadatos de edición y firma visible;
   - pestañas de páginas;
   - constructor vertical de bloques;
   - botones de subir/bajar, duplicar y eliminar;
   - vista previa sin controles administrativos;
   - `Guardar borrador` y `Publicar edición`.
3. Generar una primera página y bloque `portada` al crear; impedir guardar una
   edición con cero o más de una portada, o una portada fuera de la primera
   posición.
4. Crear `jscripts/periodico_editor.js` para añadir/eliminar bloques en el DOM,
   mantener índices de formulario, confirmar acciones sensibles y actualizar
   la preview local. Usar nombres como `editorBloques`, no variables con `$`.
5. Crear `jscripts/periodico_editor.css`, respetando tokens OPG, bordes negros,
   sombras duras y diseño responsive de hasta 700px.
6. Implementar preview POST: validar sin escribir y renderizar con la misma
   función pública. Debe requerir `post_code`, no crear URL pública y añadir
   `noindex`.

**Verificación:** guardar un borrador, recargar y recuperar exactamente el
orden/JSON de páginas y bloques; revisar editor y preview en escritorio y
móvil.

## 6. Renderer público

1. Extraer el CSS editorial estable a `jscripts/periodico.css`; retirar estilos
   inline del template actual de forma gradual.
2. Reescribir `op/periodico.php` para cargar por defecto la última edición
   publicada y aceptar `edicion` o `slug` solo para ediciones publicadas o
   archivadas.
3. Sustituir `op_periodico.html` por un shell mínimo con `{$periodico_html}` y
   navegación de archivo.
4. Hacer que el HTML final venga de `opg_periodico_render_edicion()`.
5. Retirar `wantedData` de `jscripts/periodico.js`; el bloque `wanted` se
   renderiza desde su instantánea JSON. Conservar solo JS que aporte navegación
   o interacción real.
6. Implementar archivo paginado y filtro `anio=YYYY`, con tarjeta compacta:
   número, fecha, portada y titular.

**Verificación:** una edición de prueba publicada se ve correctamente en la
ruta pública, mientras un borrador no aparece aunque se conozca su número,
slug o ID.

## 7. Importación del periódico existente

1. Crear `op/staff/periodico_importar_legacy.php` protegido por `is_staff()` o `is_narra()` y
   `post_code`; no se ejecuta al visitar la URL.
2. Codificar el inventario de la fase 1 como payload de páginas y bloques, sin
   depender de extraer HTML en producción.
3. El importador comprueba que número y slug no existan antes de insertar.
4. Insertar la edición histórica con transacción y wanted congelados.
5. Registrar una entrada de auditoría `importacion_legacy`.
6. Comparar manualmente la edición importada con el template de respaldo:
   mismas diez páginas, orden, titulares, texto, imágenes, enlaces y wanted.
7. Solo después de la comparación, apuntar `op/periodico.php` al renderer nuevo
   y retirar el contenido estático del template activo.

**Verificación:** si la comparación falla, restaurar el template de respaldo y
mantener la edición importada como borrador para corregirla; no publicar una
migración incompleta.

## 8. Auditoría y edición directa de publicaciones

1. Antes de cambiar una edición, página o bloque, cargar la representación
   normalizada actual.
2. Tras guardar, registrar solo campos cambiados en `detalle_json`; para un
   bloque incluir tipo, orden y contenido anterior/posterior.
3. Registrar también creación, duplicación, publicación, archivado e
   importación.
4. Mostrar un enlace de historial de auditoría en el editor, inicialmente de
   solo lectura.

**Verificación:** editar directamente una edición publicada y comprobar que el
cambio se refleja en público y que la auditoría conserva responsable, fecha y
valores antes/después.

## 9. Pruebas de regresión y entrega

1. Probar permisos como invitado, usuario normal y staff.
2. Probar números/slugs duplicados, JSON inválido, portada ausente, imagen con
   URL inválida y enlace relacionado externo.
3. Probar edición con una, varias y ninguna página adicional; verificar orden
   tras duplicar, mover y eliminar bloques.
4. Probar publicación: la anterior pasa a archivo y la nueva es portada.
5. Probar archivo por número, slug y año, y comprobar que no expone borradores.
6. Probar BBCode, imágenes locales/externas, wanted y enlaces relacionados.
7. Revisar responsive en ≤700px y comprobar que no hay texto cortado ni
   controles superpuestos.
8. Incrementar `v=` de `periodico.css` y `periodico_editor.js` cuando se
   desplieguen cambios de assets.
9. Documentar el resultado de la migración y retirar o deshabilitar el
   importador de una sola ejecución.

## 10. Orden de despliegue

1. Migración SQL y biblioteca en producción, sin cambiar la ruta pública.
2. Panel staff y editor, usado para crear una edición de prueba privada.
3. Renderer público preparado, todavía sin reemplazar el template estático.
4. Importación y comparación del periódico actual.
5. Cambio de `op/periodico.php` al renderer de base de datos.
6. Monitoreo editorial y desactivación del importador.

Este orden conserva una vuelta atrás simple: hasta el paso 5, el periódico
estático continúa siendo la experiencia pública activa.
