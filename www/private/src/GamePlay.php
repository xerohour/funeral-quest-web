<?php
declare(strict_types=1);

trait FqPlay
{
    private function localDate(): string
    {
        $now = new DateTimeImmutable('now');
        return $now->format('Y-m-d');
    }

    private function monthAndDay(): string
    {
        $now = new DateTimeImmutable('now');
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return $months[(int) $now->format('n') - 1] . ' ' . $now->format('j');
    }

    private function paper(mixed $items, string $empty): string
    {
        $list = is_array($items) ? $items : [];
        return $list ? implode('', $list) : $empty;
    }

    private function showFront(object $session, int $day, bool $enter = true): string
    {
        if ($enter && $this->active) {
            $this->active->location = 'News';
            $this->active->callback = 0;
            $this->active->customer = null;
            $this->active->risk = null;
            $this->store->save();
        }
        $session->newsDay = $day;
        $yesterday = $day !== (int) ($this->store->state->day ?? 1);
        $items = $yesterday ? ($this->store->state->yesterday ?? []) : ($this->store->state->news ?? []);
        $top = $this->sellerOfTheDay();
        $topToday = $top ? $top->name . ' from ' . $top->funeralName . ' with ' . (int) $this->num($top, 'buryPoints') . ' Bury Points.' : 'Sadly, no one has managed to sell anything yet today.';
        $winner = $this->tournamentEndsToday() ? $this->highestExperience() : null;
        if ($winner) {
            $topToday = 'Top seller of the universe is ' . $winner->name . '.';
        }
        return $this->scene([
            ['st_date', "Day $day"],
            ['st_top_today', $topToday],
            ['st_top_yesterday', $this->val($this->store->state, 'topYesterday', 'No one died yesterday.')],
            ['st_news', $this->tournamentCountdown() . $this->paper($items, 'Nothing has happened today.')],
            ['i_yesterday', $yesterday ? 1 : 0],
            ['i_sent', self::SENT['news']],
        ]);
    }

    private function showCrime(object $session): string
    {
        $day = (int) ($session->newsDay ?? $this->store->state->day ?? 1);
        $yesterday = $day !== (int) ($this->store->state->day ?? 1);
        $items = $yesterday ? ($this->store->state->crimeYesterday ?? []) : ($this->store->state->crime ?? []);
        return $this->scene([
            ['st_date', "Day $day"],
            ['st_news', $this->paper($items, 'There has been no crime reported today.')],
            ['i_yesterday', $yesterday ? 1 : 0],
            ['i_sent', self::SENT['news']],
        ]);
    }

    private function sellerOfTheDay(): ?object
    {
        $best = null;
        $score = 0;
        foreach ($this->store->state->players as $person) {
            if (($person->active ?? true) === false) {
                continue;
            }
            if ((int) $this->num($person, 'dayLastLoggedOn') !== (int) ($this->store->state->day ?? 1)) {
                continue;
            }
            if ((int) $this->num($person, 'buryPoints') > $score) {
                $best = $person;
                $score = (int) $person->buryPoints;
            }
        }
        return $best;
    }

    private function highestExperience(array $skip = []): ?object
    {
        $best = null;
        $score = 0;
        foreach ($this->store->state->players as $person) {
            if (($person->active ?? true) === false || in_array($person, $skip, true)) {
                continue;
            }
            if ((int) $this->num($person, 'exp') > $score) {
                $best = $person;
                $score = (int) $person->exp;
            }
        }
        return $best;
    }

    private function awardStanding(object $person, int $expBonus, int $luckBonus, string $message): void
    {
        $this->postMail($person, $message, null, null, (object) ['exp' => $expBonus, 'luck' => $luckBonus]);
    }

    private function tournamentEndsToday(): bool
    {
        return $this->tournamentDays > 0 && (int) ($this->store->state->day ?? 1) === $this->tournamentDays + 1;
    }

    private function tournamentEndsTomorrow(): bool
    {
        return $this->tournamentDays > 0 && (int) ($this->store->state->day ?? 1) === $this->tournamentDays;
    }

