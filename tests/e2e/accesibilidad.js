// Accesibilidad de todas las vistas, con axe-core - Stadion (Agon)
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

// Un contexto de navegador para un modo: sin sesion, o con la sesion de la cuenta.
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

// El foco al principio del documento: el cuerpo lo recibe un instante y deja de ser enfocable.
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

  // 1. Sin sesion
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
    { vista: 'ayuda.php', abrir: ir('ayuda.php'), que: 'la ayuda, con las preguntas de los cinco bloques',
      es: async p => (await p.$$('main section h2')).length === 5 && (await p.$$('main h3')).length > 10 },
    { vista: 'terminos.php', abrir: ir('terminos.php'), que: 'los terminos, con la tabla de los datos',
      es: async p => (await textoDe(p, 'main h1')) === 'Términos y privacidad' && await hay(p, 'main .tabla-scroll[role="region"] table') },
    { vista: 'contacto.php', abrir: ir('contacto.php'), que: 'el contacto, con el correo',
      es: async p => await hay(p, 'main a[href^="mailto:"]') },
    { vista: 'login.php', abrir: ir('login.php'), que: 'el formulario de acceso',
      es: async p => await hay(p, 'main form input[name="correo"]') && await hay(p, 'main form input[name="password"]') },
    { vista: 'registro.php', abrir: ir('registro.php'), que: 'el formulario de alta',
      es: async p => await hay(p, 'form#alta input[name="correo"]') }
  ];
  for (const v of publicas) await revisar(t, anon, v);

  // 2. Las cuentas
  console.log('===== 2. Organizador =====');
  const org = await c.cuentaNueva(nav, 'Ofelia', 'Accesible');
  const jug = await c.cuentaNueva(nav, 'Jacinto', 'Capitán');
  c.darRol(org, 'organizador');
  t.chk(org.id > 0 && jug.id > 0, `dos cuentas nuevas (${org.id}, ${jug.id}), una con el rol de organizador`);
  const O = await c.entrar(nav, org);
  const J = await c.entrar(nav, jug);
  const orgs = { dia: O.ctx, noche: await contexto(nav, 'noche', org) };
  const jugs = { dia: J.ctx, noche: await contexto(nav, 'noche', jug) };

  await revisar(t, orgs, { vista: 'crear.php (liga nueva)', abrir: ir('crear.php'), que: 'el formulario "Nuevo torneo", sin avisos',
    es: async p => (await p.title()) === 'Nuevo torneo · Stadion' && await hay(p, 'main form input[name="nombre"]') && !(await hay(p, '[role="alert"]')) });

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
    es: async p => (await p.title()).startsWith('Aviso · Nuevo torneo') && await hay(p, '[role="alert"] a[href="#campo-nombre"]')
                   && (await textoDe(p, '#error-nombre')) === 'Ya hay una liga en juego con ese nombre.'
                   && await hay(p, '#campo-nombre[aria-invalid="true"]') });
  t.chk(sql(`SELECT COUNT(*) FROM torneo WHERE id_usuario_organizador = ${org.id}`) === '0', 'el formulario con errores no crea nada');

  await revisar(t, orgs, { vista: 'panel sin ligas', abrir: ir('panel.php'), que: 'el panel vacio: "Ningún torneo a cargo de esta cuenta."',
    es: async p => (await p.title()) === 'Panel del organizador · Stadion' && (await textoDe(p, 'main')).includes('Ningún torneo a cargo de esta cuenta.') });

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

  // 3. El jugador arma su equipo
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

  // 4. El panel con la liga
  console.log('===== 4. Panel con la liga =====');
  await revisar(t, orgs, { vista: 'panel (inscripcion abierta, un pedido)', abrir: ir('panel.php'),
    que: '4 de 8 equipos, "Anotar equipo", el pedido con Aceptar y Rechazar, y "Cerrar la inscripción"',
    es: async p => (await p.$$(`#liga-${id} .pedido`)).length === 1 && await hay(p, `#liga-${id} form.anotar-equipo`)
                   && await hay(p, `#liga-${id} button[aria-label="Aceptar el pedido de ${equipo}"]`)
                   && await hay(p, `#liga-${id} form.accion-liga input[value="cerrar"]`) });

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

  // 5. Despues del fixture
  console.log('===== 5. La liga en curso =====');
  await revisar(t, jugs, { vista: 'perfil#mis-torneos (equipo en una liga)', abrir: p => p.goto(`${perfil}#mis-torneos`),
    que: 'Mis torneos con la liga en la tabla, y el equipo',
    es: async p => (await vistasAbiertas(p)) === 'mis-torneos' && (await textoDe(p, '#mis-torneos table')).includes(liga)
                   && (await textoDe(p, '#mis-equipos')).includes(equipo) });
  await revisar(t, anon, { vista: `torneo.php?id=${id}#calendario (fixture con libre)`, abrir: ir(`torneo.php?id=${id}#calendario`),
    que: 'el fixture de la liga de la prueba, con el equipo libre de cada fecha',
    es: async p => (await vistasAbiertas(p)) === 'calendario' && (await textoDe(p, '#calendario')).includes('queda libre') });

  // 6. Fechas y resultados (fase 3)
  console.log('===== 6. Fechas y resultados =====');
  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} a.btn-primario`)]);
  const url_partidos = O.p.url().split('?')[0];
  await revisar(t, orgs, { vista: 'Fechas y resultados (fecha en juego)', abrir: p => p.goto(`${url_partidos}?id=${id}`),
    que: 'la fecha 1, con el resultado, el dia y lugar y el en vivo de cada partido, y el cierre apagado',
    es: async p => (await textoDe(p, '#fecha h2')) === 'Fecha 1' && (await p.$$('#fecha form.cargar-resultado')).length === 2
                   && await hay(p, '#fecha .enlace-apagado') });
  // Un resultado cargado, otro partido en vivo y programado.
  const cruces = sql(`SELECT e.id_enfrentamiento FROM enfrentamiento e JOIN ronda r USING (id_ronda)
                      WHERE r.id_torneo = ${id} AND r.numero = 1 AND e.id_participante_visitante IS NOT NULL ORDER BY e.numero`).split('\n');
  await O.p.goto(`${url_partidos}?id=${id}`);
  await O.p.fill(`#partido-${cruces[0]} input[name="local"]`, '3');
  await O.p.fill(`#partido-${cruces[0]} input[name="visitante"]`, '1');
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#partido-${cruces[0]} form.cargar-resultado button`)]);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#partido-${cruces[1]} form.accion-liga button`)]);
  await O.p.fill(`#partido-${cruces[1]} input[name="fecha_hora"]`, '2026-11-07T18:00');
  await O.p.fill(`#partido-${cruces[1]} input[name="lugar"]`, 'Cancha Ágora');
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#partido-${cruces[1]} form.programar-partido button`)]);
  const url_cargado = O.p.url();
  await revisar(t, orgs, { vista: 'Fechas y resultados (cargado, en vivo, programado)', abrir: p => p.goto(url_cargado),
    que: 'el aviso (role="status"), el marcador, el chip en vivo y el dia en palabras',
    es: async p => (await textoDe(p, '[role="status"]')) !== '' && (await textoDe(p, `#partido-${cruces[0]}`)).includes('Jugado · 3 – 1')
                   && await hay(p, `#partido-${cruces[1]} .estado-en-vivo`) && (await textoDe(p, `#partido-${cruces[1]}`)).includes('Cancha Ágora') });
  await revisar(t, orgs, { vista: 'Fechas y resultados (fecha por jugar)', abrir: p => p.goto(`${url_partidos}?id=${id}&fecha=2`),
    que: 'la fecha 2: dia y lugar, sin resultados ni cierre',
    es: async p => (await textoDe(p, '#fecha h2')) === 'Fecha 2' && !(await hay(p, '#fecha form.cargar-resultado'))
                   && await hay(p, '#fecha form.programar-partido') });
  await revisar(t, orgs, { vista: 'Fechas y resultados devuelto con un error', que: 'titulo "Aviso ·" y la lista de errores',
    abrir: async p => {
      await p.goto(`${url_partidos}?id=${id}`);
      await p.$eval(`#partido-${cruces[1]} input[name="lugar"]`, e => e.removeAttribute('maxlength'));
      await p.fill(`#partido-${cruces[1]} input[name="lugar"]`, 'x'.repeat(81));
      const [r] = await Promise.all([p.waitForNavigation(), p.click(`#partido-${cruces[1]} form.programar-partido button`)]);
      return r;
    },
    es: async p => (await p.title()).startsWith('Aviso · Fechas y resultados') && (await textoDe(p, '[role="alert"]')).includes('80 caracteres') });

  // 7. Una liga individual (fase 3)
  console.log('===== 7. Liga individual =====');
  const individual = `Liga Individual Accesible ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.fill('input[name="nombre"]', individual);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Ajedrez' });
  await O.p.check('#compite-jugadores');
  await O.p.fill('input[name="cupo"]', '8');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  const id_ind = idDe(individual);
  await revisar(t, jugs, { vista: 'liga individual con la inscripcion abierta', abrir: ir(`torneo.php?id=${id_ind}`),
    que: '"Inscribirse", con la nota de la inscripcion directa',
    es: async p => (await textoDe(p, 'h1')) === individual && await hay(p, 'form.pedir-lugar button') });
  await revisar(t, orgs, { vista: 'panel con una liga individual', abrir: ir('panel.php'),
    que: '"Jugadores anotados", sin "Anotar equipo", y "Cancelar la liga" con su casilla',
    es: async p => (await textoDe(p, `#liga-${id_ind}`)).includes('Jugadores anotados') && !(await hay(p, `#liga-${id_ind} form.anotar-equipo`))
                   && await hay(p, `#liga-${id_ind} form.cancelar-liga input[type="checkbox"]`) });

  // 8. Una eliminacion directa (fase 3)
  console.log('===== 8. Eliminacion directa =====');
  const copa = `Copa Accesible ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.check('#formato-eliminacion');
  await O.p.fill('input[name="nombre"]', copa);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Ajedrez' });
  await O.p.fill('input[name="cupo"]', '8');
  await O.p.check('#partido-unico');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  const id_copa = idDe(copa);
  for (const n of ['Uno', 'Dos', 'Tres', 'Cuatro', 'Cinco']) {
    await O.p.goto(`${BASE}/panel.php`);
    await O.p.fill(`#liga-${id_copa} input[name="equipo"]`, `${n} ${sufijo}`);
    await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id_copa} form.anotar-equipo button`)]);
  }
  for (let i = 0; i < 2; i++) {
    await O.p.goto(`${BASE}/panel.php`);
    await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id_copa} form.accion-liga button`)]);
  }
  await revisar(t, anon, { vista: 'torneo.php#posiciones (la llave)', abrir: ir(`torneo.php?id=${id_copa}#posiciones`),
    que: 'la pestana Llave, con tres rondas, pases libres y cruces por definir',
    es: async p => (await vistasAbiertas(p)) === 'posiciones' && (await p.$$('#posiciones .llave-ronda')).length === 3 });
  await revisar(t, anon, { vista: 'llave.php?id= (eliminacion directa)', abrir: ir(`llave.php?id=${id_copa}`),
    que: 'la llave del torneo, dibujada',
    es: async p => (await p.title()) === `Llave · ${copa} · Stadion` && (await p.$$('main .llave .cruce')).length === 7 });
  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id_copa} a.btn-primario`)]);
  const url_rondas = O.p.url();
  await revisar(t, orgs, { vista: 'Rondas y resultados (eliminacion directa)', abrir: p => p.goto(url_rondas),
    que: 'los cuartos, con los pases libres y "Cerrar la ronda" apagado',
    es: async p => (await textoDe(p, '#fecha h2')) === 'Cuartos de final' && (await textoDe(p, '#fecha')).includes('Pase libre') });

  // 9. Rendimiento fisico (RF-29)
  console.log('===== 9. Rendimiento fisico =====');
  sql(`INSERT INTO medicion (id_usuario, prueba, distancia_cm, tiempo_s, masa_kg, fecha) VALUES
       (${jug.id}, 'reaccion', 14.2, NULL, NULL, '2026-09-01'), (${jug.id}, 'reaccion', 11.1, NULL, NULL, '2026-09-15'),
       (${jug.id}, 'reaccion', 8.0, NULL, NULL, '2026-10-06'), (${jug.id}, 'sprint', NULL, 2.45, 68, '2026-09-02'),
       (${jug.id}, 'sprint', NULL, 2.30, 67.5, '2026-10-07'), (${jug.id}, 'salto', NULL, 0.45, 60, '2026-10-08')`);
  await revisar(t, jugs, { vista: 'perfil#rendimiento (con mediciones)', abrir: p => p.goto(`${perfil}#rendimiento`),
    que: 'los indicadores, dos graficos, la comparacion, los formularios y la tabla de mediciones',
    es: async p => (await vistasAbiertas(p)) === 'rendimiento' && (await p.$$('#rendimiento svg.grafico')).length === 2
                   && (await p.$$('#cargar-medicion form.medicion')).length === 3 && (await p.$$('#mis-mediciones tbody tr')).length === 6 });
  await revisar(t, jugs, { vista: 'perfil#rendimiento devuelto con un error', que: 'titulo "Aviso ·", el error al lado del formulario y los campos con aria-invalid',
    abrir: async p => {
      await p.goto(`${perfil}#rendimiento`);
      const f = '#cargar-medicion form.medicion:has(input[name="prueba"][value="reaccion"])';
      await p.$eval(f, e => { e.noValidate = true; });
      await p.fill(`${f} input[name="distancia"]`, '31');
      const [r] = await Promise.all([p.waitForNavigation(), p.click(`${f} button[type="submit"]`)]);
      return r;
    },
    es: async p => (await p.title()).startsWith('Aviso · Perfil') && (await vistasAbiertas(p)) === 'rendimiento'
                   && (await textoDe(p, '#error-medicion-reaccion')) === 'La regla mide 30 cm: la distancia va de 0,5 a 30.'
                   && await hay(p, 'input[name="distancia"][aria-invalid="true"][aria-describedby="error-medicion-reaccion"]') });
  t.chk(sql(`SELECT COUNT(*) FROM medicion WHERE id_usuario = ${jug.id}`) === '6', 'el formulario con errores no guarda nada');

  // 10. Reportes y administracion
  console.log('===== 10. Reportes y administracion =====');
  await O.p.goto(`${BASE}/panel.php`);
  const url_reportes = await O.p.$eval('main a.btn:has-text("Reportes")', a => a.href);
  await revisar(t, orgs, { vista: 'reportes (organizador)', abrir: p => p.goto(url_reportes),
    que: 'los totales, los dos graficos y el detalle de sus torneos',
    es: async p => (await p.title()) === 'Reportes · Stadion' && await hay(p, '#inscriptos svg[role="img"]') && await hay(p, '#detalle tbody tr') });
  const adm = await c.cuentaNueva(nav, 'Ariadna', 'Accesible');
  c.darRol(adm, 'administrador');
  const AD = await c.entrar(nav, adm);
  const admins = { dia: AD.ctx, noche: await contexto(nav, 'noche', adm) };
  const url_admin = await c.direccionDe(AD.p, 'admin.php');
  await revisar(t, admins, { vista: 'administracion', abrir: p => p.goto(url_admin),
    que: 'pedidos, cuentas con sus botones, modulos reales y el registro con su filtro',
    es: async p => (await p.title()) === 'Administración · Stadion' && (await p.$$('#modulos .modulo')).length === 3
                   && await hay(p, '#cuentas .acciones-cuenta') && await hay(p, '#auditoria select[name="filtro"]') });
  await revisar(t, admins, { vista: 'administracion devuelta con un error', que: 'titulo "Aviso ·" y la lista de errores',
    abrir: async p => {
      await p.goto(`${url_admin}#cuentas`);
      const fila = `#cuenta-${org.id}`;
      await p.$eval(`${fila} form.baja-cuenta`, f => { f.noValidate = true; });
      const [r] = await Promise.all([p.waitForNavigation(), p.click(`${fila} form.baja-cuenta button`)]);
      return r;
    },
    es: async p => (await p.title()).startsWith('Aviso ·') && (await textoDe(p, '[role="alert"]')).includes('Dar de baja una cuenta pide marcar la confirmación.') });
  t.chk(sql(`SELECT activo FROM usuario WHERE id_usuario = ${org.id}`) === '1', 'sin la casilla, la cuenta sigue activa');

  // Resumen
  console.log('===== Resumen =====');
  if (violaciones.length === 0) {
    console.log('  Violaciones de axe: ninguna.');
  } else {
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
