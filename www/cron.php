<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    return;
}

require __DIR__ . '/private/src/boot.php';

$rolled = fq_game()->maintain();
echo $rolled ? "Midnight maint ran.\n" : "Town is already on today's date.\n";
