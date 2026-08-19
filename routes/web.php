<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClassRoomController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FinancialController;
use App\Http\Controllers\HolidayClassController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentLinkController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ─── Website Publik (marketing & informasi) ────────────────────
// Terpisah dari sistem admin: tanpa auth, layout & sistem desain sendiri.
Route::controller(PublicSiteController::class)->group(function () {
    Route::get('/', 'home')->name('public.home');
    Route::get('/tentang', 'about')->name('public.about');
    Route::get('/program', 'programs')->name('public.programs');
    Route::get('/galeri', 'gallery')->name('public.gallery');
    Route::get('/jadwal', 'schedule')->name('public.schedule');
    Route::get('/kontak', 'contact')->name('public.contact');
    Route::post('/kontak', 'storeLead')->name('public.contact.store')->middleware('throttle:6,1');
    Route::get('/sitemap.xml', 'sitemap')->name('public.sitemap');
});

// ─── Pembayaran Online (Midtrans Snap) — tanpa login ───────────
// Tautan /bayar/{token} dikirim admin ke orang tua lewat WhatsApp.
Route::get('/bayar/{token}', [PaymentLinkController::class, 'show'])->name('pay.show');
// Dipanggil halaman bayar setelah popup Snap sukses; statusnya tetap diverifikasi
// ke Midtrans dari sisi server. Dibatasi rate-nya karena terbuka tanpa login.
Route::post('/bayar/{token}/verifikasi', [PaymentLinkController::class, 'verify'])
    ->middleware('throttle:15,1')->name('pay.verify');
// Payment Notification URL yang didaftarkan di dashboard Midtrans. Dikecualikan
// dari CSRF (lihat bootstrap/app.php); keasliannya dijaga signature_key.
Route::post('/midtrans/notification', [PaymentLinkController::class, 'notification'])->name('midtrans.notification');

// ─── Auth Routes (Guest Only) ──────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ─── Guest Report Access (F9) — tanpa login sistem ─────────────
Route::get('/raport', [ReportController::class, 'guestForm'])->name('reports.guest');
Route::post('/raport', [ReportController::class, 'guestShow'])->name('reports.guest.show');

// ─── Protected Routes (Auth Required) ─────────────────────────
Route::middleware('auth')->group(function () {

    // Dashboard (F1) — `/` dipakai website publik, jadi dashboard di /dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ─── Export Report (F12) — Excel(CSV)/PDF ──────────────────
    Route::get('export/students', [ExportController::class, 'students'])->name('export.students');
    Route::get('export/payments', [ExportController::class, 'payments'])->name('export.payments');
    Route::get('export/financials', [ExportController::class, 'financials'])->name('export.financials');
    Route::get('export/attendances', [ExportController::class, 'attendances'])->name('export.attendances');

    // ─── User Management (F13) + Activity Log (F15) — Super Admin only ──
    Route::middleware('role:super_admin')->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });

    // ─── Student Management (F2) ───────────────────────────────
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('students', [StudentController::class, 'store'])->name('students.store');
    Route::get('students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
    // Hapus permanen murid — hanya Super Admin.
    Route::delete('students/{student}', [StudentController::class, 'destroy'])
        ->middleware('role:super_admin')->name('students.destroy');

    // ─── Class Management (F3) + Tutor ─────────────────────────
    Route::post('tutors', [ClassRoomController::class, 'storeTutor'])->name('tutors.store');
    Route::put('tutors/{tutor}', [ClassRoomController::class, 'updateTutor'])->name('tutors.update');
    Route::delete('tutors/{tutor}', [ClassRoomController::class, 'destroyTutor'])->name('tutors.destroy');
    Route::patch('classes/{class}/toggle-status', [ClassRoomController::class, 'toggleStatus'])->name('classes.toggle-status');
    Route::resource('classes', ClassRoomController::class)
        ->parameters(['classes' => 'class'])->except('show');

    // ─── Holiday Class (kelas musiman saat libur sekolah) ──────
    Route::resource('holiday-classes', HolidayClassController::class)
        ->parameters(['holiday-classes' => 'holidayClass'])->except('show');

    // ─── Scheduler / Replacement Class (F4) ────────────────────
    Route::get('schedules/calendar', [ScheduleController::class, 'calendar'])->name('schedules.calendar');
    // Hari libur / kelas ditiadakan — memengaruhi ketersediaan slot.
    Route::post('holidays', [ScheduleController::class, 'storeHoliday'])->name('holidays.store');
    Route::delete('holidays/{holiday}', [ScheduleController::class, 'destroyHoliday'])->name('holidays.destroy');
    // Acara / agenda umum di kalender.
    Route::post('calendar-events', [ScheduleController::class, 'storeEvent'])->name('calendar-events.store');
    Route::delete('calendar-events/{event}', [ScheduleController::class, 'destroyEvent'])->name('calendar-events.destroy');
    Route::patch('schedules/{schedule}/status', [ScheduleController::class, 'updateStatus'])
        ->middleware('role:super_admin')->name('schedules.status');
    Route::resource('schedules', ScheduleController::class)->except('show');

    // ─── Attendance (F5) ───────────────────────────────────────
    Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::get('attendances/create', [AttendanceController::class, 'create'])->name('attendances.create');
    Route::post('attendances', [AttendanceController::class, 'store'])->name('attendances.store');
    Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy'])->name('attendances.destroy');

    // ─── Payment (F6) ──────────────────────────────────────────
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    // Satu halaman untuk menerbitkan invoice, lewat pratinjau: centang satu
    // murid untuk satu invoice, centang semua untuk sebulan penuh.
    Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    // Alamat lama "Tagihan Bulanan", dipertahankan agar tautan yang terlanjur
    // di-bookmark atau dibagikan tidak mati begitu saja.
    Route::get('payments/bulk', fn () => redirect()->route('payments.create', request()->query()))
        ->name('payments.bulk.create');
    Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
    Route::put('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    Route::patch('payments/{payment}/confirm', [PaymentController::class, 'confirmPaid'])->name('payments.confirm');
    // Kirim invoice ke WhatsApp wali murid (membuka chat wa.me yang sudah terisi).
    Route::get('payments/{payment}/whatsapp', [PaymentController::class, 'sendWhatsapp'])->name('payments.whatsapp');
    // Tarik ulang status dari Midtrans bila webhook tidak sampai.
    Route::patch('payments/{payment}/sync-gateway', [PaymentController::class, 'syncGateway'])->name('payments.sync-gateway');
    // Void pembayaran — hanya Super Admin.
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])
        ->middleware('role:super_admin')->name('payments.destroy');

    // ─── Financial Tracking (F7) ───────────────────────────────
    Route::resource('financials', FinancialController::class)->except('show');

    // ─── Student Report (F8) ───────────────────────────────────
    Route::resource('reports', ReportController::class);

    // ─── Inventory (F10) ───────────────────────────────────────
    Route::post('inventory/movement', [InventoryController::class, 'storeMovement'])->name('inventory.movement');
    Route::resource('inventory', InventoryController::class)->except('show');
});
