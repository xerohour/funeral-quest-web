<?php
declare(strict_types=1);

function fq_u32(int $n): int
{
    return $n & 0xFFFFFFFF;
}

function fq_add32(int $a, int $b): int
{
    return fq_u32($a + $b);
}

function fq_rotl32(int $x, int $n): int
{
    $x = fq_u32($x);
    return fq_u32(($x << $n) | ($x >> (32 - $n)));
}

function fq_salsa20_8(array $b): array
{
    $x = $b;
    for ($i = 0; $i < 8; $i += 2) {
        $x[4] ^= fq_rotl32(fq_add32($x[0], $x[12]), 7);
        $x[8] ^= fq_rotl32(fq_add32($x[4], $x[0]), 9);
        $x[12] ^= fq_rotl32(fq_add32($x[8], $x[4]), 13);
        $x[0] ^= fq_rotl32(fq_add32($x[12], $x[8]), 18);
        $x[9] ^= fq_rotl32(fq_add32($x[5], $x[1]), 7);
        $x[13] ^= fq_rotl32(fq_add32($x[9], $x[5]), 9);
        $x[1] ^= fq_rotl32(fq_add32($x[13], $x[9]), 13);
        $x[5] ^= fq_rotl32(fq_add32($x[1], $x[13]), 18);
        $x[14] ^= fq_rotl32(fq_add32($x[10], $x[6]), 7);
        $x[2] ^= fq_rotl32(fq_add32($x[14], $x[10]), 9);
        $x[6] ^= fq_rotl32(fq_add32($x[2], $x[14]), 13);
        $x[10] ^= fq_rotl32(fq_add32($x[6], $x[2]), 18);
        $x[3] ^= fq_rotl32(fq_add32($x[15], $x[11]), 7);
        $x[7] ^= fq_rotl32(fq_add32($x[3], $x[15]), 9);
        $x[11] ^= fq_rotl32(fq_add32($x[7], $x[3]), 13);
        $x[15] ^= fq_rotl32(fq_add32($x[11], $x[7]), 18);
        $x[1] ^= fq_rotl32(fq_add32($x[0], $x[3]), 7);
        $x[2] ^= fq_rotl32(fq_add32($x[1], $x[0]), 9);
        $x[3] ^= fq_rotl32(fq_add32($x[2], $x[1]), 13);
        $x[0] ^= fq_rotl32(fq_add32($x[3], $x[2]), 18);
        $x[6] ^= fq_rotl32(fq_add32($x[5], $x[4]), 7);
        $x[7] ^= fq_rotl32(fq_add32($x[6], $x[5]), 9);
        $x[4] ^= fq_rotl32(fq_add32($x[7], $x[6]), 13);
        $x[5] ^= fq_rotl32(fq_add32($x[4], $x[7]), 18);
        $x[11] ^= fq_rotl32(fq_add32($x[10], $x[9]), 7);
        $x[8] ^= fq_rotl32(fq_add32($x[11], $x[10]), 9);
        $x[9] ^= fq_rotl32(fq_add32($x[8], $x[11]), 13);
        $x[10] ^= fq_rotl32(fq_add32($x[9], $x[8]), 18);
        $x[12] ^= fq_rotl32(fq_add32($x[15], $x[14]), 7);
        $x[13] ^= fq_rotl32(fq_add32($x[12], $x[15]), 9);
        $x[14] ^= fq_rotl32(fq_add32($x[13], $x[12]), 13);
        $x[15] ^= fq_rotl32(fq_add32($x[14], $x[13]), 18);
    }
    for ($i = 0; $i < 16; $i++) {
        $b[$i] = fq_add32($b[$i], $x[$i]);
    }
    return $b;
}

function fq_blockmix(string $block, int $r): string
{
    $blocks = str_split($block, 64);
    $x = $blocks[count($blocks) - 1];
    $y = array_fill(0, 2 * $r, '');
    for ($i = 0; $i < 2 * $r; $i++) {
        $t = $x ^ $blocks[$i];
        $words = array_values(unpack('V16', $t));
        $mixed = fq_salsa20_8($words);
        $x = pack('V16', ...$mixed);
        $y[$i] = $x;
    }
    $out = '';
    for ($i = 0; $i < $r; $i++) {
        $out .= $y[$i * 2];
    }
    for ($i = 0; $i < $r; $i++) {
        $out .= $y[$i * 2 + 1];
    }
    return $out;
}

function fq_integerify(string $block): int
{
    $tail = substr($block, -64);
    $words = unpack('V2', $tail);
    return $words[1];
}

function fq_scrypt_romix(string $block, int $n, int $r): string
{
    $v = [];
    $x = $block;
    for ($i = 0; $i < $n; $i++) {
        $v[$i] = $x;
        $x = fq_blockmix($x, $r);
    }
    for ($i = 0; $i < $n; $i++) {
        $j = fq_integerify($x) & ($n - 1);
        $x = fq_blockmix($x ^ $v[$j], $r);
    }
    return $x;
}

function fq_scrypt(string $password, string $salt, int $n, int $r, int $p, int $length): string
{
    $block = hash_pbkdf2('sha256', $password, $salt, 1, $p * 128 * $r, true);
    $chunk = 128 * $r;
    $mixed = '';
    for ($i = 0; $i < $p; $i++) {
        $mixed .= fq_scrypt_romix(substr($block, $i * $chunk, $chunk), $n, $r);
    }
    return hash_pbkdf2('sha256', $password, $mixed, 1, $length, true);
}

function fq_hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function fq_verify_password(?string $stored, string $password): bool
{
    if ($stored === null || $stored === '') {
        password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
        return false;
    }
    if (str_starts_with($stored, 'scrypt$')) {
        $parts = explode('$', $stored);
        if (count($parts) !== 6) {
            return false;
        }
        $n = (int) $parts[1];
        $r = (int) $parts[2];
        $p = (int) $parts[3];
        $salt = hex2bin($parts[4]);
        $expected = hex2bin($parts[5]);
        if ($salt === false || $expected === false || $n < 2 || ($n & ($n - 1)) !== 0) {
            return false;
        }
        $actual = fq_scrypt($password, $salt, $n, $r, $p, strlen($expected));
        return hash_equals($expected, $actual);
    }
    return password_verify($password, $stored);
}
