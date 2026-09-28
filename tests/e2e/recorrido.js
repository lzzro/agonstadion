// =====================================================================
// Recorrido de todas las paginas - Stadion (Agon)
// ---------------------------------------------------------------------
//   node tests/e2e/recorrido.js
//
//   0. el menu: en cada pagina con el menu compartido, y en cada ancho
//      (390 a 1920 px, con una ventana alta, que es donde sobra lugar), el
//      alto del <nav> es el mismo que en index.php (2 px de tolerancia), y
//      la linea de la pagina actual queda pegada al texto de su enlace.
//      (La administracion tiene su propio menu, y queda afuera.)
//   1. barrido: cada vista, en 390, 768 y 1024 px, de dia y de noche, sin
//      sesion y con ella: sin desborde a lo ancho, sin texto del color de
//      su fondo, sin respuestas con error (400 o mas) ni errores de
//      JavaScript; con sesion, el circulo en la cabecera
//   2. marcadores: ningun marcador ("2 – 0") se parte en dos renglones,
//      ni su tabla se sale de la tarjeta, y todos llevan cifras de altura
//      pareja y ancho fijo (lining-nums tabular-nums), en cinco anchos y
//      dos modos
//   3. enlaces: todo href, src y action interno de cada pagina responde
//      (menos de 400), sin sesion y con ella, y toda ancla #x existe en su
//      pagina. Un formulario se manda sin su token: responde 403 con el
//      aviso del token, que prueba que el controlador existe y corre sin
//      cambiar nada. Cerrar sesion no se manda (cortaria el recorrido).
//
// SOLO TOCA LO QUE ELLA MISMA CREA: una cuenta de @ejemplo.invalid, con
// contrasena al azar, administradora (sql/primer_administrador.sql) y
// organizadora, para abrir la administracion, el panel y "Nueva liga".
// Al final se borra (limpiarCuentas en comun.js) y se comprueba que la
// base (CHECKSUM TABLE) quedo como estaba.
// =====================================================================
'use strict';
const fs = require('fs');
const path = require('path');
const c = require('./comun');
const { BASE, sql } = c;

const PUBLICAS = ['index.php', 'torneos.php', 'torneo.php', 'torneo.php#resumen', 'torneo.php#calendario', 'torneo.php#posiciones',
                  'torneo.php#participantes', 'torneo.php#reglas', 'calendario.php', 'llave.php'];

