<?php

declare(strict_types=1);

use Pollora\Cli\Commands\NewCommand;

it('detects DDEV by its executable without requiring a successful version invocation', function (): void {
    $directory = sys_get_temp_dir().'/pollora-ddev-test-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $executable = $directory.'/ddev';
    file_put_contents($executable, "#!/bin/sh\nexit 1\n");
    chmod($executable, 0755);
    $originalPath = getenv('PATH');

    try {
        putenv('PATH='.$directory);
        $method = new ReflectionMethod(NewCommand::class, 'isDdevInstalled');
        expect($method->invoke(new NewCommand))->toBeTrue();
    } finally {
        if ($originalPath === false) {
            putenv('PATH');
        } else {
            putenv('PATH='.$originalPath);
        }

        unlink($executable);
        rmdir($directory);
    }
})->skip(PHP_OS_FAMILY === 'Windows', 'Uses a POSIX shell executable');

it('does not report DDEV installed when its executable is absent', function (): void {
    $directory = sys_get_temp_dir().'/pollora-ddev-test-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $originalPath = getenv('PATH');

    try {
        putenv('PATH='.$directory);
        $method = new ReflectionMethod(NewCommand::class, 'isDdevInstalled');
        expect($method->invoke(new NewCommand))->toBeFalse();
    } finally {
        if ($originalPath === false) {
            putenv('PATH');
        } else {
            putenv('PATH='.$originalPath);
        }

        rmdir($directory);
    }
})->skip(PHP_OS_FAMILY === 'Windows', 'Uses POSIX PATH semantics');

it('removes only root Finder metadata before DDEV composer installation', function (): void {
    $directory = sys_get_temp_dir().'/pollora-finder-test-'.bin2hex(random_bytes(8));
    mkdir($directory);
    file_put_contents($directory.'/.DS_Store', 'finder metadata');
    file_put_contents($directory.'/keep.txt', 'project data');

    try {
        $command = new NewCommand;
        $property = new ReflectionProperty(NewCommand::class, 'absolutePath');
        $property->setValue($command, $directory);
        $method = new ReflectionMethod(NewCommand::class, 'removeDdevBlockingMetadata');
        $method->invoke($command);

        expect(file_exists($directory.'/.DS_Store'))->toBeFalse()
            ->and(file_get_contents($directory.'/keep.txt'))->toBe('project data');

        // Calling it again is harmless, even if .DS_Store is already absent.
        $method->invoke($command);
    } finally {
        if (is_file($directory.'/.DS_Store')) {
            unlink($directory.'/.DS_Store');
        }

        unlink($directory.'/keep.txt');
        rmdir($directory);
    }
});
