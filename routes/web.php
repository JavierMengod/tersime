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
use App\Models\Plantilla;
use App\Http\Controllers\InformeController;
use App\Http\Controllers\InformeRegistroController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\GrafanaController;
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
    Route::get('/configuracion-iu', function () {
        return view('configuracion.iu');
    })->name('configuracion-iu');

    Route::get('/configuracion-registro', function () {
        return view('configuracion.registro');
    })->name('configuracion-registro');

    Route::get('/configuracion-limpieza', function () {
        return view('configuracion.limpieza');
    })->name('configuracion-limpieza');

    Route::get('/configuracion-otras', function () {
        return view('configuracion.otras');
    })->name('configuracion-otras');

    Route::post('/config/update', [ConfigController::class, 'update'])->name('config.update');

    // ── Monitorización ─────────────────────────────────────────────────────────
    Route::get('/monitorizacion-tiempo-real', function () {
        $dispositivos   = auth()->user()->dispositivos()->wherePivot('habilitado', 1)->get();
        $grafanaBaseUrl = config('app.grafana_base_url');
        return view('monitorizacion.tiempo-real', compact('dispositivos', 'grafanaBaseUrl'));
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
        $reglas       = auth()->user()->rules()->with('dispositivos')->get();
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

    // Medios de notificación
    Route::put('settings/notifications/{type}', [NotificationMethodController::class, 'update'])
        ->where('type', 'telegram|email|discord')
        ->name('notifications.update');

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

    // Registros de informes generados (PDF bajo demanda)
    Route::get('informes-registros', [InformeRegistroController::class, 'index'])
        ->name('informes.registros.index');

    Route::get('/informes/registros/{registro}/download', [InformeRegistroController::class, 'download'])
        ->name('informes.registros.download');

    Route::delete('/informes/registros/{registro}', [InformeRegistroController::class, 'destroy'])
        ->name('informes.registros.destroy');

    // Programaciones de informes
    Route::post('/programaciones', [ProgramacionInformesController::class, 'store'])
        ->name('programaciones.store');

    Route::put('/programacionesU/{programacionInformes}', [ProgramacionInformesController::class, 'update'])
        ->name('programaciones.update');

    Route::delete('/programaciones/{programacionInformes}', [ProgramacionInformesController::class, 'destroy'])
        ->name('programaciones.destroy');

    // ── Usuarios ───────────────────────────────────────────────────────────────
    Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios');
    Route::post('/user/create', [UserController::class, 'create'])->name('user.create');
    Route::put('/user/language', [AuthController::class, 'updateLanguage'])->name('user.update.language');

    Route::get('/usuarios-grupos', function () {
        return view('usuarios.grupos');
    })->name('usuarios-grupos');

    Route::get('/usuarios-tokens', [TokenController::class, 'index'])->name('tokens.index');
    Route::post('/usuarios-tokens', [TokenController::class, 'store'])->name('tokens.store');
    Route::delete('/usuarios-tokens/{id}', [TokenController::class, 'destroy'])->name('tokens.destroy');

    // ── Placeholders pendientes de implementar ─────────────────────────────────
    Route::get('/datos-grupos-dispositivos', function () {
        return view('dashboard');
    })->name('datos-grupos-dispositivos');

    Route::get('/datos-bd', function () {
        return view('dashboard');
    })->name('datos-bd');
});
