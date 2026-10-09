#!/bin/sh
# Comprueba que el analizador de JS y el de PHP aceptan lo mismo y que ambos cumplen los casos esperados.
cd "$(dirname "$0")/../.." || exit 1
php tests/banda_sonora/php.php > /tmp/bs_php.json && node tests/banda_sonora/js.js > /tmp/bs_js.json || exit 1
python3 - <<'PY'
import json
casos = json.load(open('tests/banda_sonora/casos.json'))
php = json.load(open('/tmp/bs_php.json')); js = json.load(open('/tmp/bs_js.json'))
fallos = 0
for (entrada, esperado), p, j in zip(casos, php, js):
    canon = ('%s|https://www.youtube.com/watch?v=%s' % (esperado, esperado)) if esperado else ''
    for nombre, obtenido in (('PHP', p), ('JS', j)):
        if obtenido != canon:
            fallos += 1
            print('FALLA %s: %r esperaba %r y dio %r' % (nombre, entrada, canon, obtenido))
print('OK: %d casos en PHP y JS, 0 fallos' % len(casos) if fallos == 0 else 'CON FALLOS: %d' % fallos)
raise SystemExit(1 if fallos else 0)
PY
