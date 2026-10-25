// =====================================================================
// Ligas individuales y cancelar una liga (fase 3) - Stadion (Agon)
// ---------------------------------------------------------------------
//   node tests/e2e/individuales.js
//
// Con cuentas nuevas en cada corrida (ver comun.js), mira en la
// pantalla Y en la base:
//   1. "Nuevo torneo" pregunta quien compite: equipos o jugadores
//   2. una liga individual en el panel: "Jugadores anotados", sin el
//      formulario de anotar equipos (y un POST armado no anota nada)
//   3. CU-09: cada jugador se inscribe solo desde la pagina de la liga,
//      sin pedido; una vez por cuenta; hasta el cupo; sin sesion, al
//      acceso; sin token, 403; la auditoria
//   4. la liga en juego: fixture, tabla y participantes con los nombres
//      de las personas; un resultado; el calendario del sitio
//   5. RF-17, cancelar en vez de borrar: con la casilla, los pedidos
//      pendientes quedan sin lugar, la liga deja de verse (y su nombre
//      queda libre), lo jugado queda en la base; otra cuenta no cancela
//      una liga ajena
// Deja rastro, como ligas.js.
// =====================================================================
'use strict';
const c = require('./comun');
const { BASE, sql, texto, azar } = c;

let nav = null;
(async () => {
  const t = c.contador('Ligas individuales y cancelar una liga');
  nav = await c.navegador();
  const sufijo = azar();

  const org = await c.cuentaNueva(nav, 'Irene', 'Individual');
  const org2 = await c.cuentaNueva(nav, 'Iván', 'Ajeno');
  c.darRol(org, 'organizador');
  c.darRol(org2, 'organizador');
  const jugadores = [];
  for (const [n, a] of [['Hipatia', 'Alejandría'], ['Tales', 'Mileto'], ['Safo', 'Lesbos'], ['Zenón', 'Elea'], ['Anaxágoras', 'Clazómenas']]) {
    jugadores.push(await c.cuentaNueva(nav, n, a));
  }
  const O = await c.entrar(nav, org);
  const O2 = await c.entrar(nav, org2);
  const url_crear = await c.direccionDe(O.p, 'crear.php');
  const url_panel = await c.direccionDe(O.p, 'panel.php');

  // --- 1. Quien compite -----------------------------------------------
  console.log('--- 1. Nuevo torneo: quien compite ---');
  await O.p.goto(`${BASE}/crear.php`);
  t.chk(await O.p.$$eval('input[name="compite"]', l => l.map(e => e.value + (e.checked ? '*' : '')).join(' ')) === 'equipos* jugadores',
        'equipos o jugadores, equipos por defecto');
  const tk = await c.token(O.p);
  const ajedrez = sql(`SELECT id_disciplina FROM disciplina WHERE nombre = 'Ajedrez'`);
  let r = await c.postear(O.ctx, url_crear, { token_csrf: tk, nombre: `Liga Rara ${sufijo}`, disciplina: ajedrez, compite: 'robots', formato: 'liga', cupo: '8',
    vueltas: 'una', partido: 'empate', victoria: '3', empate: '1', derrota: '0', desempate: 'diferencia', inicio: '' });
  t.chk(r.cuerpo.includes('id="error-compite"') && r.cuerpo.includes('href="#compite-equipos"'), 'un valor inventado: error al lado de "Compiten"');

  const liga = `Liga Individual ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.fill('input[name="nombre"]', liga);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Ajedrez' });
  await O.p.check('#compite-jugadores');
  await O.p.fill('input[name="cupo"]', '4');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  const id = parseInt(sql(`SELECT id_torneo FROM torneo WHERE nombre = ${texto(liga)}`), 10);
  t.chk(sql(`SELECT tt.nombre FROM torneo t JOIN tipo_torneo tt USING (id_tipo_torneo) WHERE t.id_torneo = ${id}`) === 'Individual',
        'la liga queda individual');

  // --- 2. El panel ----------------------------------------------------
  console.log('--- 2. El panel ---');
  await O.p.goto(`${BASE}/panel.php`);
  const tarjeta = await O.p.textContent(`#liga-${id}`);
  t.chk(tarjeta.includes('0 de 4 jugadores') && tarjeta.includes('Jugadores anotados') && tarjeta.includes('Sin jugadores anotados.')
        && await O.p.$(`#liga-${id} form.anotar-equipo`) === null && tarjeta.includes('cada uno se anota con su cuenta'),
        'jugadores en vez de equipos, sin el formulario de anotar');
  const tko = await c.token(O.p);
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'agregar', id_torneo: String(id), equipo: `Colado ${sufijo}` });
  t.chk(r.cuerpo.includes('En esta competencia compiten jugadores') && sql(`SELECT COUNT(*) FROM participante WHERE id_torneo = ${id}`) === '0',
        'un POST armado para anotar un equipo no anota nada');

  // --- 3. Cada jugador se inscribe solo -------------------------------
  console.log('--- 3. Inscripcion de jugadores (CU-09) ---');
  const anon = await nav.newContext();
  const pa = await anon.newPage();
  await pa.goto(`${BASE}/torneo.php?id=${id}`);
  t.chk((await pa.textContent('.pedir-lugar')).includes('Iniciar sesión para inscribirse'), 'sin sesion, invita a iniciarla');
  await anon.close();

  const sesiones = [];
  for (const j of jugadores) sesiones.push(await c.entrar(nav, j));
  const [J1] = sesiones;
  await J1.p.goto(`${BASE}/torneo.php?id=${id}`);
  t.chk((await J1.p.textContent('.pedir-lugar button')).includes('Inscribirse'), 'con sesion, "Inscribirse"');
  const url_insc = await J1.p.$eval('form.pedir-lugar', f => f.action);
  await Promise.all([J1.p.waitForNavigation(), J1.p.click('form.pedir-lugar button')]);
  t.chk(J1.p.url().includes('aviso=inscripto') && (await J1.p.textContent('[role="status"]')).includes('La inscripción queda hecha'),
        'la inscripcion es directa, sin pedido');
  t.chk(sql(`SELECT CONCAT(IFNULL(id_equipo, 'NULL'), '|', estado) FROM participante WHERE id_torneo = ${id} AND id_usuario = ${jugadores[0].id}`) === 'NULL|inscripto'
        && sql(`SELECT COUNT(*) FROM pedido_inscripcion WHERE id_torneo = ${id}`) === '0', 'en la base: participante con su cuenta, ningun pedido');
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'inscripcion' AND tabla_afectada = 'participante' AND id_usuario = ${jugadores[0].id}`) === '1',
        'la inscripcion queda en la auditoria');
  t.chk((await J1.p.textContent('.pedir-lugar')).includes('Inscripción hecha') && await J1.p.$('form.pedir-lugar') === null,
        'ya inscripta, la pagina lo dice y no ofrece el boton');
  const tkj = await c.token(J1.p);
  r = await c.postear(J1.ctx, url_insc, { token_csrf: tkj, id_torneo: String(id) });
  t.chk(r.destino.includes('aviso=ya-inscripto') && sql(`SELECT COUNT(*) FROM participante WHERE id_torneo = ${id}`) === '1',
        'una segunda vez: "ya juega esta competencia", y no se duplica');
  r = await c.postear(J1.ctx, url_insc, { id_torneo: String(id) });
  t.chk(r.estado === 403, 'sin token: 403');
  for (const s of sesiones.slice(1, 4)) {
    await s.p.goto(`${BASE}/torneo.php?id=${id}`);
    await Promise.all([s.p.waitForNavigation(), s.p.click('form.pedir-lugar button')]);
  }
  t.chk(sql(`SELECT COUNT(*) FROM participante WHERE id_torneo = ${id}`) === '4', 'cuatro jugadores: el cupo completo');
  const J5 = sesiones[4];
  await J5.p.goto(`${BASE}/torneo.php?id=${id}`);
  t.chk((await J5.p.textContent('.pedir-lugar')).includes('La liga tiene su cupo completo.') && await J5.p.$('form.pedir-lugar') === null,
        'con el cupo completo, "Inscribirse" apagado');
  r = await c.postear(J5.ctx, url_insc, { token_csrf: await c.token(J5.p), id_torneo: String(id) });
  t.chk(r.destino.includes('aviso=cupo') && sql(`SELECT COUNT(*) FROM participante WHERE id_torneo = ${id}`) === '4', 'y un POST armado no entra');
  // Una liga de muestra, por equipos: el mismo formulario sin equipo no pide nada.
  const muestra = sql(`SELECT id_torneo FROM torneo WHERE nombre = 'Liga Interna Club Sur'`);
  r = await c.postear(J5.ctx, url_insc, { token_csrf: await c.token(J5.p), id_torneo: muestra });
  t.chk(r.destino.includes('aviso=sin-equipo'), 'en una liga por equipos, sin equipo no se pide nada');

  // --- 4. La liga en juego --------------------------------------------
  console.log('--- 4. La liga en juego ---');
  await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'cerrar', id_torneo: String(id) });
  await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'fixture', id_torneo: String(id) });
  t.chk(sql(`SELECT COUNT(*) FROM ronda WHERE id_torneo = ${id}`) === '3', 'inscripcion cerrada y fixture: tres fechas');
  const pub = await nav.newContext();
  const pp = await pub.newPage();
  await pp.goto(`${BASE}/torneo.php?id=${id}#participantes`);
  t.chk(await pp.$eval('#participantes thead', e => e.textContent) === 'Jugador'
        && (await pp.textContent('#participantes tbody')).includes('Hipatia Alejandría'), 'Participantes: los jugadores por nombre');
  t.chk(await pp.$eval('#posiciones thead th:nth-child(2)', e => e.textContent) === 'Jugador', 'la tabla dice "Jugador"');
  t.chk((await pp.textContent('#calendario')).includes(' vs '), 'el fixture se ve con los nombres');
  t.chk((await pp.textContent('main .intro')).includes('4 jugadores'), 'la cabecera cuenta jugadores');

  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} a.btn-primario`)]);
  const url_partidos = O.p.url().split('?')[0];
  const [pid, local] = sql(`SELECT e.id_enfrentamiento, pl.id_usuario FROM enfrentamiento e JOIN ronda r USING (id_ronda)
                            JOIN participante pl ON pl.id_participante = e.id_participante_local
                            WHERE r.id_torneo = ${id} AND r.numero = 1 ORDER BY e.numero LIMIT 1`).split('\t');
  const tkp = await c.token(O.p);
  await c.postear(O.ctx, url_partidos, { token_csrf: tkp, accion: 'programar', id_torneo: String(id), id_enfrentamiento: pid,
                                         fecha_hora: '2026-11-20T19:00', lugar: 'Club de Ajedrez' });
  await c.postear(O.ctx, url_partidos, { token_csrf: tkp, accion: 'resultado', id_torneo: String(id), id_enfrentamiento: pid,
                                         desenlace: 'marcador', local: '1', visitante: '0' });
  t.chk(sql(`SELECT tp.ganados FROM tabla_posiciones tp JOIN participante p USING (id_participante) WHERE p.id_torneo = ${id} AND p.id_usuario = ${local}`) === '1',
        'un resultado entre dos jugadores suma en la tabla');
  await pp.goto(`${BASE}/calendario.php?semana=2026-11-20`);
  const semana = await pp.textContent('main');
  t.chk(semana.includes(liga) && /[A-ZÁÉÍÓÚ][a-záéíóú]+ [A-ZÁÉÍÓÚ][a-záéíóú]+ vs [A-ZÁÉÍÓÚ]/.test(semana),
        'el calendario del sitio muestra el partido con los nombres de los jugadores');
  await pub.close();

  // --- 5. Cancelar ----------------------------------------------------
  console.log('--- 5. Cancelar una liga (RF-17) ---');
  // Una liga por equipos, con un equipo y un pedido pendiente.
  const cancelable = `Liga Cancelable ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.fill('input[name="nombre"]', cancelable);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Fútbol 5' });
  await O.p.fill('input[name="cupo"]', '8');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  const idc = parseInt(sql(`SELECT id_torneo FROM torneo WHERE nombre = ${texto(cancelable)}`), 10);
  await O.p.fill(`#liga-${idc} input[name="equipo"]`, `Cancelados ${sufijo}`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${idc} form.anotar-equipo button`)]);
  const perfil = await c.direccionDe(J5.p, 'perfil.php');
  await J5.p.goto(`${perfil}#mis-torneos`);
  await J5.p.fill('input[name="nombre_equipo"]', `Pedidores ${sufijo}`);
  await Promise.all([J5.p.waitForNavigation(), J5.p.click('form.crear-equipo button')]);
  await J5.p.goto(`${BASE}/torneo.php?id=${idc}`);
  await Promise.all([J5.p.waitForNavigation(), J5.p.click('form.pedir-lugar button')]);
  t.chk(sql(`SELECT COUNT(*) FROM pedido_inscripcion WHERE id_torneo = ${idc} AND estado = 'pendiente'`) === '1', 'la liga tiene un pedido pendiente');

  await O.p.goto(`${BASE}/panel.php`);
  t.chk(await O.p.$(`#liga-${idc} form.cancelar-liga input[name="confirmo_cancelar"][required]`) !== null
        && await O.p.$(`#liga-${id} form.cancelar-liga`) !== null, 'cada liga sin terminar ofrece cancelarla, con su casilla');
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'cancelar', id_torneo: String(idc) });
  t.chk(r.cuerpo.includes('Cancelar la liga pide marcar la confirmación.') && sql(`SELECT estado FROM torneo WHERE id_torneo = ${idc}`) === 'inscripcion',
        'sin la casilla, no se cancela');
  const tk2 = await c.token(O2.p);
  r = await c.postear(O2.ctx, url_panel, { token_csrf: tk2, accion: 'cancelar', id_torneo: String(idc), confirmo_cancelar: '1' });
  t.chk(r.cuerpo.includes('Esa competencia no está a cargo de esta cuenta.') && sql(`SELECT estado FROM torneo WHERE id_torneo = ${idc}`) === 'inscripcion',
        'otro organizador no la cancela');
  await O.p.check(`#liga-${idc} input[name="confirmo_cancelar"]`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${idc} form.cancelar-liga button`)]);
  t.chk(O.p.url().includes('aviso=liga-cancelada') && (await O.p.textContent('[role="status"]')).includes('El torneo queda cancelado'),
        'con la casilla, queda cancelada (role="status")');
  t.chk(sql(`SELECT CONCAT(estado, '|', IFNULL(nombre_vigente, 'NULL')) FROM torneo WHERE id_torneo = ${idc}`) === 'cancelado|NULL'
        && sql(`SELECT COUNT(*) FROM participante WHERE id_torneo = ${idc}`) === '1', 'en la base: cancelada, con su inscripto guardado');
  t.chk(sql(`SELECT estado FROM pedido_inscripcion WHERE id_torneo = ${idc}`) === 'rechazado', 'el pedido pendiente queda sin lugar');
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'cancelacion' AND tabla_afectada = 'torneo' AND id_registro = ${idc}
             AND detalle LIKE '%1 pedido sin lugar'`) === '1', 'la cancelacion queda en la auditoria');
  t.chk(await O.p.$(`#liga-${idc}`) === null, 'el panel ya no la muestra');
  let res = await O.ctx.request.get(`${BASE}/torneo.php?id=${idc}`);
  t.chk(res.status() === 404, 'su pagina publica responde 404');
  await O.p.goto(`${BASE}/torneos.php`);
  t.chk(!(await O.p.textContent('main')).includes(cancelable), 'y no esta en la lista de torneos');
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'cancelar', id_torneo: String(idc), confirmo_cancelar: '1' });
  t.chk(r.cuerpo.includes('Esa competencia ya está cancelada.'), 'una cancelada no se cancela otra vez');
  r = await c.postear(O.ctx, url_crear, { token_csrf: tko, nombre: cancelable, disciplina: ajedrez, compite: 'equipos', formato: 'liga', cupo: '8',
    vueltas: 'una', partido: 'empate', victoria: '3', empate: '1', derrota: '0', desempate: 'diferencia', inicio: '' });
  t.chk(r.estado === 302 && sql(`SELECT COUNT(*) FROM torneo WHERE nombre = ${texto(cancelable)}`) === '2', 'su nombre queda libre para otra liga');
  // La liga individual, en curso con un resultado, tambien se cancela.
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'cancelar', id_torneo: String(id), confirmo_cancelar: '1' });
  t.chk(sql(`SELECT estado FROM torneo WHERE id_torneo = ${id}`) === 'cancelado' && sql(`SELECT COUNT(*) FROM resultado re JOIN enfrentamiento e USING (id_enfrentamiento)
             JOIN ronda ro USING (id_ronda) WHERE ro.id_torneo = ${id}`) === '1', 'una liga en curso tambien se cancela, y lo jugado queda');
  res = await O.ctx.request.get(`${url_partidos}?id=${id}`);
  t.chk(res.status() === 404, 'cancelada, sus fechas ya no se administran');

  t.fin();
})().catch(e => { console.error(e); process.exitCode = 1; }).finally(async () => { if (nav) await nav.close(); });
