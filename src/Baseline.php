<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck;

use JsonException;
use Phil\RouteAccessCheck\Exception\InvalidBaseline;
use Phil\RouteAccessCheck\Report\Report;
use Phil\RouteAccessCheck\Report\RouteStatus;
use Phil\RouteAccessCheck\Report\RouteVerdict;
use stdClass;

/**
 * The failures an application tolerates while it adopts the check: each route name with the status it failed with.
 * A route keeps failing the check when its status changes — an uncovered route that becomes a conflict — and an
 * entry that no longer matches a failing route is reported, so that the baseline only shrinks.
 *
 * ```json
 * {"version": 1, "routes": {"legacy_export": "uncovered"}}
 * ```
 */
final readonly class Baseline
{
    public const int VERSION = 1;

    public const int MAX_BYTES = 5_000_000;

    /**
     * @param array<array-key, RouteStatus> $entries the tolerated status of each route, by name; a numeric name is an integer key
     */
    public function __construct(
        public array $entries = [],
    ) {
        foreach ($entries as $status) {
            if (!$status->isFailure()) {
                throw InvalidBaseline::because(\sprintf('it tolerates failures only, not "%s".', $status->value));
            }
        }
    }

    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_BYTES) {
            throw InvalidBaseline::because(\sprintf('it is larger than %d bytes.', self::MAX_BYTES));
        }

        try {
            $document = json_decode($json, false, 3, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidBaseline::because('it is not JSON.', $jsonException);
        }

        if (!$document instanceof stdClass || self::VERSION !== ($document->version ?? null) || !($document->routes ?? null) instanceof stdClass) {
            throw InvalidBaseline::because(\sprintf('it is an object with "version": %d and "routes".', self::VERSION));
        }

        $entries = [];

        foreach (get_object_vars($document->routes) as $name => $status) {
            $read = \is_string($status) ? RouteStatus::tryFrom($status) : null;

            if (null === $read) {
                throw InvalidBaseline::because('"routes" holds the status of each route, by name.');
            }

            $entries[$name] = $read;
        }

        return new self($entries);
    }

    /**
     * The failures of a report, baselined or not.
     */
    public static function fromReport(Report $report): self
    {
        $entries = [];

        foreach ($report->verdicts as $verdict) {
            if ($verdict->status->isFailure()) {
                $entries[$verdict->route->name] = $verdict->status;
            }
        }

        return new self($entries);
    }

    public function tolerates(RouteVerdict $verdict): bool
    {
        return $verdict->status->isFailure() && ($this->entries[$verdict->route->name] ?? null) === $verdict->status;
    }

    /**
     * The document, its routes sorted by name so that a change reads as a small diff.
     */
    public function toJson(): string
    {
        $routes = $this->entries;
        ksort($routes, \SORT_STRING);

        return json_encode(['version' => self::VERSION, 'routes' => (object) $routes], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
    }
}
