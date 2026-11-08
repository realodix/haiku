<?php

namespace Realodix\Haiku\Linter\Rules\NetOptions;

use Composer\Pcre\Preg;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Support\Util;

final class RedirectValueCheck implements Rule
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

            // https://regex101.com/r/QZptvL/
            if (preg_match('/(?<=[$,])(?:redirect(?:-rule)?|rewrite)=([\w\-\.\:]+)(?=,|$)/', $line, $m)) {
                $value = Preg::replace('/:(?:-)?\d+$/', '', $m[1]);

                $this->checkUnknown($err, $value);
                $this->checkDeprecated($err, $value);
            }
        }

        return $err->toArray();
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkUnknown($err, string $value): void
    {
        if (!$this->config->rules['no_invalid_redirect_resources']) {
            return;
        }

        $resources = array_unique([
            ...Util::getRedirectResources(),
            ...Registry::AG_REDIRECT_RESOURCES,
            ...Registry::DEPRECATED_REDIRECT_RESOURCES,
            ...array_map(
                fn($name) => str_ends_with($name, '.js') ? substr($name, 0, -3) : $name,
                $this->config->userResources ?? [],
            ),
        ]);

        if (!in_array($value, $resources, true)) {
            $hint = Util::getSuggestion($resources, Registry::NORMALIZED_UNKNOWN[$value] ?? $value);
            $err->message("Invalid redirect resource: {$value}")
                ->identifier('redirect.invalidValue')
                ->when($hint, fn() => $err->tip("Did you mean \"{$hint}\"?"))
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkDeprecated($err, string $value): void
    {
        if (!$this->config->rules['no_deprecated_redirect_resources']) {
            return;
        }

        if (in_array($value, Registry::DEPRECATED_REDIRECT_RESOURCES, true)) {
            $err->message("Deprecated: The redirect resource {$value} is deprecated.")
                ->identifier('redirect.deprecated')
                ->build();
        }
    }
}
