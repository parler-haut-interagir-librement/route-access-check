<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Fixture;

use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Checks that vote on an argument Symfony maps from the request and validates first, with and without a check
 * before them.
 */
final class PayloadController
{
    /**
     * @param array<string, mixed> $order
     */
    #[IsGranted('ORDER_EDIT', 'order')]
    public function update(#[MapRequestPayload] array $order): string
    {
        return 'update '.\count($order);
    }

    /**
     * @param array<string, mixed> $order
     */
    #[IsGranted('IS_AUTHENTICATED')]
    #[IsGranted('ORDER_EDIT', subject: 'order')]
    public function guarded(#[MapRequestPayload] array $order): string
    {
        return 'guarded '.\count($order);
    }

    /**
     * @param array<string, mixed> $order
     */
    #[IsGranted('ORDER_EDIT', subject: 'order')]
    #[IsGranted('IS_AUTHENTICATED')]
    public function guardedTooLate(#[MapRequestPayload] array $order): string
    {
        return 'guarded too late '.\count($order);
    }

    /**
     * @param array<string, mixed> $query
     */
    #[IsGranted('SEARCH', ['other', 'query'])]
    public function search(int $other, #[MapQueryString] array $query): string
    {
        return 'search '.$other.' '.\count($query);
    }

    #[IsGranted('UPLOAD', 'file')]
    public function upload(#[MapUploadedFile] mixed $file): string
    {
        return 'upload '.get_debug_type($file);
    }

    #[IsGranted('VIEW', 'id')]
    public function show(int $id): string
    {
        return 'show '.$id;
    }

    #[IsGranted('VIEW', 'missing')]
    public function unknownSubject(int $id): string
    {
        return 'unknown subject '.$id;
    }

    /**
     * @param array<string, mixed> $query
     */
    #[IsGranted('SEARCH', 'query')]
    public function afterAnArgumentOfTheRoute(int $id, #[MapQueryString] array $query): string
    {
        return 'after '.$id.\count($query);
    }

    /**
     * A subject written twice, by position and by name: the name is the one that counts.
     *
     * @param array<string, mixed> $order
     */
    #[IsGranted('ORDER_EDIT', 'order', subject: 'id')]
    public function subjectTwice(int $id, #[MapRequestPayload] array $order): string
    {
        return 'twice '.$id.\count($order);
    }
}
