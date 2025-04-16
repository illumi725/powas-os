<?php

use App\Http\Controllers\API\BillingsController;
use App\Http\Controllers\ApplicationFormController;
use App\Http\Controllers\Applications\ViewApplicationController;
use App\Http\Controllers\Billings\BillPrinterController;
use App\Http\Controllers\Billings\CollectionSheetController;
use App\Http\Controllers\Chatbot\PocaController;
use App\Http\Controllers\CSV\CsvController;
use App\Http\Controllers\Members\AddMemberController;
use App\Http\Controllers\POWAS\BillingGenerateController;
use App\Http\Controllers\POWAS\ManagePowasController;
use App\Http\Controllers\POWAS\PowasApplicationsController;
use App\Http\Controllers\POWAS\ShowApplicationListController;
use App\Http\Controllers\POWAS\ShowMembersListController;
use App\Http\Controllers\POWAS\ShowPowasListController;
use App\Http\Controllers\POWAS\ShowPowasRecordsController;
use App\Http\Controllers\PowasMembersController;
use App\Http\Controllers\Readings\ReadingSheetController;
use App\Http\Controllers\ReadingsController;
use App\Http\Controllers\Receipts\BillingReceiptController;
use App\Http\Controllers\Receipts\OtherReceiptController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Transactions\TransactionsListController;
use App\Http\Controllers\Users\UsersController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\POWASController;
use App\Http\Controllers\API\MembersController;
use App\Http\Controllers\API\ReadingsAPIController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/apply', [PowasApplicationsController::class, 'index'])->name('apply');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::middleware(['role:admin|president|vice-president|secretary|treasurer|auditor|collector-reader|board', 'account_status'])->group(function () {
        Route::get('/billing-system/{powasID}', [ShowPowasRecordsController::class, 'index'])->name('powas.records');
        Route::get('/applications', [ShowApplicationListController::class, 'index'])->name('applications');
        Route::get('/view-application/{applicationid}', [ViewApplicationController::class, 'index'])->name('view-applications');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::get('/members', [ShowMembersListController::class, 'index'])->name('members');
        Route::get('/members/add/{powasID}', [AddMemberController::class, 'index'])->name('members.add');
        Route::get('/transactions/{powasID}', [TransactionsListController::class, 'index'])->name('view-transactions');
        Route::get('/accounting/{powasID}/{transactionMonth}', [TransactionsListController::class, 'accounting'])->name('accounting');
        Route::get('/voucher/print/{powasID}/{voucherID?}', [TransactionsListController::class, 'printVoucher'])->name('print-voucher');
    });
    Route::middleware('role:admin', 'account_status')->group(function () {
        Route::get('/users', [UsersController::class, 'index'])->name('users');
        Route::get('/powas', [ShowPowasListController::class, 'index'])->name('powas');
        Route::get('/poca-memory', [PocaController::class, 'index'])->name('poca-brain');
    });
    Route::middleware('role:admin|treasurer', 'account_status')->group(function () {
        Route::prefix('/receipt')->group(function () {
            Route::get('/view', [OtherReceiptController::class, 'view'])->name('other-receipt.view');
        });
        Route::get('/members-csv-template/{powasID?}/{numberOfMembers}', [CsvController::class, 'membersCSVTemplate'])->name('members-csv-template');
        Route::get('/create-reading-template/{powasID}', [CsvController::class, 'readingImportTemplate'])->name('create-reading-template');
        Route::get('/powas/{powas_id}', [ManagePowasController::class, 'index'])->name('powas.show');
    });
    Route::middleware('role:admin|treasurer|collector-reader', 'account_status')->group(function () {
        Route::get('/powas/add-billing/{powasID}/{regen}', [BillingGenerateController::class, 'index'])->name('powas.add.billing');
        Route::get('/print-billing', [BillPrinterController::class, 'view'])->name('powas.print-billing');
        Route::get('/collection-sheet/{powasID}/{billingMonth?}', [CollectionSheetController::class, 'view'])->name('powas.collection-sheet');
        Route::get('/reading-sheet/{powasID}/{readingDate?}', [ReadingSheetController::class, 'view'])->name('powas.reading-sheet');
        Route::get('/powas/add-readings/{powasID}', [ReadingsController::class, 'index'])->name('powas.add.reading');
        Route::get('/billing-receipts', [BillingReceiptController::class, 'index'])->name('billing-receipts');
    });
    Route::middleware('role:admin|member|secretary|president|treasurer', 'account_status')->group(function () {
        Route::get('/member/edit/{memberID}', [PowasMembersController::class, 'personalInfo'])->name('member-info');
    });
});

Route::prefix('/application-form')->group(function () {
    Route::post('/view/{applicationid}', [ApplicationFormController::class, 'view'])->name('application-form.view');
    Route::post('/download/{applicationid}', [ApplicationFormController::class, 'download'])->name('application-form.download');
});

// Backend APIs

// Route::get('/api/powas', [POWASController::class, 'index']);
// Route::get('/api/powas/{id}', [PowasController::class, 'show']);

// Route::get('/api/members', [MembersController::class, 'index']);

// Route::get('/api/billings/{powasID?}', [BillingsController::class, 'unpaidBills']);

// Route::get('/api/readings/{powasID?}', [ReadingsAPIController::class, 'readingsIndex']);