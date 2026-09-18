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

    private string $projectVersion = '';

    private bool $preferStable = false;

    protected function configure(): void
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Pollora application')
            ->addArgument('name', InputArgument::OPTIONAL, 'Application directory name')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force install even if the directory already exists')
            ->addOption('git', null, InputOption::VALUE_NONE, 'Initialize a Git repository')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'The branch that should be created for a new repository', 'main')
            ->addOption('ddev', null, InputOption::VALUE_NONE, 'Set up the project with DDEV')
            ->addOption('ver', null, InputOption::VALUE_REQUIRED, 'Install a specific Pollora version or constraint (e.g. 13.32.0-beta.2)')
            ->addOption('stable', null, InputOption::VALUE_NONE, 'Install the latest stable release instead of the latest pre-release');
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
        $this->projectVersion = (string) ($this->input->getOption('ver') ?? '');
        $this->preferStable = (bool) $this->input->getOption('stable');

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
        $commands[] = $composer.' create-project '.self::BASE_REPO.$this->getVersionConstraint().sprintf(' "%s" ', $directory).$this->createProjectFlags();

        $this->runCommands($commands);

        if (! $this->wasInstallSuccessful()) {
            throw new RuntimeException('There was a problem installing Pollora!');
        }

        // The skeleton's post-install scripts were skipped, so the .env file
        // and the application key have to be created here.
        $this->prepareEnvironmentFile();

        $this->runArtisan('php', 'pollora:env:setup', 'Running pollora:env:setup...');
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
        // (scripts would trigger pollora:env:setup which needs DB credentials)
        $this->output->writeln('');
        $this->output->writeln('  <info>Installing Pollora via Composer...</info>');
        $this->runCommands([
            'ddev composer create-project '.self::BASE_REPO.$this->getVersionConstraint().' --no-interaction '.$this->createProjectFlags(),
        ], workingPath: $this->absolutePath);

        if (! $this->wasInstallSuccessful()) {
            throw new RuntimeException('There was a problem installing Pollora via DDEV!');
        }

        // Configure .env with DDEV database credentials before running install
        $this->configureDdevEnv();

        // Update framework to latest patch and run env-setup
        $this->output->writeln('');
        $this->output->writeln('  <info>Finalizing installation...</info>');
        $this->runCommands([
            'ddev composer update pollora/framework --no-scripts',
            'ddev exec php artisan pollora:env-setup --install',
        ], workingPath: $this->absolutePath);

        // Run pollora:install interactively inside DDEV
        $this->runArtisanInstall('ddev exec php');

        // Clear stale WordPress theme_roots transient so WP picks up
        // the correct themes/ directory on next request
        $this->runCommands([
            "ddev exec mysql -u db -pdb db -e \"DELETE FROM pf_options WHERE option_name LIKE '%theme_roots%';\"",
        ], workingPath: $this->absolutePath, disableOutput: true);

        return $this;
    }

    private function configureDdevEnv(): void
    {
        $this->output->writeln('');
        $this->output->writeln('  <info>Configuring environment for DDEV...</info>');

        $envFile = $this->prepareEnvironmentFile();

        if ($envFile === null) {
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
    }

    /**
     * Create the .env file and its application key when they are missing.
     *
     * The key is generated here instead of with artisan key:generate, which
     * would boot the framework and fail while WordPress is not installed yet.
     *
     * @return string|null The path to the .env file, or null when the skeleton
     *                     ships no .env.example to copy.
     */
    private function prepareEnvironmentFile(): ?string
    {
        $envFile = $this->absolutePath.'/.env';

        if (! is_file($envFile)) {
            $exampleFile = $this->absolutePath.'/.env.example';

            if (is_file($exampleFile)) {
                copy($exampleFile, $envFile);
            }
        }

        if (! is_file($envFile)) {
            return null;
        }

        $env = file_get_contents($envFile);

        if ($env === false) {
            return null;
        }

        if (preg_match('/^APP_KEY=.+$/m', $env) !== 1) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $env = preg_replace('/^APP_KEY=.*/m', 'APP_KEY='.$key, $env) ?? $env;
            file_put_contents($envFile, $env);
        }

        return $envFile;
    }

    // ──────────────────────────────────────────────
    // Shared helpers
    // ──────────────────────────────────────────────

    private function runArtisanInstall(string $phpPrefix): self
    {
        return $this->runArtisan($phpPrefix, 'pollora:install', 'Running pollora:install...');
    }

    /**
     * Run an artisan command with a terminal attached, so that its prompts work.
     */
    private function runArtisan(string $phpPrefix, string $artisanCommand, string $message): self
    {
        $this->output->writeln('');
        $this->output->writeln('  <info>'.$message.'</info>');
        $this->output->writeln('');

        $isDdev = str_starts_with($phpPrefix, 'ddev');

        $command = ($isDdev ? 'ddev exec php' : $phpPrefix).' artisan '.$artisanCommand;

        $process = Process::fromShellCommandline($command, $this->absolutePath);
        $process->setTimeout(null);

        // Set TERM=dumb on the HOST side to prevent the terminal emulator
        // from responding to OSC queries when a new PTY is allocated
        $env = getenv();
        $env['TERM'] = 'dumb';
        unset($env['COLORTERM']);
        $process->setEnv($env);

        try {
            $process->setTty(Process::isTtySupported());
        } catch (RuntimeException) {
            // TTY not supported
        }

        $process->run();

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

    /**
     * Get the Composer version constraint for create-project.
     * Returns empty string for latest, or ':v13.32.0-beta.2' format for specific version.
     */
    private function getVersionConstraint(): string
    {
        if ($this->projectVersion === '') {
            return '';
        }

        $version = $this->projectVersion;

        // Exact versions are normalized to the "vX.Y.Z" tag format, anything
        // else (^13.32@beta, dev-main, ...) is handed to Composer untouched.
        if (preg_match('/^v?\d+\.\d+\.\d+/', $version) === 1) {
            $version = 'v'.ltrim($version, 'v');
        }

        return ':'.$version;
    }

    /**
     * Composer flags shared by both create-project invocations.
     *
     * --no-scripts keeps the skeleton's post-install hooks from running
     * pollora:env:setup and pollora:install on their own: driven from here
     * they get no terminal, their prompts fail, and Composer aborts the
     * install. The CLI runs them itself once the project is in place.
     */
    private function createProjectFlags(): string
    {
        return '--remove-vcs --prefer-dist --no-scripts'.$this->getStabilityOption();
    }

    /**
     * Get the Composer stability option for create-project.
     *
     * Pollora currently ships pre-releases, so they are installed by default.
     * --stable restricts the install to the latest stable release, and an
     * explicit --ver carries its own stability.
     */
    private function getStabilityOption(): string
    {
        if ($this->preferStable || $this->projectVersion !== '') {
            return '';
        }

        return ' --stability=beta';
    }

    private function isDdevInstalled(): bool
    {
        $process = new Process(['ddev', 'version']);
        $process->run();

        return $process->isSuccessful();
    }
}
