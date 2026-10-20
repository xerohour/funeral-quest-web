<?php
declare(strict_types=1);

require __DIR__ . '/Ansi.php';
require __DIR__ . '/Passwords.php';
require __DIR__ . '/Catalog.php';
require __DIR__ . '/Store.php';
require __DIR__ . '/System.php';
require __DIR__ . '/Script.php';
require __DIR__ . '/Game.php';

function fq_www(): string
{
    return dirname(__DIR__, 2);
}

function fq_roots(): array
{
    $www = fq_www();
    $repo = dirname($www);
    $packed = is_file($www . '/flash/welcome.swf');
    return [
        'public' => $packed ? $www : $repo . '/upstream/dist/public',
        'dist' => $packed ? $www . '/private/dist' : $repo . '/upstream/dist',
        'data' => $www . '/private/data/players.json',
    ];
}

function fq_settings(): array
{
    static $settings = null;
    if ($settings === null) {
        $file = dirname(__DIR__) . '/settings.php';
        $loaded = is_file($file) ? require $file : [];
        $settings = is_array($loaded) ? $loaded : [];
        $localFile = dirname(__DIR__) . '/settings.local.php';
        $local = is_file($localFile) ? require $localFile : [];
        if (is_array($local)) {
            $settings = array_replace($settings, $local);
        }
        $zone = (string) ($settings['timezone'] ?? '');
        if ($zone !== '' && in_array($zone, timezone_identifiers_list(), true)) {
            date_default_timezone_set($zone);
        }
    }
    return $settings;
}

function fq_game(?string $dataFile = null): FqGame
{
    $settings = fq_settings();
    $roots = fq_roots();
    $store = new FqStore($dataFile ?? $roots['data']);
    $game = new FqGame($store, $roots['public'], $roots['dist']);
    $game->maxPlayers = max(1, (int) ($settings['max_players'] ?? 800));
    $game->maxOnline = max(1, (int) ($settings['max_online'] ?? 500));
    $game->tournamentDays = max(0, (int) ($settings['tournament_days'] ?? 0));
    $game->serverNews = (string) ($settings['server_news'] ?? '');
    $email = trim((string) ($settings['admin_email'] ?? ''));
    $game->adminEmail = $email === '' ? '(none)' : $email;
    foreach (FqGame::DEFAULT_RULES as $key => $default) {
        $value = $settings[$key] ?? null;
        if (is_bool($value)) {
            $value = (int) $value;
        }
        if (!is_numeric($value)) {
            continue;
        }
        $game->rules[$key] = match (true) {
            is_float($default) => min(1.0, max(0.0, (float) $value)),
            $key === 'starting_luck' => min(100, max(1, (int) $value)),
            $key === 'idle_minutes' => max(1, (int) $value),
            default => max(0, (int) $value),
        };
    }
    $game->system->logToDisk = (bool) $game->rules['log_to_disk'];
    return $game;
}

function fq_secure_request(): bool
{
    $https = $_SERVER['HTTPS'] ?? '';
    if ($https !== '' && $https !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function fq_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function fq_ban_files(): array
{
    return [fq_roots()['dist'] . '/ban.txt', dirname(fq_roots()['data']) . '/ban.txt'];
}

function fq_bans(): array
{
    $bans = [];
    foreach (fq_ban_files() as $file) {
        if (!is_file($file)) {
            continue;
        }
        foreach (preg_split("/\r\n|\n|\r/", (string) file_get_contents($file)) ?: [] as $line) {
            $parts = explode('|', $line);
            if (strtolower(trim($parts[0])) === 'ban' && trim($parts[1] ?? '') !== '') {
                $bans[] = ['ip' => trim($parts[1]), 'comment' => trim($parts[2] ?? '')];
            }
        }
    }
    return $bans;
}

/** @return array{ip:string,comment:string}|null */
function fq_ban_for(string $ip): ?array
{
    if ($ip === '') {
        return null;
    }
    foreach (fq_bans() as $ban) {
        if (stripos($ip, $ban['ip']) !== false) {
            return $ban;
        }
    }
    return null;
}

function fq_is_banned(string $ip): bool
{
    return fq_ban_for($ip) !== null;
}

function fq_add_ban(string $ip, string $comment): string
{
    $ip = preg_replace('/[^0-9A-Fa-f.:]/', '', $ip) ?? '';
    if ($ip === '') {
        return '';
    }
    $file = fq_ban_files()[1];
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0775, true);
    }
    $comment = str_replace(["\r", "\n", '|'], ' ', $comment);
    $line = 'ban|' . $ip . '|Banned ' . date('m-d') . ':' . date('g:i A') . '. ' . $comment . "\r\n";
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    return $line;
}

function fq_attempts_file(): string
{
    return dirname(fq_roots()['data']) . '/attempts.json';
}

function fq_rate_limited(string $ip, string $logon): bool
{
    $file = fq_attempts_file();
    $rows = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    if (!is_array($rows)) {
        $rows = [];
    }
    $key = $ip . '|' . strtolower($logon);
    $row = $rows[$key] ?? null;
    return is_array($row) && ($row['count'] ?? 0) >= 8 && ($row['reset'] ?? 0) > (int) (microtime(true) * 1000);
}

function fq_record_failure(string $ip, string $logon): void
{
    $file = fq_attempts_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $rows = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    if (!is_array($rows)) {
        $rows = [];
    }
    $now = (int) (microtime(true) * 1000);
    $key = $ip . '|' . strtolower($logon);
    $row = $rows[$key] ?? null;
    if (!is_array($row) || ($row['reset'] ?? 0) <= $now) {
        $rows[$key] = ['count' => 1, 'reset' => $now + 10 * 60 * 1000];
    } else {
        $rows[$key]['count'] = ($row['count'] ?? 0) + 1;
    }
    file_put_contents($file, json_encode($rows));
}

function fq_clear_failures(string $ip, string $logon): void
{
    $file = fq_attempts_file();
    if (!is_file($file)) {
        return;
    }
    $rows = json_decode((string) file_get_contents($file), true);
    if (!is_array($rows)) {
        return;
    }
    unset($rows[$ip . '|' . strtolower($logon)]);
    file_put_contents($file, json_encode($rows));
}

function fq_session_id(): string
{
    $cookie = $_COOKIE['fq'] ?? '';
    if (is_string($cookie) && preg_match('/^[a-f0-9]{64}$/', $cookie)) {
        return $cookie;
    }
    $id = bin2hex(random_bytes(32));
    setcookie('fq', $id, [
        'expires' => time() + 1209600,
        'path' => '/',
        'secure' => fq_secure_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    return $id;
}
