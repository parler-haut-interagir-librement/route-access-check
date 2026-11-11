<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Contract;

use Phil\RouteAccessCheck\Report\Report;

/**
 * Writes a report in one format, under a name the console command's `--format` option takes.
 */
interface ReportFormatter
{
    /**
     * The format's name: lowercase letters, digits and `-`, such as `text` or `junit`.
     */
    public function name(): string;

    /**
     * @param bool $onlyProblems whether to leave out the routes that pass
     */
    public function format(Report $report, bool $onlyProblems = false): string;
}
