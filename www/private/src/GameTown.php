<?php
declare(strict_types=1);

trait FqTown
{
    private function incomes(): array
    {
        return ['Poor', 'Average', 'Rich', 'Super Rich'];
    }

    private function budgets(): array
    {
        return ['Poor' => [801, 2800], 'Average' => [1900, 5400], 'Rich' => [3700, 7530], 'Super Rich' => [4600, 18500]];
    }

    private function peopleArt(): array
    {
        return [
            'male' => [
                'teen' => ['flash/people/m_teen.swf', 'flash/people/m_teen_2.swf'],
                'adult' => ['flash/people/m_adult.swf', 'flash/people/m_adult_2.swf', 'flash/people/m_adult_3.swf', 'flash/people/m_adult_4.swf'],
                'old' => ['flash/people/m_old.swf', 'flash/people/m_old_2.swf', 'flash/people/m_old_5.swf', 'flash/people/m_old_6.swf'],
            ],
            'female' => [
                'teen' => ['flash/people/f_teen.swf', 'flash/people/f_teen_2.swf'],
                'adult' => ['flash/people/f_adult.swf', 'flash/people/f_adult_2.swf'],
                'old' => ['flash/people/f_old.swf', 'flash/people/f_old_2.swf'],
            ],
        ];
    }

    private function ageRange(int $age): int
    {
        if ($age >= 60) {
            return 3;
        }
        if ($age >= 28) {
            return 2;
        }
        if ($age >= 15) {
            return 1;
        }
        return 0;
    }

    private function randomAge(int $range): int
    {
        return match ($range) {
            0 => $this->between(7, 11),
            1 => $this->between(15, 19),
            2 => $this->between(28, 40),
            default => $this->between(60, 99),
        };
    }

    private function ageBand(int $age): string
    {
        return ['kid', 'teen', 'adult', 'old'][$this->ageRange($age)];
    }

    private function ageLabel(int $age): string
    {
        return match ($this->ageBand($age)) {
            'kid' => 'little',
            'teen' => 'teenage',
            'adult' => 'middle aged',
            default => 'old',
        };
    }

    private function chooseRelation(int $customerAge, int $deceasedAge, bool $customerMale, bool $deceasedMale): int
    {
        $customer = $this->ageRange($customerAge);
        $deceased = $this->ageRange($deceasedAge);
        $different = $customerMale !== $deceasedMale;
        if ($customer === 0) {
            return match ($deceased) {
                0, 1 => 1,
                2 => 2,
                default => 6,
            };
        }
        if ($customer === 1) {
            if ($deceased === 0) {
                return 1;
            }
            if ($deceased === 1) {
                return random_int(0, 2) === 1 && $different ? 7 : 1;
            }
            if ($deceased === 2) {
                return random_int(0, 2) === 1 && $different ? 7 : 2;
            }
            return 6;
        }
        if ($customer === 2) {
            if ($deceased === 0) {
                return 3;
            }
            if ($deceased === 1) {
                return random_int(0, 2) === 1 && $different ? 7 : 3;
            }
            if ($deceased === 2) {
                $roll = random_int(0, 5);
                return $roll > 3 && $different ? 4 : 1;
            }
            $roll = random_int(0, 5);
            if ($roll === 1 && $different) {
                return 7;
            }
            if ($roll === 2 && $different) {
                return 4;
            }
            return 2;
        }
        if ($deceased === 0) {
            return 8;
        }
        if ($deceased === 1) {
            return random_int(0, 9) === 1 && $different ? 7 : 8;
        }
        if ($deceased === 2) {
            $roll = random_int(0, 9);
            if ($roll === 1 && $different) {
                return 7;
            }
            if ($roll === 2 && $different) {
                return 4;
            }
            return 3;
        }
        $roll = random_int(0, 5);
        if ($roll === 1 && $different) {
            return 7;
        }
        if ($roll === 2) {
            return 1;
        }
        return $different ? 4 : 1;
    }

    private function relationWord(int $relation, bool $deceasedMale, int $customerAge): string
    {
        $young = $this->ageRange($customerAge) <= 1;
        if ($deceasedMale) {
            return match ($relation) {
                1 => 'brother',
                2 => $young ? 'dad' : 'father',
                3 => 'son',
                4 => 'husband',
                6 => 'grandfather',
                7 => 'boyfriend',
                8 => 'grandson',
                default => 'friend',
            };
        }
        return match ($relation) {
            1 => 'sister',
            2 => $young ? 'mom' : 'mother',
            3 => 'daughter',
            4 => 'wife',
            6 => 'grandmother',
            7 => 'girlfriend',
            8 => 'granddaughter',
            default => 'friend',
        };
    }

    private function frandom(float $max): float
    {
        return (mt_rand() / mt_getrandmax()) * $max;
    }

    private function skillCheck(int $luck, float $weight): bool
    {
        $roll = random_int(0, 99);
        $adjusted = $luck + (int) ((50 - $luck) * (1 - $weight));
        return $roll <= $adjusted;
    }

    private function he(object $person): string
    {
        return ($person->male ?? true) === false ? 'she' : 'he';
    }

    private function capHe(object $person): string
    {
        return ($person->male ?? true) === false ? 'She' : 'He';
    }

    private function his(object $person): string
    {
        return ($person->male ?? true) === false ? 'her' : 'his';
    }

    private function him(object $person): string
    {
        return ($person->male ?? true) === false ? 'her' : 'him';
    }

    private function moodName(bool $loved, float $value): string
    {
        $names = $loved
            ? ['In Shock', 'Dazed', 'Confused', 'Crying', 'Numb', 'Disturbed', 'Frustrated', 'Irked', 'Angry', 'Pissed']
            : ['Chipper', 'In a hurry', 'Natural', 'Tight Lipped', 'Nervous', 'Frustrated', 'Annoyed', 'Irked', 'Angry', 'Pissed'];
        if ($value <= 1) {
            return $names[0];
        }
        return $names[min(count($names) - 1, (int) ceil($value) - 1)];
    }

    private function customerNoun(object $customer): string
    {
        $band = $this->ageBand((int) $customer->age);
        $young = $band === 'kid' || $band === 'teen';
        if ($customer->male) {
            return $young ? 'boy' : 'man';
        }
        return $young ? 'girl' : 'woman';
    }

    private function customerArticle(object $customer): string
    {
        $prefix = match ($this->ageBand((int) $customer->age)) {
            'kid' => 'A little',
            'teen' => 'A teenage',
            'adult' => 'A middle aged',
            default => 'An old',
        };
        return $prefix . ' ' . $this->customerNoun($customer);
    }

    private function customerTitle(object $customer): string
    {
        $title = $this->ageLabel((int) $customer->age) . ' ' . $this->customerNoun($customer);
        return ucfirst($title);
    }

    private function casketName(string $id): string
    {
        return ['wood' => 'Wood', 'silver' => 'Silver', 'gold' => 'Gold'][$id] ?? $id;
    }

    private function optionPrice(mixed $option): int
    {
        if (is_object($option)) {
            return (int) ($option->price ?? 0);
        }
        return (int) ($option['price'] ?? 0);
    }

    private function priceOf(object $customer): int
    {
        $price = self::CASKETS[(int) $customer->casketIndex]['price'];
        foreach ($customer->options ?? [] as $option) {
            $price += $this->optionPrice($option);
        }
        for ($index = 0; $index <= (int) $customer->upgrade; $index++) {
            $price += (int) ($this->upgrades[$index]['price'] ?? 0);
        }
        return $price;
    }

    private function readCustomer(object $player, object $customer): void
    {
        $psych = (int) $this->num($player, 'psych');
        $he = $customer->male ? 'he' : 'she';
        $He = $customer->male ? 'He' : 'She';
        $his = $customer->male ? 'his' : 'her';
        $him = $customer->male ? 'him' : 'her';
        $notes = [];
        if ($this->skillCheck(20 + $psych * 13, 0.98)) {
            $customer->showResistance = true;
            $notes[] = "`wYou successfully analyze $his resistance.`\$";
        }
        if ($psych > 0 && $this->skillCheck($psych * 14, 0.98)) {
            $notes[] = $customer->loved ? "`w$He seems heartbroken.`\$" : "`w$He seems guilty.`\$";
        }
        if ($psych > 4 && $this->skillCheck($psych * 15 - 65, 0.98)) {
            $customer->budget *= 2;
            $notes[] = '`wSpending limit doubled.`$';
        }
        if ($psych > 1 && $this->skillCheck($psych * 15 - 25, 0.98)) {
            $notes[] = "`wYou estimate $he is worth about $" . fq_cash($customer->budget) . '.`$';
        }
        if ($psych > 2 && $this->skillCheck($psych * 15 - 35, 0.98)) {
            $customer->cheapness *= 0.5;
            $notes[] = '`wResistance cut in half.`$';
        }
        if ($psych > 3 && $this->skillCheck($psych * 15 - 50, 0.98)) {
            $customer->moodValue *= 0.5;
            $notes[] = '`wMood cut in half.`$';
        }
        $customer->insight = $notes
            ? "\n\n" . implode(' ', $notes)
            : "\n\n`\$You are unable to analyze anything extra about $him.";
    }

