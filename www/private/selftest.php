<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/src/boot.php';

$dir = sys_get_temp_dir() . '/fq-' . bin2hex(random_bytes(4));
mkdir($dir);
$game = fq_game($dir . '/players.json');
$store = $game->store;
$failed = 0;

function expect(string $label, string $body, array $includes): void
{
    global $failed;
    $ok = true;
    foreach ($includes as $part) {
        if (!str_contains($body, $part)) {
            $ok = false;
            break;
        }
    }
    if (!$ok) {
        $failed++;
        fwrite(STDERR, "FAIL $label\n" . substr($body, 0, 500) . "\n");
    } else {
        echo "ok $label\n";
    }
}

$quoteSlips = [];
foreach (glob(__DIR__ . '/src/*.php') as $source) {
    foreach (token_get_all((string) file_get_contents($source)) as $token) {
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && $token[1][0] === "'"
            && preg_match('/(?<!\\\\)\\\\[$n"]/', $token[1])) {
            $quoteSlips[] = basename($source) . ':' . $token[2] . ' ' . $token[1];
        }
    }
}
if ($quoteSlips) {
    $failed++;
    fwrite(STDERR, "FAIL single-quoted strings send a stray backslash\n  " . implode("\n  ", $quoteSlips) . "\n");
} else {
    echo "ok no single-quoted string sends a stray backslash\n";
}

expect('unknown color codes leave a backtick', fq_seth_ansi('`wa`ab`zc`f'), ['<font color="#FFFFFF">a`b`c`']);

$intro = $game->handle('/request.xp?checklogon', 'test');
expect('intro', $intro, ['i_sent=1']);

$bad = $game->handle('/request.xp?new&name=Al&logon=albert&f_name=Rest&password=a&password_v=a&email=a@b.co&b_male=1', 'test');
expect('short name', $bad, ['i_sent=4']);

$stripped = $game->handle('/request.xp?new&name=A@B&logon=shorty&f_name=Restful&password=secret&password_v=secret&email=a@b.co&b_male=1', 'test');
expect('name shortened by stripping', $stripped, ['i_sent=4']);

$washed = $game->handle('/request.xp?new&name=FuCk@Boy&logon=washed&f_name=Shi@tHouse&password=secret&password_v=secret&email=w@b.co&b_male=1', 'wash');
expect('cleaned parlor name', $washed, ['i_sent=5', 'fudgeBoy', 'shootHouse']);

$created = $game->handle('/request.xp?new&name=Albert&logon=albert&f_name=Restful&password=secret&password_v=secret&email=albert@example.com&b_male=1', 'test');
expect('create', $created, ['i_sent=5', 'Restful']);

$unknown = $game->handle('/request.xp?logon&st_logon=albert&st_password=wrong', 'other');
expect('bad password', $unknown, ['i_sent=2']);

$logged = $game->handle('/request.xp?logon&st_logon=albert&st_password=secret', 'other');
expect('logon', $logged, ['i_sent=5']);

$desk = $game->handle('/request.xp?command&i_command=4', 'other');
expect('morning', $desk, ['morning_coffee.swf', 'i_sent=10']);

$home = $game->handle('/request.xp?command&i_command=4', 'other');
expect('desk', $home, ['flash/places/desk.swf', 'i_sent=10']);

$customer = '';
for ($attempt = 0; $attempt < 40 && !str_contains($customer, 'funeral+service+for+my'); $attempt++) {
    $customer = $game->handle('/request.xp?command&i_command=2', 'other');
    if (str_contains($customer, 'i_command_3=31')) {
        $game->handle('/request.xp?command&i_command=31', 'other');
    }
}
expect('customer', $customer, ['flash/people/', 'funeral+service+for+my', 'Total%3A', 'i_command_3=30', 'i_sent=2']);

$sitting = $store->session('other')->customer;
$sitting->cheapness = 0;
$sitting->moodValue = 0;
$sitting->budget = 500000;
$sitting->loved = false;
$soft = $game->handle('/request.xp?command&i_command=25', 'other');
expect('soft sell', $soft, ['Thank+you', "I'll+take+it", 'Total%3A', 'i_command_2=25']);
if (count($sitting->options) !== 1) {
    $failed++;
    fwrite(STDERR, 'FAIL soft sell options ' . count($sitting->options) . "\n");
} else {
    echo "ok soft sell added an extra\n";
}
$sitting->cheapness = 0;
$sitting->moodValue = 1;
$sitting->casketIndex = 0;
$sitting->casket = (object) ['id' => 'wood', 'label' => 'wood', 'price' => 800, 'points' => 1];
$sitting->upgrade = -1;
$sitting->options = [];
$sitting->budget = 500000;
$harder = $game->handle('/request.xp?command&i_command=26', 'other');
expect('hard sell', $harder, ['Silver+Casket']);
if ((int) $sitting->casketIndex !== 1) {
    $failed++;
    fwrite(STDERR, "FAIL casket index {$sitting->casketIndex}\n");
} else {
    echo "ok casket upgraded\n";
}
$sitting->loved = false;
$sitting->cheapness = 5;
$sitting->moodValue = 1;
$guilt = $game->handle('/request.xp?command&i_command=29', 'other');
expect('guilt', $guilt, ['Total%3A']);
if (!($sitting->cheapness < 5)) {
    $failed++;
    fwrite(STDERR, "FAIL guilt cheapness {$sitting->cheapness}\n");
} else {
    echo "ok guilt loosened the purse\n";
}
$sitting->loved = true;
$sitting->cheapness = 5;
$sitting->moodValue = 1;
$sympathy = $game->handle('/request.xp?command&i_command=27', 'other');
expect('sympathy', $sympathy, ['Total%3A']);
if (!($sitting->cheapness < 5)) {
    $failed++;
    fwrite(STDERR, "FAIL sympathy cheapness {$sitting->cheapness}\n");
} else {
    echo "ok sympathy loosened the purse\n";
}
$reloaded = array_filter(['soft' => $soft, 'hard' => $harder, 'guilt' => $guilt, 'sympathy' => $sympathy], fn ($body) => preg_match('/(^|&)st_url(_bg)?=|(^|&)st_class=|(^|&)i_money=/', $body));
if ($reloaded) {
    $failed++;
    fwrite(STDERR, 'FAIL sales pitches resend the customer movie (doorbell): ' . implode(', ', array_keys($reloaded)) . "\n");
} else {
    echo "ok sales pitches keep the customer on screen like the original\n";
}

$done = $game->handle('/request.xp?command&i_command=30', 'other');
expect('sale finished', $done, ['money_bag_2.swf', 'service+is+a+success', 'sales+tag', 'i_sent=2']);

$parlor = $store->session('other');
$proprietor = $store->player('other');
$pointsBefore = $proprietor->buryPoints;
$parlor->customer = (object) ['waiting' => true];
$parlor->risk = 0;
$ring = $game->handle('/request.xp?command&i_command=31', 'other');
expect('return the ring', $ring, ['the+ring', 'Thank+you+so+much', 'Bury+Point', 'i_sent=2']);
if ($proprietor->buryPoints < $pointsBefore + 4 || $parlor->customer) {
    $failed++;
    fwrite(STDERR, "FAIL ring points={$proprietor->buryPoints}\n");
} else {
    echo "ok ring returned\n";
}

$sentKeys = function (string $body): array {
    $keys = [];
    foreach (explode('&', $body) as $pair) {
        if (str_contains($pair, '=')) {
            $keys[] = explode('=', $pair, 2)[0];
        }
    }
    sort($keys);
    return array_values(array_unique($keys));
};
$expectKeys = function (string $label, string $body, array $wanted) use ($sentKeys): void {
    global $failed;
    sort($wanted);
    $got = $sentKeys($body);
    if ($got !== $wanted) {
        $failed++;
        fwrite(STDERR, "FAIL $label sends " . implode(',', $got) . "\n");
    } else {
        echo "ok $label sends only the original fields\n";
    }
};
$expectKeys('risk accepted', $ring, ['b_kill_buttons', 'i_command_3', 'i_sent', 'i_sp', 'st_main', 'st_option_3', 'st_url']);
$parlor->customer = (object) ['waiting' => true];
$parlor->risk = 0;
$expectKeys('risk taken', $game->handle('/request.xp?command&i_command=11', 'other'), ['b_kill_buttons', 'i_command_3', 'i_sent', 'i_sp', 'st_main', 'st_option_3', 'st_url']);
$townCall = fn (string $method, ...$args) => (new ReflectionMethod($game, $method))->invoke($game, ...$args);
$riskOffer = $townCall('showRisk', $proprietor, (new ReflectionProperty($game, 'risks'))->getValue($game)[0]);
$expectKeys('risk offer', $riskOffer, ['b_kill_buttons', 'i_command_2', 'i_command_3', 'i_sent', 'i_turns', 'st_main', 'st_option_2', 'st_option_3', 'st_url', 'st_url_bg']);
$special = $townCall('specialEvent', $proprietor);
$expectKeys('special event', (string) $special, ['i_exp', 'i_luck', 'i_sent', 'i_sp', 'i_turns', 'st_main', 'st_url']);

$saved = json_decode((string) file_get_contents($dir . '/players.json'));
$albert = null;
foreach ($saved->players as $player) {
    if ($player->logon === 'albert') {
        $albert = $player;
    }
}
expect('hashed password', json_encode($albert), ['"passwordHash":']);
if (isset($albert->password)) {
    $failed++;
    fwrite(STDERR, "FAIL plaintext password was stored\n");
}

$teller = $store->player('other');
$teller->wood = 5;
$teller->silver = 2;
$teller->gold = 1;
$store->save();
$closed = $game->handle('/request.xp?command&i_command=104&i_command_sub=18', 'other');
expect('bank stays closed from the desk', $closed, ['unable+to+process', 'i_sent=-1']);
if ((int) $teller->wood !== 5 || (int) $teller->silver !== 2 || (int) $teller->gold !== 1) {
    $failed++;
    fwrite(STDERR, "FAIL bank trade ran from the desk\n");
} else {
    echo "ok bank callback ignored away from the bank\n";
}
$bank = $game->handle('/request.xp?command&i_command=39', 'other');
expect('bank', $bank, ['flash/places/bank.swf', 'st_generic_info=You+are+at+the+city+bank', 'transfer+them+to+other+accounts', "How's+business", 'st_url=&', 'st_object=i_generic', 'i_sent=10', 'All+to+wood', 'i_command_sub_2=1']);
$toWood = $game->handle('/request.xp?command&i_command=104&i_command_sub=18', 'other');
expect('all to wood', $toWood, ['i_coffin_wood=13', 'i_coffin_silver=0', 'i_coffin_gold=0', 'i_sent=33']);
$howMany = $game->handle('/request.xp?command&i_command=104&i_command_sub=11', 'other');
expect('exchange prompt names the tags', $howMany, ['4+Wood+tag(s)+=+1+Gold+tag(s).', 'How+many+Wood+tags', 'st_extra_text=13', 'i_get_text', 'i_sent=14']);
if (str_contains($howMany, 'st_url=') || str_contains($howMany, 'i_money=')) {
    $failed++;
    fwrite(STDERR, "FAIL exchange prompt reloaded the screen\n" . substr($howMany, 0, 400) . "\n");
} else {
    echo "ok exchange prompt is only the question box\n";
}
$traded = $game->handle('/request.xp?command&i_command=104&i_command_sub=5&st_extra_text=4', 'other');
expect('wood to gold', $traded, ['i_coffin_wood=9', 'i_coffin_gold=1', 'converted+4+Wood', 'Gold+tag', 'i_sent=33']);
$tooSmall = $game->handle('/request.xp?command&i_command=104&i_command_sub=5&st_extra_text=1', 'other');
expect('bank refuses a short trade', $tooSmall, ["can't+trade+just+1"]);

