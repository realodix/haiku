<?php

namespace Realodix\Haiku\Fixer\Classes;

use Realodix\Haiku\Fixer\Regex;

final class NetworkOptionCombiner
{
    private const OPTION_ALIAS_GROUPS = [
        ['stylesheet', 'css'],
        ['elemhide', 'ehide'],
        ['subdocument', 'frame'],
        ['generichide', 'ghide'],
        ['specifichide', 'shide'],
        ['xmlhttprequest', 'xhr'],
    ];

    /**
     * Merge compatible network filter rules by combining their option sets
     * when it is safe to do so.
     *
     * Redundant rules (those that do not add new options) are dropped. Unsafe
     * rules are preserved and returned unchanged.
     *
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

            if (!$this->isSafeToMerge($optionRaw)) {
                $passthrough[] = $rule;

                continue;
            }

            $options = explode(',', $optionRaw);
            $existing = $groups[$pattern]['options'] ?? [];

            if ($existing && $this->isRedundant($existing, $options)) {
                continue;
            }

            if ($existing && $this->hasAliasGroupOverlap(array_keys($existing), $options)) {
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

        // negated option (~css, ~image)
        if (preg_match('/(?:\$|,)~[^,]+/', $optionRaw)) {
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
     *   /ads.$image,css
     *   /ads.$css,image
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
     * Checks whether merging two option sets would mix different aliases belonging
     * to the same semantic option group.
     *
     * Example of unsafe merge:
     *   - *$image,css
     *   - *$image,stylesheet
     *
     * Although "css" and "stylesheet" are semantically equivalent, merging them
     * would force a canonical representation and potentially override user intent.
     *
     * This method:
     * - operates only across different rules
     * - does NOT validate per-line correctness
     * - only prevents unsafe cross-rule merges
     *
     * @param array<string> $existing Options already in the group
     * @param array<string> $incoming Options from the incoming rule
     * @return bool True if an alias conflict would be introduced
     */
    private function hasAliasGroupOverlap(array $existing, array $incoming): bool
    {
        $existingSet = array_flip($existing);

        foreach (self::OPTION_ALIAS_GROUPS as $group) {
            $foundExisting = false;
            $foundIncoming = false;

            foreach ($group as $alias) {
                if (isset($existingSet[$alias])) {
                    $foundExisting = true;
                }
                if (in_array($alias, $incoming, true)) {
                    $foundIncoming = true;
                }
            }

            if ($foundExisting && $foundIncoming) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rebuilds merged filter rules from grouped option sets.
     *
     * Each group produces a single rule with:
     * - the original pattern
     * - a sorted, de-duplicated list of options
     *
     * This method assumes all safety checks have already been performed.
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
