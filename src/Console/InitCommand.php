<?php

namespace Realodix\Haiku\Console;

use Realodix\Haiku\Config\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'init',
    description: 'Create a new haiku.yml file',
)]
class InitCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filename = Config::DEFAULT_FILENAME;

        $configFile = base_path($filename);

        if (file_exists($configFile)) {
            if (!$io->confirm("The {$filename} file already exists. Do you want to overwrite it?", false)) {
                $io->writeln('Aborted.');

                return Command::FAILURE;
            }
        }

        $this->createConfigFile($configFile);

        $io->success("{$filename} created successfully.");

        return Command::SUCCESS;
    }

    private function createConfigFile(string $configFile): void
    {
        $content = <<<'YML'
# cache_dir: .tmp

# Settings for the `fix` command
fixer:
  paths:
    - folder_1/file.txt
    - folder_2
  excludes:
    - excluded_file.txt
    - some/path/to/file.txt
    - path/to/source
  # backup: true
  # flags:
  #  keep_empty_lines: true
  #  xmode: true

# Settings for the `build` command
builder:
  output_dir: dist
  filter_list:
    - filename: general_blocklist.txt # Required
      remove_duplicates: true
      header: |
        [Adblock Plus 2.0]
        ! Title: Ad Blocklist
        ! Description: Filter list that specifically removes adverts.
        ! Last modified: %timestamp%
        ! Expires: 5 days (update frequency)
        ! Homepage: https://example.org/
        ! License: MIT
        ! --------------------------------------------------
      source: # Required
        - blocklists/general/local-rules.txt
        - https://cdn.example.org/blocklists/general.txt
YML;

        file_put_contents($configFile, $content);
    }
}