$teller->gold = 10;
$teller->transport = 0;
$teller->turns = 15;
$teller->lockpicks = 0;
$teller->smoke = 0;
$store->save();
$shop = $game->handle('/request.xp?command&i_command=40', 'other');
expect('di-mart', $shop, ['store.swf', 'store_guy.swf', 'st_generic_info=', 'Elijah', 'Hearses']);
$hearses = $game->handle('/request.xp?command&i_command=105&i_command_sub=100', 'other');
expect('hearse list', $hearses, ['Budget+Hearse', 'View+#1', 'gold+tags']);
$looked = $game->handle('/request.xp?command&i_command=105&i_command_sub=1', 'other');
expect('hearse price', $looked, ['hearse1.swf', 'flash/blank.swf', 'cost+will+be+<font+color=%22#FFFFFF%22>10', 'i_sent=33']);
$bought = $game->handle('/request.xp?command&i_command=105&i_command_sub=101', 'other');
expect('buy hearse', $bought, ['money_bag_2.swf', 'i_coffin_gold=0', 'i_turns=17', 'Budget+Hearse', 'i_sent=33']);
if ((int) $teller->transport !== 1) {
    $failed++;
    fwrite(STDERR, "FAIL transport is {$teller->transport}\n");
} else {
    echo "ok transport saved\n";
}
$teller->gold = 0;
$store->save();
$game->handle('/request.xp?command&i_command=105&i_command_sub=4', 'other');
$refused = $game->handle('/request.xp?command&i_command=105&i_command_sub=101', 'other');
expect('hearse too expensive', $refused, ['cannot+afford']);
$teller->gold = 1;
$store->save();
$supplies = $game->handle('/request.xp?command&i_command=105&i_command_sub=102', 'other');
expect('supplies', $supplies, ['supplies+section', 'Lockpick', 'i_command_2=109']);
$viewPick = $game->handle('/request.xp?command&i_command=109&i_command_sub=1', 'other');
expect('lockpick page', $viewPick, ['lockpick.swf', 'accidently']);
$pick = $game->handle('/request.xp?command&i_command=109&i_command_sub=2', 'other');
expect('lockpick', $pick, ['Lockpick', 'i_coffin_gold=0']);
if ((int) $teller->lockpicks !== 1) {
    $failed++;
    fwrite(STDERR, "FAIL lockpicks {$teller->lockpicks}\n");
} else {
    echo "ok lockpick saved\n";
}
$teller->gold = 8;
$store->save();
$game->handle('/request.xp?command&i_command=40', 'other');
$security = $game->handle('/request.xp?command&i_command=105&i_command_sub=103', 'other');
expect('security', $security, ['Smoke+Detector', 'accident+prone']);
$viewAlarm = $game->handle('/request.xp?command&i_command=109&i_command_sub=3', 'other');
expect('smoke page', $viewAlarm, ['fire_alarm.swf', '20+percent']);
$alarm = $game->handle('/request.xp?command&i_command=109&i_command_sub=2', 'other');
expect('smoke detector', $alarm, ['Smoke+Detector', 'i_coffin_gold=0']);
$game->handle('/request.xp?command&i_command=40', 'other');
$game->handle('/request.xp?command&i_command=105&i_command_sub=103', 'other');
$game->handle('/request.xp?command&i_command=109&i_command_sub=3', 'other');
$secondAlarm = $game->handle('/request.xp?command&i_command=109&i_command_sub=2', 'other');
expect('one smoke detector', $secondAlarm, ["don't+need+two"]);

$helen = $store->createPlayer([
    'name' => 'Helen',
    'logon' => 'helen',
    'funeralName' => 'Quietrest',
    'password' => 'secret',
    'email' => 'helen@example.com',
    'male' => false,
]);
$teller->buryPoints = 10;
$teller->exp = 10;
$teller->luck = 30;
$teller->yangsSent = 0;
$teller->kickedOut = false;
$store->save();
$yangs = $game->handle('/request.xp?command&i_command=13', 'other');
expect('yangs', $yangs, ['yangs.swf', 'st_generic_info=What+could+be+luckier', 'st_generic_status=June+waits', 'Lucky+Dragon', 'Order+#1', 'st_url=&']);
$dragon = $game->handle('/request.xp?command&i_command=14&i_command_sub=3', 'other');
expect('lucky dragon', $dragon, ['General+Tso', 'i_luck=40', 'i_sp=5', 'i_sent=33']);
$full = $game->handle('/request.xp?command&i_command=14&i_command_sub=3', 'other');
expect('dragon when lucky', $full, ['Maybe+later']);
$teller->luck = 10;
$teller->buryPoints = 4;
$store->save();
$skint = $game->handle('/request.xp?command&i_command=14&i_command_sub=3', 'other');
expect('dragon costs points', $skint, ['not+enough+Bury+Points']);
$teller->buryPoints = 25;
$teller->exp = 25;
$teller->yangsSent = 0;
$store->save();
$ask = $game->handle('/request.xp?command&i_command=14&i_command_sub=1', 'other');
expect('sparrow asks for a name', $ask, ['i_getname', 'lucky+person']);
$helenSlot = 0;
foreach ($store->state->players as $index => $entry) {
    if ((string) $entry->id === (string) $helen->id) {
        $helenSlot = $index;
    }
}
$unknownName = $game->handle('/request.xp?command&i_command=14&i_command_sub=103&i_his_id=-1', 'other');
expect('a missed name returns to the menu', $unknownName, ['Lucky+Sparrow', 'i_sent=33']);
$named = $game->handle("/request.xp?command&i_command=14&i_command_sub=103&i_his_id=$helenSlot", 'other');
expect('custom fortune', $named, ['i_get_text', 'custom+fortune']);
$sent = $game->handle('/request.xp?command&i_command=14&i_command_sub=104&st_extra_text=Extra+sauce', 'other');
expect('yangs delivered', $sent, ['Mr.+Yang+leaves']);
if ((int) $helen->luck !== 55 || (int) $teller->buryPoints !== 5 || (int) $teller->yangsSent !== 1) {
    $failed++;
    fwrite(STDERR, "FAIL yangs result luck={$helen->luck} points={$teller->buryPoints} sent={$teller->yangsSent}\n");
} else {
    echo "ok yangs delivery changed luck and points\n";
}
$store->logon('helenbox', $helen->id);
$cookie = $game->handle('/request.xp?command&i_command=4', 'helenbox');
if (str_contains($cookie, 'morning_coffee.swf')) {
    $cookie = $game->handle('/request.xp?command&i_command=4', 'helenbox');
}
expect('fortune cookie', $cookie, ['Extra+sauce', 'gain']);
$blocked = $game->handle('/request.xp?command&i_command=14&i_command_sub=2', 'other');
expect('one delivery a day', $blocked, ['out+delivering', 'Get+Bitchy']);
$rude = $game->handle('/request.xp?command&i_command=14&i_command_sub=105', 'other');
expect('kicked out', $rude, ['Please+leave']);
$banned = $game->handle('/request.xp?command&i_command=13', 'other');
expect('june remembers', $banned, ['Just+leave', 'June+glances']);

$teller->gold = 0;
$store->save();
$hospital = $game->handle('/request.xp?command&i_command=37', 'other');
expect('hospital desk', $hospital, ['hospital_desk.swf', 'st_generic_info=You+are+at+the+city+hospital', "don't+recall", 'st_url=&', 'Leave']);
$teller->inHospital = true;
$store->save();
$room = $game->handle('/request.xp?command&i_command=37', 'other');
expect('recovery room', $room, ['hospital_room.swf', 'st_generic_info=You+are+lying', 'whole+body+aches', 'flash/blank.swf', 'b_show_battle=2']);
$woke = $game->handle('/request.xp?command&i_command=101&i_command_sub=1', 'other');
expect('left the hospital bed', $woke, ['prince+charming']);
if (!empty($teller->inHospital)) {
    $failed++;
    fwrite(STDERR, "FAIL still in the hospital\n");
} else {
    echo "ok hospital stay cleared\n";
}
$church = $game->handle('/request.xp?command&i_command=34', 'other');
expect('church', $church, ['churchguy_h.swf', 'Father+Smyth', 'Praise+the+Lord']);

$classes = $game->handle('/request.xp?command&i_command=36', 'other');
expect('spirit university', $classes, ['psych.swf', 'st_generic_info=The+role', 'st_generic_status=Taking+classes', 'Class+schedules', 'Social+Psychology', 'Take+PSY', 'Library', 'st_url=&', 'i_sent=10']);
$broke = $game->handle('/request.xp?command&i_command=100&i_command_sub=1', 'other');
expect('class costs gold', $broke, ['fumble+through+your+pockets']);
$teller->gold = 4;
$store->save();
$studied = $game->handle('/request.xp?command&i_command=100&i_command_sub=1', 'other');
expect('psychology class', $studied, ['Mood+reading', 'i_coffin_gold=0', 'i_sent=33']);
if ((int) $teller->psych !== 1) {
    $failed++;
    fwrite(STDERR, "FAIL psych {$teller->psych}\n");
} else {
    echo "ok psychology level saved\n";
}
$teller->gold = 9;
$store->save();
$lifted = $game->handle('/request.xp?command&i_command=100&i_command_sub=3', 'other');
expect('strength class', $lifted, ['lift+weights', 'i_coffin_gold=0']);
if ((int) $teller->strength !== 4) {
    $failed++;
    fwrite(STDERR, "FAIL strength {$teller->strength}\n");
} else {
    echo "ok strength saved\n";
}
$teller->gold = 12;
$store->save();
$laps = $game->handle('/request.xp?command&i_command=100&i_command_sub=4', 'other');
expect('gym class', $laps, ['run+laps', 'hitpoints', 'i_coffin_gold=0']);
if ((int) $teller->hpMax !== 14 && (int) $teller->hpMax !== 15) {
    $failed++;
    fwrite(STDERR, "FAIL hpMax {$teller->hpMax}\n");
} else {
    echo "ok hit points saved\n";
}
$library = $game->handle('/request.xp?command&i_command=100&i_command_sub=5', 'other');
expect('library', $library, ['book+of+deeds', 'Old+Papers']);
$deeds = $game->handle('/request.xp?command&i_command=100&i_command_sub=8', 'other');
expect('deeds', $deeds, ['st_object=i_view', 'st_view=', 'worthing+noting', 'Albert', 'i_sent=10']);
$papers = $game->handle('/request.xp?command&i_command=100&i_command_sub=6', 'other');
expect('old papers', $papers, ['i_get_text', '1+and+0']);
$missing = $game->handle('/request.xp?command&i_command=100&i_command_sub=7&st_extra_text=5', 'other');
expect('missing paper', $missing, ['st_object=i_view', 'day+5', 'but+fail']);
$keptDay = (int) $store->state->day;
$store->state->day = 4;
$store->state->papers = (object) ['2' => "`7HELLO\n`^The parlor is quiet.\n\n"];
$store->save();
$oldPaper = $game->handle('/request.xp?command&i_command=100&i_command_sub=7&st_extra_text=2', 'other');
expect('old paper', $oldPaper, ['st_object=i_view', 'peed+on', 'HELLO', 'parlor+is+quiet', 'i_sent=10']);
$store->state->day = $keptDay;

