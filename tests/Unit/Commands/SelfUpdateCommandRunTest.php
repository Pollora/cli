<?php

declare(strict_types=1);

use Pollora\Cli\Application;
use Pollora\Cli\Tests\Support\Sandbox;
use Symfony\Component\Console\Tester\CommandTester;

// Packagist is unreachable here: HTTPS_PROXY points to a closed local port, so no
// request ever leaves the machine and the "latest version" lookup always fails.

beforeEach(function (): void {
    $this->sandbox = new Sandbox;
    $this->sandbox->fakeTools();
    $this->sandbox->script('composer', <<<'SH'
echo "composer $*" >> "$FAKE_LOG"
[ -z "$FAKE_FAIL_ON" ] || { echo "composer broke"; exit 1; }
echo "composer ok"
SH);
    $this->tester = new CommandTester(Application::create()->find('self-update'));
});

afterEach(function (): void {
    $this->sandbox->restore();
});

it('updates through composer global require when the latest version is unknown', function (): void {
    $status = $this->tester->execute([]);
    $display = $this->tester->getDisplay();

    expect($status)->toBe(0)
        ->and($display)->toContain('Current version:')
        ->and($display)->toContain('Checking for updates...')
        ->and($display)->toContain('composer ok')
        ->and($display)->toContain('Pollora CLI updated successfully!')
        ->and($this->sandbox->calls())->toContain('composer global require pollora/cli --no-interaction');
});

it('is also reachable as self:update', function (): void {
    $application = Application::create();

    expect($application->find('self:update'))->toBe($application->find('self-update'));
});

it('reports the manual command when composer fails', function (): void {
    $this->sandbox->env('FAKE_FAIL_ON', '1');

    $status = $this->tester->execute([]);
    $display = $this->tester->getDisplay();

    expect($status)->toBe(1)
        ->and($display)->toContain('composer broke')
        ->and($display)->toContain('Failed to update Pollora CLI.')
        ->and($display)->toContain('composer global require pollora/cli');
});
