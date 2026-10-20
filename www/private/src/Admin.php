<?php
declare(strict_types=1);

const FQ_ADMIN_HOURS = 8;

const FQ_ADMIN_NUMBERS = [
    'exp' => 'Total Bury Points', 'buryPoints' => "Today's Bury Points", 'money' => 'Money', 'turns' => 'Turns left',
    'wood' => 'Wood tags', 'silver' => 'Silver tags', 'gold' => 'Gold tags', 'plots' => 'Plots',
    'psych' => 'Psych upgrades', 'luck' => 'Luck (1-100)', 'yangsSent' => "Yang's sent today",
    'dayLastLoggedOn' => 'Last day logged on', 'hp' => 'Hitpoints', 'hpMax' => 'Max hitpoints', 'strength' => 'Strength',
    'visits' => 'Parlor visits today', 'tagsReceived' => 'Tags received today', 'transport' => 'Hearse',
    'lockpicks' => 'Lockpicks on hand', 'padlockStrength' => 'Padlock health (100 is max)',
];

const FQ_ADMIN_SWITCHES = [
    'active' => 'Active (unchecked = deleted)', 'always' => 'Always allowed on (skips the IP check)', 'male' => 'Male',
    'police' => 'Police protected', 'smoke' => 'Fire alarm installed', 'safe' => 'Safe installed', 'padlock' => 'Heavy padlock installed',
];

function fq_admin_e(mixed $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fq_admin_plain(string $text): string
{
    return preg_replace('/`./s', '', $text) ?? $text;
}

function fq_admin_load(string $dir): array
{
    $file = $dir . '/admin.json';
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    if (!is_array($data)) {
        $data = [];
    }
    $now = time();
    $data['sessions'] = array_filter($data['sessions'] ?? [], fn ($row) => is_array($row) && ($row['expires'] ?? 0) > $now);
    $data['attempts'] = array_filter($data['attempts'] ?? [], fn ($row) => is_array($row) && ($row['reset'] ?? 0) > $now);
    return $data;
}

function fq_admin_save(string $dir, array $data): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($dir . '/admin.json', json_encode($data), LOCK_EX);
}

function fq_admin_shell(string $body, string $nav = ''): string
{
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex">'
        . '<title>Funeral Quest Web Server</title><style>'
        . 'body{background:#000;color:#ccc;font:13px Verdana,Arial,sans-serif;margin:0 auto;max-width:1100px;padding:16px}'
        . 'h1{color:#fff;font-size:20px}h2{color:#ff0;font-size:15px;margin-top:24px}a{color:#0cf}'
        . 'table{border-collapse:collapse;width:100%}td,th{border:1px solid #333;padding:4px 6px;text-align:left;vertical-align:top}'
        . 'th{background:#222;color:#fff}input,select{background:#111;color:#fff;border:1px solid #555;padding:3px}'
        . 'button{background:#333;color:#fff;border:1px solid #777;padding:3px 10px;cursor:pointer}'
        . '.msg{background:#132;border:1px solid #2a5;padding:6px;margin:10px 0}.err{background:#311;border-color:#a33}'
        . 'nav a{margin-right:14px}form.inline{display:inline}pre{white-space:pre-wrap;background:#111;padding:8px;border:1px solid #333}'
        . '.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px 18px}.grid label{display:block}</style></head><body>'
        . '<h1>Funeral Quest Web Server</h1>' . $nav . $body
        . '<p style="margin-top:30px;color:#666">This product includes software written by Seth A. Robinson ( www.rtsoft.com ).</p></body></html>';
}

function fq_admin_form(string $csrf, string $action, string $fields, string $label, string $class = 'inline'): string
{
    return '<form class="' . $class . '" method="post" action="admin.php"><input type="hidden" name="csrf" value="' . fq_admin_e($csrf) . '">'
        . '<input type="hidden" name="action" value="' . fq_admin_e($action) . '">' . $fields . '<button type="submit">' . fq_admin_e($label) . '</button></form>';
}

