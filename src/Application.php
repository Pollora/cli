<?php

declare(strict_types=1);

namespace Pollora\Cli;

use Pollora\Cli\Commands\NewCommand;
use Pollora\Cli\Commands\SelfUpdateCommand;
use Pollora\Cli\Commands\VersionCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('Pollora CLI', Version::get());

        $this->addCommand(new NewCommand);
        $this->addCommand(new VersionCommand);
        $this->addCommand(new SelfUpdateCommand);
    }

    public static function create(): self
    {
        return new self;
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $detector = new ProjectDetector;
        $proxy = new ProxyCommand($output, $detector);

        if ($proxy->shouldProxy($input)) {
            return $proxy->execute($input);
        }

        // When listing commands inside a Pollora project, discover
        // and register artisan pollora:* commands so they appear in the output
        $command = $input->getFirstArgument();

        if ($detector->isPolloraProject() && ($command === 'list' || $command === null)) {
            $this->registerProjectCommands($detector);
        }

        $exitCode = parent::doRun($input, $output);

        // Show update notification after command execution
        UpdateChecker::notify($output);

        return $exitCode;
    }

    private function registerProjectCommands(ProjectDetector $detector): void
    {
        $discoverer = new ArtisanCommandDiscoverer($detector);
        $commands = $discoverer->discover();

        foreach ($commands as $name => $description) {
            // Skip if this name conflicts with a global command
            if (in_array($name, ProxyCommand::globalCommands(), true)) {
                continue;
            }

            $cmd = new Command($name);
            $cmd->setDescription($description);
            $this->add($cmd);
        }
    }
}
