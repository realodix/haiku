<?php

namespace Realodix\Haiku\Linter;

final class Registry
{
    /**
     * A list of known options.
     *
     * https://github.com/gorhill/uBlock/blob/1.71.0/src/js/static-filtering-parser.js#L3148
     */
    const OPTIONS = [
        // must assign values
        'csp', 'denyallow', 'domain', 'from', 'header', 'ipaddress', 'method', 'permissions', 'reason', 'redirect-rule',
        'redirect', 'rewrite', 'replace', 'requestheader', 'responseheader', 'to', 'top', 'urlskip', 'urltransform', 'uritransform',
        // basic
        'all', 'badfilter', 'cname', 'font', 'genericblock', 'image', 'important', 'inline-font', 'inline-script',
        'match-case', 'media', 'other', 'popunder', 'popup', 'script', 'websocket',
        '1p', 'first-party', 'strict1p', 'strict-first-party', '3p', 'third-party', 'strict3p', 'strict-third-party',
        'css', 'stylesheet', 'doc', 'document', 'ehide', 'elemhide', 'frame', 'subdocument', 'ghide', 'generichide',
        'object', 'ping', 'beacon', 'removeparam', 'shide', 'specifichide',
        'xhr', 'xmlhttprequest',
        // deprecated
        'empty', 'mp4', 'object-subrequest', 'queryprune', 'webrtc',
    ];

    const DOMAIN_OPTIONS = ['domain', 'from', 'to', 'top', 'denyallow'];

    /**
     * A list of known options from AdGuard.
     */
    const AG_OPTIONS = [
        'app', 'content', 'cookie', 'extension', 'hls', 'jsinject', 'jsonprune', 'network', 'path',
        'removeheader', 'referrerpolicy', 'stealth', 'url', 'urlblock', 'xmlprune',
        'client', 'ctag', 'dnstype', 'dnsrewrite', // Adg DNS
    ];

    const SCRIPTLETS = [
        'acs', 'abort-current-script', 'abort-current-inline-script', 'acis',
        'aopr', 'abort-on-property-read',
        'aopw', 'abort-on-property-write',
        'aost', 'abort-on-stack-trace',
        'aeld', 'addEventListener-defuser', 'prevent-addEventListener',
        'adjust-setTimeout', 'nano-setTimeout-booster', 'nano-stb',
        'alert-buster',
        'call-nothrow',
        'close-window', 'window-close-if',
        'disable-newtab-links',
        'edit-object-on-getter', 'edit-object-on-setter',
        'edit-this-object',
        'evaldata-prune',
        'freeze-element-property',
        'href-sanitizer',
        'json-edit', 'json-edit-fetch-request', 'json-edit-fetch-response', 'json-edit-xhr-response', 'jsonl-edit-xhr-response',
        'json-prune-fetch-response',
        'json-prune-xhr-response',
        'json-prune',
        'm3u-prune',
        'multiup',
        'noeval-if', 'prevent-eval-if',
        'noeval-silent',
        'noeval',
        'nowebrtc',
        'object-prune',
        'overlay-buster',
        'prevent-bab', 'nobab', 'bab-defuser',
        'prevent-canvas',
        'prevent-clipboard-write',
        'prevent-fetch', 'no-fetch-if',
        'prevent-innerHTML',
        'prevent-navigation',
        'prevent-refresh', 'refresh-defuser',
        'prevent-requestAnimationFrame', 'no-requestAnimationFrame-if', 'norafif',
        'prevent-setInterval', 'no-setInterval-if', 'nosiif', 'setInterval-defuser',
        'prevent-setTimeout', 'no-setTimeout-if', 'nostif', 'setTimeout-defuser',
        'prevent-window-open', 'nowoif', 'no-window-open-if', 'window.open-defuser',
        'prevent-xhr', 'no-xhr-if',
        'remove-attr', 'ra',
        'remove-cache-storage-item', 'adjust-setInterval', 'nano-setInterval-booster', 'nano-sib',
        'remove-class', 'rc',
        'remove-cookie', 'cookie-remover',
        'remove-node-text', 'rmnt',
        'replace-node-text', 'rpnt',
        'set-attr',
        'set-constant', 'set',
        'set-cookie', 'set-cookie-reload',
        'set-local-storage-item', 'set-session-storage-item',
        'spoof-css',
        'webrtc-if',
        'window.name-defuser',
        'xml-prune',
        'break-on-call',
    ];

    const DEPRECATED_SCRIPTLETS = [
        'aell', 'addEventListener-logger',
        'csp', 'no-floc', 'sharedWorker-defuser',
        'golem.de',
    ];

