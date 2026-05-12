<?php

declare(strict_types=1);

namespace Pollora\Cli;

use Pollora\Cli\Commands\NewCommand;
use Pollora\Cli\Commands\SelfUpdateCommand;
use Pollora\Cli\Commands\VersionCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
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
        $proxy = new ProxyCommand($output);

        if ($proxy->shouldProxy($input)) {
            return $proxy->execute($input);
        }

        $exitCode = parent::doRun($input, $output);

        // Show update notification after command execution
        UpdateChecker::notify($output);

        return $exitCode;
    }
}
