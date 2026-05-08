<?php

use App\Http\Controllers\EventSimulatorController;
use Illuminate\Support\Facades\Route;

Route::prefix('events')->middleware('throttle:60,1')->group(function () {
    Route::post('/invite', [EventSimulatorController::class, 'invite'])->name('events.invite');
    Route::post('/task-assigned', [EventSimulatorController::class, 'assignTask'])->name('events.task_assigned');
    Route::post('/comment', [EventSimulatorController::class, 'comment'])->name('events.comment');
});
