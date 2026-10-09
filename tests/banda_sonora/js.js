// node tests/banda_sonora/js.js: ejecuta analizarEnlaceYoutube() de jscripts/ficha/bandaSonora.js con los mismos casos.
const fs = require('fs');
const path = require('path');
global.window = global;
global.document = { addEventListener() {}, getElementById() { return null; }, getElementsByTagName() { return []; } };
eval(fs.readFileSync(path.join(__dirname, '../../jscripts/ficha/bandaSonora.js'), 'utf8'));
const casos = JSON.parse(fs.readFileSync(path.join(__dirname, 'casos.json'), 'utf8'));
console.log(JSON.stringify(casos.map(c => { const r = window.analizarEnlaceYoutube(c[0]); return r.ok ? r.id + '|' + r.canonica : ''; })));
