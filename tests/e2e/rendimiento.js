// =====================================================================
// Rendimiento fisico (RF-29) - Stadion (Agon)
// ---------------------------------------------------------------------
//   node tests/e2e/rendimiento.js
//
// La pestana Rendimiento del perfil, en la pantalla Y en la base:
//   1. sin mediciones: una raya en cada indicador, sin grafico, los tres
//      formularios
//   2. cargar con clics de verdad las tres pruebas: el aviso con el
//      indicador calculado (128 ms, 3,78 m/s2, 583 W), la fila en la base
//      con solo los datos crudos, y la auditoria
//   3. coma decimal; los errores al lado de su formulario (fuera de
//      rango, fecha futura, sin masa, una prueba inventada, un campo
//      mandado como arreglo): el "Aviso ·" del titulo, aria-invalid, lo
//      escrito queda, y nada se guarda
//   4. el grafico de la evolucion (dos o mas mediciones), la tabla de
//      mediciones y la comparacion con otra cuenta
//   5. borrar con clics: solo una propia; sin token, 403; sin sesion, al
//      acceso
//   6. el telefono: sin desborde, el grafico sin los dos valores
//
// SOLO TOCA LO QUE ELLA MISMA CREA, igual que sesion.js: cuentas de
// @ejemplo.invalid con contrasena al azar. Al final se borran sus
// cuentas y su auditoria (las mediciones se van con las cuentas), y se
// comprueba que la base (CHECKSUM TABLE) quedo como estaba.
// =====================================================================
'use strict';
const c = require('./comun');
const { BASE, sql } = c;

