<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Recommendation;
use App\Services\ExplanationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class GenerateLlmExplanation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<string, mixed> $facts */
    public function __construct(public readonly int $recommendationId, public readonly array $facts) {}

    public function handle(ExplanationService $explanations): void
    {
        $recommendation = Recommendation::query()->find($this->recommendationId);
        if ($recommendation === null) {
            return;
        }
        $output = $explanations->llm($this->facts);
        if ($output === null) {
            return;
        }
        // The asynchronous provider can only fill explanatory text; it cannot alter ranking or plan fields.
        $recommendation->forceFill(['llm_explanation' => $output])->save();
    }
}
