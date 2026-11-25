<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\TypeDeclaration\Rector\Class_\TypedPropertyFromCreateMockAssignRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\CodeQuality\General\ExtEmConfRector;
use Ssch\TYPO3Rector\Configuration\Typo3Option;
use Ssch\TYPO3Rector\Set\Typo3SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../../Classes',
        __DIR__ . '/../../Configuration',
        __DIR__ . '/../../Tests',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
    )
    ->withPhpSets(php82: true)
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withSets([
        Typo3SetList::CODE_QUALITY,
        Typo3SetList::GENERAL,
        Typo3SetList::TYPO3_14,
    ])
    ->withPHPStanConfigs([Typo3Option::PHPSTAN_FOR_RECTOR_PATH])
    ->withConfiguredRule(ExtEmConfRector::class, [
        ExtEmConfRector::TYPO3_VERSION_CONSTRAINT => '14.3.0-14.3.99',
        ExtEmConfRector::ADDITIONAL_VALUES_TO_BE_REMOVED => [],
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSkip([
        // EXT:checkfaluploads is optional, so its validator is referenced by a string
        StringClassNameToClassConstantRector::class => [
            '*/Classes/EventListener/CheckFalUploadsEventListener.php',
            '*/Tests/Unit/EventListener/CheckFalUploadsEventListenerTest.php',
        ],
        // Test doubles implement the signatures of TYPO3 core
        '*/Tests/Functional/Fixtures/*',
        SafeDeclareStrictTypesRector::class => [
            '*ext_emconf.php',
        ],
        // Avoid stripping class types from mock properties to keep IDE autocomplete working in functional tests
        TypedPropertyFromCreateMockAssignRector::class => [
            '*/Tests/Functional/*',
        ],
        '*Build/*',
        '*Resources/*',
        '*Model/*',
    ]);
