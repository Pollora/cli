<?php

declare(strict_types=1);

namespace Pollora\Cli\Commands;

use Pollora\Cli\Version;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class VersionCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('version')
            ->setDescription('Display the Pollora CLI version');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Pollora CLI</info> version <comment>'.Version::get().'</comment>');

        return Command::SUCCESS;
    }
}
