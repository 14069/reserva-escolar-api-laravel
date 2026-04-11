<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

abstract class SimpleNamedCatalogService
{
    abstract protected function table(): string;

    abstract protected function idField(): string;

    abstract protected function entityLabel(): string;

    abstract protected function nameFieldMax(): int;

    public function list(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($filters['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $query = DB::table($this->table())
            ->where('school_id', (int) $filters['school_id']);

        if (($filters['status'] ?? '') === 'active') {
            $query->where('active', 1);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('active', '<>', 1);
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_count')
            ->selectRaw('SUM(CASE WHEN active <> 1 THEN 1 ELSE 0 END) as inactive_count')
            ->first();

        $ordered = clone $query;
        match ((string) ($filters['sort'] ?? 'name_asc')) {
            'name_desc' => $ordered->orderByDesc('name'),
            'status' => $ordered->orderByDesc('active')->orderBy('name'),
            default => $ordered->orderBy('name'),
        };

        $rows = $ordered->offset($offset)->limit($pageSize)->get([
            'id', 'school_id', 'name', 'active', 'created_at',
        ])->map(static fn (object $row): array => [
            'id' => (int) $row->id,
            'school_id' => (int) $row->school_id,
            'name' => $row->name,
            'active' => (int) $row->active,
            'created_at' => $row->created_at,
        ])->all();

        $total = (int) ($summary->total ?? 0);
        $totalPages = (int) ceil($total / $pageSize);

        return [
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next_page' => $page < $totalPages,
                'summary' => [
                    'active_count' => (int) ($summary->active_count ?? 0),
                    'inactive_count' => (int) ($summary->inactive_count ?? 0),
                ],
            ],
        ];
    }

    public function create(array $payload, int $authUserId): array
    {
        $schoolId = (int) $payload['school_id'];
        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para cadastrar '.mb_strtolower($this->entityLabel()).'s.');
        $name = (string) $payload['name'];

        if (DB::table($this->table())->where('school_id', $schoolId)->where('name', $name)->exists()) {
            throw new HttpResponseException(
                ApiResponse::error(
                    $this->duplicateNameMessage(),
                    409,
                    'CATALOG_NAME_CONFLICT'
                )
            );
        }

        $id = DB::table($this->table())->insertGetId([
            'school_id' => $schoolId,
            'name' => $name,
            'active' => 1,
        ]);

        return [$this->idField() => (int) $id];
    }

    public function update(array $payload, int $authUserId): void
    {
        $schoolId = (int) $payload['school_id'];
        $id = (int) $payload[$this->idField()];
        $name = (string) $payload['name'];
        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para editar '.mb_strtolower($this->entityLabel()).'s.');
        $this->assertExists($id, $schoolId);

        if (DB::table($this->table())->where('school_id', $schoolId)->where('name', $name)->where('id', '<>', $id)->exists()) {
            throw new HttpResponseException(
                ApiResponse::error(
                    'Já existe outra '.mb_strtolower($this->entityLabel()).' com esse nome nesta escola.',
                    409,
                    'CATALOG_NAME_CONFLICT'
                )
            );
        }

        DB::table($this->table())->where('id', $id)->where('school_id', $schoolId)->update(['name' => $name]);
    }

    public function toggleStatus(array $payload, int $authUserId): string
    {
        $schoolId = (int) $payload['school_id'];
        $id = (int) $payload[$this->idField()];
        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para alterar status de '.mb_strtolower($this->entityLabel()).'s.');

        $row = DB::table($this->table())->where('id', $id)->where('school_id', $schoolId)->first(['id', 'active']);
        if ($row === null) {
            throw new HttpResponseException(ApiResponse::error($this->entityLabel().' não encontrada.', 404, 'CATALOG_NOT_FOUND'));
        }

        $newStatus = ((int) $row->active === 1) ? 0 : 1;
        DB::table($this->table())->where('id', $id)->where('school_id', $schoolId)->update(['active' => $newStatus]);

        return $this->entityLabel().($newStatus === 1 ? ' ativada com sucesso.' : ' desativada com sucesso.');
    }

    protected function assertActiveTechnician(int $userId, int $schoolId, string $message): void
    {
        $exists = DB::table('users')
            ->where('id', $userId)
            ->where('school_id', $schoolId)
            ->where('role', 'technician')
            ->where('active', 1)
            ->exists();

        if (! $exists) {
            throw new HttpResponseException(ApiResponse::error($message, 403, 'CATALOG_ACTION_FORBIDDEN'));
        }
    }

    protected function assertExists(int $id, int $schoolId): void
    {
        if (! DB::table($this->table())->where('id', $id)->where('school_id', $schoolId)->exists()) {
            throw new HttpResponseException(ApiResponse::error($this->entityLabel().' não encontrada.', 404, 'CATALOG_NOT_FOUND'));
        }
    }

    private function duplicateNameMessage(): string
    {
        $label = mb_strtolower($this->entityLabel());

        return 'Já existe '.($label === 'turma' ? 'uma' : 'uma').' '.$label.' com esse nome nesta escola.';
    }
}
