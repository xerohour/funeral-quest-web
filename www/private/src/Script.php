<?php
declare(strict_types=1);

function fq_script_up(string $name): string
{
    return strtoupper($name);
}

function fq_script_default(string $type): int|string
{
    return $type === 'string' ? '' : 0;
}

function fq_script_format(array $variable): string
{
    if (($variable['type'] ?? '') === 'string') {
        return (string) ($variable['value'] ?? '');
    }
    if (($variable['type'] ?? '') === 'float') {
        return number_format((float) $variable['value'], 6, '.', '');
    }
    return (string) (int) ($variable['value'] ?? 0);
}

function fq_script_expand(string $text, callable $lookup): string
{
    $out = '';
    $length = strlen($text);
    for ($i = 0; $i < $length; $i++) {
        $pair = substr($text, $i, 2);
        if ($pair === '\\\\') {
            $out .= '\\';
            $i++;
            continue;
        }
        if ($pair === '\\n') {
            $out .= "\n";
            $i++;
            continue;
        }
        if ($pair === '\\"') {
            $out .= '"';
            $i++;
            continue;
        }
        if ($text[$i] === '$') {
            if (($text[$i + 1] ?? '') === '$') {
                $out .= '$';
                $i++;
                continue;
            }
            $end = strpos($text, '$', $i + 1);
            if ($end === false) {
                $out .= '$';
                continue;
            }
            $variable = $lookup(substr($text, $i + 1, $end - ($i + 1)));
            if ($variable) {
                $out .= fq_script_format($variable);
            }
            $i = $end;
            continue;
        }
        $out .= $text[$i];
    }
    return $out;
}

function fq_script_strip_comment(string $line): string
{
    $out = '';
    $inString = false;
    $length = strlen($line);
    for ($i = 0; $i < $length; $i++) {
        $pair = substr($line, $i, 2);
        if (!$inString && $pair === '//') {
            break;
        }
        if ($line[$i] === '\\' && $inString) {
            $out .= $pair;
            $i++;
            continue;
        }
        if ($line[$i] === '"') {
            $inString = !$inString;
        }
        $out .= $line[$i];
    }
    return trim($out);
}

function fq_script_find_operator(string $line): ?array
{
    $inString = false;
    $length = strlen($line);
    for ($i = 0; $i < $length; $i++) {
        if ($line[$i] === '\\' && $inString) {
            $i++;
            continue;
        }
        if ($line[$i] === '"') {
            $inString = !$inString;
            continue;
        }
        if ($inString) {
            continue;
        }
        $two = substr($line, $i, 2);
        if (in_array($two, ['+=', '-=', '*=', '/=', '%='], true)) {
            return ['op' => $two, 'index' => $i];
        }
        if ($line[$i] === '=' && ($line[$i + 1] ?? '') !== '=') {
            return ['op' => '=', 'index' => $i];
        }
    }
    return null;
}

function fq_script_parse_atom(string $token): array
{
    $text = trim($token);
    if (str_starts_with($text, '"')) {
        $raw = '';
        $length = strlen($text);
        for ($i = 1; $i < $length; $i++) {
            if ($text[$i] === '\\') {
                $raw .= $text[$i] . ($text[$i + 1] ?? '');
                $i++;
                continue;
            }
            if ($text[$i] === '"') {
                break;
            }
            $raw .= $text[$i];
        }
        return ['kind' => 'str', 'value' => $raw];
    }
    if (preg_match('/^-?\d/', $text)) {
        if (str_contains($text, '.')) {
            return ['kind' => 'num', 'type' => 'float', 'value' => (float) $text];
        }
        return ['kind' => 'num', 'type' => 'int', 'value' => (int) $text];
    }
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*/', $text, $name)) {
        return ['kind' => 'var', 'name' => $name[0]];
    }
    return ['kind' => 'num', 'type' => 'int', 'value' => 0];
}

