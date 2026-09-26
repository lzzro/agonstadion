// =====================================================================
// Permisos de los roles y token CSRF - Stadion (Agon)
// ---------------------------------------------------------------------
//   node tests/e2e/permisos.js
//
// La bateria de la fase 1 del motor de torneos (pedir el rol de
// organizador y resolverlo desde la administracion), mas el token de
// todos los formularios que cambian algo:
//   1. el visitante, sin sesion: la administracion lo manda al acceso,
//      y un POST armado a mano no resuelve nada
//   2. un jugador: la administracion responde 403 y no muestra nada, y
//      un POST con su propio token tampoco resuelve nada
//   3. pedir el rol desde el perfil: sin token, con uno inventado, con
//      el rol cambiado en el formulario, dos veces; lo que frena la base
//      (un solo pendiente, 1062) y el DCL de sgdm_app sobre pedido_rol
//   4. el administrador: rechazar, aprobar, un pedido ya resuelto, dos
//      veces; el token de otra sesion
//   5. el administrador y su propio pedido: ni la aplicacion ni la base
//      (CHECK) lo dejan resolverlo
//   6. el token en los formularios de siempre: perfil, foto, portada,
//      cerrar sesion, inicio de sesion y registro; el token cambia al
//      iniciar sesion
//   7. la sesion vencida por inactividad, y volver a entrar
//
// SOLO TOCA LO QUE ELLA MISMA CREA. Las cuentas se crean en cada corrida
// (correo prueba-xxxxxxxx@ejemplo.invalid, contrasena al azar que vive
// solo en memoria) y el administrador es una de ellas, con
// sql/primer_administrador.sql. Cada consulta que mira o cambia algo
// esta limitada a esas cuentas. Al final, pase lo que pase, se borran
// sus filas (auditoria, pedidos, roles y las cuentas) y nada mas (ver
// limpiarCuentas en comun.js): la bateria compara la base antes y
// despues (CHECKSUM TABLE de usuario, usuario_rol, pedido_rol y
// auditoria) y falla si quedo distinta. Las otras cuentas de la base,
// sus roles y sus pedidos no se tocan.
//
// Variables de entorno, ademas de las de comun.js (ver tests/README.md):
//   STADION_DCL        propio (por defecto): sgdm_app no puede borrar en
//                      pedido_rol (error 1142), como da el DCL de
//                      schema.sql. cpanel: el hosting da los permisos de
//                      la base entera y no se pueden quitar por tabla; la
//                      prueba lo mira sin borrar nada (WHERE 1 = 0).
//   STADION_DB_SOCKET  el socket de MariaDB para las consultas hechas
//                      como sgdm_app (con apps/config/database.php)
//   STADION_SESIONES   la carpeta de las sesiones de PHP, para vencer una
//                      a mano (por defecto /var/lib/php/sessions). Si no
//                      existe, el paso 7 se saltea y lo dice.
// =====================================================================
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const c = require('./comun');
const { BASE, sql, texto } = c;

const RAIZ = path.resolve(__dirname, '..', '..');
const DCL = process.env.STADION_DCL || 'propio';
const SESIONES = (process.env.STADION_SESIONES !== undefined) ? process.env.STADION_SESIONES : '/var/lib/php/sessions';
if (DCL !== 'propio' && DCL !== 'cpanel') {
  console.error('STADION_DCL va en "propio" o "cpanel".');
  process.exit(2);
}

// Una imagen PNG de 1 x 1, para los formularios de foto y portada.
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');

// Las cuentas de esta corrida: las unicas que la bateria mira, cambia y
// borra.
const propias = [];
const ids = () => propias.map(x => x.id).filter(Number.isInteger).join(',') || '0';
const correos = () => propias.map(x => texto(x.correo)).join(',') || "''";

