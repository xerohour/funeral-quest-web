<?php
declare(strict_types=1);

final class FqStore
{
    public object $state;
    private string $file;
    /** @var resource|null */
    private $lock = null;

    public function __construct(string $file)
    {
        $this->file = $file;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $lock = fopen($file . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the town file.');
        }
        $this->lock = $lock;
        $raw = is_file($file) ? file_get_contents($file) : '';
        if ($raw === false) {
            $this->release();
            throw new RuntimeException('Unable to read the town file.');
        }
        if (trim($raw) === '') {
            $this->state = $this->fresh();
        } else {
            $decoded = json_decode($raw);
            if (!is_object($decoded)) {
                $this->release();
                throw new RuntimeException('The town file is damaged; refusing to start an empty town over it.');
            }
            $this->state = $decoded;
        }
        if (!isset($this->state->players) || !is_array($this->state->players)) {
            $this->state->players = [];
        }
        if (!isset($this->state->sessions) || !is_object($this->state->sessions)) {
            $this->state->sessions = new stdClass();
        }
        $migrated = false;
        foreach ($this->state->players as $player) {
            if (isset($player->password) && !isset($player->passwordHash)) {
                $player->passwordHash = fq_hash_password((string) $player->password);
                unset($player->password);
                $migrated = true;
            }
        }
        if ($migrated) {
            $this->save();
        }
    }

    private function fresh(): object
    {
        return (object) [
            'day' => 1,
            'nextId' => 1,
            'players' => [],
            'sessions' => new stdClass(),
        ];
    }

    public function save(): void
    {
        if ($this->lock === null) {
            throw new RuntimeException('The town file was released; it cannot be saved.');
        }
        $json = json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Unable to encode the town.');
        }
        $temp = $this->file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temp, $json) !== strlen($json) || !rename($temp, $this->file)) {
            @unlink($temp);
            throw new RuntimeException('Unable to write the town file.');
        }
    }

    public function dir(): string
    {
        return dirname($this->file);
    }

    public function release(): void
    {
        if ($this->lock !== null) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
            $this->lock = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }

    public function session(string $id): object
    {
        if (!isset($this->state->sessions->{$id})) {
            $this->state->sessions->{$id} = (object) [
                'playerId' => null,
                'gaveLogonMessage' => false,
                'customer' => null,
            ];
            $this->save();
        }
        $session = $this->state->sessions->{$id};
        $session->id = $id;
        return $session;
    }

    public function player(string $sessionId): ?object
    {
        $current = $this->session($sessionId);
        if ($current->playerId === null) {
            return null;
        }
        return $this->playerById($current->playerId);
    }

    public function peek(string $sessionId): ?object
    {
        $current = $this->state->sessions->{$sessionId} ?? null;
        if (!$current || $current->playerId === null) {
            return null;
        }
        return $this->playerById($current->playerId);
    }

    public function playerById(int|string|null $id): ?object
    {
        foreach ($this->state->players as $player) {
            if ((string) $player->id === (string) $id) {
                return $player;
            }
        }
        return null;
    }

    public function findLogon(string $logon): ?object
    {
        foreach ($this->state->players as $player) {
            if (($player->active ?? true) === false) {
                continue;
            }
            if (strcasecmp((string) $player->logon, $logon) === 0) {
                return $player;
            }
        }
        return null;
    }

    public function findName(string $name): ?object
    {
        foreach ($this->state->players as $player) {
            if (($player->active ?? true) === false) {
                continue;
            }
            if (strcasecmp((string) $player->name, $name) === 0) {
                return $player;
            }
        }
        return null;
    }

    public function createPlayer(array $fields): object
    {
        $created = (object) [
            'id' => $this->state->nextId++,
            'name' => $fields['name'],
            'logon' => $fields['logon'],
            'funeralName' => $fields['funeralName'],
            'email' => $fields['email'],
            'male' => (bool) $fields['male'],
            'passwordHash' => fq_hash_password((string) $fields['password']),
            'money' => (int) ($fields['money'] ?? 500),
            'turns' => 15,
            'exp' => 0,
            'buryPoints' => 0,
            'luck' => (int) ($fields['luck'] ?? 50),
            'wood' => 5,
            'silver' => 2,
            'gold' => 0,
            'plots' => 0,
            'transport' => 0,
            'lockpicks' => 0,
            'smoke' => 0,
            'padlock' => 0,
            'padlockStrength' => 0,
            'safe' => 0,
            'psych' => 0,
            'hp' => (int) ($fields['hp'] ?? 10),
            'hpMax' => (int) ($fields['hp'] ?? 10),
            'strength' => (int) ($fields['strength'] ?? 3),
            'yangsSent' => 0,
            'kickedOut' => false,
            'visits' => 0,
            'police' => false,
            'deliveries' => [],
            'inbox' => [],
            'dayLastLoggedOn' => 0,
            'active' => true,
        ];
        $this->state->players[] = $created;
        $this->save();
        return $created;
    }

    public function logon(string $sessionId, int|string $playerId): void
    {
        $current = $this->session($sessionId);
        $current->playerId = $playerId;
        $current->gaveLogonMessage = false;
        $current->customer = null;
        $this->save();
    }

    public function checkPassword(?object $player, string $password): bool
    {
        if (!$player) {
            fq_verify_password(null, $password);
            return false;
        }
        return fq_verify_password($player->passwordHash ?? null, $password);
    }

    public function logoff(string $sessionId): void
    {
        $current = $this->session($sessionId);
        $current->playerId = null;
        $current->gaveLogonMessage = false;
        $current->customer = null;
        $current->pendingYells = [];
        $current->chatting = false;
        $current->prowling = false;
        $current->battle = false;
        $current->targetIndex = null;
        $this->save();
    }
}
