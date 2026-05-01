<?php

declare(strict_types=1);

namespace App\Services\Lookup;

use Illuminate\Support\Facades\DB;

final class LookupQueryService
{
    public function listResources(array $filters): array
    {
        $page = isset($filters['page']) || isset($filters['page_size'])
            ? max(1, (int) ($filters['page'] ?? 1))
            : null;
        $pageSize = $page !== null
            ? min(100, max(1, (int) ($filters['page_size'] ?? 20)))
            : null;
        $offset = $page !== null ? ($page - 1) * $pageSize : null;

        $query = DB::table('resources as r')
            ->join('resource_categories as rc', 'rc.id', '=', 'r.category_id')
            ->where('r.school_id', (int) $filters['school_id']);

        if (($filters['only_active'] ?? '1') !== '0') {
            $query->where('r.active', 1);
        }

        if (($filters['status'] ?? '') === 'active') {
            $query->where('r.active', 1);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('r.active', '<>', 1);
        }

        if (! empty($filters['category'])) {
            $query->where('rc.name', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filters['search']);
            $search = '%'.$escaped.'%';
            $query->where(function ($subQuery) use ($search): void {
                $subQuery
                    ->where('r.name', 'like', $search)
                    ->orWhere('rc.name', 'like', $search);
            });
        }

        $orderedQuery = clone $query;
        match ((string) ($filters['sort'] ?? 'name_asc')) {
            'name_desc' => $orderedQuery->orderByDesc('r.name'),
            'category_asc' => $orderedQuery->orderBy('rc.name')->orderBy('r.name'),
            'status' => $orderedQuery->orderByDesc('r.active')->orderBy('r.name'),
            default => $orderedQuery->orderBy('r.name'),
        };

        $select = [
            'r.id',
            'r.name',
            'r.active',
            'rc.id as category_id',
            'rc.name as category_name',
        ];

        if ($page === null || $pageSize === null || $offset === null) {
            return [
                'data' => $orderedQuery->get($select)->map(
                    static fn (object $row): array => [
                        'id' => (int) $row->id,
                        'name' => $row->name,
                        'active' => (int) $row->active,
                        'category_id' => (int) $row->category_id,
                        'category_name' => $row->category_name,
                    ]
                )->all(),
                'meta' => null,
            ];
        }

        $total = (int) (clone $query)->count();
        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN r.active = 1 THEN 1 ELSE 0 END) as active_count')
            ->first();

        $resources = $orderedQuery
            ->offset($offset)
            ->limit($pageSize)
            ->get($select)
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'active' => (int) $row->active,
                'category_id' => (int) $row->category_id,
                'category_name' => $row->category_name,
            ])
            ->all();

        $totalPages = (int) ceil($total / $pageSize);

        return [
            'data' => $resources,
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next_page' => $page < $totalPages,
                'summary' => [
                    'active_count' => (int) ($summary->active_count ?? 0),
                ],
            ],
        ];
    }

    public function listClassGroups(int $schoolId): array
    {
        return DB::table('class_groups')
            ->where('school_id', $schoolId)
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'active'])
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'active' => (int) $row->active,
            ])
            ->all();
    }

    public function listSubjects(int $schoolId): array
    {
        return DB::table('subjects')
            ->where('school_id', $schoolId)
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'active'])
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'active' => (int) $row->active,
            ])
            ->all();
    }

    public function listAvailableLessons(array $filters): array
    {
        $schoolId = (int) $filters['school_id'];
        $resourceId = (int) $filters['resource_id'];
        $bookingDate = (string) $filters['booking_date'];

        return DB::table('lesson_slots as ls')
            ->where('ls.school_id', $schoolId)
            ->where('ls.active', 1)
            ->whereNotIn('ls.id', function ($subQuery) use ($schoolId, $resourceId, $bookingDate): void {
                $subQuery
                    ->from('booking_lessons as bl')
                    ->join('bookings as b', 'b.id', '=', 'bl.booking_id')
                    ->where('b.school_id', $schoolId)
                    ->where('b.resource_id', $resourceId)
                    ->where('b.booking_date', $bookingDate)
                    ->where('b.status', 'scheduled')
                    ->select('bl.lesson_slot_id');
            })
            ->orderBy('ls.lesson_number')
            ->get([
                'ls.id',
                'ls.lesson_number',
                'ls.label',
                'ls.start_time',
                'ls.end_time',
            ])
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'lesson_number' => (int) $row->lesson_number,
                'label' => $row->label,
                'start_time' => $row->start_time,
                'end_time' => $row->end_time,
            ])
            ->all();
    }
}
