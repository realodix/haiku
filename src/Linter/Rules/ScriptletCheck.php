<?php

namespace Realodix\Haiku\Linter\Rules;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Helper;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Util;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 */
final class ScriptletCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        $err = new RuleErrorBuilder;

        foreach ($content as $index => $line) {
            $err->line($index + 1);
            $line = trim($line);

            if (Util::isCommentOrEmpty($line)) {
                continue;
            }

            if (preg_match('/\+js\(\s*([^,\s\)]+)/', $line, $matches)) {
                $actualName = $this->normalizeParam($matches[1]);

                if (str_starts_with($actualName, 'trusted-')) {
                    continue;
                }

                if ($this->checkDeprecated($err, $actualName)) {
                    continue;
                }

                $this->checkUnknown($err, $actualName);
            }
        }

        return $err->toArray();
    }

    private function checkDeprecated(RuleErrorBuilder $err, string $value): bool
    {
        if (in_array($value, Registry::DEPRECATED_SCRIPTLETS, true)) {
            $err->message(sprintf('Deprecated scriptlet: %s', $value))
                ->build();

            return true;
        }

        return false;
    }

    /**
     * rNames:
     * - no-invalid-scriptlets
     */
    private function checkUnknown(RuleErrorBuilder $err, string $value): void
    {
        if ($this->config->rules['scriptlet_unknown'] === false) {
            return;
        }

        $scriptlets = $this->getScriptletNames();
        if (!in_array($value, $scriptlets, true)) {
            $err->message(sprintf('Unknown scriptlet: %s', $value));

            $hint = Helper::getSuggestion($scriptlets, $value);
            if ($hint) {
                $err->tip(sprintf('Did you mean "%s"?', $hint));
            }

            $err->build();
        }
    }

    /**
     * Retrieves a list of unique scriptlet names.
     *
     * @return list<string> A list of unique scriptlet names
     */
    private function getScriptletNames(): array
    {
        $config = $this->config->rules['scriptlet_unknown'];

        $names = [];
        foreach (Util::flatten(Registry::RESOURCES) as $name) {
            if (str_ends_with($name, '.js')) {
                $name = substr($name, 0, -3);
            }

            $names[] = $name;
        }

        $names = array_merge($names, Registry::SCRIPTLETS);

        if (is_array($config)) {
            $names = array_merge($names, $config['known']);
        }

        return array_unique($names);
    }

    private function normalizeParam(string $param): string
    {
        $param = trim($param);

        // remove wrapping quotes (single or double)
        if ((str_starts_with($param, '"') && str_ends_with($param, '"'))
            || (str_starts_with($param, "'") && str_ends_with($param, "'"))
        ) {
            $param = substr($param, 1, -1);
        }

        $param = trim($param);

        // remove .js suffix
        if (str_ends_with($param, '.js')) {
            $param = substr($param, 0, -3);
        }

        return $param;
    }
}
