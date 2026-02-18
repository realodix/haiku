<?php

namespace Realodix\Haiku\Fixer\Classes;

use Realodix\Haiku\Fixer\Regex;

/**
 * @phpstan-import-type _FixerFlags from \Realodix\Haiku\Config\FixerConfig
 */
final class ElementTidy
{
    public function __construct(
        private DomainNormalizer $domainNormalizer,
        private AdgModifierForElement $adgModifier,
    ) {}

    /**
     * @param _FixerFlags $flags
     */
    public function setFlags(array $flags): void
    {
        $this->domainNormalizer->flags = $flags;
        $this->adgModifier->flags = $flags;
    }

    /**
     * @param string $line The rule line
     * @param array<int, string> $m The regex match
     * @return string The normalized rule
     */
    public function applyFix(string $line, array $m): string
    {
        if ($m === []) {
            return $line;
        }

        $domainBlock = $m[1];    // [$adg-modifiers]example.com
        $modifier = $m[2] ?? ''; // [$adg-modifiers]
        $domain = $m[3];         // example.com
        $separator = $m[4];      // ##
        $selector = $m[5];       // .ads

        // Handle complicated AdGuard modifier (delegated)
        if ($resolved = $this->adgModifier->resolveComplicated($line, $domainBlock, $modifier)) {
            [$modifier, $domain, $separator, $selector] = $resolved;
        }

        $modifier = $this->adgModifier->applyFix($modifier);
        $domain = $this->domainNormalizer->applyFix($domain, ',');
        $selector = $this->normalizeSelector($selector);

        return $modifier.$domain.$separator.$selector;
    }

    private function normalizeSelector(string $str): string
    {
        // Remove leading spaces
        $str = ltrim($str);

        return $str;
    }
}
