<?php

namespace Realodix\Haiku\Linter;

final class Registry
{
    /**
     * A list of known options.
     *
     * https://github.com/gorhill/uBlock/blob/2a0842f17/src/js/static-filtering-parser.js#L3132
     */
    const OPTIONS = [
        // must assign values
        'csp', 'denyallow', 'domain', 'from', 'header', 'ipaddress', 'method', 'permissions', 'reason', 'redirect-rule',
        'redirect', 'rewrite', 'replace', 'requestheader', 'responseheader', 'to', 'urlskip', 'urltransform', 'uritransform',
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
        'prevent-canvas',
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
    ];

    const DEPRECATED_SCRIPTLETS = [
        'aell', 'addEventListener-logger',
        'csp', 'no-floc', 'sharedWorker-defuser',
        'golem.de',
    ];

    const RESOURCES = [
        'amazon_ads.js', 'amazon-adsystem.com/aax2/amzn_ads.js',
        'amazon_apstag.js' => ['ag' => ['amazon-apstag']],
        'adthrive_abd.js',
        'doubleclick_instream_ad_status.js', 'doubleclick.net/instream/ad_status.js',
        'fingerprint2.js' => ['ag' => ['fingerprintjs2']],
        'fingerprint3.js' => ['ag' => ['fingerprintjs3']],
        'google-analytics_analytics.js' => ['alias' => ['google-analytics.com/analytics.js', 'googletagmanager_gtm.js', 'googletagmanager.com/gtm.js'], 'ag' => ['google-analytics']],
        'google-analytics_ga.js' => ['alias' => ['google-analytics.com/ga.js'], 'ag' => ['google-analytics-ga']],
        'google-ima.js', 'google-ima3',
        'googlesyndication_adsbygoogle.js', 'googlesyndication.com/adsbygoogle.js', 'googlesyndication-adsbygoogle',
        'googletagservices_gpt.js', 'googletagservices.com/gpt.js', 'googletagservices-gpt',
        'nitropay_ads.js',
        'nobab.js', 'bab-defuser.js', 'prevent-bab.js',
        'nobab2.js',
        'noeval.js',
        'noeval-silent.js', 'silent-noeval.js',
        'nofab.js' => ['alias' => ['fuckadblock.js-3.2.0'], 'ag' => ['prevent-fab-3.2.0']],
        'popads.js', 'popads.net.js', 'prevent-popads-net.js',
        'popads-dummy.js',
        'prebid-ads.js' => ['ag' => ['prebid-ads']],
        'sensors-analytics.js',
    ];

    const REDIRECT_RESOURCES = [
        '1x1.gif', '1x1-transparent.gif',
        '2x2.png', '2x2-transparent.png',
        '3x2.png', '3x2-transparent.png',
        '32x32.png', '32x32-transparent.png',
        'click2load.html',
        'empty',
        'none',
        'noop-0.1s.mp3', 'noopmp3-0.1s', 'abp-resource:blank-mp3',
        'noop-0.5s.mp3',
        'noop-1s.mp4', 'noopmp4-1s', 'abp-resource:blank-mp4',
        'noop.css' => ['ag' => ['noopcss']],
        'noop.html', 'noopframe',
        'noop.js', 'noopjs', 'abp-resource:blank-js',
        'noop.json', 'noopjson',
        'noop.txt', 'nooptext',
        'noop-vast2.xml', 'noopvast-2.0',
        'noop-vast3.xml', 'noopvast-3.0',
        'noop-vast4.xml', 'noopvast-4.0',
        'noop-vmap1.xml', 'noop-vmap1.0.xml', 'noopvmap-1.0',

        'ampproject_v0.js', 'ampproject.org/v0.js',
        'google-analytics_cx_api.js', 'google-analytics.com/cx/api.js',
        'google-analytics_inpage_linkid.js', 'google-analytics.com/inpage_linkid.js',
        'hd-main.js',
        'outbrain-widget.js', 'widgets.outbrain.com/outbrain.js',
        'scorecardresearch_beacon.js', 'scorecardresearch.com/beacon.js',

        // Deprecated, but found in uBlock-1.70.1b4
        'chartbeat.js', 'static.chartbeat.com/chartbeat.js',
    ];

    const DEPRECATED_REDIRECT_RESOURCES = [
        'ligatus_angular-tag.js', 'ligatus.com/*/angular-tag.js',
        'addthis_widget.js', 'addthis.com/addthis_widget.js',
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
     * https://github.com/gorhill/uBlock/blob/e2bd8c146c/src/js/static-filtering-parser.js#L600
     * https://adguard.com/kb/general/ad-filtering/create-own-filters/#preprocessor-directives
     */
    const PREPROCESSOR_DIRECTIVES = [
        'false', 'cap_html_filtering', 'cap_ipaddress', 'cap_user_stylesheet',
        'env_chromium', 'env_edge', 'env_firefox', 'env_legacy', 'env_mobile', 'env_mv3', 'env_safari', 'ext_abp', 'ext_devbuild', 'ext_ublock', 'ext_ubol',
        'adguard', 'adguard_app_android', 'adguard_app_ios', 'adguard_app_mac', 'adguard_app_windows', 'adguard_ext_android_cb', 'adguard_ext_chromium', 'adguard_ext_edge', 'adguard_ext_firefox', 'adguard_ext_opera', 'adguard_ext_safari',
        // adguard
        'adguard_app_cli', 'adguard_ext_chromium_mv3',
    ];
}