    private function tournamentCountdown(): string
    {
        $day = (int) ($this->store->state->day ?? 1);
        if ($this->tournamentDays < 1 || $day + 5 <= $this->tournamentDays) {
            return '';
        }
        if ($day === $this->tournamentDays) {
            return "`5Today is the final day of the tournament - a winner will be declared at midnight!\n\n";
        }
        if ($this->tournamentEndsToday()) {
            return '';
        }
        if ($day === $this->tournamentDays - 1) {
            return "`5Tomorrow is the last day of the tournament.\n\n";
        }
        return '`5Only ' . ($this->tournamentDays - $day) . " days left before a tournament winner shall be declared!\n\n";
    }

    private function resetTown(): void
    {
        foreach (array_keys(get_object_vars($this->store->state->sessions)) as $id) {
            unset($this->runs[(string) $id], $this->store->state->sessions->{$id}->vm);
            $this->store->logoff((string) $id);
        }
        $this->store->state->players = [];
        $this->store->state->news = [];
        $this->store->state->crime = [];
        $this->store->state->yesterday = [];
        $this->store->state->crimeYesterday = [];
        $this->store->state->papers = new stdClass();
        unset($this->store->state->topYesterday);
        $this->store->state->day = 0;
        $this->system->forget();
        $this->runSinglePass('reset.c');
        $this->reloadGlobals();
    }

    private function rollDay(): bool
    {
        $today = $this->localDate();
        if (($this->store->state->date ?? null) === $today) {
            return false;
        }
        $first = empty($this->store->state->date);
        $this->store->state->date = $today;
        if ($first) {
            $this->runSinglePass('maint.c');
            return true;
        }
        $this->logMsg('Running maint.');
        if ($this->tournamentEndsToday()) {
            $this->logMsg('Resetting game...');
            $this->resetTown();
        }
        $lastDay = $this->tournamentEndsTomorrow();
        $seller = $this->sellerOfTheDay();
        $this->store->state->topYesterday = $seller
            ? $seller->name . ' from ' . $seller->funeralName . ' with ' . (int) $this->num($seller, 'buryPoints') . " Bury Points.\n\n"
            : 'No one died yesterday.';
        $expBonus = $this->rule('bonus_bp');
        $luckBonus = $this->rule('bonus_luck');
        if ($seller && !$lastDay) {
            $this->awardStanding($seller, $expBonus, $luckBonus, "`wCongratulations!\n`\$You were the top seller for yesterday.  You earn `w" . fq_cash($luckBonus) . "`\$ luck and `w" . fq_cash($expBonus) . "`\$ Bury Points.\n");
        }
        if (!$lastDay) {
            $firstPlace = $this->highestExperience();
            if ($firstPlace) {
                $this->awardStanding($firstPlace, $expBonus, $luckBonus, "`wCongratulations!\n`\$You ran the top parlor in town yesterday.  You earn `w" . fq_cash($luckBonus) . "`\$ luck and `w" . fq_cash($expBonus) . "`\$ Bury Points.\n");
            }
            $second = $this->highestExperience($firstPlace ? [$firstPlace] : []);
            if ($second) {
                $halfExp = (int) ($expBonus * 0.5);
                $halfLuck = (int) ($luckBonus * 0.5);
                $this->awardStanding($second, $halfExp, $halfLuck, "`wCongratulations!\n`\$You were the second highest parlor in town yesterday. You earn `w" . fq_cash($halfLuck) . "`\$ luck and `w" . fq_cash($halfExp) . "`\$ Bury Points.\n");
            }
            $third = $this->highestExperience(array_values(array_filter([$firstPlace, $second])));
            if ($third) {
                $quarterExp = (int) ($expBonus * 0.25);
                $quarterLuck = (int) ($luckBonus * 0.25);
                $this->awardStanding($third, $quarterExp, $quarterLuck, "`wCongratulations!\n`\$You were the third highest parlor in town yesterday. You earn `w" . fq_cash($quarterLuck) . "`\$ luck and `w" . fq_cash($quarterExp) . "`\$ Bury Points.\n");
            }
        }
        $closing = (int) ($this->store->state->day ?? 1);
        $papers = $this->store->state->papers ?? (object) [];
        if ($closing > 0) {
            $papers->{$closing} = implode('', $this->list($this->store->state, 'news'));
        }
        $this->store->state->papers = $papers;
        $this->store->state->yesterday = $this->list($this->store->state, 'news');
        $this->store->state->crimeYesterday = $this->list($this->store->state, 'crime');
        $this->store->state->news = [];
        $this->store->state->crime = [];
        $this->store->state->day = (int) ($this->store->state->day ?? 1) + 1;
        foreach ($this->store->state->players as $index => $person) {
            if (($person->active ?? true) === false) {
                continue;
            }
            $person->buryPoints = 0;
            $person->yangsSent = $this->tournamentEndsToday() ? $this->rule('yangs_per_day') : 0;
            $person->tagsReceived = 0;
            $day = (int) $this->store->state->day;
            if ((int) $this->num($person, 'dayLastLoggedOn') + $this->rule('inactive_days') < $day) {
                $person->active = false;
                $this->townNotice($person->name . ' closes shop and leaves town.');
            }
            if ((int) $this->num($person, 'dayLastLoggedOn') + 1 < $day) {
                $person->police = false;
            }
            if ($this->rule('delete_unsold') && ($person->active ?? true) !== false && (int) $this->num($person, 'exp') === 0 && !$this->isOnline($person, null)) {
                $person->active = false;
                $this->townNotice($person->name . ' closes shop and leaves town without a single sale.');
            }
            if (($person->active ?? true) !== false) {
                $this->runSinglePass('maint_player.c', $index);
            }
        }
        if ($this->tournamentEndsToday()) {
            $this->declareTournamentWinner();
        } else {
            $this->postDailyBlurb();
        }
        $this->system->rollStats();
        $this->runSinglePass('maint.c');
        return true;
    }

