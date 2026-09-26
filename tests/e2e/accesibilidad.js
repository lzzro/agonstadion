// =====================================================================
// Accesibilidad de todas las vistas, con axe-core - Stadion (Agon)
// ---------------------------------------------------------------------
//   node tests/e2e/accesibilidad.js
//
// Contra la copia del hosting:
//   STADION_URL=http://127.0.0.1:8096 \
//   STADION_MYSQL="mysql --socket=/srv/m114/run/mysqld.sock sgdm" \
//   node tests/e2e/accesibilidad.js
//
// Pasa axe-core (el de NODE_PATH, junto a playwright-core; ver
// tests/README.md) por cada vista, con las reglas de WCAG 2.2 A y AA y
// las buenas practicas (etiquetas wcag2a, wcag2aa, wcag21a, wcag21aa,
// wcag22aa y best-practice), en 390 px (el telefono) y en 1024 px, de
// dia y de noche. En cada una de esas cuatro vueltas mira:
//   - el modo: de noche, <html data-theme="noche">, que pone tema.js
//     a partir de la preferencia guardada (la prueba la guarda antes de
//     abrir la pagina, como si se hubiera tocado el interruptor); de
//     dia, sin el atributo
//   - que la vista sea la que se quiere revisar (el titulo, la pestana
//     abierta, el formulario...), para no revisar otra sin darse cuenta
//   - ninguna violacion de axe; cada una sale con su regla, su selector
//     y la vista, y al final van todas juntas. Lo que axe deja "sin
//     decidir" (incomplete, casi siempre contraste sobre texto oculto o
//     dentro de un SVG) no es una falla: se cuenta al final, por regla
//   - que la pagina no se desplace de costado (scrollWidth del
//     documento <= clientWidth); si se desplaza, nombra lo que se sale
//   - que el primer Tab sea "Saltar al contenido", y que su destino se
//     vea. Con ancla (torneo.php#reglas, el perfil en #mis-torneos) el
//     navegador arranca el recorrido del teclado en el destino del
//     ancla, que es lo que corresponde: para medir desde arriba, antes
//     del Tab el foco vuelve al principio del documento (como al
//     entrar a la pagina desde la barra de direcciones), y el Tab tiene
//     que caer en la copia de "Saltar al contenido" de la vista abierta
//
// Las vistas:
//   sin sesion    inicio, torneos, torneo (sin ancla y sus cuatro
//                 pestanas por ancla), una liga con la inscripcion
//                 abierta, un torneo que no existe (404), calendario (la
//                 semana por defecto y ?semana=), llave, acceso y alta;
//                 y al final la liga de la prueba, con su fixture
//   organizador   el formulario de liga nueva, el mismo devuelto por el
//                 servidor con un error (un nombre que ya usa otra liga
//                 vigente: el navegador lo deja pasar), el panel vacio,
//                 el panel con una liga con la inscripcion abierta (4
//                 equipos anotados y un pedido pendiente de un capitan)
//                 y con la inscripcion cerrada y el fixture armado (con
//                 la casilla de rehacer)
//   jugador       el perfil en Mis torneos con su equipo armado (y
//                 despues con el equipo jugando la liga), el perfil con
//                 el aviso del rol de organizador (?aviso=organizador
//                 #roles), y la liga con la inscripcion abierta con el
//                 formulario "Pedir lugar"
//
// Las cuentas se crean en cada corrida (ver comun.js): correo de
// @ejemplo.invalid y contrasena al azar, solo en memoria. Otras pruebas
// pueden estar agregando ligas a la misma base: nada se cuenta fijo.
// =====================================================================
'use strict';
const axe = require('axe-core');
const c = require('./comun');
const { BASE, sql, texto, azar } = c;

const ANCHOS = [390, 1024];
const MODOS = ['dia', 'noche'];
const ETIQUETAS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

