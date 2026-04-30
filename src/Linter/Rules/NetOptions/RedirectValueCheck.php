<?php

namespace Realodix\Haiku\Linter\Rules\NetOptions;

use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Linter\Util;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 */
final class RedirectValueCheck implements Rule
{
    public function check(array $content): array
    {
        $bag = new RuleErrorBuilder;

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if (Util::isCommentOrEmpty($line)) {
                continue;
            }

            if (preg_match('/(?<=[$,])redirect(?:-rule)?\s*=\s*([\w\-\.\:]+)?(?=,|$)/', $line, $m)) {
                $value = isset($m[1])
                    ? preg_replace('/:(?:-)?\d+$/', '', $m[1])
                    : null;

                if ($this->checkInvalid($bag, $lineNum, $value)) {
                    continue;
                }

                if ($this->checkDeprecated($bag, $lineNum, $value)) {
                    continue;
                }

                $this->checkUnknown($bag, $lineNum, $value);
            }
        }

        return $bag->toArray();
    }

    private function checkInvalid(RuleErrorBuilder $bag, int $lineNum, ?string $value): bool
    {
        if ($value === null) {
            $bag->message('Invalid redirect resource value syntax.')
                ->line($lineNum)->build();

            return true;
        }

        return false;
    }

    private function checkDeprecated(RuleErrorBuilder $bag, int $lineNum, ?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (in_array($value, Registry::DEPRECATED_REDIRECT_RESOURCES, true)) {
            $bag->message(sprintf('Deprecated redirect resource value: "%s"', $value))
                ->line($lineNum)->build();

            return true;
        }

        return false;
    }

    private function checkUnknown(RuleErrorBuilder $bag, int $lineNum, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $resources = array_merge(
            Registry::RESOURCES,
            Registry::REDIRECT_RESOURCES,
            Registry::AG_REDIRECT_RESOURCES,
        );

        if (!in_array($value, Util::flatten($resources), true)) {
            $bag->message(sprintf('Unknown redirect resource value: "%s"', $value))
                ->line($lineNum)->build();
        }
    }
}
