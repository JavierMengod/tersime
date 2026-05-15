<?php

namespace App\Http\Controllers;

use App\Models\AlertLog;
use App\Models\Informe;
use Illuminate\Http\Request;

class NotificacionesController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $tipo = $request->get('tipo', 'todas');

        $alertas  = AlertLog::where('user_id', $user->id)->latest()->get();
        $informes = Informe::where('user_id', $user->id)->latest('generated_at')->get();

        $feed = collect();

        foreach ($alertas as $alerta) {
            $subtipo = $alerta->type ?? 'info';
            $firing  = $subtipo === 'firing';
            $feed->push([
                'tipo'       => 'alerta',
                'subtipo'    => $subtipo,
                'titulo'     => $alerta->rule_name ?? __('Alerta'),
                'mensaje'    => $alerta->message ?? '',
                'fecha'      => $alerta->created_at,
                'meta'       => $alerta->device_name ?? '',
                'canales'    => $alerta->channelList(),
                'objeto'     => $alerta,
                'iconClass'  => $firing ? 'bi bi-exclamation-octagon-fill text-danger' : 'bi bi-check-circle-fill text-success',
                'badgeClass' => $firing ? 'bg-danger' : 'bg-success',
                'badgeText'  => $firing ? __('Alerta activa') : __('Resuelta'),
            ]);
        }

        foreach ($informes as $informe) {
            $subtipo = strtolower($informe->tipo ?? 'demanda');
            $feed->push([
                'tipo'       => 'informe',
                'subtipo'    => $subtipo,
                'titulo'     => __('Informe') . ' ' . ($informe->tipo ?? ''),
                'mensaje'    => __('Periodo') . ': ' . $informe->periodo_from . ' — ' . $informe->periodo_to,
                'fecha'      => $informe->generated_at ?? $informe->created_at,
                'meta'       => $informe->nombre_archivo ?? '',
                'objeto'     => $informe,
                'iconClass'  => 'bi bi-file-earmark-pdf-fill text-primary',
                'badgeClass' => 'bg-primary',
                'badgeText'  => $subtipo === 'programado' ? __('Programado') : __('Demanda'),
            ]);
        }

        if ($tipo === 'alertas') {
            $feed = $feed->filter(fn ($i) => $i['tipo'] === 'alerta');
        } elseif ($tipo === 'informes') {
            $feed = $feed->filter(fn ($i) => $i['tipo'] === 'informe');
        }

        $feed     = $feed->sortByDesc('fecha')->values();
        $noLeidas = $user->unreadNotifications->count();

        $dbNotificaciones = $user->notifications()->latest()->take(50)->get();

        return view('usuarios.notificaciones', compact(
            'alertas', 'informes', 'dbNotificaciones', 'tipo', 'feed', 'noLeidas'
        ));
    }

    public function read(string $id)
    {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return response()->noContent();
    }

    public function readAll()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back();
    }
}
