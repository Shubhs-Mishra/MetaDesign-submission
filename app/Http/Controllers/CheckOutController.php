<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\CheckOutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckOutController extends Controller
{
    public function store(
        Request $request,
        string $checkIn,
        CheckOutService $service
    ): JsonResponse {
        $result = $service->checkOut(
            $request->attributes->get('tenant'),
            (int) $checkIn
        );

        return response()->json(['data' => $result]);
    }
}