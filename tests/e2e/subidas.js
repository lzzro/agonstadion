// =====================================================================
// La subida de la foto de perfil y la portada - Stadion (Agon)
// ---------------------------------------------------------------------
//   node tests/e2e/subidas.js
//
// Las defensas de ImagenSubida y de public/subidas/.htaccess:
//   1. sin sesion no se sube nada
//   2. JPG, PNG y WEBP de verdad se aceptan, con un nombre de 32
//      caracteres al azar y la extension de su tipo real; la anterior se
//      borra del disco; el nombre que manda el navegador no se usa (un
//      ../ no sale de la carpeta); la foto se entrega como imagen, con
//      X-Content-Type-Options: nosniff
//   3. lo que se rechaza: PHP con nombre o tipo de imagen, texto, GIF,
//      SVG con script, mas de 4000 px de lado, mas de 2 MB, ningun
//      archivo
//   4. cada cuenta cambia solo SU foto (un id_usuario en el formulario
//      no sirve), y cada carga aceptada queda en la auditoria
//   5. la carpeta no ejecuta nada: una imagen con PHP pegado al final se
//      entrega tal cual; archivos .php, .phtml, .phar, .user.ini puestos a
//      mano en la carpeta no corren (403), y el mismo PHP fuera de la
//      carpeta si corre (el control de que la prueba mide al .htaccess);
//      la carpeta no se lista y el .htaccess no se entrega
//
// Las imagenes se fabrican en cada corrida, en memoria, con el propio
// Chromium (un canvas): el repositorio no guarda archivos de prueba.
//
// SOLO TOCA LO QUE ELLA MISMA CREA, igual que permisos.js: dos cuentas de
// @ejemplo.invalid, con contrasena al azar. Los archivos que pone a mano
// (en la carpeta de las subidas y el control en la raiz publica) se
// borran enseguida, y otra vez al final por las dudas. Al final se
// borran sus cuentas y sus fotos (limpiarCuentas en comun.js), y se
// comprueba que la base (CHECKSUM TABLE) y la carpeta de las subidas
// quedan como estaban.
//
// Variables de entorno, ademas de las de comun.js (ver tests/README.md):
//   STADION_SUBIDAS  la carpeta de las fotos del sitio de prueba
//                    (por defecto public/subidas del repositorio)
//   STADION_PUBLICA  la raiz publica del sitio de prueba, donde se pone
//                    el control (por defecto public/ del repositorio)
// =====================================================================
'use strict';
const fs = require('fs');
const path = require('path');
const c = require('./comun');
const { BASE, sql, SUBIDAS } = c;

const PUBLICA = process.env.STADION_PUBLICA || path.join(c.RAIZ, 'public');
const PHP = '<?php echo "EJECUTADO-".(6*7); ?>';
const HEX = '0123456789abcdef0123456789abcdef';
// Lo que la prueba pone a mano en el disco: se borra siempre.
const puestos = new Set();
function poner(archivo, contenido) {
  fs.writeFileSync(archivo, contenido);
  puestos.add(archivo);
  // Del mismo dueno que la carpeta: PHP-FPM puede negarse a leer un
  // archivo de otro.
  try { const d = fs.statSync(path.dirname(archivo)); fs.chownSync(archivo, d.uid, d.gid); } catch (e) { /* sin permiso: queda del que corre */ }
}
function sacar(archivo) { try { fs.unlinkSync(archivo); } catch (e) { /* ya no estaba */ } puestos.delete(archivo); }

