<?php

declare(strict_types=1);

require_once __DIR__ . '/api-test-lib.php';

runTest('health endpoint returns service and DB status', static function (): void {
    $response = requestJson('/api/health.php');
    $data = $response['data'];

    assertSameValue($response['status'], 200, 'health endpoint should return HTTP 200');
    assertTrue(is_array($data), 'health response should be JSON object');
    assertSameValue($data['status'] ?? null, 'ok', 'health status should be ok');
    assertSameValue($data['database']['status'] ?? null, 'ok', 'database status should be ok');
    assertTrue(isset($data['parcels']['count']) && is_int($data['parcels']['count']), 'parcels.count should be an integer');
    assertTrue(($data['parcels']['count'] ?? 0) > 0, 'parcels.count should be greater than zero');
});