function fq_script_split_args(string $body): ?array
{
    $args = [];
    $current = '';
    $inString = false;
    $length = strlen($body);
    for ($i = 0; $i < $length; $i++) {
        $pair = substr($body, $i, 2);
        if ($inString && in_array($pair, ['\\\\', '\\n', '\\"'], true)) {
            $current .= $pair;
            $i++;
            continue;
        }
        if ($body[$i] === '"') {
            $inString = !$inString;
            $current .= $body[$i];
            continue;
        }
        if (!$inString && ($body[$i] === ',' || $body[$i] === ')' || $body[$i] === ';')) {
            if (trim($current) !== '') {
                $args[] = fq_script_parse_atom($current);
            }
            $current = '';
            if ($body[$i] !== ',') {
                break;
            }
            continue;
        }
        $current .= $body[$i];
    }
    if ($inString) {
        return null;
    }
    if (trim($current) !== '') {
        $args[] = fq_script_parse_atom($current);
    }
    return $args;
}

function fq_script_parse_call(string $line): ?array
{
    $open = strpos($line, '(');
    if ($open === false) {
        return null;
    }
    $name = trim(substr($line, 0, $open));
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
        return null;
    }
    $args = fq_script_split_args(substr($line, $open + 1));
    if ($args === null) {
        return null;
    }
    return ['name' => $name, 'args' => $args];
}

function fq_script_parse_if(string $line): ?array
{
    $open = strpos($line, '(');
    $close = strrpos($line, ')');
    if ($open === false || $close === false) {
        return null;
    }
    $body = trim(substr($line, $open + 1, $close - $open - 1));
    if (!preg_match('/^(.*?)\s*(==|!=|>=|<=|>|<)\s*(.*)$/', $body, $match)) {
        return null;
    }
    return ['left' => fq_script_parse_atom($match[1]), 'op' => $match[2], 'right' => fq_script_parse_atom($match[3])];
}

function fq_script_compile_expr(string $text): ?array
{
    $call = fq_script_parse_call(rtrim(trim($text), ';'));
    if ($call) {
        return ['kind' => 'call', 'name' => $call['name'], 'args' => $call['args']];
    }
    if (trim($text) === '') {
        return null;
    }
    return fq_script_parse_atom(rtrim(trim($text), ';'));
}

function fq_script_compile(string $source, array &$locals, array &$globals): array
{
    $functions = [];
    $current = null;
    $nest = -1;
    $lines = preg_split("/\r\n|\n|\r/", $source);
    foreach ($lines as $raw) {
        $line = fq_script_strip_comment($raw);
        if ($line === '' || str_starts_with($line, '/')) {
            continue;
        }
        if ($current === null) {
            if (!preg_match('/^void\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i', $line, $header)) {
                continue;
            }
            $current = ['name' => $header[1], 'key' => fq_script_up($header[1]), 'ops' => [], 'labels' => [], 'savedParent' => null];
            $functions[$current['key']] = $current;
            $nest = -1;
            continue;
        }
        if ($nest === -1) {
            if (str_starts_with($line, '{')) {
                $nest = 0;
            } else {
                $current = null;
            }
            continue;
        }
        if (str_starts_with($line, '}')) {
            $nest--;
            if ($nest < 0) {
                $functions[$current['key']] = $current;
                $current = null;
            }
            continue;
        }
        if (str_starts_with($line, '{')) {
            $nest++;
            continue;
        }
        if (preg_match('/^goto\s+/i', $line)) {
            $label = trim(rtrim(preg_replace('/^goto\s+/i', '', $line), ';'));
            $current['ops'][] = ['kind' => 'goto', 'nest' => $nest, 'label' => $label];
            continue;
        }
        if (preg_match('/^return\b/i', $line)) {
            $current['ops'][] = ['kind' => 'return', 'nest' => $nest];
            continue;
        }
        if (preg_match('/^if\b/i', $line)) {
            $test = fq_script_parse_if($line);
            if ($test) {
                $current['ops'][] = ['kind' => 'if', 'nest' => $nest, 'left' => $test['left'], 'op' => $test['op'], 'right' => $test['right']];
            }
            continue;
        }
        if (str_contains($line, ':') && !str_contains($line, '"')) {
            $label = trim(substr($line, 0, strpos($line, ':')));
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $label)) {
                $current['labels'][$label] = count($current['ops']);
                $current['ops'][] = ['kind' => 'label', 'nest' => $nest, 'label' => $label];
                continue;
            }
        }
        $global = false;
        $rest = $line;
        if (preg_match('/^global\s+/i', $rest)) {
            $global = true;
            $rest = preg_replace('/^global\s+/i', '', $rest);
        }
        if (preg_match('/^(int|float|string)\s+/i', $rest, $declared)) {
            $rest = trim(substr($rest, strlen($declared[0])));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*/', $rest, $name)) {
                continue;
            }
            $type = strtolower($declared[1]) === 'string' ? 'string' : strtolower($declared[1]);
            $bucket = $global ? 'globals' : 'locals';
            if ($bucket === 'globals') {
                if (!isset($globals[$name[0]])) {
                    $globals[$name[0]] = ['type' => $type, 'value' => fq_script_default($type)];
                }
            } elseif (!isset($locals[$name[0]])) {
                $locals[$name[0]] = ['type' => $type, 'value' => fq_script_default($type)];
            }
            $after = trim(rtrim(trim(substr($rest, strlen($name[0]))), ';'));
            if (str_starts_with($after, '=')) {
                $expr = fq_script_compile_expr(trim(substr($after, 1)));
                if ($expr) {
                    $current['ops'][] = ['kind' => 'set', 'nest' => $nest, 'name' => $name[0], 'op' => '=', 'expr' => $expr, 'global' => $global];
                }
            }
            continue;
        }
        $operator = fq_script_find_operator($line);
        if ($operator) {
            $name = trim(substr($line, 0, $operator['index']));
            $expr = fq_script_compile_expr(trim(rtrim(substr($line, $operator['index'] + strlen($operator['op'])), ';')));
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) && $expr) {
                $current['ops'][] = ['kind' => 'set', 'nest' => $nest, 'name' => $name, 'op' => $operator['op'], 'expr' => $expr, 'global' => false];
            }
            continue;
        }
        $call = fq_script_parse_call(rtrim(trim($line), ';'));
        if (!$call) {
            continue;
        }
        $current['ops'][] = ['kind' => 'call', 'nest' => $nest, 'name' => $call['name'], 'args' => $call['args']];
    }
    if ($current !== null) {
        $functions[$current['key']] = $current;
    }
    return $functions;
}

