<?php

namespace Realodix\Haiku\Linter\Rules\Redundant;

final class ConditionalScope
{
    /**
     * Processes all lines and returns an array of condition keys per line index.
     *
     * - Lines that are !#if or !#endif will have a null value (not a rule).
     * - Lines outside valid blocks: empty string.
     * - Lines inside valid blocks: hash of the condition stack (joined by '|').
     *
     * @param list<string> $lines Array of lines (0-indexed).
     * @return array<int, string|null> Mapping line index => condition key or null for control lines.
     */
    public function process(array $lines): array
    {
        $result = [];
        $lineCount = count($lines);

        /**
         * Valid !#if positions mapped to their closing !#endif.
         *
         * Example:
         * !#if ext_chromium
         * ...
         * !#endif
         *
         * Produces:
         * [
         *     10 => ['end' => 20, 'cond' => 'ext_chromium'],
         * ]
         *
         * @var array<int, array{end: int, cond: string}>
         */
        $pairs = [];
        /**
         * Fast lookup table for valid !#endif positions.
         *
         * Used during pass 2 to avoid scanning all pairs when
         * determining whether the current line closes a block.
         *
         * @var array<int, true>
         */
        $endifPositions = [];
        /**
         * Stack of currently opened !#if directives discovered
         * during pass 1.
         *
         * @var list<array{pos: int, cond: string}>
         */
        $openPositions = [];

        // Pass 1:
        // Discover valid !#if ... !#endif pairs.
        //
        // Invalid/unclosed !#if directives are ignored because they
        // never become part of $pairs.
        for ($idx = 0; $idx < $lineCount; $idx++) {
            $line = $lines[$idx];

            if (preg_match('/^!#\s?if(?:\s+(.+))?$/i', $line, $m)) {
                $condition = trim($m[1] ?? '');

                // Only non-empty conditions are considered valid
                if ($condition !== '') {
                    $openPositions[] = [
                        'pos' => $idx,
                        'cond' => $condition,
                    ];
                }

                continue;
            }

            // Match a closing directive.
            // If there is an opened !#if waiting on the stack, create a valid pair
            // and remember the endif position.
            if ($line === '!#endif' && $openPositions !== []) {
                $last = array_pop($openPositions);

                $pairs[$last['pos']] = [
                    'end' => $idx,
                    'cond' => $last['cond'],
                ];

                $endifPositions[$idx] = true;
            }
        }

        // Pass 2:
        // Walk through the file and determine which condition stack
        // is active for every non-control line.
        $activeStack = [];
        for ($idx = 0; $idx < $lineCount; $idx++) {
            // Entering a valid conditional block
            if (isset($pairs[$idx])) {
                $activeStack[] = $pairs[$idx]['cond'];
                $result[$idx] = null;

                continue;
            }

            // Leaving a valid conditional block
            if (isset($endifPositions[$idx])) {
                array_pop($activeStack);
                $result[$idx] = null;

                continue;
            }

            // Regular line
            $result[$idx] = $this->buildConditionKey($activeStack);
        }

        return $result;
    }

    /**
     * @param list<string> $stack
     */
    private function buildConditionKey(array $stack): string
    {
        if (empty($stack)) {
            return '';
        }

        return hash('xxh3', implode('|', $stack));
    }
}
