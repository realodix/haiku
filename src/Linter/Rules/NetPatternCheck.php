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
                    ->build();
            }

            if ($hasOptions) {
                $line .= '__boundary__';
            }
        }

        // Right anchor
        if ((strlen($line) - strlen(rtrim($line, '|'))) > 1) {
            $err->message('Too many "|" at the end (only 1 allowed).')
                ->build();
        }
    }
}
