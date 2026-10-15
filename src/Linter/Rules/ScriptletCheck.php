<?php

namespace Realodix\Haiku\Linter\Rules;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Support\Util;

final class ScriptletCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    // https://github.com/gorhill/uBlock/blob/dfca51bea2/src/js/resources/cookie.js#L28
    const COOKIE_VALUES = [
        'accept', 'reject',
        'accepted', 'rejected', 'notaccepted',
        'allow', 'disallow', 'deny',
        'allowed', 'denied',
        'approved', 'disapproved',
        'checked', 'unchecked',
        'dismiss', 'dismissed',
        'enable', 'disable',
        'enabled', 'disabled',
        'essential', 'nonessential',
        'forbidden', 'forever',
        'hide', 'hidden',
        'necessary', 'required',
        'ok',
        'on', 'off',
        'true', 't', 'false', 'f',
        'yes', 'y', 'no', 'n',
        'all', 'none', 'functional',
        'granted', 'done',
        'decline', 'declined',
        'closed', 'next', 'mandatory',
        'disagree', 'agree',
        'set', 'unset',
        'given',
    ];

    public function check(array $content, $err): array
    {
        foreach ($content as $index => $line) {
            $err->line($index + 1);
            $line = trim($line);

            if (Util::isCommentOrEmpty($line)) {
                continue;
            }

            if (preg_match('/\+js\((.+)\)$/', $line, $m)) {
                $parts = preg_split('/(?<!\\\),\s*/', $m[1]);
                if ($parts === false) {
                    continue;
                }

                $name = $this->normalizeParam($parts[0]);
                $value = $this->normalizeParam($parts[2] ?? '');

                if (str_starts_with($name, 'trusted-') || str_starts_with($name, 'brave-')) {
                    continue;
                }

                $this->checkUnknownName($err, $name);
                $this->checkDeprecated($err, $name);

                $this->checkSetConstantValue($err, $name, $value);
                $this->checkSetCookieValue($err, $name, $value);
                $this->checkSetLocalStorageItemValue($err, $name, $value);
            }
        }

        return $err->toArray();
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkUnknownName($err, string $value): void
    {
        if ($this->config->rules['no_invalid_scriptlets'] === false) {
            return;
        }

        $scriptlets = $this->getScriptletNames();
        if (!in_array($value, $scriptlets, true)) {
            $hint = Util::getSuggestion($scriptlets, $value);
            $err->message("Invalid scriptlet: {$value}")
                ->identifier('scriptlet.invalid')
                ->when($hint, fn() => $err->tip("Did you mean \"{$hint}\"?"))
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkDeprecated($err, string $value): void
    {
        if (!$this->config->rules['no_deprecated_scriptlets']) {
            return;
        }

        if (in_array($value, Registry::DEPRECATED_SCRIPTLETS, true)) {
            $err->message("Deprecated: Scriptlet {$value} is deprecated.")
                ->identifier('scriptlet.deprecated')
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkSetConstantValue($err, string $name, string|int $value): void
    {
        if ($name !== 'set' && $name !== 'set-constant') {
            return;
        }

        if (is_int($value)) {
            return;
        }

        $values = [
            // https://github.com/gorhill/uBlock/blob/dfca51bea2/src/js/resources/localstorage.js#L55
            'emptyArr', 'emptyObj',
            // https://github.com/gorhill/uBlock/blob/dfca51bea2/src/js/resources/localstorage.js#L61
            // https://github.com/gorhill/uBlock/blob/dfca51bea2/src/js/resources/set-constant.js#L29
            'undefined', 'true', 'false', 'null', 'noopFunc', 'trueFunc', 'falseFunc',
            'throwFunc', '[]', '{}', "''", '',
        ];

        if (!in_array($value, $values, true)) {
            $hint = Util::getSuggestion($values, $value);
            $err->message("Invalid set-constant value: {$value}")
                ->identifier('scriptlet.invalidValue')
                ->when($hint, fn() => $err->tip("Did you mean \"{$hint}\"?"))
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkSetCookieValue($err, string $name, string|int $value): void
    {
        if ($name !== 'set-cookie' && $name !== 'set-cookie-reload') {
            return;
        }

        if (is_int($value)) {
            return;
        }

        $value = strtolower($value);

        $values = self::COOKIE_VALUES;
        if (!in_array($value, $values, true)) {
            $hint = Util::getSuggestion($values, $value);
            $err->message("Invalid set-cookie value: {$value}")
                ->identifier('scriptlet.invalidValue')
                ->when($hint, fn() => $err->tip("Did you mean \"{$hint}\"?"))
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkSetLocalStorageItemValue($err, string $name, string|int $value): void
    {
        if ($name !== 'set-local-storage-item' && $name !== 'set-session-storage-item') {
            return;
        }

        if (is_int($value)) {
            return;
        }

        $value = strtolower($value);

        // https://github.com/gorhill/uBlock/blob/dfca51bea2/src/js/resources/localstorage.js#L61
        $values = [
            '',
            'undefined', 'null',
            '{}', '[]', '""',
            '$remove$',
            ...self::COOKIE_VALUES,
        ];

        if (!in_array($value, $values, true)) {
            $hint = Util::getSuggestion($values, $value);
            $err->message("Invalid set-local-storage-item value: {$value}")
                ->identifier('scriptlet.invalidValue')
                ->when($hint, fn() => $err->tip("Did you mean \"{$hint}\"?"))
                ->build();
        }
    }

    /**
     * Retrieves a list of unique scriptlet names.
     *
     * @return array<int, string> A list of unique scriptlet names
     */
    private function getScriptletNames(): array
    {
        $config = $this->config->rules['no_invalid_scriptlets'];
        $resources = array_map(
            fn($name) => str_ends_with($name, '.js') ? substr($name, 0, -3) : $name,
            Util::getRedirectResources(scriptlet: true),
        );

        return array_unique([
            ...$resources,
            ...Registry::SCRIPTLETS,
            ...Registry::DEPRECATED_SCRIPTLETS,
            ...$config['known'] ?? [],
        ]);
    }

    private function normalizeParam(string|int $param): int|string
    {
        $param = trim($param);

        // remove wrapping quotes (single or double)
        if ((str_starts_with($param, '"') && str_ends_with($param, '"'))
            || (str_starts_with($param, "'") && str_ends_with($param, "'"))
        ) {
            $param = substr($param, 1, -1);
            $param = trim($param);
        }

        if (preg_match('/^-?\d+$/', $param)) {
            return (int) $param;
        }

        // remove .js suffix
        if (str_ends_with($param, '.js')) {
            $param = substr($param, 0, -3);
        }

        return $param;
    }
}
