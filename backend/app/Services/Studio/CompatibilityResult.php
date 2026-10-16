<?php

namespace App\Services\Studio;

final class CompatibilityResult
{
    /**
     * @param  list<array<string, mixed>>  $violations
     * @param  list<array<string, mixed>>  $blockedOptions
     * @param  list<array<string, mixed>>  $requiredOptions
     * @param  list<array<string, mixed>>  $warnings
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly array $violations = [],
        public readonly array $blockedOptions = [],
        public readonly array $requiredOptions = [],
        public readonly array $warnings = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'blocked' => ! $this->allowed,
            'violations' => $this->violations,
            'blocked_options' => $this->blockedOptions,
            'required_options' => $this->requiredOptions,
            'warnings' => $this->warnings,
        ];
    }
}
