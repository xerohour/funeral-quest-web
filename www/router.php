<?php
declare(strict_types=1);

// Local PHP development server only. Apache/cPanel uses .htaccess.
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = rawurldecode(explode('?', $requestUri, 2)[0]);
$uri = str_replace('\\', '/', $uri);
$segments = explode('/', $uri);
if (str_contains($uri, "\0") || in_array('..', $segments, true) || in_array('.', $segments, true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid path';
    return true;
}
$uri = '/' . ltrim($uri, '/');
$lower = strtolower($uri);
if ($lower === '/private' || str_starts_with($lower, '/private/')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    return true;
}
if (preg_match('#(?:^|/)request\.xp$#', $uri) || preg_match('#^/pages/(server|rankings)\.htm$#', $uri)) {
    require __DIR__ . '/index.php';
    return true;
}
if ($uri === '/' || $uri === '/index.html' || $uri === '/classic' || $uri === '/home.html') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    return true;
}
$local = __DIR__ . $uri;
if (is_file($local)) {
    return false;
}
$roots = [
    dirname(__DIR__) . '/upstream/dist/public',
    dirname(__DIR__) . '/node_modules/@ruffle-rs/ruffle',
];
foreach ($roots as $root) {
    $root = realpath($root);
    if ($root === false) {
        continue;
    }
    $relative = str_starts_with($uri, '/ruffle/') ? substr($uri, strlen('/ruffle/')) : ltrim($uri, '/');
    $file = realpath($root . '/' . $relative);
    if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
        continue;
    }
    $types = [
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'wasm' => 'application/wasm',
        'swf' => 'application/x-shockwave-flash',
        'gif' => 'image/gif',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    readfile($file);
    return true;
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
return true;
