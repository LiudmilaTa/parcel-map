<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ParcelMap\Database\Connection;
use ParcelMap\Repositories\ParcelRepository;

header('Content-Type: application/json; charset=utf-8');

try {
    $connection = new Connection(
        '127.0.0.1',
        3306,
        'mapa_parcel',
        'mapa_parcel',
        '1111'
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

    // Find parcels intersecting the requested bbox.
    $parcels = $repository->findByBoundingBox(
        $minX,
        $minY,
        $maxX,
        $maxY
    );

    $features = [];

    foreach ($parcels as $parcel) {
        $geometry = json_decode(
            (string) $parcel['geometry'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

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