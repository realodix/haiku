<?php

namespace Realodix\Haiku\Config;

use Symfony\Component\Filesystem\Path;

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
 * @phpstan-type _ConfigIgnoredError array{
 *  message?: string,
 *  messages?: list<string>,
 *  path?: string,
 *  paths?: list<string>,
 * }|string
 */
final class LinterConfig
{
    /**
     * List of resolved absolute file paths to be processed
     *
     * @var array<int, string>
     */
    public private(set) array $paths;

    /** @var list<_ConfigIgnoredError> */
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
            $this->rules = Util::resolveOverrides($this->rules, $value, 'rule');
        }
    }

    /**
     * @param array{
     *   paths?: list<string>,
     *   excludes?: list<string>,
     *   rules?: _LinterRules,
     *   ignoreErrors?: list<_ConfigIgnoredError>
     * } $config User-defined configuration from the config file
     * @param array{path: string|null} $cmdOpt Command options
     */
    public function make(array $config, array $cmdOpt): self
    {
        $this->paths = Util::paths(
            $cmdOpt['path'] ?? $config['paths'] ?? [],
            $config['excludes'] ?? [],
        );

        $this->rules = $config['rules'] ?? [];
        $this->ignoreErrors = $this->normalizeIgnorePaths($config['ignoreErrors'] ?? []);

        return $this;
    }

    /**
     * @param list<_ConfigIgnoredError> $ignoreErrors
     * @return list<_ConfigIgnoredError>
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
