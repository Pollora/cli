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

    public function isDdev(): bool
    {
        return is_dir($this->getDirectory().'/.ddev');
    }

    public function getArtisanPath(): string
    {
        return $this->getDirectory().'/artisan';
    }

    /**
     * Build the command array to execute an artisan command,
     * using DDEV when the project is configured for it.
     *
     * @param  list<string>  $args
     * @return list<string>
     */
    public function getArtisanCommand(string $artisanCommand, array $args = []): array
    {
        if ($this->isDdev()) {
            return array_merge(['ddev', 'exec', 'php', 'artisan', $artisanCommand], $args);
        }

        return array_merge([PHP_BINARY, $this->getArtisanPath(), $artisanCommand], $args);
    }
}