// Las imagenes, hechas por Chromium en un canvas.
async function fabricar(nav) {
  const ctx = await nav.newContext();
  const p = await ctx.newPage();
  await p.setContent('<!doctype html><title>imagenes</title>');
  const hacer = (ancho, alto, tipo, ruido) => p.evaluate(({ ancho, alto, tipo, ruido }) => {
    const lienzo = document.createElement('canvas');
    lienzo.width = ancho; lienzo.height = alto;
    const g = lienzo.getContext('2d');
    if (ruido) {
      // Ruido: un PNG que no se puede comprimir, para pasar los 2 MB.
      const d = g.createImageData(ancho, alto);
      for (let i = 0; i < d.data.length; i++) d.data[i] = (i % 4 === 3) ? 255 : (Math.random() * 256) | 0;
      g.putImageData(d, 0, 0);
    } else {
      g.fillStyle = '#75865A'; g.fillRect(0, 0, ancho, alto);
      g.fillStyle = '#F3EEE3'; g.fillRect(ancho / 4, alto / 4, ancho / 2, alto / 2);
    }
    return lienzo.toDataURL(tipo, 0.9).split(',')[1];
  }, { ancho, alto, tipo, ruido }).then(b => Buffer.from(b, 'base64'));
  const img = {
    jpg: await hacer(300, 300, 'image/jpeg'),
    png: await hacer(300, 300, 'image/png'),
    webp: await hacer(300, 300, 'image/webp'),
    otra: await hacer(600, 200, 'image/png'),
    ancha: await hacer(4001, 10, 'image/png'),
    pesada: await hacer(1300, 1300, 'image/png', true)
  };
  await ctx.close();
  return img;
}