function fq_admin_hidden(string $name, mixed $value): string
{
    return '<input type="hidden" name="' . fq_admin_e($name) . '" value="' . fq_admin_e($value) . '">';
}

/**
 * @param array{method:string,get:array,post:array,ip:string,cookie:?string,password:string,dir:string,game:callable,secure?:bool} $req
 * @return array{html:string,cookie:?string,redirect:?string}
 */
function fq_admin_handle(array $req): array
{
    $password = (string) $req['password'];
    if (strlen($password) < 10) {
        return ['html' => fq_admin_shell('<p>The operator page is switched off. Set <b>admin_password</b> (at least 10 characters) in private/settings.php to turn it on.</p>'), 'cookie' => null, 'redirect' => null];
    }
    $dir = (string) $req['dir'];
    $data = fq_admin_load($dir);
    $passwordTag = hash('sha256', $password);
    $cookie = (string) ($req['cookie'] ?? '');
    $key = preg_match('/^[a-f0-9]{64}$/', $cookie) ? hash('sha256', $cookie) : '';
    $row = $key !== '' ? ($data['sessions'][$key] ?? null) : null;
    $authed = is_array($row) && hash_equals((string) ($row['pw'] ?? ''), $passwordTag);
    $post = $req['post'];
    $action = (string) ($post['action'] ?? '');

    if (!$authed) {
        $error = '';
        if ($req['method'] === 'POST' && $action === 'login') {
            $ipKey = hash('sha256', (string) $req['ip']);
            $tries = $data['attempts'][$ipKey] ?? ['count' => 0, 'reset' => time() + 600];
            if ($tries['count'] >= 5) {
                $error = 'Too many wrong passwords. Wait a few minutes.';
            } elseif (hash_equals($password, (string) ($post['password'] ?? ''))) {
                unset($data['attempts'][$ipKey]);
                $token = bin2hex(random_bytes(32));
                $data['sessions'][hash('sha256', $token)] = [
                    'pw' => $passwordTag, 'csrf' => bin2hex(random_bytes(16)), 'expires' => time() + FQ_ADMIN_HOURS * 3600, 'flash' => '',
                ];
                fq_admin_save($dir, $data);
                return ['html' => '', 'cookie' => $token, 'redirect' => 'admin.php'];
            } else {
                $tries['count']++;
                $data['attempts'][$ipKey] = $tries;
                $error = 'Wrong password.';
            }
            fq_admin_save($dir, $data);
        }
        $form = '<form method="post" action="admin.php"><input type="hidden" name="action" value="login">'
            . '<p>Operator password: <input type="password" name="password" autocomplete="current-password" autofocus> <button type="submit">Log on</button></p></form>';
        return ['html' => fq_admin_shell(($error !== '' ? '<div class="msg err">' . fq_admin_e($error) . '</div>' : '') . $form), 'cookie' => null, 'redirect' => null];
    }

    $csrf = (string) $row['csrf'];
    if ($req['method'] === 'POST') {
        if (!hash_equals($csrf, (string) ($post['csrf'] ?? ''))) {
            $data['sessions'][$key]['flash'] = '!That form expired. Try again.';
            fq_admin_save($dir, $data);
            return ['html' => '', 'cookie' => null, 'redirect' => 'admin.php'];
        }
        if ($action === 'logout') {
            unset($data['sessions'][$key]);
            fq_admin_save($dir, $data);
            return ['html' => '', 'cookie' => '', 'redirect' => 'admin.php'];
        }
        $game = ($req['game'])();
        try {
            [$flash, $back] = fq_admin_act($game, $action, $post, $dir);
        } finally {
            $game->store->release();
        }
        $data = fq_admin_load($dir);
        if (isset($data['sessions'][$key])) {
            $data['sessions'][$key]['flash'] = $flash;
            fq_admin_save($dir, $data);
        }
        return ['html' => '', 'cookie' => null, 'redirect' => 'admin.php' . $back];
    }

    $flash = (string) ($row['flash'] ?? '');
    if ($flash !== '') {
        $data['sessions'][$key]['flash'] = '';
        fq_admin_save($dir, $data);
    }
    $game = ($req['game'])();
    try {
        $body = fq_admin_view($game, (string) ($req['get']['view'] ?? 'town'), $req['get'], $csrf);
    } finally {
        $game->store->release();
    }
    $note = '';
    if ($flash !== '') {
        $bad = $flash[0] === '!';
        $note = '<div class="msg' . ($bad ? ' err' : '') . '">' . fq_admin_e($bad ? substr($flash, 1) : $flash) . '</div>';
    }
    $nav = '<nav><a href="admin.php">Town</a><a href="admin.php?view=online">Online now</a><a href="admin.php?view=players">Players</a>'
        . '<a href="admin.php?view=news">Newspaper</a><a href="admin.php?view=bans">Ban list</a><a href="admin.php?view=log">Log</a>'
        . fq_admin_form($csrf, 'logout', '', 'Log off') . '</nav>';
    return ['html' => fq_admin_shell($note . $body, $nav), 'cookie' => null, 'redirect' => null];
}