let nav;
(async () => {
  const t = c.contador('Rendimiento fisico (RF-29)');
  const antes = c.huellaBase();
  const propias = [];
  nav = await c.navegador();
  try {
    const H = await c.cuentaNueva(nav, 'Hipatia', 'Alejandría', propias);
    const T = await c.cuentaNueva(nav, 'Tales', 'Mileto', propias);
    const O = await c.entrar(nav, H, { viewport: { width: 1024, height: 900 } });
    const p = O.p;
    const cuantas = () => parseInt(sql(`SELECT COUNT(*) FROM medicion WHERE id_usuario = ${H.id}`), 10);
    const hoy = sql("SELECT DATE(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-03:00'))");
    const form = prueba => `#cargar-medicion form.medicion:has(input[name="prueba"][value="${prueba}"])`;

    // --- 1. Sin mediciones -------------------------------------------------------
    console.log('--- 1. Sin mediciones ---');
    await p.goto(`${BASE}/perfil.php#rendimiento`);
    const accion = await p.$eval(form('reaccion'), f => f.action);
    const url = accion.split('#')[0];
    const vacio = await p.evaluate(() => ({
      valores: [...document.querySelectorAll('#rendimiento .medida .valor')].map(x => x.textContent),
      sin: (document.querySelector('#rendimiento').textContent.match(/Sin mediciones/g) || []).length,
      svg: document.querySelectorAll('#rendimiento svg.grafico').length,
      tabla: !!document.querySelector('#mis-mediciones'),
      etiquetas: [...document.querySelectorAll('#cargar-medicion legend')].map(x => x.textContent) }));
    t.chk(vacio.valores.join() === '—,—,—' && vacio.sin === 3 && vacio.svg === 0 && !vacio.tabla,
          'una raya en cada indicador, "Sin mediciones", sin grafico ni tabla');
    t.chk(vacio.etiquetas.join(' | ') === 'Tiempo de reacción | Aceleración de salida | Potencia de salto',
          `un formulario por prueba (${vacio.etiquetas.join(', ')})`);
    t.chk(await p.$eval(`${form('reaccion')} input[name="fecha"]`, (e, h) => e.value === h && e.max === h && e.required, hoy),
          'la fecha viene en hoy, y no deja pasar de hoy');

    // --- 2. Cargar con clics ---------------------------------------------------
    console.log('--- 2. Cargar las tres pruebas ---');
    const guardar = async (prueba, campos) => {
      for (const [n, v] of Object.entries(campos)) await p.fill(`${form(prueba)} input[name="${n}"]`, v);
      await Promise.all([p.waitForNavigation(), p.click(`${form(prueba)} button[type="submit"]`)]);
      return ((await p.textContent('[role="status"]').catch(() => '')) || '').trim();
    };
    let aviso = await guardar('reaccion', { distancia: '8' });
    t.chk(aviso === 'Tiempo de reacción: 128 ms. La medición queda guardada.', `reaccion, 8 cm: "${aviso}"`);
    t.chk(p.url().endsWith('#rendimiento') && await p.isVisible('#rendimiento .medida'), 'vuelve a la pestana Rendimiento');
    const fila = sql(`SELECT CONCAT(prueba, '|', distancia_cm, '|', IFNULL(tiempo_s, 'NULL'), '|', IFNULL(masa_kg, 'NULL'), '|', fecha)
                      FROM medicion WHERE id_usuario = ${H.id}`);
    t.chk(fila === `reaccion|8.0|NULL|NULL|${hoy}`, `en la base, solo el dato crudo (${fila})`);
    t.chk(sql(`SELECT COUNT(*) FROM auditoria a JOIN medicion m ON m.id_medicion = a.id_registro
               WHERE a.accion = 'medicion' AND a.tabla_afectada = 'medicion' AND a.id_usuario = ${H.id}
               AND a.detalle = CONCAT('Tiempo de reacción · ', m.fecha)`) === '1', 'la carga queda en la auditoria');
    aviso = await guardar('sprint', { tiempo: '2.30', masa: '68' });
    t.chk(aviso === 'Aceleración de salida: 3,78 m/s². La medición queda guardada.', `sprint, 2,30 s y 68 kg: "${aviso}"`);
    aviso = await guardar('salto', { tiempo: '0.45', masa: '60' });
    t.chk(aviso === 'Potencia de salto: 583 W. La medición queda guardada.', `salto, 0,45 s y 60 kg: "${aviso}"`);
    const valores = await p.$$eval('#rendimiento .medida .valor', l => l.map(x => x.textContent));
    t.chk(valores.join(' · ') === '128 · 3,78 · 583', `los tres indicadores, con coma decimal (${valores.join(' · ')})`);
    t.chk(sql(`SELECT GROUP_CONCAT(CONCAT(prueba, ':', IFNULL(distancia_cm, '-'), ':', IFNULL(tiempo_s, '-'), ':', IFNULL(masa_kg, '-')) ORDER BY id_medicion)
               FROM medicion WHERE id_usuario = ${H.id}`) === 'reaccion:8.0:-:-,sprint:-:2.30:68.0,salto:-:0.45:60.0',
          'tres filas, cada una con los datos de su prueba');

    // --- 3. Coma decimal y errores ---------------------------------------------
    console.log('--- 3. Coma decimal y errores ---');
    const tk = await c.token(p);
    let r = await c.postear(O.ctx, url, { token_csrf: tk, accion: 'medicion', prueba: 'reaccion', fecha: '2026-09-08', distancia: '12,0' });
    t.chk(r.cuerpo.includes('Tiempo de reacción: 156 ms.') && sql(`SELECT distancia_cm FROM medicion WHERE id_usuario = ${H.id} AND fecha = '2026-09-08'`) === '12.0',
          'con coma decimal tambien ("12,0": 156 ms)');
    const n_antes = cuantas();
    const error = async (campos, prueba, texto, desc) => {
      const res = await c.postear(O.ctx, url, { token_csrf: tk, accion: 'medicion', ...campos });
      const bien = res.estado === 200 && res.cuerpo.includes('<title>Aviso · ') && res.cuerpo.includes(texto)
                   && (prueba === null || (res.cuerpo.includes(`<p class="error-campo" id="error-medicion-${prueba}">${texto}</p>`)
                                           && res.cuerpo.includes(`aria-describedby="error-medicion-${prueba}" aria-invalid="true"`)));
      t.chk(bien && cuantas() === n_antes, `${desc}: "${texto}", y nada se guarda`);
      return res;
    };
    r = await error({ prueba: 'reaccion', fecha: hoy, distancia: '31' }, 'reaccion', 'La regla mide 30 cm: la distancia va de 0,5 a 30.', 'una regla de 31 cm');
    t.chk(r.cuerpo.includes('name="distancia" value="31"'), 'lo escrito queda en el campo');
    const manana = sql("SELECT DATE(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-03:00')) + INTERVAL 1 DAY");
    await error({ prueba: 'reaccion', fecha: manana, distancia: '10' }, 'reaccion', 'La fecha de la medición va hasta hoy.', 'la fecha de mañana');
    await error({ prueba: 'sprint', fecha: hoy, tiempo: '2,3' }, 'sprint', 'La masa va de 20 a 200 kg.', 'un sprint sin masa');
    await error({ prueba: 'salto', fecha: hoy, tiempo: '1,5', masa: '60' }, 'salto', 'El tiempo de vuelo va de 0,1 a 1 segundo.', 'un vuelo de 1,5 s');
    await error({ prueba: 'salto', fecha: '2026-02-30', tiempo: '0,4', masa: '60' }, 'salto', 'La fecha de la medición no es una fecha válida.', 'el 30 de febrero');
    await error({ prueba: 'natacion', fecha: hoy, distancia: '10' }, null, 'La prueba no es una de las previstas.', 'una prueba inventada (en la lista de arriba)');
    await error({ prueba: 'reaccion', fecha: hoy, 'distancia[]': '10' }, 'reaccion', 'La regla mide 30 cm: la distancia va de 0,5 a 30.',
                'la distancia mandada como arreglo (sin error de PHP)');

    // --- 4. Grafico, tabla y comparacion ------------------------------------------
    console.log('--- 4. Grafico, tabla y comparacion ---');
    sql(`INSERT INTO medicion (id_usuario, prueba, distancia_cm, fecha) VALUES (${T.id}, 'reaccion', 7.5, '2026-10-01')`);
    await p.goto(`${BASE}/perfil.php#rendimiento`);
    const g = await p.evaluate(() => {
      const svgs = [...document.querySelectorAll('#rendimiento svg.grafico')];
      const s = svgs[0];
      return { cuantos: svgs.length, rol: s && s.getAttribute('role'), nombre: s && s.getAttribute('aria-label'),
               puntos: s ? s.querySelectorAll('circle.punto').length : 0,
               titulos: s ? [...s.querySelectorAll('circle.punto title')].map(x => x.textContent) : [],
               datos: s ? [...s.querySelectorAll('.rotulo-dato')].map(x => x.textContent) : [],
               tarjeta: document.querySelector('#rendimiento .grilla .tarjeta small').textContent };
    });
    t.chk(g.cuantos === 1 && g.rol === 'img' && g.puntos === 2, 'un grafico, solo el del tiempo de reaccion (la unica prueba con dos mediciones)');
    t.chk(g.nombre.startsWith('Tiempo de reacción en 2 mediciones, del 8 de septiembre de 2026 al ') && g.nombre.endsWith(': de 156 a 128 milisegundos.'),
          `su nombre para el lector de pantalla: "${g.nombre}"`);
    t.chk(g.titulos.join(' | ').startsWith('08/09: 156 ms | ') && g.datos.join() === '156,128', `cada punto con su <title>, y los dos valores escritos (${g.datos.join(', ')})`);
    t.chk(g.tarjeta.startsWith('2 mediciones · la última, el '), `la tarjeta cuenta las mediciones ("${g.tarjeta}")`);
    const filas = await p.$$eval('#mis-mediciones tbody tr', l => l.map(x => [...x.querySelectorAll('td')].slice(0, 4).map(d => d.textContent.trim()).join(' | ')));
    t.chk(filas.length === 4 && filas[3] === '08/09/2026 | Tiempo de reacción | 156 ms | 12,0 cm', `la tabla, de la mas nueva a la mas vieja (la ultima: ${filas[3]})`);
    t.chk(filas.some(f => f.endsWith('| 3,78 m/s² | 2,30 s · 68,0 kg')), 'el dato crudo del sprint: "2,30 s · 68,0 kg"');
    t.chk(await p.$eval('#mis-mediciones .tabla-scroll', e => e.tabIndex === 0 && e.getAttribute('role') === 'region' && e.getAttribute('aria-label') === 'Mediciones'),
          'la tabla se desplaza con el teclado (tabindex, region y su nombre)');
    const comparado = await p.$$eval('#rendimiento table:not(#mis-mediciones table) tbody', ts => ts.map(tb => [...tb.querySelectorAll('tr')].map(tr => ({
      propia: tr.classList.contains('clasifica'), nombre: tr.querySelectorAll('td')[1].textContent, valor: tr.querySelectorAll('td')[2].textContent }))));
    const reac = comparado[0];
    const iT = reac.findIndex(x => x.nombre.startsWith('T. Mileto'));
    const iH = reac.findIndex(x => x.nombre.startsWith('H. Alejandría'));
    t.chk(iT >= 0 && iH > iT && reac[iT].valor === '124' && reac[iH].valor === '128', 'la comparacion: el tiempo menor primero (T. Mileto 124, H. Alejandría 128)');
    t.chk(reac[iH].propia && !reac[iT].propia && reac[iH].nombre === 'H. Alejandría · esta cuenta' && reac.filter(x => x.propia).length === 1,
          'la fila de esta cuenta, marcada y con " · esta cuenta" para el lector de pantalla');
    t.chk(comparado.length === 3, 'y una tabla por prueba');

    // --- 5. Borrar -----------------------------------------------------------------
    console.log('--- 5. Borrar ---');
    const boton = '#mis-mediciones button[aria-label="Borrar la medición de tiempo de reacción del 8 de septiembre de 2026"]';
    t.chk(await p.$(boton) !== null, 'cada "Borrar" dice que medicion borra');
    const id_borrada = sql(`SELECT id_medicion FROM medicion WHERE id_usuario = ${H.id} AND fecha = '2026-09-08'`);
    await Promise.all([p.waitForNavigation(), p.click(boton)]);
    t.chk((await p.textContent('[role="status"]')).trim() === 'La medición queda borrada.' && cuantas() === 3
          && sql(`SELECT COUNT(*) FROM medicion WHERE id_medicion = ${id_borrada}`) === '0', 'con un clic queda borrada');
    t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'baja' AND tabla_afectada = 'medicion' AND id_registro = ${id_borrada}
               AND id_usuario = ${H.id} AND detalle = 'Baja de medición'`) === '1', 'la baja queda en la auditoria');
    const id_ajena = sql(`SELECT id_medicion FROM medicion WHERE id_usuario = ${T.id}`);
    r = await c.postear(O.ctx, url, { token_csrf: tk, accion: 'borrar_medicion', id_medicion: id_ajena });
    t.chk(r.cuerpo.includes('Esa medición no es de esta cuenta.') && sql(`SELECT COUNT(*) FROM medicion WHERE id_medicion = ${id_ajena}`) === '1',
          'una medicion de otra cuenta no se borra');
    const id_propia = sql(`SELECT MIN(id_medicion) FROM medicion WHERE id_usuario = ${H.id}`);
    r = await c.postear(O.ctx, url, { accion: 'borrar_medicion', id_medicion: id_propia });
    t.chk(r.estado === 403 && cuantas() === 3, 'sin el token: 403 y no se borra');
    r = await c.postear(O.ctx, url, { token_csrf: 'inventado', accion: 'medicion', prueba: 'reaccion', fecha: hoy, distancia: '9' });
    t.chk(r.estado === 403 && cuantas() === 3, 'con un token inventado: 403 y no se guarda');
    const v = await nav.newContext();
    r = await c.postear(v, url, { token_csrf: tk, accion: 'medicion', prueba: 'reaccion', fecha: hoy, distancia: '9' });
    t.chk(r.estado === 302 && r.destino.endsWith('/login.php') && cuantas() === 3, 'sin sesion: al acceso, sin guardar nada');
    await v.close();

    // --- 6. El telefono ------------------------------------------------------------
    console.log('--- 6. El telefono ---');
    sql(`INSERT INTO medicion (id_usuario, prueba, distancia_cm, tiempo_s, masa_kg, fecha)
         VALUES (${H.id}, 'sprint', NULL, 2.45, 68, '2026-09-02'), (${H.id}, 'reaccion', 12, NULL, NULL, '2026-09-08')`);
    for (const ancho of [390, 768]) {
      await p.setViewportSize({ width: ancho, height: 900 });
      await p.goto(`${BASE}/perfil.php#rendimiento`);
      const m = await p.evaluate(() => ({ ancho: document.documentElement.scrollWidth,
        datos: [...document.querySelectorAll('#rendimiento .rotulo-dato')].filter(x => getComputedStyle(x).display !== 'none').length,
        dato: [...document.querySelectorAll('#mis-mediciones .dato')].every(x => getComputedStyle(x).whiteSpace === 'nowrap') }));
      t.chk(m.ancho <= ancho && m.dato && m.datos === (ancho < 768 ? 0 : 4),
            `${ancho} px: sin desborde (${m.ancho}), los datos crudos sin partirse, ${m.datos} valores escritos en los graficos`);
    }
  } finally {
    c.limpiarCuentas(propias);
  }
  t.chk(c.huellaBase() === antes, 'la base quedo como estaba (CHECKSUM TABLE de usuario, usuario_rol, pedido_rol, auditoria y medicion)');
  t.fin();
})().catch(e => { console.error(e); process.exitCode = 1; }).finally(async () => { if (nav) await nav.close(); });
