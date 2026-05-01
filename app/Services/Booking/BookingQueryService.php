<?php

declare(strict_types=1);

namespace App\Services\Booking;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class BookingQueryService
{
    public function paginate(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($filters['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        $summaryMode = (string) ($filters['summary_mode'] ?? 'full');
        $includeFullSummary = $summaryMode !== 'simple';

        $query = DB::table('bookings as b')
            ->join('resources as r', 'r.id', '=', 'b.resource_id')
            ->join('users as u', 'u.id', '=', 'b.user_id')
            ->leftJoin('users as uc', 'uc.id', '=', 'b.completed_by_user_id')
            ->join('class_groups as cg', 'cg.id', '=', 'b.class_group_id')
            ->join('subjects as s', 's.id', '=', 'b.subject_id')
            ->where('b.school_id', (int) $filters['school_id']);

        if (! empty($filters['booking_date'])) {
            $query->whereDate('b.booking_date', $filters['booking_date']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('b.booking_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('b.booking_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['status'])) {
            $query->where('b.status', $filters['status']);
        }

        if (! empty($filters['teacher'])) {
            $query->where('u.name', $filters['teacher']);
        }

        if (! empty($filters['resource'])) {
            $query->where('r.name', $filters['resource']);
        }

        if (! empty($filters['class_group'])) {
            $query->where('cg.name', $filters['class_group']);
        }

        if (! empty($filters['search'])) {
            $this->applySearchFilter($query, (string) $filters['search'], true);
        }

        $summaryBase = clone $query;
        $summaryRow = $summaryBase
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN b.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_count")
            ->selectRaw("SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw("SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
            ->selectRaw("SUM(CASE WHEN b.status = 'completed' AND DATE(b.completed_at) = CURRENT_DATE THEN 1 ELSE 0 END) as completed_today_count")
            ->when($includeFullSummary, function ($summaryQuery): void {
                $summaryQuery
                    ->selectRaw('COUNT(DISTINCT u.id) as unique_teachers_count')
                    ->selectRaw('COUNT(DISTINCT r.id) as unique_resources_count')
                    ->selectRaw('COUNT(DISTINCT cg.id) as unique_class_groups_count')
                    ->selectRaw('COUNT(DISTINCT s.id) as unique_subjects_count');
            })
            ->first();

        $orderedQuery = clone $query;
        match ((string) ($filters['sort'] ?? 'date_desc')) {
            'date_asc' => $orderedQuery->orderBy('b.booking_date')->orderBy('b.id'),
            'teacher_asc' => $orderedQuery->orderBy('u.name')->orderByDesc('b.booking_date')->orderByDesc('b.id'),
            'resource_asc' => $orderedQuery->orderBy('r.name')->orderByDesc('b.booking_date')->orderByDesc('b.id'),
            default => $orderedQuery->orderByDesc('b.booking_date')->orderByDesc('b.id'),
        };

        $bookings = $orderedQuery
            ->select([
                'b.id',
                'b.booking_date',
                'b.purpose',
                'b.status',
                'b.cancelled_at',
                'b.completed_at',
                'b.completion_feedback',
                'r.name as resource_name',
                'u.name as user_name',
                'uc.name as completed_by_name',
                'cg.name as class_group_name',
                's.name as subject_name',
            ])
            ->limit($pageSize)
            ->offset($offset)
            ->get()
            ->map(function (object $booking): array {
                return [
                    'id' => (int) $booking->id,
                    'booking_date' => $booking->booking_date,
                    'purpose' => $booking->purpose,
                    'status' => $booking->status,
                    'cancelled_at' => $booking->cancelled_at,
                    'completed_at' => $booking->completed_at,
                    'completion_feedback' => $booking->completion_feedback,
                    'resource_name' => $booking->resource_name,
                    'user_name' => $booking->user_name,
                    'completed_by_name' => $booking->completed_by_name,
                    'class_group_name' => $booking->class_group_name,
                    'subject_name' => $booking->subject_name,
                    'lessons' => [],
                ];
            })
            ->values();

        $bookingIds = $bookings->pluck('id')->all();
        if ($bookingIds !== []) {
            $lessonsByBookingId = DB::table('booking_lessons as bl')
                ->join('lesson_slots as ls', 'ls.id', '=', 'bl.lesson_slot_id')
                ->whereIn('bl.booking_id', $bookingIds)
                ->orderBy('bl.booking_id')
                ->orderBy('ls.lesson_number')
                ->get([
                    'bl.booking_id',
                    'ls.id',
                    'ls.lesson_number',
                    'ls.label',
                ])
                ->groupBy('booking_id')
                ->map(function ($rows) {
                    return collect($rows)->map(function (object $lesson): array {
                        return [
                            'id' => (int) $lesson->id,
                            'lesson_number' => (int) $lesson->lesson_number,
                            'label' => $lesson->label,
                        ];
                    })->values()->all();
                });

            $bookings = $bookings->map(function (array $booking) use ($lessonsByBookingId): array {
                $booking['lessons'] = $lessonsByBookingId->get($booking['id'], []);

                return $booking;
            })->values();
        }

        $total = (int) ($summaryRow->total ?? 0);
        $totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 0;

        $overallCount = $total;
        $totalReservedLessons = 0;
        $averageLessonsPerBooking = 0.0;
        $busiestWeekdayLabel = null;
        $teacherOptions = [];
        $resourceOptions = [];
        $classGroupOptions = [];
        $statusOptions = [];
        $teacherRanking = [];
        $resourceRanking = [];
        $classGroupRanking = [];
        $subjectRanking = [];

        if ($includeFullSummary) {
            $baseQuery = DB::table('bookings as b')
                ->join('resources as r', 'r.id', '=', 'b.resource_id')
                ->join('users as u', 'u.id', '=', 'b.user_id')
                ->join('class_groups as cg', 'cg.id', '=', 'b.class_group_id')
                ->join('subjects as s', 's.id', '=', 'b.subject_id')
                ->where('b.school_id', (int) $filters['school_id']);

            if (! empty($filters['booking_date'])) {
                $baseQuery->whereDate('b.booking_date', $filters['booking_date']);
            }
            if (! empty($filters['date_from'])) {
                $baseQuery->whereDate('b.booking_date', '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to'])) {
                $baseQuery->whereDate('b.booking_date', '<=', $filters['date_to']);
            }

            $overallCount = (clone $baseQuery)->count();

            $teacherOptions = (clone $baseQuery)
                ->select('u.name')->distinct()->orderBy('u.name')
                ->pluck('u.name')->all();

            $resourceOptions = (clone $baseQuery)
                ->select('r.name')->distinct()->orderBy('r.name')
                ->pluck('r.name')->all();

            $classGroupOptions = (clone $baseQuery)
                ->select('cg.name')->distinct()->orderBy('cg.name')
                ->pluck('cg.name')->all();

            $statusOptions = (clone $baseQuery)
                ->select('b.status')->distinct()
                ->pluck('b.status')->sort()->values()->all();

            $teacherRanking = (clone $query)
                ->selectRaw('u.name as label, COUNT(*) as value')
                ->groupBy('u.name')->orderByDesc('value')->limit(5)
                ->get()->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])->all();

            $resourceRanking = (clone $query)
                ->selectRaw('r.name as label, COUNT(*) as value')
                ->groupBy('r.name')->orderByDesc('value')->limit(5)
                ->get()->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])->all();

            $classGroupRanking = (clone $query)
                ->selectRaw('cg.name as label, COUNT(*) as value')
                ->groupBy('cg.name')->orderByDesc('value')->limit(5)
                ->get()->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])->all();

            $subjectRanking = (clone $query)
                ->selectRaw('s.name as label, COUNT(*) as value')
                ->groupBy('s.name')->orderByDesc('value')->limit(5)
                ->get()->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])->all();

            $totalReservedLessons = DB::table('booking_lessons')
                ->whereIn('booking_id', (clone $query)->select('b.id'))
                ->count();

            $averageLessonsPerBooking = $total > 0
                ? round($totalReservedLessons / $total, 2)
                : 0.0;

            $driver = DB::connection()->getDriverName();
            $weekdayExpr = match ($driver) {
                'pgsql' => 'EXTRACT(DOW FROM b.booking_date)',
                'sqlite' => "CAST(strftime('%w', b.booking_date) AS INTEGER)",
                default => 'DAYOFWEEK(b.booking_date) - 1',
            };

            $busiestRow = (clone $query)
                ->selectRaw("{$weekdayExpr} as weekday_num, COUNT(*) as cnt")
                ->groupByRaw($weekdayExpr)
                ->orderByDesc('cnt')
                ->first();

            $weekdayLabels = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            $busiestWeekdayLabel = $busiestRow !== null
                ? ($weekdayLabels[(int) $busiestRow->weekday_num] ?? null)
                : null;
        }

        return [
            'data' => $bookings->all(),
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next_page' => $page < $totalPages,
                'summary' => [
                    'overall_count' => $overallCount,
                    'scheduled_count' => (int) ($summaryRow->scheduled_count ?? 0),
                    'completed_count' => (int) ($summaryRow->completed_count ?? 0),
                    'cancelled_count' => (int) ($summaryRow->cancelled_count ?? 0),
                    'completed_today_count' => (int) ($summaryRow->completed_today_count ?? 0),
                    'unique_teachers_count' => $includeFullSummary ? (int) ($summaryRow->unique_teachers_count ?? 0) : null,
                    'unique_resources_count' => $includeFullSummary ? (int) ($summaryRow->unique_resources_count ?? 0) : null,
                    'unique_class_groups_count' => $includeFullSummary ? (int) ($summaryRow->unique_class_groups_count ?? 0) : null,
                    'unique_subjects_count' => $includeFullSummary ? (int) ($summaryRow->unique_subjects_count ?? 0) : null,
                    'total_reserved_lessons' => $totalReservedLessons,
                    'average_lessons_per_booking' => $averageLessonsPerBooking,
                    'busiest_weekday_label' => $busiestWeekdayLabel,
                    'teacher_options' => $teacherOptions,
                    'resource_options' => $resourceOptions,
                    'class_group_options' => $classGroupOptions,
                    'status_options' => $statusOptions,
                    'teacher_ranking' => $teacherRanking,
                    'resource_ranking' => $resourceRanking,
                    'class_group_ranking' => $classGroupRanking,
                    'subject_ranking' => $subjectRanking,
                ],
            ],
        ];
    }

    public function paginateMyBookings(array $filters, int $userId, bool $hasCompletionFeedbackColumn): array
    {
        $page = isset($filters['page']) || isset($filters['page_size'])
            ? max(1, (int) ($filters['page'] ?? 1))
            : null;
        $pageSize = $page !== null
            ? min(100, max(1, (int) ($filters['page_size'] ?? 20)))
            : null;
        $offset = $page !== null ? ($page - 1) * $pageSize : null;

        $query = DB::table('bookings as b')
            ->join('resources as r', 'r.id', '=', 'b.resource_id')
            ->leftJoin('users as uc', 'uc.id', '=', 'b.completed_by_user_id')
            ->join('class_groups as cg', 'cg.id', '=', 'b.class_group_id')
            ->join('subjects as s', 's.id', '=', 'b.subject_id')
            ->where('b.school_id', (int) $filters['school_id'])
            ->where('b.user_id', $userId);

        if (! empty($filters['status'])) {
            $query->where('b.status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $this->applySearchFilter($query, (string) $filters['search'], false);
        }

        $orderedQuery = clone $query;
        match ((string) ($filters['sort'] ?? 'date_desc')) {
            'date_asc' => $orderedQuery->orderBy('b.booking_date')->orderBy('b.id'),
            'resource_asc' => $orderedQuery->orderBy('r.name')->orderByDesc('b.booking_date')->orderByDesc('b.id'),
            'status' => $orderedQuery->orderBy('b.status')->orderByDesc('b.booking_date')->orderByDesc('b.id'),
            default => $orderedQuery->orderByDesc('b.booking_date')->orderByDesc('b.id'),
        };

        $select = [
            'b.id',
            'b.booking_date',
            'b.purpose',
            'b.status',
            'b.cancelled_at',
            'b.completed_at',
            'r.name as resource_name',
            'uc.name as completed_by_name',
            'cg.name as class_group_name',
            's.name as subject_name',
        ];

        if ($hasCompletionFeedbackColumn) {
            $select[] = 'b.completion_feedback';
        }

        $rows = clone $orderedQuery;
        if ($page !== null && $pageSize !== null && $offset !== null) {
            $rows->offset($offset)->limit($pageSize);
        }

        $bookings = $rows->get($select)
            ->map(function (object $booking) use ($hasCompletionFeedbackColumn): array {
                return [
                    'id' => (int) $booking->id,
                    'booking_date' => $booking->booking_date,
                    'purpose' => $booking->purpose,
                    'status' => $booking->status,
                    'cancelled_at' => $booking->cancelled_at,
                    'completed_at' => $booking->completed_at,
                    'completion_feedback' => $hasCompletionFeedbackColumn ? ($booking->completion_feedback ?? null) : null,
                    'resource_name' => $booking->resource_name,
                    'completed_by_name' => $booking->completed_by_name,
                    'class_group_name' => $booking->class_group_name,
                    'subject_name' => $booking->subject_name,
                    'lessons' => [],
                ];
            })
            ->values();

        $bookingIds = $bookings->pluck('id')->all();
        if ($bookingIds !== []) {
            $lessonsByBookingId = DB::table('booking_lessons as bl')
                ->join('lesson_slots as ls', 'ls.id', '=', 'bl.lesson_slot_id')
                ->whereIn('bl.booking_id', $bookingIds)
                ->orderBy('bl.booking_id')
                ->orderBy('ls.lesson_number')
                ->get([
                    'bl.booking_id',
                    'ls.id',
                    'ls.lesson_number',
                    'ls.label',
                ])
                ->groupBy('booking_id')
                ->map(function ($rows) {
                    return collect($rows)->map(function (object $lesson): array {
                        return [
                            'id' => (int) $lesson->id,
                            'lesson_number' => (int) $lesson->lesson_number,
                            'label' => $lesson->label,
                        ];
                    })->values()->all();
                });

            $bookings = $bookings->map(function (array $booking) use ($lessonsByBookingId): array {
                $booking['lessons'] = $lessonsByBookingId->get($booking['id'], []);

                return $booking;
            })->values();
        }

        if ($page === null || $pageSize === null || $offset === null) {
            return [
                'data' => $bookings->all(),
                'meta' => null,
            ];
        }

        $summaryRow = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN b.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_count")
            ->selectRaw("SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw("SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
            ->first();

        $total = (int) ($summaryRow->total ?? 0);
        $totalPages = (int) ceil($total / $pageSize);

        return [
            'data' => $bookings->all(),
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next_page' => $page < $totalPages,
                'summary' => [
                    'scheduled_count' => (int) ($summaryRow->scheduled_count ?? 0),
                    'completed_count' => (int) ($summaryRow->completed_count ?? 0),
                    'cancelled_count' => (int) ($summaryRow->cancelled_count ?? 0),
                ],
            ],
        ];
    }

    private function applySearchFilter(Builder $query, string $searchTerm, bool $includeTeacher): void
    {
        $searchTerm = trim($searchTerm);
        if ($searchTerm === '') {
            return;
        }

        $searchLike = '%'.$searchTerm.'%';

        $query->where(function (Builder $subQuery) use ($searchLike, $searchTerm, $includeTeacher): void {
            $subQuery->where('r.name', 'like', $searchLike)
                ->orWhere('cg.name', 'like', $searchLike)
                ->orWhere('s.name', 'like', $searchLike)
                ->orWhere('b.purpose', 'like', $searchLike);

            if ($includeTeacher) {
                $subQuery->orWhere('u.name', 'like', $searchLike);
            }

            $this->applyFormattedDateSearch($subQuery, $searchLike, $searchTerm);
        });
    }

    private function applyFormattedDateSearch(Builder $query, string $searchLike, string $rawSearchTerm): void
    {
        $driver = DB::connection()->getDriverName();

        $expression = match ($driver) {
            'pgsql' => "TO_CHAR(b.booking_date, 'DD/MM/YYYY')",
            'sqlite' => "strftime('%d/%m/%Y', b.booking_date)",
            'mysql', 'mariadb' => "DATE_FORMAT(b.booking_date, '%d/%m/%Y')",
            default => null,
        };

        if ($expression !== null) {
            $query->orWhereRaw("{$expression} LIKE ?", [$searchLike]);

            return;
        }

        $normalizedDate = $this->normalizeSearchDate($rawSearchTerm);
        if ($normalizedDate !== null) {
            $query->orWhereDate('b.booking_date', $normalizedDate);
        }
    }

    private function normalizeSearchDate(string $rawSearchTerm): ?string
    {
        $rawSearchTerm = trim($rawSearchTerm);
        if ($rawSearchTerm === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $rawSearchTerm);
            if ($date !== false && $date->format($format) === $rawSearchTerm) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