function fq_script_numeric(?array $variable): float
{
    return (float) ($variable['value'] ?? 0);
}

function fq_script_apply_math(array $left, string $op, array $right): array
{
    if (($left['type'] ?? '') === 'string' || ($right['type'] ?? '') === 'string') {
        if ($op === '+=') {
            $left['value'] = (string) ($left['value'] ?? '') . (($right['type'] ?? '') === 'string' ? $right['value'] : fq_script_format($right));
        }
        return $left;
    }
    $a = fq_script_numeric($left);
    $b = fq_script_numeric($right);
    $next = $a;
    if ($op === '=') {
        $next = $b;
    } elseif ($op === '+=') {
        $next = $a + $b;
    } elseif ($op === '-=') {
        $next = $a - $b;
    } elseif ($op === '*=') {
        $next = $a * $b;
    } elseif ($op === '/=') {
        $next = $b == 0.0 ? 0 : $a / $b;
    } elseif ($op === '%=') {
        $next = $b == 0.0 ? 0 : fmod($a, $b);
    }
    if (($left['type'] ?? '') === 'int') {
        $left['value'] = (int) $next;
    } else {
        $left['value'] = $next;
    }
    return $left;
}

function fq_script_compare(?array $left, string $op, ?array $right): bool
{
    if (($left['type'] ?? '') === 'string' || ($right['type'] ?? '') === 'string') {
        $a = (string) ($left['value'] ?? '');
        $b = (string) ($right['value'] ?? '');
        return match ($op) {
            '==' => $a === $b,
            '!=' => $a !== $b,
            '<' => strlen($a) < strlen($b),
            '>' => strlen($a) > strlen($b),
            '<=' => strlen($a) <= strlen($b),
            '>=' => strlen($a) >= strlen($b),
            default => false,
        };
    }
    $a = fq_script_numeric($left);
    $b = fq_script_numeric($right);
    return match ($op) {
        '==' => $a == $b,
        '!=' => $a != $b,
        '<' => $a < $b,
        '>' => $a > $b,
        '<=' => $a <= $b,
        '>=' => $a >= $b,
        default => false,
    };
}

final class FqRuntime
{
    public array $locals = [];
    public array $globals;
    public array $functions = [];
    public ?array $wait = null;
    public ?string $packet = null;
    public ?self $replaced = null;
    public string $source = '';
    public string $filename = '';
    /** @var callable */
    public $call;
    /** @var callable */
    public $spawn;

