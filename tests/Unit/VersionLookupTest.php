<?php

declare(strict_types=1);

use Pollora\Cli\Tests\Support\FileOverlay;
use Pollora\Cli\Tests\Support\Sandbox;
use Pollora\Cli\UpdateChecker;
use Pollora\Cli\Version;
use Symfony\Component\Console\Output\BufferedOutput;

// The lock file sits four levels above src/ and the package composer.json one
// level above: the overlay serves them from memory so no real file is involved.
const LOCK_SUFFIX = '/../../../../composer.lock';
const COMPOSER_SUFFIX = '/../composer.json';

afterEach(function (): void {
    FileOverlay::uninstall();
});

function lockWith(string $version): string
{
    return (string) json_encode(['packages' => [
        ['name' => 'monolog/monolog', 'version' => '3.0.0'],
        ['name' => 'pollora/cli', 'version' => $version],
    ]]);
}

it('reads its version from the lock file of the project that installed it', function (): void {
    FileOverlay::install([LOCK_SUFFIX => lockWith('v1.2.3')]);

    expect(Version::get())->toBe('v1.2.3');
});

it('falls back to the composer.json version when the lock does not list the CLI', function (): void {
    FileOverlay::install([
        LOCK_SUFFIX => (string) json_encode(['packages' => [['name' => 'monolog/monolog', 'version' => '3.0.0']]]),
        COMPOSER_SUFFIX => '{"name":"pollora/cli","version":"9.9.9"}',
    ]);

    expect(Version::get())->toBe('9.9.9');
});

it('falls back to dev-main when neither the lock nor composer.json gives a version', function (string $lock): void {
    FileOverlay::install([LOCK_SUFFIX => $lock, COMPOSER_SUFFIX => '{"name":"pollora/cli"}']);

    expect(Version::get())->toBe('dev-main');
})->with([
    'lock is not JSON' => 'nope',
    'lock has no packages' => '{}',
    'lock is unreadable JSON scalar' => '"text"',
]);

it('falls back to dev-main when the lock file cannot be read', function (): void {
    FileOverlay::install([LOCK_SUFFIX => false, COMPOSER_SUFFIX => '{"name":"pollora/cli"}']);

    // file_get_contents() warns about the unreadable lock before returning false.
    set_error_handler(static fn (): bool => true);

    try {
        expect(Version::get())->toBe('dev-main');
    } finally {
        restore_error_handler();
    }
});

it('reports UNKNOWN when composer.json is missing, unreadable or invalid', function (string|false|null $composer): void {
    FileOverlay::install([COMPOSER_SUFFIX => $composer]);
    set_error_handler(static fn (): bool => true);

    try {
        expect(Version::get())->toBe('UNKNOWN');
    } finally {
        restore_error_handler();
    }
})->with([
    'missing' => [null],
    'unreadable' => [false],
    'invalid JSON' => ['not json'],
]);

describe('update notification', function (): void {
    beforeEach(function (): void {
        $this->sandbox = new Sandbox;
        // Packagist is never reached: the proxy is a closed local port.
        mkdir($this->sandbox->root.'/home/.pollora', 0755, true);
    });

    afterEach(function (): void {
        $this->sandbox->restore();
    });

    function seedUpdateCache(string $home, string $version, int $checkedAt): void
    {
        file_put_contents($home.'/.pollora/update-check.json', (string) json_encode([
            'version' => $version,
            'checked_at' => $checkedAt,
        ]));
    }

    it('announces a newer version found in a fresh cache', function (): void {
        seedUpdateCache($this->sandbox->root.'/home', 'v2.0.0', time());
        FileOverlay::install([LOCK_SUFFIX => lockWith('v1.2.3')]);

        $output = new BufferedOutput;
        UpdateChecker::notify($output);

        expect($output->fetch())
            ->toContain('A new version of Pollora CLI is available: v2.0.0 (current: v1.2.3)')
            ->toContain('pollora self-update');
    });

    it('stays silent when the cached version is not newer than the current one', function (string $cached): void {
        seedUpdateCache($this->sandbox->root.'/home', $cached, time());
        FileOverlay::install([LOCK_SUFFIX => lockWith('v1.2.3')]);

        $output = new BufferedOutput;
        UpdateChecker::notify($output);

        expect($output->fetch())->toBe('');
    })->with(['v1.2.3', '1.0.0']);

    it('stays silent when the current version is not a release number', function (): void {
        seedUpdateCache($this->sandbox->root.'/home', 'v2.0.0', time());
        FileOverlay::install([LOCK_SUFFIX => lockWith('dev-main')]);

        $output = new BufferedOutput;
        UpdateChecker::notify($output);

        expect($output->fetch())->toBe('');
    });

    it('stays silent when the cache is stale and Packagist cannot be reached', function (string $content): void {
        file_put_contents($this->sandbox->root.'/home/.pollora/update-check.json', $content);
        FileOverlay::install([LOCK_SUFFIX => lockWith('v1.2.3')]);

        $output = new BufferedOutput;
        UpdateChecker::notify($output);

        expect($output->fetch())->toBe('')
            ->and((string) file_get_contents($this->sandbox->root.'/home/.pollora/update-check.json'))->toBe($content);
    })->with([
        'expired entry' => [(string) json_encode(['version' => 'v9.0.0', 'checked_at' => 1])],
        'corrupt file' => ['not json'],
        'incomplete entry' => ['{"version":"v9.0.0"}'],
    ]);

    it('stays silent when there is no cache and Packagist cannot be reached', function (): void {
        $output = new BufferedOutput;
        UpdateChecker::notify($output);

        expect($output->fetch())->toBe('')
            ->and(file_exists($this->sandbox->root.'/home/.pollora/update-check.json'))->toBeFalse();
    });
});
