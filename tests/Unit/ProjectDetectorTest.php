<?php

declare(strict_types=1);

use Pollora\Cli\ProjectDetector;

it('detects a Pollora project when artisan and vendor/pollora/framework exist', function (): void {
    $dir = sys_get_temp_dir().'/pollora-test-'.uniqid();
    mkdir($dir.'/vendor/pollora/framework', 0755, true);
    touch($dir.'/artisan');

    $detector = new ProjectDetector($dir);

    expect($detector->isPolloraProject())->toBeTrue();
    expect($detector->getArtisanPath())->toBe($dir.'/artisan');
    expect($detector->getDirectory())->toBe($dir);

    // Cleanup
    unlink($dir.'/artisan');
    rmdir($dir.'/vendor/pollora/framework');
    rmdir($dir.'/vendor/pollora');
    rmdir($dir.'/vendor');
    rmdir($dir);
});

it('returns false when not in a Pollora project', function (): void {
    $dir = sys_get_temp_dir().'/not-pollora-'.uniqid();
    mkdir($dir, 0755, true);

    $detector = new ProjectDetector($dir);

    expect($detector->isPolloraProject())->toBeFalse();

    rmdir($dir);
});

it('returns false when artisan exists but no framework vendor', function (): void {
    $dir = sys_get_temp_dir().'/partial-pollora-'.uniqid();
    mkdir($dir, 0755, true);
    touch($dir.'/artisan');

    $detector = new ProjectDetector($dir);

    expect($detector->isPolloraProject())->toBeFalse();

    unlink($dir.'/artisan');
    rmdir($dir);
});

it('uses cwd when no directory is provided', function (): void {
    $detector = new ProjectDetector;

    expect($detector->getDirectory())->toBe((string) getcwd());
});

it('detects DDEV when .ddev directory exists', function (): void {
    $dir = sys_get_temp_dir().'/pollora-ddev-'.uniqid();
    mkdir($dir.'/.ddev', 0755, true);

    $detector = new ProjectDetector($dir);

    expect($detector->isDdev())->toBeTrue();

    rmdir($dir.'/.ddev');
    rmdir($dir);
});

it('returns false for isDdev when no .ddev directory', function (): void {
    $dir = sys_get_temp_dir().'/pollora-no-ddev-'.uniqid();
    mkdir($dir, 0755, true);

    $detector = new ProjectDetector($dir);

    expect($detector->isDdev())->toBeFalse();

    rmdir($dir);
});

it('builds DDEV artisan command when in DDEV context', function (): void {
    $dir = sys_get_temp_dir().'/pollora-ddev-cmd-'.uniqid();
    mkdir($dir.'/.ddev', 0755, true);

    $detector = new ProjectDetector($dir);

    expect($detector->getArtisanCommand('pollora:status'))
        ->toBe(['ddev', 'exec', 'php', 'artisan', 'pollora:status']);

    expect($detector->getArtisanCommand('pollora:install', ['--no-interaction']))
        ->toBe(['ddev', 'exec', 'php', 'artisan', 'pollora:install', '--no-interaction']);

    rmdir($dir.'/.ddev');
    rmdir($dir);
});

it('builds classic artisan command when not in DDEV context', function (): void {
    $dir = sys_get_temp_dir().'/pollora-classic-cmd-'.uniqid();
    mkdir($dir, 0755, true);
    touch($dir.'/artisan');

    $detector = new ProjectDetector($dir);

    expect($detector->getArtisanCommand('pollora:status'))
        ->toBe([PHP_BINARY, $dir.'/artisan', 'pollora:status']);

    unlink($dir.'/artisan');
    rmdir($dir);
});
