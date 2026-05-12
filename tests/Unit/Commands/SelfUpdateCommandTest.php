<?php

declare(strict_types=1);

use Pollora\Cli\Application;

it('has the self-update command registered', function (): void {
    $app = Application::create();

    expect($app->has('self-update'))->toBeTrue();

    $command = $app->find('self-update');
    expect($command->getDescription())->toBe('Update the Pollora CLI to the latest version');
});
