<?php

declare(strict_types=1);

require_once __DIR__ . '/api-test-lib.php';

runTest('zoning endpoint returns valid FeatureCollection', static function (): void {
    $response = requestJson('/api/zoning.php');
    $data = $response['data'];

    assertSameValue($response['status'], 200, 'zoning endpoint should return HTTP 200');
    assertTrue(is_array($data), 'zoning response should be JSON object');
    assertSameValue($data['type'] ?? null, 'FeatureCollection', 'zoning type should be FeatureCollection');
    assertTrue(isset($data['features']) && is_array($data['features']), 'zoning features should be an array');
    assertTrue(count($data['features']) > 0, 'zoning features should not be empty');
});
