<?php

declare(strict_types=1);

namespace Pollora\Cli\Concerns;

use Symfony\Component\Process\Process;

trait RunsCommands
{
    protected function runCommand(string $command, ?string $workingPath = null, bool $disableOutput = false): Process
    {
        return $this->runCommands([$command], $workingPath, $disableOutput);
    }

    /**
     * @param  list<string>  $commands
     */
    protected function runCommands(array $commands, ?string $workingPath = null, bool $disableOutput = false): Process
    {
        // --no-ansi and --quiet are Composer flags: the commands that are not Composer must not get them
        // (GNU rm refuses an option it does not know, so `pollora new --force` failed on Linux).
        $skipFlags = static fn (string $value): bool => str_starts_with($value, 'chmod')
            || str_starts_with($value, 'git')
            || str_starts_with($value, 'ddev')
            || str_starts_with($value, 'rm ')
            || str_starts_with($value, 'rd ');

        if (! $this->output->isDecorated()) {
            $commands = array_map(static fn (string $value): string => $skipFlags($value) ? $value : $value.' --no-ansi', $commands);
        }

        if ($this->input->getOption('quiet')) {
            $commands = array_map(static fn (string $value): string => $skipFlags($value) ? $value : $value.' --quiet', $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), $workingPath, timeout: null);

        if ($disableOutput) {
            $process->disableOutput()->run();
        } else {
            $process->run(function (string $type, string $line): void {
                $this->output->write('    '.$line);
            });
        }

        return $process;
    }
}
