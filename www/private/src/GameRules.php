<?php
declare(strict_types=1);

trait FqRules
{
    private function linesOf(string $name): array
    {
        $file = $this->distRoot . '/' . $name;
        if (!is_file($file)) {
            return [];
        }
        return preg_split("/\r\n|\n|\r/", (string) file_get_contents($file)) ?: [];
    }

    private function loadRatings(): array
    {
        $ratings = [];
        foreach ($this->linesOf('ratings.txt') as $line) {
            $bar = strpos($line, '|');
            if ($bar === false || $bar < 1) {
                continue;
            }
            $index = (int) substr($line, 0, $bar);
            if ($index >= 1 && $index <= 100) {
                $ratings[$index] = substr($line, $bar + 1);
            }
        }
        return $ratings;
    }

    private function loadSellItems(string $name): array
    {
        $items = [];
        $current = ['article' => 'a', 'name' => '', 'price' => 0];
        foreach ($this->linesOf($name) as $line) {
            $parts = explode('|', $line);
            $key = $parts[0];
            $value = $parts[1] ?? '';
            if ($key === 'reference') {
                $current['article'] = $value;
            } elseif ($key === 'name') {
                $current['name'] = $value;
            } elseif ($key === 'price') {
                $current['price'] = (int) $value;
            } elseif ($key === 'add_item') {
                if ($current['name'] !== '') {
                    $items[] = $current;
                }
                $current = ['article' => 'a', 'name' => '', 'price' => 0];
            }
        }
        return $items;
    }

    private function loadFortunes(string $name): array
    {
        $lines = [];
        foreach ($this->linesOf($name) as $line) {
            if (str_starts_with($line, 'message|')) {
                $text = trim(substr($line, strlen('message|')));
                if ($text !== '') {
                    $lines[] = $text;
                }
            }
        }
        return $lines ?: ['The paper is blank!'];
    }