$row = $game->handle('/request.xp?command&i_command=38', 'other');
expect('parlor row', $row, ['palors.swf', 'dense+fog', 'more+parlors', 'Visit']);
$listed = $game->handle('/request.xp?command&i_command=102&i_command_sub=1', 'other');
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$todayStamp = $months[(int) date('n') - 1] . '+' . date('j');
expect('parlor list', $listed, ['i_sent=9', 'Helen', 'i_rank_command=38', 'on+now', '%22#FC54FC%22', "st_date=$todayStamp"]);
$store->logoff('helenbox');
$helen->police = true;
$fullRank = $game->handle('/request.xp?command&i_command=9', 'other');
expect('full ranking', $fullRank, ['i_rank_command=4', 'Protected', 'Helen', "st_date=$todayStamp"]);
$hidden = $game->handle('/request.xp?command&i_command=102&i_command_sub=1', 'other');
if (str_contains($hidden, 'Helen')) {
    $failed++;
    fwrite(STDERR, "FAIL police protection still listed on the parlor row\n");
} else {
    echo "ok police protection hides a parlor from the row\n";
}
$helen->police = false;
$store->logon('helenbox', $helen->id);
$askWho = $game->handle('/request.xp?command&i_command=102&i_command_sub=4', 'other');
expect('whose parlor', $askWho, ['i_getname', 'i_sent=14', "Who's+funeral"]);
$helenIndex = 0;
foreach ($store->state->players as $index => $entry) {
    if ((string) $entry->id === (string) $helen->id) {
        $helenIndex = $index;
    }
}
$lookedUp = $game->handle('/request.xp?command&i_command=17&st_input=Hel&i_his_id=0', 'other');
expect('name lookup', $lookedUp, ['i_namenext', 'st_message=Helen', "i_his_id=$helenIndex"]);
$occupied = $game->handle("/request.xp?command&i_command=102&i_command_sub=5&i_his_id=$helenIndex", 'other');
expect('owner is home', $occupied, ['young+couple']);
$store->logoff('helenbox');
$dark = $game->handle("/request.xp?command&i_command=102&i_command_sub=5&i_his_id=$helenIndex", 'other');
expect('lights off', $dark, ['lights+are+off', 'Try+Door', 'music_sneak.swf', 'i_sent=33']);
$blocked = $game->handle('/request.xp?logon&st_logon=helen&st_password=secret', 'homecoming');
expect('parlor is occupied', $blocked, ['poking+around+your+parlor', 'i_sent=-1']);
if ($store->session('homecoming')->playerId !== null) {
    $failed++;
    fwrite(STDERR, "FAIL Helen logged on during a break-in\n");
} else {
    echo "ok the owner stays outside during a break-in\n";
}
if ((int) $teller->visits !== 1) {
    $failed++;
    fwrite(STDERR, "FAIL visits {$teller->visits}\n");
} else {
    echo "ok visit counted\n";
}
$slipped = $game->handle('/request.xp?command&i_command=102&i_command_sub=6', 'other');
expect('inside the parlor', $slipped, ['desk_enemy.swf', 'snoring', 'Steal', 'Attack', 'i_sent=33']);
$grabbed = $game->handle('/request.xp?command&i_command=102&i_command_sub=7', 'other');
expect('stole and ran', $grabbed, ['lingerie', 'run+like+hell']);
$helen->padlock = 1;
$helen->padlockStrength = 5;
$teller->lockpicks = 1;
$store->save();
$locked = $game->handle("/request.xp?command&i_command=102&i_command_sub=5&i_his_id=$helenIndex", 'other');
expect('back for the lock', $locked, ['lights+are+off']);
$barred = $game->handle('/request.xp?command&i_command=102&i_command_sub=6', 'other');
expect('padlock', $barred, ['heavy+padlock', 'Pick+Lock']);
$picked = $game->handle('/request.xp?command&i_command=102&i_command_sub=9', 'other');
expect('lockpick spent', $picked, ['lockpick']);
$padlockArt = '';
foreach ($helen->inbox ?? [] as $note) {
    if (str_contains((string) ($note->text ?? ''), 'padlock')) {
        $padlockArt = (string) ($note->art ?? '');
    }
}
if ((int) $teller->lockpicks !== 0 || (int) $helen->padlock !== 0 || $padlockArt !== '') {
    $failed++;
    fwrite(STDERR, "FAIL lock pick locks={$teller->lockpicks} padlock={$helen->padlock} art=$padlockArt\n");
} else {
    echo "ok padlock broke\n";
}
$teller->visits = 0;
$helen->padlock = 0;
$helen->hpMax = 1;
$helen->exp = 100;
$helen->buryPoints = 0;
$store->save();
$asleep = $game->handle("/request.xp?command&i_command=102&i_command_sub=5&i_his_id=$helenIndex", 'other');
expect('asleep again', $asleep, ['Try+Door']);
$game->handle('/request.xp?command&i_command=102&i_command_sub=6', 'other');
$game->handle('/request.xp?command&i_command=102&i_command_sub=10', 'other');
$fight = $game->handle('/request.xp?command&i_command=102&i_command_sub=8', 'other');
expect('battle starts', $fight, ['in+battle', 'music_fight.swf', 'b_show_battle=1', 'st_object=i_generic', 'i_sent=10']);
$beaten = $fight;
for ($swing = 0; $swing < 8 && !str_contains($beaten, 'won+the+battle') && !str_contains($beaten, 'unable+to+get+up'); $swing++) {
    $beaten = $game->handle('/request.xp?command&i_command=103&i_command_sub=2', 'other');
}
expect('battle ends', $beaten, ['won+the+battle', 'i_sent=33']);
if (empty($helen->inHospital) || empty($helen->police) || (int) $helen->exp !== 99) {
    $failed++;
    fwrite(STDERR, "FAIL battle result hospital=" . var_export($helen->inHospital, true) . " police=" . var_export($helen->police, true) . " exp={$helen->exp}\n");
} else {
    echo "ok battle sent her to the hospital\n";
}
$yelled = $game->handle('/request.xp?command&i_command=103&i_command_sub=5&st_extra_text=12ABORT12', 'other');
expect('left the scene', $yelled, ['dense+fog']);
$savedAlbert = ['hp' => $teller->hp, 'hpMax' => $teller->hpMax, 'strength' => $teller->strength, 'plots' => $teller->plots, 'visits' => $teller->visits, 'wood' => $teller->wood, 'silver' => $teller->silver, 'gold' => $teller->gold, 'exp' => $teller->exp, 'buryPoints' => $teller->buryPoints];
$savedHelen = ['hp' => $helen->hp, 'hpMax' => $helen->hpMax, 'strength' => $helen->strength, 'plots' => $helen->plots, 'police' => $helen->police, 'inHospital' => $helen->inHospital ?? false, 'exp' => $helen->exp, 'buryPoints' => $helen->buryPoints, 'wood' => $helen->wood, 'silver' => $helen->silver, 'gold' => $helen->gold];
$helen->police = false;
$helen->inHospital = false;
$helen->hpMax = 80;
$helen->strength = 40;
$helen->plots = 50;
$teller->hpMax = 1;
$teller->strength = 1;
$teller->plots = 0;
$teller->visits = 0;
$store->logoff('helenbox');
$store->save();
$game->handle("/request.xp?command&i_command=102&i_command_sub=5&i_his_id=$helenIndex", 'other');
$game->handle('/request.xp?command&i_command=102&i_command_sub=6', 'other');
$game->handle('/request.xp?command&i_command=102&i_command_sub=10', 'other');
$fell = $game->handle('/request.xp?command&i_command=102&i_command_sub=8', 'other');
for ($swing = 0; $swing < 12 && !str_contains($fell, 'unable+to+get+up'); $swing++) {
    $fell = $game->handle('/request.xp?command&i_command=103&i_command_sub=2', 'other');
}
if (!str_contains($fell, 'unable+to+get+up') || !str_contains($fell, '<font+color=%22#FFFF33%22>Bury+Points.') || str_contains($fell, '<font+color=%22#FFFF33%22>+Bury+Points.')) {
    $failed++;
    fwrite(STDERR, "FAIL battle loss\n" . substr($fell, 0, 400) . "\n");
} else {
    echo "ok a lost fight says Bury Points without a gap\n";
}
foreach ($savedAlbert as $field => $value) {
    $teller->{$field} = $value;
}
foreach ($savedHelen as $field => $value) {
    $helen->{$field} = $value;
}
$store->save();
$albertIndex = 0;
foreach ($store->state->players as $index => $entry) {
    if ((string) $entry->id === (string) $teller->id) {
        $albertIndex = $index;
    }
}
$game->handle('/request.xp?command&i_command=38', 'other');
$mine = $game->handle("/request.xp?command&i_command=102&i_command_sub=5&i_his_id=$albertIndex", 'other');
expect('own parlor', $mine, ['strangely+familiar']);
$teller->visits = 2;
$store->save();
$tired = $game->handle('/request.xp?command&i_command=102&i_command_sub=4', 'other');
expect('visit limit', $tired, ["everyone's+parlor", 'i_sent=33']);

$sheet = $game->handle('/request.xp?command&i_command=3', 'other');
$ranked = array_values($store->state->players);
usort($ranked, function ($a, $b) {
    $exp = (int) $b->exp <=> (int) $a->exp;
    return $exp !== 0 ? $exp : ((int) $a->id <=> (int) $b->id);
});
$place = 0;
foreach ($ranked as $at => $entry) {
    if ((string) $entry->id === (string) $teller->id) {
        $place = $at;
    }
}
$percent = min(100, max(1, 100 - (int) (($place / max(1, count($ranked))) * 100)));
$ratingText = '(No text for this rating.  Email Seth some ideas!)';
foreach (preg_split("/\r\n|\n|\r/", (string) file_get_contents(fq_roots()['dist'] . '/ratings.txt')) as $line) {
    $bar = strpos($line, '|');
    if ($bar !== false && (int) substr($line, 0, $bar) === $percent) {
        $ratingText = substr($line, $bar + 1);
    }
}
expect('stats screen', $sheet, ['i_sent=3', 'Smoke+detector+installed.', 'Level+2', 'Budget+Hearse', 'st_option_2=+++++Name', 'i_command_3=4', fq_format_for_web('#' . ($place + 1) . ' - ' . $ratingText)]);
$mirror = $game->handle('/request.xp?command&i_command=43', 'other');
expect('name change', $mirror, ['question.swf', 'stare+at+yourself', 'gold+tag', 'Yes']);
$askName = $game->handle('/request.xp?command&i_command=108&i_command_sub=2', 'other');
expect('new name prompt', $askName, ['i_get_text', 'Enter+your+new+name', 'i_sent=14']);
$brokeName = $game->handle('/request.xp?command&i_command=108&i_command_sub=1&st_extra_text=Bertie', 'other');
expect('name costs gold', $brokeName, ['enough+gold+tags', 'i_sent=33']);
$teller->gold = 1;
$store->save();
$sameName = $game->handle('/request.xp?command&i_command=108&i_command_sub=1&st_extra_text=Albert', 'other');
expect('same name', $sameName, ['like+the+sound', 'i_sent=33']);
$punctName = $game->handle('/request.xp?command&i_command=108&i_command_sub=1&st_extra_text=Al@bert', 'other');
expect('punctuation is the same name', $punctName, ['like+the+sound', 'i_sent=33']);
$abortName = $game->handle('/request.xp?command&i_command=108&i_command_sub=1&st_extra_text=12ABORT12@', 'other');
expect('cancel is recognized after the name is cleaned', $abortName, ['gold+tag', 'question.swf']);
if ((int) $teller->gold !== 1 || $teller->name !== 'Albert') {
    $failed++;
    fwrite(STDERR, "FAIL abort kept name={$teller->name} gold={$teller->gold}\n");
} else {
    echo "ok a cleaned cancel does not spend the gold tag\n";
}
$renamed = $game->handle('/request.xp?command&i_command=108&i_command_sub=1&st_extra_text=Bertie', 'other');
expect('name changed', $renamed, ['known+as', 'Bertie', 'i_coffin_gold=0', 'i_sent=33']);
if ($teller->name !== 'Bertie' || (int) $teller->gold !== 0) {
    $failed++;
    fwrite(STDERR, "FAIL rename name={$teller->name} gold={$teller->gold}\n");
} else {
    echo "ok name saved\n";
}

$teller->gold = 0;
$store->save();
$offer = $game->runScript('other', 'events/arson.c');
expect('twins offer', $offer, ['m_old_3.swf', 'play+with+fire', 'i_command_2=109']);
$poorTwins = $game->handle('/request.xp?command&i_command=109&i_command_sub=1', 'other');
expect('twins want gold', $poorTwins, ['enough+gold+tags', 'i_sent=33']);
$teller->gold = 6;
$store->save();
$game->runScript('other', 'events/arson.c');
$hireTwins = $game->handle('/request.xp?command&i_command=109&i_command_sub=1', 'other');
expect('twins ask for a parlor', $hireTwins, ['i_getname', 'i_sent=14', 'Twins+to+pay']);
$ownParlor = $game->handle("/request.xp?command&i_command=109&i_his_id=$albertIndex", 'other');
expect('twins refuse your parlor', $ownParlor, ['own+building', 'i_command_2=109']);
if ((int) $teller->gold !== 6) {
    $failed++;
    fwrite(STDERR, "FAIL twins charged early gold={$teller->gold}\n");
} else {
    echo "ok twins have not been paid yet\n";
}
$store->logon('helenbox', $helen->id);
$game->runScript('other', 'events/arson.c');
$game->handle('/request.xp?command&i_command=109&i_command_sub=1', 'other');
$yard = $game->handle("/request.xp?command&i_command=109&i_his_id=$helenIndex", 'other');
expect('twins see the owner', $yard, ['working+in+the+yard']);
$store->logoff('helenbox');
$helen->wood = 7;
$helen->plots = 40;
$helen->smoke = 0;
$helen->safe = 0;
$game->runScript('other', 'events/arson.c');
$game->handle('/request.xp?command&i_command=109&i_command_sub=1', 'other');
$burned = $game->handle("/request.xp?command&i_command=109&i_his_id=$helenIndex", 'other');
expect('twins burn the parlor', $burned, ['fire+engulfs', 'Quietrest', 'fire.swf', 'i_coffin_gold=0']);
$woodMail = false;
foreach ($helen->inbox ?? [] as $note) {
    if (str_contains((string) ($note->text ?? ''), 'wood tag')) {
        $woodMail = true;
    }
}
if ((int) $helen->wood !== 0 || (int) $helen->plots !== 38 || (int) $teller->gold !== 0 || !$woodMail) {
    $failed++;
    fwrite(STDERR, "FAIL fire result wood={$helen->wood} plots={$helen->plots} gold={$teller->gold} mail=" . count($helen->inbox ?? []) . "\n");
} else {
    echo "ok fire took the graves, wood tags, and gold\n";
}

