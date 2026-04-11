<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Notification\ListNotificationsRequest;
use App\Http\Requests\Notification\MarkAllNotificationsReadRequest;
use App\Http\Requests\Notification\MarkNotificationReadRequest;
use App\Http\Requests\Notification\UnreadNotificationsCountRequest;
use App\Services\Auth\ApiTokenAuthService;
use App\Services\Notification\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class NotificationController
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly ApiTokenAuthService $authService,
    ) {}

    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $schoolId = (int) $filters['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId);
        $result = $this->notificationService->listForUser($filters, (int) $authUser->id);

        return ApiResponse::success(
            $result['data'],
            'Notificações listadas com sucesso.',
            200,
            $result['meta']
        );
    }

    public function unreadCount(UnreadNotificationsCountRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId);

        return ApiResponse::success(
            ['unread_count' => $this->notificationService->getUnreadCount($schoolId, (int) $authUser->id)],
            'Contagem de notificações carregada com sucesso.'
        );
    }

    public function markRead(MarkNotificationReadRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId);

        $this->notificationService->markRead(
            $schoolId,
            (int) $authUser->id,
            (int) $payload['notification_id']
        );

        return ApiResponse::success(null, 'Notificação marcada como lida.');
    }

    public function markAllRead(MarkAllNotificationsReadRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $schoolId = (int) $payload['school_id'];
        $authUser = $this->authService->authenticate($request, $schoolId);
        $updatedCount = $this->notificationService->markAllRead($schoolId, (int) $authUser->id);

        return ApiResponse::success(
            ['updated_count' => $updatedCount],
            'Todas as notificações foram marcadas como lidas.'
        );
    }
}