    public function __construct(array &$globals, callable $call, callable $spawn)
    {
        $this->globals = &$globals;
        $this->call = $call;
        $this->spawn = $spawn;
    }

    public function lookup(string $name): ?array
    {
        return $this->locals[$name] ?? $this->globals[$name] ?? null;
    }

    public function waiting(): bool
    {
        return $this->wait !== null || ($this->replaced && $this->replaced->waiting());
    }

    public function spawnChild(string $path): ?self
    {
        $child = ($this->spawn)($path);
        if ($child instanceof self) {
            $this->replaced = $child;
        }
        return $child instanceof self ? $child : null;
    }

    public function packetOf(): ?string
    {
        if ($this->replaced) {
            return $this->replaced->packetOf();
        }
        return $this->packet;
    }

    private function readAtom(?array $atom, bool $expanding): array
    {
        if (!$atom) {
            return ['type' => 'int', 'value' => 0];
        }
        if ($atom['kind'] === 'num') {
            return ['type' => $atom['type'], 'value' => $atom['value']];
        }
        if ($atom['kind'] === 'str') {
            $value = $expanding ? fq_script_expand($atom['value'], fn ($name) => $this->lookup($name)) : $atom['value'];
            return ['type' => 'string', 'value' => $value];
        }
        if ($atom['kind'] === 'var') {
            return $this->lookup($atom['name']) ?? ['type' => 'int', 'value' => 0];
        }
        if ($atom['kind'] === 'call') {
            return $this->callValue($atom['name'], $atom['args']);
        }
        return ['type' => 'int', 'value' => 0];
    }

    private function callValue(string $name, array $args): array
    {
        $key = fq_script_up($name);
        if (isset($this->functions[$key])) {
            $status = $this->run($key, 0);
            return $status === 'wait' ? ['type' => 'wait'] : ['type' => 'int', 'value' => 0];
        }
        $expanded = [];
        foreach ($args as $atom) {
            $value = $this->readAtom($atom, false);
            if (($value['type'] ?? '') === 'string') {
                $expanded[] = ['type' => 'string', 'value' => fq_script_expand((string) $value['value'], fn ($found) => $this->lookup($found))];
            } else {
                $expanded[] = $value;
            }
        }
        $result = ($this->call)($key, $expanded, $this);
        if (!empty($result['wait'])) {
            $this->packet = $result['packet'] ?? null;
            return ['type' => 'wait'];
        }
        return $result ?: ['type' => 'int', 'value' => 0];
    }

