<?php

namespace Realodix\Haiku\Config;

use Realodix\Haiku\Helper;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-type _LinterRules array{
 *  cosm_id_selector_start: bool,
 *  domain_case: bool,
 *  net_pattern_anchor: bool,
 *  no_dupe_domains: bool,
 *  no_dupe_options: bool,
 *  no_dupe_rules: bool,
 *  no_extra_blank_lines: bool|int,
 *  no_short_rules: bool|int,
 *  pp_if_closed: bool,
 *  pp_value: bool,
 *  scriptlet_unknown: bool|array{known: list<string>},
 * }
 */
final class LinterConfig
{
    /**
     * List of resolved absolute file paths to be processed
     *
     * @var array<int, string>
     */
    public private(set) array $paths;

    /** @var list<mixed> */
    public array $ignoreErrors = [];

    /** @var _LinterRules */
    public array $rules = [
        'cosm_id_selector_start' => false,
        'domain_case' => true,
        'net_pattern_anchor' => true,
        'no_dupe_domains' => true,
        'no_dupe_options' => true,
        'no_dupe_rules' => true,
        'no_extra_blank_lines' => false,
        'no_short_rules' => false,
        'pp_if_closed' => true,
        'pp_value' => true,
        'scriptlet_unknown' => true,
    ] {
        /** @param array<string, mixed> $value */
        set(array $value) {
            $this->rules = $this->resolveRules($value);
        }
    }

    /**
     * @param array{
     *   paths?: list<string>,
     *   excludes?: list<string>,
     *   rules?: _LinterRules,
     *   ignoreErrors?: list<mixed>
     * } $config User-defined configuration from the config file
     * @param array{path: string|null} $cmdOpt Command options
     */
    public function make(array $config, array $cmdOpt): self
    {
        $this->paths = $this->paths(
            $cmdOpt['path'] ?? $config['paths'] ?? [],
            $config['excludes'] ?? [],
        );

        $this->rules = $config['rules'] ?? [];
        $this->ignoreErrors = $this->normalizeIgnorePaths($config['ignoreErrors'] ?? []);

        return $this;
    }

    /**
     * Resolves and validates rule overrides.
     *
     * @param array<string, mixed> $override
     * @return _LinterRules
     */
    private function resolveRules(array $override): array
    {
        $rules = $this->rules;
        // 'fmode' acts as a bulk toggle for all boolean rules
        if (array_key_exists('fmode', $override)) {
            $value = (bool) $override['fmode'];
            foreach ($rules as $name => $defaultValue) {
                $rules[$name] = $value;
            }
            unset($override['fmode']);
        }

        // Apply specific overrides
        foreach ($override as $name => $value) {
            if (!array_key_exists($name, $rules)) {
                $hint = Helper::getSuggestion(array_merge(array_keys($rules), ['fmode']), $name);
                throw new InvalidConfigurationException(sprintf(
                    'Unknown flag: "%s"'.($hint ? ", did you mean '%s'?" : '.'),
                    $name,
                    $hint,
                ));
            }

            $rules[$name] = $value;
        }

        return $rules;
    }

    /**
     * Resolves provided paths into a unique list of absolute file paths.
     *
     * @param array<int, string>|string $paths
     * @param array<int, string> $excludes Excludes files or dirs
     * @return array<int, string>
     */
    private function paths(array|string $paths, array $excludes): array
    {
        $rootPath = base_path();
        $paths = is_array($paths) ? $paths : [$paths];
        $paths = !empty($paths) ? $paths : [$rootPath];

        $resolvedPaths = [];
        foreach ($paths as $path) {
            if (Path::isRelative($path)) {
                $path = Path::makeAbsolute($path, $rootPath);
            }

            if (is_dir($path)) {
                $finder = $this->finder($path, $excludes);
                foreach ($finder as $file) {
                    $resolvedPaths[] = $file->getRealPath();
                }
            } else {
                $resolvedPaths[] = $path;
            }
        }

        $resolvedPaths = array_map(fn($path) => Path::canonicalize($path), $resolvedPaths);

        return array_unique($resolvedPaths);
    }

    /**
     * @param string $dir The directory to use for the search
     * @param array<int, string> $excludes Excludes files or dirs
     * @return \Symfony\Component\Finder\Finder
     */
    private function finder(string $dir, array $excludes)
    {
        if ($dir === base_path()) {
            $excludes = array_merge($excludes, ['node_modules', 'vendor']);
        }

        $excludes = array_map(fn($paths) => Path::canonicalize($paths), $excludes);
        $excludes = array_unique($excludes);

        $finder = new Finder;
        $finder->files()
            ->in($dir)
            ->name(['*.txt', '*.adfl'])
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->notPath($excludes);

        return $finder;
    }

    /**
     * @param list<mixed> $ignoreErrors
     * @return list<mixed>
     */
    private function normalizeIgnorePaths(array $ignoreErrors): array
    {
        foreach ($ignoreErrors as &$ignore) {
            if (is_string($ignore)) {
                continue;
            }

            if (isset($ignore['path'])) {
                $ignore['path'] = Path::normalize($ignore['path']);
            }

            if (isset($ignore['paths'])) {
                $ignore['paths'] = array_map(
                    fn($p) => Path::normalize($p),
                    (array) $ignore['paths'],
                );
            }
        }

        return $ignoreErrors;
    }
}
