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
const fs = require('fs');
const path = require('path');

// La raiz del repositorio, y la carpeta donde el sitio de prueba guarda
// las fotos (en la disposicion del hosting es la de su copia).
const RAIZ = path.resolve(__dirname, '..', '..');
const SUBIDAS = process.env.STADION_SUBIDAS || path.join(RAIZ, 'public', 'subidas');

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
// datos. La contrasena es al azar y vive solo en memoria. Con $lista (el
// arreglo de las cuentas que la bateria borra al final), la cuenta se
// anota ahi ANTES de mandar el alta: si algo corta despues, la limpieza
// la encuentra igual, por el correo.
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

// --- Lo que una bateria crea, y solo eso, se borra al final ---------
// Para las baterias que dejan la base como la encontraron (permisos.js,
// sesion.js, subidas.js, recorrido.js). Cada una anota sus cuentas en
// un arreglo de { id, correo } y, al terminar, pase lo que pase, llama a
// limpiarCuentas(): borra la auditoria de esas cuentas (la propia, la de
// sus pedidos de rol, la del primer administrador y la de un inicio de
// sesion fallido con su correo), sus pedidos de rol, sus fotos del disco
// y las cuentas (los roles se van con ellas, ON DELETE CASCADE). Cada
// DELETE nombra las cuentas por id y por correo a la vez, y solo correos
// prueba-...@ejemplo.invalid: una cuenta que no sea de prueba no se borra
// nunca, aunque se cuele en el arreglo.
// Devuelve cuantas fotos de esas cuentas habia que borrar y cuantas no
// estaban en la carpeta de las subidas (si faltan, la carpeta que mira la
// prueba no es la del sitio: ver STADION_SUBIDAS).
function limpiarCuentas(cuentas) {
  const resultado = { fotos: 0, faltaban: 0 };
  const validas = cuentas.filter(x => x && /^prueba-[0-9a-f]+@ejemplo\.invalid$/.test(x.correo));
  // Una cuenta que se corto a mitad del alta no tiene id: se busca por
  // el correo.
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
  // Las fotos: solo nombres como los que genera ImagenSubida (32
  // caracteres al azar), y solo dentro de la carpeta de las subidas.
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
           OR (id_usuario IS NULL AND accion = 'login_error' AND detalle IN (${correos}));
       DELETE FROM pedido_rol WHERE id_usuario IN (${cuentas_sql});
       DELETE FROM usuario WHERE id_usuario IN (${ids}) AND correo IN (${correos});`);
  return resultado;
}

// La huella de las tablas que tocan esas baterias: si al final da lo
// mismo que al principio, la base quedo como estaba.
const huellaBase = () => sql('CHECKSUM TABLE usuario, usuario_rol, pedido_rol, auditoria');

// Los archivos de la carpeta de las subidas, para comparar antes y
// despues. Sin la carpeta, la prueba no sigue: compararia nada con nada.
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

// La direccion real de una pagina de la aplicacion (la del controlador),
// siguiendo el desvio de su pagina publica: crear.php, panel.php...
async function direccionDe(p, publica) {
  await p.goto(`${BASE}/${publica}`);
  return p.url().split('#')[0].split('?')[0];
}

module.exports = { BASE, RAIZ, SUBIDAS, sql, texto, azar, clave, navegador, contador, cuentaNueva, darRol, entrar, token, postear,
                   direccionDe, limpiarCuentas, huellaBase, archivosSubidas };
