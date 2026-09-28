// =====================================================================
// Las paginas publicas leen todo de la base - Stadion (Agon)
// ---------------------------------------------------------------------
//   node tests/e2e/publicas.js
//
// Contra la copia del hosting:
//   STADION_URL=http://127.0.0.1:8096 \
//   STADION_MYSQL="mysql --socket=/srv/m114/run/mysqld.sock sgdm" \
//   node tests/e2e/publicas.js
//
// Recorre las paginas publicas como un visitante sin sesion y compara
// lo que muestran con lo que dice la base EN ESE MOMENTO: otras pruebas
// agregan ligas y cuentas a la misma base, asi que nada se cuenta fijo
// ("3 ligas"). Lo que si va escrito es lo que tiene que mostrar la
// migracion 005 (las tres ligas de muestra): eso no cambia.
//   1. torneos.php: una tarjeta por torneo publico, en el orden de la
//      base, con su chip, su linea, su barra, su enlace y la marca "De
//      muestra" solo en las de muestra; el numero de la introduccion
//   2. torneo.php: sin numero, la liga destacada (la del partido en
//      vivo), con su tabla, sus participantes, sus reglas y su resumen;
//      las cinco pestanas por ancla (:target) y lo que acompana a la
//      vista abierta (el menu y "Saltar al contenido"); otra liga, sin
//      copias en el menu; un numero que no existe, 404
//   3. calendario.php: la semana del partido en vivo, los dias de la
//      semana de 2026, "Horario a confirmar", las semanas de antes y de
//      despues, una semana que no es fecha, y "Hoy" apagado en la de hoy
//   4. llave.php: solo hay ligas, cada una con el enlace a su tabla
//   5. index.php: los tres numeros, las tarjetas y el recuadro del
//      costado
//   6. una liga nueva, de una cuenta de prueba (el nombre guardado en
//      minuscula), aparece sin la marca "De muestra"; cancelada, deja de
//      ser publica en todas partes
//   7. en todas las paginas: "Saltar al contenido" primero, ningun
//      href="#", el CSS y el JS con su marca de version (la misma en
//      todas, y la que corresponde al archivo), sin voseo ni "tu/tus",
//      ningun enlace a panel.html, y las direcciones .html viejas
//      desviadas con un 301
//
// La cuenta de prueba se crea en cada corrida (ver comun.js): correo de
// @ejemplo.invalid y contrasena al azar, solo en memoria.
// =====================================================================
'use strict';
const crypto = require('crypto');
const c = require('./comun');
const { BASE, sql, texto, azar } = c;

// --- Fechas en castellano, las mismas reglas que apps/fechas.php -------
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
               'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
const mediodia = f => new Date(f.slice(0, 10) + 'T12:00:00Z');
const fechaTexto = f => `${parseInt(f.slice(8, 10), 10)} de ${MESES[parseInt(f.slice(5, 7), 10) - 1]}`;
const diaSemana = f => DIAS[mediodia(f).getUTCDay()];
const sumarDias = (f, n) => new Date(mediodia(f).getTime() + n * 86400000).toISOString().slice(0, 10);
const lunesDe = f => sumarDias(f, -((mediodia(f).getUTCDay() + 6) % 7));
const mayuscula = s => s.charAt(0).toUpperCase() + s.slice(1);
// "Semana del 14 al 20 de septiembre", o "del 28 de septiembre al 4 de
// octubre" si cruza de mes (igual que calendario.php).
function tituloSemana(lunes) {
  const domingo = sumarDias(lunes, 6);
  return (lunes.slice(5, 7) === domingo.slice(5, 7))
    ? `Semana del ${parseInt(lunes.slice(8, 10), 10)} al ${fechaTexto(domingo)}`
    : `Semana del ${fechaTexto(lunes)} al ${fechaTexto(domingo)}`;
}
// Hoy, en Montevideo (la zona de las paginas), como AAAA-MM-DD.
const hoy = new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Montevideo' }).format(new Date());

// "Viernes 18 de septiembre" -> 2026-09-18 (el ano, el de la semana).
function diaDeEtiqueta(etiqueta, anio) {
  const m = /^(\S+) (\d{1,2}) de (\S+)$/.exec(etiqueta.trim());
  if (!m || !MESES.includes(m[3])) return null;
  const f = `${anio}-${String(MESES.indexOf(m[3]) + 1).padStart(2, '0')}-${m[2].padStart(2, '0')}`;
  return { fecha: f, bien: m[1] === mayuscula(diaSemana(f)) };
}

// Los nombres de persona, como Usuario::paraMostrar(): guardados enteros
// en minuscula, suben la primera letra.
const paraMostrar = s => (s === '' || s !== s.toLowerCase()) ? s : s.charAt(0).toUpperCase() + s.slice(1);
const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;

