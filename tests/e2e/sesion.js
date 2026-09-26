// =====================================================================
// La cabecera con sesion, el perfil y la tarjeta de los roles
// Stadion (Agon)
// ---------------------------------------------------------------------
//   node tests/e2e/sesion.js
//
//   1. sin sesion, la cabecera de siempre: Iniciar sesion · Crear torneo
//   2. con sesion y sin foto: un circulo con las iniciales en cada
//      pagina, con el nombre en aria-label y title, antes de Crear torneo,
//      de 34 px (30 en el telefono), olivo palido de dia y el de noche,
//      con foco visible
//   3. con foto: la misma foto del perfil, recortada en el circulo
//   4. los nombres para mostrar (Usuario::paraMostrar): "ariadna" sube la
//      primera letra, "de Ítaca" queda como se escribio; la base y el
//      formulario, tal cual
//   5. cerrar sesion desde el perfil: formulario POST debajo del nombre,
//      texto discreto; vuelve al inicio, borra la sesion y la cookie, y
//      queda "logout" en la auditoria
//   6. media hora sin uso: la cabecera vuelve a Iniciar sesion
//   7. el perfil con pestanas (Datos, Mis torneos, Rendimiento), sin
//      JavaScript; las estadisticas reales y las de muestra; la foto y la
//      portada
//   8. la tarjeta de los roles: pedir el de organizador con clics de
//      verdad, la administracion (su lista, sus marcas, sin desborde en
//      tres anchos y dos modos), rechazar, pedir otra vez y aprobar, y el
//      registro en sustantivos
//
// "Mis torneos" con ligas de verdad lo prueba ligas.js, que las arma.
//
// SOLO TOCA LO QUE ELLA MISMA CREA, igual que permisos.js: cuentas de
// @ejemplo.invalid con contrasena al azar; el administrador es una de
// ellas (sql/primer_administrador.sql). Al final se borran sus cuentas,
// sus pedidos, su auditoria y sus fotos (limpiarCuentas en comun.js), y
// se comprueba que la base (CHECKSUM TABLE) y la carpeta de las subidas
// quedan como estaban.
//
// Variables de entorno: las de comun.js, STADION_SUBIDAS (ver
// subidas.js) y STADION_SESIONES (ver permisos.js; sin la carpeta de
// las sesiones, los pasos 5 y 6 no miran el archivo de la sesion).
// =====================================================================
'use strict';
const fs = require('fs');
const path = require('path');
const c = require('./comun');
const { BASE, sql } = c;

const SESIONES = (process.env.STADION_SESIONES !== undefined) ? process.env.STADION_SESIONES : '/var/lib/php/sessions';
const PAGINAS = ['index.php', 'torneos.php', 'torneo.php', 'calendario.php', 'llave.php', 'crear.php'];

// Lo que se ve del circulo de la cabecera.
const circulo = p => p.evaluate(() => {
  const a = document.querySelector('header .acciones a.sesion-circulo');
  if (!a) return null;
  const hijo = a.firstElementChild, s = getComputedStyle(hijo);
  return { href: a.getAttribute('href'), aria: a.getAttribute('aria-label'), title: a.getAttribute('title'), tag: hijo.tagName,
           texto: hijo.textContent, src: hijo.getAttribute('src'), ancho: hijo.getBoundingClientRect().width, radio: s.borderRadius,
           fondo: s.backgroundColor, borde: s.borderTopColor, fuente: s.fontFamily,
           iniciar: !!document.querySelector('header a[href$="login.php"]'), salir: !!document.querySelector('header form'),
           orden: [...document.querySelectorAll('header .acciones > *')].map(e => e.className) };
});
const desborde = p => p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const archivoSesion = async ctx => {
  const k = (await ctx.cookies()).find(x => x.name === 'PHPSESSID');
  return k ? path.join(SESIONES, `sess_${k.value}`) : '';
};

