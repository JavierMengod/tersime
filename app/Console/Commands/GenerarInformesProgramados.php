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

        $programaciones = ProgramacionInformes::with(['usuario', 'dispositivos'])
            ->where('activo', true)
            ->get()
            ->filter(fn($p) => $p->proximaEjecucion($ahora)->lte($ahora));

        if ($programaciones->isEmpty()) {
            $this->info('No hay informes programados vencidos.');
            return;
        }

        $this->info("Encolando {$programaciones->count()} programación(es)...");

        foreach ($programaciones as $programacion) {
            $usuario = $programacion->usuario;

            if (!$usuario) {
                Log::warning("[InformesProgramados] Programación {$programacion->id} sin usuario.");
                continue;
            }

            $dispositivos = $programacion->dispositivos;

            if ($dispositivos->isEmpty()) {
                Log::warning("[InformesProgramados] Programación {$programacion->id} sin dispositivos.");
                continue;
            }

            // Fix: límite de cola por usuario — evita saturar el worker con un único usuario.
            $pendientes = Informe::where('user_id', $usuario->id)
                ->whereIn('estado', ['pending', 'processing'])
                ->count();

            if ($pendientes >= 3) {
                $this->warn("  ↷ '{$programacion->nombre}' omitida: {$usuario->nombre} ya tiene {$pendientes} informes en cola.");
                Log::warning("[InformesProgramados] Cola llena para usuario {$usuario->id}", [
                    'programacion_id' => $programacion->id,
                    'pendientes'      => $pendientes,
                ]);
                continue;
            }

            // Atomic compare-and-swap: solo procede si ultima_ejecucion_en no cambió desde que lo leímos.
            // Evita doble despacho si dos instancias del cron se solapan.
            $actualizado = ProgramacionInformes::where('id', $programacion->id)
                ->where(function ($q) use ($programacion) {
                    if ($programacion->ultima_ejecucion_en) {
                        $q->where('ultima_ejecucion_en', $programacion->ultima_ejecucion_en);
                    } else {
                        $q->whereNull('ultima_ejecucion_en');
                    }
                })
                ->update(['ultima_ejecucion_en' => $ahora]);

            if (!$actualizado) {
                $this->line("  ↷ '{$programacion->nombre}' ya fue reclamada por otro proceso.");
                continue;
            }

            [$fechaDesde, $fechaHasta] = $this->calcularRango($ahora, $programacion);
            $idsDispositivos           = $dispositivos->pluck('id')->toArray();

            try {
                $informe = Informe::create([
                    'user_id'        => $usuario->id,
                    'tipo'           => 'Programado',
                    'periodo_from'   => Carbon::parse($fechaDesde)->toDateString(),
                    'periodo_to'     => $fechaHasta->toDateString(),
                    'telegram'       => $programacion->telegram,
                    'discord'        => $programacion->discord,
                    'correo'         => $programacion->correo,
                    'correo_destino' => $programacion->correo_destino,
                    'estado'         => 'pending',
                ]);

                $informe->dispositivos()->sync($idsDispositivos);

                GenerarInformeJob::dispatch(
                    $informe->id,
                    $usuario->id,
                    $idsDispositivos,
                    $fechaDesde,
                    $fechaHasta->toDateString(),
                    $programacion->telegram,
                    $programacion->correo,
                    $programacion->discord,
                    $programacion->correo_destino,
                );

                $this->info("  ✔ '{$programacion->nombre}' encolada (informe #{$informe->id}).");
                Log::info("[InformesProgramados] Job despachado", [
                    'programacion_id' => $programacion->id,
                    'informe_id'      => $informe->id,
                    'desde'           => $fechaDesde,
                    'hasta'           => $fechaHasta->toDateString(),
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
                return [$ahora->copy()->subHours($valor)->format('Y-m-d H:i:s'), $ahora->copy()];
        }
    }

    private function limpiarInformesAtascados(Carbon $ahora): void
    {
        $limite = $ahora->copy()->subMinutes(22);

        $conteo = Informe::where('estado', 'processing')
            ->where('updated_at', '<', $limite)
            ->update([
                'estado'        => 'failed',
                'mensaje_error' => 'El proceso de generación se interrumpió inesperadamente.',
            ]);

        if ($conteo > 0) {
            $this->warn("  ⚠ {$conteo} informe(s) atascado(s) marcado(s) como fallido(s).");
            Log::warning("[InformesProgramados] Informes atascados limpiados", ['count' => $conteo]);
        }
    }
}
