// =====================================================================
// Reportes, mis resultados e integrantes (tercera entrega) - Stadion
// ---------------------------------------------------------------------
//   node tests/e2e/reportes.js
//
// Con cuentas nuevas en cada corrida (ver comun.js), mira en la
// pantalla Y en la base:
//   1. una eliminacion directa individual de 4 con su sede (RF-13),
//      jugada hasta la final con clics
//   2. "Mis resultados" de cada jugador (RF-26): sus partidos con el
//      marcador visto desde su lado, y las Finales y los Kotinos reales
//   3. los reportes (RF-28): los de la organizadora (sus torneos y los
//      numeros), los de otra organizadora (ninguno), los de la
//      administracion (todos, con quien organiza), y sin el rol
//   4. los integrantes con su dorsal (RF-12) y una liga con dos
//      clasificados a playoffs (RF-14): el integrante ve el equipo y los
//      partidos del equipo en "Mis resultados"
// Deja rastro, como ligas.js.
// =====================================================================
'use strict';
const c = require('./comun');
const { BASE, sql, texto, azar } = c;

let nav = null;
(async () => {
  const t = c.contador('Reportes, mis resultados e integrantes');
  nav = await c.navegador();
  const sufijo = azar();

  const org = await c.cuentaNueva(nav, 'Atalanta', 'Arcadia');
  const org2 = await c.cuentaNueva(nav, 'Penélope', 'Ítaca');
  const adm = await c.cuentaNueva(nav, 'Ariadna', 'Minos');
  c.darRol(org, 'organizador');
  c.darRol(org2, 'organizador');
  c.darRol(adm, 'administrador');
  const O = await c.entrar(nav, org, { viewport: { width: 1024, height: 900 } });
  const url_panel = await c.direccionDe(O.p, 'panel.php');
  const tko = await c.token(O.p);

  // --- 1. Una eliminacion directa individual, con su sede --------------
  console.log('--- 1. La copa, con su sede ---');
  const copa = `Copa de Troya ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.check('#formato-eliminacion');
  await O.p.fill('input[name="nombre"]', copa);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Ajedrez' });
  await O.p.check('#compite-jugadores');
  await O.p.fill('input[name="cupo"]', '4');
  await O.p.fill('input[name="sede"]', 'Club de Ajedrez del Cerro');
  await O.p.check('#partido-unico');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  const idc = parseInt(sql(`SELECT id_torneo FROM torneo WHERE nombre = ${texto(copa)}`), 10);
  t.chk(sql(`SELECT sede FROM torneo WHERE id_torneo = ${idc}`) === 'Club de Ajedrez del Cerro', 'la sede queda guardada (RF-13)');
  const jug = {};
  for (const [n, a] of [['Aquiles', 'Ftía'], ['Héctor', 'Troya'], ['Ayax', 'Salamina'], ['Paris', 'Ilión']]) {
    jug[n] = await c.cuentaNueva(nav, n, `${a} ${sufijo}`);
    const J = await c.entrar(nav, jug[n]);
    await J.p.goto(`${BASE}/torneo.php?id=${idc}`);
    await Promise.all([J.p.waitForNavigation(), J.p.click('form.pedir-lugar button')]);
    await J.ctx.close();
  }
  const P = await (await nav.newContext()).newPage();
  await P.goto(`${BASE}/torneo.php?id=${idc}`);
  t.chk((await P.textContent('main .intro')).includes('Club de Ajedrez del Cerro'), 'la pagina del torneo muestra la sede');
  await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'cerrar', id_torneo: String(idc) });
  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${idc} form.accion-liga button`)]);
  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${idc} a.btn-primario`)]);
  const url_partidos = O.p.url().split('?')[0];
  // Gana siempre el local: el sembrado 1 (Aquiles) gana la copa, y el 2
  // (Hector) llega a la final.
  for (let ronda = 0; ronda < 2; ronda++) {
    await O.p.goto(`${url_partidos}?id=${idc}`);
    const ids = await O.p.$$eval('#fecha form.cargar-resultado input[name="id_enfrentamiento"]', l => l.map(e => e.value));
    for (const id of ids) {
      await O.p.fill(`#partido-${id} input[name="local"]`, '1');
      await O.p.fill(`#partido-${id} input[name="visitante"]`, '0');
      await Promise.all([O.p.waitForNavigation(), O.p.click(`#partido-${id} form.cargar-resultado button`)]);
    }
    await Promise.all([O.p.waitForNavigation(), O.p.click('#fecha form.accion-liga input[value="cerrar_fecha"] ~ button')]);
  }
  t.chk(sql(`SELECT estado FROM torneo WHERE id_torneo = ${idc}`) === 'finalizado', 'semifinales y final jugadas: la copa termina');

  // --- 2. Mis resultados (RF-26) ----------------------------------------
  console.log('--- 2. Mis resultados ---');
  const verPerfil = async (cuenta) => {
    const J = await c.entrar(nav, cuenta);
    await J.p.goto(`${BASE}/perfil.php#mis-torneos`);
    const datos = await J.p.evaluate(() => ({
      stats: [...document.querySelectorAll('.datos > div')].map(d => d.querySelector('.etiqueta').textContent + ':' + d.querySelector('strong').textContent
                                                                    + (d.querySelector('.muestra') ? ':muestra' : '')),
      resumen: (document.querySelector('#mis-resultados > p') || {}).textContent || '',
      filas: [...document.querySelectorAll('#mis-resultados tbody tr')].map(tr => [...tr.querySelectorAll('td')].slice(1).map(td => td.innerText.replace(/\s+/g, ' ').trim()).join(' | ')),
      region: !!document.querySelector('#mis-resultados .tabla-scroll[role="region"][tabindex="0"][aria-label="Mis resultados"]') }));
    await J.ctx.close();
    return datos;
  };
  const aquiles = await verPerfil(jug.Aquiles);
  t.chk(aquiles.stats.join(' ') === 'Torneos:1 Finales:1 Kotinos · victorias:1', `Aquiles, campeon: ${aquiles.stats.join(', ')} (ya sin "De muestra")`);
  t.chk(aquiles.filas.length === 2 && aquiles.filas[0] === `${copa} Final | Héctor Troya ${sufijo} | 1 – 0 | Victoria`
        && aquiles.filas[1].startsWith(`${copa} Semifinal | Paris Ilión ${sufijo} | 1 – 0 | Victoria`),
        `sus dos partidos, la final primero (${aquiles.filas[0]})`);
  t.chk(aquiles.resumen.trim() === '2 partidos jugados · 2 ganados · 0 empatados · 0 perdidos' && aquiles.region, `el resumen: "${aquiles.resumen.trim()}", y la tabla desplazable`);
  const hector = await verPerfil(jug['Héctor']);
  t.chk(hector.stats.join(' ') === 'Torneos:1 Finales:1 Kotinos · victorias:0' && hector.filas[0] === `${copa} Final | Aquiles Ftía ${sufijo} | 0 – 1 | Derrota`,
        'Hector, finalista: una final, ningun kotino, y el marcador desde su lado (0 – 1)');
  const paris = await verPerfil(jug.Paris);
  t.chk(paris.stats.join(' ') === 'Torneos:1 Finales:0 Kotinos · victorias:0' && paris.filas.length === 1
        && paris.filas[0].endsWith('| 0 – 1 | Derrota'), 'Paris: un partido, perdido en la semifinal');

  // --- 3. Reportes (RF-28) ---------------------------------------------
  console.log('--- 3. Reportes ---');
  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click('main a.btn:has-text("Reportes")')]);
  const url_reportes = O.p.url().split('#')[0];
  t.chk(await O.p.title() === 'Reportes · Stadion' && (await O.p.textContent('main .intro')) === 'Los torneos a cargo de esta cuenta.',
        'el panel lleva a los reportes de la cuenta');
  const rep = await O.p.evaluate(() => ({
    datos: [...document.querySelectorAll('.datos > div')].map(d => d.querySelector('.etiqueta').textContent + ':' + d.querySelector('strong').textContent),
    filas: [...document.querySelectorAll('#detalle tbody tr')].map(tr => [...tr.querySelectorAll('td')].map(td => td.textContent.replace(/\s+/g, ' ').trim())),
    inscriptos: document.querySelector('#inscriptos svg').getAttribute('aria-label'),
    partidos: document.querySelector('#partidos svg').getAttribute('aria-label'),
    vencedor: !!document.querySelector('#detalle .estado-vencedor'),
    menu: (document.querySelector('nav a[aria-current]') || {}).textContent }));
  t.chk(rep.datos.join(' ') === 'Torneo:1 Participantes:4 Partidos jugados:3 Tantos por partido:1,0', `los totales: ${rep.datos.join(', ')}`);
  t.chk(rep.filas.length === 1 && rep.filas[0][0].startsWith(copa) && rep.filas[0][0].includes('Eliminación directa')
        && rep.filas[0].slice(1, 5).join(' | ') === 'Finalizado | 4 de 4 | 3 de 3 | 1,0' && rep.filas[0][5].startsWith(`Aquiles Ftía ${sufijo}`) && rep.vencedor,
        `el detalle: ${rep.filas[0] ? rep.filas[0].join(' | ') : '(vacio)'}`);
  t.chk(rep.inscriptos === `Inscriptos sobre el cupo: ${copa}, 4 de 4.` && rep.partidos === `Partidos jugados y por jugar: ${copa}, 3 jugados · 0 por jugar.`,
        'los graficos dicen lo que muestran (aria-label)');
  t.chk(rep.menu === 'Organizadores', 'el menu marca Organizadores');
  const O2 = await c.entrar(nav, org2);
  await O2.p.goto(url_reportes);
  t.chk((await O2.p.textContent('main')).includes('Ningún torneo a cargo de esta cuenta.') && !(await O2.p.textContent('main')).includes(copa),
        'otra organizadora no ve los torneos ajenos');
  const AD = await c.entrar(nav, adm);
  await AD.p.goto(url_reportes);
  const de_admin = await AD.p.textContent('#detalle');
  t.chk((await AD.p.textContent('main .intro')) === 'Todos los torneos del sitio, de todas las cuentas.' && de_admin.includes(copa)
        && de_admin.includes(`organiza Atalanta Arcadia`), 'la administracion ve todos, con quien organiza');
  await AD.p.goto(`${BASE}/admin.php`);
  t.chk(await AD.p.$('aside a:has-text("Reportes de todos los torneos")') !== null, 'la administracion tiene su enlace a los reportes');
  const J = await c.entrar(nav, jug.Paris);
  let res = await J.ctx.request.get(url_reportes, { maxRedirects: 0 });
  t.chk(res.status() === 302 && /aviso=organizador/.test(res.headers()['location'] || ''), 'sin el rol, al perfil con el aviso');
  const anon = await nav.newContext();
  res = await anon.request.get(url_reportes, { maxRedirects: 0 });
  t.chk(res.status() === 302 && /login\.php/.test(res.headers()['location'] || ''), 'sin sesion, al acceso');
  await anon.close();

  // --- 4. Integrantes (RF-12) y playoffs (RF-14) ---------------------------
  console.log('--- 4. Integrantes y playoffs ---');
  const cap = await c.cuentaNueva(nav, 'Jasón', `Yolco ${sufijo}`);
  const miembro = await c.cuentaNueva(nav, 'Orfeo', `Tracia ${sufijo}`);
  const C = await c.entrar(nav, cap);
  const perfil = await c.direccionDe(C.p, 'perfil.php');
  const equipo = `Argonautas ${sufijo}`;
  await C.p.goto(`${perfil}#mis-torneos`);
  await C.p.fill('input[name="nombre_equipo"]', equipo);
  await Promise.all([C.p.waitForNavigation(), C.p.click('form.crear-equipo button')]);
  const id_eq = parseInt(sql(`SELECT id_equipo FROM equipo WHERE nombre = ${texto(equipo)}`), 10);
  await C.p.fill(`#equipo-${id_eq} form.sumar-integrante input[name="correo"]`, miembro.correo);
  await C.p.fill(`#equipo-${id_eq} form.sumar-integrante input[name="dorsal"]`, '10');
  await Promise.all([C.p.waitForNavigation(), C.p.click(`#equipo-${id_eq} form.sumar-integrante button`)]);
  t.chk((await C.p.textContent('[role="status"]')).trim() === `Orfeo Tracia ${sufijo} queda en el equipo, con el dorsal 10.`
        && sql(`SELECT dorsal FROM integrante_equipo WHERE id_equipo = ${id_eq} AND id_usuario = ${miembro.id} AND activo = 1`) === '10',
        'el capitan suma a Orfeo por su correo, con el dorsal 10');
  await C.p.fill(`#equipo-${id_eq} form.sumar-integrante input[name="correo"]`, `nadie-${azar()}@ejemplo.invalid`);
  await Promise.all([C.p.waitForNavigation(), C.p.click(`#equipo-${id_eq} form.sumar-integrante button`)]);
  t.chk((await C.p.textContent(`#error-integrante-${id_eq}`)) === 'Ninguna cuenta activa con ese correo.'
        && (await C.p.title()).startsWith('Aviso ·') && await C.p.$(`#equipo-${id_eq} input[name="correo"][aria-invalid="true"]`) !== null,
        'un correo de nadie: el error al lado del formulario');
  const tkc = await c.token(C.p);
  let r = await c.postear(C.ctx, perfil, { token_csrf: tkc, accion: 'dorsal_integrante', id_equipo: String(id_eq), id_usuario: String(cap.id), dorsal: '10' });
  t.chk(r.cuerpo.includes('El dorsal 10 ya está ocupado en este equipo.'), 'el capitan no se pone un dorsal ocupado');
  const M = await c.entrar(nav, miembro);
  await M.p.goto(`${BASE}/perfil.php#mis-torneos`);
  t.chk((await M.p.textContent('#mis-equipos')).includes(equipo) && (await M.p.textContent('#mis-equipos')).includes('Dorsal 10 · capitanía de Jasón'),
        'Orfeo ve el equipo que integra, con su dorsal y su capitan');
  const tkm = await c.token(M.p);
  r = await c.postear(M.ctx, perfil, { token_csrf: tkm, accion: 'baja_integrante', id_equipo: String(id_eq), id_usuario: String(cap.id) });
  t.chk(r.cuerpo.includes('Ese equipo no lo capitanea esta cuenta.'), 'un integrante no da de baja a nadie');

  // La liga, con dos clasificados a playoffs.
  const liga = `Liga del Vellocino ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.fill('input[name="nombre"]', liga);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Fútbol 5' });
  await O.p.fill('input[name="cupo"]', '4');
  await O.p.selectOption('select[name="clasifican"]', '2');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  const idl = parseInt(sql(`SELECT id_torneo FROM torneo WHERE nombre = ${texto(liga)}`), 10);
  t.chk(sql(`SELECT clasifican_playoffs FROM configuracion_torneo WHERE id_torneo = ${idl}`) === '2', 'la liga guarda dos clasificados a playoffs (RF-14)');
  const tko_crear = await c.token(O.p);
  r = await c.postear(O.ctx, await c.direccionDe(O.p, 'crear.php'), { token_csrf: tko_crear, nombre: `Copa Mala ${sufijo}`, disciplina: sql(`SELECT id_disciplina FROM disciplina WHERE nombre = 'Ajedrez'`),
    compite: 'equipos', formato: 'eliminacion', cupo: '8', vueltas: 'una', partido: 'unico', victoria: '3', empate: '1', derrota: '0',
    desempate: 'diferencia', inicio: '', rondas: '', sede: '', clasifican: '4' });
  t.chk(r.cuerpo.includes('id="error-clasifican"') && r.cuerpo.includes('Solo en una liga clasifican a playoffs.'), 'en una eliminacion directa, no');
  await C.p.goto(`${BASE}/torneo.php?id=${idl}`);
  await Promise.all([C.p.waitForNavigation(), C.p.click('form.pedir-lugar button')]);
  const ped = sql(`SELECT id_pedido_inscripcion FROM pedido_inscripcion WHERE id_torneo = ${idl} AND estado = 'pendiente'`);
  await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'aceptar', id_pedido: ped });
  for (const n of ['Colcos', 'Lemnos', 'Cícico']) {
    await O.p.goto(`${BASE}/panel.php`);
    await O.p.fill(`#liga-${idl} input[name="equipo"]`, `${n} ${sufijo}`);
    await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${idl} form.anotar-equipo button`)]);
  }
  await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'cerrar', id_torneo: String(idl) });
  await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'fixture', id_torneo: String(idl) });
  await O.p.goto(`${url_partidos}?id=${idl}`);
  const del_equipo = sql(`SELECT en.id_enfrentamiento FROM enfrentamiento en JOIN ronda ro USING (id_ronda) JOIN participante p
                          ON p.id_participante IN (en.id_participante_local, en.id_participante_visitante)
                          WHERE ro.id_torneo = ${idl} AND ro.numero = 1 AND p.id_equipo = ${id_eq}`);
  await O.p.fill(`#partido-${del_equipo} input[name="local"]`, '3');
  await O.p.fill(`#partido-${del_equipo} input[name="visitante"]`, '3');
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#partido-${del_equipo} form.cargar-resultado button`)]);
  await M.p.goto(`${BASE}/perfil.php#mis-torneos`);
  const filas_m = await M.p.$$eval('#mis-resultados tbody tr', l => l.map(tr => [...tr.querySelectorAll('td')].slice(1).map(td => td.innerText.replace(/\s+/g, ' ').trim()).join(' | ')));
  t.chk(filas_m.length === 1 && filas_m[0].startsWith(`${liga} Fecha 1`) && filas_m[0].endsWith('| 3 – 3 | Empate'),
        `el integrante ve el partido de su equipo en "Mis resultados" (${filas_m[0]})`);
  await P.goto(`${BASE}/torneo.php?id=${idl}#reglas`);
  t.chk((await P.textContent('#reglas')).includes('Clasifican los 2 primeros de la tabla.'), 'Reglas cuenta los dos clasificados');

  t.fin();
})().catch(e => { console.error(e); process.exitCode = 1; }).finally(async () => { if (nav) await nav.close(); });
