#!/bin/sh
# Puerta unica de "verde" del proyecto. Una sola orden que un agente (o una persona) ejecuta para saber si el
# trabajo esta listo para revision. Termina con codigo 0 (VERDE) o distinto de 0 (ROJO).
#
# Que comprueba, en este orden:
#   1. Que no se haya tocado ningun archivo prohibido (global.php, inc/config.php, nucleo de MyBB, admin/, cache/, uploads/).
#   2. Que cada archivo PHP cambiado pase `php -l`.
#   3. Que lo anadido no contenga credenciales (claves de API, contrasenas literales, claves privadas).
#   4. Aviso (no bloquea): rayas largas en lo anadido, que el proyecto no usa en textos escritos.
#   5. Las pruebas de tests/<area>/ que no necesitan base de datos (las de base de datos solo con --db).
#
# Uso:
#   sh tests/run_all.sh                 comprobaciones y pruebas sin base de datos
#   sh tests/run_all.sh --rapido        solo las comprobaciones 1 a 4 (sin ejecutar pruebas)
#   sh tests/run_all.sh --db            tambien las pruebas con MySQL de pruebas (ver tests/aventuras/bootstrap_db.php)
#   sh tests/run_all.sh --area NOMBRE   en la parte de pruebas, solo las de tests/NOMBRE/ (las comprobaciones 1 a 4 se hacen igual)
#   sh tests/run_all.sh --base REF      compara contra REF en lugar de HEAD
#   sh tests/run_all.sh --archivos "a.php b.php"   usa esta lista como archivos cambiados (para CI o para probar el arnes)
#
# Excepciones explicitas (solo cuando el usuario lo haya pedido):
#   OPG_PERMITIR_PROHIBIDOS="admin/index.php"   permite tocar esos archivos concretos
#   una linea con el texto  run_all:permitir    queda fuera de la busqueda de credenciales
#
# Pruebas que ya fallaban antes de este arnes se listan en tests/known_failures.txt: se muestran como FALLO CONOCIDO
# y no bloquean, pero siguen a la vista hasta que se arreglen.

cd "$(dirname "$0")/.." || exit 1

BASE=HEAD
RAPIDO=0
RUN_DB=0
AREA=""
ARCHIVOS_FIJOS=""

while [ $# -gt 0 ]; do
    case "$1" in
        --rapido) RAPIDO=1 ;;
        --db) RUN_DB=1 ;;
        --area) shift; AREA="$1" ;;
        --base) shift; BASE="$1" ;;
        --archivos) shift; ARCHIVOS_FIJOS="$1" ;;
        -h|--help|--ayuda) sed -n '2,26p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Opcion desconocida: $1 (usa --ayuda)"; exit 2 ;;
    esac
    shift
done

FALLOS=0
AVISOS=0

fallo() { FALLOS=$((FALLOS + 1)); echo "  FALLO: $1"; }
aviso() { AVISOS=$((AVISOS + 1)); echo "  AVISO: $1"; }
titulo() { echo; echo "== $1"; }

# ---------------------------------------------------------------------------------------------------------------
# Archivos cambiados (sin borrados) y nombres cambiados (incluidos borrados)
# ---------------------------------------------------------------------------------------------------------------
if [ -n "$ARCHIVOS_FIJOS" ]; then
    CAMBIADOS=$(echo "$ARCHIVOS_FIJOS" | tr ' ' '\n' | sed '/^$/d')
    TODOS_NOMBRES="$CAMBIADOS"
    NUEVOS=""
else
    CAMBIADOS=$( { git diff --name-only --diff-filter=ACMR "$BASE" 2>/dev/null; git ls-files --others --exclude-standard 2>/dev/null; } | sort -u )
    TODOS_NOMBRES=$( { git diff --name-only "$BASE" 2>/dev/null; git ls-files --others --exclude-standard 2>/dev/null; } | sort -u )
    NUEVOS=$(git ls-files --others --exclude-standard 2>/dev/null)
fi

