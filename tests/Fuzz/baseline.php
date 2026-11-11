<?php

declare(strict_types=1);

/*
 * The reader of baseline files, on text nobody wrote: whatever it reads, it returns a baseline whose own document it
 * reads back to the same entries, or refuses it with InvalidBaseline, and throws nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\RouteAccessCheck\Baseline;
use Phil\RouteAccessCheck\Exception\InvalidBaseline;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $baseline = Baseline::fromJson($input);
    } catch (InvalidBaseline) {
        return;
    }

    if (Baseline::fromJson($baseline->toJson())->entries !== $baseline->entries) {
        throw new LogicException('The document of a baseline does not read back to the same entries.');
    }
});
$config->setMaxLen(256);
