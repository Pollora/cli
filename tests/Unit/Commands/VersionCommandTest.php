<?php

declare(strict_types=1);

use Pollora\Cli\Application;
use Symfony\Component\Console\Tester\CommandTester;

it('displays the CLI version', function (): void {
    $app = Application::create();
    $command = $app->find('version');
    $tester = new CommandTester($command);

    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('Pollora CLI');
});
