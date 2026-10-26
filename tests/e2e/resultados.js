// Fase 3 del motor de torneos, de punta a punta - Stadion (Agon)
'use strict';
const c = require('./comun');
const { BASE, sql, texto, azar } = c;

let nav = null;
(async () => {
  const t = c.contador('Resultados, fechas y final de una liga');
  nav = await c.navegador();
  const sufijo = azar();

  const org = await c.cuentaNueva(nav, 'Rita', 'Resultados');
  const org2 = await c.cuentaNueva(nav, 'Raúl', 'Ajeno');
  const jugador = await c.cuentaNueva(nav, 'Juan', 'Jugador');
  const admin = await c.cuentaNueva(nav, 'Ana', 'Administra');
  c.darRol(org, 'organizador');
  c.darRol(org2, 'organizador');
  c.darRol(admin, 'administrador');
  const O = await c.entrar(nav, org);
  const O2 = await c.entrar(nav, org2);
  const J = await c.entrar(nav, jugador);
  const url_crear = await c.direccionDe(O.p, 'crear.php');
  const url_panel = await c.direccionDe(O.p, 'panel.php');
  const esports = sql(`SELECT id_disciplina FROM disciplina WHERE nombre = 'Esports'`);

  // 1. Como se juega cada partido
  console.log('--- 1. Nuevo torneo: los partidos ---');
  await O.p.goto(`${BASE}/crear.php`);
  const opciones = await O.p.$$eval('input[name="partido"]', l => l.map(e => e.value + (e.checked ? '*' : '')).join(' '));
  t.chk(opciones === 'empate* unico 3 5 7', `cinco formatos, partido unico con empate por defecto (${opciones})`);
  const tk = await c.token(O.p);
  const base_liga = { token_csrf: tk, disciplina: esports, cupo: '8', vueltas: 'una', victoria: '3', empate: '1',
                      derrota: '0', desempate: 'diferencia', compite: 'equipos', formato: 'liga', inicio: '' };
  let r = await c.postear(O.ctx, url_crear, { ...base_liga, nombre: `Liga Mala ${sufijo}`, partido: '4' });
  t.chk(r.cuerpo.includes('id="error-partido"') && r.cuerpo.includes('href="#partido-empate"'),
        'un formato inventado (al mejor de 4): error al lado de "Partidos", con el enlace a su primera opcion');
  r = await c.postear(O.ctx, url_crear, { ...base_liga, nombre: `Liga Única ${sufijo}`, partido: 'unico' });
  t.chk(sql(`SELECT CONCAT(c.admite_empate, '|', IFNULL(c.mejor_de, 'NULL')) FROM configuracion_torneo c JOIN torneo t USING (id_torneo)
             WHERE t.nombre = ${texto('Liga Única ' + sufijo)}`) === '0|NULL', 'partido unico siempre con ganador: sin empate, sin serie');

  const liga = `Liga Series ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.fill('input[name="nombre"]', liga);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Esports' });
  await O.p.fill('input[name="cupo"]', '4');
  await O.p.check('#partido-3');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  const id = parseInt(sql(`SELECT id_torneo FROM torneo WHERE nombre = ${texto(liga)}`), 10);
  t.chk(sql(`SELECT CONCAT(admite_empate, '|', mejor_de) FROM configuracion_torneo WHERE id_torneo = ${id}`) === '0|3',
        'al mejor de 3 por el formulario: se guarda la serie y sin empate');

  // Cuatro equipos, inscripcion cerrada y fixture.
  for (const nombre of ['Ares', 'Bia', 'Cratos', 'Dike']) {
    await O.p.goto(`${BASE}/panel.php`);
    await O.p.fill(`#liga-${id} input[name="equipo"]`, `${nombre} ${sufijo}`);
    await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${id} form.anotar-equipo button`)]);
  }
  const tko = await c.token(O.p);
  await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'cerrar', id_torneo: String(id) });
  await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'fixture', id_torneo: String(id) });
  t.chk(sql(`SELECT COUNT(*) FROM ronda WHERE id_torneo = ${id}`) === '3', 'cuatro equipos: tres fechas');

  // 2. Del panel a Fechas y resultados
  console.log('--- 2. Fechas y resultados ---');
  await O.p.goto(`${BASE}/panel.php`);
  const enlace = await O.p.$(`#liga-${id} a.btn-primario`);
  t.chk(enlace !== null && (await enlace.textContent()).includes('Fechas y resultados')
        && (await enlace.textContent()).includes(liga), 'el panel lleva a "Fechas y resultados", con el nombre de la liga para el lector');
  t.chk((await O.p.textContent(`#liga-${id}`)).includes('Fecha 1 de 3 en juego.'), 'y dice que fecha esta en juego');
  await Promise.all([O.p.waitForNavigation(), enlace.click()]);
  const url_partidos = O.p.url().split('?')[0];
  t.chk(/partidos/i.test(url_partidos) && await O.p.title() === `Fechas y resultados · ${liga} · Stadion`,
        `la pagina de la liga abre (${O.p.url().replace(BASE, '')})`);
  t.chk(await O.p.$eval('nav a.activo', a => a.textContent + '|' + a.getAttribute('aria-current')) === 'Organizadores|true',
        'el menu marca Organizadores con aria-current="true"');
  t.chk(await O.p.$eval('.fechas-liga a[aria-current="page"]', a => a.textContent) === 'Fecha 1 · en juego'
        && (await O.p.$$('.fechas-liga a')).length === 3, 'la lista de fechas, con la que esta en juego marcada');
  t.chk(await O.p.$eval('#fecha h2', h => h.textContent) === 'Fecha 1' && (await O.p.$$('#fecha .partido-carga')).length === 2,
        'la fecha 1, con sus dos partidos');
  t.chk(await O.p.$('#fecha .enlace-apagado') !== null && (await O.p.textContent('#fecha')).includes('Faltan 2 resultados.'),
        '"Cerrar la Fecha 1" apagado, con la nota de lo que falta');
  t.chk((await O.p.textContent('.ayuda-campo[id^="ayuda-marcador-"]')).includes('2-0 o 2-1'), 'la ayuda del marcador dice los de la serie');

  // Los partidos de la fecha 1, en orden.
  const partidos = sql(`SELECT e.id_enfrentamiento, pl.id_participante, pv.id_participante
                        FROM enfrentamiento e JOIN ronda r USING (id_ronda)
                             JOIN participante pl ON pl.id_participante = e.id_participante_local
                             JOIN participante pv ON pv.id_participante = e.id_participante_visitante
                        WHERE r.id_torneo = ${id} AND r.numero = 1 ORDER BY e.numero`).split('\n').map(f => f.split('\t').map(Number));
  const [p1, p2] = partidos;
  const tabla = () => sql(`SELECT GROUP_CONCAT(CONCAT(tp.ganados, '-', tp.perdidos, ' ', tp.favor, ':', tp.contra) ORDER BY tp.id_participante SEPARATOR ' | ')
                           FROM tabla_posiciones tp JOIN participante p USING (id_participante) WHERE p.id_torneo = ${id}`);
  const fila_de = idp => sql(`SELECT CONCAT(ganados, '-', perdidos, ' ', favor, ':', contra) FROM tabla_posiciones WHERE id_participante = ${idp}`);

  // 3. Cargar, corregir y walkover
  console.log('--- 3. Resultados ---');
  const cargar = async (partido, local, visitante, desenlace = 'marcador') => {
    const bloque = `#partido-${partido}`;
    await O.p.selectOption(`${bloque} select[name="desenlace"]`, desenlace);
    await O.p.fill(`${bloque} input[name="local"]`, local);
    await O.p.fill(`${bloque} input[name="visitante"]`, visitante);
    await Promise.all([O.p.waitForNavigation(), O.p.click(`${bloque} form.cargar-resultado button`)]);
  };
  const antes = tabla();
  // 1-1, saltando el max del campo con un POST armado.
  const tkp = await c.token(O.p);
  r = await c.postear(O.ctx, url_partidos, { token_csrf: tkp, accion: 'resultado', id_torneo: String(id), id_enfrentamiento: String(p1[0]),
                                             desenlace: 'marcador', local: '1', visitante: '1' });
  t.chk(r.estado === 200 && /<title>Aviso · Fechas y resultados/.test(r.cuerpo)
        && r.cuerpo.includes('Al mejor de 3, la serie termina 2-0 o 2-1, para cualquiera de los dos lados.'),
        'un 1-1 en una serie al mejor de 3: "Aviso ·" y el porque');
  r = await c.postear(O.ctx, url_partidos, { token_csrf: tkp, accion: 'resultado', id_torneo: String(id), id_enfrentamiento: String(p1[0]),
                                             desenlace: 'marcador', 'local[]': '2', visitante: '0' });
  t.chk(r.estado === 200 && r.cuerpo.includes('El marcador lleva un número'), 'un marcador mandado como arreglo no corta la pagina');
  t.chk(tabla() === antes && sql(`SELECT COUNT(*) FROM resultado WHERE id_enfrentamiento = ${p1[0]}`) === '0', 'nada de eso se guarda');

  await cargar(p1[0], '2', '1');
  t.chk(O.p.url().includes('aviso=resultado') && (await O.p.textContent('[role="status"]')).includes('El resultado queda cargado'),
        'un 2-1 por el formulario: la pagina lo confirma (role="status")');
  t.chk(fila_de(p1[1]) === '1-0 2:1' && fila_de(p1[2]) === '0-1 1:2', 'la tabla guardada, al dia');
  t.chk((await O.p.textContent(`#partido-${p1[0]} .pedido-dato`)).includes('Jugado · 2 – 1')
        && (await O.p.textContent(`#partido-${p1[0]} legend`)).includes('Corregir el resultado'),
        'el partido dice su marcador, y el formulario pasa a corregir');
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'resultado' AND tabla_afectada = 'enfrentamiento'
             AND id_registro = ${p1[0]} AND id_usuario = ${org.id} AND detalle LIKE '%2-1%'`) === '1', 'la carga queda en la auditoria');
  await cargar(p1[0], '0', '2');
  t.chk(O.p.url().includes('aviso=correccion') && fila_de(p1[1]) === '0-1 0:2' && fila_de(p1[2]) === '1-0 2:0',
        'corregido a 0-2: la tabla cambia entera');
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'resultado' AND id_registro = ${p1[0]} AND detalle LIKE '%corrección'`) === '1',
        'la correccion tambien queda, nombrada');
  await cargar(p2[0], '', '', 'wo-visitante');
  t.chk(fila_de(p2[2]) === '1-0 0:0' && fila_de(p2[1]) === '0-1 0:0'
        && sql(`SELECT CONCAT(walkover, puntaje_local, puntaje_visitante) FROM resultado WHERE id_enfrentamiento = ${p2[0]}`) === '100',
        'walkover para el visitante: victoria sin tantos');

  // 4. Dia y lugar; en vivo
  console.log('--- 4. Programar y en vivo ---');
  const p3 = parseInt(sql(`SELECT e.id_enfrentamiento FROM enfrentamiento e JOIN ronda r USING (id_ronda)
                           WHERE r.id_torneo = ${id} AND r.numero = 2 ORDER BY e.numero LIMIT 1`), 10);
  await O.p.goto(`${url_partidos}?id=${id}&fecha=2`);
  t.chk(await O.p.$eval('#fecha h2', h => h.textContent) === 'Fecha 2' && await O.p.$('#fecha form.cargar-resultado') === null
        && (await O.p.textContent('#fecha')).includes('Los resultados se cargan cuando llega su turno'),
        'la fecha 2 todavia no carga resultados, y lo dice');
  await O.p.fill(`#partido-${p3} input[name="fecha_hora"]`, '2026-11-21T19:30');
  await O.p.fill(`#partido-${p3} input[name="lugar"]`, 'Sala Olimpia');
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#partido-${p3} form.programar-partido button`)]);
  t.chk(O.p.url().includes('fecha=2') && O.p.url().includes('aviso=programado')
        && sql(`SELECT CONCAT(fecha_hora, '|', lugar) FROM enfrentamiento WHERE id_enfrentamiento = ${p3}`) === '2026-11-21 19:30:00|Sala Olimpia',
        'el dia, la hora y el lugar se guardan, y se vuelve a la misma fecha');
  t.chk((await O.p.textContent(`#partido-${p3} .pedido-dato`)).includes('Sábado 21 de noviembre, 19:30 · Sala Olimpia'),
        'y se muestran en palabras');
  await O.p.fill(`#partido-${p3} input[name="fecha_hora"]`, '');
  r = await c.postear(O.ctx, url_partidos, { token_csrf: tkp, accion: 'programar', id_torneo: String(id), id_enfrentamiento: String(p3),
                                             fecha_hora: '2026-11-31T19:30', lugar: '' });
  t.chk(r.cuerpo.includes('El día y la hora no tienen un formato válido.'), 'un dia que no existe: aviso');

  await O.p.goto(`${url_partidos}?id=${id}`);
  t.chk(await O.p.$(`#partido-${p1[0]} form.accion-liga input[name="en_vivo"]`) === null, 'un partido jugado no ofrece "en vivo"');
  // Uno de la fecha 1 sin jugar: no hay (los dos tienen resultado).

  // 5. Cerrar la fecha
  console.log('--- 5. Cerrar la fecha ---');
  const ronda1 = sql(`SELECT id_ronda FROM ronda WHERE id_torneo = ${id} AND numero = 1`);
  const ronda2 = sql(`SELECT id_ronda FROM ronda WHERE id_torneo = ${id} AND numero = 2`);
  r = await c.postear(O.ctx, url_partidos, { token_csrf: tkp, accion: 'cerrar_fecha', id_torneo: String(id), id_ronda: ronda2 });
  t.chk(r.cuerpo.includes('Las fechas se cierran en orden: primero la Fecha 1.'), 'la fecha 2 no se cierra antes que la 1');
  t.chk(await O.p.$('#fecha form.accion-liga input[value="cerrar_fecha"]') !== null, 'con todo cargado, "Cerrar la Fecha 1" es un boton');
  await Promise.all([O.p.waitForNavigation(), O.p.click('#fecha form.accion-liga button')]);
  t.chk(O.p.url().includes('aviso=fecha-cerrada') && sql(`SELECT estado FROM ronda WHERE id_ronda = ${ronda1}`) === 'cerrada'
        && await O.p.$eval('#fecha h2', h => h.textContent) === 'Fecha 2', 'la fecha 1 queda cerrada y la pagina pasa a la 2');
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'cierre' AND tabla_afectada = 'ronda' AND id_registro = ${ronda1}`) === '1',
        'el cierre queda en la auditoria');
  r = await c.postear(O.ctx, url_partidos, { token_csrf: tkp, accion: 'resultado', id_torneo: String(id), id_enfrentamiento: String(p1[0]),
                                             desenlace: 'marcador', local: '2', visitante: '0' });
  t.chk(r.cuerpo.includes('La Fecha 1 está cerrada: sus resultados quedan como están.') && fila_de(p1[1]) === '0-1 0:2',
        'cerrada, un resultado suyo no se corrige');
  await O.p.goto(`${url_partidos}?id=${id}&fecha=1`);
  t.chk(await O.p.$('#fecha form') === null && (await O.p.textContent('#fecha')).includes('Fecha cerrada'), 'la fecha cerrada se ve sin formularios');

  // En vivo, ahora que la 2 esta en juego.
  await O.p.goto(`${url_partidos}?id=${id}`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#partido-${p3} form.accion-liga button`)]);
  t.chk(sql(`SELECT estado FROM enfrentamiento WHERE id_enfrentamiento = ${p3}`) === 'en_vivo'
        && await O.p.$(`#partido-${p3} .estado-en-vivo`) !== null, 'un partido de la fecha en juego queda en vivo');
  await O.p.goto(`${BASE}/torneo.php?id=${id}`);
  t.chk(await O.p.$eval('main .estado', e => e.textContent) === 'En vivo', 'y la pagina de la liga lo muestra en vivo');
  t.chk((await O.p.$$eval('#reglas th', l => l.map(e => e.textContent))).includes('Series')
        && (await O.p.textContent('#reglas')).includes('Cada enfrentamiento es una serie al mejor de 3'), 'Reglas cuenta la serie');
  t.chk((await O.p.textContent('#calendario')).includes('W.O.') && (await O.p.textContent('#calendario')).includes('· gana'),
        'el calendario muestra el walkover y quien lo gana');
  t.chk(await O.p.$(`main a[href*="partidos"][href*="id=${id}"]`) !== null, 'a quien la organiza, la pagina de la liga lo lleva a sus resultados');
  await J.p.goto(`${BASE}/torneo.php?id=${id}`);
  t.chk(await J.p.$('main a[href*="partidos"]') === null, 'a otra cuenta, no');

  // 6. El resto de la liga y el final
  console.log('--- 6. El final ---');
  const pendientes = () => sql(`SELECT e.id_enfrentamiento FROM enfrentamiento e JOIN ronda r USING (id_ronda)
                                WHERE r.id_torneo = ${id} AND r.estado <> 'cerrada' AND e.estado <> 'jugado'
                                  AND r.numero = (SELECT MIN(numero) FROM ronda WHERE id_torneo = ${id} AND estado <> 'cerrada')
                                ORDER BY e.numero`).split('\n').filter(Boolean).map(Number);
  for (let fecha = 2; fecha <= 3; fecha++) {
    await O.p.goto(`${url_partidos}?id=${id}`);
    for (const partido of pendientes()) await cargar(partido, '2', '0');
    await Promise.all([O.p.waitForNavigation(), O.p.click('#fecha form.accion-liga button')]);
  }
  t.chk(O.p.url().includes('aviso=liga-terminada') && sql(`SELECT estado FROM torneo WHERE id_torneo = ${id}`) === 'finalizado',
        'la ultima fecha cerrada termina la liga');
  const campeon = sql(`SELECT IFNULL(e.nombre, '') FROM tabla_posiciones tp JOIN participante p USING (id_participante) JOIN equipo e USING (id_equipo)
                       WHERE p.id_torneo = ${id} ORDER BY tp.ganados * 3 DESC, tp.favor - tp.contra DESC, tp.favor DESC, e.nombre LIMIT 1`);
  t.chk((await O.p.textContent('main .tarjeta-olivo h2')) === campeon && await O.p.$('main .tarjeta-olivo .estado-vencedor svg') !== null,
        `la pagina muestra al campeon (${campeon}) con el chip de vencedor`);
  t.chk(await O.p.$('#fecha form') === null, 'terminada, ningun formulario');
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'final' AND tabla_afectada = 'torneo' AND id_registro = ${id}
             AND detalle LIKE ${texto('%campeón: ' + campeon)}`) === '1', 'el final queda en la auditoria, con el campeon');
  await O.p.goto(`${BASE}/panel.php`);
  t.chk((await O.p.textContent(`#liga-${id}`)).includes('Liga terminada') && (await O.p.textContent(`#liga-${id} .fila strong`)) === campeon,
        'el panel muestra la liga terminada y su campeon');
  await J.p.goto(`${BASE}/torneo.php?id=${id}`);
  t.chk((await J.p.textContent('#resumen .tarjeta-olivo h3')) === campeon && await J.p.$('#resumen .estado-vencedor') !== null,
        'la pagina publica de la liga muestra al campeon con el chip de vencedor');
  r = await c.postear(O.ctx, url_partidos, { token_csrf: tkp, accion: 'resultado', id_torneo: String(id), id_enfrentamiento: String(p3),
                                             desenlace: 'marcador', local: '0', visitante: '2' });
  t.chk(r.cuerpo.includes('La liga está finalizada'), 'con la liga terminada, nada cambia');

  // 7. Permisos
  console.log('--- 7. Permisos ---');
  const id_otra = parseInt(sql(`SELECT id_torneo FROM torneo WHERE nombre = ${texto('Liga Única ' + sufijo)}`), 10);
  let res = await O2.ctx.request.get(`${url_partidos}?id=${id}`, { maxRedirects: 0 });
  let cuerpo = await res.text();
  t.chk(res.status() === 404 && cuerpo.includes('Esa competencia no está a cargo de esta cuenta.') && !cuerpo.includes('partido-carga'),
        'otro organizador no ve las fechas de la liga (404)');
  const tk2 = await c.token(O2.p);
  const ronda3 = sql(`SELECT id_ronda FROM ronda WHERE id_torneo = ${id} AND numero = 3`);
  r = await c.postear(O2.ctx, url_partidos, { token_csrf: tk2, accion: 'cerrar_fecha', id_torneo: String(id), id_ronda: ronda3 });
  t.chk(r.cuerpo.includes('Esa competencia no está a cargo de esta cuenta.'), 'ni la toca con un POST armado');
  res = await J.ctx.request.get(`${url_partidos}?id=${id}`, { maxRedirects: 0 });
  t.chk(res.status() === 302 && /aviso=organizador/.test(res.headers()['location'] || ''), 'sin el rol, al perfil con el aviso');
  r = await c.postear(J.ctx, url_partidos, { token_csrf: await c.token(J.p), accion: 'en_vivo', id_torneo: String(id_otra),
                                             id_enfrentamiento: '1', en_vivo: '1' });
  t.chk(r.estado === 403, 'sin el rol, un POST: 403');
  const anonimo = await nav.newContext();
  res = await anonimo.request.get(`${url_partidos}?id=${id}`, { maxRedirects: 0 });
  t.chk(res.status() === 302 && /login\.php/.test(res.headers()['location'] || ''), 'sin sesion, al acceso');
  await anonimo.close();
  r = await c.postear(O.ctx, url_partidos, { accion: 'programar', id_torneo: String(id), id_enfrentamiento: String(p3), fecha_hora: '', lugar: 'x' });
  t.chk(r.estado === 403, 'sin el token: 403');
  r = await c.postear(O.ctx, url_partidos, { token_csrf: tkp, accion: 'borrar', id_torneo: String(id) });
  t.chk(r.cuerpo.includes('Esa acción no existe en esta página.'), 'una accion inventada: aviso');
  res = await O.ctx.request.get(`${url_partidos}?id=999999`);
  t.chk(res.status() === 404, 'una liga que no existe: 404');
  res = await O.ctx.request.get(`${url_partidos}?id[]=1&fecha[]=2`);
  t.chk(res.status() === 404, 'un numero mandado como arreglo no corta la pagina');

  // 8. La administracion
  console.log('--- 8. Administracion ---');
  const A = await c.entrar(nav, admin);
  await A.p.goto(`${BASE}/admin.php`);
  const registro = await A.p.textContent('main');
  for (const nombre of ['Carga de resultado', 'Programación de partido', 'Cierre de fecha', 'Final de torneo']) {
    t.chk(registro.includes(nombre), `la auditoria nombra "${nombre}"`);
  }

  t.fin();
})().catch(e => { console.error(e); process.exitCode = 1; }).finally(async () => { if (nav) await nav.close(); });
