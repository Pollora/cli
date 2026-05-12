<?php

declare(strict_types=1);

namespace Pollora\Cli;

final class Version
{
    public static function get(): string
    {
        $composerJsonPath = __DIR__.'/../composer.json';

        if (! is_file($composerJsonPath)) {
            return 'UNKNOWN';
        }

        $contents = file_get_contents($composerJsonPath);

        if ($contents === false) {
            return 'UNKNOWN';
        }

        /** @var array{version?: string}|null $data */
        $data = json_decode($contents, true);

        if (! is_array($data)) {
            return 'UNKNOWN';
        }

        // Try reading from composer.lock when installed as a dependency
        $lockPath = __DIR__.'/../../../../composer.lock';

        if (is_file($lockPath)) {
            $lockContents = file_get_contents($lockPath);
        }

        if (isset($lockContents) && $lockContents !== false) {
            /** @var array{packages?: list<array{name: string, version: string}>}|null $lock */
            $lock = json_decode($lockContents, true);

            if (is_array($lock) && isset($lock['packages'])) {
                foreach ($lock['packages'] as $package) {
                    if ($package['name'] === 'pollora/cli') {
                        return $package['version'];
                    }
                }
            }
        }

        return $data['version'] ?? 'dev-main';
    }
}
