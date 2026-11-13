<?php

declare(strict_types=1);

use Pollora\Cli\Concerns\RunsCommands;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * The command line that RunsCommands builds, read back from the process it ran.
 *
 * @param  list<string>  $commands
 */
function runsCommandsLine(array $commands, bool $quiet = false): string
{
    $runner = new class
    {
        use RunsCommands;

        public BufferedOutput $output;

        public ArrayInput $input;

        public function build(array $commands): string
        {
            return $this->runCommands($commands, disableOutput: true)->getCommandLine();
        }
    };

    $runner->output = new BufferedOutput; // not decorated, as when the output is not a terminal
    $runner->input = new ArrayInput($quiet ? ['--quiet' => true] : [], new InputDefinition([new InputOption('quiet', null, InputOption::VALUE_NONE)]));

    return $runner->build($commands);
}

it('does not hand the Composer flags to rm, as GNU rm refuses an option it does not know', function (): void {
    $gone = sys_get_temp_dir().'/pollora-cli-flags-'.bin2hex(random_bytes(4));

    expect(runsCommandsLine(['rm -rf "'.$gone.'"']))->toBe('rm -rf "'.$gone.'"')
        ->and(runsCommandsLine(['rm -rf "'.$gone.'"'], quiet: true))->toBe('rm -rf "'.$gone.'"');
});

it('does not hand the Composer flags to rd, the Windows way of removing a directory', function (): void {
    expect(runsCommandsLine(['rd /s /q "C:\\gone"']))->toBe('rd /s /q "C:\\gone"');
});

it('still hands --no-ansi to the other commands when the output is not decorated, and --quiet when asked', function (): void {
    expect(runsCommandsLine(['echo composer']))->toBe('echo composer --no-ansi')
        ->and(runsCommandsLine(['echo composer'], quiet: true))->toBe('echo composer --no-ansi --quiet');
});

it('still leaves chmod, git and ddev without flags', function (): void {
    expect(runsCommandsLine(['chmod +x x', 'git init']))->toBe('chmod +x x && git init');
});
