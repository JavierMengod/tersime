<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificacionesController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $tipo = $request->get('tipo', 'todas');

        $todas = $user->notifications()->latest()->get();

        $cuentaAlertas  = $todas->filter(fn($n) => in_array($n->data['tipo'] ?? '', ['firing', 'resolution']))->count();
        $cuentaInformes = $todas->filter(fn($n) => ($n->data['tipo'] ?? '') === 'informe')->count();

        $feed = collect();

        foreach ($todas as $notif) {
            $data  = $notif->data;
            $tipoN = $data['tipo'] ?? '';

            if ($tipoN === 'firing' || $tipoN === 'resolution') {
                $firing = $tipoN === 'firing';
                $feed->push([
                    'tipo'       => 'alerta',
                    'subtipo'    => $tipoN,
                    'titulo'     => $data['titulo']             ?? __('Alerta'),
                    'mensaje'    => $data['mensaje']            ?? '',
                    'fecha'      => $notif->created_at,
                    'meta'       => $data['nombre_dispositivo'] ?? '',
                    'canales'    => $data['canales']            ?? [],
                    'url'        => $data['url']                ?? route('alertas.historial'),
                    'iconClass'  => $firing ? 'bi bi-exclamation-octagon-fill text-danger' : 'bi bi-check-circle-fill text-success',
                    'badgeClass' => $firing ? 'bg-danger' : 'bg-success',
                    'badgeText'  => $firing ? __('Alerta activa') : __('Resuelta'),
                ]);

            } elseif ($tipoN === 'informe') {
                $subtipo = strtolower($data['subtipo'] ?? 'demanda');
                $feed->push([
                    'tipo'       => 'informe',
                    'subtipo'    => $subtipo,
                    'titulo'     => $data['titulo']        ?? __('Informe'),
                    'mensaje'    => $data['mensaje']       ?? '',
                    'fecha'      => $notif->created_at,
                    'meta'       => $data['nombre_archivo'] ?? '',
                    'url'        => $data['url']            ?? '#',
                    'iconClass'  => 'bi bi-file-earmark-pdf-fill text-primary',
                    'badgeClass' => 'bg-primary',
                    'badgeText'  => $subtipo === 'programado' ? __('Programado') : __('Demanda'),
                ]);

            } elseif ($tipoN === 'reset_password' && $user->admin) {
                $feed->push([
                    'tipo'       => 'reset_password',
                    'subtipo'    => 'reset_password',
                    'titulo'     => $data['titulo']   ?? __('Solicitud de contraseña'),
                    'mensaje'    => $data['mensaje']  ?? '',
                    'fecha'      => $notif->created_at,
                    'meta'       => '',
                    'url'        => $data['url']      ?? route('usuarios.index'),
                    'iconClass'  => 'bi bi-key-fill text-warning',
                    'badgeClass' => 'bg-warning text-dark',
                    'badgeText'  => __('Reset contraseña'),
                ]);
            }
        }

        if ($tipo === 'alertas') {
            $feed = $feed->filter(fn($i) => $i['tipo'] === 'alerta');
        } elseif ($tipo === 'informes') {
            $feed = $feed->filter(fn($i) => $i['tipo'] === 'informe');
        } elseif ($tipo === 'reset_password') {
            $feed = $feed->filter(fn($i) => $i['tipo'] === 'reset_password');
        }

        $feed     = $feed->values();
        $noLeidas = $user->unreadNotifications->count();

        $perPage = 20;
        $page    = LengthAwarePaginator::resolveCurrentPage();
        $feed    = new LengthAwarePaginator(
            $feed->forPage($page, $perPage),
            $feed->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        return view('usuarios.notificaciones', compact(
            'tipo', 'feed', 'noLeidas', 'cuentaAlertas', 'cuentaInformes'
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
