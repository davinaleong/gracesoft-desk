<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'password.changed', 'twofactor.configured'])
    ->name('dashboard');

Route::middleware(['auth', 'password.changed', 'twofactor.configured'])->group(function () {
    Route::resource('projects', ProjectController::class)->except('destroy');
    Route::resource('time-entries', TimeEntryController::class);
    Route::resource('transactions', TransactionController::class)->except('destroy');

    Route::get('/reports/finance', [ReportController::class, 'finance'])->name('reports.finance');
    Route::get('/reports/projects', [ReportController::class, 'projects'])->name('reports.projects');
    Route::get('/reports/monthly-summary', [ReportController::class, 'monthlySummary'])->name('reports.monthly-summary');

    Route::get('/reports/finance/print', [ReportController::class, 'printFinance'])->name('reports.finance.print');
    Route::get('/reports/projects/print', [ReportController::class, 'printProjects'])->name('reports.projects.print');
    Route::get('/reports/monthly-summary/print', [ReportController::class, 'printMonthlySummary'])->name('reports.monthly-summary.print');

    Route::get('/reports/finance/export', [ReportExportController::class, 'finance'])->name('reports.finance.export');
    Route::get('/reports/projects/export', [ReportExportController::class, 'projects'])->name('reports.projects.export');
    Route::get('/reports/monthly-summary/export', [ReportExportController::class, 'monthlySummary'])->name('reports.monthly-summary.export');

    Route::get('/settings/system', [SystemSettingsController::class, 'edit'])->name('settings.system.edit');
    Route::put('/settings/system', [SystemSettingsController::class, 'update'])->name('settings.system.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

require __DIR__.'/auth.php';
