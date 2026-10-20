<?php
declare(strict_types=1);

require __DIR__ . '/GameRules.php';
require __DIR__ . '/GamePlay.php';
require __DIR__ . '/GameTown.php';

final class FqGame
{
    use FqRules, FqPlay, FqTown;

    public FqStore $store;
    public int $maxPlayers = 800;
    public int $maxOnline = 500;
    public int $tournamentDays = 0;
    public string $serverNews = '';
    public string $adminEmail = '(none)';
    public array $rules = self::DEFAULT_RULES;
    public string $clientIp = '';
    public FqSystem $system;
    private string $publicRoot;
    private string $distRoot;
    private float $bootedAt;
    private array $ratings = [];
    private array $softOptions = [];
    private array $upgrades = [];
    private array $storyEvents = [];
    private array $newsBlurbs = [];
    private array $risks = [];
    private array $cursedFortunes = [];
    private array $kindFortunes = [];
    private array $swearWords = [];
    private array $scriptGlobals = [
        'true' => ['type' => 'int', 'value' => 1],
        'false' => ['type' => 'int', 'value' => 0],
    ];
    private array $runs = [];
    private bool $globalsReady = false;
    private ?object $active = null;

    private const SENT = [
        'fail' => -1, 'intro' => 1, 'unknownUser' => 2, 'newDetails' => 3, 'newName' => 4,
        'news' => 5, 'quit' => 1, 'loadMovie' => 2, 'object' => 10, 'add' => 14,
        'callback' => 16, 'ranking' => 9, 'generic' => 33,
    ];

    public const DEFAULT_RULES = [
        'turns_per_day' => 15, 'yangs_per_day' => 1, 'visits_per_day' => 2, 'risk' => 50,
        'bonus_bp' => 20, 'bonus_luck' => 25, 'trade_bonus' => 25, 'tags_per_day' => 1, 'name_change_cost' => 1,
        'starting_money' => 500, 'starting_luck' => 50, 'starting_hp' => 10, 'starting_strength' => 3,
        'normal_odds' => 0.08, 'luck_odds' => 0.08, 'script_odds' => 0.05,
        'ip_check' => 1, 'inactive_days' => 15, 'delete_unsold' => 1, 'swear_filter' => 1, 'idle_minutes' => 4, 'log_to_disk' => 0,
    ];
    private const TAG_VALUE = ['wood' => 1, 'silver' => 2, 'gold' => 4];
    private const PROJECT_URL = 'https://github.com/auzziejay/funeral-quest-web';
    private const FOG = 'A dense fog covers the row upon row of funeral homes. You see disturbed, emotionally unstable people trying to choose which parlor to patronize.  You fight the urge to hand out business cards, you have more important things to attend to.';
    private const YANGS_MENU = "`wThe Menu\n\n`w#1`\$ - Lucky Dragon (adds `w10 `\$luck, for here only) Cost: `w5`\$ Bury Points\n`w#2`\$ - Lucky Sparrow (adds `w5 `\$luck, for delivery only) Cost: `w20`\$ Bury Points\n`w#3`\$ - Cursed Tiger (removes `w10`\$ luck, for delivery only) Cost: `w20`\$ Bury Points\n";
    private const STORE_ROOM = 'flash/places/store.swf';

    private const CASKETS = [
        ['id' => 'wood', 'label' => 'wood', 'price' => 800, 'points' => 1],
        ['id' => 'silver', 'label' => 'silver', 'price' => 2600, 'points' => 2],
        ['id' => 'gold', 'label' => 'gold', 'price' => 5300, 'points' => 3],
    ];