/** @return array{0:string,1:string} */
function fq_admin_act(FqGame $game, string $action, array $post, string $dir): array
{
    $state = $game->store->state;
    if ($action === 'newday') {
        $game->forceNewDay();
        return ['Maint ran. It is now day ' . (int) $state->day . '.', ''];
    }
    if ($action === 'reset') {
        if (trim((string) ($post['confirm'] ?? '')) !== 'RESET') {
            return ['!Type RESET in the box to erase the game.', ''];
        }
        $game->resetGame();
        return ['The game was reset. Every player was deleted and a new game started.', ''];
    }
    if ($action === 'kick') {
        $id = (string) ($post['session'] ?? '');
        $entry = $state->sessions->{$id} ?? null;
        $person = is_object($entry) ? $game->store->playerById($entry->playerId ?? null) : null;
        $game->kickSession($id);
        return [$person ? $person->name . ' was kicked.' : 'Nobody to kick.', '?view=online'];
    }
    if ($action === 'ban') {
        $ip = trim((string) ($post['ip'] ?? ''));
        if ($ip === '' || !preg_match('/^[0-9A-Fa-f.:]+$/', $ip)) {
            return ['!That is not an IP address.', '?view=bans'];
        }
        $game->system->log('Added command to ban.txt: ' . fq_add_ban($ip, (string) ($post['comment'] ?? '')));
        return ["Added $ip to the ban list.", '?view=bans'];
    }
    if ($action === 'save') {
        return fq_admin_save_player($game, $post);
    }
    return ['!Unknown action.', ''];
}

