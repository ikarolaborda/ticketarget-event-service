<?php

declare(strict_types=1);

use App\Http\Controllers\DeleteEventController;
use App\Http\Controllers\ListEventsController;
use App\Http\Controllers\ShowEventController;
use App\Http\Controllers\StoreEventController;
use App\Http\Controllers\StoreTicketsController;
use App\Http\Controllers\StoreVenueController;
use App\Http\Controllers\UpdateEventController;
use Illuminate\Support\Facades\Route;

Route::get('/events', ListEventsController::class)->name('events.index');
Route::get('/event/{event}', ShowEventController::class)->name('events.show');

// Catalog administration — Sanctum token with the `events:write` ability.
Route::middleware(['auth:sanctum', 'abilities:events:write'])->group(function (): void {
    Route::post('/venues', StoreVenueController::class)->name('venues.store');
    Route::post('/events', StoreEventController::class)->name('events.store');
    Route::put('/events/{event}', UpdateEventController::class)->name('events.update');
    Route::delete('/events/{event}', DeleteEventController::class)->name('events.destroy');
    Route::post('/events/{event}/tickets', StoreTicketsController::class)->name('events.tickets.store');
});