// --- Lo que dice la base ------------------------------------------------
const PUBLICO = "t.estado IN ('inscripcion', 'en_curso', 'finalizado')";
function filas(consulta) {
  const salida = sql(consulta);
  return salida === '' ? [] : salida.split('\n').map(l => l.split('\t'));
}
// Los torneos publicos, en el orden de TorneoRepositorio::listarPublicos.
function publicos() {
  return filas(`SELECT t.id_torneo, t.nombre, t.estado, t.max_participantes, IFNULL(t.fecha_inicio, ''),
                       o.nombre, o.apellido, o.de_muestra, m.nombre, d.nombre,
                       (SELECT COUNT(*) FROM participante p
                         WHERE p.id_torneo = t.id_torneo AND p.estado IN ('inscripto', 'confirmado')),
                       (SELECT COUNT(*) FROM ronda r WHERE r.id_torneo = t.id_torneo),
                       IFNULL((SELECT MIN(r.numero) FROM ronda r WHERE r.id_torneo = t.id_torneo AND r.estado <> 'cerrada'), 0),
                       EXISTS (SELECT 1 FROM enfrentamiento en INNER JOIN ronda r ON r.id_ronda = en.id_ronda
                               WHERE r.id_torneo = t.id_torneo AND en.estado = 'en_vivo') AS en_vivo
                FROM torneo t
                     INNER JOIN usuario o ON o.id_usuario = t.id_usuario_organizador
                     INNER JOIN modulo_competencia m ON m.id_modulo = t.id_modulo
                     INNER JOIN disciplina d ON d.id_disciplina = t.id_disciplina
                WHERE ${PUBLICO}
                ORDER BY en_vivo DESC, CASE t.estado WHEN 'en_curso' THEN 0 WHEN 'inscripcion' THEN 1 ELSE 2 END,
                         t.fecha_inicio IS NULL, t.fecha_inicio, t.nombre`)
    .map(f => ({ id: parseInt(f[0], 10), nombre: f[1], estado: f[2], cupo: parseInt(f[3], 10), inicio: f[4],
                 organizador: `${paraMostrar(f[5])} ${paraMostrar(f[6])}`.trim(), muestra: f[7] === '1',
                 modulo: f[8], disciplina: f[9], inscriptos: parseInt(f[10], 10), fechas: parseInt(f[11], 10),
                 actual: parseInt(f[12], 10), en_vivo: f[13] === '1' }));
}
// La tabla de posiciones como se ve: el texto visible de cada fila (sin
// el texto oculto para el lector de pantalla, que va aparte), si lleva
// la clase y la raya de los que clasifican, los encabezados que se ven,
// las columnas cuyo encabezado y valores no alinean igual, y las cifras.
function leerTabla(p) {
  return p.evaluate(() => {
    const visible = e => getComputedStyle(e).display !== 'none';
    const filas = [...document.querySelectorAll('#posiciones tbody tr')].map(tr => {
      const celdas = [...tr.children].filter(visible);
      const texto = celdas.map(td => { const c = td.cloneNode(true); c.querySelectorAll('.visualmente-oculto').forEach(x => x.remove()); return c.textContent; });
      const pos = tr.querySelector('td.pos');
      const raya = pos ? getComputedStyle(pos).borderLeftColor : '';
      return { celdas: texto.join(' '), clasifica: tr.classList.contains('clasifica'),
               raya: raya !== '' && raya !== 'rgba(0, 0, 0, 0)' && raya !== 'transparent',
               oculto: [...tr.querySelectorAll('.visualmente-oculto')].map(x => x.textContent).join('') };
    });
    const ths = [...document.querySelectorAll('#posiciones thead th')].filter(visible);
    const primera = document.querySelector('#posiciones tbody tr');
    const tds = primera ? [...primera.children].filter(visible) : [];
    const alineadas = ths.map((th, i) => tds[i] && getComputedStyle(th).textAlign !== getComputedStyle(tds[i]).textAlign ? th.textContent : null).filter(Boolean);
    const num = document.querySelector('#posiciones td.num');
    return { filas, encabezados: ths.map(th => th.textContent).join('|'), alineadas,
             cifras: num ? getComputedStyle(num).fontVariantNumeric : '' };
  });
}
// Lo que tiene que decir cada tarjeta, con las reglas de apps/ligas.php.
function chipEsperado(t) {
  if (t.estado === 'inscripcion') return 'estado-inscripcion|Inscripción abierta';
  if (t.estado === 'en_curso') return t.en_vivo ? 'estado-en-vivo|En vivo' : 'estado-en-juego|En juego';
  return 'estado-cerrado|Finalizado';
}
function avanceEsperado(t) {
  if (t.estado === 'inscripcion') {
    return `${t.inscriptos} de ${t.cupo} equipos` + (t.inicio ? ` · desde el ${fechaTexto(t.inicio)}` : '');
  }
  const equipos = plural(t.inscriptos, 'equipo', 'equipos');
  if (t.fechas === 0) return `${equipos} · fixture por armar`;
  if (t.actual === 0) return `${equipos} · ${plural(t.fechas, 'fecha jugada', 'fechas jugadas')}`;
  return `${equipos} · Fecha ${t.actual} de ${t.fechas}`;
}
function porcentajeEsperado(t) {
  if (t.estado === 'inscripcion') return Math.round(100 * t.inscriptos / Math.max(1, t.cupo));
  if (t.fechas === 0) return 0;
  return Math.round(100 * (t.actual === 0 ? t.fechas : t.actual) / t.fechas);
}
// La tabla de una liga, calculada de tabla_posiciones y ordenada con el
// desempate de la liga (ConfiguracionTorneo::ordenarPosiciones).
// Como en torneo.php: la columna E solo si la liga admite empate, y los
// tantos a favor y en contra ("13:4") antes de la diferencia.
function tablaEsperada(id) {
  const [pv, pe, pd, criterio, admite] = filas(`SELECT puntos_victoria, puntos_empate, puntos_derrota, criterio_desempate, admite_empate
                                                FROM configuracion_torneo WHERE id_torneo = ${id}`)[0];
  const lista = filas(`SELECT e.nombre, tp.ganados, tp.empatados, tp.perdidos, tp.favor, tp.contra
                       FROM tabla_posiciones tp
                            INNER JOIN participante p ON p.id_participante = tp.id_participante
                            INNER JOIN equipo e ON e.id_equipo = p.id_equipo
                       WHERE p.id_torneo = ${id} AND p.estado IN ('inscripto', 'confirmado')`)
    .map(([nombre, g, e, p, favor, contra]) => {
      const x = { nombre, g: +g, e: +e, p: +p, favor: +favor, contra: +contra, dif: favor - contra };
      x.pts = x.g * pv + x.e * pe + x.p * pd;
      return x;
    });
  const primero = x => criterio === 'favor' ? x.favor : x.dif;
  const segundo = x => criterio === 'favor' ? x.dif : x.favor;
  lista.sort((a, b) => (b.pts - a.pts) || (primero(b) - primero(a)) || (segundo(b) - segundo(a))
                       || (a.nombre < b.nombre ? -1 : a.nombre > b.nombre ? 1 : 0));
  const signo = n => n > 0 ? `+${n}` : String(n);
  return lista.map((x, i) => [String(i + 1), x.nombre, String(x.g + x.e + x.p), String(x.g)]
    .concat(admite === '1' ? [String(x.e)] : [], [String(x.p), `${x.favor}:${x.contra}`, signo(x.dif), String(x.pts)]).join(' '));
}
const idDe = nombre => parseInt(sql(`SELECT id_torneo FROM torneo WHERE nombre = ${texto(nombre)} ORDER BY id_torneo LIMIT 1`), 10);

// --- Lo que se lee de una pagina abierta ----------------------------------
// Las tarjetas de torneos (torneos.php y el inicio).
const leerTarjetas = (p, donde) => p.$$eval(`${donde} article.tarjeta`, arts => arts
  .filter(a => a.querySelector('.barra'))
  .map(a => {
    const chip = a.querySelector('.estado');
    const enlace = a.querySelector('a[href^="torneo.php"]');
    return {
      chip: chip ? chip.className.replace('estado ', '') + '|' + chip.textContent : '',
      tipo: (a.querySelector('.fila .etiqueta') || { textContent: '' }).textContent,
      nombre: a.querySelector('h3').textContent,
      linea: a.querySelector('p.etiqueta').textContent,
      barra: a.querySelector('.barra span').style.width,
      enlace: enlace ? enlace.getAttribute('href') : '',
      oculto: enlace ? (enlace.querySelector('.visualmente-oculto') || { textContent: '' }).textContent.trim() : '',
      muestra: a.querySelectorAll('.muestra').length
    };
  }));
// Las vistas abiertas, la pestana marcada de cada una, la entrada del
// menu marcada y el "Saltar al contenido" que se ve (lo que se ve:
// display distinto de none en el elemento y en sus padres).
async function estadoVistas(p) {
  return p.evaluate(() => {
    const ve = (e) => { for (let x = e; x; x = x.parentElement) { if (getComputedStyle(x).display === 'none') return false; } return true; };
    const abiertas = [...document.querySelectorAll('.vista')].filter(ve);
    return {
      vistas: abiertas.map(v => v.id).join(','),
      pestana: abiertas.map(v => { const a = v.querySelector('.pestanas a.activo'); return a ? a.getAttribute('href') + '|' + a.getAttribute('aria-current') : ''; }).join(','),
      menu: [...document.querySelectorAll('nav a.activo')].filter(ve).map(a => a.textContent + '|' + a.getAttribute('aria-current')).join(','),
      saltar: [...document.querySelectorAll('a.saltar')].filter(ve).map(a => a.getAttribute('href')).join(',')
    };
  });
}

// Otras pruebas pueden estar cambiando la misma base mientras esta
// corre: se lee la base antes y despues de abrir la pagina, y si cambio
// en el medio, se vuelve a abrir (hasta cinco veces). Asi lo que se
// compara es la pagina contra la base de ese mismo momento.
async function estable(leerBase, abrir) {
  let ultimo = null;
  for (let vez = 0; vez < 5; vez++) {
    const antes = JSON.stringify(leerBase());
    const visto = await abrir();
    const base = leerBase();
    ultimo = { visto, base };
    if (JSON.stringify(base) === antes) break;
  }
  return ultimo;
}

// Voseo y segunda persona: las formas que no pueden aparecer, y las
// palabras terminadas en -ás, -és, -ís (la forma del voseo: tenés,
// podés, elegís) que si son castellano de todos.
const VOSEO = new RegExp('(?<!\\p{L})(' + ['tu', 'tus', 'tú', 'tuyo', 'tuya', 'tuyos', 'tuyas', 'vos', 'contigo', 'ti', 'sos',
  'tienes', 'puedes', 'quieres', 'sabes', 'eres', 'eliges', 'fijate', 'registrate', 'anotate', 'sumate', 'unite',
  'mirá', 'abrí', 'elegí', 'ingresá', 'entrá', 'iniciá', 'creá', 'completá', 'escribí', 'subí', 'organizá', 'armá',
  'seguí', 'probá', 'empezá', 'descubrí', 'andá', 'vení', 'decí', 'poné', 'salí', 'cargá', 'buscá'].join('|') + ')(?!\\p{L})', 'giu');
const TERMINA = /(?<!\p{L})\p{L}+(?:ás|és|ís)(?!\p{L})/giu;
const PERMITIDAS = new Set(['más', 'además', 'jamás', 'atrás', 'detrás', 'demás', 'después', 'través', 'interés',
  'inglés', 'francés', 'país', 'parís', 'cortés', 'compás']);
