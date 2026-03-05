<?php

namespace Realodix\Haiku\Fixer\Classes;

use Realodix\Haiku\Config\FixerConfig;
use Realodix\Haiku\Fixer\Regex;

/**
 * Combines compatible network filter rules that share the same pattern by merging
 * their option sets when it is semantically safe.
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

    public function __construct(
        private FixerConfig $config,
    ) {}

    /**
     * @param array<int, string> $rules
     * @return array<int, string>
     */
    public function applyFix(array $rules): array
    {
        if (!$this->config->flags['combine_option_sets']) {
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
            $options = explode(',', $m[2]);

            if (!$this->hasSupportedOptions($options)) {
                $passthrough[] = $rule;

                continue;
            }

            $existing = $groups[$pattern]['options'] ?? [];

            if ($existing) {
                if (!$this->canMerge($existing, $options)) {
                    $passthrough[] = $rule;

                    continue;
                }

                // Overwrite existing aliases with the incoming ones
                foreach ($options as $opt) {
                    $this->overwriteAlias($groups[$pattern]['options'], $opt);
                    $groups[$pattern]['options'][$opt] = true;
                }
            }

            // Store options as a keyed set for fast lookup and deduplication
            foreach ($options as $opt) {
                $groups[$pattern]['options'][$opt] = true;
            }

            $groups[$pattern]['pattern'] = $pattern;
        }

        return array_merge($passthrough, $this->rebuild($groups));
    }

    /**
     * Checks whether all options belong to the supported merge set.
     *
     * @param array<int, string> $options Parsed option list (without `$`), possibly
     *                                    prefixed with `~`
     */
    private function hasSupportedOptions(array $options): bool
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
     * Removes all existing options that belong to the same alias group as the given
     * incoming option.
     *
     * Alias groups represent semantically equivalent options (e.g. `css` and `stylesheet`).
     * When an alias is encountered, all other aliases from the same group are removed
     * to avoid duplication within the merged set.
     *
     * @param array<string, bool> $existing Current merged options (by reference)
     * @param string $incoming The option being inserted
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
     * Determines whether two option sets can be safely merged based on their polarity
     * structure.
     *
     * @param array<string, bool> $existing
     * @param array<int, string> $incoming
     */
    private function canMerge(array $existing, array $incoming): bool
    {
        [$ePos, $eNeg] = $this->splitPolarity(array_keys($existing));
        [$iPos, $iNeg] = $this->splitPolarity($incoming);

        $eState = $this->polarityState($ePos, $eNeg);
        $iState = $this->polarityState($iPos, $iNeg);

        return match ([$eState, $iState]) {
            ['POS', 'POS'] => true,
            ['NEG', 'NEG'] => true,

            // pos + mixed
            // Allowed only if they share at least one positive option
            ['POS', 'MIXED'] => (bool) array_intersect($ePos, $iPos),
            ['MIXED', 'POS'] => (bool) array_intersect($ePos, $iPos),

            // neg + mixed
            // Allowed only if they share at least one negated option
            ['NEG', 'MIXED'] => (bool) array_intersect($eNeg, $iNeg),
            ['MIXED', 'NEG'] => (bool) array_intersect($eNeg, $iNeg),

            // other combinations
            default => false,
        };
    }

    /**
     * Classifies an option set by polarity structure.
     *
     * @param array<int, string> $pos
     * @param array<int, string> $neg
     * @return 'POS'|'NEG'|'MIXED'
     */
    private function polarityState(array $pos, array $neg): string
    {
        // @phpstan-ignore match.unhandled
        return match (true) {
            // only positive ($image)
            $pos !== [] && $neg === [] => 'POS',
            // only negated ($~image)
            $pos === [] && $neg !== [] => 'NEG',
            // contains both positive and negated options
            $pos !== [] && $neg !== [] => 'MIXED',
        };
    }

    /**
     * Splits an option list into positive and negated subsets.
     *
     * @param array<int, string> $options
     * @return array{
     *     0: array<int, string>, // positive
     *     1: array<int, string>  // negative (without `~`)
     * }
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
     * Reconstructs filter rules from grouped and merged option sets.
     *
     * This method assumes all safety checks have already been performed.
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
