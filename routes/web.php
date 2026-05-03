<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'password.changed', 'twofactor.configured'])->name('dashboard');

Route::middleware(['auth', 'password.changed', 'twofactor.configured'])->group(function () {
    Route::resource('projects', ProjectController::class)->except('destroy');
    Route::resource('time-entries', TimeEntryController::class);
    Route::resource('transactions', TransactionController::class)->except('destroy');

    Route::get('/settings/system', [SystemSettingsController::class, 'edit'])->name('settings.system.edit');
    Route::put('/settings/system', [SystemSettingsController::class, 'update'])->name('settings.system.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

require __DIR__.'/auth.php';
