<?php

declare(strict_types=1);

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\TextPrompt;
use Pollora\Cli\Concerns\ConfiguresPrompts;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Prompts remember "fall back" for good once asked to: start from a clean state.
 */
function cpResetFallback(): void
{
    (new ReflectionProperty(Prompt::class, 'shouldFallback'))->setValue(null, false);
}

function cpFallbackState(): bool
{
    return (new ReflectionProperty(Prompt::class, 'shouldFallback'))->getValue();
}

beforeEach(fn () => cpResetFallback());
afterEach(fn () => cpResetFallback());

function cpHost(string $answers, bool $interactive = false, ?BufferedOutput $output = null): object
{
    $host = new class
    {
        use ConfiguresPrompts;

        public function setUp(ArrayInput $input, BufferedOutput $output): void
        {
            $this->configurePrompts($input, $output);
        }

        public function until(Closure $prompt, bool|string $required, ?Closure $validate, BufferedOutput $output): mixed
        {
            return $this->promptUntilValid($prompt, $required, $validate, $output);
        }
    };

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $answers);
    rewind($stream);

    $input = new ArrayInput([]);
    $input->setInteractive($interactive);
    $output ??= new BufferedOutput;
    $host->setUp($input, $output);

    $input->setStream($stream);
    $input->setInteractive(true);

    return $host;
}

it('falls back to the plain prompts only when there is no terminal to talk to', function (bool $interactive): void {
    $input = new ArrayInput([]);
    $input->setInteractive($interactive);

    (new class
    {
        use ConfiguresPrompts;

        public function setUp(ArrayInput $input): void
        {
            $this->configurePrompts($input, new BufferedOutput);
        }
    })->setUp($input);

    // Windows has no usable terminal handling and always falls back.
    expect(cpFallbackState())->toBe(! $interactive || PHP_OS_FAMILY === 'Windows');
})->with([true, false]);

it('offers the default of a text prompt on an empty answer, and nothing when there is none', function (): void {
    cpHost("\n");
    expect((new TextPrompt('Name?', default: 'demo'))->prompt())->toBe('demo');

    cpHost("\n");
    expect((new TextPrompt('Name?'))->prompt())->toBe('');

    cpHost("typed\n");
    expect((new TextPrompt('Name?', default: 'demo'))->prompt())->toBe('typed');
});

it('lets a select prompt answer with an empty choice', function (): void {
    cpHost("\n");

    expect((new SelectPrompt('Pick', ['' => 'None', 'a' => 'Alpha'], ''))->prompt())->toBe('');
});

it('uses the default of a confirm prompt on an empty answer', function (bool $default): void {
    cpHost("\n");

    expect((new ConfirmPrompt('Sure?', default: $default))->prompt())->toBe($default);
})->with([true, false]);

it('asks again when a required answer is an empty list', function (): void {
    $output = new BufferedOutput;
    $answers = [[], ['x']];

    $result = cpHost('', output: $output)->until(static function () use (&$answers): array {
        return array_shift($answers);
    }, true, null, $output);

    expect($result)->toBe(['x']);
});

it('shows the complaint of a required answer and of a validator as an error', function (): void {
    $output = new BufferedOutput;
    $output->setDecorated(true);

    $custom = ['', 'ok'];
    $generic = [false, true];
    $host = cpHost('', output: $output);

    $host->until(static function () use (&$custom): string {
        return array_shift($custom);
    }, 'Please answer.', null, $output);
    $host->until(static function () use (&$generic): bool {
        return array_shift($generic);
    }, true, null, $output);
    $host->until(static fn (): string => 'x', false, static function (string $value): string {
        static $first = true;
        $error = $first ? 'Too short.' : '';
        $first = false;

        return $error;
    }, $output);

    expect($output->fetch())->toBe(
        "\033[37;41mPlease answer.\033[39;49m\n"
        ."\033[37;41mRequired.\033[39;49m\n"
        ."\033[37;41mToo short.\033[39;49m\n"
    );
});

it('accepts an answer the validator rejects with an empty message as valid', function (): void {
    $output = new BufferedOutput;

    $result = cpHost('', output: $output)->until(static fn (): string => 'x', false, static fn (string $value): string => '', $output);

    expect($result)->toBe('x')
        ->and($output->fetch())->toBe('');
});
