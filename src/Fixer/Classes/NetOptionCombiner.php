<?php

namespace Realodix\Haiku\Fixer\Classes;

use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Helper;

/**
 * Merge compatible network filter rules by combining their option sets when it
 * is safe to do so. Redundant rules are dropped. Unregistered rules are preserved.
 */
final class NetOptionCombiner
{
    private const OPTIONS = [
        'document', 'doc',
        'font',
        'image',
        'media',
        'script',
        'stylesheet', 'css',
        'subdocument', 'frame',
        'websocket',
        'xmlhttprequest', 'xhr',
    ];

    private const OPTION_ALIAS = [
        ['document', 'doc'],
        ['stylesheet', 'css'],
        ['subdocument', 'frame'],
        ['xmlhttprequest', 'xhr'],
    ];

    /**
     * @param array<int, string> $rules
     * @return array<int, string>
     */
    public function applyFix(array $rules): array
    {
        if (!Helper::flag('combine_option_sets')) {
            return $rules;
        }

        $groups = [];
        $passthrough = [];

        foreach ($rules as $rule) {
            if (!preg_match(Regex::NET_OPTION, $rule, $m)) {
                $passthrough[] = $rule;

                continue;
            }

            $pattern = $m[1];
            $optionRaw = $m[2];
            $options = explode(',', $optionRaw);

            if (!$this->isMergeable($options)) {
                $passthrough[] = $rule;

                continue;
            }

            $existing = $groups[$pattern]['options'] ?? [];

            if ($existing) {
                if (!$this->canMergeConsideringPolarity($existing, $options)) {
                    $passthrough[] = $rule;

                    continue;
                }

                if ($this->isRedundant($existing, $options)) {
                    continue;
                }

                // overwrite existing aliases with the incoming ones
                foreach ($options as $opt) {
                    $this->overwriteAlias($groups[$pattern]['options'], $opt);
                    $groups[$pattern]['options'][$opt] = true;
                }
            }

            foreach ($options as $opt) {
                $groups[$pattern]['options'][$opt] = true;
            }

            $groups[$pattern]['pattern'] = $pattern;
        }

        return array_merge($passthrough, $this->rebuild($groups));
    }

    /**
     * Determines whether a set of network filter options is eligible to be merged.
     *
     * @param array<int, string> $options Parsed option list (without `$`), possibly
     *                                    prefixed with `~`
     * @return bool True if the options are safe to merge
     */
    private function isMergeable(array $options): bool
    {
        foreach ($options as $opt) {
            $clean = ltrim($opt, '~');

            if (!in_array($clean, self::OPTIONS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks whether a new rule adds any options that are not already present
     * in the existing merged option set.
     *
     * If all incoming options already exist, the rule is considered redundant
     * and can be safely discarded.
     *
     * This prevents duplicate rules such as:
     * - /ads.$image,css
     * - /ads.$image
     *
     * @param array<string, bool> $existing Currently merged options
     * @param array<int, string> $incoming Incoming rule options
     * @return bool True if the incoming rule adds no new information
     */
    private function isRedundant(array $existing, array $incoming): bool
    {
        foreach ($incoming as $opt) {
            if (!isset($existing[$opt])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Removes existing aliases that belong to the same alias group
     * as the incoming option.
     *
     * @param array<string, bool> $existing
     */
    private function overwriteAlias(array &$existing, string $incoming): void
    {
        foreach (self::OPTION_ALIAS as $group) {
            if (!in_array($incoming, $group, true)) {
                continue;
            }

            // remove all aliases in the same group
            foreach ($group as $alias) {
                unset($existing[$alias]);
            }

            return;
        }
    }

    /**
     * Determines whether two option sets can be safely merged, taking option
     * polarity into account. The check is symmetric: the order of existing and
     * incoming options does not affect the outcome.
     *
     * @param array<string, bool> $existing
     * @param array<int, string> $incoming
     */
    private function canMergeConsideringPolarity(array $existing, array $incoming): bool
    {
        [$ePos, $eNeg] = $this->splitPolarity(array_keys($existing));
        [$iPos, $iNeg] = $this->splitPolarity($incoming);

        $eState = $this->polarityState($ePos, $eNeg);
        $iState = $this->polarityState($iPos, $iNeg);

        // allowed
        if ($eState === $iState && $eState !== 'MIXED') {
            return true;
        }

        // pos + mixed
        // Allowed only if they share at least one positive option
        if ($eState === 'POS' && $iState === 'MIXED'
            || $eState === 'MIXED' && $iState === 'POS') {
            return (bool) array_intersect($ePos, $iPos);
        }

        // neg + mixed
        // Allowed only if they share at least one negated option
        if ($eState === 'NEG' && $iState === 'MIXED'
            || $eState === 'MIXED' && $iState === 'NEG') {
            return (bool) array_intersect($eNeg, $iNeg);
        }

        return false;
    }

    /**
     * Determines whether two option sets can be safely merged, taking option
     * polarity into account. This method does not validate option correctness;
     * it only classifies structural polarity.
     *
     * @param array<int, string> $pos
     * @param array<int, string> $neg
     * @return 'POS'|'NEG'|'MIXED'
     */
    private function polarityState(array $pos, array $neg): string
    {
        // @phpstan-ignore match.unhandled
        return match (true) {
            // contains only positive options ($image)
            $pos !== [] && $neg === [] => 'POS',
            // contains only negated options ($~image)
            $pos === [] && $neg !== [] => 'NEG',
            // contains both positive and negated options
            $pos !== [] && $neg !== [] => 'MIXED',
        };
    }

    /**
     * @param array<int, string> $options
     * @return list<array<int, string>>
     */
    private function splitPolarity(array $options): array
    {
        $pos = [];
        $neg = [];

        foreach ($options as $opt) {
            if ($opt[0] === '~') {
                $neg[] = substr($opt, 1);
            } else {
                $pos[] = $opt;
            }
        }

        return [$pos, $neg];
    }

    /**
     * Rebuilds merged filter rules from grouped option sets. This method assumes
     * all safety checks have already been performed.
     *
     * @param array<string, array{
     *  pattern: string,
     *  options: array<string, bool>
     * }> $groups
     * @return array<int, string>
     */
    private function rebuild(array $groups): array
    {
        $out = [];

        foreach ($groups as $g) {
            $out[] = $g['pattern'].'$'.implode(',', array_keys($g['options']));
        }

        return $out;
    }
}
