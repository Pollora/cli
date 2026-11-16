<?php

declare(strict_types=1);

use Laravel\Prompts\Prompt;
use Pollora\Cli\Application;
use Pollora\Cli\Commands\NewCommand;
use Pollora\Cli\Tests\Support\Sandbox;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

const NC_ART = "\n"
    ."  ██████╗  ██████╗ ██╗     ██╗      ██████╗ ██████╗  █████╗\n"
    ."  ██╔══██╗██╔═══██╗██║     ██║     ██╔═══██╗██╔══██╗██╔══██╗\n"
    ."  ██████╔╝██║   ██║██║     ██║     ██║   ██║██████╔╝███████║\n"
    ."  ██╔═══╝ ██║   ██║██║     ██║     ██║   ██║██╔══██╗██╔══██║\n"
    ."  ██║     ╚██████╔╝███████╗███████╗╚██████╔╝██║  ██║██║  ██║\n"
    ."  ╚═╝      ╚═════╝ ╚══════╝╚══════╝ ╚═════╝ ╚═╝  ╚═╝╚═╝  ╚═╝\n"
    ."\n";

beforeEach(function (): void {
    $this->sandbox = new Sandbox;
    $this->sandbox->fakeTools();

    $this->tester = new CommandTester(Application::create()->find('new'));
});

afterEach(function (): void {
    Prompt::fallbackWhen(false);
    $this->sandbox->restore();
});

it('prints the whole Composer installation exactly', function (): void {
    $status = $this->tester->execute(['name' => 'demo'], ['interactive' => false]);

    expect($status)->toBe(0)
        ->and($this->tester->getDisplay())->toBe(
            NC_ART
            ."\n"
            ."  Running pollora:env:setup...\n"
            ."\n"
            ."\n"
            ."  Running pollora:install...\n"
            ."\n"
            ."\n"
            ."  [OK] Pollora was installed successfully!\n"
            ."\n"
            ."  Enter your project directory with cd demo\n"
            ."  Documentation: https://pollora.dev\n"
            ."\n"
        );
});

it('prints the whole DDEV installation exactly', function (): void {
    $status = $this->tester->execute(['name' => 'demo', '--ddev' => true], ['interactive' => false]);

    expect($status)->toBe(0)
        ->and($this->tester->getDisplay())->toBe(
            NC_ART
            ."\n"
            ."  Setting up DDEV environment...\n"
            ."\n"
            ."  Starting DDEV...\n"
            ."\n"
            ."  Installing Pollora via Composer...\n"
            ."\n"
            ."  Configuring environment for DDEV...\n"
            ."\n"
            ."  Finalizing installation...\n"
            ."\n"
            ."  Running pollora:install...\n"
            ."\n"
            ."\n"
            ."  [OK] Pollora was installed successfully!\n"
            ."\n"
            ."  Enter your project directory with cd demo\n"
            ."  Your site is available at https://demo.ddev.site\n"
            ."\n"
            ."  Run Pollora commands from the project directory with pollora (e.g. pollora status): they run inside DDEV\n"
            ."  Use ddev launch to open your site in a browser\n"
            ."  Documentation: https://pollora.dev\n"
            ."\n"
        );
});

it('frames an error message in a box as wide as the message', function (): void {
    mkdir($this->sandbox->work.'/demo');
    $message = 'Application already exists! Choose another name or pass --force to replace it.';
    $pad = str_repeat(' ', mb_strlen($message));

    $status = $this->tester->execute(['name' => 'demo'], ['interactive' => false]);

    expect($status)->toBe(1)
        ->and($this->tester->getDisplay())->toBe(
            NC_ART
            ."\n"
            ."    {$pad}  \n"
            ."    {$message}  \n"
            ."    {$pad}  \n"
            ."\n"
        );
});

it('streams what artisan prints and tells it the terminal is dumb', function (): void {
    $this->sandbox->env('TERM', 'xterm-256color');
    $this->sandbox->script('php', 'echo "artisan TERM=$TERM"');

    $this->tester->execute(['name' => 'demo'], ['interactive' => false]);

    expect($this->tester->getDisplay())
        ->toContain("    artisan TERM=dumb\n");
});

it('shows what the tools print, indented, except for the database cleanup', function (): void {
    $this->sandbox->env('FAKE_ECHO', '1');

    $this->tester->execute(['name' => 'demo', '--ddev' => true], ['interactive' => false]);
    $display = $this->tester->getDisplay();

    expect($display)->toContain("    out: ddev start\n")
        ->and($display)->toContain("    out: ddev composer update pollora/framework --no-scripts\n")
        ->and($display)->not->toContain('mysql');
});

it('creates a missing project directory for DDEV with its parents and the usual permissions', function (): void {
    $umask = umask(0);

    try {
        $status = $this->tester->execute(['name' => 'a/b/demo', '--ddev' => true], ['interactive' => false]);
    } finally {
        umask($umask);
    }

    expect($status)->toBe(0)
        ->and(fileperms($this->sandbox->work.'/a/b/demo') & 0777)->toBe(0755)
        ->and(fileperms($this->sandbox->work.'/a') & 0777)->toBe(0755);
});

it('hands the requested version to the DDEV create-project call', function (): void {
    $this->tester->execute(['name' => 'demo', '--ddev' => true, '--ver' => '13.1.0'], ['interactive' => false]);

    expect($this->sandbox->calls())
        ->toContain("ddev composer create-project pollora/pollora:v13.1.0 --no-interaction --remove-vcs --prefer-dist --no-scripts\n");
});

