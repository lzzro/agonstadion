// =====================================================================
// Cabeceras de seguridad y cookie de la sesion - Stadion (Agon)
// (Ciberseguridad, tercera entrega; ver el informe OWASP)
// ---------------------------------------------------------------------
//   node tests/e2e/cabeceras.js
//
// Mira, sin cambiar nada del sitio:
//   1. las cabeceras de seguridad en las paginas, el CSS, el JS, una
//      direccion que no existe y un controlador: nosniff, X-Frame-Options,
//      Referrer-Policy, Permissions-Policy y la politica de contenido
//      (CSP); por http, sin HSTS
//   2. la cookie de la sesion: HttpOnly y SameSite=Lax, sin Secure por
//      http; un identificador inventado no se acepta (modo estricto) y al
//      iniciar sesion el identificador cambia (fijacion de sesion)
//   3. la politica de contenido no frena nada propio: con una cuenta
//      administradora y organizadora, ninguna vista da un aviso de la
//      politica, y tema.js corre (el modo noche cambia)
//   4. lo que la politica si frena: un script escrito dentro de la
//      pagina (lo que intentaria un XSS) no corre, y ninguna pagina se
//      deja mostrar dentro de otro sitio (clickjacking)
//   5. con STADION_HTTPS (la direccion de una copia del sitio servida
//      con un certificado propio, ver tests/README.md): la cookie con
//      Secure, y HSTS
//
// Crea una sola cuenta, y al final la borra y comprueba que la base
// (CHECKSUM TABLE) quedo como estaba.
// =====================================================================
'use strict';
const c = require('./comun');
const { BASE } = c;

const HTTPS = (process.env.STADION_HTTPS || '').replace(/\/$/, '');
if (HTTPS) {
  const anfitrion = new URL(HTTPS).hostname;
  if (!['127.0.0.1', 'localhost', '[::1]', '::1'].includes(anfitrion) && !anfitrion.endsWith('.local')) {
    console.error(`STADION_HTTPS tiene que ser la propia maquina, no ${anfitrion}.`);
    process.exit(2);
  }
}

// Las cabeceras que tiene que llevar toda respuesta del sitio.
const ESPERADAS = {
  'x-content-type-options': 'nosniff',
  'x-frame-options': 'DENY',
  'referrer-policy': 'strict-origin-when-cross-origin',
  'permissions-policy': 'camera=(), microphone=(), geolocation=()',
};
const CSP_PARTES = ["default-src 'self'", "script-src 'self'", "frame-ancestors 'none'", "form-action 'self'",
                    "object-src 'none'", "base-uri 'self'"];

function cabecerasBien(h) {
  const faltan = Object.keys(ESPERADAS).filter(k => h[k] !== ESPERADAS[k]);
  const csp = h['content-security-policy'] || '';
  CSP_PARTES.filter(p => !csp.includes(p)).forEach(p => faltan.push(`CSP: ${p}`));
  return faltan;
}

// Lo que el navegador anota cuando la politica frena algo.
const esAvisoCsp = texto => /Content Security Policy|Refused to (load|execute|apply|frame|connect|evaluate)/i.test(texto);

