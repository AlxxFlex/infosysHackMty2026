<?php

declare(strict_types=1);

namespace App\Contracts;

interface ExplanationProvider
{
    /** @param array<string, mixed> $facts */
    public function explain(array $facts): ?string;

    public function name(): string;
}
