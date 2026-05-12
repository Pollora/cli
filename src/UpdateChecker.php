<?php

declare(strict_types=1);

namespace Pollora\Cli;

use GuzzleHttp\Client;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Checks for new CLI versions and displays a notification.
 *
 * Uses a local cache file to avoid hitting the Packagist API on every run.
 * Checks at most once every 24 hours.
 */
final class UpdateChecker
{
    private const PACKAGIST_ENDPOINT = 'https://repo.packagist.org/p2/pollora/cli.json';

    private const CHECK_INTERVAL = 86400; // 24 hours

    public static function notify(OutputInterface $output): void
    {
        $latestVersion = self::getLatestVersionCached();

        if ($latestVersion === null) {
            return;
        }

        $currentVersion = ltrim(Version::get(), 'v');
        $latest = ltrim($latestVersion, 'v');

        // Can't compare non-semver (dev-main, UNKNOWN, etc.)
        if (! preg_match('/^\d+\.\d+/', $currentVersion)) {
            return;
        }

        if (version_compare($currentVersion, $latest, '>=')) {
            return;
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '  <comment>A new version of Pollora CLI is available: <info>%s</info> (current: %s)</comment>',
            $latestVersion,
            Version::get(),
        ));
        $output->writeln('  <comment>Run <info>pollora self-update</info> to update.</comment>');
        $output->writeln('');
    }

    private static function getLatestVersionCached(): ?string
    {
        $cacheFile = self::getCacheFile();

        if (is_file($cacheFile)) {
            /** @var array{version?: string, checked_at?: int}|null $cache */
            $cache = json_decode((string) file_get_contents($cacheFile), true);

            if (is_array($cache) && isset($cache['checked_at'], $cache['version']) && time() - $cache['checked_at'] < self::CHECK_INTERVAL) {
                return $cache['version'];
            }
        }

        $version = self::fetchLatestVersion();

        if ($version !== null) {
            $dir = dirname($cacheFile);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($cacheFile, json_encode([
                'version' => $version,
                'checked_at' => time(),
            ]));
        }

        return $version;
    }

    private static function fetchLatestVersion(): ?string
    {
        try {
            $client = new Client;
            $response = $client->get(self::PACKAGIST_ENDPOINT, [
                'timeout' => 3,
                'connect_timeout' => 2,
            ]);

            /** @var array<string, mixed>|null $data */
            $data = json_decode((string) $response->getBody(), true);

            if (! is_array($data) || ! isset($data['packages']['pollora/cli'])) {
                return null;
            }

            // Packagist returns versions sorted by newest first
            // Find the latest stable (non-dev) version
            foreach ($data['packages']['pollora/cli'] as $release) {
                $version = $release['version'];
                // Skip dev versions
                if (str_starts_with((string) $version, 'dev-')) {
                    continue;
                }
                if (str_contains((string) $version, '-dev')) {
                    continue;
                }

                return $version;
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function getCacheFile(): string
    {
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? sys_get_temp_dir();

        return $home.'/.pollora/update-check.json';
    }
}
