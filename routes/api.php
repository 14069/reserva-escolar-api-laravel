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
use App\Http\Controllers\Api\SystemAdminAuthController;
use App\Http\Controllers\Api\SystemAdminMetricsController;
use App\Http\Controllers\Api\SystemAdminSchoolController;
use App\Http\Controllers\Api\TeacherController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health.show');

Route::controller(AuthController::class)->group(function (): void {
    Route::post('/login', 'login')->name('auth.login')->middleware('throttle:5,1');
    Route::post('/logout', 'logout')->name('auth.logout');
});

Route::prefix('system-admin')->name('system-admin.')->group(function (): void {
    Route::controller(SystemAdminAuthController::class)->group(function (): void {
        Route::post('/login', 'login')->name('login')->middleware('throttle:5,1');
        Route::post('/logout', 'logout')->name('logout');
    });

    Route::get('/schools', [SystemAdminSchoolController::class, 'index'])->name('schools.index');
    Route::get('/schools/{school}', [SystemAdminSchoolController::class, 'show'])->name('schools.show');
    Route::post('/schools/{school}/toggle-status', [SystemAdminSchoolController::class, 'toggleStatus'])->name('schools.toggle-status');

    Route::get('/metrics', [SystemAdminMetricsController::class, 'index'])->name('metrics');
});

Route::post('/account/change-password', [AccountController::class, 'changePassword'])
    ->name('account.change-password')
    ->middleware('throttle:5,1');

Route::post('/schools/register', [SchoolRegistrationController::class, 'store'])
    ->name('schools.register')
    ->middleware('throttle:3,1');

Route::controller(InternalController::class)->group(function (): void {
    Route::get('/internal/check-database-connection', 'checkDatabaseConnection')->name('internal.check-database-connection');
    Route::match(['get', 'post'], '/internal/send-booking-completion-reminders', 'sendBookingCompletionReminders')->name('internal.send-booking-completion-reminders');
});

Route::controller(BookingController::class)->group(function (): void {
    Route::get('/bookings', 'index')->name('bookings.index');
    Route::get('/my-bookings', 'myBookings')->name('bookings.mine');
    Route::post('/bookings', 'store')->name('bookings.store')->middleware('throttle:20,1');
    Route::post('/bookings/cancel', 'cancel')->name('bookings.cancel');
    Route::post('/bookings/complete', 'complete')->name('bookings.complete');
});

Route::controller(LookupController::class)->group(function (): void {
    Route::get('/resources', 'resources')->name('lookups.resources');
    Route::get('/class-groups', 'classGroups')->name('lookups.class-groups');
    Route::get('/subjects', 'subjects')->name('lookups.subjects');
    Route::get('/available-lessons', 'availableLessons')->name('lookups.available-lessons');
});

Route::controller(NotificationController::class)->group(function (): void {
    Route::get('/notifications', 'index')->name('notifications.index');
    Route::get('/notifications/unread-count', 'unreadCount')->name('notifications.unread-count');
    Route::post('/notifications/read', 'markRead')->name('notifications.read');
    Route::post('/notifications/read-all', 'markAllRead')->name('notifications.read-all');
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
        Route::post('/lesson-slots/update', 'update')->name('lesson-slots/update');
        Route::post('/lesson-slots/toggle-status', 'toggleStatus')->name('lesson-slots.toggle-status');
    });
});
