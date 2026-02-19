<?php

namespace Realodix\Haiku\Fixer;

use Realodix\Haiku\Fixer\Classes\Combiner;
use Realodix\Haiku\Fixer\Classes\ElementTidy;
use Realodix\Haiku\Fixer\Classes\NetOptionCombiner;
use Realodix\Haiku\Fixer\Classes\NetworkTidy;
use Realodix\Haiku\Helper;

final class Processor
{
    public function __construct(
        private Combiner $combiner,
        private ElementTidy $elementTidy,
        private NetworkTidy $networkTidy,
        private NetOptionCombiner $optionCombiner,
    ) {}

    /**
     * Processes an array of filter lines, optimizing them into a sorted
     * and combined list.
     *
     * @param array<int, string> $lines An array of raw filter lines
     * @return array<int, string> The processed and optimized list of filter lines
     */
    public function process(array $lines): array
    {
        $result = []; // Stores the final processed rules
        $section = []; // Temporary storage for a section of rules

        foreach ($lines as $line) {
            $line = trim($line);
            if (Helper::flag('remove_empty_lines') && $line === '') {
                continue;
            }

            // Check for comments, headers, or include directives,
            // which act as section breaks.
            if ($this->isSpecialLine($line)) {
                // Write current section if it exists and reset counters
                if ($section) {
                    $result = array_merge($result, $this->processSection($section));
                    $section = [];
                }

                // Add the comment/header line to the result
                $result[] = $line;

                continue;
            }

            if (preg_match(Regex::COSMETIC_RULE, $line, $m)) {
                $section[] = $this->elementTidy->applyFix($line, $m);
            } else {
                $section[] = $this->networkTidy->applyFix($line);
            }
        }

        // Write any remaining section
        if ($section) {
            $result = array_merge($result, $this->processSection($section));
        }

        return $result;
    }

    /**
     * Processes a section of filter rules by normalizing, sorting, de-duplicating
     * and combining them into their final form.
     *
     * @param array<int, string> $section Tidied filter rules
     * @return array<int, string> The processed lines for the section
     */
    private function processSection(array $section): array
    {
        $cosmetic = [];
        $network = [];

        // categorize the line as either an element rule or a network filter
        foreach ($section as $rule) {
            if (preg_match(Regex::COSMETIC_RULE, $rule)) {
                $cosmetic[] = $rule;
            } elseif (preg_match(Regex::AG_JS_RULE, $rule)) {
                $cosmetic[] = $rule;
            } else {
                $network[] = $rule;
            }
        }

        $cosmetic = Helper::uniqueSorted($cosmetic, fn($value) => $this->cosmeticRulesOrder($value))
            ->all();
        $cosmetic = $this->combiner->applyFix($cosmetic, Regex::COSMETIC_DOMAIN, ',');

        $network = $this->optionCombiner->applyFix($network);
        $network = Helper::uniqueSorted(
            $network,
            fn($value) => str_starts_with($value, '@@') ? '}'.$value : $value,
            SORT_STRING | SORT_FLAG_CASE,
        )->all();
        $network = $this->combiner->applyFix($network, Regex::NET_OPTION_DOMAIN, '|');

        return array_merge($network, $cosmetic);
    }

    /**
     * Returns a string representing the order of a cosmetic rule.
     *
     * @param string $rule The cosmetic rule to determine the order for
     * @return string The rule with the order prefix added, or the original rule if no prefix is needed.
     */
    private function cosmeticRulesOrder(string $rule): string
    {
        preg_match(Regex::COSMETIC_DOMAIN, $rule, $m);
        $rule = isset($m[1]) ? substr($rule, strlen($m[1])) : $rule;

        // https://regex101.com/r/eqaq6o/1
        if (preg_match('/^(#@?[?$]{1,2}#|#@?#\^|\$@?\$)/', $rule)
            || str_starts_with($rule, '[$')) {
            return '1'.$rule;
        }

        // scriptlet rules
        if (str_starts_with($rule, '##+') || str_starts_with($rule, '#@#+')
            || str_starts_with($rule, '#%#') || str_starts_with($rule, '#@%#')) {
            return '2'.$rule;
        }

        // regex domain
        if (str_starts_with($rule, '/')) {
            return '3'.$rule;
        }

        return $rule;
    }

    /**
     * Determines if a given filter line is a special line.
     */
    public function isSpecialLine(string $line): bool
    {
        return
            // comment
            str_starts_with($line, '!')
            // special comments starting with # but not ## (element hiding)
            || str_starts_with($line, '#') && !Helper::isCosmeticRule($line)
            // header
            || str_starts_with($line, '[') && str_ends_with($line, ']') && !str_contains($line, '$')
            // YAML metadata
            || trim($line, '-') === '';
    }
}