let nav;
(async () => {
  const t = c.contador('Cabeceras de seguridad y cookie de la sesion');
  const antes = c.huellaBase();
  const propias = [];
  nav = await c.navegador();
  try {
    const anon = await nav.newContext();
    const pedir = (url, opciones = {}) => anon.request.get(url, { maxRedirects: 0, ...opciones });

    // --- 1. Las cabeceras ------------------------------------------------------------
    console.log('--- 1. Las cabeceras ---');
    const A = await c.cuentaNueva(nav, 'Casandra', 'Troya', propias);
    c.darRol(A, 'administrador');
    c.darRol(A, 'organizador');
    const AD = await c.entrar(nav, A, { viewport: { width: 1024, height: 900 } });
    const PERFIL = await c.direccionDe(AD.p, 'perfil.php');
    const ADMIN = await c.direccionDe(AD.p, 'admin.php');
    const CREAR = await c.direccionDe(AD.p, 'crear.php');
    const PANEL = await c.direccionDe(AD.p, 'panel.php');
    const REPORTES = PANEL.replace(/[^/]*$/, '') + (PANEL.includes('/apps/controllers/') ? 'reportesController.php' : 'reportes.php');

    const direcciones = [
      ['index.php', 200], ['torneos.php', 200], ['torneo.php', 200], ['calendario.php', 200], ['llave.php', 200],
      ['login.php', 200], ['registro.php', 200], ['css/style.css', 200], ['js/tema.js', 200], ['no-existe.php', 404],
    ];
    for (const [ruta, estado] of direcciones) {
      const r = await pedir(`${BASE}/${ruta}`);
      const faltan = cabecerasBien(r.headers());
      t.chk(r.status() === estado && faltan.length === 0,
            `${ruta}: ${r.status()} con las cabeceras${faltan.length ? ' (faltan: ' + faltan.join(', ') + ')' : ''}`);
    }
    // Un controlador sin sesion: desvia al acceso, y el desvio tambien las
    // lleva (Header always).
    let r = await pedir(PERFIL);
    let faltan = cabecerasBien(r.headers());
    t.chk(r.status() === 302 && faltan.length === 0,
          `el perfil sin sesion: ${r.status()} al acceso, con las cabeceras${faltan.length ? ' (faltan: ' + faltan.join(', ') + ')' : ''}`);
    r = await pedir(`${BASE}/index.php`);
    t.chk(!('strict-transport-security' in r.headers()), 'por http no hay HSTS (solo vale con https)');

    // --- 2. La cookie de la sesion -----------------------------------------------------
    console.log('--- 2. La cookie de la sesion ---');
    // Un contexto nuevo, sin cookie: login.php abre la sesion y la manda.
    const limpio = await nav.newContext();
    r = await limpio.request.get(`${BASE}/login.php`, { maxRedirects: 0 });
    const cookie = r.headers()['set-cookie'] || '';
    await limpio.close();
    t.chk(/PHPSESSID=/.test(cookie) && /HttpOnly/i.test(cookie) && /SameSite=Lax/i.test(cookie) && /path=\//i.test(cookie)
          && !/;\s*secure/i.test(cookie), `login.php abre la sesion con HttpOnly y SameSite=Lax, sin Secure por http (${cookie})`);
    const inventado = 'a'.repeat(26);
    r = await anon.request.get(`${BASE}/login.php`, { maxRedirects: 0, headers: { Cookie: `PHPSESSID=${inventado}` } });
    const nuevo = ((r.headers()['set-cookie'] || '').match(/PHPSESSID=([^;]+)/) || [])[1] || '';
    t.chk(nuevo !== '' && nuevo !== inventado, 'un identificador de sesion inventado no se acepta: el sitio da otro');

    const ctx = await nav.newContext();
    const p = await ctx.newPage();
    await p.goto(`${BASE}/login.php`);
    const sesion = async () => ((await ctx.cookies()).find(x => x.name === 'PHPSESSID') || {});
    const antes_de_entrar = (await sesion()).value;
    await p.fill('input[name="correo"]', A.correo);
    await p.fill('input[name="password"]', A.clave);
    await Promise.all([p.waitForNavigation(), p.click('main form button[type="submit"]')]);
    const despues = await sesion();
    t.chk(antes_de_entrar && despues.value && despues.value !== antes_de_entrar,
          'al iniciar sesion el identificador cambia (sin fijacion de sesion)');
    t.chk(despues.httpOnly === true && despues.sameSite === 'Lax' && despues.secure === false,
          'en el navegador: httpOnly, SameSite Lax, y sin Secure por http');
    t.chk(await p.evaluate(() => document.cookie.includes('PHPSESSID')) === false,
          'un script de la pagina no puede leer la cookie de la sesion');
    await ctx.close();

    // --- 3. La politica no frena nada propio -----------------------------------------------
    console.log('--- 3. La politica no frena nada propio ---');
    const avisos = [];
    const errores = [];
    AD.p.on('console', m => { if (esAvisoCsp(m.text())) avisos.push(`${AD.p.url()}: ${m.text()}`); });
    AD.p.on('pageerror', e => errores.push(`${AD.p.url()}: ${e.message}`));
    const vistas = [`${BASE}/index.php`, `${BASE}/torneos.php`, `${BASE}/torneo.php`, `${BASE}/torneo.php#posiciones`,
                    `${BASE}/calendario.php`, `${BASE}/llave.php`, PERFIL, `${PERFIL}#mis-torneos`, `${PERFIL}#rendimiento`,
                    CREAR, PANEL, ADMIN, REPORTES];
    let abiertas = 0;
    for (const v of vistas) {
      // Pasar de una vista a otra de la misma pagina (#mis-torneos) no
      // pide nada al servidor: goto no devuelve respuesta, y vale.
      const resp = await AD.p.goto(v);
      if (resp === null ? v.includes('#') : resp.status() === 200) abiertas++;
    }
    const sinSesion = await nav.newContext();
    const ps = await sinSesion.newPage();
    ps.on('console', m => { if (esAvisoCsp(m.text())) avisos.push(`${ps.url()}: ${m.text()}`); });
    for (const v of ['login.php', 'registro.php']) {
      const resp = await ps.goto(`${BASE}/${v}`);
      if (resp && resp.status() === 200) abiertas++;
    }
    t.chk(abiertas === vistas.length + 2, `las ${vistas.length + 2} vistas abren (${abiertas})`);
    t.chk(avisos.length === 0, `ningun aviso de la politica de contenido${avisos.length ? ': ' + avisos.slice(0, 3).join(' | ') : ''}`);
    t.chk(errores.length === 0, `ningun error de JavaScript${errores.length ? ': ' + errores.slice(0, 3).join(' | ') : ''}`);
    await ps.goto(`${BASE}/index.php`);
    const tema_antes = await ps.evaluate(() => document.documentElement.getAttribute('data-theme'));
    await ps.click('button[aria-label="Modo noche"]');
    const tema_despues = await ps.evaluate(() => document.documentElement.getAttribute('data-theme'));
    t.chk(tema_antes !== 'noche' && tema_despues === 'noche', `tema.js corre: el interruptor pasa a modo noche (${tema_antes} -> ${tema_despues})`);

    // --- 4. Lo que la politica frena ------------------------------------------------------------
    console.log('--- 4. Lo que la politica frena ---');
    avisos.length = 0;
    const bloqueado = await ps.evaluate(() => new Promise(listo => {
      document.addEventListener('securitypolicyviolation', e => listo(e.violatedDirective), { once: true });
      const s = document.createElement('script');
      s.textContent = 'window.inyectado = true;';
      document.body.appendChild(s);
      setTimeout(() => listo(window.inyectado ? 'corrio' : 'sin aviso'), 1500);
    }));
    t.chk(bloqueado.startsWith('script-src') && await ps.evaluate(() => window.inyectado === undefined),
          `un script escrito dentro de la pagina no corre (frenado por ${bloqueado})`);
    const ajeno = await sinSesion.newPage();
    const avisos_marco = [];
    ajeno.on('console', m => { if (esAvisoCsp(m.text())) avisos_marco.push(m.text()); });
    await ajeno.setContent(`<h1>Otro sitio</h1><iframe src="${BASE}/index.php" width="600" height="400"></iframe>`);
    await ajeno.waitForTimeout(1500);
    const marco = ajeno.frames().find(f => f !== ajeno.mainFrame());
    const dentro = marco ? await marco.evaluate(() => document.body ? document.body.innerText : '').catch(() => '') : '';
    t.chk(!dentro.includes('STADION') && avisos_marco.some(x => /frame-ancestors|Refused to frame/i.test(x)),
          'ninguna pagina se muestra dentro de otro sitio (frame-ancestors)');
    await sinSesion.close();

    // --- 5. Con HTTPS ---------------------------------------------------------------------------
    if (HTTPS) {
      console.log(`--- 5. Con HTTPS (${HTTPS}) ---`);
      const seguro = await nav.newContext({ ignoreHTTPSErrors: true });
      r = await seguro.request.get(`${HTTPS}/login.php`, { maxRedirects: 0 });
      const h = r.headers();
      const ck = h['set-cookie'] || '';
      t.chk(r.status() === 200 && /;\s*secure/i.test(ck) && /HttpOnly/i.test(ck) && /SameSite=Lax/i.test(ck),
            `por https la cookie lleva Secure, HttpOnly y SameSite=Lax (${ck})`);
      t.chk(h['strict-transport-security'] === 'max-age=31536000', `por https llega HSTS (${h['strict-transport-security'] || 'nada'})`);
      faltan = cabecerasBien(h);
      t.chk(faltan.length === 0, `y las demas cabeceras${faltan.length ? ' (faltan: ' + faltan.join(', ') + ')' : ''}`);
      await seguro.close();
    } else {
      console.log('--- 5. Con HTTPS: sin STADION_HTTPS, no se prueba ---');
    }
    await anon.close();
  } finally {
    c.limpiarCuentas(propias);
  }
  t.chk(c.huellaBase() === antes, 'la base quedo como estaba (CHECKSUM TABLE de usuario, usuario_rol, pedido_rol, auditoria y medicion)');
  t.fin();
})().catch(e => { console.error(e); process.exitCode = 1; }).finally(async () => { if (nav) await nav.close(); });
