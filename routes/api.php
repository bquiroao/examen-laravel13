<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Clinical\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('tenant')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('sesion')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/auth/hospitales', [AuthController::class, 'hospitals']);
        Route::post('/auth/cambiar-hospital', [AuthController::class, 'switchHospital']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/disponibilidad', [AppointmentController::class, 'availability'])->middleware('permiso:citas.ver');
        Route::get('/citas', [AppointmentController::class, 'index'])->middleware('permiso:citas.ver');
        Route::post('/citas', [AppointmentController::class, 'store'])->middleware('permiso:citas.gestionar');
        Route::post('/citas/{appointment}/estado', [AppointmentController::class, 'transition'])->middleware('permiso:citas.gestionar')->whereUuid('appointment');
    });
});