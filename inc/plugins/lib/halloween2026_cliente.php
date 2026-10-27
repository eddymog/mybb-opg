<?php
/**
 * Halloween 2026: script del navegador para los secretos, ya compacto y sin comentarios dentro del JavaScript (se escribe
 * dentro de la pagina, no es un archivo aparte). Esta hecho para no decir nada: no lleva palabras del evento, ni textos
 * (los pone el servidor), ni selectores (llegan cifrados cuando alguien interactua con la pagina).
 *
 * Leyenda del resultado de un reclamo (lista, ver opg_hlw_secret_client_result):
 *  0 estado, 1 numero, 2 nombre, 3 mensaje, 4 premio, 5 imagen, 6 rotulo superior, 7 titulo, 8 boton, 9 motivo.
 * Leyenda de una entrada de la configuracion: 0 codigo, 1 selector, 2 enlace, 3 delegado, 4 texto que debe contener,
 * 5 condicion sobre otro elemento ([selector, texto]) o vacio.
 */

if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

function opg_hlw_client_script($ticket, $postKey)
{
    $code = <<<'JS'
(function(){var T='{{T}}',K='{{K}}',U='/op/ajuste_indice.php',Q=0,Y=null,Z={},V=0,W=null,R=[];
function d(s){return atob(s.replace(/-/g,'+').replace(/_/g,'/'))}
function j(s){return JSON.parse(decodeURIComponent(escape(s)))}
function r(b){return fetch(U,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'my_post_key='+encodeURIComponent(K)+'&'+b})}
function ck(n){var a=document.cookie?document.cookie.split('; '):[],i,p;for(i=0;i<a.length;i++){p=a[i].split('=');if(p[0]===n)return p.slice(1).join('=')}return ''}
function mem(n){if(!n)return;var l=ck('ei_g'),a=l?l.split('.'):[];if(a.indexOf(String(n))===-1){a.push(String(n));document.cookie='ei_g='+a.join('.')+'; max-age=31536000; path=/; samesite=lax'}}
function st(){if(document.getElementById('ei-s'))return;var c=document.createElement('style');c.id='ei-s';c.textContent='.ei-m{position:fixed;inset:0;z-index:100002;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(10,8,14,.82);animation:ei-f .25s ease-out}.ei-t{box-sizing:border-box;width:min(420px,100%);padding:28px 24px 22px;border:3px solid #000;border-radius:10px;background:#24202b;color:#f7f2ed;text-align:center;box-shadow:6px 6px 0 #000;font-family:InterRegular,Arial,sans-serif;animation:ei-p .45s cubic-bezier(.2,1.4,.4,1)}.ei-m.ei-1 .ei-t{border-color:#efc15c}.ei-k{margin:0 0 6px;color:#f07824;font-weight:800;letter-spacing:.06em;text-transform:uppercase;font-size:12px}.ei-h{margin:0 0 4px;font:30px moonGetHeavy,Arial,sans-serif;line-height:1.1;color:#fff}.ei-i{width:128px;height:128px;margin:14px auto;border:3px solid #000;border-radius:50%;background-color:#19151f;background-repeat:no-repeat;background-position:center;background-size:70%;box-shadow:0 0 0 4px #efc15c,0 0 28px 6px rgba(239,193,92,.55);animation:ei-u 1.1s ease-out both}.ei-n{margin:0;font-size:18px;font-weight:800;color:#efc15c}.ei-x{margin:10px 0 0;line-height:1.5;color:#e8dfe4}.ei-g{display:inline-block;margin:14px 0 0;padding:8px 14px;border:2px solid #000;border-radius:6px;background:#3aa980;color:#0d231d;font-weight:800}.ei-m.ei-1 .ei-g{background:#efc15c;color:#19151f}.ei-c{display:block;margin:20px auto 0;padding:10px 26px;border:2px solid #000;border-radius:6px;background:#f07824;color:#fff;font-weight:800;font-size:15px;cursor:pointer}.ei-c:hover{background:#c95b12}.ei-a{position:fixed;right:18px;bottom:18px;z-index:100001;max-width:340px;padding:12px 16px;border:2px solid #000;border-radius:7px;background:#24202b;color:#f7f2ed;font-weight:700;box-shadow:4px 4px 0 #000;transition:opacity .4s}.ei-a.ei-o{opacity:0}@keyframes ei-f{from{opacity:0}to{opacity:1}}@keyframes ei-p{from{transform:scale(.7);opacity:0}to{transform:scale(1);opacity:1}}@keyframes ei-u{0%{filter:grayscale(1) brightness(.6);transform:scale(.8)}60%{transform:scale(1.08)}100%{filter:none;transform:scale(1)}}@media (prefers-reduced-motion:reduce){.ei-m,.ei-t,.ei-i{animation:none}}';document.head.appendChild(c)}
function ty(t){if(!t)return;st();var b=document.createElement('div');b.className='ei-a';b.setAttribute('role','status');b.textContent=t;document.body.appendChild(b);setTimeout(function(){b.classList.add('ei-o');setTimeout(function(){if(b.parentNode)b.parentNode.removeChild(b)},400)},5000)}
function md(v,e,fn){st();mem(v[1]);var o=document.getElementById('ei-d');if(o&&o.parentNode)o.parentNode.removeChild(o);var im='';try{if(v[5])im='url("'+v[5]+'")';else if(e&&e.tagName==='IMG'&&(e.currentSrc||e.src))im='url("'+(e.currentSrc||e.src)+'")';else if(e)im=getComputedStyle(e).backgroundImage}catch(x){im=''}
var m=document.createElement('div');m.id='ei-d';m.className='ei-m'+(v[0]==='first'?' ei-1':'');m.setAttribute('role','dialog');m.setAttribute('aria-modal','true');m.setAttribute('aria-labelledby','ei-q');var t=document.createElement('div');t.className='ei-t';
function ad(tag,cl,tx,id){var n=document.createElement(tag);n.className=cl;n.textContent=tx;if(id)n.id=id;t.appendChild(n);return n}
ad('p','ei-k',v[6]);ad('h2','ei-h',v[7],'ei-q');var i=ad('div','ei-i','');if(im&&im!=='none')i.style.backgroundImage=im;ad('p','ei-n',v[2]);ad('p','ei-x',v[3]);ad('div','ei-g',v[4]);var c=ad('button','ei-c',v[8]);c.type='button';m.appendChild(t);document.body.appendChild(m);
function cl(){document.removeEventListener('keydown',kd,true);if(m.parentNode)m.parentNode.removeChild(m);if(fn)fn()}
function kd(x){if(x.key==='Escape')cl()}
c.addEventListener('click',cl);m.addEventListener('click',function(x){if(x.target===m)cl()});document.addEventListener('keydown',kd,true);c.focus()}
function rs(){var v=ck('ei_r');if(!v)return 0;document.cookie='ei_r=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';try{var l=j(d(v)).filter(function(x){return x&&(x[0]==='first'||x[0]==='new')});(function nx(){var x=l.shift();if(x)md(x,null,nx)})()}catch(x){return 0}return 1}
function pl(){var n=0;clearInterval(Y);Y=setInterval(function(){n++;if(rs()||n>=8)clearInterval(Y)},800)}
function cm(en,e,h){if(Z[en[0]])return;Z[en[0]]=1;var go=function(){if(h)location.href=h};r('t='+encodeURIComponent(en[0])).then(function(x){return x.json()}).then(function(x){if(x[0]==='first'||x[0]==='new')md(x,e,go);else{if(x[3])ty(x[3]);go()}if(x[9]&&window.console)console.info('['+x[0]+'] '+x[9])}).catch(function(){go()}).then(function(){Z[en[0]]=0})}
function mt(en,t){var h=null;try{h=t&&t.closest?t.closest(en[1]):null}catch(y){h=null}if(h&&en[4]&&(h.textContent||'').replace(/\s+/g,' ').indexOf(en[4])===-1)h=null;if(h&&en[5]){var o=null;try{o=document.querySelector(en[5][0])}catch(y){o=null}if(!o||(o.textContent||'').replace(/\s+/g,' ').indexOf(en[5][1])===-1)h=null}return h}
function bd(en){var e=null,f=/^#v[0-9a-f]{6}$/.test(en[1]);try{e=document.querySelector(en[1])}catch(x){return}
if(en[3]&&!f){document.addEventListener('click',function(x){var h=mt(en,x.target);if(h)cm(en,h,'')},true);R.push(en);return}
if(!e&&f){e=document.createElement('span');e.id=en[1].slice(1);e.setAttribute('aria-hidden','true');e.style.cssText='position:absolute;left:-9999px;top:0;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none';document.body.appendChild(e)}
if(!e)return;e.addEventListener('click',function(x){var l=en[2]?(e.href?e:(e.closest?e.closest('a'):null)):null,h=l&&l.href?l.href:'';if(h&&(x.ctrlKey||x.metaKey||x.shiftKey||x.button!==0))return;x.preventDefault();x.stopPropagation();cm(en,e,h)},true)}
function ld(){if(Q)return;Q=1;r('c='+encodeURIComponent(T)).then(function(x){return x.text()}).then(function(x){var s=d(x),o='',i;for(i=0;i<s.length;i++)o+=String.fromCharCode(s.charCodeAt(i)^T.charCodeAt(i%16));j(o).forEach(bd);V=1;if(W&&Date.now()-W.t<3000)R.forEach(function(en){var h=mt(en,W.e);if(h)cm(en,h,'')})}).catch(function(){Q=0})}
var ev=['pointermove','touchstart','keydown','scroll','mousedown'];function go(){ev.forEach(function(n){removeEventListener(n,go,true)});ld()}
ev.forEach(function(n){addEventListener(n,go,true)});setTimeout(go,4000);rs();
document.addEventListener('keydown',function(x){if(x.key==='Enter')pl()},true);document.addEventListener('click',pl,true);document.addEventListener('click',function(x){if(!V)W={e:x.target,t:Date.now()}},true)})();
JS;
    return '<script>' . str_replace(array('{{T}}', '{{K}}'), array((string)$ticket, (string)$postKey), $code) . '</script>';
}
