// =====================================================================
// La politica de contrasenas (Ciberseguridad, tercera entrega)
// Stadion (Agon)
// ---------------------------------------------------------------------
//   node tests/e2e/contrasenas.js
//
//   1. el alta rechaza una clave sin mayuscula, sin numero o con el
//      nombre de la cuenta, y no crea la cuenta; con una que cumple, si
//   2. el bloqueo: 5 inicios de sesion fallidos con el mismo correo, y
//      el sexto, aun con la clave correcta, no entra; el aviso es el
//      mismo con un correo que no tiene cuenta; el intento bloqueado
//      queda en la auditoria y no alarga la pausa; pasados 15 minutos
//      (las filas se corren 16 minutos para atras), entra
//   3. cambiar la contrasena desde el perfil: con la actual equivocada,
//      con la repeticion distinta, con una nueva que no cumple y con la
//      misma, no cambia nada; con todo bien, cambia, deja la fila en la
//      auditoria, y despues la vieja ya no entra y la nueva si; sin el
//      token, 403
//
// SOLO TOCA LO QUE ELLA MISMA CREA: cuentas de @ejemplo.invalid con
// contrasena al azar. Al final borra sus cuentas y su auditoria
// (limpiarCuentas en comun.js, mas las filas del correo sin cuenta) y
// comprueba que la base quedo como estaba (CHECKSUM TABLE).
// =====================================================================
'use strict';
const c = require('./comun');
const { BASE, sql, texto } = c;

// Manda el formulario de acceso y devuelve lo que se ve despues.
async function intentar(nav, correo, clave) {
  const ctx = await nav.newContext();
  const p = await ctx.newPage();
  await p.goto(`${BASE}/login.php`);
  await p.fill('input[name="correo"]', correo);
  await p.fill('input[name="password"]', clave);
  await Promise.all([p.waitForNavigation(), p.click('main form button[type="submit"]')]);
  const avisos = await p.$$eval('[role="alert"] li', ls => ls.map(l => l.textContent.trim()));
  const estado = await p.$eval('[role="status"]', e => e.textContent.trim()).catch(() => '');
  // Si entro, el perfil se abre; si no, manda al acceso.
  await p.goto(`${BASE}/perfil.php`);
  const adentro = !p.url().includes('login.php');
  await ctx.close();
  return { avisos, estado, adentro };
}

// El formulario de alta, con la clave que se pida.
async function alta(nav, nombre, apellido, correo, clave) {
  const ctx = await nav.newContext();
  const p = await ctx.newPage();
  await p.goto(`${BASE}/registro.php`);
  await p.fill('input[name="nombre"]', nombre);
  await p.fill('input[name="apellido"]', apellido);
  await p.fill('input[name="correo"]', correo);
  await p.fill('input[name="password"]', clave);
  await p.check('input[name="terminos"]');
  await Promise.all([p.waitForNavigation(), p.click('form#alta button[type="submit"]')]);
  const avisos = await p.$$eval('[role="alert"] li', ls => ls.map(l => l.textContent.trim()));
  await ctx.close();
  return avisos;
}

const cuantasCuentas = correo => sql(`SELECT COUNT(*) FROM usuario WHERE correo = ${texto(correo)}`);

