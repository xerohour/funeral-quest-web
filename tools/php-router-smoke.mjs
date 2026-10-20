// Run from the repository root: npm run test:router
// Uses only static paths on a temporary localhost server; never opens town data.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import http from 'node:http';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const php = process.env.FQ_PHP || 'php';
const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'fq-config-test-'));
try {
  fs.mkdirSync(path.join(fixture, 'private'));
  fs.cpSync(path.join(root, 'www/private/src'), path.join(fixture, 'private/src'), { recursive: true });
  fs.writeFileSync(path.join(fixture, 'private/settings.php'), "<?php return ['timezone' => 'UTC', 'max_players' => 800];\n");
  fs.writeFileSync(path.join(fixture, 'private/settings.local.php'), "<?php return ['timezone' => 'America/Chicago', 'admin_password' => 'fixture-only-password'];\n");
  fs.writeFileSync(path.join(fixture, 'check.php'), `<?php
require __DIR__ . '/private/src/boot.php';
$settings = fq_settings();
if ($settings['max_players'] !== 800 || $settings['timezone'] !== 'America/Chicago'
    || $settings['admin_password'] !== 'fixture-only-password' || date_default_timezone_get() !== 'America/Chicago') {
    fwrite(STDERR, 'Local settings were not merged correctly.'); exit(1);
}
echo 'ok local overrides preserve defaults and set timezone';
`);
  const config = spawnSync(php, [path.join(fixture, 'check.php')], { encoding: 'utf8', windowsHide: true });
  if (config.error) throw config.error;
  assert.equal(config.status, 0, config.stderr || config.stdout);
  console.log(config.stdout);
} finally {
  // Only remove the temporary directory created above, within the OS temp root.
  assert.equal(path.dirname(path.resolve(fixture)), path.resolve(os.tmpdir()));
  fs.rmSync(fixture, { recursive: true, force: true });
}

const probe = net.createServer();
await new Promise((resolve, reject) => { probe.once('error', reject); probe.listen(0, '127.0.0.1', resolve); });
const port = probe.address().port;
await new Promise(resolve => probe.close(resolve));
const child = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', path.join(root, 'www'), path.join(root, 'www/router.php')], {
  cwd: root, stdio: ['ignore', 'ignore', 'pipe'], windowsHide: true,
});
let log = '';
let launchError;
child.stderr.on('data', chunk => { log += chunk; });
child.on('error', error => { launchError = error; });
const ended = new Promise(resolve => { child.once('exit', resolve); child.once('error', resolve); });
function request(requestPath) {
  return new Promise((resolve, reject) => {
    const call = http.get({ host: '127.0.0.1', port, path: requestPath }, response => {
      let body = '';
      response.on('data', chunk => { body += chunk; });
      response.on('end', () => resolve({ status: response.statusCode, body }));
    });
    call.once('error', reject);
    call.setTimeout(3000, () => call.destroy(new Error('Request timed out.')));
  });
}
try {
  let ready = false;
  for (let tries = 0; tries < 50; tries++) {
    if (launchError) throw launchError;
    if (child.exitCode !== null) throw new Error(log || 'PHP server exited.');
    try { if ((await request('/')).status === 200) { ready = true; break; } } catch {}
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  assert.ok(ready, `PHP server did not start. ${log}`);
  for (const [requestPath, expected] of [
    ['/', 200], ['/client/play.js', 200],
    ['/private/', 403], ['/private/settings.php', 403], ['/PRIVATE/src/Store.php', 403],
    ['/private/request.xp?checklogon', 403],
    ['/ruffle/%2e%2e/%2e%2e/%2e%2e/www/private/src/Store.php', 400],
    ['/ruffle/../../../www/private/src/Store.php', 400],
    ['/ruffle/%2e%2e%5c%2e%2e%5c%2e%2e%5cwww%5cprivate%5csrc%5cStore.php', 400],
    ['/private%00/settings.php', 400],
  ]) {
    const result = await request(requestPath);
    assert.equal(result.status, expected, `${requestPath}: ${result.body.slice(0, 100)}`);
    assert.ok(!result.body.includes('final class FqStore'), 'Private source was disclosed.');
    console.log(`ok ${expected} ${requestPath}`);
  }
} finally {
  if (child.exitCode === null && !launchError) child.kill();
  await ended;
}
console.log('PHP router and configuration smoke tests passed');
