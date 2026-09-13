<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\ShiftResource;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;

class ShiftController extends ApiController
{
    public function show(Shift $shift): JsonResponse
    {
        return $this->ok(new ShiftResource($shift));
    }

    public function orders(Shift $shift): JsonResponse
    {
        $orders = $shift->shiftOrders()->with('order')->get()->map(function ($so) {
            $item = (new OrderResource($so->order))->resolve();
            $item['shift_order_id'] = $so->id;
            $item['status'] = $so->status->value;

            return $item;
        });

        return $this->ok($orders->values()->all(), 200, ['count' => $orders->count()]);
    }
}