/** @return array{0:string,1:string} */
function fq_admin_save_player(FqGame $game, array $post): array
{
    $index = (int) ($post['index'] ?? -1);
    $person = $game->store->state->players[$index] ?? null;
    if (!is_object($person)) {
        return ['!No such player.', '?view=players'];
    }
    $back = '?view=edit&id=' . $index;
    $online = false;
    foreach ($game->store->state->sessions as $entry) {
        if ((string) ($entry->playerId ?? '') === (string) $person->id) {
            $online = true;
        }
    }
    $logon = $game->cleanName(trim((string) ($post['logon'] ?? '')));
    $name = $game->cleanName(trim((string) ($post['name'] ?? '')));
    $parlor = $game->cleanName(trim((string) ($post['funeralName'] ?? '')));
    foreach ([$logon, $name, $parlor] as $text) {
        if (strlen($text) < 3 || strlen($text) > 20) {
            return ['!Logon, name, and parlor must each be 3 to 20 characters.', $back];
        }
    }
    foreach ($game->store->state->players as $other) {
        if ($other !== $person && ($other->active ?? true) !== false && strcasecmp((string) $other->logon, $logon) === 0) {
            return ['!Another player already uses that logon.', $back];
        }
    }
    $active = !empty($post['active']);
    if (!$active && ($person->active ?? true) !== false && $online) {
        return ["!Sorry, cannot delete the guy, he's online.  Kick him first.", $back];
    }
    $person->logon = $logon;
    $person->name = $name;
    $person->funeralName = $parlor;
    $person->email = substr(trim((string) ($post['email'] ?? '')), 0, 100);
    $newPassword = (string) ($post['password'] ?? '');
    if ($newPassword !== '') {
        if (strlen($newPassword) < 3 || strlen($newPassword) > 20) {
            return ['!Passwords are 3 to 20 characters.', $back];
        }
        $person->passwordHash = fq_hash_password($newPassword);
    }
    foreach (array_keys(FQ_ADMIN_NUMBERS) as $field) {
        $value = max(0, (int) ($post[$field] ?? 0));
        if ($field === 'luck') {
            $value = min(100, max(1, $value));
        }
        if ($field === 'transport') {
            $value = min(count(fq_transport()) - 1, $value);
        }
        $person->{$field} = $value;
    }
    foreach (array_keys(FQ_ADMIN_SWITCHES) as $field) {
        $on = !empty($post[$field]);
        $person->{$field} = in_array($field, ['smoke', 'safe', 'padlock'], true) ? ($on ? 1 : 0) : $on;
    }
    $game->store->save();
    return ["Saved $name.", $back];
}

