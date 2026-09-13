<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\RecommendationRequest;
use App\Http\Resources\Api\V1\RecommendationResource;
use App\Models\Shift;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;

class RecommendationController extends ApiController
{
    public function __construct(private readonly RecommendationService $recommendations) {}

    public function store(RecommendationRequest $request, Shift $shift): JsonResponse
    {
        $result = $this->recommendations->recommend($shift, $shift->simulationRun, $request->date('simulation_time'));

        return $this->ok(new RecommendationResource($result['recommendation']), 201, ['ranking' => array_map(fn ($item) => $item->toArray(), $result['ranking']), 'plan' => $result['plan']->toArray(), 'facts' => $result['facts']]);
    }
}