$teller->buryPoints = 11;
$teller->exp = 40;
$teller->dayLastLoggedOn = $store->state->day;
$helen->buryPoints = 0;
$helen->exp = 10;
$dormant = $store->createPlayer([
    'name' => 'Dormant',
    'logon' => 'dormant',
    'funeralName' => 'Dusty',
    'password' => 'secret',
    'email' => 'd@b.co',
    'male' => true,
]);
$dormant->exp = 5;
$dormant->dayLastLoggedOn = (int) $store->state->day - 16;
$idle = $store->createPlayer([
    'name' => 'Idle',
    'logon' => 'idle',
    'funeralName' => 'Empty',
    'password' => 'secret',
    'email' => 'i@b.co',
    'male' => true,
]);
$idle->exp = 0;
$idle->dayLastLoggedOn = (int) $store->state->day;
$store->session('other')->pendingYells = [];
$store->state->date = '2000-01-01';
$store->save();
$game->maintain();
$closedShop = false;
$noSale = false;
foreach ($store->session('other')->pendingYells ?? [] as $line) {
    $text = (string) $line;
    if (str_contains($text, 'Dormant closes shop and leaves town.')) {
        $closedShop = true;
    }
    if (str_contains($text, 'Idle closes shop and leaves town without a single sale.')) {
        $noSale = true;
    }
}
$refused = $game->handle('/request.xp?logon&st_logon=dormant&st_password=secret', 'closed');
if (!$closedShop || !$noSale || ($dormant->active ?? true) !== false || ($idle->active ?? true) !== false || !str_contains($refused, 'i_sent=2')) {
    $failed++;
    fwrite(STDERR, "FAIL closed shop dormant=" . var_export($closedShop, true) . ' idle=' . var_export($noSale, true) . " active=" . var_export($dormant->active, true) . "\n$refused\n");
} else {
    echo "ok midnight closes an empty parlor and a parlor that never came back\n";
}
$reopened = $game->handle('/request.xp?new&name=Dormant&logon=dusty&f_name=Second&password=secret&password_v=secret&email=d2@b.co&b_male=1', 'reuse');
expect('a closed parlor name can be used again', $reopened, ['i_sent=5', 'Dormant']);
$game->handle('/request.xp?command&i_command=43', 'other');
$teller->gold = 1;
$store->save();
$tookIdle = $game->handle('/request.xp?command&i_command=108&i_command_sub=1&st_extra_text=Idle', 'other');
expect('a closed parlor name can be taken', $tookIdle, ['known+as', 'Idle', 'i_sent=33']);
if ($teller->name !== 'Idle') {
    $failed++;
    fwrite(STDERR, "FAIL closed name stayed {$teller->name}\n");
} else {
    echo "ok the name change accepted a closed parlor\n";
}
$sellerMail = false;
$parlorMail = false;
foreach ($teller->inbox ?? [] as $note) {
    $text = (string) ($note->text ?? '');
    if (str_contains($text, 'top seller') && (int) ($note->grant->exp ?? 0) === 20) {
        $sellerMail = true;
    }
    if (str_contains($text, 'top parlor') && (int) ($note->grant->exp ?? 0) === 20) {
        $parlorMail = true;
    }
}
if (!$sellerMail || !$parlorMail || (int) $teller->exp !== 40 || (int) $teller->buryPoints !== 0 || $store->state->date === '2000-01-01' || !str_contains((string) $store->state->topYesterday, 'with 11 Bury Points')) {
    $failed++;
    fwrite(STDERR, "FAIL midnight maint seller=" . var_export($sellerMail, true) . ' parlor=' . var_export($parlorMail, true) . " exp={$teller->exp} points={$teller->buryPoints} date={$store->state->date} top={$store->state->topYesterday}\n");
} else {
    echo "ok midnight maint queues the seller award for the new day\n";
}
$nextMorning = '';
for ($step = 0; $step < 8 && !str_contains($nextMorning, 'eyes+grow+heavy'); $step++) {
    $nextMorning = $game->handle('/request.xp?command&i_command=4', 'other');
}
expect('new morning', $nextMorning, ['eyes+grow+heavy', 'morning_coffee.swf', 'i_sp=0', 'i_exp=40']);
if ((int) $teller->buryPoints !== 0 || (int) $teller->exp !== 40) {
    $failed++;
    fwrite(STDERR, "FAIL new day points={$teller->buryPoints} exp={$teller->exp}\n");
} else {
    echo "ok a new day clears bury points before the award mail is read\n";
}
$award = '';
for ($step = 0; $step < 12 && (!str_contains($award, 'top+seller') || !str_contains($award, 'top+parlor')); $step++) {
    $award .= $game->handle('/request.xp?command&i_command=4', 'other');
}
if (!str_contains($award, 'top+seller') || !str_contains($award, 'top+parlor') || (int) $teller->exp < 80 || (int) $teller->buryPoints < 40) {
    $failed++;
    fwrite(STDERR, "FAIL award mail exp={$teller->exp} points={$teller->buryPoints}\n" . substr($award, 0, 300) . "\n");
} else {
    echo "ok the morning mail pays yesterday's bonus onto today's bury points\n";
}

$cafe = $game->handle('/request.xp?command&i_command=23', 'other');
expect('pale horse', $cafe, ['i_chat', 'Pale+Horse', 'mochachino', 'only+one+here', 'i_chat_speed=2000', 'i_sent=10']);
$arrived = $game->handle('/request.xp?command&i_command=24', 'other');
expect('saunters in', $arrived, ["{$teller->name}+saunters+in", 'i_sent=24']);
$said = $game->handle('/request.xp?command&i_command=24&st_input=hello', 'other');
expect('cafe speech', $said, ["{$teller->name}%3A", 'hello']);
$swore = $game->handle('/request.xp?command&i_command=24&st_input=fuck', 'other');
expect('cafe filter', $swore, ['fudge']);
$cafeHelp = $game->handle('/request.xp?command&i_command=24&st_input=/?', 'other');
expect('cafe help', $cafeHelp, ['Cafe+chat+help', '/give+Seth+gold']);
$store->logon('helenbox', $helen->id);
$joined = $game->handle('/request.xp?command&i_command=23', 'helenbox');
expect('helen joins the cafe', $joined, ['Other+people+here', $teller->name]);
$game->handle('/request.xp?command&i_command=24', 'helenbox');
$teller->gold = 2;
$helen->gold = 0;
$helen->tagsReceived = 0;
$gift = $game->handle('/request.xp?command&i_command=24&st_input=/give+Helen+gold', 'other');
expect('gives a gold tag', $gift, ['gives', 'Helen', 'Gold+tag']);
if ((int) $teller->gold !== 1 || (int) $helen->gold !== 1) {
    $failed++;
    fwrite(STDERR, "FAIL gift gold={$teller->gold} helen={$helen->gold}\n");
} else {
    echo "ok gold tag changed hands\n";
}
$secondGift = $game->handle('/request.xp?command&i_command=24&st_input=/give+Helen+gold', 'other');
expect('one tag a day', $secondGift, ['already+received']);
if ((int) $teller->gold !== 1) {
    $failed++;
    fwrite(STDERR, "FAIL second gift gold={$teller->gold}\n");
} else {
    echo "ok the second gold tag stayed put\n";
}
$leftCafe = $game->handle('/request.xp?command&i_command=12', 'other');
expect('left for the map', $leftCafe, ['st_object=i_map']);
$noticed = $game->handle('/request.xp?command&i_command=24', 'helenbox');
expect('steps outside', $noticed, ['steps+outside']);
$missingMail = $game->handle('/request.xp?command&i_command=20&i_his_id=99&st_input=Hello', 'other');
expect('mail to nobody', $missingMail, ['i_message', "doesn't+exist", 'i_sent=14']);
$store->logon('helenbox', $helen->id);
$helen->inbox = [];
$game->handle('/request.xp?command&i_command=23', 'helenbox');
$posted = $game->handle("/request.xp?command&i_command=20&i_his_id=$helenIndex&st_input=Hello+there", 'other');
expect('letter sent', $posted, ['i_sent=16']);
$mailNotice = $game->handle('/request.xp?command&i_command=24', 'helenbox');
expect('mail while chatting', $mailNotice, ['mail+waiting']);
$letter = $game->handle('/request.xp?command&i_command=4', 'helenbox');
if (str_contains($letter, 'morning_coffee.swf')) {
    $letter = $game->handle('/request.xp?command&i_command=4', 'helenbox');
}
expect('letter on the desk', $letter, ['received+a+letter+from', $teller->name, 'Hello+there', 'st_quote=>Hello+there', 'i_command_2=21', "i_his_id=$albertIndex", 'i_sent=10']);
$reply = $game->handle('/request.xp?command&i_command=21', 'helenbox');
expect('reply is the original error', $reply, ['g_st_error=Server+unable+to+process+request+21.', 'i_sent=-1']);

$announced = $game->handle('/request.xp?command&i_command=8&st_note=Big+sale+[example.com]', 'other');
expect('announcement', $announced, ['makes+an+announcement', 'Big+sale', 'example.com', 'i_yesterday=0', 'i_sent=5']);
$tight = str_contains($announced, 'announcement%3A<font+color=%22#A85400%22><br>Big+sale')
    && !str_contains($announced, 'announcement%3A<font+color=%22#A85400%22><br><br>');
$reader = $store->session('other');
$paperPlace = ($reader->location ?? '') === 'News' && (int) ($reader->callback ?? -1) === 0;
$reader->callback = 104;
$reader->location = 'Bank';
$store->save();
$refreshed = $game->handle('/request.xp?command&i_command=41', 'other');
expect('paper refresh', $refreshed, ['i_sent=5']);
$stayedInBank = ($reader->location ?? '') === 'Bank' && (int) ($reader->callback ?? 0) === 104;
$opened = $game->handle('/request.xp?command&i_command=5', 'other');
expect('opened the paper', $opened, ['i_yesterday=0', 'i_sent=5']);
$movedToNews = ($reader->location ?? '') === 'News' && (int) ($reader->callback ?? -1) === 0;
$fromPaper = $game->handle('/request.xp?command&i_command=104&i_command_sub=18', 'other');
expect('bank closed by the paper', $fromPaper, ['unable+to+process', 'i_sent=-1']);
if ($tight && $paperPlace && $stayedInBank && $movedToNews) {
    echo "ok the paper is one column and reading it leaves the bank\n";
} else {
    fwrite(STDERR, "FAIL paper tight=" . ($tight ? '1' : '0') . " place=" . ($paperPlace ? '1' : '0') . " stayed=" . ($stayedInBank ? '1' : '0') . " moved=" . ($movedToNews ? '1' : '0') . "\n");
    $failed++;
}
$crimePaper = $game->handle('/request.xp?command&i_command=42', 'other');
expect('crime page', $crimePaper, ['no+crime+reported', 'i_sent=5']);
$prior = $game->handle('/request.xp?command&i_command=7', 'other');
expect("yesterday's paper", $prior, ['i_yesterday=1', 'st_date=Day+' . ($store->state->day - 1)]);

$parish = $game->handle('/request.xp?command&i_command=44&st_extra_text=church.c', 'other');
expect('real church', $parish, ['churchguy_h.swf', 'background_church.swf', 'too+warmly', 'Praise+the+Lord', 'i_command_3=12', 'st_object=i_generic']);

$resumeDir = sys_get_temp_dir() . '/fq-resume-' . bin2hex(random_bytes(4));
mkdir($resumeDir);
$first = fq_game($resumeDir . '/players.json');
$first->handle('/request.xp?new&name=Clara&logon=clara&f_name=Stillness&password=secret&password_v=secret&email=clara@example.com&b_male=0', 'box');
$waiting = $first->runScript('box', 'events/arson.c');
expect('twins offer saved', $waiting, ['play+with+fire', 'i_command_2=109']);
$first->store->release();
unset($first);
$second = fq_game($resumeDir . '/players.json');
$resumed = $second->handle('/request.xp?command&i_command=109&i_command_sub=1', 'box');
expect('waiting screen survives a new PHP process', $resumed, ['enough+gold+tags', 'i_sent=33']);

$make = new ReflectionMethod(FqGame::class, 'makeCustomer');
$make->setAccessible(true);
$familyOk = true;
$romantic = ['husband', 'wife', 'boyfriend', 'girlfriend'];
for ($n = 0; $n < 80; $n++) {
    $visitor = $make->invoke($game, $teller);
    $age = (int) $visitor->age;
    $dead = (int) $visitor->deceasedAge;
    $band = ($age >= 15 && $age <= 19) || ($age >= 28 && $age <= 40) || ($age >= 60 && $age <= 99);
    $deadBand = ($dead >= 7 && $dead <= 11) || ($dead >= 15 && $dead <= 19) || ($dead >= 28 && $dead <= 40) || ($dead >= 60 && $dead <= 99);
    $sameSexRomance = $visitor->male === $visitor->deceasedMale && in_array($visitor->relation, $romantic, true);
    $teenImpossible = $age <= 19 && in_array($visitor->relation, ['son', 'daughter', 'grandson', 'granddaughter', 'husband', 'wife', 'father', 'mother'], true);
    $adultImpossible = $age >= 28 && $age <= 40 && in_array($visitor->relation, ['grandson', 'granddaughter', 'grandfather', 'grandmother'], true);
    $oldImpossible = $age >= 60 && in_array($visitor->relation, ['dad', 'mom', 'father', 'mother', 'grandfather', 'grandmother'], true);
    $picture = $age >= 60 ? 'old' : ($age >= 28 ? 'adult' : 'teen');
    $portraitOk = str_contains((string) $visitor->portrait, $visitor->male ? '/m_' : '/f_') && str_contains((string) $visitor->portrait, $picture);
    if (!$band || !$deadBand || $sameSexRomance || $teenImpossible || $adultImpossible || $oldImpossible || !$portraitOk) {
        $familyOk = false;
        fwrite(STDERR, "FAIL family age=$age dead=$dead relation={$visitor->relation} portrait={$visitor->portrait}\n");
        break;
    }
}
if ($familyOk) {
    echo "ok customers match the original ages and relatives\n";
} else {
    $failed++;
}

