<?php

use App\Http\Controllers\Api\Admin\CourtController;
use App\Http\Controllers\Api\Admin\CourtScheduleController;
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
        Route::post('courts/{court}/image', [CourtController::class, 'uploadImage']);
        Route::apiResource('courts', CourtController::class);

        // Weekly schedule (recurring template) & date exceptions (Eid/holidays)
        Route::get('courts/{court}/schedule', [CourtScheduleController::class, 'showSchedule']);
        Route::put('courts/{court}/schedule', [CourtScheduleController::class, 'updateSchedule']);
        Route::patch('courts/{court}/schedule', [CourtScheduleController::class, 'updateScheduleDay']);
        Route::get('courts/{court}/schedule-exceptions', [CourtScheduleController::class, 'listExceptions']);
        Route::post('courts/{court}/schedule-exceptions', [CourtScheduleController::class, 'storeException']);
        Route::delete('courts/{court}/schedule-exceptions/{exception}', [CourtScheduleController::class, 'destroyException']);

        // Slot management. `POST /admin/slots` generates slots from the court's
        // schedule (replaces the old one-at-a-time create); no `show`.
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
