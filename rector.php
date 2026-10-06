<?php

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use Rector\TypeDeclaration\Rector\While_\WhileNullableToInstanceofRector;

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
        FlipTypeControlToUseExclusiveTypeRector::class,
        WhileNullableToInstanceofRector::class,
        // Strict types change scalar coercion; adopt them for the whole package at once, not file by file.
        SafeDeclareStrictTypesRector::class,
    ]);
