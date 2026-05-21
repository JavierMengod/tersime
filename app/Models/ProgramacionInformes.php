<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramacionInformes extends Model
{
    use HasFactory;

    protected $table = 'programacion_informes';

    protected $fillable = [
        'user_id',
        'nombre',
        'tipo_periodo',
        'valor_periodo',
        'hora_inicio',
        'telegram',
        'discord',
        'correo',
        'correo_destino',
        'activo',
        'last_run_at',
    ];

    protected $casts = [
        'last_run_at'   => 'datetime',
        'activo'        => 'boolean',
        'telegram'      => 'boolean',
        'discord'       => 'boolean',
        'correo'        => 'boolean',
        'valor_periodo' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dispositivos()
    {
        return $this->belongsToMany(
            Dispositivo::class,
            'dispositivo_programacion_informe',
            'programacion_informe_id',
            'dispositivo_id'
        )->withTimestamps();
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function formatearFrecuencia(): string
    {
        $v    = (int) ($this->valor_periodo ?? 1);
        $tipo = $this->tipo_periodo ?? 'horas';

        if ($tipo === 'meses') {
            return $v . ($v === 1 ? ' mes' : ' meses');
        }
        if ($tipo === 'dias') {
            return $v . ($v === 1 ? ' día' : ' días');
        }
        return $v . ($v === 1 ? ' hora' : ' horas');
    }

    public function proximaEjecucion(?Carbon $ahora = null): Carbon
    {
        $ahora = $ahora ?? Carbon::now();
        $valor = (int) ($this->valor_periodo ?? 1);
        $tipo  = $this->tipo_periodo ?? 'horas';

        if (!$this->last_run_at) {
            // Primera ejecución: para horas arranca inmediatamente.
            // Para días/meses con hora_inicio calcula la próxima ventana real.
            if ($tipo === 'horas') {
                return $ahora->copy();
            }
            if ($this->hora_inicio) {
                return $this->siguienteVentana($ahora, $tipo, $valor);
            }
            return $ahora->copy();
        }

        if ($tipo === 'horas') {
            return $this->last_run_at->copy()->addHours($valor);
        }

        $next = $tipo === 'meses'
            ? $this->last_run_at->copy()->addMonths($valor)
            : $this->last_run_at->copy()->addDays($valor);

        if ($this->hora_inicio) {
            [$h, $m] = array_map('intval', explode(':', $this->hora_inicio));
            $next->setTime($h, $m, 0);
        }

        return $next;
    }

    private function siguienteVentana(Carbon $ahora, string $tipo, int $valor): Carbon
    {
        [$h, $m] = array_map('intval', explode(':', $this->hora_inicio));
        $hoy = $ahora->copy()->setTime($h, $m, 0);

        // Si la hora todavía no ha llegado hoy, la primera ejecución es hoy
        if ($hoy->greaterThan($ahora)) {
            return $hoy;
        }

        // Ya pasó hoy: esperar al siguiente período
        return $tipo === 'meses' ? $hoy->addMonths($valor) : $hoy->addDays($valor);
    }
}
