<?php

namespace Realodix\Haiku\Fixer;

use Realodix\Haiku\Config\FixerConfig;
use Realodix\Haiku\Fixer\Components\Combiner;
use Realodix\Haiku\Fixer\Components\ElementTidy;
use Realodix\Haiku\Fixer\Components\NetOptionCombiner;
use Realodix\Haiku\Fixer\Components\NetworkTidy;
use Realodix\Haiku\Helper;

final class Fixer
{
    public function __construct(
        private Combiner $combiner,
        private ElementTidy $elementTidy,
        private NetworkTidy $networkTidy,
        private NetOptionCombiner $optionCombiner,
        private FixerConfig $config,
    ) {}

    /**
     * Process raw filter lines into their normalized form.
     *
     * @param array<int, string> $lines An array of raw filter lines
     * @return array<int, string> The processed and optimized list of filter lines
     */
    public function fix(array $lines): array
    {
        $result = []; // Stores the final processed rules
        $section = []; // Temporary storage for a section of rules

        foreach ($lines as $i => $line) {
            $line = trim($line);

            if ($line === '') {
                $this->handleEmptyLine($i, $lines, $section, $result);

                continue;
            }

            // Check for comments, headers, or include directives,
            // which act as section breaks.
            if ($this->isSpecialLine($line)) {
                // Write current section if it exists and reset counters
                $this->flushSection($section, $result);

                // Add the comment/header line to the result
                $result[] = $line;

                continue;
            }

            // Handle rule lines
            if (preg_match(Regex::COSMETIC_RULE, $line, $m)) {
                $section[] = $this->elementTidy->applyFix($line, $m);
            } else {
                $section[] = $this->networkTidy->applyFix($line);
            }
        }

        // Write any remaining section
        $this->flushSection($section, $result);

        return $result;
    }

    /**
     * Flush the current section into the final result.
     *
     * If the section buffer is non-empty, it will be processed and appended to the result
     * in its transformed form. The section buffer is then cleared.
     *
     * @param array<int, string> &$section Reference to the current rule section buffer
     * @param array<int, string> &$result Reference to the final output buffer
     */
    private function flushSection(array &$section, array &$result): void
    {
        if ($section) {
            foreach ($this->processSection($section) as $line) {
                $result[] = $line;
            }

            $section = [];
        }
    }

    /**
     * Process a logical section of rules.
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
            if (preg_match(Regex::COSMETIC_RULE, $rule)
                || preg_match(Regex::AG_JS_RULE, $rule)) {
                $cosmetic[] = $rule;
            } else {
                $network[] = $rule;
            }
        }

        $cosmetic = Helper::uniqueSortBy($cosmetic, fn($value) => $this->cosmeticSortKey($value));
        $cosmetic = $this->combiner->applyFix($cosmetic, Regex::COSMETIC_DOMAIN, ',');

        $network = $this->optionCombiner->applyFix($network);
        $network = Helper::uniqueSortBy(
            $network,
            fn($value) => str_starts_with($value, '@@') ? '}'.$value : $value,
            SORT_STRING | SORT_FLAG_CASE,
        );
        $network = $this->combiner->applyFix($network, Regex::NET_OPTION_DOMAIN, '|');

        return array_merge($network, $cosmetic);
    }

    /**
     * Handle an empty line according to the configured policy.
     *
     * In cases where the line is preserved, the current section is flushed beforehand
     * to prevent structural spacing from being merged into rule logic.
     *
     * @param int $index The current line index within the original input
     * @param array<int, string> $lines The full list of input lines
     * @param array<int, string> &$section Reference to the current rule section buffer
     * @param array<int, string> &$result Reference to the final output buffer
     */
    private function handleEmptyLine(int $index, array $lines, array &$section, array &$result): void
    {
        $mode = $this->config->flags['remove_empty_lines'];

        if ($mode === true) {
            return;
        }

        if ($mode === 'keep_before_comment') {
            $next = $lines[$index + 1] ?? null;

            if ($next !== null && str_starts_with(trim($next), '!')) {
                $this->flushSection($section, $result);
                $result[] = '';
            }

            return;
        }

        // mode === false
        $this->flushSection($section, $result);
        $result[] = '';
    }

    /**
     * Generate a sorting key for cosmetic rules.
     *
     * @param string $rule The cosmetic rule to determine the order for
     * @return string Sorting key
     */
    private function cosmeticSortKey(string $rule): string
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
     * Determine whether a line is structural rather than a filter rule.
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
            || preg_match('/^-+$/', $line);
    }
}
