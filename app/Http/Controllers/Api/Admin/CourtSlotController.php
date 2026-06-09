<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Slot\StoreSlotRequest;
use App\Http\Requests\Slot\UpdateSlotRequest;
use App\Http\Resources\CourtSlotResource;
use App\Services\ScheduleService;
use App\Services\SlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourtSlotController extends Controller
{
    public function __construct(
        private readonly SlotService $slotService,
        private readonly ScheduleService $scheduleService,
    ) {
    }

    /**
     * List slots (admin), filterable by court/date/booked state.
     *
     * GET /api/admin/slots
     */
    public function index(Request $request): JsonResponse
    {
        $slots = $this->slotService->list(
            $request->only(['court_id', 'date', 'is_booked']),
            (int) $request->integer('per_page', 15),
        );

        return $this->successResponse(
            CourtSlotResource::collection($slots)->response()->getData(true),
            'Slots retrieved successfully.'
        );
    }

    /**
     * Create slots for a court = generate its recurring weekly slots from the schedule.
     * Replaces the old one-slot-at-a-time create.
     *
     * POST /api/admin/slots  { "court_id": 1 }
     */
    public function store(StoreSlotRequest $request): JsonResponse
    {
        $result = $this->scheduleService->generateRecurringSlots(
            (int) $request->validated()['court_id'],
        );

        return $this->successResponse(
            $result,
            "Created {$result['created_count']} recurring slot(s) ({$result['existing_count']} already existed).",
            201
        );
    }

    /**
     * Update a slot.
     *
     * PUT/PATCH /api/admin/slots/{id}
     */
    public function update(UpdateSlotRequest $request, int $id): JsonResponse
    {
        $slot = $this->slotService->update($id, $request->validated());

        return $this->successResponse(
            new CourtSlotResource($slot),
            'Slot updated successfully.'
        );
    }

    /**
     * Delete a slot.
     *
     * DELETE /api/admin/slots/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->slotService->delete($id);

        return $this->successResponse(null, 'Slot deleted successfully.');
    }
}
