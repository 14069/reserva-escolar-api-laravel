<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Support\ApiTimestamp;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

final class NotificationService
{
    public function listForUser(array $filters, int $userId): array
    {
        $schoolId = (int) $filters['school_id'];
        $unreadOnly = ($filters['unread_only'] ?? '0') === '1';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($filters['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $baseQuery = DB::table('notifications')
            ->where('school_id', $schoolId)
            ->where('user_id', $userId);

        if ($unreadOnly) {
            $baseQuery->whereNull('read_at');
        }

        $total = (int) (clone $baseQuery)->count();
        $unreadCount = $this->getUnreadCount($schoolId, $userId);

        $notifications = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($pageSize)
            ->get([
                'id',
                'type',
                'title',
                'message',
                'booking_id',
                'metadata_json',
                'read_at',
                'created_at',
            ])
            ->map(static function (object $row): array {
                $metadata = null;
                if (is_string($row->metadata_json) && trim($row->metadata_json) !== '') {
                    $decoded = json_decode($row->metadata_json, true);
                    $metadata = is_array($decoded) ? $decoded : null;
                }

                return [
                    'id' => (int) $row->id,
                    'type' => $row->type,
                    'title' => $row->title,
                    'message' => $row->message,
                    'booking_id' => $row->booking_id !== null ? (int) $row->booking_id : null,
                    'metadata' => $metadata,
                    'read_at' => ApiTimestamp::serialize($row->read_at),
                    'created_at' => ApiTimestamp::serialize($row->created_at) ?? '',
                ];
            })
            ->all();

        $totalPages = (int) ceil($total / $pageSize);

        return [
            'data' => $notifications,
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next_page' => $page < $totalPages,
                'summary' => [
                    'unread_count' => $unreadCount,
                ],
            ],
        ];
    }

    public function getUnreadCount(int $schoolId, int $userId): int
    {
        return (int) DB::table('notifications')
            ->where('school_id', $schoolId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(int $schoolId, int $userId, int $notificationId): void
    {
        $baseQuery = DB::table('notifications')
            ->where('id', $notificationId)
            ->where('school_id', $schoolId)
            ->where('user_id', $userId);

        if (! (clone $baseQuery)->exists()) {
            throw new HttpResponseException(
                ApiResponse::error('Notificação não encontrada.', 404, 'NOTIFICATION_NOT_FOUND')
            );
        }

        $baseQuery
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }

    public function markAllRead(int $schoolId, int $userId): int
    {
        return DB::table('notifications')
            ->where('school_id', $schoolId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }
}
