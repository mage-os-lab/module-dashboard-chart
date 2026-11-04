<?php

declare(strict_types=1);

use MageOS\DashboardChart\Test\Tooling\XsdValidator;

require __DIR__ . '/../vendor/autoload.php';

$etc = dirname(__DIR__) . '/etc';
$validator = new XsdValidator();
$errors = [
    ...$validator->validateFile($etc . '/dashboard_charts.xml', $etc . '/dashboard_charts_file.xsd'),
    ...$validator->validateFile($etc . '/dashboard_charts.xml', $etc . '/dashboard_charts.xsd'),
];

foreach ($errors as $error) {
    fwrite(STDERR, 'dashboard_charts.xml ' . $error . PHP_EOL);
}

exit($errors === [] ? 0 : 1);
