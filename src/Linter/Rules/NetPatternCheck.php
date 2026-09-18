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
            ) {
                continue;
            }

            if (preg_match(Regex::NET_OPTION, $line, $m)) {
                $line = $m[1];
            }

            $this->checkSpaceInPattern($err, $line);
        }

        return $err->toArray();
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkSpaceInPattern($err, string $line): void
    {
        if (!$this->config->rules['no_spaces_in_net_pattern']) {
            return;
        }

        if (!str_contains($line, ' ')
            || str_contains($line, ' * ') // uBo dynamic filtering rules
            || preg_match('/^(0|127)\./', $line) // host
            || str_contains($line, ' CNAME ')
            // bind / unbound / SmartDNS
            || preg_match('/^[a-z\-\s\:]+("|\/)/', $line)
            // ublacklist
            || preg_match('/^[$!]?[a-z]+\s?[*$^]?=~?\s?["\/]/', $line)
        ) {
            return;
        }

        $err->message('Net pattern should not contain spaces.')
            ->build();
    }
}
