<?php

namespace Realodix\Haiku\Config;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @phpstan-type _FilterSet array{
 *  outdir: string,
 *  header: string,
 *  includes: array<int, string>,
 *  remove_duplicates: bool,
 * }
 * @phpstan-type _FilterSetInput list<array{
 *  filename: string,
 *  header?: string,
 *  includes?: array<int, string>,
 *  source?: array<int, string>,
 *  remove_duplicates?: bool,
 * }>
 * @phpstan-type _BuilderConfigInput array{
 *  output_dir?: string,
 *  filter_lists: _FilterSetInput,
 * }
 */
final class BuilderConfig
{
    /** @var list<_FilterSet> */
    public private(set) array $filterSet;

    private string $outputDir;

    public function __construct(
        private Filesystem $fs,
    ) {}

    /**
     * @param _BuilderConfigInput $config
     */
    public function make(array $config): self
    {
        $this->validate($config);

        $this->outputDir = $this->outputDir($config['output_dir'] ?? null);
        $this->filterSet = $this->filterSets($config['filter_lists']);

        return $this;
    }

    /**
     * @codeCoverageIgnore
     * Resolves and ensures the existence of the output directory.
     *
     * - If the "output_dir" key is defined in the configuration, its path is
     *   resolved relative to the project base path. The directory will be
     *   created if it does not already exist.
     * - If no "output_dir" is provided, the project base path will be used.
     *
     * @param string|null $dir The output directory
     */
    private function outputDir(?string $dir): string
    {
        if ($dir === null) {
            return base_path();
        }

        if (Path::isAbsolute($dir)) {
            throw new InvalidConfigurationException(sprintf(
                'The "output_dir" must be a relative path, %s given.',
                $dir,
            ));
        }

        $dir = base_path($dir);
        if (!$this->fs->exists($dir)) {
            $this->fs->mkdir($dir);
        }

        return $dir;
    }

    /**
     * Resolves the filter list configuration for each filter list.
     *
     * @param _FilterSetInput $entries
     * @return list<_FilterSet>
     */
    private function filterSets(array $entries): array
    {
        $sets = [];
        foreach ($entries as $entry) {
            $sets[] = [
                'outdir' => Path::join($this->outputDir, $entry['filename']),
                'header' => $entry['header'] ?? '',
                'includes' => $entry['includes'],
                'remove_duplicates' => $entry['remove_duplicates'] ?? false,
            ];
        }

        return $sets;
    }

    /**
     * @codeCoverageIgnore
     * @param _BuilderConfigInput $config
     */
    private function validate(array $config): void
    {
        if (empty($config['filter_lists'])) {
            throw new InvalidConfigurationException(
                "The 'builder > filter_lists' configuration is missing.",
            );
        }

        $index = 0;
        foreach ($config['filter_lists'] as $entry) {
            $index++;
            if (empty($entry['filename'])) {
                throw new InvalidConfigurationException(
                    "The 'builder > filter_lists > {$index} > filename' configuration is missing.",
                );
            }

            if (empty($entry['includes'])) {
                throw new InvalidConfigurationException(sprintf(
                    "The 'builder > filter_lists > includes' configuration of '%s' is missing.",
                    basename($entry['filename']),
                ));
            }
        }
    }
}
