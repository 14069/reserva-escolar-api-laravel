<?php

declare(strict_types=1);

namespace App\Services\Booking;

use Illuminate\Support\Facades\DB;
use Throwable;

final class BookingNotificationService
{
    public function notifyTechniciansAboutBookingCreated(
        int $schoolId,
        int $bookingId,
        int $actorUserId
    ): void {
        $this->notifyTechniciansAboutBookingEvent(
            $schoolId,
            $bookingId,
            'booking_created',
            $actorUserId
        );
    }

    public function notifyTechniciansAboutBookingEvent(
        int $schoolId,
        int $bookingId,
        string $type,
        int $actorUserId,
        ?string $completionFeedback = null
    ): void {
        try {
            $context = DB::table('bookings as b')
                ->join('resources as r', 'r.id', '=', 'b.resource_id')
                ->join('users as u', 'u.id', '=', 'b.user_id')
                ->join('class_groups as cg', 'cg.id', '=', 'b.class_group_id')
                ->join('subjects as s', 's.id', '=', 'b.subject_id')
                ->where('b.school_id', $schoolId)
                ->where('b.id', $bookingId)
                ->first([
                    'b.id',
                    'b.school_id',
                    'b.user_id',
                    'b.booking_date',
                    'r.name as resource_name',
                    'u.name as user_name',
                    'cg.name as class_group_name',
                    's.name as subject_name',
                ]);

            if ($context === null) {
                return;
            }

            $technicianIds = DB::table('users')
                ->where('school_id', $schoolId)
                ->where('role', 'technician')
                ->where('active', 1)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($technicianIds === []) {
                return;
            }

            $resourceName = trim((string) ($context->resource_name ?? 'Recurso'));
            $userName = trim((string) ($context->user_name ?? 'Professor'));
            $bookingDate = trim((string) ($context->booking_date ?? ''));
            $feedback = trim((string) ($completionFeedback ?? ''));

            $title = 'Atualização de agendamento';
            $message = $userName . ' atualizou o agendamento de ' . $resourceName . '.';

            if ($type === 'booking_created') {
                $title = 'Novo agendamento criado';
                $message = $userName . ' agendou ' . $resourceName . ' para ' . $bookingDate . '.';
            } elseif ($type === 'booking_cancelled') {
                $title = 'Agendamento cancelado';
                $message = $userName . ' cancelou o agendamento de ' . $resourceName . ' em ' . $bookingDate . '.';
            } elseif ($type === 'booking_completed') {
                $title = 'Agendamento finalizado';
                $message = $userName . ' finalizou o agendamento de ' . $resourceName . '.';
                if ($feedback !== '') {
                    $message .= ' Feedback: ' . $feedback;
                }
            }

            $metadata = [
                'resource_name' => $resourceName,
                'booking_date' => $bookingDate,
                'user_name' => $userName,
                'class_group_name' => (string) ($context->class_group_name ?? ''),
                'subject_name' => (string) ($context->subject_name ?? ''),
                'actor_user_id' => $actorUserId,
            ];

            if ($feedback !== '') {
                $metadata['completion_feedback'] = $feedback;
            }

            $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $rows = array_map(function (int $technicianId) use (
                $schoolId,
                $bookingId,
                $type,
                $title,
                $message,
                $resourceName,
                $userName,
                $metadataJson
            ): array {
                return [
                    'school_id' => $schoolId,
                    'user_id' => $technicianId,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'booking_id' => $bookingId,
                    'metadata_json' => $metadataJson,
                    'created_at' => now(),
                ];
            }, $technicianIds);

            DB::table('notifications')->insert($rows);
        } catch (Throwable $error) {
            logger()->error('Create booking notification failed.', [
                'message' => $error->getMessage(),
                'booking_id' => $bookingId,
            ]);
        }
    }
}
