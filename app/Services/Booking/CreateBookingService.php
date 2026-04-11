<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CreateBookingService
{
    public function __construct(
        private readonly BookingDatabaseSupport $databaseSupport,
        private readonly BookingNotificationService $notificationService,
    ) {}

    public function create(array $payload, User $authUser): array
    {
        $schoolId = (int) $payload['school_id'];
        $resourceId = (int) $payload['resource_id'];
        $classGroupId = (int) $payload['class_group_id'];
        $subjectId = (int) $payload['subject_id'];
        $bookingDate = (string) $payload['booking_date'];
        $purpose = (string) ($payload['purpose'] ?? '');
        $lessonIds = array_values(array_unique(array_map('intval', $payload['lesson_ids'] ?? [])));
        $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));
        sort($lessonIds);

        $userId = (int) $authUser->id;
        $hasIdempotencyKeyColumn = $idempotencyKey !== '' &&
            $this->databaseSupport->columnExists('bookings', 'idempotency_key');

        $lockName = null;

        try {
            DB::beginTransaction();

            $this->assertReferenceExists('users', $userId, $schoolId, 'Usuário inválido para esta escola.');
            $this->assertReferenceExists('resources', $resourceId, $schoolId, 'Recurso inválido para esta escola.');

            $lockName = $this->databaseSupport->acquireBookingCreationLock(
                $schoolId,
                $resourceId,
                $bookingDate
            );

            if ($lockName === false) {
                throw new HttpResponseException(
                    ApiResponse::error(
                        'Não foi possível processar o agendamento agora. Tente novamente em instantes.',
                        503,
                        'BOOKING_LOCK_TIMEOUT'
                    )
                );
            }

            $this->assertReferenceExists('class_groups', $classGroupId, $schoolId, 'Turma inválida para esta escola.');
            $this->assertReferenceExists('subjects', $subjectId, $schoolId, 'Disciplina inválida para esta escola.');
            $this->assertLessonIdsAreValid($schoolId, $lessonIds);

            if ($hasIdempotencyKeyColumn) {
                $existingBookingId = $this->findExistingBookingIdForIdempotency($schoolId, $userId, $idempotencyKey);
                if ($existingBookingId !== null) {
                    DB::commit();
                    $this->databaseSupport->releaseBookingCreationLock($lockName);

                    return [
                        'status' => 200,
                        'message' => 'Agendamento já processado anteriormente.',
                        'data' => [
                            'booking_id' => $existingBookingId,
                            'resource_id' => $resourceId,
                            'booking_date' => $bookingDate,
                            'lesson_ids' => $lessonIds,
                        ],
                    ];
                }
            }

            $conflicts = $this->fetchBookingConflictsForLessons($schoolId, $resourceId, $bookingDate, $lessonIds);
            if ($conflicts !== []) {
                DB::rollBack();
                $this->databaseSupport->releaseBookingCreationLock($lockName);
                $this->throwBookingConflict($resourceId, $bookingDate, $conflicts);
            }

            $bookingInsert = [
                'school_id' => $schoolId,
                'resource_id' => $resourceId,
                'user_id' => $userId,
                'class_group_id' => $classGroupId,
                'subject_id' => $subjectId,
                'booking_date' => $bookingDate,
                'purpose' => $purpose,
                'status' => 'scheduled',
            ];

            if ($hasIdempotencyKeyColumn) {
                $bookingInsert['idempotency_key'] = $idempotencyKey;
            }

            $bookingId = (int) DB::table('bookings')->insertGetId($bookingInsert);

            $bookingLessons = array_map(static fn (int $lessonId): array => [
                'booking_id' => $bookingId,
                'lesson_slot_id' => $lessonId,
            ], $lessonIds);
            DB::table('booking_lessons')->insert($bookingLessons);

            DB::commit();
            $this->databaseSupport->releaseBookingCreationLock($lockName);

            $this->notificationService->notifyTechniciansAboutBookingCreated($schoolId, $bookingId, $userId);

            return [
                'status' => 201,
                'message' => 'Agendamento criado com sucesso.',
                'data' => [
                    'booking_id' => $bookingId,
                    'resource_id' => $resourceId,
                    'booking_date' => $bookingDate,
                    'lesson_ids' => $lessonIds,
                ],
            ];
        } catch (HttpResponseException $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->databaseSupport->releaseBookingCreationLock($lockName);
            throw $exception;
        } catch (QueryException $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->databaseSupport->releaseBookingCreationLock($lockName);

            if ($hasIdempotencyKeyColumn) {
                $normalizedErrorMessage = strtolower($exception->getMessage());
                $errorCode = (string) ($exception->errorInfo[0] ?? $exception->getCode());

                if (
                    str_contains($normalizedErrorMessage, 'idempotency') ||
                    in_array($errorCode, ['23505', '23000'], true)
                ) {
                    $existingBookingId = $this->findExistingBookingIdForIdempotency($schoolId, $userId, $idempotencyKey);
                    if ($existingBookingId !== null) {
                        return [
                            'status' => 200,
                            'message' => 'Agendamento já processado anteriormente.',
                            'data' => [
                                'booking_id' => $existingBookingId,
                                'resource_id' => $resourceId,
                                'booking_date' => $bookingDate,
                                'lesson_ids' => $lessonIds,
                            ],
                        ];
                    }
                }
            }

            $sqlState = (string) ($exception->errorInfo[0] ?? '');
            $normalizedErrorMessage = strtolower($exception->getMessage());
            if ($sqlState === 'P0001' && str_contains($normalizedErrorMessage, 'booking_conflict')) {
                $conflicts = $this->fetchBookingConflictsForLessons($schoolId, $resourceId, $bookingDate, $lessonIds);
                if ($conflicts !== []) {
                    $this->throwBookingConflict($resourceId, $bookingDate, $conflicts);
                }
            }

            throw new HttpResponseException(
                ApiResponse::error('Erro ao criar agendamento.', 500, 'INTERNAL_SERVER_ERROR')
            );
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->databaseSupport->releaseBookingCreationLock($lockName);

            throw new HttpResponseException(
                ApiResponse::error('Erro ao criar agendamento.', 500, 'INTERNAL_SERVER_ERROR')
            );
        }
    }

    private function assertReferenceExists(
        string $tableName,
        int $id,
        int $schoolId,
        string $message
    ): void {
        $exists = DB::table($tableName)
            ->where('id', $id)
            ->where('school_id', $schoolId)
            ->where('active', 1)
            ->exists();

        if (! $exists) {
            throw new HttpResponseException(
                ApiResponse::error($message, 404, 'BOOKING_REFERENCE_NOT_FOUND')
            );
        }
    }

    private function assertLessonIdsAreValid(int $schoolId, array $lessonIds): void
    {
        $validLessonCount = DB::table('lesson_slots')
            ->where('school_id', $schoolId)
            ->where('active', 1)
            ->whereIn('id', $lessonIds)
            ->count();

        if ($validLessonCount !== count($lessonIds)) {
            throw new HttpResponseException(
                ApiResponse::error(
                    'Uma ou mais aulas selecionadas são inválidas.',
                    400,
                    'BOOKING_INVALID_LESSONS'
                )
            );
        }
    }

    private function findExistingBookingIdForIdempotency(
        int $schoolId,
        int $userId,
        string $idempotencyKey
    ): ?int {
        $bookingId = DB::table('bookings')
            ->where('school_id', $schoolId)
            ->where('user_id', $userId)
            ->where('idempotency_key', $idempotencyKey)
            ->value('id');

        return $bookingId !== null ? (int) $bookingId : null;
    }

    private function fetchBookingConflictsForLessons(
        int $schoolId,
        int $resourceId,
        string $bookingDate,
        array $lessonIds
    ): array {
        if ($lessonIds === []) {
            return [];
        }

        return DB::table('booking_lessons as bl')
            ->join('bookings as b', 'b.id', '=', 'bl.booking_id')
            ->join('lesson_slots as ls', 'ls.id', '=', 'bl.lesson_slot_id')
            ->where('b.school_id', $schoolId)
            ->where('b.resource_id', $resourceId)
            ->where('b.booking_date', $bookingDate)
            ->where('b.status', 'scheduled')
            ->whereIn('bl.lesson_slot_id', $lessonIds)
            ->orderBy('ls.lesson_number')
            ->get([
                'bl.lesson_slot_id',
                'ls.label',
                'ls.lesson_number',
            ])
            ->map(static fn (object $row): array => [
                'lesson_slot_id' => (int) $row->lesson_slot_id,
                'label' => (string) $row->label,
                'lesson_number' => (int) $row->lesson_number,
            ])
            ->all();
    }

    private function throwBookingConflict(int $resourceId, string $bookingDate, array $conflicts): never
    {
        $unavailableLessonIds = array_map(
            static fn (array $row): int => (int) ($row['lesson_slot_id'] ?? 0),
            $conflicts
        );

        throw new HttpResponseException(
            ApiResponse::error(
                'Esse horário acabou de ser reservado por outro professor.',
                409,
                'BOOKING_CONFLICT',
                [
                    'resource_id' => $resourceId,
                    'booking_date' => $bookingDate,
                    'unavailable_lesson_ids' => $unavailableLessonIds,
                    'unavailable_lessons' => $conflicts,
                ]
            )
        );
    }
}
