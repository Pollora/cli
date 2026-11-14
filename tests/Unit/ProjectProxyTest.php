<?php

declare(strict_types=1);

use Pollora\Cli\Application;
use Pollora\Cli\ArtisanCommandDiscoverer;
use Pollora\Cli\ProjectDetector;
use Pollora\Cli\ProxyCommand;
use Pollora\Cli\Tests\Support\Sandbox;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/*
 * A fake Pollora project: its artisan is a small PHP script that records how it
 * was called and prints a canned `list --format=json` answer.
 */
function fakeProject(Sandbox $sandbox, string $artisanBody): string
{
    $project = $sandbox->work.'/project';
    mkdir($project.'/vendor/pollora/framework', 0755, true);
    file_put_contents($project.'/artisan', "<?php\n".$artisanBody);

    return $project;
}

const FAKE_ARTISAN_RECORDING = <<<'PHP'
file_put_contents(__DIR__.'/call.json', json_encode(array_slice($argv, 1)));
if (in_array('fail', $argv, true)) {
    exit(7);
}
if (($argv[1] ?? '') === 'list') {
    echo json_encode(['commands' => [
        ['name' => 'list', 'description' => 'List commands'],
        ['name' => 'pollora:status', 'description' => 'Show the status'],
        ['name' => 'pollora:new', 'description' => 'Clashes with a global command'],
        ['name' => 'pollora:bare'],
        ['name' => 'migrate', 'description' => 'Not a Pollora command'],
    ]]);
}
PHP;

beforeEach(function (): void {
    $this->sandbox = new Sandbox;
    $this->sandbox->fakeTools();

    $this->argv = $_SERVER['argv'] ?? [];
});

afterEach(function (): void {
    $_SERVER['argv'] = $this->argv;
    $this->sandbox->restore();
});

it('proxies the command and its arguments to the project artisan', function (): void {
    $project = fakeProject($this->sandbox, FAKE_ARTISAN_RECORDING);
    $_SERVER['argv'] = ['pollora', 'status', '--flag', 'value'];

    $proxy = new ProxyCommand(new BufferedOutput, new ProjectDetector($project));
    $status = $proxy->execute(new ArgvInput($_SERVER['argv']));

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($project.'/call.json'), true))->toBe(['pollora:status', '--flag', 'value']);
});

it('returns the exit code of the proxied artisan command', function (): void {
    $project = fakeProject($this->sandbox, FAKE_ARTISAN_RECORDING);
    $_SERVER['argv'] = ['pollora', 'status', 'fail'];

    $proxy = new ProxyCommand(new BufferedOutput, new ProjectDetector($project));

    expect($proxy->execute(new ArgvInput($_SERVER['argv'])))->toBe(7);
});

it('does not forward arguments it cannot read from a command line', function (): void {
    $project = fakeProject($this->sandbox, FAKE_ARTISAN_RECORDING);

    $proxy = new ProxyCommand(new BufferedOutput, new ProjectDetector($project));
    $status = $proxy->execute(new ArrayInput(['command' => 'status']));

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($project.'/call.json'), true))->toBe(['pollora:status']);
});

it('fails when asked to proxy without any command', function (): void {
    $project = fakeProject($this->sandbox, FAKE_ARTISAN_RECORDING);

    $proxy = new ProxyCommand(new BufferedOutput, new ProjectDetector($project));

    expect($proxy->execute(new ArrayInput([])))->toBe(1)
        ->and(file_exists($project.'/call.json'))->toBeFalse();
});

it('runs the command inside DDEV when the project has a .ddev directory', function (): void {
    $project = fakeProject($this->sandbox, FAKE_ARTISAN_RECORDING);
    mkdir($project.'/.ddev');
    $this->sandbox->script('ddev', 'echo "ddev $*" >> "$FAKE_LOG"; exit 3');
    $_SERVER['argv'] = ['pollora', 'status', '-v'];

    $proxy = new ProxyCommand(new BufferedOutput, new ProjectDetector($project));
    $status = $proxy->execute(new ArgvInput($_SERVER['argv']));

    expect($status)->toBe(3)
        ->and($this->sandbox->calls())->toContain('ddev exec php artisan pollora:status -v')
        ->and(file_exists($project.'/call.json'))->toBeFalse();
});

it('discovers the pollora commands of the project by their short name', function (): void {
    $project = fakeProject($this->sandbox, FAKE_ARTISAN_RECORDING);

    $commands = (new ArtisanCommandDiscoverer(new ProjectDetector($project)))->discover();

    expect($commands)->toBe([
        'status' => 'Show the status',
        'new' => 'Clashes with a global command',
        'bare' => '',
    ]);
});

it('discovers nothing when artisan fails', function (): void {
    $project = fakeProject($this->sandbox, "exit(1);\n");

    expect((new ArtisanCommandDiscoverer(new ProjectDetector($project)))->discover())->toBe([]);
});

it('discovers nothing when artisan does not answer with JSON', function (): void {
    $project = fakeProject($this->sandbox, "echo 'not json';\n");

    expect((new ArtisanCommandDiscoverer(new ProjectDetector($project)))->discover())->toBe([]);
});

it('discovers nothing when the JSON answer lists no commands', function (): void {
    $project = fakeProject($this->sandbox, "echo '{}';\n");

    expect((new ArtisanCommandDiscoverer(new ProjectDetector($project)))->discover())->toBe([]);
});

it('hands any other command over to the project when run inside a Pollora project', function (): void {
    $project = fakeProject($this->sandbox, FAKE_ARTISAN_RECORDING);
    chdir($project);
    $_SERVER['argv'] = ['pollora', 'status'];

    $application = Application::create();
    $application->setAutoExit(false);

    $status = $application->doRun(new ArgvInput($_SERVER['argv']), new BufferedOutput);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($project.'/call.json'), true))->toBe(['pollora:status']);
});

it('lists the project commands next to the global ones inside a Pollora project', function (): void {
    $project = fakeProject($this->sandbox, FAKE_ARTISAN_RECORDING);
    chdir($project);
    $_SERVER['argv'] = ['pollora', 'list'];

    $application = Application::create();
    $application->setAutoExit(false);

    $output = new BufferedOutput;
    $status = $application->doRun(new ArgvInput($_SERVER['argv']), $output);
    $display = $output->fetch();

    expect($status)->toBe(0)
        ->and($display)->toContain('Show the status')
        ->and($display)->not->toContain('Clashes with a global command')
        ->and($display)->toContain('Create a new Pollora application');
});

it('runs its own commands outside a Pollora project', function (): void {
    $_SERVER['argv'] = ['pollora', 'list'];

    $application = Application::create();
    $application->setAutoExit(false);

    $output = new BufferedOutput;
    $status = $application->doRun(new ArgvInput($_SERVER['argv']), $output);

    expect($status)->toBe(0)
        ->and($output->fetch())->not->toContain('Show the status');
});