let nav;
(async () => {
  const t = c.contador('Politica de contrasenas');
  const antes = c.huellaBase();
  const propias = [];
  const sin_cuenta = `prueba-${c.azar()}@ejemplo.invalid`;
  nav = await c.navegador();
  try {
    // --- 1. El alta ------------------------------------------------------------
    console.log('--- 1. El alta ---');
    const ayuda = await (async () => {
      const ctx = await nav.newContext(); const p = await ctx.newPage();
      await p.goto(`${BASE}/registro.php`);
      const txt = await p.$eval('#ayuda-clave', e => e.textContent);
      await ctx.close(); return txt;
    })();
    t.chk(/mayúscula, una minúscula y un número/.test(ayuda), `la ayuda del alta dice la regla ("${ayuda}")`);

    const correo1 = `prueba-${c.azar()}@ejemplo.invalid`;
    propias.push({ correo: correo1 });
    let av = await alta(nav, 'Pentesilea', 'Prueba', correo1, 'sinmayuscula123');
    t.chk(av.some(a => /una mayúscula, una minúscula y un número/.test(a)) && cuantasCuentas(correo1) === '0',
          'sin mayúscula: el aviso de la regla y ninguna cuenta');
    av = await alta(nav, 'Pentesilea', 'Prueba', correo1, 'Sin numeros nunca');
    t.chk(av.some(a => /una mayúscula, una minúscula y un número/.test(a)) && cuantasCuentas(correo1) === '0',
          'sin número: el mismo aviso y ninguna cuenta');
    av = await alta(nav, 'Pentesilea', 'Prueba', correo1, 'Reina Pentesilea 7');
    t.chk(av.some(a => /no puede contener el nombre/.test(a)) && cuantasCuentas(correo1) === '0',
          'con el nombre adentro: el aviso y ninguna cuenta');
    const A = await c.cuentaNueva(nav, 'Pentesilea', 'Prueba', propias);
    t.chk(Number.isInteger(A.id) && A.id > 0, 'con una clave que cumple, la cuenta queda abierta');
    const hash = sql(`SELECT hash_password FROM usuario WHERE id_usuario = ${A.id}`);
    t.chk(hash.startsWith('$2y$') && !hash.includes(A.clave), 'y en la base, solo el hash');

    // --- 2. El bloqueo ---------------------------------------------------------
    console.log('--- 2. El bloqueo ---');
    for (let i = 1; i <= 5; i++) {
      const r = await intentar(nav, A.correo, 'Equivocada' + i + '00');
      t.chk(!r.adentro && r.avisos.includes('El correo o la contraseña no coinciden.'),
            `intento fallido ${i}: el aviso de siempre`);
    }
    let r = await intentar(nav, A.correo, A.clave);
    t.chk(!r.adentro && r.avisos.some(a => /Demasiados intentos fallidos/.test(a) && /15 minutos/.test(a)),
          'el sexto, con la clave correcta, no entra: "Demasiados intentos fallidos... 15 minutos"');
    t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'login_error' AND detalle = ${texto('Bloqueo: ' + A.correo)}`) === '1',
          'el intento bloqueado queda en la auditoría, con "Bloqueo:" en el detalle');
    t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE accion = 'login_ok' AND id_usuario = ${A.id}`) === '0',
          'y ningún inicio de sesión de esa cuenta');

    for (let i = 1; i <= 5; i++) await intentar(nav, sin_cuenta, 'Cualquiera' + i + '00');
    r = await intentar(nav, sin_cuenta, 'Cualquiera600');
    t.chk(r.avisos.some(a => /Demasiados intentos fallidos/.test(a)),
          'con un correo sin cuenta pasa lo mismo: el bloqueo no dice qué correos tienen cuenta');

    // Pasan 15 minutos: las filas del correo se corren 16 minutos atras.
    sql(`UPDATE auditoria SET fecha_hora = fecha_hora - INTERVAL 16 MINUTE
         WHERE accion = 'login_error' AND detalle IN (${texto(A.correo)}, ${texto('Bloqueo: ' + A.correo)})`);
    r = await intentar(nav, A.correo, A.clave);
    t.chk(r.adentro, 'pasados 15 minutos del quinto fallo, la clave correcta entra');

    // --- 3. Cambiar la contrasena ----------------------------------------------
    console.log('--- 3. Cambiar la contraseña ---');
    const s = await c.entrar(nav, A);
    const p = s.p;
    await p.goto(`${BASE}/perfil.php`);
    t.chk(await p.$('form.cambiar-clave input[name="clave_actual"][autocomplete="current-password"]') !== null
          && await p.$('form.cambiar-clave input[name="clave_nueva"][aria-describedby~="ayuda-clave-nueva"]') !== null,
          'en Datos, la tarjeta Contraseña: la actual, la nueva con su ayuda y la repetición');
    const cambiar = async (actual, nueva, repetir) => {
      await p.goto(`${BASE}/perfil.php`);
      await p.fill('input[name="clave_actual"]', actual);
      await p.fill('input[name="clave_nueva"]', nueva);
      await p.fill('input[name="clave_repetir"]', repetir);
      await Promise.all([p.waitForNavigation(), p.click('form.cambiar-clave button[type="submit"]')]);
      return {
        error: await p.$eval('#error-clave', e => e.textContent.trim()).catch(() => ''),
        estado: await p.$eval('[role="status"]', e => e.textContent.trim()).catch(() => ''),
        titulo: await p.title(),
        invalido: await p.$eval('input[name="clave_nueva"]', e => e.getAttribute('aria-invalid')).catch(() => null),
        hash: sql(`SELECT hash_password FROM usuario WHERE id_usuario = ${A.id}`)
      };
    };
    const nueva = 'Nq8' + Array.from({ length: 4 }, () => String(1e5 + Math.floor(Math.random() * 9e5))).join('');
    let x = await cambiar('Otra cosa 123', nueva, nueva);
    t.chk(x.error === 'La contraseña actual no coincide.' && x.hash === hash && x.titulo.startsWith('Aviso ·') && x.invalido === 'true',
          'con la actual equivocada: el aviso al lado, "Aviso ·" en el título, el campo marcado y nada cambia');
    x = await cambiar(A.clave, nueva, nueva + 'x');
    t.chk(x.error === 'La contraseña nueva y su repetición no coinciden.' && x.hash === hash, 'con la repetición distinta, nada cambia');
    x = await cambiar(A.clave, 'sinmayuscula123', 'sinmayuscula123');
    t.chk(/una mayúscula, una minúscula y un número/.test(x.error) && x.hash === hash, 'con una nueva que no cumple, nada cambia');
    x = await cambiar(A.clave, A.clave, A.clave);
    t.chk(x.error === 'La contraseña nueva es igual a la actual.' && x.hash === hash, 'con la misma, nada cambia');
    x = await cambiar(A.clave, nueva, nueva);
    t.chk(x.estado === 'La contraseña queda cambiada.' && x.hash !== hash && x.hash.startsWith('$2y$'),
          'con todo bien: "La contraseña queda cambiada." y un hash nuevo');
    t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE id_usuario = ${A.id} AND accion = 'modificacion' AND detalle = 'Cambio de contraseña'`) === '1',
          'la auditoría registra "Cambio de contraseña", sin la clave');
    t.chk(sql(`SELECT COUNT(*) FROM auditoria WHERE id_usuario = ${A.id} AND (detalle LIKE ${texto('%' + nueva + '%')} OR detalle LIKE ${texto('%' + A.clave + '%')})`) === '0',
          'ninguna clave en claro en la auditoría');
    const r403 = await s.ctx.request.post(p.url().split('#')[0], { form: { accion: 'clave', clave_actual: nueva, clave_nueva: 'Otra Nueva 999', clave_repetir: 'Otra Nueva 999' }, maxRedirects: 0 });
    t.chk(r403.status() === 403 && sql(`SELECT hash_password FROM usuario WHERE id_usuario = ${A.id}`) === x.hash,
          'sin el token: 403 y nada cambia');
    await s.ctx.close();
    r = await intentar(nav, A.correo, A.clave);
    t.chk(!r.adentro, 'después, la vieja ya no entra');
    r = await intentar(nav, A.correo, nueva);
    t.chk(r.adentro, 'y la nueva sí');
  } catch (e) {
    console.error(e);
    t.chk(false, `la bateria corre entera (${e.message.split('\n')[0]})`);
  } finally {
    try {
      c.limpiarCuentas(propias);
      sql(`DELETE FROM auditoria WHERE id_usuario IS NULL AND accion = 'login_error'
            AND detalle IN (${texto(sin_cuenta)}, ${texto('Bloqueo: ' + sin_cuenta)})`);
      t.chk(c.huellaBase() === antes, 'al final, usuario, usuario_rol, pedido_rol, auditoria y medicion quedan iguales (CHECKSUM TABLE)');
    } catch (e) {
      t.chk(false, `la limpieza (${e.message.split('\n')[0]})`);
    }
    if (nav) await nav.close();
    t.fin();
  }
})();
