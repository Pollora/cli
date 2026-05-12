<?php

declare(strict_types=1);

use Pollora\Cli\ProjectDetector;
use Pollora\Cli\ProxyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function createPolloraProject(): string
{
    $dir = sys_get_temp_dir().'/pollora-proxy-test-'.uniqid();
    mkdir($dir.'/vendor/pollora/framework', 0755, true);
    touch($dir.'/artisan');

    return $dir;
}

function cleanupDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }

    rmdir($dir);
}

it('should proxy when in a Pollora project and command is not global', function (): void {
    $dir = createPolloraProject();
    $detector = new ProjectDetector($dir);
    $proxy = new ProxyCommand(new BufferedOutput, $detector);
    $input = new ArrayInput(['command' => 'make-plugin']);

    expect($proxy->shouldProxy($input))->toBeTrue();

    cleanupDir($dir);
});

it('should not proxy global commands', function (string $command): void {
    $dir = createPolloraProject();
    $detector = new ProjectDetector($dir);
    $proxy = new ProxyCommand(new BufferedOutput, $detector);
    $input = new ArrayInput(['command' => $command]);

    expect($proxy->shouldProxy($input))->toBeFalse();

    cleanupDir($dir);
})->with(['new', 'version', 'self-update', 'list', 'help', 'completion']);

it('should not proxy when not in a Pollora project', function (): void {
    $dir = sys_get_temp_dir().'/not-pollora-proxy-'.uniqid();
    mkdir($dir, 0755, true);

    $detector = new ProjectDetector($dir);
    $proxy = new ProxyCommand(new BufferedOutput, $detector);
    $input = new ArrayInput(['command' => 'make-plugin']);

    expect($proxy->shouldProxy($input))->toBeFalse();

    cleanupDir($dir);
});

it('should not proxy when no command is given', function (): void {
    $dir = createPolloraProject();
    $detector = new ProjectDetector($dir);
    $proxy = new ProxyCommand(new BufferedOutput, $detector);
    $input = new ArrayInput([]);

    expect($proxy->shouldProxy($input))->toBeFalse();

    cleanupDir($dir);
});

it('lists global commands', function (): void {
    $commands = ProxyCommand::globalCommands();

    expect($commands)->toBeArray()
        ->and($commands)->toContain('new', 'version', 'self-update', 'list', 'help');
});