// Una imagen hecha por Chromium en un canvas (ver subidas.js).
async function imagen(nav, tipo, ancho, alto) {
  const ctx = await nav.newContext();
  const p = await ctx.newPage();
  await p.setContent('<!doctype html><title>imagen</title>');
  const b64 = await p.evaluate(({ tipo, ancho, alto }) => {
    const l = document.createElement('canvas'); l.width = ancho; l.height = alto;
    const g = l.getContext('2d'); g.fillStyle = '#75865A'; g.fillRect(0, 0, ancho, alto);
    g.fillStyle = '#F3EEE3'; g.fillRect(ancho / 4, alto / 4, ancho / 2, alto / 2);
    return l.toDataURL(tipo, 0.9).split(',')[1];
  }, { tipo, ancho, alto });
  await ctx.close();
  return { name: 'imagen.' + tipo.split('/')[1].replace('jpeg', 'jpg'), mimeType: tipo, buffer: Buffer.from(b64, 'base64') };
}

let nav;
(async () => {
  const t = c.contador('Cabecera con sesion, perfil y tarjeta de los roles');
  const antes = { huella: c.huellaBase(), carpeta: c.archivosSubidas() };
  const propias = [];
  nav = await c.navegador();
  try {
    // Nombres guardados en minuscula (y uno con mayuscula adentro), para
    // probar Usuario::paraMostrar.
    const A = await c.cuentaNueva(nav, 'ariadna', 'minos', propias);
    const O = await c.cuentaNueva(nav, 'odiseo', 'de Ítaca', propias);
    const N = await c.cuentaNueva(nav, 'nausícaa', 'Prueba', propias);
    sql(fs.readFileSync(path.join(c.RAIZ, 'sql', 'primer_administrador.sql'), 'utf8').replace(/CORREO_DE_LA_CUENTA/g, A.correo));
    t.chk(sql(`SELECT COUNT(*) FROM usuario_rol ur JOIN rol r USING (id_rol) WHERE ur.id_usuario = ${A.id} AND r.nombre = 'administrador'`) === '1',
          'tres cuentas nuevas de @ejemplo.invalid; la primera, administradora (sql/primer_administrador.sql)');

    // --- 1. Sin sesion ----------------------------------------------------------
    console.log('--- 1. Sin sesion ---');
    const v = await nav.newContext({ viewport: { width: 1024, height: 900 } });
    const pv = await v.newPage();
    const sin = [];
    // (login.php y registro.php no llevan estas acciones: su <header> es
    // el panel de marmol.)
    for (const pag of PAGINAS.slice(0, 5)) {
      await pv.goto(`${BASE}/${pag}`);
      const acciones = await pv.$$eval('header .acciones > *', es => es.map(e => e.tagName + ' ' + (e.getAttribute('href') || '').replace(/^\.\//, '')
                                                                             + ' ' + e.textContent.trim()));
      if (acciones.join(' | ') !== 'A login.php Iniciar sesión | A crear.php Crear torneo' || await circulo(pv) !== null) sin.push(`${pag}: ${acciones.join(' | ')}`);
    }
    t.chk(sin.length === 0, `sin sesion, la cabecera de siempre en 5 paginas: Iniciar sesion · Crear torneo, sin circulo${sin.length ? ' (' + sin.join('; ') + ')' : ''}`);
    await v.close();

    // --- 2. Con sesion y sin foto ---------------------------------------------------
    console.log('--- 2. Con sesion y sin foto: el circulo con las iniciales ---');
    const S = await c.entrar(nav, A, { viewport: { width: 1024, height: 900 } });
    const p = S.p;
    const ingreso = await p.textContent('main');
    t.chk(ingreso.includes('queda abierta a nombre de Ariadna Minos.'), 'el aviso de ingreso nombra a la persona como se muestra: "Ariadna Minos"');
    const r0 = await circulo(p);
    t.chk(r0 && r0.texto === 'AM', `la vista de resultado ya lleva el circulo (${r0 && r0.texto})`);
    for (const pag of PAGINAS) {
      await p.goto(`${BASE}/${pag}`);
      const k = await circulo(p);
      t.chk(k && k.tag === 'SPAN' && k.texto === 'AM' && k.aria === 'Perfil de Ariadna Minos' && k.title === k.aria && !k.iniciar && !k.salir
            && k.orden.join() === 'sesion-circulo,btn btn-primario',
            `${pag}: circulo "${k && k.texto}" con aria-label y title "${k && k.aria}", antes de Crear torneo, sin Iniciar sesion ni Cerrar sesion`);
    }
    await p.goto(`${BASE}/index.php`);
    let k = await circulo(p);
    t.chk(Math.round(k.ancho) === 34 && k.radio === '50%' && /Cormorant/.test(k.fuente), `a 1024 px: ${Math.round(k.ancho)} px, redondo, iniciales en ${k.fuente.split(',')[0]}`);
    t.chk(k.fondo === 'rgb(230, 234, 217)' && k.borde === 'rgb(117, 134, 90)', `de dia: fondo olivo palido ${k.fondo}, borde olivo ${k.borde}`);
    await p.evaluate(() => document.documentElement.setAttribute('data-theme', 'noche'));
    k = await circulo(p);
    t.chk(k.fondo === 'rgb(35, 42, 27)' && k.borde === 'rgb(185, 203, 156)', `de noche: fondo ${k.fondo}, borde ${k.borde}`);
    await p.evaluate(() => document.documentElement.removeAttribute('data-theme'));
    await p.setViewportSize({ width: 390, height: 900 });
    k = await circulo(p);
    t.chk(Math.round(k.ancho) === 30, `a 390 px: ${Math.round(k.ancho)} px`);
    await p.setViewportSize({ width: 1024, height: 900 });
    let foco = null;
    for (let i = 0; i < 12 && !foco; i++) {
      await p.keyboard.press('Tab');
      foco = await p.evaluate(() => { const a = document.activeElement; if (!a.classList.contains('sesion-circulo')) return null;
        const s = getComputedStyle(a); return `${s.outlineStyle} ${s.outlineWidth} ${s.outlineColor}`; });
    }
    t.chk(foco && foco.startsWith('solid 2px'), `con Tab el circulo toma un foco visible (${foco})`);

    // --- 3. Con foto --------------------------------------------------------------
    console.log('--- 3. Con foto: la misma del perfil, en el circulo ---');
    await p.goto(`${BASE}/perfil.php`);
    const PERFIL = p.url().split('#')[0];
    await p.setInputFiles('form.subida:has(input[value="foto"]) input[type=file]', await imagen(nav, 'image/jpeg', 300, 300));
    await Promise.all([p.waitForNavigation(), p.click('form.subida:has(input[value="foto"]) button')]);
    const grande = await p.evaluate(() => document.querySelector('main img.avatar').getAttribute('src').split('/').pop());
    // La carpeta que mira la prueba tiene que ser la del sitio: si no, la
    // limpieza borraria en otro lado.
    if (!t.chk(fs.existsSync(path.join(c.SUBIDAS, grande)), `la foto queda en la carpeta de las subidas que mira la prueba (${c.SUBIDAS})`)) {
      throw new Error('la carpeta de las subidas no es la del sitio: ver STADION_SUBIDAS');
    }
    for (const pag of ['index.php', 'torneo.php']) {
      await p.goto(`${BASE}/${pag}`);
      k = await circulo(p);
      const bien = await p.evaluate(() => { const i = document.querySelector('header a.sesion-circulo img');
        return i && i.complete && i.naturalWidth > 0 && getComputedStyle(i).objectFit === 'cover' && i.alt === ''; });
      t.chk(k.tag === 'IMG' && k.src.endsWith(grande) && bien, `${pag}: <img> ${grande}, la del perfil (cargada, object-fit cover, alt vacio: el enlace ya dice a quien lleva)`);
    }

    // --- 4. Nombres para mostrar -----------------------------------------------------
    console.log('--- 4. Nombres para mostrar ---');
    await p.goto(PERFIL);
    const n = await p.evaluate(() => ({ h1: document.querySelector('main h1').textContent, nombre: document.querySelector('input[name=nombre]').value,
      apellido: document.querySelector('input[name=apellido]').value, rank: document.querySelector('#rendimiento tr.clasifica td').textContent,
      linea: document.querySelector('.perfil-cabecera .intro').textContent }));
    t.chk(n.h1 === 'Ariadna Minos' && n.rank === 'Ariadna Minos', `guardado "ariadna minos": en pantalla "${n.h1}" (titulo y ranking)`);
    t.chk(n.nombre === 'ariadna' && n.apellido === 'minos' && sql(`SELECT CONCAT(nombre, ' ', apellido) FROM usuario WHERE id_usuario = ${A.id}`) === 'ariadna minos',
          'el formulario muestra lo guardado tal cual, y la base no cambia');
    t.chk(/administrador/.test(n.linea) && /jugador/.test(n.linea), `debajo del nombre, los roles: "${n.linea.trim()}"`);

    // --- 5. Cerrar sesion desde el perfil ------------------------------------------------
    console.log('--- 5. Cerrar sesion desde el perfil ---');
    const f = await p.evaluate(() => { const x = document.querySelector('.perfil-cabecera form.salir-perfil'); const b = x && x.querySelector('button');
      const s = b && getComputedStyle(b);
      return x && { metodo: x.getAttribute('method'), texto: b.textContent, borde: s.borderTopStyle, fondo: s.backgroundColor, color: s.color,
        mayus: s.textTransform, espacio: s.letterSpacing,
        debajo: x.getBoundingClientRect().top > document.querySelector('.perfil-cabecera .intro').getBoundingClientRect().top }; });
    t.chk(f && f.metodo === 'post' && f.texto === 'Cerrar sesión' && f.debajo, 'Cerrar sesion: un formulario POST, debajo del nombre y los roles');
    t.chk(f.borde === 'none' && f.fondo === 'rgba(0, 0, 0, 0)' && f.color === 'rgb(112, 107, 97)' && f.mayus === 'uppercase' && parseFloat(f.espacio) > 1,
          `texto discreto: sin borde ni fondo, en ${f.color}, en versalitas`);
    const sesion1 = await archivoSesion(S.ctx);
    const habia = sesion1 !== '' && fs.existsSync(sesion1);   // el archivo de la sesion, antes de cerrarla
    const salidas = () => sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'logout' AND id_usuario = ${A.id}`);
    const salidas0 = salidas();
    await Promise.all([p.waitForNavigation(), p.click('.perfil-cabecera form.salir-perfil button')]);
    t.chk(/\/index\.php$/.test(p.url()) && await p.isVisible('header a[href$="login.php"]') && !(await circulo(p)),
          'vuelve al inicio, con Iniciar sesion y sin circulo');
    const sin_cookie = !(await S.ctx.cookies()).some(x => x.name === 'PHPSESSID');
    if (habia) {
      t.chk(!fs.existsSync(sesion1) && sin_cookie, 'la sesion se borra del servidor, y la cookie del navegador');
    } else {
      t.chk(sin_cookie, 'la cookie de la sesion se borra del navegador');
      console.log(`  --    el borrado en el servidor: omitido (no esta el archivo de la sesion en "${SESIONES}"; ver STADION_SESIONES)`);
    }
    t.chk(salidas() === String(parseInt(salidas0, 10) + 1), 'y queda una fila "logout" en la auditoria');

    // --- 6. Media hora sin uso -------------------------------------------------------------
    console.log('--- 6. Media hora sin uso ---');
    await S.ctx.close();
    const S2 = await c.entrar(nav, A, { viewport: { width: 1024, height: 900 } });
    await S2.p.goto(`${BASE}/torneos.php`);
    const sesion2 = await archivoSesion(S2.ctx);
    if (sesion2 !== '' && fs.existsSync(sesion2)) {
      fs.writeFileSync(sesion2, fs.readFileSync(sesion2, 'utf8').replace(/ultima_actividad\|i:\d+;/, `ultima_actividad|i:${Math.floor(Date.now() / 1000) - 1801};`));
      await S2.p.goto(`${BASE}/calendario.php`);
      t.chk(await S2.p.isVisible('header a[href$="login.php"]') && !(await circulo(S2.p)), 'a los 30 minutos y 1 segundo: vuelve Iniciar sesion, sin circulo');
    } else {
      console.log(`  --    media hora sin uso: omitida (no esta el archivo de la sesion en "${SESIONES}"; ver STADION_SESIONES)`);
    }
    await S2.ctx.close();

    // --- 7. El perfil con pestanas ----------------------------------------------------------
    console.log('--- 7. El perfil con pestanas ---');
    const P = await c.entrar(nav, N, { viewport: { width: 1024, height: 900 } });
    const pp = P.p;
    const estado = () => pp.evaluate(() => {
      const abiertas = [...document.querySelectorAll('.vista')].filter(x => getComputedStyle(x).display !== 'none');
      return { vistas: abiertas.map(x => x.id), activa: abiertas.map(x => (x.querySelector('.pestanas .activo') || {}).textContent) };
    });
    for (const [ancla, id, texto] of [['', 'datos', 'Datos'], ['#datos', 'datos', 'Datos'], ['#mis-torneos', 'mis-torneos', 'Mis torneos'], ['#rendimiento', 'rendimiento', 'Rendimiento']]) {
      await pp.goto(PERFIL + ancla);
      const e = await estado();
      t.chk(e.vistas.length === 1 && e.vistas[0] === id && e.activa[0] === texto, `${ancla || '(sin ancla)'}: se ve solo ${e.vistas.join(',')}, con su pestana marcada`);
    }
    for (const [de, a] of [['Mis torneos', 'mis-torneos'], ['Rendimiento', 'rendimiento'], ['Datos', 'datos']]) {
      await pp.click(`.vista .pestanas a:visible:text-is("${de}")`);
      t.chk((await estado()).vistas.join() === a, `un clic en ${de} abre ${a}, sin JavaScript`);
    }
    await pp.goto(PERFIL);
    t.chk(await pp.$$eval('nav a.activo, nav a[aria-current]', as => as.length) === 0, 'el menu no marca ninguna entrada: el perfil no cuelga de ninguna');
    const datos = await pp.$$eval('.datos > div', ds => ds.map(d => ({ n: d.querySelector('strong').textContent, e: d.querySelector('.etiqueta').textContent, m: !!d.querySelector('.muestra') })));
    t.chk(datos[0].e === 'Torneos' && datos[0].n === '0' && !datos[0].m, 'Torneos: 0, sin marca (sale de la base)');
    t.chk(datos[1].m && datos[2].m, `${datos[1].e} y ${datos[2].e}: los dos con la marca "De muestra"`);
    await pp.goto(PERFIL + '#mis-torneos');
    t.chk((await pp.textContent('#mis-torneos')).includes('Ningún torneo a nombre de esta cuenta.') && !(await pp.$('#mis-torneos .muestra')),
          'Mis torneos, vacia: el mensaje, sin datos de ejemplo ni marca');
    await pp.goto(PERFIL + '#rendimiento');
    const rend = await pp.evaluate(() => ({ valores: [...document.querySelectorAll('#rendimiento .medida .valor')].map(x => x.textContent),
      svg: !!document.querySelector('#rendimiento svg.grafico polyline'), destacada: (document.querySelector('#rendimiento tr.clasifica') || {}).textContent || '',
      marcas: document.querySelectorAll('#rendimiento .muestra').length, instrumentos: /Phyphox/.test(document.querySelector('#rendimiento').textContent) }));
    t.chk(rend.valores.join() === '187,3.8,612' && rend.svg, `Rendimiento: ${rend.valores.join(' · ')} y el grafico`);
    t.chk(/Nausícaa Prueba\s*187/.test(rend.destacada), `el ranking destaca a quien tiene la sesion ("${rend.destacada.replace(/\s+/g, ' ').trim()}")`);
    t.chk(rend.marcas === 3 && rend.instrumentos, `${rend.marcas} marcas "De muestra" (indicadores, grafico y ranking), y la nota de los instrumentos`);
    await pp.goto(PERFIL);
    const sin_img = await pp.evaluate(() => ({ av: document.querySelector('.avatar').tagName + ':' + document.querySelector('.avatar').textContent,
      po: !!document.querySelector('.portada img') }));
    t.chk(sin_img.av === 'SPAN:NP' && !sin_img.po, `sin imagenes: el circulo con las iniciales (${sin_img.av.split(':')[1]}) y la portada lisa`);
    await pp.setInputFiles('form.subida:has(input[value="foto"]) input[type=file]', await imagen(nav, 'image/png', 300, 300));
    await Promise.all([pp.waitForNavigation(), pp.click('form.subida:has(input[value="foto"]) button')]);
    await pp.setInputFiles('form.subida:has(input[value="portada"]) input[type=file]', await imagen(nav, 'image/webp', 600, 200));
    await Promise.all([pp.waitForNavigation(), pp.click('form.subida:has(input[value="portada"]) button')]);
    const con = await pp.evaluate(() => { const a = document.querySelector('img.avatar'), b = document.querySelector('.portada img');
      return { a: a && a.getAttribute('src'), b: b && b.getAttribute('src'), ca: a && a.complete && a.naturalWidth, cb: b && b.complete && b.naturalWidth }; });
    t.chk(/subidas\/[0-9a-f]{32}\.png$/.test(con.a) && con.ca === 300, `la foto: ${con.a && con.a.split('/').pop()}, cargada (${con.ca} px)`);
    t.chk(/subidas\/[0-9a-f]{32}\.webp$/.test(con.b) && con.cb === 600, `la portada: ${con.b && con.b.split('/').pop()}, cargada (${con.cb} px)`);
    await P.ctx.close();

    // --- 8. La tarjeta de los roles -----------------------------------------------------------
    console.log('--- 8. La tarjeta de los roles y la administracion ---');
    const J = await c.entrar(nav, O, { viewport: { width: 390, height: 900 } });
    const pj = J.p;
    await pj.goto(PERFIL);
    const boton = pj.locator('form.pedir-rol button');
    t.chk(await boton.count() === 1, '"Odiseo de Ítaca" ve "Pedir el rol de organizador"');
    const bb = await boton.boundingBox();
    const tb = await pj.locator('aside .tarjeta', { has: pj.locator('form.pedir-rol') }).boundingBox();
    t.chk(bb.width < tb.width - 60, `a 390 px el boton no ocupa toda la tarjeta (${Math.round(bb.width)} de ${Math.round(tb.width)} px)`);
    await Promise.all([pj.waitForNavigation(), boton.click()]);
    t.chk((await pj.textContent('main')).includes('queda en revision'), 'aviso: el pedido queda en revision');
    t.chk((await pj.textContent('aside')).includes('pedido en revisión desde el'), 'la tarjeta dice "pedido en revisión desde el …"');
    t.chk(await pj.locator('form.pedir-rol').count() === 0 && await pj.locator('aside a', { hasText: 'Administración' }).count() === 0,
          'sin el boton mientras esta en revision, y sin enlace a la administracion');

    const D = await c.entrar(nav, A, { viewport: { width: 1024, height: 900 } });
    const pa = D.p;
    await pa.goto(PERFIL);
    const enlace = pa.locator('aside a', { hasText: 'Administración' });
    t.chk(await enlace.count() === 1, 'la administradora ve "Administración →" en su perfil');
    await Promise.all([pa.waitForNavigation(), enlace.click()]);
    const ADMIN = pa.url().split('#')[0];
    t.chk((await pa.textContent('h1')).includes('Administración'), 'el enlace abre la administracion');
    const suyo = () => pa.locator('#pedidos .pedido', { hasText: O.correo });
    t.chk(await suyo().count() === 1, 'el pedido esta en la lista');
    t.chk(await pa.locator('#cuentas tbody tr').count() === parseInt(sql('SELECT COUNT(*) FROM usuario'), 10), 'Cuentas: todas las de la base, ninguna mas');
    const de_muestra = parseInt(sql('SELECT COUNT(*) FROM usuario WHERE de_muestra = 1'), 10);
    t.chk(await pa.locator('#cuentas .muestra').count() === de_muestra && await pa.locator('#modulos .muestra').count() === 1
          && await pa.locator('#pedidos .muestra, #auditoria .muestra').count() === 0,
          `la marca "De muestra" en las ${de_muestra} cuentas de muestra y en Modulos; lo demas es real, sin marca`);
    t.chk(await pa.locator('header a.sesion-circulo').count() === 1 && await pa.locator('nav a.activo').count() === 0,
          'la cabecera con el circulo, y el menu propio sin entrada marcada');
    for (const id of ['pedidos', 'cuentas', 'modulos', 'auditoria']) t.chk(await pa.locator(`#${id}`).count() === 1, `la seccion #${id} existe, para su enlace`);
    for (const ancho of [390, 768, 1024]) for (const modo of ['dia', 'noche']) {
      const x = await nav.newContext({ viewport: { width: ancho, height: 900 } });
      await x.addCookies(await D.ctx.cookies());
      if (modo === 'noche') await x.addInitScript(() => { try { localStorage.setItem('stadion-tema', 'noche'); } catch (e) { /* sin almacenamiento */ } });
      const q = await x.newPage();
      await q.goto(ADMIN, { waitUntil: 'networkidle' });
      const d = await desborde(q);
      const botones = await q.locator('#pedidos .pedido', { hasText: O.correo }).locator('.resolver-pedido button')
        .evaluateAll(bs => bs.map(b => b.getBoundingClientRect().right <= document.documentElement.clientWidth));
      t.chk(d <= 0 && botones.length === 2 && botones.every(Boolean), `administracion a ${ancho} px de ${modo}: sin desborde, Aprobar y Rechazar a la vista`);
      await x.close();
    }
    await Promise.all([pa.waitForNavigation(), suyo().locator('button', { hasText: 'Rechazar' }).click()]);
    t.chk((await pa.textContent('main')).includes('queda rechazado') && await suyo().count() === 0, 'Rechazar: el aviso, y el pedido sale de la lista');
    t.chk((await pa.textContent('#auditoria')).includes('Rechazo de pedido de rol · Rol organizador para Odiseo de Ítaca'), 'el registro lo nombra en sustantivos');
    await pj.goto(PERFIL);
    t.chk((await pj.textContent('aside')).includes('queda rechazado el') && await pj.locator('form.pedir-rol').count() === 1,
          'Odiseo ve el rechazo, y puede pedirlo otra vez');
    await Promise.all([pj.waitForNavigation(), pj.click('form.pedir-rol button')]);
    await pa.goto(ADMIN);
    await Promise.all([pa.waitForNavigation(), suyo().locator('button', { hasText: 'Aprobar' }).click()]);
    t.chk((await pa.textContent('main')).includes('Odiseo de Ítaca queda con el rol de organizador'), 'Aprobar: el aviso');
    const registro = await pa.locator('#auditoria .registro-linea').allTextContents();
    t.chk(registro.some(l => l.includes('Aprobación de rol · Rol organizador para Odiseo de Ítaca'))
          && registro.some(l => l.includes('Pedido de rol · Rol organizador'))
          && registro.some(l => l.includes('Aprobación de rol · Primer administrador')),
          'en el registro: la aprobacion, el pedido y el primer administrador, en sustantivos');
    await pj.goto(PERFIL);
    t.chk(await pj.locator('.pedido-rol').count() === 0 && (await pj.textContent('aside')).includes('organizador'),
          'Odiseo, organizador: la tarjeta sin pedido, con el rol en la lista');
    await J.ctx.close();
    await D.ctx.close();

    // La tarjeta en su estado inicial, en el telefono: sin desborde y con
    // el foco a la vista.
    for (const modo of ['dia', 'noche']) {
      const x = await c.entrar(nav, N, { viewport: { width: 390, height: 900 } });
      if (modo === 'noche') await x.p.evaluate(() => { try { localStorage.setItem('stadion-tema', 'noche'); } catch (e) { /* sin almacenamiento */ } });
      await x.p.goto(PERFIL, { waitUntil: 'networkidle' });
      await x.p.focus('form.pedir-rol button');
      await x.p.keyboard.press('Shift+Tab');
      await x.p.keyboard.press('Tab');
      const f2 = await x.p.evaluate(() => { const s = getComputedStyle(document.activeElement); return document.activeElement.textContent.trim() + ' ' + s.outlineStyle; });
      t.chk(await desborde(x.p) <= 0 && /Pedir.*(solid|auto)/.test(f2), `el perfil a 390 px de ${modo}: sin desborde, y el foco a la vista en el boton (${f2})`);
      await x.ctx.close();
    }
  } catch (e) {
    console.error(e);
    t.chk(false, `la bateria corre entera (${e.message.split('\n')[0]})`);
  } finally {
    try {
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
