<?php

declare(strict_types=1);

namespace Pollora\Cli\Concerns;

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\TextPrompt;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait ConfiguresPrompts
{
    protected function configurePrompts(InputInterface $input, OutputInterface $output): void
    {
        Prompt::fallbackWhen(! $input->isInteractive() || PHP_OS_FAMILY === 'Windows');

        TextPrompt::fallbackUsing(fn (TextPrompt $prompt): string => $this->promptUntilValid(
            fn (): string => (new SymfonyStyle($input, $output))->ask($prompt->label, $prompt->default ?: null) ?? '',
            $prompt->required,
            $prompt->validate,
            $output
        ));

        ConfirmPrompt::fallbackUsing(fn (ConfirmPrompt $prompt): bool => $this->promptUntilValid(
            fn (): bool => (new SymfonyStyle($input, $output))->confirm($prompt->label, $prompt->default),
            $prompt->required,
            $prompt->validate,
            $output
        ));

        SelectPrompt::fallbackUsing(fn (SelectPrompt $prompt): string => $this->promptUntilValid(
            fn (): string => (new SymfonyStyle($input, $output))->choice($prompt->label, $prompt->options, $prompt->default),
            false,
            $prompt->validate,
            $output
        ));
    }

    protected function promptUntilValid(\Closure $prompt, bool|string $required, ?\Closure $validate, OutputInterface $output): mixed
    {
        while (true) {
            $result = $prompt();

            if ($required && (in_array($result, ['', [], false], true))) {
                $output->writeln('<error>'.(is_string($required) ? $required : 'Required.').'</error>');

                continue;
            }

            if ($validate instanceof \Closure) {
                $error = $validate($result);

                if (is_string($error) && $error !== '') {
                    $output->writeln(sprintf('<error>%s</error>', $error));

                    continue;
                }
            }

            return $result;
        }
    }
}
