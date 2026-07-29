<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use ParcelMap\Database\Connection;
use ParcelMap\Repositories\ParcelRepository;

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

    $repository = new ParcelRepository(
        $pdo
    );

    // Get and validate the requested map bounding box.
    $bbox = $_GET['bbox'] ?? null;

    if ($bbox === null) {
        http_response_code(400);

        echo json_encode(
            [
                'error' => 'Missing bbox parameter.',
                'example' => 'bbox=minX,minY,maxX,maxY',
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        exit;
    }

    $coordinates = array_map(
        'trim',
        explode(',', $bbox)
    );

    if (count($coordinates) !== 4) {
        http_response_code(400);

        echo json_encode(
            [
                'error' => 'Invalid bbox format.',
                'example' => 'bbox=minX,minY,maxX,maxY',
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        exit;
    }

    [
        $minX,
        $minY,
        $maxX,
        $maxY,
    ] = array_map(
        'floatval',
        $coordinates
    );

    if (
        $minX >= $maxX
        || $minY >= $maxY
    ) {
        http_response_code(400);

        echo json_encode(
            [
                'error' => 'Invalid bbox coordinates.',
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        exit;
    }

    $zoningNames = [];

    $zoningNamesRaw = filter_input(
        INPUT_GET,
        'zoning_names',
        FILTER_UNSAFE_RAW
    );

    if (is_string($zoningNamesRaw) && trim($zoningNamesRaw) !== '') {
        $zoningNames = array_values(
            array_filter(
                array_map('trim', explode('||', $zoningNamesRaw)),
                static fn(string $name): bool => $name !== ''
            )
        );
    }

    if (count($zoningNames) === 0) {
        $zoningName = filter_input(
            INPUT_GET,
            'zoning_name',
            FILTER_UNSAFE_RAW
        );

        $zoningName = is_string($zoningName)
            ? trim($zoningName)
            : null;

        if ($zoningName !== null && $zoningName !== '') {
            $zoningNames = [$zoningName];
        }
    }

    // Find parcels intersecting the requested bbox.
    $parcels = $repository->findByBoundingBox(
        $minX,
        $minY,
        $maxX,
        $maxY,
        $zoningNames
    );

    $features = [];

    foreach ($parcels as $parcel) {
        $geometry = null;

        try {
            $geometry = json_decode(
                (string) $parcel['geometry'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            continue;
        }

        if (!is_array($geometry)) {
            continue;
        }

        $features[] = [
            'type' => 'Feature',

            'id' => (int) $parcel['id'],

            'geometry' => $geometry,

            'properties' => [
                'id' => (int) $parcel['id'],

                'cuzk_id' => $parcel['cuzk_id'],

                'local_id' => $parcel['local_id'],

                'label' => $parcel['label'],

                'national_cadastral_reference' =>
                    $parcel['national_cadastral_reference'],

                'area_value' => $parcel['area_value'] !== null
                    ? (float) $parcel['area_value']
                    : null,

                'zoning_name' =>
                    $parcel['zoning_name'],

                'administrative_unit_name' =>
                    $parcel['administrative_unit_name'],
            ],
        ];
    }

    echo json_encode(
        [
            'type' => 'FeatureCollection',
            'features' => $features,
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );

} catch (Throwable $exception) {
    http_response_code(500);

    echo json_encode(
        [
            'error' => 'Internal server error.',
            'message' => $exception->getMessage(),
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );
}