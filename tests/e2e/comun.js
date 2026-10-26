// Lo que comparten las pruebas de navegador - Stadion (Agon)
'use strict';
const { chromium } = require('playwright-core');
const { execSync } = require('child_process');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const RAIZ = path.resolve(__dirname, '..', '..');
const SUBIDAS = process.env.STADION_SUBIDAS || path.join(RAIZ, 'public', 'subidas');

const BASE = (process.env.STADION_URL || 'http://127.0.0.1:8095').replace(/\/$/, '');
const MYSQL = process.env.STADION_MYSQL || 'mysql sgdm';

const anfitrion = new URL(BASE).hostname;
if (!['127.0.0.1', 'localhost', '[::1]', '::1'].includes(anfitrion)
    && !anfitrion.endsWith('.local') && !anfitrion.endsWith('.localhost')) {
  console.error(`Las pruebas corren solo contra la propia maquina, no contra ${anfitrion}.`);
  process.exit(2);
}

// SQL contra la base de prueba; devuelve el texto de la salida.
function sql(consulta) {
  return execSync(`${MYSQL} -N -B --default-character-set=utf8mb4`, { input: consulta }).toString().trim();
}
// Un valor de SQL entre comillas simples, sin dejar escapar nada.
function texto(valor) {
  return "'" + String(valor).replace(/\\/g, '\\\\').replace(/'/g, "''") + "'";
}

const azar = (n = 4) => crypto.randomBytes(n).toString('hex');
const clave = () => 'Pq7' + Array.from({ length: 4 }, () => String(crypto.randomInt(100000, 1000000))).join('');

async function navegador() {
  const opciones = {};
  if (process.env.STADION_CHROMIUM) opciones.executablePath = process.env.STADION_CHROMIUM;
  return chromium.launch(opciones);
}

// Cuenta el resultado de cada comprobacion, y al final sale con 1 si alguna fallo.
function contador(titulo) {
  let malas = 0, buenas = 0;
  console.log(`===== ${titulo} (${BASE}) =====`);
  return {
    chk(condicion, descripcion) {
      if (condicion) { buenas++; console.log(`  ok    ${descripcion}`); }
      else { malas++; console.log(`  FALLA ${descripcion}`); }
      return condicion;
    },
    fin() {
      console.log(`${malas === 0 ? 'TODO BIEN' : malas + ' FALLARON'}: ${buenas} de ${buenas + malas} comprobaciones`);
      process.exitCode = malas === 0 ? 0 : 1;
    }
  };
}

// Da de alta una cuenta por el formulario de registro y devuelve sus datos.
async function cuentaNueva(nav, nombre, apellido, lista) {
  const datos = { nombre, apellido, correo: `prueba-${azar()}@ejemplo.invalid`, clave: clave() };
  if (Array.isArray(lista)) lista.push(datos);
  const ctx = await nav.newContext();
  const p = await ctx.newPage();
  await p.goto(`${BASE}/registro.php`);
  await p.fill('input[name="nombre"]', nombre);
  await p.fill('input[name="apellido"]', apellido);
  await p.fill('input[name="correo"]', datos.correo);
  await p.fill('input[name="password"]', datos.clave);
  await p.check('input[name="terminos"]');
  await Promise.all([p.waitForNavigation(), p.click('form#alta button[type="submit"]')]);
  await ctx.close();
  datos.id = parseInt(sql(`SELECT id_usuario FROM usuario WHERE correo = ${texto(datos.correo)}`), 10);
  return datos;
}

function darRol(cuenta, rol) {
  sql(`INSERT IGNORE INTO usuario_rol (id_usuario, id_rol)
       SELECT ${cuenta.id}, id_rol FROM rol WHERE nombre = ${texto(rol)}`);
}

// Un contexto de navegador con la sesion de esa cuenta abierta.
async function entrar(nav, cuenta, opciones = {}) {
  const ctx = await nav.newContext(opciones);
  const p = await ctx.newPage();
  await p.goto(`${BASE}/login.php`);
  await p.fill('input[name="correo"]', cuenta.correo);
  await p.fill('input[name="password"]', cuenta.clave);
  await Promise.all([p.waitForNavigation(), p.click('main form button[type="submit"]')]);
  return { ctx, p };
}

// El token CSRF de la sesion: todos los formularios llevan el mismo, uno por sesion.
async function token(p) {
  const aca = await p.$eval('input[name="token_csrf"]', e => e.value).catch(() => null);
  if (aca) return aca;
  const otra = await p.context().newPage();
  await otra.goto(`${BASE}/perfil.php`);
  const valor = await otra.$eval('input[name="token_csrf"]', e => e.value).catch(() => null);
  await otra.close();
  return valor;
}

function limpiarCuentas(cuentas) {
  const resultado = { fotos: 0, faltaban: 0 };
  const validas = cuentas.filter(x => x && /^prueba-[0-9a-f]+@ejemplo\.invalid$/.test(x.correo));
  // Una cuenta que se corto a mitad del alta no tiene id: se busca por el correo.
  for (const x of validas) {
    if (!Number.isInteger(x.id)) {
      const id = parseInt(sql(`SELECT IFNULL(MAX(id_usuario), 0) FROM usuario WHERE correo = ${texto(x.correo)}`), 10);
      if (id > 0) x.id = id;
    }
  }
  const propias = validas.filter(x => Number.isInteger(x.id));
  if (propias.length === 0) return resultado;
  const ids = propias.map(x => x.id).join(',');
  const correos = propias.map(x => texto(x.correo)).join(',');
  const cuentas_sql = `SELECT id_usuario FROM usuario WHERE id_usuario IN (${ids}) AND correo IN (${correos})`;
  const fotos = sql(`SELECT foto_perfil FROM usuario WHERE id_usuario IN (${cuentas_sql}) AND foto_perfil IS NOT NULL
                     UNION SELECT foto_portada FROM usuario WHERE id_usuario IN (${cuentas_sql}) AND foto_portada IS NOT NULL`);
  for (const nombre of fotos.split('\n').filter(f => /^[0-9a-f]{32}\.(jpg|png|webp)$/.test(f))) {
    resultado.fotos++;
    try { fs.unlinkSync(path.join(SUBIDAS, nombre)); } catch (e) { resultado.faltaban++; }
  }
  sql(`DELETE FROM auditoria
        WHERE id_usuario IN (${cuentas_sql})
           OR (tabla_afectada IN ('usuario', 'usuario_rol') AND id_registro IN (${cuentas_sql}))
           OR (tabla_afectada = 'pedido_rol' AND id_registro IN
                 (SELECT id_pedido_rol FROM pedido_rol WHERE id_usuario IN (${cuentas_sql})))
           OR (id_usuario IS NULL AND accion = 'login_error' AND detalle IN (${correos}))
           OR (id_usuario IS NULL AND accion = 'login_error'
               AND detalle IN (${propias.map(x => texto('Bloqueo: ' + x.correo)).join(',')}));
       DELETE FROM pedido_rol WHERE id_usuario IN (${cuentas_sql});
       DELETE FROM usuario WHERE id_usuario IN (${ids}) AND correo IN (${correos});`);
  return resultado;
}

const huellaBase = () => sql('CHECKSUM TABLE usuario, usuario_rol, pedido_rol, auditoria, medicion');

// Los archivos de la carpeta de las subidas, para comparar antes y despues.
function archivosSubidas() {
  if (!fs.existsSync(path.join(SUBIDAS, '.htaccess'))) {
    throw new Error(`No esta la carpeta de las subidas del sitio en ${SUBIDAS} (ver STADION_SUBIDAS)`);
  }
  return fs.readdirSync(SUBIDAS).sort().join(',');
}

// Un POST armado a mano, con la cookie de la sesion del contexto.
async function postear(ctx, url, campos) {
  const r = await ctx.request.post(url, { form: campos, maxRedirects: 0 });
  return { estado: r.status(), destino: r.headers()['location'] || '', cuerpo: await r.text() };
}

async function direccionDe(p, publica) {
  await p.goto(`${BASE}/${publica}`);
  return p.url().split('#')[0].split('?')[0];
}

module.exports = { BASE, RAIZ, SUBIDAS, sql, texto, azar, clave, navegador, contador, cuentaNueva, darRol, entrar, token, postear,
                   direccionDe, limpiarCuentas, huellaBase, archivosSubidas };
