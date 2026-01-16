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
     *
     * @var string
     */
    // const NET_OPTION = '/^(.*)\$(~?[\w\-]+(?:=[^,\s]+)?(?:,~?[\w\-]+(?:=[^,\s]+)?)*)$/';
    // const NET_OPTION = '/^(.*)\$(~?[\w\-]+(?:=[^\s]+)?(?:,~?[\w\-]+(?:=[^\s]+)?)*)$/';
    const NET_OPTION = '/^(.*)\$(~?[\w\-]+(?:=.+)?(?:,~?[\w\-]+(?:=.+)?)*)$/';

    /**
     * Regex to find domain-related options in a network filter.
     *
     * @var string
     */
    const NET_OPTION_DOMAIN = '/(?:\$|,)(?:denyallow|domain|from|method|to)\=([^,\s]+)$/';

    /**
     * Regex to safely split a network filter's options.
     *
     * @link https://regex101.com/r/Cwwmct/2
     *
     * @var string
     */
    const NET_OPTION_SPLIT = '/(?<!\\\),(?=[a-zA-Z~,]|(?:1p|3p)|$)/';

    /**
     * Regex to identify and capture parts of an element hiding rule.
     *
     * @example example.com,example.org##.ad
     *
     * @link
     *  https://regex101.com/r/yY3a26/8
     *  https://regex101.com/r/4aHTZj
     *
     * @var string
     */
    const COSMETIC_RULE = '/^((\[\$[^\]]+\])?([^\^$\\{\@\"\!]*?|\/.+\/))(#@?[$?]{1,2}#|#@?%#(?=\/\/)|#@?#[\^\+]?|\$\@?\$)(.*)$/';

    /**
     * Regex to find domains in element-hiding rules.
     *
     * @link https://regex101.com/r/2E6nAd
     *
     * @var string
     */
    const COSMETIC_DOMAIN = '/^(?!##?\s)([^\/\|\@\"!]*?)(##|#[@?$%]{1,3}#|\$@?\$)/';

    /**
     * Regex to find AdGuard JS rules.
     *
     * @link https://regex101.com/r/K4VTwP/1
     *
     * @var string
     */
    const AG_JS_RULE = '/^(?:\[\$[^\]]+\])?([^\/\|\@\"\!]*?)(#@?%#(?!\/\/scriptlet))(.*)$/';
}
