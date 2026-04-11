<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class BookingStatusService
{
    public function __construct(
        private readonly BookingDatabaseSupport $databaseSupport,
        private readonly BookingNotificationService $notificationService,
    ) {}

    public function cancel(array $payload, User $authUser): void
    {
        $schoolId = (int) $payload['school_id'];
        $bookingId = (int) $payload['booking_id'];
        $userId = (int) $authUser->id;

        $booking = DB::table('bookings as b')
            ->join('users as u', function ($join) use ($userId): void {
                $join->on('u.school_id', '=', 'b.school_id')
                    ->where('u.id', '=', $userId);
            })
            ->where('b.id', $bookingId)
            ->where('b.school_id', $schoolId)
            ->first([
                'b.id',
                'b.user_id',
                'b.status',
                'u.role',
            ]);

        if ($booking === null) {
            throw new HttpResponseException(
                ApiResponse::error('Agendamento não encontrado.', 404, 'BOOKING_NOT_FOUND')
            );
        }

        if ($booking->status === 'cancelled') {
            throw new HttpResponseException(
                ApiResponse::error('Este agendamento já foi cancelado.', 400, 'BOOKING_ALREADY_CANCELLED')
            );
        }

        if ($booking->status === 'completed') {
            throw new HttpResponseException(
                ApiResponse::error(
                    'Agendamentos finalizados não podem ser cancelados.',
                    400,
                    'BOOKING_ALREADY_COMPLETED'
                )
            );
        }

        if ((int) $booking->user_id !== $userId && $booking->role !== 'technician') {
            throw new HttpResponseException(
                ApiResponse::error(
                    'Você não tem permissão para cancelar este agendamento.',
                    403,
                    'BOOKING_CANCEL_FORBIDDEN'
                )
            );
        }

        DB::table('bookings')
            ->where('id', $bookingId)
            ->where('school_id', $schoolId)
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $userId,
            ]);

        $this->notificationService->notifyTechniciansAboutBookingEvent(
            $schoolId,
            $bookingId,
            'booking_cancelled',
            $userId
        );
    }

    public function complete(array $payload, User $authUser): void
    {
        $schoolId = (int) $payload['school_id'];
        $bookingId = (int) $payload['booking_id'];
        $userId = (int) $authUser->id;
        $completionFeedback = $payload['completion_feedback'] ?? null;
        $hasCompletionFeedbackColumn = $this->databaseSupport->columnExists('bookings', 'completion_feedback');

        $booking = DB::table('bookings as b')
            ->join('users as u', function ($join) use ($userId): void {
                $join->on('u.school_id', '=', 'b.school_id')
                    ->where('u.id', '=', $userId);
            })
            ->where('b.id', $bookingId)
            ->where('b.school_id', $schoolId)
            ->first([
                'b.id',
                'b.user_id',
                'b.status',
                'b.booking_date',
                'u.role',
            ]);

        if ($booking === null) {
            throw new HttpResponseException(
                ApiResponse::error('Agendamento não encontrado.', 404, 'BOOKING_NOT_FOUND')
            );
        }

        if ((int) $booking->user_id !== $userId && $booking->role !== 'technician') {
            throw new HttpResponseException(
                ApiResponse::error(
                    'Você não tem permissão para finalizar este agendamento.',
                    403,
                    'BOOKING_COMPLETE_FORBIDDEN'
                )
            );
        }

        if ($booking->status === 'cancelled') {
            throw new HttpResponseException(
                ApiResponse::error(
                    'Agendamentos cancelados não podem ser finalizados.',
                    400,
                    'BOOKING_ALREADY_CANCELLED'
                )
            );
        }

        if ($booking->status === 'completed') {
            throw new HttpResponseException(
                ApiResponse::error('Este agendamento já foi finalizado.', 400, 'BOOKING_ALREADY_COMPLETED')
            );
        }

        $bookingDate = trim((string) ($booking->booking_date ?? ''));
        $today = Carbon::today()->format('Y-m-d');
        if ($bookingDate === '' || $bookingDate > $today) {
            throw new HttpResponseException(
                ApiResponse::error(
                    'Só é possível finalizar reservas no dia do uso ou depois dele.',
                    400,
                    'BOOKING_COMPLETE_TOO_EARLY'
                )
            );
        }

        try {
            $update = [
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by_user_id' => $userId,
            ];

            if ($hasCompletionFeedbackColumn) {
                $update['completion_feedback'] = $completionFeedback;
            }

            DB::table('bookings')
                ->where('id', $bookingId)
                ->where('school_id', $schoolId)
                ->update($update);
        } catch (QueryException) {
            throw new HttpResponseException(
                ApiResponse::error(
                    'Não foi possível finalizar o agendamento. Verifique se a tabela bookings possui as colunas de conclusão esperadas.',
                    500,
                    'BOOKING_COMPLETE_SCHEMA_ERROR'
                )
            );
        }

        $this->notificationService->notifyTechniciansAboutBookingEvent(
            $schoolId,
            $bookingId,
            'booking_completed',
            $userId,
            $completionFeedback
        );
    }
}
