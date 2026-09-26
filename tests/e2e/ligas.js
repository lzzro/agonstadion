// =====================================================================
// Fase 2 del motor de torneos, de punta a punta - Stadion (Agon)
// ---------------------------------------------------------------------
//   node tests/e2e/ligas.js
//
// Con cuentas nuevas en cada corrida (ver comun.js), recorre lo que
// pide la fase 2 y mira el resultado en la pantalla Y en la base:
//   1. crear una liga: solo organizadores; validacion en el servidor,
//      campo por campo, con "Aviso ·" en el titulo; la liga nace con la
//      inscripcion abierta y queda en la auditoria
//   2. el organizador anota equipos a mano, por nombre
//   3. un jugador arma su equipo en el perfil y pide lugar; un solo
//      pendiente por equipo y liga; el organizador acepta o rechaza
//   4. el cupo se respeta (anotar y aceptar)
//   5. cerrar la inscripcion (con 4 o mas) rechaza los pendientes
//   6. el fixture: solo con la inscripcion cerrada; todos contra todos,
//      nadie dos veces por fecha, un libre por fecha si son impares; no
//      se rehace sin confirmar, ni con resultados
//   7. ida y vuelta: dos cruces por pareja, con la localia al reves
//   8. permisos: sin sesion, sin el rol, con la liga de otro, sin token
//   9. las cuentas de muestra no inician sesion
// =====================================================================
'use strict';
const c = require('./comun');
const { BASE, sql, texto, azar } = c;

