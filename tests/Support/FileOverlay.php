<?php

declare(strict_types=1);

namespace Pollora\Cli\Tests\Support;

/**
 * Lets a test decide what a few well-known files look like to the code under
 * test, without touching the real file system.
 *
 * It replaces the "file" stream wrapper for the duration of a test: paths
 * ending with a registered suffix are served from memory (or hidden, or made
 * unreadable); every other path falls through to the native wrapper.
 * Always call uninstall() when the test is over.
 */
final class FileOverlay
{
    /** @var resource|null */
    public $context;

    /** @var array<string, string|false|null|\Closure> suffix => content, false = unreadable, null = absent, closure = content computed at each open */
    private static array $files = [];

    /** @var resource|null */
    private $handle;

    private string $data = '';

    private int $position = 0;

    private bool $virtual = false;

    /**
     * @param  array<string, string|false|null|\Closure>  $files  suffix => content; false = exists but cannot be read, null = does not exist, closure = returns the content (or false) at each open
     */
    public static function install(array $files): void
    {
        self::loadSources();
        self::$files = $files;
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', self::class);
    }

    /**
     * Load every class of the package while the real "file" wrapper is still
     * in place: a class first loaded through this overlay (or after it is
     * removed) would bypass any wrapper that rewrites the source, such as the
     * one of the mutation runner, and the tests would no longer see its changes.
     */
    private static function loadSources(): void
    {
        $src = dirname(__DIR__, 2).'/src';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $class = 'Pollora\\Cli\\'.str_replace('/', '\\', substr((string) $file, strlen($src) + 1, -4));
            class_exists($class);
        }
    }

    public static function uninstall(): void
    {
        self::$files = [];
        // Safe to call twice: the native wrapper is simply restored.
        @stream_wrapper_unregister('file');
        stream_wrapper_restore('file');
    }

    /**
     * @return array{0: bool, 1: string|false|null|\Closure}
     */
    private function match(string $path): array
    {
        foreach (self::$files as $suffix => $content) {
            if (str_ends_with($path, $suffix)) {
                return [true, $content];
            }
        }

        return [false, null];
    }

    private function native(callable $call): mixed
    {
        stream_wrapper_restore('file');
        // A missing file is an ordinary answer here, not a warning.
        set_error_handler(static fn (): bool => true);

        try {
            return $call();
        } finally {
            restore_error_handler();
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }
    }

    public function stream_open(string $path, string $mode): bool
    {
        [$matched, $content] = $this->match($path);

        if ($matched) {
            if ($content instanceof \Closure) {
                $content = $content();
            }

            if (! is_string($content)) {
                return false;
            }

            $this->virtual = true;
            $this->data = $content;

            return true;
        }

        $handle = $this->native(static fn (): mixed => fopen($path, $mode));

        if (! is_resource($handle)) {
            return false;
        }

        $this->handle = $handle;

        return true;
    }

    public function stream_read(int $count): string|false
    {
        if ($this->virtual) {
            $chunk = substr($this->data, $this->position, $count);
            $this->position += strlen($chunk);

            return $chunk;
        }

        return fread($this->handle, $count); // @phpstan-ignore argument.type
    }

    public function stream_write(string $data): int
    {
        return $this->virtual ? 0 : (int) fwrite($this->handle, $data); // @phpstan-ignore argument.type
    }

    public function stream_eof(): bool
    {
        return $this->virtual ? $this->position >= strlen($this->data) : feof($this->handle); // @phpstan-ignore argument.type
    }

    public function stream_close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * @return array<string, int>|false
     */
    public function stream_stat(): array|false
    {
        if ($this->virtual) {
            return ['mode' => 0100644, 'size' => strlen($this->data)];
        }

        return fstat($this->handle); // @phpstan-ignore argument.type
    }

    public function stream_set_option(): bool
    {
        return false;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return (bool) $this->native(static fn (): bool => mkdir($path, $mode, (bool) ($options & STREAM_MKDIR_RECURSIVE)));
    }

    /**
     * @return array<string, int>|false
     */
    public function url_stat(string $path): array|false
    {
        [$matched, $content] = $this->match($path);

        if ($matched) {
            if ($content === null) {
                return false;
            }

            return ['mode' => 0100644, 'size' => is_string($content) ? strlen($content) : 0];
        }

        return $this->native(static fn (): array|false => stat($path));
    }
}
