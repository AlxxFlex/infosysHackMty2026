<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiController extends Controller
{
    protected function ok(JsonResource|array $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json(['data' => $data instanceof JsonResource ? $data->resolve() : $data, 'meta' => $meta + ['is_simulated' => true]], $status);
    }
}
