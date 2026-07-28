<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ParcelMap\Services\CoordinateTransformService;
use ParcelMap\Services\CuzkParcelParser;
use proj4php\Proj4php;

header('Content-Type: application/json; charset=utf-8');

try {
    $directory = __DIR__ . '/../../storage/cuzk/final';
    $files = glob($directory . '/*-zoning.xml');

    if ($files === false || $files === []) {
        echo json_encode([
            'type' => 'FeatureCollection',
            'features' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        exit;
    }

    $parser = new CuzkParcelParser(new CoordinateTransformService(new Proj4php()));
    $allFeatures = [];

    foreach ($files as $file) {
        $xml = file_get_contents($file);

        if ($xml === false) {
            continue;
        }

        $result = $parser->parseZoning($xml);

        if (!empty($result['features'])) {
            $allFeatures = array_merge($allFeatures, $result['features']);
        }
    }

    echo json_encode([
        'type' => 'FeatureCollection',
        'features' => $allFeatures,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(500);

    echo json_encode([
        'error' => 'Internal server error.',
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
