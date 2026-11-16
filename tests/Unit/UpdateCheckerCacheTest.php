<?php

declare(strict_types=1);

use Pollora\Cli\Tests\Support\Clock;
use Pollora\Cli\Tests\Support\FakeHttp;
use Pollora\Cli\Tests\Support\FileOverlay;
use Pollora\Cli\Tests\Support\Sandbox;
use Pollora\Cli\UpdateChecker;
use Symfony\Component\Console\Output\BufferedOutput;

const UC_NOW = 1_700_000_000;
const UC_ENDPOINT = 'https://repo.packagist.org/p2/pollora/cli.json';

beforeEach(function (): void {
    $this->sandbox = new Sandbox;
    $this->home = $this->sandbox->root.'/home';
    $this->cache = $this->home.'/.pollora/update-check.json';
    FakeHttp::reset();
    Clock::freeze(UC_NOW);
    FileOverlay::install([
        '/src/../../../../composer.lock' => (string) json_encode(['packages' => [['name' => 'pollora/cli', 'version' => 'v1.2.3']]]),
    ]);
});

afterEach(function (): void {
    FileOverlay::uninstall();
    Clock::thaw();
    FakeHttp::reset();
    $this->sandbox->restore();
});

function ucNotify(): string
{
    $output = new BufferedOutput;
    UpdateChecker::notify($output);

    return $output->fetch();
}

function ucSeed(string $cache, int $checkedAt, string $version = 'v2.0.0'): void
{
    mkdir(dirname($cache), 0755, true);
    file_put_contents($cache, (string) json_encode(['version' => $version, 'checked_at' => $checkedAt]));
}

it('announces the newest stable release it fetched, and remembers it', function (): void {
    FakeHttp::packagist(['dev-main', 'v2.1.0-dev', 'v2.0.0', 'v1.9.0']);

    expect(ucNotify())->toBe(
        "\n"
        ."  A new version of Pollora CLI is available: v2.0.0 (current: v1.2.3)\n"
        ."  Run pollora self-update to update.\n"
        ."\n"
    )
        ->and(FakeHttp::$requests)->toBe([[UC_ENDPOINT, ['timeout' => 3, 'connect_timeout' => 2]]])
        ->and(json_decode((string) file_get_contents($this->cache), true))->toBe(['version' => 'v2.0.0', 'checked_at' => UC_NOW]);
});

it('creates the cache directory with the usual permissions', function (): void {
    FakeHttp::packagist(['v2.0.0']);
    $umask = umask(0);

    try {
        ucNotify();
    } finally {
        umask($umask);
    }

    expect(is_dir($this->home.'/.pollora'))->toBeTrue()
        ->and(fileperms($this->home.'/.pollora') & 0777)->toBe(0755);
});

it('does not ask Packagist while the cache is younger than a day, and asks once it is a day old', function (int $age, int $requests): void {
    ucSeed($this->cache, UC_NOW - $age);
    FakeHttp::packagist(['v3.0.0']);

    $display = ucNotify();

    expect(FakeHttp::$requests)->toHaveCount($requests)
        ->and($display)->toContain($requests === 0 ? 'available: v2.0.0' : 'available: v3.0.0');
})->with([
    'just fetched' => [0, 0],
    'a second short of a day' => [86399, 0],
    'exactly a day' => [86400, 1],
    'a second over a day' => [86401, 1],
]);

it('fetches again when the cache lacks its version', function (): void {
    mkdir(dirname($this->cache), 0755, true);
    file_put_contents($this->cache, (string) json_encode(['checked_at' => UC_NOW]));
    FakeHttp::packagist(['v3.0.0']);

    expect(ucNotify())->toContain('available: v3.0.0')
        ->and(FakeHttp::$requests)->toHaveCount(1);
});

it('fetches again when the cache lacks its date', function (): void {
    mkdir(dirname($this->cache), 0755, true);
    file_put_contents($this->cache, (string) json_encode(['version' => 'v2.0.0']));
    FakeHttp::packagist(['v3.0.0']);

    expect(ucNotify())->toContain('available: v3.0.0');
});

it('does not decode a cache file it cannot read', function (): void {
    mkdir(dirname($this->cache), 0755, true);
    FileOverlay::uninstall();
    file_put_contents($this->cache, 'x');
    FileOverlay::install([
        '/src/../../../../composer.lock' => (string) json_encode(['packages' => [['name' => 'pollora/cli', 'version' => 'v1.2.3']]]),
        '/.pollora/update-check.json' => false,
    ]);
    set_error_handler(static fn (): bool => true);

    try {
        // Packagist cannot be reached (no canned answer): the notification is just skipped.
        expect(ucNotify())->toBe('');
    } finally {
        restore_error_handler();
    }
});

it('stays silent when the cached version is the current one or older, and speaks when it is newer', function (string $cached, bool $speaks): void {
    ucSeed($this->cache, UC_NOW, $cached);

    expect(ucNotify() !== '')->toBe($speaks);
})->with([
    'same version' => ['v1.2.3', false],
    'same without v' => ['1.2.3', false],
    'older' => ['v1.0.0', false],
    'newer patch' => ['v1.2.4', true],
    'newer major' => ['2.0.0', true],
]);

it('looks for its cache in USERPROFILE when HOME is not defined', function (): void {
    unset($_SERVER['HOME']);
    $this->sandbox->env('USERPROFILE', $this->sandbox->root.'/profile');
    FakeHttp::packagist(['v2.0.0']);

    ucNotify();

    expect(is_file($this->sandbox->root.'/profile/.pollora/update-check.json'))->toBeTrue();
});

it('skips a version list that has no usable release, or is not what Packagist sends', function (?string $answer): void {
    FakeHttp::$answer = $answer;
    ucSeed($this->cache, 1);

    expect(ucNotify())->toBe('');
})->with([
    'only dev versions' => [(string) json_encode(['packages' => ['pollora/cli' => [['version' => 'dev-main'], ['version' => '1.0.0-dev']]]])],
    'package missing' => [(string) json_encode(['packages' => []])],
    'not JSON' => ['<html>'],
    'unreachable' => [null],
]);

it('reads no missing key from an answer that has no package list', function (string $answer): void {
    FakeHttp::$answer = $answer;
    $warnings = [];
    set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $display = ucNotify();
    } finally {
        restore_error_handler();
    }

    expect($display)->toBe('')
        ->and($warnings)->toBe([]);
})->with([
    'empty object' => ['{}'],
    'no package' => ['{"packages":{}}'],
    'not JSON' => ['nope'],
]);
