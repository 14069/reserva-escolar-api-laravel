<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Catalog\LessonSlotMutationRequest;
use App\Http\Requests\Catalog\LessonSlotsAdminIndexRequest;
use App\Http\Requests\Catalog\LessonSlotStatusToggleRequest;
use App\Services\Auth\ApiTokenAuthService;
use App\Services\Catalog\LessonSlotService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class LessonSlotController
{
    public function __construct(
        private readonly LessonSlotService $lessonSlotService,
        private readonly ApiTokenAuthService $authService,
    ) {}

    public function index(LessonSlotsAdminIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $schoolId = (int) $filters['school_id'];
        $this->authService->authenticate($request, $schoolId, 'technician');
        $result = $this->lessonSlotService->list($filters);

        return ApiResponse::success($result['data'], 'Aulas listadas com sucesso.', 200, $result['meta']);
    }

    public function store(LessonSlotMutationRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $result = $this->lessonSlotService->create($payload, (int) $authUser->id);

        return ApiResponse::success($result, 'Aula cadastrada com sucesso.', 201);
    }

    public function update(LessonSlotMutationRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $this->lessonSlotService->update($payload, (int) $authUser->id);

        return ApiResponse::success(null, 'Aula atualizada com sucesso.');
    }

    public function toggleStatus(LessonSlotStatusToggleRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $message = $this->lessonSlotService->toggleStatus($payload, (int) $authUser->id);

        return ApiResponse::success(null, $message);
    }
}
