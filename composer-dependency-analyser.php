<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

/**
 * The library's declared dependencies, checked against the library installed on its own: PHP, ext-mbstring,
 * symfony/routing and symfony/http-foundation. The test fixtures carry Symfony's #[IsGranted] by name: the library reads it through
 * reflection without loading its class, so symfony/security-http is never installed for them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreUnknownClasses(['Symfony\Component\Security\Http\Attribute\IsGranted']);
