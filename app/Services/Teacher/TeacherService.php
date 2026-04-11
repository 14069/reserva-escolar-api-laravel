<?php

declare(strict_types=1);

namespace App\Services\Teacher;

use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class TeacherService
{
    public function list(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($filters['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $query = DB::table('users')
            ->where('school_id', (int) $filters['school_id'])
            ->where('role', 'teacher');

        if (($filters['status'] ?? '') === 'active') {
            $query->where('active', 1);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('active', '<>', 1);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($subQuery) use ($search): void {
                $subQuery
                    ->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        $summaryRow = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_count')
            ->selectRaw('SUM(CASE WHEN active <> 1 THEN 1 ELSE 0 END) as inactive_count')
            ->first();

        $orderedQuery = clone $query;
        match ((string) ($filters['sort'] ?? 'name_asc')) {
            'name_desc' => $orderedQuery->orderByDesc('name'),
            'status' => $orderedQuery->orderByDesc('active')->orderBy('name'),
            default => $orderedQuery->orderBy('name'),
        };

        $teachers = $orderedQuery
            ->offset($offset)
            ->limit($pageSize)
            ->get([
                'id',
                'school_id',
                'name',
                'email',
                'role',
                'active',
                'created_at',
            ])
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'school_id' => (int) $row->school_id,
                'name' => $row->name,
                'email' => $row->email,
                'role' => $row->role,
                'active' => (int) $row->active,
                'created_at' => $row->created_at,
            ])
            ->all();

        $total = (int) ($summaryRow->total ?? 0);
        $totalPages = (int) ceil($total / $pageSize);

        return [
            'data' => $teachers,
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next_page' => $page < $totalPages,
                'summary' => [
                    'active_count' => (int) ($summaryRow->active_count ?? 0),
                    'inactive_count' => (int) ($summaryRow->inactive_count ?? 0),
                ],
            ],
        ];
    }

    public function create(array $payload, int $authUserId): array
    {
        $schoolId = (int) $payload['school_id'];
        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para cadastrar professores.');

        $emailExists = DB::table('users')
            ->where('school_id', $schoolId)
            ->where('email', $payload['email'])
            ->exists();

        if ($emailExists) {
            throw new HttpResponseException(
                ApiResponse::error('Já existe um usuário com esse email nesta escola.', 409, 'TEACHER_EMAIL_CONFLICT')
            );
        }

        $teacherId = DB::table('users')->insertGetId([
            'school_id' => $schoolId,
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
            'role' => 'teacher',
            'active' => 1,
        ]);

        return ['teacher_id' => (int) $teacherId];
    }

    public function update(array $payload, int $authUserId): void
    {
        $schoolId = (int) $payload['school_id'];
        $teacherId = (int) $payload['teacher_id'];

        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para editar professores.');
        $this->assertTeacherExists($teacherId, $schoolId);

        $emailExists = DB::table('users')
            ->where('school_id', $schoolId)
            ->where('email', $payload['email'])
            ->where('id', '<>', $teacherId)
            ->exists();

        if ($emailExists) {
            throw new HttpResponseException(
                ApiResponse::error(
                    'Já existe outro usuário com esse email nesta escola.',
                    409,
                    'TEACHER_EMAIL_CONFLICT'
                )
            );
        }

        DB::table('users')
            ->where('id', $teacherId)
            ->where('school_id', $schoolId)
            ->update([
                'name' => $payload['name'],
                'email' => $payload['email'],
            ]);
    }

    public function toggleStatus(array $payload, int $authUserId): string
    {
        $schoolId = (int) $payload['school_id'];
        $teacherId = (int) $payload['teacher_id'];

        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para alterar status de professores.');

        $teacher = DB::table('users')
            ->where('id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('role', 'teacher')
            ->first(['id', 'active']);

        if ($teacher === null) {
            throw new HttpResponseException(
                ApiResponse::error('Professor não encontrado.', 404, 'TEACHER_NOT_FOUND')
            );
        }

        $newStatus = ((int) $teacher->active === 1) ? 0 : 1;

        DB::table('users')
            ->where('id', $teacherId)
            ->where('school_id', $schoolId)
            ->update(['active' => $newStatus]);

        return $newStatus === 1
            ? 'Professor ativado com sucesso.'
            : 'Professor desativado com sucesso.';
    }

    public function resetPassword(array $payload, int $authUserId): void
    {
        $schoolId = (int) $payload['school_id'];
        $teacherId = (int) $payload['teacher_id'];

        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para redefinir senha.');
        $this->assertTeacherExists($teacherId, $schoolId);

        DB::table('users')
            ->where('id', $teacherId)
            ->where('school_id', $schoolId)
            ->update([
                'password' => Hash::make($payload['new_password']),
            ]);
    }

    private function assertActiveTechnician(int $userId, int $schoolId, string $message): void
    {
        $exists = DB::table('users')
            ->where('id', $userId)
            ->where('school_id', $schoolId)
            ->where('role', 'technician')
            ->where('active', 1)
            ->exists();

        if (!$exists) {
            throw new HttpResponseException(ApiResponse::error($message, 403, 'TEACHER_ACTION_FORBIDDEN'));
        }
    }

    private function assertTeacherExists(int $teacherId, int $schoolId): void
    {
        $exists = DB::table('users')
            ->where('id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('role', 'teacher')
            ->exists();

        if (!$exists) {
            throw new HttpResponseException(
                ApiResponse::error('Professor não encontrado.', 404, 'TEACHER_NOT_FOUND')
            );
        }
    }
}
