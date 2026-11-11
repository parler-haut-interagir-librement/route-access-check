<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Format;

use Override;
use Phil\RouteAccessCheck\Contract\ReportFormatter;
use Phil\RouteAccessCheck\Report\Report;
use Phil\RouteAccessCheck\Report\RouteStatus;

/**
 * The report as a JUnit XML document, which continuous integration servers display as tests: a test case per route,
 * a failure per failing route, ignored and baselined routes skipped, and the warnings in the suite's output.
 */
final readonly class JunitFormatter implements ReportFormatter
{
    #[Override]
    public function name(): string
    {
        return 'junit';
    }

    #[Override]
    public function format(Report $report, bool $onlyProblems = false): string
    {
        $verdicts = Describe::shown($report, $onlyProblems);
        $cases = [];
        $skipped = 0;

        foreach ($verdicts as $verdict) {
            $case = \sprintf('    <testcase name="%s" classname="route-access">', $this->escape($verdict->route->name.' '.Describe::route($verdict)));

            if ($verdict->fails()) {
                $case .= \sprintf('<failure type="%s" message="%s"/>', $verdict->status->value, $this->escape($verdict->problem()));
            } elseif ($verdict->baselined || RouteStatus::Ignored === $verdict->status) {
                ++$skipped;
                $case .= \sprintf('<skipped message="%s"/>', $verdict->baselined ? 'baselined: '.$this->escape($verdict->problem()) : 'ignored');
            }

            $cases[] = $case.'</testcase>';
        }

        $warnings = Describe::warnings($report);
        $output = [] === $warnings ? '' : "\n    <system-out>".$this->escape(implode("\n", $warnings)).'</system-out>';

        return \sprintf(
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<testsuites>\n  <testsuite name=\"route-access\" tests=\"%d\" failures=\"%d\" skipped=\"%d\">\n%s%s\n  </testsuite>\n</testsuites>\n",
            \count($verdicts),
            \count($report->failures()),
            $skipped,
            implode("\n", $cases),
            $output,
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_XML1 | \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
