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
        [$selector, $separator] = $this->convertAbpExtendedSelectors($selector, $separator);
        $selector = $this->normalizeSelector($selector);

        return $modifier.$domain.$separator.$selector;
    }

    private function normalizeSelector(string $str): string
    {
        $str = ltrim($str); // Remove leading spaces
        $str = $this->convertExactAttributeSelector($str);
        $str = $this->convertLegacyRemoveAction($str);

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

    /**
     * Convert ABP/AG remove action to uBO format.
     *
     * Example:
     * - .ads { remove: true; } -> .ads:remove()
     *
     * https://help.adblockplus.org/hc/en-us/articles/360062733293-How-to-write-filters#elemhide_css
     * https://adguard.com/kb/general/ad-filtering/create-own-filters/#remove-pseudos
     * https://github.com/gorhill/uBlock/wiki/Static-filter-syntax#subjectremove
     */
    private function convertLegacyRemoveAction(string $selector): string
    {
        if (!$this->config->flags['convert_legacy_remove_action']) {
            return $selector;
        }

        return preg_replace(
            // https://regex101.com/r/XGKbVr
            '/\s*\{\s*remove\s*:\s*true[; ]*\}$/',
            ':remove()',
            $selector,
        );
    }

    /**
     * Convert ABP extended selectors to uBO format.
     *
     * https://adblockplus.org/filter-cheatsheet#elementhidingemulation-selectors
     *
     * @return list<string>
     */
    private function convertAbpExtendedSelectors(string $selector, string $separator): array
    {
        if (!$this->config->flags['convert_abp_extended_selectors']
            || !str_contains($selector, ':-abp-')
        ) {
            return [$selector, $separator];
        }

        static $separatorMap = [
            '#?#' => '##',
            '#@?#' => '#@#',
        ];

        static $selectorMap = [
            ':-abp-contains(' => ':has-text(',
            ':-abp-has(' => ':has(',
        ];

        $separator = str_replace(array_keys($separatorMap), array_values($separatorMap), $separator);
        $selector = str_replace(array_keys($selectorMap), array_values($selectorMap), $selector);

        return [$selector, $separator];
    }
}
