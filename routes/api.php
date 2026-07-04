<?php

declare(strict_types=1);

use App\Http\Controllers\DeleteEventController;
use App\Http\Controllers\DeleteZoneController;
use App\Http\Controllers\GenerateZoneTicketsController;
use App\Http\Controllers\ListEventsController;
use App\Http\Controllers\ListEventZonesController;
use App\Http\Controllers\ListVenuesController;
use App\Http\Controllers\ListVenueZonesController;
use App\Http\Controllers\ShowEventController;
use App\Http\Controllers\StoreEventController;
use App\Http\Controllers\StoreTicketsController;
use App\Http\Controllers\StoreVenueController;
use App\Http\Controllers\StoreZoneController;
use App\Http\Controllers\UpdateEventController;
use App\Http\Controllers\UpdateZoneController;
use App\Http\Middleware\AdminBearerAuth;
use Illuminate\Support\Facades\Route;

Route::get('/events', ListEventsController::class)->name('events.index');
Route::get('/event/{event}', ShowEventController::class)->name('events.show');
Route::get('/venues', ListVenuesController::class)->name('venues.index');
Route::get('/venues/{venue}/zones', ListVenueZonesController::class)->name('venues.zones.index');
Route::get('/events/{event}/zones', ListEventZonesController::class)->name('events.zones.index');

// Catalog administration — platform JWT with is_admin OR Sanctum events:write.
Route::middleware(AdminBearerAuth::class)->group(function (): void {
    Route::post('/venues', StoreVenueController::class)->name('venues.store');
    Route::post('/events', StoreEventController::class)->name('events.store');
    Route::put('/events/{event}', UpdateEventController::class)->name('events.update');
    Route::delete('/events/{event}', DeleteEventController::class)->name('events.destroy');
    Route::post('/events/{event}/tickets', StoreTicketsController::class)->name('events.tickets.store');
    Route::post('/venues/{venue}/zones', StoreZoneController::class)->name('venues.zones.store');
    Route::put('/venues/{venue}/zones/{zone}', UpdateZoneController::class)->name('venues.zones.update');
    Route::delete('/venues/{venue}/zones/{zone}', DeleteZoneController::class)->name('venues.zones.destroy');
    Route::post('/events/{event}/zones/{zone}/tickets', GenerateZoneTicketsController::class)->name('events.zones.tickets.store');
});
