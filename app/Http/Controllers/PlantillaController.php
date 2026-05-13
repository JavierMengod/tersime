<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PlantillaController extends Controller
{
    public function create(Request $request, $canal)
    {
        return redirect()->back()->with('success', 'Plantilla guardada correctamente.');
    }

    public function destroy(Request $request, $canal, $id)
    {
        return redirect()->back()->with('success', 'Plantilla eliminada correctamente.');
    }
}
