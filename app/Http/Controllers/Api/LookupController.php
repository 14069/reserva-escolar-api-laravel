<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Lookup\AvailableLessonsRequest;
use App\Http\Requests\Lookup\ListClassGroupsRequest;
use App\Http\Requests\Lookup\ListResourcesRequest;
use App\Http\Requests\Lookup\ListSubjectsRequest;
use App\Services\Auth\ApiTokenAuthService;
use App\Services\Lookup\LookupQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class LookupController
{
    public function __construct(
        private readonly LookupQueryService $lookupQueryService,
        private readonly ApiTokenAuthService $authService,
    ) {}

    public function resources(ListResourcesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $schoolId = (int) $filters['school_id'];
        $onlyActive = (bool) ($filters['only_active'] ?? true);
        $roleRequired = ! $onlyActive ? 'technician' : null;

        $this->authService->authenticate(
            $request,
            $schoolId,
            $roleRequired
        );

        $result = $this->lookupQueryService->listResources($filters);

        return ApiResponse::success(
            $result['data'],
            'Recursos listados com sucesso.',
            200,
            $result['meta']
        );
    }

    public function classGroups(ListClassGroupsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $schoolId = (int) $filters['school_id'];
        $this->authService->authenticate($request, $schoolId);

        return ApiResponse::success(
            $this->lookupQueryService->listClassGroups($schoolId),
            'Turmas listadas com sucesso.'
        );
    }

    public function subjects(ListSubjectsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $schoolId = (int) $filters['school_id'];
        $this->authService->authenticate($request, $schoolId);

        return ApiResponse::success(
            $this->lookupQueryService->listSubjects($schoolId),
            'Disciplinas listadas com sucesso.'
        );
    }

    public function availableLessons(AvailableLessonsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $schoolId = (int) $filters['school_id'];
        $this->authService->authenticate($request, $schoolId);

        return ApiResponse::success(
            $this->lookupQueryService->listAvailableLessons($filters),
            'Aulas disponíveis listadas com sucesso.'
        );
    }
}
