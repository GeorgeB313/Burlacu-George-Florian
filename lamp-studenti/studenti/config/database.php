<?php
declare(strict_types=1);

/**
 * Returns a shared PDO instance configured for the MovieHub MySQL database.
 */
function db(): \PDO
{
    static $pdo = null;

    if ($pdo instanceof \PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'mysql';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_DATABASE') ?: 'studenti';
    $username = getenv('DB_USER') ?: 'user';
    $password = getenv('DB_PASSWORD') ?: 'password';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

    $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new \PDO($dsn, $username, $password, $options);

    return $pdo;
}
