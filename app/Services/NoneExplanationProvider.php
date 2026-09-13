<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ExplanationProvider;

final class NoneExplanationProvider implements ExplanationProvider
{
    public function explain(array $facts): ?string
    {
        return null;
    }

    public function name(): string
    {
        return 'none';
    }
}
