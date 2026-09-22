<?php

use App\Http\Controllers\Api\Bot\FinanceController;
use App\Http\Controllers\Api\Bot\ProjectsController;
use App\Http\Controllers\Api\Bot\TimeEntriesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('bot')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/projects', [ProjectsController::class, 'index']);
    Route::get('/time-entries/summary', [TimeEntriesController::class, 'summary']);
    Route::get('/finance/summary', [FinanceController::class, 'summary']);
});
