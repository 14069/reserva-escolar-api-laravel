<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Catalog\ClassGroupMutationRequest;
use App\Http\Requests\Catalog\ClassGroupStatusToggleRequest;
use App\Http\Requests\Catalog\ClassGroupsAdminIndexRequest;
use App\Services\Auth\ApiTokenAuthService;
use App\Services\Catalog\ClassGroupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ClassGroupController
{
    public function __construct(
        private readonly ClassGroupService $classGroupService,
        private readonly ApiTokenAuthService $authService,
    ) {
    }

    public function index(ClassGroupsAdminIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $schoolId = (int) $filters['school_id'];
        $this->authService->authenticate($request, $schoolId, 'technician');
        $result = $this->classGroupService->list($filters);

        return ApiResponse::success($result['data'], 'Turmas listadas com sucesso.', 200, $result['meta']);
    }

    public function store(ClassGroupMutationRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $result = $this->classGroupService->create($payload, (int) $authUser->id);

        return ApiResponse::success($result, 'Turma cadastrada com sucesso.', 201);
    }

    public function update(ClassGroupMutationRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $this->classGroupService->update($payload, (int) $authUser->id);

        return ApiResponse::success(null, 'Turma atualizada com sucesso.');
    }

    public function toggleStatus(ClassGroupStatusToggleRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $message = $this->classGroupService->toggleStatus($payload, (int) $authUser->id);

        return ApiResponse::success(null, $message);
    }
}
