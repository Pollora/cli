<?php

declare(strict_types=1);

use Pollora\Cli\Application;
use Symfony\Component\Console\Tester\CommandTester;

it('has the new command registered', function (): void {
    $app = Application::create();

    expect($app->has('new'))->toBeTrue();

    $command = $app->find('new');
    expect($command->getDescription())->toBe('Create a new Pollora application');
});

it('requires a name argument', function (): void {
    $app = Application::create();
    $command = $app->find('new');
    $definition = $command->getDefinition();

    expect($definition->hasArgument('name'))->toBeTrue();
});

it('supports --force option', function (): void {
    $app = Application::create();
    $command = $app->find('new');
    $definition = $command->getDefinition();

    expect($definition->hasOption('force'))->toBeTrue();
});

it('supports --git option', function (): void {
    $app = Application::create();
    $command = $app->find('new');
    $definition = $command->getDefinition();

    expect($definition->hasOption('git'))->toBeTrue();
});

it('supports --branch option with default value', function (): void {
    $app = Application::create();
    $command = $app->find('new');
    $definition = $command->getDefinition();

    expect($definition->hasOption('branch'))->toBeTrue()
        ->and($definition->getOption('branch')->getDefault())->toBe('main');
});

it('supports --ver option', function (): void {
    $app = Application::create();
    $command = $app->find('new');
    $definition = $command->getDefinition();

    expect($definition->hasOption('ver'))->toBeTrue();
});

it('supports --ddev option', function (): void {
    $app = Application::create();
    $command = $app->find('new');
    $definition = $command->getDefinition();

    expect($definition->hasOption('ddev'))->toBeTrue();
});

it('fails when directory already exists', function (): void {
    $dir = sys_get_temp_dir().'/pollora-exists-'.uniqid();
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/composer.json', '{}');

    $app = Application::create();
    $command = $app->find('new');
    $tester = new CommandTester($command);

    $cwd = getcwd();
    chdir(sys_get_temp_dir());

    $tester->execute([
        'name' => basename($dir),
    ], ['interactive' => false]);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('already exists');

    chdir((string) $cwd);
    unlink($dir.'/composer.json');
    rmdir($dir);
});