$store->logon('helenbox', $helen->id);
$game->handle('/request.xp?command&i_command=23', 'helenbox');
$finish = new ReflectionMethod(FqGame::class, 'finishSale');
$finish->setAccessible(true);
$finish->invoke($game, $teller, (object) [
    'budget' => 5300,
    'male' => true,
    'age' => 35,
    'casketIndex' => 2,
    'casket' => (object) ['id' => 'gold'],
    'upgrade' => -1,
    'options' => [],
]);
$leave = new ReflectionMethod(FqGame::class, 'walkOut');
$leave->setAccessible(true);
$leave->invoke($game, $teller, (object) [
    'male' => true,
    'age' => 35,
    'income' => 'Rich',
    'relation' => 'brother',
    'deceasedMale' => true,
    'loved' => false,
    'portrait' => 'flash/people/m_adult.swf',
    'moodValue' => 3,
    'cheapness' => 3,
]);
$stolenNote = null;
foreach ($store->state->players as $person) {
    foreach ($person->inbox ?? [] as $note) {
        if (str_contains((string) ($note->text ?? ''), 'stealing this sale')) {
            $stolenNote = $note;
        }
    }
}
$stolenPoints = (int) ($stolenNote->grant->exp ?? 0);
if (!$stolenNote || ($stolenNote->background ?? '') !== 'flash/background/background_2.swf' || ($stolenNote->art ?? '') !== 'flash/people/m_adult.swf' || $stolenPoints < 5 || $stolenPoints > 9) {
    $failed++;
    fwrite(STDERR, "FAIL stolen sale mail art=" . ($stolenNote->art ?? '') . ' bg=' . ($stolenNote->background ?? '') . " points=$stolenPoints\n");
} else {
    echo "ok a stolen sale waits in the mail with the customer and the parlor background\n";
}
$broadcast = $game->handle('/request.xp?command&i_command=24', 'helenbox');
expect('pale horse television', $broadcast, ['The+TV%3A', 'BP+sale', 'looking+pissed']);

$reward = new ReflectionMethod(FqGame::class, 'rewardText');
$reward->setAccessible(true);
$onePoint = $reward->invoke($game, [[1, 'Bury Point', 'Bury Points'], [0, 'luck', 'luck'], [1, 'turn', 'turns']], true);
$lostPoint = $reward->invoke($game, [[-1, 'Bury Point', 'Bury Points'], [0, 'luck', 'luck'], [-1, 'turn', 'turns']], true);
$morningWords = str_contains($onePoint, 'Bury Point') && !str_contains($onePoint, 'Bury Points')
    && str_contains($onePoint, ' turn.') && !str_ends_with($onePoint, '..')
    && str_contains($lostPoint, 'Bury Points') && str_contains($lostPoint, ' turns')
    && !str_ends_with($lostPoint, '..');
$heads = new ReflectionMethod(FqGame::class, 'yangsHeadlines');
$heads->setAccessible(true);
$lines = $heads->invoke($game, $teller, $helen);
$paperOk = str_contains($lines[0], '`7SPOTLIGHT ON OUR TOWN') && str_contains($lines[0], '`^Local mortician')
    && str_contains($lines[1], (string) $helen->funeralName);
$mailer = new ReflectionMethod(FqGame::class, 'postDailyBlurb');
$mailer->setAccessible(true);
for ($n = 0; $n < 24; $n++) {
    $mailer->invoke($game);
}
$sawReward = false;
$doubleStop = false;
foreach ($store->state->players as $person) {
    foreach ($person->inbox ?? [] as $letter) {
        $text = (string) ($letter->text ?? '');
        if (!str_contains($text, 'You earn') && !str_contains($text, 'You lose')) {
            continue;
        }
        $sawReward = true;
        if (preg_match('/(?:Bury Point|Bury Points|luck|turn|turns)\.\./', $text)) {
            $doubleStop = true;
        }
    }
}
if ($morningWords && $paperOk && $sawReward && !$doubleStop) {
    echo "ok morning mail and yang's paper match the original\n";
} else {
    fwrite(STDERR, "FAIL morning mail words=" . ($morningWords ? '1' : '0') . " paper=" . ($paperOk ? '1' : '0') . " reward=" . ($sawReward ? '1' : '0') . " double=" . ($doubleStop ? '1' : '0') . "\n");
    $failed++;
}

$alive = 0;
$departed = null;
foreach ($store->state->players as $person) {
    if (($person->active ?? true) !== false) {
        $alive++;
        $departed = $person;
    }
}
$departed->active = false;
$banner = $game->handle('/request.xp?checklogon', 'banner');
$departed->active = true;
$ghost = $store->createPlayer([
    'name' => 'Departed',
    'logon' => 'departed',
    'funeralName' => 'Gone',
    'password' => 'secret',
    'email' => 'gone@example.com',
    'male' => true,
]);
$ghost->active = false;
$ghost->exp = 1000000;
$store->save();
$ranked = $game->handle('/request.xp?command&i_command=3', 'other');
$me = $store->player('other');
$mine = (int) $me->exp;
$myId = (int) $me->id;
$ahead = 0;
foreach ($store->state->players as $person) {
    $exp = (int) ($person->exp ?? 0);
    $id = (int) $person->id;
    if ($exp > $mine || ($exp === $mine && $id < $myId)) {
        $ahead++;
    }
}
expect('departed parlor still counts in rank', $ranked, ['st_rating=#' . ($ahead + 1) . '+-+']);
if (str_contains($banner, 'NEW%21') || str_contains($banner, 'NEW!')) {
    $failed++;
    fwrite(STDERR, "FAIL login banner still shows NEW badges\n");
} else {
    echo "ok login banner has no NEW badges\n";
}

expect('login banner', $banner, [
    '<u><a+href=%22https%3A//github.com/auzziejay/funeral-quest-web%22+target=%22_blank%22>Funeral+Quest+Web+Server</a></u>',
    'of+' . ($alive - 1) . '+players',
    'st_server_news=&',
    '<p+align=%22center%22>',
    '+++|+++',
    'About+this+game</a></u><br>',
]);

$game->handle('/request.xp?logon&st_logon=albert&st_password=secret', 'faint');
$store->player('faint')->inbox = [];
$store->session('faint')->pendingMorning = null;
$backAgain = $game->handle('/request.xp?command&i_command=4', 'faint');
expect('second logon greets you back', $backAgain, ['Unfinished+business?<br>', 'i_sent=10']);
if (str_contains($backAgain, '\\n')) {
    $failed++;
    fwrite(STDERR, "FAIL greeting has a literal backslash-n\n");
} else {
    echo "ok greeting breaks the line\n";
}
$albertNow = $store->player('faint');
$plotsBefore = (int) $albertNow->plots;
$woodBefore = (int) $albertNow->wood;
$risking = $store->session('faint');
$risking->customer = (object) [
    'male' => true,
    'budget' => 900,
    'casketIndex' => 0,
    'casket' => (object) ['id' => 'wood'],
    'upgrade' => -1,
    'options' => [],
];
$risking->risk = 0;
$kept = $game->handle('/request.xp?command&i_command=1', 'faint');
expect('quit during a risk', $kept, ['i_sent=1']);
if ((int) $albertNow->plots !== $plotsBefore || (int) $albertNow->wood !== $woodBefore) {
    $failed++;
    fwrite(STDERR, "FAIL risk quit plots={$albertNow->plots} wood={$albertNow->wood}\n");
} else {
    echo "ok quitting a risk does not finish a funeral\n";
}

$game->handle('/request.xp?logon&st_logon=albert&st_password=secret', 'faint');
$plotsBefore = (int) $albertNow->plots;
$woodBefore = (int) $albertNow->wood;
$expBefore = (int) $albertNow->exp;
$selling = $store->session('faint');
$selling->customer = (object) [
    'male' => true,
    'budget' => 900,
    'casketIndex' => 0,
    'casket' => (object) ['id' => 'wood'],
    'upgrade' => -1,
    'options' => [],
];
$selling->risk = null;
unset($selling->vm);
$leftSale = $game->handle('/request.xp?command&i_command=1', 'faint');
expect('quit during a sale', $leftSale, ['i_sent=1']);
$faintMail = false;
foreach ($albertNow->inbox ?? [] as $note) {
    $text = (string) ($note->text ?? '');
    if (str_contains($text, 'You fainted while dealing with a customer') && str_contains($text, 'riding in an ambulance') && ($note->art ?? '') === 'flash/places/hospital_ambulance.swf') {
        $faintMail = true;
    }
}
if (!$faintMail || (int) $albertNow->plots !== $plotsBefore + 1 || (int) $albertNow->wood !== $woodBefore + 1 || (int) $albertNow->exp <= $expBefore || $store->session('faint')->playerId !== null) {
    $failed++;
    fwrite(STDERR, "FAIL faint sale mail=" . var_export($faintMail, true) . " plots={$albertNow->plots} wood={$albertNow->wood} exp={$albertNow->exp}\n");
} else {
    echo "ok quitting mid-sale finishes the funeral and leaves the ambulance note\n";
}

$game->handle('/request.xp?logon&st_logon=albert&st_password=secret', 'refresh');
$plotsBefore = (int) $albertNow->plots;
$refreshing = $store->session('refresh');
$refreshing->customer = (object) [
    'male' => false,
    'budget' => 900,
    'casketIndex' => 0,
    'casket' => (object) ['id' => 'wood'],
    'upgrade' => -1,
    'options' => [],
];
$refreshing->risk = null;
unset($refreshing->vm);
$reopened = $game->handle('/request.xp?checklogon', 'refresh');
expect('refresh during a sale', $reopened, ['i_sent=1']);
$secondFaint = 0;
foreach ($albertNow->inbox ?? [] as $note) {
    if (str_contains((string) ($note->text ?? ''), 'You fainted while dealing with a customer')) {
        $secondFaint++;
    }
}
if ($secondFaint < 2 || (int) $albertNow->plots !== $plotsBefore + 1 || $store->session('refresh')->playerId !== null) {
    $failed++;
    fwrite(STDERR, "FAIL refresh faint count=$secondFaint plots={$albertNow->plots}\n");
} else {
    echo "ok refreshing mid-sale also finishes the funeral\n";
}

$listener = $store->session('helenbox');
$listener->chatting = false;
$listener->pendingMorning = 'return';
$listener->gaveLogonMessage = true;
$listener->pendingYells = [];
$helen->inbox = [];
$helen->deliveries = [];
$helen->dayLastLoggedOn = (int) $store->state->day;
$store->save();
$game->handle('/request.xp?command&i_command=23', 'other');
$shout = $game->handle('/request.xp?command&i_command=24&st_input=/yell+help', 'other');
$shouter = $store->player('other');
expect('town yell', $shout, [$shouter->name, 'yells%3A', 'help']);
$duringCoffee = $game->handle('/request.xp?command&i_command=4', 'helenbox');
expect('yell waits through morning', $duringCoffee, ['morning_coffee.swf']);
if (str_contains($duringCoffee, 'yells%3A')) {
    $failed++;
    fwrite(STDERR, "FAIL yell showed on the morning screen\n");
} else {
    echo "ok a yell waits until after the morning greeting\n";
}
$heard = $game->handle('/request.xp?command&i_command=4', 'helenbox');
expect('yell on the desk', $heard, [$shouter->name, 'yells%3A', 'help']);
if (str_contains($heard, 'sitting+at+your+desk')) {
    $failed++;
    fwrite(STDERR, "FAIL yell desk still says sitting\n");
} else {
    echo "ok a yell replaces the quiet desk line\n";
}
$quiet = $game->handle('/request.xp?command&i_command=4', 'helenbox');
expect('desk after the yell', $quiet, ['sitting+at+your+desk']);
if (str_contains($quiet, 'yells%3A') || $store->session('other')->pendingYells) {
    $failed++;
    fwrite(STDERR, "FAIL yell repeated\n");
} else {
    echo "ok the yell is shown once\n";
}