let nav = null;
(async () => {
  const t = c.contador('Ligas de punta a punta');
  nav = await c.navegador();
  const sufijo = azar();

  // --- Las cuentas ---------------------------------------------------
  const org = await c.cuentaNueva(nav, 'Olga', 'Organiza');
  const org2 = await c.cuentaNueva(nav, 'Otto', 'Organiza');
  const jugador = await c.cuentaNueva(nav, 'Julia', 'Capitana');
  const jugador2 = await c.cuentaNueva(nav, 'Joel', 'Capitán');
  c.darRol(org, 'organizador');
  c.darRol(org2, 'organizador');

  const O = await c.entrar(nav, org);
  const J = await c.entrar(nav, jugador);
  const J2 = await c.entrar(nav, jugador2);
  const O2 = await c.entrar(nav, org2);

  const url_crear = await c.direccionDe(O.p, 'crear.php');
  const url_panel = await c.direccionDe(O2.p, 'panel.php');
  t.chk(/crear/i.test(url_crear) && /panel/i.test(url_panel), `crear.php y panel.php llevan a sus controladores (${url_crear}, ${url_panel})`);

  // --- 1. Crear una liga ----------------------------------------------
  console.log('--- 1. Crear una liga ---');
  await O.p.goto(`${BASE}/crear.php`);
  t.chk(await O.p.title() === 'Nueva liga · Stadion', 'el formulario abre para un organizador');
  t.chk(await O.p.$eval('nav a.activo', a => a.textContent + '|' + a.getAttribute('aria-current')) === 'Organizadores|true',
        'el menu marca Organizadores con aria-current="true"');
  t.chk(await O.p.$('input[name="token_csrf"]') !== null, 'el formulario lleva el token');

  // Todo mal, saltando la validacion del navegador (un POST armado).
  const tk = await c.token(O.p);
  const nombre_malo = `Li`;
  let r = await c.postear(O.ctx, url_crear, { token_csrf: tk, nombre: nombre_malo, disciplina: '9999', cupo: '50',
    vueltas: 'tres', victoria: '0', empate: '1', derrota: '0', desempate: 'azar', inicio: '2026-02-30' });
  t.chk(r.estado === 200 && /<title>Aviso · Nueva liga/.test(r.cuerpo), 'con errores: 200 y el titulo empieza con "Aviso ·"');
  for (const campo of ['nombre', 'disciplina', 'cupo', 'vueltas', 'victoria', 'desempate', 'inicio']) {
    t.chk(r.cuerpo.includes(`id="error-${campo}"`), `el error de ${campo} va al lado de su campo`);
  }
  t.chk(/id="campo-cupo"[^>]*aria-invalid="true"[^>]*aria-describedby="ayuda-cupo error-cupo"/.test(r.cuerpo),
        'el campo con error lleva aria-invalid y nombra su ayuda y su error');
  t.chk(sql(`SELECT COUNT(*) FROM torneo WHERE id_usuario_organizador = ${org.id}`) === '0', 'con errores no se crea nada');

  // Empate que vale mas que la victoria, y derrota mas que el empate.
  r = await c.postear(O.ctx, url_crear, { token_csrf: tk, nombre: `Liga X ${sufijo}`, disciplina: sql(`SELECT id_disciplina FROM disciplina WHERE nombre = 'Ajedrez'`),
    cupo: '8', vueltas: 'una', victoria: '2', empate: '3', derrota: '0', desempate: 'diferencia', inicio: '' });
  t.chk(r.cuerpo.includes('El empate no vale más que la victoria.'), 'el empate no puede valer mas que la victoria');

  // Bien, por el formulario.
  const liga = `Liga de Prueba ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.fill('input[name="nombre"]', liga);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Fútbol 5' });
  await O.p.fill('input[name="cupo"]', '5');
  await O.p.check('#vueltas-una');
  await O.p.selectOption('select[name="desempate"]', 'favor');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  t.chk(/panel/i.test(O.p.url()) && O.p.url().includes('aviso=liga-creada'), `despues de crear, al panel (${O.p.url().replace(BASE, '')})`);
  t.chk((await O.p.textContent('[role="status"]')).includes('La liga queda creada'), 'el panel confirma la liga nueva (role="status")');
  const id = parseInt(sql(`SELECT id_torneo FROM torneo WHERE nombre = ${texto(liga)}`), 10);
  const fila = sql(`SELECT t.estado, IFNULL(t.fecha_inicio, 'NULL'), t.max_participantes, t.id_usuario_organizador, c.criterio_desempate,
                           c.ida_y_vuelta, c.puntos_victoria, c.puntos_empate, c.puntos_derrota, m.nombre, tt.nombre
                    FROM torneo t JOIN configuracion_torneo c USING (id_torneo)
                         JOIN modulo_competencia m USING (id_modulo) JOIN tipo_torneo tt USING (id_tipo_torneo)
                    WHERE t.id_torneo = ${id}`).split('\t');
  t.chk(fila.join('|') === `inscripcion|NULL|5|${org.id}|favor|0|3|1|0|Liga|Por equipos`,
        `en la base: inscripcion abierta, sin fecha, cupo 5, de la cuenta de la sesion, 3/1/0, desempate por favor (${fila.join('|')})`);
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'alta' AND tabla_afectada = 'torneo' AND id_registro = ${id} AND id_usuario = ${org.id}`) === '1',
        'la liga nueva queda en la auditoria');

  // El mismo nombre otra vez.
  r = await c.postear(O.ctx, url_crear, { token_csrf: tk, nombre: liga.toUpperCase(), disciplina: sql(`SELECT id_disciplina FROM disciplina WHERE nombre = 'Ajedrez'`),
    cupo: '8', vueltas: 'una', victoria: '3', empate: '1', derrota: '0', desempate: 'diferencia', inicio: '' });
  t.chk(r.cuerpo.includes('id="error-nombre"') && r.cuerpo.includes('Ya hay una liga en juego con ese nombre.'),
        'dos ligas vigentes no comparten nombre (ni cambiando mayusculas)');

  // --- 2. Anotar equipos a mano ---------------------------------------
  console.log('--- 2. Equipos anotados por el organizador ---');
  const anotar = async (nombre) => {
    await O.p.goto(`${BASE}/panel.php`);
    await O.p.fill(`#liga-${id} input[name="equipo"]`, nombre);
    await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} form.anotar-equipo button`)]);
  };
  for (const letra of ['A', 'B', 'C']) await anotar(`Equipo ${letra} ${sufijo}`);
  t.chk(sql(`SELECT COUNT(*) FROM participante WHERE id_torneo = ${id}`) === '3', 'tres equipos anotados a mano');
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'inscripcion' AND id_registro = ${id}`) === '3', 'cada uno queda en la auditoria');
  await anotar(`equipo a ${sufijo}`);
  t.chk((await O.p.title()).startsWith('Aviso ·') && (await O.p.textContent(`#error-equipo-${id}`)).includes('ya juega esta liga'),
        'el mismo equipo otra vez (en minuscula): aviso al lado del campo');
  const tko = await c.token(O.p);
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'cerrar', id_torneo: String(id) });
  t.chk(r.cuerpo.includes('Con menos de 4 equipos la inscripción sigue abierta.'), 'con 3 equipos la inscripcion no se cierra');
  await O.p.goto(`${BASE}/panel.php`);
  t.chk(await O.p.$(`#liga-${id} form.accion-liga input[value="cerrar"]`) === null
        && (await O.p.textContent(`#liga-${id}`)).includes('Con menos de 4 equipos'), 'y el panel lo muestra apagado, con la nota');

  // --- 3. El jugador arma su equipo y pide lugar ------------------------
  console.log('--- 3. Pedido de lugar ---');
  await J.p.goto(`${BASE}/crear.php`);
  t.chk(/aviso=organizador/.test(J.p.url()) && J.p.url().endsWith('#roles'), 'sin el rol, crear.php lleva al perfil, a los roles');
  t.chk((await J.p.title()).startsWith('Aviso ·') && (await J.p.textContent('#roles .aviso-rol')).includes('pide el rol de organizador'),
        'con el aviso al lado de "Pedir el rol de organizador"');
  await J.p.goto(`${BASE}/panel.php`);
  t.chk(/aviso=organizador/.test(J.p.url()), 'y el panel tambien');
  const tkj = await c.token(J.p);
  r = await c.postear(J.ctx, url_panel, { token_csrf: tkj, accion: 'cerrar', id_torneo: String(id) });
  t.chk(r.estado === 403, 'un POST al panel sin el rol: 403');
  r = await c.postear(J.ctx, url_crear, { token_csrf: tkj, nombre: `Liga Intrusa ${sufijo}`, disciplina: '1', cupo: '8', vueltas: 'una',
    victoria: '3', empate: '1', derrota: '0', desempate: 'diferencia', inicio: '' });
  t.chk(r.estado === 403 && sql(`SELECT COUNT(*) FROM torneo WHERE nombre = 'Liga Intrusa ${sufijo}'`) === '0', 'crear sin el rol: 403 y nada');

  await J.p.goto(`${BASE}/torneo.php?id=${id}`);
  t.chk((await J.p.textContent('.pedir-lugar')).includes('Armar un equipo para pedir lugar'), 'sin equipo, la liga manda a armarlo');
  const equipo_j = `Los Probadores ${sufijo}`;
  const perfil = await c.direccionDe(J.p, 'perfil.php');
  await J.p.goto(`${perfil}#mis-torneos`);
  await J.p.fill('input[name="nombre_equipo"]', equipo_j);
  await J.p.fill('input[name="ciudad_equipo"]', 'Minas');
  await Promise.all([J.p.waitForNavigation(), J.p.click('form.crear-equipo button')]);
  t.chk(J.p.url().endsWith('#mis-torneos') && (await J.p.textContent('#mis-equipos')).includes(equipo_j), 'el equipo queda en "Mis equipos", en su pestaña');
  t.chk(sql(`SELECT CONCAT(e.id_usuario_capitan, '|', COUNT(i.id_usuario)) FROM equipo e JOIN integrante_equipo i USING (id_equipo)
             WHERE e.nombre = ${texto(equipo_j)} GROUP BY e.id_equipo`) === `${jugador.id}|1`, 'en la base: capitan y primer integrante');
  await J.p.fill('input[name="nombre_equipo"]', equipo_j.toLowerCase());
  await Promise.all([J.p.waitForNavigation(), J.p.click('form.crear-equipo button')]);
  t.chk((await J.p.textContent('#error-nombre-equipo')).includes('Ya hay un equipo con ese nombre.'), 'otro equipo con el mismo nombre: aviso al lado');

  await J.p.goto(`${BASE}/torneo.php?id=${id}`);
  await Promise.all([J.p.waitForNavigation(), J.p.click('form.pedir-lugar button')]);
  t.chk(J.p.url().includes('aviso=pedido-enviado') && (await J.p.textContent('[role="status"]')).includes('queda en revisión'),
        'el pedido queda en revision');
  t.chk(sql(`SELECT COUNT(*) FROM pedido_inscripcion pi JOIN equipo e USING (id_equipo) WHERE pi.id_torneo = ${id} AND e.nombre = ${texto(equipo_j)}
             AND pi.estado = 'pendiente' AND pi.id_usuario = ${jugador.id}`) === '1', 'en la base: un pedido pendiente del capitan');
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'solicitud' AND id_usuario = ${jugador.id}`) === '1', 'y en la auditoria');
  await J.p.goto(`${BASE}/torneo.php?id=${id}`);
  t.chk((await J.p.textContent('.pedir-lugar')).includes('pedido en revisión desde'), 'la liga dice que el pedido esta en revision');
  const url_insc = await J.p.evaluate(() => { const f = document.querySelector('form[action*="nscripcion"]'); return f ? f.action : null; });
  const id_equipo_j = sql(`SELECT id_equipo FROM equipo WHERE nombre = ${texto(equipo_j)}`);
  const tkj2 = await c.token(J.p) || tkj;
  // El formulario ya no esta (no hay equipo que pueda pedir): la
  // direccion del controlador sale de la de crear.
  const insc = url_insc || url_crear.replace(/crearController\.php$/, 'inscripcionController.php').replace(/crear\.php$/, 'inscripcion.php');
  r = await c.postear(J.ctx, insc, { token_csrf: tkj2, id_torneo: String(id), id_equipo: id_equipo_j });
  t.chk(r.destino.includes('aviso=en-revision'), 'un segundo pedido del mismo equipo: "ya tiene un pedido en revisión"');
  const id_equipo_a = sql(`SELECT id_equipo FROM equipo WHERE nombre = 'Equipo A ${sufijo}'`);
  r = await c.postear(J.ctx, insc, { token_csrf: tkj2, id_torneo: String(id), id_equipo: id_equipo_a });
  t.chk(r.destino.includes('aviso=sin-equipo'), 'pedir lugar con un equipo ajeno: no pide nada');
  r = await c.postear(J.ctx, insc, { id_torneo: String(id), id_equipo: id_equipo_j });
  t.chk(r.estado === 403, 'sin token: 403');

  // El segundo capitan.
  const equipo_j2 = `Los Rechazados ${sufijo}`;
  await J2.p.goto(`${await c.direccionDe(J2.p, 'perfil.php')}#mis-torneos`);
  await J2.p.fill('input[name="nombre_equipo"]', equipo_j2);
  await Promise.all([J2.p.waitForNavigation(), J2.p.click('form.crear-equipo button')]);
  await J2.p.goto(`${BASE}/torneo.php?id=${id}`);
  await Promise.all([J2.p.waitForNavigation(), J2.p.click('form.pedir-lugar button')]);

  // --- El organizador resuelve ----------------------------------------
  console.log('--- El organizador acepta y rechaza ---');
  await O.p.goto(`${BASE}/panel.php`);
  t.chk((await O.p.$$(`#liga-${id} .pedido`)).length === 2, 'el panel muestra los dos pedidos pendientes');
  t.chk(await O.p.$(`#liga-${id} button[aria-label="Aceptar el pedido de ${equipo_j}"]`) !== null, 'cada boton dice de quien es el pedido');
  // Otro organizador no puede resolverlos.
  const id_ped1 = sql(`SELECT pi.id_pedido_inscripcion FROM pedido_inscripcion pi JOIN equipo e USING (id_equipo) WHERE e.nombre = ${texto(equipo_j)} AND pi.estado = 'pendiente'`);
  const tko2 = await (async () => { await O2.p.goto(`${BASE}/panel.php`); return c.token(O2.p); })();
  r = await c.postear(O2.ctx, url_panel, { token_csrf: tko2, accion: 'aceptar', id_pedido: id_ped1 });
  t.chk(r.cuerpo.includes('Los pedidos de una liga los resuelve quien la organiza.')
        && sql(`SELECT estado FROM pedido_inscripcion WHERE id_pedido_inscripcion = ${id_ped1}`) === 'pendiente', 'otro organizador no acepta pedidos ajenos');
  for (const accion of ['cerrar', 'fixture', 'agregar']) {
    r = await c.postear(O2.ctx, url_panel, { token_csrf: tko2, accion, id_torneo: String(id), equipo: `Colado ${sufijo}` });
    t.chk(r.cuerpo.includes('Esa liga no está a cargo de esta cuenta.'), `ni ${accion} en una liga ajena`);
  }
  t.chk(sql(`SELECT COUNT(*) FROM equipo WHERE nombre = 'Colado ${sufijo}'`) === '0', 'y no quedo nada anotado');

  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} button[aria-label="Aceptar el pedido de ${equipo_j}"]`)]);
  t.chk(O.p.url().includes('aviso=pedido-aceptado'), 'aceptar');
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} button[aria-label="Rechazar el pedido de ${equipo_j2}"]`)]);
  t.chk(O.p.url().includes('aviso=pedido-rechazado'), 'rechazar');
  t.chk(sql(`SELECT COUNT(*) FROM participante p JOIN equipo e USING (id_equipo) WHERE p.id_torneo = ${id} AND e.nombre = ${texto(equipo_j)}`) === '1',
        'el aceptado juega la liga');
  t.chk(sql(`SELECT GROUP_CONCAT(estado ORDER BY id_pedido_inscripcion) FROM pedido_inscripcion WHERE id_torneo = ${id}`) === 'aceptado,rechazado',
        'en la base: aceptado y rechazado, con fecha y responsable');
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE tabla_afectada = 'pedido_inscripcion' AND accion IN ('aprobacion', 'rechazo') AND id_usuario = ${org.id}`) === '2',
        'las dos resoluciones quedan en la auditoria');
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'aceptar', id_pedido: id_ped1 });
  t.chk(r.cuerpo.includes('Ese pedido ya no está pendiente.'), 'un pedido resuelto no se resuelve otra vez');

  // El rechazado puede pedir de nuevo.
  await J2.p.goto(`${BASE}/torneo.php?id=${id}`);
  t.chk((await J2.p.textContent('.pedir-lugar')).includes('pedido rechazado el'), 'la liga le dice al rechazado que puede pedir otra vez');
  await Promise.all([J2.p.waitForNavigation(), J2.p.click('form.pedir-lugar button')]);
  t.chk(J2.p.url().includes('aviso=pedido-enviado'), 'y lo pide otra vez');

  // --- 4. El cupo -------------------------------------------------------
  console.log('--- 4. El cupo ---');
  await anotar(`Equipo D ${sufijo}`);
  t.chk(sql(`SELECT COUNT(*) FROM participante WHERE id_torneo = ${id}`) === '5', 'cinco de cinco');
  await O.p.goto(`${BASE}/panel.php`);
  t.chk(await O.p.$(`#liga-${id} form.anotar-equipo`) === null && (await O.p.textContent(`#liga-${id}`)).includes('El cupo está completo.'),
        'con el cupo completo, "Anotar equipo" queda apagado');
  t.chk(await O.p.$(`#liga-${id} button[aria-label="Aceptar el pedido de ${equipo_j2}"]`) === null, 'y "Aceptar" tambien');
  const id_ped2 = sql(`SELECT pi.id_pedido_inscripcion FROM pedido_inscripcion pi JOIN equipo e USING (id_equipo) WHERE e.nombre = ${texto(equipo_j2)} AND pi.estado = 'pendiente'`);
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'aceptar', id_pedido: id_ped2 });
  t.chk(r.cuerpo.includes('cupo completo') && sql(`SELECT COUNT(*) FROM participante WHERE id_torneo = ${id}`) === '5', 'aceptar a mano pasado el cupo: no');
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'agregar', id_torneo: String(id), equipo: `Equipo E ${sufijo}` });
  t.chk(r.cuerpo.includes('cupo completo') && sql(`SELECT COUNT(*) FROM participante WHERE id_torneo = ${id}`) === '5', 'anotar a mano pasado el cupo: no');
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'fixture', id_torneo: String(id) });
  t.chk(r.cuerpo.includes('El fixture se arma con la inscripción cerrada.'), 'el fixture no se arma con la inscripcion abierta');

  // --- 5. Cerrar la inscripcion ------------------------------------------
  console.log('--- 5. Cierre de la inscripcion ---');
  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} form.accion-liga button`)]);
  t.chk(O.p.url().includes('aviso=inscripcion-cerrada'), 'la inscripcion se cierra');
  t.chk(sql(`SELECT estado FROM torneo WHERE id_torneo = ${id}`) === 'en_curso', 'la liga queda en curso');
  t.chk(sql(`SELECT CONCAT(estado, '|', id_usuario_resuelve) FROM pedido_inscripcion WHERE id_pedido_inscripcion = ${id_ped2}`) === `rechazado|${org.id}`,
        'el pedido que quedaba pendiente se rechaza');
  t.chk(sql(`SELECT detalle FROM auditoria WHERE accion = 'cierre' AND id_registro = ${id}`).includes('1 pedido sin lugar'), 'el cierre queda en la auditoria');
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'agregar', id_torneo: String(id), equipo: `Tarde ${sufijo}` });
  t.chk(r.cuerpo.includes('La inscripción de esa liga está cerrada.'), 'con la inscripcion cerrada no se anota a nadie');

  // --- 6. El fixture --------------------------------------------------------
  console.log('--- 6. El fixture ---');
  const fixture = () => sql(`SELECT r.numero, e.numero, e.id_participante_local, IFNULL(e.id_participante_visitante, 0), e.id_enfrentamiento
                             FROM ronda r JOIN enfrentamiento e USING (id_ronda) WHERE r.id_torneo = ${id} ORDER BY r.numero, e.numero`)
                         .split('\n').map(l => l.split('\t').map(Number));
  const revisar = (filas, n, vueltas, que) => {
    const fechas = new Map(), parejas = new Map();
    for (const [ronda, , l, v] of filas) {
      if (!fechas.has(ronda)) fechas.set(ronda, []);
      fechas.get(ronda).push(l); if (v) fechas.get(ronda).push(v);
      if (v) { const k = [l, v].sort().join('-'); parejas.set(k, (parejas.get(k) || 0) + 1); }
    }
    const esperadas = vueltas * (n % 2 ? n : n - 1);
    t.chk(fechas.size === esperadas, `${que}: ${fechas.size} fechas (esperadas ${esperadas})`);
    t.chk([...fechas.values()].every(eq => new Set(eq).size === eq.length && eq.length === n), `${que}: cada equipo una vez por fecha, nunca dos`);
    t.chk(parejas.size === n * (n - 1) / 2 && [...parejas.values()].every(x => x === vueltas), `${que}: cada pareja se cruza ${vueltas === 1 ? 'una vez' : 'dos veces'}`);
    const libres = filas.filter(f => f[3] === 0).length;
    t.chk(libres === (n % 2 ? esperadas : 0), `${que}: ${libres} libres (uno por fecha si son impares)`);
  };
  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} form.accion-liga button`)]);
  t.chk(O.p.url().includes('aviso=fixture-armado'), 'el fixture se arma');
  const f1 = fixture();
  revisar(f1, 5, 1, 'con 5 equipos');
  t.chk(sql(`SELECT CONCAT(COUNT(*), '|', SUM(ganados + empatados + perdidos + favor + contra)) FROM tabla_posiciones tp JOIN participante p USING (id_participante) WHERE p.id_torneo = ${id}`) === '5|0',
        'cada equipo con su fila en la tabla, en cero');
  t.chk(sql(`SELECT rondas_previstas FROM configuracion_torneo WHERE id_torneo = ${id}`) === '5', 'la configuracion anota 5 fechas');
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'fixture' AND id_registro = ${id}`) === '1', 'el fixture queda en la auditoria');
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'fixture', id_torneo: String(id) });
  t.chk(r.cuerpo.includes('Rehacerlo pide confirmación.'), 'armarlo otra vez sin pedir rehacer: no');
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'rehacer', id_torneo: String(id) });
  t.chk(r.cuerpo.includes('Rehacer el fixture pide marcar la confirmación.') && JSON.stringify(fixture()) === JSON.stringify(f1),
        'rehacer sin la casilla: no cambia nada');
  await O.p.goto(`${BASE}/panel.php`);
  await O.p.check(`#liga-${id} input[name="confirmo"]`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} form.accion-liga button`)]);
  const f2 = fixture();
  t.chk(O.p.url().includes('aviso=fixture-rehecho') && f2[0][4] !== f1[0][4], 'con la casilla marcada se rehace (partidos nuevos)');
  revisar(f2, 5, 1, 'rehecho');
  // Con un resultado cargado ya no se rehace.
  sql(`INSERT INTO resultado (id_enfrentamiento, puntaje_local, puntaje_visitante, id_participante_ganador)
       VALUES (${f2[0][4]}, 1, 0, ${f2[0][2]})`);
  r = await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'rehacer', id_torneo: String(id), confirmo: '1' });
  t.chk(r.cuerpo.includes('El fixture ya tiene resultados: no se rehace.') && JSON.stringify(fixture()) === JSON.stringify(f2),
        'con un resultado cargado, no se rehace');
  await O.p.goto(`${BASE}/panel.php`);
  t.chk(await O.p.$(`#liga-${id} input[name="confirmo"]`) === null && (await O.p.textContent(`#liga-${id}`)).includes('Con resultados cargados'),
        'y el panel lo muestra apagado, con la nota');

  // La pagina publica de la liga.
  // (Una recarga: la direccion es la misma de antes con otra ancla, y
  // sin recargar el navegador mostraria la pagina vieja.)
  await J.p.goto(`${BASE}/torneo.php?id=${id}#calendario`);
  await J.p.reload();
  const cal = await J.p.textContent('#calendario');
  t.chk(cal.includes('Fecha 5') && cal.includes('queda libre') && cal.includes(equipo_j), 'torneo.php muestra el fixture, con el libre');

  // --- 7. Ida y vuelta ---------------------------------------------------------
  console.log('--- 7. Ida y vuelta ---');
  const liga2 = `Liga Doble ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.fill('input[name="nombre"]', liga2);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Esports' });
  await O.p.fill('input[name="cupo"]', '4');
  await O.p.check('#vueltas-dos');
  await O.p.fill('input[name="inicio"]', '2026-11-07');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  const id2 = parseInt(sql(`SELECT id_torneo FROM torneo WHERE nombre = ${texto(liga2)}`), 10);
  t.chk(sql(`SELECT CONCAT(fecha_inicio, '|', ida_y_vuelta) FROM torneo JOIN configuracion_torneo USING (id_torneo) WHERE id_torneo = ${id2}`) === '2026-11-07|1',
        'ida y vuelta, con fecha de inicio');
  for (const letra of ['P', 'Q', 'R', 'S']) {
    await O.p.goto(`${BASE}/panel.php`);
    await O.p.fill(`#liga-${id2} input[name="equipo"]`, `Doble ${letra} ${sufijo}`);
    await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id2} form.anotar-equipo button`)]);
  }
  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id2} form.accion-liga button`)]);
  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id2} form.accion-liga button`)]);
  const fd = sql(`SELECT r.numero, e.numero, e.id_participante_local, IFNULL(e.id_participante_visitante, 0) FROM ronda r JOIN enfrentamiento e USING (id_ronda)
                  WHERE r.id_torneo = ${id2} ORDER BY r.numero, e.numero`).split('\n').map(l => l.split('\t').map(Number));
  revisar(fd, 4, 2, 'ida y vuelta con 4');
  const ida = fd.filter(f => f[0] <= 3).map(f => `${f[2]}-${f[3]}`).sort();
  const vuelta = fd.filter(f => f[0] > 3).map(f => `${f[3]}-${f[2]}`).sort();
  t.chk(JSON.stringify(ida) === JSON.stringify(vuelta), 'la vuelta repite la ida con la localia al reves');

  // --- 8. Sin sesion y sin token ------------------------------------------------
  console.log('--- 8. Sin sesion y sin token ---');
  const anon = await nav.newContext();
  for (const url of [url_crear, url_panel]) {
    const a = await c.postear(anon, url, { accion: 'cerrar', id_torneo: String(id) });
    t.chk(a.estado === 302 && a.destino.includes('login.php'), `sin sesion, ${url.replace(BASE, '')} manda al acceso`);
  }
  r = await c.postear(O.ctx, url_panel, { accion: 'agregar', id_torneo: String(id2), equipo: `Sin token ${sufijo}` });
  t.chk(r.estado === 403 && sql(`SELECT COUNT(*) FROM equipo WHERE nombre = 'Sin token ${sufijo}'`) === '0', 'el panel sin token: 403 y nada');
  r = await c.postear(O.ctx, url_crear, { nombre: `Sin token ${sufijo}`, cupo: '8' });
  t.chk(r.estado === 403, 'crear sin token: 403');

  // --- 9. Las cuentas de muestra no inician sesion ----------------------------------
  console.log('--- 9. Cuentas de muestra ---');
  const m = await anon.newPage();
  await m.goto(`${BASE}/login.php`);
  await m.fill('input[name="correo"]', 'vortice@ejemplo.invalid');
  await m.fill('input[name="password"]', 'cualquier-cosa-larga');
  await Promise.all([m.waitForNavigation(), m.click('main form button[type="submit"]')]);
  t.chk((await m.textContent('main')).includes('Una cuenta de muestra no abre sesión.'), 'el inicio de sesion rechaza la cuenta de muestra, con su aviso');
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'login_error' AND detalle LIKE 'Cuenta de muestra: vortice@%'`) !== '0', 'y lo deja en la auditoria');
  await m.goto(`${BASE}/panel.php`);
  t.chk(/login\.php/.test(m.url()), 'sin sesion abierta: el panel manda al acceso');

  await nav.close();
  t.fin();
})().catch(async e => { console.error(e); process.exitCode = 1; if (nav) await nav.close(); });
