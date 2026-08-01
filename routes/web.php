<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GitHubConnectionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectImportController;
use App\Http\Controllers\ProjectStageController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TimeEntryImportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionImportController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'password.changed', 'twofactor.configured'])
    ->name('dashboard');

Route::middleware(['auth', 'password.changed', 'twofactor.configured', 'archive.open'])->group(function () {
    Route::get('/projects/import/template', [ProjectImportController::class, 'template'])->name('projects.import.template');
    Route::get('/projects/import', [ProjectImportController::class, 'create'])->name('projects.import.create');
    Route::post('/projects/import/preview', [ProjectImportController::class, 'preview'])->name('projects.import.preview');
    Route::post('/projects/import/commit', [ProjectImportController::class, 'commit'])->name('projects.import.commit');

    Route::get('/time-entries/import/template', [TimeEntryImportController::class, 'template'])->name('time-entries.import.template');
    Route::get('/time-entries/import', [TimeEntryImportController::class, 'create'])->name('time-entries.import.create');
    Route::post('/time-entries/import/preview', [TimeEntryImportController::class, 'preview'])->name('time-entries.import.preview');
    Route::post('/time-entries/import/commit', [TimeEntryImportController::class, 'commit'])->name('time-entries.import.commit');

    Route::resource('projects', ProjectController::class)->except('destroy');
    Route::resource('time-entries', TimeEntryController::class);

    Route::get('/transactions/import/template', [TransactionImportController::class, 'template'])->name('transactions.import.template');
    Route::get('/transactions/import', [TransactionImportController::class, 'create'])->name('transactions.import.create');
    Route::post('/transactions/import/preview', [TransactionImportController::class, 'preview'])->name('transactions.import.preview');
    Route::post('/transactions/import/commit', [TransactionImportController::class, 'commit'])->name('transactions.import.commit');

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

    Route::get('/settings/project-stages', [ProjectStageController::class, 'index'])->name('settings.project-stages.index');
    Route::get('/settings/project-stages/create', [ProjectStageController::class, 'create'])->name('settings.project-stages.create');
    Route::post('/settings/project-stages', [ProjectStageController::class, 'store'])->name('settings.project-stages.store');
    Route::get('/settings/project-stages/{projectStage}/edit', [ProjectStageController::class, 'edit'])->name('settings.project-stages.edit');
    Route::put('/settings/project-stages/{projectStage}', [ProjectStageController::class, 'update'])->name('settings.project-stages.update');
    Route::delete('/settings/project-stages/{projectStage}', [ProjectStageController::class, 'destroy'])->name('settings.project-stages.destroy');
    Route::patch('/settings/project-stages/{projectStage}/move-up', [ProjectStageController::class, 'moveUp'])->name('settings.project-stages.move-up');
    Route::patch('/settings/project-stages/{projectStage}/move-down', [ProjectStageController::class, 'moveDown'])->name('settings.project-stages.move-down');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::post('/documents/{document}/attach', [DocumentController::class, 'attach'])->name('documents.attach');
    Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::resource('vendors', VendorController::class);
    Route::resource('services', ServiceController::class);

    Route::get('/settings/github', [GitHubConnectionController::class, 'show'])->name('settings.github.show');
    Route::get('/settings/github/redirect', [GitHubConnectionController::class, 'redirect'])->name('settings.github.redirect');
    Route::delete('/settings/github', [GitHubConnectionController::class, 'destroy'])->name('settings.github.destroy');
});

// GitHub OAuth callback — exempt from password.changed / twofactor middleware (arrives mid-flow)
Route::middleware('auth')->get('/settings/github/callback', [GitHubConnectionController::class, 'callback'])->name('settings.github.callback');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

require __DIR__.'/auth.php';