    private function declareTournamentWinner(): void
    {
        $winner = $this->highestExperience();
        if ($winner) {
            $this->addNews("`7TOURNAMENT WINNER HAS BEEN DECLARED!\n");
            $this->addNews('`^With ' . fq_cash((int) $this->num($winner, 'exp')) . ' Bury Points and ' . fq_cash((int) $this->num($winner, 'plots')) . ' plots, ' . $winner->name . " has been declared the victor.\n\n");
        }
        $this->addNews("`7GIANT METEOR TO DESTROY THE WORLD AT MIDNIGHT!\n");
        $this->addNews("`^\"This is a real bummer, but at least we have time to read our mail and stuff.  But who will bury us?,\" comments a local astronomer.\n\n");
        if ($winner) {
            $this->store->state->deeds = (string) ($this->store->state->deeds ?? '') . $winner->name . " won the 'Best Funeral Directory In The Universe' award.\n";
        }
    }

    private function postDailyBlurb(): void
    {
        $day = (int) $this->store->state->day;
        $choices = array_values(array_filter($this->newsBlurbs, fn ($entry) => $day >= $entry['low'] && $day <= $entry['high']));
        $event = $this->pick($choices);
        if (!$event) {
            return;
        }
        $this->addNews('`7' . $event['title'] . "\n`^" . $event['message'] . "\n\n");
        if (($event['giveRandom'] ?? '') === '') {
            return;
        }
        $people = array_values(array_filter($this->store->state->players, fn ($person) => ($person->active ?? true) !== false));
        if (!$people) {
            return;
        }
        $person = $this->pick($people);
        $this->logMsg("Today's random event happening to {$person->name}.");
        $points = $this->between((int) $event['bp'][0], (int) $event['bp'][1]);
        $luck = $this->between((int) $event['luck'][0], (int) $event['luck'][1]);
        $turns = $this->between((int) $event['turns'][0], (int) $event['turns'][1]);
        $reward = $this->rewardText([[$points, 'Bury Point', 'Bury Points'], [$luck, 'luck', 'luck'], [$turns, 'turn', 'turns']], true);
        $this->postMail($person, str_replace('\\n', "\n", $event['giveRandom']) . $reward, $event['pic'], null, (object) ['exp' => $points, 'luck' => $luck, 'turns' => $turns]);
        if (($event['addLog'] ?? '') !== '') {
            $this->addNews($this->fillBlanks($event['addLog'], $person) . "\n\n");
        }
    }

