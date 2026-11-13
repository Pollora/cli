<?php

declare(strict_types=1);

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Pollora\Cli\Concerns\ConfiguresPrompts;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

afterEach(function (): void {
    Prompt::fallbackWhen(false);
});

/**
 * Wire the prompt fallbacks to an input fed with the given answers.
 */
function promptHost(string $answers, ?BufferedOutput $output = null): object
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
    $input->setInteractive(false);
    $output ??= new BufferedOutput;
    $host->setUp($input, $output);

    $input->setStream($stream);
    $input->setInteractive(true);

    return $host;
}

it('answers a select prompt from the typed choice', function (): void {
    promptHost("b\n");

    expect((new SelectPrompt('Pick one', ['a' => 'Alpha', 'b' => 'Beta'], 'a'))->prompt())->toBe('b');
});

it('answers a confirm prompt from the typed answer', function (): void {
    promptHost("no\n");

    expect((new ConfirmPrompt('Sure?', default: true))->prompt())->toBeFalse();
});

it('asks again until a required answer is given', function (): void {
    $output = new BufferedOutput;
    $answers = ['', 'value'];

    $result = promptHost('', $output)->until(static function () use (&$answers): string {
        return array_shift($answers);
    }, 'Please answer.', null, $output);

    expect($result)->toBe('value')
        ->and($output->fetch())->toContain('Please answer.');
});

it('uses a generic message when a required answer has no custom one', function (): void {
    $output = new BufferedOutput;
    $answers = [false, true];

    $result = promptHost('', $output)->until(static function () use (&$answers): bool {
        return array_shift($answers);
    }, true, null, $output);

    expect($result)->toBeTrue()
        ->and($output->fetch())->toContain('Required.');
});

it('asks again until the validator accepts the answer', function (): void {
    $output = new BufferedOutput;
    $answers = ['bad', 'good'];

    $result = promptHost('', $output)->until(
        static function () use (&$answers): string {
            return array_shift($answers);
        },
        false,
        static fn (string $value): ?string => $value === 'bad' ? 'That is bad.' : null,
        $output,
    );

    expect($result)->toBe('good')
        ->and($output->fetch())->toContain('That is bad.');
});
