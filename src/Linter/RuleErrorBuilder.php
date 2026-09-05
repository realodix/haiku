<?php

namespace Realodix\Haiku\Linter;

/**
 * @phpstan-type _RuleError array{
 *  message: string,
 *  line: int,
 *  covered_by_line?: int,
 *  tip?: string,
 *  ruleId?: string,
 *  link?: string
 * }
 */
final class RuleErrorBuilder
{
    use \Illuminate\Support\Traits\Conditionable;

    private string $message;

    private int $line;

    private ?int $coverLine = null;

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

    /**
     * Set the line number of the rule that covers the current rule.
     */
    public function coverLine(int $line): self
    {
        $this->coverLine = $line;

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

        if ($this->coverLine !== null) {
            $error['covered_by_line'] = $this->coverLine;
        }

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
        $this->coverLine = null;
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