    private function refreshDay(object $session, object $player): void
    {
        $rolled = $this->rollDay();
        $previous = (int) $this->num($player, 'dayLastLoggedOn');
        if ($previous === (int) ($this->store->state->day ?? 1)) {
            if ($rolled) {
                $this->store->save();
            }
            return;
        }
        $player->dayLastLoggedOn = (int) $this->store->state->day;
        $player->turns = $this->rule('turns_per_day') + (int) fq_ride($player)['bonus'];
        $player->kickedOut = false;
        $player->visits = 0;
        $player->police = false;
        $player->buryPoints = 0;
        $this->runSinglePass('newday_player.c', $this->playerIndex($player));
        if ($this->tournamentEndsToday()) {
            $player->turns = 0;
            $player->visits = $this->rule('visits_per_day');
        }
        if ($previous === 0) {
            $session->pendingMorning = 'first';
        } else {
            $session->pendingMorning = !empty($session->gaveLogonMessage) ? 'awake' : 'return';
        }
        $this->store->save();
    }

    private function room(int $command, ?string $place = null, bool $endScript = false): void
    {
        if (!$this->active) {
            return;
        }
        if ($endScript) {
            unset($this->runs[$this->active->id], $this->active->vm);
        }
        $this->active->callback = $command;
        if ($place !== null) {
            $this->active->location = $place;
        }
        $this->store->save();
    }

    private function main(object $session, object $player): string
    {
        $this->room(0, 'At Home', true);
        $ride = fq_ride($player);
        $pending = $session->pendingMorning ?? null;
        $bonus = ($ride['bonus'] ?? 0) > 0 ? 'Your ' . $ride['name'] . ' gives you time for `w' . $ride['bonus'] . '`$ extra customers. ' : '';
        if (!empty($player->police)) {
            $bonus .= 'There are police patrolling your parlor.';
        }
        if ($pending) {
            $session->pendingMorning = null;
            $session->gaveLogonMessage = true;
            $greeting = $pending === 'first'
                ? '`wWelcome to Funeral Quest, ' . $player->name . ".  Good luck.`^\n\n`\$You unlock the front door and enter your beloved " . $player->funeralName . " Funeral Parlor.  Ah, it's great to be alive.\n\n$bonus"
                : ($pending === 'return'
                    ? '`wWelcome back, ' . $player->name . "!`^\n\n`\$You unlock the front door and enter your beloved " . $player->funeralName . " Funeral Parlor.  Ah, it's great to be alive.\n\n$bonus"
                    : "`wYou're eyes grow heavy.  You wake up the next morning, still at your desk.\n\n`\$You quickly freshen up.. who knows what great things await " . $player->funeralName . " Funeral Parlor today?\n\n$bonus");
            $this->store->save();
            return $this->scene(array_merge($this->stats($player), [['st_url', $this->art('flash/morning_coffee.swf')], ['st_object', 'm_main'], ['st_main', $greeting]], $this->continueButton(), [['i_sent', self::SENT['object']]]));
        }
        $inbox = $this->list($player, 'inbox');
        if ($inbox && empty($session->gaveLogonMessage) && empty($inbox[0]->letter)) {
            $session->gaveLogonMessage = true;
            return $this->showInbox($player, '`$Welcome back, `w' . $player->name . "`\$.  Unfinished business?\n");
        }
        if (empty($session->gaveLogonMessage)) {
            $session->gaveLogonMessage = true;
            $notice = $this->takeYells($session, "\n");
            $this->store->save();
            return $this->scene(array_merge($this->stats($player), [
                ['st_url', $this->art('flash/places/desk.swf')],
                ['st_object', 'm_main'],
                ['st_main', '`$Welcome back, `w' . $player->name . "`\$.  Unfinished business?\n" . $notice],
                ['i_sent', self::SENT['object']],
            ]));
        }
        if ($inbox) {
            return $this->showInbox($player);
        }
        $deliveries = $this->list($player, 'deliveries');
        if ($deliveries) {
            return $this->showDelivery($player);
        }
        $yells = $this->takeYells($session, "\n");
        $line = $yells !== ''
            ? '`$'.$yells
            : (!empty($player->police) ? "`\$You are sitting at your desk.  Police are outside, you feel safe.\n" : "`\$You are sitting at your desk.\n");
        $this->store->save();
        return $this->scene(array_merge($this->stats($player), [
            ['st_url', $this->art('flash/places/desk.swf')],
            ['st_object', 'm_main'],
            ['st_main', $line],
            ['i_sent', self::SENT['object']],
        ]));
    }

