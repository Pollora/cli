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

it('skips the skeleton post-install scripts and drives them itself', function (): void {
    expect(createProjectFlagsFor(version: '', stable: false))
        ->toBe('--remove-vcs --prefer-dist --no-scripts --stability=beta')
        ->and(createProjectFlagsFor(version: '', stable: true))
        ->toBe('--remove-vcs --prefer-dist --no-scripts');
});

it('creates the env file with an application key and leaves an existing key alone', function (): void {
    $dir = sys_get_temp_dir().'/pollora-env-'.uniqid();
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/.env.example', "APP_NAME=Pollora\nAPP_KEY=\nDB_CONNECTION=sqlite\n");

    expect(prepareEnvironmentFileIn($dir))->toBe($dir.'/.env');

    $key = (string) preg_replace('/^.*APP_KEY=(.*)$.*/ms', '$1', (string) file_get_contents($dir.'/.env'));
    expect($key)->toStartWith('base64:');

    // Running again must not rotate the key of an already configured project.
    prepareEnvironmentFileIn($dir);
    expect(file_get_contents($dir.'/.env'))->toContain('APP_KEY='.$key);

    unlink($dir.'/.env');
    unlink($dir.'/.env.example');
    rmdir($dir);
});

it('returns null when there is no env file to prepare', function (): void {
    $dir = sys_get_temp_dir().'/pollora-env-'.uniqid();
    mkdir($dir, 0755, true);

    expect(prepareEnvironmentFileIn($dir))->toBeNull();

    rmdir($dir);
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

function createProjectFlagsFor(string $version, bool $stable): string
{
    $command = newCommandWith($version, $stable);
    $method = new ReflectionMethod($command, 'createProjectFlags');

    return (string) $method->invoke($command);
}

function prepareEnvironmentFileIn(string $directory): ?string
{
    $command = newCommandWith('');
    $property = new ReflectionProperty($command, 'absolutePath');
    $property->setValue($command, $directory);

    $method = new ReflectionMethod($command, 'prepareEnvironmentFile');

    /** @var string|null $path */
    $path = $method->invoke($command);

    return $path;
}
