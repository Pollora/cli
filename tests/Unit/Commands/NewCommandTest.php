<?php

declare(strict_types=1);

use Pollora\Cli\Application;
use Pollora\Cli\Commands\NewCommand;
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

it('supports --stable option', function (): void {
    $app = Application::create();
    $command = $app->find('new');
    $definition = $command->getDefinition();

    expect($definition->hasOption('stable'))->toBeTrue();
});

it('installs pre-releases by default and stable releases with --stable', function (): void {
    expect(stabilityOptionFor(version: '', stable: false))->toBe(' --stability=beta')
        ->and(stabilityOptionFor(version: '', stable: true))->toBe('')
        ->and(stabilityOptionFor(version: '13.32.0-beta.2', stable: false))->toBe('');
});

it('normalizes exact versions and passes constraints through', function (): void {
    expect(versionConstraintFor(''))->toBe('')
        ->and(versionConstraintFor('13.32.0-beta.2'))->toBe(':v13.32.0-beta.2')
        ->and(versionConstraintFor('v13.4.0'))->toBe(':v13.4.0')
        ->and(versionConstraintFor('^13.32@beta'))->toBe(':^13.32@beta')
        ->and(versionConstraintFor('dev-main'))->toBe(':dev-main');
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

function newCommandWith(string $version, bool $stable = false): NewCommand
{
    $command = new NewCommand;
    $reflection = new ReflectionClass($command);

    foreach (['projectVersion' => $version, 'preferStable' => $stable] as $name => $value) {
        $property = $reflection->getProperty($name);
        $property->setValue($command, $value);
    }

    return $command;
}

function versionConstraintFor(string $version): string
{
    $command = newCommandWith($version);
    $method = new ReflectionMethod($command, 'getVersionConstraint');

    return (string) $method->invoke($command);
}

function stabilityOptionFor(string $version, bool $stable): string
{
    $command = newCommandWith($version, $stable);
    $method = new ReflectionMethod($command, 'getStabilityOption');

    return (string) $method->invoke($command);
}
