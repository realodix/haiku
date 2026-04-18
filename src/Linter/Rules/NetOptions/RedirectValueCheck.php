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
        $errors = [];

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

                if ($err = $this->checkInvalid($lineNum, $value)) {
                    $errors[] = $err;

                    continue;
                }

                if ($err = $this->checkDeprecated($lineNum, $value)) {
                    $errors[] = $err;

                    continue;
                }

                if ($err = $this->checkUnknown($lineNum, $value)) {
                    $errors[] = $err;
                }
            }
        }

        return $errors;
    }

    /**
     * @return _RuleError|null
     */
    private function checkInvalid(int $lineNum, ?string $value)
    {
        if ($value === null) {
            return RuleErrorBuilder::message('Invalid redirect resource value syntax.')
                ->line($lineNum)->build();
        }

        return null;
    }

    /**
     * @return _RuleError|null
     */
    private function checkDeprecated(int $lineNum, string $value)
    {
        if (in_array($value, Registry::DEPRECATED_REDIRECT_RESOURCES, true)) {
            return RuleErrorBuilder::message(sprintf('Deprecated redirect resource value: "%s"', $value))
                ->line($lineNum)->build();
        }

        return null;
    }

    /**
     * @return _RuleError|null
     */
    private function checkUnknown(int $lineNum, string $value)
    {
        $resources = array_merge(
            Registry::RESOURCES,
            Registry::REDIRECT_RESOURCES,
            Registry::AG_REDIRECT_RESOURCES,
        );

        if (!in_array($value, Util::flatten($resources), true)) {
            return RuleErrorBuilder::message(sprintf('Unknown redirect resource value: "%s"', $value))
                ->line($lineNum)->build();
        }

        return null;
    }
}
