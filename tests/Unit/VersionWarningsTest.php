<?php

declare(strict_types=1);

use Pollora\Cli\Tests\Support\FileOverlay;
use Pollora\Cli\Version;

afterEach(function (): void {
    FileOverlay::uninstall();
});

/**
 * Version::get() together with every PHP warning it raised on the way.
 *
 * @return array{string, list<string>}
 */
function versionAndWarnings(): array
{
    $warnings = [];
    set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $version = Version::get();
    } finally {
        restore_error_handler();
    }

    return [$version, $warnings];
}

it('answers UNKNOWN without even trying to read a composer.json that is missing', function (): void {
    FileOverlay::install(['/src/../composer.json' => null]);

    expect(versionAndWarnings())->toBe(['UNKNOWN', []]);
});

it('answers UNKNOWN without decoding a composer.json that cannot be read', function (): void {
    FileOverlay::install(['/src/../composer.json' => false]);

    [$version, $warnings] = versionAndWarnings();

    expect($version)->toBe('UNKNOWN')
        // the single warning is the failed read itself, nothing is decoded afterwards
        ->and($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('file_get_contents');
});

it('answers UNKNOWN without looking at the lock when composer.json is not JSON', function (): void {
    FileOverlay::install(['/src/../composer.json' => 'not json', '/src/../../../../composer.lock' => '{"packages":[{"name":"pollora/cli","version":"v9.9.9"}]}']);

    expect(versionAndWarnings())->toBe(['UNKNOWN', []]);
});

it('walks the packages of the lock only when it lists some', function (string $lock): void {
    FileOverlay::install(['/src/../composer.json' => '{"name":"pollora/cli"}', '/src/../../../../composer.lock' => $lock]);

    expect(versionAndWarnings())->toBe(['dev-main', []]);
})->with([
    'object without packages' => ['{}'],
    'packages that is empty' => ['{"packages":[]}'],
    'packages of other vendors' => ['{"packages":[{"name":"monolog/monolog","version":"3.0.0"}]}'],
]);

it('picks the CLI among the packages of the lock whatever its position', function (): void {
    FileOverlay::install([
        '/src/../composer.json' => '{"name":"pollora/cli"}',
        '/src/../../../../composer.lock' => '{"packages":[{"name":"a/a","version":"1.0.0"},{"name":"pollora/cli","version":"v4.5.6"},{"name":"z/z","version":"2.0.0"}]}',
    ]);

    expect(versionAndWarnings())->toBe(['v4.5.6', []]);
});

it('falls back to the composer.json version when the lock sits nowhere near vendor', function (): void {
    // Only the lock path next to vendor is read: the one found elsewhere stays unused.
    FileOverlay::install([
        '/src/../composer.json' => '{"name":"pollora/cli","version":"1.0.0"}',
        '/src/../../../../composer.lock' => null,
    ]);

    expect(versionAndWarnings())->toBe(['1.0.0', []]);
});
