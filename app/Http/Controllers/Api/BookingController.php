<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Booking\ListBookingsRequest;
use App\Http\Requests\Booking\ListMyBookingsRequest;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\CompleteBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Services\Auth\ApiTokenAuthService;
use App\Services\Booking\CreateBookingService;
use App\Services\Booking\BookingStatusService;
use App\Services\Booking\BookingQueryService;
use App\Services\Booking\BookingDatabaseSupport;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class BookingController
{
    public function __construct(
        private readonly BookingQueryService $bookingQueryService,
        private readonly ApiTokenAuthService $authService,
        private readonly CreateBookingService $createBookingService,
        private readonly BookingStatusService $bookingStatusService,
        private readonly BookingDatabaseSupport $databaseSupport,
    ) {
    }

    public function index(ListBookingsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $schoolId = (int) $filters['school_id'];

        $this->authService->authenticate($request, $schoolId, 'technician');
        $result = $this->bookingQueryService->paginate($filters);

        return ApiResponse::success(
            $result['data'],
            'Todos os agendamentos listados com sucesso.',
            200,
            $result['meta']
        );
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId);
        $result = $this->createBookingService->create($payload, $authUser);

        return ApiResponse::success(
            $result['data'],
            $result['message'],
            $result['status']
        );
    }

    public function myBookings(ListMyBookingsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $schoolId = (int) $filters['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId);
        $result = $this->bookingQueryService->paginateMyBookings(
            $filters,
            (int) $authUser->id,
            $this->databaseSupport->columnExists('bookings', 'completion_feedback')
        );

        return ApiResponse::success(
            $result['data'],
            'Agendamentos do usuário listados com sucesso.',
            200,
            $result['meta']
        );
    }

    public function cancel(CancelBookingRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId);

        $this->bookingStatusService->cancel($payload, $authUser);

        return ApiResponse::success(null, 'Agendamento cancelado com sucesso.');
    }

    public function complete(CompleteBookingRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId);

        $this->bookingStatusService->complete($payload, $authUser);

        return ApiResponse::success(null, 'Agendamento finalizado com sucesso.');
    }
}
