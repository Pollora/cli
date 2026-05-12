<?php

declare(strict_types=1);

use Pollora\Cli\Application;

it('creates an application instance', function (): void {
    $app = Application::create();

    expect($app)->toBeInstanceOf(Application::class)
        ->and($app->getName())->toBe('Pollora CLI');
});

it('registers all commands', function (): void {
    $app = Application::create();

    expect($app->has('new'))->toBeTrue()
        ->and($app->has('version'))->toBeTrue()
        ->and($app->has('self-update'))->toBeTrue();
});