    public function __construct(FqStore $store, string $publicRoot, string $distRoot)
    {
        $this->store = $store;
        $this->system = FqSystem::at($store->dir());
        $this->publicRoot = $publicRoot;
        $this->distRoot = $distRoot;
        if (!isset($store->state->bootedAt)) {
            $store->state->bootedAt = time();
            $store->save();
        }
        $this->bootedAt = (float) $store->state->bootedAt;
        $this->ratings = $this->loadRatings();
        $this->softOptions = $this->loadSellItems('soft_sell.txt');
        $this->upgrades = $this->loadSellItems('hard_sell.txt');
        $this->storyEvents = $this->loadStoryEvents('events.txt');
        $this->newsBlurbs = $this->loadStoryEvents('news_blurbs.txt', 'flash/stuff/money_bag_2.swf');
        $this->risks = $this->loadRisks();
        $this->cursedFortunes = $this->loadFortunes('yangs.txt');
        $this->kindFortunes = $this->loadFortunes('yangs_nice.txt');
        $this->swearWords = $this->loadSwearWords();
    }

    private function val(object $obj, string $key, mixed $default = null): mixed
    {
        return property_exists($obj, $key) && $obj->{$key} !== null ? $obj->{$key} : $default;
    }

    private function num(object $obj, string $key, int|float $default = 0): int|float
    {
        $value = $this->val($obj, $key, $default);
        return is_numeric($value) ? $value + 0 : $default;
    }

    private function rule(string $key): int
    {
        return (int) ($this->rules[$key] ?? self::DEFAULT_RULES[$key]);
    }

    private function odds(string $key): float
    {
        return (float) ($this->rules[$key] ?? self::DEFAULT_RULES[$key]);
    }

    private function recordIp(object $player): void
    {
        if ($this->clientIp === '') {
            return;
        }
        $player->oldIp = (string) ($player->lastIp ?? '');
        $player->lastIp = $this->clientIp;
    }

    private function sameIpToday(?object $except): ?object
    {
        if (!$this->rule('ip_check') || $this->clientIp === '') {
            return null;
        }
        $day = (int) ($this->store->state->day ?? 1);
        foreach ($this->store->state->players as $person) {
            if (($person->active ?? true) === false || !empty($person->always) || ($except && (string) $person->id === (string) $except->id)) {
                continue;
            }
            if ((int) $this->num($person, 'dayLastLoggedOn') === $day && (string) ($person->lastIp ?? '') === $this->clientIp) {
                return $person;
            }
        }
        return null;
    }

    private function pick(array $list): mixed
    {
        if (!$list) {
            return null;
        }
        return $list[random_int(0, count($list) - 1)];
    }

