<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SimulationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $runId,
        public readonly string $type,
        public readonly string $simulationTime,
        public readonly array $ids = [],
        public readonly array $data = [],
        public readonly string $version = 'v1',
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('simulation.'.$this->runId)];
    }

    public function broadcastAs(): string
    {
        return $this->type;
    }

    public function broadcastWith(): array
    {
        return [
            'version' => $this->version,
            'run_id' => $this->runId,
            'type' => $this->type,
            'simulation_time' => $this->simulationTime,
            'ids' => $this->ids,
            'data' => $this->data + ['is_simulated' => true],
        ];
    }
}
