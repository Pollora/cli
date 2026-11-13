<?php

declare(strict_types=1);

use Laravel\Prompts\Prompt;
use Pollora\Cli\Application;
use Pollora\Cli\Tests\Support\Sandbox;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function (): void {
    $this->sandbox = new Sandbox;
    $this->sandbox->fakeTools();

    $this->tester = new CommandTester(Application::create()->find('new'));
});

afterEach(function (): void {
    Prompt::fallbackWhen(false);
    $this->sandbox->restore();
});

it('installs a project with Composer then runs the artisan setup commands', function (): void {
    $status = $this->tester->execute(['name' => 'demo'], ['interactive' => false]);
    $calls = $this->sandbox->calls();

    expect($status)->toBe(0)
        ->and($calls)->toContain('composer create-project pollora/pollora demo --remove-vcs --prefer-dist --no-scripts --stability=beta --no-ansi')
        ->and($calls)->toContain('php artisan pollora:env:setup --no-interaction')
        ->and($calls)->toContain('php artisan pollora:install --no-interaction')
        ->and($this->tester->getDisplay())->toContain('Pollora was installed successfully')
        ->and($this->tester->getDisplay())->toContain('cd demo')
        ->and($this->tester->getDisplay())->not->toContain('ddev.site')
        ->and((string) file_get_contents($this->sandbox->work.'/demo/.env'))->toContain('APP_KEY=base64:');
});

it('pins the requested version and the stable channel on the create-project call', function (): void {
    $this->tester->execute(['name' => 'demo', '--ver' => '13.1.0'], ['interactive' => false]);
    $this->tester->execute(['name' => 'other', '--stable' => true], ['interactive' => false]);

    expect($this->sandbox->calls())
        ->toContain('composer create-project pollora/pollora:v13.1.0 demo --remove-vcs --prefer-dist --no-scripts --no-ansi')
        ->toContain('composer create-project pollora/pollora other --remove-vcs --prefer-dist --no-scripts --no-ansi');
});

it('forwards --quiet to Composer and leaves ANSI on when the output is decorated', function (): void {
    $this->tester->execute(['name' => 'demo', '--quiet' => true], ['interactive' => false, 'decorated' => true]);

    $calls = $this->sandbox->calls();

    expect($calls)->toContain('--stability=beta --quiet')
        ->and($calls)->not->toContain('--no-ansi');
});

it('fails with a message when Composer leaves no project behind', function (): void {
    $this->sandbox->env('FAKE_NO_INSTALL', '1');

    $status = $this->tester->execute(['name' => 'demo'], ['interactive' => false]);

    expect($status)->toBe(1)
        ->and($this->tester->getDisplay())->toContain('problem installing Pollora')
        ->and($this->sandbox->calls())->not->toContain('artisan');
});

it('fails when an artisan setup command fails', function (): void {
    $this->sandbox->env('FAKE_FAIL_ON', 'pollora:install');

    $status = $this->tester->execute(['name' => 'demo'], ['interactive' => false]);

    expect($status)->toBe(1)
        ->and($this->tester->getDisplay())->toContain('php artisan pollora:install failed');
});

it('replaces an existing directory when --force is given', function (): void {
    mkdir($this->sandbox->work.'/demo');
    file_put_contents($this->sandbox->work.'/demo/old.txt', 'old');

    $status = $this->tester->execute(['name' => 'demo', '--force' => true], ['interactive' => false]);

    expect($status)->toBe(0)
        ->and(file_exists($this->sandbox->work.'/demo/old.txt'))->toBeFalse()
        ->and(is_file($this->sandbox->work.'/demo/artisan'))->toBeTrue();
});

it('refuses --force in the current directory', function (): void {
    $status = $this->tester->execute(['name' => '.', '--force' => true], ['interactive' => false]);

    expect($status)->toBe(1)
        ->and($this->sandbox->calls())->toBe('');
});

it('refuses to install into a current directory that already holds a project', function (): void {
    file_put_contents($this->sandbox->work.'/composer.json', '{}');

    $status = $this->tester->execute(['name' => '.'], ['interactive' => false]);

    expect($status)->toBe(1)
        ->and($this->tester->getDisplay())->toContain('already exists');
});

it('installs into the current directory when the name is a dot', function (): void {
    $status = $this->tester->execute(['name' => '.'], ['interactive' => false]);

    expect($status)->toBe(0)
        ->and($this->sandbox->calls())->toContain('composer create-project pollora/pollora . ')
        ->and(is_file($this->sandbox->work.'/artisan'))->toBeTrue();
});

it('uses a composer.phar found in the current directory', function (): void {
    file_put_contents(
        $this->sandbox->work.'/composer.phar',
        '<?php mkdir($argv[3].\'/vendor\', 0755, true); touch($argv[3].\'/artisan\'); file_put_contents($argv[3].\'/composer.json\', \'{}\');',
    );

    $status = $this->tester->execute(['name' => 'demo'], ['interactive' => false]);

    expect($status)->toBe(0)
        ->and($this->sandbox->calls())->not->toContain('composer create-project');
});

it('initialises a Git repository on the requested branch with --git', function (): void {
    $status = $this->tester->execute(['name' => 'demo', '--git' => true, '--branch' => 'trunk'], ['interactive' => false]);
    $calls = $this->sandbox->calls();

    expect($status)->toBe(0)
        ->and($calls)->toContain('git --version')
        ->and($calls)->toContain('git init -q')
        ->and($calls)->toContain('git add .')
        ->and($calls)->toContain('git commit -q -m Initial Pollora project')
        ->and($calls)->toContain('git branch -M trunk');
});

