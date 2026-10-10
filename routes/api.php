<?php

use App\Http\Controllers\CheckOutController;
use App\Http\Controllers\RoomCheckInController;
use App\Http\Controllers\RoomOccupantController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

Route::middleware(ResolveTenant::class)->group(function () {
    Route::post('/rooms/{room}/check-ins', [RoomCheckInController::class, 'store']);
    Route::post('/check-ins/{checkIn}/check-out', [CheckOutController::class, 'store']);
    Route::get('/rooms/{room}/occupants', [RoomOccupantController::class, 'index']);
});