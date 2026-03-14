<?php

namespace Realodix\Haiku\Fixer\Components;

use Realodix\Haiku\Config\FixerConfig;
use Realodix\Haiku\Helper;

final class ElementTidy
{
    public function __construct(
        private DomainNormalizer $domainNormalizer,
        private AdgModifierForElement $adgModifier,
        private FixerConfig $config,
    ) {}

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
        $str = ltrim($str); // Remove leading spaces
        $str = $this->convertExactAttributeSelector($str);

        return $str;
    }

    /**
     * Converts an exact attribute selector to a CSS selector.
     *
     * Example:
     * - [class="ads"] -> .ads
     * - [id="ads"] -> #ads
     *
     * @param string $selector The selector to be converted
     * @return string The converted CSS selector
     */
    private function convertExactAttributeSelector(string $selector): string
    {
        if (!$this->config->flags['exact_attr_to_css_selector']) {
            return $selector;
        }

        $selector = preg_replace_callback(
            // https://regex101.com/r/aKP06x
            '/\[(class|id)="([\x{0021}\x{0023}-\x{007E}]+)"\]/',
            function ($m) {
                [$full, $attr, $value] = $m;

                // Ensure strict [attr="value"] form only
                // no operators, modifiers, or spaces
                if ($full !== '['.$attr.'="'.$value.'"]') {
                    return $full;
                }

                $value = Helper::cssEscape($value);

                return ($attr === 'class' ? '.' : '#').$value;
            },
            $selector,
        );

        return $selector;
    }
}
