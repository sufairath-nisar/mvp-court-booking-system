<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\GenerateSlotsRequest;
use App\Http\Requests\Schedule\SetCourtScheduleRequest;
use App\Http\Requests\Schedule\StoreScheduleExceptionRequest;
use App\Http\Resources\CourtScheduleExceptionResource;
use App\Http\Resources\CourtScheduleResource;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;

class CourtScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
    ) {
    }

    /**
     * GET /api/admin/courts/{court}/schedule
     */
    public function showSchedule(int $court): JsonResponse
    {
        return $this->successResponse(
            CourtScheduleResource::collection($this->scheduleService->getWeeklySchedule($court)),
            'Weekly schedule retrieved successfully.'
        );
    }

    /**
     * PUT /api/admin/courts/{court}/schedule
     */
    public function updateSchedule(SetCourtScheduleRequest $request, int $court): JsonResponse
    {
        $schedule = $this->scheduleService->setWeeklySchedule($court, $request->validated()['schedule']);

        return $this->successResponse(
            CourtScheduleResource::collection($schedule),
            'Weekly schedule updated successfully.'
        );
    }

    /**
     * GET /api/admin/courts/{court}/schedule-exceptions
     */
    public function listExceptions(int $court): JsonResponse
    {
        return $this->successResponse(
            CourtScheduleExceptionResource::collection($this->scheduleService->listExceptions($court)),
            'Schedule exceptions retrieved successfully.'
        );
    }

    /**
     * POST /api/admin/courts/{court}/schedule-exceptions
     */
    public function storeException(StoreScheduleExceptionRequest $request, int $court): JsonResponse
    {
        $exception = $this->scheduleService->addException($court, $request->validated());

        return $this->successResponse(
            new CourtScheduleExceptionResource($exception),
            'Schedule exception saved successfully.',
            201
        );
    }

    /**
     * DELETE /api/admin/courts/{court}/schedule-exceptions/{exception}
     */
    public function destroyException(int $court, int $exception): JsonResponse
    {
        $this->scheduleService->deleteException($court, $exception);

        return $this->successResponse(null, 'Schedule exception deleted successfully.');
    }

    /**
     * POST /api/admin/courts/{court}/generate-slots
     *
     * Build concrete slots from the weekly schedule (+ exceptions) for a date range.
     */
    public function generate(GenerateSlotsRequest $request, int $court): JsonResponse
    {
        $data = $request->validated();

        $result = $this->scheduleService->generateSlots($court, $data['start_date'], $data['end_date']);

        return $this->successResponse(
            $result,
            "Generated {$result['created_count']} slot(s); skipped {$result['skipped_count']} overlapping.",
            201
        );
    }
}
