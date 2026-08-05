<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Auth\SystemAdminTokenService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SystemAdminSchoolController
{
    public function __construct(
        private readonly SystemAdminTokenService $auth,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->auth->authenticate($request);

        $schools = DB::table('schools')
            ->leftJoin('users', 'users.school_id', '=', 'schools.id')
            ->select(
                'schools.id',
                'schools.school_name',
                'schools.school_code',
                'schools.active',
                'schools.created_at',
                DB::raw('COUNT(users.id) as users_count'),
            )
            ->groupBy('schools.id', 'schools.school_name', 'schools.school_code', 'schools.active', 'schools.created_at')
            ->orderByDesc('schools.created_at')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'school_name' => $row->school_name,
                'school_code' => $row->school_code,
                'active' => (bool) $row->active,
                'users_count' => (int) $row->users_count,
                'created_at' => $row->created_at,
            ])
            ->all();

        return ApiResponse::success($schools, '', 200, ['total' => count($schools)]);
    }

    public function show(Request $request, int $schoolId): JsonResponse
    {
        $this->auth->authenticate($request);

        $school = DB::table('schools')->where('id', $schoolId)->first();

        if ($school === null) {
            return ApiResponse::error('Escola não encontrada.', 404, 'SCHOOL_NOT_FOUND');
        }

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();

        $totalBookings = DB::table('bookings')->where('school_id', $schoolId)->count();
        $bookingsThisMonth = DB::table('bookings')
            ->where('school_id', $schoolId)
            ->where('created_at', '>=', $startOfMonth)
            ->count();
        $bookingsByStatus = DB::table('bookings')
            ->where('school_id', $schoolId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $activeResources = DB::table('resources')
            ->where('school_id', $schoolId)->where('active', 1)->count();
        $totalResources = DB::table('resources')
            ->where('school_id', $schoolId)->count();

        $totalTeachers = DB::table('users')
            ->where('school_id', $schoolId)->where('role', 'teacher')->where('active', 1)->count();
        $totalTechnicians = DB::table('users')
            ->where('school_id', $schoolId)->where('role', 'technician')->where('active', 1)->count();

        $activeClassGroups = DB::table('class_groups')
            ->where('school_id', $schoolId)->where('active', 1)->count();
        $activeSubjects = DB::table('subjects')
            ->where('school_id', $schoolId)->where('active', 1)->count();
        $activeLessonSlots = DB::table('lesson_slots')
            ->where('school_id', $schoolId)->where('active', 1)->count();

        return ApiResponse::success([
            'id' => (int) $school->id,
            'school_name' => $school->school_name,
            'school_code' => $school->school_code,
            'active' => (bool) $school->active,
            'created_at' => $school->created_at,
            'metrics' => [
                'total_bookings' => $totalBookings,
                'bookings_this_month' => $bookingsThisMonth,
                'bookings_pending' => (int) ($bookingsByStatus['pending'] ?? 0),
                'bookings_completed' => (int) ($bookingsByStatus['completed'] ?? 0),
                'bookings_cancelled' => (int) ($bookingsByStatus['cancelled'] ?? 0),
                'active_resources' => $activeResources,
                'total_resources' => $totalResources,
                'total_teachers' => $totalTeachers,
                'total_technicians' => $totalTechnicians,
                'active_class_groups' => $activeClassGroups,
                'active_subjects' => $activeSubjects,
                'active_lesson_slots' => $activeLessonSlots,
            ],
        ]);
    }

    public function toggleStatus(Request $request, int $schoolId): JsonResponse
    {
        $this->auth->authenticate($request);

        $school = DB::table('schools')->where('id', $schoolId)->first();

        if ($school === null) {
            return ApiResponse::error('Escola não encontrada.', 404, 'SCHOOL_NOT_FOUND');
        }

        $newActive = ! (bool) $school->active;

        DB::table('schools')->where('id', $schoolId)->update([
            'active' => $newActive,
            'updated_at' => now(),
        ]);

        if (! $newActive) {
            DB::table('users')
                ->where('school_id', $schoolId)
                ->update(['api_token' => null, 'api_token_expires_at' => null, 'updated_at' => now()]);
        }

        $label = $newActive ? 'ativada' : 'suspensa';

        return ApiResponse::success(
            ['id' => $schoolId, 'active' => $newActive],
            "Escola {$label} com sucesso."
        );
    }
}