let nav;
(async () => {
  const t = c.contador('Subida de la foto de perfil y la portada');
  const antes = { huella: c.huellaBase(), carpeta: c.archivosSubidas() };
  const propias = [];
  nav = await c.navegador();
  try {
    const img = await fabricar(nav);
    t.chk(img.jpg[0] === 0xFF && img.webp.slice(8, 12).toString() === 'WEBP' && img.pesada.length > 3 * 1024 * 1024,
          `las imagenes de prueba, hechas en memoria (la pesada, ${(img.pesada.length / 1048576).toFixed(1)} MB)`);
    const archivo = (name, mimeType, buffer) => ({ name, mimeType, buffer });
    const GIF = Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64');
    const SVG = Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>');

    const A = await c.cuentaNueva(nav, 'Ícaro', 'Prueba', propias);
    const B = await c.cuentaNueva(nav, 'Dédalo', 'Prueba', propias);
    const foto = cuenta => sql(`SELECT IFNULL(foto_perfil, 'NULL') FROM usuario WHERE id_usuario = ${cuenta.id}`);
    const portada = cuenta => sql(`SELECT IFNULL(foto_portada, 'NULL') FROM usuario WHERE id_usuario = ${cuenta.id}`);
    const existe = nombre => fs.existsSync(path.join(SUBIDAS, nombre));

    // --- 1. Sin sesion ------------------------------------------------------
    console.log('--- 1. Sin sesion ---');
    const anon = await nav.newContext();
    const PERFIL = new URL((await anon.request.get(`${BASE}/perfil.php`, { maxRedirects: 0 })).headers()['location'], `${BASE}/perfil.php`).href;
    let r = await anon.request.post(PERFIL, { multipart: { accion: 'foto', imagen: archivo('valida.png', 'image/png', img.png) }, maxRedirects: 0 });
    t.chk(r.status() === 302 && (r.headers()['location'] || '').endsWith('/login.php') && c.archivosSubidas() === antes.carpeta,
          'subir sin sesion: 302 al acceso, y ningun archivo nuevo en la carpeta');
    await anon.close();

    // --- 2. Imagenes validas ------------------------------------------------
    console.log('--- 2. Imagenes validas ---');
    const S = await c.entrar(nav, A);
    const TK = await c.token(S.p);
    const subir = async (campos, extra = {}) => {
      const resp = await S.ctx.request.post(PERFIL, { multipart: Object.assign({ token_csrf: TK }, campos, extra), maxRedirects: 0 });
      const cuerpo = await resp.text();
      const alerta = /<div role="alert">\s*<ul class="avisos">\s*<li>([^<]*)<\/li>/.exec(cuerpo);
      const estado = /<p class="intro" role="status">([^<]*)<\/p>/.exec(cuerpo);
      return { estado: resp.status(), aviso: alerta ? alerta[1] : (estado ? estado[1] : '') };
    };
    let previa = null;
    for (const [nombre, tipo, ext] of [['valida.jpg', 'image/jpeg', 'jpg'], ['valida.png', 'image/png', 'png'], ['valida.webp', 'image/webp', 'webp']]) {
      r = await subir({ accion: 'foto', imagen: archivo(nombre, tipo, img[ext]) });
      const n = foto(A);
      t.chk(r.aviso === 'La foto de perfil queda cargada.' && new RegExp(`^[0-9a-f]{32}\\.${ext}$`).test(n) && existe(n)
            && (previa === null || !existe(previa)),
            `${nombre}: "${r.aviso}" · guardada como ${n}${previa ? ' · la anterior ya no esta en el disco' : ''}`);
      previa = n;
    }
    const p_foto = foto(A);
    r = await subir({ accion: 'portada', imagen: archivo('../../apps/config/pisada.png', 'image/png', img.otra) });
    const q = portada(A);
    const pisadas = [path.join(c.RAIZ, 'apps', 'config', 'pisada.png'), path.join(PUBLICA, '..', 'apps', 'config', 'pisada.png'),
                     path.join(PUBLICA, '..', 'stadion_app', 'config', 'pisada.png')];
    t.chk(/^[0-9a-f]{32}\.png$/.test(q) && pisadas.every(x => !fs.existsSync(x)), `un nombre con ../ no se usa: la portada queda como ${q}`);
    t.chk(existe(q) && existe(p_foto), 'la portada no toca la foto: las dos en el disco');
    let g = await S.ctx.request.get(`${BASE}/subidas/${p_foto}`);
    t.chk(g.status() === 200 && g.headers()['content-type'] === 'image/webp', `la foto se entrega como imagen (${g.status()} ${g.headers()['content-type']})`);
    t.chk(/nosniff/i.test(g.headers()['x-content-type-options'] || ''), `con X-Content-Type-Options: ${g.headers()['x-content-type-options']}`);

    // --- 3. Lo que se rechaza --------------------------------------------------
    console.log('--- 3. Lo que se rechaza (el tipo se mira por dentro, no por el nombre) ---');
    const carpeta = c.archivosSubidas();
    const rechazo = async (imagen, aviso, que) => {
      r = await subir({ accion: 'foto', imagen });
      t.chk(foto(A) === p_foto && c.archivosSubidas() === carpeta && r.aviso.startsWith(aviso), `${que}: "${r.aviso}"`);
    };
    await rechazo(archivo('shell.php', 'image/jpeg', Buffer.from(PHP)), 'Solo se aceptan', 'shell.php declarado image/jpeg');
    await rechazo(archivo('falsa.jpg', 'image/jpeg', Buffer.from(PHP)), 'Solo se aceptan', 'falsa.jpg (PHP adentro)');
    await rechazo(archivo('texto.png', 'image/png', Buffer.from('Esto es texto, no una imagen.\n')), 'Solo se aceptan', 'texto.png (texto adentro)');
    await rechazo(archivo('anim.gif', 'image/gif', GIF), 'Solo se aceptan', 'anim.gif (un GIF de verdad)');
    await rechazo(archivo('dibujo.svg', 'image/svg+xml', SVG), 'Solo se aceptan', 'dibujo.svg (con un script)');
    await rechazo(archivo('ancha.png', 'image/png', img.ancha), 'La imagen supera los 4000', 'ancha.png (4001 × 10 px)');
    await rechazo(archivo('pesada.png', 'image/png', img.pesada), 'La imagen supera los 2 MB', `pesada.png (${(img.pesada.length / 1048576).toFixed(1)} MB)`);
    await rechazo(archivo('enorme.bin', 'image/png', Buffer.alloc(9 * 1024 * 1024, 7)), 'La imagen supera los 2 MB', 'enorme.bin (9 MB)');
    r = await subir({ accion: 'foto' });
    t.chk(r.aviso === 'Falta elegir una imagen.', `sin archivo: "${r.aviso}"`);

    // --- 4. Cada cuenta cambia solo su foto, y la auditoria ---------------------
    console.log('--- 4. Cada cuenta cambia solo su foto ---');
    r = await subir({ accion: 'foto', imagen: archivo('otra.png', 'image/png', img.otra) }, { id_usuario: String(B.id) });
    t.chk(foto(B) === 'NULL' && foto(A) !== p_foto && existe(foto(A)),
          `con id_usuario=${B.id} en el formulario cambia la de la cuenta de la sesion (${foto(A)}); la otra sigue sin foto`);
    const cargas = que => sql(`SELECT COUNT(*) FROM auditoria WHERE id_usuario = ${A.id} AND accion = 'modificacion' AND detalle = '${que}'`);
    t.chk(cargas('Carga de foto de perfil') === '4' && cargas('Carga de portada') === '1',
          'en la auditoria: 4 "Carga de foto de perfil" y 1 "Carga de portada" (una por carga aceptada, ninguna por las rechazadas)');

    // --- 5. La carpeta no ejecuta nada -----------------------------------------
    console.log('--- 5. La carpeta no ejecuta nada ---');
    const poliglota = Buffer.concat([img.png, Buffer.from(PHP)]);
    r = await subir({ accion: 'foto', imagen: archivo('poliglota.png', 'image/png', poliglota) });
    const pg = foto(A);
    t.chk(r.aviso.includes('queda cargada'), 'poliglota.png (un PNG de verdad con PHP pegado al final): se acepta, es una imagen');
    g = await S.ctx.request.get(`${BASE}/subidas/${pg}`);
    let cuerpo = (await g.body()).toString('latin1');
    t.chk(cuerpo.includes('<?php echo') && !cuerpo.includes('EJECUTADO-42') && g.headers()['content-type'] === 'image/png',
          `  ...pero se entrega tal cual (${g.headers()['content-type']}): el PHP de adentro no corre`);
    const control = path.join(PUBLICA, `control-${c.azar()}.php`);
    poner(control, PHP);
    g = await S.ctx.request.get(`${BASE}/${path.basename(control)}`);
    const salida = await g.text();
    sacar(control);
    t.chk(salida === 'EJECUTADO-42', `control: el mismo PHP fuera de subidas/ si corre ("${salida.slice(0, 20)}"), asi que lo de abajo mide al .htaccess`);
    for (const nombre of ['shell.php', 'shell.phtml', 'shell.phar', 'shell.php.png', 'foto.php5', `${HEX}.php`, '.user.ini']) {
      const x = path.join(SUBIDAS, nombre);
      poner(x, PHP);
      g = await S.ctx.request.get(`${BASE}/subidas/${nombre}`);
      cuerpo = await g.text();
      sacar(x);
      t.chk(g.status() === 403 && !cuerpo.includes('EJECUTADO-42'), `subidas/${nombre} puesto a mano: ${g.status()}, no corre`);
    }
    const falsa = path.join(SUBIDAS, `${HEX}.png`);
    poner(falsa, PHP);
    g = await S.ctx.request.get(`${BASE}/subidas/${HEX}.png`);
    cuerpo = await g.text();
    sacar(falsa);
    t.chk(g.status() === 200 && g.headers()['content-type'] === 'image/png' && !cuerpo.includes('EJECUTADO-42'),
          `subidas/${HEX}.png con PHP adentro: ${g.status()} ${g.headers()['content-type']}, se entrega sin correr`);
    g = await S.ctx.request.get(`${BASE}/subidas/`);
    t.chk(g.status() === 403 && !(await g.text()).includes('Index of'), `la carpeta no se lista (${g.status()})`);
    g = await S.ctx.request.get(`${BASE}/subidas/.htaccess`);
    t.chk(g.status() === 403, `subidas/.htaccess no se entrega (${g.status()})`);
    await S.ctx.close();
  } catch (e) {
    console.error(e);
    t.chk(false, `la bateria corre entera (${e.message.split('\n')[0]})`);
  } finally {
    try {
      for (const x of [...puestos]) sacar(x);
      const limpieza = c.limpiarCuentas(propias);
      t.chk(limpieza.faltaban === 0, `al final, las ${limpieza.fotos} fotos de la corrida estaban en la carpeta de las subidas, y se borran`);
      t.chk(c.archivosSubidas() === antes.carpeta, 'la carpeta de las subidas tiene los mismos archivos que antes');
      t.chk(c.huellaBase() === antes.huella, 'y usuario, usuario_rol, pedido_rol y auditoria quedan iguales (CHECKSUM TABLE)');
    } catch (e) {
      t.chk(false, `la limpieza (${e.message.split('\n')[0]})`);
    }
    if (nav) await nav.close();
    t.fin();
  }
})();