    private function showInbox(object $player, string $prefix = ''): string
    {
        $inbox = $this->list($player, 'inbox');
        $note = array_shift($inbox);
        $player->inbox = $inbox;
        $yells = $this->active ? $this->takeYells($this->active, "\n") : '';
        if (!empty($note->letter)) {
            $this->store->save();
            $body = (string) ($note->body ?? '');
            return $this->scene([
                ...$this->stats($player),
                ['st_url', $this->art('flash/places/desk.swf')],
                ['st_object', 'm_main'],
                ['st_quote', $this->quoteForReply($body)],
                ['st_option_2', '    Reply'],
                ['i_command_2', 21],
                ['i_his_id', $note->fromIndex],
                ['st_option_3', 'Continue'],
                ['i_command_3', 4],
                ['b_kill_buttons', 1],
                ['st_main', '`w`$You have received a letter from `w' . $note->fromName . "`\$:\n\n$body\n" . $yells],
                ['i_sent', self::SENT['object']],
            ]);
        }
        $this->applyGrant($player, $note->grant ?? null);
        $this->store->save();
        $picture = trim((string) ($note->art ?? ''));
        $fields = [
            ...$this->stats($player),
            ['st_url', $this->art($picture !== '' ? $picture : 'flash/places/desk.swf')],
            ['st_object', 'm_main'],
            ['st_main', ($prefix !== '' ? $prefix : '`$') . '`w' . ($note->text ?? '') . "\n" . $yells],
            ...$this->continueButton(),
            ['i_sent', self::SENT['object']],
        ];
        $background = trim((string) ($note->background ?? ''));
        if ($background !== '') {
            $fields[] = ['st_url_bg', $this->art($background)];
        }
        return $this->scene($fields);
    }

    private function quoteForReply(string $content): string
    {
        $text = str_replace("\r\n", "\n", $content);
        $marker = strrpos($text, "\n>");
        if ($marker !== false) {
            $start = $marker + 1;
            $after = strpos($text, "\n", $start);
            if ($after !== false) {
                $start = $after + 1;
                if (($text[$start] ?? '') === "\n") {
                    $start++;
                }
            }
            $text = substr($text, $start);
        }
        return '>' . str_replace("\n", "\n>", $text) . "\n\n";
    }

    private function showDelivery(object $player): string
    {
        $items = $this->list($player, 'deliveries');
        $note = array_shift($items);
        $player->deliveries = $items;
        $this->store->save();
        return $this->scene([
            ...$this->stats($player),
            ['st_url', $this->art($note->art ?? 'flash/chinese_takeout.swf')],
            ['st_main', $note->text ?? ''],
            ...$this->continueButton(),
            ['i_sent', self::SENT['loadMovie']],
        ]);
    }

    private function applyGrant(object $player, mixed $grant): void
    {
        if (!is_object($grant)) {
            return;
        }
        if (isset($grant->exp)) {
            $this->applyExp($player, (int) $grant->exp);
        }
        if (isset($grant->luck)) {
            $this->applyLuck($player, (int) $grant->luck);
        }
        if (isset($grant->turns)) {
            $this->applyTurns($player, (int) $grant->turns);
        }
    }

    private function applyExp(object $player, int $amount): void
    {
        $delta = $amount;
        if ((int) $this->num($player, 'buryPoints') + $delta < 0) {
            $delta = -((int) $this->num($player, 'buryPoints'));
        }
        $player->buryPoints = max(0, (int) $this->num($player, 'buryPoints') + $delta);
        $player->exp = max(0, (int) $this->num($player, 'exp') + $delta);
    }