let nav;
(async () => {
  const t = c.contador('Recorrido de todas las paginas');
  const antes = { huella: c.huellaBase() };
  const propias = [];
  nav = await c.navegador();
  try {
    const A = await c.cuentaNueva(nav, 'Teseo', 'Prueba', propias);
    sql(fs.readFileSync(path.join(c.RAIZ, 'sql', 'primer_administrador.sql'), 'utf8').replace(/CORREO_DE_LA_CUENTA/g, A.correo));
    c.darRol(A, 'organizador');
    const S = await c.entrar(nav, A);
    const sesion = await S.ctx.storageState();
    await S.ctx.close();
    t.chk(true, 'una cuenta nueva de @ejemplo.invalid, administradora y organizadora');

    // --- 0. El menu ---------------------------------------------------------------------
    console.log('--- 0. El menu: el mismo alto en todas las paginas ---');
    const CON_MENU = PUBLICAS.concat(['crear.php', 'panel.php', 'perfil.php']);
    const medirMenu = p => p.evaluate(() => {
      const n = document.querySelector('nav');
      const caja = n.getBoundingClientRect();
      // La entrada marcada que se ve (en torneo.php hay copias ocultas).
      const a = [...n.querySelectorAll('a.activo')].find(x => x.getClientRects().length > 0);
      let linea = null;
      if (a) {
        const r = document.createRange();
        r.selectNodeContents(a);
        const texto = [...r.getClientRects()].reduce((m, x) => Math.max(m, x.bottom), 0);
        linea = Math.round(a.getBoundingClientRect().bottom - texto);   // del pie del texto a la linea
      }
      return { alto: Math.round(caja.height), linea };
    });
    const menuMal = [];
    let menuVistas = 0;
    for (const ancho of [390, 768, 1024, 1440, 1920]) {
      const ctx = await nav.newContext({ viewport: { width: ancho, height: 1400 }, storageState: sesion });
      const p = await ctx.newPage();
      await p.goto(`${BASE}/index.php`, { waitUntil: 'networkidle' });
      const base = await medirMenu(p);
      for (const vista of CON_MENU) {
        await p.goto(`${BASE}/${vista}`, { waitUntil: 'networkidle' });
        const m = await medirMenu(p);
        menuVistas++;
        if (Math.abs(m.alto - base.alto) > 2) menuMal.push(`${vista} a ${ancho} px: el menu mide ${m.alto} px (en index.php, ${base.alto})`);
        if (m.linea !== null && m.linea > 12) menuMal.push(`${vista} a ${ancho} px: la linea de la pagina actual queda ${m.linea} px debajo del texto`);
      }
      await ctx.close();
    }
    t.chk(menuMal.length === 0, `el menu mide lo mismo que en index.php en ${menuVistas} combinaciones de pagina y ancho, con la linea pegada al texto`
          + (menuMal.length ? `:\n      ${menuMal.join('\n      ')}` : ''));

    // --- 1. Barrido ---------------------------------------------------------------------
    console.log('--- 1. Barrido: desborde, texto invisible y errores ---');
    for (const con_sesion of [false, true]) {
      const vistas = PUBLICAS.concat(con_sesion
        ? ['crear.php', 'panel.php', 'admin.php', 'perfil.php', 'perfil.php#mis-torneos', 'perfil.php#rendimiento']
        : ['crear.php', 'login.php', 'registro.php']);
      const malas = [];
      for (const vista of vistas) for (const ancho of [390, 768, 1024]) for (const modo of ['dia', 'noche']) {
        const ctx = await nav.newContext({ viewport: { width: ancho, height: 900 }, storageState: con_sesion ? sesion : undefined });
        if (modo === 'noche') await ctx.addInitScript(() => { try { localStorage.setItem('stadion-tema', 'noche'); } catch (e) { /* sin almacenamiento */ } });
        const p = await ctx.newPage();
        const errores = [];
        p.on('pageerror', e => errores.push('JS: ' + e.message));
        p.on('response', r => { if (r.status() >= 400 && !/fonts\.(googleapis|gstatic)/.test(r.url())) errores.push(`HTTP ${r.status()} ${r.url().replace(BASE, '')}`); });
        await p.goto(`${BASE}/${vista}`, { waitUntil: 'networkidle' });
        const m = await p.evaluate(() => ({
          desborde: document.documentElement.scrollWidth - document.documentElement.clientWidth,
          invisibles: [...document.querySelectorAll('h1, h2, h3, p, td, th, span, a, button, label, li')].filter(el => {
            const s = getComputedStyle(el);
            return s.color === s.backgroundColor && el.textContent.trim() !== '' && el.getBoundingClientRect().height > 0; }).length,
          noche: document.documentElement.getAttribute('data-theme') === 'noche',
          circulo: !!document.querySelector('header a.sesion-circulo') }));
        const mal = [];
        if (m.desborde > 0) mal.push(`desborde de ${m.desborde} px`);
        if (m.invisibles > 0) mal.push(`${m.invisibles} textos invisibles`);
        if (errores.length) mal.push(errores[0]);
        if ((modo === 'noche') !== m.noche) mal.push('el modo no es el pedido');
        if (con_sesion && !m.circulo) mal.push('sin el circulo de la sesion');
        if (mal.length) malas.push(`${vista} ${ancho} ${modo}: ${mal.join('; ')}`);
        await ctx.close();
      }
      t.chk(malas.length === 0, `${con_sesion ? 'con' : 'sin'} sesion: ${vistas.length} vistas × 3 anchos × 2 modos = ${vistas.length * 6} combinaciones, `
            + (malas.length ? `${malas.length} con problemas:\n      ${malas.join('\n      ')}` : 'todas sin desborde, sin texto invisible y sin errores'));
    }

    // --- 2. Marcadores --------------------------------------------------------------------
    console.log('--- 2. Ningun marcador se parte ---');
    const VISTAS_M = ['index.php', 'calendario.php', 'llave.php', 'torneo.php', 'torneo.php#calendario', 'torneo.php#posiciones',
                      'torneo.php#participantes', 'torneo.php#reglas'];
    let revisados = 0;
    const partidos = [];
    for (const vista of VISTAS_M) for (const ancho of [390, 768, 1024, 1250, 1440]) for (const modo of ['dia', 'noche']) {
      const ctx = await nav.newContext({ viewport: { width: ancho, height: 900 } });
      if (modo === 'noche') await ctx.addInitScript(() => { try { localStorage.setItem('stadion-tema', 'noche'); } catch (e) { /* sin almacenamiento */ } });
      const p = await ctx.newPage();
      await p.goto(`${BASE}/${vista}`, { waitUntil: 'networkidle' });
      const encontrados = await p.evaluate(() => {
        const renglones = el => { const r = document.createRange(); r.selectNodeContents(el);
          return new Set([...r.getClientRects()].filter(x => x.width > 0).map(x => Math.round(x.top))).size; };
        const visible = el => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
        const salida = [];
        document.querySelectorAll('td, span').forEach(el => {
          if (el.children.length || !visible(el)) return;
          const texto = el.textContent.trim();
          if (!/^\d+\s*[–—-]\s*\d+$/.test(texto)) return;
          const caja = el.closest('.tarjeta, .tabla-scroll, section') || document.body;
          const tabla = el.closest('table');
          const sale = (tabla && !el.closest('.tabla-scroll')) ? tabla.getBoundingClientRect().right - caja.getBoundingClientRect().right : 0;
          salida.push({ texto, renglones: renglones(el), donde: el.tagName.toLowerCase() + '.' + el.className, sale,
                        cifras: getComputedStyle(el).fontVariantNumeric });
        });
        return salida;
      });
      for (const x of encontrados) {
        revisados++;
        if (x.renglones > 1) partidos.push(`${vista} ${ancho} ${modo}: "${x.texto}" en ${x.donde} ocupa ${x.renglones} renglones`);
        if (x.sale > 1) partidos.push(`${vista} ${ancho} ${modo}: la tabla de "${x.texto}" se sale ${Math.round(x.sale)} px de su tarjeta`);
        if (!/lining-nums/.test(x.cifras) || !/tabular-nums/.test(x.cifras)) partidos.push(`${vista} ${ancho} ${modo}: "${x.texto}" en ${x.donde} no lleva cifras parejas de ancho fijo (${x.cifras})`);
      }
      await ctx.close();
    }
    t.chk(revisados > 0 && partidos.length === 0, `${revisados} marcadores revisados (${VISTAS_M.length} vistas × 5 anchos × 2 modos): `
          + (partidos.length ? `${partidos.length} con problemas:\n      ${partidos.join('\n      ')}` : 'ninguno se parte, todos con cifras parejas'));

    // --- 3. Enlaces --------------------------------------------------------------------------
    console.log('--- 3. Enlaces, recursos y formularios ---');
    for (const con_sesion of [false, true]) {
      const ctx = await nav.newContext({ storageState: con_sesion ? sesion : undefined });
      const p = await ctx.newPage();
      const paginas = ['index.php', 'torneos.php', 'torneo.php', 'calendario.php', 'llave.php', 'crear.php', 'panel.php', 'admin.php']
        .concat(con_sesion ? ['perfil.php'] : ['login.php', 'registro.php']);
      const vistos = new Set();
      const rotos = [];
      let total = 0;
      for (const pagina of paginas) {
        await p.goto(`${BASE}/${pagina}`, { waitUntil: 'networkidle' });
        const aca = p.url().split('#')[0];
        const refs = await p.evaluate(() => [...document.querySelectorAll('a[href], link[href], script[src], img[src], form[action]')]
          .map(e => ({ tag: e.tagName, v: e.getAttribute('href') || e.getAttribute('src') || e.getAttribute('action') })));
        const ids = await p.evaluate(() => [...document.querySelectorAll('[id]')].map(e => e.id));
        for (const ref of refs) {
          const u = new URL(ref.v, aca);
          if (u.origin !== new URL(BASE).origin) continue;   // las fuentes de Google
          total++;
          if (ref.v === '#' ) { rotos.push(`${pagina}: href="#"`); continue; }
          if (u.href.split('#')[0] === aca && u.hash) {
            if (!ids.includes(decodeURIComponent(u.hash.slice(1)))) rotos.push(`${pagina}: ${ref.v} (no hay ningun id asi)`);
            continue;
          }
          const clave = u.href.split('#')[0] + (ref.tag === 'FORM' ? ' POST' : '');
          if (vistos.has(clave)) continue;
          vistos.add(clave);
          if (ref.tag === 'FORM' && /salir/i.test(ref.v)) continue;
          const r = (ref.tag === 'FORM')
            ? await ctx.request.post(u.href.split('#')[0], { maxRedirects: 0, failOnStatusCode: false })
            : await ctx.request.get(u.href.split('#')[0], { failOnStatusCode: false });
          if (ref.tag === 'FORM' && r.status() === 403 && (await r.text()).includes('no corresponde a esta sesion')) continue;
          if (r.status() >= 400) rotos.push(`${pagina}: ${ref.tag} ${ref.v} → ${r.status()}`);
        }
      }
      if (con_sesion) {
        await p.goto(`${BASE}/index.php`);
        if (!(await p.$('header a.sesion-circulo'))) rotos.push('la sesion se perdio durante el recorrido');
      }
      t.chk(total > 0 && rotos.length === 0, `${con_sesion ? 'con' : 'sin'} sesion: ${total} enlaces, recursos y formularios de ${paginas.length} paginas, `
            + (rotos.length ? `${rotos.length} rotos:\n      ${rotos.join('\n      ')}` : 'ninguno roto'));
      await ctx.close();
    }
  } catch (e) {
    console.error(e);
    t.chk(false, `la bateria corre entera (${e.message.split('\n')[0]})`);
  } finally {
    try {
      c.limpiarCuentas(propias);
      t.chk(c.huellaBase() === antes.huella, 'al final, usuario, usuario_rol, pedido_rol y auditoria quedan iguales (CHECKSUM TABLE)');
    } catch (e) {
      t.chk(false, `la limpieza (${e.message.split('\n')[0]})`);
    }
    if (nav) await nav.close();
    t.fin();
  }
})();