    /**
     * https://github.com/gorhill/uBlock/blob/1.74.0/src/js/redirect-resources.js
     */
    const REDIRECT_RESOURCE = [
        'none',
        '1x1.gif' => ['alias' => ['1x1-transparent.gif']],
        '2x2.png' => ['alias' => ['2x2-transparent.png']],
        '3x2.png' => ['alias' => ['3x2-transparent.png']],
        '32x32.png' => ['alias' => ['32x32-transparent.png']],
        'amazon_ads.js' => [
            'alias' => ['amazon-adsystem.com/aax2/amzn_ads.js'],
            'scriptlet' => true,
        ],
        'amazon_apstag.js' => ['alias' => ['amazon-apstag' /* AG */]],
        'ampproject_v0.js' => ['alias' => ['ampproject.org/v0.js']],
        'adthrive_abd.js' => ['scriptlet' => true],
        'click2load.html',
        'doubleclick_instream_ad_status.js' => [
            'alias' => ['doubleclick.net/instream/ad_status.js'],
            'scriptlet' => true,
        ],
        'empty',
        'fingerprint2.js' => ['alias' => ['fingerprintjs2'], 'scriptlet' => true],
        'fingerprint3.js' => ['alias' => ['fingerprintjs3'], 'scriptlet' => true],
        'google-analytics_analytics.js' => [
            'alias' => [
                'google-analytics.com/analytics.js',
                'googletagmanager_gtm.js',
                'googletagmanager.com/gtm.js',
                // AG
                'google-analytics',
            ],
            'scriptlet' => true,
        ],
        'google-analytics_cx_api.js' => ['alias' => ['google-analytics.com/cx/api.js']],
        'google-analytics_ga.js' => [
            'alias' => [
                'google-analytics.com/ga.js',
                // AG
                'google-analytics-ga',
            ],
            'scriptlet' => true,
        ],
        'google-analytics_inpage_linkid.js' => ['alias' => ['google-analytics.com/inpage_linkid.js']],
        'google-ima.js' => [
            'alias' => ['google-ima3'],
            'scriptlet' => true,
        ],
        'google-ima-dai.js' => [
            'alias' => ['google-ima3-dai'],
            'scriptlet' => true,
        ],
        'googlesyndication_adsbygoogle.js' => [
            'alias' => [
                'googlesyndication.com/adsbygoogle.js',
                'googlesyndication-adsbygoogle',
            ],
            'scriptlet' => true,
        ],
        'googletagservices_gpt.js' => [
            'alias' => [
                'googletagservices.com/gpt.js',
                'googletagservices-gpt',
            ],
            'scriptlet' => true,
        ],
        'hd-main.js',
        'nitropay_ads.js' => ['scriptlet' => true],
        'nobab2.js' => ['scriptlet' => true],
        'noeval.js' => ['scriptlet' => true],
        'noeval-silent.js', 'silent-noeval.js',
        'nofab.js' => [
            'alias' => [
                'fuckadblock.js-3.2.0',
                // AG
                'prevent-fab-3.2.0',
            ],
            'scriptlet' => true,
        ],
        'noop-0.1s.mp3' => ['alias' => ['noopmp3-0.1s', 'abp-resource:blank-mp3']],
        'noop-0.5s.mp3',
        'noop-1s.mp4' => ['alias' => ['noopmp4-1s', 'abp-resource:blank-mp4']],
        'noop.css' => ['alias' => ['noopcss' /* AG */]],
        'noop.html' => ['alias' => ['noopframe']],
        'noop.js' => ['alias' => ['noopjs', 'abp-resource:blank-js']],
        'noop.json' => ['alias' => ['noopjson']],
        'noop.txt' => ['alias' => ['nooptext']],
        'noop-vast2.xml' => ['alias' => ['noopvast-2.0']],
        'noop-vast3.xml' => ['alias' => ['noopvast-3.0']],
        'noop-vast4.xml' => ['alias' => ['noopvast-4.0']],
        'noop-vmap1.xml' => ['alias' => ['noop-vmap1.0.xml', 'noopvmap-1.0']],
        'outbrain-widget.js' => ['alias' => ['widgets.outbrain.com/outbrain.js']],
        'piano-analytics.js',
        'popads.js' => [
            'alias' => [
                'popads.net.js',
                'prevent-popads-net.js',
            ],
            'scriptlet' => true,
        ],
        'popads-dummy.js' => ['scriptlet' => true],
        'prebid-ads.js' => [
            'alias' => ['prebid-ads' /* AG */],
            'scriptlet' => true,
        ],

        'scorecardresearch_beacon.js' => ['alias' => ['scorecardresearch.com/beacon.js']],
        'sensors-analytics.js' => ['scriptlet' => true],

        // Deprecated, but found in uBlock-1.70.1b4
        'chartbeat.js' => ['alias' => ['static.chartbeat.com/chartbeat.js']],
    ];

    const DEPRECATED_REDIRECT_RESOURCES = [
        'addthis_widget.js', 'addthis.com/addthis_widget.js',
        'ligatus_angular-tag.js', 'ligatus.com/*/angular-tag.js',
        'monkeybroker.js', 'd3pkae9owd2lcf.cloudfront.net/mb105.js',
    ];

    const AG_REDIRECT_RESOURCES = [
        'ati-smarttag',
        'didomi-loader',
        'gemius',
        'metrika-yandex-tag',
        'metrika-yandex-watch',
        'prebid',
        'scorecardresearch-beacon',
    ];

    /**
     * https://github.com/gorhill/uBlock/blob/1.73.1b6/src/js/static-filtering-parser.js#L603
     * https://adguard.com/kb/general/ad-filtering/create-own-filters/#preprocessor-directives
     */
    const PREPROCESSOR_DIRECTIVES = [
        'ext_ublock',
        'ext_ubol',
        'ext_devbuild',
        'env_brave',
        'env_chromium',
        'env_edge',
        'env_firefox',
        'env_legacy',
        'env_mobile',
        'env_mv3',
        'env_safari',
        'cap_html_filtering',
        'cap_ipaddress',
        'false',
        'ext_abp',
        'adguard',
        'adguard_app_android',
        'adguard_app_cli',
        'adguard_app_ios',
        'adguard_app_mac',
        'adguard_app_windows',
        'adguard_ext_android_cb',
        'adguard_ext_chromium',
        'adguard_ext_chromium_mv3',
        'adguard_ext_edge',
        'adguard_ext_firefox',
        'adguard_ext_opera',
        'adguard_ext_safari',
        // https://github.com/gorhill/uBlock/commit/fb09b0947d
        // 'cap_user_stylesheet',
    ];

    /**
     * Helps find the correct value
     */
    const NORMALIZED_UNKNOWN = [
        // filter options
        'xml' => 'xhl',

        // redirect value
        'noopmp4' => 'noopmp4-',
    ];
}
