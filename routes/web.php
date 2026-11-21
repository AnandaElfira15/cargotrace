<?php

use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CarrierController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ExceptionController;
use App\Http\Controllers\Web\ExternalController;
use App\Http\Controllers\Web\LocationController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\SettingController;
use App\Http\Controllers\Web\ShipmentController;
use App\Http\Controllers\Web\TrackingController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:login')->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

    Route::resource('shipments', ShipmentController::class);
    Route::get('shipments/{shipment}/weather', [ExternalController::class, 'weather'])->name('shipments.weather');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // Staff (admin + operator)
    Route::middleware('role:admin,operator')->group(function () {
        Route::post('shipments/{shipment}/tracking', [TrackingController::class, 'store'])->name('shipments.tracking.store');
        Route::post('shipments/{shipment}/exceptions', [ExceptionController::class, 'store'])->name('shipments.exceptions.store');
        Route::get('exceptions', [ExceptionController::class, 'index'])->name('exceptions.index');
        Route::patch('exceptions/{shipmentException}', [ExceptionController::class, 'update'])->name('exceptions.update');
        Route::get('geocode', [ExternalController::class, 'geocode'])->name('geocode');
    });

    // Administrator only
    Route::middleware('role:admin')->group(function () {
        Route::resource('carriers', CarrierController::class)->except('show');
        Route::resource('locations', LocationController::class)->except('show');
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
});
