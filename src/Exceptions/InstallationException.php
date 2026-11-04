<?php

declare(strict_types=1);

namespace Pollora\Cli\Exceptions;

use RuntimeException;

/**
 * Raised when `pollora new` cannot create the project; the message says what to do next.
 */
final class InstallationException extends RuntimeException
{
    public static function applicationExists(): self
    {
        return new self('Application already exists! Choose another name or pass --force to replace it.');
    }

    public static function forceInCurrentDirectory(): self
    {
        return new self('Cannot use --force option when using current directory for installation! Run the command from the parent directory.');
    }

    public static function installFailed(): self
    {
        return new self('There was a problem installing Pollora! Fix the problem above, then run the command again.');
    }

    public static function ddevMissing(): self
    {
        return new self('DDEV is not installed. Please install it from https://ddev.readthedocs.io');
    }

    public static function ddevInstallFailed(): self
    {
        return new self('There was a problem installing Pollora via DDEV! Fix the problem above, then run the command again.');
    }

    public static function metadataFileNotRemovable(): self
    {
        return new self('Could not remove .DS_Store before DDEV Composer installation. Delete it by hand, then run the command again.');
    }

    public static function artisanFailed(string $artisanCommand): self
    {
        return new self(sprintf(
            'php artisan %s failed. Fix the problem above, then run it again from your project directory.',
            $artisanCommand
        ));
    }
}