    private function loadStoryEvents(string $name, string $picture = 'flash/places/embalming-room.swf'): array
    {
        $events = [];
        $low = -229999999;
        $high = 299999999;
        $current = null;
        $flush = function () use (&$events, &$current) {
            if ($current && ($current['message'] ?? '') !== '') {
                $events[] = $current;
            }
            $current = null;
        };
        foreach ($this->linesOf($name) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//')) {
                continue;
            }
            $parts = explode('|', $trimmed);
            $key = $parts[0];
            if ($key === 'range') {
                $flush();
                $low = (int) ($parts[1] ?? $low);
                $high = (int) ($parts[2] ?? $high);
            } elseif ($key === 'start_entry') {
                $flush();
                $current = [
                    'low' => $low, 'high' => $high, 'title' => '', 'message' => '', 'giveRandom' => '',
                    'addLog' => '', 'pic' => $picture, 'bp' => [0, 0], 'luck' => [0, 0], 'turns' => [0, 0],
                ];
            } elseif ($current && $key === 'title') {
                $current['title'] = implode('|', array_slice($parts, 1));
            } elseif ($current && $key === 'message') {
                $current['message'] = implode('|', array_slice($parts, 1));
            } elseif ($current && $key === 'give_random') {
                $current['giveRandom'] = implode('|', array_slice($parts, 1));
            } elseif ($current && $key === 'add_log') {
                $current['addLog'] = implode('|', array_slice($parts, 1));
            } elseif ($current && $key === 'set_pic') {
                $current['pic'] = $parts[1] ?? $current['pic'];
            } elseif ($current && $key === 'mod_bp') {
                $current['bp'] = [(int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0)];
            } elseif ($current && $key === 'mod_luck') {
                $current['luck'] = [(int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0)];
            } elseif ($current && $key === 'mod_turns') {
                $current['turns'] = [(int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0)];
            }
        }
        $flush();
        return $events;
    }

    private function loadRisks(): array
    {
        $risks = [];
        $blank = fn () => [
            'startMessage' => '', 'startUrl' => '', 'startBg' => '', 'accept' => 'Accept', 'risk' => 'Risk',
            'acceptMessage' => '', 'acceptUrl' => '', 'acceptBg' => '', 'successMessage' => '', 'successUrl' => '',
            'successBg' => '', 'failMessage' => '', 'failUrl' => '', 'failBg' => '',
        ];
        $current = $blank();
        foreach ($this->linesOf('risks.txt') as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//')) {
                continue;
            }
            $parts = explode('|', $trimmed);
            $key = $parts[0];
            $value = implode('|', array_slice($parts, 1));
            $map = [
                'start_message' => 'startMessage', 'start_url' => 'startUrl', 'start_url_bg' => 'startBg',
                'normal' => 'accept', 'risk' => 'risk', 'success_message' => 'successMessage',
                'success_url' => 'successUrl', 'success_url_bg' => 'successBg', 'fail_message' => 'failMessage',
                'fail_url' => 'failUrl', 'fail_url_bg' => 'failBg', 'normal_message' => 'acceptMessage',
                'normal_url' => 'acceptUrl', 'normal_url_bg' => 'acceptBg',
            ];
            if ($key === 'add_risk') {
                if ($current['startMessage'] !== '') {
                    $risks[] = $current;
                }
                $current = $blank();
            } elseif (isset($map[$key])) {
                $current[$map[$key]] = $value;
            }
        }
        return $risks;
    }

    private function loadSwearWords(): array
    {
        $words = [];
        foreach ($this->linesOf('swear_words.txt') as $line) {
            $parts = explode('|', $line);
            if (($parts[0] ?? '') === 'addword' && ($parts[1] ?? '') !== '') {
                $words[] = [$parts[1], $parts[2] ?? ''];
            }
        }
        return $words;
    }

    private function readScript(string $file): string
    {
        $buf = (string) file_get_contents($file);
        if (strlen($buf) >= 2 && $buf[0] === "\xFF" && $buf[1] === "\xFE") {
            $text = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($buf, 2));
            return $text === false ? '' : $text;
        }
        return preg_replace('/^\xEF\xBB\xBF/', '', $buf) ?? $buf;
    }

    private function scriptFile(string $relative): ?string
    {
        $slashed = ltrim(str_replace('\\', '/', $relative), '/');
        $slashed = preg_replace('#^script/#', '', $slashed) ?? $slashed;
        if ($slashed === '' || str_contains($slashed, '..')) {
            return null;
        }
        $file = $this->distRoot . '/script/' . $slashed;
        $root = realpath($this->distRoot . '/script');
        $real = realpath($file);
        if ($root === false || $real === false || !str_starts_with($real, $root)) {
            return null;
        }
        return $real;
    }

    private function ensureGlobals(): void
    {
        if ($this->globalsReady) {
            return;
        }
        $this->globalsReady = true;
        if (isset($this->store->state->scriptGlobals)) {
            $loaded = json_decode(json_encode($this->store->state->scriptGlobals), true);
            if (is_array($loaded)) {
                $this->scriptGlobals = $loaded;
            }
        }
        if (isset($this->scriptGlobals['C_WOOD_TAG'])) {
            return;
        }
        $file = $this->scriptFile('init.c');
        if (!$file) {
            return;
        }
        $runtime = new FqRuntime($this->scriptGlobals, fn () => ['type' => 'int', 'value' => 0], fn () => null);
        $runtime->start($this->readScript($file), 'init.c', []);
        $this->store->state->scriptGlobals = json_decode(json_encode($this->scriptGlobals));
    }

    private function runtime(object $session, object $player): FqRuntime
    {
        $this->ensureGlobals();
        $index = $this->playerIndex($player);
        return new FqRuntime(
            $this->scriptGlobals,
            function (string $name, array $args, FqRuntime $api) use ($session, $index) {
                return $this->hostCall($session, $name, $args, $api, $index);
            },
            function (string $path) use ($session, $player) {
                return $this->beginScript($session, $player, $path);
            }
        );
    }

    private function hydrate(object $session): void
    {
        if (!isset($session->vm) || isset($this->runs[$session->id])) {
            return;
        }
        $player = $this->store->playerById($session->playerId);
        if (!$player) {
            return;
        }
        $runtime = $this->runtime($session, $player);
        $snap = json_decode(json_encode($session->vm), true);
        if (is_array($snap)) {
            $runtime->restore($snap);
            $this->runs[$session->id] = $runtime;
        }
    }

    private function persistVm(object $session): void
    {
        $runtime = $this->runs[$session->id] ?? null;
        if ($runtime && $runtime->waiting()) {
            $session->vm = json_decode(json_encode($runtime->export()));
        } else {
            unset($session->vm);
        }
        $this->store->state->scriptGlobals = json_decode(json_encode($this->scriptGlobals));
    }

    private function beginScript(object $session, object $player, string $relative): ?FqRuntime
    {
        $file = $this->scriptFile($relative);
        if (!$file) {
            return null;
        }
        $runtime = $this->runtime($session, $player);
        $this->runs[$session->id] = $runtime;
        $runtime->start($this->readScript($file), basename($file), [
            ['i_my_id', ['type' => 'int', 'value' => $this->playerIndex($player)]],
            ['i_my_result', ['type' => 'int', 'value' => 0]],
            ['st_my_name', ['type' => 'string', 'value' => $player->name]],
            ['st_my_text', ['type' => 'string', 'value' => '']],
        ]);
        return $runtime;
    }

    private function startFqScript(object $session, object $player, string $relative, string $place = 'Script'): string
    {
        $this->room(109, $place);
        $runtime = $this->beginScript($session, $player, $relative);
        if (!$runtime) {
            return $this->main($session, $player);
        }
        $this->persistVm($session);
        $this->store->save();
        $active = $this->runs[$session->id] ?? $runtime;
        return $this->packetToScene($active->packetOf()) ?? $this->main($session, $player);
    }

    private function resumeFqScript(object $session, object $player, int $result): string
    {
        $runtime = $this->runs[$session->id] ?? null;
        if (!$runtime || !$runtime->waiting()) {
            return $this->main($session, $player);
        }
        $runtime->resume($result);
        $this->persistVm($session);
        $this->store->save();
        $active = $this->runs[$session->id] ?? $runtime;
        return $this->packetToScene($active->packetOf()) ?? $this->main($session, $player);
    }

    private function packetToScene(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }
        $fields = [];
        foreach (explode('&', $body) as $part) {
            if ($part === '') {
                continue;
            }
            $eq = strpos($part, '=');
            $key = $eq === false ? $part : substr($part, 0, $eq);
            $value = $eq === false ? '' : substr($part, $eq + 1);
            if ($key === 'st_url' || $key === 'st_url_bg') {
                $value = str_replace('\\', '/', $value);
            }
            $fields[] = [$key, $value];
        }
        return $this->scene($fields);
    }

    private function finishPacket(string $body, string $mode): string
    {
        $creates = str_contains($body, 'st_object=');
        if ($mode === 'overlay' && $creates) {
            $body .= 'i_return_command=109&';
        }
        $sent = self::SENT['generic'];
        if ($creates) {
            $sent = $mode === 'overlay' ? self::SENT['add'] : self::SENT['object'];
        } elseif ($mode === 'stats') {
            $sent = 3;
        }
        return $body . 'i_sent=' . $sent . '&';
    }

    private function buttonKey(int $number): string
    {
        return [1 => '3', 2 => '2', 3 => '1', 4 => '0', 5 => 'a'][$number] ?? '3';
    }

    private function centerButton(string $label): string
    {
        if (strlen($label) >= 10) {
            return $label;
        }
        return str_repeat(' ', intdiv(15 - strlen($label), 2)) . $label;
    }

    private function addPacketButton(string $packet, int $number, string $label, int $command, ?int $sub): string
    {
        $key = $this->buttonKey($number);
        $next = $packet . "st_option_$key=" . $this->centerButton($label) . "&i_command_$key=$command&";
        if ($sub !== null) {
            $next .= "i_command_sub_$key=$sub&";
        }
        return $next;
    }

    private function hostText(array $args, int $index): string
    {
        return (string) ($args[$index]['value'] ?? '');
    }

    private function hostNum(array $args, int $index): int
    {
        return (int) ($args[$index]['value'] ?? 0);
    }

    private function customValue(?object $person, int $index): int
    {
        $fields = ['smoke', 'safe', 'padlock', 'lockpicks', 'padlockStrength'];
        $field = $fields[$index] ?? null;
        if (!$person || !$field) {
            return 0;
        }
        if (in_array($field, ['smoke', 'safe', 'padlock'], true)) {
            return $this->num($person, $field) ? 1 : 0;
        }
        return (int) $this->num($person, $field);
    }

    private function setCustom(?object $person, int $index, int $value): void
    {
        $fields = ['smoke', 'safe', 'padlock', 'lockpicks', 'padlockStrength'];
        $field = $fields[$index] ?? null;
        if (!$person || !$field) {
            return;
        }
        if (in_array($field, ['smoke', 'safe', 'padlock'], true)) {
            $person->{$field} = $value ? 1 : 0;
        } else {
            $person->{$field} = $value;
        }
    }

    private function hostCall(object $session, string $key, array $args, FqRuntime $api, int $playerIndex): array
    {
        $text = fn (int $i) => $this->hostText($args, $i);
        $num = fn (int $i) => $this->hostNum($args, $i);
        $person = fn () => $this->playerAt($playerIndex);
        if ($key === 'ADDCUSTOM') {
            return ['type' => 'string', 'value' => $text(0) . $text(1) . '=' . $text(2) . '&'];
        }
        if ($key === 'ADDBUTTON') {
            return ['type' => 'string', 'value' => $this->addPacketButton($text(0), $num(1), $text(2), 109, $num(3))];
        }
        if ($key === 'ADDBUTTONTOWNMAP') {
            return ['type' => 'string', 'value' => $this->addPacketButton($text(0), $num(1), $text(2), 12, null)];
        }
        if ($key === 'ADDBUTTONHOME') {
            return ['type' => 'string', 'value' => $this->addPacketButton($text(0), $num(1), $text(2), 4, null)];
        }
        if ($key === 'ADDBUTTONSTORE') {
            return ['type' => 'string', 'value' => $this->addPacketButton($text(0), $num(1), $text(2), 40, null)];
        }
        if ($key === 'ADDBUTTONCHANGENAME') {
            return ['type' => 'string', 'value' => $this->addPacketButton($text(0), $num(1), $text(2), 43, null)];
        }
        if ($key === 'ADDUPDATETAGS') {
            $owner = $person();
            $tags = '';
            foreach (['wood', 'silver', 'gold'] as $field) {
                $tags .= "i_coffin_$field=" . fq_cash($owner ? (int) $this->num($owner, $field) : 0) . '&';
            }
            return ['type' => 'string', 'value' => $text(0) . $tags];
        }
        if ($key === 'ADDUPDATESTATS') {
            $owner = $person();
            return ['type' => 'string', 'value' => $owner ? $text(0) . $this->statPacket($owner) : $text(0)];
        }
        if ($key === 'SETLOCATION') {
            $session->location = $text(0);
            return ['type' => 'int', 'value' => 0];
        }
        if ($key === 'SENDPACKETANDWAIT') {
            return ['wait' => true, 'packet' => $this->finishPacket($text(0), 'wait')];
        }
        if ($key === 'SENDPACKETANDWAITOVERLAY') {
            return ['wait' => true, 'packet' => $this->finishPacket($text(0), 'overlay')];
        }
        if ($key === 'SENDPACKETANDWAITSTATS') {
            return ['wait' => true, 'packet' => $this->finishPacket($text(0), 'stats')];
        }
        if ($key === 'STRINGEXPAND') {
            return ['type' => 'string', 'value' => $text(0)];
        }
        if ($key === 'LOGMSG' || $key === 'LOGERROR') {
            $key === 'LOGMSG' ? $this->logMsg($text(0)) : $this->system->error($text(0));
            return ['type' => 'int', 'value' => 0];
        }
        if ($key === 'RANDOMRANGE') {
            return ['type' => 'int', 'value' => $this->between($num(0), $num(1))];
        }
        if ($key === 'RANDOM') {
            return ['type' => 'int', 'value' => $this->between(0, $num(0))];
        }
        if ($key === 'GETTAGS' || $key === 'SETTAGS' || $key === 'MODTAGS') {
            $owner = $this->playerAt($num(0));
            $field = ['wood', 'silver', 'gold'][$num(1)] ?? null;
            if ($owner && $field && $key !== 'GETTAGS') {
                $owner->{$field} = $key === 'SETTAGS' ? $num(2) : (int) $this->num($owner, $field) + $num(2);
            }
            return ['type' => 'int', 'value' => $owner && $field ? (int) $this->num($owner, $field) : 0];
        }
        if ($key === 'GETCUSTOMBYID') {
            return ['type' => 'int', 'value' => $this->customValue($this->playerAt($num(0)), $num(1))];
        }
        if ($key === 'SETCUSTOMBYID' || $key === 'MODCUSTOMBYID') {
            $owner = $this->playerAt($num(0));
            $current = $this->customValue($owner, $num(1));
            $this->setCustom($owner, $num(1), $key === 'SETCUSTOMBYID' ? $num(2) : $current + $num(2));
            return ['type' => 'int', 'value' => $this->customValue($owner, $num(1))];
        }
        if ($key === 'ISPLAYERONNOW') {
            $owner = $this->playerAt($num(0));
            return ['type' => 'int', 'value' => $owner && $this->isOnline($owner, null) ? 1 : 0];
        }
        if ($key === 'GETTOURNEYDAYSLEFT') {
            return ['type' => 'int', 'value' => $this->tournamentDays > 0 ? $this->tournamentDays - (int) ($this->store->state->day ?? 1) : -2];
        }
        if ($key === 'GETNAMEFROMID') {
            return ['type' => 'string', 'value' => $this->playerAt($num(0))->name ?? ''];
        }
        if ($key === 'GETHOMENAMEFROMID') {
            return ['type' => 'string', 'value' => $this->playerAt($num(0))->funeralName ?? ''];
        }
        if ($key === 'GETTOTALBURIALS' || $key === 'MODTOTALBURIALS') {
            $owner = $this->playerAt($num(0));
            if ($key === 'MODTOTALBURIALS' && $owner) {
                $owner->plots = (int) $this->num($owner, 'plots') + $num(1);
            }
            return ['type' => 'int', 'value' => (int) ($owner ? $this->num($owner, 'plots') : 0)];
        }
        if ($key === 'ADDTONEWS') {
            $this->addNews($text(0));
            return ['type' => 'int', 'value' => 0];
        }
        if ($key === 'BROADCASTTV') {
            if (($this->store->state->tvOn ?? true) !== false) {
                $this->pushChat('`7The TV: `w' . $text(0));
            }
            return ['type' => 'int', 'value' => 0];
        }
        if ($key === 'MAILTEXT') {
            $owner = $this->playerAt($num(0));
            if ($owner) {
                $owner->inbox = $this->list($owner, 'inbox');
                $owner->inbox[] = (object) ['text' => $text(1), 'art' => ''];
            }
            return ['type' => 'int', 'value' => 0];
        }
        if ($key === 'MAILPICTURE') {
            $owner = $this->playerAt($num(0));
            $inbox = $owner ? $this->list($owner, 'inbox') : [];
            $note = $inbox ? $inbox[count($inbox) - 1] : null;
            if ($note && empty($note->letter)) {
                $note->art = str_replace('\\', '/', $text(1));
            }
            return ['type' => 'int', 'value' => 0];
        }
        if ($key === 'MAILCONTINUEBUTTON') {
            return ['type' => 'int', 'value' => 0];
        }
        if ($key === 'RUNSCRIPTNORETURN') {
            $api->spawnChild($text(0));
            return ['type' => 'int', 'value' => 0];
        }
        if ($key === 'ADDUPDATEBPS') {
            $owner = $person();
            return ['type' => 'string', 'value' => $owner
                ? $text(0) . 'i_exp=' . fq_cash((int) $this->num($owner, 'exp')) . '&i_sp=' . fq_cash((int) $this->num($owner, 'buryPoints')) . '&'
                : $text(0)];
        }
        if ($key === 'ADDUPDATELUCK') {
            $owner = $person();
            return ['type' => 'string', 'value' => $owner ? $text(0) . 'i_luck=' . (int) $this->num($owner, 'luck', 50) . '&' : $text(0)];
        }
        if ($key === 'ADDUPDATETURNS') {
            $owner = $person();
            return ['type' => 'string', 'value' => $owner ? $text(0) . 'i_turns=' . (int) $this->num($owner, 'turns') . '&' : $text(0)];
        }
        if ($key === 'GETTRANSPORTATIONFROMID') {
            $owner = $this->playerAt($num(0));
            return ['type' => 'string', 'value' => $owner ? fq_ride($owner)['name'] : ''];
        }
        $counters = [
            'TOTALBPS' => ['exp', 0], 'LUCK' => ['luck', 50], 'TURNS' => ['turns', 0], 'STRENGTH' => ['strength', 3],
            'MAXHP' => ['hpMax', 10], 'PSYCHUPGRADES' => ['psych', 0], 'PLAYERFIGHTS' => ['visits', 0], 'TODAYSBPS' => ['buryPoints', 0],
        ];
        $verb = substr($key, 0, 3);
        $stat = $counters[substr($key, 3)] ?? null;
        if ($stat && ($verb === 'GET' || $verb === 'MOD')) {
            $owner = $this->playerAt($num(0));
            if (!$owner) {
                return ['type' => 'int', 'value' => 0];
            }
            [$field, $default] = $stat;
            if ($verb === 'MOD') {
                if ($field === 'luck') {
                    $this->applyLuck($owner, $num(1));
                } elseif ($field === 'buryPoints') {
                    $this->applyExp($owner, $num(1));
                } else {
                    $owner->{$field} = max(0, (int) $this->num($owner, $field, $default) + $num(1));
                }
            }
            return ['type' => 'int', 'value' => (int) $this->num($owner, $field, $default)];
        }
        if ($key === 'GETPOLICE' || $key === 'SETPOLICE') {
            $owner = $this->playerAt($num(0));
            if ($owner && $key === 'SETPOLICE') {
                $owner->police = $num(1) !== 0;
            }
            return ['type' => 'int', 'value' => $owner && !empty($owner->police) ? 1 : 0];
        }
        if ($key === 'GETRANKPERCENT') {
            $owner = $this->playerAt($num(0));
            return ['type' => 'int', 'value' => $owner ? $this->rankOf($owner)['percent'] : 0];
        }
        if ($key === 'GETTOPPLAYERID') {
            $top = $this->highestExperience();
            return ['type' => 'int', 'value' => $top ? $this->playerIndex($top) : -1];
        }
        if ($key === 'GETRANDOMPLAYERID') {
            return ['type' => 'int', 'value' => $this->randomPlayerExcluding($num(0))];
        }
        if ($key === 'GETDAY') {
            return ['type' => 'int', 'value' => (int) ($this->store->state->day ?? 1)];
        }
        if ($key === 'FRANDOMRANGE') {
            $low = (float) ($args[0]['value'] ?? 0);
            $high = (float) ($args[1]['value'] ?? 0);
            return ['type' => 'float', 'value' => (mt_rand() / mt_getrandmax()) * ($high - $low) + $low];
        }
        if ($key === 'GETSTRINGPIECE') {
            return ['type' => 'string', 'value' => $this->stringPiece($text(0), $text(1), $num(2))];
        }
        return ['type' => 'int', 'value' => 0];
    }

    private function randomPlayerExcluding(int $exclude): int
    {
        $choices = [];
        foreach ($this->store->state->players as $index => $entry) {
            if (($entry->active ?? true) !== false) {
                $choices[] = $index;
            }
        }
        if (count($choices) < ($exclude > -1 ? 2 : 1)) {
            return -1;
        }
        $choices = array_values(array_filter($choices, fn ($index) => $index !== $exclude));
        return $choices ? $choices[array_rand($choices)] : -1;
    }

    private function stringPiece(string $source, string $separator, int $piece): string
    {
        $mark = $separator === '' ? "\0" : $separator[0];
        $source .= "\0";
        $part = 1;
        $out = '';
        $length = strlen($source);
        for ($at = 0; $at < $length; $at++) {
            $char = $source[$at];
            if ($char === $mark) {
                $part++;
                if ($part === $piece + 1) {
                    break;
                }
                if ($at < $length - 1) {
                    $out = '';
                }
            }
            if ($char !== $mark && $char !== "\0") {
                $out .= $char;
            }
        }
        return $part < $piece ? '' : str_replace("\n", '', $out);
    }

    private function runSinglePass(string $relative, ?int $playerIndex = null): void
    {
        $file = $this->scriptFile($relative);
        if (!$file) {
            return;
        }
        $this->ensureGlobals();
        $session = (object) ['id' => '', 'playerId' => null];
        $runtime = new FqRuntime(
            $this->scriptGlobals,
            fn (string $name, array $args, FqRuntime $api) => $this->hostCall($session, $name, $args, $api, $playerIndex ?? -1),
            fn () => null
        );
        $runtime->start($this->readScript($file), basename($file), $playerIndex === null ? [] : [
            ['i_my_id', ['type' => 'int', 'value' => $playerIndex]],
        ]);
        $this->store->state->scriptGlobals = json_decode(json_encode($this->scriptGlobals));
    }

    private function reloadGlobals(): void
    {
        $this->scriptGlobals = [];
        $this->globalsReady = false;
        unset($this->store->state->scriptGlobals);
        $this->ensureGlobals();
    }

    private function list(object $obj, string $key): array
    {
        $value = $this->val($obj, $key, []);
        if (is_object($value)) {
            $value = array_values((array) $value);
        }
        if (!is_array($value)) {
            $value = [];
        }
        $obj->{$key} = $value;
        return $value;
    }

    private function playerAt(int $index): ?object
    {
        $entry = $this->store->state->players[$index] ?? null;
        if (!$entry || ($entry->active ?? true) === false) {
            return null;
        }
        return $entry;
    }

    private function playerIndex(object $person): int
    {
        foreach ($this->store->state->players as $index => $entry) {
            if ((string) $entry->id === (string) $person->id) {
                return $index;
            }
        }
        return -1;
    }

    private function isOnline(object $person, ?string $except): bool
    {
        foreach ($this->store->state->sessions as $id => $entry) {
            if ($id !== $except && (string) $entry->playerId === (string) $person->id) {
                return true;
            }
        }
        return false;
    }

    private function addNews(string $text): void
    {
        $news = $this->list($this->store->state, 'news');
        $news[] = $text;
        $this->store->state->news = $news;
    }

    private function postMail(object $person, string $text, ?string $picture = null, ?string $background = null, ?object $grant = null): void
    {
        $inbox = $this->list($person, 'inbox');
        $note = (object) ['text' => $text, 'art' => $picture];
        if ($background !== null && $background !== '') {
            $note->background = $background;
        }
        if ($grant) {
            $note->grant = $grant;
        }
        $inbox[] = $note;
        $person->inbox = $inbox;
    }

    private function crimeNews(string $text): void
    {
        $crime = $this->list($this->store->state, 'crime');
        $crime[] = $text;
        $this->store->state->crime = $crime;
    }

    private function tv(string $text): void
    {
        if (($this->store->state->tvOn ?? true) === false) {
            $this->logMsg('`7The TV: `w' . $text);
            return;
        }
        $this->pushChat('`7The TV: `w' . $text);
    }

    private function pushChat(string $text): void
    {
        $this->logMsg($text);
        foreach ($this->store->state->sessions as $entry) {
            if (empty($entry->chatting) || $entry->playerId === null) {
                continue;
            }
            $log = $this->list($entry, 'chatLog');
            $log[] = $text;
            $entry->chatLog = $log;
        }
    }

    private function pushChatTo(int|string $playerId, string $text): void
    {
        $this->logMsg($text);
        foreach ($this->store->state->sessions as $entry) {
            if ((string) $entry->playerId !== (string) $playerId) {
                continue;
            }
            $log = $this->list($entry, 'chatLog');
            $log[] = $text;
            $entry->chatLog = $log;
        }
    }
}