    private function applyLuck(object $player, int $amount): void
    {
        $player->luck = min(100, max(1, (int) $this->num($player, 'luck', 50) + $amount));
    }

    private function applyTurns(object $player, int $amount): void
    {
        $player->turns = max(0, (int) $this->num($player, 'turns') + $amount);
    }

    private function fillBlanks(string $text, object $player): string
    {
        return str_replace(
            ['\\n', '*SIR*', '*NAME*', '*PARLOR*', '*HOME*'],
            ["\n", ($player->male ?? true) === false ? "ma'am" : 'sir', $player->name, $player->funeralName, $player->funeralName],
            $text
        );
    }

    private function rewardText(array $changes, bool $onlyPositiveOne = false): string
    {
        $text = '';
        $count = 0;
        foreach ($changes as [$amount, $singular, $plural]) {
            if ($amount === 0) {
                continue;
            }
            $count++;
            $one = $onlyPositiveOne ? $amount === 1 : abs($amount) === 1;
            $name = $one ? $singular : $plural;
            if ($count === 1) {
                $text .= $amount > 0 ? "\n\n`\$You earn `w$amount`\$ $name" : "\n\n`4You lose `w" . abs($amount) . "`4 $name";
            } else {
                $text .= $amount > 0 ? " and `w$amount`\$ $name" : ' and `w' . abs($amount) . "`4 $name";
            }
        }
        if ($count > 0) {
            $text .= '.';
        }
        return $text;
    }

    private function movieScene(object $player, string $message, ?string $picture = null, ?string $background = null, array $buttons = [], ?array $only = null): string
    {
        $stats = $this->stats($player);
        if ($only !== null) {
            $stats = array_values(array_filter($stats, fn ($field) => in_array($field[0], $only, true)));
        }
        $fields = [...$stats, ['st_url', $this->art($picture ?: 'flash/places/desk.swf')], ['st_main', $message], ['i_sent', self::SENT['loadMovie']]];
        if ($background) {
            $fields[] = ['st_url_bg', $this->art($background)];
        }
        if ($buttons) {
            $fields[] = ['b_kill_buttons', 1];
            array_push($fields, ...$this->buttons($buttons));
        }
        return $this->scene($fields);
    }

    private function genericPlace(object $player, string $picture, string $message, array $buttons, array $options = []): string
    {
        $person = str_contains($picture, '/people/');
        $foreground = $options['foreground'] ?? ($person ? $picture : 'flash/blank.swf');
        $background = $options['background'] ?? ($person ? 'flash/blank.swf' : ($picture ?: 'flash/blank.swf'));
        $fields = [
            ...$this->stats($player),
            ['st_url', array_key_exists('url', $options) ? (string) $options['url'] : $this->art($foreground)],
            ['st_url_bg', $this->art($background)],
            ['st_generic_info', $options['info'] ?? ''],
            ['st_generic_status', $options['status'] ?? ''],
            ['st_main', $message],
            ['st_object', 'i_generic'],
            ['b_show_tags', !empty($options['battle']) ? 2 : 1],
            ['i_sent', self::SENT['object']],
        ];
        if (array_key_exists('battle', $options)) {
            $fields[] = ['b_show_battle', $options['battle'] ? 1 : 2];
        }
        array_push($fields, ...$this->buttons($buttons));
        return $this->scene($fields);
    }

    private function tagFields(object $player): array
    {
        $fields = [];
        foreach ($this->stats($player) as $field) {
            if (str_starts_with($field[0], 'i_coffin_')) {
                $fields[] = $field;
            }
        }
        return $fields;
    }

