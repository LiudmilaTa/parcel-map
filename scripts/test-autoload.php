<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ParcelMap\Test;

// Create an instance of the Test class.
$test = new Test();

echo $test->message() . PHP_EOL;