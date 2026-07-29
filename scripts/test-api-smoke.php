<?php

declare(strict_types=1);

$tests = [
    __DIR__ . '/tests/test-api-health.php',
    __DIR__ . '/tests/test-api-zoning.php',
    __DIR__ . '/tests/test-api-parcels.php',
];

echo "API Smoke Tests\n";
echo "===============\n";
echo 'Base URL: ' . (getenv('PARCEL_MAP_BASE_URL') ?: 'http://127.0.0.1:8000') . PHP_EOL . PHP_EOL;

foreach ($tests as $test) {
    echo 'Running: ' . basename($test) . PHP_EOL;

    passthru('php ' . escapeshellarg($test), $exitCode);

    if ($exitCode !== 0) {
        echo PHP_EOL . 'Smoke test suite failed.' . PHP_EOL;
        exit($exitCode);
    }

    echo PHP_EOL;
}

echo 'All smoke tests passed.' . PHP_EOL;
