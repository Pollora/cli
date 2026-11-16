<?php

declare(strict_types=1);

use Pollora\Cli\Application;
use Pollora\Cli\ArtisanCommandDiscoverer;
use Pollora\Cli\ProjectDetector;
use Pollora\Cli\ProxyCommand;
use Pollora\Cli\Tests\Support\Clock;
use Pollora\Cli\Tests\Support\FakeHttp;
use Pollora\Cli\Tests\Support\FileOverlay;
use Pollora\Cli\Tests\Support\Sandbox;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

const PB_ARTISAN = <<<'PHP'
file_put_contents(__DIR__.'/call.json', json_encode(['args' => array_slice($argv, 1), 'cwd' => getcwd()]));
if (is_file(__DIR__.'/fail.flag')) {
    echo json_encode(['commands' => [['name' => 'pollora:ghost', 'description' => 'Should not be listed']]]);
    exit(2);
}
if (($argv[1] ?? '') === 'list') {
    echo json_encode(['commands' => [
        ['name' => 'pollora:status', 'description' => 'Show the status'],
        ['name' => 'pollora:new', 'description' => 'Clashes with a global command'],
        ['name' => 'pollora:bare'],
    ]]);
}
PHP;

beforeEach(function (): void {
    $this->sandbox = new Sandbox;
    $this->sandbox->fakeTools();

    $this->project = $this->sandbox->work.'/project';
    mkdir($this->project.'/vendor/pollora/framework', 0755, true);
    file_put_contents($this->project.'/artisan', "<?php\n".PB_ARTISAN);
    $this->argv = $_SERVER['argv'] ?? [];
});

afterEach(function (): void {
    $_SERVER['argv'] = $this->argv;
    FileOverlay::uninstall();
    Clock::thaw();
    FakeHttp::reset();
    $this->sandbox->restore();
});

function pbCall(string $project): array
{
    return json_decode((string) file_get_contents($project.'/call.json'), true);
}

it('runs the proxied command from the project directory, wherever the CLI was started', function (): void {
    $_SERVER['argv'] = ['pollora', 'status'];

    (new ProxyCommand(new BufferedOutput, new ProjectDetector($this->project)))->execute(new ArgvInput($_SERVER['argv']));

    expect(realpath(pbCall($this->project)['cwd']))->toBe(realpath($this->project));
});

it('relays what the proxied command prints', function (): void {
    $this->sandbox->script('ddev', 'echo "from ddev"');
    mkdir($this->project.'/.ddev');
    $output = new BufferedOutput;
    $_SERVER['argv'] = ['pollora', 'status'];

    (new ProxyCommand($output, new ProjectDetector($this->project)))->execute(new ArgvInput($_SERVER['argv']));

    expect($output->fetch())->toBe("from ddev\n");
});

it('does not proxy, nor run, an empty command name', function (): void {
    $proxy = new ProxyCommand(new BufferedOutput, new ProjectDetector($this->project));
    $input = new ArgvInput(['pollora', '']);

    expect($proxy->shouldProxy($input))->toBeFalse()
        ->and($proxy->execute($input))->toBe(1)
        ->and(file_exists($this->project.'/call.json'))->toBeFalse();
});

it('asks artisan for the JSON list from the project directory', function (): void {
    (new ArtisanCommandDiscoverer(new ProjectDetector($this->project)))->discover();

    $call = pbCall($this->project);

    expect($call['args'])->toBe(['list', '--format=json'])
        ->and(realpath($call['cwd']))->toBe(realpath($this->project));
});

it('discovers nothing from an artisan that fails, even when it printed a list', function (): void {
    touch($this->project.'/fail.flag');

    expect((new ArtisanCommandDiscoverer(new ProjectDetector($this->project)))->discover())->toBe([]);
});

it('registers every project command after a clashing one, and not the clashing one itself', function (): void {
    chdir($this->project);
    $_SERVER['argv'] = ['pollora', 'list'];
    $application = Application::create();
    $application->setAutoExit(false);

    $output = new BufferedOutput;
    $application->doRun(new ArgvInput($_SERVER['argv']), $output);
    $display = $output->fetch();

    expect($display)->toContain('Show the status')
        ->and($display)->toContain('bare')
        ->and($display)->not->toContain('Clashes with a global command');
});

it('lists the project commands when no command is given', function (): void {
    chdir($this->project);
    $_SERVER['argv'] = ['pollora'];
    $application = Application::create();
    $application->setAutoExit(false);

    $output = new BufferedOutput;
    $application->doRun(new ArgvInput($_SERVER['argv']), $output);

    expect($output->fetch())->toContain('Show the status');
});

it('registers the project commands only to list them', function (): void {
    chdir($this->project);
    $_SERVER['argv'] = ['pollora', 'help', 'status'];
    $application = Application::create();
    $application->setAutoExit(false);
    $application->setCatchExceptions(false);

    expect(fn (): int => $application->doRun(new ArgvInput($_SERVER['argv']), new BufferedOutput))
        ->toThrow(CommandNotFoundException::class);
});

it('announces a newer release after the command it ran', function (): void {
    chdir($this->sandbox->work);
    $_SERVER['argv'] = ['pollora', 'list'];
    $application = Application::create();
    $application->setAutoExit(false);

    Clock::freeze(1_700_000_000);
    mkdir($this->sandbox->root.'/home/.pollora', 0755, true);
    file_put_contents($this->sandbox->root.'/home/.pollora/update-check.json', (string) json_encode(['version' => 'v99.0.0', 'checked_at' => 1_700_000_000]));
    FileOverlay::install(['/src/../../../../composer.lock' => (string) json_encode(['packages' => [['name' => 'pollora/cli', 'version' => 'v1.0.0']]])]);

    $output = new BufferedOutput;
    $application->doRun(new ArgvInput($_SERVER['argv']), $output);

    expect($output->fetch())->toEndWith("A new version of Pollora CLI is available: v99.0.0 (current: v1.0.0)\n  Run pollora self-update to update.\n\n");
});
