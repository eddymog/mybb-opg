# CLAUDE.md

Las instrucciones del proyecto viven en [AGENTS.md](AGENTS.md). Este archivo solo las importa, para que Claude Code y cualquier otra herramienta lean la misma fuente.

@AGENTS.md

## Notas específicas de Claude Code

- Los roles del equipo están en [roles/](roles/) y el proceso de trabajo en [process.md](process.md). Antes de empezar una tarea, identifica qué rol estás ejerciendo y lee su archivo.
- Si lanzas subagentes, dales su rol y los documentos de la funcionalidad en el prompt: cada uno arranca sin contexto.
- La memoria del usuario (preferencias de registro del español, regla de no modificar `global.php`, lista de archivos cambiados al final) ya está reflejada en la sección "Working Conventions" de AGENTS.md. Si hay un conflicto, gana lo que diga el usuario en la conversación.
