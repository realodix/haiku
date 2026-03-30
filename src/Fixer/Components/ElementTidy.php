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

        $modifier = $m[2] ?? ''; // [$adg-modifiers]
        $domain = $m[3];         // example.com
        $separator = $m[4];      // ##
        $selector = $m[5];       // .ads

        if (!$this->adgModifier->verify($modifier)) {
            return $line;
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
        $str = $this->convertAttributeSelector($str);
        $str = $this->convertLegacyRemoveAction($str);

        return $str;
    }

    /**
     * Converts attribute selectors into basic selectors.
     *
     * Mode:
     * - 'strict': Only allow ~= for class selectors
     * - 'loose': Allow both = and ~= for class
     *
     * @param string $selector The selector to be converted
     * @return string The converted CSS selector
     */
    private function convertAttributeSelector(string $selector): string
    {
        $mode = $this->config->flags['attr_to_basic_selector'];

        if (!$mode) {
            return $selector;
        }

        $selector = preg_replace_callback(
            // https://regex101.com/r/lg5nfI/
            '/\[(class|id)(=|~=)"([\x{0021}\x{0023}-\x{007E}]+)"\]/',
            function ($m) use ($mode) {
                [$full, $attr, $op, $value] = $m;

                if (// Never convert [id~="..."]
                    // "~=" implies token matching, which is not valid for id semantics
                    ($attr === 'id' && $op === '~=')
                    // Strict mode: only allow ~= for class selectors
                    || ($mode === 'strict' && $attr === 'class' && $op !== '~=')
                ) {
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
        if (!$this->config->flags['no_legacy_remove_action']) {
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
        if (!$this->config->flags['no_legacy_ext_selectors']
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
