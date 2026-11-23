# CLAUDE.md

Las instrucciones del proyecto viven en [AGENTS.md](AGENTS.md), que es la fuente única y la que leen también otras herramientas (por ejemplo Codex). Este archivo solo la importa y añade notas específicas de Claude Code.

@AGENTS.md

## Notas específicas de Claude Code

- Los roles del equipo están en [docs/agents/roles/](docs/agents/roles/) y el proceso de trabajo en [docs/agents/process.md](docs/agents/process.md). Antes de empezar una tarea, identifica qué rol estás ejerciendo y lee su archivo.
- Para tareas de arquitectura o de datos, consulta también [docs/agents/context.md](docs/agents/context.md) y [docs/agents/DATABASE.md](docs/agents/DATABASE.md). `DATABASE.md` es muy largo: búscalo por tabla en lugar de leerlo entero.
- La puerta de verde es `sh tests/run_all.sh`. Nada se da por listo con ella en rojo.
- Si lanzas subagentes, dales su rol, el número de la issue con sus criterios y los documentos de la funcionalidad en el prompt: cada uno arranca sin contexto.
- La memoria del usuario (preferencias de registro del español, regla de no modificar `global.php`, lista de archivos cambiados al final) ya está reflejada en la sección "Working Conventions" de AGENTS.md. Si hay un conflicto, gana lo que diga el usuario en la conversación.
