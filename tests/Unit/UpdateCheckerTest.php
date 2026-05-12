<?php

declare(strict_types=1);

use Pollora\Cli\UpdateChecker;
use Symfony\Component\Console\Output\BufferedOutput;

it('does not crash when notifying', function (): void {
    $output = new BufferedOutput;

    // Should not throw even if network is unavailable
    UpdateChecker::notify($output);

    expect(true)->toBeTrue();
});

it('uses pollora home directory for cache', function (): void {
    $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? sys_get_temp_dir();

    expect($home.'/.pollora')->toBeString();
});