// Lo que se junta para el resumen del final.
const violaciones = [];      // { regla, impacto, ayuda, selector, donde }
const desbordes = [];        // { donde, culpables }
const sin_decidir = new Map(); // regla => casos

const idDe = nombre => parseInt(sql(`SELECT id_torneo FROM torneo WHERE nombre = ${texto(nombre)} ORDER BY id_torneo LIMIT 1`), 10);

// Un contexto de navegador para un modo: sin sesion, o con la sesion
// de la cuenta. De noche, la preferencia queda guardada antes de abrir
// cualquier pagina (tema.js la lee en el <head>).
async function contexto(nav, modo, cuenta) {
  let ctx;
  if (cuenta) {
    const s = await c.entrar(nav, cuenta);
    ctx = s.ctx;
    await s.p.close();
  } else {
    ctx = await nav.newContext();
  }
  if (modo === 'noche') {
    await ctx.addInitScript(() => { try { localStorage.setItem('stadion-tema', 'noche'); } catch (e) {} });
  }
  return ctx;
}

// --- Lo que se mide dentro de la pagina ---------------------------------
// Corren en el navegador (p.evaluate): no pueden usar nada de afuera.

// El ancho del documento y, si se sale, lo que lo empuja: los elementos
// que pasan el borde derecho sin un padre que los recorte o los
// desplace (una .tabla-scroll se desplaza adentro, y esta bien), solo
// los de mas afuera.
function medirDesborde() {
  const raiz = document.documentElement;
  const cw = raiz.clientWidth, sw = raiz.scrollWidth;
  const culpables = [];
  if (sw > cw) {
    // La etiqueta, el id y las clases; un fieldset, con su leyenda.
    const nombre = e => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + [...e.classList].map(k => '.' + k).join('')
      + (e.tagName === 'FIELDSET' && e.querySelector('legend') ? ` «${e.querySelector('legend').textContent.trim()}»` : '');
    const recortado = e => {
      for (let a = e.parentElement; a && a !== document.body; a = a.parentElement) {
        if (getComputedStyle(a).overflowX !== 'visible') return true;
      }
      return false;
    };
    const fuera = new Map();
    for (const e of document.body.querySelectorAll('*')) {
      const r = e.getBoundingClientRect();
      const derecha = r.right + window.scrollX;
      if ((r.width || r.height) && derecha > cw + 0.5 && !recortado(e)) fuera.set(e, derecha);
    }
    for (const [e, derecha] of fuera) {
      if (!fuera.has(e.parentElement)) culpables.push(`${nombre(e)} (hasta ${Math.round(derecha)} px)`);
    }
  }
  return { cw, sw, culpables: culpables.slice(0, 6) };
}

// El foco al principio del documento: el cuerpo lo recibe un instante
// y deja de ser enfocable. El Tab siguiente sale desde ahi.
function alPrincipio() {
  const b = document.body;
  b.setAttribute('tabindex', '-1');
  b.focus({ preventScroll: true });
  b.removeAttribute('tabindex');
}

// Lo que tiene el foco, y si su destino (un ancla) se ve.
function leerFoco() {
  const a = document.activeElement;
  const ve = e => { for (let x = e; x; x = x.parentElement) { if (getComputedStyle(x).display === 'none') return false; } return true; };
  const href = (a && a.getAttribute('href')) || '';
  const destino = href.startsWith('#') ? document.getElementById(href.slice(1)) : null;
  return { clase: a ? a.className : '', texto: a ? a.textContent.trim() : '', href, destino: !!destino && ve(destino) };
}

// Las vistas (.vista) que se ven, por id.
const vistasAbiertas = p => p.evaluate(() => {
  const ve = e => { for (let x = e; x; x = x.parentElement) { if (getComputedStyle(x).display === 'none') return false; } return true; };
  return [...document.querySelectorAll('.vista')].filter(ve).map(v => v.id).join(',');
});
const hay = async (p, selector) => (await p.$(selector)) !== null;
const textoDe = async (p, selector) => { const e = await p.$(selector); return e ? (await e.textContent()).replace(/\s+/g, ' ').trim() : ''; };