it('skips the Git repository when git is not available', function (): void {
    $this->sandbox->env('FAKE_FAIL_ON', '--version');

    $status = $this->tester->execute(['name' => 'demo', '--git' => true], ['interactive' => false]);

    expect($status)->toBe(0)
        ->and($this->sandbox->calls())->not->toContain('git init');
});

it('installs a DDEV project and points its environment at the DDEV database', function (): void {
    $status = $this->tester->execute(['name' => 'demo', '--ddev' => true], ['interactive' => false]);
    $calls = $this->sandbox->calls();
    $env = (string) file_get_contents($this->sandbox->work.'/demo/.env');

    expect($status)->toBe(0)
        ->and($calls)->toContain('ddev config --project-name=demo --project-type=wordpress')
        ->and($calls)->toContain('ddev start')
        ->and($calls)->toContain('ddev composer create-project pollora/pollora --no-interaction --remove-vcs --prefer-dist --no-scripts --stability=beta')
        ->and($calls)->toContain('ddev composer update pollora/framework --no-scripts')
        ->and($calls)->toContain('ddev exec php artisan pollora:env-setup --install')
        ->and($calls)->toContain('ddev exec php artisan pollora:install --no-interaction')
        ->and($calls)->toContain('ddev exec mysql')
        ->and($env)->toContain('DB_HOST=db')
        ->and($env)->toContain('DB_PASSWORD=db')
        ->and($env)->toContain('APP_URL=https://demo.ddev.site')
        ->and($env)->toContain('APP_KEY=base64:')
        ->and($this->tester->getDisplay())->toContain('https://demo.ddev.site')
        ->and($this->tester->getDisplay())->toContain('ddev launch');
});

it('still finishes a DDEV install when the skeleton ships no env file', function (): void {
    $this->sandbox->env('FAKE_NO_ENV', '1');

    $status = $this->tester->execute(['name' => 'demo', '--ddev' => true], ['interactive' => false]);

    expect($status)->toBe(0)
        ->and(file_exists($this->sandbox->work.'/demo/.env'))->toBeFalse();
});

it('fails when --ddev is asked and DDEV is not installed', function (): void {
    $this->sandbox->onlyFakePath();
    unlink($this->sandbox->bin.'/ddev');

    $status = $this->tester->execute(['name' => 'demo', '--ddev' => true], ['interactive' => false]);

    expect($status)->toBe(1)
        ->and(is_dir($this->sandbox->work.'/demo'))->toBeFalse();
});

it('fails when the DDEV Composer install leaves no project behind', function (): void {
    $this->sandbox->env('FAKE_NO_INSTALL', '1');

    $status = $this->tester->execute(['name' => 'demo', '--ddev' => true], ['interactive' => false]);

    expect($status)->toBe(1)
        ->and($this->sandbox->calls())->not->toContain('pollora:install');
});

/**
 * Run the `new` command with typed answers, going through the prompt fallbacks
 * (the same path the CLI takes without a real terminal).
 *
 * @param  array<string, mixed>  $arguments
 * @return array{int, string}
 */
function runNewWithAnswers(array $arguments, string $answers): array
{
    $command = Application::create()->find('new');
    $command->mergeApplicationDefinition();

    $input = new ArrayInput(['command' => 'new'] + $arguments, $command->getDefinition());
    $input->setInteractive(false);

    $output = new BufferedOutput;

    $invoke = static fn (string $method, mixed ...$args): mixed => (new ReflectionMethod($command, $method))->invoke($command, ...$args);

    // initialize() switches the prompts to their fallbacks while the input is not interactive...
    $invoke('initialize', $input, $output);

    // ...then the answers are fed to them as if typed in a terminal.
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $answers);
    rewind($stream);
    $input->setStream($stream);
    $input->setInteractive(true);

    $invoke('interact', $input, $output);
    $status = $invoke('execute', $input, $output);

    return [(int) $status, $output->fetch()];
}

it('asks for the project name, rejects invalid ones and then installs', function (): void {
    // Empty (required), "a b" (invalid characters), "ac" (valid). DDEV: no. Git: no.
    [$status, $display] = runNewWithAnswers([], "\na b\nac\nno\nno\n");

    expect($status)->toBe(0)
        ->and($display)->toContain('The project name is required.')
        ->and($display)->toContain('letters, numbers, dashes, underscores, and periods')
        ->and(is_file($this->sandbox->work.'/ac/artisan'))->toBeTrue()
        ->and($this->sandbox->calls())->toContain('php artisan pollora:install')
        ->and($this->sandbox->calls())->not->toContain('--no-interaction')
        ->and($this->sandbox->calls())->not->toContain('ddev config')
        ->and($this->sandbox->calls())->not->toContain('git init');
});

it('offers DDEV and Git interactively and follows the answers', function (): void {
    [$status] = runNewWithAnswers(['name' => 'demo'], "yes\nyes\n");

    expect($status)->toBe(0)
        ->and($this->sandbox->calls())->toContain('ddev config')
        ->and($this->sandbox->calls())->toContain('git init -q');
});

it('does not offer DDEV interactively when it is not installed', function (): void {
    $this->sandbox->onlyFakePath();
    unlink($this->sandbox->bin.'/ddev');

    // Only the Git question is asked.
    [$status] = runNewWithAnswers(['name' => 'demo'], "no\n");

    expect($status)->toBe(0)
        ->and($this->sandbox->calls())->not->toContain('ddev')
        ->and($this->sandbox->calls())->not->toContain('git init');
});
