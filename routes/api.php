<?php

use App\Http\Controllers\Api\Admin\CourtController;
use App\Http\Controllers\Api\Admin\CourtSlotController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Consumer\BookingController;
use App\Http\Controllers\Api\Consumer\CourtBrowseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public authentication routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin routes (role: admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->group(function () {
        // Court management
        Route::apiResource('courts', CourtController::class);

        // Slot management (no `show` — slots are listed/browsed in bulk)
        Route::apiResource('slots', CourtSlotController::class)->except(['show']);
    });

/*
|--------------------------------------------------------------------------
| Consumer routes (role: consumer)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:consumer'])
    ->prefix('consumer')
    ->group(function () {
        // Browse courts & available slots
        Route::get('courts', [CourtBrowseController::class, 'index']);
        Route::get('courts/{court}/available-slots', [CourtBrowseController::class, 'availableSlots']);

        // Bookings
        Route::post('bookings', [BookingController::class, 'store']);
        Route::patch('bookings/{id}/cancel', [BookingController::class, 'cancel']);
    });