function voseo(textoPagina) {
  const malas = new Set();
  for (const m of textoPagina.matchAll(VOSEO)) malas.add(m[0]);
  for (const m of textoPagina.matchAll(TERMINA)) if (!PERMITIDAS.has(m[0].toLowerCase())) malas.add(m[0]);
  return [...malas];
}

let nav = null;
(async () => {
  const t = c.contador('Paginas publicas: todo de la base');
  nav = await c.navegador();
  const sufijo = azar();

  const V = idDe('Liga Valorant · Otoño');
  const B = idDe('Liga Barrial del Cerro');
  const S = idDe('Liga Interna Club Sur');
  t.chk(V > 0 && B > 0 && S > 0, `las tres ligas de muestra estan en la base (${V}, ${B}, ${S})`);

  // --- 6a. Una liga nueva, de una cuenta de prueba ----------------------
  // Se crea antes que nada, asi todo lo que sigue la tiene que contar.
  console.log('--- Una liga nueva, de una cuenta de prueba ---');
  const org = await c.cuentaNueva(nav, 'olivia', 'de la prueba');
  c.darRol(org, 'organizador');
  const O = await c.entrar(nav, org);
  const liga = `Liga Pública ${sufijo}`;
  await O.p.goto(`${BASE}/crear.php`);
  await O.p.fill('input[name="nombre"]', liga);
  await O.p.selectOption('select[name="disciplina"]', { label: 'Ajedrez' });
  await O.p.fill('input[name="cupo"]', '8');
  await O.p.check('#vueltas-una');
  await Promise.all([O.p.waitForNavigation(), O.p.click('main form button[type="submit"]')]);
  const N = idDe(liga);
  t.chk(N > 0 && sql(`SELECT CONCAT(estado, '|', IFNULL(fecha_inicio, 'NULL'), '|', id_usuario_organizador) FROM torneo WHERE id_torneo = ${N}`)
        === `inscripcion|NULL|${org.id}`, `la liga nueva queda en la base: inscripcion abierta, sin fecha (${N})`);
  await O.ctx.close();

  // El visitante, sin sesion.
  const ctx = await nav.newContext();
  const p = await ctx.newPage();

  // --- 1. torneos.php ---------------------------------------------------
  console.log('--- 1. torneos.php ---');
  const abrirTorneos = async () => {
    await p.goto(`${BASE}/torneos.php`);
    return { tarjetas: await leerTarjetas(p, 'main'), intro: await p.textContent('main .intro') };
  };
  let vista = await estable(publicos, abrirTorneos);
  let lista = vista.base;
  let tarjetas = vista.visto.tarjetas;
  t.chk(tarjetas.length === lista.length, `una tarjeta por torneo publico de la base (${tarjetas.length} de ${lista.length})`);
  t.chk(tarjetas.map(x => x.enlace).join(' ') === lista.map(x => `torneo.php?id=${x.id}`).join(' '),
        'en el orden de la base: en vivo, en curso, inscripcion abierta, finalizados');
  const vivos = lista.filter(x => x.en_vivo).length;
  const intro = `${plural(lista.length, 'competencia pública', 'competencias públicas')} · ${vivos} en vivo`;
  t.chk(vista.visto.intro === intro, `la introduccion cuenta lo de la base ("${intro}")`);
  for (const x of lista) {
    const k = tarjetas.find(y => y.enlace === `torneo.php?id=${x.id}`);
    const esperado = { chip: chipEsperado(x), tipo: `${x.modulo} · ${x.disciplina}`, nombre: x.nombre,
                       linea: `${x.organizador} · ${avanceEsperado(x)}`, barra: `${porcentajeEsperado(x)}%`,
                       oculto: x.nombre, muestra: x.muestra ? 1 : 0 };
    const visto = k ? { chip: k.chip, tipo: k.tipo, nombre: k.nombre, linea: k.linea, barra: k.barra, oculto: k.oculto, muestra: k.muestra } : null;
    t.chk(JSON.stringify(visto) === JSON.stringify(esperado),
          `tarjeta de ${x.nombre}: ${esperado.chip.split('|')[1]} · ${esperado.linea} · ${esperado.barra}${x.muestra ? ' · De muestra' : ''}`
          + (visto && JSON.stringify(visto) !== JSON.stringify(esperado) ? ` (se ve: ${JSON.stringify(visto)})` : ''));
  }
  // Lo que tiene que mostrar la migracion 005, escrito.
  const tarjeta = id => tarjetas.find(y => y.enlace === `torneo.php?id=${id}`) || {};
  t.chk(tarjeta(V).chip === 'estado-en-vivo|En vivo' && tarjeta(V).linea === 'Comunidad Vórtice · 12 equipos · Fecha 8 de 11'
        && tarjeta(V).barra === '73%' && tarjeta(V).muestra === 1, 'Liga Valorant: En vivo, "Comunidad Vórtice · 12 equipos · Fecha 8 de 11", 73%, De muestra');
  t.chk(tarjeta(B).chip === 'estado-en-juego|En juego' && tarjeta(B).linea === 'Centro Juvenil Cerro · 10 equipos · Fecha 4 de 9'
        && tarjeta(B).barra === '44%' && tarjeta(B).muestra === 1, 'Liga Barrial: En juego, "Fecha 4 de 9", 44%, De muestra');
  t.chk(tarjeta(S).chip === 'estado-inscripcion|Inscripción abierta' && tarjeta(S).linea === 'Club Sur · 9 de 12 equipos · desde el 4 de octubre'
        && tarjeta(S).barra === '75%' && tarjeta(S).muestra === 1, 'Liga Interna Club Sur: inscripcion abierta, "9 de 12 equipos · desde el 4 de octubre", 75%, De muestra');
  t.chk(tarjeta(N).chip === 'estado-inscripcion|Inscripción abierta' && tarjeta(N).linea === 'Olivia De la prueba · 0 de 8 equipos'
        && tarjeta(N).barra === '0%' && tarjeta(N).muestra === 0,
        'la liga nueva: sin marca, "Olivia De la prueba · 0 de 8 equipos" (el nombre en minuscula sube la primera letra), 0%');
  t.chk(lista.filter(x => !x.muestra).every(x => tarjeta(x.id).muestra === 0) && lista.filter(x => x.muestra).every(x => tarjeta(x.id).muestra === 1),
        `"De muestra" en las ${lista.filter(x => x.muestra).length} de muestra y en ninguna de las otras ${lista.filter(x => !x.muestra).length}`);

  // --- 2. torneo.php ----------------------------------------------------
  console.log('--- 2. torneo.php ---');
  const destacadas = sql(`SELECT GROUP_CONCAT(DISTINCT r.id_torneo) FROM enfrentamiento en INNER JOIN ronda r ON r.id_ronda = en.id_ronda
                          INNER JOIN torneo t ON t.id_torneo = r.id_torneo WHERE en.estado = 'en_vivo' AND ${PUBLICO}`);
  t.chk(destacadas.split(',').includes(String(V)), `en la base, la Valorant tiene un partido en vivo (ligas con uno: ${destacadas})`);
  let r = await p.goto(`${BASE}/torneo.php`);
  t.chk(r.status() === 200 && (await p.textContent('h1')) === 'Liga Valorant · Otoño' && (await p.title()) === 'Liga Valorant · Otoño · Stadion',
        'sin numero muestra la liga destacada: la Liga Valorant');
  const intro_v = sql(`SELECT CONCAT(fecha_inicio, '|', fecha_fin, '|', sede) FROM torneo WHERE id_torneo = ${V}`).split('|');
  t.chk((await p.textContent('main .intro')) === `Organiza Comunidad Vórtice · 12 equipos · ${fechaTexto(intro_v[0])} – ${fechaTexto(intro_v[1])} · ${intro_v[2]}`,
        'la introduccion: organizador, equipos, fechas y sede de la base');
  t.chk((await p.textContent('main .torneo-zona > section .fila .etiqueta')) === 'Liga · Esports · Fecha 8 de 11'
        && (await p.textContent('main .torneo-zona > section .fila .estado')) === 'En vivo', 'arriba del nombre: "Liga · Esports · Fecha 8 de 11" y En vivo');

  // La tabla.
  const leida = await leerTabla(p);
  const tabla = leida.filas;
  t.chk(tabla.length === 12, `la tabla tiene 12 filas (${tabla.length})`);
  // La Liga Valorant se juega al mejor de 3 mapas: sin empates. El orden
  // y las diferencias son los de la maqueta de siempre; los puntos no,
  // porque la maqueta tenia empates (ver sql/migraciones/005_ligas.sql).
  const esperada8 = ['1 Titanes CS 7 6 1 13:4 +9 18', '2 Nova Esports 7 5 2 12:6 +6 15', '3 Vortex 7 4 3 11:8 +3 12',
                     '4 Delta Gaming 7 4 3 10:9 +1 12', '5 Aurora FC 7 3 4 10:10 0 9', '6 Halcones 7 3 4 10:11 -1 9',
                     '7 Ping Masters 7 3 4 9:11 -2 9', '8 Liceo 3 7 3 4 8:11 -3 9'];
  t.chk(tabla.slice(0, 8).map(x => x.celdas).join('/') === esperada8.join('/'),
        'las 8 primeras filas: el orden y las diferencias de la maqueta, sin empates, con los mapas a favor y en contra');
  t.chk(leida.encabezados === '#|Equipo|PJ|G|P|Mapas|Dif|Pts',
        `sin empates, la columna E no esta (ni el encabezado ni las celdas), y los tantos se llaman Mapas (${leida.encabezados})`);
  t.chk(sql(`SELECT CONCAT(admite_empate, '|', (SELECT SUM(empatados) FROM tabla_posiciones tp JOIN participante pa USING (id_participante)
                                                WHERE pa.id_torneo = ${V})) FROM configuracion_torneo WHERE id_torneo = ${V}`) === '0|0',
        'en la base: admite_empate = 0 y ningun empate');
  t.chk(tabla.slice(7, 10).map(x => x.celdas).join(' / ') === '8 Liceo 3 7 3 4 8:11 -3 9 / 9 Rambla Esports 7 3 4 7:10 -3 9 / 10 Atlántida GG 7 3 4 6:9 -3 9',
        'Liceo 3, Rambla y Atlantida (9 puntos, -3): la columna Mapas muestra por que van en ese orden (8, 7 y 6 a favor)');
  t.chk(tabla.map(x => x.celdas).join('/') === tablaEsperada(V).join('/'), 'las 12 filas son las de tabla_posiciones, con el orden del desempate');
  const clasifican = parseInt(sql(`SELECT clasifican_playoffs FROM configuracion_torneo WHERE id_torneo = ${V}`), 10);
  t.chk(clasifican === 4 && tabla.every((x, i) => x.clasifica === (i < 4)), 'los que clasifican salen de la configuracion (4): las 4 primeras, y solo esas, llevan class="clasifica"');
  t.chk(tabla.every((x, i) => x.raya === (i < 4) && x.oculto === (i < 4 ? ' · clasifica a playoffs' : '')),
        'la marca no es solo color: raya vertical al borde de la fila, y "clasifica a playoffs" en texto para el lector de pantalla');
  t.chk((await p.textContent('#posiciones .tarjeta > .etiqueta')).includes('Clasifican a playoffs: los 4 primeros · Victoria 3 pts · Sin empates · Mapas: a favor y en contra · Dif: diferencia de mapas')
        && await p.$('#posiciones .tarjeta > .etiqueta .marca-clasifica[aria-hidden="true"]') !== null,
        'debajo de la tabla, la leyenda con la misma raya: clasifican los 4 primeros, sin empates, mapas y diferencia');
  t.chk(leida.alineadas.length === 0, `cada columna alinea igual el encabezado y los valores${leida.alineadas.length ? ' (no: ' + leida.alineadas.join(', ') + ')' : ''}`);
  t.chk(leida.cifras === 'lining-nums tabular-nums', `las cifras de la tabla, de altura pareja y ancho fijo (${leida.cifras})`);
  await p.setViewportSize({ width: 390, height: 900 });
  const chica = await leerTabla(p);
  const marco = await p.$eval('#posiciones .tabla-scroll', x => x.scrollWidth - x.clientWidth);
  t.chk(chica.encabezados === '#|Equipo|PJ|Mapas|Dif|Pts' && marco <= 0,
        `en el telefono (390 px) quedan #, equipo, PJ, mapas, Dif y Pts, sin desbordar su recuadro (${chica.encabezados}; ${marco} px de mas)`);
  await p.setViewportSize({ width: 1280, height: 900 });

  // Participantes.
  const equipos_v = await p.$$eval('#participantes tbody tr td:first-child', tds => tds.map(td => td.textContent));
  const equipos_base = filas(`SELECT e.nombre FROM participante p INNER JOIN equipo e ON e.id_equipo = p.id_equipo
                              WHERE p.id_torneo = ${V} AND p.estado IN ('inscripto', 'confirmado') ORDER BY p.id_participante`).map(f => f[0]);
  t.chk(equipos_v.length === 12 && equipos_v.join('|') === equipos_base.join('|'), `Participantes: los 12 equipos de la base (${equipos_v.length})`);
  t.chk((await p.textContent('#participantes .tarjeta > .etiqueta')) === '12 equipos', 'y al pie, "12 equipos"');

  // Reglas.
  const reglas = await p.$$eval('#reglas tbody tr', trs => trs.map(tr => tr.querySelector('th').textContent + ': ' + tr.querySelector('td').textContent));
  t.chk(sql(`SELECT CONCAT(puntos_victoria, '/', puntos_empate, '/', puntos_derrota, '/', criterio_desempate, '/', admite_empate) FROM configuracion_torneo WHERE id_torneo = ${V}`) === '3/1/0/diferencia/0'
        && reglas.includes('Puntaje: 3 puntos la victoria y 0 la derrota. Ningún partido termina empatado.'),
        'Reglas: 3/1/0 en la configuracion, sin empates (admite_empate = 0): no nombra los puntos del empate');
  t.chk(reglas.includes('Desempate: Primero la diferencia de mapas (a favor menos en contra); si persiste, los mapas a favor. Si todo coincide, el orden alfabético.'),
        'Reglas: el desempate de la liga, con la unidad de la disciplina');
  t.chk(reglas.includes('Playoffs: Clasifican los 4 primeros de la tabla.') && reglas.some(x => x.startsWith('Partidos: Series al mejor de 3 mapas: gana la serie quien se queda con dos, así que no hay empates.')),
        'Reglas: los playoffs y el texto de las reglas de la base');
  t.chk(await p.$$eval('#reglas tbody th', ths => ths.every(th => th.getAttribute('scope') === 'row')), 'los rotulos de Reglas son <th scope="row">');

  // Resumen.
  const vivo_base = sql(`SELECT CONCAT(el.nombre, ' vs ', ev.nombre) FROM enfrentamiento en INNER JOIN ronda r ON r.id_ronda = en.id_ronda
                         INNER JOIN participante pl ON pl.id_participante = en.id_participante_local INNER JOIN equipo el ON el.id_equipo = pl.id_equipo
                         INNER JOIN participante pv ON pv.id_participante = en.id_participante_visitante INNER JOIN equipo ev ON ev.id_equipo = pv.id_equipo
                         WHERE r.id_torneo = ${V} AND en.estado = 'en_vivo'`);
  const resumen = await p.$$eval('#resumen section.tarjeta', ss => ss.map(s => s.textContent.replace(/\s+/g, ' ').trim()));
  const ahora = await p.$$eval('#resumen section.tarjeta', ss => ss.filter(x => (x.querySelector('.etiqueta') || {}).textContent === 'Ahora')
    .map(x => x.querySelector('h3').textContent + '|' + (x.querySelector('.estado-en-vivo') || {}).textContent).join(','));
  t.chk(vivo_base === 'Titanes CS vs Vortex' && ahora === 'Titanes CS vs Vortex|En vivo', `Resumen: el partido en vivo de la base, Titanes CS vs Vortex, con el chip (${ahora})`);
  const proximo_base = sql(`SELECT CONCAT(el.nombre, ' vs ', ev.nombre, '|', en.fecha_hora) FROM enfrentamiento en INNER JOIN ronda r ON r.id_ronda = en.id_ronda
                            INNER JOIN participante pl ON pl.id_participante = en.id_participante_local INNER JOIN equipo el ON el.id_equipo = pl.id_equipo
                            INNER JOIN participante pv ON pv.id_participante = en.id_participante_visitante INNER JOIN equipo ev ON ev.id_equipo = pv.id_equipo
                            WHERE r.id_torneo = ${V} AND en.estado = 'programado' AND en.fecha_hora IS NOT NULL
                              AND en.id_participante_visitante IS NOT NULL
                              AND en.fecha_hora >= CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-03:00')
                            ORDER BY en.fecha_hora, r.numero, en.numero LIMIT 1`);
  // "Proximo" es de ahora en adelante (Montevideo): un programado con la
  // hora ya pasada no cuenta. Si no queda ninguno, la tarjeta no esta.
  const tarjeta_proximo = resumen.filter(s => s.startsWith('Próximo enfrentamiento'));
  if (proximo_base === '') {
    t.chk(tarjeta_proximo.length === 0, 'Resumen: sin partidos por jugar de ahora en adelante, no hay "Próximo enfrentamiento"');
  } else {
    const [cruce, cuando] = proximo_base.split('|');
    const ronda_proximo = sql(`SELECT r.numero FROM enfrentamiento en INNER JOIN ronda r ON r.id_ronda = en.id_ronda
                               WHERE r.id_torneo = ${V} AND en.estado = 'programado' AND en.fecha_hora = ${texto(cuando)} ORDER BY r.numero, en.numero LIMIT 1`);
    const esperado = `Próximo enfrentamiento ${cruce} Fecha ${ronda_proximo} · ${diaSemana(cuando)} ${fechaTexto(cuando)}, ${cuando.slice(11, 16)}`;
    t.chk(cuando >= new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Montevideo', dateStyle: 'short', timeStyle: 'medium' }).format(new Date()).slice(0, 16)
          && tarjeta_proximo.length === 1 && tarjeta_proximo[0].startsWith(esperado),
          `Resumen: el proximo es el primero por jugar de ahora en adelante (${esperado.replace('Próximo enfrentamiento ', '')})`);
  }
  t.chk((await p.textContent('#resumen .intro')).includes('7 fechas cerradas; la 8 en juego.'), 'Resumen: 7 fechas cerradas y la 8 en juego');
  t.chk(resumen.some(s => s.includes('Al frente de la tabla Titanes CS 18 puntos en 7 fechas')), 'Resumen: al frente, Titanes CS con 18 puntos');

  // El organizador.
  const organizados = sql(`SELECT COUNT(*) FROM torneo t WHERE t.id_usuario_organizador = (SELECT id_usuario_organizador FROM torneo WHERE id_torneo = ${V}) AND ${PUBLICO}`);
  t.chk((await p.textContent('aside .tarjeta-olivo h3')) === 'Comunidad Vórtice' && await p.$('aside .tarjeta-olivo .muestra') !== null
        && (await p.textContent('aside .tarjeta-olivo p')).startsWith(plural(+organizados, 'torneo organizado', 'torneos organizados')),
        'el organizador, con la marca De muestra y sus torneos contados en la base');

  // El menu y "Saltar al contenido" de la liga destacada: dos copias.
  const menu = await p.$$eval('nav a', as => as.map(a => [a.textContent, a.className, a.getAttribute('aria-current') || '',
    a.getAttribute('data-vista') || '', a.getAttribute('data-fuera') || ''].join('|')).join(' '));
  t.chk(menu === 'Inicio|||| Torneos|activo|true||posiciones Torneos|||posiciones| Calendario|||| Posiciones||||posiciones '
               + 'Posiciones|activo|page|posiciones| Organizadores||||',
        'la liga destacada lleva Torneos y Posiciones dos veces en el menu (data-vista / data-fuera)');
  const saltos = await p.$$eval('a.saltar', as => as.map(a => a.getAttribute('data-vista') + '>' + a.getAttribute('href')).join(' '));
  t.chk(saltos === 'resumen>#contenido calendario>#calendario posiciones>#posiciones participantes>#participantes reglas>#reglas',
        'y una copia de "Saltar al contenido" por vista');

  // Las cinco pestanas, por ancla.
  let e = await estadoVistas(p);
  t.chk(e.vistas === 'resumen' && e.pestana === '#resumen|page' && e.menu === 'Torneos|true' && e.saltar === '#contenido',
        `sin ancla: Resumen, el menu marca Torneos (true), saltar va a #contenido (${JSON.stringify(e)})`);
  for (const vista of ['calendario', 'posiciones', 'participantes', 'reglas', 'resumen']) {
    await p.goto(`${BASE}/torneo.php#${vista}`);
    e = await estadoVistas(p);
    const menu_esperado = vista === 'posiciones' ? 'Posiciones|page' : 'Torneos|true';
    const saltar_esperado = vista === 'resumen' ? '#contenido' : `#${vista}`;
    t.chk(e.vistas === vista && e.pestana === `#${vista}|page` && e.menu === menu_esperado && e.saltar === saltar_esperado,
          `#${vista}: se ve solo esa vista${vista === 'resumen' ? '' : ' (Resumen oculta)'}, su pestana marcada, el menu en ${menu_esperado}, saltar a ${saltar_esperado}`
          + (e.vistas === vista ? '' : ` (${JSON.stringify(e)})`));
  }
  // Con un clic en la pestana, igual.
  await p.goto(`${BASE}/torneo.php`);
  await p.click('#resumen .pestanas a[href="#reglas"]');
  e = await estadoVistas(p);
  t.chk(e.vistas === 'reglas' && p.url().endsWith('torneo.php#reglas'), 'un clic en la pestana Reglas abre Reglas, sin JavaScript');
  // "Posiciones" del menu, desde otra pagina.
  await p.goto(`${BASE}/torneos.php`);
  await Promise.all([p.waitForNavigation(), p.click('nav a[href="torneo.php#posiciones"]')]);
  e = await estadoVistas(p);
  t.chk(e.vistas === 'posiciones' && e.menu === 'Posiciones|page', '"Posiciones" del menu, desde torneos.php, abre la tabla de la destacada y la marca');

  // Otra liga: sin copias en el menu, que marca siempre Torneos.
  await p.goto(`${BASE}/torneo.php?id=${B}`);
  t.chk((await p.textContent('h1')) === 'Liga Barrial del Cerro', `torneo.php?id=${B} muestra la Liga Barrial`);
  t.chk(await p.$$eval('nav [data-vista], nav [data-fuera]', x => x.length) === 0
        && await p.$$eval('nav a.activo', as => as.map(a => a.textContent + '|' + a.getAttribute('aria-current')).join(',')) === 'Torneos|true',
        'en otra liga el menu no tiene copias y marca solo Torneos, con aria-current="true"');
  await p.goto(`${BASE}/torneo.php?id=${B}#posiciones`);
  e = await estadoVistas(p);
  t.chk(e.vistas === 'posiciones' && e.menu === 'Torneos|true' && e.saltar === '#posiciones',
        'con #posiciones abre su tabla, y el menu sigue en Torneos (Posiciones es la de la destacada)');
  const leida_b = await leerTabla(p);
  const tabla_b = leida_b.filas.map(x => x.celdas + (x.clasifica ? ' *' : ''));
  t.chk(tabla_b.join('/') === tablaEsperada(B).join('/'), `la tabla de la Barrial es la de la base, sin clasificados (${tabla_b.length} filas)`);
  t.chk(leida_b.encabezados === '#|Equipo|PJ|G|E|P|Goles|Dif|Pts' && !(await p.textContent('#posiciones .tarjeta > .etiqueta')).includes('Clasifican'),
        `la Barrial admite empate: la columna E esta, los tantos se llaman Goles, y no hay leyenda de playoffs (${leida_b.encabezados})`);
  t.chk((await p.textContent('main .torneo-zona > section .fila .etiqueta')) === 'Liga · Fútbol 5 · Fecha 4 de 9'
        && (await p.textContent('#posiciones .tarjeta > .etiqueta')).includes('diferencia de goles'), 'la Barrial: "Fecha 4 de 9" y la diferencia de goles');

  // La de la inscripcion abierta.
  await p.goto(`${BASE}/torneo.php?id=${S}`);
  t.chk((await p.textContent('main .torneo-zona > section .fila .etiqueta')) === 'Liga · Fútbol · 9 de 12 equipos'
        && (await p.textContent('#resumen .intro')).includes('9 de 12 lugares ocupados. La primera fecha, el domingo 4 de octubre.'),
        'la Liga Interna: 9 de 12, y la primera fecha el domingo 4 de octubre');
  t.chk(await p.$$eval('#participantes tbody tr', trs => trs.length) === parseInt(sql(`SELECT COUNT(*) FROM participante WHERE id_torneo = ${S} AND estado IN ('inscripto', 'confirmado')`), 10),
        'sus participantes son los de la base');
  // Una liga de muestra no recibe pedidos: la organiza una cuenta que no
  // inicia sesion, y el pedido quedaria sin resolver.
  t.chk(await p.$('.pedir-lugar a, .pedir-lugar form') === null
        && (await p.textContent('.pedir-lugar')).replace(/\s+/g, ' ').trim() === 'Pedir lugar Una liga de muestra no recibe pedidos.'
        && await p.$('.pedir-lugar .enlace-apagado[aria-disabled="true"]') !== null,
        'la Liga Interna es de muestra: "Pedir lugar" apagado, con el porque');

  // La liga nueva: sin la marca.
  await p.goto(`${BASE}/torneo.php?id=${N}`);
  t.chk((await p.textContent('h1')) === liga && (await p.textContent('aside .tarjeta-olivo h3')) === 'Olivia De la prueba'
        && await p.$('.muestra') === null, 'la liga nueva: su organizador, sin ninguna marca De muestra');
  t.chk(await p.$eval('.pedir-lugar a', a => a.getAttribute('href') + '|' + a.textContent) === 'login.php|Iniciar sesión para pedir lugar',
        'en la liga nueva, sin sesion, "Iniciar sesión para pedir lugar" lleva al acceso');

  // Un numero que no existe.
  r = await p.goto(`${BASE}/torneo.php?id=999999`);
  t.chk(r.status() === 404 && (await p.textContent('h1')) === 'Sin torneo con ese número' && (await p.title()) === 'Torneo no encontrado · Stadion',
        'torneo.php?id=999999: 404, "Sin torneo con ese número"');
  t.chk(await p.$$eval('a.saltar', as => as.length) === 1 && await p.$$eval('nav a.activo', as => as.map(a => a.textContent + '|' + a.getAttribute('aria-current')).join(',')) === 'Torneos|true',
        'y ahi un solo "Saltar al contenido" y el menu en Torneos');

  // --- 3. calendario.php --------------------------------------------------
  console.log('--- 3. calendario.php ---');
  const semana = (desde) => {
    const hasta = sumarDias(desde, 6);
    const rango = `${PUBLICO} AND en.estado <> 'anulado' AND COALESCE(DATE(en.fecha_hora), r.fecha_fin) BETWEEN '${desde}' AND '${hasta}'`;
    const desde_base = `FROM enfrentamiento en INNER JOIN ronda r ON r.id_ronda = en.id_ronda INNER JOIN torneo t ON t.id_torneo = r.id_torneo WHERE ${rango}`;
    return { partidos: parseInt(sql(`SELECT COUNT(*) ${desde_base}`), 10),
             dias: filas(`SELECT DISTINCT COALESCE(DATE(en.fecha_hora), r.fecha_fin) AS dia ${desde_base} ORDER BY dia`).map(f => f[0]) };
  };
  const leerSemana = () => p.evaluate(() => ({
    titulo: document.querySelector('main h1').textContent,
    dias: [...document.querySelectorAll('.agenda-dia .dia')].map(d => d.textContent),
    partidos: [...document.querySelectorAll('.agenda .partido')].map(x => ({
      id: (x.querySelector('.torneo a') || { getAttribute: () => '' }).getAttribute('href'),
      arriba: x.querySelector('.torneo').textContent, lados: x.querySelector('.lados').textContent,
      hora: x.querySelector('.hora').textContent, vivo: x.querySelector('.estado-en-vivo') !== null,
      muestra: x.querySelectorAll('.muestra').length })),
    enlaces: [...document.querySelectorAll('.semanas a')].map(a => a.textContent.trim() + '>' + a.getAttribute('href')),
    hoy: [...document.querySelectorAll('.semanas span.enlace-apagado[aria-disabled="true"]')].map(s => s.textContent),
    aside: document.querySelector('aside .tarjeta-olivo h3').textContent,
    aside_etiqueta: document.querySelector('aside .tarjeta-olivo .etiqueta').textContent
  }));
  const vivo_dia = sql(`SELECT DATE(en.fecha_hora) FROM enfrentamiento en INNER JOIN ronda r ON r.id_ronda = en.id_ronda INNER JOIN torneo t ON t.id_torneo = r.id_torneo
                        WHERE en.estado = 'en_vivo' AND ${PUBLICO} ORDER BY en.fecha_hora LIMIT 1`);
  const lunes = lunesDe(vivo_dia);
  const muestras = new Set(lista.filter(x => x.muestra).map(x => x.id));
  const cal = await estable(() => semana(lunes), async () => { await p.goto(`${BASE}/calendario.php`); return leerSemana(); });
  let s = cal.visto;
  const base14 = cal.base;
  t.chk(lunes === '2026-09-14' && s.titulo === 'Semana del 14 al 20 de septiembre',
        `sin semana: la del partido en vivo de la base (${vivo_dia}), "Semana del 14 al 20 de septiembre"`);
  const dias14 = s.dias.map(d => diaDeEtiqueta(d, 2026));
  t.chk(dias14.every(d => d && d.bien), `los dias de la semana son los de 2026 (${s.dias.join(', ')})`);
  t.chk(s.dias.includes('Viernes 18 de septiembre'), 'el 18 de septiembre es viernes');
  t.chk(dias14.map(d => d && d.fecha).join(',') === base14.dias.join(','), `los dias con partidos son los de la base (${base14.dias.length})`);
  t.chk(s.partidos.length === base14.partidos && s.aside === plural(base14.partidos, 'partido', 'partidos'),
        `los partidos de la semana son los de la base (${s.partidos.length} de ${base14.partidos}), y el costado dice "${s.aside}"`);
  const domingo = await p.$$eval('.agenda-dia', ds => {
    const d = ds.find(x => x.querySelector('.dia').textContent === 'Domingo 20 de septiembre');
    const x = d ? [...d.querySelectorAll('.partido')].find(y => y.querySelector('.lados').textContent === 'Liceo 3 vs Sur Gaming') : null;
    return x ? x.querySelector('.hora').textContent + '|' + x.querySelector('.torneo').textContent : '';
  });
  t.chk(domingo.startsWith('—|') && domingo.includes('Fecha 8 · Horario a confirmar'), 'el domingo 20, "Liceo 3 vs Sur Gaming" sin hora: "Horario a confirmar"');
  const en_vivo = s.partidos.filter(x => x.vivo);
  t.chk(en_vivo.length === 1 && en_vivo[0].lados === 'Titanes CS vs Vortex' && (await p.textContent('main .intro')).includes('Uno en vivo.'),
        'el partido en vivo lleva su chip, y la introduccion lo cuenta');
  const mal_marcados = s.partidos.filter(x => {
    const m = /^torneo\.php\?id=(\d+)$/.exec(x.id || '');
    return !m || (x.muestra === 1) !== muestras.has(parseInt(m[1], 10)) || x.muestra > 1;
  });
  t.chk(mal_marcados.length === 0 && s.partidos.some(x => x.muestra === 1),
        `cada renglon de una liga de muestra lleva "De muestra", y ningun otro (${mal_marcados.length} mal)`);
  const anterior = sumarDias(lunes, -7), siguiente = sumarDias(lunes, 7);
  t.chk(s.enlaces[0] === `← Semana anterior>calendario.php?semana=${anterior}` && s.enlaces[s.enlaces.length - 1] === `Semana siguiente →>calendario.php?semana=${siguiente}`,
        `Semana anterior y siguiente llevan ?semana= (${s.enlaces.join(' · ')})`);
  if (lunesDe(hoy) !== lunes) {
    t.chk(s.enlaces.includes(`Hoy>calendario.php?semana=${lunesDe(hoy)}`) && s.hoy.length === 0, `fuera de la semana de hoy, "Hoy" es un enlace a ?semana=${lunesDe(hoy)}`);
    t.chk(s.aside_etiqueta === 'Resumen de la semana', `fuera de la semana de hoy, el costado no dice "Esta semana" (${s.aside_etiqueta})`);
  }
  await Promise.all([p.waitForNavigation(), p.click('.semanas a:first-child')]);
  t.chk((await p.textContent('main h1')) === tituloSemana(anterior) && p.url().endsWith(`?semana=${anterior}`),
        `Semana anterior funciona: "${tituloSemana(anterior)}"`);
  const cal21 = await estable(() => semana(siguiente), async () => {
    await p.goto(`${BASE}/calendario.php`);
    await Promise.all([p.waitForNavigation(), p.click('.semanas a:last-child')]);
    return leerSemana();
  });
  s = cal21.visto;
  const base21 = cal21.base;
  t.chk(s.titulo === 'Semana del 21 al 27 de septiembre' && p.url().endsWith(`?semana=${siguiente}`), `Semana siguiente funciona: "${s.titulo}"`);
  t.chk(s.partidos.length === base21.partidos && s.dias.map(d => (diaDeEtiqueta(d, 2026) || {}).fecha).join(',') === base21.dias.join(',')
        && s.dias.every(d => (diaDeEtiqueta(d, 2026) || {}).bien), `y muestra los partidos de la base de esa semana (${s.partidos.length}), con sus dias bien`);
  for (const basura of ['basura', '2026-02-30', '2026-9-1', '']) {
    await p.goto(`${BASE}/calendario.php?semana=${encodeURIComponent(basura)}`);
    t.chk((await p.textContent('main h1')) === 'Semana del 14 al 20 de septiembre', `?semana=${basura || '(vacia)'} cae en la semana por defecto`);
  }
  await p.goto(`${BASE}/calendario.php?semana=2026-09-26`);
  t.chk((await p.textContent('main h1')) === 'Semana del 21 al 27 de septiembre', '?semana=2026-09-26 muestra la semana del 21 al 27');
  await p.goto(`${BASE}/calendario.php?semana=${hoy}`);
  s = await leerSemana();
  t.chk(s.titulo === tituloSemana(lunesDe(hoy)) && s.hoy.join() === 'Hoy' && !s.enlaces.some(x => x.startsWith('Hoy>')),
        `?semana=${hoy} (hoy): "Hoy" esta apagado (span.enlace-apagado), porque es la semana de hoy`);
  t.chk(s.aside_etiqueta === 'Esta semana', 'y el costado dice "Esta semana"');
  await p.goto(`${BASE}/calendario.php?semana=2026-09-28`);
  t.chk((await p.textContent('main h1')) === 'Semana del 28 de septiembre al 4 de octubre', 'una semana que cruza de mes: "Semana del 28 de septiembre al 4 de octubre"');

  // --- 4. llave.php --------------------------------------------------------
  console.log('--- 4. llave.php ---');
  vista = await estable(publicos, async () => {
    await p.goto(`${BASE}/llave.php`);
    return { intro: await p.textContent('main .intro'),
             ligas: await p.$$eval('.lista-apilada li', lis => lis.map(li => li.querySelector('a').getAttribute('href') + '|' + li.querySelectorAll('.muestra').length)) };
  });
  const no_ligas = vista.base.filter(x => x.modulo !== 'Liga').length;
  t.chk(no_ligas === 0 && vista.visto.intro.includes('Ninguna competencia en juego la usa: por ahora, todas son ligas.'),
        'en la base solo hay ligas, y la llave lo dice');
  const ligas_llave = vista.visto.ligas;
  const ligas_base = vista.base.filter(x => x.modulo === 'Liga');
  t.chk(ligas_llave.join(' ') === ligas_base.map(x => `torneo.php?id=${x.id}#posiciones|${x.muestra ? 1 : 0}`).join(' '),
        `lista las ${ligas_base.length} ligas publicas, cada una con el enlace a su tabla y la marca solo en las de muestra`);
  t.chk(await p.$$eval('nav a.activo', as => as.map(a => a.textContent + '|' + a.getAttribute('aria-current')).join(',')) === 'Torneos|true', 'el menu marca Torneos (true)');
  await p.goto(`${BASE}/llave.php?id=${V}`);
  t.chk((await p.textContent('main .intro')) === 'Liga Valorant · Otoño es una liga: no tiene llave. Su orden lo da la tabla de posiciones.'
        && await p.$(`main section:first-child a[href="torneo.php?id=${V}#posiciones"]`) !== null,
        `llave.php?id=${V} dice que la Valorant es una liga, y lleva a su tabla`);

  // --- 5. index.php --------------------------------------------------------
  console.log('--- 5. index.php ---');
  // La base, torneos.php y el inicio, del mismo momento.
  vista = await estable(() => ({ lista: publicos(), formatos: parseInt(sql('SELECT COUNT(*) FROM modulo_competencia'), 10) }), async () => {
    const primeras = (await abrirTorneos()).tarjetas.slice(0, 3);
    await p.goto(`${BASE}/index.php`);
    return { primeras, tarjetas: await leerTarjetas(p, 'main'),
             datos: await p.$$eval('.datos > div', ds => ds.map(d => d.querySelector('strong').textContent + ' ' + d.querySelector('.etiqueta').textContent)),
             marcas: await p.$$eval('.datos > div', ds => ds.map(d => (d.querySelector('.muestra') || { textContent: '' }).textContent)) };
  });
  const datos = vista.visto.datos;
  const activos = vista.base.lista.filter(x => x.estado === 'inscripcion' || x.estado === 'en_curso').length;
  const participantes = vista.base.lista.reduce((n, x) => n + x.inscriptos, 0);
  t.chk(datos.join(' / ') === `${activos} Torneos activos / ${participantes} Participantes / ${vista.base.formatos} Formatos`,
        `los tres numeros son los de la base (${datos.join(' / ')})`);
  // La marca de cada total: "De muestra" si todo sale de ligas de
  // muestra, "Incluye muestra" si una parte, nada si ninguna. Formatos
  // es el catalogo: no lleva.
  const marca = (de_muestra, total) => de_muestra <= 0 ? '' : (de_muestra >= total ? 'De muestra' : 'Incluye muestra');
  const activas = vista.base.lista.filter(x => x.estado === 'inscripcion' || x.estado === 'en_curso');
  const esperadas = [marca(activas.filter(x => x.muestra).length, activos),
                     marca(vista.base.lista.filter(x => x.muestra).reduce((n, x) => n + x.inscriptos, 0), participantes), ''];
  t.chk(vista.visto.marcas.join('|') === esperadas.join('|') && esperadas[0] === 'Incluye muestra',
        `los totales que suman ligas de muestra lo dicen (${vista.visto.marcas.map(m => m || '—').join(' / ')})`);
  tarjetas = vista.visto.tarjetas;
  const primeras = vista.visto.primeras;
  t.chk(tarjetas.length === 3 && tarjetas.every((k, i) => k.enlace === primeras[i].enlace && k.nombre === primeras[i].nombre && k.chip === primeras[i].chip
        && k.barra === primeras[i].barra && k.muestra === primeras[i].muestra && primeras[i].linea.endsWith(' · ' + k.linea)),
        `las tarjetas son las 3 primeras de torneos.php (${tarjetas.map(k => k.nombre).join(', ')})`);
  const costado = await p.evaluate(() => {
    const d = document.querySelector('aside .tarjeta');
    return { etiqueta: d.querySelector('.etiqueta').textContent, chip: (d.querySelector('.estado') || { textContent: '' }).textContent,
             nombre: d.querySelector('h3').textContent,
             filas: [...d.querySelectorAll('table.en-vivo tr')].map(tr => [...tr.children].map(td => td.textContent).join(' | ')),
             enlace: d.querySelector('a').getAttribute('href') + '|' + d.querySelector('a').textContent.replace(/\s+/g, ' ').trim(),
             muestra: d.querySelectorAll('.muestra').length };
  });
  t.chk(costado.etiqueta === 'Fecha 8' && costado.chip === 'En vivo' && costado.nombre === 'Liga Valorant · Otoño' && costado.muestra === 1,
        'el recuadro del costado: la Fecha 8 de la Valorant, En vivo, De muestra');
  const cruces = filas(`SELECT el.nombre, ev.nombre, en.estado, IFNULL(TIME_FORMAT(en.fecha_hora, '%H:%i'), '') FROM enfrentamiento en
                        INNER JOIN ronda r ON r.id_ronda = en.id_ronda
                        INNER JOIN participante pl ON pl.id_participante = en.id_participante_local INNER JOIN equipo el ON el.id_equipo = pl.id_equipo
                        INNER JOIN participante pv ON pv.id_participante = en.id_participante_visitante INNER JOIN equipo ev ON ev.id_equipo = pv.id_equipo
                        WHERE r.id_torneo = ${V} AND r.numero = 8 ORDER BY en.fecha_hora IS NULL, en.fecha_hora, en.numero LIMIT 3`)
    .map(([l, v, estado, hora]) => `${l} | ${estado === 'en_vivo' || hora === '' ? '—' : hora} | ${v}`);
  t.chk(costado.filas.join(' / ') === cruces.join(' / '), `con los tres primeros partidos de la fecha, de la base (${costado.filas.join(' / ')})`);
  t.chk(costado.enlace === `torneo.php?id=${V}#posiciones|Tabla completa de Liga Valorant · Otoño →`, '"Tabla completa" lleva a la tabla de la Valorant');
  await Promise.all([p.waitForNavigation(), p.click('aside .tarjeta a[href$="#posiciones"]')]);
  e = await estadoVistas(p);
  t.chk(e.vistas === 'posiciones' && (await p.textContent('h1')) === 'Liga Valorant · Otoño', 'y al seguirlo, abre la vista Posiciones');

  // --- 6b. La liga nueva, cancelada, deja de ser publica ---------------------
  console.log('--- 6. La liga nueva, cancelada ---');
  sql(`UPDATE torneo SET estado = 'cancelado' WHERE id_torneo = ${N}`);
  vista = await estable(publicos, abrirTorneos);
  lista = vista.base;
  tarjetas = vista.visto.tarjetas;
  t.chk(!tarjetas.some(k => k.enlace === `torneo.php?id=${N}`) && tarjetas.length === lista.length,
        `cancelada, sale de torneos.php (${tarjetas.length} tarjetas, ${lista.length} en la base)`);
  t.chk(vista.visto.intro.startsWith(plural(lista.length, 'competencia pública', 'competencias públicas')), 'y la introduccion la descuenta');
  r = await p.goto(`${BASE}/torneo.php?id=${N}`);
  t.chk(r.status() === 404 && (await p.textContent('h1')) === 'Sin torneo con ese número', 'torneo.php de la cancelada: 404');
  await p.goto(`${BASE}/llave.php`);
  t.chk(!(await p.content()).includes(liga), 'llave.php no la lista');
  await p.goto(`${BASE}/llave.php?id=${N}`);
  const llave_n = await p.content();
  t.chk(!llave_n.includes(liga) && !llave_n.includes(`torneo.php?id=${N}#posiciones`),
        'llave.php?id= de la cancelada no la nombra ni lleva a su tabla (que da 404): no es publica');
  vista = await estable(publicos, async () => { await p.goto(`${BASE}/index.php`); return p.$$eval('.datos strong', ss => ss.map(x => x.textContent)); });
  t.chk(vista.visto[0] === String(vista.base.filter(x => x.estado === 'inscripcion' || x.estado === 'en_curso').length), 'el inicio ya no la cuenta entre los activos');

  // --- 7. Lo que vale para todas las paginas -----------------------------------
  console.log('--- 7. En todas las paginas ---');
  const paginas = ['index.php', 'torneos.php', 'torneo.php', `torneo.php?id=${B}`, `torneo.php?id=${S}`, 'torneo.php?id=999999',
                   'calendario.php', `calendario.php?semana=${hoy}`, 'llave.php', `llave.php?id=${V}`, 'login.php', 'registro.php'];
  const marcas = { css: new Map(), js: new Map() };
  for (const pagina of paginas) {
    const q = await ctx.newPage();
    await q.goto(`${BASE}/${pagina}`);
    const d = await q.evaluate(() => {
      const partes = [];
      const w = document.createTreeWalker(document.documentElement, NodeFilter.SHOW_TEXT);
      while (w.nextNode()) { const padre = w.currentNode.parentElement; if (padre && !padre.closest('script, style')) partes.push(w.currentNode.nodeValue); }
      for (const el of document.querySelectorAll('[aria-label], [title], [placeholder], [alt], meta[name="description"]')) {
        for (const a of ['aria-label', 'title', 'placeholder', 'alt', 'content']) if (el.hasAttribute(a)) partes.push(el.getAttribute(a));
      }
      const primero = document.querySelector('a');
      return {
        primero: primero ? primero.className + '|' + primero.textContent : '',
        main: document.querySelectorAll('main#contenido').length,
        vacios: document.querySelectorAll('a[href="#"], [action="#"]').length,
        css: [...document.querySelectorAll('link[rel="stylesheet"]')].map(l => l.getAttribute('href')),
        js: [...document.querySelectorAll('script[src]')].map(x => x.getAttribute('src')),
        panel: [...document.querySelectorAll('[href], [action]')].map(x => x.getAttribute('href') || x.getAttribute('action')).filter(h => /panel\.html/i.test(h)),
        texto: partes.join(' ')
      };
    });
    await q.keyboard.press('Tab');
    const foco = await q.evaluate(() => document.activeElement.className + '|' + document.activeElement.textContent);
    t.chk(d.primero === 'saltar|Saltar al contenido' && foco === 'saltar|Saltar al contenido' && d.main === 1,
          `${pagina}: el primer enlace (y el primer Tab) es "Saltar al contenido", y hay un <main id="contenido">`);
    t.chk(d.vacios === 0, `${pagina}: ningun href="#"`);
    const css = d.css.map(h => /(?:^|\/)css\/style\.css\?v=([0-9a-f]{10})$/.exec(h));
    const js = d.js.map(h => /(?:^|\/)js\/tema\.js\?v=([0-9a-f]{10})$/.exec(h));
    t.chk(css.length === 1 && css[0] && js.length === 1 && js[0], `${pagina}: style.css y tema.js con ?v= y 10 hexadecimales (${d.css.concat(d.js).join(' ')})`);
    if (css[0]) marcas.css.set(css[0][1], new URL(d.css[0], q.url()).href);
    if (js[0]) marcas.js.set(js[0][1], new URL(d.js[0], q.url()).href);
    const malas = voseo(d.texto);
    t.chk(malas.length === 0, `${pagina}: sin voseo ni "tu/tus"${malas.length ? ' (' + malas.join(', ') + ')' : ''}`);
    t.chk(d.panel.length === 0, `${pagina}: ningun enlace a panel.html`);
    await q.close();
  }
  for (const [que, mapa] of Object.entries(marcas)) {
    const [marca, url] = [...mapa.entries()][0] || [];
    let real = '';
    if (url) {
      const bajada = await ctx.request.get(url);
      real = crypto.createHash('md5').update(await bajada.body()).digest('hex').slice(0, 10);
    }
    t.chk(mapa.size === 1 && marca === real, `la marca de ${que === 'css' ? 'style.css' : 'tema.js'} es la misma en todas (${[...mapa.keys()].join(', ')}) y es la del archivo (${real})`);
  }
  for (const vieja of ['panel', 'index', 'torneos', 'torneo', 'calendario', 'llave', 'login', 'registro', 'crear', 'perfil', 'admin']) {
    const x = await ctx.request.get(`${BASE}/${vieja}.html`, { maxRedirects: 0 });
    const destino = x.headers()['location'] || '';
    t.chk(x.status() === 301 && destino.replace(/^https?:\/\/[^/]+/, '') === new URL(`${BASE}/${vieja}.php`).pathname,
          `${vieja}.html responde 301 a ${vieja}.php (${x.status()} ${destino})`);
  }

  await nav.close();
  t.fin();
})().catch(async e => { console.error(e); process.exitCode = 1; if (nav) await nav.close(); });
