<?php

declare(strict_types=1);

function getBaseUrl(): string
{
    $baseUrl = getenv('PARCEL_MAP_BASE_URL') ?: 'http://127.0.0.1:8000';

    return rtrim($baseUrl, '/');
}

function requestJson(string $path): array
{
    $url = getBaseUrl() . $path;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'timeout' => 15,
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    $body = file_get_contents($url, false, $context);

    if ($body === false) {
        throw new RuntimeException('Request failed: ' . $url);
    }

    $status = 0;
    $headers = $http_response_header ?? [];

    if (isset($headers[0]) && preg_match('/HTTP\/\S+\s+(\d{3})/', $headers[0], $matches) === 1) {
        $status = (int) $matches[1];
    }

    $data = null;

    if (trim($body) !== '') {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    return [
        'status' => $status,
        'data' => $data,
        'body' => $body,
        'url' => $url,
    ];
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSameValue(mixed $actual, mixed $expected, string $message): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($message . ' (actual: ' . var_export($actual, true) . ', expected: ' . var_export($expected, true) . ')');
    }
}

function runTest(string $name, callable $test): void
{
    try {
        $test();
        echo '[PASS] ' . $name . PHP_EOL;
    } catch (Throwable $exception) {
        echo '[FAIL] ' . $name . ': ' . $exception->getMessage() . PHP_EOL;
        exit(1);
    }
}
