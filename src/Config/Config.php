<?php

namespace Realodix\Haiku\Config;

use Illuminate\Support\Arr;
use Nette\Schema\Processor;
use Realodix\Haiku\Enums\Section;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

final class Config
{
    const DEFAULT_FILENAME = 'haiku.yml';

    public ?string $cacheDir;

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private BuilderConfig $builder,
        private FixerConfig $fixer,
        private Processor $schemaProcessor,
        private OutputInterface $output,
    ) {}

    /**
     * Loads and returns the Builder configuration.
     *
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt
     */
    public function builder($cmdOpt): BuilderConfig
    {
        $this->load(Section::B, $cmdOpt->configFile);

        if (!isset($this->config['builder'])) {
            throw new InvalidConfigurationException('The "builder" configuration is missing.');
        }

        return $this->builder->make($this->config['builder']);
    }

    /**
     * Loads and returns the Fixer configuration.
     *
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt
     */
    public function fixer($cmdOpt): FixerConfig
    {
        $this->load(Section::F, $cmdOpt->configFile);

        return $this->fixer->make(
            $this->config['fixer'] ?? [],
            [
                'path' => $cmdOpt->path,
            ],
        );
    }

    /**
     * @param string|null $cachePath Custom cache path from command options
     */
    public function getCachePath(?string $cachePath = null): ?string
    {
        return $cachePath ?? $this->cacheDir;
    }

    /**
     * Loads the YAML configuration file and parses its content.
     *
     * @param Section $section The context/section being loaded
     * @param string|null $path Custom path to the configuration file
     *
     * @throws InvalidConfigurationException If the file is mandatory but not found
     */
    private function load(Section $section, ?string $path): self
    {
        try {
            $config = Yaml::parseFile($this->resolvePath($path));
            $this->validate($config, $section);
        } catch (\Symfony\Component\Yaml\Exception\ParseException) {
            $config = [];

            if ($section === Section::B) {
                throw new InvalidConfigurationException('The configuration file does not exist.');
            }
        }

        $this->config = $config;
        $this->cacheDir = $config['cache_dir'] ?? null;

        return $this;
    }

    /**
     * Returns the absolute path to a configuration file.
     *
     * If no configuration file is specified, it defaults to the path of the
     * `haiku.yml` file.
     *
     * @param string|null $path Custom path to the configuration file
     */
    private function resolvePath(?string $path): string
    {
        return base_path($path ?? self::DEFAULT_FILENAME);
    }

    /**
     * Validates the loaded configuration against the defined schema.
     *
     * @param array<string, mixed> $config
     * @param \Realodix\Haiku\Enums\Section $section
     */
    private function validate($config, $section): void
    {
        if ($section === Section::B) {
            $config = Arr::only($config, ['cache_dir', 'builder']);
            $schema = Schema::builder();
        } else {
            $config = Arr::only($config, ['cache_dir', 'fixer']);
            $schema = Schema::fixer();
        }

        try {
            $this->schemaProcessor->process($schema, $config);
        } catch (\Nette\Schema\ValidationException $e) {
            $this->output->writeln('');
            $this->output->writeln('<error>Configuration error:</error>');

            foreach ($e->getMessages() as $message) {
                $this->output->writeln("- {$message}");
            }

            exit(1);
        }
    }
}
