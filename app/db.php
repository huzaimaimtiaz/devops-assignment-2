<?php
/**
 * Database connection helper.
 *
 * Credentials come from environment variables set in the docker-compose
 * file, so the same image serves Part-I and Part-II without changes.
 */

function db_config(): array
{
    return [
        'host' => getenv('DB_HOST') ?: 'db',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'atcdb',
        'user' => getenv('DB_USER') ?: 'atcapp',
        'pass' => getenv('DB_PASS') ?: 'Atcapp_2026',
    ];
}

function app_part(): string
{
    return getenv('APP_PART') ?: 'Part-I';
}

const STRIP_STATUSES = [
    'SCHEDULED', 'INBOUND', 'HOLDING', 'CLEARED', 'DEPARTED', 'LANDED',
];

/**
 * Returns a shared PDO handle. The database container needs a few seconds
 * to initialise on a cold start, so the connection is retried instead of
 * failing the whole page.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        $cfg['port'],
        $cfg['name']
    );

    $lastMessage = 'unknown error';
    for ($attempt = 1; $attempt <= 10; $attempt++) {
        try {
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            $lastMessage = $e->getMessage();
            sleep(2);
        }
    }

    throw new RuntimeException('Database not reachable: ' . $lastMessage);
}

/** Status summary rendered in the page footer to evidence DB integration. */
function db_status(): array
{
    $cfg = db_config();
    try {
        $pdo = db();
        return [
            'connected'   => true,
            'server'      => $pdo->query('SELECT VERSION()')->fetchColumn(),
            'host'        => $cfg['host'] . ':' . $cfg['port'],
            'database'    => $cfg['name'],
            'user'        => $cfg['user'],
            'controllers' => (int) $pdo->query('SELECT COUNT(*) FROM controllers')->fetchColumn(),
            'strips'      => (int) $pdo->query('SELECT COUNT(*) FROM flight_strips')->fetchColumn(),
        ];
    } catch (Throwable $e) {
        return [
            'connected' => false,
            'host'      => $cfg['host'] . ':' . $cfg['port'],
            'database'  => $cfg['name'],
            'user'      => $cfg['user'],
            'error'     => $e->getMessage(),
        ];
    }
}

/**
 * Verifies a password against the stored hash. Seeded accounts use a
 * SHA-256 hash created by MySQL; accounts added later use password_hash().
 */
function password_matches(string $plain, string $stored): bool
{
    if ($stored !== '' && $stored[0] === '$') {
        return password_verify($plain, $stored);
    }
    return hash_equals(strtolower($stored), hash('sha256', $plain));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function utc_clock(): string
{
    return gmdate('d M Y H:i') . 'Z';
}
