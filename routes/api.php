<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ConsumptionController;
use App\Http\Controllers\Api\PrediccionController;

// ── Rutas públicas ─────────────────────────────────────────────────────────────
Route::post('/auth/login', [AuthController::class, 'login']);

// Consumida por el datasource JSON-API de Grafana (sin sesión de usuario)
Route::get('/prediction', [PrediccionController::class, 'index']);

// ── Rutas protegidas con Sanctum ────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout',      [AuthController::class, 'logout']);
    Route::get('/auth/me',           [AuthController::class, 'me']);

    // Devices
    Route::get('/devices',                        [DeviceController::class, 'index']);
    Route::get('/devices/{id}/current',           [DeviceController::class, 'current']);
    Route::get('/devices/{id}/consumption',       [DeviceController::class, 'consumption']);
    Route::get('/devices/{id}/stats',             [DeviceController::class, 'stats']);
    Route::get('/devices/{id}/forecast',          [DeviceController::class, 'forecast']);

    // Rules
    Route::get('/rules',             [RuleController::class, 'index']);
    Route::post('/rules',            [RuleController::class, 'store']);
    Route::put('/rules/{id}',        [RuleController::class, 'update']);
    Route::delete('/rules/{id}',     [RuleController::class, 'destroy']);
    Route::patch('/rules/{id}/toggle', [RuleController::class, 'toggle']);

    // Alerts
    Route::get('/alerts',            [AlertController::class, 'index']);

    // Reports
    Route::get('/reports',                         [ReportController::class, 'index']);
    Route::get('/reports/{informe}/download',      [ReportController::class, 'download']);
    Route::delete('/reports/{informe}',            [ReportController::class, 'destroy']);

    // Consumption
    Route::get('/consumption/summary', [ConsumptionController::class, 'summary']);
    Route::get('/consumption/cost',    [ConsumptionController::class, 'cost']);

});
