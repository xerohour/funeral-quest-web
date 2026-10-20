<?php
declare(strict_types=1);

final class FqSystem
{
    public const LOG_LINES = 80;

    /** @var array<string, FqSystem> */
    private static array $open = [];
    private static bool $hooked = false;

    public bool $logToDisk = false;
    private int $hits = 0;
    private int $logons = 0;
    /** @var list<string> */
    private array $lines = [];
    /** @var list<string> */
    private array $disk = [];
    /** @var list<string> */
    private array $errors = [];
    private bool $rollStats = false;

    private function __construct(private string $dir)
    {
    }

    public static function at(string $dir): self
    {
        $key = rtrim(str_replace('\\', '/', $dir), '/');
        if (!isset(self::$open[$key])) {
            self::$open[$key] = new self($key);
        }
        if (!self::$hooked) {
            self::$hooked = true;
            register_shutdown_function(static function (): void {
                foreach (self::$open as $system) {
                    $system->flush();
                }
            });
        }
        return self::$open[$key];
    }

    public function hit(): void
    {
        $this->hits++;
    }

    public function logon(): void
    {
        $this->logons++;
    }

    public function log(string $text): void
    {
        $line = date('m-d') . ' ' . date('g:i A') . ':' . $text;
        $this->lines[] = $line;
        if ($this->logToDisk) {
            $this->disk[] = $line . "\r\n";
        }
    }

    public function error(string $text): void
    {
        $line = 'WARNING: ' . date('m-d') . ' ' . date('g:i A') . ':' . $text;
        $this->lines[] = $line;
        $this->errors[] = $line . "\r\n";
    }

    public function rollStats(): void
    {
        $this->rollStats = true;
        $this->flush();
    }

    public function read(): array
    {
        $this->flush();
        return $this->load();
    }

    public function flush(): void
    {
        if (!$this->hits && !$this->logons && !$this->lines && !$this->disk && !$this->errors && !$this->rollStats) {
            return;
        }
        if (!is_dir($this->dir) && !mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            return;
        }
        $lock = fopen($this->dir . '/system.json.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            return;
        }
        try {
            $data = $this->load();
            $data['hitsToday'] += $this->hits;
            $data['hitsTotal'] += $this->hits;
            $data['logonsToday'] += $this->logons;
            $data['logonsTotal'] += $this->logons;
            $data['log'] = array_slice([...$data['log'], ...$this->lines], -self::LOG_LINES);
            if ($this->rollStats) {
                file_put_contents($this->dir . '/stats.txt', date('m-d') . ' ' . date('H:i') . ':Logons=' . $data['logonsToday'] . ', hits=' . $data['hitsToday'] . "\r\n", FILE_APPEND);
                $data['logonsToday'] = 0;
                $data['hitsToday'] = 0;
            }
            if ($this->disk) {
                file_put_contents($this->dir . '/log.txt', implode('', $this->disk), FILE_APPEND);
            }
            if ($this->errors) {
                file_put_contents($this->dir . '/errorlog.txt', implode('', $this->errors), FILE_APPEND);
            }
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $temp = $this->dir . '/system.json.' . bin2hex(random_bytes(6)) . '.tmp';
            if ($json !== false && file_put_contents($temp, $json) === strlen($json)) {
                rename($temp, $this->dir . '/system.json');
            } else {
                @unlink($temp);
            }
            $this->hits = 0;
            $this->logons = 0;
            $this->lines = [];
            $this->disk = [];
            $this->errors = [];
            $this->rollStats = false;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function forget(): void
    {
        $this->flush();
        foreach (['log.txt', 'errorlog.txt'] as $name) {
            @unlink($this->dir . '/' . $name);
        }
    }

    public function file(string $name): string
    {
        $this->flush();
        $path = $this->dir . '/' . $name;
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function load(): array
    {
        $file = $this->dir . '/system.json';
        $raw = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $raw = is_array($raw) ? $raw : [];
        $data = [];
        foreach (['logonsToday', 'logonsTotal', 'hitsToday', 'hitsTotal'] as $key) {
            $data[$key] = max(0, (int) ($raw[$key] ?? 0));
        }
        $data['log'] = array_values(array_filter($raw['log'] ?? [], 'is_string'));
        return $data;
    }
}
