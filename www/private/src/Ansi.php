<?php
declare(strict_types=1);

function fq_cash(int|float $amount): string
{
    $digits = (string) (int) $amount;
    $length = strlen($digits);
    $step = $length % 3;
    $out = $step ? substr($digits, 0, $step) . ',' : '';
    for ($i = $step; $i < $length; $i += 3) {
        $out .= substr($digits, $i, 3) . ',';
    }
    return substr($out, 0, -1);
}

function fq_seth_ansi(string $input): string
{
    $colors = [
        '1' => '#0000A8', '2' => '#007000', '3' => '#008080', '4' => '#FF0000',
        '5' => '#CC66CC', '6' => '#A85400', '7' => '#808080', '8' => '#505050',
        '9' => '#5454FC', '0' => '#00B000', '!' => '#00A0A0', '@' => '#FC5454',
        '#' => '#FC54FC', '$' => '#FFFF33', 'y' => '#FFFF33', 'w' => '#FFFFFF',
        '^' => '#000000',
    ];
    $out = '';
    $length = strlen($input);
    for ($i = 0; $i < $length; $i++) {
        if ($input[$i] !== '`' || $i + 1 >= $length) {
            $out .= $input[$i];
            continue;
        }
        $code = $input[++$i];
        if ($code === '=') {
            $out .= '%3D';
            continue;
        }
        if ($code === 'f' && $i + 1 < $length) {
            $size = 8 + (int) $input[++$i] * 2;
            $out .= '<font size="' . $size . '">';
            continue;
        }
        $out .= isset($colors[$code]) ? '<font color="' . $colors[$code] . '">' : '`';
    }
    return $out;
}

function fq_format_for_web(string $value): string
{
    return strtr($value, [
        ' ' => '+',
        '"' => '%22',
        '!' => '%21',
        ',' => '%2C',
        ';' => '%3B',
        ':' => '%3A',
    ]);
}

function fq_decode_token(string $value): string
{
    $spaced = str_replace('+', ' ', $value);
    return rawurldecode($spaced);
}

function fq_vars_to_body(array $fields): string
{
    $parts = [];
    foreach ($fields as $field) {
        $value = str_replace(["\r\n", "\n"], '<br>', (string) $field[1]);
        $parts[] = $field[0] . '=' . $value;
    }
    return fq_format_for_web(fq_seth_ansi(implode('&', $parts) . '&')) . "/r/n";
}

function fq_parse_request(string $url): ?array
{
    $marker = 'request.xp?';
    $at = strpos($url, $marker);
    if ($at === false) {
        return null;
    }
    $rest = substr($url, $at + strlen($marker));
    $hash = strpos($rest, '#');
    if ($hash !== false) {
        $rest = substr($rest, 0, $hash);
    }
    $question = strpos($rest, '?');
    $amp = strpos($rest, '&');
    $split = strlen($rest);
    if ($question !== false) {
        $split = $question;
    }
    if ($amp !== false && $amp < $split) {
        $split = $amp;
    }
    $command = fq_decode_token(substr($rest, 0, $split));
    $query = ltrim(substr($rest, $split), '?&');
    $tokens = [];
    if ($query !== '') {
        foreach (explode('&', $query) as $part) {
            if ($part === '') {
                continue;
            }
            $eq = strpos($part, '=');
            $key = fq_decode_token($eq === false ? $part : substr($part, 0, $eq));
            $value = fq_decode_token($eq === false ? '' : substr($part, $eq + 1));
            $tokens[$key] = $value;
        }
    }
    return ['command' => $command, 'tokens' => $tokens];
}
