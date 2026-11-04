<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\MethodCall\RenameMethodRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;

return RectorConfig::configure()
    ->withFileExtensions(['php'])
    ->withImportNames(importShortClasses: false)
    ->withParallel()
    ->withPaths(array_map(
        static fn (string $dir): string => __DIR__ . '/' . $dir,
        ['Block', 'Controller', 'Model', 'Test', 'ViewModel']
    ))
    ->withSets([
        LevelSetList::UP_TO_PHP_83,
        PHPUnitSetList::COMPOSER_BASED,
    ])
    ->withPreparedSets(typeDeclarations: true)
    ->withSkip([
        ReturnNeverTypeRector::class,
        // The PHPUnit 13 set renames expectExceptionMessage(), which Magento's PHPUnit 12 unit runner lacks.
        RenameMethodRector::class,
    ]);
