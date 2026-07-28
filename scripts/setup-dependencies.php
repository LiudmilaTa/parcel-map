<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$composerExecutable = 'composer';

if (PHP_OS_FAMILY === 'Windows') {
    $composerExecutable = 'composer.bat';
}

$commands = [];

if (!is_dir($projectRoot . '/vendor')) {
    $commands[] = [$composerExecutable, 'install'];
} else {
    $commands[] = [$composerExecutable, 'update'];
}

foreach ($commands as [$command, $argument]) {
    $fullCommand = escapeshellcmd($command) . ' ' . escapeshellarg($argument);
    echo 'Running: ' . $fullCommand . PHP_EOL;
    $result = 0;
    passthru($fullCommand, $result);

    if ($result !== 0) {
        fwrite(STDERR, "Dependency setup failed for {$command} {$argument}" . PHP_EOL);
        exit($result);
    }
}

echo 'Dependencies are ready.' . PHP_EOL;
