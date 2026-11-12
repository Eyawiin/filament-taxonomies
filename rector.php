<?php

use Rector\CodeQuality\Rector\If_\ExplicitNullableObjectInstanceRector;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withPhpSets()
    ->withSkip([
        // Explicit null checks on nullable models read clearer than fully qualified instanceof checks.
        ExplicitNullableObjectInstanceRector::class,
        // Strict types change scalar coercion; adopt them for the whole package at once, not file by file.
        SafeDeclareStrictTypesRector::class,
    ]);