    private function refreshVisit(array $options): string
    {
        $fields = [['st_main', $options['message'] ?? '']];
        if (array_key_exists('info', $options)) {
            $fields[] = ['st_generic_info', $options['info']];
        }
        if (array_key_exists('status', $options)) {
            $fields[] = ['st_generic_status', $options['status']];
        }
        if (array_key_exists('url', $options)) {
            $fields[] = ['st_url', (string) $options['url']];
        } elseif (array_key_exists('foreground', $options)) {
            $fields[] = ['st_url', $this->art($options['foreground'])];
        }
        if (array_key_exists('background', $options)) {
            $fields[] = ['st_url_bg', $this->art($options['background'])];
        }
        foreach ($options['extra'] ?? [] as $extra) {
            $fields[] = $extra;
        }
        array_push($fields, ...$this->buttons($options['buttons'] ?? []));
        $fields[] = ['i_sent', self::SENT['generic']];
        return $this->scene($fields);
    }

    private function refreshMain(string $message, array $buttons, array $extra = []): string
    {
        return $this->scene([
            ['st_main', $message],
            ...$extra,
            ...$this->buttons($buttons),
            ['i_sent', self::SENT['generic']],
        ]);
    }

    private function viewPage(string $text, int $command, int $sub, string $background = 'flash/places/psych.swf'): string
    {
        return $this->scene([
            ['st_url', ''],
            ['st_url_bg', $this->art($background)],
            ['st_object', 'i_view'],
            ['st_view', $text],
            ...$this->buttons([['key' => '3', 'label' => 'Back', 'command' => $command, 'sub' => $sub]]),
            ['i_sent', self::SENT['object']],
        ]);
    }

    private function placeScreen(object $player, array $options): string
    {
        $fields = [
            ...$this->stats($player),
            ['st_url', $this->art($options['foreground'] ?? 'flash/blank.swf')],
            ['st_url_bg', $this->art($options['background'] ?? 'flash/blank.swf')],
            ['st_generic_info', $options['info'] ?? ''],
            ['st_generic_status', ''],
            ['st_main', $options['message'] ?? ''],
            ['st_object', 'i_generic'],
            ['b_show_tags', !empty($options['battle']) ? 2 : 1],
            ['b_show_battle', !empty($options['battle']) ? 1 : 2],
            ['i_sent', self::SENT['object']],
        ];
        foreach ($options['extra'] ?? [] as $extra) {
            $fields[] = $extra;
        }
        array_push($fields, ...$this->buttons($options['buttons'] ?? []));
        return $this->scene($fields);
    }

    private function handleCommand(object $session, object $player, array $tokens): string
    {
        $command = (int) ($tokens['i_command'] ?? 0);
        $needsRoom = [14 => 14, 100 => 100, 101 => 101, 102 => 102, 103 => 103, 104 => 104, 105 => 105, 108 => 108, 109 => 109];
        if (isset($needsRoom[$command]) && (int) ($session->callback ?? 0) !== $command) {
            return $this->scene([
                ['g_st_error', "Server unable to process request $command."],
                ['i_sent', self::SENT['fail']],
            ]);
        }
        $spoke = $command === 24 && trim((string) ($tokens['st_input'] ?? '')) !== '';
        if (!($command === 24 && !$spoke)) {
            $session->lastPacket = (int) (microtime(true) * 1000);
        }
        if (!in_array($command, [23, 24, 22], true) && !empty($session->chatting)) {
            if ($command === 12) {
                $this->pushChat('`5' . $player->name . ' steps outside.');
            }
            $session->chatting = false;
            $this->store->save();
        }
        return match ($command) {
            1 => $this->quit($session),
            37 => $this->hospitalLobby($player),
            101 => $this->hospitalCallback($player, $tokens),
            34 => $this->startFqScript($session, $player, 'public/church.c'),
            36 => $this->spiritLobby($player),
            100 => $this->spiritCallback($player, $tokens),
            13 => $this->yangsLobby($player),
            14 => $this->yangsCallback($session, $player, $tokens),
            40 => $this->storeLobby($player),
            105 => $this->storeCallback($session, $player, $tokens),
            39 => $this->bankLobby($player),
            104 => $this->bankCallback($player, $tokens),
            4 => $this->main($session, $player),
            5 => $this->showFront($session, (int) ($this->store->state->day ?? 1)),
            7 => $this->showFront($session, (int) ($this->store->state->day ?? 1) - 1),
            41 => $this->showFront($session, (int) ($session->newsDay ?? $this->store->state->day ?? 1), false),
            42 => $this->showCrime($session),
            8 => $this->announce($session, $player, $tokens),
            12 => $this->mapScene($session, $player),
            20 => $this->sendMail($player, $tokens),
            21 => $this->scene([
                ['g_st_error', 'Server unable to process request 21.'],
                ['i_sent', self::SENT['fail']],
            ]),
            22, 23 => $this->openCafe($session, $player),
            24 => $this->chatPoll($session, $player, $tokens),
            44 => $this->scriptCommand($session, $player, $tokens),
            17 => $this->lookupName($tokens),
            38 => $this->compLobby($session, $player),
            102 => $this->compCallback($session, $player, $tokens),
            103 => $this->battleCallback($session, $player, $tokens),
            9 => $this->rankingScreen(4),
            3 => $this->startFqScript($session, $player, 'stats.c', 'Stats'),
            43 => $this->optionsScreen($player),
            108 => $this->optionsCallback($player, $tokens),
            31 => $this->saved($this->acceptRisk($session, $player)),
            11 => $this->saved($this->takeRisk($session, $player)),
            109 => $this->saved($this->resumeFqScript($session, $player, $this->scriptResult($tokens))),
            2 => $this->waitCustomer($session, $player),
            30 => $this->finishIfCustomer($session, $player),
            25 => $this->sellIf($session, $player, 'soft'),
            26 => $this->sellIf($session, $player, 'hard'),
            27 => $this->sellIf($session, $player, 'sympathy'),
            29 => $this->sellIf($session, $player, 'guilt'),
            32 => $this->scene([['st_object', 'i_city_hall'], ['i_sent', self::SENT['object']]]),
            default => $this->scene([
                ['g_st_error', "Server unable to process request $command."],
                ['i_sent', self::SENT['fail']],
            ]),
        };
    }

