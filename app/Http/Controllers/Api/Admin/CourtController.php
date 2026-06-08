<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Court\StoreCourtRequest;
use App\Http\Requests\Court\UpdateCourtRequest;
use App\Http\Resources\CourtResource;
use App\Services\CourtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourtController extends Controller
{
    public function __construct(
        private readonly CourtService $courtService,
    ) {
    }

    /**
     * List courts (admin view, includes inactive).
     *
     * GET /api/admin/courts
     */
    public function index(Request $request): JsonResponse
    {
        $courts = $this->courtService->list(
            $request->only(['sport_type', 'is_active', 'search']),
            (int) $request->integer('per_page', 15),
        );

        return $this->successResponse(
            CourtResource::collection($courts)->response()->getData(true),
            'Courts retrieved successfully.'
        );
    }

    /**
     * Show a single court.
     *
     * GET /api/admin/courts/{id}
     */
    public function show(int $id): JsonResponse
    {
        return $this->successResponse(
            new CourtResource($this->courtService->find($id)),
            'Court retrieved successfully.'
        );
    }

    /**
     * Create a court.
     *
     * POST /api/admin/courts
     */
    public function store(StoreCourtRequest $request): JsonResponse
    {
        $court = $this->courtService->create($request->validated());

        return $this->successResponse(
            new CourtResource($court),
            'Court created successfully.',
            201
        );
    }

    /**
     * Update a court.
     *
     * PUT/PATCH /api/admin/courts/{id}
     */
    public function update(UpdateCourtRequest $request, int $id): JsonResponse
    {
        $court = $this->courtService->update($id, $request->validated());

        return $this->successResponse(
            new CourtResource($court),
            'Court updated successfully.'
        );
    }

    /**
     * Delete a court.
     *
     * DELETE /api/admin/courts/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->courtService->delete($id);

        return $this->successResponse(null, 'Court deleted successfully.');
    }
}