// --- Pedidos al sitio, con la cookie de la sesion de cada contexto -----
async function pedir(ctx, url) {
  const r = await ctx.request.get(url, { maxRedirects: 0 });
  return { estado: r.status(), destino: r.headers()['location'] || '', cuerpo: await r.text() };
}
async function subir(ctx, url, campos) {
  const r = await ctx.request.post(url, { multipart: campos, maxRedirects: 0 });
  return { estado: r.status(), destino: r.headers()['location'] || '', cuerpo: await r.text() };
}
const tokenEn = cuerpo => { const m = /name="token_csrf" value="([0-9a-f]+)"/.exec(cuerpo); return m ? m[1] : ''; };
async function tokenDe(ctx, url) { return tokenEn((await pedir(ctx, url)).cuerpo); }
const absoluta = (desde, destino) => new URL(destino, desde).href;
const veces = (cuerpo, frase) => cuerpo.split(frase).length - 1;

// --- La base ----------------------------------------------------------
const pendientes = () => sql(`SELECT COUNT(*) FROM pedido_rol WHERE estado = 'pendiente' AND id_usuario IN (${ids()})`);
const roles = cuenta => sql(`SELECT IFNULL(GROUP_CONCAT(r.nombre ORDER BY r.nombre), '') FROM usuario_rol ur
                             JOIN rol r USING (id_rol) WHERE ur.id_usuario = ${cuenta.id}`);
const nombre = cuenta => sql(`SELECT nombre FROM usuario WHERE id_usuario = ${cuenta.id}`);
const foto = (cuenta, columna) => sql(`SELECT IFNULL(${columna}, '-') FROM usuario WHERE id_usuario = ${cuenta.id}`);
const rolesAjenos = () => sql(`SELECT COUNT(*), IFNULL(SUM(CRC32(CONCAT(id_usuario, '-', id_rol))), 0) FROM usuario_rol WHERE id_usuario NOT IN (${ids()})`);

