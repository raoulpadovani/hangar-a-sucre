<?php

// getenv() ne voit pas les fastcgi_param Nginx (uniquement $_SERVER) ; on vérifie les trois sources
function event_repository_getenv(string $name): ?string
{
    $value = getenv($name);
    if (is_string($value) && $value !== '') {
        return $value;
    }

    if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return $_SERVER[$name];
    }

    if (isset($_ENV[$name]) && is_string($_ENV[$name]) && $_ENV[$name] !== '') {
        return $_ENV[$name];
    }

    return null;
}

function event_repository_config(): array
{
    return [
        'dsn' => event_repository_getenv('DB_DSN') ?? '',
        'host' => event_repository_getenv('DB_HOST') ?? 'localhost',
        'port' => event_repository_getenv('DB_PORT') ?? '3306',
        'name' => event_repository_getenv('DB_NAME') ?? 'events',
        'user' => event_repository_getenv('DB_USER') ?? 'root',
        'pass' => event_repository_getenv('DB_PASSWORD') ?? 'root',
    ];
}

function event_repository_build_dsn(array $config, bool $withDatabase): string
{
    if ($config['dsn'] !== '') {
        return $config['dsn'];
    }

    $dsn = sprintf('mysql:host=%s;port=%s', $config['host'], $config['port']);
    if ($withDatabase) {
        $dsn .= ';dbname=' . $config['name'];
    }

    return $dsn . ';charset=utf8mb4';
}

function event_repository_connection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = event_repository_config();
    $dsn = event_repository_build_dsn($config, false);

    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $config['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . $config['name'] . '`');
        event_repository_initialize($pdo);
    } catch (Throwable $exception) {
        throw new RuntimeException('Connexion à la base MySQL impossible. Vérifie DB_NAME/DB_USER/DB_PASSWORD.', 0, $exception);
    }

    return $pdo;
}

function event_repository_initialize(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS events (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            event_date DATE NULL DEFAULT NULL,
            event_time VARCHAR(20) NOT NULL DEFAULT "",
            location VARCHAR(255) NOT NULL DEFAULT "",
            description TEXT NOT NULL,
            ticket_link VARCHAR(2048) NOT NULL DEFAULT "",
            price VARCHAR(100) NOT NULL DEFAULT "",
            image VARCHAR(1024) NOT NULL DEFAULT "",
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_events_event_date (event_date),
            INDEX idx_events_updated_at (updated_at)
        )'
    );
}

function event_repository_map_row(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'date' => (string) ($row['event_date'] ?? ''),
        'time' => (string) $row['event_time'],
        'location' => (string) $row['location'],
        'description' => (string) $row['description'],
        'ticketLink' => (string) $row['ticket_link'],
        'price' => (string) $row['price'],
        'image' => (string) $row['image'],
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
    ];
}

function event_repository_list_events(PDO $pdo, bool $upcomingOnly = false): array
{
    $sql = 'SELECT id, title, event_date, event_time, location, description, ticket_link, price, image, created_at, updated_at FROM events';
    $parameters = [];

    if ($upcomingOnly) {
        $sql .= ' WHERE event_date IS NULL OR event_date >= :today';
        $parameters[':today'] = date('Y-m-d');
    }

    $sql .= ' ORDER BY event_date IS NULL ASC, event_date ASC, id ASC';

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return array_map('event_repository_map_row', $statement->fetchAll());
}

function event_repository_get_event(PDO $pdo, int $eventId): ?array
{
    $statement = $pdo->prepare('SELECT id, title, event_date, event_time, location, description, ticket_link, price, image, created_at, updated_at FROM events WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $eventId]);
    $row = $statement->fetch();

    return $row ? event_repository_map_row($row) : null;
}

function event_repository_save_event(PDO $pdo, array $eventData, ?int $eventId = null): int
{
    $pdo->beginTransaction();

    try {
        $image = (string) ($eventData['image'] ?? '');

        if ($eventId !== null) {
            $existingEvent = event_repository_get_event($pdo, $eventId);
            if ($existingEvent === null) {
                throw new RuntimeException('Événement introuvable.');
            }

            if ($image === '') {
                $image = (string) ($existingEvent['image'] ?? '');
            }

            $statement = $pdo->prepare(
                'UPDATE events
                 SET title = :title, event_date = :event_date, event_time = :event_time, location = :location,
                     description = :description, ticket_link = :ticket_link, price = :price, image = :image,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );

            $statement->execute([
                ':title' => (string) ($eventData['title'] ?? ''),
                ':event_date' => ((string) ($eventData['date'] ?? '') !== '' ? (string) $eventData['date'] : null),
                ':event_time' => (string) ($eventData['time'] ?? ''),
                ':location' => (string) ($eventData['location'] ?? ''),
                ':description' => (string) ($eventData['description'] ?? ''),
                ':ticket_link' => (string) ($eventData['ticketLink'] ?? ''),
                ':price' => (string) ($eventData['price'] ?? ''),
                ':image' => $image,
                ':id' => $eventId,
            ]);

            $pdo->commit();

            return $eventId;
        }

        $statement = $pdo->prepare(
            'INSERT INTO events (title, event_date, event_time, location, description, ticket_link, price, image, created_at, updated_at)
             VALUES (:title, :event_date, :event_time, :location, :description, :ticket_link, :price, :image, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );

        $statement->execute([
            ':title' => (string) ($eventData['title'] ?? ''),
            ':event_date' => ((string) ($eventData['date'] ?? '') !== '' ? (string) $eventData['date'] : null),
            ':event_time' => (string) ($eventData['time'] ?? ''),
            ':location' => (string) ($eventData['location'] ?? ''),
            ':description' => (string) ($eventData['description'] ?? ''),
            ':ticket_link' => (string) ($eventData['ticketLink'] ?? ''),
            ':price' => (string) ($eventData['price'] ?? ''),
            ':image' => $image,
        ]);

        $eventId = (int) $pdo->lastInsertId();
        $pdo->commit();

        return $eventId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function event_repository_delete_event(PDO $pdo, int $eventId): void
{
    $statement = $pdo->prepare('DELETE FROM events WHERE id = :id');
    $statement->execute([':id' => $eventId]);
}
