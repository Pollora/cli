<?php

declare(strict_types=1);

namespace Pollora\Cli\Tests\Support;

/**
 * A throw-away working area for tests that run external programs.
 *
 * It owns a temporary directory holding a "work" folder (the current directory
 * during the test) and a "bin" folder of fake executables placed first in PATH,
 * so no real composer, ddev, git, php or network call is ever made.
 */
final class Sandbox
{
    public readonly string $root;

    public readonly string $work;

    public readonly string $bin;

    public readonly string $log;

    private readonly string|false $originalCwd;

    /** @var array<string, string|false> */
    private array $originalEnv = [];

    /** @var array<string, mixed> */
    private array $originalServer = [];

    public function __construct()
    {
        $this->root = realpath(sys_get_temp_dir()).'/pollora-cli-'.bin2hex(random_bytes(6));
        $this->work = $this->root.'/work';
        $this->bin = $this->root.'/bin';
        $this->log = $this->root.'/calls.log';

        mkdir($this->work, 0755, true);
        mkdir($this->bin, 0755, true);
        touch($this->log);

        $this->originalCwd = getcwd();
        $this->env('FAKE_LOG', $this->log);
        $this->env('FAKE_FAIL_ON', '');
        $this->env('FAKE_NO_INSTALL', '');
        $this->env('HTTPS_PROXY', 'http://127.0.0.1:1');
        $this->env('HOME', $this->root.'/home');
        $this->env('PATH', $this->bin.':'.getenv('PATH'));

        chdir($this->work);
    }

    public function env(string $name, string $value): void
    {
        if (! array_key_exists($name, $this->originalEnv)) {
            $this->originalEnv[$name] = getenv($name);
        }

        if (! array_key_exists($name, $this->originalServer)) {
            $this->originalServer[$name] = $_SERVER[$name] ?? null;
        }

        // Symfony Process only passes on the variables that $_SERVER also knows.
        putenv($name.'='.$value);
        $_SERVER[$name] = $value;
    }

    /**
     * Restrict PATH to the fake bin folder plus the system essentials.
     */
    public function onlyFakePath(): void
    {
        $this->env('PATH', $this->bin.':/usr/bin:/bin');
    }

    public function script(string $name, string $body): void
    {
        $path = $this->bin.'/'.$name;
        file_put_contents($path, "#!/bin/sh\n".$body."\n");
        chmod($path, 0755);
    }

    /**
     * Fake `composer`, `ddev`, `php` and `git` that record their arguments.
     */
    public function fakeTools(): void
    {
        $skeleton = <<<'SH'
mkdir -p "$1/vendor"
touch "$1/artisan"
echo '{}' > "$1/composer.json"
[ -n "$FAKE_NO_ENV" ] || printf 'APP_KEY=\n#DB_HOST=127.0.0.1\n# DB_PASSWORD=\nAPP_URL=http://localhost\n' > "$1/.env.example"
SH;

        $guard = <<<'SH'
echo "$(basename "$0") $*" >> "$FAKE_LOG"
if [ -n "$FAKE_FAIL_ON" ]; then
  case "$*" in *"$FAKE_FAIL_ON"*) exit 1;; esac
fi
SH;

        $this->script('composer', $guard."\n".'if [ "$1" = create-project ] && [ -z "$FAKE_NO_INSTALL" ]; then set -- "$3"; '.$skeleton."\nfi\nexit 0");
        $this->script('ddev', $guard."\n".'if [ "$1" = composer ] && [ "$2" = create-project ] && [ -z "$FAKE_NO_INSTALL" ]; then set -- "."; '.$skeleton."\nfi\nexit 0");
        $this->script('php', $guard."\nexit 0");
        $this->script('git', $guard."\nexit 0");
    }

    public function calls(): string
    {
        return (string) file_get_contents($this->log);
    }

    public function restore(): void
    {
        if ($this->originalCwd !== false) {
            chdir($this->originalCwd);
        }

        foreach ($this->originalEnv as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }

        foreach ($this->originalServer as $name => $value) {
            if ($value === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $value;
            }
        }

        self::removeTree($this->root);
    }

    public static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::removeTree($path.'/'.$entry);
            }
        }

        rmdir($path);
    }
}
