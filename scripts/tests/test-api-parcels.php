<?php

declare(strict_types=1);

require_once __DIR__ . '/api-test-lib.php';

if (!function_exists('runTest')) {
    function runTest(string $name, callable $test): void
    {
        $test();
    }
}

if (!function_exists('requestJson')) {
    function requestJson(string $path): array
    {
        return [];
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition, string $message): void
    {
    }
}

if (!function_exists('assertSameValue')) {
    function assertSameValue(mixed $actual, mixed $expected, string $message): void
    {
    }
}

runTest('parcels endpoint returns filtered FeatureCollection', static function (): void {
    $query = '/api/parcels.php?bbox=15.259193649291994,50.40819856686914,15.443988571166992,50.4663018341015&zoning_names=Ji%C4%8D%C3%ADn';
    $response = requestJson($query);
    $data = $response['data'];

    assertSameValue($response['status'], 200, 'parcels endpoint should return HTTP 200');
    assertTrue(is_array($data), 'parcels response should be JSON object');
    assertSameValue($data['type'] ?? null, 'FeatureCollection', 'parcels type should be FeatureCollection');
    assertTrue(isset($data['features']) && is_array($data['features']), 'parcels features should be an array');
    assertTrue(count($data['features']) > 0, 'parcels features should not be empty for this bbox');

    $feature = $data['features'][0] ?? null;
    assertTrue(is_array($feature), 'parcels should contain at least one feature object');
    assertSameValue($feature['type'] ?? null, 'Feature', 'feature type should be Feature');
    assertTrue(isset($feature['geometry']['type']), 'feature geometry.type should exist');
    assertTrue(isset($feature['properties']['zoning_name']), 'feature properties.zoning_name should exist');
});

runTest('parcels endpoint validates malformed bbox', static function (): void {
    $response = requestJson('/api/parcels.php?bbox=1,2,3');
    $data = $response['data'];

    assertSameValue($response['status'], 400, 'malformed bbox should return HTTP 400');
    assertTrue(is_array($data), 'validation error response should be JSON object');
    assertSameValue($data['error'] ?? null, 'Invalid bbox format.', 'bbox validation message should be explicit');
});
