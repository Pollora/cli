<?php

declare(strict_types=1);

namespace Pollora\Cli;

use Symfony\Component\Process\Process;

final readonly class ArtisanCommandDiscoverer
{
    public function __construct(
        private ProjectDetector $detector,
    ) {}

    /**
     * Discover available pollora:* artisan commands and return them
     * as an array of [short_name => description].
     *
     * @return array<string, string>
     */
    public function discover(): array
    {
        $process = new Process(
            $this->detector->getArtisanCommand('list', ['--format=json']),
        );

        $process->setTimeout(10);
        $process->setWorkingDirectory($this->detector->getDirectory());
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        /** @var array{commands?: list<array{name: string, description?: string}>}|null $data */
        $data = json_decode($process->getOutput(), true);

        if (! is_array($data)) {
            return [];
        }

        $commands = [];

        foreach ($data['commands'] ?? [] as $cmd) {
            $name = $cmd['name'];

            if (! str_starts_with($name, 'pollora:')) {
                continue;
            }

            // Use the short name (without pollora: prefix) as the CLI command name
            $shortName = substr($name, 8);
            $commands[$shortName] = $cmd['description'] ?? '';
        }

        return $commands;
    }
}
