<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAjustesRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Http\Requests\UserRequest;
use App\Models\RegistroAlerta;
use App\Models\Informe;
use App\Models\Ajuste;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ConfigController extends Controller
{
    private const DEFAULT_ALERT_RETENTION_DAYS  = '90';
    private const DEFAULT_REPORT_RETENTION_DAYS = '180';

    public function __construct()
    {
        $soloAdmin = ['sistema', 'updateSistema', 'purgarAlertas', 'purgarInformes',
                      'conexiones', 'updateConexiones', 'logs', 'clearLogs', 'downloadLog'];

        $this->middleware(function ($request, $next) {
            abort_unless(auth()->user() && auth()->user()->admin, 403);
            return $next($request);
        })->only($soloAdmin);
    }

    // ── Perfil ─────────────────────────────────────────────────────────────────

    public function perfil()
    {
        return view('configuracion.perfil');
    }

    public function updatePreferencias(UpdatePreferencesRequest $request)
    {
        auth()->user()->fill($request->validated())->save();

        return back()->with('success_perfil', 'Perfil actualizado.');
    }

    // ── Ajustes ────────────────────────────────────────────────────────────────

    public function ajustes()
    {
        return view('configuracion.ajustes', [
            'zonaHoraria' => UserRequest::timezones(),
        ]);
    }

    public function updateAjustes(UpdateAjustesRequest $request)
    {
        auth()->user()->fill($request->validated())->save();

        return back()->with('success_ajustes', 'Ajustes guardados.');
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $usuario = auth()->user();

        if (!Hash::check($request->current_password, $usuario->password)) {
            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.'])
                         ->with('open_password', true);
        }

        $usuario->password = Hash::make($request->new_password);
        $usuario->save();

        Log::info('[Config] Contraseña cambiada: ' . $usuario->name);
        return back()->with('success_password', 'Contraseña actualizada.');
    }

    // ── Sistema (admin) ────────────────────────────────────────────────────────

    public function sistema()
    {
        $claves    = ['alert_log_retention_days', 'report_retention_days'];
        $guardados = Ajuste::whereIn('key', $claves)->get()->keyBy('key');

        $configuracion = [
            'alert_log_retention_days' => optional($guardados->get('alert_log_retention_days'))->value ?? self::DEFAULT_ALERT_RETENTION_DAYS,
            'report_retention_days'    => optional($guardados->get('report_retention_days'))->value    ?? self::DEFAULT_REPORT_RETENTION_DAYS,
        ];

        $estadisticas = [
            'alert_logs_total' => RegistroAlerta::count(),
            'reports_total'    => Informe::count(),
        ];

        return view('configuracion.sistema', compact('configuracion', 'estadisticas'));
    }

    public function updateSistema(Request $request)
    {
        $datos = $request->validate([
            'alert_log_retention_days' => 'required|integer|min:1|max:3650',
            'report_retention_days'    => 'required|integer|min:1|max:3650',
        ]);

        foreach ($datos as $clave => $valor) {
            Ajuste::set($clave, $valor ?? '');
        }

        Log::info('[Config] Configuración del sistema actualizada por: ' . auth()->user()->name);
        return back()->with('success', 'Configuración guardada.');
    }

    public function purgarAlertas()
    {
        $dias        = (int) Ajuste::get('alert_log_retention_days', self::DEFAULT_ALERT_RETENTION_DAYS);
        $fechaCorte  = Carbon::now()->subDays($dias);
        $eliminados  = RegistroAlerta::where('created_at', '<', $fechaCorte)->delete();

        Log::info("[Config] Purgadas {$eliminados} alertas anteriores a {$fechaCorte->toDateString()}");
        return back()->with('success', "Purgados {$eliminados} registros de alerta anteriores a {$dias} días.");
    }

    public function purgarInformes()
    {
        $dias       = (int) Ajuste::get('report_retention_days', self::DEFAULT_REPORT_RETENTION_DAYS);
        $fechaCorte = Carbon::now()->subDays($dias);
        $eliminados = 0;

        Informe::where('generated_at', '<', $fechaCorte)->chunk(100, function ($informes) use (&$eliminados) {
            foreach ($informes as $informe) {
                $this->eliminarPdfInforme($informe->pdf_path);
                $informe->delete();
                $eliminados++;
            }
        });

        Log::info("[Config] Purgados {$eliminados} informes anteriores a {$fechaCorte->toDateString()}");
        return back()->with('success', "Purgados {$eliminados} informes anteriores a {$dias} días.");
    }

    // ── Conexiones (admin) ─────────────────────────────────────────────────────

    public function conexiones()
    {
        $claves    = ['influxdb_url', 'influxdb_org', 'influxdb_bucket', 'influxdb_token',
                      'grafana_base_url', 'grafana_datasource_id', 'grafana_renderer_url', 'grafana_api_key',
                      'predictor_url', 'predictor_timeout', 'predictor_default_hours',
                      'openrouter_model', 'openrouter_api_key'];
        $guardados = Ajuste::whereIn('key', $claves)->get()->keyBy('key');
        $s         = fn(string $clave, $defecto = '') => optional($guardados->get($clave))->value ?? $defecto;

        $configuracion = [
            'influxdb_url'            => $s('influxdb_url',            env('INFLUXDB_URL', '')),
            'influxdb_org'            => $s('influxdb_org',            env('INFLUXDB_ORG', '')),
            'influxdb_bucket'         => $s('influxdb_bucket',         env('INFLUX_BUCKET', 'PINZAS')),
            'grafana_base_url'        => $s('grafana_base_url',        env('GRAFANA_BASE_URL', '')),
            'grafana_datasource_id'   => $s('grafana_datasource_id',   env('GRAFANA_DATASOURCE_ID', '3')),
            'grafana_renderer_url'    => $s('grafana_renderer_url',    env('GRAFANA_RENDERER_URL', '')),
            'predictor_url'           => $s('predictor_url',           env('PREDICTOR_URL', '')),
            'predictor_timeout'       => $s('predictor_timeout',       '120'),
            'predictor_default_hours' => $s('predictor_default_hours', '24'),
            'openrouter_model'        => $s('openrouter_model',        env('OPENROUTER_MODEL', '')),
        ];

        $tieneInfluxToken   = !empty($s('influxdb_token',      env('INFLUXDB_TOKEN', '')));
        $tieneGrafanaKey    = !empty($s('grafana_api_key',      env('GRAFANA_API_KEY', '')));
        $tieneOpenRouterKey = !empty($s('openrouter_api_key',   env('OPENROUTER_API_KEY', '')));

        return view('configuracion.conexiones', compact('configuracion', 'tieneInfluxToken', 'tieneGrafanaKey', 'tieneOpenRouterKey'));
    }

    public function updateConexiones(Request $request)
    {
        $datos = $request->validate([
            'influxdb_url'            => 'required|url|max:500',
            'influxdb_org'            => 'required|string|max:255',
            'influxdb_bucket'         => 'required|string|max:255',
            'influxdb_token'          => 'nullable|string|max:1000',
            'grafana_base_url'        => 'required|url|max:500',
            'grafana_datasource_id'   => 'required|integer|min:1',
            'grafana_api_key'         => 'nullable|string|max:1000',
            'grafana_renderer_url'    => 'nullable|url|max:500',
            'predictor_url'           => 'nullable|url|max:500',
            'predictor_timeout'       => 'required|integer|min:5|max:600',
            'predictor_default_hours' => 'required|integer|min:1|max:168',
            'openrouter_api_key'      => 'nullable|string|max:1000',
            'openrouter_model'        => 'nullable|string|max:255',
        ]);

        // Block SSRF: only validate URLs that are new or changed.
        // Already-stored URLs were checked when first saved; re-checking them would
        // block legitimate saves when the admin just wants to update an unrelated field.
        $camposUrl = ['influxdb_url', 'grafana_base_url', 'grafana_renderer_url', 'predictor_url'];
        foreach ($camposUrl as $campo) {
            if (empty($datos[$campo])) continue;
            if ($datos[$campo] === Ajuste::get($campo)) continue;
            $host = parse_url($datos[$campo], PHP_URL_HOST);
            if (!$host) continue;
            $ip = gethostbyname($host);
            if (filter_var($ip, FILTER_VALIDATE_IP) &&
                !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return back()->withErrors([$campo => "La URL apunta a una dirección de red privada o reservada."]);
            }
        }

        $sensibles = ['influxdb_token', 'grafana_api_key', 'openrouter_api_key'];

        foreach ($datos as $clave => $valor) {
            if (in_array($clave, $sensibles, true)) {
                if (!empty($valor)) {
                    Ajuste::set($clave, $valor);
                }
            } else {
                Ajuste::set($clave, $valor ?? '');
            }
        }

        Log::info('[Config] Conexiones actualizadas por: ' . auth()->user()->name);
        return back()->with('success', 'Conexiones guardadas.');
    }

    // ── Logs ───────────────────────────────────────────────────────────────────

    public function logs()
    {
        $rutaLog  = storage_path('logs/laravel.log');
        $entradas = [];

        if (is_file($rutaLog)) {
            $lineas   = $this->leerUltimasLineas($rutaLog, 2000);
            $entradas = $this->parsearLineasLog($lineas);
        }

        $tamanoLog = is_file($rutaLog) ? round(filesize($rutaLog) / 1024, 1) : 0;

        return view('configuracion.logs', compact('entradas', 'tamanoLog'));
    }

    public function clearLogs()
    {
        $rutaLog = storage_path('logs/laravel.log');
        if (is_file($rutaLog)) {
            file_put_contents($rutaLog, '');
        }

        Log::info('[Config] Log vaciado por: ' . auth()->user()->name);
        return back()->with('success', 'Log vaciado correctamente.');
    }

    public function downloadLog()
    {
        $rutaLog = storage_path('logs/laravel.log');
        abort_unless(is_file($rutaLog), 404);

        return response()->download($rutaLog, 'laravel-' . now()->format('Ymd') . '.log');
    }

    // ── Privados ───────────────────────────────────────────────────────────────

    private function eliminarPdfInforme(?string $rutaPdf): void
    {
        if (empty($rutaPdf)) {
            return;
        }

        $relativa = ltrim(preg_replace('#^public/#', '', ltrim($rutaPdf, '/')), '/');
        if (Storage::disk('public')->exists($relativa)) {
            Storage::disk('public')->delete($relativa);
            return;
        }

        $absoluta = $this->resolverRutaAbsoluta($rutaPdf);
        if ($absoluta && is_file($absoluta)) {
            unlink($absoluta);
        }
    }

    private function leerUltimasLineas(string $ruta, int $maxLineas): array
    {
        $archivo = new \SplFileObject($ruta, 'r');
        $archivo->seek(PHP_INT_MAX);
        $total = $archivo->key();

        $inicio = max(0, $total - $maxLineas);
        $lineas = [];

        $archivo->seek($inicio);
        while (!$archivo->eof()) {
            $linea = $archivo->fgets();
            if ($linea !== false && $linea !== '') {
                $lineas[] = rtrim($linea);
            }
        }

        return array_reverse($lineas);
    }

    private function parsearLineasLog(array $lineas): array
    {
        $entradas = [];
        $actual   = null;

        foreach ($lineas as $linea) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+)$/', $linea, $m)) {
                if ($actual) {
                    $entradas[] = $actual;
                }
                $actual = [
                    'datetime' => $m[1],
                    'level'    => strtoupper($m[2]),
                    'message'  => $m[3],
                    'extra'    => [],
                ];
            } elseif ($actual && trim($linea) !== '') {
                $actual['extra'][] = $linea;
            }
        }

        if ($actual) {
            $entradas[] = $actual;
        }

        return $entradas;
    }

    private function resolverRutaAbsoluta(?string $ruta): ?string
    {
        if (empty($ruta)) return null;
        if (preg_match('/^(\/|[A-Za-z]:\\\\)/', $ruta)) return $ruta;
        $relativa = preg_replace('#^(storage/app/public/|public/|storage/)#', '', ltrim($ruta, '/'));
        return storage_path('app/public/' . $relativa);
    }
}
