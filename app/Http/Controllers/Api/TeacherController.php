<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Teacher\CreateTeacherRequest;
use App\Http\Requests\Teacher\ListTeachersRequest;
use App\Http\Requests\Teacher\ResetTeacherPasswordRequest;
use App\Http\Requests\Teacher\ToggleTeacherStatusRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Services\Auth\ApiTokenAuthService;
use App\Services\Teacher\TeacherService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TeacherController
{
    public function __construct(
        private readonly TeacherService $teacherService,
        private readonly ApiTokenAuthService $authService,
    ) {
    }

    public function index(ListTeachersRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $schoolId = (int) $filters['school_id'];
        $this->authService->authenticate($request, $schoolId, 'technician');
        $result = $this->teacherService->list($filters);

        return ApiResponse::success(
            $result['data'],
            'Professores listados com sucesso.',
            200,
            $result['meta']
        );
    }

    public function store(CreateTeacherRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $result = $this->teacherService->create($payload, (int) $authUser->id);

        return ApiResponse::success($result, 'Professor cadastrado com sucesso.', 201);
    }

    public function update(UpdateTeacherRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $this->teacherService->update($payload, (int) $authUser->id);

        return ApiResponse::success(null, 'Professor atualizado com sucesso.');
    }

    public function toggleStatus(ToggleTeacherStatusRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $message = $this->teacherService->toggleStatus($payload, (int) $authUser->id);

        return ApiResponse::success(null, $message);
    }

    public function resetPassword(ResetTeacherPasswordRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $this->teacherService->resetPassword($payload, (int) $authUser->id);

        return ApiResponse::success(null, 'Senha redefinida com sucesso.');
    }
}
