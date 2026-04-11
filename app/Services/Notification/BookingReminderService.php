<?php

declare(strict_types=1);

namespace App\Services\Notification;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class BookingReminderService
{
    public function sendCompletionReminders(): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $nowTime = Carbon::now()->format('H:i:s');

        $bookings = DB::table('bookings as b')
            ->join('resources as r', 'r.id', '=', 'b.resource_id')
            ->join('booking_lessons as bl', 'bl.booking_id', '=', 'b.id')
            ->join('lesson_slots as ls', 'ls.id', '=', 'bl.lesson_slot_id')
            ->where('b.status', 'scheduled')
            ->groupBy('b.id', 'b.school_id', 'b.user_id', 'b.booking_date', 'r.name')
            ->orderBy('b.booking_date')
            ->orderBy('latest_end_time')
            ->orderBy('b.id')
            ->get([
                'b.id',
                'b.school_id',
                'b.user_id',
                'b.booking_date',
                'r.name as resource_name',
                DB::raw('MAX(ls.end_time) as latest_end_time'),
            ]);

        $createdCount = 0;
        $createdBookingIds = [];
        $evaluatedCount = 0;

        foreach ($bookings as $booking) {
            $evaluatedCount++;

            $bookingId = (int) $booking->id;
            $schoolId = (int) $booking->school_id;
            $userId = (int) $booking->user_id;
            $bookingDate = trim((string) $booking->booking_date);
            $resourceName = trim((string) $booking->resource_name);
            $latestEndTime = trim((string) ($booking->latest_end_time ?? ''));

            $isOverdue = false;
            if ($bookingDate !== '' && $bookingDate < $today) {
                $isOverdue = true;
            } elseif ($bookingDate === $today && $latestEndTime !== '' && $latestEndTime <= $nowTime) {
                $isOverdue = true;
            }

            if (! $isOverdue) {
                continue;
            }

            $alreadySent = DB::table('notifications')
                ->where('school_id', $schoolId)
                ->where('user_id', $userId)
                ->where('type', 'booking_reminder_complete')
                ->where('booking_id', $bookingId)
                ->whereDate('created_at', Carbon::today())
                ->exists();

            if ($alreadySent) {
                continue;
            }

            DB::table('notifications')->insert([
                'school_id' => $schoolId,
                'user_id' => $userId,
                'type' => 'booking_reminder_complete',
                'title' => 'Finalize seu agendamento',
                'message' => 'O período reservado de '.$resourceName.' já terminou. Finalize o agendamento para liberar o recurso.',
                'booking_id' => $bookingId,
                'metadata_json' => json_encode([
                    'resource_name' => $resourceName,
                    'booking_date' => $bookingDate,
                    'latest_end_time' => $latestEndTime,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);

            $createdCount++;
            $createdBookingIds[] = $bookingId;
        }

        return [
            'evaluated_count' => $evaluatedCount,
            'created_count' => $createdCount,
            'booking_ids' => $createdBookingIds,
        ];
    }
}