it('removes the existing directory only when --force is given', function (): void {
    $this->sandbox->script('rm', 'echo "rm $*" >> "$FAKE_LOG"');
    mkdir($this->sandbox->work.'/old');

    $this->tester->execute(['name' => 'demo'], ['interactive' => false]);
    $without = $this->sandbox->calls();

    $this->tester->execute(['name' => 'old', '--force' => true], ['interactive' => false]);

    expect($without)->not->toContain('rm ')
        ->and($this->sandbox->calls())->toContain('rm -rf '.$this->sandbox->work.'/old');
});

it('does not remove anything when --force is given for the current directory', function (): void {
    $this->sandbox->script('rm', 'echo "rm $*" >> "$FAKE_LOG"');

    $this->tester->execute(['name' => '.', '--force' => true], ['interactive' => false]);

    expect($this->sandbox->calls())->not->toContain('rm ');
});

it('takes the options in whatever type they come', function (): void {
    // A stray int in an option must not break the install: every option is cast.
    $status = $this->tester->execute(['name' => 'demo', '--force' => 1, '--git' => 1, '--ddev' => 0, '--stable' => 1, '--ver' => 13], ['interactive' => false]);

    expect($status)->toBe(0)
        ->and($this->sandbox->calls())->toContain('pollora/pollora:13 ')
        ->and($this->sandbox->calls())->toContain('git init -q')
        ->and($this->sandbox->calls())->not->toContain('ddev')
        // an explicit version carries its own stability, whatever --stable says
        ->and($this->sandbox->calls())->not->toContain('--stability');
});

it('takes --stable as a flag of its own', function (): void {
    $this->tester->execute(['name' => 'demo', '--stable' => 1], ['interactive' => false]);

    expect($this->sandbox->calls())->not->toContain('--stability');
});

it('stops when the Composer install leaves an incomplete project', function (string $missing): void {
    $this->sandbox->env('FAKE_OMIT', $missing);

    $status = $this->tester->execute(['name' => 'demo'], ['interactive' => false]);

    expect($status)->toBe(1)
        ->and($this->tester->getDisplay())->toContain('problem installing Pollora')
        ->and($this->sandbox->calls())->not->toContain('artisan');
})->with(['vendor', 'artisan', 'json']);

it('offers DDEV with yes as the default and Git with no', function (): void {
    ncRunWithAnswers(['name' => 'demo'], "\n\n");

    expect($this->sandbox->calls())->toContain('ddev config')
        ->and($this->sandbox->calls())->not->toContain('git init');
});

it('does not ask about Git when --git is already given', function (): void {
    // Only the DDEV question is answered: asking about Git as well would ask the empty input.
    ncRunWithAnswers(['name' => 'demo', '--git' => true], "no\n");

    expect($this->sandbox->calls())->toContain('git init -q');
});

it('does not ask anything when the terminal is not interactive', function (): void {
    $status = $this->tester->execute(['name' => 'demo'], ['interactive' => false]);

    expect($status)->toBe(0)
        ->and($this->sandbox->calls())->not->toContain('ddev')
        ->and($this->sandbox->calls())->not->toContain('git');
});

it('names the artisan command line after the prefix of the php to run', function (string $prefix, string $expected): void {
    $command = new NewCommand;
    $input = new ArrayInput([]);
    $input->setInteractive(true);
    (new ReflectionProperty($command, 'input'))->setValue($command, $input);

    expect((new ReflectionMethod($command, 'artisanCommandLine'))->invoke($command, $prefix, 'x'))->toBe($expected);
})->with([
    'plain php' => ['php', 'php artisan x'],
    'a php path' => ['/usr/bin/php', '/usr/bin/php artisan x'],
    'ddev' => ['ddev', 'ddev exec php artisan x'],
    'ddev with arguments' => ['ddev exec php', 'ddev exec php artisan x'],
    'ddev with other arguments' => ['ddev other', 'ddev exec php artisan x'],
    'a program that only ends with ddev' => ['myddev', 'myddev artisan x'],
]);

/**
 * Run the `new` command with typed answers, as the CLI does without a real terminal.
 *
 * @param  array<string, mixed>  $arguments
 * @return array{int, string}
 */
function ncRunWithAnswers(array $arguments, string $answers): array
{
    $command = Application::create()->find('new');
    $command->mergeApplicationDefinition();

    $input = new ArrayInput(['command' => 'new'] + $arguments, $command->getDefinition());
    $input->setInteractive(false);

    $output = new BufferedOutput;

    $invoke = static fn (string $method, mixed ...$args): mixed => (new ReflectionMethod($command, $method))->invoke($command, ...$args);

    $invoke('initialize', $input, $output);

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $answers);
    rewind($stream);
    $input->setStream($stream);
    $input->setInteractive(true);

    $invoke('interact', $input, $output);
    $status = $invoke('execute', $input, $output);

    return [(int) $status, $output->fetch()];
}

it('removes the Finder metadata that appeared while DDEV started, so Composer accepts the directory', function (): void {
    $this->sandbox->script('ddev', <<<'SH'
echo "ddev $*" >> "$FAKE_LOG"
case "$1" in
  start) touch .DS_Store ;;
  composer)
    if [ "$2" = create-project ]; then
      [ ! -e .DS_Store ] || exit 1
      mkdir -p vendor; touch artisan; echo '{}' > composer.json
    fi ;;
esac
exit 0
SH);

    $status = $this->tester->execute(['name' => 'demo', '--ddev' => true], ['interactive' => false]);

    expect($status)->toBe(0)
        ->and(file_exists($this->sandbox->work.'/demo/.DS_Store'))->toBeFalse();
});
