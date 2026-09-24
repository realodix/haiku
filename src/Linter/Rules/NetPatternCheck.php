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
            $this->checkBadDomainAnchors($err, $line);
        }

        return $err->toArray();
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkTooShortPattern($err, string $line, bool $hasOptions): void
    {
        $mode = $this->config->rules['no_short_rules'];

        if ($mode === false
            || $hasOptions && ($line === '' || $line === '*' || $line === '@@*')
        ) {
            return;
        }

        if (strlen($line) < $mode) {
            $err->message("The rule is too short (under {$mode} characters).")
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkBadDomainAnchors($err, string $line): void
    {
        if (!$this->config->rules['no_bad_domain_anchors']) {
            return;
        }

        // Left anchor
        $reLeftPattern = '(@@)?(\|+)';
        preg_match("/^{$reLeftPattern}/", $line, $m);
        $leadingPipes = isset($m[2]) ? strlen($m[2]) : 0;
        if ($leadingPipes > 2) {
            $err->message('Too many "|" at the beginning (max 2 allowed).')
                ->build();
        }
        if (preg_match("/^{$reLeftPattern}$/", $line, $m)) {
            return;
        }

        // Right anchor
        preg_match('/\|+$/', $line, $m);
        $trailingPipes = isset($m[0]) ? strlen($m[0]) : 0;

        if ($trailingPipes > 1) {
            $err->message('Too many "|" at the end (only 1 allowed).')
                ->build();
        }
    }
}
