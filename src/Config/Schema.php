<?php

namespace Realodix\Haiku\Config;

use Nette\Schema\Expect;

final class Schema
{
    /**
     * @return \Nette\Schema\Elements\Structure
     */
    public static function global()
    {
        return Expect::structure([
            'cache_dir' => Expect::string(),
        ]);
    }

    /**
     * @return \Nette\Schema\Elements\Structure
     */
    public static function fixer()
    {
        return self::global()->extend([
            'fixer' => Expect::structure([
                'paths' => Expect::listOf('string'),
                'excludes' => Expect::listOf('string'),
                'backup' => Expect::bool(),
                'flags' => Expect::structure([
                    'attr_to_basic_selector' => Expect::anyOf('strict', 'loose'),
                    'option_format' => Expect::anyOf('native', 'long', 'short'),
                    'option_order' => Expect::anyOf('name', 'type', false),
                    'remove_empty_lines' => Expect::anyOf(true, false, 'keep_before_comment'),
                    'domain_order' => Expect::string() // @deprecated since v1.13.14
                        ->deprecated('The "domain_order" flag is deprecated.')
                        ->assert(fn(string $value): bool => in_array(
                            $value, ['name', 'normal', 'negated_first'], true,
                        )),
                    'normalize_domain' => Expect::bool() // @deprecated since v1.13.14
                        ->deprecated('The "normalize_domain" flag is deprecated.'),
                    // @deprecated since v1.13.17
                    'no_legacy_ext_selectors' => Expect::bool()
                        ->deprecated(
                            'The "no_legacy_ext_selectors" flag is deprecated. '
                            .'Use "convert_legacy_ext_selectors" instead.',
                        ),
                    'no_legacy_remove_action' => Expect::bool()
                        ->deprecated(
                            'The "no_legacy_remove_action" flag is deprecated. '
                            .'Use "convert_legacy_remove_action" instead.',
                        ),
                    'normalize_domain_separators' => Expect::bool()
                        ->deprecated(
                            'The "normalize_domain_separators" flag is deprecated. '
                            .'Use "fix_domain_separators" instead.',
                        ),
                    'reduce_subdomains' => Expect::bool()
                        ->deprecated(
                            'The "reduce_subdomains" flag is deprecated. '
                            .'Use "remove_subdomains" instead.',
                        ),
                    'reduce_wildcard_covered_domains' => Expect::bool()
                        ->deprecated(
                            'The "reduce_wildcard_covered_domains" flag is deprecated. '
                            .'Use "remove_wildcard_covered_domains" instead.',
                        ),
                ])->otherItems(Expect::bool()),
            ]),
        ]);
    }

    /**
     * @return \Nette\Schema\Elements\Structure
     */
    public static function builder()
    {
        return self::global()->extend([
            'builder' => Expect::structure([
                'output_dir' => Expect::string(),
                'filter_list' => Expect::listOf(Expect::structure([
                    'filename' => Expect::string(),
                    'remove_duplicates' => Expect::bool(),
                    'header' => Expect::string(),
                    'includes' => Expect::listOf('string'),
                    // @deprecated since v1.13.13
                    'source' => Expect::listOf('string')
                        ->deprecated(
                            'The "source" property in "filter_list" is deprecated. '
                            .'Use "includes" instead.',
                        ),
                ])),
            ]),
        ]);
    }

    /**
     * @return \Nette\Schema\Elements\Structure
     */
    public static function linter()
    {
        return self::global()->extend([
            'linter' => Expect::structure([
                'paths' => Expect::listOf('string'),
                'excludes' => Expect::listOf('string'),
                'rules' => Expect::structure([
                    'no_extra_blank_lines' => Expect::anyOf(Expect::int(), false),
                    'no_short_rules' => Expect::anyOf(Expect::int(), false),
                    'scriptlet_unknown' => Expect::anyOf(
                        Expect::bool(),
                        Expect::structure([
                            'known' => Expect::listOf('string')->min(1),
                        ])->castTo('array'),
                    ),
                ])->otherItems(Expect::bool()),
                'ignoreErrors' => Expect::listOf(
                    Expect::anyOf(
                        Expect::string(),
                        Expect::structure([
                            'message' => Expect::string(),
                            'messages' => Expect::listOf('string'),
                            'path' => Expect::string(),
                            'paths' => Expect::listOf('string'),
                        ])->castTo('array'),
                    ),
                ),
            ]),
        ]);
    }
}
