<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelMap\Test;

$test = new Test();

echo $test->message() . PHP_EOL;