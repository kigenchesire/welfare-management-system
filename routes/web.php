<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberPortalController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WelfareCaseController;
use Illuminate\Support\Facades\Route;

/*
| Every staff route is protected by a permission middleware: hiding menu
| items is not relied on for security.
*/

// ---------------- Public ----------------
// The public website (homepage + online registration) is switched off for now:
// visitors land on the login page. To switch it back on, restore the two lines
// below and delete the redirect, then uncomment the "website" links in
// resources/views/auth/login.blade.php, layouts/auth.blade.php,
// layouts/app.blade.php and admin/website.blade.php.
// Route::get('/', [HomeController::class, 'index'])->name('home');
// Route::post('/register', [HomeController::class, 'register'])->middleware('throttle:10,1')->name('register');
Route::redirect('/', '/login')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
});

// ---------------- Signed in ----------------
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::put('/password', [AuthController::class, 'changePassword'])->name('password.update');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/notifications', [CommunicationController::class, 'notifications'])->name('notifications');
    Route::get('/notifications/{notification}', [CommunicationController::class, 'readNotification'])->name('notifications.read');
    Route::post('/notifications/read-all', [CommunicationController::class, 'readAll'])->name('notifications.read-all');

    // Member self-service (always scoped to the signed-in member).
    Route::prefix('my')->name('member.')->controller(MemberPortalController::class)->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/profile', 'profile')->name('profile');
        Route::get('/beneficiaries', 'beneficiaries')->name('beneficiaries');
        Route::get('/wallet', 'wallet')->name('wallet');
        Route::get('/invoices', 'invoices')->name('invoices');
        Route::get('/invoices/{invoice}', 'invoice')->whereNumber('invoice')->name('invoice');
        Route::get('/cases', 'cases')->name('cases');
        Route::get('/payments', 'payments')->name('payments');
        Route::get('/payments/{payment}/receipt', 'receipt')->whereNumber('payment')->name('receipt');
    });

    // ---------- Membership ----------
    Route::middleware('permission:members.view')->group(function () {
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/pending', [MemberController::class, 'pending'])->name('members.pending');
        Route::get('/members/search', [MemberController::class, 'search'])->name('members.search');
        Route::get('/members/{member}', [MemberController::class, 'show'])->whereNumber('member')->name('members.show');
        Route::get('/members/{member}/beneficiaries.json', [MemberController::class, 'beneficiariesJson'])->name('members.beneficiaries-json');
        Route::get('/beneficiaries', [BeneficiaryController::class, 'index'])->name('beneficiaries.index');
    });
    Route::middleware('permission:members.manage')->group(function () {
        Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::post('/members/{member}/status', [MemberController::class, 'changeStatus'])->name('members.status');
        Route::post('/members/{member}/reset-password', [MemberController::class, 'resetPassword'])->name('members.reset-password');
    });
    Route::middleware('permission:members.approve')->group(function () {
        Route::post('/members/{member}/approve', [MemberController::class, 'approve'])->name('members.approve');
        Route::post('/members/{member}/reject', [MemberController::class, 'reject'])->name('members.reject');
    });
    Route::middleware('permission:beneficiaries.manage')->group(function () {
        Route::post('/members/{member}/beneficiaries', [BeneficiaryController::class, 'store'])->name('beneficiaries.store');
        Route::put('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'update'])->name('beneficiaries.update');
        Route::delete('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'destroy'])->name('beneficiaries.destroy');
    });

    // ---------- Welfare cases ----------
    Route::middleware('permission:cases.manage')->group(function () {
        Route::get('/cases/create', [WelfareCaseController::class, 'create'])->name('cases.create');
        Route::post('/cases', [WelfareCaseController::class, 'store'])->name('cases.store');
        Route::post('/cases/{case}/process', [WelfareCaseController::class, 'process'])->name('cases.process');
        Route::post('/cases/{case}/close', [WelfareCaseController::class, 'close'])->name('cases.close');
        Route::post('/cases/{case}/cancel', [WelfareCaseController::class, 'cancel'])->name('cases.cancel');
    });
    Route::post('/cases/{case}/payments', [WelfareCaseController::class, 'recordPayment'])->middleware('permission:deposits.record')->name('cases.payments.store');
    Route::post('/cases/{case}/remind', [WelfareCaseController::class, 'remind'])->middleware('permission:cases.manage|deposits.record')->name('cases.remind');
    Route::middleware('permission:cases.view')->group(function () {
        Route::get('/cases', [WelfareCaseController::class, 'index'])->name('cases.index');
        Route::get('/cases/{case}', [WelfareCaseController::class, 'show'])->whereNumber('case')->name('cases.show');
    });

    // ---------- Invoices ----------
    Route::middleware('permission:invoices.manage')->group(function () {
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'payFromWallet'])->name('invoices.pay');
    });
    Route::middleware('permission:invoices.view')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
    });

    // ---------- Finance ----------
    Route::middleware('permission:deposits.record')->group(function () {
        Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    });
    Route::middleware('permission:wallets.view')->group(function () {
        Route::get('/wallets', [WalletController::class, 'index'])->name('wallets.index');
        Route::get('/wallets/{member}', [WalletController::class, 'show'])->name('wallets.show');
        Route::get('/transactions', [WalletController::class, 'transactions'])->name('transactions.index');
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment')->name('payments.show');
    });
    Route::post('/wallets/{member}/adjust', [WalletController::class, 'adjust'])->middleware('permission:adjustments.manage')->name('wallets.adjust');
    Route::post('/wallets/low-balance-reminders', [WalletController::class, 'remindLowBalances'])->middleware('permission:deposits.record')->name('wallets.remind');
    Route::get('/finance', FinanceController::class)->middleware('permission:finance.view')->name('finance.summary');

    // ---------- Reports (permission checked per report group) ----------
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{key}', [ReportController::class, 'show'])->name('reports.show');

    // ---------- Communication ----------
    Route::middleware('permission:announcements.manage')->group(function () {
        Route::get('/announcements', [CommunicationController::class, 'announcements'])->name('announcements.index');
        Route::post('/announcements', [CommunicationController::class, 'storeAnnouncement'])->name('announcements.store');
    });
    Route::get('/sms-logs', [CommunicationController::class, 'smsLogs'])->middleware('permission:sms.view')->name('sms.logs');

    // ---------- Administration ----------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('permission:users.manage')->group(function () {
            Route::get('/users', [AdminController::class, 'users'])->name('users');
            Route::get('/users/create', [AdminController::class, 'createUser'])->name('users.create');
            Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
            Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
            Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
            Route::post('/users/{user}/reset-password', [AdminController::class, 'resetUserPassword'])->name('users.reset-password');
        });
        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('/roles', [AdminController::class, 'roles'])->name('roles');
            Route::put('/roles/{role}', [AdminController::class, 'updateRole'])->name('roles.update');
        });
        Route::middleware('permission:audit.view')->group(function () {
            Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
            Route::get('/audit/{log}', [AdminController::class, 'auditShow'])->name('audit.show');
        });
        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('/settings', [SettingController::class, 'edit'])->name('settings');
            Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
            Route::post('/settings/test-sms', [CommunicationController::class, 'testSms'])->name('settings.test-sms');
        });
        Route::middleware('permission:website.manage')->group(function () {
            Route::get('/website', [SettingController::class, 'website'])->name('website');
            Route::put('/website', [SettingController::class, 'updateWebsite'])->name('website.update');
            Route::post('/website/images', [SettingController::class, 'uploadImage'])->name('website.images.store');
            Route::put('/website/images/{image}', [SettingController::class, 'updateImage'])->name('website.images.update');
            Route::delete('/website/images/{image}', [SettingController::class, 'deleteImage'])->name('website.images.destroy');
        });
    });
});
