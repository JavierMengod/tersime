<?php

use App\Http\Controllers\AlertLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispositivoController;
use App\Http\Controllers\GrafanaController;
use App\Http\Controllers\GrafanaProxyController;
use App\Http\Controllers\InformeController;
use App\Http\Controllers\NotificacionesController;
use App\Http\Controllers\NotificationMethodController;
use App\Http\Controllers\PlantillaController;
use App\Http\Controllers\PrediccionController;
use App\Http\Controllers\ProgramacionInformesController;
use App\Http\Controllers\ReglaController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ── Autenticación ──────────────────────────────────────────────────────────────
Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ── Rutas públicas: datos JSON para paneles Grafana (sin sesión) ───────────────
Route::get('/prediccion/obtener', [PrediccionController::class, 'obtenerDatos'])
    ->name('prediccion.obtener');

// Alias legacy — paneles Grafana ya configurados, no modificar
Route::get('/monitorizacion-prediccion/obtener-datos', [PrediccionController::class, 'obtenerDatos'])
    ->name('prediccion.obtener.legacy');

// ── Rutas autenticadas ─────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // ── Dashboard ──────────────────────────────────────────────────────────────
    Route::get('/inicio',    [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/datos-bd',  [DashboardController::class, 'datosBd'])->name('datos.bd');

    // ── Configuración ──────────────────────────────────────────────────────────
    Route::prefix('configuracion')->name('configuracion.')->group(function () {

        Route::get('cuenta',              [ConfigController::class, 'cuenta'])->name('cuenta');
        Route::post('cuenta/preferencias',[ConfigController::class, 'updatePreferencias'])->name('cuenta.preferencias');
        Route::post('cuenta/password',    [ConfigController::class, 'updatePassword'])->name('cuenta.password');

        Route::get('sistema',                  [ConfigController::class, 'sistema'])->name('sistema');
        Route::post('sistema',                 [ConfigController::class, 'updateSistema'])->name('sistema.update');
        Route::post('sistema/purgar-alertas',  [ConfigController::class, 'purgarAlertas'])->name('sistema.purgar_alertas');
        Route::post('sistema/purgar-informes', [ConfigController::class, 'purgarInformes'])->name('sistema.purgar_informes');

        Route::get('conexiones',  [ConfigController::class, 'conexiones'])->name('conexiones');
        Route::post('conexiones', [ConfigController::class, 'updateConexiones'])->name('conexiones.update');

        Route::get('logs',          [ConfigController::class, 'logs'])->name('logs');
        Route::post('logs/clear',   [ConfigController::class, 'clearLogs'])->name('logs.clear');
        Route::get('logs/download', [ConfigController::class, 'downloadLog'])->name('logs.download');
    });

    // ── Monitorización ─────────────────────────────────────────────────────────
    Route::prefix('monitorizacion')->name('monitorizacion.')->group(function () {

        Route::get('tiempo-real',  [DispositivoController::class, 'tiempoReal'])->name('tiempo_real');
        Route::get('dispositivos', [DispositivoController::class, 'index'])->name('dispositivos');
        Route::get('series',       [GrafanaController::class, 'series'])->name('series');

        Route::get('prediccion', [PrediccionController::class, 'index'])->name('prediccion');
    });

    // ── Dispositivos (CRUD) ────────────────────────────────────────────────────
    Route::prefix('dispositivos')->name('dispositivos.')->group(function () {

        Route::post('/',                     [DispositivoController::class, 'store'])->name('store');
        Route::put('{dispositivo}',          [DispositivoController::class, 'update'])->name('update');
        Route::patch('{dispositivo}/toggle', [DispositivoController::class, 'toggle'])->name('toggle');
        Route::delete('{dispositivo}',       [DispositivoController::class, 'destroy'])->name('destroy');
    });

    // ── Alertas ────────────────────────────────────────────────────────────────
    Route::prefix('alertas')->name('alertas.')->group(function () {

        // Reglas de alerta
        Route::get('reglas',                   [ReglaController::class, 'index'])->name('reglas');
        Route::post('reglas',                  [ReglaController::class, 'store'])->name('reglas.store');
        Route::put('reglas/{regla}',           [ReglaController::class, 'update'])->name('reglas.update');
        Route::patch('reglas/{regla}/toggle',  [ReglaController::class, 'toggle'])->name('reglas.toggle');
        Route::delete('reglas/{regla}',        [ReglaController::class, 'destroy'])->name('reglas.destroy');

        // Plantillas de mensaje
        Route::get('plantillas',                 [PlantillaController::class, 'index'])->name('plantillas');
        Route::post('plantillas/{canal}',         [PlantillaController::class, 'store'])->name('plantillas.store');
        Route::delete('plantillas/{canal}/{id}',  [PlantillaController::class, 'destroy'])->name('plantillas.destroy');

        // Métodos de notificación (telegram / email / discord)
        Route::get('medios',            [NotificationMethodController::class, 'index'])->name('medios');
        Route::put('medios/{type}',     [NotificationMethodController::class, 'update'])
            ->where('type', 'telegram|email|discord')->name('medios.update');
        Route::delete('medios/{type}',  [NotificationMethodController::class, 'destroy'])
            ->where('type', 'telegram|email|discord')->name('medios.destroy');

        Route::get('historial', [AlertLogController::class, 'index'])->name('historial');
    });

    // ── Notificaciones internas (campana) ──────────────────────────────────────
    Route::prefix('notificaciones')->name('notificaciones.')->group(function () {

        Route::get('/',          [NotificacionesController::class, 'index'])->name('index');
        Route::patch('read-all', [NotificacionesController::class, 'readAll'])->name('read_all');   // estático antes que {id}
        Route::patch('{id}/read',[NotificacionesController::class, 'read'])->name('read');
    });

    // ── Informes ───────────────────────────────────────────────────────────────
    Route::prefix('informes')->name('informes.')->group(function () {

        Route::get('programados',                [InformeController::class, 'programados'])->name('programados');
        Route::get('registro',                   [InformeController::class, 'registro'])->name('registro');
        Route::get('demanda',                    [InformeController::class, 'demanda'])->name('demanda');
        Route::post('demanda',                   [InformeController::class, 'generarInformeDemanda'])->name('demanda.store');
        Route::get('demanda/{filename}/download',[InformeController::class, 'descargarBajoDemanda'])->name('demanda.download');
        Route::get('{informe}/status',           [InformeController::class, 'status'])->name('status');
        Route::get('{informe}/download',         [InformeController::class, 'download'])->name('download');
        Route::delete('{informe}',               [InformeController::class, 'destroy'])->name('destroy');
    });

    // ── Programaciones de informes ─────────────────────────────────────────────
    Route::prefix('programaciones')->name('programaciones.')->group(function () {

        Route::post('/',                      [ProgramacionInformesController::class, 'store'])->name('store');
        Route::put('{programacion}',          [ProgramacionInformesController::class, 'update'])->name('update');
        Route::delete('{programacion}',       [ProgramacionInformesController::class, 'destroy'])->name('destroy');
        Route::patch('{programacion}/toggle', [ProgramacionInformesController::class, 'toggle'])->name('toggle');
    });

    // ── Usuarios ───────────────────────────────────────────────────────────────
    Route::prefix('usuarios')->name('usuarios.')->group(function () {

        Route::get('/',               [UserController::class, 'index'])->name('index');
        Route::post('/',              [UserController::class, 'store'])->name('store');
        Route::put('language',        [AuthController::class, 'updateLanguage'])->name('language');  // estático antes que {user}
        Route::put('{user}',          [UserController::class, 'update'])->name('update');
        Route::delete('{user}',       [UserController::class, 'destroy'])->name('destroy');
        Route::patch('{user}/toggle', [UserController::class, 'toggle'])->name('toggle');

        // ── Tokens de API ──────────────────────────────────────────────────────
        Route::prefix('tokens')->name('tokens.')->group(function () {
            Route::get('/',       [TokenController::class, 'index'])->name('index');
            Route::post('/',      [TokenController::class, 'store'])->name('store');
            Route::delete('{id}', [TokenController::class, 'destroy'])->name('destroy');
        });
    });

    // ── Proxy autenticado hacia Grafana (Auth Proxy) ───────────────────────────
    // Toda petición pasa por aquí; Laravel inyecta X-WEBAUTH-USER antes de reenviarla.
    Route::any('/grafana',        [GrafanaProxyController::class, 'proxy'])->defaults('path', '');
    Route::any('/grafana/{path}', [GrafanaProxyController::class, 'proxy'])->where('path', '.*');
});
