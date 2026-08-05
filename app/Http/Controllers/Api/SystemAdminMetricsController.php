<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Auth\SystemAdminTokenService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SystemAdminMetricsController
{
    public function __construct(
        private readonly SystemAdminTokenService $auth,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->auth->authenticate($request);

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();

        $schoolStats = DB::table('schools')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN active = true THEN 1 ELSE 0 END) as active_count'),
                DB::raw('SUM(CASE WHEN active = false THEN 1 ELSE 0 END) as suspended_count'),
                DB::raw('SUM(CASE WHEN created_at >= \'' . $startOfMonth->toDateTimeString() . '\' THEN 1 ELSE 0 END) as new_this_month'),
            )
            ->first();

        $userStats = DB::table('users')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN role = \'teacher\' THEN 1 ELSE 0 END) as teachers'),
                DB::raw('SUM(CASE WHEN role = \'technician\' THEN 1 ELSE 0 END) as technicians'),
            )
            ->first();

        $bookingStats = DB::table('bookings')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status = \'cancelled\' THEN 1 ELSE 0 END) as cancelled'),
                DB::raw('SUM(CASE WHEN created_at >= \'' . $startOfMonth->toDateTimeString() . '\' THEN 1 ELSE 0 END) as this_month'),
            )
            ->first();

        $recentSchools = DB::table('schools')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'school_name', 'school_code', 'active', 'created_at'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'school_name' => $row->school_name,
                'school_code' => $row->school_code,
                'active' => (bool) $row->active,
                'created_at' => $row->created_at,
            ])
            ->all();

        return ApiResponse::success([
            'schools' => [
                'total' => (int) ($schoolStats->total ?? 0),
                'active' => (int) ($schoolStats->active_count ?? 0),
                'suspended' => (int) ($schoolStats->suspended_count ?? 0),
                'new_this_month' => (int) ($schoolStats->new_this_month ?? 0),
            ],
            'users' => [
                'total' => (int) ($userStats->total ?? 0),
                'teachers' => (int) ($userStats->teachers ?? 0),
                'technicians' => (int) ($userStats->technicians ?? 0),
            ],
            'bookings' => [
                'total' => (int) ($bookingStats->total ?? 0),
                'this_month' => (int) ($bookingStats->this_month ?? 0),
                'pending' => (int) ($bookingStats->pending ?? 0),
                'completed' => (int) ($bookingStats->completed ?? 0),
                'cancelled' => (int) ($bookingStats->cancelled ?? 0),
            ],
            'recent_schools' => $recentSchools,
        ]);
    }
}
