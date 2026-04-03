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
        $errors = [];

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if (Util::isCommentOrEmpty($line)) {
                continue;
            }

            if (preg_match('/\+js\(\s*([^,\s\)]+)/', $line, $matches)) {
                $actualName = $this->normalizeParam($matches[1]);

                if (str_starts_with($actualName, 'trusted-')) {
                    continue;
                }

                if ($err = $this->checkDeprecated($lineNum, $actualName)) {
                    $errors[] = $err;

                    continue;
                }

                if ($err = $this->checkUnknown($lineNum, $actualName)) {
                    $errors[] = $err;
                }
            }
        }

        return $errors;
    }

    /**
     * @return _RuleError|null
     */
    private function checkDeprecated(int $lineNum, string $value)
    {
        if (in_array($value, Registry::DEPRECATED_SCRIPTLETS, true)) {
            return RuleErrorBuilder::message(sprintf('Deprecated scriptlet: "%s".', $value))
                ->line($lineNum)
                ->build();
        }

        return null;
    }

    /**
     * @return _RuleError|null
     */
    private function checkUnknown(int $lineNum, string $value)
    {
        if ($this->config->rules['check_unknown_scriptlet'] === false) {
            return null;
        }

        $scriptlets = $this->getScriptletNames();
        if (!in_array($value, $scriptlets, true)) {
            $builder = RuleErrorBuilder::message(sprintf('Unknown scriptlet: "%s"', $value))
                ->line($lineNum);

            $hint = Helper::getSuggestion($scriptlets, $value);
            if ($hint) {
                $builder->tip(sprintf('Did you mean "%s"?', $hint));
            }

            return $builder->build();
        }

        return null;
    }

    /**
     * Retrieves a list of unique scriptlet names.
     *
     * @return list<string> A list of unique scriptlet names
     */
    private function getScriptletNames(): array
    {
        $config = $this->config->rules['check_unknown_scriptlet'];

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
