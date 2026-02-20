<?php

namespace Realodix\Haiku\Config;

use Realodix\Haiku\Config\ValueObject\FilterSet;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @phpstan-type _FilterList list<array{
 *  filename: string, header?: string,
 *  source: array<int, string>,
 *  header?: string,
 *  remove_duplicates?: bool,
 * }>
 * @phpstan-type _BuilderConfig array{
 *  output_dir?: string,
 *  filter_list: _FilterList,
 * }
 */
final class BuilderConfig
{
    /** @var list<FilterSet> */
    public array $filterSet;

    private string $outputDir;

    public function __construct(
        private Filesystem $fs,
    ) {}

    /**
     * @param _BuilderConfig $config
     */
    public function make(array $config): self
    {
        $this->validate($config);

        $this->outputDir = $this->outputDir($config['output_dir'] ?? null);
        $this->filterSet = $this->filterSets($config['filter_list']);

        return $this;
    }

    /**
     * Resolves and ensures the existence of the output directory.
     *
     * - If the "output_dir" key is defined in the configuration, its path is
     *   resolved relative to the project base path. The directory will be
     *   created if it does not already exist.
     * - If no "output_dir" is provided, the project base path will be used.
     *
     * @param string|null $dir The output directory
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException
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

        $outputDir = base_path($dir);
        if (!$this->fs->exists($outputDir)) {
            $this->fs->mkdir($outputDir);
        }

        return $outputDir;
    }

    /**
     * Resolves the filter list configuration for each filter list.
     *
     * @param _FilterList $filterLists
     * @return list<FilterSet>
     */
    private function filterSets(array $filterLists): array
    {
        $filters = [];
        foreach ($filterLists as $list) {
            $filters[] = new FilterSet(
                outputPath: Path::join($this->outputDir, $list['filename']),
                header: $list['header'] ?? '',
                source: $list['source'],
                unique: $list['remove_duplicates'] ?? false,
            );
        }

        return $filters;
    }

    /**
     * @param _BuilderConfig $config
     */
    private function validate(array $config): void
    {
        if (empty($config['filter_list'])) {
            throw new InvalidConfigurationException(
                "The 'builder > filter_list' configuration is missing.",
            );
        }

        $index = 0;
        foreach ($config['filter_list'] as $list) {
            $index++;
            if (empty($list['filename'])) {
                throw new InvalidConfigurationException(
                    "The 'builder > filter_list > {$index} > filename' configuration is missing.",
                );
            }

            if (empty($list['source'])) {
                throw new InvalidConfigurationException(sprintf(
                    "The 'builder > filter_list > source' configuration of '%s' is missing.",
                    basename($list['filename']),
                ));
            }
        }
    }
}
