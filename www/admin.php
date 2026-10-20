<?php
declare(strict_types=1);

ini_set('display_errors', '0');
require __DIR__ . '/private/src/boot.php';
require __DIR__ . '/private/src/Admin.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

$cookie = $_COOKIE['fqadmin'] ?? null;
$result = fq_admin_handle([
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'get' => $_GET,
    'post' => $_POST,
    'ip' => fq_client_ip(),
    'cookie' => is_string($cookie) ? $cookie : null,
    'password' => (string) (fq_settings()['admin_password'] ?? ''),
    'dir' => dirname(fq_roots()['data']),
    'game' => fn () => fq_game(),
]);

if ($result['cookie'] !== null) {
    setcookie('fqadmin', $result['cookie'], [
        'expires' => $result['cookie'] === '' ? time() - 3600 : time() + FQ_ADMIN_HOURS * 3600,
        'path' => '/',
        'secure' => fq_secure_request(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
if ($result['redirect'] !== null) {
    header('Location: ' . $result['redirect'], true, 303);
    return;
}
header('Content-Type: text/html; charset=utf-8');
echo $result['html'];
