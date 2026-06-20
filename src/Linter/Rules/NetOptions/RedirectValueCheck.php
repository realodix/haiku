<?php

namespace Realodix\Haiku\Linter\Rules\NetOptions;

use Realodix\Haiku\Helper;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Linter\Util;

final class RedirectValueCheck implements Rule
{
    public function check(array $content, $err): array
    {
        foreach ($content as $index => $line) {
            $err->line($index + 1);
            $line = trim($line);

            if (Util::isCommentOrEmpty($line)) {
                continue;
            }

            if (preg_match('/(?<=[$,])redirect(?:-rule)?\s*=\s*([\w\-\.\:]+)?(?=,|$)/', $line, $m)) {
                $value = isset($m[1])
                    ? preg_replace('/:(?:-)?\d+$/', '', $m[1])
                    : null;

                if ($this->checkInvalid($err, $value)) {
                    continue;
                }

                if ($this->checkDeprecated($err, $value)) {
                    continue;
                }

                $this->checkUnknown($err, $value);
            }
        }

        return $err->toArray();
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkInvalid($err, ?string $value): bool
    {
        if ($value === null) {
            $err->message('Invalid redirect resource value syntax.')
                ->build();

            return true;
        }

        return false;
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkDeprecated($err, string $value): bool
    {
        if (in_array($value, Registry::DEPRECATED_REDIRECT_RESOURCES, true)) {
            $err->message(sprintf('Deprecated redirect resource value: "%s"', $value))
                ->build();

            return true;
        }

        return false;
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkUnknown($err, string $value): void
    {
        $knownResources = Util::flattenWithKeys(array_merge(
            Registry::RESOURCES,
            Registry::REDIRECT_RESOURCES,
            Registry::AG_REDIRECT_RESOURCES,
        ));

        if (!in_array($value, $knownResources, true)) {
            $value = Registry::NORMALIZED_UNKNOWN[$value] ?? $value;
            $hint = Helper::getSuggestion($knownResources, $value);

            $err->message(sprintf('Unknown redirect resource value: "%s"', $value))
                ->when($hint, function () use ($err, $hint) {
                    $err->tip(sprintf('Did you mean "%s"?', $hint));
                })->build();
        }
    }
}
