<?php

declare(strict_types=1);

namespace Pollora\Cli\Commands;

use Pollora\Cli\Concerns\ConfiguresPrompts;
use Pollora\Cli\Concerns\RunsCommands;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

final class NewCommand extends Command
{
    use ConfiguresPrompts;
    use RunsCommands;

    private const BASE_REPO = 'pollora/pollora';

    protected InputInterface $input;

    protected OutputInterface $output;

    private string $relativePath = '';

    private string $absolutePath = '';

    private bool $force = false;

    private bool $initGit = false;

    private bool $useDdev = false;

    protected function configure(): void
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Pollora application')
            ->addArgument('name', InputArgument::OPTIONAL, 'Application directory name')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force install even if the directory already exists')
            ->addOption('git', null, InputOption::VALUE_NONE, 'Initialize a Git repository')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'The branch that should be created for a new repository', 'main')
            ->addOption('ddev', null, InputOption::VALUE_NONE, 'Set up the project with DDEV');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;

        $this->configurePrompts($input, $output);
        $this->showTitleArt();
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if ($input->getArgument('name') === null) {
            $input->setArgument('name', text(
                label: 'What is the name of your project?',
                placeholder: 'E.g. my-pollora-site',
                required: 'The project name is required.',
                validate: static fn (string $value): ?string => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
                    ? 'The name may only contain letters, numbers, dashes, underscores, and periods.'
                    : null,
            ));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this
                ->processArguments()
                ->validateArguments()
                ->askForDdev()
                ->install()
                ->initializeGitRepository()
                ->showSuccessMessage();
        } catch (RuntimeException $runtimeException) {
            $this->showError($runtimeException->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function processArguments(): self
    {
        /** @var string $name */
        $name = $this->input->getArgument('name');
        $this->relativePath = $name;

        $cwd = (string) getcwd();
        $this->absolutePath = $this->relativePath !== '.'
            ? $cwd.'/'.$this->relativePath
            : $cwd;

        $this->force = (bool) $this->input->getOption('force');
        $this->initGit = (bool) $this->input->getOption('git');
        $this->useDdev = (bool) $this->input->getOption('ddev');

        return $this;
    }

    private function validateArguments(): self
    {
        if (! $this->force && $this->applicationExists()) {
            throw new RuntimeException('Application already exists!');
        }

        if ($this->force && $this->pathIsCwd()) {
            throw new RuntimeException('Cannot use --force option when using current directory for installation!');
        }

        return $this;
    }

    private function askForDdev(): self
    {
        if ($this->useDdev || ! $this->input->isInteractive()) {
            return $this;
        }

        if ($this->isDdevInstalled()) {
            $this->useDdev = confirm(
                label: 'Set up the project with DDEV?',
                default: true,
            );
        }

        return $this;
    }

    private function install(): self
    {
        if ($this->useDdev) {
            return $this->installWithDdev();
        }

        return $this->installWithComposer();
    }

    // ──────────────────────────────────────────────
    // Standard install (without DDEV)
    // ──────────────────────────────────────────────

    private function installWithComposer(): self
    {
        $commands = [];

        if ($this->force && ! $this->pathIsCwd()) {
            $commands[] = PHP_OS_FAMILY === 'Windows'
                ? sprintf('rd /s /q "%s"', $this->absolutePath)
                : sprintf('rm -rf "%s"', $this->absolutePath);
        }

        $composer = $this->findComposer();
        $directory = $this->pathIsCwd() ? '.' : $this->relativePath;
        $commands[] = $composer.' create-project '.self::BASE_REPO.sprintf(' "%s" --remove-vcs --prefer-dist', $directory);

        $this->runCommands($commands);

        if (! $this->wasInstallSuccessful()) {
            throw new RuntimeException('There was a problem installing Pollora!');
        }

        $this->runArtisanInstall('php');

        return $this;
    }

    // ──────────────────────────────────────────────
    // DDEV install
    // ──────────────────────────────────────────────

    private function installWithDdev(): self
    {
        if (! $this->isDdevInstalled()) {
            throw new RuntimeException('DDEV is not installed. Please install it from https://ddev.readthedocs.io');
        }

        $this->output->writeln('');
        $this->output->writeln('  <info>Setting up DDEV environment...</info>');

        // Create project directory
        if (! is_dir($this->absolutePath)) {
            mkdir($this->absolutePath, 0755, true);
        }

        // Configure DDEV
        $this->runCommands([
            sprintf(
                'ddev config --project-name=%s --project-type=wordpress --docroot=public --php-version=8.4 --database=mariadb:10.11 --disable-settings-management',
                escapeshellarg($this->relativePath)
            ),
        ], workingPath: $this->absolutePath);

        // Start DDEV
        $this->output->writeln('');
        $this->output->writeln('  <info>Starting DDEV...</info>');
        $this->runCommands(['ddev start'], workingPath: $this->absolutePath);

        // Install project files without running post-install scripts
        // (scripts would trigger pollora:env-setup which needs DB credentials)
        $this->output->writeln('');
        $this->output->writeln('  <info>Installing Pollora via Composer...</info>');
        $this->runCommands([
            'ddev composer create-project '.self::BASE_REPO.' --remove-vcs --prefer-dist --no-interaction --no-scripts',
        ], workingPath: $this->absolutePath);

        if (! $this->wasInstallSuccessful()) {
            throw new RuntimeException('There was a problem installing Pollora via DDEV!');
        }

        // Configure .env with DDEV database credentials before running install
        $this->configureDdevEnv();

        // Regenerate autoload without triggering artisan scripts
        // (scripts run pollora:install non-interactively which fails on prompts)
        $this->output->writeln('');
        $this->output->writeln('  <info>Finalizing installation...</info>');
        $this->runCommands([
            'ddev composer dump-autoload --no-scripts',
        ], workingPath: $this->absolutePath);

        // Run pollora:install interactively inside DDEV
        $this->runArtisanInstall('ddev exec php');

        return $this;
    }

    private function configureDdevEnv(): void
    {
        $this->output->writeln('');
        $this->output->writeln('  <info>Configuring environment for DDEV...</info>');

        $envFile = $this->absolutePath.'/.env';

        if (! is_file($envFile)) {
            $exampleFile = $this->absolutePath.'/.env.example';
            if (is_file($exampleFile)) {
                copy($exampleFile, $envFile);
            }
        }

        if (! is_file($envFile)) {
            return;
        }

        $env = file_get_contents($envFile);

        if ($env === false) {
            return;
        }

        $siteUrl = 'https://'.$this->relativePath.'.ddev.site';

        // Set DDEV database credentials and app URL
        $replacements = [
            '/^#?\s*DB_CONNECTION=.*/m' => 'DB_CONNECTION=mysql',
            '/^#?\s*DB_HOST=.*/m' => 'DB_HOST=db',
            '/^#?\s*DB_PORT=.*/m' => 'DB_PORT=3306',
            '/^#?\s*DB_DATABASE=.*/m' => 'DB_DATABASE=db',
            '/^#?\s*DB_USERNAME=.*/m' => 'DB_USERNAME=db',
            '/^#?\s*DB_PASSWORD=.*/m' => 'DB_PASSWORD=db',
            '/^APP_URL=.*/m' => 'APP_URL='.$siteUrl,
        ];

        foreach ($replacements as $pattern => $replacement) {
            $env = preg_replace($pattern, $replacement, $env) ?? $env;
        }

        file_put_contents($envFile, $env);

        // Generate application key
        $this->runCommands([
            'ddev exec php artisan key:generate --no-interaction',
        ], workingPath: $this->absolutePath);
    }

    // ──────────────────────────────────────────────
    // Shared helpers
    // ──────────────────────────────────────────────

    private function runArtisanInstall(string $phpPrefix): self
    {
        $this->output->writeln('');
        $this->output->writeln('  <info>Running pollora:install...</info>');
        $this->output->writeln('');

        $isDdev = str_starts_with($phpPrefix, 'ddev');

        $command = $isDdev
            ? 'ddev exec TERM=dumb php artisan pollora:install'
            : $phpPrefix.' artisan pollora:install';

        $process = Process::fromShellCommandline($command, $this->absolutePath);
        $process->setTimeout(null);

        try {
            $process->setTty(Process::isTtySupported());
        } catch (RuntimeException) {
            // TTY not supported
        }

        $process->run(function (string $type, string $line): void {
            $this->output->write('    '.$line);
        });

        return $this;
    }

    private function initializeGitRepository(): self
    {
        if (! $this->initGit && $this->input->isInteractive()) {
            $this->initGit = confirm(
                label: 'Initialize a Git repository?',
                default: false,
            );
        }

        if (! $this->initGit || ! $this->isGitInstalled()) {
            return $this;
        }

        /** @var string $branch */
        $branch = $this->input->getOption('branch');

        $commands = [
            'git init -q',
            'git add .',
            'git commit -q -m "Initial Pollora project"',
            'git branch -M '.$branch,
        ];

        $this->runCommands($commands, workingPath: $this->absolutePath);

        return $this;
    }

    private function showSuccessMessage(): self
    {
        $this->output->writeln('');
        $this->output->writeln('  <info>[OK] Pollora was installed successfully!</info>');
        $this->output->writeln('');

        if ($this->useDdev) {
            $this->output->writeln(sprintf('  Enter your project directory with <comment>cd %s</comment>', $this->relativePath));
            $this->output->writeln('  Your site is available at <info>https://'.$this->relativePath.'.ddev.site</info>');
            $this->output->writeln('');
            $this->output->writeln('  Use <comment>ddev pollora</comment> to run Pollora commands');
            $this->output->writeln('  Use <comment>ddev launch</comment> to open your site in a browser');
        } else {
            $this->output->writeln(sprintf('  Enter your project directory with <comment>cd %s</comment>', $this->relativePath));
        }

        $this->output->writeln('  Documentation: <info>https://pollora.dev</info>');
        $this->output->writeln('');

        return $this;
    }

    private function showTitleArt(): void
    {
        $this->output->writeln('');
        $this->output->writeln('<fg=yellow>  ██████╗  ██████╗ ██╗     ██╗      ██████╗ ██████╗  █████╗</>');
        $this->output->writeln('<fg=yellow>  ██╔══██╗██╔═══██╗██║     ██║     ██╔═══██╗██╔══██╗██╔══██╗</>');
        $this->output->writeln('<fg=yellow>  ██████╔╝██║   ██║██║     ██║     ██║   ██║██████╔╝███████║</>');
        $this->output->writeln('<fg=yellow>  ██╔═══╝ ██║   ██║██║     ██║     ██║   ██║██╔══██╗██╔══██║</>');
        $this->output->writeln('<fg=yellow>  ██║     ╚██████╔╝███████╗███████╗╚██████╔╝██║  ██║██║  ██║</>');
        $this->output->writeln('<fg=yellow>  ╚═╝      ╚═════╝ ╚══════╝╚══════╝ ╚═════╝ ╚═╝  ╚═╝╚═╝  ╚═╝</>');
        $this->output->writeln('');
    }

    private function showError(string $message): void
    {
        $padding = str_repeat(' ', mb_strlen($message));

        $this->output->writeln('');
        $this->output->writeln(sprintf('  <bg=red>  %s  </>', $padding));
        $this->output->writeln(sprintf('  <bg=red>  %s  </>', $message));
        $this->output->writeln(sprintf('  <bg=red>  %s  </>', $padding));
        $this->output->writeln('');
    }

    private function applicationExists(): bool
    {
        if ($this->pathIsCwd()) {
            return is_file($this->absolutePath.'/composer.json');
        }

        return is_dir($this->absolutePath) || is_file($this->absolutePath);
    }

    private function pathIsCwd(): bool
    {
        return $this->absolutePath === (string) getcwd();
    }

    private function wasInstallSuccessful(): bool
    {
        return is_file($this->absolutePath.'/composer.json')
            && is_dir($this->absolutePath.'/vendor')
            && is_file($this->absolutePath.'/artisan');
    }

    private function findComposer(): string
    {
        $cwd = (string) getcwd();
        $composerPath = $cwd.'/composer.phar';

        if (file_exists($composerPath)) {
            return '"'.PHP_BINARY.'" '.$composerPath;
        }

        return 'composer';
    }

    private function isGitInstalled(): bool
    {
        $process = new Process(['git', '--version']);
        $process->run();

        return $process->isSuccessful();
    }

    private function isDdevInstalled(): bool
    {
        $process = new Process(['ddev', 'version']);
        $process->run();

        return $process->isSuccessful();
    }
}
