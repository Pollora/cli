<?php

declare(strict_types=1);

namespace Pollora\Cli\Commands;

use GuzzleHttp\Client;
use Pollora\Cli\Version;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class SelfUpdateCommand extends Command
{
    private const PACKAGIST_ENDPOINT = 'https://repo.packagist.org/p2/pollora/cli.json';

    protected function configure(): void
    {
        $this
            ->setName('self-update')
            ->setAliases(['self:update'])
            ->setDescription('Update the Pollora CLI to the latest version');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $currentVersion = Version::get();
        $output->writeln(sprintf('<info>Current version:</info> %s', $currentVersion));

        $latestVersion = $this->getLatestVersion();

        if ($latestVersion !== null && $this->isUpToDate($currentVersion, $latestVersion)) {
            $output->writeln(sprintf('<info>You are already using the latest version (%s).</info>', $latestVersion));

            return Command::SUCCESS;
        }

        if ($latestVersion !== null) {
            $output->writeln(sprintf('<comment>Updating to %s...</comment>', $latestVersion));
        } else {
            $output->writeln('<comment>Checking for updates...</comment>');
        }

        $output->writeln('');

        $process = new Process(['composer', 'global', 'update', 'pollora/cli', '--no-interaction']);
        $process->setTimeout(300);

        $process->run(static function (string $type, string $line) use ($output): void {
            $output->write('  '.$line);
        });

        if (! $process->isSuccessful()) {
            $output->writeln('');
            $output->writeln('<error>Failed to update Pollora CLI.</error>');
            $output->writeln('  You can try manually: <comment>composer global update pollora/cli</comment>');

            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>Pollora CLI updated successfully!</info>');

        return Command::SUCCESS;
    }

    private function isUpToDate(string $current, string $latest): bool
    {
        $current = ltrim($current, 'v');
        $latest = ltrim($latest, 'v');

        if (! preg_match('/^\d+\.\d+/', $current)) {
            return false;
        }

        return version_compare($current, $latest, '>=');
    }

    private function getLatestVersion(): ?string
    {
        try {
            $client = new Client;
            $response = $client->get(self::PACKAGIST_ENDPOINT, [
                'timeout' => 5,
            ]);

            /** @var array<string, mixed>|null $data */
            $data = json_decode((string) $response->getBody(), true);

            if (! is_array($data) || ! isset($data['packages']['pollora/cli'])) {
                return null;
            }

            foreach ($data['packages']['pollora/cli'] as $release) {
                $version = $release['version'];
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
}
