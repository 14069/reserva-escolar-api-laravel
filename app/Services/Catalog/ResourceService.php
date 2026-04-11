<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

final class ResourceService
{
    public function categories(): array
    {
        return DB::table('resource_categories')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'name' => $row->name])
            ->all();
    }

    public function create(array $payload, int $authUserId): array
    {
        $schoolId = (int) $payload['school_id'];
        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para cadastrar recursos.');
        $this->assertCategoryExists((int) $payload['category_id']);

        if (DB::table('resources')->where('school_id', $schoolId)->where('name', $payload['name'])->exists()) {
            throw new HttpResponseException(ApiResponse::error('Já existe um recurso com esse nome nesta escola.', 409, 'RESOURCE_NAME_CONFLICT'));
        }

        $id = DB::table('resources')->insertGetId([
            'school_id' => $schoolId,
            'category_id' => (int) $payload['category_id'],
            'name' => $payload['name'],
            'active' => 1,
        ]);

        return ['resource_id' => (int) $id];
    }

    public function update(array $payload, int $authUserId): void
    {
        $schoolId = (int) $payload['school_id'];
        $resourceId = (int) $payload['resource_id'];
        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para editar recursos.');
        $this->assertResourceExists($resourceId, $schoolId);
        $this->assertCategoryExists((int) $payload['category_id']);

        if (DB::table('resources')->where('school_id', $schoolId)->where('name', $payload['name'])->where('id', '<>', $resourceId)->exists()) {
            throw new HttpResponseException(ApiResponse::error('Já existe outro recurso com esse nome nesta escola.', 409, 'RESOURCE_NAME_CONFLICT'));
        }

        DB::table('resources')->where('id', $resourceId)->where('school_id', $schoolId)->update([
            'name' => $payload['name'],
            'category_id' => (int) $payload['category_id'],
        ]);
    }

    public function toggleStatus(array $payload, int $authUserId): array
    {
        $schoolId = (int) $payload['school_id'];
        $resourceId = (int) $payload['resource_id'];
        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para alterar status de recursos.');

        $resource = DB::table('resources')->where('id', $resourceId)->where('school_id', $schoolId)->first(['id', 'active']);
        if ($resource === null) {
            throw new HttpResponseException(ApiResponse::error('Recurso não encontrado.', 404, 'RESOURCE_NOT_FOUND'));
        }

        $newStatus = ((int) $resource->active === 1) ? 0 : 1;
        DB::table('resources')->where('id', $resourceId)->where('school_id', $schoolId)->update(['active' => $newStatus]);

        return [
            'message' => $newStatus === 1 ? 'Recurso ativado com sucesso.' : 'Recurso desativado com sucesso.',
            'data' => ['resource_id' => $resourceId, 'active' => $newStatus],
        ];
    }

    private function assertActiveTechnician(int $userId, int $schoolId, string $message): void
    {
        if (!DB::table('users')->where('id', $userId)->where('school_id', $schoolId)->where('role', 'technician')->where('active', 1)->exists()) {
            throw new HttpResponseException(ApiResponse::error($message, 403, 'RESOURCE_ACTION_FORBIDDEN'));
        }
    }

    private function assertCategoryExists(int $categoryId): void
    {
        if (!DB::table('resource_categories')->where('id', $categoryId)->exists()) {
            throw new HttpResponseException(ApiResponse::error('Categoria inválida.', 404, 'RESOURCE_CATEGORY_NOT_FOUND'));
        }
    }

    private function assertResourceExists(int $resourceId, int $schoolId): void
    {
        if (!DB::table('resources')->where('id', $resourceId)->where('school_id', $schoolId)->exists()) {
            throw new HttpResponseException(ApiResponse::error('Recurso não encontrado.', 404, 'RESOURCE_NOT_FOUND'));
        }
    }
}
