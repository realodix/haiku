<?php

namespace Realodix\Haiku\Fixer\Classes;

use Realodix\Haiku\Fixer\Regex;

/**
 * Merge compatible network filter rules by combining their option sets when it
 * is safe to do so. Redundant rules (those that do not add new options) are
 * dropped. Unsafe rules are preserved and returned unchanged.
 */
final class NetworkOptionCombiner
{
    private const OPTION_ALIAS = [
        ['stylesheet', 'css'],
        ['elemhide', 'ehide'],
        ['subdocument', 'frame'],
        ['generichide', 'ghide'],
        ['specifichide', 'shide'],
        ['xmlhttprequest', 'xhr'],
    ];

    /**
     * @param array<string> $rules
     * @return array<string>
     */
    public function applyFix(array $rules): array
    {
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
                    $groups[$pattern]['pattern'] = $pattern;
                }
            }

            if (!$this->isSafeToMerge($optionRaw)) {
                $passthrough[] = $rule;

                continue;
            }

            foreach ($options as $opt) {
                $groups[$pattern]['options'][$opt] = true;
                $groups[$pattern]['pattern'] = $pattern;
            }
        }

        return array_merge($passthrough, $this->rebuild($groups));
    }

    /**
     * Determines whether a raw option string is safe to be merged.
     *
     * This method performs **coarse safety checks only** and does not validate
     * correctness of individual options. Unsafe rules must not be merged, as doing
     * so could alter the filter's execution semantics.
     *
     * @param string $optionRaw Raw option substring (after `$`)
     * @return bool True if the options are safe to merge
     */
    private function isSafeToMerge(string $optionRaw): bool
    {
        // value-based options (domain=, to=)
        if (str_contains($optionRaw, '=')) {
            return false;
        }

        // https://regex101.com/r/ayVPBx/2
        if (preg_match(
            '/([\$,])
            ( badfilter|important|all|other
              |(?:1|3)p|(?:strict-)?(?:first|third)-party|strict(?:1|3)p
            )
            (,|$)/x',
            $optionRaw,
        )) {
            return false;
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
     * @param array<string, bool> $existingOptions Currently merged options
     * @param array<string> $newOptions Incoming rule options
     * @return bool True if the incoming rule adds no new information
     */
    private function isRedundant(array $existingOptions, array $newOptions): bool
    {
        foreach ($newOptions as $opt) {
            if (!isset($existingOptions[$opt])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Removes existing aliases that belong to the same alias group
     * as the incoming option.
     *
     * @param array<string, bool> $existingOptions
     */
    private function overwriteAlias(array &$existingOptions, string $incomingOption): void
    {
        foreach (self::OPTION_ALIAS as $group) {
            if (!in_array($incomingOption, $group, true)) {
                continue;
            }

            // remove all aliases in the same group
            foreach ($group as $alias) {
                unset($existingOptions[$alias]);
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
     * @param array<string> $incoming
     */
    private function canMergeConsideringPolarity(array $existing, array $incoming): bool
    {
        [$ePos, $eNeg] = $this->splitPolarity(array_keys($existing));
        [$iPos, $iNeg] = $this->splitPolarity($incoming);

        $eState = $this->polarityState($ePos, $eNeg);
        $iState = $this->polarityState($iPos, $iNeg);

        // allowed
        if ($eState === 'POS' && $iState === 'POS'
            || $eState === 'NEG' && $iState === 'NEG') {
            return true;
        }

        // pos + mixed
        // allowed only if they share at least one positive option
        if ($eState === 'POS' && $iState === 'MIXED'
            || $eState === 'MIXED' && $iState === 'POS') {
            return (bool) array_intersect($ePos, $iPos);
        }

        // neg + mixed
        // allowed only if they share at least one negated option
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
     * @param array<string> $pos
     * @param array<string> $neg
     */
    private function polarityState(array $pos, array $neg): string
    {
        return match (true) {
            // contains only negated options ($~image)
            $pos === [] && $neg !== [] => 'NEG',
            // contains only positive options ($image)
            $pos !== [] && $neg === [] => 'POS',
            // contains both positive and negated options
            $pos !== [] && $neg !== [] => 'MIXED',
            default => 'EMPTY', // practically unreachable
        };
    }

    /**
     * @param array<string> $options
     * @return array{0: array<string>, 1: array<string>}
     */
    private function splitPolarity(array $options): array
    {
        $positive = [];
        $negative = [];

        foreach ($options as $opt) {
            if ($opt[0] === '~') {
                $negative[] = substr($opt, 1);
            } else {
                $positive[] = $opt;
            }
        }

        return [$positive, $negative];
    }

    /**
     * Rebuilds merged filter rules from grouped option sets. This method assumes
     * all safety checks have already been performed.
     *
     * @param array<string, array{
     *  pattern: string,
     *  options: array<string, bool>
     * }> $groups
     * @return array<string> Final merged filter rules
     */
    private function rebuild(array $groups): array
    {
        $result = [];

        foreach ($groups as $group) {
            $options = array_keys($group['options']);
            $result[] = $group['pattern'].'$'.implode(',', $options);
        }

        return $result;
    }
}
