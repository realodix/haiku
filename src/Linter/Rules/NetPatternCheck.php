<?php

namespace Realodix\Haiku\Linter\Rules;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Support\Util;

final class NetPatternCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content, $err): array
    {
        foreach ($content as $index => $line) {
            $err->line($index + 1);
            $line = trim($line);

            if (preg_match(Regex::IS_COSMETIC_RULE, $line)
                || Util::isCommentOrEmpty($line)
                || Util::isMetaLine($line)
            ) {
                continue;
            }

            $hasOptions = false;
            if (preg_match(Regex::NET_OPTION, $line, $m)) {
                $hasOptions = true;
                $line = $m[1];
            }

            $this->checkTooShortPattern($err, $line, $hasOptions);
            $this->checkBadDomainAnchors($err, $line, $hasOptions);
            $this->checkLikePathOrRegex($err, $line);
            $this->checkUnnecessaryWildcard($err, $line);
        }

        return $err->toArray();
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkTooShortPattern($err, string $line, bool $hasOptions): void
    {
        $config = $this->config->rules['no_short_rules'];
        if (!$config || $hasOptions && ($line === '' || $line === '*' || $line === '@@*')) {
            return;
        }

        if (strlen($line) < $config['minLen']) {
            $err->message("The rule is too short (under {$config['minLen']} characters).")
                ->identifier('netPattern.tooShort')
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkBadDomainAnchors($err, string $line, bool $hasOptions): void
    {
        if (!$this->config->rules['no_bad_domain_anchors']) {
            return;
        }

        // Left anchor
        if (preg_match('/^(@@)?(\|+)/', $line, $m)) {
            if (strlen($m[2]) > 2) {
                $err->message('Too many "|" at the beginning (max 2 allowed).')
                    ->identifier('netPattern.tooManyLeftAnchors')
                    ->build();
            }

            if ($hasOptions) {
                $line .= '__boundary__';
            }
        }

        // Right anchor
        if ((strlen($line) - strlen(rtrim($line, '|'))) > 1) {
            $err->message('Too many "|" at the end (only 1 allowed).')
                ->identifier('netPattern.tooManyRightAnchors')
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkLikePathOrRegex($err, string $line): void
    {
        if (!$this->config->rules['likePathOrRegex']) {
            return;
        }

        if (!preg_match('/^(?:@@)?(\*)?\/(.+)\/(\*)?$/', $line, $m)) {
            return;
        }

        $isPath = !empty($m[1]) || !empty($m[3]);
        $hasRegexSyntax = strpbrk($m[2], '\()[]{}|^$') !== false
            || (str_contains($m[2], '?')
                // https://regex101.com/r/aODftK
                && !preg_match('/([^\\\]\/|\.[a-z]{2,})\?[a-zA-Z*]+=/', $m[2])
            );

        if ($isPath && $hasRegexSyntax) {
            $err->message('Possible regex pattern in path format.')
                ->tip('Use regex format by removing "*" to the appropriate.')
                ->identifier('net.pathLikeRegex')
                ->build();

            return;
        }

        if (!$isPath && !$hasRegexSyntax) {
            if ($m[2] === '.*') {
                return;
            }

            $err->message('Possible path pattern in regex format.')
                ->tip('Use path format by adding "*" to the appropriate side.')
                ->identifier('net.regexLikePath')
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkUnnecessaryWildcard($err, string $line): void
    {
        if (!$this->config->rules['net.unnecessaryWildcard']) {
            return;
        }

        if (str_starts_with($line, '@@')) {
            $line = substr($line, 2);
        }

        if (str_starts_with($line, '/') && str_ends_with($line, '/*')
            || str_starts_with($line, '*/') && str_ends_with($line, '/')
            || strlen($line) < 4
        ) {
            return;
        }

        $hasLeadingWildcard = false;
        $hasTrailingWildcard = false;

        if (str_starts_with($line, '**') || (str_starts_with($line, '*/') && str_ends_with($line, '/*'))) {
            $hasLeadingWildcard = true;
        }

        if (str_ends_with($line, '**')) {
            $hasTrailingWildcard = true;
        }

        if ($hasLeadingWildcard && $hasTrailingWildcard) {
            $message = 'Unnecessary leading and trailing wildcards.';
        } elseif ($hasLeadingWildcard) {
            $message = 'Unnecessary leading wildcard.';
        } elseif ($hasTrailingWildcard) {
            $message = 'Unnecessary trailing wildcard.';
        } else {
            return;
        }

        $err->message($message)
            ->identifier('net.unnecessaryWildcard')
            ->build();
    }
}
