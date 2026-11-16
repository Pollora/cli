<?php

declare(strict_types=1);

use Pollora\Cli\Application;
use Pollora\Cli\Tests\Support\FileOverlay;
use Symfony\Component\Console\Tester\CommandTester;

afterEach(function (): void {
    FileOverlay::uninstall();
});

it('prints the version of the CLI on one line', function (): void {
    $tester = new CommandTester(Application::create()->find('version'));

    FileOverlay::install([
        '/src/../../../../composer.lock' => (string) json_encode(['packages' => [['name' => 'pollora/cli', 'version' => 'v4.5.6']]]),
    ]);

    expect($tester->execute([]))->toBe(0)
        ->and($tester->getDisplay())->toBe("Pollora CLI version v4.5.6\n");
});
