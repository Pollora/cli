<?php

declare(strict_types=1);

use Pollora\Cli\Exceptions\InstallationException;

it('stays catchable as a runtime exception', function (): void {
    expect(InstallationException::applicationExists())->toBeInstanceOf(RuntimeException::class);
});

it('says how to recover from each installation failure', function (string $named, string $fragment): void {
    $exception = $named === 'artisanFailed'
        ? InstallationException::artisanFailed('pollora:install')
        : InstallationException::$named();

    expect($exception->getMessage())->toContain($fragment);
})->with([
    'existing application' => ['applicationExists', '--force'],
    'force in the current directory' => ['forceInCurrentDirectory', 'parent directory'],
    'failed install' => ['installFailed', 'run the command again'],
    'missing DDEV' => ['ddevMissing', 'ddev.readthedocs.io'],
    'failed DDEV install' => ['ddevInstallFailed', 'run the command again'],
    'DS_Store not removable' => ['metadataFileNotRemovable', 'Delete it by hand'],
    'failed artisan command' => ['artisanFailed', 'php artisan pollora:install failed'],
]);
