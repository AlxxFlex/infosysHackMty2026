<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\OptimizedPlan;
use App\Exceptions\InvalidPlanExecution;
use App\Http\Requests\Api\V1\AcceptPlanRequest;
use App\Http\Resources\Api\V1\DeliveryResource;
use App\Models\Recommendation;
use App\Models\Shift;
use App\Services\PlanExecutionService;
use Illuminate\Http\JsonResponse;

class PlanController extends ApiController
{
    public function __construct(private readonly PlanExecutionService $execution) {}

    public function accept(AcceptPlanRequest $request, Shift $shift): JsonResponse
    {
        $recommendation = null;
        if ($request->filled('recommendation_id')) {
            $recommendation = Recommendation::query()->where('shift_id', $shift->id)->findOrFail($request->integer('recommendation_id'));
            $payload = $recommendation->metrics + ['order_ids' => $recommendation->selected_order_ids, 'sequence' => $recommendation->route_sequence, 'feasible' => true];
        } else {
            $payload = (array) $request->input('plan', []);
            if ($payload === []) {
                throw new InvalidPlanExecution('A complete plan or recommendation is required.');
            } if ($request->filled('order_ids')) {
                $payload['order_ids'] = $request->input('order_ids');
            } if ($request->filled('sequence')) {
                $payload['sequence'] = $request->input('sequence');
            }
        } $plan = OptimizedPlan::fromArray($payload);
        $delivery = $this->execution->acceptAndExecute($shift, $shift->simulationRun, $plan, $recommendation, $request->string('idempotency_key')->toString());

        return $this->ok(new DeliveryResource($delivery), 201, ['idempotency_key' => $request->string('idempotency_key')->toString()]);
    }
}
