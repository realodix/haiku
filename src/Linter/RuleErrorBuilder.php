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

    /** @var list<_RuleError> */
    private array $errors = [];

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
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

    public function build(): void
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

        $this->errors[] = $error;

        // Reset state for the next error
        unset($this->message);
        $this->identifier = null;
        $this->tip = null;
        $this->link = null;
    }

    /**
     * @return list<_RuleError>
     */
    public function toArray(): array
    {
        return $this->errors;
    }
}
