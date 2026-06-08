<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Slot\BulkStoreSlotRequest;
use App\Http\Requests\Slot\StoreSlotRequest;
use App\Http\Requests\Slot\UpdateSlotRequest;
use App\Http\Resources\CourtSlotResource;
use App\Services\SlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourtSlotController extends Controller
{
    public function __construct(
        private readonly SlotService $slotService,
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
     * Create a slot for a court (overlap-protected).
     *
     * POST /api/admin/slots
     */
    public function store(StoreSlotRequest $request): JsonResponse
    {
        $slot = $this->slotService->create($request->validated());

        return $this->successResponse(
            new CourtSlotResource($slot),
            'Slot created successfully.',
            201
        );
    }

    /**
     * Bulk-generate slots for a court over a date range (avoids creating them one by one).
     *
     * POST /api/admin/slots/bulk
     */
    public function bulkStore(BulkStoreSlotRequest $request): JsonResponse
    {
        $result = $this->slotService->generateBulk($request->validated());

        return $this->successResponse(
            [
                'created_count' => $result['created_count'],
                'skipped_count' => $result['skipped_count'],
                'slots'         => CourtSlotResource::collection($result['created']),
            ],
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
