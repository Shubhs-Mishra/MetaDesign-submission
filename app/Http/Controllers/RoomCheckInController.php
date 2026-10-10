<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckInRequest;
use App\Models\Tenant;
use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;

class RoomCheckInController extends Controller
{
    public function store(
        StoreCheckInRequest $request,
        string $room,
        CheckInService $service
    ): JsonResponse {
        $checkIn = $service->checkIn(
            $request->attributes->get('tenant'),
            (int) $room,
            (int) $request->validated('child_id')
        );

        return response()->json(['data' => $checkIn], 201);
    }
}