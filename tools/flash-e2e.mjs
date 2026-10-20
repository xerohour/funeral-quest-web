// Drives the real welcome.swf (through Ruffle) in headless Chrome with trusted mouse and
// keyboard input, then saves a screenshot after every step.
//
//   node tools/flash-e2e.mjs [baseUrl] [logon] [password] ["label@x,y;label@x,y"]
//
// Defaults: http://127.0.0.1:8091 test test. The optional last argument lists extra clicks
// after logging on, in Flash stage coordinates (550x400). Screenshots go to .e2e/.

import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const base = process.argv[2] ?? 'http://127.0.0.1:8091';
const logon = process.argv[3] ?? 'test';
const password = process.argv[4] ?? 'test';
const steps = (process.argv[5] ?? '').split(';').filter(Boolean).map((step) => {
  const [label, at] = step.split('@');
  const [x, y] = at.split(',').map(Number);
  return { label, x, y };
});
const chromePaths = [
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
  '/usr/bin/google-chrome',
  '/usr/bin/chromium',
  '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
];
const outDir = join(process.cwd(), process.env.FQ_E2E_OUT ?? '.e2e');
mkdirSync(outDir, { recursive: true });

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function findChrome() {
  const { existsSync } = await import('node:fs');
  const found = chromePaths.find((path) => existsSync(path));
  if (!found) throw new Error('Chrome or Edge was not found.');
  return found;
}

async function launch() {
  const profile = mkdtempSync(join(tmpdir(), 'fq-e2e-'));
  const port = 9300 + Math.floor(Math.random() * 500);
  const chrome = spawn(await findChrome(), [
    '--headless=new',
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${profile}`,
    '--window-size=1100,800',
    '--autoplay-policy=no-user-gesture-required',
    '--no-first-run',
    '--no-default-browser-check',
    'about:blank',
  ], { stdio: 'ignore' });
  for (let tries = 0; tries < 50; tries++) {
    try {
      const pages = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
      const page = pages.find((entry) => entry.type === 'page');
      if (page) return { chrome, profile, ws: page.webSocketDebuggerUrl };
    } catch {}
    await sleep(200);
  }
  chrome.kill();
  throw new Error('Chrome did not open a debugging port.');
}

function connect(url) {
  const socket = new WebSocket(url);
  let nextId = 1;
  const waiting = new Map();
  const listeners = [];
  socket.addEventListener('message', (event) => {
    const message = JSON.parse(event.data);
    if (message.id && waiting.has(message.id)) {
      const { resolve, reject } = waiting.get(message.id);
      waiting.delete(message.id);
      if (message.error) reject(new Error(message.error.message));
      else resolve(message.result);
    } else if (message.method) {
      for (const listener of listeners) listener(message);
    }
  });
  const ready = new Promise((resolve) => socket.addEventListener('open', resolve));
  return {
    ready,
    on: (listener) => listeners.push(listener),
    send: (method, params = {}) => new Promise((resolve, reject) => {
      const id = nextId++;
      waiting.set(id, { resolve, reject });
      socket.send(JSON.stringify({ id, method, params }));
    }),
    close: () => socket.close(),
  };
}

const { chrome, profile, ws } = await launch();
const cdp = connect(ws);
await cdp.ready;
const requests = [];
cdp.on((message) => {
  if (message.method === 'Network.requestWillBeSent' && message.params.request.url.includes('request.xp')) {
    requests.push(message.params.request.url.replace(base, ''));
  }
});
await cdp.send('Network.enable');
await cdp.send('Page.enable');
await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1100, height: 800, deviceScaleFactor: 1, mobile: false });

let shot = 0;
async function snap(label) {
  shot++;
  const { data } = await cdp.send('Page.captureScreenshot', { format: 'png' });
  const file = join(outDir, `${String(shot).padStart(2, '0')}-${label}.png`);
  writeFileSync(file, Buffer.from(data, 'base64'));
  console.log(`screenshot ${file}`);
}

async function evaluate(expression) {
  const { result } = await cdp.send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
  return result.value;
}

async function stageBox() {
  return evaluate(`(() => {
    const player = document.querySelector('ruffle-player, ruffle-object, ruffle-embed');
    if (!player) return null;
    const r = player.getBoundingClientRect();
    const scale = Math.min(r.width / 550, r.height / 400);
    return { left: r.left + (r.width - 550 * scale) / 2, top: r.top + (r.height - 400 * scale) / 2, scale };
  })()`);
}

async function click(stageX, stageY) {
  const box = await stageBox();
  const x = box.left + stageX * box.scale;
  const y = box.top + stageY * box.scale;
  await cdp.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x, y });
  await cdp.send('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', clickCount: 1 });
  await cdp.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x, y, button: 'left', clickCount: 1 });
  await sleep(400);
}

async function type(text) {
  for (const ch of text) {
    const code = /[a-z]/i.test(ch) ? `Key${ch.toUpperCase()}` : /\d/.test(ch) ? `Digit${ch}` : '';
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: ch, code, text: ch, unmodifiedText: ch });
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: ch, code });
    await sleep(60);
  }
}

async function waitForRequest(fragment, timeout = 8000) {
  const start = Date.now();
  while (Date.now() - start < timeout) {
    if (requests.some((url) => url.includes(fragment))) return true;
    await sleep(200);
  }
  return false;
}

let failed = false;
try {
  await cdp.send('Page.navigate', { url: `${base}/` });
  for (let tries = 0; tries < 50 && !(await stageBox()); tries++) await sleep(200);
  if (!(await waitForRequest('checklogon', 15000))) throw new Error('The Flash client never asked for the intro.');
  await sleep(2500);
  await snap('intro');

  await click(296, 277);
  await type(logon);
  await click(296, 303);
  await type(password);
  await sleep(300);
  await snap('typed');

  await click(215, 326);
  if (!(await waitForRequest('?logon&'))) throw new Error('LOGON did not send a request.');
  await sleep(3000);
  await snap('after-logon');
  const sent = requests.find((url) => url.includes('?logon&')) ?? '';
  console.log(`logon request: ${sent}`);
  if (!sent.includes(`st_logon=${logon}`)) {
    failed = true;
    console.log('FAIL the typed logon did not reach the server');
  } else {
    console.log('ok typed logon reached the server');
  }
  for (const step of steps) {
    const before = requests.length;
    await click(step.x, step.y);
    await sleep(2500);
    await snap(step.label);
    console.log(`${step.label}: ${requests.slice(before).join(' | ') || '(no request)'}`);
  }
} catch (error) {
  failed = true;
  console.log(`FAIL ${error.message}`);
  await snap('error').catch(() => {});
} finally {
  console.log('requests:\n  ' + requests.join('\n  '));
  cdp.close();
  chrome.kill();
  await sleep(500);
  try { rmSync(profile, { recursive: true, force: true }); } catch {}
}
process.exit(failed ? 1 : 0);
