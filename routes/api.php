<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ClassGroupController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InternalController;
use App\Http\Controllers\Api\LessonSlotController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\SchoolRegistrationController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TeacherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Canonical API routes
|--------------------------------------------------------------------------
|
| The migration now supports three route layers at once:
| - canonical routes for the long-term Laravel API shape
| - compatibility routes already used during the migration
| - legacy .php aliases so the Flutter app can swap backends without
|   changing every client endpoint immediately
|
*/

Route::get('/health', HealthController::class)->name('health.show');
Route::get('/health.php', HealthController::class)->name('legacy.health');

Route::controller(AuthController::class)->group(function (): void {
    Route::post('/login', 'login')->name('auth.login')->middleware('throttle:5,1');
    Route::post('/logout', 'logout')->name('auth.logout');

    Route::post('/login.php', 'login')->name('legacy.login')->middleware('throttle:5,1');
    Route::post('/logout.php', 'logout')->name('legacy.logout');
});

Route::controller(AccountController::class)->group(function (): void {
    Route::post('/account/change-password', 'changePassword')->name('account.change-password')->middleware('throttle:5,1');
    Route::post('/change-my-password', 'changePassword')->name('compat.account.change-password')->middleware('throttle:5,1');
    Route::post('/change_my_password.php', 'changePassword')->name('legacy.change-my-password')->middleware('throttle:5,1');
});

Route::controller(SchoolRegistrationController::class)->group(function (): void {
    Route::post('/schools/register', 'store')->name('schools.register')->middleware('throttle:3,1');
    Route::post('/register-school', 'store')->name('compat.schools.register')->middleware('throttle:3,1');
    Route::post('/register_school.php', 'store')->name('legacy.register-school')->middleware('throttle:3,1');
});

Route::controller(InternalController::class)->group(function (): void {
    Route::get('/internal/check-database-connection', 'checkDatabaseConnection')->name('internal.check-database-connection');
    Route::match(['get', 'post'], '/internal/send-booking-completion-reminders', 'sendBookingCompletionReminders')->name('internal.send-booking-completion-reminders');

    Route::get('/check-supabase-connection', 'checkDatabaseConnection')->name('compat.internal.check-database-connection');
    Route::match(['get', 'post'], '/send-booking-completion-reminders', 'sendBookingCompletionReminders')->name('compat.internal.send-booking-completion-reminders');

    Route::get('/check_supabase_connection.php', 'checkDatabaseConnection')->name('legacy.check-supabase-connection');
    Route::match(['get', 'post'], '/send_booking_completion_reminders.php', 'sendBookingCompletionReminders')->name('legacy.send-booking-completion-reminders');
});

Route::controller(BookingController::class)->group(function (): void {
    Route::get('/bookings', 'index')->name('bookings.index');
    Route::get('/my-bookings', 'myBookings')->name('bookings.mine');
    Route::post('/bookings', 'store')->name('bookings.store')->middleware('throttle:20,1');
    Route::post('/bookings/cancel', 'cancel')->name('bookings.cancel');
    Route::post('/bookings/complete', 'complete')->name('bookings.complete');

    Route::get('/get_all_bookings.php', 'index')->name('legacy.bookings.index');
    Route::get('/get_my_bookings.php', 'myBookings')->name('legacy.bookings.mine');
    Route::post('/create_booking.php', 'store')->name('legacy.bookings.store')->middleware('throttle:20,1');
    Route::post('/cancel_booking.php', 'cancel')->name('legacy.bookings.cancel');
    Route::post('/complete_booking.php', 'complete')->name('legacy.bookings.complete');
});

