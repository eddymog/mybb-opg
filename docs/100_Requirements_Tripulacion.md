# Requisitos: Sistema de Tripulaciones

> Documento de acumulación de requisitos. El objetivo es usar esto como base para luego diseñar un **design plan** y un **implementation plan**.

## Resumen

Sistema de Tripulaciones para el foro: cuatro páginas que cubren el ciclo completo — ver todas las tripulaciones, solicitar la creación de una nueva, gestionar la propia desde adentro, y moderarlas desde staff.

## 1. Directorio de Tripulaciones (página pública)

Página que muestra las tripulaciones del foro.

- Muestra todas las tripulaciones, sin importar su estado (Activa, Inactiva o Disuelta) — no solo las activas.
- Por cada tripulación en la lista: logo/bandera, tripulantes/personajes.
- Filtro por facción.
- Filtros adicionales: por estado de "buscamos miembros" y por cantidad de miembros — para facilitar que un personaje nuevo encuentre tripulación a la que unirse.
- Mensaje público editable por tripulación, visible desde la lista (ej. "banda cerrada", "buscamos miembros", etc.).

## 2. Solicitud de Creación de Tripulación

Página donde un usuario puede hacer una petición para crear una tripulación nueva, a través de un formulario.

- Antes este proceso se hacía de forma manual, creando un post en la zona de posts del foro. Esta página lo reemplaza y lo automatiza mediante un formulario.
- La solicitud debe ser aprobada por staff (usuarios donde `is_staff()` es verdadero) antes de que la tripulación quede creada. La aprobación se gestiona desde la Consola de Mods (ver sección 4).

**Campos del formulario:**

| Campo | Descripción |
|---|---|
| Nombre de la tripulación | Sujeto a moderación de contenido — límites razonables, sin nombres problemáticos. |
| Bandera/logo | Opcional. |
| Link | Tema (thread) donde se formó por primera vez la tripulación. |
| Usuarios iniciales | Los personajes que forman la tripulación desde el inicio. |
| Líder | El usuario que se asigna como Capitán inicial de la tripulación (rango funcional — ver [Roles y permisos](#roles-y-permisos) en la sección 3). |
| Jerarquía | Texto libre, puramente decorativo (ej. "segundo al mando", "navegante") — no otorga permisos. Los usuarios iniciales y/o el líder deciden cómo repartirlo. |
| Ideales | Opcional / no hace falta extenderse si no se considera necesario. |
| Otros | Campo abierto para cualquier información adicional relevante. |

## 3. Página de la Tripulación (gestión interna, para miembros)

Página propia de cada tripulación, donde los miembros pueden ver y gestionar lo relacionado a ella.

**Gestión de miembros:**
- El capitán y vicecapitán pueden añadir nuevos miembros y quitar miembros, de forma fácil/directa.
- Sección con el listado completo de todos los miembros.

**Roles y permisos:**
- Existen dos sistemas de rol independientes:
  - **Roles decorativos:** el líder puede asignar un rol a cada miembro, incluido a sí mismo (ej. "segundo al mando", "navegante"). Son puramente cosméticos/de rol interpretativo — no otorgan ningún permiso.
  - **Rangos funcionales:** determinan qué puede hacer cada usuario dentro del sistema.
    - **Capitán / Vicecapitán:** permisos especiales para modificar la tripulación — añadir/quitar miembros, modificar el logo, modificar el nombre, etc. No están limitados a una sola persona por rango — en teoría, podría existir una tripulación donde todos los miembros tengan el rango de Capitán, si así se decide.
    - **Miembro:** rango funcional base. Tiene acceso a toda la tripulación (ver cofre, listado de miembros, barcos, historial, etc.) pero no puede hacer cambios — no puede modificar el logo ni el nombre de la tripulación.

**Identidad de la tripulación:**
- Logo/imagen de la tripulación, con opción de subir/cambiar la imagen. El capitán y vicecapitán pueden modificarlo.
- Estado de la tripulación visible (Activa, Inactiva o Disuelta).

**Recursos:**
- Cofre de la tripulación: dinero, objetos e items. Visible para todos los miembros de la tripulación; privado para el resto de usuarios.
- Lista de barcos que posee la tripulación.

**Estadísticas:**
- Suma de la reputación de todos los miembros de la tripulación — calculada como la suma del stat de reputación de cada personaje en su ficha.

**Historial:**
- Historial de cambios de la tripulación. Es público.

## 4. Consola de Mods

Página dentro de la consola de moderación donde el staff puede manipular todo lo relacionado a las tripulaciones.

- Aprobar o rechazar solicitudes de creación de tripulación (ver sección 2).
- Cambiar el estado de una tripulación (Activa, Inactiva, Disuelta) — es un cambio manual hecho por staff. Por defecto, toda tripulación nueva se considera Activa.
