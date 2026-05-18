<?php

namespace App\Console\Commands;

use App\Jobs\GenerarInformeJob;
use App\Models\Informe;
use App\Models\ProgramacionInformes;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerarInformesProgramados extends Command
{
    protected $signature   = 'informes:programados';
    protected $description = 'Encola los informes programados que están vencidos y limpia bloqueados';

    public function handle(): void
    {
        $ahora = Carbon::now();

        $this->limpiarInformesAtascados($ahora);

        $programaciones = ProgramacionInformes::with(['user', 'dispositivos'])
            ->where('activo', true)
            ->get()
            ->filter(fn($p) => !$p->last_run_at || $p->proximaEjecucion()->lte($ahora));

        if ($programaciones->isEmpty()) {
            $this->info('No hay informes programados vencidos.');
            return;
        }

        $this->info("Encolando {$programaciones->count()} programación(es)...");

        foreach ($programaciones as $programacion) {
            $user = $programacion->user;

            if (!$user) {
                Log::warning("[InformesProgramados] Programación {$programacion->id} sin usuario.");
                continue;
            }

            $dispositivos = $programacion->dispositivos;

            if ($dispositivos->isEmpty()) {
                Log::warning("[InformesProgramados] Programación {$programacion->id} sin dispositivos.");
                continue;
            }

            // Atomic compare-and-swap: solo procede si last_run_at no cambió desde que lo leímos.
            // Evita doble despacho si dos instancias del cron se solapan.
            $updated = ProgramacionInformes::where('id', $programacion->id)
                ->where(function ($q) use ($programacion) {
                    if ($programacion->last_run_at) {
                        $q->where('last_run_at', $programacion->last_run_at);
                    } else {
                        $q->whereNull('last_run_at');
                    }
                })
                ->update(['last_run_at' => $ahora]);

            if (!$updated) {
                $this->line("  ↷ '{$programacion->nombre}' ya fue reclamada por otro proceso.");
                continue;
            }

            [$fromDate, $toDate] = $this->calcularRango($ahora, $programacion);
            $dispositivosIds     = $dispositivos->pluck('id')->toArray();

            try {
                $informe = Informe::create([
                    'user_id'        => $user->id,
                    'tipo'           => 'Programado',
                    'periodo_from'   => Carbon::parse($fromDate)->toDateString(),
                    'periodo_to'     => $toDate->toDateString(),
                    'telegram'       => $programacion->telegram,
                    'discord'        => $programacion->discord,
                    'correo'         => $programacion->correo,
                    'correo_destino' => $programacion->correo_destino,
                    'status'         => 'pending',
                ]);

                $informe->dispositivos()->sync($dispositivosIds);

                GenerarInformeJob::dispatch(
                    $informe->id,
                    $user->id,
                    $dispositivosIds,
                    $fromDate,
                    $toDate->toDateString(),
                    $programacion->telegram,
                    $programacion->correo,
                    $programacion->discord,
                    $programacion->correo_destino,
                );

                $this->info("  ✔ '{$programacion->nombre}' encolada (informe #{$informe->id}).");
                Log::info("[InformesProgramados] Job despachado", [
                    'programacion_id' => $programacion->id,
                    'informe_id'      => $informe->id,
                    'from'            => $fromDate,
                    'to'              => $toDate->toDateString(),
                ]);
            } catch (\Throwable $e) {
                $this->error("  ✘ Error en '{$programacion->nombre}': " . $e->getMessage());
                Log::error("[InformesProgramados] Error encolando programación {$programacion->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Proceso completado.');
    }

    /**
     * Devuelve [fromDate string, toDate Carbon].
     * Para periodos en horas, fromDate incluye hora exacta (datetime string).
     * Para días/meses, fromDate es fecha pura (Y-m-d) para cubrir el día completo.
     */
    private function calcularRango(Carbon $ahora, ProgramacionInformes $p): array
    {
        $valor = (int) ($p->valor_periodo ?? 1);
        $tipo  = $p->tipo_periodo ?? 'horas';

        switch ($tipo) {
            case 'meses':
                return [$ahora->copy()->subMonths($valor)->toDateString(), $ahora->copy()];
            case 'dias':
                return [$ahora->copy()->subDays($valor)->toDateString(), $ahora->copy()];
            default:
                // Devuelve datetime completo para que el servicio respete la hora exacta
                return [$ahora->copy()->subHours($valor)->format('Y-m-d H:i:s'), $ahora->copy()];
        }
    }

    /**
     * Marca como 'failed' los informes que llevan más de 30 minutos en 'processing'.
     * Protege contra workers muertos (SIGKILL, OOM) que dejan informes bloqueados.
     */
    private function limpiarInformesAtascados(Carbon $ahora): void
    {
        $limite = $ahora->copy()->subMinutes(22);

        $count = Informe::where('status', 'processing')
            ->where('updated_at', '<', $limite)
            ->update([
                'status'        => 'failed',
                'error_message' => 'El proceso de generación se interrumpió inesperadamente.',
            ]);

        if ($count > 0) {
            $this->warn("  ⚠ {$count} informe(s) atascado(s) marcado(s) como fallido(s).");
            Log::warning("[InformesProgramados] Informes atascados limpiados", ['count' => $count]);
        }
    }
}
