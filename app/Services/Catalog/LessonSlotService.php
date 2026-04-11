<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

final class LessonSlotService
{
    public function list(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($filters['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $query = DB::table('lesson_slots')
            ->where('school_id', (int) $filters['school_id']);

        if (($filters['status'] ?? '') === 'active') {
            $query->where('active', 1);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('active', '<>', 1);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($sub) use ($search): void {
                $sub->where('label', 'like', $search)
                    ->orWhereRaw('CAST(lesson_number AS TEXT) LIKE ?', [$search])
                    ->orWhereRaw("COALESCE(CAST(start_time AS TEXT), '') LIKE ?", [$search])
                    ->orWhereRaw("COALESCE(CAST(end_time AS TEXT), '') LIKE ?", [$search]);
            });
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_count')
            ->selectRaw('SUM(CASE WHEN active <> 1 THEN 1 ELSE 0 END) as inactive_count')
            ->first();

        $ordered = clone $query;
        match ((string) ($filters['sort'] ?? 'lesson_number_asc')) {
            'lesson_number_desc' => $ordered->orderByDesc('lesson_number'),
            'label_asc' => $ordered->orderBy('label')->orderBy('lesson_number'),
            'status' => $ordered->orderByDesc('active')->orderBy('lesson_number'),
            default => $ordered->orderBy('lesson_number'),
        };

        $rows = $ordered->offset($offset)->limit($pageSize)->get([
            'id', 'school_id', 'lesson_number', 'label', 'start_time', 'end_time', 'active', 'created_at',
        ])->map(static fn (object $row): array => [
            'id' => (int) $row->id,
            'school_id' => (int) $row->school_id,
            'lesson_number' => (int) $row->lesson_number,
            'label' => $row->label,
            'start_time' => $row->start_time,
            'end_time' => $row->end_time,
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
        $lessonNumber = (int) $payload['lesson_number'];
        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para cadastrar aulas.');

        if (DB::table('lesson_slots')->where('school_id', $schoolId)->where('lesson_number', $lessonNumber)->exists()) {
            throw new HttpResponseException(ApiResponse::error('Já existe uma aula com esse número nesta escola.', 409, 'LESSON_SLOT_NUMBER_CONFLICT'));
        }

        $id = DB::table('lesson_slots')->insertGetId([
            'school_id' => $schoolId,
            'lesson_number' => $lessonNumber,
            'label' => $payload['label'],
            'start_time' => $payload['start_time'],
            'end_time' => $payload['end_time'],
            'active' => 1,
        ]);

        return ['lesson_slot_id' => (int) $id];
    }

    public function update(array $payload, int $authUserId): void
    {
        $schoolId = (int) $payload['school_id'];
        $lessonSlotId = (int) $payload['lesson_slot_id'];
        $lessonNumber = (int) $payload['lesson_number'];
        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para editar aulas.');
        $this->assertExists($lessonSlotId, $schoolId);

        if (DB::table('lesson_slots')->where('school_id', $schoolId)->where('lesson_number', $lessonNumber)->where('id', '<>', $lessonSlotId)->exists()) {
            throw new HttpResponseException(ApiResponse::error('Já existe outra aula com esse número nesta escola.', 409, 'LESSON_SLOT_NUMBER_CONFLICT'));
        }

        DB::table('lesson_slots')->where('id', $lessonSlotId)->where('school_id', $schoolId)->update([
            'lesson_number' => $lessonNumber,
            'label' => $payload['label'],
            'start_time' => $payload['start_time'],
            'end_time' => $payload['end_time'],
        ]);
    }

    public function toggleStatus(array $payload, int $authUserId): string
    {
        $schoolId = (int) $payload['school_id'];
        $lessonSlotId = (int) $payload['lesson_slot_id'];
        $this->assertActiveTechnician($authUserId, $schoolId, 'Usuário sem permissão para alterar status de aulas.');
        $row = DB::table('lesson_slots')->where('id', $lessonSlotId)->where('school_id', $schoolId)->first(['id', 'active']);
        if ($row === null) {
            throw new HttpResponseException(ApiResponse::error('Aula não encontrada.', 404, 'LESSON_SLOT_NOT_FOUND'));
        }

        $newStatus = ((int) $row->active === 1) ? 0 : 1;
        DB::table('lesson_slots')->where('id', $lessonSlotId)->where('school_id', $schoolId)->update(['active' => $newStatus]);

        return $newStatus === 1 ? 'Aula ativada com sucesso.' : 'Aula desativada com sucesso.';
    }

    private function assertActiveTechnician(int $userId, int $schoolId, string $message): void
    {
        if (!DB::table('users')->where('id', $userId)->where('school_id', $schoolId)->where('role', 'technician')->where('active', 1)->exists()) {
            throw new HttpResponseException(ApiResponse::error($message, 403, 'LESSON_SLOT_ACTION_FORBIDDEN'));
        }
    }

    private function assertExists(int $lessonSlotId, int $schoolId): void
    {
        if (!DB::table('lesson_slots')->where('id', $lessonSlotId)->where('school_id', $schoolId)->exists()) {
            throw new HttpResponseException(ApiResponse::error('Aula não encontrada.', 404, 'LESSON_SLOT_NOT_FOUND'));
        }
    }
}