N_CAMBIADOS=$(echo "$CAMBIADOS" | sed '/^$/d' | wc -l | tr -d ' ')
echo "Base de comparacion: $BASE  |  archivos cambiados: $N_CAMBIADOS"

# ---------------------------------------------------------------------------------------------------------------
# 1. Archivos prohibidos
# ---------------------------------------------------------------------------------------------------------------
titulo "1. Archivos prohibidos"
PROHIBIDOS_ENCONTRADOS=""
for f in $TODOS_NOMBRES; do
    case "$f" in
        global.php|inc/config.php|inc/class_core.php|inc/class_session.php|inc/class_plugins.php|inc/class_templates.php) p=1 ;;
        inc/db_*.php|inc/datahandlers/*|cache/*|uploads/*|admin/*) p=1 ;;
        *) p=0 ;;
    esac
    if [ "$p" = "1" ]; then
        permitido=0
        for ok in $OPG_PERMITIR_PROHIBIDOS; do [ "$ok" = "$f" ] && permitido=1; done
        if [ "$permitido" = "0" ]; then PROHIBIDOS_ENCONTRADOS="$PROHIBIDOS_ENCONTRADOS $f"; fi
    fi
done
if [ -n "$PROHIBIDOS_ENCONTRADOS" ]; then
    for f in $PROHIBIDOS_ENCONTRADOS; do fallo "se ha tocado un archivo prohibido: $f"; done
else
    echo "  ok: no se tocaron archivos prohibidos"
fi

# ---------------------------------------------------------------------------------------------------------------
# 2. Sintaxis PHP
# ---------------------------------------------------------------------------------------------------------------
titulo "2. Sintaxis PHP (php -l)"
N_PHP=0
for f in $CAMBIADOS; do
    case "$f" in
        *.php)
            [ -f "$f" ] || continue
            N_PHP=$((N_PHP + 1))
            salida=$(php -l "$f" 2>&1)
            if [ $? -ne 0 ]; then fallo "$f: $(echo "$salida" | head -1)"; fi
            ;;
    esac
done
echo "  archivos PHP revisados: $N_PHP"

# ---------------------------------------------------------------------------------------------------------------
# 3 y 4. Lo anadido: credenciales (fallo) y rayas largas (aviso)
# ---------------------------------------------------------------------------------------------------------------
titulo "3. Credenciales en lo anadido"
anadido() {
    # Imprime las lineas anadidas de un archivo (todo el archivo si es nuevo o si se pasa una lista fija).
    f="$1"
    if [ -n "$ARCHIVOS_FIJOS" ] || echo "$NUEVOS" | grep -qxF "$f"; then
        cat "$f" 2>/dev/null
    else
        git diff -U0 "$BASE" -- "$f" 2>/dev/null | grep '^+' | grep -v '^+++' | sed 's/^+//'
    fi
}
PATRON_SECRETOS='AIza[0-9A-Za-z_-]{30,}|sk-[A-Za-z0-9_-]{30,}|BEGIN (RSA |OPENSSH |EC |DSA )?PRIVATE KEY|eyJ[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}\.|(password|passwd|secret|api_?key|token)[\"'"'"']?[[:space:]]*(=|=>|:)[[:space:]]*[\"'"'"'][^\"'"'"']{8,}[\"'"'"']'
ENCONTRADO=0
for f in $CAMBIADOS; do
    [ -f "$f" ] || continue
    case "$f" in *.png|*.jpg|*.jpeg|*.gif|*.webp|*.mp4|*.gz|*.zip|*.pdf|*.woff|*.woff2|*.ttf) continue ;; esac
    tam=$(wc -c < "$f" | tr -d ' ')
    [ "$tam" -gt 2000000 ] && continue
    hallazgo=$(anadido "$f" | grep -Ei "$PATRON_SECRETOS" | grep -v 'run_all:permitir' | head -3)
    if [ -n "$hallazgo" ]; then
        ENCONTRADO=1
        fallo "posible credencial en $f (si es falsa, anade 'run_all:permitir' en esa linea)"
    fi
done
[ "$ENCONTRADO" = "0" ] && echo "  ok: no se encontraron credenciales en lo anadido"

titulo "4. Rayas largas en lo anadido (aviso, no bloquea)"
RAYAS=0
RAYA_LARGA=$(printf '\342\200\224')
for f in $CAMBIADOS; do
    [ -f "$f" ] || continue
    case "$f" in *.php|*.md|*.html|*.js|*.css|*.sql|*.sh) ;; *) continue ;; esac
    n=$(anadido "$f" | grep -c "$RAYA_LARGA")
    if [ "$n" -gt 0 ]; then RAYAS=1; aviso "$f: $n linea(s) anadida(s) con raya larga"; fi
done
[ "$RAYAS" = "0" ] && echo "  ok: sin rayas largas en lo anadido"

# ---------------------------------------------------------------------------------------------------------------
# 5. Pruebas
# ---------------------------------------------------------------------------------------------------------------
if [ "$RAPIDO" = "1" ]; then
    echo; echo "(--rapido: se omiten las pruebas)"
else
    titulo "5. Pruebas"
    CONOCIDOS=""
    [ -f tests/known_failures.txt ] && CONOCIDOS=$(sed 's/#.*//; s/[[:space:]]*$//' tests/known_failures.txt | sed '/^$/d')

    es_ayudante() {
        case "$(basename "$1")" in
            bootstrap_*.php|harness_*.php|worker.php|http.php|probar_esquema.php) return 0 ;;
        esac
        # Partes de otra prueba (se cargan desde ella y por separado solo dicen "Ejecuta tests/...")
        grep -q "empty(\$GLOBALS\['continuar'\])" "$1" && return 0
        return 1
    }
    necesita_db() {
        grep -qE 'bootstrap_db|mysqli_connect|DbShim|Sin MySQL de pruebas' "$1"
    }
    es_conocido() {
        for k in $CONOCIDOS; do [ "$k" = "$1" ] && return 0; done
        return 1
    }
    ejecutar() {
        etiqueta="$1"; shift
        salida=$("$@" 2>&1); rc=$?
        resumen=$(echo "$salida" | tail -1 | cut -c1-100)
        if [ $rc -eq 0 ]; then
            if es_conocido "$etiqueta"; then aviso "$etiqueta pasa ahora: quitalo de tests/known_failures.txt"; fi
            echo "  ok    $etiqueta :: $resumen"
        else
            if es_conocido "$etiqueta"; then
                aviso "FALLO CONOCIDO (no bloquea) $etiqueta :: $resumen"
            else
                fallo "$etiqueta (codigo $rc) :: $resumen"
                echo "$salida" | head -8 | sed 's/^/        /'
            fi
        fi
    }

    OMITIDAS_DB=0
    for dir in tests/*/; do
        dir=${dir%/}
        if [ -n "$AREA" ] && [ "$dir" != "tests/$AREA" ]; then continue; fi
        if [ -f "$dir/run.sh" ]; then
            ejecutar "$dir/run.sh" sh "$dir/run.sh"
            continue
        fi
        for t in "$dir"/*.php; do
            [ -f "$t" ] || continue
            es_ayudante "$t" && continue
            if necesita_db "$t"; then
                if [ "$RUN_DB" = "1" ]; then ejecutar "$t" php "$t"; else OMITIDAS_DB=$((OMITIDAS_DB + 1)); fi
                continue
            fi
            ejecutar "$t" php "$t"
        done
    done
    [ "$OMITIDAS_DB" -gt 0 ] && echo "  (omitidas $OMITIDAS_DB prueba(s) con base de datos; usa --db con el MySQL de pruebas levantado)"
fi

# ---------------------------------------------------------------------------------------------------------------
# Resultado
# ---------------------------------------------------------------------------------------------------------------
echo
if [ "$FALLOS" -eq 0 ]; then
    echo "VERDE (avisos: $AVISOS)"
    exit 0
fi
echo "ROJO ($FALLOS fallo(s), avisos: $AVISOS)"
exit 1