Route::controller(LookupController::class)->group(function (): void {
    Route::get('/resources', 'resources')->name('lookups.resources');
    Route::get('/class-groups', 'classGroups')->name('lookups.class-groups');
    Route::get('/subjects', 'subjects')->name('lookups.subjects');
    Route::get('/available-lessons', 'availableLessons')->name('lookups.available-lessons');

    Route::get('/get_resources.php', 'resources')->name('legacy.lookups.resources');
    Route::get('/get_class_groups.php', 'classGroups')->name('legacy.lookups.class-groups');
    Route::get('/get_subjects.php', 'subjects')->name('legacy.lookups.subjects');
    Route::get('/get_available_lessons.php', 'availableLessons')->name('legacy.lookups.available-lessons');
});

Route::controller(NotificationController::class)->group(function (): void {
    Route::get('/notifications', 'index')->name('notifications.index');
    Route::get('/notifications/unread-count', 'unreadCount')->name('notifications.unread-count');
    Route::post('/notifications/read', 'markRead')->name('notifications.read');
    Route::post('/notifications/read-all', 'markAllRead')->name('notifications.read-all');

    Route::get('/get_notifications.php', 'index')->name('legacy.notifications.index');
    Route::get('/get_notifications_unread_count.php', 'unreadCount')->name('legacy.notifications.unread-count');
    Route::post('/mark_notification_read.php', 'markRead')->name('legacy.notifications.read');
    Route::post('/mark_all_notifications_read.php', 'markAllRead')->name('legacy.notifications.read-all');
});

Route::prefix('/admin')->name('admin.')->group(function (): void {
    Route::controller(TeacherController::class)->group(function (): void {
        Route::get('/teachers', 'index')->name('teachers.index');
        Route::post('/teachers', 'store')->name('teachers.store');
        Route::post('/teachers/update', 'update')->name('teachers.update');
        Route::post('/teachers/toggle-status', 'toggleStatus')->name('teachers.toggle-status');
        Route::post('/teachers/reset-password', 'resetPassword')->name('teachers.reset-password');
    });

    Route::controller(SubjectController::class)->group(function (): void {
        Route::get('/subjects', 'index')->name('subjects.index');
        Route::post('/subjects', 'store')->name('subjects.store');
        Route::post('/subjects/update', 'update')->name('subjects.update');
        Route::post('/subjects/toggle-status', 'toggleStatus')->name('subjects.toggle-status');
    });

    Route::controller(ClassGroupController::class)->group(function (): void {
        Route::get('/class-groups', 'index')->name('class-groups.index');
        Route::post('/class-groups', 'store')->name('class-groups.store');
        Route::post('/class-groups/update', 'update')->name('class-groups.update');
        Route::post('/class-groups/toggle-status', 'toggleStatus')->name('class-groups.toggle-status');
    });

    Route::controller(ResourceController::class)->group(function (): void {
        Route::get('/resource-categories', 'categories')->name('resource-categories.index');
        Route::post('/resources', 'store')->name('resources.store');
        Route::post('/resources/update', 'update')->name('resources.update');
        Route::post('/resources/toggle-status', 'toggleStatus')->name('resources.toggle-status');
    });

    Route::controller(LessonSlotController::class)->group(function (): void {
        Route::get('/lesson-slots', 'index')->name('lesson-slots.index');
        Route::post('/lesson-slots', 'store')->name('lesson-slots.store');
        Route::post('/lesson-slots/update', 'update')->name('lesson-slots.update');
        Route::post('/lesson-slots/toggle-status', 'toggleStatus')->name('lesson-slots.toggle-status');
    });
});

Route::controller(TeacherController::class)->group(function (): void {
    Route::get('/teachers', 'index')->name('compat.teachers.index');
    Route::post('/teachers', 'store')->name('compat.teachers.store');
    Route::post('/teachers/update', 'update')->name('compat.teachers.update');
    Route::post('/teachers/toggle-status', 'toggleStatus')->name('compat.teachers.toggle-status');
    Route::post('/teachers/reset-password', 'resetPassword')->name('compat.teachers.reset-password');

    Route::get('/get_teachers.php', 'index')->name('legacy.teachers.index');
    Route::post('/create_teacher.php', 'store')->name('legacy.teachers.store');
    Route::post('/update_teacher.php', 'update')->name('legacy.teachers.update');
    Route::post('/toggle_teacher_status.php', 'toggleStatus')->name('legacy.teachers.toggle-status');
    Route::post('/reset_teacher_password.php', 'resetPassword')->name('legacy.teachers.reset-password');
});