$store->session('helenbox')->pendingYells = [];
$store->session('other')->pendingYells = [];
$store->save();
$arrived = $game->handle('/request.xp?logon&st_logon=washed&st_password=secret', 'arrive');
expect('neighbor logon opens the paper', $arrived, ['i_sent=5']);
$heardArrival = false;
$keptArrival = false;
foreach ($store->session('helenbox')->pendingYells ?? [] as $line) {
    if (str_contains((string) $line, 'fudgeBoy logged on.')) {
        $heardArrival = true;
    }
}
foreach ($store->session('arrive')->pendingYells ?? [] as $line) {
    if (str_contains((string) $line, 'fudgeBoy logged on.')) {
        $keptArrival = true;
    }
}
if (!$heardArrival || !$keptArrival) {
    $failed++;
    fwrite(STDERR, "FAIL logon heard=" . var_export($heardArrival, true) . " self=" . var_export($keptArrival, true) . "\n");
} else {
    echo "ok the town hears a logon\n";
}
$left = $game->handle('/request.xp?command&i_command=1', 'arrive');
expect('quit', $left, ['i_sent=1']);
$heardLeave = false;
foreach ($store->session('helenbox')->pendingYells ?? [] as $line) {
    if (str_contains((string) $line, 'fudgeBoy logged off.')) {
        $heardLeave = true;
    }
}
if (!$heardLeave || $store->session('arrive')->pendingYells) {
    $failed++;
    fwrite(STDERR, "FAIL logoff heard=" . var_export($heardLeave, true) . "\n");
} else {
    echo "ok the town hears a logoff\n";
}
$store->session('helenbox')->pendingYells = [];
$store->save();
$moved = $game->handle('/request.xp?new&name=Nora&logon=nora&f_name=Northrest&password=secret&password_v=secret&email=n@b.co&b_male=0', 'nora');
expect('new neighbor', $moved, ['i_sent=5', 'Northrest', 'Nora']);
$heardMove = false;
$heardLogon = false;
foreach ($store->session('helenbox')->pendingYells ?? [] as $line) {
    $text = (string) $line;
    if (str_contains($text, 'Nora has just moved into town!')) {
        $heardMove = true;
    }
    if (str_contains($text, 'Nora logged on.')) {
        $heardLogon = true;
    }
}
if (!$heardMove || $heardLogon) {
    $failed++;
    fwrite(STDERR, "FAIL moved=" . var_export($heardMove, true) . " logon=" . var_export($heardLogon, true) . "\n");
} else {
    echo "ok a new parlor is announced to the town\n";
}

$quietSince = (int) (microtime(true) * 1000) - 10000;
$store->session('other')->lastPacket = $quietSince;
$game->handle('/request.xp?command&i_command=24', 'other');
if ((int) $store->session('other')->lastPacket !== $quietSince) {
    $failed++;
    fwrite(STDERR, "FAIL empty cafe poll refreshed activity\n");
} else {
    echo "ok an empty cafe poll does not count as activity\n";
}
$game->handle('/request.xp?command&i_command=24&st_input=still+here', 'other');
if ((int) $store->session('other')->lastPacket <= $quietSince) {
    $failed++;
    fwrite(STDERR, "FAIL speech did not refresh activity\n");
} else {
    echo "ok speaking in the cafe counts as activity\n";
}

$plotsBefore = (int) $helen->plots;
$woodBefore = (int) $helen->wood;
$idle = $store->session('helenbox');
$idle->playerId = $helen->id;
$idle->lastPacket = (int) (microtime(true) * 1000) - (5 * 60 * 1000);
$idle->customer = (object) [
    'male' => false,
    'budget' => 900,
    'casketIndex' => 0,
    'casket' => (object) ['id' => 'wood'],
    'upgrade' => -1,
    'options' => [],
];
$idle->risk = null;
unset($idle->vm);
$store->save();
$booted = $game->handle('/request.xp?command&i_command=4', 'helenbox');
expect('kicked for inactivity', $booted, ['user+not+logged+on', 'i_sent=-1']);
$kickHeard = false;
foreach ($store->session('other')->pendingYells ?? [] as $line) {
    if (str_contains((string) $line, $helen->name . ' got kicked for inactivity')) {
        $kickHeard = true;
    }
}
if (!$kickHeard || $store->session('helenbox')->playerId !== null || (int) $helen->plots !== $plotsBefore + 1 || (int) $helen->wood !== $woodBefore + 1) {
    $failed++;
    fwrite(STDERR, "FAIL kick heard=" . var_export($kickHeard, true) . " plots={$helen->plots} wood={$helen->wood}\n");
} else {
    echo "ok four idle minutes kicks you, finishes the sale, and tells the town\n";
}

$teller->turns = 0;
$store->session('other')->customer = null;
$store->session('other')->risk = null;
unset($store->session('other')->vm);
$store->save();
$closedSign = $game->handle('/request.xp?command&i_command=2', 'other');
$closedLine = str_contains($closedSign, 'come+back+tomorrow')
    || str_contains($closedSign, 'closing+time')
    || str_contains($closedSign, 'look+at+your+watch');
if (!$closedLine || !str_contains($closedSign, 'i_sent=2') || str_contains($closedSign, 'i_money=') || str_contains($closedSign, 'i_turns=')) {
    $failed++;
    fwrite(STDERR, "FAIL closed sign\n" . substr($closedSign, 0, 400) . "\n");
} else {
    echo "ok out of turns shows the closed sign without the stats\n";
}
$mystery = $game->handle('/request.xp?command&i_command=99', 'other');
expect('an unknown command is the original error', $mystery, ['g_st_error=Server+unable+to+process+request+99.', 'i_sent=-1']);
$graveyard = $game->handle('/request.xp?command&i_command=35', 'other');
expect('the graveyard has no server handler', $graveyard, ['g_st_error=Server+unable+to+process+request+35.', 'i_sent=-1']);
$cityHall = $game->handle('/request.xp?command&i_command=32', 'other');
if (rtrim($cityHall, "/rn") !== 'st_object=i_city_hall&i_sent=10&') {
    $failed++;
    fwrite(STDERR, "FAIL city hall\n" . substr($cityHall, 0, 400) . "\n");
} else {
    echo "ok city hall only asks the client for its own object\n";
}

$shipped = fq_settings();
$fresh = fq_game($dir . '/settings/players.json');
if (($shipped['max_players'] ?? null) !== $fresh->maxPlayers || ($shipped['max_online'] ?? null) !== $fresh->maxOnline || !array_key_exists('timezone', $shipped)) {
    $failed++;
    fwrite(STDERR, "FAIL settings.php limits are not applied\n");
} else {
    echo "ok settings.php sets the town limits\n";
}
if ($fresh->tournamentDays !== (int) ($shipped['tournament_days'] ?? -1) || $fresh->serverNews !== (string) ($shipped['server_news'] ?? null)) {
    $failed++;
    fwrite(STDERR, "FAIL settings.php tournament or server news not applied\n");
} else {
    echo "ok settings.php sets tournament mode and server news\n";
}
$fresh->store->release();

$cup = fq_game($dir . '/tourney/players.json');
$cup->tournamentDays = 3;
$cup->serverNews = '`wWelcome to the cup!';
$cupIntro = $cup->handle('/request.xp?checklogon', 'cupA');
expect('tournament intro', $cupIntro, ['Game+Info%3A+Tournament+Mode+Active+(day+1+of+3)+-+0+of+0+players+on+now.', 'st_server_news=<font+color=%22#FFFFFF%22>Welcome+to+the+cup%21']);
$cup->handle('/request.xp?new&name=Victor&logon=victor&f_name=Laurel&password=secret&password_v=secret&email=v@example.com&b_male=1', 'cupA');
$cup->handle('/request.xp?new&name=Rowena&logon=rowena&f_name=Thistle&password=secret&password_v=secret&email=r@example.com&b_male=0', 'cupB');
$cupFront = $cup->handle('/request.xp?command&i_command=5', 'cupA');
expect('tournament countdown in the paper', $cupFront, ['Only+2+days+left+before+a+tournament+winner+shall+be+declared%21']);
$victor = $cup->store->findLogon('victor');
$rowena = $cup->store->findLogon('rowena');
$victor->exp = 140;
$victor->plots = 9;
$victor->buryPoints = 30;
$victor->dayLastLoggedOn = 3;
$rowena->exp = 60;
$rowena->dayLastLoggedOn = 3;
$cup->store->state->day = 3;
$cup->store->state->date = '2000-01-01';
$cup->store->state->news = [];
$frozen = $cup->handle('/request.xp?checklogon', 'cupC');
expect('tournament over at the door', $frozen, ['Game+Info%3A+Tournament+is+OVER%2C+Victor+declared+winner.++New+game+starting+tomorrow.', 'st_no_more=A+new+game+is+starting+tomorrow%2C+please+come+back+then%21']);
$frozenNews = implode('', $cup->store->state->news ?? []);
$bonusMail = 0;
foreach ([$victor, $rowena] as $resident) {
    foreach ($resident->inbox ?? [] as $note) {
        if (str_contains((string) ($note->text ?? ''), 'Congratulations')) {
            $bonusMail++;
        }
    }
}
if ((int) $cup->store->state->day !== 4 || !str_contains($frozenNews, 'TOURNAMENT WINNER HAS BEEN DECLARED') || !str_contains($frozenNews, 'With 140 Bury Points and 9 plots, Victor has been declared the victor.')
    || !str_contains($frozenNews, 'GIANT METEOR') || $bonusMail !== 0 || (int) $victor->yangsSent !== 1
    || ($cup->store->state->deeds ?? '') !== "Victor won the 'Best Funeral Directory In The Universe' award.\n") {
    $failed++;
    fwrite(STDERR, "FAIL tournament final day day={$cup->store->state->day} mail=$bonusMail yangs={$victor->yangsSent}\n$frozenNews\n");
} else {
    echo "ok the tournament ends with a winner, a meteor, and no bonuses\n";
}
$cup->handle('/request.xp?logon&st_logon=victor&st_password=secret', 'cupA');
$cupPaper = $cup->handle('/request.xp?command&i_command=5', 'cupA');
expect('top seller of the universe', $cupPaper, ['st_top_today=Top+seller+of+the+universe+is+Victor.']);
if ((int) $victor->turns !== 0 || (int) $victor->visits !== 2) {
    $failed++;
    fwrite(STDERR, "FAIL frozen day turns={$victor->turns} visits={$victor->visits}\n");
} else {
    echo "ok nobody can play on the frozen day\n";
}
$cup->store->state->date = '2000-01-02';
$restart = $cup->handle('/request.xp?command&i_command=4', 'cupA');
expect('the reset logs everyone off', $restart, ['user+not+logged+on', 'i_sent=-1']);
$cupNew = $cup->handle('/request.xp?checklogon', 'cupA');
expect('a new game starts at day 1', $cupNew, ['Tournament+Mode+Active+(day+1+of+3)+-+0+of+0+players']);
if (count($cup->store->state->players) !== 0 || ($cup->store->state->deeds ?? '') === '' || ($cup->store->state->topYesterday ?? '') !== 'No one died yesterday.') {
    $failed++;
    fwrite(STDERR, "FAIL tournament reset players=" . count($cup->store->state->players) . "\n");
} else {
    echo "ok the town is wiped but the book of deeds remains\n";
}
$cup->handle('/request.xp?new&name=Newbie&logon=newbie&f_name=Fresh&password=secret&password_v=secret&email=n@example.com&b_male=1', 'cupA');
$cup->handle('/request.xp?command&i_command=36', 'cupA');
$book = $cup->handle('/request.xp?command&i_command=100&i_command_sub=8', 'cupA');
expect('the book of deeds lists the winner', $book, ["The+Book+Of+Deeds", "Victor+won+the+'Best+Funeral+Directory+In+The+Universe'+award."]);
$cup->handle('/request.xp?new&name=Tom+%26+%22Jerry%22&logon=tomjerry&f_name=Cat&password=secret&password_v=secret&email=t@example.com&b_male=0', 'cupB');
$cup->handle('/request.xp?new&name=Gone&logon=goner&f_name=Closed&password=secret&password_v=secret&email=g@example.com&b_male=1', 'cupC');
$gone = $cup->store->findLogon('goner');
$gone->exp = 999;
$gone->active = false;
$cup->store->findLogon('tomjerry')->exp = 1250;
$rankPage = (string) $cup->webPage('rankings');
expect('rankings page', $rankPage, ['<title>Funeral Quest Rankings</title>', '<tr><td width="30">1</td><td width="212">Tom &amp; &quot;Jerry&quot;</td><td width="47">F</td><td width="206">Cat</td><td width="131"><div align="right">1,250</div></td></tr><tr><td width="30">3</td><td width="212">Newbie</td>', 'Go back']);
$cup->adminEmail = 'boss@example.com';
$serverPage = (string) $cup->webPage('server');
expect('server settings page', $serverPage, ['Server Configuration Information', '<a href="mailto:boss@example.com">boss@example.com</a>', 'Turns per day: <font color="#FFFFFF">15<br>', 'Starting Luck: <font color="#FFFFFF">50<br>', 'Go back']);
if ($cup->webPage('../private/settings') !== null) {
    $failed++;
    fwrite(STDERR, "FAIL webPage served an unknown page\n");
} else {
    echo "ok only the two generated pages are served\n";
}
$cup->store->release();

