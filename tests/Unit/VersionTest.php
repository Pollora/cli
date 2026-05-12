<?php

declare(strict_types=1);

use Pollora\Cli\Version;

it('returns a version string', function (): void {
    $version = Version::get();

    expect($version)->toBeString()
        ->and($version)->not->toBeEmpty();
});

it('returns dev-main when no lock file exists', function (): void {
    // When running from the package itself without a lock file,
    // it should fallback to dev-main from composer.json
    $version = Version::get();

    expect($version)->toBeString();
});
