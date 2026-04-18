<?php

namespace Realodix\Haiku\Linter;

/**
 * @phpstan-type _RuleError array{
 *  message: string,
 *  line: int,
 *  tip?: string,
 *  ruleId?: string,
 *  link?: string
 * }
 */
final class RuleErrorBuilder
{
    private string $message;

    private int $line;

    private ?string $identifier = null;

    private ?string $tip = null;

    private ?string $link = null;

    private function __construct(string $message)
    {
        $this->message = $message;
    }

    public static function message(string $message): self
    {
        return new self($message);
    }

    public function line(int $line): self
    {
        $this->line = $line;

        return $this;
    }

    public function tip(string $tip): self
    {
        $this->tip = $tip;

        return $this;
    }

    public function identifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function link(string $link): self
    {
        $this->link = $link;

        return $this;
    }

    /**
     * @return _RuleError
     */
    public function build(): array
    {
        $error = [
            'message' => $this->message,
            'line' => $this->line,
        ];

        if ($this->identifier !== null) {
            $error['ruleId'] = $this->identifier;
        }

        if ($this->tip !== null) {
            $error['tip'] = $this->tip;
        }

        if ($this->link !== null) {
            $error['link'] = $this->link;
        }

        return $error;
    }
}