// --- Una vista, en los dos anchos y los dos modos -----------------------
//   ctxs    { dia, noche }: los contextos (sin sesion, o con la cuenta)
//   v.vista el nombre que sale en cada renglon
//   v.abrir (p) => la respuesta de la pagina (un goto, o un envio)
//   v.que   lo que dice que es la vista buscada, y v.es (p, r) lo mira
async function revisar(t, ctxs, v) {
  console.log(`--- ${v.vista} ---`);
  for (const modo of MODOS) {
    for (const ancho of ANCHOS) {
      const donde = `${v.vista} · ${ancho} px · ${modo === 'noche' ? 'noche' : 'día'}`;
      const p = await ctxs[modo].newPage();
      try {
        await p.setViewportSize({ width: ancho, height: 900 });
        const respuesta = await v.abrir(p);

        const tema = await p.evaluate(() => document.documentElement.getAttribute('data-theme'));
        t.chk(modo === 'noche' ? tema === 'noche' : tema === null,
              `${donde}: ${modo === 'noche' ? '<html data-theme="noche">' : 'modo día, sin data-theme'}${(modo === 'noche') !== (tema === 'noche') ? ` (data-theme=${tema})` : ''}`);
        t.chk(await v.es(p, respuesta), `${donde}: ${v.que}`);

        const d = await p.evaluate(medirDesborde);
        if (d.sw > d.cw) desbordes.push({ donde, culpables: d.culpables });
        t.chk(d.sw <= d.cw, `${donde}: no se desplaza de costado (${d.sw} de ${d.cw} px)`
              + (d.culpables.length ? `\n          se sale: ${d.culpables.join(' · ')}` : ''));

        await p.evaluate(axe.source);
        const r = await p.evaluate(async (etiquetas) => {
          const res = await window.axe.run(document, { runOnly: { type: 'tag', values: etiquetas }, resultTypes: ['violations', 'incomplete'] });
          return {
            violaciones: res.violations.map(x => ({ regla: x.id, impacto: x.impact, ayuda: x.help,
              nodos: x.nodes.map(n => n.target.map(s => Array.isArray(s) ? s.join(' >>> ') : s).join(' ')) })),
            sin_decidir: res.incomplete.map(x => ({ regla: x.id, casos: x.nodes.length }))
          };
        }, ETIQUETAS);
        const lineas = [];
        for (const x of r.violaciones) {
          for (const selector of x.nodos) {
            violaciones.push({ regla: x.regla, impacto: x.impacto, ayuda: x.ayuda, selector, donde });
            lineas.push(`${x.regla} (${x.impacto}) en ${selector}: ${x.ayuda}`);
          }
        }
        for (const x of r.sin_decidir) sin_decidir.set(x.regla, (sin_decidir.get(x.regla) || 0) + x.casos);
        t.chk(lineas.length === 0, `${donde}: axe-core, sin violaciones`
              + (lineas.length ? lineas.map(l => `\n          ${l}`).join('') : ''));

        if (p.url().includes('#')) await p.evaluate(alPrincipio);
        await p.keyboard.press('Tab');
        const foco = await p.evaluate(leerFoco);
        t.chk(foco.clase === 'saltar' && foco.texto === 'Saltar al contenido' && foco.destino,
              `${donde}: el primer Tab es "Saltar al contenido" y su destino se ve (${foco.clase}|${foco.texto.slice(0, 40)}|${foco.href})`);
      } catch (e) {
        t.chk(false, `${donde}: la vista se abre y se revisa (${e.message.split('\n')[0]})`);
      } finally {
        await p.close();
      }
    }
  }
}

