<?php

declare(strict_types=1);

use Pollora\Cli\Application;
use Pollora\Cli\Tests\Support\FakeHttp;
use Pollora\Cli\Tests\Support\FileOverlay;
use Pollora\Cli\Tests\Support\Sandbox;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function (): void {
    $this->sandbox = new Sandbox;
    $this->sandbox->script('composer', <<<'SH'
echo "composer $*" >> "$FAKE_LOG"
[ -z "$FAKE_FAIL_ON" ] || { echo "composer broke"; exit 1; }
echo "composer ok"
SH);
    FakeHttp::reset();
    $this->current = static fn (string $version) => FileOverlay::install([
        '/src/../../../../composer.lock' => (string) json_encode(['packages' => [['name' => 'pollora/cli', 'version' => $version]]]),
    ]);
    $this->tester = new CommandTester(Application::create()->find('self-update'));
});

afterEach(function (): void {
    FileOverlay::uninstall();
    FakeHttp::reset();
    $this->sandbox->restore();
});

it('asks Packagist with a five second timeout and requires the newest stable release as a caret constraint', function (): void {
    ($this->current)('v1.2.3');
    FakeHttp::packagist(['dev-main', '2.0.0-dev', 'v1.5.0', 'v1.4.0']);

    $status = $this->tester->execute([]);

    expect($status)->toBe(0)
        ->and($this->tester->getDisplay())->toBe(
            "Current version: v1.2.3\n"
            ."Updating to v1.5.0...\n"
            ."\n"
            ."  composer ok\n"
            ."\n"
            ."Pollora CLI updated successfully!\n"
        )
        ->and(FakeHttp::$requests)->toBe([['https://repo.packagist.org/p2/pollora/cli.json', ['timeout' => 5]]])
        ->and($this->sandbox->calls())->toBe("composer global require pollora/cli:^1.5.0 --no-interaction\n");
});

it('strips the v of the tag whatever the spelling of the version', function (): void {
    ($this->current)('v1.2.3');
    FakeHttp::packagist(['3.1.0']);

    $this->tester->execute([]);

    expect($this->sandbox->calls())->toBe("composer global require pollora/cli:^3.1.0 --no-interaction\n");
});

it('does not update a CLI that is already up to date', function (string $current, string $latest): void {
    ($this->current)($current);
    FakeHttp::packagist([$latest]);

    $status = $this->tester->execute([]);

    expect($status)->toBe(0)
        ->and($this->tester->getDisplay())->toBe("Current version: {$current}\nYou are already using the latest version ({$latest}).\n")
        ->and($this->sandbox->calls())->toBe('');
})->with([
    'same version' => ['v1.2.3', 'v1.2.3'],
    'same without v' => ['1.2.3', 'v1.2.3'],
    'ahead of Packagist' => ['v1.3.0', 'v1.2.9'],
]);

it('updates a development build and a CLI that is behind', function (string $current): void {
    ($this->current)($current);
    FakeHttp::packagist(['v1.2.3']);

    $this->tester->execute([]);

    expect($this->tester->getDisplay())->toContain('Updating to v1.2.3...')
        ->and($this->sandbox->calls())->toContain('pollora/cli:^1.2.3');
})->with(['dev-main', 'v1.2.2', 'UNKNOWN']);

it('falls back to a plain require when Packagist gives no usable answer', function (?string $answer): void {
    ($this->current)('v1.2.3');
    FakeHttp::$answer = $answer;

    $this->tester->execute([]);

    expect($this->tester->getDisplay())->toContain("Checking for updates...\n\n  composer ok")
        ->and($this->sandbox->calls())->toBe("composer global require pollora/cli --no-interaction\n");
})->with([
    'unreachable' => [null],
    'not JSON' => ['nope'],
    'package missing' => [(string) json_encode(['packages' => []])],
    'only dev versions' => [(string) json_encode(['packages' => ['pollora/cli' => [['version' => 'dev-main'], ['version' => '1.0.0-dev']]]])],
]);

it('shows each line of composer indented and reports the failure with the manual command', function (): void {
    ($this->current)('v1.2.3');
    FakeHttp::packagist(['v1.5.0']);
    $this->sandbox->env('FAKE_FAIL_ON', '1');

    $status = $this->tester->execute([]);

    expect($status)->toBe(1)
        ->and($this->tester->getDisplay())->toBe(
            "Current version: v1.2.3\n"
            ."Updating to v1.5.0...\n"
            ."\n"
            ."  composer broke\n"
            ."\n"
            ."Failed to update Pollora CLI.\n"
            ."  You can try manually: composer global require pollora/cli\n"
        );
});

it('reads no missing key from an answer that has no package list', function (string $answer): void {
    ($this->current)('v1.2.3');
    FakeHttp::$answer = $answer;
    $warnings = [];
    set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $this->tester->execute([]);
    } finally {
        restore_error_handler();
    }

    expect($this->tester->getDisplay())->toContain('Checking for updates...')
        ->and($warnings)->toBe([]);
})->with([
    'empty object' => ['{}'],
    'no package' => ['{"packages":{}}'],
    'not JSON' => ['nope'],
]);

it('never takes a build that is not a release number for up to date, even a patched one', function (): void {
    ($this->current)('pl1');
    FakeHttp::packagist(['v1.2.3']);

    $this->tester->execute([]);

    expect($this->tester->getDisplay())->toContain('Updating to v1.2.3...');
});
