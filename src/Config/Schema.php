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
                    'domain_order' => Expect::anyOf('normal', 'negated_first', 'localhost_first', 'localhost_negated_first'),
                    'option_format' => Expect::anyOf('long', 'short'),
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
}
