<?php

namespace App\Http\Controllers;

use App\Services\RoomOccupantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomOccupantController extends Controller
{
    public function index(
        Request $request,
        string $room,
        RoomOccupantService $service
    ): JsonResponse {
        $occupants = $service->getOccupants(
            $request->attributes->get('tenant'),
            (int) $room
        );

        return response()->json(['data' => $occupants]);
    }
}