let nav = null;
(async () => {
  const t = c.contador('Accesibilidad: axe-core, desborde y teclado');
  nav = await c.navegador();
  const sufijo = azar();
  t.chk(axe.version === '4.13.0' || axe.version.startsWith('4.13.'), `axe-core ${axe.version} (se pide la 4.13)`);

  const S = idDe('Liga Interna Club Sur');
  t.chk(S > 0 && sql(`SELECT estado FROM torneo WHERE id_torneo = ${S}`) === 'inscripcion',
        `la Liga Interna Club Sur esta en la base, con la inscripcion abierta (${S})`);

  // --- 1. Sin sesion ------------------------------------------------------
  console.log('===== 1. Sin sesion =====');
  const anon = { dia: await contexto(nav, 'dia'), noche: await contexto(nav, 'noche') };
  const ir = url => p => p.goto(`${BASE}/${url}`);
  const pestanas = ['calendario', 'posiciones', 'participantes', 'reglas'];
  const publicas = [
    { vista: 'index.php', abrir: ir('index.php'), que: 'el inicio, con sus tres numeros',
      es: async p => (await p.$$('main .datos > div')).length === 3 },
    { vista: 'torneos.php', abrir: ir('torneos.php'), que: 'la lista de torneos, con sus tarjetas',
      es: async p => (await p.$$('main article.tarjeta')).length > 0 },
    { vista: 'torneo.php', abrir: ir('torneo.php'), que: 'la liga destacada, en Resumen',
      es: async p => (await textoDe(p, 'h1')) !== '' && (await vistasAbiertas(p)) === 'resumen' },
    ...pestanas.map(x => ({ vista: `torneo.php#${x}`, abrir: ir(`torneo.php#${x}`), que: `se ve solo la pestana ${x}`,
      es: async p => (await vistasAbiertas(p)) === x })),
    { vista: `torneo.php?id=${S} (inscripcion abierta)`, abrir: ir(`torneo.php?id=${S}`),
      que: 'la Liga Interna, de muestra: "Pedir lugar" apagado, con el porque',
      es: async p => (await textoDe(p, 'h1')) === 'Liga Interna Club Sur' && await hay(p, '.pedir-lugar .enlace-apagado[aria-disabled="true"]')
                     && (await textoDe(p, '.pedir-lugar')).includes('Una liga de muestra no recibe pedidos.') },
    { vista: 'torneo.php?id=999999 (404)', abrir: ir('torneo.php?id=999999'), que: '404, "Sin torneo con ese número"',
      es: async (p, r) => r.status() === 404 && (await textoDe(p, 'h1')) === 'Sin torneo con ese número' },
    { vista: 'calendario.php', abrir: ir('calendario.php'), que: 'la semana del partido en vivo',
      es: async p => (await textoDe(p, 'main h1')).startsWith('Semana del') && (await p.$$('.agenda .partido')).length > 0 },
    { vista: 'calendario.php?semana=2026-09-26', abrir: ir('calendario.php?semana=2026-09-26'), que: 'la semana del 21 al 27 de septiembre',
      es: async p => (await textoDe(p, 'main h1')) === 'Semana del 21 al 27 de septiembre' },
    { vista: 'llave.php', abrir: ir('llave.php'), que: 'la lista de ligas, cada una con su tabla',
      es: async p => (await p.$$('main .lista-apilada li')).length > 0 },
    { vista: 'login.php', abrir: ir('login.php'), que: 'el formulario de acceso',
      es: async p => await hay(p, 'main form input[name="correo"]') && await hay(p, 'main form input[name="password"]') },
    { vista: 'registro.php', abrir: ir('registro.php'), que: 'el formulario de alta',
      es: async p => await hay(p, 'form#alta input[name="correo"]') }
  ];
  for (const v of publicas) await revisar(t, anon, v);

  // --- 2. Las cuentas -------------------------------------------------------
  console.log('===== 2. Organizador =====');
  const org = await c.cuentaNueva(nav, 'Ofelia', 'Accesible');
  const jug = await c.cuentaNueva(nav, 'Jacinto', 'Capitán');
  c.darRol(org, 'organizador');
  t.chk(org.id > 0 && jug.id > 0, `dos cuentas nuevas (${org.id}, ${jug.id}), una con el rol de organizador`);
  const O = await c.entrar(nav, org);
  const J = await c.entrar(nav, jug);
  const orgs = { dia: O.ctx, noche: await contexto(nav, 'noche', org) };
  const jugs = { dia: J.ctx, noche: await contexto(nav, 'noche', jug) };

  await revisar(t, orgs, { vista: 'crear.php (liga nueva)', abrir: ir('crear.php'), que: 'el formulario "Nueva liga", sin avisos',
    es: async p => (await p.title()) === 'Nueva liga · Stadion' && await hay(p, 'main form input[name="nombre"]') && !(await hay(p, '[role="alert"]')) });

  // El formulario devuelto con un error del servidor: todo lo que el
  // navegador acepta, pero el nombre es el de otra liga vigente.
  await revisar(t, orgs, { vista: 'crear.php devuelto con errores', que: 'titulo "Aviso ·", el error al lado del nombre y el campo con aria-invalid',
    abrir: async p => {
      await p.goto(`${BASE}/crear.php`);
      await p.fill('input[name="nombre"]', 'Liga Interna Club Sur');
      await p.selectOption('select[name="disciplina"]', { label: 'Fútbol 5' });
      await p.fill('input[name="cupo"]', '8');
      await p.check('#vueltas-una');
      const [r] = await Promise.all([p.waitForNavigation(), p.click('main form button[type="submit"]')]);
      return r;
    },
    es: async p => (await p.title()).startsWith('Aviso · Nueva liga') && await hay(p, '[role="alert"] a[href="#campo-nombre"]')
                   && (await textoDe(p, '#error-nombre')) === 'Ya hay una liga en juego con ese nombre.'
                   && await hay(p, '#campo-nombre[aria-invalid="true"]') });
  t.chk(sql(`SELECT COUNT(*) FROM torneo WHERE id_usuario_organizador = ${org.id}`) === '0', 'el formulario con errores no crea nada');

  await revisar(t, orgs, { vista: 'panel sin ligas', abrir: ir('panel.php'), que: 'el panel vacio: "Ninguna liga a cargo de esta cuenta."',
    es: async p => (await p.title()) === 'Panel del organizador · Stadion' && (await textoDe(p, 'main')).includes('Ninguna liga a cargo de esta cuenta.') });

  // Una liga con la inscripcion abierta y 4 equipos anotados a mano.
  const liga = `Liga Accesible ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.fill('input[name="nombre"]', liga);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Fútbol 5' });
  await O.p.fill('input[name="cupo"]', '8');
  await O.p.check('#vueltas-una');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  const id = idDe(liga);
  t.chk(id > 0 && sql(`SELECT CONCAT(estado, '|', id_usuario_organizador) FROM torneo WHERE id_torneo = ${id}`) === `inscripcion|${org.id}`,
        `la liga de la prueba queda creada, con la inscripcion abierta (${id})`);
  for (const letra of ['A', 'B', 'C', 'D']) {
    await O.p.goto(`${BASE}/panel.php`);
    await O.p.fill(`#liga-${id} input[name="equipo"]`, `Equipo ${letra} ${sufijo}`);
    await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} form.anotar-equipo button`)]);
  }
  t.chk(sql(`SELECT COUNT(*) FROM participante WHERE id_torneo = ${id}`) === '4', 'cuatro equipos anotados a mano');
  await revisar(t, anon, { vista: `torneo.php?id=${id} (sin sesion)`, abrir: ir(`torneo.php?id=${id}`),
    que: 'la liga de la prueba, con "Iniciar sesión para pedir lugar"',
    es: async p => (await textoDe(p, 'h1')) === liga && (await textoDe(p, '.pedir-lugar a')) === 'Iniciar sesión para pedir lugar' });

  // --- 3. El jugador arma su equipo -------------------------------------------
  console.log('===== 3. Jugador =====');
  const equipo = `Los Contrastes ${sufijo}`;
  const perfil = await c.direccionDe(J.p, 'perfil.php');
  await J.p.goto(`${perfil}#mis-torneos`);
  await J.p.fill('input[name="nombre_equipo"]', equipo);
  await J.p.fill('input[name="ciudad_equipo"]', 'Florida');
  await Promise.all([J.p.waitForNavigation(), J.p.click('form.crear-equipo button')]);
  t.chk(sql(`SELECT id_usuario_capitan FROM equipo WHERE nombre = ${texto(equipo)}`) === String(jug.id), 'el jugador arma su equipo, de capitan');

  await revisar(t, jugs, { vista: 'perfil#mis-torneos (equipo armado)', abrir: p => p.goto(`${perfil}#mis-torneos`),
    que: 'se ve Mis torneos, con el equipo en "Mis equipos"',
    es: async p => (await vistasAbiertas(p)) === 'mis-torneos' && (await textoDe(p, '#mis-equipos')).includes(equipo) });

  await J.p.goto(`${BASE}/crear.php`);
  const url_roles = J.p.url();
  t.chk(/aviso=organizador/.test(url_roles) && url_roles.endsWith('#roles'), `sin el rol, crear.php lleva al perfil con el aviso (${url_roles.replace(BASE, '')})`);
  await revisar(t, jugs, { vista: 'perfil?aviso=organizador#roles', abrir: p => p.goto(url_roles),
    que: 'titulo "Aviso ·", el aviso al lado de "Pedir el rol de organizador", en Datos',
    es: async p => (await p.title()).startsWith('Aviso ·') && (await vistasAbiertas(p)) === 'datos'
                   && await hay(p, '#roles .aviso-rol[role="alert"]') && await hay(p, '#roles form.pedir-rol button') });

  await revisar(t, jugs, { vista: `torneo.php?id=${id} ("Pedir lugar")`, abrir: ir(`torneo.php?id=${id}`),
    que: 'la liga de la prueba, con el formulario "Pedir lugar" y el equipo para elegir',
    es: async p => (await textoDe(p, 'h1')) === liga && await hay(p, 'form.pedir-lugar select[name="id_equipo"]')
                   && (await textoDe(p, 'form.pedir-lugar button')).startsWith('Pedir lugar') });

  await J.p.goto(`${BASE}/torneo.php?id=${id}`);
  await Promise.all([J.p.waitForNavigation(), J.p.click('form.pedir-lugar button')]);
  t.chk(sql(`SELECT COUNT(*) FROM pedido_inscripcion pi JOIN equipo e USING (id_equipo)
             WHERE pi.id_torneo = ${id} AND e.nombre = ${texto(equipo)} AND pi.estado = 'pendiente'`) === '1', 'el capitan pide lugar: un pedido pendiente');

  // --- 4. El panel con la liga ----------------------------------------------------
  console.log('===== 4. Panel con la liga =====');
  await revisar(t, orgs, { vista: 'panel (inscripcion abierta, un pedido)', abrir: ir('panel.php'),
    que: '4 de 8 equipos, "Anotar equipo", el pedido con Aceptar y Rechazar, y "Cerrar la inscripción"',
    es: async p => (await p.$$(`#liga-${id} .pedido`)).length === 1 && await hay(p, `#liga-${id} form.anotar-equipo`)
                   && await hay(p, `#liga-${id} button[aria-label="Aceptar el pedido de ${equipo}"]`)
                   && await hay(p, `#liga-${id} form.accion-liga input[value="cerrar"]`) });

  // Se acepta el pedido (asi el equipo del jugador juega la liga), se
  // cierra la inscripcion y se arma el fixture.
  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} button[aria-label="Aceptar el pedido de ${equipo}"]`)]);
  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} form.accion-liga button`)]);
  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} form.accion-liga button`)]);
  const url_fixture = O.p.url();
  t.chk(url_fixture.includes('aviso=fixture-armado') && sql(`SELECT CONCAT(t.estado, '|', COUNT(r.id_ronda)) FROM torneo t LEFT JOIN ronda r USING (id_torneo)
        WHERE t.id_torneo = ${id} GROUP BY t.id_torneo`) === 'en_curso|5', 'pedido aceptado, inscripcion cerrada y fixture armado: 5 equipos, 5 fechas');

  await revisar(t, orgs, { vista: 'panel (fixture armado, recien)', abrir: p => p.goto(url_fixture),
    que: 'el aviso del fixture (role="status") y la casilla para rehacerlo',
    es: async p => (await textoDe(p, '[role="status"]')) !== '' && await hay(p, `#liga-${id} input[name="confirmo"]`) });
  await revisar(t, orgs, { vista: 'panel (inscripcion cerrada, fixture armado)', abrir: ir('panel.php'),
    que: '"Ver el fixture" y la casilla de rehacer, sin "Anotar equipo"',
    es: async p => await hay(p, `#liga-${id} input[type="checkbox"][name="confirmo"]`) && !(await hay(p, `#liga-${id} form.anotar-equipo`))
                   && (await textoDe(p, `#liga-${id}`)).includes('5 fechas armadas') });

  // --- 5. Despues del fixture -------------------------------------------------------
  console.log('===== 5. La liga en curso =====');
  await revisar(t, jugs, { vista: 'perfil#mis-torneos (equipo en una liga)', abrir: p => p.goto(`${perfil}#mis-torneos`),
    que: 'Mis torneos con la liga en la tabla, y el equipo',
    es: async p => (await vistasAbiertas(p)) === 'mis-torneos' && (await textoDe(p, '#mis-torneos table')).includes(liga)
                   && (await textoDe(p, '#mis-equipos')).includes(equipo) });
  await revisar(t, anon, { vista: `torneo.php?id=${id}#calendario (fixture con libre)`, abrir: ir(`torneo.php?id=${id}#calendario`),
    que: 'el fixture de la liga de la prueba, con el equipo libre de cada fecha',
    es: async p => (await vistasAbiertas(p)) === 'calendario' && (await textoDe(p, '#calendario')).includes('queda libre') });

  // --- Resumen ------------------------------------------------------------------------
  console.log('===== Resumen =====');
  if (violaciones.length === 0) {
    console.log('  Violaciones de axe: ninguna.');
  } else {
    // Agrupadas por regla y selector, sin el numero de orden de los
    // renglones (:nth-child): el mismo elemento repetido es un caso.
    const grupos = new Map();
    for (const x of violaciones) {
      const k = `${x.regla} (${x.impacto}) · ${x.selector.replace(/:nth-child\(\d+\)/g, ':nth-child(…)')}`;
      if (!grupos.has(k)) grupos.set(k, { ayuda: x.ayuda, casos: 0, donde: new Set() });
      grupos.get(k).casos++;
      grupos.get(k).donde.add(x.donde);
    }
    console.log(`  Violaciones de axe: ${violaciones.length}, en ${grupos.size} reglas y selectores distintos:`);
    for (const [k, g] of grupos) console.log(`    ${k}: ${g.ayuda} (${g.casos} casos)\n      en ${[...g.donde].join('; ')}`);
  }
  if (desbordes.length === 0) {
    console.log('  Desbordes a lo ancho: ninguno.');
  } else {
    console.log(`  Desbordes a lo ancho: ${desbordes.length}:`);
    for (const x of desbordes) console.log(`    ${x.donde}: ${x.culpables.join(' · ') || '(sin culpable a la vista)'}`);
  }
  console.log(`  Sin decidir por axe (no son fallas): ${sin_decidir.size === 0 ? 'nada'
              : [...sin_decidir].map(([regla, n]) => `${regla} ${n}`).join(', ')}`);

  await nav.close();
  t.fin();
})().catch(async e => { console.error(e); process.exitCode = 1; if (nav) await nav.close(); });
