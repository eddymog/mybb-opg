#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [[ -z "$ROOT_DIR" ]]; then
    printf 'Error: este comando debe ejecutarse dentro del repositorio.\n' >&2
    exit 1
fi

cd "$ROOT_DIR"

CONFIG_FILE="$ROOT_DIR/.vscode/sftp.json"
if [[ ! -f "$CONFIG_FILE" ]]; then
    printf 'Error: no se encontro %s.\n' "$CONFIG_FILE" >&2
    exit 1
fi

for command_name in git jq sftp; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        printf 'Error: falta el comando requerido: %s.\n' "$command_name" >&2
        exit 1
    fi
done

DRY_RUN=0
ASSUME_YES=0
declare -a REQUESTED_FILES=()

usage() {
    cat <<'EOF'
Uso:
  scripts/deploy_changed.sh [--dry-run] [--yes] [archivo ...]

Opciones:
  --dry-run  Muestra los archivos publicables sin conectarse al servidor.
  --yes      Publica sin solicitar confirmacion interactiva.
  --help     Muestra esta ayuda.

Sin archivos concretos, usa los archivos nuevos o modificados detectados por Git.
Los patrones de exclusion se leen exclusivamente de .vscode/sftp.json.
Este comando nunca elimina archivos remotos.
EOF
}

while (($#)); do
    case "$1" in
        --dry-run)
            DRY_RUN=1
            ;;
        --yes)
            ASSUME_YES=1
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        --)
            shift
            while (($#)); do
                REQUESTED_FILES+=("$1")
                shift
            done
            break
            ;;
        -*)
            printf 'Error: opcion desconocida: %s\n' "$1" >&2
            usage >&2
            exit 1
            ;;
        *)
            REQUESTED_FILES+=("$1")
            ;;
    esac
    shift
done

HOST="$(jq -er '.host' "$CONFIG_FILE")"
PORT="$(jq -er '.port // 22' "$CONFIG_FILE")"
USERNAME="$(jq -er '.username' "$CONFIG_FILE")"
PRIVATE_KEY="$(jq -er '.privateKeyPath' "$CONFIG_FILE")"
REMOTE_ROOT="$(jq -er '.remotePath' "$CONFIG_FILE")"
IGNORE_PATTERNS=()
while IFS= read -r pattern; do
    IGNORE_PATTERNS+=("$pattern")
done < <(jq -r '.ignore[]?' "$CONFIG_FILE")

if [[ ! -f "$PRIVATE_KEY" ]]; then
    printf 'Error: no se encontro la clave privada configurada.\n' >&2
    exit 1
fi

REMOTE_ROOT="${REMOTE_ROOT%/}"

matches_ignore_pattern() {
    local path="$1"
    local pattern="$2"
    local trimmed_pattern
    local basename

    path="${path#./}"
    pattern="${pattern#./}"
    basename="${path##*/}"

    # Una ruta de directorio excluye tambien todo su contenido.
    if [[ "$path" == "$pattern" || "$path" == "$pattern/"* ]]; then
        return 0
    fi

    # Los patrones sin barra se aplican tambien al nombre de cada componente.
    if [[ "$pattern" != */* ]]; then
        if [[ "$basename" == $pattern || "/$path/" == *"/$pattern/"* ]]; then
            return 0
        fi
    fi

    # La extension SFTP admite patrones glob, incluido **/ al inicio.
    if [[ "$path" == $pattern ]]; then
        return 0
    fi
    if [[ "$pattern" == '**/'* ]]; then
        trimmed_pattern="${pattern#\*\*/}"
        if [[ "$path" == $trimmed_pattern || "$path" == */$trimmed_pattern ]]; then
            return 0
        fi
    fi

    return 1
}

is_ignored() {
    local path="$1"
    local pattern

    for pattern in "${IGNORE_PATTERNS[@]}"; do
        if matches_ignore_pattern "$path" "$pattern"; then
            return 0
        fi
    done
    return 1
}

declare -a CANDIDATES=()
if ((${#REQUESTED_FILES[@]})); then
    CANDIDATES=("${REQUESTED_FILES[@]}")
else
    while IFS= read -r -d '' path; do
        CANDIDATES+=("$path")
    done < <(git diff --name-only --diff-filter=ACMRTUXB -z HEAD --)

    while IFS= read -r -d '' path; do
        CANDIDATES+=("$path")
    done < <(git ls-files --others --exclude-standard -z)
fi

array_contains() {
    local sought="$1"
    shift
    local item

    for item in "$@"; do
        if [[ "$item" == "$sought" ]]; then
            return 0
        fi
    done
    return 1
}

declare -a FILES=()
declare -a SEEN_FILES=()
for candidate in "${CANDIDATES[@]}"; do
    candidate="${candidate#./}"

    if array_contains "$candidate" "${SEEN_FILES[@]-}"; then
        continue
    fi
    SEEN_FILES+=("$candidate")

    if [[ ! -f "$candidate" ]]; then
        printf 'Omitido (no es un archivo local): %s\n' "$candidate" >&2
        continue
    fi
    if is_ignored "$candidate"; then
        continue
    fi
    FILES+=("$candidate")
done

if ((${#FILES[@]} == 0)); then
    printf 'No hay archivos nuevos o modificados para publicar.\n'
    exit 0
fi

printf 'Archivos que se publicaran (%d):\n\n' "${#FILES[@]}"
printf '  %s\n' "${FILES[@]}"
printf '\nDestino: %s@%s:%s\n' "$USERNAME" "$HOST" "$REMOTE_ROOT"
printf 'Exclusiones: %s\n' "$CONFIG_FILE"
printf 'No se eliminara ningun archivo remoto.\n'

if ((DRY_RUN)); then
    exit 0
fi

if ((ASSUME_YES == 0)); then
    if [[ ! -t 0 ]]; then
        printf '\nError: se requiere una terminal interactiva o la opcion --yes.\n' >&2
        exit 1
    fi
    printf '\nContinuar con la publicacion? [s/N] '
    read -r confirmation
    case "$confirmation" in
        s|S|si|SI|Si|sI)
            ;;
        *)
            printf 'Publicacion cancelada.\n'
            exit 0
            ;;
    esac
fi

BATCH_FILE="$(mktemp "${TMPDIR:-/tmp}/opg-sftp.XXXXXX")"
trap 'rm -f "$BATCH_FILE"' EXIT

sftp_quote() {
    local value="$1"
    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"
    printf '"%s"' "$value"
}

declare -a CREATED_DIRS=()
for file in "${FILES[@]}"; do
    parent="${file%/*}"
    if [[ "$parent" != "$file" ]]; then
        current=""
        IFS='/' read -r -a parts <<< "$parent"
        for part in "${parts[@]}"; do
            current="${current:+$current/}$part"
            if ! array_contains "$current" "${CREATED_DIRS[@]-}"; then
                CREATED_DIRS+=("$current")
                printf -- '-mkdir %s\n' "$(sftp_quote "$REMOTE_ROOT/$current")" >> "$BATCH_FILE"
            fi
        done
    fi
    printf 'put %s %s\n' \
        "$(sftp_quote "$ROOT_DIR/$file")" \
        "$(sftp_quote "$REMOTE_ROOT/$file")" >> "$BATCH_FILE"
done

printf '\nConectando por SFTP...\n'
sftp \
    -b "$BATCH_FILE" \
    -i "$PRIVATE_KEY" \
    -P "$PORT" \
    -o BatchMode=yes \
    -o StrictHostKeyChecking=accept-new \
    "$USERNAME@$HOST"

printf '\nPublicacion completada: %d archivo(s).\n' "${#FILES[@]}"
