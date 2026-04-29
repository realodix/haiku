<?php

namespace Realodix\Haiku\Linter\Rules\Preprocessor;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;

final class IfClosedCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        if (!$this->config->rules['pp_if_closed']) {
            return [];
        }

        $errors = [];
        /** @var list<array{line: int, type: string, hasElse: bool}> */
        $stack = [];

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if (preg_match('/^!#\s?if(?:\s|$)/i', $line)) {
                $stack[] = ['lineNum' => $lineNum, 'type' => 'if', 'hasElse' => false];

                continue;
            }

            if (preg_match('/^!#\s?else\s*$/i', $line)) {
                if (empty($stack)) {
                    $errors[] = RuleErrorBuilder::message('Found "!#else" without matching "!#if".')
                        ->line($lineNum)
                        ->build();

                    $stack[] = ['lineNum' => $lineNum, 'type' => 'else', 'hasElse' => true];
                } else {
                    $topIndex = count($stack) - 1;
                    if ($stack[$topIndex]['hasElse']) {
                        $errors[] = RuleErrorBuilder::message('Found multiple "!#else" for the same "!#if".')
                            ->line($lineNum)
                            ->build();
                    }
                    $stack[$topIndex]['hasElse'] = true;
                }

                continue;
            }

            if (preg_match('/^!#\s?endif\s*$/i', $line)) {
                if (empty($stack)) {
                    $errors[] = RuleErrorBuilder::message('Found "!#endif" without matching "!#if".')
                        ->line($lineNum)
                        ->build();
                } else {
                    array_pop($stack);
                }

                continue;
            }
        }

        foreach (array_reverse($stack) as $unclosed) {
            $directive = $unclosed['type'] === 'if' ? '!#if' : '!#else';
            $errors[] = RuleErrorBuilder::message(sprintf('The "%s" statement is not closed by "!#endif".', $directive))
                ->line($unclosed['lineNum'])
                ->build();
        }

        return $errors;
    }
}
