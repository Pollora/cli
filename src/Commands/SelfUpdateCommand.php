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
    private const GITHUB_LATEST_RELEASE_ENDPOINT = 'https://api.github.com/repos/Pollora/cli/releases/latest';

    protected function configure(): void
    {
        $this
            ->setName('self-update')
            ->setDescription('Update the Pollora CLI to the latest version');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $currentVersion = Version::get();
        $output->writeln('<info>Current version:</info> '.$currentVersion);

        $latestVersion = $this->getLatestVersion();

        if ($latestVersion !== null && version_compare($currentVersion, $latestVersion, '>=')) {
            $output->writeln('<info>You are already using the latest version.</info>');

            return Command::SUCCESS;
        }

        if ($latestVersion !== null) {
            $output->writeln(sprintf('<comment>Updating to %s...</comment>', $latestVersion));
        } else {
            $output->writeln('<comment>Updating to latest version...</comment>');
        }

        $process = new Process(['composer', 'global', 'update', 'pollora/cli']);
        $process->setTimeout(300);

        $process->run(static function (string $type, string $line) use ($output): void {
            $output->write($line);
        });

        if (! $process->isSuccessful()) {
            $output->writeln('<error>Failed to update Pollora CLI.</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Pollora CLI updated successfully!</info>');

        return Command::SUCCESS;
    }

    private function getLatestVersion(): ?string
    {
        try {
            $client = new Client;
            $response = $client->get(self::GITHUB_LATEST_RELEASE_ENDPOINT, [
                'timeout' => 5,
                'headers' => [
                    'Accept' => 'application/vnd.github.v3+json',
                ],
            ]);

            /** @var array{tag_name?: string}|null $data */
            $data = json_decode((string) $response->getBody(), true);

            return $data['tag_name'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