function fq_admin_view(FqGame $game, string $view, array $get, string $csrf): string
{
    $state = $game->store->state;
    $online = [];
    foreach ($state->sessions as $id => $entry) {
        if (($entry->playerId ?? null) !== null) {
            $online[(string) $id] = $entry;
        }
    }
    if ($view === 'online') {
        $rows = '';
        foreach ($online as $id => $entry) {
            $person = $game->store->playerById($entry->playerId);
            $idle = isset($entry->lastPacket) ? max(0, (int) ((microtime(true) * 1000 - (int) $entry->lastPacket) / 1000)) . 's' : '';
            $ip = (string) ($entry->ip ?? '');
            $rows .= '<tr><td>' . fq_admin_e($person->name ?? '?') . '</td><td>' . fq_admin_e($person->logon ?? '') . '</td><td>'
                . fq_admin_e($entry->location ?? '') . '</td><td>' . $idle . '</td><td>' . fq_admin_e($ip) . '</td><td>'
                . fq_admin_form($csrf, 'kick', fq_admin_hidden('session', $id), 'Kick')
                . ($ip !== '' ? ' ' . fq_admin_form($csrf, 'ban', fq_admin_hidden('ip', $ip) . fq_admin_hidden('comment', 'Name: ' . ($person->name ?? '') . ', Parlor: ' . ($person->funeralName ?? '')), 'Ban IP') : '')
                . '</td></tr>';
        }
        return '<h2>Online now (' . count($online) . ')</h2><table><tr><th>Name</th><th>Logon</th><th>Location</th><th>Idle</th><th>IP</th><th></th></tr>'
            . ($rows ?: '<tr><td colspan="6">Nobody is on.</td></tr>') . '</table>';
    }
    if ($view === 'players') {
        $rows = '';
        $onIds = array_map(fn ($entry) => (string) $entry->playerId, $online);
        foreach ($state->players as $index => $person) {
            $status = ($person->active ?? true) === false ? 'Deleted' : (in_array((string) $person->id, $onIds, true) ? 'On now' : 'Day ' . (int) ($person->dayLastLoggedOn ?? 0));
            $ip = (string) ($person->lastIp ?? '');
            $rows .= '<tr><td>' . $index . '</td><td>' . fq_admin_e($person->name) . '</td><td>' . fq_admin_e($person->logon) . '</td><td>'
                . fq_admin_e($person->funeralName) . '</td><td>' . fq_cash((int) ($person->exp ?? 0)) . '</td><td>' . $status . '</td><td>' . fq_admin_e($ip) . '</td><td>'
                . '<a href="admin.php?view=edit&amp;id=' . $index . '">Edit</a>'
                . ($ip !== '' ? ' ' . fq_admin_form($csrf, 'ban', fq_admin_hidden('ip', $ip) . fq_admin_hidden('comment', 'Name: ' . $person->name . ', Parlor: ' . $person->funeralName), 'Ban IP') : '')
                . '</td></tr>';
        }
        return '<h2>Players (' . count($state->players) . ')</h2><table><tr><th>#</th><th>Name</th><th>Logon</th><th>Parlor</th><th>Bury Points</th><th>Status</th><th>Last IP</th><th></th></tr>'
            . ($rows ?: '<tr><td colspan="8">No players yet.</td></tr>') . '</table>';
    }
    if ($view === 'edit') {
        $index = (int) ($get['id'] ?? -1);
        $person = $state->players[$index] ?? null;
        if (!is_object($person)) {
            return '<p>No such player.</p>';
        }
        $day = (int) ($state->day ?? 1);
        $last = (int) ($person->dayLastLoggedOn ?? 0);
        $fields = fq_admin_hidden('index', $index) . '<div class="grid">';
        foreach (['logon' => 'Logon', 'name' => 'Name', 'funeralName' => 'Parlor', 'email' => 'Email'] as $field => $label) {
            $fields .= '<label>' . $label . '<br><input name="' . $field . '" value="' . fq_admin_e($person->{$field} ?? '') . '"></label>';
        }
        $fields .= '<label>New password (blank keeps it)<br><input name="password" type="password" autocomplete="new-password"></label>';
        $defaults = ['luck' => 50, 'hp' => 10, 'hpMax' => 10, 'strength' => 3];
        foreach (FQ_ADMIN_NUMBERS as $field => $label) {
            $fields .= '<label>' . fq_admin_e($label) . '<br><input name="' . $field . '" type="number" min="0" value="' . (int) ($person->{$field} ?? ($defaults[$field] ?? 0)) . '"></label>';
        }
        $fields .= '</div><p>';
        foreach (FQ_ADMIN_SWITCHES as $field => $label) {
            $checked = $field === 'active' ? ($person->active ?? true) !== false : !empty($person->{$field} ?? ($field === 'male'));
            $fields .= '<label><input type="checkbox" name="' . $field . '" value="1"' . ($checked ? ' checked' : '') . '> ' . fq_admin_e($label) . '</label><br>';
        }
        $fields .= '</p>';
        $hearses = [];
        foreach (fq_transport() as $at => $ride) {
            $hearses[] = $at . ' = ' . $ride['name'];
        }
        return '<h2>Edit ' . fq_admin_e($person->name) . '</h2>'
            . '<p>Last logged on: day ' . $last . ' (' . ($last === $day ? 'today' : ($day - $last) . ' days ago') . '). Last IP: '
            . fq_admin_e($person->lastIp ?? '') . '. Previous IP: ' . fq_admin_e($person->oldIp ?? '') . '.</p>'
            . '<p style="color:#888">Hearse numbers: ' . fq_admin_e(implode(', ', $hearses)) . '</p>'
            . fq_admin_form($csrf, 'save', $fields, 'Save player', 'block')
            . '<p><a href="admin.php?view=players">Back to players</a></p>';
    }
    if ($view === 'news') {
        $show = function (string $title, mixed $items, string $empty): string {
            $text = is_array($items) ? implode('', array_map('strval', $items)) : '';
            return '<h2>' . $title . '</h2><pre>' . fq_admin_e(fq_admin_plain($text) !== '' ? fq_admin_plain($text) : $empty) . '</pre>';
        };
        return $show("Today's newspaper", $state->news ?? [], 'Nothing yet today.')
            . $show("Today's crime report", $state->crime ?? [], 'There has been no crime reported today.')
            . $show("Yesterday's newspaper", $state->yesterday ?? [], 'Nothing.');
    }
    if ($view === 'bans') {
        $rows = '';
        foreach (fq_bans() as $ban) {
            $rows .= '<tr><td>' . fq_admin_e($ban['ip']) . '</td><td>' . fq_admin_e($ban['comment']) . '</td></tr>';
        }
        return '<h2>Ban list</h2><p>Anyone whose IP address contains one of these is refused. The list is private/dist/ban.txt plus private/data/ban.txt (bans added here). Edit those files to remove a ban.</p>'
            . '<table><tr><th>IP</th><th>Comment</th></tr>' . ($rows ?: '<tr><td colspan="2">Nobody is banned.</td></tr>') . '</table>'
            . '<h2>Add a ban</h2>' . fq_admin_form($csrf, 'ban', 'IP <input name="ip"> Comment <input name="comment"> ', 'Ban');
    }
    $system = $game->system->read();
    if ($view === 'log') {
        $tail = function (string $text, int $count): string {
            $lines = array_values(array_filter(preg_split("/\r\n|\n|\r/", $text) ?: [], fn ($line) => $line !== ''));
            return implode("\n", array_slice($lines, -$count));
        };
        $stats = $tail($game->system->file('stats.txt'), 60);
        $errors = $tail($game->system->file('errorlog.txt'), 40);
        return '<h2>Log</h2><p>The last ' . FqSystem::LOG_LINES . ' lines, newest at the bottom. '
            . (!empty($game->rules['log_to_disk']) ? 'Every line is also saved to private/data/log.txt.' : "Set 'log_to_disk' => true in private/settings.local.php through cPanel File Manager to also keep every line in private/data/log.txt.") . '</p>'
            . '<pre>' . fq_admin_e(fq_admin_plain(implode("\n", $system['log'])) ?: 'Nothing logged yet.') . '</pre>'
            . '<h2>Stats</h2><p>One line per maint: the logons and hits for that day (private/data/stats.txt).</p>'
            . '<pre>' . fq_admin_e($stats !== '' ? $stats : 'No maint has run yet.') . '</pre>'
            . '<h2>Error log</h2><pre>' . fq_admin_e($errors !== '' ? $errors : 'No errors.') . '</pre>';
    }
    $tournament = $game->tournamentDays > 0
        ? 'Tournament Mode Active (day ' . (int) ($state->day ?? 1) . ' of ' . $game->tournamentDays . ')'
        : 'No Reset Mode Active (day ' . (int) ($state->day ?? 1) . ')';
    $active = count(array_filter($state->players, fn ($person) => ($person->active ?? true) !== false));
    return '<h2>Town</h2><p>' . fq_admin_e($tournament) . '. ' . $active . ' parlors, ' . count($online) . ' on now. Last maint: '
        . fq_admin_e($state->date ?? 'never') . '.</p>'
        . '<p>Logons today: ' . $system['logonsToday'] . ' (total ' . $system['logonsTotal'] . '). Hits today: ' . $system['hitsToday'] . ' (total ' . $system['hitsTotal'] . ').</p>'
        . '<p>To change server settings, edit private/settings.local.php through cPanel File Manager. Defaults and descriptions are in private/settings.php; this page does not have a server-settings editor. Game text (events, risks, sales pitches, the swear filter) lives in private/dist.</p>'
        . '<h2>New day</h2><p>Runs midnight maint right now, the same as the original server\'s New Day button.</p>'
        . fq_admin_form($csrf, 'newday', '', 'Run maint now')
        . '<h2>Reset game</h2><p>Deletes every player and starts a new game. This cannot be undone. Type RESET to confirm.</p>'
        . fq_admin_form($csrf, 'reset', '<input name="confirm" autocomplete="off"> ', 'Reset game');
}