    private function saved(string $body): string
    {
        $this->store->save();
        return $body;
    }

    private function scriptResult(array $tokens): int
    {
        $result = array_key_exists('i_his_id', $tokens) ? (float) $tokens['i_his_id'] : (float) ($tokens['i_command_sub'] ?? 0);
        return is_finite($result) ? (int) $result : 0;
    }

    private function quit(object $session): string
    {
        $leaving = $this->store->playerById($session->playerId);
        if ($leaving) {
            $this->townNotice($leaving->name . ' logged off.', (string) $session->id);
        }
        $this->settleAbandonedSale($session);
        $this->store->logoff($session->id);
        return $this->scene([['i_sent', self::SENT['quit']]]);
    }

    private function mapScene(object $session, object $player): string
    {
        $this->room(0, 'Town Map', true);
        return $this->scene([...$this->stats($player), ['st_object', 'i_map'], ['i_sent', self::SENT['object']]]);
    }

    private function scriptCommand(object $session, object $player, array $tokens): string
    {
        $name = (string) ($tokens['st_extra_text'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
            return $this->main($session, $player);
        }
        return $this->startFqScript($session, $player, 'public/' . $name);
    }

    private function missingCustomer(object $player): string
    {
        return $this->scene([...$this->stats($player), ['g_st_error', "Can't accept, no customer exists"], ['i_sent', self::SENT['fail']]]);
    }

    private function finishIfCustomer(object $session, object $player): string
    {
        if (!is_object($session->customer ?? null)) {
            return $this->missingCustomer($player);
        }
        if (!$this->saleInProgress($session)) {
            return $this->scene([...$this->stats($player), ['g_st_error', 'Huh?  Not this kind of customer.'], ['i_sent', self::SENT['fail']]]);
        }
        $response = $this->finishSale($player, $session->customer);
        $session->customer = null;
        $this->store->save();
        return $response;
    }

    private function sellIf(object $session, object $player, string $kind): string
    {
        if (empty($session->customer)) {
            return $this->missingCustomer($player);
        }
        $response = match ($kind) {
            'soft' => $this->softSell($session, $player),
            'hard' => $this->hardSell($session, $player),
            'sympathy' => $this->sympathySell($session, $player),
            default => $this->guiltSell($session, $player),
        };
        $this->store->save();
        return $response;
    }
}
