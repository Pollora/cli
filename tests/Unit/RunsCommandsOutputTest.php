<?php

declare(strict_types=1);

use Pollora\Cli\Concerns\RunsCommands;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A host of the RunsCommands trait with a real output buffer.
 *
 * @return array{object, BufferedOutput}
 */
function rcHost(bool $quiet = false): array
{
    $host = new class
    {
        use RunsCommands;

        public BufferedOutput $output;

        public ArrayInput $input;

        public function line(string $command, bool $disableOutput = false): string
        {
            return $this->runCommand($command, disableOutput: $disableOutput)->getCommandLine();
        }
    };

    $host->output = new BufferedOutput;
    $host->input = new ArrayInput($quiet ? ['--quiet' => true] : [], new InputDefinition([new InputOption('quiet', null, InputOption::VALUE_NONE)]));

    return [$host, $host->output];
}

it('leaves the commands that start with a non-Composer program without flags', function (string $command): void {
    [$host] = rcHost(quiet: true);

    expect($host->line($command, disableOutput: true))->toBe($command);
})->with(['chmod +x x', 'git init', 'ddev start', 'rm -rf x', 'rd /s /q x']);

it('hands the flags to a command that only mentions such a program at its end', function (string $command): void {
    [$host] = rcHost(quiet: true);

    expect($host->line($command, disableOutput: true))->toBe($command.' --no-ansi --quiet');
})->with(['echo chmod', 'echo git', 'echo ddev', 'echo rm ', 'echo rd ']);

it('writes what the command prints, indented, unless the output is disabled', function (): void {
    [$host, $output] = rcHost();

    $host->line('echo hello');
    $shown = $output->fetch();

    $host->line('echo hello', disableOutput: true);

    expect($shown)->toBe("    hello --no-ansi\n")
        ->and($output->fetch())->toBe('');
});
