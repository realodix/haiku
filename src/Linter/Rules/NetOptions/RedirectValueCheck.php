<?php

namespace Realodix\Haiku\Linter\Rules\NetOptions;

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
                $value = preg_replace('/:(?:-)?\d+$/', '', $m[1]);

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

        $knownResources = array_merge(
            Util::getRedirectResources(),
            Registry::AG_REDIRECT_RESOURCES,
            Registry::DEPRECATED_REDIRECT_RESOURCES,
        );

        if (!in_array($value, $knownResources, true)) {
            $hint = Util::getSuggestion($knownResources, Registry::NORMALIZED_UNKNOWN[$value] ?? $value);
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
