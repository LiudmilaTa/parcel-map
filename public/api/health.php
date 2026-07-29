<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use ParcelMap\Database\Connection;

function resolveEnvValue(string $key, ?string $fallbackKey = null, string $default = ''): string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: '';

    if ($value !== '') {
        return $value;
    }

    if ($fallbackKey !== null && $fallbackKey !== '') {
        $fallbackValue = $_ENV[$fallbackKey] ?? $_SERVER[$fallbackKey] ?? getenv($fallbackKey) ?: '';

        if ($fallbackValue !== '') {
            return $fallbackValue;
        }
    }

    return $default;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $projectRoot = dirname(__DIR__, 2);

    if (file_exists($projectRoot . '/.env')) {
        Dotenv::createImmutable($projectRoot)->safeLoad();
    }

    $databaseName = resolveEnvValue('MAPA_PARCEL_DB', 'DB_NAME', 'mapa_parcel');
    $username = resolveEnvValue('MAPA_PARCEL_USER', 'DB_USER', 'mapa_parcel');
    $password = resolveEnvValue('MAPA_PARCEL_PASSWORD', 'DB_PASSWORD', '1111');
    $host = resolveEnvValue('MAPA_PARCEL_HOST', 'DB_HOST', '127.0.0.1');
    $port = (int) resolveEnvValue('MAPA_PARCEL_PORT', 'DB_PORT', '3306');

    $connection = new Connection(
        $host,
        $port,
        $databaseName,
        $username,
        $password
    );

    $pdo = $connection->getPdo();

    $pdo->query('SELECT 1');
    $parcelsCount = (int) $pdo->query('SELECT COUNT(*) FROM parcels')->fetchColumn();

    echo json_encode(
        [
            'status' => 'ok',
            'database' => [
                'status' => 'ok',
                'host' => $host,
                'port' => $port,
                'name' => $databaseName,
                'user' => $username,
            ],
            'parcels' => [
                'count' => $parcelsCount,
            ],
            'timestamp' => date(DATE_ATOM),
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );
} catch (Throwable $exception) {
    http_response_code(503);

    echo json_encode(
        [
            'status' => 'error',
            'error' => 'Service unavailable',
            'message' => $exception->getMessage(),
            'timestamp' => date(DATE_ATOM),
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );
}
