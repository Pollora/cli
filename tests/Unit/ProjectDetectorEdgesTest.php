<?php

declare(strict_types=1);

use Pollora\Cli\ProjectDetector;
use Pollora\Cli\Tests\Support\Sandbox;

beforeEach(function (): void {
    $this->sandbox = new Sandbox;
});

afterEach(function (): void {
    $this->sandbox->restore();
});

it('needs the artisan file itself, not only the framework in vendor', function (): void {
    mkdir($this->sandbox->work.'/vendor/pollora/framework', 0755, true);

    expect((new ProjectDetector($this->sandbox->work))->isPolloraProject())->toBeFalse();
});

it('has no directory when the current one no longer exists', function (): void {
    $gone = $this->sandbox->root.'/gone';
    mkdir($gone);
    chdir($gone);
    rmdir($gone);

    if (getcwd() !== false) {
        $this->markTestSkipped('This platform still reports a removed current directory.');
    }

    expect((new ProjectDetector)->getDirectory())->toBe('');
});
