<?php

declare(strict_types=1);

namespace Pollora\Cli;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final readonly class ProxyCommand
{
    private ProjectDetector $detector;

    public function __construct(
        private OutputInterface $output,
        ?ProjectDetector $detector = null,
    ) {
        $this->detector = $detector ?? new ProjectDetector;
    }

    /**
     * Determine if we should proxy the command to the local artisan binary.
     *
     * We proxy when:
     * 1. We're inside a Pollora project
     * 2. The command is not one of the global CLI commands (new, version, self-update, list, help)
     */
    public function shouldProxy(InputInterface $input): bool
    {
        if (! $this->detector->isPolloraProject()) {
            return false;
        }

        $command = $this->resolveCommandName($input);

        if ($command === null) {
            return false;
        }

        return ! in_array($command, self::globalCommands(), true);
    }

    public function execute(InputInterface $input): int
    {
        $command = $this->resolveCommandName($input);

        if ($command === null) {
            return 1;
        }

        $artisanCommand = 'pollora:'.$command;

        // Build the full command with any remaining arguments
        $args = $this->resolveArguments($input);

        $process = new Process(
            array_merge([PHP_BINARY, $this->detector->getArtisanPath(), $artisanCommand], $args),
        );

        $process->setTimeout(null);
        $process->setWorkingDirectory($this->detector->getDirectory());

        try {
            $process->setTty(Process::isTtySupported());
        } catch (\RuntimeException) {
            // TTY not supported
        }

        $process->run(function (string $type, string $line): void {
            $this->output->write($line);
        });

        return $process->getExitCode() ?? 1;
    }

    /**
     * @return list<string>
     */
    public static function globalCommands(): array
    {
        return ['new', 'version', 'self-update', 'list', 'help', 'completion'];
    }

    private function resolveCommandName(InputInterface $input): ?string
    {
        $firstArgument = $input->getFirstArgument();

        if ($firstArgument === null || $firstArgument === '') {
            return null;
        }

        return $firstArgument;
    }

    /**
     * @return list<string>
     */
    private function resolveArguments(InputInterface $input): array
    {
        /** @var list<string> $tokens */
        $tokens = $input instanceof ArgvInput
            ? array_slice($_SERVER['argv'] ?? [], 2)
            : [];

        return $tokens;
    }
}
