<?php

namespace Realodix\Haiku\Linter\Rules\Preprocessor;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Linter\Rules\Rule;

final class IfClosedCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content, $err): array
    {
        if (!$this->config->rules['if_directive_balance']) {
            return [];
        }

        /** @var list<array{lineNum: int, type: string, hasElse: bool}> */
        $stack = [];

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $err->line($lineNum);
            $line = trim($line);

            if (str_starts_with($line, '!#if')) {
                $stack[] = ['lineNum' => $lineNum, 'type' => 'if', 'hasElse' => false];

                continue;
            }

            if (str_starts_with($line, '!#else')) {
                if (empty($stack)) {
                    $err->message('Unexpected !#else without matching !#if.')
                        ->build();

                    $stack[] = ['lineNum' => $lineNum, 'type' => 'else', 'hasElse' => true];
                } else {
                    $topIndex = count($stack) - 1;
                    if ($stack[$topIndex]['hasElse']) {
                        $err->message('Multiple !#else directives for the same !#if.')
                            ->build();
                    }
                    $stack[$topIndex]['hasElse'] = true;
                }

                continue;
            }

            if (str_starts_with($line, '!#endif')) {
                if (empty($stack)) {
                    $err->message('Unexpected !#endif without matching !#if.')
                        ->build();
                } else {
                    array_pop($stack);
                }

                continue;
            }
        }

        foreach (array_reverse($stack) as $unclosed) {
            $directive = $unclosed['type'] === 'if' ? '!#if' : '!#else';
            $err->message("Unclosed {$directive} directive.")
                ->line($unclosed['lineNum'])
                ->build();
        }

        return $err->toArray();
    }
}
