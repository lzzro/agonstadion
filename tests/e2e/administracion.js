// =====================================================================
// La administracion completa (RF-24 y RF-11) - Stadion (Agon)
// ---------------------------------------------------------------------
//   node tests/e2e/administracion.js
//
// Con cuentas nuevas, mira en la pantalla Y en la base:
//   1. los modulos: deshabilitar el suizo con un clic; "Nuevo torneo" ya
//      no lo ofrece y un POST armado con el suizo da su error; volver a
//      habilitarlo; la auditoria
//   2. la baja logica (RF-11): sin la casilla, no; con ella, la cuenta
//      queda de baja, su sesion abierta se cierra en el pedido siguiente
//      y el inicio de sesion la rechaza; reactivarla
//   3. las cuentas administrativas: sumar a la administracion (la cuenta
//      entra a la pagina) y quitar (403); la cuenta propia y una de
//      muestra no cambian
//   4. el registro de auditoria: el filtro por accion y las paginas
//   5. sin token, 403 y nada cambia
//
// SOLO TOCA LO QUE ELLA MISMA CREA, como sesion.js: al final borra sus
// cuentas y su auditoria, deja los modulos como estaban y comprueba que
// la base (CHECKSUM TABLE) quedo igual.
// =====================================================================
'use strict';
const c = require('./comun');
const { BASE, sql, texto } = c;

