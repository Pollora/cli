<?php

declare(strict_types=1);

use Pollora\Cli\Commands\NewCommand;
use Pollora\Cli\Tests\Support\FileOverlay;
use Pollora\Cli\Tests\Support\Sandbox;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function (): void {
    $this->sandbox = new Sandbox;
    $this->command = new NewCommand;
});

afterEach(function (): void {
    FileOverlay::uninstall();
    $this->sandbox->restore();
});

function necSet(NewCommand $command, array $properties): void
{
    foreach ($properties as $name => $value) {
        (new ReflectionProperty($command, $name))->setValue($command, $value);
    }
}

function necCall(NewCommand $command, string $method): mixed
{
    return (new ReflectionMethod($command, $method))->invoke($command);
}

/**
 * Every warning PHP raised while the callback ran.
 *
 * @return list<string>
 */
function necWarnings(Closure $callback): array
{
    $warnings = [];
    set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $warnings;
}

it('points every database setting and the app URL of the env file at DDEV', function (): void {
    $dir = $this->sandbox->work;
    file_put_contents($dir.'/.env', implode("\n", [
        'APP_URL=http://localhost',
        'DB_CONNECTION=sqlite',
        '#DB_HOST=127.0.0.1',
        '# DB_PORT=1234',
        'DB_DATABASE=laravel',
        'DB_USERNAME=root',
        'DB_PASSWORD=secret',
        'APP_KEY=base64:keep',
        'OTHER=untouched',
        '',
    ]));
    $output = new BufferedOutput;
    necSet($this->command, ['absolutePath' => $dir, 'relativePath' => 'demo', 'output' => $output]);

    necCall($this->command, 'configureDdevEnv');

    expect(file_get_contents($dir.'/.env'))->toBe(implode("\n", [
        'APP_URL=https://demo.ddev.site',
        'DB_CONNECTION=mysql',
        'DB_HOST=db',
        'DB_PORT=3306',
        'DB_DATABASE=db',
        'DB_USERNAME=db',
        'DB_PASSWORD=db',
        'APP_KEY=base64:keep',
        'OTHER=untouched',
        '',
    ]))
        ->and($output->fetch())->toBe("\n  Configuring environment for DDEV...\n");
});

it('leaves a project without env file alone', function (): void {
    necSet($this->command, ['absolutePath' => $this->sandbox->work, 'relativePath' => 'demo', 'output' => new BufferedOutput]);

    expect(necWarnings(fn (): mixed => necCall($this->command, 'configureDdevEnv')))->toBe([])
        ->and(file_exists($this->sandbox->work.'/.env'))->toBeFalse();
});

it('does not touch an env file that cannot be read', function (): void {
    necSet($this->command, ['absolutePath' => $this->sandbox->work, 'relativePath' => 'demo', 'output' => new BufferedOutput]);
    FileOverlay::install(['/.env' => false]);

    $warnings = necWarnings(fn (): mixed => necCall($this->command, 'configureDdevEnv'));

    // The only trouble reported is the failed read: nothing is searched in the missing content.
    expect($warnings)->each->toContain('file_get_contents');
});

it('stops when the env file turns unreadable after it was prepared', function (): void {
    necSet($this->command, ['absolutePath' => $this->sandbox->work, 'relativePath' => 'demo', 'output' => new BufferedOutput]);
    $reads = 0;
    FileOverlay::install(['/.env' => static function () use (&$reads): string|false {
        // First read (key check): a complete file. Second read (DDEV settings): gone.
        return ++$reads === 1 ? "APP_KEY=base64:keep\n" : false;
    }]);

    $warnings = necWarnings(fn (): mixed => necCall($this->command, 'configureDdevEnv'));

    expect($reads)->toBe(2)
        ->and($warnings)->each->toContain('file_get_contents');
});

it('returns nothing, quietly, when there is no env file and no example to copy', function (): void {
    necSet($this->command, ['absolutePath' => $this->sandbox->work]);

    $result = null;
    $warnings = necWarnings(function () use (&$result): void {
        $result = necCall($this->command, 'prepareEnvironmentFile');
    });

    expect($result)->toBeNull()
        ->and($warnings)->toBe([]);
});

it('returns nothing when the env file cannot be read', function (): void {
    necSet($this->command, ['absolutePath' => $this->sandbox->work]);
    FileOverlay::install(['/.env' => false]);

    $result = 'unset';
    necWarnings(function () use (&$result): void {
        $result = necCall($this->command, 'prepareEnvironmentFile');
    });

    expect($result)->toBeNull();
});

it('generates an application key of 32 random bytes', function (): void {
    file_put_contents($this->sandbox->work.'/.env', "APP_KEY=\n");
    necSet($this->command, ['absolutePath' => $this->sandbox->work]);

    necCall($this->command, 'prepareEnvironmentFile');
    preg_match('/^APP_KEY=base64:(.+)$/m', (string) file_get_contents($this->sandbox->work.'/.env'), $match);

    expect($match)->toHaveCount(2)
        ->and(strlen((string) base64_decode($match[1], true)))->toBe(32);
});

it('does not rewrite an env file that already has its key', function (): void {
    file_put_contents($this->sandbox->work.'/.env', "APP_KEY=base64:abc\nDB_HOST=x\n");
    necSet($this->command, ['absolutePath' => $this->sandbox->work]);

    expect(necCall($this->command, 'prepareEnvironmentFile'))->toBe($this->sandbox->work.'/.env')
        ->and(file_get_contents($this->sandbox->work.'/.env'))->toBe("APP_KEY=base64:abc\nDB_HOST=x\n");
});

it('copies the example when the project has no env file yet', function (): void {
    file_put_contents($this->sandbox->work.'/.env.example', "APP_NAME=Demo\nAPP_KEY=\n");
    necSet($this->command, ['absolutePath' => $this->sandbox->work]);

    necCall($this->command, 'prepareEnvironmentFile');

    expect(file_get_contents($this->sandbox->work.'/.env'))->toStartWith("APP_NAME=Demo\nAPP_KEY=base64:");
});

it('copes with a current directory that no longer exists', function (): void {
    $gone = $this->sandbox->root.'/gone';
    mkdir($gone);
    chdir($gone);
    rmdir($gone);

    if (getcwd() !== false) {
        $this->markTestSkipped('This platform still reports a removed current directory.');
    }

    $input = new ArrayInput(['name' => '.'], new InputDefinition([
        new InputArgument('name'),
        new InputOption('force'), new InputOption('git'), new InputOption('ddev'), new InputOption('ver', null, InputOption::VALUE_REQUIRED), new InputOption('stable'),
    ]));
    necSet($this->command, ['input' => $input]);

    necCall($this->command, 'processArguments');

    expect((new ReflectionProperty($this->command, 'absolutePath'))->getValue($this->command))->toBe('')
        // an empty path is the current directory when there is none
        ->and(necCall($this->command, 'pathIsCwd'))->toBeTrue();
});
