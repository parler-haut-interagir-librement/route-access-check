<?php

declare(strict_types=1);

use Phil\RouteAccessCheck\Format\GitHubFormatter;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // The fixture gives #[IsGranted] the arguments an application may write but Symfony's class does not
            // type — an object, a class that does not exist —: the check reads them without instantiating it.
            [
                'identifier' => 'argument.type',
                'paths' => ['tests/Unit/Fixture/AccountController.php'],
            ],
            [
                'identifier' => 'class.notFound',
                'paths' => ['tests/Unit/Fixture/AccountController.php'],
            ],
            // An attribute of the application's own, read by name: its class is never declared.
            [
                'identifier' => 'attribute.notFound',
                'paths' => ['tests/Unit/Fixture/MixedController.php'],
            ],
        ],
    ],
    'rector' => [
        // Symfony's #[IsGranted] is named by a string: the library reads it by name and does not require
        // symfony/security-http, which a class constant would make a dependency of.
        'skip' => [
            StringClassNameToClassConstantRector::class => ['src/Detector/SecurityAttributeDetector.php', 'composer-dependency-analyser.php'],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The GitHub annotations list the failures alone whatever $onlyProblems says: the default value of the
            // parameter the interface declares changes nothing.
            'FalseValue' => [
                'ignore' => [GitHubFormatter::class.'::format'],
            ],
        ],
    ],
    'fuzz' => [
        // The reader of baseline files, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'baseline' => 'tests/Fuzz/baseline.php',
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
