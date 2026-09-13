<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventType;
use App\Http\Requests\Api\V1\CreateSimulationRequest;
use App\Http\Requests\Api\V1\ScheduleEventRequest;
use App\Http\Requests\Api\V1\TickSimulationRequest;
use App\Http\Resources\Api\V1\SimulationEventResource;
use App\Http\Resources\Api\V1\SimulationResource;
use App\Models\SimulationRun;
use App\Services\ShiftService;
use App\Services\SimulationEventService;
use App\Services\SimulatorService;
use Illuminate\Http\JsonResponse;

class SimulationController extends ApiController
{
    public function __construct(private readonly ShiftService $shifts, private readonly SimulatorService $simulator, private readonly SimulationEventService $events) {}

    public function store(CreateSimulationRequest $request): JsonResponse
    {
        $run = $this->shifts->createRun($request->string('scenario_key')->toString(), $request->integer('seed'), $request->date('simulated_started_at'), $request->input('config_snapshot'));

        return $this->ok(new SimulationResource($run), 201, ['created' => true]);
    }

    public function show(SimulationRun $run): JsonResponse
    {
        return $this->ok(new SimulationResource($run->load('shifts')));
    }

    public function start(SimulationRun $run): JsonResponse
    {
        return $this->ok(new SimulationResource($this->shifts->startRun($run)));
    }

    public function pause(SimulationRun $run): JsonResponse
    {
        return $this->ok(new SimulationResource($this->shifts->pauseRun($run)));
    }

    public function resume(SimulationRun $run): JsonResponse
    {
        return $this->ok(new SimulationResource($this->shifts->resumeRun($run)));
    }

    public function tick(TickSimulationRequest $request, SimulationRun $run): JsonResponse
    {
        $updated = $this->simulator->tick($run, $request->integer('minutes'), $request->string('idempotency_key')->toString());

        return $this->ok(new SimulationResource($updated->load('shifts')), 200, ['idempotency_key' => $request->string('idempotency_key')->toString()]);
    }

    public function finish(SimulationRun $run): JsonResponse
    {
        return $this->ok(new SimulationResource($this->shifts->finishRun($run)));
    }

    public function events(ScheduleEventRequest $request, SimulationRun $run): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        $at = $request->filled('scheduled_at')
            ? $request->date('scheduled_at')
            : $run->simulated_started_at->copy()->addMinutes($request->integer('scheduled_minute'));
        $event = $this->events->schedule($run, EventType::from($request->string('type')->toString()), $at, $request->input('payload'), $request->integer('sequence', 0));

        return $this->ok(new SimulationEventResource($event), 201);
    }
}
