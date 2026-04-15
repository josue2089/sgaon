<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AcademicLevelController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampusController;
use App\Http\Controllers\ClassSessionController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseLevelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\MakeupRecoveryController;
use App\Http\Controllers\OperationWizardController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProgramLevelController;
use App\Http\Controllers\ProgramLevelLessonController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleTemplateController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware(['auth', 'campus.access'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:admin')->group(function () {
        Route::resource('students', StudentController::class)->except('show');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::post('/students/{student}/attachments', [StudentController::class, 'storeAttachment'])->name('students.attachments.store');
        Route::get('/students/{student}/attachments/{attachment}', [StudentController::class, 'downloadAttachment'])->name('students.attachments.download');
        Route::delete('/students/{student}/attachments/{attachment}', [StudentController::class, 'destroyAttachment'])->name('students.attachments.destroy');
        Route::get('/students/{student}/enrollment-sheet', [StudentController::class, 'enrollmentSheet'])->name('students.enrollment-sheet');
        Route::get('/students/{student}/enrollment-sheet/pdf', [StudentController::class, 'enrollmentSheetPdf'])->name('students.enrollment-sheet.pdf');
        Route::resource('teachers', TeacherController::class)->except('show');
        Route::get('/teachers-calendar', [TeacherController::class, 'calendar'])->name('teachers.calendar');
        Route::get('/teachers/{teacher}', [TeacherController::class, 'show'])->name('teachers.show');
        Route::post('/courses/{course}/students', [CourseController::class, 'syncStudents'])->name('courses.students.sync');
        Route::delete('/courses/{course}/students/{enrollment}', [CourseController::class, 'removeStudent'])->name('courses.students.remove');
        Route::resource('courses', CourseController::class);
        Route::resource('groups', GroupController::class)->except('show');
        Route::resource('enrollments', EnrollmentController::class)->except('show');
        Route::resource('sessions', ClassSessionController::class)->except('show');
        Route::get('/makeups', [MakeupRecoveryController::class, 'index'])->name('makeups.index');
        Route::post('/makeups/sessions', [MakeupRecoveryController::class, 'storeSession'])->name('makeups.sessions.store');
        Route::patch('/makeups/requests/{makeupRequest}', [MakeupRecoveryController::class, 'reviewRequest'])->name('makeups.requests.review');
        Route::patch('/makeups/bookings/{booking}', [MakeupRecoveryController::class, 'updateBooking'])->name('makeups.bookings.update');
        Route::get('/operations/wizard', [OperationWizardController::class, 'index'])->name('operations.wizard');
        Route::post('/operations/wizard/course', [OperationWizardController::class, 'storeCourse'])->name('operations.wizard.course');
        Route::post('/operations/wizard/group', [OperationWizardController::class, 'storeGroup'])->name('operations.wizard.group');
        Route::post('/operations/wizard/session', [OperationWizardController::class, 'storeSession'])->name('operations.wizard.session');
        Route::post('/operations/wizard/enrollment', [OperationWizardController::class, 'storeEnrollment'])->name('operations.wizard.enrollment');
        Route::middleware('master.admin')->group(function () {
            Route::resource('campuses', CampusController::class)->except('show');
            Route::resource('periods', PeriodController::class)->except('show');
            Route::resource('schedules', ScheduleTemplateController::class)->except('show');
            Route::resource('holidays', HolidayController::class)->except('show');
            Route::resource('academic-levels', AcademicLevelController::class)->except('show');
            Route::resource('course-levels', CourseLevelController::class)->except('show');
            Route::resource('programs', ProgramController::class);
            Route::get('/settings/makeup-payment-instructions', [SystemSettingController::class, 'editMakeupPaymentInstructions'])->name('settings.makeup-payment-instructions.edit');
            Route::put('/settings/makeup-payment-instructions', [SystemSettingController::class, 'updateMakeupPaymentInstructions'])->name('settings.makeup-payment-instructions.update');
            Route::get('/programs/{program}/levels/create', [ProgramLevelController::class, 'create'])->name('program-levels.create');
            Route::post('/programs/{program}/levels', [ProgramLevelController::class, 'store'])->name('program-levels.store');
            Route::get('/program-levels/{programLevel}', [ProgramLevelController::class, 'show'])->name('program-levels.show');
            Route::get('/program-levels/{programLevel}/edit', [ProgramLevelController::class, 'edit'])->name('program-levels.edit');
            Route::put('/program-levels/{programLevel}', [ProgramLevelController::class, 'update'])->name('program-levels.update');
            Route::delete('/program-levels/{programLevel}', [ProgramLevelController::class, 'destroy'])->name('program-levels.destroy');
            Route::post('/program-levels/{programLevel}/duplicate', [ProgramLevelController::class, 'duplicate'])->name('program-levels.duplicate');
            Route::get('/program-levels/{programLevel}/lessons/create', [ProgramLevelLessonController::class, 'create'])->name('program-level-lessons.create');
            Route::post('/program-levels/{programLevel}/lessons', [ProgramLevelLessonController::class, 'store'])->name('program-level-lessons.store');
            Route::get('/program-level-lessons/{programLevelLesson}/edit', [ProgramLevelLessonController::class, 'edit'])->name('program-level-lessons.edit');
            Route::put('/program-level-lessons/{programLevelLesson}', [ProgramLevelLessonController::class, 'update'])->name('program-level-lessons.update');
            Route::delete('/program-level-lessons/{programLevelLesson}', [ProgramLevelLessonController::class, 'destroy'])->name('program-level-lessons.destroy');
        });
        Route::middleware('permission:finance.manage')->group(function () {
            Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
            Route::get('/finance/receipts/{receipt}', [FinanceController::class, 'showReceipt'])->name('finance.receipts.show');
            Route::get('/finance/receipts/{receipt}/pdf', [FinanceController::class, 'downloadReceiptPdf'])->name('finance.receipts.pdf');
            Route::get('/finance/students/{student}/history', [FinanceController::class, 'studentHistory'])->name('finance.students.history');
            Route::post('/finance/charges', [FinanceController::class, 'storeCharge'])->name('finance.charges.store');
            Route::post('/finance/payments', [FinanceController::class, 'storePayment'])->name('finance.payments.store');
        });
        Route::middleware('permission:reports.view')->group(function () {
            Route::get('/reports/attendance', [ReportController::class, 'attendance'])->name('reports.attendance');
            Route::get('/reports/payments', [ReportController::class, 'payments'])->name('reports.payments');
            Route::get('/reports/level-renewals', [ReportController::class, 'levelRenewals'])->name('reports.level-renewals');
            Route::post('/reports/presets', [ReportController::class, 'storePreset'])->name('reports.presets.store');
            Route::delete('/reports/presets/{preset}', [ReportController::class, 'destroyPreset'])->name('reports.presets.destroy');
            Route::post('/reports/exports', [ReportController::class, 'queueExport'])->name('reports.exports.queue');
            Route::get('/reports/exports/{export}/download', [ReportController::class, 'downloadExport'])->name('reports.exports.download');
        });
        Route::middleware('permission:audit.view')->group(function () {
            Route::get('/reports/audit', [ReportController::class, 'audit'])->name('reports.audit');
        });
    });

    Route::middleware(['role:admin,teacher', 'permission:attendance.manage'])->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    });

    Route::middleware(['role:student', 'permission:portal.student.view'])->group(function () {
        Route::get('/portal/student', [PortalController::class, 'student'])->name('portal.student');
        Route::post('/portal/student/makeups/{makeupRequest}/payment', [PortalController::class, 'submitMakeupPayment'])->name('portal.student.makeups.payment');
        Route::post('/portal/student/makeups/{makeupRequest}/book', [PortalController::class, 'bookMakeupSession'])->name('portal.student.makeups.book');
    });

    Route::middleware(['role:representative', 'permission:portal.representative.view'])->group(function () {
        Route::get('/portal/representative', [PortalController::class, 'representative'])->name('portal.representative');
    });
});