    private function run(string $key, int $startIp): string
    {
        $nestLock = -1;
        $i = $startIp;
        $ops = $this->functions[$key]['ops'];
        while ($i < count($ops)) {
            $op = $ops[$i];
            if ($nestLock !== -1) {
                if ($nestLock < $op['nest']) {
                    $i++;
                    continue;
                }
                $nestLock = -1;
            }
            if ($op['kind'] === 'label') {
                $i++;
                continue;
            }
            if ($op['kind'] === 'goto') {
                $target = $this->functions[$key]['labels'][$op['label']] ?? null;
                $nestLock = -1;
                if ($target === null) {
                    $i++;
                    continue;
                }
                $i = $target;
                continue;
            }
            if ($op['kind'] === 'return') {
                break;
            }
            if ($op['kind'] === 'if') {
                if (!fq_script_compare($this->readAtom($op['left'], false), $op['op'], $this->readAtom($op['right'], false))) {
                    $nestLock = $op['nest'];
                }
                $i++;
                continue;
            }
            if ($op['kind'] === 'set') {
                $expr = ($op['expr']['kind'] ?? '') === 'call'
                    ? $this->callValue($op['expr']['name'], $op['expr']['args'])
                    : $this->readAtom($op['expr'], false);
                if (($expr['type'] ?? '') === 'wait') {
                    if ($this->wait === null) {
                        $this->wait = ['key' => $key, 'ip' => $i + 1];
                    }
                    return 'wait';
                }
                $dest = $this->locals[$op['name']] ?? $this->globals[$op['name']] ?? null;
                if ($dest) {
                    if ($op['op'] === '=') {
                        if ($dest['type'] === 'int') {
                            $dest['value'] = (int) fq_script_numeric($expr);
                        } elseif ($dest['type'] === 'float') {
                            $dest['value'] = fq_script_numeric($expr);
                        } else {
                            $dest['value'] = ($expr['type'] ?? '') === 'string' ? (string) ($expr['value'] ?? '') : fq_script_format($expr);
                        }
                    } else {
                        $right = ($expr['type'] ?? '') === $dest['type']
                            ? $expr
                            : ['type' => $dest['type'] === 'string' ? 'string' : 'float', 'value' => $expr['value'] ?? 0];
                        $dest = fq_script_apply_math($dest, $op['op'], $right);
                    }
                    if (isset($this->locals[$op['name']])) {
                        $this->locals[$op['name']] = $dest;
                    } else {
                        $this->globals[$op['name']] = $dest;
                    }
                }
                $i++;
                continue;
            }
            if ($op['kind'] === 'call') {
                $callKey = fq_script_up($op['name']);
                if (isset($this->functions[$callKey])) {
                    $this->functions[$callKey]['savedParent'] = ['key' => $key, 'ip' => $i + 1];
                    $status = $this->run($callKey, 0);
                    if ($status === 'wait') {
                        return 'wait';
                    }
                    $i++;
                    continue;
                }
                $result = $this->callValue($op['name'], $op['args']);
                if (($result['type'] ?? '') === 'wait') {
                    if ($this->wait === null) {
                        $this->wait = ['key' => $key, 'ip' => $i + 1];
                    }
                    return 'wait';
                }
                $i++;
                continue;
            }
            $i++;
        }
        $parent = $this->functions[$key]['savedParent'] ?? null;
        if ($startIp !== 0 && $parent) {
            $this->functions[$key]['savedParent'] = null;
            if (isset($this->functions[$parent['key']])) {
                return $this->run($parent['key'], $parent['ip']);
            }
        }
        return 'ok';
    }

    public function start(string $source, string $filename, array $preset): ?string
    {
        $this->source = $source;
        $this->filename = $filename;
        $this->locals = [];
        foreach ($preset as $item) {
            $this->locals[$item[0]] = $item[1];
        }
        $this->functions = fq_script_compile($source, $this->locals, $this->globals);
        $this->wait = null;
        $this->packet = null;
        $this->replaced = null;
        if (isset($this->functions['ONCREATE'])) {
            $this->run('ONCREATE', 0);
        }
        if (isset($this->functions['MAIN']) && $this->wait === null) {
            $this->run('MAIN', 0);
        }
        return $this->packetOf();
    }

    public function resume(int|float|string $result): ?string
    {
        if ($this->replaced) {
            return $this->replaced->resume($result);
        }
        if ($this->wait === null) {
            return $this->packetOf();
        }
        $next = $this->wait;
        $this->wait = null;
        $this->packet = null;
        if (isset($this->locals['i_my_result'])) {
            $this->locals['i_my_result']['value'] = (int) $result;
        }
        if (isset($this->functions[$next['key']])) {
            $this->run($next['key'], $next['ip']);
        }
        return $this->packetOf();
    }

    public function export(): array
    {
        $parents = [];
        foreach ($this->functions as $key => $fn) {
            if (!empty($fn['savedParent'])) {
                $parents[$key] = $fn['savedParent'];
            }
        }
        return [
            'source' => $this->source,
            'filename' => $this->filename,
            'locals' => $this->locals,
            'wait' => $this->wait,
            'parents' => $parents,
            'replaced' => $this->replaced?->export(),
        ];
    }

    public function restore(array $snap): void
    {
        $this->source = (string) ($snap['source'] ?? '');
        $this->filename = (string) ($snap['filename'] ?? '');
        $this->locals = $snap['locals'] ?? [];
        $this->functions = fq_script_compile($this->source, $this->locals, $this->globals);
        foreach ($this->locals as $name => $variable) {
            $this->locals[$name] = $variable;
        }
        $this->wait = $snap['wait'] ?? null;
        foreach (($snap['parents'] ?? []) as $key => $parent) {
            if (isset($this->functions[$key])) {
                $this->functions[$key]['savedParent'] = $parent;
            }
        }
        $this->replaced = null;
        if (!empty($snap['replaced'])) {
            $child = new self($this->globals, $this->call, $this->spawn);
            $child->restore($snap['replaced']);
            $this->replaced = $child;
        }
    }
}
