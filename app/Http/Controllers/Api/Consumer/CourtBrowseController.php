<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourtResource;
use App\Http\Resources\CourtSlotResource;
use App\Services\CourtService;
use App\Services\SlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourtBrowseController extends Controller
{
    public function __construct(
        private readonly CourtService $courtService,
        private readonly SlotService $slotService,
    ) {
    }

    /**
     * List active courts for consumers to browse.
     *
     * GET /api/consumer/courts
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['sport_type', 'search']);
        $filters['is_active'] = true; // consumers only ever see active courts

        $courts = $this->courtService->list($filters, (int) $request->integer('per_page', 15));

        return $this->successResponse(
            CourtResource::collection($courts)->response()->getData(true),
            'Courts retrieved successfully.'
        );
    }

    /**
     * List available (un-booked, future) slots for a given court.
     *
     * GET /api/consumer/courts/{court}/available-slots?date=YYYY-MM-DD
     */
    public function availableSlots(Request $request, int $court): JsonResponse
    {
        $slots = $this->slotService->availableForCourt($court, $request->query('date'));

        return $this->successResponse(
            CourtSlotResource::collection($slots),
            'Available slots retrieved successfully.'
        );
    }
}