    private function makeCustomer(object $player): object
    {
        $male = mt_rand(0, 1) === 0;
        $age = $this->randomAge(1 + random_int(0, 2));
        $deceasedMale = mt_rand(0, 1) === 0;
        $deceasedAge = $this->randomAge(random_int(0, 3));
        $income = $this->pick($this->incomes());
        $range = $this->budgets()[$income];
        $budget = $this->between($range[0], $range[1]);
        $casketIndex = 0;
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $candidate = random_int(0, count(self::CASKETS) - 1);
            if (self::CASKETS[$candidate]['price'] <= $budget) {
                $casketIndex = $candidate;
                break;
            }
        }
        $band = $this->ageBand($age);
        $art = $this->peopleArt();
        $names = $male
            ? ['Arthur', 'Walter', 'Edgar', 'Frank', 'Harold', 'George', 'Henry', 'Albert']
            : ['Helen', 'Ruth', 'Mabel', 'Clara', 'Edna', 'Margaret', 'Dorothy', 'Evelyn'];
        $customer = (object) [
            'name' => $this->pick($names),
            'male' => $male,
            'age' => $age,
            'deceasedAge' => $deceasedAge,
            'loved' => mt_rand(0, 1) === 1,
            'deceasedMale' => $deceasedMale,
            'relation' => $this->relationWord($this->chooseRelation($age, $deceasedAge, $male, $deceasedMale), $deceasedMale, $age),
            'deathObject' => $this->pick(['car', 'tractor', 'fridge', 'piano', 'bull', 'elephant', 'truck', 'fork-lift', 'exercise machine', 'power boat', 'computer', 'house']),
            'deathMethod' => $this->pick(['hit by', 'smashed by', 'run over by', 'thrown by', 'crushed by', 'done in by', 'fatally stuck inside', 'smacked a good one by']),
            'casketIndex' => $casketIndex,
            'casket' => (object) self::CASKETS[$casketIndex],
            'upgrade' => -1,
            'options' => [],
            'income' => $income,
            'moodValue' => $this->frandom(10),
            'cheapness' => $this->frandom(10),
            'showResistance' => false,
            'budget' => $budget,
            'bill' => self::CASKETS[$casketIndex]['price'],
            'portrait' => $this->pick($art[$male ? 'male' : 'female'][$band]),
            'background' => 'flash/background/background_' . $this->between(1, 4) . '.swf',
        ];
        $this->readCustomer($player, $customer);
        return $customer;
    }

    private function customerText(object $customer): string
    {
        $title = $this->customerTitle($customer);
        $casket = $this->casketName($customer->casket->id);
        $resistance = $customer->showResistance
            ? ' `5(Resistance: `#' . (int) round($customer->cheapness * 10) . '%25`5)'
            : '';
        $who = $customer->deceasedMale ? 'him' : 'her';
        return "`w$title: `\$\"Please, I need a funeral service for my {$customer->relation}. I would need a plot and a $casket casket for $who.\"{$customer->insight}\n\nTotal: $" . fq_cash($customer->bill) . $resistance;
    }

    private function saleButtons(): array
    {
        return [
            ['st_option_3', '     Done'], ['i_command_3', 30],
            ['st_option_2', '  Soft Sell'], ['i_command_2', 25],
            ['st_option_1', '  Hard Sell'], ['i_command_1', 26],
            ['st_option_0', '   Sympathy'], ['i_command_0', 27],
            ['st_option_a', '     Guilt'], ['i_command_a', 29],
        ];
    }

    private function showCustomer(object $player, object $customer): string
    {
        return $this->scene([
            ...$this->stats($player),
            ['st_url', $this->art($customer->portrait)],
            ['st_url_bg', $this->art($customer->background)],
            ['st_mood', $this->moodName((bool) $customer->loved, (float) $customer->moodValue)],
            ['st_class', $customer->income],
            ['st_main', $this->customerText($customer)],
            ...$this->saleButtons(),
            ['b_kill_buttons', 1],
            ['b_show_stats', 1],
            ['i_sent', self::SENT['loadMovie']],
        ]);
    }

    private function totalSuffix(object $customer): string
    {
        $customer->bill = $this->priceOf($customer);
        $resistance = $customer->showResistance
            ? ' `5(Resistance: `#' . (int) round($customer->cheapness * 10) . '%25`5)'
            : '';
        return "\n\nTotal: $" . fq_cash($customer->bill) . $resistance;
    }

    private function continueSale(object $player, object $customer, string $message): string
    {
        return $this->scene([
            ['st_main', $message . $this->totalSuffix($customer)],
            ['st_mood', $this->moodName((bool) $customer->loved, (float) $customer->moodValue)],
            ['st_option_3', '     Done'], ['i_command_3', 30],
            ['b_kill_buttons', 1],
            ['st_option_2', '  Soft Sell'], ['i_command_2', 25],
            ['st_option_1', '  Hard Sell'], ['i_command_1', 26],
            ['st_option_0', '   Sympathy'], ['i_command_0', 27],
            ['st_option_a', '     Guilt'], ['i_command_a', 29],
            ['b_show_stats', 1],
            ['i_sent', self::SENT['loadMovie']],
        ]);
    }

    private function walkOut(object $player, object $customer, string $lead = '', ?array $lines = null): string
    {
        $title = $this->customerTitle($customer);
        $replies = $lines ?? [
            '"You know what? I really don\'t like the way you work. Bye."',
            '"Enough is enough.  I\'m sick of you."',
            '"I just realized that you disgust me.  Sayoonara."',
            '"Can we cut the crap for a second here?  I guess not, bye."',
        ];
        $him = $customer->male ? 'him' : 'her';
        $he = $customer->male ? 'he' : 'she';
        $message = $lead . "`w$title: `\$" . $this->pick($replies) . "\n";
        $others = array_values(array_filter(
            $this->store->state->players,
            fn ($entry) => ($entry->active ?? true) !== false && (string) $entry->id !== (string) $player->id
        ));
        if ($others) {
            $other = $this->pick($others);
            $message .= "\n`\$You watch $him through the window after $he leaves.  You see $him go into {$other->funeralName} to see {$other->name}!";
            $stolen = 5 + random_int(0, 4);
            $article = $this->customerArticle($customer);
            $sir = ($other->male ?? true) === false ? "ma'am" : 'sir';
            $this->postMail($other, "\n`\$" . $article . " walks in with a dead body. \"Hello $sir, I would like you to know I got treated like crap by {$player->name} of {$player->funeralName}. I know you'll do a better job!\"\n\nYou earn `w$stolen`\$ Bury Points for stealing this sale!\n", $customer->portrait, 'flash/background/background_2.swf', (object) ['exp' => $stolen]);
        }
        $this->tv('"A ' . $customer->income . ' ' . $this->ageLabel((int) $customer->age) . ' ' . $this->customerNoun($customer) . ' just left ' . $player->funeralName . ' looking pissed," a reporter says.');
        return $this->scene([
            ...$this->stats($player),
            ['st_url', $this->art('flash/stuff/money_bag.swf')],
            ['st_main', $message],
            ['st_option_3', ' Continue'],
            ['i_command_3', 4],
            ['b_kill_buttons', 1],
            ['b_show_stats', 0],
            ['i_sent', self::SENT['loadMovie']],
        ]);
    }

    private function saleInProgress(object $session): bool
    {
        if (!is_object($session->customer ?? null)) {
            return false;
        }
        if (property_exists($session, 'risk') && $session->risk !== null && $session->risk !== '') {
            return false;
        }
        if (!empty($session->vm)) {
            return false;
        }
        return true;
    }

    private function settleAbandonedSale(object $session): void
    {
        if (!$this->saleInProgress($session)) {
            return;
        }
        $player = $this->store->playerById($session->playerId ?? null);
        if (!$player) {
            return;
        }
        $this->finishSale($player, $session->customer, true);
        $session->customer = null;
        $session->risk = null;
    }

    private function finishSale(object $player, object $customer, bool $crashed = false): string
    {
        $customer->bill = $this->priceOf($customer);
        $bonus = $this->rule('trade_bonus');
        $extra = '';
        $points = (int) ($customer->bill * 0.0064);
        $who = $customer->male ? 'he' : 'she';
        if ((int) $customer->bill === (int) $customer->budget) {
            $points += 200;
            $extra = "`wEXCELLENT! `\$For making this funeral cost the max $who could pay you receive a `w200`\$ BP bonus!\n\n";
            $this->addNews("`7{$player->name} makes a perfect sale, the industry is in awe.\n\n");
        } elseif (abs((int) $customer->bill - (int) $customer->budget) <= 50) {
            $points += $bonus + 10;
            $extra = "`wGREAT! `\$For getting within 50 of this customers limit you receive a `w" . ($bonus + 10) . "`\$ BP bonus!\n\n";
        } elseif (abs((int) $customer->bill - (int) $customer->budget) <= 100) {
            $points += $bonus;
            $extra = "`wNICE! `\$For getting within 100 of this customers limit you receive a `w$bonus`\$ BP bonus!\n\n";
        }
        $player->buryPoints = (int) $this->num($player, 'buryPoints') + $points;
        $player->exp = (int) $this->num($player, 'exp') + $points;
        $id = $customer->casket->id;
        $player->{$id} = (int) $this->num($player, $id) + 1;
        $player->plots = (int) $this->num($player, 'plots') + 1;
        $tag = $this->casketName($id);
        $income = (string) ($customer->income ?? 'Error');
        $this->logMsg($crashed
            ? "{$player->name} crashed, but we're giving him $points BP's off a $income guy"
            : "{$player->name} earned $points BP's off a $income guy");
        if ($crashed) {
            $this->tv('"' . $player->name . ' has fainted in ' . $this->his($player) . ' parlor.  Working too hard maybe?" a guy reports.');
        }
        if ($points > 69) {
            $this->tv("\"It appears {$player->name} just made a $points BP sale,\" a guy reports.");
        }
        if ($crashed) {
            $this->postMail($player, "\n`wYou fainted while dealing with a customer.`\$\n\nYou vaguely remember riding in an ambulance.\n\n`\$When you get home you find `w" . fq_cash($points) . " `\$Bury Points and a $tag tag on the desk!", 'flash/places/hospital_ambulance.swf');
            return '';
        }
        return $this->scene([
            ...$this->stats($player),
            ['st_url', $this->art('flash/stuff/money_bag_2.swf')],
            ['st_main', "`w`\$The service is a success!  Total bill is $" . fq_cash($customer->bill) . ".\n\n{$extra}You earn `w$points`\$ Bury Points and a $tag sales tag."],
            ['st_option_3', ' Continue'],
            ['i_command_3', 4],
            ['b_kill_buttons', 1],
            ['b_show_stats', 0],
            ['i_sent', self::SENT['loadMovie']],
        ]);
    }

    private function youngLine(object $customer): string
    {
        return '`w' . $this->customerTitle($customer) . ': `$';
    }

    private function limitLines(object $customer): array
    {
        $cash = fq_cash($customer->budget);
        return [
            '"That would put me over my limit.. I don\'t have time for this, bye."',
            "\"Damn, you see, I can only spend \$$cash.  I'm leaving.\"",
            "\"My visa only goes to \$$cash... I'll try next-door.\"",
        ];
    }

    private function softSell(object $session, object $player): string
    {
        $customer = $session->customer;
        $option = $this->pick($this->softOptions);
        $price = fq_cash($this->optionPrice($option));
        $seller = "`w{$player->name}: `\$";
        $young = $this->youngLine($customer);
        $article = is_object($option) ? $option->article : $option['article'];
        $name = is_object($option) ? $option->name : $option['name'];
        $pitch = $this->pick([
            $seller . "\"You know, your neighbor was in here recently.. he bought $article $name for only \$$price. Impeccable taste.\"\n\n",
            $seller . "\"I can tell by the fine clothes you're wearing that you appreciate the finer things. How about $article $name for \$$price?\"\n\n",
            $seller . "\"I don't mean to rush you, but this isn't really something you can wait a week thinking about. We do have $article $name for \$$price...\"\n\n",
            $seller . "\"You know..  I bet your {$customer->relation}'s social security death benefits would probably cover $article $name for \$$price?\"\n\n",
        ]);
        if ($this->frandom(10) < $customer->cheapness) {
            $customer->moodValue += 0.4 + $this->frandom(0.7);
            if ($customer->moodValue >= 10) {
                $session->customer = null;
                return $this->walkOut($player, $customer, $pitch);
            }
            $alive = $customer->deceasedMale ? 'he' : 'she';
            $refusal = $this->pick([
                $young . "\"I really don't think my {$customer->relation} needs that.\"",
                $young . "\"Actually, my {$customer->relation} wanted a very simple funeral.\"",
                $young . '"Um, no."',
                $customer->loved
                    ? $young . "\"When my {$customer->relation} was alive $alive asked me not to buy that.  I'm sorry.\""
                    : $young . "\"I'm not gonna pay another \$$price for an 'effing {$customer->relation} I barely knew!\"",
                $young . '"You\'ve got to be kidding."',
                $young . '"I\'m not saying I can\'t afford that, but I don\'t think I really need it."',
            ]);
            return $this->continueSale($player, $customer, $pitch . $refusal);
        }
        if ($this->priceOf($customer) + $this->optionPrice($option) <= $customer->budget) {
            $customer->options[] = $option;
            $mood = $customer->moodValue;
            if ($mood < 2) {
                $answer = $young . '"Thank you, I\'ll take it."';
            } elseif ($mood < 4) {
                $answer = $young . "\"You don't say? $name is something I want then.\"";
            } elseif ($mood < 7) {
                $answer = $young . '"Fine, I\'ll take it."';
            } elseif ($mood < 9) {
                $answer = $young . '"I think I\'m getting ripped off here but I\'ll take it."';
            } else {
                $answer = $young . '"WHATEVER!! Just give it to me I guess."';
            }
            return $this->continueSale($player, $customer, $pitch . $answer);
        }
        if ($customer->moodValue > 9) {
            $session->customer = null;
            return $this->walkOut($player, $customer, '', $this->limitLines($customer));
        }
        $customer->moodValue += 0.4 + $this->frandom(1);
        return $this->continueSale($player, $customer, $pitch . $young . '"That would put me over my limit.. how about something cheaper?"');
    }

    private function hardSell(object $session, object $player): string
    {
        $customer = $session->customer;
        if ((int) $customer->casketIndex >= 2) {
            return $this->sellUpgrade($session, $player);
        }
        $extra = self::CASKETS[$customer->casketIndex + 1]['price'] - self::CASKETS[$customer->casketIndex]['price'];
        $price = fq_cash($extra);
        $seller = "`w{$player->name}: `\$";
        $young = $this->youngLine($customer);
        $metal = (int) $customer->casketIndex === 1 ? 'gold' : 'silver';
        $pitch = (int) $customer->casketIndex === 0
            ? $this->pick([
                $seller . "\"Well, I suppose you COULD go with a wood casket.. except it really doesn't offer much protection from worms and the like.  How about a Silver Casket for only \$$price additional?\"\n\n",
                $seller . "\"Wouldn't your {$customer->relation} prefer our traditional Silver Casket for only \$$price more?\"\n\n",
            ])
            : $this->pick([
                $seller . "\"Just think of the beautiful memory picture you would have of your {$customer->relation} in a Gold Casket - a bargain for only \$$price more!\"\n\n",
                $seller . "\"A Gold Casket (only \$$price additional) reflects character and station in life.. its suberb styling reflects a successful living.\"\n\n",
            ]);
        if ($this->frandom(10) < $customer->cheapness || $customer->cheapness > 8) {
            $customer->moodValue += 0.6 + $this->frandom(1);
            if ($customer->moodValue >= 10) {
                $session->customer = null;
                return $this->walkOut($player, $customer, $pitch);
            }
            $refusal = $this->pick([
                $young . "\"I really don't think my {$customer->relation} needs that.\"",
                $young . "\"Actually, my {$customer->relation} wanted a very simple funeral.\"",
                $young . '"A casket is a casket, right?"',
                $customer->loved
                    ? $young . '"No way, for that much it should have AC and a stereo!"'
                    : $young . "\"My {$customer->relation} doesn't deserve $metal.\"",
                $young . '"I don\'t want to spend that much."',
                $young . '"I\'ve got the dough, but I would rather give to charity."',
            ]);
            return $this->continueSale($player, $customer, $pitch . $refusal);
        }
        if ($this->priceOf($customer) + $extra <= $customer->budget) {
            $customer->casketIndex++;
            $customer->casket = (object) self::CASKETS[$customer->casketIndex];
            $answer = $customer->moodValue < 7
                ? $this->pick([
                    $young . '"Alright, I\'ll take it."',
                    $young . '"Ok, I\'ll do the upgrade."',
                    $young . '"Well, since you put it that way, ok."',
                ])
                : $young . "\"Fine, I'll take the damn $metal casket, ok?\"";
            return $this->continueSale($player, $customer, $pitch . $answer);
        }
        if ($customer->moodValue > 9) {
            $session->customer = null;
            return $this->walkOut($player, $customer, $pitch, $this->limitLines($customer));
        }
        $customer->moodValue += 0.4 + $this->frandom(1);
        return $this->continueSale($player, $customer, $pitch . $young . '"That casket would put me over my limit.. how about something cheaper?"');
    }

    private function sellUpgrade(object $session, object $player): string
    {
        $customer = $session->customer;
        $next = (int) $customer->upgrade + 1;
        $young = $this->youngLine($customer);
        $seller = "`w{$player->name}: `\$";
        if ($next >= count($this->upgrades)) {
            return $this->continueSale($player, $customer, '`yThis ' . $this->customerNoun($customer) . ' has already bought everything big you can think of.  It\'s a great feeling.');
        }
        $item = $this->upgrades[$next];
        $last = (int) $customer->upgrade < 0 ? 'Gold Casket' : $this->upgrades[$customer->upgrade]['name'];
        $price = fq_cash($item['price']);
        $pitch = $this->pick([
            $seller . "\"A simple $last may be enough for some.  But tell me, don't the words '{$item['name']}' (only \$$price additional) give you goosebumps?\"\n\n",
            $seller . "\"I would feel guilty if I didn't mention we do offer a {$item['name']} for a small fee of \$$price.\"\n\n",
            $seller . "\"{$item['name']}'s are a great idea for an enternal slumber. A fee of \$$price will be charged, alright?\"\n\n",
        ]);
        if ($this->frandom(10) < $customer->cheapness || $customer->cheapness > 8) {
            $customer->moodValue += 0.7 + $this->frandom(1);
            if ($customer->moodValue >= 10) {
                $session->customer = null;
                return $this->walkOut($player, $customer, $pitch);
            }
            $refusal = $this->pick([
                $young . '"No."',
                $young . "\"Actually I want a simple funeral for my {$customer->relation}.\"",
                $young . '"I would rather spend money getting drunk after this."',
                $customer->loved
                    ? $young . '"God, can\'t you see this is hurting me talking about this now?"'
                    : $young . "\"In case you haven't noticed, I wasn't overly fond of my {$customer->relation}.  Forget it.\"",
                $young . "\"You just said \${$item['price']} right? I hope you're kidding.\"",
                $young . '"I\'ve got the dough, but I would rather spend it on something that matters."',
            ]);
            return $this->continueSale($player, $customer, $pitch . $refusal);
        }
        if ($this->priceOf($customer) + $item['price'] <= $customer->budget) {
            $customer->upgrade++;
            $answer = $customer->moodValue < 7
                ? $this->pick([$young . '"Alright, I\'ll take it."', $young . '"Ok."', $young . '"Maybe you\'re right.  Ok."'])
                : $young . '"Fine, whatever, ok?"';
            return $this->continueSale($player, $customer, $pitch . $answer);
        }
        if ($customer->moodValue > 9) {
            $session->customer = null;
            return $this->walkOut($player, $customer, $pitch);
        }
        $customer->moodValue += 0.4 + $this->frandom(1);
        return $this->continueSale($player, $customer, $pitch . $young . '"That would put me over my limit.. how about something cheaper?"');
    }

    private function guiltSell(object $session, object $player): string
    {
        $customer = $session->customer;
        $young = $this->youngLine($customer);
        $seller = "`w{$player->name}: `\$";
        if ($customer->moodValue >= 10) {
            $session->customer = null;
            return $this->walkOut($player, $customer, "`\$You are interrupted!\n\n");
        }
        $sir = $customer->male ? 'sir' : "ma'am";
        $Sir = $customer->male ? 'Sir' : "Ma'am";
        $pitch = $this->pick([
            $seller . "\"The funeral is not occasion for a display of cheapness, $sir.\"\n\n",
            $seller . "\"$Sir,  let me ask you one simple thing.  Did you hate " . ($customer->deceasedMale ? 'him' : 'her') . "?  Why else wouldn't you make this funeral something to be proud of?\"\n\n",
            $seller . "\"Some believe an expensive funeral absolves the purchaser from any sin.  I happen to believe this, my friend.\"\n\n",
            $seller . "\"I would stay away from cremation.  Your loved one deserves better than 'bake and shake' - people would talk.\"\n\n",
            $seller . "\"Resistance to a 'proper' funeral will send the wrong message, wouldn't you agree?\"\n\n",
        ]);
        $customer->moodValue += $this->frandom(1);
        if ($customer->loved) {
            $customer->moodValue += 1 + $this->frandom(2);
            $alive = $customer->deceasedMale ? 'him' : 'her';
            $answer = $this->pick([
                $young . "\"I LOVED $alive! Don't you get it? None of that stuff matters to me!\"",
                $young . "\"My {$customer->relation} was {$customer->deathMethod} a {$customer->deathObject} for god-sake! How can you say something like that?\"",
                $young . '"If you want to keep my business you\'re going to stop saying stuff like that."',
            ]);
        } else {
            $customer->cheapness = min(10, max(0, $customer->cheapness - (0.7 + $this->frandom(2))));
            $answer = $this->pick([
                $young . '"You\'re right. I should be a little looser with my money."',
                $young . "\"Good call.  I guess it would be appropriate to spend more on my {$customer->relation}.\"",
                $young . '"You\'ve got a point there.. It makes me mad but you\'re right."',
                $young . '"I agree in principle.. but I only want to spend $' . fq_cash($customer->budget) . ' on this."',
            ]);
        }
        return $this->continueSale($player, $customer, $pitch . $answer);
    }

    private function sympathySell(object $session, object $player): string
    {
        $customer = $session->customer;
        $young = $this->youngLine($customer);
        $seller = "`w{$player->name}: `\$";
        if ($customer->moodValue >= 10) {
            $session->customer = null;
            return $this->walkOut($player, $customer, "`\$You are interrupted!\n\n");
        }
        $pitch = $this->pick([
            $seller . '"' . ($customer->male ? 'Sir' : "Ma'am") . ", I understand you've been through a lot, please tell me what happened.\"\n\n",
            $seller . "\"I know how you feel.  I know someone who lost a {$customer->relation} once.\"\n\n",
            $seller . '"So you two were close?  ' . ($customer->deceasedMale ? 'He' : 'She') . " sounds very special.\"\n\n",
            $seller . "\"I'm not a licensed therapist.. but keep talking, get the pain out.\"\n\n",
            $seller . "\"Any time a {$customer->deathObject} is involved in an accident it's going to be traumatic.\"\n\n",
        ]);
        $customer->moodValue += 0.4 + $this->frandom(1);
        if (!$customer->loved) {
            $customer->moodValue += 1 + $this->frandom(2);
            $answer = $this->pick([
                $young . '"Sorry, I don\'t read ya, pard."',
                $young . '"I don\'t go for all this touchy feeling crap, ok?  Don\'t hug me."',
                $young . "\"Um yeah.. except I actually have another {$customer->relation}, so it ain't so bad.  No more wasting my time, ok?\"",
            ]);
        } else {
            $customer->cheapness = min(10, max(0, $customer->cheapness - (0.7 + $this->frandom(2))));
            $answer = $this->pick([
                $young . '"It\'s just.. I feel like ' . ($customer->deceasedMale ? 'he' : 'she') . ' is just in another room, you know?"',
                $young . '"I still have dreams.. of ' . ($customer->deceasedMale ? 'him' : 'her') . " getting {$customer->deathMethod} a {$customer->deathObject} over and over.\"",
                $young . "\"I mean, usually someone's {$customer->relation} is close.. but we were REALLY close if you know what I mean.\"",
                $young . '"I appreciate what you\'re trying to do but I need to get this over with..for $' . fq_cash($customer->budget) . ' or less.."',
            ]);
        }
        return $this->continueSale($player, $customer, $pitch . $answer);
    }

    private function span(int $low, int $high): int
    {
        return $this->between(min($low, $high), max($low, $high));
    }

    private function noTurns(object $player): string
    {
        $message = $this->pick([
            'You are out of turns for the day, come back tomorrow!',
            "It's closing time, you'll have to wait until tomorrow.",
            "You look at your watch - it's late.  Maybe tomorrow.",
        ]);
        return $this->scene([
            ['st_main', '`$'.$message],
            ['st_url', $this->art('flash/closed.swf')],
            ['i_sent', self::SENT['loadMovie']],
        ]);
    }

    private function specialEvent(object $player): ?string
    {
        $choices = array_values(array_filter(
            $this->storyEvents,
            fn ($entry) => (int) $this->num($player, 'exp') >= $entry['low'] && (int) $this->num($player, 'exp') <= $entry['high']
        ));
        $event = $this->pick($choices);
        if (!$event) {
            return null;
        }
        $points = $this->span((int) $event['bp'][0], (int) $event['bp'][1]);
        $luck = $this->span((int) $event['luck'][0], (int) $event['luck'][1]);
        $turns = $this->span((int) $event['turns'][0], (int) $event['turns'][1]);
        $this->applyExp($player, $points);
        $this->applyLuck($player, $luck);
        $this->applyTurns($player, $turns);
        $reward = $this->rewardText([[$points, 'Bury Point', 'Bury Points'], [$luck, 'luck', 'luck'], [$turns, 'turn', 'turns']]);
        return $this->movieScene($player, "`wSPECIAL EVENT\n\n`\$" . $event['message'] . $reward, $event['pic'], null, [], ['i_sp', 'i_exp', 'i_luck', 'i_turns']);
    }

    private function showRisk(object $player, array $risk): string
    {
        return $this->movieScene($player, $this->fillBlanks($risk['startMessage'], $player), $risk['startUrl'], $risk['startBg'], [
            ['key' => '3', 'label' => $risk['accept'], 'command' => 31],
            ['key' => '2', 'label' => $risk['risk'], 'command' => 11],
        ], ['i_turns']);
    }

    private function currentRisk(object $session): ?array
    {
        if (!property_exists($session, 'risk') || $session->risk === null || $session->risk === '') {
            return null;
        }
        return $this->risks[(int) $session->risk] ?? null;
    }

    private function missingRisk(object $player): string
    {
        return $this->scene([...$this->stats($player), ['g_st_error', "Huh?  Can't double with this customer."], ['i_sent', self::SENT['fail']]]);
    }

    private function acceptRisk(object $session, object $player): string
    {
        $risk = $this->currentRisk($session);
        if (!is_object($session->customer ?? null)) {
            return $this->missingCustomer($player);
        }
        if (!$risk) {
            return $this->missingRisk($player);
        }
        $points = $this->between(4, 10);
        $this->applyExp($player, $points);
        $earned = $points === 1 ? "`\$You earned one Bury Point, not bad.\n" : "`\$You earned `w$points`\$ Bury Points.\n";
        $session->customer = null;
        $session->risk = null;
        $this->logMsg($player->name . " didn't take a risk (" . (int) $this->num($player, 'luck') . " luck) earned $points bp.");
        return $this->movieScene($player, $this->fillBlanks($risk['acceptMessage'], $player) . "\n\n" . $earned, $risk['acceptUrl'], $risk['acceptBg'], [
            ['key' => '3', 'label' => ' Continue', 'command' => 4],
        ], ['i_sp']);
    }

    private function takeRisk(object $session, object $player): string
    {
        $risk = $this->currentRisk($session);
        if (!is_object($session->customer ?? null)) {
            return $this->missingCustomer($player);
        }
        if (!$risk) {
            return $this->missingRisk($player);
        }
        $won = $this->skillCheck((int) $this->num($player, 'luck', 50), 0.9);
        $stake = $this->rule('risk');
        $this->applyExp($player, $won ? $stake : -$stake);
        $message = $won
            ? $this->fillBlanks($risk['successMessage'], $player) . "\n\n`\$You earned `w$stake`\$ Bury Points!"
            : $this->fillBlanks($risk['failMessage'], $player) . "\n\n`\$You lose `#$stake`\$ Bury Points.";
        $session->customer = null;
        $session->risk = null;
        $luck = (int) $this->num($player, 'luck');
        $this->logMsg($won ? "{$player->name} took risk ($luck luck) won $stake bp." : "{$player->name} took a risk ($luck luck) lost $stake bp.");
        return $this->movieScene($player, $message, $won ? $risk['successUrl'] : $risk['failUrl'], $won ? $risk['successBg'] : $risk['failBg'], [
            ['key' => '3', 'label' => ' Continue', 'command' => 4],
        ], ['i_sp']);
    }

    private function waitCustomer(object $session, object $player): string
    {
        unset($this->runs[$session->id], $session->vm);
        if ((int) $this->num($player, 'turns') < 1) {
            return $this->noTurns($player);
        }
        if ($this->frandom(1) < $this->odds('normal_odds')) {
            $event = $this->specialEvent($player);
            if ($event) {
                $this->store->save();
                return $event;
            }
        }
        $session->customer = $this->makeCustomer($player);
        if ($this->risks && $this->frandom(1) < $this->odds('luck_odds')) {
            $session->risk = random_int(0, count($this->risks) - 1);
            $this->store->save();
            return $this->showRisk($player, $this->risks[$session->risk]);
        }
        $session->risk = null;
        if ($this->frandom(1) < $this->odds('script_odds')) {
            $session->customer = null;
            $session->risk = null;
            $event = $this->startFqScript($session, $player, 'script_event.c');
            $this->store->save();
            return $event;
        }
        $player->turns = (int) $this->num($player, 'turns') - 1;
        $this->store->save();
        return $this->showCustomer($player, $session->customer);
    }

    private function bankButtons(): array
    {
        return [
            ['key' => '0', 'label' => 'All to wood', 'command' => 104, 'sub' => 18],
            ['key' => '1', 'label' => 'All to gold', 'command' => 104, 'sub' => 17],
            ['key' => '2', 'label' => 'Exchange', 'command' => 104, 'sub' => 1],
            ['key' => '3', 'label' => 'Leave', 'command' => 12],
        ];
    }

    private function bankLobby(object $player, ?string $message = null): string
    {
        $this->room(104, 'Bank');
        if ($message !== null) {
            return $this->refreshMain($message, $this->bankButtons(), $this->tagFields($player));
        }
        return $this->genericPlace($player, 'flash/places/bank.swf', "`wTeller: `\$\"How's business, {$player->name}?\"", $this->bankButtons(), [
            'info' => 'You are at the city bank.  This facility allows you to exchange casket sales tags and transfer them to other accounts.',
            'url' => '',
        ]);
    }

    private function exchangeChoices(object $player, string $message, bool $tags = false): string
    {
        return $this->refreshMain($message, [
            ['key' => '2', 'label' => 'Gold', 'command' => 104, 'sub' => 2],
            ['key' => '1', 'label' => 'Silver', 'command' => 104, 'sub' => 3],
            ['key' => '0', 'label' => 'Wood', 'command' => 104, 'sub' => 4],
            ['key' => '3', 'label' => 'Back', 'command' => 39],
        ], $tags ? $this->tagFields($player) : []);
    }

    private function exchangeTags(object $player, int $count, string $from, string $to): array
    {
        $fromValue = self::TAG_VALUE[$from];
        $toValue = self::TAG_VALUE[$to];
        if ($count === 0) {
            return ['ok' => false, 'reason' => 'zero'];
        }
        if ($count < 0) {
            return ['ok' => false, 'reason' => 'negative'];
        }
        if ($count > (int) $this->num($player, $from)) {
            return ['ok' => false, 'reason' => 'short'];
        }
        if ((int) (($fromValue * $count) / $toValue) < 1) {
            return ['ok' => false, 'reason' => 'small'];
        }
        $gained = (int) ($count * ($fromValue / $toValue));
        $lost = (int) (($gained * $toValue) / $fromValue);
        $player->{$from} = (int) $this->num($player, $from) - $lost;
        $player->{$to} = (int) $this->num($player, $to) + $gained;
        return ['ok' => true, 'lost' => $lost, 'gained' => $gained];
    }

    private function bankPrompt(object $player, string $from, string $to, int $executeSub): string
    {
        $holdFrom = (int) (self::TAG_VALUE[$to] / self::TAG_VALUE[$from]);
        $holdTo = 1;
        if ($from === 'silver' && $to === 'gold') {
            $holdFrom = 2;
        }
        if ($holdFrom < 1) {
            $holdTo = (int) (self::TAG_VALUE[$from] / self::TAG_VALUE[$to]);
            $holdFrom = 1;
        }
        $fromName = $this->casketName($from);
        $toName = $this->casketName($to);
        return $this->scene([
            ['st_message', "`wTeller: `\$\"$holdFrom $fromName tag(s) = $holdTo $toName tag(s).  How many $fromName tags would you like to exchange?\""],
            ['st_object', 'i_get_text'],
            ['st_extra_text', (int) $this->num($player, $from)],
            ['i_return_command', 104],
            ['i_return_command_sub', $executeSub],
            ['i_sent', self::SENT['add']],
        ]);
    }

    private function bankCallback(object $player, array $tokens): string
    {
        $sub = (int) ($tokens['i_command_sub'] ?? 0);
        if ($sub === 18) {
            $player->wood = (int) $this->num($player, 'wood') + (int) $this->num($player, 'silver') * 2 + (int) $this->num($player, 'gold') * 4;
            $player->silver = 0;
            $player->gold = 0;
            $this->store->save();
            return $this->bankLobby($player, '`wA teller converts all your tags to Wood for you.');
        }
        if ($sub === 17) {
            $player->wood = (int) $this->num($player, 'wood') + (int) $this->num($player, 'silver') * 2 + (int) $this->num($player, 'gold') * 4;
            $player->silver = 0;
            $player->gold = 0;
            $this->exchangeTags($player, (int) $player->wood, 'wood', 'gold');
            $this->store->save();
            return $this->bankLobby($player, '`wA teller converts all your tags to Gold for you.');
        }
        if ($sub === 1) {
            return $this->exchangeChoices($player, '`wTeller: `$"Which kind tag would you like to exchange?"');
        }
        $menus = [
            4 => ['`wTeller: `$"What would you like to convert your Wood to?"', [['key' => '2', 'label' => 'Gold', 'command' => 104, 'sub' => 11], ['key' => '1', 'label' => 'Silver', 'command' => 104, 'sub' => 12], ['key' => '3', 'label' => 'Back', 'command' => 104, 'sub' => 1]]],
            3 => ['`wTeller: `$"What would you like to convert your Silver to?"', [['key' => '2', 'label' => 'Gold', 'command' => 104, 'sub' => 13], ['key' => '1', 'label' => 'Wood', 'command' => 104, 'sub' => 14], ['key' => '3', 'label' => 'Back', 'command' => 104, 'sub' => 1]]],
            2 => ['`wTeller: `$"What would you like to convert your Gold to?"', [['key' => '2', 'label' => 'Silver', 'command' => 104, 'sub' => 16], ['key' => '1', 'label' => 'Wood', 'command' => 104, 'sub' => 15], ['key' => '3', 'label' => 'Back', 'command' => 104, 'sub' => 1]]],
        ];
        if (isset($menus[$sub])) {
            return $this->refreshMain($menus[$sub][0], $menus[$sub][1]);
        }
        $prompts = [11 => ['wood', 'gold', 5], 12 => ['wood', 'silver', 6], 13 => ['silver', 'gold', 7], 14 => ['silver', 'wood', 8], 15 => ['gold', 'wood', 9], 16 => ['gold', 'silver', 10]];
        if (isset($prompts[$sub])) {
            return $this->bankPrompt($player, ...$prompts[$sub]);
        }
        $pairs = [5 => ['wood', 'gold'], 6 => ['wood', 'silver'], 7 => ['silver', 'gold'], 8 => ['silver', 'wood'], 9 => ['gold', 'wood'], 10 => ['gold', 'silver']];
        if (isset($pairs[$sub])) {
            if (($tokens['st_extra_text'] ?? '') === '12ABORT12') {
                return $this->exchangeChoices($player, '`wTeller: `$"Which kind tag would you like to exchange?"');
            }
            $count = (int) ($tokens['st_extra_text'] ?? '');
            $result = $this->exchangeTags($player, $count, $pairs[$sub][0], $pairs[$sub][1]);
            if (!$result['ok'] && $result['reason'] === 'zero') {
                return $this->exchangeChoices($player, '`wTeller: `$"Which kind tag would you like to exchange?"');
            }
            $kind = ucfirst($pairs[$sub][1]);
            $held = ucfirst($pairs[$sub][0]);
            if (!$result['ok'] && $result['reason'] === 'negative') {
                $sir = ($player->male ?? true) === false ? "ma'am" : 'sir';
                return $this->refreshMain("`wTeller: `\$\"You are very amusing $sir.\"", [
                    ['key' => '3', 'label' => 'Continue', 'command' => 104, 'sub' => 1],
                ]);
            }
            if (!$result['ok'] && $result['reason'] === 'short') {
                return $this->refreshMain('`wTeller: `$"You can\'t exchange what you don\'t have.  I\'m trying to be nice here, but please, think next time."', [
                    ['key' => '3', 'label' => 'Continue', 'command' => 104, 'sub' => 1],
                ]);
            }
            if (!$result['ok']) {
                return $this->refreshMain("`wTeller: `\$\"You don't understand, do you. You can't trade just $count for a $kind tag, alright?\"", [
                    ['key' => '3', 'label' => 'Continue', 'command' => 104, 'sub' => 1],
                ]);
            }
            $this->store->save();
            return $this->exchangeChoices($player, "`wTeller: `\$\"Very good, I have converted {$result['lost']} $held tag(s) to {$result['gained']} $kind tag(s). Any other tags you would like to convert?\"", true);
        }
        return $this->bankLobby($player);
    }

    private function yangsButtons(): array
    {
        return [
            ['key' => '0', 'label' => 'Order #1', 'command' => 14, 'sub' => 3],
            ['key' => '1', 'label' => 'Order #2', 'command' => 14, 'sub' => 1],
            ['key' => '2', 'label' => 'Order #3', 'command' => 14, 'sub' => 2],
            ['key' => '3', 'label' => 'Leave', 'command' => 12],
        ];
    }

    private function yangsMenu(): string
    {
        return $this->refreshMain(self::YANGS_MENU, $this->yangsButtons());
    }

    private function yangsContinue(string $message, array $extra = []): string
    {
        return $this->refreshMain($message, [
            ['key' => '3', 'label' => 'Continue', 'command' => 14, 'sub' => 100],
        ], $extra);
    }

    private function yangsLobby(object $player): string
    {
        $this->room(14, "Yang's", true);
        $intro = 'What could be luckier than a box of delectable grilled teriyaki spread over hot rice? Your mouth waters at the very thought of it.';
        if (!empty($player->kickedOut)) {
            return $this->genericPlace($player, 'flash/places/yangs.swf', "`w{$player->name}: `\$\"Hello? I'd like some service here!\"\n\n`wJune: `\$\"Just leave, asshole.\"", [
                ['key' => '3', 'label' => 'Leave', 'command' => 12],
            ], [
                'info' => $intro,
                'status' => 'June glances at you nervously.',
                'url' => '',
            ]);
        }
        return $this->genericPlace($player, 'flash/places/yangs.swf', self::YANGS_MENU, $this->yangsButtons(), [
            'info' => $intro,
            'status' => 'June waits for you to order.',
            'url' => '',
        ]);
    }

    private function spendPoints(object $player, int $cost): void
    {
        $loss = min((int) $this->num($player, 'buryPoints'), $cost);
        $player->buryPoints = (int) $this->num($player, 'buryPoints') - $loss;
        $player->exp = max(0, (int) $this->num($player, 'exp') - $loss);
    }

    private function changeLuck(object $player, int $delta): void
    {
        $player->luck = min(100, max(1, (int) $this->num($player, 'luck', 50) + $delta));
    }

    private function cleanFortune(string $text): string
    {
        $clean = preg_replace('/[<>`]/', '', $text) ?? '';
        $clean = preg_replace('/\s+/', ' ', $clean) ?? '';
        return substr(trim($clean), 0, 120);
    }

    private function orderDragon(object $player): string
    {
        if ((int) $this->num($player, 'luck', 50) >= 40) {
            $message = "`\$Maybe later.  You especially like to eat Yang's when you're feeling down on your luck. (like less than 40 percent for instance)";
        } elseif ((int) $this->num($player, 'buryPoints') < 5) {
            $message = '`$You check your wallet.. shoot, not enough Bury Points!';
        } else {
            $this->spendPoints($player, 5);
            $player->luck = (int) $this->num($player, 'luck', 50) + 10;
            $this->store->save();
            $message = "`\$You relish every bite of your General Tso's Chicken.\n\nYou gain `w10`\$ luck.";
            return $this->yangsContinue($message, [
                ['i_luck', (int) $this->num($player, 'luck', 50)],
                ['i_exp', fq_cash((int) $this->num($player, 'exp'))],
                ['i_sp', fq_cash((int) $this->num($player, 'buryPoints'))],
            ]);
        }
        return $this->yangsContinue($message);
    }

    private function namePrompt(string $message, int $returnSub): string
    {
        return $this->scene([
            ['st_message', $message],
            ['st_object', 'i_getname'],
            ['i_return_command', 14],
            ['i_return_command_sub', $returnSub],
            ['i_sent', self::SENT['add']],
        ]);
    }

    private function fortunePrompt(int $returnSub): string
    {
        return $this->scene([
            ['st_message', '`wJune: `$"If you would like to write a custom fortune, enter it now."'],
            ['st_extra_text', ''],
            ['st_object', 'i_get_text'],
            ['i_return_command', 14],
            ['i_return_command_sub', $returnSub],
            ['i_sent', self::SENT['add']],
        ]);
    }

    private function deliveryBlocked(string $speaker, string $request): string
    {
        return $this->refreshMain("`w$speaker: `\$\"$request\"\n\n`wJune: `\$\"I'm sorry, my dad is out delivering food right now.  How about something for here?\"", [
            ['key' => '2', 'label' => 'Get Bitchy', 'command' => 14, 'sub' => 105],
            ['key' => '3', 'label' => 'Continue', 'command' => 14, 'sub' => 100],
        ]);
    }

    private function rememberName(object $session, object $player, array $tokens, string $kind): string
    {
        $rawId = $tokens['i_his_id'] ?? null;
        if ($rawId === null || $rawId === '' || !is_numeric($rawId)) {
            $typed = trim((string) ($tokens['st_extra_text'] ?? ''));
            if ($typed === '' || $typed === '12ABORT12') {
                return $this->yangsMenu();
            }
            $found = $this->store->findName($typed);
            if (!$found) {
                return $this->yangsMenu();
            }
            $session->yangsTarget = $found->id;
        } else {
            $index = (int) $rawId;
            if ($index < 0) {
                return $this->yangsMenu();
            }
            $found = $this->store->state->players[$index] ?? null;
            $session->yangsTarget = $found->id ?? null;
        }
        $session->yangsKind = $kind;
        $this->store->save();
        return $this->fortunePrompt($kind === 'sparrow' ? 104 : 102);
    }

    private function deliverFood(object $session, object $player, array $tokens, string $kind): string
    {
        if (($tokens['st_extra_text'] ?? '') === '12ABORT12') {
            $session->yangsTarget = null;
            return $this->yangsMenu();
        }
        if ((int) $this->num($player, 'yangsSent') >= $this->rule('yangs_per_day')) {
            return $this->yangsContinue('`$"We only allow ' . $this->rule('yangs_per_day') . ' delivery per day," Mr Yang tells you apologetically. ');
        }
        $target = null;
        foreach ($this->store->state->players as $entry) {
            if ((string) $entry->id === (string) ($session->yangsTarget ?? '')) {
                $target = $entry;
            }
        }
        if (!$target || ($target->active ?? true) === false) {
            return $this->yangsContinue("`\$Error delivering food, he wasn't home.");
        }
        if ((string) $target->id === (string) $player->id) {
            return $this->yangsContinue("`w{$player->name}: `\$\"If you could just deliver that to my parlor, that would be great, m'kay?\"\n\n`wJune: `\$\"Why don't you just get food for here?\"\n\n`w{$player->name}: `\$\"Ok, fine.\"\n\n");
        }
        if ((int) $this->num($player, 'buryPoints') < 20) {
            return $this->yangsContinue("`\$You don't have enough Bury Points.  You realize you would just look stupid trying to order now...");
        }
        $typed = $this->cleanFortune((string) ($tokens['st_extra_text'] ?? ''));
        $fortune = $typed !== '' ? $typed : $this->pick($kind === 'sparrow' ? $this->kindFortunes : $this->cursedFortunes);
        $this->spendPoints($player, 20);
        $player->yangsSent = (int) $this->num($player, 'yangsSent') + 1;
        $luck = $kind === 'sparrow' ? 5 : -10;
        $this->changeLuck($target, $luck);
        $cookie = $kind === 'sparrow'
            ? "\n`\${$player->name} has sent you some chinese food.\nYour fortune cookie reads:\n`w\"$fortune\"\n`\$You gain `w$luck`\$ luck!\n"
            : "\n`\$Some delicious smelling chinese food has been left on your doorstep.\nYour fortune cookie reads:\n`w\"$fortune\"\n`\$You lose `w10`\$ luck!\n";
        $deliveries = $this->list($target, 'deliveries');
        $deliveries[] = (object) ['text' => $cookie, 'art' => 'flash/chinese_takeout.swf'];
        $target->deliveries = $deliveries;
        $this->tv("A Yang's commercial comes on. It looks like {$target->funeralName} in the background.");
        $this->logMsg($player->name . ($kind === 'sparrow' ? " sent nice Yang's to " : " sent Yang's to ") . $target->name . ', cookie: ' . $fortune);
        if ($kind === 'tiger' && random_int(0, 3) === 1) {
            $this->addNews($this->pick($this->yangsHeadlines($player, $target)));
        }
        $session->yangsTarget = null;
        $this->store->save();
        return $this->yangsContinue('`$Mr. Yang leaves to deliver the food.', [
            ['i_exp', fq_cash((int) $this->num($player, 'exp'))],
            ['i_sp', fq_cash((int) $this->num($player, 'buryPoints'))],
        ]);
    }

    private function yangsHeadlines(object $player, object $target): array
    {
        return [
            "`7SPOTLIGHT ON OUR TOWN\n`^Local mortician {$player->name} is seen buying some Yang's for delivery.\n\n",
            "`7YANG'S NOW DELIVERS\n`^\"Yes, delivery is very popular.  You see this package from {$player->name}? I'm going deliver it all the way to {$target->funeralName}, no extra charge!\" Mr Yang smiles.\n\n",
            "`7YANG'S DOING WELL\n`^\"Ah, yes.  Business is very good today! People like {$player->name} buying so much delivery,\" Mr Yang comments.\n\n",
            "`7YANG'S MAKES A GREAT GIFT\n`^Looking for that special gift?  How about food? Today {$player->name} was seen buying some, seems popular.\n\n",
        ];
    }

    private function yangsCallback(object $session, object $player, array $tokens): string
    {
        $sub = (int) ($tokens['i_command_sub'] ?? 0);
        if (!empty($player->kickedOut)) {
            return $this->yangsLobby($player);
        }
        if ($sub === 100) {
            return $this->yangsMenu();
        }
        if ($sub === 3) {
            return $this->orderDragon($player);
        }
        if ($sub === 1) {
            if ((int) $this->num($player, 'yangsSent') >= $this->rule('yangs_per_day')) {
                return $this->deliveryBlocked('You', 'I would like a lucky sparrow delivered, please.');
            }
            return $this->namePrompt('`wJune: `$"That\'s very nice of you.  Who\'s the lucky person?"', 103);
        }
        if ($sub === 2) {
            if ((int) $this->num($player, 'yangsSent') >= $this->rule('yangs_per_day')) {
                return $this->deliveryBlocked($player->name, 'I would like a cursed tiger delivered, please.');
            }
            return $this->namePrompt('`wJune: `$"Ah.. someone you are not too fond of, I take it.  Who would this unlucky person be?" ', 101);
        }
        if ($sub === 103) {
            return $this->rememberName($session, $player, $tokens, 'sparrow');
        }
        if ($sub === 101) {
            return $this->rememberName($session, $player, $tokens, 'tiger');
        }
        if ($sub === 104) {
            return $this->deliverFood($session, $player, $tokens, 'sparrow');
        }
        if ($sub === 102) {
            return $this->deliverFood($session, $player, $tokens, 'tiger');
        }
        if ($sub === 105) {
            $player->kickedOut = true;
            $this->store->save();
            return $this->refreshMain("`w{$player->name}: `\$\"That's very interesting.  Now get the god damn teriyaki, put it in a box, and deliver it.\"\n\n`wJune: `\$\"Please leave now.\"", [
                ['key' => '3', 'label' => 'Leave', 'command' => 12],
            ]);
        }
        return $this->yangsLobby($player);
    }

    private function hospitalRoom(object $player): string
    {
        $this->room(101, 'Hospital');
        return $this->genericPlace($player, 'flash/places/hospital_room.swf', '`$Your whole body aches.', [
            ['key' => '3', 'label' => 'Leave', 'command' => 101, 'sub' => 1],
        ], [
            'info' => "You are lying on a bed, it doesn't look like your own.",
            'url' => 'flash/blank.swf',
            'battle' => false,
        ]);
    }

    private function hospitalLobby(object $player): string
    {
        $this->room(101, 'Hospital');
        if (!empty($player->inHospital)) {
            return $this->hospitalRoom($player);
        }
        return $this->genericPlace($player, 'flash/places/hospital_desk.swf', "`wNurse: `\$\"Hi {$player->name}.  I don't recall you having a pick-up today.\"", [
            ['key' => '3', 'label' => 'Leave', 'command' => 12],
        ], [
            'info' => 'You are at the city hospital.  A nurse smiles at you.',
            'url' => '',
        ]);
    }

    private function hospitalLeave(object $player): string
    {
        $player->inHospital = false;
        $player->hp = (int) ($player->hpMax ?? $player->hp ?? 10);
        $who = ($player->male ?? true) ? 'prince charming' : 'sleeping beauty';
        return $this->genericPlace($player, 'flash/places/hospital_desk.swf', "`wNurse: `\$\"I see you finally woke up, $who.  Hope you'll be more careful next time.\"", [
            ['key' => '3', 'label' => 'Leave', 'command' => 12],
        ], [
            'info' => 'You are at the city hospital.  A nurse smiles at you.',
            'url' => '',
        ]);
    }

    private function hospitalCallback(object $player, array $tokens): string
    {
        if ((int) ($tokens['i_command_sub'] ?? 0) === 1) {
            return $this->hospitalLeave($player);
        }
        return !empty($player->inHospital) ? $this->hospitalRoom($player) : $this->hospitalLobby($player);
    }

    private function psychCost(object $player): int
    {
        return 4 + (int) $this->num($player, 'psych') * 2;
    }

    private function hpMaxOf(object $player): int
    {
        return (int) ($player->hpMax ?? $player->hp ?? 10);
    }

    private function spiritButtons(object $player): array
    {
        return [
            ['key' => 'a', 'label' => 'Library', 'command' => 100, 'sub' => 5],
            ['key' => '0', 'label' => 'Take PSY', 'command' => 100, 'sub' => 1],
            ['key' => '1', 'label' => 'Take PE', 'command' => 100, 'sub' => 4],
            ['key' => '2', 'label' => 'Take ST', 'command' => 100, 'sub' => 3],
            ['key' => '3', 'label' => 'Leave', 'command' => 12],
        ];
    }

    private function spiritSchedule(object $player): string
    {
        return "`wClass schedules\n\n`\$PSY - Social Psychology (cost: `w" . $this->psychCost($player) . "`\$ gold tags)\n`\$PE - Physical Education (cost: `w" . (int) ($this->hpMaxOf($player) * 1.2) . "`\$ gold tags)\n`\$ST - Strength Training (cost: `w" . ((int) $this->num($player, 'strength', 3) * 3) . '`$ gold tags)';
    }

    private function spiritFrame(): array
    {
        return [
            'info' => 'The role of the funeral director is constantly changing, encompassing more and more responsibilities each year. (for a fee, of course)',
            'status' => 'Taking classes at SU is a great way to expand your options.',
            'url' => '',
        ];
    }

    private function spiritLobby(object $player, bool $fresh = true): string
    {
        $this->room(100, 'Spirit U');
        if (!$fresh) {
            return $this->refreshMain($this->spiritSchedule($player), $this->spiritButtons($player));
        }
        return $this->genericPlace($player, 'flash/places/psych.swf', $this->spiritSchedule($player), $this->spiritButtons($player), $this->spiritFrame());
    }

    private function spiritContinue(object $player, string $message, bool $tags = false): string
    {
        $extra = [];
        if ($tags) {
            foreach ($this->stats($player) as $field) {
                if (str_starts_with($field[0], 'i_coffin_')) {
                    $extra[] = $field;
                }
            }
        }
        return $this->refreshMain($message, [
            ['key' => '3', 'label' => 'Continue', 'command' => 100, 'sub' => 2],
        ], $extra);
    }

    private function libraryText(): string
    {
        return "You are in the library.\n\n`\$Students are quietly studying here.  You see one nerdy looking kid hogging the lone library computer.\n\n`\$The book of deeds is sitting on a pedestal close by.";
    }

    private function libraryButtons(): array
    {
        return [
            ['key' => '1', 'label' => 'View Deeds', 'command' => 100, 'sub' => 8],
            ['key' => '2', 'label' => 'Old Papers', 'command' => 100, 'sub' => 6],
            ['key' => '3', 'label' => 'Back', 'command' => 100, 'sub' => 2],
        ];
    }

    private function spiritLibrary(object $player, bool $fresh = false): string
    {
        if ($fresh) {
            $this->room(100, 'Spirit U');
            return $this->genericPlace($player, 'flash/places/psych.swf', $this->libraryText(), $this->libraryButtons(), $this->spiritFrame());
        }
        return $this->refreshMain($this->libraryText(), $this->libraryButtons());
    }

    private function spiritCallback(object $player, array $tokens): string
    {
        $sub = (int) ($tokens['i_command_sub'] ?? 0);
        if ($sub === 9) {
            return $this->spiritLibrary($player, true);
        }
        if ($sub === 2) {
            return $this->spiritLobby($player, false);
        }
        if ($sub === 1) {
            $cost = $this->psychCost($player);
            if ($cost > (int) $this->num($player, 'gold')) {
                return $this->spiritContinue($player, "You fumble through your pockets in vain - you don't have enough gold tags to pay for the class.");
            }
            $player->gold = (int) $this->num($player, 'gold') - $cost;
            $player->psych = (int) $this->num($player, 'psych') + 1;
            $skills = [1 => 'Mood reading', 2 => 'Financial Estimation', 3 => 'Cutting resistance in half', 4 => 'Cutting mood in half', 5 => 'Doubling spending limit'];
            $extra = isset($skills[$player->psych]) ? "\n\n`\$You have learned a new skill: `w{$skills[$player->psych]}" : '';
            $this->store->save();
            return $this->spiritContinue($player, 'You study hard for about an hour.  Your psychology level is raised to `w' . ($player->psych + 1) . "`\$!$extra", true);
        }
        if ($sub === 3) {
            $cost = (int) $this->num($player, 'strength', 3) * 3;
            if ($cost > (int) $this->num($player, 'gold')) {
                return $this->spiritContinue($player, "You fumble through your pockets in vain - you don't have enough gold tags to pay for the class.");
            }
            $player->gold = (int) $this->num($player, 'gold') - $cost;
            $player->strength = (int) $this->num($player, 'strength', 3) + 1;
            $this->store->save();
            return $this->spiritContinue($player, 'You lift weights for a while.  You gain `w1`$ strength!', true);
        }
        if ($sub === 4) {
            $cost = (int) ($this->hpMaxOf($player) * 1.2);
            if ($cost > (int) $this->num($player, 'gold')) {
                return $this->spiritContinue($player, "You fumble through your pockets in vain - you don't have enough gold tags to pay for the class.");
            }
            $player->gold = (int) $this->num($player, 'gold') - $cost;
            $gain = 4 + random_int(0, 1);
            $player->hpMax = $this->hpMaxOf($player) + $gain;
            $this->store->save();
            return $this->spiritContinue($player, "You run laps around the gym.  You gain `w$gain`\$ hitpoints!", true);
        }
        if ($sub === 5) {
            return $this->spiritLibrary($player);
        }
        if ($sub === 6) {
            $latest = max(0, (int) ($this->store->state->day ?? 1) - 1);
            return $this->scene([
                ['st_message', "Which old newspaper would you like to look at?  You can choose between 1 and $latest."],
                ['st_extra_text', ''],
                ['st_object', 'i_get_text'],
                ['i_return_command', 100],
                ['i_return_command_sub', 7],
                ['i_sent', self::SENT['add']],
            ]);
        }
        if ($sub === 7) {
            if (($tokens['st_extra_text'] ?? '') === '12ABORT12') {
                return $this->spiritLibrary($player);
            }
            $day = (int) ($tokens['st_extra_text'] ?? '');
            $latest = (int) ($this->store->state->day ?? 1) - 1;
            if ($day < 1 || $day > $latest) {
                $text = "You desperately hunt around for anything dealing with a day $day but fail.";
            } else {
                $saved = (string) ($this->store->state->papers->{$day} ?? '');
                $text = "You find a yellowing paper from day $day, it looks like it may have been peed on.`\$\n\n" . str_replace(['`7', '`^'], ['`w', '`$'], $saved);
            }
            return $this->viewPage($text, 100, 9);
        }
        if ($sub === 8) {
            $top = $this->highestExperience();
            $deeds = (string) ($this->store->state->deeds ?? '');
            if ($deeds !== '') {
                return $this->viewPage("The Book Of Deeds`\$\n\n" . $deeds, 100, 9);
            }
            $book = "The Book Of Deeds`\$\n\nNo one in Deathville has ever done anything worthing noting.\n\nIt's sad, really.\n";
            if ($top) {
                $book .= "\nThere is a glimmer of hope in `w{$top->name}`\$, but " . $this->he($top) . ' has a way to go.';
            }
            return $this->viewPage($book, 100, 9);
        }
        return $this->spiritLobby($player);
    }

    private function storeLobby(object $player): string
    {
        $this->room(105, 'Store', true);
        return $this->genericPlace($player, 'flash/people/store_guy.swf', "`wElijah: `\$\"What can I get for you today, {$player->name}?\"", [
            ['key' => '0', 'label' => 'Security', 'command' => 105, 'sub' => 103],
            ['key' => '1', 'label' => 'Supplies', 'command' => 105, 'sub' => 102],
            ['key' => '2', 'label' => 'Hearses', 'command' => 105, 'sub' => 100],
            ['key' => '3', 'label' => 'Leave', 'command' => 12],
        ], [
            'background' => self::STORE_ROOM,
            'info' => "Di-Mart is not only an official DuraRest (tm) reseller but also carries many other useful supplies for the death care industry.\n\nA really creepy looking guy in a suit smiles at you.",
        ]);
    }

    private function hearseMenu(object $player): string
    {
        $lines = ['`wElijah: `$"We carry nothing but the best in funeral transportation."'];
        $buttons = [];
        $keys = [1 => 'a', 2 => '0', 3 => '1', 4 => '2'];
        $fleet = fq_transport();
        for ($index = 1; $index < count($fleet); $index++) {
            $item = $fleet[$index];
            $lines[] = "`w$index`\$ - {$item['name']} - Cost: `w{$item['price']}`\$ gold tags.";
            $buttons[] = ['key' => $keys[$index], 'label' => "View #$index", 'command' => 105, 'sub' => $index];
        }
        $buttons[] = ['key' => '3', 'label' => 'Back', 'command' => 40];
        return $this->refreshVisit([
            'url' => 'flash/people/store_guy.swf',
            'message' => implode("\n", $lines),
            'buttons' => $buttons,
        ]);
    }

    private function hearseLook(object $session, object $player, int $index): string
    {
        $fleet = fq_transport();
        $item = $fleet[$index];
        $owned = fq_ride($player);
        $trade = (int) ($owned['price'] / 2);
        $cost = $item['price'] - $trade;
        $session->storeItem = $index;
        $this->store->save();
        return $this->refreshVisit([
            'url' => 'flash/blank.swf',
            'background' => $item['url'],
            'message' => "`wThe {$item['name']}`\$ - {$item['description']}\n\nEffect: `w{$item['bonus']}`\$ extra turns per day.\n\nAfter trading in your {$owned['name']} for `w$trade`\$ gold tags, the cost will be `w$cost`\$ gold tags.",
            'buttons' => [
                ['key' => '2', 'label' => 'Buy it', 'command' => 105, 'sub' => 101],
                ['key' => '3', 'label' => 'Back', 'command' => 105, 'sub' => 100],
            ],
        ]);
    }

    private function buyHearse(object $session, object $player): string
    {
        $fleet = fq_transport();
        $index = (int) ($session->storeItem ?? 0);
        if ($index < 1 || $index >= count($fleet)) {
            return $this->hearseMenu($player);
        }
        $item = $fleet[$index];
        $owned = fq_ride($player);
        $price = $item['price'] - (int) ($owned['price'] / 2);
        if ($price > (int) $this->num($player, 'gold')) {
            return $this->refreshMain("`wElijah: `\$\"I'm sorry, I really don't have time to waste.  You obviously cannot afford a luxury sedan such as the {$item['name']}.\"", [
                ['key' => '3', 'label' => 'Back', 'command' => 105, 'sub' => 100],
            ]);
        }
        $player->gold = (int) $this->num($player, 'gold') - $price;
        $player->transport = $index;
        $player->turns = (int) $this->num($player, 'turns') + $item['bonus'];
        $this->store->save();
        return $this->refreshVisit([
            'url' => 'flash/stuff/money_bag_2.swf',
            'message' => "You trade in your {$owned['name']} for a brand new {$item['name']}!\n\nYou have time for an extra `w{$item['bonus']}`\$ customers today.",
            'buttons' => [['key' => '3', 'label' => 'Back', 'command' => 105, 'sub' => 100]],
            'extra' => [
                ...$this->tagFields($player),
                ['i_turns', fq_cash((int) $this->num($player, 'turns'))],
            ],
        ]);
    }

    private function storeCallback(object $session, object $player, array $tokens): string
    {
        $sub = (int) ($tokens['i_command_sub'] ?? 0);
        if ($sub === 100) {
            return $this->hearseMenu($player);
        }
        if ($sub === 101) {
            return $this->buyHearse($session, $player);
        }
        if ($sub === 102) {
            return $this->startFqScript($session, $player, 'store_supplies.c', 'Store');
        }
        if ($sub === 103) {
            return $this->startFqScript($session, $player, 'store_security.c', 'Store');
        }
        if ($sub >= 1 && $sub <= 4) {
            return $this->hearseLook($session, $player, $sub);
        }
        return $this->storeLobby($player);
    }

    private function endProwl(object $session): void
    {
        $session->prowling = false;
        $session->battle = false;
        $session->targetIndex = null;
    }

    private function foe(object $session): ?object
    {
        if (!property_exists($session, 'targetIndex') || $session->targetIndex === null || $session->targetIndex === '') {
            return null;
        }
        return $this->playerAt((int) $session->targetIndex);
    }

    private function parlorIntruder(object $owner): bool
    {
        foreach ($this->store->state->sessions as $entry) {
            if (empty($entry->prowling) || $entry->playerId === null) {
                continue;
            }
            $target = $this->foe($entry);
            if ($target && (string) $target->id === (string) $owner->id) {
                return true;
            }
        }
        return false;
    }

    private function otherProwler(int $index, string $sessionId): ?object
    {
        foreach ($this->store->state->sessions as $id => $entry) {
            if ((string) $id === $sessionId || empty($entry->prowling) || (int) ($entry->targetIndex ?? -1) !== $index) {
                continue;
            }
            $who = $this->store->player((string) $id);
            if ($who) {
                return $who;
            }
        }
        return null;
    }

    private function watchText(object $player): string
    {
        $left = $this->rule('visits_per_day') - (int) $this->num($player, 'visits');
        if ($left <= 0) {
            return "You look at your watch, you should really get back to your parlor.\n";
        }
        if ($left === 1) {
            return "You look at your watch, you have time to visit `w1`\$ more parlor today.\n";
        }
        return "You look at your watch, you have time to visit `w$left`\$ more parlors today.\n";
    }

    private function compLobby(object $session, object $player): string
    {
        $this->endProwl($session);
        $this->room(102, 'Parlors');
        return $this->placeScreen($player, [
            'background' => 'flash/places/palors.swf',
            'info' => self::FOG,
            'message' => $this->watchText($player),
            'buttons' => $this->compButtons(),
        ]);
    }

    private function compButtons(): array
    {
        return [
            ['key' => '1', 'label' => 'Visit', 'command' => 102, 'sub' => 4],
            ['key' => '2', 'label' => 'Look', 'command' => 102, 'sub' => 1],
            ['key' => '3', 'label' => 'Leave', 'command' => 12],
        ];
    }

    private function compMenu(object $player): string
    {
        return $this->refreshMain($this->watchText($player), $this->compButtons());
    }

    private function padRight(string $text, int $width): string
    {
        return strlen($text) >= $width ? $text : $text . str_repeat(' ', $width - strlen($text));
    }

    private function padLeft(string $text, int $width): string
    {
        return strlen($text) >= $width ? $text : str_repeat(' ', $width - strlen($text)) . $text;
    }

    private function rankingStatus(object $person): string
    {
        foreach ($this->store->state->sessions as $entry) {
            if ((string) ($entry->playerId ?? '') === (string) $person->id) {
                return !empty($entry->chatting) ? 'in cafe' : 'on now';
            }
        }
        return !empty($person->police) ? 'Protected' : '';
    }

    private function rankingScreen(int $returnCommand, bool $hidePolice = false): string
    {
        $people = array_values(array_filter(
            $this->store->state->players,
            fn ($entry) => ($entry->active ?? true) !== false && !($hidePolice && !empty($entry->police))
        ));
        usort($people, fn ($a, $b) => (int) $this->num($b, 'exp') <=> (int) $this->num($a, 'exp'));
        $message = '';
        $count = 0;
        foreach ($people as $entry) {
            $sex = ($entry->male ?? true) === false ? '<font color="#FC54FC">F</font>' : 'M';
            $message .= $this->padRight((string) $entry->name, 20) . $sex . '  ' . $this->padRight((string) $entry->funeralName, 20) . $this->padLeft(fq_cash((int) $this->num($entry, 'exp')), 10) . ' ' . $this->rankingStatus($entry) . "\n";
            $count++;
        }
        while ($count % 19 !== 0) {
            $message .= "\n";
            $count++;
        }
        return $this->scene([
            ['st_date', $this->monthAndDay()],
            ['st_news', $message],
            ['i_rank_command', $returnCommand],
            ['i_sent', self::SENT['ranking']],
        ]);
    }

    private function lookupName(array $tokens): string
    {
        $query = (string) ($tokens['st_input'] ?? $tokens['st_extra_text'] ?? '');
        $start = (int) ($tokens['i_his_id'] ?? -1);
        $found = -1;
        $needle = strtolower($query);
        $players = $this->store->state->players;
        if ($needle !== '' && $start >= 0 && $start < count($players)) {
            for ($index = $start; $index < count($players); $index++) {
                $entry = $players[$index];
                if (($entry->active ?? true) === false) {
                    continue;
                }
                if (str_contains(strtolower((string) $entry->name), $needle)) {
                    $found = $index;
                    break;
                }
            }
        }
        return $this->scene([
            ['st_object', 'i_namenext'],
            ['i_his_id', $found],
            ['st_message', $found === -1 ? '' : $players[$found]->name],
            ['i_sent', self::SENT['add']],
        ]);
    }

    private function compAskName(): string
    {
        return $this->scene([
            ['st_message', "`\$Who's funeral home would you like to pay a little visit to?"],
            ['st_object', 'i_getname'],
            ['i_return_command', 102],
            ['i_return_command_sub', 5],
            ['i_sent', self::SENT['add']],
        ]);
    }

    private function compInFront(object $session, object $player): string
    {
        $target = $this->foe($session);
        if (!$target) {
            return $this->compLobby($session, $player);
        }
        $info = 'You are standing in front of a funeral parlor.  The sign above the door proudly announces the name, "' . $target->funeralName . '". ';
        $picture = [
            'background' => 'flash/places/funeral_enemy.swf',
            'foreground' => 'flash/music/music_sneak.swf',
            'info' => $info,
            'status' => '',
        ];
        if ((string) $target->id === (string) $player->id) {
            $this->endProwl($session);
            return $this->refreshVisit($picture + [
                'message' => "This parlor looks strangely familiar.\n\nYou decide to go inside...",
                'buttons' => [['key' => '3', 'label' => 'Continue', 'command' => 4]],
            ]);
        }
        if ($this->isOnline($target, $session->id)) {
            $this->endProwl($session);
            return $this->refreshVisit($picture + [
                'message' => "You see {$target->name} through the window talking earnestly to a young couple, perhaps later would be better...",
                'buttons' => [['key' => '3', 'label' => 'Leave', 'command' => 38]],
            ]);
        }
        if (!empty($target->police)) {
            $this->endProwl($session);
            $picture['background'] = 'flash/places/funeral_enemy_police.swf';
            return $this->refreshVisit($picture + [
                'message' => 'You park your ' . fq_ride($player)['name'] . " a few blocks away from {$target->funeralName}.  As you approach you see {$target->name} talking to some cops, maybe you should come back after things cool down.",
                'buttons' => [['key' => '3', 'label' => 'Leave', 'command' => 38]],
            ]);
        }
        $rival = $this->otherProwler((int) $session->targetIndex, (string) $session->id);
        if ($rival) {
            $this->endProwl($session);
            return $this->refreshVisit($picture + [
                'message' => "As you take your position behind a bush, you are surprised to see {$rival->name} already sitting there.\n\n`w{$rival->name}: `\$\"Oh, hey {$player->name}.  Tell ya what, " . $this->he($target) . "'s mine first, ok?\"\n\nYou agree to come back later.",
                'buttons' => [['key' => '3', 'label' => 'Leave', 'command' => 38]],
            ]);
        }
        $player->visits = (int) $this->num($player, 'visits') + 1;
        $session->prowling = true;
        $this->store->save();
        return $this->refreshVisit($picture + [
            'message' => "The lights are off, the parlor is silent.  {$target->name} must be sleeping inside.\n\nNobody seems to be looking, you could try the door-knob if you like.\n\nYou see a `w" . fq_ride($target)['name'] . "`\$ parked nearby.",
            'buttons' => [
                ['key' => '2', 'label' => 'Try Door', 'command' => 102, 'sub' => 6],
                ['key' => '3', 'label' => 'Leave', 'command' => 38],
            ],
        ]);
    }

    private function doorMenu(object $player, string $message): string
    {
        return $this->refreshVisit([
            'message' => $message,
            'buttons' => [
                ['key' => '2', 'label' => 'Pick Lock', 'command' => 102, 'sub' => 9],
                ['key' => '3', 'label' => 'Leave', 'command' => 38],
            ],
        ]);
    }

    private function compInside(object $session, object $player): string
    {
        $target = $this->foe($session);
        if (!$target || empty($session->prowling)) {
            return $this->compLobby($session, $player);
        }
        $message = "You quickly slip in and close the door.  You hear {$target->name} softly snoring in the back room.";
        if ((int) $this->num($target, 'safe') > 0) {
            $message .= "\n\nYou see a large combination `wSafe`\$ in the corner.  You won't be able to steal tags.";
        }
        $message .= "\n\nJudging from the room, you would guess {$target->name} has `w{$target->hpMax}`\$ hitpoints.";
        $buttons = [];
        if ((int) $this->num($target, 'safe') === 0) {
            $buttons[] = ['key' => '2', 'label' => 'Steal', 'command' => 102, 'sub' => 7];
        }
        $buttons[] = ['key' => '3', 'label' => 'Attack', 'command' => 102, 'sub' => 8];
        return $this->refreshVisit([
            'url' => '',
            'background' => 'flash/places/desk_enemy.swf',
            'info' => "You have broken into {$target->funeralName}.",
            'status' => '',
            'message' => $message,
            'buttons' => $buttons,
        ]);
    }

    private function stealTags(object $from, object $to, int $max): array
    {
        $kinds = [['wood', 'wooden'], ['silver', 'silver'], ['gold', 'gold']];
        [$id, $label] = $kinds[random_int(0, 2)];
        if ((int) $this->num($from, $id) <= 0) {
            return ['count' => -1, 'label' => $label];
        }
        $count = $this->between(0, $max);
        if ($count > (int) $this->num($from, $id)) {
            $count = (int) $this->num($from, $id);
        }
        $from->{$id} = (int) $this->num($from, $id) - $count;
        $to->{$id} = (int) $this->num($to, $id) + $count;
        return ['count' => $count, 'label' => $label];
    }

    private function compSteal(object $session, object $player): string
    {
        $target = $this->foe($session);
        if (!$target || empty($session->prowling)) {
            return $this->compLobby($session, $player);
        }
        if ((int) $this->num($target, 'safe') > 0) {
            return $this->compInside($session, $player);
        }
        $stolen = $this->stealTags($target, $player, 3);
        if ($stolen['count'] === -1) {
            $message = "You look around but can't find anything of value.";
        } elseif ($stolen['count'] === 0) {
            $message = 'You begin wildly opening drawers and things looking for anything of value.';
        } elseif ($stolen['count'] === 1) {
            $message = "You look around and find `w1 {$stolen['label']}`\$ tag hidden on top of the refrigerator!";
        } else {
            $message = "You look around and find `w{$stolen['count']} {$stolen['label']}`\$ tags in a drawer!";
        }
        $clothes = ($player->male ?? true) !== false && ($target->male ?? true) === false ? 'lingerie' : 'pajamas';
        $message .= "\n\nYou are startled by a noise behind you.  It's {$target->name} in " . $this->his($target) . " $clothes! You crash through a window and run like hell.";
        $note = "\n`wYou caught {$player->name} sneaking around inside your parlor!`\$\n";
        if ($stolen['count'] === 1) {
            $note .= 'You scare ' . $this->him($player) . " off.  You notice `w{$stolen['count']} {$stolen['label']} tag `\$is missing.";
        } elseif ($stolen['count'] > 1) {
            $note .= 'You scare ' . $this->him($player) . " off.  You notice `w{$stolen['count']} {$stolen['label']} tags `\$are missing.";
        } else {
            $note .= 'You scare ' . $this->him($player) . ' off before ' . $this->he($player) . ' could steal anything.';
        }
        $this->postMail($target, $note, 'flash/people/man_prowl.swf');
        $this->endProwl($session);
        $this->store->save();
        return $this->refreshVisit([
            'message' => $message,
            'buttons' => [['key' => '3', 'label' => 'Leave', 'command' => 38]],
        ]);
    }

    private function compTryDoor(object $session, object $player): string
    {
        $target = $this->foe($session);
        if (!$target || empty($session->prowling)) {
            return $this->compLobby($session, $player);
        }
        if ((int) $this->num($target, 'padlock') > 0) {
            $holding = (int) $this->num($player, 'lockpicks') > 0 ? "\n\nYou are holding `w" . fq_cash((int) $player->lockpicks) . '`y lockpick(s).' : '';
            $text = (int) $this->num($player, 'lockpicks') > 0
                ? "There is a heavy padlock on the door at `w{$target->padlockStrength} percent strength`y. What do you want to do?$holding"
                : "There is a heavy padlock on the door at `w{$target->padlockStrength} percent strength`y.  What do you want to do?";
            return $this->doorMenu($player, $text);
        }
        return $this->compInside($session, $player);
    }

    private function compPickLock(object $session, object $player): string
    {
        $target = $this->foe($session);
        if (!$target || empty($session->prowling)) {
            return $this->compLobby($session, $player);
        }
        if ((int) $this->num($player, 'lockpicks') < 1) {
            return $this->doorMenu($player, 'You attempt to pick the lock with your finger.  No go.  Perhaps if you had a real lockpick...');
        }
        $weakened = $this->between(5, 10);
        $target->padlockStrength = (int) $this->num($target, 'padlockStrength') - $weakened;
        $player->lockpicks = (int) $this->num($player, 'lockpicks') - 1;
        if ((int) $target->padlockStrength < 1) {
            $target->padlock = 0;
            $target->padlockStrength = 0;
            $message = 'As you are picking the lock the whole thing breaks and falls off!  Too bad your lockpick got stuck inside it.';
            $this->postMail($target, '`yYour padlock is `4damaged beyond repair`y by a prowler and falls off!');
        } else {
            $message = "`yYou successfully pick the lock.  Your lockpick breaks as you try to get it out which causes the padlock `w$weakened`y points of damage.";
            $this->postMail($target, "`yYour padlock is weakened by `4$weakened`y down to `w{$target->padlockStrength} percent strength`y by a prowler.");
        }
        $this->store->save();
        return $this->refreshVisit([
            'message' => $message,
            'buttons' => [['key' => '3', 'label' => 'Continue', 'command' => 102, 'sub' => 10]],
        ]);
    }

    private function swingDamage(int $strength): int
    {
        $half = ($strength + 1) / 2;
        return (int) floor($this->frandom($half) + $half);
    }

    private function hitVerb(int $amount, bool $mine): string
    {
        $bands = [[1, 2, 'slap', 'slaps'], [3, 4, 'sock', 'socks'], [5, 6, 'punch', 'punches'], [7, 8, 'kick', 'kicks'], [9, 10, 'throw', 'throws'], [11, 12, 'squeeze', 'squeezes'], [13, 14, 'ninja', "ninja's"]];
        foreach ($bands as $band) {
            if ($amount >= $band[0] && $amount <= $band[1]) {
                return $mine ? $band[2] : $band[3];
            }
        }
        return $mine ? 'slam' : 'slams';
    }

    private function battlePoints(object $winner, object $loser, bool $defense): array
    {
        $won = (int) floor((int) $this->num($loser, 'exp') * ($defense ? 0.01 : 0.02));
        $lost = (int) floor((int) $this->num($loser, 'exp') * ($defense ? 0.005 : 0.01));
        $apply = function (object $who, int $amount): void {
            $who->buryPoints = max(0, (int) $this->num($who, 'buryPoints') + $amount);
            $who->exp = max(0, (int) $this->num($who, 'exp') + $amount);
        };
        $apply($loser, -$lost);
        $apply($winner, $won);
        return ['won' => $won, 'lost' => $lost];
    }

    private function battleScreen(object $player, object $target, string $message, array $buttons, ?string $artUrl = null, bool $blankBackground = false): string
    {
        $this->room(103);
        return $this->placeScreen($player, [
            'background' => $blankBackground ? 'flash/blank.swf' : 'flash/places/funeral_enemy.swf',
            'foreground' => $artUrl ?: 'flash/music/music_fight.swf',
            'info' => 'You are in battle.',
            'message' => $message,
            'buttons' => $buttons,
            'battle' => true,
            'extra' => [
                ['st_stats_me', "{$player->name} - {$player->hp} of {$player->hpMax} hitpoints"],
                ['st_stats_him', "{$target->name} - {$target->hp} of {$target->hpMax} hitpoints"],
                ['i_hp_me', $player->hp],
                ['i_hp_him', $target->hp],
                ['i_hp_max_me', $player->hpMax],
                ['i_hp_max_him', $target->hpMax],
            ],
        ]);
    }

    private function battleMenus(): array
    {
        return [
            ['key' => '2', 'label' => 'Attack', 'command' => 103, 'sub' => 2],
            ['key' => '3', 'label' => 'Run', 'command' => 103, 'sub' => 1],
        ];
    }

    private function attackerSwing(object $player, object $target): string
    {
        $hit = $this->swingDamage((int) $this->num($player, 'strength'));
        if ($hit === 0) {
            return "{$target->name} dodges your swing.\n\n";
        }
        if (random_int(0, 9) === 0) {
            $hit *= 2;
            $target->hp -= $hit;
            return "You `wsoul smash`\$ {$target->name} for `4$hit`\$ damage!\n\n";
        }
        $target->hp -= $hit;
        return 'You ' . $this->hitVerb($hit, true) . " {$target->name} for `4$hit`\$ damage.\n\n";
    }

    private function defenderSwing(object $player, object $target): string
    {
        $hit = $this->swingDamage((int) $this->num($target, 'strength'));
        if (random_int(0, 19) === 0 || $hit === 0) {
            return "{$target->name} swings but you sidestep the blow.\n\n";
        }
        $player->hp -= $hit;
        return $target->name . ' ' . $this->hitVerb($hit, false) . " you for `4$hit`\$ damage.\n\n";
    }

    private function finishBattle(object $session, object $player, object $target, string $lead): ?string
    {
        if ((int) $target->hp < 1) {
            $target->hp = 0;
            $target->police = true;
            $target->inHospital = true;
            $stolen = $this->stealTags($target, $player, 4);
            $points = $this->battlePoints($player, $target, false);
            $pocket = $stolen['count'] < 1
                ? ' Nothing was stolen.'
                : ($stolen['count'] === 1
                    ? " `w{$stolen['count']}`\$ {$stolen['label']} tag was stolen from your pocket."
                    : " `w{$stolen['count']}`\$ {$stolen['label']} tags were stolen from your pockets.");
            $this->postMail($target, "\n`wYou were attacked in your sleep by {$player->name}!`\$\n\nYou are badly injured.  You vaguely remember riding in an ambulance.\n\n`\$You lose `w" . fq_cash($points['lost']) . " `\$Bury Points.$pocket", 'flash/stuff/battle_lose.swf');
            $this->tv("It looks like {$target->name} was taken to the hospital.");
            $found = $stolen['count'] < 1
                ? 'You go through ' . $this->his($target) . ' pockets but find nothing.'
                : ($stolen['count'] === 1
                    ? 'You go through ' . $this->his($target) . " pockets and find `w{$stolen['count']} `\$" . $stolen['label'] . ' tag.'
                    : 'You go through ' . $this->his($target) . " pockets and find `w{$stolen['count']} `\$" . $stolen['label'] . ' tags.');
            $session->battle = false;
            $session->prowling = false;
            $this->store->save();
            return $this->refreshVisit([
                'url' => 'flash/stuff/money_bag_2.swf',
                'message' => $lead . "{$target->name} crumples to the floor. You have won the battle!\n\nYou earn `w" . fq_cash($points['won']) . " `\$Bury Points. $found",
                'buttons' => [['key' => '3', 'label' => 'Continue', 'command' => 103, 'sub' => 3]],
                'extra' => $this->battleMeters($player, $target),
            ]);
        }
        if ((int) $player->hp < 1) {
            $player->hp = 0;
            $player->inHospital = true;
            $points = $this->battlePoints($target, $player, true);
            $this->postMail($target, "\n`wYou were attacked in your sleep by {$player->name}!`\$\n\nYou woke up and kicked " . $this->his($player) . " ass!\n\n`\$You earn `w" . fq_cash($points['won']) . " `\$Bury Points. (applied to day {$this->store->state->day})", 'flash/places/hospital_ambulance.swf');
            $this->tv("It looks like {$player->name} was taken to the hospital.");
            $session->battle = false;
            $session->prowling = false;
            $this->store->save();
            return $this->refreshVisit([
                'url' => 'flash/stuff/battle_lose.swf',
                'background' => 'flash/blank.swf',
                'message' => $lead . "You fall down, unable to get up.\n\nYou lose `w" . fq_cash($points['lost']) . ' `$Bury Points.',
                'buttons' => [['key' => '3', 'label' => 'Continue', 'command' => 103, 'sub' => 4]],
                'extra' => $this->battleMeters($player, $target),
            ]);
        }
        return null;
    }

    private function compAttack(object $session, object $player): string
    {
        $target = $this->foe($session);
        if (!$target || empty($session->prowling)) {
            return $this->compLobby($session, $player);
        }
        $player->hp = $player->hpMax ?? 10;
        $target->hp = $target->hpMax ?? 10;
        $session->battle = true;
        $lines = ["Stop stealing my customers!", 'I will BURY you!', "Messing with me was a grave mistake, dear {$target->name}.", ($target->male ?? true) === false ? 'Discount this, bitch!' : 'Discount this, bastard!', 'I was in this town first!', "You're an amateur! You don't belong here!"];
        $message = '`w"' . $lines[random_int(0, 5)] . '" `$you scream as you break into ' . $this->his($target) . " room.\n\n";
        if ((int) $this->num($player, 'plots') > (int) $this->num($target, 'plots')) {
            $message .= "{$target->name} is completely surprised, you get first swing!\n\n";
        } else {
            $message .= "{$target->name}'s eyes flick open, " . $this->he($target) . " knew you were in the house!\n\n";
            $message .= $this->defenderSwing($player, $target);
            $done = $this->finishBattle($session, $player, $target, $message);
            if ($done) {
                return $done;
            }
        }
        $this->store->save();
        return $this->battleScreen($player, $target, $message, $this->battleMenus());
    }

    private function battleAttack(object $session, object $player): string
    {
        $target = $this->foe($session);
        if (!$target || empty($session->battle) || (int) $player->hp <= 0 || (int) $target->hp <= 0) {
            return $this->scene([['g_st_error', 'Your FQ client is acting oddly.  (Be careful, this has been logged...)'], ['i_sent', self::SENT['fail']]]);
        }
        $message = $this->attackerSwing($player, $target);
        $won = $this->finishBattle($session, $player, $target, $message);
        if ($won) {
            return $won;
        }
        $message .= $this->defenderSwing($player, $target);
        $lost = $this->finishBattle($session, $player, $target, $message);
        if ($lost) {
            return $lost;
        }
        $this->store->save();
        return $this->refreshVisit([
            'message' => $message,
            'buttons' => $this->battleMenus(),
            'extra' => $this->battleMeters($player, $target),
        ]);
    }

    private function battleMeters(object $player, object $target): array
    {
        return [
            ['st_stats_me', "{$player->name} - {$player->hp} of {$player->hpMax} hitpoints"],
            ['st_stats_him', "{$target->name} - {$target->hp} of {$target->hpMax} hitpoints"],
            ['i_hp_me', $player->hp],
            ['i_hp_him', $target->hp],
        ];
    }

    private function battleRun(object $session, object $player): string
    {
        $target = $this->foe($session);
        if (!$target || empty($session->battle) || (int) $player->hp <= 0 || (int) $target->hp <= 0) {
            return $this->scene([['g_st_error', 'Your FQ client is acting oddly.  (Be careful, this has been logged...)'], ['i_sent', self::SENT['fail']]]);
        }
        $this->postMail($target, "\n`wYou were attacked in your sleep by {$player->name}!`\$\n\n" . $this->capHe($player) . " got scared and ran off.\n\n", 'flash/people/man_prowl.swf');
        $this->endProwl($session);
        $this->store->save();
        return $this->refreshVisit([
            'message' => 'You backflip through an open window and take off running.',
            'buttons' => [['key' => '3', 'label' => 'Continue', 'command' => 38]],
        ]);
    }

    private function cleanYell(string $text): string
    {
        $clean = preg_replace('/[<>`]/', '', $text) ?? '';
        $clean = preg_replace('/\s+/', ' ', $clean) ?? '';
        return substr(trim($clean), 0, 120);
    }

    private function battleYell(string $kind): string
    {
        $message = $kind === 'win'
            ? '`wYou hear police sirens in the distance... you can yell something as you leave or just hit ENTER to continue.'
            : '`wYou feel yourself being lifted into an ambulance.  Mutter something or just hit ENTER to continue.';
        return $this->scene([
            ['st_message', $message],
            ['st_extra_text', ''],
            ['st_object', 'i_get_text'],
            ['i_return_command', 103],
            ['i_return_command_sub', $kind === 'win' ? 5 : 6],
            ['i_sent', self::SENT['add']],
        ]);
    }

    private function battleHeard(object $session, object $player, array $tokens, bool $won): string
    {
        $target = $this->foe($session);
        $spoken = $this->cleanYell((string) ($tokens['st_extra_text'] ?? ''));
        $quiet = ($tokens['st_extra_text'] ?? '') === '12ABORT12' || strlen($spoken) < 2;
        if ($target) {
            if ($won) {
                $this->crimeNews($quiet
                    ? "`8{$player->name} brutally assaulted {$target->name}!\n\n"
                    : "`8{$player->name} brutally assaulted {$target->name}!`6\n\"$spoken\" `8justified {$player->name}.\n\n");
                if (!$quiet) {
                    $this->postMail($target, "`\$While recovering you can't get what {$player->name} yelled while escaping out of your head...\n\n`w\"$spoken\"`\$", 'flash/places/hospital_room.swf');
                }
            } else {
                $this->crimeNews($quiet
                    ? "`8{$target->name} assaulted {$player->name} in self defense!\n\n"
                    : "`8{$target->name} assaulted {$player->name} in self defense!`6\n\"$spoken\" `8moaned {$player->name} from the ambulance.\n\n");
                if (!$quiet) {
                    $this->postMail($target, '`$As ' . $player->name . ' is leaving you hear ' . $this->him($player) . " moaning from the ambulance...\n\n`w\"$spoken\"`\$", 'flash/places/hospital_ambulance.swf');
                }
            }
        }
        $this->store->save();
        if ($won) {
            return $this->compLobby($session, $player);
        }
        $this->endProwl($session);
        return $this->hospitalRoom($player);
    }

    private function battleCallback(object $session, object $player, array $tokens): string
    {
        $sub = (int) ($tokens['i_command_sub'] ?? 0);
        return match ($sub) {
            1 => $this->battleRun($session, $player),
            2 => $this->battleAttack($session, $player),
            3 => $this->battleYell('win'),
            4 => $this->battleYell('lost'),
            5 => $this->battleHeard($session, $player, $tokens, true),
            6 => $this->battleHeard($session, $player, $tokens, false),
            default => $this->compLobby($session, $player),
        };
    }

    private function compCallback(object $session, object $player, array $tokens): string
    {
        $sub = (int) ($tokens['i_command_sub'] ?? 0);
        if ($sub === 1) {
            return $this->rankingScreen(38, true);
        }
        if ($sub === 3) {
            $this->endProwl($session);
            return $this->compMenu($player);
        }
        if ($sub === 4) {
            return (int) $this->num($player, 'visits') >= $this->rule('visits_per_day')
                ? $this->refreshMain("Is today look at everyone's parlor day?  No, it's not.  Maybe tomorrow.", [
                    ['key' => '3', 'label' => 'Continue', 'command' => 102, 'sub' => 3],
                ])
                : $this->compAskName();
        }
        if ($sub === 5) {
            if ((int) $this->num($player, 'visits') >= $this->rule('visits_per_day')) {
                return $this->compMenu($player);
            }
            $raw = $tokens['i_his_id'] ?? null;
            $index = $raw === null || $raw === '' ? -1 : (int) $raw;
            $target = $this->playerAt($index);
            if (!$target) {
                return $this->compMenu($player);
            }
            $session->targetIndex = $index;
            return $this->compInFront($session, $player);
        }
        if ($sub === 6) {
            return $this->compTryDoor($session, $player);
        }
        if ($sub === 7) {
            return $this->compSteal($session, $player);
        }
        if ($sub === 8) {
            return $this->compAttack($session, $player);
        }
        if ($sub === 9) {
            return $this->compPickLock($session, $player);
        }
        if ($sub === 10) {
            return $this->compInside($session, $player);
        }
        return $this->compLobby($session, $player);
    }

    private function rankOf(object $player): array
    {
        $everyone = array_values($this->store->state->players);
        usort($everyone, function ($a, $b) {
            $exp = (int) $this->num($b, 'exp') <=> (int) $this->num($a, 'exp');
            return $exp !== 0 ? $exp : ((int) $a->id <=> (int) $b->id);
        });
        $index = -1;
        foreach ($everyone as $at => $entry) {
            if ((string) $entry->id === (string) $player->id) {
                $index = $at;
                break;
            }
        }
        $total = count($everyone);
        if ($index < 0 || $total < 1) {
            return ['rank' => 1, 'percent' => 100];
        }
        $percent = 100 - (int) (($index / $total) * 100);
        return ['rank' => $index + 1, 'percent' => min(100, max(1, $percent))];
    }

    private function statPacket(object $person): string
    {
        $standing = $this->rankOf($person);
        $rating = $this->ratings[$standing['percent']] ?? '(No text for this rating.  Email Seth some ideas!)';
        $fields = [
            ...$this->stats($person),
            ['i_plots', fq_cash((int) $this->num($person, 'plots'))],
            ['i_psych', 'Level ' . fq_cash((int) $this->num($person, 'psych') + 1)],
            ['st_rating', '#' . $standing['rank'] . ' - ' . $rating],
            ['i_yangs', fq_cash($this->rule('yangs_per_day') - (int) $this->num($person, 'yangsSent'))],
            ['i_visits', fq_cash($this->rule('visits_per_day') - (int) $this->num($person, 'visits'))],
            ['i_hit_max', fq_cash((int) ($person->hpMax ?? 10))],
            ['i_strength', fq_cash((int) $this->num($person, 'strength', 3))],
            ['i_lockpick', fq_cash((int) $this->num($person, 'lockpicks'))],
            ['st_ride', fq_ride($person)['name']],
        ];
        $packet = '';
        foreach ($fields as [$key, $value]) {
            $packet .= "$key=$value&";
        }
        return $packet;
    }

    private function changeNameScreen(object $player, string $message, array $buttons): string
    {
        return $this->placeScreen($player, [
            'foreground' => 'flash/stuff/question.swf',
            'info' => "You stare at yourself in the mirror.\n\nMaybe it's time for a change.",
            'message' => $message,
            'buttons' => $buttons,
        ]);
    }

    private function optionsScreen(object $player): string
    {
        $this->room(108, 'Change Name', true);
        if ($this->rule('name_change_cost') < 1) {
            return $this->placeScreen($player, [
                'foreground' => 'flash/stuff/question.swf',
                'message' => "`\$This server doesn't allow name changes.  You're stuck with what your parents gave you.",
                'buttons' => [['key' => '3', 'label' => 'Continue', 'command' => 3]],
            ]);
        }
        return $this->changeNameScreen($player, '`$Announcing your new name in the newspaper will cost `w' . $this->rule('name_change_cost') . '`$ gold tag(s).  Do it?', [
            ['key' => '2', 'label' => 'Yes', 'command' => 108, 'sub' => 2],
            ['key' => '3', 'label' => 'No', 'command' => 3],
        ]);
    }

    private function cleanPlayerName(string $text): string
    {
        $stripped = preg_replace('/[?|=\/\\\\;:,{}@+`<>%$]/', '', $text) ?? '';
        $out = '';
        $length = strlen($stripped);
        for ($i = 0; $i < $length; $i++) {
            if (ord($stripped[$i]) < 123) {
                $out .= $stripped[$i];
            }
        }
        return $out;
    }

    private function publicName(string $text): string
    {
        return $this->filterChat($this->cleanPlayerName($text));
    }

    private function optionsCallback(object $player, array $tokens): string
    {
        $sub = (int) ($tokens['i_command_sub'] ?? 0);
        if ($sub === 2) {
            return $this->scene([
                ['st_message', '`wEnter your new name.'],
                ['st_extra_text', ''],
                ['st_object', 'i_get_text'],
                ['i_return_command', 108],
                ['i_return_command_sub', 1],
                ['i_sent', self::SENT['add']],
            ]);
        }
        if ($sub !== 1) {
            return $this->optionsScreen($player);
        }
        if ($this->rule('name_change_cost') < 1) {
            return $this->optionsScreen($player);
        }
        $next = $this->publicName((string) ($tokens['st_extra_text'] ?? ''));
        if ($next === '12ABORT12') {
            return $this->optionsScreen($player);
        }
        if ((int) $this->num($player, 'gold') < $this->rule('name_change_cost')) {
            return $this->refreshMain("`\$You don't have enough gold tags.  Go exchange your woods or something.", [
                ['key' => '3', 'label' => 'Continue', 'command' => 3],
            ]);
        }
        if ($next === $player->name) {
            return $this->refreshMain("`\$Yeah, $next.. you like the sound of that... oh wait...", [
                ['key' => '3', 'label' => 'Continue', 'command' => 43],
            ]);
        }
        if (strlen($next) < 3) {
            return $this->refreshMain('`$What, are those initials?  Pick a longer name.', [
                ['key' => '3', 'label' => 'Continue', 'command' => 43],
            ]);
        }
        if (strlen($next) > 20) {
            return $this->refreshMain('`$Is that a name or a lecture?  Try something shorter.', [
                ['key' => '3', 'label' => 'Continue', 'command' => 43],
            ]);
        }
        $taken = $this->store->findName($next);
        if ($taken && (string) $taken->id !== (string) $player->id) {
            return $this->refreshMain("`\$Um, don't you think the real $next would kind of be pissed?  Try something original.", [
                ['key' => '3', 'label' => 'Continue', 'command' => 43],
            ]);
        }
        $previous = $player->name;
        $player->gold = (int) $this->num($player, 'gold') - $this->rule('name_change_cost');
        $player->name = $next;
        $this->addNews("`5NAME CHANGE: $previous has changed " . $this->his($player) . " name to $next.\n\n");
        $this->store->save();
        return $this->refreshMain("`\$You will now be known as `w$next`\$.", [
            ['key' => '3', 'label' => 'Continue', 'command' => 3],
        ], $this->tagFields($player));
    }

    private function filterChat(string $text): string
    {
        $out = $text;
        if (!$this->rule('swear_filter')) {
            return $out;
        }
        foreach ($this->swearWords as [$bad, $good]) {
            $out = preg_replace('/' . preg_quote($bad, '/') . '/i', $good, $out) ?? $out;
        }
        return $out;
    }

    private function linkify(string $text): string
    {
        $work = $text;
        while (true) {
            $start = strpos($work, '[');
            $end = $start === false ? false : strpos($work, ']', $start + 1);
            if ($start === false || $end === false) {
                return $work;
            }
            $url = str_replace(['[', ']'], '', substr($work, $start + 1, $end - $start - 1));
            $link = "`#<a href=\"http://$url\" target=\"_blank\">$url`6</a>";
            $work = substr($work, 0, $start) . $link . substr($work, $end + 1);
        }
    }

    private function announce(object $session, object $player, array $tokens): string
    {
        $message = $this->linkify(preg_replace('/[<&^*+]/', '', str_replace('`', '', $this->filterChat((string) ($tokens['st_note'] ?? '')))) ?? '');
        $this->addNews("`8{$player->name} of {$player->funeralName} makes an announcement:`6\n");
        $this->addNews("$message\n\n");
        $this->store->save();
        return $this->showFront($session, (int) ($this->store->state->day ?? 1));
    }

    private function sendMail(object $player, array $tokens): string
    {
        $index = (int) ($tokens['i_his_id'] ?? -1);
        $target = $this->playerAt($index);
        if (!$target || !array_key_exists('i_his_id', $tokens) || !is_numeric($tokens['i_his_id'])) {
            return $this->scene([
                ['st_object', 'i_message'],
                ['st_message', "Can't email, player doesn't exist?"],
                ['i_sent', self::SENT['add']],
            ]);
        }
        $inbox = $this->list($target, 'inbox');
        $inbox[] = (object) [
            'letter' => true,
            'fromIndex' => $this->playerIndex($player),
            'fromName' => $player->name,
            'body' => (string) ($tokens['st_input'] ?? ''),
        ];
        $target->inbox = $inbox;
        $this->pushChatTo($target->id, 'You have mail waiting for you at your parlor.');
        $this->logMsg($player->name . ' wrote email to ' . $target->name . ': ' . (string) ($tokens['st_input'] ?? ''));
        $this->store->save();
        return $this->scene([['i_sent', self::SENT['callback']]]);
    }

    private function cafeRoster(string $sessionId): string
    {
        $names = [];
        foreach ($this->store->state->sessions as $id => $entry) {
            if (empty($entry->chatting) || $entry->playerId === null || (string) $id === $sessionId) {
                continue;
            }
            $person = $this->store->player((string) $id);
            if ($person) {
                $names[] = $person->name;
            }
        }
        if (!$names) {
            return '`$You are the only one here.';
        }
        $list = '';
        foreach ($names as $index => $name) {
            $list .= $index < count($names) - 1 ? " $name," : " $name";
        }
        return "`\$Other people here:$list";
    }

    private function openCafe(object $session, object $player): string
    {
        $session->chatting = true;
        $this->room(0, 'in cafe');
        $this->pushChat("`5{$player->name} saunters in.");
        $text = "`\$The `wPale Horse `\$has been here since before you can remember.  A great place to gossip or just sip a mochachino and watch tv.\n\nYou always wondered why they had that big glass bottle on the roof.\n\n" . $this->cafeRoster((string) $session->id) . "\nEnter /? for help.\n\n";
        $this->store->save();
        return $this->scene([
            ...$this->stats($player),
            ['st_message', $text],
            ['st_object', 'i_chat'],
            ['i_chat_speed', 2000],
            ['i_sent', self::SENT['object']],
        ]);
    }

    private function cafePerson(string $name): ?object
    {
        $needle = strtolower($name);
        foreach ($this->store->state->sessions as $id => $entry) {
            if (empty($entry->chatting) || $entry->playerId === null) {
                continue;
            }
            $person = $this->store->player((string) $id);
            if ($person && str_contains(strtolower((string) $person->name), $needle)) {
                return $person;
            }
        }
        return null;
    }

    private function giveTag(object $player, string $text): void
    {
        $parts = explode(' ', $text);
        $target = $this->cafePerson($parts[1] ?? '');
        if (!$target) {
            $this->pushChatTo($player->id, '`5You look around the room but don\'t see anyone with `w\'' . ($parts[1] ?? '') . '\'`5 in their name.');
            return;
        }
        $kind = strtolower($parts[2] ?? '');
        $label = ['wood' => 'Wood', 'silver' => 'Silver', 'gold' => 'Gold'][$kind] ?? null;
        if (!$label) {
            $this->pushChatTo($player->id, '`5Valid tag types are wood, silver and gold.');
            return;
        }
        if ((int) $this->num($player, $kind) < 1) {
            $this->pushChat("`5{$player->name} looks in his pocket for a $label tag but comes up empty.");
            return;
        }
        if ((string) $target->id === (string) $player->id) {
            $this->pushChat("`5{$player->name} absently tosses a $label tag into the air and catches it.");
            return;
        }
        $received = (int) $this->num($target, 'tagsReceived');
        if ($received >= $this->rule('tags_per_day')) {
            $reason = $received === 0 ? 'the admin has disabled tag transfer' : "he's already received $received today";
            $this->pushChat("`5{$player->name} is unable to give `w{$target->name} `5a $label tag. ($reason)");
            return;
        }
        $player->{$kind} = (int) $this->num($player, $kind) - 1;
        $target->{$kind} = (int) $this->num($target, $kind) + 1;
        $target->tagsReceived = $received + 1;
        $this->pushChat("`5{$player->name} gives `w{$target->name} `5a $label tag.");
    }

    private function cafeCommand(object $player, string $text): void
    {
        $command = strtolower(explode(' ', $text)[0] ?? '');
        if ($command === '?') {
            $this->pushChatTo($player->id, "\n** `\$Cafe chat help **`w\n\n`w/yell `\$to send a message to everyone\n`w/me`\$ to do a custom action\n`w/all `\$to see who is online now\n`w/give name tag_type `\$to give a tag. Example: `w/give Seth gold\n`w/info `\$to show how many BP's, turns and tags you have now.\n`w/tv on (or off) `\$to control the cafe tv.\n");
            return;
        }
        if ($command === 'give') {
            $this->giveTag($player, $text);
            return;
        }
        if ($command === 'info') {
            $visits = $this->rule('visits_per_day') - (int) $this->num($player, 'visits');
            $this->pushChatTo($player->id, '`5You have `w' . (int) $this->num($player, 'turns') . "`5 turns left today and `w$visits`5 parlor visits.\nToday's BP's: `w" . fq_cash((int) $this->num($player, 'buryPoints')) . '`5, total BP\'s: `w' . fq_cash((int) $this->num($player, 'exp')) . "`5.\nCasket tags: `w" . (int) $this->num($player, 'wood') . '`5 Wood, `w' . (int) $this->num($player, 'silver') . '`5 Silver, `w' . (int) $this->num($player, 'gold') . '`5 Gold.');
            return;
        }
        if ($command === 'who') {
            $found = '';
            foreach ($this->store->state->sessions as $id => $entry) {
                if ((string) ($entry->playerId ?? '') === (string) $player->id) {
                    $found = (string) $id;
                    break;
                }
            }
            $this->pushChatTo($player->id, $this->cafeRoster($found));
            return;
        }
        if ($command === 'tv') {
            $setting = explode(' ', $text)[1] ?? '';
            if ($setting === '') {
                $this->pushChatTo($player->id, ($this->store->state->tvOn ?? true) === false ? '`5The TV is currently off, that stuff will rot your brain.' : '`5The TV is currently on.');
            } elseif (strtolower($setting) === 'on') {
                $this->store->state->tvOn = true;
                $this->pushChatTo($player->id, '`5You turn the tv on.');
            } else {
                $this->store->state->tvOn = false;
                $this->pushChatTo($player->id, '`5You turn the tv off.');
            }
            return;
        }
        if ($command === 'me') {
            $this->pushChat('`$' . $player->name . ' ' . $this->filterChat(substr($text, 3)));
            return;
        }
        if ($command === 'yell') {
            $this->postYell($player, substr($text, 5));
            return;
        }
        if ($command === 'all') {
            $rows = ["\n`wName                 Status            Last hit", '`$-----------------------------------------------'];
            $now = (int) (microtime(true) * 1000);
            foreach ($this->store->state->sessions as $entry) {
                if ($entry->playerId === null) {
                    continue;
                }
                $person = $this->store->playerById($entry->playerId);
                if (!$person) {
                    continue;
                }
                $seconds = max(1, (int) floor(($now - (int) ($entry->lastPacket ?? $now)) / 1000));
                if ($seconds >= 86400) {
                    $span = [(int) round($seconds / 86400), 'Day'];
                } elseif ($seconds >= 3600) {
                    $span = [(int) round($seconds / 3600), 'Hr'];
                } elseif ($seconds >= 60) {
                    $span = [(int) round($seconds / 60), 'Min'];
                } else {
                    $span = [$seconds, 'Sec'];
                }
                $when = str_pad((string) $span[0], 6, ' ', STR_PAD_LEFT) . ' ' . $span[1];
                $rows[] = str_pad((string) $person->name, 20) . str_pad((string) ($entry->location ?? 'Parlor'), 12) . '  ' . $when;
            }
            foreach ($rows as $row) {
                $this->pushChatTo($player->id, $row);
            }
            return;
        }
        $this->pushChatTo($player->id, 'Unknown command.  Do /? for the list.');
    }

    private function postYell(object $player, string $text): void
    {
        $this->townNotice($player->name . ' yells: ' . $this->filterChat($text));
    }

    private function townNotice(string $text, ?string $except = null): void
    {
        $when = (new DateTimeImmutable('now'))->format('g:i A');
        $this->townBroadcast($when . ':' . $text, $except);
        $this->logMsg($when . ':' . $text);
    }

    private function townBroadcast(string $line, ?string $except = null): void
    {
        foreach ($this->store->state->sessions as $id => $entry) {
            if ($entry->playerId === null || ($except !== null && (string) $id === $except)) {
                continue;
            }
            $pending = $this->list($entry, 'pendingYells');
            $pending[] = $line;
            $entry->pendingYells = $pending;
        }
    }

    private function takeYells(object $session, string $ending): string
    {
        $pending = $this->list($session, 'pendingYells');
        if (!$pending) {
            return '';
        }
        $session->pendingYells = [];
        $body = '';
        foreach ($pending as $line) {
            $body .= '`w' . $line . $ending;
        }
        return $body;
    }

    private function processChat(object $player, string $input): void
    {
        $rest = $input;
        while ($rest !== '') {
            $bar = strpos($rest, '|');
            $line = $bar === false ? $rest : substr($rest, 0, $bar);
            $rest = $bar === false ? '' : substr($rest, $bar + 1);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '/')) {
                $this->logMsg($player->name . ' does a ' . $line);
                $this->cafeCommand($player, substr($line, 1));
            } else {
                $this->pushChat("`\${$player->name}: `w" . $this->filterChat($line) . '<font size="12">');
            }
        }
    }

    private function chatPoll(object $session, object $player, array $tokens): string
    {
        $input = (string) ($tokens['st_input'] ?? '');
        $sending = !array_key_exists('b_send_input', $tokens) || $tokens['b_send_input'] === '' || $tokens['b_send_input'] === '1';
        if ($sending && $input !== '') {
            $this->processChat($player, $input);
            $this->store->save();
        }
        $lines = $this->list($session, 'chatLog');
        $session->chatLog = [];
        $yells = !empty($session->chatting) ? $this->takeYells($session, "\r") : '';
        if ($lines || $yells !== '') {
            $this->store->save();
        }
        $text = '';
        foreach ($lines as $line) {
            // Ruffle drops a line break that sits between two tags, so it goes before any trailing tags.
            $text .= preg_replace('/(?:<[^<>]*>)*$/', "\r$0", (string) $line, 1);
        }
        return $this->scene([
            ['st_message', $yells . $text],
            ['i_sent', 24],
        ]);
    }
}
