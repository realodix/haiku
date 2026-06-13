<?php

namespace Realodix\Haiku\Linter\Rules;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Helper;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Linter\Util;

final class ScriptletCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content, $err): array
    {
        foreach ($content as $index => $line) {
            $err->line($index + 1);
            $line = trim($line);

            if (Util::isCommentOrEmpty($line)) {
                continue;
            }

            if (preg_match('/\+js\(\s*([^,\s\)]+)/', $line, $m)) {
                $actualName = $this->normalizeParam($m[1]);

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

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkDeprecated($err, string $value): bool
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
     *
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkUnknown($err, string $value): void
    {
        if ($this->config->rules['scriptlet_unknown'] === false) {
            return;
        }

        $scriptlets = $this->getScriptletNames();
        if (!in_array($value, $scriptlets, true)) {
            $hint = Helper::getSuggestion($scriptlets, $value);

            $err->message(sprintf('Unknown scriptlet: %s', $value))
                ->when($hint, function () use ($err, $hint) {
                    $err->tip(sprintf('Did you mean "%s"?', $hint));
                })->build();
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
        $resources = array_map(
            fn($name) => str_ends_with($name, '.js') ? substr($name, 0, -3) : $name,
            Util::flatten(Registry::RESOURCES),
        );

        return array_unique([
            ...$resources,
            ...Registry::SCRIPTLETS,
            ...$config['known'] ?? [],
        ]);
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
