<?php

namespace Realodix\Haiku\Fixer\Classes;

use Realodix\Haiku\Fixer\Regex;

final class ElementTidy
{
    public function __construct(
        private DomainNormalizer $domainNormalizer,
        private AdgModifierForElement $adgModifier,
    ) {}

    public function setExperimental(bool $xMode): void
    {
        $this->domainNormalizer->xMode = $xMode;
    }

    /**
     * @param string $line The rule line
     * @param array<string> $m The regex match
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

        if (str_starts_with($modifier, '[$') && $this->adgModifier->isComplicated($modifier)) {
            $modifier = $this->adgModifier->extract($domainBlock);

            if (is_null($modifier)) {
                return $line;
            }

            $line = substr($line, strlen($modifier));

            preg_match(Regex::COSMETIC_RULE, $line, $m);
            $domain = $m[3];
            $separator = $m[4];
            $selector = $m[5];
        }

        $modifier = $this->adgModifier->applyFix($modifier);
        $domain = $this->normalizeDomain($domain);
        $selector = $this->normalizeSelector($selector);

        return $modifier.$domain.$separator.$selector;
    }

    private function normalizeDomain(string $str): string
    {
        // fix incorrect domain separators when they do not contain regex
        if (!str_contains($str, '/') && str_contains($str, '|')) {
            $str = str_replace('|', ',', $str);
        }

        return $this->domainNormalizer->applyFix($str, ',');
    }

    private function normalizeSelector(string $str): string
    {
        // remove leading spaces
        $str = ltrim($str);

        return $str;
    }
}
