<?php

namespace Realodix\Haiku\Fixer\Components;

/**
 * Combines adjacent filter rules that are identical except for their domain list.
 */
final class Combiner
{
    public function __construct(
        private DomainNormalizer $domainNormalizer,
    ) {}

    /**
     * Combines domains for (further) identical rules.
     *
     * @param array<int, string> $filters List of filter rules
     * @param string $domainPattern The regex pattern to extract the domain part
     * @param string $separator Domain separator (`,` or `|`)
     * @return array<int, string> Combined filter rules
     */
    public function applyFix(array $filters, string $domainPattern, string $separator): array
    {
        $combined = [];
        $filterCount = count($filters);

        for ($i = 0; $i < $filterCount; $i++) {
            $currentLine = $filters[$i];
            $nextLine = $filters[$i + 1] ?? null;

            $currentLineParse = $this->parseDomain($currentLine, $domainPattern);

            if ($nextLine === null || $currentLineParse['full_match'] === '') {
                $combined[] = $currentLine;

                continue;
            }

            $nextLineParse = $this->parseDomain($nextLine, $domainPattern);

            if ($this->canCombine($currentLineParse, $nextLineParse, $separator)) {
                $newDomain = $this->combineDomains(
                    $currentLineParse['domain'],
                    $nextLineParse['domain'],
                    $separator,
                );

                // Replace the domain in `$currentLine` and insert it into `$nextLine`.
                $newFullMatch = str_replace($currentLineParse['domain'], $newDomain, $currentLineParse['full_match']);
                /** @var array<int, string> $filters */
                $filters[$i + 1] = preg_replace($domainPattern, $newFullMatch, $currentLine);
            } else {
                $combined[] = $currentLine;
            }
        }

        return $combined;
    }

    /**
     * Merges two domain lists into a single normalized domain list.
     *
     * @param string $currentDomain The first domain value
     * @param string $nextDomain The second domain value to combine
     * @param string $separator Domain separator (`,` or `|`)
     * @return string The combined domain value
     */
    private function combineDomains(string $currentDomain, string $nextDomain, string $separator): string
    {
        $newDomain = $currentDomain.$separator.$nextDomain;

        return $this->domainNormalizer->applyFix($newDomain, $separator);
    }

    /**
     * Extracts the domain section and base rule from a filter string.
     *
     * @param string $filter Filter rule to parse
     * @param string $domainPattern The regex pattern to extract the domain part
     * @return array{
     *  full_match: string, // The full matched domain part (e.g., $domains=example.com|~example.net)
     *  domain: string,     // The domain list (e.g., example.com|~example.net)
     *  pattern: string     // The filter rule without the domain part. (e.g., ||example.com^)
     * } Parsed domain components
     */
    private function parseDomain(string $filter, string $domainPattern): array
    {
        if (preg_match($domainPattern, $filter, $matches)) {
            return [
                'full_match' => $matches[0],
                'domain' => $matches[1] ?? '',
                'pattern' => preg_replace($domainPattern, '', $filter),
            ];
        }

        return ['full_match' => '', 'domain' => '', 'pattern' => $filter];
    }

    /**
     * Determines if two filter rules can be combined.
     *
     * @param array{full_match: string, domain: string, pattern: string} $currentLine The analysis of the current filter rule
     * @param array{full_match: string, domain: string, pattern: string} $nextLine The analysis of the next filter rule
     * @param string $separator Domain separator (`,` or `|`)
     * @return bool True if the rules can be safely combined
     */
    private function canCombine(array $currentLine, array $nextLine, string $separator): bool
    {
        if ($nextLine['full_match'] === '' || $currentLine['domain'] === '' || $nextLine['domain'] === ''
            || str_starts_with($currentLine['domain'], '/') || str_starts_with($nextLine['domain'], '/')
        ) {
            return false;
        }

        // Check domain structure compatibility
        $replaced = str_replace($currentLine['domain'], $nextLine['domain'], $currentLine['full_match']);
        if ($replaced !== $nextLine['full_match']) {
            return false;
        }

        // Check if the base filter parts (without domains) are identical
        if ($currentLine['pattern'] !== $nextLine['pattern']) {
            return false;
        }

        // Both domain parts must share the same polarity:
        // either both maybeMixed (e.g., example.com) or both negated (e.g., ~example.org).
        $currentType = $this->domainSetType($currentLine['domain'], $separator);
        $nextType = $this->domainSetType($nextLine['domain'], $separator);

        return $currentType === $nextType;
    }

    /**
     * Classifies a domain list by its polarity structure.
     *
     * A domain list is classified as:
     * - negated    : all domains are prefixed with `~`
     * - maybeMixed : contains at least one non-negated domain
     *
     * Note:
     * 'maybeMixed' does not guarantee that both positive and negative domains are present.
     * It simply indicates that at least one positive (non-negated) domain exists.
     *
     * @param string $domainList Domain list string
     * @param string $separator Domain separator (`,` or `|`)
     * @return 'maybeMixed'|'negated'
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
