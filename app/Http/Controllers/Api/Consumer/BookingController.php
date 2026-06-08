<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    /**
     * Book a slot.
     *
     * POST /api/consumer/bookings
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->book(
            $request->user(),
            (int) $request->validated('slot_id'),
        );

        return $this->successResponse(
            new BookingResource($booking),
            'Booking confirmed successfully.',
            201
        );
    }

    /**
     * Cancel a booking (only before slot start time).
     *
     * PATCH /api/consumer/bookings/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $booking = $this->bookingService->cancel($request->user(), $id);

        return $this->successResponse(
            new BookingResource($booking),
            'Booking cancelled successfully.'
        );
    }
}
