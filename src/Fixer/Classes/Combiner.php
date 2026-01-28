<?php

namespace Realodix\Haiku\Fixer\Classes;

use Realodix\Haiku\Fixer\ValueObject\DomainSection;

final class Combiner
{
    public function __construct(
        private DomainNormalizer $domainNormalizer,
    ) {}

    public function setExperimental(bool $xMode): void
    {
        $this->domainNormalizer->xMode = $xMode;
    }

    /**
     * Combines domains for (further) identical rules.
     *
     * @param array<string> $filters The filter rules
     * @param string $domainPattern The regex pattern to extract the domain part
     * @param string $separator The separator character used between domains (`,` or `|`)
     * @return array<string> Combined filter rules
     */
    public function applyFix(array $filters, string $domainPattern, string $separator): array
    {
        $combined = [];
        $filterCount = count($filters);

        for ($i = 0; $i < $filterCount; $i++) {
            $currentLine = $filters[$i];
            $nextLine = $filters[$i + 1] ?? null;

            $currentLineParse = $this->parseDomain($currentLine, $domainPattern);

            if ($nextLine === null || $currentLineParse->fullMatch === '') {
                $combined[] = $currentLine;

                continue;
            }

            $nextLineParse = $this->parseDomain($nextLine, $domainPattern);

            if ($this->canCombine($currentLineParse, $nextLineParse, $separator)) {
                $newDomain = $this->combineDomains(
                    $currentLineParse->domainList,
                    $nextLineParse->domainList,
                    $separator,
                );

                // Replace the domain in `$currentLine` and insert it into `$nextLine`.
                $newFullMatch = str_replace($currentLineParse->domainList, $newDomain, $currentLineParse->fullMatch);
                $filters[$i + 1] = preg_replace($domainPattern, $newFullMatch, $currentLine);
            } else {
                $combined[] = $currentLine;
            }
        }

        return $combined;
    }

    /**
     * Combines two domain values into one, removing duplicates and sorting alphabetically
     * while preserving inverse domains.
     *
     * @param string $currentDomain The first domain value
     * @param string $nextDomain The second domain value to combine
     * @param string $separator The separator character used between domains (`,` or `|`)
     * @return string The combined domain value
     */
    private function combineDomains(string $currentDomain, string $nextDomain, string $separator): string
    {
        $newDomain = $currentDomain.$separator.$nextDomain;

        return $this->domainNormalizer->applyFix($newDomain, $separator);
    }

    /**
     * Parses a filter rule into its domain and base rule parts.
     *
     * @param string $filter The filter rule to analyze
     * @param string $domainPattern The regex pattern to extract the domain part
     * @return \Realodix\Haiku\Fixer\ValueObject\DomainSection An object containing the extracted parts of the filter
     */
    private function parseDomain(string $filter, string $domainPattern)
    {
        if (preg_match($domainPattern, $filter, $matches)) {
            return new DomainSection(
                fullMatch: $matches[0],
                domainList: $matches[1] ?? '',
                baseRule: preg_replace($domainPattern, '', $filter),
            );
        }

        return new DomainSection('', '', $filter);
    }

    /**
     * Determines if two filter rules can be combined.
     *
     * This method checks for several conditions:
     * 1. Both rules must have a domain part.
     * 2. The structure of the domain part must be compatible.
     * 3. The base rules (the part of the rule without the domain) must be identical.
     * 4. The domain types (maybeMixed or negated) must be compatible.
     *
     * @param \Realodix\Haiku\Fixer\ValueObject\DomainSection $currentLine The analysis of the current filter rule
     * @param \Realodix\Haiku\Fixer\ValueObject\DomainSection $nextLine The analysis of the next filter rule
     * @param string $separator The separator character used between domains (`,` or `|`)
     * @return bool True if the rules can be combined, false otherwise
     */
    private function canCombine($currentLine, $nextLine, string $separator): bool
    {
        if ($nextLine->fullMatch === '' || $currentLine->domainList === '' || $nextLine->domainList === ''
            || str_starts_with($currentLine->domainList, '/') || str_starts_with($nextLine->domainList, '/')
        ) {
            return false;
        }

        // Check domain structure compatibility
        $replaced = str_replace($currentLine->domainList, $nextLine->domainList, $currentLine->fullMatch);
        if ($replaced !== $nextLine->fullMatch) {
            return false;
        }

        // Check if the base filter parts (without domains) are identical
        if ($currentLine->baseRule !== $nextLine->baseRule) {
            return false;
        }

        // Both domain parts must share the same polarity:
        // either both maybeMixed (e.g., example.com) or both negated (e.g., ~example.org).
        $currentType = $this->domainSetType($currentLine->domainList, $separator);
        $nextType = $this->domainSetType($nextLine->domainList, $separator);

        return $currentType === $nextType;
    }

    /**
     * Determines the type of a domain set used in a filter rule.
     *
     * A domain set can be one of two types:
     * - maybeMixed: contains at least 1 normal domain (e.g., ~x.com|y.com|~z.com).
     * - negated: contains only negated domains (prefixed with `~`, e.g., ~x.com|~y.com).
     *
     * @param string $domainList The domain string to check
     * @param string $separator The separator character used between domains (`,` or `|`)
     * @return string Returns either `'maybeMixed'` or `'negated'`
     */
    private function domainSetType(string $domainList, string $separator): string
    {
        // $hasNormal = substr_count($domainList, '~') < substr_count($domainList, $separator) + 1;

        // return $hasNormal ? 'maybeMixed' : 'negated';

        $domains = explode($separator, $domainList);
        foreach ($domains as $d) {
            if (!str_starts_with($d, '~')) {
                return 'maybeMixed';
            }
        }

        return 'negated';
    }
}