let nav;
(async () => {
  const t = c.contador('La administracion completa (RF-24 y RF-11)');
  const antes = c.huellaBase();
  const modulos_antes = sql('SELECT GROUP_CONCAT(CONCAT(id_modulo, ":", habilitado) ORDER BY id_modulo) FROM modulo_competencia');
  const propias = [];
  nav = await c.navegador();
  try {
    sql('UPDATE modulo_competencia SET habilitado = 1');
    const A = await c.cuentaNueva(nav, 'Ariadna', 'Minos', propias);
    const O = await c.cuentaNueva(nav, 'Olga', 'Organiza', propias);
    const T = await c.cuentaNueva(nav, 'Teseo', 'Atenas', propias);
    c.darRol(A, 'administrador');
    c.darRol(O, 'organizador');
    const AD = await c.entrar(nav, A, { viewport: { width: 1024, height: 900 } });
    const OR = await c.entrar(nav, O);
    const TE = await c.entrar(nav, T);
    const ADMIN = await c.direccionDe(AD.p, 'admin.php');
    const CREAR = await c.direccionDe(OR.p, 'crear.php');
    const tk = await c.token(AD.p);
    const aviso = async () => ((await AD.p.textContent('[role="status"]').catch(() => '')) || '').trim();

    // --- 1. Modulos ---------------------------------------------------------------
    console.log('--- 1. Modulos ---');
    await AD.p.goto(`${ADMIN}#modulos`);
    const estados = await AD.p.$$eval('#modulos .modulo', l => l.map(m => m.querySelector('strong').textContent + ':' + m.querySelector('.modulo-estado .etiqueta').textContent));
    t.chk(estados.join(' ') === 'Liga:Habilitado Eliminación directa:Habilitado Suizo:Habilitado' && await AD.p.$('#modulos .muestra') === null,
          `los tres modulos, reales y habilitados (${estados.join(', ')})`);
    await Promise.all([AD.p.waitForNavigation(), AD.p.click('#modulos button:has-text("Deshabilitar el módulo Suizo")')]);
    t.chk((await aviso()).startsWith('El módulo Suizo queda deshabilitado') && sql("SELECT habilitado FROM modulo_competencia WHERE nombre = 'Suizo'") === '0',
          'un clic deshabilita el suizo (aviso y base)');
    t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'modificacion' AND tabla_afectada = 'modulo_competencia'
               AND id_usuario = ${A.id} AND detalle = 'Deshabilitación del módulo Suizo'`) === '1', 'queda en la auditoria');
    await OR.p.goto(CREAR);
    const ofrece = await OR.p.$$eval('input[name="formato"]', l => l.map(e => e.value).join(' '));
    t.chk(ofrece === 'liga eliminacion' && (await OR.p.textContent('main .intro')).startsWith('Liga o eliminación directa.'),
          `"Nuevo torneo" ya no lo ofrece (${ofrece})`);
    const tko = await c.token(OR.p);
    const ajedrez = sql(`SELECT id_disciplina FROM disciplina WHERE nombre = 'Ajedrez'`);
    const nombre_suizo = `Suizo Apagado ${c.azar()}`;
    let r = await c.postear(OR.ctx, CREAR, { token_csrf: tko, nombre: nombre_suizo, disciplina: ajedrez, compite: 'jugadores', formato: 'suizo',
      cupo: '8', vueltas: 'una', partido: 'unico', victoria: '3', empate: '1', derrota: '0', desempate: 'diferencia', inicio: '', rondas: '', sede: '', clasifican: '0' });
    t.chk(r.cuerpo.includes('id="error-formato"') && r.cuerpo.includes('Ese formato no está habilitado.')
          && sql(`SELECT COUNT(*) FROM torneo WHERE nombre = ${texto(nombre_suizo)}`) === '0', 'un POST armado con el suizo: error al lado del formato, y nada se crea');
    await AD.p.goto(`${ADMIN}#modulos`);
    await Promise.all([AD.p.waitForNavigation(), AD.p.click('#modulos button:has-text("Deshabilitar el módulo Eliminación directa")')]);
    r = await c.postear(AD.ctx, ADMIN, { token_csrf: tk, accion: 'deshabilitar_modulo', id_modulo: sql("SELECT id_modulo FROM modulo_competencia WHERE nombre = 'Liga'") });
    t.chk(r.cuerpo.includes('Siempre queda al menos un módulo de competencia habilitado.') && sql("SELECT habilitado FROM modulo_competencia WHERE nombre = 'Liga'") === '1',
          'el ultimo habilitado no se deshabilita');
    for (const m of ['Suizo', 'Eliminación directa']) {
      await AD.p.goto(`${ADMIN}#modulos`);
      await Promise.all([AD.p.waitForNavigation(), AD.p.click(`#modulos button:has-text("Habilitar el módulo ${m}")`)]);
    }
    t.chk(sql('SELECT GROUP_CONCAT(habilitado) FROM modulo_competencia') === '1,1,1', 'y vuelven a habilitarse con un clic');

    // --- 2. La baja logica (RF-11) -------------------------------------------------
    console.log('--- 2. Baja logica ---');
    await TE.p.goto(`${BASE}/perfil.php`);
    t.chk(TE.p.url().includes('perfil'), 'Teseo tiene la sesion abierta');
    r = await c.postear(AD.ctx, ADMIN, { token_csrf: tk, accion: 'baja_cuenta', id_usuario: String(T.id) });
    t.chk(r.cuerpo.includes('Dar de baja una cuenta pide marcar la confirmación.') && sql(`SELECT activo FROM usuario WHERE id_usuario = ${T.id}`) === '1',
          'sin la casilla, no se da de baja');
    await AD.p.goto(`${ADMIN}#cuentas`);
    const fila = `#cuenta-${T.id}`;
    await AD.p.check(`${fila} input[name="confirmo_baja"]`);
    await Promise.all([AD.p.waitForNavigation(), AD.p.click(`${fila} form.baja-cuenta button`)]);
    t.chk((await aviso()) === 'Teseo Atenas queda de baja: no inicia sesión, y su historial queda.'
          && sql(`SELECT activo FROM usuario WHERE id_usuario = ${T.id}`) === '0', 'con la casilla, de baja (la fila queda, activo 0)');
    t.chk((await AD.p.textContent(fila)).includes('De baja') && await AD.p.$(`${fila} button:has-text("Reactivar")`) !== null, 'la lista la muestra de baja, con "Reactivar"');
    t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'baja' AND tabla_afectada = 'usuario' AND id_registro = ${T.id} AND id_usuario = ${A.id}`) === '1',
          'la baja queda en la auditoria');
    await TE.p.goto(`${BASE}/perfil.php`);
    t.chk(/login\.php/.test(TE.p.url()), 'la sesion abierta de Teseo se cierra en el pedido siguiente');
    const intento = await nav.newContext();
    const ip = await intento.newPage();
    await ip.goto(`${BASE}/login.php`);
    await ip.fill('input[name="correo"]', T.correo);
    await ip.fill('input[name="password"]', T.clave);
    await Promise.all([ip.waitForNavigation(), ip.click('main form button[type="submit"]')]);
    t.chk((await ip.textContent('main')).includes('Esa cuenta está dada de baja.'), 'y el inicio de sesion la rechaza');
    await intento.close();
    await AD.p.goto(`${ADMIN}#cuentas`);
    await Promise.all([AD.p.waitForNavigation(), AD.p.click(`${fila} button:has-text("Reactivar")`)]);
    t.chk((await aviso()).startsWith('Teseo Atenas vuelve a estar activa') && sql(`SELECT activo FROM usuario WHERE id_usuario = ${T.id}`) === '1',
          'reactivada con un clic');
    const TE2 = await c.entrar(nav, T);
    await TE2.p.goto(`${BASE}/perfil.php`);
    t.chk(!/login\.php/.test(TE2.p.url()), 'y vuelve a iniciar sesion');

    // --- 3. Cuentas administrativas ----------------------------------------------
    console.log('--- 3. Cuentas administrativas ---');
    let res = await TE2.ctx.request.get(ADMIN, { maxRedirects: 0 });
    t.chk(res.status() === 403, 'sin el rol, la administracion responde 403');
    await AD.p.goto(`${ADMIN}#cuentas`);
    await Promise.all([AD.p.waitForNavigation(), AD.p.click(`${fila} button:has-text("Sumar a la administración")`)]);
    t.chk((await aviso()) === 'Teseo Atenas queda en la administración.', 'sumarla a la administracion, con un clic');
    res = await TE2.ctx.request.get(ADMIN, { maxRedirects: 0 });
    t.chk(res.status() === 200 && (await res.text()).includes('<h1>Administración</h1>'), 'y la cuenta entra a la pagina');
    await AD.p.goto(`${ADMIN}#cuentas`);
    await Promise.all([AD.p.waitForNavigation(), AD.p.click(`${fila} button:has-text("Quitar de la administración")`)]);
    res = await TE2.ctx.request.get(ADMIN, { maxRedirects: 0 });
    t.chk((await aviso()) === 'Teseo Atenas deja la administración.' && res.status() === 403, 'quitarla: otra vez 403');
    t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE tabla_afectada = 'usuario_rol' AND id_registro = ${T.id} AND accion IN ('alta', 'baja')`) === '2',
          'las dos quedan en la auditoria');
    t.chk((await AD.p.textContent(`#cuenta-${A.id}`)).includes('Esta cuenta: la cambia otra cuenta de la administración')
          && await AD.p.$(`#cuenta-${A.id} button`) === null, 'la fila de la cuenta propia, sin botones');
    r = await c.postear(AD.ctx, ADMIN, { token_csrf: tk, accion: 'baja_cuenta', id_usuario: String(A.id), confirmo_baja: '1' });
    t.chk(r.cuerpo.includes('La cuenta propia no cambia de estado desde acá') && sql(`SELECT activo FROM usuario WHERE id_usuario = ${A.id}`) === '1',
          'un POST armado contra la cuenta propia no hace nada');
    r = await c.postear(AD.ctx, ADMIN, { token_csrf: tk, accion: 'quitar_admin', id_usuario: String(A.id) });
    t.chk(r.cuerpo.includes('El rol propio no se quita desde acá'), 'ni le quita el rol');
    const muestra = sql('SELECT IFNULL(MIN(id_usuario), 0) FROM usuario WHERE de_muestra = 1');
    if (muestra !== '0') {
      r = await c.postear(AD.ctx, ADMIN, { token_csrf: tk, accion: 'baja_cuenta', id_usuario: muestra, confirmo_baja: '1' });
      t.chk(r.cuerpo.includes('Una cuenta de muestra no cambia de estado.'), 'una cuenta de muestra tampoco');
    }

    // --- 4. El registro --------------------------------------------------------------
    console.log('--- 4. El registro de auditoria ---');
    await AD.p.goto(`${ADMIN}?filtro=baja#auditoria`);
    const lineas = await AD.p.$$eval('#auditoria .registro-linea span:last-child', l => l.map(x => x.textContent));
    t.chk(lineas.length > 0 && lineas.every(x => x.startsWith('Baja')), `el filtro por accion: solo bajas (${lineas.length} renglones)`);
    t.chk(await AD.p.$eval('#auditoria select[name="filtro"]', s => s.value) === 'baja', 'el filtro queda elegido');
    const total = parseInt(sql('SELECT COUNT(*) FROM auditoria'), 10);
    const paginas = Math.max(1, Math.ceil(total / 30));
    await AD.p.goto(`${ADMIN}?filtro=inventada&pagina=999#auditoria`);
    const pie = (await AD.p.textContent('#auditoria .paginas-registro small')).trim();
    t.chk(pie.startsWith(`Página ${paginas} de ${paginas}`) && await AD.p.$eval('#auditoria select[name="filtro"]', s => s.value) === '',
          `un filtro inventado vale "Todas", y una pagina de mas, la ultima ("${pie}")`);
    if (paginas > 1) {
      await AD.p.goto(`${ADMIN}#auditoria`);
      await Promise.all([AD.p.waitForNavigation(), AD.p.click('#auditoria a:has-text("Anteriores")')]);
      t.chk(AD.p.url().includes('pagina=2') && (await AD.p.$$('#auditoria .registro-linea')).length > 0, '"Anteriores" lleva a la pagina 2');
    }

    // --- 5. Sin token ---------------------------------------------------------------------
    console.log('--- 5. Sin token ---');
    r = await c.postear(AD.ctx, ADMIN, { accion: 'dar_admin', id_usuario: String(T.id) });
    t.chk(r.estado === 403 && sql(`SELECT COUNT(*) FROM usuario_rol ur JOIN rol r USING (id_rol) WHERE ur.id_usuario = ${T.id} AND r.nombre = 'administrador'`) === '0',
          'sin token: 403 y nada cambia');
    r = await c.postear(AD.ctx, ADMIN, { token_csrf: 'inventado', accion: 'deshabilitar_modulo', id_modulo: sql("SELECT id_modulo FROM modulo_competencia WHERE nombre = 'Suizo'") });
    t.chk(r.estado === 403 && sql("SELECT habilitado FROM modulo_competencia WHERE nombre = 'Suizo'") === '1', 'con un token inventado, tampoco');
  } finally {
    for (const par of modulos_antes.split(',')) {
      const [id, hab] = par.split(':');
      sql(`UPDATE modulo_competencia SET habilitado = ${parseInt(hab, 10)} WHERE id_modulo = ${parseInt(id, 10)}`);
    }
    // La auditoria de los modulos lleva el id del modulo, no de una cuenta:
    // limpiarCuentas la borra por la cuenta que la hizo.
    c.limpiarCuentas(propias);
  }
  t.chk(c.huellaBase() === antes, 'la base quedo como estaba (CHECKSUM TABLE de usuario, usuario_rol, pedido_rol, auditoria y medicion)');
  t.chk(sql('SELECT GROUP_CONCAT(CONCAT(id_modulo, ":", habilitado) ORDER BY id_modulo) FROM modulo_competencia') === modulos_antes, 'y los modulos como estaban');
  t.fin();
})().catch(e => { console.error(e); process.exitCode = 1; }).finally(async () => { if (nav) await nav.close(); });
