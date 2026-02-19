<?php

namespace Realodix\Haiku\Fixer;

final class Regex
{
    /**
     * Regex to capture the filter body and its options.
     *
     * @example ||example.com^$script,domain=example.org
     *
     * @link https://regex101.com/r/t2MFGs/1
     */
    // const NET_OPTION = '/^(.*)\$(~?[\w\-]+(?:=[^,\s]+)?(?:,~?[\w\-]+(?:=[^,\s]+)?)*)$/';
    // const NET_OPTION = '/^(.*)\$(~?[\w\-]+(?:=[^\s]+)?(?:,~?[\w\-]+(?:=[^\s]+)?)*)$/';
    const NET_OPTION = '/^(.*)\$(~?[\w\-]+(?:=.+)?(?:,~?[\w\-]+(?:=.+)?)*)$/';

    /**
     * Regex to find domain-related options in a network filter.
     */
    const NET_OPTION_DOMAIN = '/(?:\$|,)(?:denyallow|domain|from|method|to)\=([^,\s]+)$/';

    /**
     * Regex to identify and capture parts of an element hiding rule.
     *
     * @example example.com,example.org##.ad
     *
     * @link
     *  https://regex101.com/r/yY3a26/8
     *  https://regex101.com/r/4aHTZj
     */
    const COSMETIC_RULE = '/^((\[\$[^\]]+\])?([^\^$\\{\@\"\!]*?|\/.+\/))(#@?[$?]{1,2}#|#@?%#(?=\/\/)|#@?#[\^\+]?|\$\@?\$)(.*)$/';

    /**
     * Regex to find domains in element-hiding rules.
     *
     * @link https://regex101.com/r/2E6nAd
     */
    const COSMETIC_DOMAIN = '/^(?!##?\s)([^\/\|\@\"!]*?)(##|#[@?$%]{1,3}#|\$@?\$)/';

    /**
     * Regex to find AdGuard JS rules.
     *
     * @link https://regex101.com/r/K4VTwP/1
     */
    const AG_JS_RULE = '/^(?:\[\$[^\]]+\])?([^\/\|\@\"\!]*?)(#@?%#(?!\/\/scriptlet))(.*)$/';
}
