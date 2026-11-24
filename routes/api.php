<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarrierController;
use App\Http\Controllers\Api\ExceptionController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\TradeLaneController;
use Illuminate\Support\Facades\Route;

// All routes below are served under /api (prefix added by bootstrap/app.php).

Route::post('register', [AuthController::class, 'register'])->middleware('throttle:login');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);

    // Named api.shipments.* so they never collide with the web routes named shipments.* (route() would otherwise point web links at /api/...)
    Route::apiResource('shipments', ShipmentController::class)->names('api.shipments');

    Route::get('shipments/{shipment}/tracking', [TrackingController::class, 'index']);
    Route::post('shipments/{shipment}/tracking', [TrackingController::class, 'store']);

    Route::get('shipments/{shipment}/exceptions', [ExceptionController::class, 'index']);
    Route::post('shipments/{shipment}/exceptions', [ExceptionController::class, 'store']);
    Route::patch('exceptions/{shipmentException}', [ExceptionController::class, 'update']);

    Route::get('trade-lanes', [TradeLaneController::class, 'index']);
    Route::get('carriers', [CarrierController::class, 'index']);
    Route::get('locations', [LocationController::class, 'index']);

    Route::middleware('role:admin')->group(function () {
        Route::post('carriers', [CarrierController::class, 'store']);
        Route::post('locations', [LocationController::class, 'store']);
    });
});
