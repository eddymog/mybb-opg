# One Piece Gaiden

Foro de rol personalizado ambientado en el universo de One Piece, construido sobre MyBB 1.8 con un sistema de juego completamente personalizado.

## Stack

- **Backend:** PHP 7.0+ / MySQL (MySQLi)
- **Motor de foro:** MyBB 1.8.36
- **Frontend:** jQuery, AJAX, SCEditor (editor de texto enriquecido)
- **APIs externas:** VPN detection (VPNApi.io, ProxyCheck.io, IPHub.info), Discord Webhooks
- **Chat en tiempo real:** Plugin rt_chat (Redis/Memcache)

## Estructura del proyecto

```
/                        → Scripts principales de MyBB (index.php, showthread.php, etc.)
/op/                     → Sistema de juego principal (~90 módulos PHP)
/op/staff/               → Herramientas de administración para staff (~57 archivos)
/op/functions/            → Funciones compartidas del juego
/opg/                    → Sistemas de gacha (cofres, akumas, haki)
/api/                    → API REST con autenticación JWT (6 endpoints)
/inc/                    → Core de MyBB (clases, plugins, handlers)
/inc/plugins/            → Plugins de MyBB + 14 procesadores BBCode personalizados
/jscripts/               → JavaScript del cliente (mapas, cartas, calculadoras)
/images/                 → Assets estáticos
/uploads/                → Archivos subidos por usuarios
/admin/                  → Panel de administración de MyBB
```

## Sistemas de juego

- **Fichas de personaje** — Creación, atributos, facciones, razas y tiers de poder (`op/ficha.php`, `op/ficha_crear.php`)
- **Combate** — API JSON de combate con cálculo de daño y tiradas (`op/api_combate.php`)
- **Técnicas** — Sistema de aprendizaje y entrenamiento con cola temporal (`op/entrenamiento_tecnicas.php`)
- **Economía** — Berries, nikas, kuros. Tiendas, mercado negro, intercambios (`op/mercado_negro.php`, `op/tienda.php`, `op/intercambio.php`)
- **Crafteo** — Creación y mejora de objetos con cola temporal (`op/crafteo.php`, `op/mejoras.php`)
- **Exploración** — Viajes entre islas con eventos navales aleatorios (`op/viajes.php`, `op/isla.php`)
- **Gacha** — Tiradas de frutas del diablo, haki, cofres y rey (`opg/`)
- **Frutas del diablo** — Sistema de akumas con asignación y tiering (`op/akumas.php`)
- **Virtudes y defectos** — Modificadores de personaje (`op/` + `mybb_op_virtudes`)
- **NPCs y compañeros** — Sistema de tripulación y mascotas (`op/companeros.php`, `op/npcs.php`)
- **BBCodes personalizados** — 14 tags de juego: `[ficha]`, `[dado]`, `[consumir]`, `[npc=ID]`, `[tecnica=TID]`, `[objeto=ID]`, etc.

## Configuración local

### Requisitos

- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.4+
- Apache con `mod_rewrite` habilitado
- Redis o Memcache (para el chat en tiempo real)

### Instalación

1. Clona el repositorio
2. Crea `inc/config.php` a partir de la plantilla con tus credenciales de base de datos:
   ```php
   $config['database']['type'] = 'mysqli';
   $config['database']['database'] = 'tu_base_de_datos';
   $config['database']['table_prefix'] = 'mybb_';
   $config['database']['hostname'] = 'localhost';
   $config['database']['username'] = 'tu_usuario';
   $config['database']['password'] = 'tu_password';
   $config['database']['encoding'] = 'utf8';
   $config['admin_dir'] = 'admin';
   ```
3. Importa el dump de la base de datos (`rovddqmy_op.sql`) en tu servidor MySQL
4. Asegúrate de que `cache/`, `uploads/` y `logs/` tienen permisos de escritura
5. Apunta el virtual host de Apache a la raíz del proyecto

## Variables de entorno / Claves API

Las siguientes claves están configuradas directamente en los archivos indicados:

| Clave | Archivo(s) |
|-------|------------|
| VPNApi.io Key | `index.php`, `newthread.php`, `op/ficha.php` |
| ProxyCheck.io Key | `index.php`, `newthread.php`, `op/ficha.php` |
| IPHub API Key | `index.php`, `newthread.php`, `op/ficha.php` |
| Discord Webhook URLs | Configurados en panel admin → plugin rt_discord_webhooks |
| JWT Secret | `api/index.php` (actualmente hardcoded como `'test'`) |

> **Nota de seguridad:** `inc/config.php` contiene credenciales y está excluido del repositorio vía `.gitignore`. Nunca lo commitees.

## Base de datos

- **Motor:** MySQL 5.7 / MariaDB 10.4+
- **Dump:** `rovddqmy_op.sql` (esquema completo, ~120 tablas)
- **Tablas custom:** 69 tablas prefijadas `mybb_op_*` para el sistema de juego
- **Triggers:** 2 triggers de auditoría (`u_fichas_triggers` en fichas, `u_users_triggers` en users)
- **Referencia completa:** Ver `DATABASE.md` para esquema detallado con columnas, tipos, índices y relaciones

## API REST

La API vive en `/api/` con autenticación JWT:

| Ruta | Método | Descripción |
|------|--------|-------------|
| `?route=auth/login` | POST | Login, devuelve token JWT |
| `?route=me` | GET | Datos del usuario autenticado |
| `?route=threads` | GET | Listado de temas con paginación |
| `?route=thread` | GET | Tema individual con posts |
| `?route=new_reply` | POST | Crear respuesta en un tema |
| `?route=ficha` | GET | Datos de ficha de personaje |

## Herramientas de staff

57 herramientas de administración en `/op/staff/` para:
- Aprobar fichas en cola (`fichas_en_cola.php`)
- Gestionar atributos de personaje (`ficha_atributos.php`)
- Crear/modificar objetos (`objetos_crear.php`, `objetos_modificar.php`)
- Crear/modificar técnicas (`tecnicas_crear.php`, `tecnicas_modificar.php`)
- Gestionar frutas del diablo (`akumas_crear.php`, `akumas_modificar.php`)
- Distribuir recompensas (`entregar_recompensas.php`, `recompensasAventuras.php`)
- Consolas de moderación y narración (`consola_mod.php`, `consola_narras.php`)
- Gestión de islas y NPCs (`islas_modificar.php`, `npcs_modificar.php`)
- Distribución de salarios (`salario_faccion.php`, `salario_staff.php`)
