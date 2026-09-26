// =====================================================================
// Lo que comparten las pruebas de navegador - Stadion (Agon)
// ---------------------------------------------------------------------
// Configuracion por variables de entorno (ver tests/README.md):
//   STADION_URL       la direccion del sitio de prueba
//                     (por defecto http://127.0.0.1:8095)
//   STADION_MYSQL     el comando que abre la base de PRUEBA con una
//                     cuenta que puede dar roles (por defecto "mysql sgdm")
//   STADION_CHROMIUM  la ruta de Chromium, si playwright no lo encuentra
//
// Las cuentas de prueba se crean en cada corrida, con un correo de
// @ejemplo.invalid (un dominio que no existe) y una contrasena al azar
// que no se guarda en ningun lado: en este repositorio no hay ninguna
// contrasena ni ningun dato real.
// =====================================================================
'use strict';
const { chromium } = require('playwright-core');
const { execSync } = require('child_process');
const crypto = require('crypto');

const BASE = (process.env.STADION_URL || 'http://127.0.0.1:8095').replace(/\/$/, '');
const MYSQL = process.env.STADION_MYSQL || 'mysql sgdm';

// Estas pruebas crean cuentas, ligas y equipos, y dan roles por SQL:
// son para una instalacion de PRUEBA en la propia maquina (127.0.0.1,
// localhost, o un nombre .local como el stadion.local del virtual host
// de XAMPP). Contra otra direccion no arrancan: un servidor real no se
// toca nunca.
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
const clave = () => crypto.randomBytes(15).toString('base64').replace(/[^A-Za-z0-9]/g, 'x');

async function navegador() {
  const opciones = {};
  if (process.env.STADION_CHROMIUM) opciones.executablePath = process.env.STADION_CHROMIUM;
  return chromium.launch(opciones);
}

// Cuenta el resultado de cada comprobacion, y al final sale con 1 si
// alguna fallo.
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

// Da de alta una cuenta por el formulario de registro y devuelve sus
// datos. La contrasena es al azar y vive solo en memoria.
async function cuentaNueva(nav, nombre, apellido) {
  const datos = { nombre, apellido, correo: `prueba-${azar()}@ejemplo.invalid`, clave: clave() };
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

// El token CSRF de la sesion: todos los formularios llevan el mismo, uno
// por sesion. Si la pagina abierta no tiene ningun formulario, se lee
// del perfil (su "Cerrar sesion" es un formulario con el token).
async function token(p) {
  const aca = await p.$eval('input[name="token_csrf"]', e => e.value).catch(() => null);
  if (aca) return aca;
  const otra = await p.context().newPage();
  await otra.goto(`${BASE}/perfil.php`);
  const valor = await otra.$eval('input[name="token_csrf"]', e => e.value).catch(() => null);
  await otra.close();
  return valor;
}

// Un POST armado a mano, con la cookie de la sesion del contexto.
async function postear(ctx, url, campos) {
  const r = await ctx.request.post(url, { form: campos, maxRedirects: 0 });
  return { estado: r.status(), destino: r.headers()['location'] || '', cuerpo: await r.text() };
}

// La direccion real de una pagina de la aplicacion (la del controlador),
// siguiendo el desvio de su pagina publica: crear.php, panel.php...
async function direccionDe(p, publica) {
  await p.goto(`${BASE}/${publica}`);
  return p.url().split('#')[0].split('?')[0];
}

module.exports = { BASE, sql, texto, azar, navegador, contador, cuentaNueva, darRol, entrar, token, postear, direccionDe };