Route::controller(SubjectController::class)->group(function (): void {
    Route::get('/subjects-admin', 'index')->name('compat.subjects.index');
    Route::post('/subjects-admin', 'store')->name('compat.subjects.store');
    Route::post('/subjects-admin/update', 'update')->name('compat.subjects.update');
    Route::post('/subjects-admin/toggle-status', 'toggleStatus')->name('compat.subjects.toggle-status');

    Route::get('/get_subjects_admin.php', 'index')->name('legacy.subjects.index');
    Route::post('/create_subject.php', 'store')->name('legacy.subjects.store');
    Route::post('/update_subject.php', 'update')->name('legacy.subjects.update');
    Route::post('/toggle_subject_status.php', 'toggleStatus')->name('legacy.subjects.toggle-status');
});

Route::controller(ClassGroupController::class)->group(function (): void {
    Route::get('/class-groups-admin', 'index')->name('compat.class-groups.index');
    Route::post('/class-groups-admin', 'store')->name('compat.class-groups.store');
    Route::post('/class-groups-admin/update', 'update')->name('compat.class-groups.update');
    Route::post('/class-groups-admin/toggle-status', 'toggleStatus')->name('compat.class-groups.toggle-status');

    Route::get('/get_class_groups_admin.php', 'index')->name('legacy.class-groups.index');
    Route::post('/create_class_group.php', 'store')->name('legacy.class-groups.store');
    Route::post('/update_class_group.php', 'update')->name('legacy.class-groups.update');
    Route::post('/toggle_class_group_status.php', 'toggleStatus')->name('legacy.class-groups.toggle-status');
});

Route::controller(ResourceController::class)->group(function (): void {
    Route::get('/resource-categories', 'categories')->name('compat.resource-categories.index');
    Route::get('/resources/categories', 'categories')->name('compat.resources.categories');
    Route::post('/resources-admin', 'store')->name('compat.resources.store');
    Route::post('/resources-admin/update', 'update')->name('compat.resources.update');
    Route::post('/resources-admin/toggle-status', 'toggleStatus')->name('compat.resources.toggle-status');

    Route::get('/get_resource_categories.php', 'categories')->name('legacy.resource-categories.index');
    Route::post('/create_resource.php', 'store')->name('legacy.resources.store');
    Route::post('/update_resource.php', 'update')->name('legacy.resources.update');
    Route::post('/toggle_resource_status.php', 'toggleStatus')->name('legacy.resources.toggle-status');
});

Route::controller(LessonSlotController::class)->group(function (): void {
    Route::get('/lesson-slots', 'index')->name('compat.lesson-slots.canonical');
    Route::get('/lesson-slots-admin', 'index')->name('compat.lesson-slots.index');
    Route::post('/lesson-slots-admin', 'store')->name('compat.lesson-slots.store');
    Route::post('/lesson-slots-admin/update', 'update')->name('compat.lesson-slots.update');
    Route::post('/lesson-slots-admin/toggle-status', 'toggleStatus')->name('compat.lesson-slots.toggle-status');

    Route::get('/get_lesson_slots_admin.php', 'index')->name('legacy.lesson-slots.index');
    Route::post('/create_lesson_slot.php', 'store')->name('legacy.lesson-slots.store');
    Route::post('/update_lesson_slot.php', 'update')->name('legacy.lesson-slots.update');
    Route::post('/toggle_lesson_slot_status.php', 'toggleStatus')->name('legacy.lesson-slots.toggle-status');
});
