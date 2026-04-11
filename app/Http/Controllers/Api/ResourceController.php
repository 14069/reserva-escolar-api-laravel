<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Catalog\ResourceMutationRequest;
use App\Http\Requests\Catalog\ResourceStatusToggleRequest;
use App\Services\Auth\ApiTokenAuthService;
use App\Services\Catalog\ResourceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ResourceController
{
    public function __construct(
        private readonly ResourceService $resourceService,
        private readonly ApiTokenAuthService $authService,
    ) {
    }

    public function categories(Request $request): JsonResponse
    {
        $request->validate(['school_id' => 'required|integer']);
        $schoolId = (int) $request->get('school_id');
        $this->authService->authenticate($request, $schoolId);

        return ApiResponse::success(
            $this->resourceService->categories(),
            'Categorias listadas com sucesso.'
        );
    }

    public function store(ResourceMutationRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $result = $this->resourceService->create($payload, (int) $authUser->id);

        return ApiResponse::success($result, 'Recurso criado com sucesso.', 201);
    }

    public function update(ResourceMutationRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $this->resourceService->update($payload, (int) $authUser->id);

        return ApiResponse::success(null, 'Recurso atualizado com sucesso.');
    }

    public function toggleStatus(ResourceStatusToggleRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId, 'technician');
        $result = $this->resourceService->toggleStatus($payload, (int) $authUser->id);

        return ApiResponse::success($result['data'], $result['message']);
    }
}
