<?php

namespace App\Http\Controllers;

use App\Models\Plantilla;
use Illuminate\Http\Request;

class PlantillaController extends Controller
{
    public function index()
    {
        $dispositivos = auth()->user()->dispositivos()->wherePivot('habilitado', 1)->get();
        $plantillas   = \App\Models\Plantilla::where('user_id', auth()->id())->get()->groupBy('canal');

        return view('alertas.plantillas', compact('dispositivos', 'plantillas'));
    }

    public function store(Request $request, $canal)
    {
        return $this->create($request, $canal);
    }

    public function create(Request $request, $canal)
    {
        $request->validate([
            'contenido' => 'required|string|max:2000',
        ]);

        if (!in_array($canal, ['telegram', 'email', 'discord'], true)) {
            return redirect()->back()->withErrors('Canal no válido.');
        }

        Plantilla::create([
            'user_id'   => auth()->id(),
            'canal'     => $canal,
            'contenido' => $request->input('contenido'),
        ]);

        return redirect()->back()->with('status', 'Plantilla guardada correctamente.');
    }

    public function destroy(Request $request, $canal, $id)
    {
        Plantilla::where('id', $id)
            ->where('user_id', auth()->id())
            ->delete();

        return redirect()->back()->with('status', 'Plantilla eliminada correctamente.');
    }
}