// Una sentencia como sgdm_app, la cuenta de la aplicacion (con
// apps/config/database.php y el socket de STADION_DB_SOCKET): devuelve el
// numero de error, 0 si anduvo, o "filas:N" si es una consulta. Antes de
// usarla, la bateria comprueba que sgdm_app ve la misma base que
// STADION_MYSQL (ver mismaBase); si no, no sigue.
function comoApp(sentencia) {
  const args = [];
  if (process.env.STADION_DB_SOCKET) args.push('-d', `mysqli.default_socket=${process.env.STADION_DB_SOCKET}`);
  args.push('-r', 'require $argv[1] . "/apps/config/database.php"; mysqli_report(MYSQLI_REPORT_OFF);'
                + ' $c = conectarBD(); if ($c === null) { echo "sin conexion"; exit; } $r = $c->query($argv[2]);'
                + ' echo ($r instanceof mysqli_result) ? "filas:" . $r->num_rows : $c->errno;',
            RAIZ, sentencia);
  try {
    return execFileSync('php', args, { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
  } catch (e) {
    return 'no corre';
  }
}
// Una cuenta de la corrida, por id y por correo a la vez: en otra base,
// ese par no existe y la sentencia no alcanza a nadie.
const laCuenta = cuenta => `SELECT id_usuario FROM usuario WHERE id_usuario = ${cuenta.id} AND correo = ${texto(cuenta.correo)}`;
const mismaBase = cuenta => comoApp(laCuenta(cuenta)) === 'filas:1';

let nav;
(async () => {
  const t = c.contador(`Permisos de los roles y token CSRF (DCL ${DCL})`);
  const antes = { huella: c.huellaBase(), roles: rolesAjenos() };
  nav = await c.navegador();
  try {
    // --- Las cuentas de esta corrida ------------------------------------
    const admin = await c.cuentaNueva(nav, 'Ariadna', 'Prueba', propias);
    const jug = await c.cuentaNueva(nav, 'Odiseo', 'Prueba', propias);
    const nueva = { correo: `prueba-${c.azar()}@ejemplo.invalid`, clave: 'x' + c.azar(12) };   // la del registro, paso 6
    propias.push(nueva);
    t.chk(Number.isInteger(admin.id) && Number.isInteger(jug.id), `dos cuentas nuevas de @ejemplo.invalid (${admin.id} y ${jug.id})`);
    sql(fs.readFileSync(path.join(RAIZ, 'sql', 'primer_administrador.sql'), 'utf8').replace(/CORREO_DE_LA_CUENTA/g, admin.correo));
    t.chk(roles(admin) === 'administrador,jugador', 'primer administrador por SQL (sql/primer_administrador.sql), en una cuenta de la corrida');
    // Las consultas como sgdm_app (comoApp) tienen que ir a la misma base.
    if (!t.chk(mismaBase(jug), 'sgdm_app ve la misma base que la prueba (su cuenta nueva, por id y correo; ver STADION_DB_SOCKET)')) {
      throw new Error('sgdm_app mira otra base: la bateria no sigue');
    }

    // Las direcciones de los controladores, como las dejo cada
    // instalacion: la local y la del hosting son distintas.
    const anon = await nav.newContext();
    const a = await pedir(anon, `${BASE}/admin.php`);
    const ADMIN = absoluta(`${BASE}/admin.php`, a.destino);
    const PERFIL = absoluta(`${BASE}/perfil.php`, (await pedir(anon, `${BASE}/perfil.php`)).destino);
    const accion = (cuerpo, antes_de) => (new RegExp(`<form[^>]*action="([^"]+)"[^>]*${antes_de}`).exec(cuerpo) || [])[1] || '';
    const LOGIN = absoluta(`${BASE}/login.php`, accion((await pedir(anon, `${BASE}/login.php`)).cuerpo, 'method="post"'));
    const REG = absoluta(`${BASE}/registro.php`, accion((await pedir(anon, `${BASE}/registro.php`)).cuerpo, 'id="alta"'));
    t.chk(/admin/i.test(ADMIN) && /perfil/i.test(PERFIL) && /login/i.test(LOGIN) && /regist/i.test(REG),
          'las direcciones de los controladores salen de las paginas (admin.php, perfil.php, login.php, registro.php)');

    // --- 1. El visitante, sin sesion ------------------------------------
    console.log('--- 1. El visitante, sin sesion ---');
    let r = await pedir(anon, ADMIN);
    t.chk(r.estado === 302 && r.destino.endsWith('/login.php'), `GET a la administracion sin sesion: 302 al acceso (${r.destino})`);
    sql(`INSERT INTO pedido_rol (id_usuario, id_rol) SELECT ${jug.id}, id_rol FROM rol WHERE nombre = 'organizador'`);
    const P = sql(`SELECT id_pedido_rol FROM pedido_rol WHERE id_usuario = ${jug.id} AND estado = 'pendiente'`);
    r = await c.postear(anon, ADMIN, { accion: 'aprobar', id_pedido: P, token_csrf: 'x' });
    t.chk(r.estado === 302, 'POST aprobar sin sesion: 302 al acceso');
    t.chk(pendientes() === '1', '  ...el pedido sigue pendiente');
    t.chk((await pedir(anon, `${BASE}/admin.php`)).estado === 302, 'admin.php sin sesion: 302');
    r = await pedir(anon, `${BASE}/admin.html`);
    t.chk(r.estado === 301 && absoluta(`${BASE}/admin.html`, r.destino) === `${BASE}/admin.php`, `la vieja admin.html: 301 a admin.php (${r.destino})`);

    // --- 2. Un jugador ---------------------------------------------------
    console.log('--- 2. Un jugador ---');
    const J = await c.entrar(nav, jug);
    t.chk(J.p.url().includes('perfil') || (await J.p.textContent('body')).includes('queda abierta'), 'el jugador inicia sesion');
    r = await pedir(J.ctx, ADMIN);
    t.chk(r.estado === 403, 'GET a la administracion como jugador: 403');
    t.chk(veces(r.cuerpo, 'Pedidos de rol') + veces(r.cuerpo, admin.correo) + veces(r.cuerpo, 'Registro de auditor') === 0,
          '  ...sin nada de la administracion');
    t.chk(r.cuerpo.includes('solo para la administracion.'), '  ...con el aviso');
    let TJ = await tokenDe(J.ctx, PERFIL);
    r = await c.postear(J.ctx, ADMIN, { accion: 'aprobar', id_pedido: P, token_csrf: TJ });
    t.chk(r.estado === 403, 'POST aprobar como jugador, con su token valido: 403');
    t.chk(pendientes() === '1', '  ...el pedido sigue pendiente');
    t.chk(roles(jug) === 'jugador', '  ...y el jugador sigue sin el rol de organizador');
    r = await c.postear(J.ctx, ADMIN, { accion: 'rechazar', id_pedido: P, token_csrf: TJ });
    t.chk(r.estado === 403, 'POST rechazar como jugador: 403');
    t.chk(pendientes() === '1', '  ...el pedido sigue pendiente');
    sql(`DELETE FROM pedido_rol WHERE id_pedido_rol = ${P} AND id_usuario = ${jug.id}`);

    // --- 3. Pedir el rol desde el perfil ---------------------------------
    console.log('--- 3. Pedir el rol desde el perfil ---');
    r = await pedir(J.ctx, PERFIL);
    TJ = tokenEn(r.cuerpo);
    t.chk(veces(r.cuerpo, 'Pedir el rol de organizador') === 1, 'el perfil muestra el boton');
    r = await c.postear(J.ctx, PERFIL, { accion: 'pedir_rol' });
    t.chk(r.estado === 403 && pendientes() === '0', 'pedir sin token: 403, y ningun pedido');
    r = await c.postear(J.ctx, PERFIL, { accion: 'pedir_rol', token_csrf: '0123abcd' });
    t.chk(r.estado === 403 && pendientes() === '0', 'pedir con un token inventado: 403, y ningun pedido');
    r = await c.postear(J.ctx, PERFIL, { accion: 'pedir_rol', rol: 'administrador', token_csrf: TJ });
    t.chk(r.estado === 200, 'pedir con token (y rol=administrador en el formulario): 200');
    t.chk(pendientes() === '1', '  ...un pedido pendiente');
    t.chk(sql(`SELECT r.nombre FROM pedido_rol p JOIN rol r USING (id_rol) WHERE p.id_usuario = ${jug.id}`) === 'organizador',
          '  ...de organizador, no de administrador: el rol no se lee del formulario');
    t.chk(r.cuerpo.includes('pedido en revisión') && !r.cuerpo.includes('Pedir el rol de organizador'), '  ...el perfil dice "en revision", sin el boton');
    t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'pedido_rol' AND id_usuario = ${jug.id}
               AND id_registro = (SELECT id_pedido_rol FROM pedido_rol WHERE id_usuario = ${jug.id})`) === '1', '  ...y queda en la auditoria');
    r = await c.postear(J.ctx, PERFIL, { accion: 'pedir_rol', token_csrf: TJ });
    t.chk(r.cuerpo.includes('Ya hay un pedido en revision.') && pendientes() === '1', 'pedir otra vez: aviso, y sigue uno solo');
    t.chk(comoApp(`INSERT INTO pedido_rol (id_usuario, id_rol) SELECT u.id_usuario, r.id_rol FROM (${laCuenta(jug)}) u
                   JOIN rol r ON r.nombre = 'organizador'`) === '1062',
          'la base frena el segundo pendiente (sgdm_app: error 1062)');
    // El permiso se mira sin borrar nada (WHERE 1 = 0).
    if (DCL === 'propio') {
      t.chk(comoApp('DELETE FROM pedido_rol WHERE 1 = 0') === '1142', 'sgdm_app no puede borrar pedidos (DCL: error 1142)');
    } else {
      t.chk(comoApp('DELETE FROM pedido_rol WHERE 1 = 0') === '0',
            'sgdm_app con los permisos de cPanel (la base entera): el DELETE se permite; aca no borra nada');
    }
    t.chk(pendientes() === '1', '  ...sigue uno solo');
    const P1 = sql(`SELECT id_pedido_rol FROM pedido_rol WHERE id_usuario = ${jug.id} AND estado = 'pendiente'`);

    // --- 4. El administrador ---------------------------------------------
    console.log('--- 4. El administrador ---');
    const A = await c.entrar(nav, admin);
    r = await pedir(A.ctx, ADMIN);
    const TA = tokenEn(r.cuerpo);
    t.chk(r.estado === 200 && veces(r.cuerpo, jug.correo) === 2, 'la administracion muestra el pedido y la cuenta del jugador');
    t.chk(veces((await pedir(A.ctx, PERFIL)).cuerpo, 'Administración <span aria-hidden="true">→</span>') === 1,
          'el perfil del administrador lleva a la administracion');
    t.chk(!(await pedir(J.ctx, PERFIL)).cuerpo.includes('Administración <span'), 'el del jugador, no');
    r = await c.postear(A.ctx, ADMIN, { accion: 'rechazar', id_pedido: P1 });
    t.chk(r.estado === 403 && pendientes() === '1', 'rechazar sin token: 403, y sigue pendiente');
    r = await c.postear(A.ctx, ADMIN, { accion: 'rechazar', id_pedido: P1, token_csrf: TJ });
    t.chk(r.estado === 403 && pendientes() === '1', 'rechazar con el token de otra sesion: 403, y sigue pendiente');
    // Un pedido guardado con la hora del servidor (UTC, tres horas
    // adelante), como los de antes de que la conexion pasara a la hora de
    // Montevideo: se resuelve igual, y la resolucion no queda antes.
    sql(`UPDATE pedido_rol SET fecha_pedido = NOW() + INTERVAL 3 HOUR WHERE id_pedido_rol = ${P1} AND id_usuario IN (${laCuenta(jug)})`);
    r = await c.postear(A.ctx, ADMIN, { accion: 'rechazar', id_pedido: P1, token_csrf: TA });
    t.chk(r.estado === 200, 'rechazar: 200');
    t.chk(sql(`SELECT fecha_resolucion >= fecha_pedido FROM pedido_rol WHERE id_pedido_rol = ${P1}`) === '1',
          '  ...aunque el pedido figure tres horas adelante (guardado en UTC), y la resolucion no queda antes');
    t.chk(sql(`SELECT CONCAT(estado, ' ', id_usuario_resuelve, ' ', fecha_resolucion IS NOT NULL) FROM pedido_rol WHERE id_pedido_rol = ${P1}`)
          === `rechazado ${admin.id} 1`, '  ...rechazado, con fecha y quien lo resuelve');
    t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'rechazo' AND id_usuario = ${admin.id} AND id_registro = ${P1}`) === '1',
          '  ...y queda en la auditoria');
    t.chk(roles(jug) === 'jugador', '  ...el jugador sigue sin el rol');
    r = await c.postear(A.ctx, ADMIN, { accion: 'aprobar', id_pedido: P1, token_csrf: TA });
    t.chk(r.cuerpo.includes('ya no esta pendiente.') && roles(jug) === 'jugador', 'aprobar un pedido ya rechazado: aviso, y nada cambia');
    const perfil_j = (await pedir(J.ctx, PERFIL)).cuerpo;
    t.chk(perfil_j.includes('queda rechazado') && perfil_j.includes('Pedir el rol de organizador'),
          'el perfil del jugador dice que queda rechazado, y puede pedirlo otra vez');
    r = await c.postear(J.ctx, PERFIL, { accion: 'pedir_rol', token_csrf: TJ });
    t.chk(r.estado === 200 && pendientes() === '1', 'lo pide otra vez: pendiente de nuevo');
    const P2 = sql(`SELECT id_pedido_rol FROM pedido_rol WHERE id_usuario = ${jug.id} AND estado = 'pendiente'`);
    r = await c.postear(A.ctx, ADMIN, { accion: 'aprobar', id_pedido: P2, token_csrf: TA });
    t.chk(r.estado === 200 && roles(jug) === 'jugador,organizador', 'aprobar: 200, y el jugador pasa a organizador');
    t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'aprobacion' AND id_usuario = ${admin.id} AND id_registro = ${P2}`) === '1',
          '  ...y queda en la auditoria');
    await c.postear(A.ctx, ADMIN, { accion: 'aprobar', id_pedido: P2, token_csrf: TA });
    t.chk(sql(`SELECT COUNT(*) FROM usuario_rol ur JOIN rol r USING (id_rol) WHERE ur.id_usuario = ${jug.id} AND r.nombre = 'organizador'`) === '1',
          '  ...aprobar dos veces no lo duplica');
    const perfil_o = (await pedir(J.ctx, PERFIL)).cuerpo;
    t.chk(!perfil_o.includes('Pedir el rol de organizador') && !perfil_o.includes('pedido en revisión'),
          'el perfil del organizador: sin el boton ni el pedido en revision');
    r = await c.postear(J.ctx, PERFIL, { accion: 'pedir_rol', token_csrf: TJ });
    t.chk(r.cuerpo.includes('ya tiene el rol de organizador.') && pendientes() === '0', 'si lo pide por POST armado: aviso, y ningun pendiente');
    t.chk((await pedir(J.ctx, ADMIN)).estado === 403, 'el organizador sigue sin entrar a la administracion (403)');

    // --- 5. El administrador y su propio pedido ----------------------------
    console.log('--- 5. El administrador y su propio pedido ---');
    const TAP = await tokenDe(A.ctx, PERFIL);
    r = await c.postear(A.ctx, PERFIL, { accion: 'pedir_rol', token_csrf: TAP });
    t.chk(r.estado === 200 && pendientes() === '1', 'el administrador pide el rol de organizador');
    const P3 = sql(`SELECT id_pedido_rol FROM pedido_rol WHERE id_usuario = ${admin.id} AND estado = 'pendiente'`);
    const adm = (await pedir(A.ctx, ADMIN)).cuerpo;
    t.chk(adm.includes('Lo resuelve otra cuenta de la administración.') && !adm.includes(`name="id_pedido" value="${P3}"`),
          'la administracion dice que lo resuelve otra cuenta, sin botones para ese pedido');
    r = await c.postear(A.ctx, ADMIN, { accion: 'aprobar', id_pedido: P3, token_csrf: TA });
    t.chk(r.cuerpo.includes('Un pedido propio lo resuelve otra cuenta') && pendientes() === '1' && roles(admin) === 'administrador,jugador',
          'aprobarse a si mismo: aviso, sigue pendiente, y sin el rol');
    r = await c.postear(A.ctx, ADMIN, { accion: 'rechazar', id_pedido: P3, token_csrf: TA });
    t.chk(r.cuerpo.includes('Un pedido propio') && pendientes() === '1', 'rechazarse a si mismo: aviso, y sigue pendiente');
    t.chk(comoApp(`UPDATE pedido_rol SET estado = 'aprobado', fecha_resolucion = NOW(), id_usuario_resuelve = id_usuario
                   WHERE id_pedido_rol = ${P3} AND id_usuario IN (${laCuenta(admin)})`) === '4025', 'la base tambien lo frena (CHECK, error 4025)');
    r = await c.postear(A.ctx, ADMIN, { accion: 'borrar', id_pedido: P3, token_csrf: TA });
    t.chk(r.cuerpo.includes('Solo se aprueba o se rechaza un pedido.') && pendientes() === '1', 'una accion inventada: aviso, y nada cambia');
    const inexistente = parseInt(sql('SELECT IFNULL(MAX(id_pedido_rol), 0) + 1000 FROM pedido_rol'), 10);
    r = await c.postear(A.ctx, ADMIN, { accion: 'aprobar', id_pedido: inexistente, token_csrf: TA });
    t.chk(r.cuerpo.includes('Ese pedido no existe.'), 'un pedido que no existe: aviso');
    const nombre_j = nombre(jug);
    r = await c.postear(J.ctx, PERFIL, { accion: 'datos', nombre: 'Robado', apellido: 'X', token_csrf: TA });
    t.chk(r.estado === 403 && nombre(jug) === nombre_j, 'el token del administrador en la sesion del jugador: 403, y el nombre intacto');

    // --- 6. El token en los formularios de siempre -------------------------
    console.log('--- 6. El token en los formularios de siempre ---');
    r = await c.postear(J.ctx, PERFIL, { accion: 'datos', nombre: 'Cambio', apellido: 'X' });
    t.chk(r.estado === 403 && r.cuerpo.includes('El formulario no corresponde a esta sesion') && nombre(jug) === nombre_j,
          'datos sin token: 403, con el aviso, y el nombre intacto');
    r = await c.postear(J.ctx, PERFIL, { nombre: 'Cambio', apellido: 'X' });
    t.chk(r.estado === 403 && nombre(jug) === nombre_j, 'datos sin accion ni token: 403, y el nombre intacto');
    r = await c.postear(J.ctx, PERFIL, { accion: 'datos', nombre: 'odiseo', apellido: 'de Ítaca', token_csrf: TJ });
    t.chk(r.estado === 200 && r.cuerpo.includes('El perfil queda guardado.') && nombre(jug) === 'odiseo', 'datos con token: 200, y guardado');
    // Un campo mandado como arreglo (nombre[]=x) no rompe la pagina: el
    // campo queda vacio y el formulario responde con su aviso.
    r = await c.postear(J.ctx, PERFIL, { accion: 'datos', 'nombre[]': 'x', apellido: 'X', token_csrf: TJ });
    t.chk(r.estado === 200 && r.cuerpo.includes('<div role="alert">') && nombre(jug) === 'odiseo',
          `el perfil con nombre[]=x: ${r.estado}, con el aviso, y el nombre intacto`);
    const f0 = foto(jug, 'foto_perfil'), g0 = foto(jug, 'foto_portada');
    const imagen = { name: 'otra.png', mimeType: 'image/png', buffer: PNG };
    r = await subir(J.ctx, PERFIL, { accion: 'foto', imagen });
    t.chk(r.estado === 403 && foto(jug, 'foto_perfil') === f0, 'foto sin token: 403, y la foto intacta');
    r = await subir(J.ctx, PERFIL, { accion: 'portada', imagen, token_csrf: 'abc' });
    t.chk(r.estado === 403 && foto(jug, 'foto_portada') === g0, 'portada con un token inventado: 403, y la portada intacta');
    const SALIR = absoluta(PERFIL, (/<form class="salir-perfil" action="([^"]+)"/.exec((await pedir(J.ctx, PERFIL)).cuerpo) || [])[1] || '');
    r = await c.postear(J.ctx, SALIR, { nada: '1' });
    t.chk(r.estado === 403 && (await pedir(J.ctx, PERFIL)).estado === 200, 'cerrar sesion sin token: 403, y la sesion sigue');
    r = await c.postear(J.ctx, SALIR, { token_csrf: TA });
    t.chk(r.estado === 403 && (await pedir(J.ctx, PERFIL)).estado === 200, 'cerrar sesion con el token de otra sesion: 403, y la sesion sigue');
    r = await c.postear(J.ctx, SALIR, { token_csrf: TJ });
    t.chk(r.estado === 302 && (await pedir(J.ctx, PERFIL)).estado === 302, 'cerrar sesion con token: 302, y la sesion queda cerrada');

    const x = await nav.newContext();
    r = await c.postear(x, LOGIN, { correo: jug.correo, password: jug.clave });
    t.chk(r.estado === 403 && (await pedir(x, PERFIL)).estado === 302, 'iniciar sesion sin token: 403, y sin sesion');
    r = await c.postear(x, LOGIN, { correo: jug.correo, password: jug.clave, token_csrf: TA });
    t.chk(r.estado === 403 && (await pedir(x, PERFIL)).estado === 302, 'iniciar sesion con el token de otra sesion: 403, y sin sesion');
    const x2 = await nav.newContext();
    r = await c.postear(x2, LOGIN, { 'correo[]': jug.correo, 'password[]': jug.clave, token_csrf: await tokenDe(x2, `${BASE}/login.php`) });
    t.chk(r.estado === 200 && r.cuerpo.includes('<div role="alert">') && (await pedir(x2, PERFIL)).estado === 302,
          `iniciar sesion con correo[] y password[]: ${r.estado}, con el aviso, y sin sesion`);
    r = await c.postear(x2, REG, { 'nombre[]': 'x', apellido: 'Prueba', 'correo[]': nueva.correo, password: nueva.clave, terminos: '1',
                                   token_csrf: await tokenDe(x2, `${BASE}/registro.php`) });
    t.chk(r.estado === 200 && r.cuerpo.includes('<div role="alert">') && sql(`SELECT COUNT(*) FROM usuario WHERE correo = ${texto(nueva.correo)}`) === '0',
          `registro con nombre[] y correo[]: ${r.estado}, con el aviso, y sin cuenta`);
    await x2.close();
    const alta = { nombre: 'Nadie', apellido: 'Prueba', correo: nueva.correo, password: nueva.clave, terminos: '1' };
    r = await c.postear(x, REG, alta);
    t.chk(r.estado === 403 && sql(`SELECT COUNT(*) FROM usuario WHERE correo = ${texto(nueva.correo)}`) === '0', 'registro sin token: 403, y sin cuenta');
    const reg = await nav.newContext();
    r = await c.postear(reg, REG, Object.assign({ token_csrf: await tokenDe(reg, `${BASE}/registro.php`) }, alta));
    nueva.id = parseInt(sql(`SELECT IFNULL(MAX(id_usuario), 0) FROM usuario WHERE correo = ${texto(nueva.correo)}`), 10) || undefined;
    t.chk(r.estado === 200 && Number.isInteger(nueva.id), 'registro con token: 200, y la cuenta creada');

    const y = await nav.newContext();
    const t_antes = await tokenDe(y, `${BASE}/login.php`);
    await c.postear(y, LOGIN, { correo: jug.correo, password: jug.clave, token_csrf: t_antes });
    const t_despues = await tokenDe(y, PERFIL);
    t.chk(t_despues !== '' && t_antes !== t_despues, 'el token cambia al iniciar sesion');
    r = await c.postear(y, PERFIL, { accion: 'datos', nombre: 'Viejo', apellido: 'X', token_csrf: t_antes });
    t.chk(r.estado === 403 && nombre(jug) === 'odiseo', '  ...y el de antes ya no sirve');

    // --- 7. La sesion vencida, y volver a entrar ---------------------------
    console.log('--- 7. La sesion vencida ---');
    const cookie = (await y.cookies()).find(k => k.name === 'PHPSESSID');
    const archivo = cookie ? path.join(SESIONES, `sess_${cookie.value}`) : '';
    if (SESIONES !== '' && archivo !== '' && fs.existsSync(archivo)) {
      // La marca de la ultima actividad, de hace mas de una hora.
      const datos = fs.readFileSync(archivo, 'utf8');
      fs.writeFileSync(archivo, datos.replace(/ultima_actividad\|i:\d+/, `ultima_actividad|i:${Math.floor(Date.now() / 1000) - 4000}`));
      r = await pedir(y, `${BASE}/login.php`);
      t.chk(veces(r.cuerpo, 'Iniciar sesión</h2>') === 1, 'vencida, login.php muestra el formulario');
      r = await c.postear(y, LOGIN, { correo: jug.correo, password: jug.clave, token_csrf: tokenEn(r.cuerpo) });
      t.chk(r.estado === 200 && r.cuerpo.includes('queda abierta'), '  ...y se puede entrar otra vez');
      t.chk((await pedir(y, PERFIL)).estado === 200, '  ...y el perfil abre');
    } else {
      console.log(`  --    sesion vencida: omitida (no esta el archivo de la sesion en "${SESIONES}"; ver STADION_SESIONES)`);
    }

    for (const ctx of [anon, J.ctx, A.ctx, x, reg, y]) await ctx.close();
  } catch (e) {
    console.error(e);
    t.chk(false, `la bateria corre entera (${e.message.split('\n')[0]})`);
  } finally {
    // Pase lo que pase: se borra lo de esta corrida, y la base queda como
    // estaba.
    try {
      c.limpiarCuentas(propias);
      t.chk(sql(`SELECT COUNT(*) FROM usuario WHERE correo IN (${correos()})`) === '0', 'al final, las cuentas de la corrida ya no estan');
      t.chk(rolesAjenos() === antes.roles, 'los roles de las demas cuentas quedan como estaban');
      t.chk(c.huellaBase() === antes.huella, 'usuario, usuario_rol, pedido_rol y auditoria quedan iguales que antes de empezar (CHECKSUM TABLE)');
    } catch (e) {
      t.chk(false, `la limpieza (${e.message.split('\n')[0]})`);
    }
    if (nav) await nav.close();
    t.fin();
  }
})();
