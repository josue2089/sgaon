<?php

use App\Http\Controllers\AcademicLevelController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampusController;
use App\Http\Controllers\CampusProgramPriceController;
use App\Http\Controllers\ClassSessionController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseGradeController;
use App\Http\Controllers\CourseLevelController;
use App\Http\Controllers\CourseNextLevelController;
use App\Http\Controllers\CourseObservationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataHealthController;
use App\Http\Controllers\EnrollmentChangeController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\ExchangeRateSettingsController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HistoricalStudentController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\MakeupRecoveryController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\PortalAccessController;
use App\Http\Controllers\PortalActivationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProgramLevelController;
use App\Http\Controllers\ProgramLevelLessonController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleTemplateController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\StudentMakeupController;
use App\Http\Controllers\StudentPortalAccessController;
use App\Http\Controllers\StudentSearchController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\TeacherAccessController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

// Enlace de invitación o recuperación del portal: el token identifica a la persona. #471
Route::get('/activar/{token}', [PortalActivationController::class, 'show'])->name('portal.activate');
Route::post('/activar/{token}', [PortalActivationController::class, 'store'])->middleware('throttle:10,1')->name('portal.activate.store');

Route::middleware(['auth', 'campus.access'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/cuenta/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/cuenta/password', [AccountPasswordController::class, 'update'])->name('account.password.update');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/api/finance/exchange-rate', [ExchangeRateController::class, 'latest'])->name('finance.exchange-rate');
    // Archivos privados: el controlador verifica sede, alumno o representante. #438
    Route::get('/files/payment-proofs/{paymentRequest}', [PrivateFileController::class, 'paymentProof'])->name('files.payment-proof');
    Route::get('/files/makeup-attachments/{attachment}', [PrivateFileController::class, 'makeupAttachment'])->name('files.makeup-attachment');

    Route::middleware('role:admin')->group(function () {
        Route::get('/students/import', [StudentImportController::class, 'create'])->name('students.import');
        Route::post('/students/import/preview', [StudentImportController::class, 'preview'])->name('students.import.preview');
        Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import.store');
        Route::get('/students/search', StudentSearchController::class)->name('students.search');
        // El asistente de operaciones se retiró (#241): la creación de cursos e inscripciones tiene sus pantallas.
        Route::redirect('/operations/wizard', '/courses');
        Route::get('/students/historical', [HistoricalStudentController::class, 'index'])->name('students.historical.index');
        Route::get('/students/historical/import', [HistoricalStudentController::class, 'importForm'])->name('students.historical.import');
        Route::post('/students/historical/import/preview', [HistoricalStudentController::class, 'importPreview'])->name('students.historical.import.preview');
        Route::post('/students/historical/import', [HistoricalStudentController::class, 'importStore'])->name('students.historical.import.store');
        Route::post('/students/historical/{student}/activate', [HistoricalStudentController::class, 'activate'])->name('students.historical.activate');
        Route::get('/students/export', [StudentController::class, 'export'])
            ->middleware('master.admin')
            ->name('students.export');
        Route::post('/students/{student}/move-to-historical', [StudentController::class, 'moveToHistorical'])->name('students.move-to-historical');
        Route::resource('students', StudentController::class)->except('show');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::get('/portal-access', [PortalAccessController::class, 'index'])->name('portal-access.index');
        Route::post('/portal-access/invite', [PortalAccessController::class, 'invite'])->name('portal-access.invite');
        Route::post('/students/{student}/portal-access/invite', [StudentPortalAccessController::class, 'invite'])->name('students.portal-access.invite');
        Route::post('/students/{student}/portal-access/deactivate', [StudentPortalAccessController::class, 'deactivate'])->name('students.portal-access.deactivate');
        Route::post('/students/{student}/attachments', [StudentController::class, 'storeAttachment'])->name('students.attachments.store');
        Route::get('/students/{student}/attachments/{attachment}', [StudentController::class, 'downloadAttachment'])->name('students.attachments.download');
        Route::delete('/students/{student}/attachments/{attachment}', [StudentController::class, 'destroyAttachment'])->name('students.attachments.destroy');
        Route::get('/students/{student}/enrollment-sheet', [StudentController::class, 'enrollmentSheet'])->name('students.enrollment-sheet');
        Route::get('/students/{student}/enrollment-sheet/pdf', [StudentController::class, 'enrollmentSheetPdf'])->name('students.enrollment-sheet.pdf');
        Route::get('/students/{student}/extracurricular-sheet/pdf', [StudentController::class, 'extracurricularSheetPdf'])->name('students.extracurricular-sheet.pdf');
        Route::get('/students/{student}/logbook/{enrollment}', [StudentController::class, 'logbook'])->name('students.logbook');
        Route::get('/students/{student}/logbook/{enrollment}/pdf', [StudentController::class, 'logbookPdf'])->name('students.logbook.pdf');
        Route::post('/students/{student}/payments', [StudentController::class, 'storePayment'])
            ->middleware('permission:finance.manage')
            ->name('students.payments.store');
        Route::get('/students/{student}/makeups/create', [StudentMakeupController::class, 'create'])->name('students.makeups.create');
        Route::post('/students/{student}/makeups', [StudentMakeupController::class, 'store'])->name('students.makeups.store');
        Route::post('/students/{student}/charges', [StudentController::class, 'storeCharge'])
            ->middleware('permission:finance.manage')
            ->name('students.charges.store');
        Route::resource('teachers', TeacherController::class)->except('show');
        Route::get('/teachers-calendar', [TeacherController::class, 'calendar'])->name('teachers.calendar');
        Route::get('/teachers/{teacher}', [TeacherController::class, 'show'])->name('teachers.show');
        Route::post('/teachers/{teacher}/access/invite', [TeacherAccessController::class, 'invite'])->name('teachers.access.invite');
        Route::post('/teachers/{teacher}/access/deactivate', [TeacherAccessController::class, 'deactivate'])->name('teachers.access.deactivate');
        Route::post('/courses/{course}/students', [CourseController::class, 'syncStudents'])->name('courses.students.sync');
        Route::post('/courses/{course}/recalculate-calendar', [CourseController::class, 'recalculateCalendar'])->name('courses.recalculate-calendar');
        Route::post('/courses/{course}/extra-sessions', [CourseController::class, 'storeExtraSession'])->name('courses.extra-sessions.store');
        Route::post('/courses/{course}/extracurricular-charges', [CourseController::class, 'generateExtracurricularCharges'])->name('courses.extracurricular-charges.generate');
        Route::delete('/courses/{course}/students/{enrollment}', [CourseController::class, 'removeStudent'])->name('courses.students.remove');
        Route::get('/courses/{course}/report.pdf', [CourseController::class, 'reportPdf'])
            ->middleware('master.admin')
            ->name('courses.report.pdf');
        Route::get('/courses/{course}/next-level', [CourseNextLevelController::class, 'create'])->name('courses.next-level.create');
        Route::post('/courses/{course}/next-level', [CourseNextLevelController::class, 'store'])->name('courses.next-level.store');
        Route::resource('courses', CourseController::class);
        Route::resource('groups', GroupController::class)->except('show');
        Route::post('/enrollments/{enrollment}/withdraw', [EnrollmentChangeController::class, 'withdraw'])->name('enrollments.withdraw');
        Route::get('/enrollments/{enrollment}/transfer', [EnrollmentChangeController::class, 'editTransfer'])->name('enrollments.transfer.edit');
        Route::post('/enrollments/{enrollment}/transfer', [EnrollmentChangeController::class, 'transfer'])->name('enrollments.transfer');
        Route::resource('enrollments', EnrollmentController::class)->except('show');
        Route::resource('sessions', ClassSessionController::class)->except('show');
        Route::get('/makeups', [MakeupRecoveryController::class, 'index'])->name('makeups.index');
        Route::post('/makeups/sessions', [MakeupRecoveryController::class, 'storeSession'])->name('makeups.sessions.store');
        Route::patch('/makeups/requests/{makeupRequest}', [MakeupRecoveryController::class, 'reviewRequest'])->name('makeups.requests.review');
        Route::patch('/makeups/bookings/{booking}', [MakeupRecoveryController::class, 'updateBooking'])->name('makeups.bookings.update');
        // Feriados: el master gestiona todo; el admin de sede, los días sin clase de su sede.
        Route::resource('holidays', HolidayController::class)->except('show');
        Route::middleware('master.admin')->group(function () {
            Route::get('/data-health', [DataHealthController::class, 'index'])->name('data-health.index');
            Route::post('/data-health/reconcile-charges', [DataHealthController::class, 'reconcileCharges'])->name('data-health.reconcile-charges');
            Route::resource('admin-users', AdminUserController::class)
                ->except(['show'])
                ->parameters(['admin-users' => 'user']);
            Route::post('admin-users/{user}/resend-credentials', [AdminUserController::class, 'resendCredentials'])
                ->name('admin-users.resend-credentials');
            Route::resource('campuses', CampusController::class);
            Route::post('campuses/{campus}/program-prices', [CampusProgramPriceController::class, 'store'])->name('campuses.program-prices.store');
            Route::delete('campuses/{campus}/program-prices/{price}', [CampusProgramPriceController::class, 'destroy'])->name('campuses.program-prices.destroy');
            Route::resource('periods', PeriodController::class)->except('show');
            Route::resource('schedules', ScheduleTemplateController::class)->except('show');
            Route::resource('academic-levels', AcademicLevelController::class)->except('show');
            Route::resource('course-levels', CourseLevelController::class)->except('show');
            Route::resource('programs', ProgramController::class);
            Route::get('/settings/makeup-payment-instructions', [SystemSettingController::class, 'editMakeupPaymentInstructions'])->name('settings.makeup-payment-instructions.edit');
            Route::put('/settings/makeup-payment-instructions', [SystemSettingController::class, 'updateMakeupPaymentInstructions'])->name('settings.makeup-payment-instructions.update');
            Route::get('/settings/billing', [SystemSettingController::class, 'editBilling'])->name('settings.billing.edit');
            Route::put('/settings/billing', [SystemSettingController::class, 'updateBilling'])->name('settings.billing.update');
            Route::resource('settings/payment-methods', PaymentMethodController::class)
                ->except(['show'])
                ->names('settings.payment-methods')
                ->parameters(['payment-methods' => 'paymentMethod']);
            Route::get('/programs/{program}/levels/create', [ProgramLevelController::class, 'create'])->name('program-levels.create');
            Route::post('/programs/{program}/levels', [ProgramLevelController::class, 'store'])->name('program-levels.store');
            Route::get('/program-levels/{programLevel}', [ProgramLevelController::class, 'show'])->name('program-levels.show');
            Route::get('/program-levels/{programLevel}/edit', [ProgramLevelController::class, 'edit'])->name('program-levels.edit');
            Route::put('/program-levels/{programLevel}', [ProgramLevelController::class, 'update'])->name('program-levels.update');
            Route::delete('/program-levels/{programLevel}', [ProgramLevelController::class, 'destroy'])->name('program-levels.destroy');
            Route::post('/program-levels/{programLevel}/duplicate', [ProgramLevelController::class, 'duplicate'])->name('program-levels.duplicate');
            Route::get('/program-levels/{programLevel}/lessons/create', [ProgramLevelLessonController::class, 'create'])->name('program-level-lessons.create');
            Route::post('/program-levels/{programLevel}/lessons', [ProgramLevelLessonController::class, 'store'])->name('program-level-lessons.store');
            Route::post('/program-levels/{programLevel}/lessons/import/preview', [ProgramLevelLessonController::class, 'importPreview'])->name('program-level-lessons.import.preview');
            Route::post('/program-levels/{programLevel}/lessons/import', [ProgramLevelLessonController::class, 'importStore'])->name('program-level-lessons.import.store');
            Route::get('/program-level-lessons/{programLevelLesson}/edit', [ProgramLevelLessonController::class, 'edit'])->name('program-level-lessons.edit');
            Route::put('/program-level-lessons/{programLevelLesson}', [ProgramLevelLessonController::class, 'update'])->name('program-level-lessons.update');
            Route::delete('/program-level-lessons/{programLevelLesson}', [ProgramLevelLessonController::class, 'destroy'])->name('program-level-lessons.destroy');
        });
        Route::middleware('permission:finance.manage')->group(function () {
            Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
            Route::get('/finance/summary', [FinanceController::class, 'summary'])->name('finance.summary');
            Route::get('/finance/receipts/{receipt}', [FinanceController::class, 'showReceipt'])->name('finance.receipts.show');
            Route::get('/finance/receipts/{receipt}/pdf', [FinanceController::class, 'downloadReceiptPdf'])->name('finance.receipts.pdf');
            Route::post('/finance/receipts/{receipt}/resend', [FinanceController::class, 'resendReceipt'])->middleware('throttle:10,1')->name('finance.receipts.resend');
            Route::get('/finance/students/{student}/history', [FinanceController::class, 'studentHistory'])->name('finance.students.history');
            Route::post('/finance/charges', [FinanceController::class, 'storeCharge'])->name('finance.charges.store');
            Route::get('/settings/exchange-rates', [ExchangeRateSettingsController::class, 'index'])->name('settings.exchange-rates.index');
            Route::post('/settings/exchange-rates/sync', [ExchangeRateSettingsController::class, 'sync'])->middleware('throttle:6,1')->name('settings.exchange-rates.sync');
            Route::post('/settings/exchange-rates/manual', [ExchangeRateSettingsController::class, 'storeManual'])->name('settings.exchange-rates.manual');
            Route::post('/finance/charges/{charge}/void', [FinanceController::class, 'voidCharge'])->name('finance.charges.void');
            Route::post('/finance/payments', [FinanceController::class, 'storePayment'])->name('finance.payments.store');
            Route::post('/finance/payments/{payment}/void', [FinanceController::class, 'voidPayment'])->name('finance.payments.void');
            Route::patch('/finance/payment-requests/{paymentRequest}', [FinanceController::class, 'reviewPaymentRequest'])->name('finance.payment-requests.review');
        });
        Route::middleware('permission:reports.view')->group(function () {
            Route::get('/reports/attendance', [ReportController::class, 'attendance'])->name('reports.attendance');
            Route::get('/reports/payments', [ReportController::class, 'payments'])->name('reports.payments');
            Route::get('/reports/level-renewals', [ReportController::class, 'levelRenewals'])->name('reports.level-renewals');
            Route::get('/reports/campus-totals', [ReportController::class, 'campusTotals'])->name('reports.campus-totals');
            Route::get('/reports/campus-projection', [ReportController::class, 'campusProjection'])
                ->middleware('master.admin')
                ->name('reports.campus-projection');
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
        Route::post('/attendance/reschedule', [AttendanceController::class, 'reschedule'])->name('attendance.reschedule');
    });

    Route::middleware(['role:admin,teacher', 'permission:grades.manage'])->group(function () {
        Route::get('/courses/{course}/grades', [CourseGradeController::class, 'index'])->name('courses.grades.index');
        Route::get('/courses/{course}/grades/create', [CourseGradeController::class, 'create'])->name('courses.grades.create');
        Route::post('/courses/{course}/grades', [CourseGradeController::class, 'store'])->name('courses.grades.store');
        Route::get('/grade-evaluation-sets/{gradeEvaluationSet}', [CourseGradeController::class, 'show'])->name('grade-evaluation-sets.show');
        Route::get('/grade-evaluation-sets/{gradeEvaluationSet}/edit', [CourseGradeController::class, 'edit'])->name('grade-evaluation-sets.edit');
        Route::put('/grade-evaluation-sets/{gradeEvaluationSet}', [CourseGradeController::class, 'update'])->name('grade-evaluation-sets.update');
        Route::get('/courses/{course}/observations', [CourseObservationController::class, 'edit'])->name('courses.observations.edit');
        Route::put('/courses/{course}/observations', [CourseObservationController::class, 'update'])->name('courses.observations.update');
    });

    Route::middleware(['role:student', 'permission:portal.student.view'])->group(function () {
        Route::get('/portal/student', [PortalController::class, 'student'])->name('portal.student');
        Route::post('/portal/student/renewal-enrollments/{course}', [PortalController::class, 'enrollInRenewalCourse'])->name('portal.student.renewals.enroll');
        Route::post('/portal/student/makeups/{makeupRequest}/payment', [PortalController::class, 'submitMakeupPayment'])->name('portal.student.makeups.payment');
        Route::post('/portal/student/makeups/{makeupRequest}/book', [PortalController::class, 'bookMakeupSession'])->name('portal.student.makeups.book');
        Route::post('/portal/student/charges/{charge}/payment', [PortalController::class, 'submitChargePaymentAsStudent'])->name('portal.student.charges.payment');
        Route::get('/portal/student/receipts/{receipt}/pdf', [PortalController::class, 'downloadReceiptAsStudent'])->name('portal.student.receipts.pdf');
        Route::get('/portal/student/logbook/{enrollment}/pdf', [PortalController::class, 'downloadLogbookAsStudent'])->name('portal.student.logbook.pdf');
    });

    Route::middleware(['role:representative', 'permission:portal.representative.view'])->group(function () {
        Route::get('/portal/representative', [PortalController::class, 'representative'])->name('portal.representative');
        Route::post('/portal/representative/students/{student}/renewals/{course}', [PortalController::class, 'enrollInRenewalCourseAsRepresentative'])->name('portal.representative.renewals.enroll');
        Route::post('/portal/representative/charges/{charge}/payment', [PortalController::class, 'submitChargePaymentAsRepresentative'])->name('portal.representative.charges.payment');
        Route::post('/portal/representative/makeups/{makeupRequest}/payment', [PortalController::class, 'submitMakeupPaymentAsRepresentative'])->name('portal.representative.makeups.payment');
        Route::post('/portal/representative/makeups/{makeupRequest}/book', [PortalController::class, 'bookMakeupSessionAsRepresentative'])->name('portal.representative.makeups.book');
        Route::get('/portal/representative/receipts/{receipt}/pdf', [PortalController::class, 'downloadReceiptAsRepresentative'])->name('portal.representative.receipts.pdf');
        Route::get('/portal/representative/logbook/{enrollment}/pdf', [PortalController::class, 'downloadLogbookAsRepresentative'])->name('portal.representative.logbook.pdf');
    });
});
