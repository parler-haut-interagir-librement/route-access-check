<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Format;

use Override;
use Phil\RouteAccessCheck\Contract\ReportFormatter;
use Phil\RouteAccessCheck\Report\Report;

/**
 * The report as GitHub Actions workflow commands: an error annotation per failing route, on its controller's file and
 * line when they are known, a warning per warning, then the summary.
 */
final readonly class GitHubFormatter implements ReportFormatter
{
    /**
     * @param string|null $projectDir the root the annotated files are given relative to, as GitHub expects
     */
    public function __construct(
        private ?string $projectDir = null,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return 'github';
    }

    #[Override]
    public function format(Report $report, bool $onlyProblems = false): string
    {
        $lines = [];

        foreach ($report->failures() as $verdict) {
            $controller = $verdict->route->controller;
            $properties = '';

            if (null !== $controller?->file) {
                $properties = 'file='.$this->property($this->relative($controller->file));
                $properties .= null === $controller->line ? '' : ',line='.$controller->line;
                $properties .= ',';
            }

            $message = \sprintf('Route "%s" (%s): %s.', $verdict->route->name, Describe::route($verdict), $verdict->problem());
            $lines[] = '::error '.$properties.'title=Route access::'.$this->data($message);
        }

        foreach (Describe::warnings($report) as $warning) {
            $lines[] = '::warning title=Route access::'.$this->data($warning);
        }

        $lines[] = Describe::summary($report);

        return implode("\n", $lines)."\n";
    }

    private function relative(string $file): string
    {
        $root = null === $this->projectDir ? null : rtrim($this->projectDir, '/').'/';

        return null !== $root && str_starts_with($file, $root) ? substr($file, \strlen($root)) : $file;
    }

    private function data(string $value): string
    {
        return strtr($value, ['%' => '%25', "\r" => '%0D', "\n" => '%0A']);
    }

    private function property(string $value): string
    {
        return strtr($value, ['%' => '%25', "\r" => '%0D', "\n" => '%0A', ':' => '%3A', ',' => '%2C']);
    }
}
