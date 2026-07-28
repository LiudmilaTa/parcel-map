<?php

declare(strict_types=1);

$areas = [
    'jicin' => [
        'name' => 'Jičín',
        'code' => '659541',
    ],
];

$requiredAreas = [
    'jicin' => '659541',
    'miletin' => null,
    'sobotka' => null,
    'stara-paka' => null,
];

foreach ($requiredAreas as $slug => $expectedCode) {
    if (!isset($areas[$slug])) {
        fwrite(STDERR, "Missing area: {$slug}\n");
        exit(1);
    }

    $actualCode = $areas[$slug]['code'] ?? null;

    if ($expectedCode !== null && $actualCode !== $expectedCode) {
        fwrite(STDERR, "Unexpected code for {$slug}: {$actualCode}\n");
        exit(1);
    }
}

echo "Area configuration is ready.\n";
