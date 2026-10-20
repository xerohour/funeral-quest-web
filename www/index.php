<?php
declare(strict_types=1);

ini_set('display_errors', '0');
require __DIR__ . '/private/src/boot.php';

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$ban = fq_ban_for(fq_client_ip());
if ($ban !== null) {
    FqSystem::at(dirname(fq_roots()['data']))->log('Ignoring IP ' . fq_client_ip() . ', on ban list. (' . $ban['comment'] . ')');
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unauthorized';
    return;
}
if (preg_match('#/pages/(server|rankings)\.htm$#', (string) parse_url($uri, PHP_URL_PATH), $page)) {
    $html = fq_game()->webPage($page[1]);
    if ($html === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
        return;
    }
    header('Content-Type: text/html; charset=ISO-8859-1');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $html;
    return;
}
if (!str_contains($uri, 'request.xp?')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    return;
}

$game = fq_game();
$game->clientIp = fq_client_ip();
$request = fq_parse_request($uri);
$sessionId = fq_session_id();
if ($request && $request['command'] === 'logon') {
    $logon = (string) ($request['tokens']['st_logon'] ?? '');
    $ip = fq_client_ip();
    if (fq_rate_limited($ip, $logon)) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo fq_vars_to_body([
            ['g_st_error', 'Too many logon attempts. Wait a few minutes.'],
            ['i_sent', -1],
        ]);
        return;
    }
    $body = $game->handle($uri, $sessionId);
    $player = $game->store->peek($sessionId);
    if ($player) {
        fq_clear_failures($ip, $logon);
    } else {
        fq_record_failure($ip, $logon);
    }
} else {
    $body = $game->handle($uri, $sessionId);
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
echo $body ?? '';
