<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificacionesController extends Controller
{
    public function index(Request $request)
    {
        $usuario = auth()->user();
        $tipo    = $request->get('tipo', 'todas');

        $todas = $usuario->notifications()->latest()->get();

        $cuentaAlertas  = $todas->filter(fn($n) => in_array($n->data['tipo'] ?? '', ['firing', 'resolution']))->count();
        $cuentaInformes = $todas->filter(fn($n) => ($n->data['tipo'] ?? '') === 'informe')->count();

        $feed = collect();

        foreach ($todas as $notificacion) {
            $datos      = $notificacion->data;
            $tipoNotif  = $datos['tipo'] ?? '';

            if ($tipoNotif === 'firing' || $tipoNotif === 'resolution') {
                $activa = $tipoNotif === 'firing';
                $feed->push([
                    'tipo'        => 'alerta',
                    'subtipo'     => $tipoNotif,
                    'titulo'      => $datos['titulo']             ?? __('Alerta'),
                    'mensaje'     => $datos['mensaje']            ?? '',
                    'fecha'       => $notificacion->created_at,
                    'meta'        => $datos['nombre_dispositivo'] ?? '',
                    'canales'     => $datos['canales']            ?? [],
                    'url'         => $datos['url']                ?? route('alertas.historial'),
                    'claseIcono'  => $activa ? 'bi bi-exclamation-octagon-fill text-danger' : 'bi bi-check-circle-fill text-success',
                    'claseBadge'  => $activa ? 'bg-danger' : 'bg-success',
                    'textoBadge'  => $activa ? __('Alerta activa') : __('Resuelta'),
                ]);

            } elseif ($tipoNotif === 'informe') {
                $subtipo = strtolower($datos['subtipo'] ?? 'demanda');
                $feed->push([
                    'tipo'        => 'informe',
                    'subtipo'     => $subtipo,
                    'titulo'      => $datos['titulo']        ?? __('Informe'),
                    'mensaje'     => $datos['mensaje']       ?? '',
                    'fecha'       => $notificacion->created_at,
                    'meta'        => $datos['nombre_archivo'] ?? '',
                    'url'         => $datos['url']            ?? '#',
                    'claseIcono'  => 'bi bi-file-earmark-pdf-fill text-primary',
                    'claseBadge'  => 'bg-primary',
                    'textoBadge'  => $subtipo === 'programado' ? __('Programado') : __('Demanda'),
                ]);

            } elseif ($tipoNotif === 'reset_password' && $usuario->administrador) {
                $feed->push([
                    'tipo'        => 'reset_password',
                    'subtipo'     => 'reset_password',
                    'titulo'      => $datos['titulo']   ?? __('Solicitud de contraseña'),
                    'mensaje'     => $datos['mensaje']  ?? '',
                    'fecha'       => $notificacion->created_at,
                    'meta'        => '',
                    'url'         => $datos['url']      ?? route('usuarios.index'),
                    'claseIcono'  => 'bi bi-key-fill text-warning',
                    'claseBadge'  => 'bg-warning text-dark',
                    'textoBadge'  => __('Reset contraseña'),
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
        $noLeidas = $usuario->unreadNotifications->count();

        $porPagina = 20;
        $pagina    = LengthAwarePaginator::resolveCurrentPage();
        $feed      = new LengthAwarePaginator(
            $feed->forPage($pagina, $porPagina),
            $feed->count(),
            $porPagina,
            $pagina,
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
