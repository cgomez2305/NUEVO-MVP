#!/usr/bin/env node
/*
  Capturas reales de pantallas de Veci en varios anchos, para revisar un
  cambio de diseño antes de darlo por terminado.

  Uso:
    node capturas.js --rutas /t/donamaria,/t/salonbonita --out /ruta/salida \
      [--base http://localhost:8000] [--anchos 390,768,1280] [--alto 844] \
      [--completa] [--oscuro] [--cookies archivo-netscape.txt]

  - Por defecto captura solo lo que se ve en pantalla (viewport), que es lo
    que de verdad ve una persona; --completa agrega la página entera.
  - --oscuro emula prefers-color-scheme: dark.
  - --cookies reutiliza un cookie jar de curl (formato Netscape) para
    capturar pantallas con sesión (panel del dueño).
  - Reporta errores de consola y peticiones fallidas: un diseño "bonito"
    con un 404 de CSS o un error de JS no está terminado.

  Necesita puppeteer-core. Busca el módulo en PPTR_PATH, luego en
  /tmp/pptr/node_modules, luego en node_modules del proyecto; y Chromium en
  CHROME_PATH o en /opt/pw-browsers.
*/
const fs = require('fs');
const path = require('path');

function arg(nombre, def) {
  const i = process.argv.indexOf('--' + nombre);
  if (i === -1) return def;
  const v = process.argv[i + 1];
  return v === undefined || v.startsWith('--') ? true : v;
}

function cargarPuppeteer() {
  const candidatos = [process.env.PPTR_PATH, '/tmp/pptr/node_modules/puppeteer-core', 'puppeteer-core'].filter(Boolean);
  for (const c of candidatos) {
    try { return require(c); } catch (e) { /* siguiente */ }
  }
  console.error('No encontré puppeteer-core. Instálalo en /tmp/pptr: (cd /tmp/pptr && npm i puppeteer-core)');
  process.exit(1);
}

function buscarChrome() {
  if (process.env.CHROME_PATH) return process.env.CHROME_PATH;
  const base = '/opt/pw-browsers';
  if (fs.existsSync(base)) {
    for (const dir of fs.readdirSync(base).filter((d) => d.startsWith('chromium-')).sort().reverse()) {
      const p = path.join(base, dir, 'chrome-linux', 'chrome');
      if (fs.existsSync(p)) return p;
    }
  }
  return '/usr/bin/chromium';
}

function leerCookies(archivo, dominio) {
  return fs.readFileSync(archivo, 'utf8').split('\n')
    .map((l) => l.replace(/^#HttpOnly_/, ''))
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => l.split('\t'))
    .filter((c) => c.length >= 7)
    .map((c) => ({ name: c[5], value: c[6].trim(), domain: dominio, path: c[2] || '/' }));
}

(async () => {
  const base = arg('base', 'http://localhost:8000');
  const rutas = String(arg('rutas', '/')).split(',').map((r) => r.trim()).filter(Boolean);
  const anchos = String(arg('anchos', '390,1280')).split(',').map(Number);
  const alto = Number(arg('alto', 844));
  const out = arg('out', path.join(process.cwd(), 'capturas'));
  const completa = arg('completa', false) === true;
  const oscuro = arg('oscuro', false) === true;
  const cookies = arg('cookies', null);
  fs.mkdirSync(out, { recursive: true });

  const puppeteer = cargarPuppeteer();
  const navegador = await puppeteer.launch({
    executablePath: buscarChrome(),
    args: [
      '--no-sandbox', '--disable-dev-shm-usage', '--font-render-hinting=none',
      // En el contenedor de Claude Code el tráfico sale por un proxy con CA
      // propia (ya instalada en el almacén NSS del navegador): hay que usarlo
      // explícitamente para que carguen las fuentes de Google.
      ...(process.env.HTTPS_PROXY ? ['--proxy-server=' + process.env.HTTPS_PROXY, '--proxy-bypass-list=localhost;127.0.0.1'] : []),
    ],
  });

  let problemas = 0;
  for (const ruta of rutas) {
    for (const ancho of anchos) {
      const pagina = await navegador.newPage();
      const errores = [];
      pagina.on('console', (m) => { if (m.type() === 'error') errores.push('consola: ' + m.text()); });
      pagina.on('pageerror', (e) => errores.push('js: ' + e.message));
      pagina.on('requestfailed', (r) => errores.push('falló: ' + r.url()));
      pagina.on('response', (r) => { if (r.status() >= 400) errores.push(r.status() + ': ' + r.url()); });

      await pagina.setViewport({ width: ancho, height: ancho < 600 ? alto : 900, deviceScaleFactor: ancho < 600 ? 2 : 1 });
      if (oscuro) await pagina.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'dark' }]);
      if (cookies) await pagina.setCookie(...leerCookies(cookies, new URL(base).hostname));

      await pagina.goto(base + ruta, { waitUntil: 'networkidle0', timeout: 30000 });
      await pagina.evaluate(() => document.fonts && document.fonts.ready);
      await new Promise((r) => setTimeout(r, 700)); // animaciones de entrada

      const desborde = await pagina.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      if (desborde > 0) errores.push('scroll horizontal de ' + desborde + 'px');

      const nombre = ruta.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'inicio';
      const sufijo = (oscuro ? '-oscuro' : '') + '-' + ancho;
      await pagina.screenshot({ path: path.join(out, nombre + sufijo + '.png') });
      if (completa) await pagina.screenshot({ path: path.join(out, nombre + sufijo + '-completa.png'), fullPage: true });

      const marca = errores.length ? '✗' : '✓';
      console.log(`${marca} ${ruta} @${ancho}px → ${nombre + sufijo}.png`);
      errores.forEach((e) => console.log('    ' + e));
      problemas += errores.length;
      await pagina.close();
    }
  }
  await navegador.close();
  process.exit(problemas ? 2 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
