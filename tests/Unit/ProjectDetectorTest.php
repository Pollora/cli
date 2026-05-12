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
