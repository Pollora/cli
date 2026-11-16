<?php

declare(strict_types=1);

namespace Pollora\Cli\Tests\Support;

/**
 * What the replacement GuzzleHttp\Client below answers, and what it was asked.
 *
 * No test ever reaches Packagist: without a canned answer the client fails like
 * an unreachable network would.
 */
final class FakeHttp
{
    public static string|\Throwable|null $answer = null;

    /** @var list<array{string, array<string, mixed>}> */
    public static array $requests = [];

    public static function reset(): void
    {
        self::$answer = null;
        self::$requests = [];
    }

    /**
     * @param  list<string>  $versions  newest first
     */
    public static function packagist(array $versions): void
    {
        self::$answer = (string) json_encode(['packages' => ['pollora/cli' => array_map(
            static fn (string $version): array => ['version' => $version],
            $versions,
        )]]);
    }
}

/**
 * The frozen "now" of the code under test (see the time() override below).
 */
final class Clock
{
    public static ?int $now = null;

    public static function freeze(int $now): void
    {
        self::$now = $now;
    }

    public static function thaw(): void
    {
        self::$now = null;
    }
}
