<?php

declare(strict_types=1);

namespace Pollora\Cli;

final readonly class ProjectDetector
{
    public function __construct(
        private string $directory = '',
    ) {}

    public function getDirectory(): string
    {
        return $this->directory !== '' ? $this->directory : (string) getcwd();
    }

    public function isPolloraProject(): bool
    {
        $dir = $this->getDirectory();

        return file_exists($dir.'/artisan')
            && is_dir($dir.'/vendor/pollora/framework');
    }

    public function getArtisanPath(): string
    {
        return $this->getDirectory().'/artisan';
    }
}
