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
     * Create slots for a court by generating them from its weekly schedule
     * (+ exceptions) across a date range. Replaces the old one-slot-at-a-time create.
     *
     * POST /api/admin/slots
     */
    public function store(StoreSlotRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->scheduleService->generateSlots(
            (int) $data['court_id'],
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['exclude_dates'] ?? [],
            $data['preview'] ?? false,
            $data['days'] ?? null,
        );

        if (! empty($result['preview'])) {
            return $this->successResponse(
                $result,
                "Preview: {$result['would_create']} slot(s) would be generated, {$result['would_skip']} skipped.",
                200
            );
        }

        return $this->successResponse(
            $result,
            "Generated {$result['created_count']} slot(s); skipped {$result['skipped_count']} overlapping.",
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
