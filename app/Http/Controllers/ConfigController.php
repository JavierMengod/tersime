<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Http\Requests\UserRequest;
use App\Models\AlertLog;
use App\Models\Informe;
use App\Models\Setting;
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
        $adminOnly = ['sistema', 'updateSistema', 'purgarAlertas', 'purgarInformes',
                      'conexiones', 'updateConexiones', 'logs', 'clearLogs', 'downloadLog'];

        $this->middleware(function ($request, $next) {
            abort_unless(auth()->user() && auth()->user()->admin, 403);
            return $next($request);
        })->only($adminOnly);
    }

    // ── Cuenta ─────────────────────────────────────────────────────────────────

    public function cuenta()
    {
        return view('configuracion.cuenta', [
            'timezones' => UserRequest::timezones(),
        ]);
    }

    public function updatePreferencias(UpdatePreferencesRequest $request)
    {
        auth()->user()->fill($request->validated())->save();

        return back()->with('success_prefs', 'Preferencias guardadas.');
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.'])
                         ->with('open_password', true);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        Log::info('[Config] Contraseña cambiada: ' . $user->name);
        return back()->with('success_password', 'Contraseña actualizada.');
    }

    // ── Sistema (admin) ────────────────────────────────────────────────────────

    public function sistema()
    {
        $settingKeys = ['alert_log_retention_days', 'report_retention_days'];
        $saved       = Setting::whereIn('key', $settingKeys)->get()->keyBy('key');

        $settings = [
            'alert_log_retention_days' => optional($saved->get('alert_log_retention_days'))->value ?? self::DEFAULT_ALERT_RETENTION_DAYS,
            'report_retention_days'    => optional($saved->get('report_retention_days'))->value    ?? self::DEFAULT_REPORT_RETENTION_DAYS,
        ];

        $stats = [
            'alert_logs_total' => AlertLog::count(),
            'reports_total'    => Informe::count(),
        ];

        return view('configuracion.sistema', compact('settings', 'stats'));
    }

    public function updateSistema(Request $request)
    {
        $data = $request->validate([
            'alert_log_retention_days' => 'required|integer|min:1|max:3650',
            'report_retention_days'    => 'required|integer|min:1|max:3650',
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        Log::info('[Config] Configuración del sistema actualizada por: ' . auth()->user()->name);
        return back()->with('success', 'Configuración guardada.');
    }

    public function purgarAlertas()
    {
        $days    = (int) Setting::get('alert_log_retention_days', self::DEFAULT_ALERT_RETENTION_DAYS);
        $cutoff  = Carbon::now()->subDays($days);
        $deleted = AlertLog::where('created_at', '<', $cutoff)->delete();

        Log::info("[Config] Purgadas {$deleted} alertas anteriores a {$cutoff->toDateString()}");
        return back()->with('success', "Purgados {$deleted} registros de alerta anteriores a {$days} días.");
    }

    public function purgarInformes()
    {
        $days    = (int) Setting::get('report_retention_days', self::DEFAULT_REPORT_RETENTION_DAYS);
        $cutoff  = Carbon::now()->subDays($days);
        $deleted = 0;

        Informe::where('generated_at', '<', $cutoff)->chunk(100, function ($informes) use (&$deleted) {
            foreach ($informes as $informe) {
                $this->deleteInformePdf($informe->pdf_path);
                $informe->delete();
                $deleted++;
            }
        });

        Log::info("[Config] Purgados {$deleted} informes anteriores a {$cutoff->toDateString()}");
        return back()->with('success', "Purgados {$deleted} informes anteriores a {$days} días.");
    }

    // ── Conexiones (admin) ─────────────────────────────────────────────────────

    public function conexiones()
    {
        $keys  = ['influxdb_url', 'influxdb_org', 'influxdb_bucket', 'influxdb_token',
                  'grafana_base_url', 'grafana_datasource_id', 'grafana_renderer_url', 'grafana_api_key',
                  'predictor_url', 'predictor_timeout', 'predictor_default_hours',
                  'openrouter_model', 'openrouter_api_key'];
        $saved = Setting::whereIn('key', $keys)->get()->keyBy('key');
        $s     = fn(string $key, $default = '') => optional($saved->get($key))->value ?? $default;

        $settings = [
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

        $hasInfluxToken   = !empty($s('influxdb_token',      env('INFLUXDB_TOKEN', '')));
        $hasGrafanaKey    = !empty($s('grafana_api_key',      env('GRAFANA_API_KEY', '')));
        $hasOpenRouterKey = !empty($s('openrouter_api_key',   env('OPENROUTER_API_KEY', '')));

        return view('configuracion.conexiones', compact('settings', 'hasInfluxToken', 'hasGrafanaKey', 'hasOpenRouterKey'));
    }

    public function updateConexiones(Request $request)
    {
        $data = $request->validate([
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

        // Block SSRF: service URLs must not resolve to private/reserved IP ranges.
        // Even for admin users, pointing these at internal metadata services is dangerous.
        $urlFields = ['influxdb_url', 'grafana_base_url', 'grafana_renderer_url', 'predictor_url'];
        foreach ($urlFields as $field) {
            if (empty($data[$field])) continue;
            $host = parse_url($data[$field], PHP_URL_HOST);
            if (!$host) continue;
            $ip = gethostbyname($host);
            if (filter_var($ip, FILTER_VALIDATE_IP) &&
                !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return back()->withErrors([$field => "La URL apunta a una dirección de red privada o reservada."]);
            }
        }

        $sensitive = ['influxdb_token', 'grafana_api_key', 'openrouter_api_key'];

        foreach ($data as $key => $value) {
            if (in_array($key, $sensitive, true)) {
                if (!empty($value)) {
                    Setting::set($key, $value);
                }
            } else {
                Setting::set($key, $value ?? '');
            }
        }

        Log::info('[Config] Conexiones actualizadas por: ' . auth()->user()->name);
        return back()->with('success', 'Conexiones guardadas.');
    }

    // ── Logs ───────────────────────────────────────────────────────────────────

    public function logs()
    {
        $logPath = storage_path('logs/laravel.log');
        $entries = [];

        if (is_file($logPath)) {
            $lines   = $this->tailFile($logPath, 2000);
            $entries = $this->parseLogLines($lines);
        }

        $logSize = is_file($logPath) ? round(filesize($logPath) / 1024, 1) : 0;

        return view('configuracion.logs', compact('entries', 'logSize'));
    }

    public function clearLogs()
    {
        $logPath = storage_path('logs/laravel.log');
        if (is_file($logPath)) {
            file_put_contents($logPath, '');
        }

        Log::info('[Config] Log vaciado por: ' . auth()->user()->name);
        return back()->with('success', 'Log vaciado correctamente.');
    }

    public function downloadLog()
    {
        $logPath = storage_path('logs/laravel.log');
        abort_unless(is_file($logPath), 404);

        return response()->download($logPath, 'laravel-' . now()->format('Ymd') . '.log');
    }

    // ── Privados ───────────────────────────────────────────────────────────────

    private function deleteInformePdf(?string $pdfPath): void
    {
        if (empty($pdfPath)) {
            return;
        }

        $relative = ltrim(preg_replace('#^public/#', '', ltrim($pdfPath, '/')), '/');
        if (Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
            return;
        }

        $abs = $this->resolveAbsPath($pdfPath);
        if ($abs && is_file($abs)) {
            unlink($abs);
        }
    }

    private function tailFile(string $path, int $maxLines): array
    {
        $file  = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $total = $file->key();

        $start = max(0, $total - $maxLines);
        $lines = [];

        $file->seek($start);
        while (!$file->eof()) {
            $line = $file->fgets();
            if ($line !== false && $line !== '') {
                $lines[] = rtrim($line);
            }
        }

        return array_reverse($lines);
    }

    private function parseLogLines(array $lines): array
    {
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+)$/', $line, $m)) {
                if ($current) {
                    $entries[] = $current;
                }
                $current = [
                    'datetime' => $m[1],
                    'level'    => strtoupper($m[2]),
                    'message'  => $m[3],
                    'extra'    => [],
                ];
            } elseif ($current && trim($line) !== '') {
                $current['extra'][] = $line;
            }
        }

        if ($current) {
            $entries[] = $current;
        }

        return $entries;
    }

    private function resolveAbsPath(?string $path): ?string
    {
        if (empty($path)) return null;
        if (preg_match('/^(\/|[A-Za-z]:\\\\)/', $path)) return $path;
        $rel = preg_replace('#^(storage/app/public/|public/|storage/)#', '', ltrim($path, '/'));
        return storage_path('app/public/' . $rel);
    }
}
