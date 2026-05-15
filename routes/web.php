<?php

use App\Http\Controllers\AlertLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispositivoController;
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
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ── Ruta pública: datos para Grafana JSON-API (sin sesión) ─────────────────────
Route::get('/prediccion/obtener', [PrediccionController::class, 'obtenerDatos'])
    ->name('prediccion.obtener');

// Alias legacy usado por paneles Grafana ya configurados — mantener sin cambiar
Route::get('/monitorizacion-prediccion/obtener-datos', [PrediccionController::class, 'obtenerDatos'])
    ->name('prediccion.obtener.legacy');

// ── Rutas autenticadas ─────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // ── Dashboard ──────────────────────────────────────────────────────────────
    Route::get('/inicio', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/perfil', [ConfigController::class, 'cuenta'])->name('perfil');

    // ── Configuración ──────────────────────────────────────────────────────────
    Route::get('/configuracion/cuenta',  [ConfigController::class, 'cuenta'])->name('configuracion.cuenta');
    Route::post('/configuracion/cuenta', [ConfigController::class, 'updateCuenta'])->name('configuracion.cuenta.update');

    Route::get('/configuracion/sistema',                    [ConfigController::class, 'sistema'])->name('configuracion.sistema');
    Route::post('/configuracion/sistema',                   [ConfigController::class, 'updateSistema'])->name('configuracion.sistema.update');
    Route::post('/configuracion/sistema/purgar-alertas',    [ConfigController::class, 'purgarAlertas'])->name('configuracion.sistema.purgar-alertas');
    Route::post('/configuracion/sistema/purgar-informes',   [ConfigController::class, 'purgarInformes'])->name('configuracion.sistema.purgar-informes');

    Route::get('/configuracion/conexiones',  [ConfigController::class, 'conexiones'])->name('configuracion.conexiones');
    Route::post('/configuracion/conexiones', [ConfigController::class, 'updateConexiones'])->name('configuracion.conexiones.update');

    Route::get('/configuracion/logs',          [ConfigController::class, 'logs'])->name('configuracion.logs');
    Route::post('/configuracion/logs/clear',   [ConfigController::class, 'clearLogs'])->name('configuracion.logs.clear');
    Route::get('/configuracion/logs/download', [ConfigController::class, 'downloadLog'])->name('configuracion.logs.download');

    // ── Monitorización ─────────────────────────────────────────────────────────
    Route::get('/monitorizacion/tiempo-real',  [DispositivoController::class, 'tiempoReal'])->name('monitorizacion.tiempo-real');
    Route::get('/monitorizacion/dispositivos', [DispositivoController::class, 'index'])->name('monitorizacion.dispositivos');
    Route::get('/monitorizacion/series',       [\App\Http\Controllers\GrafanaController::class, 'series'])->name('monitorizacion.series');

    // ── Predicción ─────────────────────────────────────────────────────────────
    Route::get('/monitorizacion/prediccion',  [PrediccionController::class, 'index'])->name('prediccion.index');
    Route::post('/monitorizacion/prediccion', [PrediccionController::class, 'predecir'])->name('prediccion.store');

    // ── Dispositivos (CRUD) ────────────────────────────────────────────────────
    Route::post('/dispositivos',                        [DispositivoController::class, 'store'])->name('dispositivos.store');
    Route::put('/dispositivos/{dispositivo}',           [DispositivoController::class, 'update'])->name('dispositivos.update');
    Route::patch('/dispositivos/{dispositivo}/toggle',  [DispositivoController::class, 'toggle'])->name('dispositivos.toggle');
    Route::delete('/dispositivos/{dispositivo}',        [DispositivoController::class, 'destroy'])->name('dispositivos.destroy');

    // ── Alertas ────────────────────────────────────────────────────────────────
    Route::get('/alertas/acciones',    [ReglaController::class, 'index'])->name('alertas.acciones');
    Route::get('/alertas/plantillas',  [PlantillaController::class, 'index'])->name('alertas.plantillas');
    Route::get('/alertas/medios',      [NotificationMethodController::class, 'index'])->name('alertas.medios');
    Route::get('/alertas/historial',   [AlertLogController::class, 'index'])->name('alertas.historial');

    // Plantillas de alerta
    Route::post('/plantillas/{canal}',       [PlantillaController::class, 'store'])->name('alertas.plantillas.store');
    Route::delete('/plantillas/{canal}/{id}',[PlantillaController::class, 'destroy'])->name('alertas.plantillas.destroy');

    // Reglas de alerta
    Route::post('/reglas',             [ReglaController::class, 'store'])->name('reglas.store');
    Route::put('/reglas/{id}',         [ReglaController::class, 'update'])->name('reglas.update');
    Route::patch('/reglas/{id}/toggle',[ReglaController::class, 'toggle'])->name('reglas.toggle');
    Route::delete('/reglas/{id}',      [ReglaController::class, 'destroy'])->name('reglas.destroy');

    // ── Notificaciones internas (campana) ──────────────────────────────────────
    Route::patch('/notificaciones/{id}/read', [NotificacionesController::class, 'read'])->name('notificaciones.read');
    Route::patch('/notificaciones/read-all',  [NotificacionesController::class, 'readAll'])->name('notificaciones.read-all');

    Route::get('/usuario/notificaciones', [NotificacionesController::class, 'index'])->name('notificaciones.index');

    // ── Métodos de notificación (telegram / email / discord) ───────────────────
    Route::put('configuracion/medios/{type}',    [NotificationMethodController::class, 'update'])
        ->where('type', 'telegram|email|discord')
        ->name('medios.update');

    Route::delete('configuracion/medios/{type}', [NotificationMethodController::class, 'destroy'])
        ->where('type', 'telegram|email|discord')
        ->name('medios.destroy');

    // ── Informes ───────────────────────────────────────────────────────────────
    Route::get('/informes/programados',             [InformeController::class, 'programados'])->name('informes.programados');
    Route::get('/informes/registro',                [InformeController::class, 'registro'])->name('informes.registro');
    Route::get('/informes/demanda',                 [InformeController::class, 'demanda'])->name('informes.demanda');
    Route::post('/informes/demanda',                [InformeController::class, 'generarInformeDemanda'])->name('informes.demanda.generar');
    Route::get('/informes/demanda/descargar/{filename}', [InformeController::class, 'descargarBajoDemanda'])->name('informes.demanda.descargar');
    Route::get('/informes/{informe}/download',      [InformeController::class, 'download'])->name('informes.download');
    Route::delete('/informes/{informe}',            [InformeController::class, 'destroy'])->name('informes.destroy');

    // ── Programaciones de informes ─────────────────────────────────────────────
    Route::post('/programaciones',                          [ProgramacionInformesController::class, 'store'])->name('programaciones.store');
    Route::put('/programaciones/{programacionInformes}',    [ProgramacionInformesController::class, 'update'])->name('programaciones.update');
    Route::delete('/programaciones/{programacionInformes}', [ProgramacionInformesController::class, 'destroy'])->name('programaciones.destroy');
    Route::patch('/programaciones/{programacionInformes}/toggle', [ProgramacionInformesController::class, 'toggle'])->name('programaciones.toggle');

    // ── Usuarios ───────────────────────────────────────────────────────────────
    Route::get('/usuarios',                    [UserController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios',                   [UserController::class, 'store'])->name('usuarios.store');
    Route::put('/usuarios/{user}',             [UserController::class, 'update'])->name('usuarios.update');
    Route::delete('/usuarios/{user}',          [UserController::class, 'destroy'])->name('usuarios.destroy');
    Route::patch('/usuarios/{user}/toggle',    [UserController::class, 'toggle'])->name('usuarios.toggle');
    Route::put('/usuarios/language',           [AuthController::class, 'updateLanguage'])->name('usuarios.language');

    // ── Tokens de API ──────────────────────────────────────────────────────────
    Route::get('/usuarios/tokens',             [TokenController::class, 'index'])->name('tokens.index');
    Route::post('/usuarios/tokens',            [TokenController::class, 'store'])->name('tokens.store');
    Route::delete('/usuarios/tokens/{id}',     [TokenController::class, 'destroy'])->name('tokens.destroy');

    // ── Placeholder (pendiente de implementar) ─────────────────────────────────
    Route::get('/datos-bd', fn () => view('dashboard'))->name('datos-bd');

    // ── Proxy autenticado hacia Grafana (Auth Proxy) ───────────────────────────
    // El navegador nunca habla con Grafana directamente: toda petición pasa por
    // aquí y Laravel inyecta X-WEBAUTH-USER antes de reenviarla a Grafana.
    Route::any('/grafana',        [GrafanaProxyController::class, 'proxy'])->defaults('path', '');
    Route::any('/grafana/{path}', [GrafanaProxyController::class, 'proxy'])->where('path', '.*');
});