$hookDist = $dir . '/hookdist';
mkdir($hookDist . '/script', 0777, true);
copy(fq_roots()['dist'] . '/script/init.c', $hookDist . '/script/init.c');
file_put_contents($hookDist . '/script/newday_player.c', "void Main()\n{\n  ModTurns(i_my_id, 20);\n  SetPolice(i_my_id, 1);\n  ModLuck(i_my_id, 200);\n  String st_piece = GetStringPiece(\"alpha|beta|gamma\", \"|\", 2);\n  if (st_piece == \"beta\")\n  {\n    ModStrength(i_my_id, 4);\n  }\n}\n");
file_put_contents($hookDist . '/script/maint_player.c', "void Main()\n{\n  ModTotalBPs(i_my_id, 5);\n  ModPlayerFights(i_my_id, 1);\n}\n");
file_put_contents($hookDist . '/script/maint.c', "void Main()\n{\n  int i_day = GetDay();\n  int i_top = GetTopPlayerID();\n  int i_other = GetRandomPlayerID(i_top);\n  String st_top = GetNameFromID(i_top);\n  AddToNews(\"`wMaint ran on day \$i_day\$, leader \$st_top\$, other \$i_other\$.\\n\");\n}\n");
file_put_contents($hookDist . '/script/reset.c', "void Main()\n{\n  AddToNews(\"`wReset ran.\\n\");\n}\n");
$hooks = fq_game($dir . '/hooks/players.json');
(new ReflectionProperty(FqGame::class, 'distRoot'))->setValue($hooks, $hookDist);
$hooks->tournamentDays = 1;
$hooks->handle('/request.xp?new&name=Hooky&logon=hooky&f_name=Hooks&password=secret&password_v=secret&email=h@example.com&b_male=1', 'hookA');
$hookMorning = $hooks->handle('/request.xp?command&i_command=4', 'hookA');
expect('newday_player.c runs at the first logon of the day', $hookMorning, ['There+are+police+patrolling+your+parlor.']);
$hooky = $hooks->store->findLogon('hooky');
if ((int) $hooky->turns !== 35 || (int) $hooky->luck !== 100 || (int) $hooky->strength !== 7 || empty($hooky->police)) {
    $failed++;
    fwrite(STDERR, "FAIL newday_player.c turns={$hooky->turns} luck={$hooky->luck} strength={$hooky->strength}\n");
} else {
    echo "ok newday_player.c changes turns, luck, strength, and police\n";
}
$hooks->store->state->date = '2000-01-01';
$hooks->handle('/request.xp?checklogon', 'hookB');
$hookNews = implode('', $hooks->store->state->news ?? []);
if ((int) $hooky->exp !== 5 || (int) $hooky->visits !== 1 || !str_contains($hookNews, 'Maint ran on day 2, leader Hooky, other -1.')) {
    $failed++;
    fwrite(STDERR, "FAIL maint scripts exp={$hooky->exp} visits={$hooky->visits}\n$hookNews\n");
} else {
    echo "ok maint_player.c runs per parlor and maint.c after maint\n";
}
$hooks->store->state->date = '2000-01-02';
$hooks->handle('/request.xp?checklogon', 'hookB');
$hookYesterday = implode('', $hooks->store->state->yesterday ?? []);
$hookNews = implode('', $hooks->store->state->news ?? []);
if (!str_contains($hookYesterday, 'Reset ran.') || !str_contains($hookNews, 'Maint ran on day 1, leader , other -1.')
    || !isset($hooks->store->state->scriptGlobals->C_WOOD_TAG)) {
    $failed++;
    fwrite(STDERR, "FAIL reset.c yesterday=$hookYesterday news=$hookNews\n");
} else {
    echo "ok reset.c runs on a tournament reset and init.c globals come back\n";
}
$shippedRules = [];
foreach (FqGame::DEFAULT_RULES as $ruleKey => $ruleDefault) {
    $shippedRules[$ruleKey] = is_bool($shipped[$ruleKey] ?? null) ? (int) $shipped[$ruleKey] : ($shipped[$ruleKey] ?? null);
}
if ($shippedRules !== FqGame::DEFAULT_RULES) {
    $failed++;
    fwrite(STDERR, "FAIL settings.php game settings differ from the original defaults\n");
} else {
    echo "ok settings.php ships the original game settings\n";
}
$ruled = fq_game($dir . '/rules/players.json');
$ruled->rules = array_merge(FqGame::DEFAULT_RULES, [
    'turns_per_day' => 20, 'yangs_per_day' => 3, 'visits_per_day' => 4, 'risk' => 75, 'tags_per_day' => 0,
    'name_change_cost' => 0, 'starting_luck' => 77, 'starting_hp' => 12, 'starting_strength' => 5,
]);
$rulePage = (string) $ruled->webPage('server');
expect('server page shows the configured settings', $rulePage, ['Turns per day: <font color="#FFFFFF">20<br>', 'Player visits per day: <font color="#FFFFFF">4<br>', 'Risk win/lose amount: <font color="#FFFFFF">75<br>', 'Cost to change name: <font color="#FFFFFF">Disabled<br>', 'Starting Luck: <font color="#FFFFFF">77<br>']);
$ruled->handle('/request.xp?new&name=Ruler&logon=ruler&f_name=Rules&password=secret&password_v=secret&email=r@example.com&b_male=1', 'ruleA');
$ruled->handle('/request.xp?command&i_command=4', 'ruleA');
$ruler = $ruled->store->findLogon('ruler');
if ((int) $ruler->turns !== 20 || (int) $ruler->luck !== 77 || (int) $ruler->hpMax !== 12 || (int) $ruler->strength !== 5) {
    $failed++;
    fwrite(STDERR, "FAIL configured start turns={$ruler->turns} luck={$ruler->luck} hp={$ruler->hpMax} strength={$ruler->strength}\n");
} else {
    echo "ok new parlors start with the configured turns, luck, hitpoints, and strength\n";
}
$ruleSheet = $ruled->handle('/request.xp?command&i_command=3', 'ruleA');
expect('stats screen uses the configured limits', $ruleSheet, ['i_yangs=3&', 'i_visits=4&']);
$ruled->handle('/request.xp?command&i_command=4', 'ruleA');
$noRename = $ruled->handle('/request.xp?command&i_command=43', 'ruleA');
expect('name changes can be turned off', $noRename, ["This+server+doesn't+allow+name+changes.", 'Continue']);
$ruled->handle('/request.xp?command&i_command=4', 'ruleA');
$ruleSession = $ruled->store->session('ruleA');
$ruleSession->customer = (object) [];
$ruleSession->risk = 0;
$ruler->buryPoints = 500;
$ruler->exp = 500;
$ruleRisk = $ruled->handle('/request.xp?command&i_command=11', 'ruleA');
if (!str_contains($ruleRisk, '75') || !in_array((int) $ruler->exp, [425, 575], true)) {
    $failed++;
    fwrite(STDERR, "FAIL configured risk exp={$ruler->exp}\n" . substr($ruleRisk, 0, 300) . "\n");
} else {
    echo "ok risks use the configured amount\n";
}
$ruled->store->release();

$ipTown = fq_game($dir . '/iptown/players.json');
$ipTown->clientIp = '198.51.100.7';
$ipTown->handle('/request.xp?new&name=Firstie&logon=firstie&f_name=Primary&password=secret&password_v=secret&email=f@example.com&b_male=1', 'ipA');
$ipTown->handle('/request.xp?command&i_command=4', 'ipA');
$ipTown->clientIp = '198.51.100.8';
$ipTown->handle('/request.xp?new&name=Secondo&logon=secondo&f_name=Backup&password=secret&password_v=secret&email=s@example.com&b_male=1', 'ipB');
$ipTown->handle('/request.xp?checklogon', 'ipB');
$ipTown->clientIp = '198.51.100.7';
$sameNew = $ipTown->handle('/request.xp?new&name=Thirdly&logon=thirdly&f_name=Spare&password=secret&password_v=secret&email=t@example.com&b_male=1', 'ipC');
expect('one new parlor per IP per day', $sameNew, ['only+allows+one+player+per+IP+address+per+day.', 'i_sent=-1']);
$sameLogon = $ipTown->handle('/request.xp?logon&st_logon=secondo&st_password=secret', 'ipC');
expect('one logon per IP per day', $sameLogon, ['only+allows+one+player+per+IP+address+per+day.', 'i_sent=-1']);
$firstie = $ipTown->store->findLogon('firstie');
$firstie->always = true;
$allowed = $ipTown->handle('/request.xp?logon&st_logon=secondo&st_password=secret', 'ipC');
expect('always-allowed parlors do not block the IP', $allowed, ['i_sent=5']);
$secondo = $ipTown->store->findLogon('secondo');
if ($firstie->lastIp !== '198.51.100.7' || $secondo->lastIp !== '198.51.100.7' || $secondo->oldIp !== '198.51.100.8' || ($ipTown->store->session('ipC')->ip ?? '') !== '198.51.100.7') {
    $failed++;
    fwrite(STDERR, "FAIL IP tracking\n");
} else {
    echo "ok last and previous IPs are kept per parlor\n";
}
$ipTown->rules['ip_check'] = 0;
$ipTown->handle('/request.xp?checklogon', 'ipD');
$unchecked = $ipTown->handle('/request.xp?new&name=Thirdly&logon=thirdly&f_name=Spare&password=secret&password_v=secret&email=t@example.com&b_male=1', 'ipD');
expect('the IP check can be turned off', $unchecked, ['i_sent=5']);
$ipErrors = $ipTown->system->file('errorlog.txt');
if (!str_contains($ipErrors, ':User with IP 198.51.100.7 not allowed to create a new user. (already on as Firstie (198.51.100.7) today)')
    || !str_contains($ipErrors, ":User with IP 198.51.100.7 can't logon as Secondo because he already played as Firstie (198.51.100.7) today.")) {
    $failed++;
    fwrite(STDERR, "FAIL IP refusals go to errorlog.txt\n$ipErrors\n");
} else {
    echo "ok IP refusals go to errorlog.txt\n";
}
$ipTown->store->release();
if (!fq_is_banned('66.66.66.66') || !fq_is_banned('x66.66.66.66') || fq_is_banned('10.0.0.1') || fq_is_banned('')) {
    $failed++;
    fwrite(STDERR, "FAIL ban.txt matching\n");
} else {
    echo "ok ban.txt refuses matching IPs like the original\n";
}

$logTown = fq_game($dir . '/logtown/players.json');
$logTown->clientIp = '192.0.2.9';
$logTown->handle('/request.xp?new&name=Logger&logon=logger&f_name=Logs&password=secret&password_v=secret&email=l@example.com&b_male=1', 'logA');
$logTown->handle('/request.xp?checklogon', 'logA');
$logTown->handle('/request.xp?logon&st_logon=logger&st_password=secret', 'logA');
$logTown->handle('/request.xp?command&i_command=4', 'logB');
$logTown->webPage('server');
$before = $logTown->system->read();
$logTown->forceNewDay();
$after = $logTown->system->read();
$statsLine = $logTown->system->file('stats.txt');
$joined = implode("\n", $after['log']);
$logChecks = [
    'counts logons and hits' => $before['logonsToday'] === 2 && $before['hitsToday'] === 5,
    'maint writes stats.txt' => (bool) preg_match('/^\d\d-\d\d \d\d:\d\d:Logons=2, hits=5\r\n$/', $statsLine),
    'maint zeroes today but keeps totals' => $after['logonsToday'] === 0 && $after['hitsToday'] === 0 && $after['logonsTotal'] === 2 && $after['hitsTotal'] === 5,
    'logs new accounts' => str_contains($joined, ':Creating player account 0.') && str_contains($joined, 'Logger has just moved into town!'),
    'logs connections' => str_contains($joined, ':Connection from Logger (192.0.2.9)'),
    'logs error messages' => str_contains($joined, ':Sending error message info to user : Connection with server died. (user not logged on)'),
    'logs maint' => str_contains($joined, ':Running maint.'),
    'log lines carry the date and time' => (bool) preg_match('/^\d\d-\d\d \d{1,2}:\d\d (AM|PM):/', $after['log'][0] ?? ''),
    'log is off disk by default' => $logTown->system->file('log.txt') === '',
];
$logTown->system->logToDisk = true;
$logTown->system->log('disk line');
$logTown->system->error('script trouble');
$logTown->system->flush();
$logChecks['log_to_disk saves log.txt'] = str_ends_with($logTown->system->file('log.txt'), ":disk line\r\n");
$logChecks['LogError writes errorlog.txt'] = (bool) preg_match('/^WARNING: \d\d-\d\d \d{1,2}:\d\d (AM|PM):script trouble\r\n$/', $logTown->system->file('errorlog.txt'));
for ($line = 0; $line < 100; $line++) {
    $logTown->system->log("filler $line");
}
$logChecks['window keeps the last 80 lines'] = count($logTown->system->read()['log']) === 80 && str_ends_with($logTown->system->read()['log'][79], ':filler 99');
$logTown->store->release();
foreach ($logChecks as $label => $ok) {
    if (!$ok) {
        $failed++;
        fwrite(STDERR, "FAIL server log: $label\n" . json_encode($after) . "\n" . json_encode($before) . "\n");
    } else {
        echo "ok server log: $label\n";
    }
}

