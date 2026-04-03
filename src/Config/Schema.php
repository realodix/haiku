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
                    'domain_order' => Expect::anyOf('name', 'normal', 'negated_first', 'localhost_first', 'localhost_negated_first'),
                    'option_format' => Expect::anyOf('native', 'long', 'short'),
                    'option_order' => Expect::anyOf('name', 'type', false),
                    'remove_empty_lines' => Expect::anyOf(true, false, 'keep_before_comment'),
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
                    'source' => Expect::listOf('string'),
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
                    'check_unknown_scriptlet' => Expect::anyOf(
                        Expect::bool(),
                        Expect::structure([
                            'known' => Expect::listOf('string')->min(1),
                        ])->castTo('array'),
                    ),
                ])->otherItems(Expect::bool()),
            ]),
        ]);
    }
}
