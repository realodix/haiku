<?php

namespace Realodix\Haiku\Fixer\Type;

use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Helper;

final class ElementTidy
{
    public function __construct(
        private AdgModifierForElement $adgModifier,
    ) {}

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
        $domain = Helper::normalizeDomain($domain, ',');
        $selector = $this->normalizeSelector($selector);

        return $modifier.$domain.$separator.$selector;
    }

    private function normalizeSelector(string $str): string
    {
        // remove extra spaces
        $str = preg_replace('/\s\s+/', ' ', $str);
        // remove leading spaces
        $str = ltrim($str);

        return $str;
    }
}