$cafeFile = $dir . '/cafe/players.json';
$cafeRequest = function (string $query, string $sessionId) use ($cafeFile): string {
    $town = fq_game($cafeFile);
    $town->clientIp = '198.51.100.7';
    $body = (string) $town->handle('/request.xp?' . $query, $sessionId);
    $town->store->release();
    return $body;
};
$cafeRequest('new&name=Sipper&logon=sipper&f_name=Mocha&password=secret&password_v=secret&email=s@example.com&b_male=1', 'cafeA');
$cafeRequest('command&i_command=23', 'cafeA');
$cafeFirst = $cafeRequest('command&i_command=24', 'cafeA');
$cafeQuiet = $cafeRequest('command&i_command=24', 'cafeA');
$cafeSaid = $cafeRequest('command&i_command=24&st_input=hello+there', 'cafeA');
$cafeAfter = $cafeRequest('command&i_command=24', 'cafeA');
$cafeChecks = [
    'the first poll delivers the arrival' => substr_count($cafeFirst, 'saunters+in.') === 1,
    'an idle poll delivers nothing again' => str_contains($cafeQuiet, 'st_message=&'),
    'a spoken line comes back once' => substr_count($cafeSaid, 'hello') === 1 && !str_contains($cafeSaid, 'saunters+in.'),
    'a spoken line breaks before its trailing size tag' => str_contains($cafeSaid, "there\r<font+size=%2212%22>&"),
    'every line ends in a break' => str_contains($cafeFirst, "town%21\r<font") && str_contains($cafeFirst, "saunters+in.\r&"),
    'the next poll does not repeat it' => !str_contains($cafeAfter, 'hello'),
];
foreach ($cafeChecks as $label => $ok) {
    if (!$ok) {
        $failed++;
        fwrite(STDERR, "FAIL cafe across requests: $label\n$cafeFirst\n$cafeQuiet\n$cafeSaid\n$cafeAfter\n");
    } else {
        echo "ok cafe across requests: $label\n";
    }
}

require_once __DIR__ . '/src/Admin.php';
$adminDir = $dir . '/admin';
$adminGame = fn () => fq_game($adminDir . '/players.json');
$opsStore = fq_game($adminDir . '/players.json');
$opsStore->handle('/request.xp?new&name=Opsman&logon=opsman&f_name=Opsy&password=secret&password_v=secret&email=o@example.com&b_male=1', 'opsA');
$opsStore->handle('/request.xp?command&i_command=4', 'opsA');
$opsStore->store->release();
$admin = function (string $method, array $get = [], array $post = [], ?string $cookie = null, string $password = 'operator-pass') use ($adminDir, $adminGame): array {
    return fq_admin_handle(['method' => $method, 'get' => $get, 'post' => $post, 'ip' => '203.0.113.5', 'cookie' => $cookie, 'password' => $password, 'dir' => $adminDir, 'game' => $adminGame]);
};
$off = $admin('GET', [], [], null, 'short');
expect('operator page is off without a password', $off['html'], ['switched off']);
$gate = $admin('GET');
expect('operator page asks for the password', $gate['html'], ['type="password"']);
$wrong = $admin('POST', [], ['action' => 'login', 'password' => 'nope']);
expect('operator page refuses a wrong password', $wrong['html'], ['Wrong password.']);
$in = $admin('POST', [], ['action' => 'login', 'password' => 'operator-pass']);
$opCookie = (string) $in['cookie'];
$town = $admin('GET', [], [], $opCookie);
preg_match('/name="csrf" value="([a-f0-9]+)"/', $town['html'], $csrfMatch);
$opCsrf = $csrfMatch[1] ?? '';
expect('operator town view', $town['html'], ['No Reset Mode Active (day 1)', '1 parlors, 1 on now', 'Run maint now', 'Logons today: 1 (total 1). Hits today: 2 (total 2).']);
$logView = $admin('GET', ['view' => 'log'], [], $opCookie);
expect('operator log view', $logView['html'], [':Creating player account 0.', 'Opsman has just moved into town!', 'No maint has run yet.', 'No errors.']);
$forged = $admin('POST', [], ['action' => 'newday', 'csrf' => 'bad'], $opCookie);
$afterForged = $admin('GET', [], [], $opCookie);
expect('operator forms need the CSRF token', $afterForged['html'], ['That form expired.', '(day 1)']);
$admin('POST', [], ['action' => 'newday', 'csrf' => $opCsrf], $opCookie);
$afterDay = $admin('GET', [], [], $opCookie);
expect('operator new day runs maint', $afterDay['html'], ['Maint ran. It is now day 2.', '(day 2)']);
$editPage = $admin('GET', ['view' => 'edit', 'id' => '0'], [], $opCookie);
expect('operator player editor', $editPage['html'], ['Edit Opsman', 'name="luck"', 'Always allowed on']);
$saveFields = ['action' => 'save', 'csrf' => $opCsrf, 'index' => '0', 'logon' => 'opsman', 'name' => 'Ops <b>Man</b>', 'funeralName' => 'Opsy', 'email' => 'o@example.com',
    'password' => 'newpass', 'exp' => '900', 'luck' => '250', 'gold' => '7', 'transport' => '99', 'active' => '1', 'always' => '1', 'male' => '1'];
$admin('POST', [], $saveFields, $opCookie);
$deleteOnline = $admin('POST', [], array_merge($saveFields, ['active' => '']), $opCookie);
$afterDelete = $admin('GET', ['view' => 'edit', 'id' => '0'], [], $opCookie);
expect('operator cannot delete an online player', $afterDelete['html'], ['cannot delete the guy, he&#039;s online.']);
$check = fq_game($adminDir . '/players.json');
$opsman = $check->store->findLogon('opsman');
$opsOk = $opsman && $opsman->name === 'Ops bManb' && (int) $opsman->exp === 900 && (int) $opsman->luck === 100 && (int) $opsman->gold === 7
    && (int) $opsman->transport === count(fq_transport()) - 1 && $opsman->always === true && ($opsman->active ?? true) !== false
    && fq_verify_password($opsman->passwordHash ?? null, 'newpass');
$check->store->release();
if (!$opsOk) {
    $failed++;
    fwrite(STDERR, "FAIL operator player save " . json_encode($opsman) . "\n");
} else {
    echo "ok operator saves player fields with the game's name rules\n";
}
$online = $admin('GET', ['view' => 'online'], [], $opCookie);
expect('operator online list', $online['html'], ['Ops bManb', '>Kick</button>']);
$admin('POST', [], ['action' => 'kick', 'session' => 'opsA', 'csrf' => $opCsrf], $opCookie);
$kicked = $admin('GET', ['view' => 'online'], [], $opCookie);
expect('operator kick', $kicked['html'], ['Ops bManb was kicked.', 'Nobody is on.']);
$admin('POST', [], ['action' => 'ban', 'ip' => 'not an ip', 'csrf' => $opCsrf], $opCookie);
$badBan = $admin('GET', ['view' => 'bans'], [], $opCookie);
expect('operator ban needs an IP', $badBan['html'], ['That is not an IP address.', '66.66.66.66']);
$admin('POST', [], ['action' => 'reset', 'confirm' => 'nah', 'csrf' => $opCsrf], $opCookie);
$admin('POST', [], ['action' => 'reset', 'confirm' => 'RESET', 'csrf' => $opCsrf], $opCookie);
$afterReset = $admin('GET', [], [], $opCookie);
expect('operator reset wipes the town', $afterReset['html'], ['The game was reset.', '0 parlors', '(day 1)']);
$admin('POST', [], ['action' => 'logout', 'csrf' => $opCsrf], $opCookie);
$loggedOut = $admin('GET', [], [], $opCookie);
expect('operator log off', $loggedOut['html'], ['type="password"']);

$piece = new ReflectionMethod(FqGame::class, 'stringPiece');
$pieces = [
    $piece->invoke($hooks, "a,b\n,c", ',', 1), $piece->invoke($hooks, "a,b\n,c", ',', 2), $piece->invoke($hooks, "a,b\n,c", ',', 3),
    $piece->invoke($hooks, "a,b\n,c", ',', 4), $piece->invoke($hooks, "a,b\n,c", ',', 0), $piece->invoke($hooks, 'whole', '', 1),
];
$call = new ReflectionMethod(FqGame::class, 'hostCall');
$noGlobals = [];
$api = new FqRuntime($noGlobals, fn () => ['type' => 'int', 'value' => 0], fn () => null);
$float = $call->invoke($hooks, (object) ['id' => ''], 'FRANDOMRANGE', [['type' => 'float', 'value' => 1.5], ['type' => 'float', 'value' => 1.5]], $api, -1);
if ($pieces !== ['a', 'b', 'c', '', 'c', 'whole'] || $float !== ['type' => 'float', 'value' => 1.5]) {
    $failed++;
    fwrite(STDERR, "FAIL GetStringPiece/FRandomRange " . json_encode([$pieces, $float]) . "\n");
} else {
    echo "ok GetStringPiece and FRandomRange match the original\n";
}
$hooks->store->release();

$townSize = 0;
foreach ($store->state->players as $resident) {
    if (($resident->active ?? true) !== false) {
        $townSize++;
    }
}
$uptimeFile = $dir . '/uptime/players.json';
$restarted = fq_game($uptimeFile);
$restarted->store->state->bootedAt = time() - 125;
$restarted->store->save();
$restarted->store->release();
$sameTown = fq_game($uptimeFile);
expect('uptime survives between requests', (string) $sameTown->handle('/request.xp?checklogon', 'uptime'), ['Uptime%3A+2+Min']);
$sameTown->store->release();
$open = $game->handle('/request.xp?checklogon', 'fulltown');
expect('a town with room has no full line', $open, ['st_no_more=&']);
$game->maxPlayers = $townSize;
$full = $game->handle('/request.xp?checklogon', 'fulltown');
expect('a full town says so on the intro', $full, ["st_no_more=The+town+is+full.++(this+server+allows+{$townSize}+players+max)++Come+back+later%21"]);
$turnedAway = $game->handle('/request.xp?new&name=Latecomer&logon=latecomer&f_name=Restful&password=secret&password_v=secret&email=a@b.co&b_male=1', 'fulltown');
expect('a full town refuses new parlors', $turnedAway, ['The+town+is+full.', 'i_sent=4']);
if ($store->findLogon('latecomer') !== null) {
    $failed++;
    fwrite(STDERR, "FAIL a parlor opened in a full town\n");
} else {
    echo "ok no parlor opens in a full town\n";
}
$game->maxPlayers = 800;
$onNow = 0;
foreach ($store->state->sessions as $visitor) {
    if ($visitor->playerId !== null) {
        $onNow++;
    }
}
$game->maxOnline = $onNow;
$crowded = $game->handle('/request.xp?logon&st_logon=helen&st_password=secret', 'crowd');
expect('a crowded server turns away logons', $crowded, ["g_st_error=Sorry%2C+there+are+already+{$onNow}+players+logged+on+right+now", 'i_sent=-1']);
if ($store->session('crowd')->playerId !== null) {
    $failed++;
    fwrite(STDERR, "FAIL a crowded server still logged someone on\n");
} else {
    echo "ok a crowded server keeps you off\n";
}
$game->maxOnline = 500;

$game->handle('/request.xp?checklogon', 'drifter');
$game->handle('/request.xp?checklogon', 'passerby');
if (isset($store->state->sessions->drifter) || !isset($store->state->sessions->passerby)) {
    $failed++;
    fwrite(STDERR, "FAIL empty sessions are not pruned\n");
} else {
    echo "ok sessions without a parlor are pruned\n";
}

$lockDir = sys_get_temp_dir() . '/fq-lock-' . bin2hex(random_bytes(4));
mkdir($lockDir);
$lockFile = $lockDir . '/players.json';
$holder = fq_game($lockFile);
$holder->handle('/request.xp?new&name=Lockwood&logon=lockwood&f_name=Stillness&password=secret&password_v=secret&email=l@example.com&b_male=1', 'holder');
$php = PHP_BINARY;
$probeFile = $lockDir . '/probe.php';
file_put_contents($probeFile, '<?php $h = fopen(' . var_export($lockFile . '.lock', true) . ', \'c\'); echo flock($h, LOCK_EX | LOCK_NB) ? \'free\' : \'held\';');
$whileHeld = trim((string) shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($probeFile)));
$holder->store->release();
$afterRelease = trim((string) shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($probeFile)));
if ($whileHeld !== 'held' || $afterRelease !== 'free') {
    $failed++;
    fwrite(STDERR, "FAIL town lock while held=$whileHeld after=$afterRelease\n");
} else {
    echo "ok a request holds the town lock until it finishes\n";
}
$leftovers = glob($lockDir . '/*.tmp') ?: [];
$reopened = fq_game($lockFile);
if ($leftovers || $reopened->store->findLogon('lockwood') === null) {
    $failed++;
    fwrite(STDERR, "FAIL atomic save left temp files or lost the parlor\n");
} else {
    echo "ok saves land whole with no temp files left\n";
}
$reopened->store->release();
file_put_contents($lockFile, '{"players": [');
try {
    fq_game($lockFile);
    $failed++;
    fwrite(STDERR, "FAIL a damaged town file was replaced\n");
} catch (RuntimeException $damaged) {
    if (file_get_contents($lockFile) !== '{"players": [') {
        $failed++;
        fwrite(STDERR, "FAIL a damaged town file was rewritten\n");
    } else {
        echo "ok a damaged town file is never overwritten with an empty town\n";
    }
}

if ($failed) {
    fwrite(STDERR, "$failed failed\n");
    exit(1);
}
echo "protocol self-test passed\n";
