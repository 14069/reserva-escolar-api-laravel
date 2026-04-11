<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Catalog\SubjectMutationRequest;
use App\Http\Requests\Catalog\SubjectsAdminIndexRequest;
use App\Http\Requests\Catalog\SubjectStatusToggleRequest;
use App\Services\Auth\ApiTokenAuthService;
use App\Services\Catalog\SubjectService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SubjectController
{
    public function __construct(
        private readonly SubjectService $subjectService,
        private readonly ApiTokenAuthService $authService,
    ) {}

    public function index(SubjectsAdminIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $schoolId = (int) $filters['school_id'];
        $this->authService->authenticate($request, $schoolId, 'technician');
        $result = $this->subjectService->list($filters);

        return ApiResponse::success($result['data'], 'Disciplinas listadas com sucesso.', 200, $result['meta']);
    }

    public function store(SubjectMutationRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $result = $this->subjectService->create($payload, (int) $authUser->id);

        return ApiResponse::success($result, 'Disciplina cadastrada com sucesso.', 201);
    }

    public function update(SubjectMutationRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $this->subjectService->update($payload, (int) $authUser->id);

        return ApiResponse::success(null, 'Disciplina atualizada com sucesso.');
    }

    public function toggleStatus(SubjectStatusToggleRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $message = $this->subjectService->toggleStatus($payload, (int) $authUser->id);

        return ApiResponse::success(null, $message);
    }
}
