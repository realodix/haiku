<?php

namespace Realodix\Haiku\Builder;

use Realodix\Haiku\Helper;

final class Cleaner
{
    /**
     * Cleans a list of raw filter source contents by removing metadata, comments,
     * and empty lines, leaving only the valid filter rules.
     *
     * @param array<string> $text Raw filter source contents
     * @param bool $unique Removes duplicate filter rules
     * @return array<string> The cleaned filter contents, each containing only valid rules
     */
    public static function clean(array $text, bool $unique): array
    {
        return collect($text)
            ->flatMap(function (string $content) {
                $content = self::removeMetadataAgent($content);
                $content = self::removeComment($content);
                $content = rtrim($content);

                return $content === '' ? [] : [$content];
            })
            ->when($unique, fn($collection) => $collection->unique())
            ->values()->all();
    }

    /**
     * Remove adblock agent metadata.
     *
     * Like this:
     * - [Adblock Plus 2.0]
     * - [uBlock Origin]
     * - [AdGuard]
     *
     * References:
     * - https://github.com/AdguardTeam/FiltersCompiler/blob/e071fdef76/src/main/utils/workaround.js#L10
     * - https://github.com/github-linguist/linguist/blob/2409807814/lib/linguist/heuristics.yml#L927
     */
    private static function removeMetadataAgent(string $content): string
    {
        // https://regex101.com/r/eZnxif
        return preg_replace(
            '/^\[(Ad[Bb]lock|[Aa]d[Gg]uard|u[Bb](?:lock|[Oo]))([a-zA-Z0-9\.\s]+)?\]$/m',
            '',
            $content,
        );
    }

    /**
     * Remove comments (lines that start with !) from lines.
     *
     * Don not remove comments that start with !# (Preprocessor directives).
     * - https://github.com/gorhill/uBlock/wiki/Static-filter-syntax#pre-parsing-directives
     * - https://adguard.com/kb/general/ad-filtering/create-own-filters/#preprocessor-directives
     */
    private static function removeComment(string $content): string
    {
        if (str_starts_with($content, '#') && !Helper::isCosmeticRule($content)) {
            return '';
        }

        // https://regex101.com/r/VSOcD6/1
        return preg_replace('/^!(?!#\s?(?:include\s|if|endif|else)).*/m', '', $content);
    }
}