    private function between(int $min, int $max): int
    {
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }
        return random_int($min, $max);
    }

    private function scene(array $fields): string
    {
        $error = null;
        $failed = false;
        foreach ($fields as $field) {
            if ($field[0] === 'g_st_error') {
                $error = (string) $field[1];
            }
            if ($field[0] === 'i_sent' && (string) $field[1] === (string) self::SENT['fail']) {
                $failed = true;
            }
        }
        if ($error !== null && $failed) {
            $this->logMsg('Sending error message info to user : ' . $error);
        }
        return fq_vars_to_body($fields);
    }

    private function logMsg(string $text): void
    {
        $this->system->log($text);
    }

    private function art(string $relative): string
    {
        $file = $this->publicRoot . '/' . str_replace('\\', '/', $relative);
        return is_file($file) ? str_replace('\\', '/', $relative) : 'flash/places/desk.swf';
    }

    private function stats(object $player): array
    {
        return [
            ['i_money', fq_cash((int) $this->num($player, 'money'))],
            ['i_exp', fq_cash((int) $this->num($player, 'exp'))],
            ['i_turns', fq_cash((int) $this->num($player, 'turns'))],
            ['i_sp', fq_cash((int) $this->num($player, 'buryPoints'))],
            ['i_luck', (int) $this->num($player, 'luck', 50)],
            ['i_coffin_wood', fq_cash((int) $this->num($player, 'wood'))],
            ['i_coffin_silver', fq_cash((int) $this->num($player, 'silver'))],
            ['i_coffin_gold', fq_cash((int) $this->num($player, 'gold'))],
        ];
    }

    private function continueButton(int $command = 4): array
    {
        return [['st_option_3', 'Continue'], ['i_command_3', $command], ['b_kill_buttons', 1]];
    }

    private function buttons(array $buttons): array
    {
        $fields = [];
        foreach ($buttons as $button) {
            $label = $button['label'];
            if ($label !== '' && $label[0] !== ' ' && strlen($label) < 10) {
                $label = $this->centerButton($label);
            }
            $fields[] = ['st_option_' . $button['key'], $label];
            $fields[] = ['i_command_' . $button['key'], $button['command']];
            if (array_key_exists('sub', $button) && $button['sub'] !== null) {
                $fields[] = ['i_command_sub_' . $button['key'], $button['sub']];
            }
        }
        return $fields;
    }

    public function runScript(string $sessionId, string $relative): ?string
    {
        $session = $this->store->session($sessionId);
        $this->active = $session;
        $this->hydrate($session);
        $player = $this->store->player($sessionId);
        if (!$player) {
            return null;
        }
        return $this->startFqScript($session, $player, $relative);
    }

    public function maintain(): bool
    {
        $this->sweepIdle();
        $rolled = $this->rollDay();
        if ($rolled) {
            $this->store->save();
        }
        return $rolled;
    }

    public function forceNewDay(): void
    {
        $this->store->state->date = '1970-01-01';
        $this->rollDay();
        $this->store->save();
    }

    public function resetGame(): void
    {
        $this->resetTown();
        $this->store->state->date = '1970-01-01';
        $this->rollDay();
        $this->store->save();
    }

    public function kickSession(string $id): void
    {
        $entry = $this->store->state->sessions->{$id} ?? null;
        if (!is_object($entry)) {
            return;
        }
        $entry->id = $id;
        $this->settleAbandonedSale($entry);
        unset($entry->vm, $this->runs[$id]);
        $this->store->logoff($id);
        $this->store->save();
    }

    public function cleanName(string $text): string
    {
        return $this->cleanPlayerName($text);
    }

    private function sweepIdle(): void
    {
        $now = (int) (microtime(true) * 1000);
        $limit = $this->rule('idle_minutes') * 60 * 1000;
        $current = $this->active->id ?? null;
        $pruned = false;
        foreach (get_object_vars($this->store->state->sessions) as $id => $entry) {
            if ($entry->playerId === null) {
                if ((string) $id !== (string) $current) {
                    unset($this->store->state->sessions->{$id}, $this->runs[(string) $id]);
                    $pruned = true;
                }
                continue;
            }
            if (!isset($entry->lastPacket)) {
                $entry->lastPacket = $now;
                continue;
            }
            if ($now - (int) $entry->lastPacket < $limit) {
                continue;
            }
            $person = $this->store->playerById($entry->playerId);
            $this->settleAbandonedSale($entry);
            if ($person) {
                $this->townNotice($person->name . ' got kicked for inactivity', (string) $id);
            }
            unset($entry->vm, $this->runs[(string) $id]);
            $this->store->logoff((string) $id);
        }
        if ($pruned) {
            $this->store->save();
        }
    }

    public function handle(string $url, string $sessionId): ?string
    {
        $request = fq_parse_request($url);
        if (!$request) {
            return null;
        }
        $this->system->hit();
        $session = $this->store->session($sessionId);
        if ($this->clientIp !== '') {
            $session->ip = $this->clientIp;
        }
        $this->active = $session;
        $this->hydrate($session);
        $this->sweepIdle();
        if ($this->rollDay()) {
            $this->store->save();
        }
        if ($request['command'] === 'checklogon') {
            if ($session->playerId !== null) {
                $leaving = $this->store->playerById($session->playerId);
                if ($leaving) {
                    $this->townNotice($leaving->name . ' logged off.', $sessionId);
                }
                $this->settleAbandonedSale($session);
                $this->store->logoff($sessionId);
            }
            return $this->intro();
        }
        if ($request['command'] === 'new') {
            $holder = $this->sameIpToday(null);
            if ($holder) {
                $this->system->error("User with IP {$this->clientIp} not allowed to create a new user. (already on as {$holder->name} ({$holder->lastIp}) today)");
                return $this->scene([['g_st_error', 'Sorry, this game has activated a rule that only allows one player per IP address per day.'], ['i_sent', self::SENT['fail']]]);
            }
            $request['tokens']['name'] = $this->publicName((string) ($request['tokens']['name'] ?? ''));
            $request['tokens']['f_name'] = $this->publicName((string) ($request['tokens']['f_name'] ?? ''));
            $problem = $this->validateNew($request['tokens']);
            if ($problem) {
                return $this->scene([['g_st_error', $problem[1]], ['i_sent', $problem[2]]]);
            }
            if ($session->playerId !== null) {
                $this->settleAbandonedSale($session);
                $this->store->logoff($sessionId);
            }
            $created = $this->store->createPlayer([
                'name' => $request['tokens']['name'],
                'logon' => $request['tokens']['logon'],
                'funeralName' => $request['tokens']['f_name'],
                'password' => $request['tokens']['password'],
                'email' => $request['tokens']['email'],
                'male' => ($request['tokens']['b_male'] ?? '') === '1',
                'money' => $this->rule('starting_money'),
                'luck' => $this->rule('starting_luck'),
                'hp' => $this->rule('starting_hp'),
                'strength' => $this->rule('starting_strength'),
            ]);
            $this->logMsg('Creating player account ' . $this->playerIndex($created) . '.');
            $this->addNews('`5A new parlor (' . $created->funeralName . ') run by ' . $created->name . " has been opened!\n\n");
            $this->recordIp($created);
            $this->system->logon();
            $this->store->logon($sessionId, $created->id);
            $session = $this->store->session($sessionId);
            $this->active = $session;
            $this->townNotice($created->name . ' has just moved into town!');
            return $this->showFront($session, (int) ($this->store->state->day ?? 1));
        }
        if ($request['command'] === 'logon') {
            if ($session->playerId !== null) {
                $leaving = $this->store->playerById($session->playerId);
                if ($leaving) {
                    $this->townNotice($leaving->name . ' logged off.', $sessionId);
                }
                $this->settleAbandonedSale($session);
                $this->store->logoff($sessionId);
                $session = $this->store->session($sessionId);
                $this->active = $session;
            }
            $found = $this->store->findLogon((string) ($request['tokens']['st_logon'] ?? ''));
            if (!$this->store->checkPassword($found, (string) ($request['tokens']['st_password'] ?? ''))) {
                return $this->scene([['i_sent', self::SENT['unknownUser']]]);
            }
            $holder = empty($found->always) ? $this->sameIpToday($found) : null;
            if ($holder) {
                $this->system->error("User with IP {$this->clientIp} can't logon as {$found->name} because he already played as {$holder->name} ({$holder->lastIp}) today.");
                $this->townNotice($found->name . ' was kicked, someone with that IP already played today.');
                return $this->scene([['g_st_error', 'Sorry, this game has activated a rule that only allows one player per IP address per day.'], ['i_sent', self::SENT['fail']]]);
            }
            if ($this->onlineCount() >= $this->maxOnline) {
                return $this->scene([
                    ['g_st_error', "Sorry, there are already {$this->maxOnline} players logged on right now which is the max this server will allow.  Please try again in a few minutes."],
                    ['i_sent', self::SENT['fail']],
                ]);
            }
            if ($this->parlorIntruder($found)) {
                return $this->scene([
                    ['g_st_error', 'Someone is currently poking around your parlor...please log back on in a few minutes!'],
                    ['i_sent', self::SENT['fail']],
                ]);
            }
            $this->recordIp($found);
            $this->store->logon($sessionId, $found->id);
            $session = $this->store->session($sessionId);
            $this->active = $session;
            $this->logMsg('Connection from ' . $found->name . ' (' . ($this->clientIp !== '' ? $this->clientIp : '0.0.0.0') . ')');
            $this->townNotice($found->name . ' logged on.');
            $this->system->logon();
            return $this->showFront($session, (int) ($this->store->state->day ?? 1));
        }
        if ($request['command'] === 'command') {
            $player = $this->store->player($sessionId);
            if (!$player) {
                return $this->scene([
                    ['g_st_error', 'Connection with server died. (user not logged on)'],
                    ['i_sent', self::SENT['fail']],
                ]);
            }
            $this->refreshDay($session, $player);
            return $this->handleCommand($session, $player, $request['tokens']);
        }
        return $this->scene([['g_st_error', 'Unknown request'], ['i_sent', self::SENT['fail']]]);
    }

    public function webPage(string $page): ?string
    {
        $this->system->hit();
        $dir = $this->publicRoot . '/pages/';
        if ($page === 'rankings') {
            $start = 'rank_start.htm';
            $end = 'rank_end.htm';
            $people = $this->store->state->players;
            usort($people, fn ($a, $b) => (int) $this->num($b, 'exp') <=> (int) $this->num($a, 'exp'));
            $body = '';
            foreach ($people as $index => $entry) {
                if (($entry->active ?? true) === false) {
                    continue;
                }
                $body .= '<tr><td width="30">' . ($index + 1) . '</td><td width="212">' . $this->html((string) $entry->name)
                    . '</td><td width="47">' . (($entry->male ?? true) === false ? 'F' : 'M') . '</td><td width="206">' . $this->html((string) $entry->funeralName)
                    . '</td><td width="131"><div align="right">' . fq_cash((int) $this->num($entry, 'exp')) . '</div></td></tr>';
            }
        } elseif ($page === 'server') {
            $start = 'server_start.htm';
            $end = 'server_end.htm';
            $email = $this->html($this->adminEmail);
            $nameCost = $this->rule('name_change_cost');
            $body = fq_seth_ansi("`\$Admin email: `w<a href=\"mailto:$email\">$email</a><br>"
                . '`$Turns per day: `w' . $this->rule('turns_per_day') . '<br>'
                . "`\$Yang's per day: `w" . $this->rule('yangs_per_day') . '<br>'
                . '`$Player visits per day: `w' . $this->rule('visits_per_day') . '<br>'
                . '`$Risk win/lose amount: `w' . $this->rule('risk') . '<br>'
                . '`$Top player BP bonus: `w' . $this->rule('bonus_bp') . '<br>'
                . '`$Top player luck bonus: `w' . $this->rule('bonus_luck') . '<br>'
                . '`$Amount of tags players can receive by /give per day: `w' . $this->rule('tags_per_day') . '<br>'
                . '`$Cost to change name: `w' . ($nameCost > 0 ? $nameCost : 'Disabled') . '<br>'
                . '<br>'
                . '`$Starting Hitpoints: `w' . $this->rule('starting_hp') . '<br>'
                . '`$Starting Strength: `w' . $this->rule('starting_strength') . '<br>'
                . '`$Starting Luck: `w' . $this->rule('starting_luck') . '<br>');
        } else {
            return null;
        }
        $head = @file_get_contents($dir . $start);
        $tail = @file_get_contents($dir . $end);
        if ($head === false || $tail === false) {
            return null;
        }
        return $head . $body . $tail;
    }

    private function html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'ISO-8859-1');
    }

    private function onlineCount(): int
    {
        $online = 0;
        foreach ($this->store->state->sessions as $entry) {
            if ($entry->playerId !== null) {
                $online++;
            }
        }
        return $online;
    }

    private function population(): int
    {
        $population = 0;
        foreach ($this->store->state->players as $person) {
            if (($person->active ?? true) !== false) {
                $population++;
            }
        }
        return $population;
    }

    private function townFullText(): string
    {
        return "The town is full.  (this server allows {$this->maxPlayers} players max)  Come back later!";
    }

    private function intro(): string
    {
        $online = $this->onlineCount();
        $seconds = max(1, time() - $this->bootedAt);
        if ($seconds / 60 > 59) {
            $uptime = str_pad((string) (int) round($seconds / 3600), 6, ' ', STR_PAD_LEFT) . ' Hour(s)';
        } elseif ($seconds > 59) {
            $uptime = str_pad((string) (int) round($seconds / 60), 6, ' ', STR_PAD_LEFT) . ' Min';
        } else {
            $uptime = str_pad((string) (int) round($seconds), 6, ' ', STR_PAD_LEFT) . ' Sec';
        }
        $day = (int) ($this->store->state->day ?? 1);
        $population = $this->population();
        if ($this->tournamentDays > 0) {
            $info = "Game Info: Tournament Mode Active (day $day of {$this->tournamentDays}) - $online of $population players on now.";
            if ($this->tournamentEndsToday()) {
                $winner = $this->highestExperience();
                $info = $winner
                    ? "Game Info: Tournament is OVER, {$winner->name} declared winner.  New game starting tomorrow."
                    : 'New tournament starting tomorrow, please come back then!';
            }
        } else {
            $info = "Game Info: No Reset Mode Active (day $day) - $online of $population players on now.";
        }
        $noMore = $population >= $this->maxPlayers ? $this->townFullText() : '';
        if ($this->tournamentEndsToday()) {
            $noMore = 'A new game is starting tomorrow, please come back then!';
        }
        $pad = max(0, intdiv(120 - strlen($info), 2));
        return $this->scene([
            ['st_intro_news', '<p align="center"><u><a href="' . self::PROJECT_URL . '" target="_blank">Funeral Quest Web Server</a></u>   |   <u><a href="pages/about.htm">About this game</a></u><br><font color="#BBBBBB">Original game copyright 2001-2011: <u><a href="https://www.rtsoft.com">Robinson Technologies</a></u>   |   Uptime: ' . trim($uptime) . '</font></p>'],
            ['st_no_more', $noMore],
            ['st_game_stats', str_repeat(' ', $pad) . $info],
            ['st_server_news', $this->serverNews],
            ['i_sent', self::SENT['intro']],
        ]);
    }

    private function validateNew(array $tokens): ?array
    {
        $name = $tokens['name'] ?? '';
        $logon = $tokens['logon'] ?? '';
        $funeral = $tokens['f_name'] ?? '';
        $password = $tokens['password'] ?? '';
        $confirm = $tokens['password_v'] ?? '';
        $email = $tokens['email'] ?? '';
        $checks = [
            [$this->population() >= $this->maxPlayers, $this->townFullText(), self::SENT['newName']],
            [strlen($name) < 3, '`wYour name is too short.  Try a longer one.', self::SENT['newName']],
            [strlen($name) > 20, '`wYour name is too long.  Try a shorter one.', self::SENT['newName']],
            [$this->store->findName($name) !== null, "`wSomeone is already using the name $name. Please try another.", self::SENT['newName']],
            [strlen($logon) < 3, '`wYour logon name is too short.  Try a longer one.', self::SENT['newDetails']],
            [strlen($logon) > 20, '`wYour logon name is too long.  Try a shorter one.', self::SENT['newDetails']],
            [$this->store->findLogon($logon) !== null, "`wSomeone is already using the logon name of $logon. Please try another.", self::SENT['newDetails']],
            [strlen($funeral) < 3, '`wYour funeral parlor name is too short.  Try a longer one.', self::SENT['newDetails']],
            [strlen($funeral) > 20, '`wYour funeral parlor name is too long.  Try a shorter one.', self::SENT['newDetails']],
            [strlen($password) < 1, '`wYour password is too short.  Try a longer one.', self::SENT['newDetails']],
            [strlen($password) > 20, '`wYour password is too long.  Try a shorter one.', self::SENT['newDetails']],
            [$password !== $confirm, "`wYour passwords don't match!  Try again, genius.", self::SENT['newDetails']],
            [strlen($email) < 4, '`wEmail address required.  (Only used for game purposes)', self::SENT['newDetails']],
            [strlen($email) > 45, '`wEmail address is too big.  Try a smaller one.', self::SENT['newDetails']],
        ];
        foreach ($checks as $check) {
            if ($check[0]) {
                return $check;
            }
        }
        return null;
    }
}
