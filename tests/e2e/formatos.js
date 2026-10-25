// =====================================================================
// Eliminacion directa y suizo, de punta a punta (fase 3) - Stadion (Agon)
// ---------------------------------------------------------------------
//   node tests/e2e/formatos.js
//
// Con cuentas nuevas en cada corrida (ver comun.js), juega por las
// pantallas una eliminacion directa y un suizo, y mira la pantalla Y la
// base:
//   1. "Nuevo torneo" pregunta el formato; una eliminacion directa no
//      admite empate ni ida y vuelta; el suizo pide sus rondas
//   2. la eliminacion directa: "Armar la llave" en el panel, la llave en
//      la pagina del torneo y en llave.php (con "Por definir"), "Rondas y
//      resultados", cada ronda cerrada arma la siguiente, y la final da
//      el campeon (con el chip de vencedor)
//   3. el suizo: "Armar la primera ronda", la tabla, tres rondas sin
//      repetir rival, el libre, y el campeon
// Deja rastro, como ligas.js.
// =====================================================================
'use strict';
const c = require('./comun');
const { BASE, sql, texto, azar } = c;

let nav = null;
(async () => {
  const t = c.contador('Eliminacion directa y suizo de punta a punta');
  nav = await c.navegador();
  const sufijo = azar();
  const org = await c.cuentaNueva(nav, 'Fermín', 'Formatos');
  c.darRol(org, 'organizador');
  const O = await c.entrar(nav, org);
  const url_crear = await c.direccionDe(O.p, 'crear.php');
  const url_panel = await c.direccionDe(O.p, 'panel.php');
  const ajedrez = sql(`SELECT id_disciplina FROM disciplina WHERE nombre = 'Ajedrez'`);
  const pub = await nav.newContext();
  const P = await pub.newPage();

  // --- 1. El formato ---------------------------------------------------
  console.log('--- 1. Nuevo torneo: el formato ---');
  await O.p.goto(`${BASE}/crear.php`);
  t.chk(await O.p.title() === 'Nuevo torneo · Stadion'
        && await O.p.$$eval('input[name="formato"]', l => l.map(e => e.value + (e.checked ? '*' : '')).join(' ')) === 'liga* eliminacion suizo',
        'tres formatos, la liga por defecto');
  const tk = await c.token(O.p);
  const base = { token_csrf: tk, disciplina: ajedrez, compite: 'equipos', cupo: '8', victoria: '3', empate: '1', derrota: '0',
                 desempate: 'diferencia', inicio: '', rondas: '' };
  let r = await c.postear(O.ctx, url_crear, { ...base, nombre: `Copa Mala ${sufijo}`, formato: 'eliminacion', vueltas: 'dos', partido: 'empate' });
  t.chk(r.cuerpo.includes('En una eliminación directa ningún partido termina empatado.') && r.cuerpo.includes('Solo una liga se juega de ida y vuelta.')
        && sql(`SELECT COUNT(*) FROM torneo WHERE nombre = ${texto('Copa Mala ' + sufijo)}`) === '0',
        'una eliminacion directa con empate o de ida y vuelta: los dos errores, y nada creado');
  r = await c.postear(O.ctx, url_crear, { ...base, nombre: `Suizo Malo ${sufijo}`, formato: 'suizo', vueltas: 'una', partido: 'empate', rondas: '9' });
  t.chk(r.cuerpo.includes('El suizo se juega de 3 a 7 rondas.'), 'un suizo de 9 rondas, no');
  r = await c.postear(O.ctx, url_crear, { ...base, nombre: `Raro ${sufijo}`, formato: 'americano', vueltas: 'una', partido: 'empate' });
  t.chk(r.cuerpo.includes('id="error-formato"'), 'un formato inventado: error al lado de "Formato"');

  // --- 2. Eliminacion directa ------------------------------------------
  console.log('--- 2. Eliminacion directa ---');
  const copa = `Copa Formatos ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.check('#formato-eliminacion');
  await O.p.fill('input[name="nombre"]', copa);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Ajedrez' });
  await O.p.fill('input[name="cupo"]', '8');
  await O.p.check('#partido-unico');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  const idc = parseInt(sql(`SELECT id_torneo FROM torneo WHERE nombre = ${texto(copa)}`), 10);
  t.chk(sql(`SELECT CONCAT(m.nombre, '|', c.admite_empate) FROM torneo t JOIN modulo_competencia m USING (id_modulo)
             JOIN configuracion_torneo c USING (id_torneo) WHERE t.id_torneo = ${idc}`) === 'Eliminación directa|0',
        'la eliminacion directa queda creada, sin empate');
  for (const n of ['Aquiles', 'Héctor', 'Áyax', 'Paris', 'Eneas', 'Néstor']) {
    await O.p.goto(`${BASE}/panel.php`);
    await O.p.fill(`#liga-${idc} input[name="equipo"]`, `${n} ${sufijo}`);
    await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${idc} form.anotar-equipo button`)]);
  }
  const tko = await c.token(O.p);
  await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'cerrar', id_torneo: String(idc) });
  await O.p.goto(`${BASE}/panel.php`);
  const boton = await O.p.textContent(`#liga-${idc} form.accion-liga button`);
  t.chk(boton.startsWith('Armar la llave') && (await O.p.textContent(`#liga-${idc}`)).includes('2 pases libres'),
        'el panel ofrece "Armar la llave", y cuenta los pases libres');
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${idc} form.accion-liga button`)]);
  t.chk(sql(`SELECT GROUP_CONCAT(CONCAT(nombre, ':', (SELECT COUNT(*) FROM enfrentamiento e WHERE e.id_ronda = r.id_ronda)))
             FROM ronda r WHERE id_torneo = ${idc}`) === 'Cuartos de final:4', 'la llave: cuartos de final, con 4 cruces (2 libres)');

  await P.goto(`${BASE}/torneo.php?id=${idc}#posiciones`);
  t.chk(await P.$eval('#posiciones .pestanas a.activo', a => a.textContent) === 'Llave'
        && (await P.$$('#posiciones .llave .llave-ronda')).length === 3
        && (await P.textContent('#posiciones .llave')).includes('Pase libre')
        && (await P.textContent('#posiciones .llave')).includes('Por definir'), 'la pagina del torneo muestra la llave: tres rondas, libres y por definir');
  t.chk((await P.textContent('#reglas')).includes('Eliminación directa de 6 equipos'), 'Reglas cuenta el formato');
  await P.goto(`${BASE}/llave.php`);
  t.chk(await P.$(`#llaves a[href="llave.php?id=${idc}"]`) !== null, 'llave.php la lista entre las llaves');
  await P.goto(`${BASE}/llave.php?id=${idc}`);
  t.chk(await P.title() === `Llave · ${copa} · Stadion` && (await P.$$('main .llave .cruce')).length === 7, 'llave.php?id= dibuja su llave: 4 + 2 + 1 cruces');

  await O.p.goto(`${BASE}/panel.php`);
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${idc} a.btn-primario`)]);
  const url_partidos = O.p.url().split('?')[0];
  t.chk(await O.p.title() === `Rondas y resultados · ${copa} · Stadion` && await O.p.$eval('#fecha h2', h => h.textContent) === 'Cuartos de final',
        '"Rondas y resultados", con los cuartos en juego');
  // Gana siempre el local.
  const jugar = async () => {
    await O.p.goto(`${url_partidos}?id=${idc}`);
    const ids = await O.p.$$eval('#fecha form.cargar-resultado input[name="id_enfrentamiento"]', l => l.map(e => e.value));
    for (const id of ids) {
      await O.p.fill(`#partido-${id} input[name="local"]`, '1');
      await O.p.fill(`#partido-${id} input[name="visitante"]`, '0');
      await Promise.all([O.p.waitForNavigation(), O.p.click(`#partido-${id} form.cargar-resultado button`)]);
    }
    await Promise.all([O.p.waitForNavigation(), O.p.click('#fecha form.accion-liga input[value="cerrar_fecha"] ~ button')]);
  };
  await jugar();
  t.chk(O.p.url().includes('aviso=fecha-cerrada') && (await O.p.textContent('[role="status"]')).includes('La ronda queda cerrada, y la siguiente queda armada.')
        && await O.p.$eval('#fecha h2', h => h.textContent) === 'Semifinal', 'cerrados los cuartos, la semifinal queda armada y en juego');
  t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'cierre' AND tabla_afectada = 'ronda' AND detalle LIKE ${texto(copa + ' · Cuartos de final')}`) === '1',
        'el cierre de la ronda queda en la auditoria');
  await jugar();
  await jugar();
  t.chk(O.p.url().includes('aviso=liga-terminada') && sql(`SELECT estado FROM torneo WHERE id_torneo = ${idc}`) === 'finalizado'
        && (await O.p.textContent('main .tarjeta-olivo')).includes('Ganador de la final'), 'la final cerrada termina el torneo');
  const campeon = await O.p.textContent('main .tarjeta-olivo h2');
  t.chk(campeon === `Aquiles ${sufijo}`, `el campeon es el sembrado 1, que gano siempre como local (${campeon})`);
  await P.goto(`${BASE}/torneo.php?id=${idc}`);
  t.chk((await P.textContent('#resumen .tarjeta-olivo h3')) === campeon && await P.$('#resumen .estado-vencedor') !== null,
        'la pagina publica muestra al campeon con el chip de vencedor');
  await P.goto(`${BASE}/llave.php?id=${idc}`);
  t.chk(!(await P.textContent('main .llave')).includes('Por definir') && (await P.textContent('main .llave .llave-ronda:last-child .pasa')).includes(campeon),
        'la llave completa, con el campeon pasando en la final');
  await O.p.goto(`${BASE}/panel.php`);
  t.chk((await O.p.textContent(`#liga-${idc}`)).includes('Torneo terminado') && (await O.p.textContent(`#liga-${idc} .fila strong`)) === campeon,
        'el panel muestra el torneo terminado y su campeon');

  // --- 3. Suizo --------------------------------------------------------
  console.log('--- 3. Suizo ---');
  const suizo = `Suizo Formatos ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.check('#formato-suizo');
  await O.p.fill('input[name="nombre"]', suizo);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Ajedrez' });
  await O.p.check('#compite-jugadores');
  await O.p.fill('input[name="cupo"]', '8');
  await O.p.selectOption('select[name="rondas"]', '3');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  const ids = parseInt(sql(`SELECT id_torneo FROM torneo WHERE nombre = ${texto(suizo)}`), 10);
  t.chk(sql(`SELECT CONCAT(m.nombre, '|', c.rondas_previstas) FROM torneo t JOIN modulo_competencia m USING (id_modulo)
             JOIN configuracion_torneo c USING (id_torneo) WHERE t.id_torneo = ${ids}`) === 'Suizo|3', 'el suizo queda creado, a 3 rondas');
  for (const [n, a] of [['Pitágoras', 'Samos'], ['Euclides', 'Megara'], ['Arquímedes', 'Siracusa'], ['Hipatia', 'Egipto'], ['Eratóstenes', 'Cirene']]) {
    const j = await c.cuentaNueva(nav, n, a);
    const J = await c.entrar(nav, j);
    await J.p.goto(`${BASE}/torneo.php?id=${ids}`);
    await Promise.all([J.p.waitForNavigation(), J.p.click('form.pedir-lugar button')]);
    await J.ctx.close();
  }
  t.chk(sql(`SELECT COUNT(*) FROM participante WHERE id_torneo = ${ids}`) === '5', 'cinco jugadores inscriptos solos');
  await c.postear(O.ctx, url_panel, { token_csrf: tko, accion: 'cerrar', id_torneo: String(ids) });
  await O.p.goto(`${BASE}/panel.php`);
  t.chk((await O.p.textContent(`#liga-${ids} form.accion-liga button`)).startsWith('Armar la primera ronda'), 'el panel ofrece "Armar la primera ronda"');
  await Promise.all([O.p.waitForNavigation(), O.p.click(`#liga-${ids} form.accion-liga button`)]);
  const jugarSuizo = async () => {
    await O.p.goto(`${url_partidos}?id=${ids}`);
    const partidos = await O.p.$$eval('#fecha form.cargar-resultado input[name="id_enfrentamiento"]', l => l.map(e => e.value));
    for (const id of partidos) {
      await O.p.fill(`#partido-${id} input[name="local"]`, '2');
      await O.p.fill(`#partido-${id} input[name="visitante"]`, '1');
      await Promise.all([O.p.waitForNavigation(), O.p.click(`#partido-${id} form.cargar-resultado button`)]);
    }
    await Promise.all([O.p.waitForNavigation(), O.p.click('#fecha form.accion-liga input[value="cerrar_fecha"] ~ button')]);
  };
  await O.p.goto(`${url_partidos}?id=${ids}`);
  t.chk(await O.p.$eval('#fecha h2', h => h.textContent) === 'Ronda 1' && (await O.p.textContent('#fecha')).includes('suma como partido ganado'),
        'Ronda 1 en juego, con el libre que suma');
  await jugarSuizo();
  await jugarSuizo();
  t.chk(sql(`SELECT COUNT(*) FROM ronda WHERE id_torneo = ${ids}`) === '3' && sql(`SELECT estado FROM torneo WHERE id_torneo = ${ids}`) === 'en_curso',
        'dos rondas cerradas: la tercera armada');
  await jugarSuizo();
  t.chk(sql(`SELECT estado FROM torneo WHERE id_torneo = ${ids}`) === 'finalizado', 'la tercera cerrada termina el suizo');
  const repetidos = sql(`SELECT COUNT(*) FROM (SELECT LEAST(id_participante_local, id_participante_visitante) a,
                                                      GREATEST(id_participante_local, id_participante_visitante) b, COUNT(*) n
                                               FROM enfrentamiento e JOIN ronda r USING (id_ronda)
                                               WHERE r.id_torneo = ${ids} AND e.id_participante_visitante IS NOT NULL
                                               GROUP BY a, b HAVING n > 1) x`);
  const libres = sql(`SELECT COUNT(DISTINCT id_participante_local) FROM enfrentamiento e JOIN ronda r USING (id_ronda)
                      WHERE r.id_torneo = ${ids} AND e.id_participante_visitante IS NULL`);
  t.chk(repetidos === '0' && libres === '3', 'ningun cruce repetido, y tres libres distintos');
  await P.goto(`${BASE}/torneo.php?id=${ids}#posiciones`);
  const primero = await P.$eval('#posiciones tbody tr td:nth-child(2)', e => e.textContent.replace(' · clasifica a playoffs', ''));
  t.chk(await P.$eval('#posiciones .pestanas a.activo', a => a.textContent) === 'Posiciones'
        && (await P.textContent('#resumen .tarjeta-olivo h3')) === primero, `el suizo tiene tabla, y su primero es el campeon (${primero})`);
  t.chk((await P.textContent('#reglas')).includes('Sistema suizo de 5 jugadores, a 3 rondas'), 'Reglas cuenta el suizo');

  await pub.close();
  t.fin();
})().catch(e => { console.error(e); process.exitCode = 1; }).finally(async () => { if (nav) await nav.close(); });
