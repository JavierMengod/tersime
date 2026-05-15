<?php

namespace App\Http\Controllers;

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
    private static array $timezones = [
        'Europe/Madrid'       => 'Europe/Madrid (ES)',
        'Europe/London'       => 'Europe/London (UK)',
        'Europe/Paris'        => 'Europe/Paris (FR)',
        'Europe/Berlin'       => 'Europe/Berlin (DE)',
        'America/New_York'    => 'America/New_York (US East)',
        'America/Chicago'     => 'America/Chicago (US Central)',
        'America/Denver'      => 'America/Denver (US Mountain)',
        'America/Los_Angeles' => 'America/Los_Angeles (US West)',
        'America/Sao_Paulo'   => 'America/Sao_Paulo (BR)',
        'Asia/Tokyo'          => 'Asia/Tokyo (JP)',
        'Asia/Shanghai'       => 'Asia/Shanghai (CN)',
        'UTC'                 => 'UTC',
    ];

    // ── Cuenta ─────────────────────────────────────────────────────────────────

    public function cuenta()
    {
        return view('configuracion.cuenta', [
            'timezones' => self::$timezones,
        ]);
    }

    public function updateCuenta(Request $request)
    {
        $user = auth()->user();

        // Preferencias
        if ($request->has('save_prefs')) {
            $data = $request->validate([
                'language' => 'required|in:es,en,fr',
                'theme'    => 'required|in:light,dark',
                'timezone' => 'required|string|in:' . implode(',', array_keys(self::$timezones)),
            ]);

            $user->language = $data['language'];
            $user->theme    = $data['theme'];
            $user->timezone = $data['timezone'];
            $user->save();

            return back()->with('success_prefs', 'Preferencias guardadas.');
        }

        // Contraseña
        if ($request->has('save_password')) {
            $request->validate([
                'current_password'      => 'required|string',
                'new_password'          => 'required|string|min:6|confirmed',
            ]);

            if (!Hash::check($request->current_password, $user->password)) {
                return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.'])
                             ->with('open_password', true);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            Log::info('[Config] Contraseña cambiada: ' . $user->name);
            return back()->with('success_password', 'Contraseña actualizada.');
        }

        return back();
    }

    // ── Sistema (admin) ────────────────────────────────────────────────────────

    public function sistema()
    {
        abort_unless(auth()->user()->admin, 403);

        $settings = [
            'alert_log_retention_days' => Setting::get('alert_log_retention_days', '90'),
            'report_retention_days'    => Setting::get('report_retention_days', '180'),
        ];

        $stats = [
            'alert_logs_total' => AlertLog::count(),
            'reports_total'    => Informe::count(),
        ];

        return view('configuracion.sistema', compact('settings', 'stats'));
    }

    public function updateSistema(Request $request)
    {
        abort_unless(auth()->user()->admin, 403);

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

    public function purgarAlertas(Request $request)
    {
        abort_unless(auth()->user()->admin, 403);

        $days     = (int) Setting::get('alert_log_retention_days', '90');
        $cutoff   = Carbon::now()->subDays($days);
        $deleted  = AlertLog::where('created_at', '<', $cutoff)->delete();

        Log::info("[Config] Purgadas {$deleted} alertas anteriores a {$cutoff->toDateString()}");
        return back()->with('success', "Purgados {$deleted} registros de alerta anteriores a {$days} días.");
    }

    public function purgarInformes(Request $request)
    {
        abort_unless(auth()->user()->admin, 403);

        $days   = (int) Setting::get('report_retention_days', '180');
        $cutoff = Carbon::now()->subDays($days);

        $informes = Informe::where('generated_at', '<', $cutoff)->get();
        $deleted  = 0;

        foreach ($informes as $informe) {
            if (!empty($informe->pdf_path)) {
                $relative = ltrim(preg_replace('#^public/#', '', ltrim($informe->pdf_path, '/')), '/');
                if (Storage::disk('public')->exists($relative)) {
                    Storage::disk('public')->delete($relative);
                } else {
                    $abs = $this->resolveAbsPath($informe->pdf_path);
                    if ($abs && is_file($abs)) {
                        @unlink($abs);
                    }
                }
            }
            $informe->delete();
            $deleted++;
        }

        Log::info("[Config] Purgados {$deleted} informes anteriores a {$cutoff->toDateString()}");
        return back()->with('success', "Purgados {$deleted} informes anteriores a {$days} días.");
    }

    // ── Conexiones (admin) ─────────────────────────────────────────────────────

    public function conexiones()
    {
        abort_unless(auth()->user()->admin, 403);

        $settings = [
            'influxdb_url'            => Setting::get('influxdb_url', env('INFLUXDB_URL', '')),
            'influxdb_org'            => Setting::get('influxdb_org', env('INFLUXDB_ORG', '')),
            'influxdb_bucket'         => Setting::get('influxdb_bucket', env('INFLUX_BUCKET', 'PINZAS')),
            'grafana_base_url'        => Setting::get('grafana_base_url', env('GRAFANA_BASE_URL', '')),
            'grafana_datasource_id'   => Setting::get('grafana_datasource_id', env('GRAFANA_DATASOURCE_ID', '3')),
            'grafana_renderer_url'    => Setting::get('grafana_renderer_url', env('GRAFANA_RENDERER_URL', '')),
            'predictor_url'           => Setting::get('predictor_url', env('PREDICTOR_URL', '')),
            'predictor_timeout'       => Setting::get('predictor_timeout', '120'),
            'predictor_default_hours' => Setting::get('predictor_default_hours', '24'),
            'openrouter_model'        => Setting::get('openrouter_model', env('OPENROUTER_MODEL', '')),
        ];

        $hasInfluxToken      = !empty(Setting::get('influxdb_token', env('INFLUXDB_TOKEN', '')));
        $hasGrafanaKey       = !empty(Setting::get('grafana_api_key', env('GRAFANA_API_KEY', '')));
        $hasOpenRouterKey    = !empty(Setting::get('openrouter_api_key', env('OPENROUTER_API_KEY', '')));

        return view('configuracion.conexiones', compact('settings', 'hasInfluxToken', 'hasGrafanaKey', 'hasOpenRouterKey'));
    }

    public function updateConexiones(Request $request)
    {
        abort_unless(auth()->user()->admin, 403);

        $data = $request->validate([
            'influxdb_url'            => 'required|string|max:500',
            'influxdb_org'            => 'required|string|max:255',
            'influxdb_bucket'         => 'required|string|max:255',
            'influxdb_token'          => 'nullable|string|max:1000',
            'grafana_base_url'        => 'required|string|max:500',
            'grafana_datasource_id'   => 'required|integer|min:1',
            'grafana_api_key'         => 'nullable|string|max:1000',
            'grafana_renderer_url'    => 'nullable|string|max:500',
            'predictor_url'           => 'nullable|string|max:500',
            'predictor_timeout'       => 'required|integer|min:5|max:600',
            'predictor_default_hours' => 'required|integer|min:1|max:168',
            'openrouter_api_key'      => 'nullable|string|max:1000',
            'openrouter_model'        => 'nullable|string|max:255',
        ]);

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

    public function logs(Request $request)
    {
        abort_unless(auth()->user()->admin, 403);

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
        abort_unless(auth()->user()->admin, 403);

        $logPath = storage_path('logs/laravel.log');
        if (is_file($logPath)) {
            file_put_contents($logPath, '');
        }

        Log::info('[Config] Log vaciado por: ' . auth()->user()->name);
        return back()->with('success', 'Log vaciado correctamente.');
    }

    public function downloadLog()
    {
        abort_unless(auth()->user()->admin, 403);

        $logPath = storage_path('logs/laravel.log');
        abort_unless(is_file($logPath), 404);

        return response()->download($logPath, 'laravel-' . now()->format('Ymd') . '.log');
    }

    private function tailFile(string $path, int $maxLines): array
    {
        $file  = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $total = $file->key();

        $start  = max(0, $total - $maxLines);
        $lines  = [];

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
