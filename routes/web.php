<?php

use App\Http\Controllers\ProgramacionInformesController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DispositivoController;
use App\Http\Controllers\NotificationMethodController;
use App\Http\Controllers\ReglaController;
use App\Http\Controllers\PlantillaController;
use App\Http\Controllers\AlertLogController;
use App\Models\Plantilla;
use App\Http\Controllers\InformeController;
use App\Http\Controllers\NotificacionesController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\GrafanaController;
use App\Http\Controllers\GrafanaProxyController;
use App\Http\Controllers\PrediccionController;

// ── Autenticación ──────────────────────────────────────────────────────────────
Route::controller(AuthController::class)->group(function () {
    Route::get('/', 'showLoginForm')->name('login');
    Route::post('/login', 'login')->name('formulario-login');
    Route::get('/logout', 'logout')->name('logout');
});

// ── Rutas públicas de datos (llamadas desde Grafana JSON-API sin sesión) ────────
Route::get('/prediccion/obtener', [PrediccionController::class, 'obtenerDatos'])
    ->name('prediccion.obtener');

// Alias legacy que tenía configurado el panel de Grafana
Route::get('/monitorizacion-prediccion/obtener-datos', [PrediccionController::class, 'obtenerDatos']);

// ── Rutas autenticadas ─────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/inicio', function () {
        $dispositivos = auth()->user()->dispositivos()->wherePivot('habilitado', 1)->get();
        return view('dashboard', compact('dispositivos'));
    })->name('dashboard');

    Route::get('/perfil', function () {
        return view('dashboard');
    })->name('perfil');

    // ── Configuración ──────────────────────────────────────────────────────────
    Route::get('/configuracion/cuenta',  [ConfigController::class, 'cuenta'])->name('configuracion-cuenta');
    Route::post('/configuracion/cuenta', [ConfigController::class, 'updateCuenta'])->name('configuracion-cuenta.update');

    Route::get('/configuracion/sistema',           [ConfigController::class, 'sistema'])->name('configuracion-sistema');
    Route::post('/configuracion/sistema',          [ConfigController::class, 'updateSistema'])->name('configuracion-sistema.update');
    Route::post('/configuracion/sistema/purgar-alertas',  [ConfigController::class, 'purgarAlertas'])->name('configuracion-sistema.purgar-alertas');
    Route::post('/configuracion/sistema/purgar-informes', [ConfigController::class, 'purgarInformes'])->name('configuracion-sistema.purgar-informes');

    Route::get('/configuracion/conexiones',  [ConfigController::class, 'conexiones'])->name('configuracion-conexiones');
    Route::post('/configuracion/conexiones', [ConfigController::class, 'updateConexiones'])->name('configuracion-conexiones.update');

    Route::get('/configuracion/logs',          [ConfigController::class, 'logs'])->name('configuracion-logs');
    Route::post('/configuracion/logs/clear',   [ConfigController::class, 'clearLogs'])->name('configuracion-logs.clear');
    Route::get('/configuracion/logs/download', [ConfigController::class, 'downloadLog'])->name('configuracion-logs.download');

    // ── Monitorización ─────────────────────────────────────────────────────────
    Route::get('/monitorizacion-tiempo-real', function () {
        $dispositivos = auth()->user()->dispositivos()->wherePivot('habilitado', 1)->get();
        return view('monitorizacion.tiempo-real', compact('dispositivos'));
    })->name('monitorizacion-tiempo-real');

    Route::get('/monitorizacion-dispositivos', [DispositivoController::class, 'index'])
        ->name('monitorizacion-dispositivos');

    Route::get('/monitorizacion/series', [GrafanaController::class, 'series'])
        ->name('monitorizacion.series');

    Route::get('/monitorizacion-prediccion', [PrediccionController::class, 'index'])
        ->name('prediccion.index');

    Route::post('/predecir', [PrediccionController::class, 'predecir'])
        ->name('prediccion.predecir');

    // ── Dispositivos ───────────────────────────────────────────────────────────
    Route::post('/dispositivo/store', [DispositivoController::class, 'store'])
        ->name('dispositivo.store');

    Route::put('/dispositivos/{dispositivo}', [DispositivoController::class, 'update'])
        ->name('dispositivo.update');

    Route::patch('/dispositivos/{dispositivo}/toggle', [DispositivoController::class, 'toggle'])
        ->name('dispositivo.toggle');

    Route::delete('/dispositivos/{dispositivo}', [DispositivoController::class, 'destroy'])
        ->name('dispositivo.destroy');

    // ── Alertas ────────────────────────────────────────────────────────────────
    Route::get('/alertas-acciones', function () {
        $dispositivos = auth()->user()->dispositivos()->wherePivot('habilitado', 1)->get();
        $reglas       = auth()->user()->rules()->with('dispositivos')->paginate(10);
        return view('alertas.acciones', compact('dispositivos', 'reglas'));
    })->name('alertas-acciones');

    Route::get('/alertas-plantillas', function () {
        $dispositivos = auth()->user()->dispositivos()->wherePivot('habilitado', 1)->get();
        $plantillas   = Plantilla::where('user_id', auth()->id())->get()->groupBy('canal');
        return view('alertas.plantillas', compact('dispositivos', 'plantillas'));
    })->name('alertas-plantillas');

    Route::get('/alertas-medios', function () {
        return view('alertas.notificacion');
    })->name('alertas-medios');

    Route::get('/alertas-historial', [AlertLogController::class, 'index'])->name('alertas-historial');

    // Plantillas de alerta (crear / eliminar)
    Route::post('/plantillas/{canal}', [PlantillaController::class, 'create'])
        ->name('alertas-plantillas.crear');

    Route::delete('/plantillas/{canal}/{id}', [PlantillaController::class, 'destroy'])
        ->name('alertas-plantillas.eliminar');

    // Reglas de alerta
    Route::post('/reglas/update', [ReglaController::class, 'guardar'])->name('reglas.guardar');
    Route::put('/reglas/{id}', [ReglaController::class, 'update'])->name('reglas.update');
    Route::patch('/reglas/{id}/toggle', [ReglaController::class, 'toggle'])->name('reglas.toggle');
    Route::delete('/reglas/{id}', [ReglaController::class, 'destroy'])->name('reglas.destroy');

    // Notificaciones internas (campana)
    Route::patch('/notificaciones/{id}/read', function (string $id) {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();
        return response()->noContent();
    })->name('notifications.read');

    Route::patch('/notificaciones/read-all', function () {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    })->name('notifications.read-all');

    // Medios de notificación
    Route::put('settings/notifications/{type}', [NotificationMethodController::class, 'update'])
        ->where('type', 'telegram|email|discord')
        ->name('notifications.update');

    Route::delete('settings/notifications/{type}', [NotificationMethodController::class, 'destroy'])
        ->where('type', 'telegram|email|discord')
        ->name('notifications.destroy');

    // ── Informes ───────────────────────────────────────────────────────────────
    Route::get('informes-programados', [InformeController::class, 'programados'])
        ->name('informes-programados');

    Route::get('informes-registro', [InformeController::class, 'registro'])
        ->name('informes-registro');

    Route::get('informes-demanda', [InformeController::class, 'demanda'])
        ->name('informes-demanda');

    Route::post('/informes/demanda', [InformeController::class, 'generarInformeDemanda'])
        ->name('informes.demanda.generar');

    Route::get('/informes/demanda/descargar/{filename}', function ($filename) {
        $path = storage_path('app/public/informes/' . $filename);
        abort_unless(file_exists($path), 404);
        return response()->download($path);
    })->name('informes.demanda.descargar');

    // Descarga y borrado de informes generados
    Route::get('/informes/{informe}/download', [InformeController::class, 'download'])
        ->name('informes.download');

    Route::delete('/informes/{informe}', [InformeController::class, 'destroy'])
        ->name('informes.destroy');

    // Programaciones de informes
    Route::post('/programaciones', [ProgramacionInformesController::class, 'store'])
        ->name('programaciones.store');

    Route::put('/programacionesU/{programacionInformes}', [ProgramacionInformesController::class, 'update'])
        ->name('programaciones.update');

    Route::delete('/programaciones/{programacionInformes}', [ProgramacionInformesController::class, 'destroy'])
        ->name('programaciones.destroy');

    Route::patch('/programaciones/{programacionInformes}/toggle', [ProgramacionInformesController::class, 'toggle'])
        ->name('programaciones.toggle');

    // ── Notificaciones ────────────────────────────────────────────────────────
    Route::get('/usuario/notificaciones', [NotificacionesController::class, 'index'])
        ->name('notificaciones.index');

    // ── Usuarios ───────────────────────────────────────────────────────────────
    Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios');
    Route::post('/usuarios', [UserController::class, 'store'])->name('user.store');
    Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('user.update');
    Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->name('user.destroy');
    Route::patch('/usuarios/{user}/toggle', [UserController::class, 'toggle'])->name('user.toggle');
    Route::put('/user/language', [AuthController::class, 'updateLanguage'])->name('user.update.language');


    Route::get('/usuarios-tokens', [TokenController::class, 'index'])->name('tokens.index');
    Route::post('/usuarios-tokens', [TokenController::class, 'store'])->name('tokens.store');
    Route::delete('/usuarios-tokens/{id}', [TokenController::class, 'destroy'])->name('tokens.destroy');

    // ── Placeholders pendientes de implementar ─────────────────────────────────

    Route::get('/datos-bd', function () {
        return view('dashboard');
    })->name('datos-bd');

    // ── Proxy autenticado hacia Grafana (Auth Proxy) ───────────────────────────
    // El navegador nunca habla con Grafana directamente: toda petición pasa por
    // aquí y Laravel inyecta X-WEBAUTH-USER antes de reenviarla a Grafana.
    // Requiere que Grafana tenga serve_from_sub_path = true y [auth.proxy] activo.
    Route::any('/grafana', [GrafanaProxyController::class, 'proxy'])
        ->defaults('path', '');

    Route::any('/grafana/{path}', [GrafanaProxyController::class, 'proxy'])
        ->where('path', '.*');
});
