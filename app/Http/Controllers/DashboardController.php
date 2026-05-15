<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        $dispositivos = auth()->user()->dispositivos()->wherePivot('habilitado', 1)->get();

        return view('dashboard', compact('dispositivos'));
    }
}
